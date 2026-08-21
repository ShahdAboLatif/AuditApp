<?php

namespace App\Services\EventConsume\Handlers;

use App\Models\Store;

trait EmployeeHandlerHelpers
{
    /**
     * Resolve the internal integer stores.id for an employee event.
     *
     * The event carries the human-facing store_number. We look up the matching
     * store and return its integer PK — that value is what employees.store_id
     * must contain.
     *
     * NOTE (AuditApp difference): the inventory project looks up
     * Store::where('store_number', …); AuditApp's stores table keeps that human
     * key in the `store` column (filled by StoreCreatedHandler), so we match on
     * `store` here. Everything else mirrors inventory.
     *
     * Throws if the store isn't synced yet (the inbox pattern will retry).
     */
    private function resolveStoreId(array $emp, array $event): int
    {
        $storeNumber = null;

        $stores = data_get($emp, 'stores', []);
        if (is_array($stores) && count($stores) > 0) {
            $latest = $this->latestEntry($stores);
            // Producers may nest the store object under `store` or send store_number flat.
            $storeNumber = data_get($latest, 'store.store_number')
                        ?? data_get($latest, 'store_number');
        }

        if ($storeNumber === null || trim((string) $storeNumber) === '') {
            $storeNumber = data_get($event, 'data.store_number')
                        ?? data_get($event, 'store_number');
        }

        $storeNumber = trim((string) $storeNumber);
        if ($storeNumber === '') {
            throw new \Exception('EmployeeHandler: cannot resolve store_number from payload');
        }

        // AuditApp keeps the human store key in `store` (not `store_number`).
        $storeId = Store::where('store', $storeNumber)->value('id');

        if ($storeId === null) {
            // The store event hasn't been consumed yet. Throw so the inbox retries.
            throw new \Exception("EmployeeHandler: store with store_number '{$storeNumber}' not synced yet");
        }

        return (int) $storeId;
    }

    private function resolveActive(array $emp): bool
    {
        $histories = data_get($emp, 'status_histories', []);
        if (!is_array($histories) || count($histories) === 0) {
            return true;
        }
        $latest = $this->latestEntry($histories);
        $status = strtolower((string) data_get($latest, 'status', ''));
        return in_array($status, ['hired', 'rehired', 'oje'], true);
    }

    /**
     * Pick the newest entry by timestamp (effective_date > created_at > updated_at).
     * Uses real Unix timestamps so string sort quirks (e.g. missing fields) never
     * bury a genuinely newer row.
     */
    private function latestEntry(array $entries): array
    {
        $latest = null;
        $latestTs = null;

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $ts = $this->entryTimestamp($entry);

            if ($latest === null || ($ts !== null && ($latestTs === null || $ts > $latestTs))) {
                $latest = $entry;
                $latestTs = $ts;
            }
        }

        return $latest ?? [];
    }

    private function entryTimestamp(array $entry): ?int
    {
        foreach (['effective_date', 'created_at', 'updated_at'] as $field) {
            $value = data_get($entry, $field);
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $ts = strtotime($value);
            if ($ts !== false) {
                return $ts;
            }
        }

        return null;
    }
}
