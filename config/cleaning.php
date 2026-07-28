<?php

return [
    /*
    | Role names (as they arrive from pizzasys in user_store_roles.role_name) that
    | identify a Store Manager — used to decide who gets notified when a task is
    | created for a store. Adjust to match the real auth role strings.
    */
    'manager_roles' => array_filter(array_map('trim', explode(
        ',',
        env('CLEANING_MANAGER_ROLES', 'store_manager,qa_store_manager')
    ))),
];
