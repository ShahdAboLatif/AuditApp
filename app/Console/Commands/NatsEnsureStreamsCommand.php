<?php

namespace App\Console\Commands;

use App\Services\Nats\NatsClientFactory;
use Illuminate\Console\Command;
use Throwable;

/**
 * Creates (if missing) the JetStream streams + durable pull consumers this app
 * needs. Idempotent — safe to run on every boot.
 *
 * - Publisher streams (config nats.publishers): e.g. QA_EVENTS capturing qa.v1.>
 *   so the outbox can publish.
 * - Consumer streams (config nats.streams): e.g. AUTH_EVENTS / HIRING_EVENTS +
 *   their durable consumers so nats:consume can pull.
 *
 * NOTE: in production the publishing systems (pizzasys, HiringPizza) own their
 * streams; createIfNotExists() only creates when absent, so this never clobbers
 * an existing stream. Mainly for local/all-in-one bootstrap.
 */
class NatsEnsureStreamsCommand extends Command
{
    protected $signature = 'nats:ensure-streams';
    protected $description = 'Create JetStream streams + durable consumers if they do not exist';

    public function handle(NatsClientFactory $factory): int
    {
        $client = $factory->make();
        $api = $client->getApi();

        // 1) Publisher streams (we publish qa.v1.* here).
        foreach ((array) config('nats.publishers', []) as $cfg) {
            $name = (string) ($cfg['name'] ?? '');
            $subjects = (array) ($cfg['subjects'] ?? []);
            if ($name === '' || empty($subjects)) {
                continue;
            }
            $this->ensureStream($api, $name, $subjects);
        }

        // 2) Consumer streams + durables (we pull auth.* / hiring.* here).
        foreach ((array) config('nats.streams', []) as $cfg) {
            $name = (string) ($cfg['name'] ?? '');
            $durable = (string) ($cfg['durable'] ?? '');
            $filter = (string) ($cfg['filter_subject'] ?? '>');
            if ($name === '' || $durable === '') {
                continue;
            }

            $stream = $this->ensureStream($api, $name, [$filter]);
            if ($stream !== null) {
                $this->ensureConsumer($stream, $durable, $filter);
            }
        }

        $this->info('NATS streams/consumers ensured.');

        return self::SUCCESS;
    }

    private function ensureStream($api, string $name, array $subjects)
    {
        try {
            $stream = $api->getStream($name);
            $stream->getConfiguration()
                ->setSubjects($subjects)
                ->setRetentionPolicy('limits')
                ->setStorageBackend('file');
            $stream->createIfNotExists();

            $this->line("  stream OK: {$name} [" . implode(',', $subjects) . ']');

            return $stream;
        } catch (Throwable $e) {
            $this->warn("  stream FAILED: {$name} — {$e->getMessage()}");

            return null;
        }
    }

    private function ensureConsumer($stream, string $durable, string $filter): void
    {
        try {
            $consumer = $stream->getConsumer($durable);
            $consumer->getConfiguration()->setSubjectFilter($filter);
            $consumer->create(true);   // ifNotExists = true

            $this->line("  consumer OK: {$durable} (filter {$filter})");
        } catch (Throwable $e) {
            $this->warn("  consumer FAILED: {$durable} — {$e->getMessage()}");
        }
    }
}
