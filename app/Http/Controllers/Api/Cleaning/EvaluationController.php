<?php

namespace App\Http\Controllers\Api\Cleaning;

use App\Http\Controllers\Controller;
use App\Models\CleaningTask;
use App\Models\Evaluation;
use App\Models\EvaluationChartVerdict;
use App\Models\EvaluationItemValue;
use App\Models\Store;
use App\Services\Cleaning\EvaluationService;
use App\Services\Cleaning\ManagerRecipientResolver;
use App\Services\Nats\OutboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EvaluationController extends Controller
{
    public function __construct(
        private readonly EvaluationService $evaluations,
        private readonly OutboxService $outbox,
        private readonly ManagerRecipientResolver $recipients,
    ) {
    }

    /**
     * The full grid (all stores the caller may see).
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period_type' => ['nullable', Rule::in(['date', 'week'])],
            'period_key'  => ['required', 'string', 'max:20'],
        ]);

        $grid = $this->evaluations->buildGrid(
            $data['period_type'] ?? 'week',
            $data['period_key'],
            $this->allowedStoreIds($request),
        );

        return response()->json($grid);
    }

    /**
     * Set one cell — an item value (Group A) or a chart verdict (Group B).
     */
    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_id'    => ['required', 'integer', 'exists:stores,id'],
            'period_type' => ['nullable', Rule::in(['date', 'week'])],
            'period_key'  => ['required', 'string', 'max:20'],
            'kind'        => ['required', Rule::in(['item', 'chart'])],
            // item:
            'inspection_item_id' => ['required_if:kind,item', 'integer', 'exists:inspection_items,id'],
            'value'              => ['required_if:kind,item', Rule::in(['pass', 'fail', 'auto_fail', 'empty'])],
            // chart:
            'cleaning_task_id' => ['required_if:kind,chart', 'integer', 'exists:cleaning_tasks,id'],
            'verdict'          => ['required_if:kind,chart', Rule::in(['pass', 'fail', 'auto_fail'])],
        ]);

        $this->assertCanAccess($request, (int) $data['store_id']);
        $periodType = $data['period_type'] ?? 'week';

        DB::transaction(function () use ($data, $periodType) {
            $evaluation = Evaluation::firstOrCreate(
                ['store_id' => $data['store_id'], 'period_type' => $periodType, 'period_key' => $data['period_key']],
                ['created_by' => Auth::id()],
            );

            if ($data['kind'] === 'item') {
                EvaluationItemValue::updateOrCreate(
                    ['evaluation_id' => $evaluation->id, 'inspection_item_id' => $data['inspection_item_id']],
                    ['value' => $data['value']],
                );
            } else {
                $task = CleaningTask::findOrFail($data['cleaning_task_id']);
                EvaluationChartVerdict::updateOrCreate(
                    ['evaluation_id' => $evaluation->id, 'cleaning_task_id' => $task->id],
                    ['frequency' => $task->frequency, 'weight' => (int) ($task->weight ?? 0), 'verdict' => $data['verdict']],
                );
            }
        });

        // Return the recalculated single-store row.
        $grid = $this->evaluations->buildGrid($periodType, $data['period_key'], [(int) $data['store_id']]);

        return response()->json(['data' => $grid['rows']->first()]);
    }

    /**
     * Finalize an evaluation → notify the store (Step 4.4, optional).
     */
    public function finalize(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_id'    => ['required', 'integer', 'exists:stores,id'],
            'period_type' => ['nullable', Rule::in(['date', 'week'])],
            'period_key'  => ['required', 'string', 'max:20'],
        ]);

        $this->assertCanAccess($request, (int) $data['store_id']);
        $periodType = $data['period_type'] ?? 'week';

        $row = $this->evaluations->buildGrid($periodType, $data['period_key'], [(int) $data['store_id']])['rows']->first();

        $this->outbox->record('qa.v1.cleaning.evaluation.completed', [
            'store_id'        => (int) $data['store_id'],
            'period_type'     => $periodType,
            'period_key'      => $data['period_key'],
            'item_score'      => $row['item_score'] ?? null,
            'chart_score'     => $row['chart_score'] ?? null,
            'notify_user_ids' => $this->recipients->forStores([(int) $data['store_id']]),
            'message'         => 'Your store evaluation is ready.',
        ]);

        return response()->json(['data' => $row]);
    }

    /** @return int[] */
    private function allowedStoreIds(Request $request): array
    {
        $user = $request->user();
        return $user ? $user->allowedStoreIdsCached() : Store::query()->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    private function assertCanAccess(Request $request, int $storeId): void
    {
        $user = $request->user();
        if ($user && !$user->canAccessStoreId($storeId)) {
            abort(403, 'You cannot access this store.');
        }
    }
}
