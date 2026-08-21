<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Photos attached when grading an inspection item (Group A).
        Schema::create('evaluation_item_value_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_item_value_id')
                ->constrained('evaluation_item_values', 'id', 'eiva_item_value_id_foreign')
                ->cascadeOnDelete();
            $table->string('path'); // storage path on the public disk
            $table->timestamps();

            $table->index('evaluation_item_value_id', 'eiva_item_value_id_idx');
        });

        // Photos attached when grading a cleaning chart task (Group B).
        Schema::create('evaluation_chart_verdict_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_chart_verdict_id')
                ->constrained('evaluation_chart_verdicts', 'id', 'ecva_verdict_id_foreign')
                ->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();

            $table->index('evaluation_chart_verdict_id', 'ecva_verdict_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_chart_verdict_attachments');
        Schema::dropIfExists('evaluation_item_value_attachments');
    }
};
