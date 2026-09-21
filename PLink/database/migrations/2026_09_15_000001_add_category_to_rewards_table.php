<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            if (!Schema::hasColumn('rewards', 'category')) {
                $table->string('category', 100)
                    ->nullable()
                    ->default(null)
                    ->after('reward_name');

                $table->index('category');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            if (Schema::hasColumn('rewards', 'category')) {
                $table->dropIndex(['category']);
                $table->dropColumn('category');
            }
        });
    }
};
