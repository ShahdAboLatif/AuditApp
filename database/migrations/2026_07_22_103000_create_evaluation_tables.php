<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Group A columns — auditor-managed inspection items ("Parking", "Sink"…).
        Schema::create('inspection_items', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // One inspection of one store for one period (day or week).
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->enum('period_type', ['date', 'week'])->default('week');
            $table->string('period_key');            // e.g. 2026-07-21 or 2026-W30
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'period_type', 'period_key'], 'eval_store_period_unique');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
        });

        // Group A cells — one item's value for one evaluation.
        Schema::create('evaluation_item_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->foreignId('inspection_item_id')->constrained('inspection_items')->cascadeOnDelete();
            $table->enum('value', ['pass', 'fail', 'auto_fail', 'empty'])->default('empty');
            $table->timestamps();

            $table->unique(['evaluation_id', 'inspection_item_id'], 'eiv_unique');
        });

        // Group B cells — auditor's verdict on each chart task, with weight snapshot.
        Schema::create('evaluation_chart_verdicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->foreignId('cleaning_task_id')->constrained('cleaning_tasks')->cascadeOnDelete();
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'hourly']);  // snapshot
            $table->unsignedTinyInteger('weight')->default(0);                     // snapshot
            $table->enum('verdict', ['pass', 'fail', 'auto_fail']);
            $table->timestamps();

            $table->unique(['evaluation_id', 'cleaning_task_id'], 'ecv_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_chart_verdicts');
        Schema::dropIfExists('evaluation_item_values');
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('inspection_items');
    }
};
