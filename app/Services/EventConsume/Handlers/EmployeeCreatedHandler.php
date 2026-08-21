<?php

namespace App\Services\EventConsume\Handlers;

use App\Models\Employee;
use App\Services\EventConsume\EventHandlerInterface;
use Illuminate\Support\Facades\DB;

class EmployeeCreatedHandler implements EventHandlerInterface
{
    use EmployeeHandlerHelpers;

    public function handle(array $event): void
    {
        $emp = data_get($event, 'data.employee')
            ?? data_get($event, 'employee')
            ?? throw new \Exception('EmployeeCreatedHandler: employee payload not found');

        $id = (int) data_get($emp, 'id');
        if ($id <= 0) {
            throw new \Exception('EmployeeCreatedHandler: missing/invalid id');
        }

        $firstName = trim((string) data_get($emp, 'first_name', ''));
        $lastName  = trim((string) data_get($emp, 'last_name', ''));
        if ($firstName === '' || $lastName === '') {
            throw new \Exception('EmployeeCreatedHandler: first_name and last_name are required');
        }

        $middleName = data_get($emp, 'middle_name');
        if ($middleName !== null) {
            $middleName = trim((string) $middleName) ?: null;
        }

        $storeId = $this->resolveStoreId($emp, $event);
        $active  = $this->resolveActive($emp);

        DB::transaction(function () use ($id, $firstName, $middleName, $lastName, $storeId, $active) {
            Employee::query()->updateOrCreate(
                ['id' => $id],
                [
                    'first_name'  => $firstName,
                    'middle_name' => $middleName,
                    'last_name'   => $lastName,
                    'store_id'    => $storeId,
                    'active'      => $active,
                ]
            );
        });
    }
}
