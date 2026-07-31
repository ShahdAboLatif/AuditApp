<?php

namespace App\Services\Cleaning;

use App\Models\CleaningCompletion;
use App\Models\CleaningTask;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Computes "what's due for a store on a date" + each item's status
 * (done / pending / overdue) by evaluating the rule and checking completions.
 * Nothing here is stored — it's always current.
 */
class CleaningDueService
{
    public function __construct(private readonly CleaningScheduleService $schedule)
    {
    }

    public function dueForStoreOnDate(int $storeId, CarbonInterface $date): Collection
    {
        $date = $date->copy()->startOfDay();

        $tasks = CleaningTask::query()
            ->whereHas('stores', fn ($q) => $q->where('stores.id', $storeId))
            ->get();

        return $tasks
            ->filter(fn (CleaningTask $t) => $this->schedule->isDueOnDate($t, $date))
            ->map(fn (CleaningTask $t) => $this->itemFor($t, $storeId, $date))
            ->values();
    }

    /**
     * History for one task+store: every real completion (always shown) plus
     * derived "overdue" periods within [from, to]. Ordered newest first.
     */
    public function historyForTask(CleaningTask $task, int $storeId, CarbonInterface $from, CarbonInterface $to): array
    {
        $byPeriod = [];

        // 1) real completions — always included, regardless of date
        $completions = CleaningCompletion::query()
            ->with(['employees:id,first_name,middle_name,last_name', 'attachments:id,cleaning_completion_id,path'])
            ->where('cleaning_task_id', $task->id)
            ->where('store_id', $storeId)
            ->get();

        foreach ($completions as $c) {
            $key = $c->period_start->toDateString();
            $byPeriod[$key] = [
                'task_id'   => $task->id,
                'label'     => $task->name,
                'frequency' => $task->frequency,
                'period'    => [$key, $c->period_end->toDateString()],
                'status'    => 'done',
                'done_at'   => $c->completed_at?->toIso8601String(),
                'done_by'   => $c->employees->map(fn ($e) => trim("{$e->first_name} {$e->last_name}"))->values(),
                'has_photo' => $c->attachments->isNotEmpty(),
                'photos'    => $this->photoUrls($c),
                'note'      => $c->note,
            ];
        }

        // 2) derive missed (overdue) periods across the range that have no completion
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            if (!$this->schedule->isDueOnDate($task, $d)) {
                continue;
            }
            [$ps, $pe] = $this->schedule->periodBounds($task, $d);
            $key = $ps->toDateString();
            if (isset($byPeriod[$key])) {
                continue;
            }
            if ($pe->copy()->endOfDay()->lt(now())) {
                $byPeriod[$key] = [
                    'task_id'   => $task->id,
                    'label'     => $task->name,
                    'frequency' => $task->frequency,
                    'period'    => [$key, $pe->toDateString()],
                    'status'    => 'overdue',
                    'done_at'   => null,
                    'done_by'   => [],
                    'has_photo' => false,
                    'photos'    => [],
                    'note'      => null,
                ];
            }
        }

        return collect($byPeriod)
            ->sortByDesc(fn ($i) => $i['period'][0])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{date:string, items:array}>
     */
    public function dueRange(int $storeId, CarbonInterface $from, CarbonInterface $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $days = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $days[] = [
                'date'  => $d->toDateString(),
                'items' => $this->dueForStoreOnDate($storeId, $d)->all(),
            ];
        }

        return $days;
    }

    private function itemFor(CleaningTask $task, int $storeId, Carbon $date): array
    {
        [$periodStart, $periodEnd] = $this->schedule->periodBounds($task, $date);

        $completion = CleaningCompletion::query()
            ->with(['employees:id,first_name,middle_name,last_name', 'attachments:id,cleaning_completion_id,path'])
            ->where('cleaning_task_id', $task->id)
            ->where('store_id', $storeId)
            ->whereDate('period_start', $periodStart->toDateString())
            ->first();

        $status = $this->status($completion, $periodEnd);

        return [
            'task_id'        => $task->id,
            'label'          => $task->name,
            'description'    => $task->description,
            'frequency'      => $task->frequency,
            'weight'         => $task->weight,
            'photo_required' => (bool) $task->photo_required,
            'period'         => [$periodStart->toDateString(), $periodEnd->toDateString()],
            'status'         => $status,
            'completion_id'  => $completion?->id,
            'done_at'        => $completion?->completed_at?->toIso8601String(),
            'done_by'        => $completion
                ? $completion->employees->map(fn ($e) => trim("{$e->first_name} {$e->last_name}"))->values()
                : [],
            'has_photo'      => $completion ? $completion->attachments->isNotEmpty() : false,
            'photos'         => $completion ? $this->photoUrls($completion) : [],
            'note'           => $completion?->note,
        ];
    }

    /**
     * Relative public URLs for a completion's photos. Relative (not absolute) so
     * the browser resolves them against the serving origin (works on any port /
     * host without depending on APP_URL).
     */
    private function photoUrls(CleaningCompletion $c): array
    {
        return $c->attachments
            ->map(fn ($a) => '/storage/' . ltrim($a->path, '/'))
            ->values()
            ->all();
    }

    private function status(?CleaningCompletion $completion, Carbon $periodEnd): string
    {
        if ($completion) {
            return 'done';
        }

        // overdue = the period's last day is already over and nothing was logged
        if ($periodEnd->copy()->endOfDay()->lt(now())) {
            return 'overdue';
        }

        return 'pending';
    }
}
