<?php

namespace App\Console\Commands;

use App\Models\EventOutbox;
use App\Services\Nats\NatsPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Drains the event_outbox: publishes unsent rows to NATS and marks them sent.
 * Run on a short schedule (every minute) or with --forever as a worker process.
 */
class OutboxPublishCommand extends Command
{
    protected $signature = 'outbox:publish {--forever : Keep running and poll continuously} {--batch=100 : Max rows per pass} {--sleep=2 : Seconds between passes in --forever mode}';
    protected $description = 'Publish pending qa.v1.* events from the outbox to NATS';

    private const MAX_ATTEMPTS = 10;

    public function handle(NatsPublisher $publisher): int
    {
        $forever = (bool) $this->option('forever');
        $batch   = max(1, (int) $this->option('batch'));
        $sleep   = max(1, (int) $this->option('sleep'));

        do {
            $sent = $this->drainOnce($publisher, $batch);
            if ($forever) {
                if ($sent === 0) {
                    sleep($sleep);
                }
            }
        } while ($forever);

        return self::SUCCESS;
    }

    private function drainOnce(NatsPublisher $publisher, int $batch): int
    {
        $rows = EventOutbox::query()
            ->whereNull('published_at')
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->orderBy('id')
            ->limit($batch)
            ->get();

        $sent = 0;
        foreach ($rows as $row) {
            try {
                $publisher->publishRaw($row->subject, (array) $row->payload);
                $row->published_at = now();
                $row->last_error = null;
                $row->save();
                $sent++;
            } catch (Throwable $e) {
                $row->attempts = (int) $row->attempts + 1;
                $row->last_error = $e->getMessage();
                $row->save();

                Log::error('Outbox publish failed', [
                    'event_id' => $row->event_id,
                    'subject'  => $row->subject,
                    'attempts' => $row->attempts,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }
}
