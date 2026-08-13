<?php

namespace App\Services\Cleaning;

use App\Models\CleaningCompletion;
use App\Models\CleaningTask;
use App\Services\Nats\EventFactory;
use App\Services\Nats\OutboxService;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Completing / un-completing a task for a store on a date. Writes ONE completion
 * row for the period that date falls in. Holds the two rules:
 *  - at least one employee,
 *  - a photo is required unless the task is hourly (photo_required flag).
 */
class CleaningCompletionService
{
    public function __construct(
        private readonly EventFactory $events,
        private readonly OutboxService $outbox,
        private readonly CleaningScheduleService $schedule,
    ) {
    }

    /**
     * @param  int[]  $employeeIds
     * @param  UploadedFile[]  $photos
     */
    public function complete(
        CleaningTask $task,
        int $storeId,
        CarbonInterface $date,
        array $employeeIds,
        ?string $note,
        array $photos,
        ?int $completedByUserId
    ): CleaningCompletion {
        // Rule 1: at least one employee.
        if (empty($employeeIds)) {
            throw ValidationException::withMessages([
                'employee_ids' => 'Select at least one employee who did the task.',
            ]);
        }

        [$periodStart, $periodEnd] = $this->schedule->periodBounds($task, $date);

        $existing = CleaningCompletion::query()
            ->where('cleaning_task_id', $task->id)
            ->where('store_id', $storeId)
            ->whereDate('period_start', $periodStart->toDateString())
            ->first();

        // Rule 2: photo required for daily/weekly/monthly, optional for hourly.
        $hasPhoto = !empty($photos) || ($existing && $existing->attachments()->exists());
        if ($task->photo_required && !$hasPhoto) {
            throw ValidationException::withMessages([
                'photos' => 'At least one photo is required for this task before it can be marked done.',
            ]);
        }

        return DB::transaction(function () use ($task, $storeId, $periodStart, $periodEnd, $employeeIds, $note, $photos, $completedByUserId, $existing) {
            $completion = $existing ?: new CleaningCompletion([
                'cleaning_task_id' => $task->id,
                'store_id'         => $storeId,
                'period_start'     => $periodStart->toDateString(),
                'period_end'       => $periodEnd->toDateString(),
            ]);
            $completion->period_end = $periodEnd->toDateString();
            $completion->completed_at = now();
            $completion->completed_by_user_id = $completedByUserId;
            $completion->note = $note;
            $completion->save();

            foreach ($photos as $photo) {
                $completion->attachments()->create(['path' => $photo->store('cleaning', 'public')]);
            }

            $completion->employees()->sync($employeeIds);


            // Ask NotificationsPizza to actually deliver — it resolves the
            // QA auditors itself from role + store. A completed task needs to
            // be verified by an auditor, not the store manager. Channels: 'web' = in-app.
            $envelope = $this->events->make('notifications.v1.notification.role.send', [
                'channels' => ['web'],
                'roles'    => array_values((array) config('cleaning.auditor_roles', ['QA Auditor'])),
                'stores'   => [$storeId],
                'payload'  => [
                    'type'       => 'cleaning_task_completed',
                    'title'      => 'Task completed',
                    'body'       => "\"{$task->name}\" has been marked as done.",
                    'action_url' => "/cleaning/tasks/{$task->id}?store_id={$storeId}",
                ],
            ]);
            $this->outbox->record('notifications.v1.notification.role.send', $envelope);

            return $completion->fresh(['employees', 'attachments', 'task']);
        });
    }

    /**
     * Undo: remove the completion for the period that date falls in.
     */
    public function uncomplete(CleaningTask $task, int $storeId, CarbonInterface $date): void
    {
        [$periodStart] = $this->schedule->periodBounds($task, $date);

        CleaningCompletion::query()
            ->where('cleaning_task_id', $task->id)
            ->where('store_id', $storeId)
            ->whereDate('period_start', $periodStart->toDateString())
            ->get()
            ->each(fn (CleaningCompletion $c) => $c->delete());
    }
}
