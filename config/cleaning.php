<?php

return [
    /*
    | Role names (as they arrive from pizzasys in user_store_roles.role_name) that
    | identify a Store Manager — used to decide who gets notified when a task is
    | created for a store. Adjust to match the real auth role strings.
    */
    'manager_roles' => array_filter(array_map('trim', explode(
        ',',
        env('CLEANING_MANAGER_ROLES', 'Store Manager')
    ))),

    /*
    | Role names that identify a QA Auditor — used to decide who gets notified
    | when a task is marked as completed (they need to come verify it).
    */
    'auditor_roles' => array_filter(array_map('trim', explode(
        ',',
        env('CLEANING_AUDITOR_ROLES', 'QA Auditor')
    ))),
];
