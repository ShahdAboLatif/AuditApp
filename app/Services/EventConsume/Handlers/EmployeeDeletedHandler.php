<?php

namespace App\Services\EventConsume\Handlers;

use App\Models\Employee;
use App\Services\EventConsume\EventHandlerInterface;
use Illuminate\Support\Facades\DB;

class EmployeeDeletedHandler implements EventHandlerInterface
{
    public function handle(array $event): void
    {
        $id = (int) (
            data_get($event, 'data.employee_id') ??
            data_get($event, 'employee_id')       ??
            data_get($event, 'data.employee.id')  ??
            data_get($event, 'employee.id')       ??
            0
        );

        if ($id <= 0) {
            throw new \Exception('EmployeeDeletedHandler: missing/invalid employee id');
        }

        DB::transaction(function () use ($id) {
            Employee::query()->where('id', $id)->delete();
        });
    }
}
