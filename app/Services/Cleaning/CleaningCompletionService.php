<?php

namespace App\Services\Cleaning;

use App\Models\CleaningCompletion;
use App\Models\CleaningTask;
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
        private readonly OutboxService $outbox,
        private readonly CleaningScheduleService $schedule,
    ) {
    }

    /**
     * @param  int[]  $employeeIds
     */
    public function complete(
        CleaningTask $task,
        int $storeId,
        CarbonInterface $date,
        array $employeeIds,
        ?string $note,
        ?UploadedFile $photo,
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
        $hasPhoto = $photo !== null || ($existing && $existing->attachments()->exists());
        if ($task->photo_required && !$hasPhoto) {
            throw ValidationException::withMessages([
                'photo' => 'A photo is required for this task before it can be marked done.',
            ]);
        }

        return DB::transaction(function () use ($task, $storeId, $periodStart, $periodEnd, $employeeIds, $note, $photo, $completedByUserId, $existing) {
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

            if ($photo !== null) {
                $completion->attachments()->create(['path' => $photo->store('cleaning', 'public')]);
            }

            $completion->employees()->sync($employeeIds);

            $this->outbox->record('qa.v1.cleaning.task.completed', [
                'completion_id' => $completion->id,
                'task_id'       => $task->id,
                'task_name'     => $task->name,
                'store_id'      => $storeId,
                'period'        => [$periodStart->toDateString(), $periodEnd->toDateString()],
                'employee_ids'  => array_values($employeeIds),
                'has_photo'     => $completion->attachments()->exists(),
                'completed_at'  => $completion->completed_at?->toIso8601String(),
            ]);

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
