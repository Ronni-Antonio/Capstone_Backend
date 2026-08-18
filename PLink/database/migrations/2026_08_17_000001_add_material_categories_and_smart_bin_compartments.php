<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('recyclable_types', function (Blueprint $table) {
            $table->string('material_category', 30)
                ->default('plastic')
                ->after('name');
            $table->index('material_category');
        });

        // Existing installations already have the paper/invalid rows. Correct
        // their category after adding the new column (all other existing types
        // remain plastic by default).
        DB::table('recyclable_types')
            ->where(function ($query) {
                $query->where('name', 'like', '%paper%')
                    ->orWhere('code', 'Code 9');
            })
            ->update(['material_category' => 'paper']);

        DB::table('recyclable_types')
            ->where('code', 'INVALID')
            ->update(['material_category' => 'other']);

        Schema::create('smart_bin_compartments', function (Blueprint $table) {
            $table->id('compartment_id');
            $table->foreignId('smart_bin_id')
                ->constrained('smart_bins', 'smart_bin_id')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('material_category', 30); // plastic, paper
            $table->string('status')->default('online'); // online, offline, maintenance, full
            $table->integer('current_distance_cm')->nullable();
            $table->integer('current_fill_percentage')->default(0);
            $table->integer('full_threshold_cm');
            $table->integer('empty_threshold_cm');
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->unique(['smart_bin_id', 'material_category'], 'bin_material_compartment_unique');
            $table->index(['smart_bin_id', 'status']);
        });

        Schema::create('smart_bin_compartment_logs', function (Blueprint $table) {
            $table->id('compartment_log_id');
            $table->foreignId('compartment_id')
                ->constrained('smart_bin_compartments', 'compartment_id')
                ->cascadeOnDelete();
            $table->integer('distance_cm')->nullable();
            $table->integer('fill_percentage')->nullable();
            $table->string('status')->default('normal');
            $table->timestamps();

            $table->index(['compartment_id', 'created_at']);
        });


        // Backfill existing Smart Bins so `php artisan migrate` is enough to
        // enable the two-compartment UI without wiping current data.
        $now = now();
        DB::table('smart_bins')->orderBy('smart_bin_id')->each(function ($bin) use ($now) {
            DB::table('smart_bin_compartments')->insert([
                [
                    'smart_bin_id' => $bin->smart_bin_id,
                    'name' => 'Plastic Compartment',
                    'material_category' => 'plastic',
                    'status' => $bin->status === 'offline' ? 'offline' : 'online',
                    'current_distance_cm' => $bin->current_distance_cm,
                    'current_fill_percentage' => $bin->current_fill_percentage ?? 0,
                    'full_threshold_cm' => $bin->full_threshold_cm,
                    'empty_threshold_cm' => $bin->empty_threshold_cm,
                    'last_active_at' => $bin->last_active_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'smart_bin_id' => $bin->smart_bin_id,
                    'name' => 'Paper Compartment',
                    'material_category' => 'paper',
                    'status' => $bin->status === 'offline' ? 'offline' : 'online',
                    'current_distance_cm' => $bin->empty_threshold_cm,
                    'current_fill_percentage' => 0,
                    'full_threshold_cm' => $bin->full_threshold_cm,
                    'empty_threshold_cm' => $bin->empty_threshold_cm,
                    'last_active_at' => $bin->last_active_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_bin_compartment_logs');
        Schema::dropIfExists('smart_bin_compartments');

        Schema::table('recyclable_types', function (Blueprint $table) {
            $table->dropIndex(['material_category']);
            $table->dropColumn('material_category');
        });
    }
};
