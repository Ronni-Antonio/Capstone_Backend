<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Rewards;

class RewardStockAlertService
{
    public const LOW_STOCK_TYPE = 'reward_low_stock';
    public const OUT_OF_STOCK_TYPE = 'reward_out_of_stock';

    /**
     * Keep reward inventory notifications in sync with a stock change.
     *
     * Alerts are transition-based so the admin is not spammed on every
     * redemption while an item remains in the same low-stock state.
     */
    public function sync(
        Rewards $reward,
        ?int $previousStock = null,
        ?int $previousThreshold = null
    ): void {
        $currentStock = (int) $reward->stock_quantity;
        $currentThreshold = max(0, (int) ($reward->low_stock_threshold ?? 10));
        $currentStatus = $this->statusFor($currentStock, $currentThreshold);

        $previousStatus = null;
        if ($previousStock !== null) {
            $previousStatus = $this->statusFor(
                $previousStock,
                max(0, $previousThreshold ?? $currentThreshold)
            );
        }

        if ($currentStatus === 'healthy') {
            $this->resolveInventoryAlerts($reward);
            return;
        }

        // Same state means there is no new threshold crossing. Keep an
        // existing unread alert current, but do not create another one.
        if ($previousStatus === $currentStatus) {
            $this->refreshUnreadAlert($reward, $currentStatus);
            return;
        }

        // A transition between low and out-of-stock closes the older type.
        if ($currentStatus === 'out') {
            $this->resolveAlertType($reward, self::LOW_STOCK_TYPE);
        } else {
            $this->resolveAlertType($reward, self::OUT_OF_STOCK_TYPE);
        }

        $this->createOrRefreshAlert($reward, $currentStatus);
    }

    private function statusFor(int $stock, int $threshold): string
    {
        if ($stock <= 0) {
            return 'out';
        }

        if ($stock <= $threshold) {
            return 'low';
        }

        return 'healthy';
    }

    private function createOrRefreshAlert(Rewards $reward, string $status): void
    {
        $type = $status === 'out'
            ? self::OUT_OF_STOCK_TYPE
            : self::LOW_STOCK_TYPE;

        $existing = $this->unreadAlertQuery($reward, $type)->latest()->first();

        $payload = $this->alertPayload($reward, $status);

        if ($existing) {
            $existing->update($payload);
            return;
        }

        Notification::create($payload);
    }

    private function refreshUnreadAlert(Rewards $reward, string $status): void
    {
        $type = $status === 'out'
            ? self::OUT_OF_STOCK_TYPE
            : self::LOW_STOCK_TYPE;

        $existing = $this->unreadAlertQuery($reward, $type)->latest()->first();

        if ($existing) {
            $existing->update($this->alertPayload($reward, $status));
        }
    }

    private function alertPayload(Rewards $reward, string $status): array
    {
        $stock = (int) $reward->stock_quantity;
        $threshold = max(0, (int) ($reward->low_stock_threshold ?? 10));

        if ($status === 'out') {
            return [
                'notification_type' => self::OUT_OF_STOCK_TYPE,
                'title' => "Reward out of stock: {$reward->reward_name}",
                'message' => "{$reward->reward_name} has no stock remaining. Add stock before students can redeem this reward again.",
                'data' => [
                    'reward_id' => (int) $reward->reward_id,
                    'reward_name' => $reward->reward_name,
                    'stock_quantity' => $stock,
                    'low_stock_threshold' => $threshold,
                    'inventory_status' => 'out_of_stock',
                ],
                'is_read' => false,
                'read_at' => null,
            ];
        }

        return [
            'notification_type' => self::LOW_STOCK_TYPE,
            'title' => "Low reward stock: {$reward->reward_name}",
            'message' => "Only {$stock} unit" . ($stock === 1 ? '' : 's') . " of {$reward->reward_name} remain. The low-stock threshold is {$threshold}.",
            'data' => [
                'reward_id' => (int) $reward->reward_id,
                'reward_name' => $reward->reward_name,
                'stock_quantity' => $stock,
                'low_stock_threshold' => $threshold,
                'inventory_status' => 'low_stock',
            ],
            'is_read' => false,
            'read_at' => null,
        ];
    }

    private function resolveInventoryAlerts(Rewards $reward): void
    {
        Notification::query()
            ->whereIn('notification_type', [self::LOW_STOCK_TYPE, self::OUT_OF_STOCK_TYPE])
            ->where('is_read', false)
            ->where('data->reward_id', (int) $reward->reward_id)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    private function resolveAlertType(Rewards $reward, string $type): void
    {
        $this->unreadAlertQuery($reward, $type)->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    private function unreadAlertQuery(Rewards $reward, string $type)
    {
        return Notification::query()
            ->where('notification_type', $type)
            ->where('is_read', false)
            ->where('data->reward_id', (int) $reward->reward_id);
    }
}
