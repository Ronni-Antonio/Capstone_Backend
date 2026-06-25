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
        Schema::create('analytics_reports', function (Blueprint $table) {
            $table->bigIncrements('analytics_report_id')->primary();
            $table->string('report_type'); // e.g., "daily", "weekly", "monthly", "yearly", "custom"
            $table->string('title');
            $table->date('report_date_start')->nullable();
            $table->date('report_date_end')->nullable();
            $table->integer('total_items_collected')->default(0);
            $table->decimal('total_weight_kg', 10, 2)->default(0);
            $table->integer('total_points_awarded')->default(0);
            $table->integer('total_rewards_redeemed')->default(0);
            $table->integer('total_students_participated')->default(0);
            $table->json('plastic_type_breakdown')->nullable(); // e.g., {"PET": 150, "HDPE": 75, ...}
            $table->json('grade_level_breakdown')->nullable();
            $table->json('predictive_insights')->nullable(); // AI predictions
            $table->json('generated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_reports');
    }
};
