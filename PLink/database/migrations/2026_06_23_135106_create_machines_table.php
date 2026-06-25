<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->bigIncrements('machine_id')->primary();
            $table->string('name');
            $table->string('location'); // e.g., "Pasig Elementary School - Main Building"
            $table->string('status')->default('online'); // online, offline, maintenance, full
            $table->decimal('current_weight_kg', 10, 2)->default(0);
            $table->decimal('max_capacity_kg', 10, 2)->default(100);
            $table->datetime('last_maintenance_at')->nullable();
            $table->datetime('last_active_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
