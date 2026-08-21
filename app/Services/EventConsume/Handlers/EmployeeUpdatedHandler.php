<?php

namespace App\Services\EventConsume\Handlers;

use App\Models\Employee;
use App\Services\EventConsume\EventHandlerInterface;
use Illuminate\Support\Facades\DB;

class EmployeeUpdatedHandler implements EventHandlerInterface
{
    use EmployeeHandlerHelpers;

    public function handle(array $event): void
    {
        $emp = data_get($event, 'data.employee') ?? data_get($event, 'employee');

        $id = (int) (
            data_get($event, 'data.employee_id') ??
            data_get($event, 'employee_id')       ??
            data_get($emp,   'id')                ??
            0
        );

        if ($id <= 0) {
            throw new \Exception('EmployeeUpdatedHandler: missing/invalid employee id');
        }

        $employee = Employee::query()->find($id);
        if (!$employee) {
            throw new \Exception("EmployeeUpdatedHandler: employee {$id} not synced yet");
        }

        $changed = data_get($event, 'data.changed_fields', []);
        $update  = [];

        foreach (['first_name', 'last_name'] as $field) {
            $val = data_get($changed, "{$field}.to") ?? data_get($emp, $field);
            if ($val !== null) {
                $update[$field] = trim((string) $val);
            }
        }

        // middle_name must handle explicit null (clearing the field)
        if (array_key_exists('middle_name', is_array($changed) ? $changed : [])) {
            $update['middle_name'] = data_get($changed, 'middle_name.to');
        } elseif ($emp !== null && array_key_exists('middle_name', $emp)) {
            $update['middle_name'] = $emp['middle_name'] ?: null;
        }

        // Resolve new store if stores array changed
        $storesTo = data_get($changed, 'stores.to') ?? data_get($emp, 'stores');
        if (is_array($storesTo) && count($storesTo) > 0) {
            $update['store_id'] = $this->resolveStoreId(['stores' => $storesTo], $event);
        }

        // Resolve active if status_histories changed
        $histTo = data_get($changed, 'status_histories.to') ?? data_get($emp, 'status_histories');
        if (is_array($histTo) && count($histTo) > 0) {
            $update['active'] = $this->resolveActive(['status_histories' => $histTo]);
        }

        if (empty($update)) {
            return;
        }

        DB::transaction(function () use ($id, $update) {
            Employee::query()->where('id', $id)->update($update);
        });
    }
}
