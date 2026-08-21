<?php

namespace App\Services\Cleaning;

use App\Models\UserStoreRole;

/**
 * Given store ids, returns the user ids of the Store Managers who should be
 * notified. A row with store_id = NULL means "all stores" and always matches.
 */
class ManagerRecipientResolver
{
    /**
     * @param  int[]  $storeIds
     * @return int[]  distinct manager user ids
     */
    public function forStores(array $storeIds): array
    {
        $roles = (array) config('cleaning.manager_roles', []);
        if (empty($roles) || empty($storeIds)) {
            return [];
        }

        return UserStoreRole::query()
            ->where('active', true)
            ->whereIn('role_name', $roles)
            ->where(function ($q) use ($storeIds) {
                $q->whereNull('store_id')            // "all stores"
                  ->orWhereIn('store_id', $storeIds);
            })
            ->pluck('user_id')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
    }
}
