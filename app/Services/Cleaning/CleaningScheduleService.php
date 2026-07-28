<?php

namespace App\Services\Cleaning;

use App\Models\CleaningTask;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Pure schedule math — no database writes. Given a task's recurrence rule,
 * answers "is it due on this date?" and "which period does this date fall in?".
 * Occurrences are never stored; this is evaluated on read.
 */
class CleaningScheduleService
{
    public function isDueOnDate(CleaningTask $task, CarbonInterface $date): bool
    {
        $d = $date->copy()->startOfDay();
        $start = $task->starts_at->copy()->startOfDay();

        if ($d->lt($start)) {
            return false;
        }
        if ($task->ends_at && $d->gt($task->ends_at->copy()->startOfDay())) {
            return false;
        }

        $n = max(1, (int) $task->interval);

        return match ($task->frequency) {
            'daily'   => ((int) $start->diffInDays($d)) % $n === 0,
            'weekly'  => $this->weeklyDue($task, $d, $start, $n),
            'monthly' => $this->monthsDiff($start, $d) % $n === 0,
            'hourly'  => true, // due every day within range (hour-blocks are a later refinement)
            default   => false,
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}  [period_start, period_end] (dates)
     */
    public function periodBounds(CleaningTask $task, CarbonInterface $date): array
    {
        $d = $date->copy()->startOfDay();

        return match ($task->frequency) {
            'weekly'  => [$d->copy()->startOfWeek(Carbon::MONDAY), $d->copy()->startOfWeek(Carbon::MONDAY)->addDays(6)],
            'monthly' => [$d->copy()->startOfMonth(), $d->copy()->endOfMonth()],
            default   => [$d->copy(), $d->copy()], // daily / hourly = that single day
        };
    }

    private function weeklyDue(CleaningTask $task, Carbon $d, Carbon $start, int $n): bool
    {
        $ws = $start->copy()->startOfWeek(Carbon::MONDAY);
        $wd = $d->copy()->startOfWeek(Carbon::MONDAY);
        $weeks = intdiv((int) $ws->diffInDays($wd), 7);

        if ($weeks % $n !== 0) {
            return false;
        }

        $days = $task->week_days ?? [];
        if (!empty($days)) {
            return in_array((int) $d->isoWeekday(), array_map('intval', $days), true);
        }

        return true; // no specific weekday → due any day that week
    }

    private function monthsDiff(Carbon $start, Carbon $d): int
    {
        return ($d->year * 12 + $d->month) - ($start->year * 12 + $start->month);
    }
}
