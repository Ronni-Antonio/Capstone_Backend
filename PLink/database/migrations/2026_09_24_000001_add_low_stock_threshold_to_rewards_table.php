<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->unsignedInteger('low_stock_threshold')
                ->default(10)
                ->after('stock_quantity');
        });

        // Seed one current-state inventory alert for rewards that are already
        // low/out of stock when this feature is deployed.
        $now = now();
        $rewards = DB::table('rewards')
            ->select('reward_id', 'reward_name', 'stock_quantity', 'low_stock_threshold')
            ->get();

        foreach ($rewards as $reward) {
            $stock = (int) $reward->stock_quantity;
            $threshold = (int) $reward->low_stock_threshold;

            if ($stock > $threshold) {
                continue;
            }

            $outOfStock = $stock <= 0;
            $type = $outOfStock ? 'reward_out_of_stock' : 'reward_low_stock';
            $title = $outOfStock
                ? "Reward out of stock: {$reward->reward_name}"
                : "Low reward stock: {$reward->reward_name}";
            $message = $outOfStock
                ? "{$reward->reward_name} has no stock remaining. Add stock before students can redeem this reward again."
                : "Only {$stock} unit" . ($stock === 1 ? '' : 's') . " of {$reward->reward_name} remain. The low-stock threshold is {$threshold}.";

            DB::table('notifications')->insert([
                'student_id' => null,
                'smart_bin_id' => null,
                'notification_type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => json_encode([
                    'reward_id' => (int) $reward->reward_id,
                    'reward_name' => $reward->reward_name,
                    'stock_quantity' => $stock,
                    'low_stock_threshold' => $threshold,
                    'inventory_status' => $outOfStock ? 'out_of_stock' : 'low_stock',
                ]),
                'is_read' => false,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('notifications')
            ->whereIn('notification_type', ['reward_low_stock', 'reward_out_of_stock'])
            ->delete();

        Schema::table('rewards', function (Blueprint $table) {
            $table->dropColumn('low_stock_threshold');
        });
    }
};
