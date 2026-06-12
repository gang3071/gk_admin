<?php
/**
 * 摸奖券过期处理定时任务
 * 每5分钟执行一次，自动将过期奖券标记为失效
 */

namespace process;

use addons\webman\model\LotteryTicket;
use support\Log;
use support\Redis;
use Workerman\Timer;

class LotteryTicketExpireProcess
{
    /**
     * Worker启动时执行
     */
    public function onWorkerStart()
    {
        // 每5分钟执行一次（300秒）
        Timer::add(300, function() {
            $this->expireTickets();
        });

        // 立即执行一次
        $this->expireTickets();
    }

    /**
     * 处理过期奖券
     */
    private function expireTickets()
    {
        try {
            $now = date('Y-m-d H:i:s');

            // ✅ 优化：先获取受影响的奖券ID和玩家ID
            $expiredTickets = LotteryTicket::query()
                ->where('status', LotteryTicket::STATUS_UNUSED)
                ->where('expired_at', '<', $now)
                ->get(['id', 'player_id']);

            if ($expiredTickets->isEmpty()) {
                return;
            }

            // 提取ID列表
            $ticketIds = $expiredTickets->pluck('id')->toArray();
            $playerIds = $expiredTickets->pluck('player_id')->unique()->toArray();

            // ✅ 批量更新过期奖券状态（使用WHERE IN限定ID范围，避免竞态）
            $count = LotteryTicket::query()
                ->whereIn('id', $ticketIds)  // ← 关键：限定ID范围
                ->where('status', LotteryTicket::STATUS_UNUSED)  // 双重检查
                ->update(['status' => LotteryTicket::STATUS_EXPIRED]);

            Log::info('[摸奖券] 过期奖券处理完成', [
                'queried' => count($ticketIds),  // ✅ 查询到的数量
                'updated' => $count,             // ✅ 实际更新的数量
                'affected_players' => count($playerIds),
                'time' => $now
            ]);

            // ✅ 清除受影响玩家的有效奖券缓存
            $this->clearPlayerTicketCache($playerIds);

        } catch (\Exception $e) {
            Log::error('[摸奖券] 过期奖券处理失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }

    /**
     * 清除玩家有效奖券统计缓存
     * @param array $playerIds 受影响的玩家ID列表
     */
    private function clearPlayerTicketCache(array $playerIds)
    {
        if (empty($playerIds)) {
            return;
        }

        $clearedCount = 0;

        try {
            foreach ($playerIds as $playerId) {
                $cacheKey = "player:{$playerId}:valid_ticket_count";

                if (Redis::del($cacheKey)) {
                    $clearedCount++;
                }
            }

            Log::info('[摸奖券] 玩家缓存清除完成', [
                'total_players' => count($playerIds),
                'cleared_count' => $clearedCount
            ]);

        } catch (\Exception $e) {
            Log::warning('[摸奖券] 缓存清除失败', [
                'error' => $e->getMessage(),
                'cleared_count' => $clearedCount
            ]);
        }
    }
}
