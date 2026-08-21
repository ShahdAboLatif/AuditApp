<?php

namespace App\Services\Nats;

use App\Models\EventOutbox;

/**
 * Stores an already-built CloudEvent envelope into the outbox (NOT directly
 * to NATS). Call after EventFactory::make(); the outbox:publish worker then
 * delivers it to NATS. Envelope building lives in EventFactory, not here.
 */
class OutboxService
{
    public function record(string $subject, array $envelope): EventOutbox
    {
        $subject = $this->applyEnvironmentPrefix($subject);

        return EventOutbox::query()->create([
            'event_id'     => $envelope['id'],
            'subject'      => $subject,
            'payload'      => $envelope,
            'published_at' => null,
            'attempts'     => 0,
        ]);
    }

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
