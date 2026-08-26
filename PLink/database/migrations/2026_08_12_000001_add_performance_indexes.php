<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('recycling_transactions', function (Blueprint $table) {
            $table->index(['status', 'started_at'], 'rt_status_started_idx');
            $table->index(['status', 'completed_at'], 'rt_status_completed_idx');
        });

        Schema::table('redemptions', function (Blueprint $table) {
            $table->index('redeemed_at', 'redemptions_redeemed_at_idx');
        });

        Schema::table('smart_bin_logs', function (Blueprint $table) {
            $table->index('created_at', 'smart_bin_logs_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('recycling_transactions', function (Blueprint $table) {
            $table->dropIndex('rt_status_started_idx');
            $table->dropIndex('rt_status_completed_idx');
        });

        Schema::table('redemptions', function (Blueprint $table) {
            $table->dropIndex('redemptions_redeemed_at_idx');
        });

        Schema::table('smart_bin_logs', function (Blueprint $table) {
            $table->dropIndex('smart_bin_logs_created_at_idx');
        });
    }
};
