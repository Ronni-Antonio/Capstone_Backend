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
        Schema::create('machine_logs', function (Blueprint $table) {
            $table->bigIncrements('machine_log_id')->primary();
            $table->unsignedBigInteger('machine_id');
            $table->string('log_type'); // e.g., "startup", "shutdown", "error", "maintenance", "status_change", "item_deposited"
            $table->text('message');
            $table->json('details')->nullable();
            $table->timestamps();

            $table->foreign('machine_id')->references('machine_id')->on('machines')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machine_logs');
    }
};
