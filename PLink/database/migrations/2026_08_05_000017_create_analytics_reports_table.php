<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_reports', function (Blueprint $table) {
            $table->id('analytics_report_id');

            $table->foreignId('generated_by_user_id')
                ->nullable()
                ->constrained('users', 'id')
                ->nullOnDelete();

            $table->string('report_type');
            $table->string('title');
            $table->date('report_date_start')->nullable();
            $table->date('report_date_end')->nullable();

            // Store only generated/snapshot values; source data remains normalized.
            $table->unsignedInteger('total_items_collected')->default(0);
            $table->decimal('total_weight_kg', 10, 2)->default(0);
            $table->unsignedInteger('total_points_awarded')->default(0);
            $table->unsignedInteger('total_rewards_redeemed')->default(0);
            $table->unsignedInteger('total_students_participated')->default(0);

            $table->json('summary')->nullable();
            $table->json('predictive_insights')->nullable();

            $table->timestamps();

            $table->index(['report_type', 'created_at']);
            $table->index(['report_date_start', 'report_date_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_reports');
    }
};
