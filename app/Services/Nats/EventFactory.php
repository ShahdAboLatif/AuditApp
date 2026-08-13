<?php

namespace App\Services\Nats;

use Illuminate\Support\Str;

/**
 * Builds a CloudEvent envelope. Does NOT store or publish anything —
 * OutboxService::record() takes the built envelope and persists it.
 *
 * Example:
 *   $envelope = $events->make('notifications.v1.notification.role.send', [
 *       'channels' => ['web'], 'roles' => [...], 'stores' => [...], 'payload' => [...],
 *   ]);
 *   $outbox->record('notifications.v1.notification.role.send', $envelope);
 */
class EventFactory
{
    public function make(string $type, array $data, array $metaOverrides = []): array
    {
        $type = $this->applyEnvironmentPrefix($type);

        return [
            'specversion'     => '1.0',
            'id'              => (string) Str::ulid(),
            'type'            => $type,
            'source'          => 'qa-system',
            'subject'         => $type,
            'time'            => now()->utc()->toIso8601String(),
            'datacontenttype' => 'application/json',
            'data'            => $data,
            'meta'            => $metaOverrides,
        ];
    }

    /**
     * In dev mode, qa.v1.* / notifications.v1.* become their *.testing.v1.*
     * equivalents (mirrors auth/hiring behaviour).
     */
    private function applyEnvironmentPrefix(string $subject): string
    {
        if (!config('nats.dev_mode')) {
            return $subject;
        }

        if (str_starts_with($subject, 'qa.v1.')) {
            return str_replace('qa.v1.', 'qa.testing.v1.', $subject);
        }

        if (str_starts_with($subject, 'notifications.v1.')) {
            return str_replace('notifications.v1.', 'notifications.testing.v1.', $subject);
        }

        return $subject;
    }
}
