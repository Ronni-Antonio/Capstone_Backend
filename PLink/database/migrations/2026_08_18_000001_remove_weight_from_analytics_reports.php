<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('analytics_reports', 'total_weight_kg')) {
            Schema::table('analytics_reports', function (Blueprint $table) {
                $table->dropColumn('total_weight_kg');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('analytics_reports', 'total_weight_kg')) {
            Schema::table('analytics_reports', function (Blueprint $table) {
                $table->decimal('total_weight_kg', 10, 2)->default(0)->after('total_items_collected');
            });
        }
    }
};
