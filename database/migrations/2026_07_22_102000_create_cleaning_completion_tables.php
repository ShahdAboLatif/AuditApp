<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who performed a completion (can be several employees).
        Schema::create('cleaning_completion_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cleaning_completion_id')->constrained('cleaning_completions')->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id');
            $table->timestamps();

            $table->unique(['cleaning_completion_id', 'employee_id'], 'cce_unique');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });

        // The proof photo(s) attached on completion.
        Schema::create('cleaning_completion_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cleaning_completion_id')->constrained('cleaning_completions')->cascadeOnDelete();
            $table->string('path'); // storage path on the public disk
            $table->timestamps();

            $table->index('cleaning_completion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cleaning_completion_attachments');
        Schema::dropIfExists('cleaning_completion_employees');
    }
};
