<?php

namespace App\Services\Nats;

use App\Models\EventOutbox;
use Illuminate\Support\Str;

/**
 * Writes outgoing qa.v1.* events into the outbox (NOT directly to NATS).
 * Call record() inside the same DB transaction as the business change; the
 * outbox:publish worker then delivers it to NATS.
 *
 * Example (Phase 2):
 *   $outbox->record('qa.v1.cleaning.task.created', [
 *       'task' => [...], 'store_ids' => [1,2,3], 'notify_user_ids' => [51,52],
 *   ]);
 */
class OutboxService
{
    /**
     * Build a CloudEvent envelope + queue it for publishing. Returns the event id.
     */
    public function record(string $subject, array $data, array $meta = []): string
    {
        $subject = $this->applyEnvironmentPrefix($subject);
        $eventId = (string) Str::ulid();

        $envelope = [
            'specversion'     => '1.0',
            'id'              => $eventId,
            'type'            => $subject,
            'source'          => 'qa-system',
            'subject'         => $subject,
            'time'            => now()->utc()->toIso8601String(),
            'datacontenttype' => 'application/json',
            'data'            => $data,
            'meta'            => $meta,
        ];

        EventOutbox::query()->create([
            'event_id'     => $eventId,
            'subject'      => $subject,
            'payload'      => $envelope,
            'published_at' => null,
            'attempts'     => 0,
        ]);

        return $eventId;
    }

    /**
     * In dev mode, qa.v1.* becomes qa.testing.v1.* (mirrors auth/hiring behaviour).
     */
    private function applyEnvironmentPrefix(string $subject): string
    {
        if (!config('nats.dev_mode')) {
            return $subject;
        }

        if (str_starts_with($subject, 'qa.v1.')) {
            return str_replace('qa.v1.', 'qa.testing.v1.', $subject);
        }

        // Notifications commands sent to NotificationsPizza follow the same rule.
        if (str_starts_with($subject, 'notifications.v1.')) {
            return str_replace('notifications.v1.', 'notifications.testing.v1.', $subject);
        }

        return $subject;
    }
}
