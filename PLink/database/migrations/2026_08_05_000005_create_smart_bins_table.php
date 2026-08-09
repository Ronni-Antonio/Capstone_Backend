<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('smart_bins', function (Blueprint $table) {
            $table->id('smart_bin_id');
            $table->string('name');
            $table->string('location');
            $table->string('status')->default('online'); // online, offline, maintenance, full
            $table->integer('current_fill_percentage'); // in percentage
            $table->integer('current_distance_cm'); // in centimeters
            $table->integer("full_threshold_cm");
            $table->integer("empty_threshold_cm");
            $table->timestamp('last_maintenance_at')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_bins');
    }
};
