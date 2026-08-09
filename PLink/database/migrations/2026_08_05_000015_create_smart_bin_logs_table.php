<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('smart_bin_logs', function (Blueprint $table) {
            $table->id('smart_bin_log_id');

            $table->foreignId('smart_bin_id')
                ->constrained('smart_bins', 'smart_bin_id')
                ->cascadeOnDelete();

            $table->integer("distance_cm")->nullable();
            $table->integer("fill_percentage")->nullable();
            $table->string('status'); // status, error, scanner, maintenance, sensor
            $table->timestamps();

            $table->index(['smart_bin_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_bin_logs');
    }
};
