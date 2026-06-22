<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->bigIncrements('setting_id');
            
            // School Info
            $table->string('school_name')->nullable();
            $table->string('school_address')->nullable();
            $table->string('school_year')->nullable();
            $table->string('school_email')->nullable();

            // Point Conversion & Penalties
            $table->integer('point_conversion')->default(5);
            $table->integer('penalty_rejected')->default(-1);
            $table->integer('penalty_invalid')->default(-2);
            $table->integer('penalty_non_pet')->default(-1);
            $table->integer('penalty_custom')->default(-1);

            // Notification Settings
            $table->boolean('notify_machine_full')->default(true);
            $table->boolean('notify_scanner_errors')->default(true);
            $table->boolean('notify_machine_offline')->default(true);
            $table->boolean('notify_maintenance')->default(true);
            $table->boolean('notify_weekly_summary')->default(false);
            $table->boolean('notify_milestones')->default(true);

            // Backup
            $table->boolean('auto_backup')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};