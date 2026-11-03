<?php

namespace process;

use addons\webman\model\DishOrder;
use addons\webman\model\PlayerPointsRecord;
use addons\webman\service\PlayerPointsService;
use support\Db;
use support\Log;
use Workerman\Crontab\Crontab;

/**
 * 餐點訂單超時自動取消定時任務
 *
 * 每 5 分鐘掃描一次待確認訂單（status=0），
 * 超過 2 小時未確認的訂單自動取消並退還積分。
 */
class DishOrderAutoExpireTask
{
    private $log = null;

    private static bool $running = false;

    public function onWorkerStart(): void
    {
        $this->log = Log::channel('default');

        // 每 5 分鐘執行一次
        new Crontab('0 */5 * * * *', function () {
            $this->doWork();
        });
    }

    private function doWork(): void
    {
        if (self::$running) {
            return;
        }

        self::$running = true;

        try {
            $expireTime = date('Y-m-d H:i:s', strtotime('-2 hours'));
            $cancelled  = 0;
            $failed     = 0;

            DishOrder::query()
                ->where('status', DishOrder::STATUS_PENDING)
                ->where('created_at', '<', $expireTime)
                ->orderBy('id')
                ->chunkById(100, function ($orders) use (&$cancelled, &$failed) {
                    foreach ($orders as $order) {
                        try {
                            $this->cancelOrder((int)$order->id);
                            $cancelled++;
                        } catch (\Throwable $e) {
                            $failed++;
                            $this->log->error('DishOrderAutoExpireTask 取消訂單失敗', [
                                'order_id' => $order->id,
                                'error'    => $e->getMessage(),
                            ]);
                        }
                    }
                });

            if ($cancelled > 0 || $failed > 0) {
                $this->log->info('DishOrderAutoExpireTask 完成', [
                    'cancelled'     => $cancelled,
                    'failed'        => $failed,
                    'expire_before' => $expireTime,
                ]);
            }

        } finally {
            self::$running = false;
        }
    }

    private function cancelOrder(int $orderId): void
    {
        Db::beginTransaction();

        try {
            $order = DishOrder::query()
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (!$order || $order->status !== DishOrder::STATUS_PENDING) {
                Db::rollBack();
                return;
            }

            $order->status = DishOrder::STATUS_CANCELLED;
            $order->save();

            $pointsToReturn = (int)$order->total_amount;

            if ($pointsToReturn > 0) {
                PlayerPointsService::addPoints(
                    (int)$order->player_id,
                    $pointsToReturn,
                    '訂單超時自動取消退積分 ' . $order->order_no,
                    ['admin_id' => 0, 'admin_name' => '系統自動取消', 'admin_ip' => ''],
                    PlayerPointsRecord::TYPE_REFUND,
                    PlayerPointsRecord::SOURCE_REFUND
                );
            }

            Db::commit();

        } catch (\Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }
}
