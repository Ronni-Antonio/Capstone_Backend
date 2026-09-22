<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('smart_bin_compartments', function (Blueprint $table) {
            $table->decimal('current_distance_cm', 8, 2)->nullable()->change();
        });

        Schema::table('smart_bin_compartment_logs', function (Blueprint $table) {
            $table->decimal('distance_cm', 8, 2)->nullable()->change();
        });

        Schema::table('smart_bins', function (Blueprint $table) {
            $table->decimal('current_distance_cm', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('smart_bin_compartments', function (Blueprint $table) {
            $table->unsignedInteger('current_distance_cm')->nullable()->change();
        });

        Schema::table('smart_bin_compartment_logs', function (Blueprint $table) {
            $table->unsignedInteger('distance_cm')->nullable()->change();
        });

        Schema::table('smart_bins', function (Blueprint $table) {
            $table->unsignedInteger('current_distance_cm')->nullable()->change();
        });
    }
};
