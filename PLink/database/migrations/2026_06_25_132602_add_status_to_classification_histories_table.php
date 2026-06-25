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
        Schema::table('classification_histories', function (Blueprint $table) {
            $table->string('status')->default('valid')->after('notes'); // valid, contaminated, non_pet, rejected, invalid
            $table->integer('points_change')->default(0)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classification_histories', function (Blueprint $table) {
            $table->dropColumn(['status', 'points_change']);
        });
    }
};
