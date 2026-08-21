<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A completion = a task actually DONE for a store in one period.
        // Only "done" rows exist. "pending" / "overdue" are computed on read
        // (from the task rule + whether a completion exists for the period).
        Schema::create('cleaning_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cleaning_task_id')->constrained('cleaning_tasks')->cascadeOnDelete();
            $table->unsignedBigInteger('store_id');

            $table->date('period_start');   // the period this completion covers
            $table->date('period_end');

            $table->dateTime('completed_at');
            $table->unsignedBigInteger('completed_by_user_id')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            // one completion per task+store+period
            $table->unique(['cleaning_task_id', 'store_id', 'period_start'], 'ccomp_unique');
            $table->index(['store_id', 'period_start']);
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cleaning_completions');
    }
};
