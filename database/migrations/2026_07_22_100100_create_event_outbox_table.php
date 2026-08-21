<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Transactional outbox: rows written here (in the same DB transaction as the
        // business change) are published to NATS by the outbox:publish worker.
        // This guarantees an event is never lost even if NATS is momentarily down.
        Schema::create('event_outbox', function (Blueprint $table) {
            $table->id();

            $table->string('event_id', 64)->unique();   // ULID of the CloudEvent
            $table->string('subject', 255)->index();     // final subject (dev prefix applied)
            $table->json('payload');                      // full CloudEvent envelope

            $table->timestamp('published_at')->nullable()->index(); // null = not sent yet
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index(['published_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_outbox');
    }
};
