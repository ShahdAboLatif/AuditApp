<?php

namespace App\Services\Cleaning;

use App\Models\CleaningTask;
use App\Models\Evaluation;
use App\Models\InspectionItem;
use App\Models\Store;

/**
 * Builds the evaluation grid (rows = stores). Single source of truth used by
 * BOTH the grid endpoint and the report endpoints, so the numbers always match.
 */
class EvaluationService
{
    public function __construct(private readonly CleaningScoringService $scoring)
    {
    }

    /**
     * @param  int[]  $storeIds  stores the caller is allowed to see
     */
    public function buildGrid(string $periodType, string $periodKey, array $storeIds): array
    {
        $items = InspectionItem::query()
            ->where('active', true)
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'name']);

        $stores = Store::query()->whereIn('id', $storeIds)->orderBy('id')->get(['id', 'store']);

        $rows = $stores->map(function (Store $store) use ($items, $periodType, $periodKey) {
            $evaluation = Evaluation::with(['itemValues', 'chartVerdicts'])
                ->where('store_id', $store->id)
                ->where('period_type', $periodType)
                ->where('period_key', $periodKey)
                ->first();

            // --- Group A: item cells ---
            $valueByItem = $evaluation
                ? $evaluation->itemValues->keyBy('inspection_item_id')
                : collect();

            $itemValues = [];
            foreach ($items as $item) {
                $itemValues[$item->name] = $valueByItem->get($item->id)->value ?? 'empty';
            }
            $itemScore = $this->scoring->itemScore(array_values($itemValues));

            // --- Group B: chart tasks grouped by frequency ---
            $verdictByTask = $evaluation
                ? $evaluation->chartVerdicts->keyBy('cleaning_task_id')
                : collect();

            $tasks = CleaningTask::query()
                ->whereHas('stores', fn ($q) => $q->where('stores.id', $store->id))
                ->get(['id', 'name', 'frequency', 'weight']);

            $chart = ['daily' => [], 'weekly' => [], 'monthly' => [], 'hourly' => []];
            $scoreInput = [];
            foreach ($tasks as $task) {
                $v = $verdictByTask->get($task->id);
                $weight = $v ? (int) $v->weight : (int) ($task->weight ?? 0);
                $verdict = $v->verdict ?? null;   // null = not graded yet
                $chart[$task->frequency][] = [
                    'task_id' => $task->id,
                    'name'    => $task->name,
                    'weight'  => $weight,
                    'verdict' => $verdict,
                ];
                $scoreInput[] = ['weight' => $weight, 'verdict' => $verdict];
            }
            $chartScore = $this->scoring->chartScore($scoreInput);

            return [
                'store_id'    => $store->id,
                'store'       => $store->store,
                'item_values' => $itemValues,
                'item_score'  => $itemScore,
                'chart'       => $chart,
                'chart_score' => $chartScore['pct'],
                'weight_lost' => $chartScore['lost'],
            ];
        })->values();

        return [
            'period_type' => $periodType,
            'period_key'  => $periodKey,
            'items'       => $items,
            'rows'        => $rows,
        ];
    }
}
