<?php

namespace App\Services\Nats;

use Basis\Nats\Client;

/**
 * Thin wrapper that publishes an already-built payload to a NATS subject.
 * A JetStream stream configured to capture `qa.v1.>` (see config/nats.php
 * publishers) will persist it. Used by the outbox worker, not called directly
 * by business code (business code uses OutboxService::record()).
 */
class NatsPublisher
{
    private ?Client $client = null;

    public function __construct(private readonly NatsClientFactory $factory)
    {
    }

    public function publishRaw(string $subject, array $payload): void
    {
        $client = $this->client ??= $this->factory->make();
        $client->publish($subject, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
