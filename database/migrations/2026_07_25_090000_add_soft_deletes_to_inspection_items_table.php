<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Removing an inspection item is now a soft delete, so its past
        // evaluation cells (evaluation_item_values) are preserved instead of
        // being cascade-deleted.
        Schema::table('inspection_items', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('inspection_items', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
