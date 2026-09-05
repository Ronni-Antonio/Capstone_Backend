<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)
                ->default(0)
                ->after('points_cost');

            $table->timestamp('last_restock')
                ->nullable()
                ->after('stock_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->dropColumn([
                'unit_price',
                'last_restock',
            ]);
        });
    }
};