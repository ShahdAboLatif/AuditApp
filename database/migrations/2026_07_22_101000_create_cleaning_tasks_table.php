<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The task DEFINITION + its recurrence RULE. No occurrence rows are stored;
        // "due" and "done/overdue" are computed on read from this rule + completions.
        Schema::create('cleaning_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('weight')->nullable();          // 0..100, importance
            $table->boolean('photo_required')->default(true);           // DERIVED from frequency

            // ── recurrence rule ──
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'hourly']);
            $table->unsignedInteger('interval')->default(1);            // "every N" days/weeks/months
            $table->json('week_days')->nullable();                      // [1..7] ISO, for weekly (optional)
            $table->unsignedTinyInteger('interval_hours')->nullable();  // required when frequency=hourly
            $table->date('starts_at');                                  // anchor: first period start
            $table->date('ends_at')->nullable();                        // recurrence stops (optional)
            $table->time('due_time')->nullable();                       // optional "by this time"

            $table->unsignedBigInteger('created_by')->nullable();       // auditor user id
            $table->timestamps();
            $table->softDeletes();
        });

        // Which stores a task applies to.
        Schema::create('cleaning_task_stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cleaning_task_id')->constrained('cleaning_tasks')->cascadeOnDelete();
            $table->unsignedBigInteger('store_id');
            $table->timestamps();

            $table->unique(['cleaning_task_id', 'store_id'], 'cts_task_store_unique');
            $table->index('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cleaning_task_stores');
        Schema::dropIfExists('cleaning_tasks');
    }
};
