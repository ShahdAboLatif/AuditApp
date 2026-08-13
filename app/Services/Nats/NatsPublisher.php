<?php

namespace App\Services\Nats;

use Basis\Nats\Client;
use Exception;

/**
 * Publishes an already-built payload to a NATS subject and waits for a real
 * JetStream publish ack (via Client::dispatch(), a request/reply call) rather
 * than a bare fire-and-forget Client::publish(). A subject with no backing
 * JetStream stream never gets an ack, so this throws instead of silently
 * dropping the message. Used by the outbox worker, not called directly by
 * business code (business code uses OutboxService::record()).
 */
class NatsPublisher
{
    private ?Client $client = null;

    public function __construct(private readonly NatsClientFactory $factory)
    {
    }

    public function publishRaw(string $subject, array $payload): void
    {
        $this->assertSubjectAllowed($subject);

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new Exception("Failed to encode payload as JSON for subject '{$subject}'.");
        }

        $client = $this->client ??= $this->factory->make();
        $timeout = (float) config('nats.publish_ack_timeout', 3);

        $ack = $client->dispatch($subject, $json, $timeout);
        $decoded = is_string($ack->body ?? null) ? json_decode($ack->body, true) : null;

        if (!is_array($decoded) || isset($decoded['error']) || !isset($decoded['stream'])) {
            $raw = is_string($ack->body ?? null) ? $ack->body : 'no reply body';

            throw new Exception("NATS publish not acknowledged by a JetStream stream for subject '{$subject}': {$raw}");
        }
    }

    /**
     * Reject subjects not explicitly allowed (config('nats.allowed_publish_subjects'))
     * before they ever hit the network. Deliberately separate from
     * config('nats.publishers'), which only lists streams this app provisions
     * via nats:ensure-streams — a subject can be allowed to publish without
     * this app owning that stream's lifecycle (see config/nats.php).
     */
    private function assertSubjectAllowed(string $subject): void
    {
        $patterns = array_map('strval', (array) config('nats.allowed_publish_subjects', []));

        if (empty($patterns)) {
            return;
        }

        foreach ($patterns as $pattern) {
            if ($this->matchesNatsSubject($subject, $pattern)) {
                return;
            }
        }

        throw new Exception("Subject '{$subject}' is not in config('nats.allowed_publish_subjects').");
    }

    private function matchesNatsSubject(string $subject, string $pattern): bool
    {
        $subjectTokens = explode('.', $subject);
        $patternTokens = explode('.', $pattern);

        $si = 0;
        $pi = 0;

        while ($pi < count($patternTokens)) {
            $token = $patternTokens[$pi];

            if ($token === '>') {
                return $pi === count($patternTokens) - 1;
            }

            if ($si >= count($subjectTokens)) {
                return false;
            }

            if ($token !== '*' && $token !== $subjectTokens[$si]) {
                return false;
            }

            $si++;
            $pi++;
        }

        return $si === count($subjectTokens);
    }
}
