<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id('setting_id');
            $table->string('school_name')->nullable();
            $table->string('school_address')->nullable();
            $table->string('school_year')->nullable();
            $table->string('school_email')->nullable();

            $table->boolean('notify_machine_full')->default(true);
            $table->boolean('notify_scanner_errors')->default(true);
            $table->boolean('notify_machine_offline')->default(true);
            $table->boolean('notify_maintenance')->default(true);
            $table->boolean('notify_weekly_summary')->default(false);
            $table->boolean('notify_milestones')->default(true);
            $table->boolean('auto_backup')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
