<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Employees are REPLICATED from HiringPizza via hiring.v1.employee.* events.
        // Same shape as the inventory project: one store_id per employee (resolved
        // from the event's store_number to the local stores.id).
        Schema::create('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // Hiring controls the ID
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->unsignedBigInteger('store_id');       // matches stores.id
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
