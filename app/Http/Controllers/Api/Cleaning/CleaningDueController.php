<?php

namespace App\Http\Controllers\Api\Cleaning;

use App\Http\Controllers\Controller;
use App\Models\CleaningTask;
use App\Models\Employee;
use App\Models\Store;
use App\Services\Cleaning\CleaningCompletionService;
use App\Services\Cleaning\CleaningDueService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CleaningDueController extends Controller
{
    public function __construct(
        private readonly CleaningDueService $due,
        private readonly CleaningCompletionService $completion,
    ) {
    }

    /** GET /cleaning/stores/{store_id}/dates/{date}/due */
    public function dueOnDate(Request $request, string $store_id, string $date): JsonResponse
    {
        $storeId = $this->resolveStore($request, $store_id);

        return response()->json([
            'store_id'  => $storeId,
            'date'      => $date,
            'items'     => $this->due->dueForStoreOnDate($storeId, Carbon::parse($date)),
            'employees' => $this->employees($storeId),
        ]);
    }

    /** GET /cleaning/stores/{store_id}/due-range?from=&to= */
    public function dueRange(Request $request, string $store_id): JsonResponse
    {
        $storeId = $this->resolveStore($request, $store_id);
        $v = $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        return response()->json([
            'store_id' => $storeId,
            'from'     => $v['from'],
            'to'       => $v['to'],
            'days'     => $this->due->dueRange($storeId, Carbon::parse($v['from']), Carbon::parse($v['to'])),
        ]);
    }

    /** POST /cleaning/stores/{store_id}/tasks/{task}/complete */
    public function complete(Request $request, string $store_id, CleaningTask $task): JsonResponse
    {
        $storeId = $this->resolveStore($request, $store_id);
        $this->assertTaskInStore($task, $storeId);

        $v = $request->validate([
            'date'           => ['nullable', 'date'],
            'employee_ids'   => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'note'           => ['nullable', 'string'],
            'photos'         => ['nullable', 'array'],
            'photos.*'       => ['file', 'image', 'max:10240'],
        ]);

        $completion = $this->completion->complete(
            $task,
            $storeId,
            isset($v['date']) ? Carbon::parse($v['date']) : now(),
            $v['employee_ids'],
            $v['note'] ?? null,
            $request->file('photos') ?? [],
            $request->user()?->id,
        );

        return response()->json(['data' => $completion]);
    }

    /** POST /cleaning/stores/{store_id}/tasks/{task}/uncomplete */
    public function uncomplete(Request $request, string $store_id, CleaningTask $task): JsonResponse
    {
        $storeId = $this->resolveStore($request, $store_id);
        $this->assertTaskInStore($task, $storeId);

        $v = $request->validate(['date' => ['nullable', 'date']]);
        $this->completion->uncomplete($task, $storeId, isset($v['date']) ? Carbon::parse($v['date']) : now());

        return response()->json(['uncompleted' => true]);
    }

    /** GET /cleaning/stores/{store_id}/tasks/{task}/history?from=&to= */
    public function history(Request $request, string $store_id, CleaningTask $task): JsonResponse
    {
        $storeId = $this->resolveStore($request, $store_id);
        $this->assertTaskInStore($task, $storeId);

        $to = $request->query('to') ? Carbon::parse($request->query('to')) : now();
        // Default the start to the task's anchor so all past periods are covered.
        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))
            : ($task->starts_at ? $task->starts_at->copy() : $to->copy()->subDays(60));

        return response()->json([
            'data' => $this->due->historyForTask($task, $storeId, $from, $to),
        ]);
    }

    // ── helpers ──

    private function resolveStore(Request $request, string $store_id): int
    {
        $realStoreId = Store::idFromNumber($store_id);
        abort_if($realStoreId === null, 404, 'Store not found.');

        $user = $request->user();
        if ($user && !$user->canAccessStoreId($realStoreId)) {
            abort(403, 'You cannot access this store.');
        }

        return $realStoreId;
    }

    private function assertTaskInStore(CleaningTask $task, int $storeId): void
    {
        abort_unless($task->stores()->where('stores.id', $storeId)->exists(), 404, 'Task not assigned to this store.');
    }

    private function employees(int $storeId): \Illuminate\Support\Collection
    {
        return Employee::query()
            ->where('store_id', $storeId)
            ->where('active', true)
            ->get()
            ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->full_name])
            ->values();
    }
}
