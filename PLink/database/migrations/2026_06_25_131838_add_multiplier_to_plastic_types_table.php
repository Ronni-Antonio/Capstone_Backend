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
        Schema::table('plastic_types', function (Blueprint $table) {
            $table->decimal('multiplier', 8, 2)->default(1.00)->after('points_per_item');
        });

        // Set initial multiplier values based on existing points_per_item and current point_conversion
        $settings = \App\Models\systemSettings::first();
        $pointConversion = $settings ? $settings->point_conversion : 5;
        
        \App\Models\PlasticType::all()->each(function ($plasticType) use ($pointConversion) {
            if ($plasticType->points_per_item && $pointConversion > 0) {
                $plasticType->multiplier = $plasticType->points_per_item / $pointConversion;
                $plasticType->save();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plastic_types', function (Blueprint $table) {
            $table->dropColumn('multiplier');
        });
    }
};
