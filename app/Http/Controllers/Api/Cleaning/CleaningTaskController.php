<?php

namespace App\Http\Controllers\Api\Cleaning;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cleaning\StoreCleaningTaskRequest;
use App\Http\Requests\Cleaning\UpdateCleaningTaskRequest;
use App\Models\CleaningTask;
use App\Services\Cleaning\ManagerRecipientResolver;
use App\Services\Nats\OutboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CleaningTaskController extends Controller
{
    public function __construct(
        private readonly OutboxService $outbox,
        private readonly ManagerRecipientResolver $recipients,
    ) {
    }

    /**
     * List task definitions (auditor / super admin view).
     */
    public function index(Request $request): JsonResponse
    {
        $tasks = CleaningTask::with('stores:id,store')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $tasks]);
    }

    public function show(CleaningTask $task): JsonResponse
    {
        return response()->json(['data' => $task->load('stores:id,store')]);
    }

    /**
     * Create a task + its recurrence RULE, and assign stores. No occurrence rows
     * are generated — due/status is computed on read. photo_required is DERIVED
     * from frequency, never taken from the client.
     */
    public function store(StoreCleaningTaskRequest $request): JsonResponse
    {
        $data = $request->validated();

        $task = DB::transaction(function () use ($data) {
            $task = CleaningTask::create([
                'name'           => $data['name'],
                'description'    => $data['description'] ?? null,
                'weight'         => $data['weight'] ?? null,
                'photo_required' => CleaningTask::photoRequiredFor($data['frequency']),
                'frequency'      => $data['frequency'],
                'interval'       => $data['interval'] ?? 1,
                'week_days'      => $data['week_days'] ?? null,
                'interval_hours' => $data['frequency'] === 'hourly' ? ($data['interval_hours'] ?? null) : null,
                'starts_at'      => $data['starts_at'],
                'ends_at'        => $data['ends_at'] ?? null,
                'due_time'       => $data['due_time'] ?? null,
                'created_by'     => Auth::id(),
            ]);

            $task->stores()->sync($data['store_ids']);

            // 1) QA domain event (audit trail / other QA consumers).
            $notifyUserIds = $this->recipients->forStores($data['store_ids']);
            $this->outbox->record('qa.v1.cleaning.task.created', [
                'task_id'         => $task->id,
                'task_name'       => $task->name,
                'frequency'       => $task->frequency,
                'store_ids'       => array_values($data['store_ids']),
                'notify_user_ids' => $notifyUserIds,
                'message'         => "New task \"{$task->name}\" assigned to your store.",
            ]);

            // 2) Ask NotificationsPizza to actually deliver — it resolves the
            //    store managers itself from role + store. Channels: 'web' = in-app.
            $this->outbox->record('notifications.v1.notification.role.send', [
                'channels' => ['web'],
                'roles'    => array_values((array) config('cleaning.manager_roles', ['store_manager'])),
                'stores'   => array_values($data['store_ids']),
                'payload'  => [
                    'title' => 'New cleaning task',
                    'body'  => "\"{$task->name}\" assigned to your store.",
                ],
            ]);

            return $task;
        });

        return response()->json(['data' => $task->load('stores:id,store')], 201);
    }

    /**
     * Update a task's rule / name / stores. Only provided fields change.
     * photo_required is re-derived from frequency (never taken from the client).
     * No occurrences to regenerate — status is computed on read.
     */
    public function update(UpdateCleaningTaskRequest $request, CleaningTask $task): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($task, $data) {
            $fields = collect($data)->except('store_ids')->all();

            if (array_key_exists('frequency', $fields)) {
                $fields['photo_required'] = CleaningTask::photoRequiredFor($fields['frequency']);
                $fields['interval_hours'] = $fields['frequency'] === 'hourly'
                    ? ($data['interval_hours'] ?? $task->interval_hours)
                    : null;
            }

            if (!empty($fields)) {
                $task->update($fields);
            }

            if (array_key_exists('store_ids', $data)) {
                $task->stores()->sync($data['store_ids']);
            }
        });

        return response()->json(['data' => $task->fresh()->load('stores:id,store')]);
    }

    /**
     * Soft-delete a task: it disappears from the Due list, but its completions
     * and history are preserved (and past evaluations keep working).
     */
    public function destroy(CleaningTask $task): JsonResponse
    {
        $task->delete();

        return response()->json(['deleted' => true]);
    }
}
