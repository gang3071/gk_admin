<?php

namespace addons\webman\service;

use addons\webman\model\LotteryTicket;
use addons\webman\model\LotteryTicketActivity;
use support\Db;
use support\Log;
use support\Redis;

/**
 * 摸奖券发放服务
 * 处理奖券编号唯一性和并发控制
 */
class LotteryTicketIssueService
{
    /**
     * 发放奖券（带唯一性检查和并发控制）
     *
     * @param int $activityId 活动ID
     * @param int $playerId 玩家ID
     * @param int $count 发放数量
     * @param string $source 来源：'recharge'-充值赠送 'activity'-活动赠送 'manual'-手动发放
     * @return array 发放的奖券列表
     * @throws \Exception
     */
    public function issueTickets(int $activityId, int $playerId, int $count, string $source = LotteryTicket::SOURCE_MANUAL): array
    {
        if ($count <= 0) {
            throw new \Exception('发放数量必须大于0');
        }

        // ✅ 新增：检查活动剩余容量
        $remaining = $this->getRemainingCapacity($activityId);

        if ($remaining <= 0) {
            throw new \Exception('活动奖券编号已用尽，无法发放');
        }

        // ✅ 新增：调整发放数量（避免超出容量导致全部失败）
        $actualCount = min($count, $remaining);

        if ($actualCount < $count) {
            Log::warning('[摸奖券] 容量不足，减少发放数量', [
                'activity_id' => $activityId,
                'player_id' => $playerId,
                'requested' => $count,
                'actual' => $actualCount,
                'remaining' => $remaining,
                'source' => $source
            ]);
        }

        // 获取活动信息
        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity) {
            throw new \Exception('活动不存在');
        }

        // ✅ 检查活动状态：只有进行中才能发券
        // 新状态流程：ONGOING → PENDING_DRAW → DRAWING → ENDED
        // 待开奖(PENDING_DRAW)后不再发券（打码进度已结束）
        if ($activity->status !== LotteryTicketActivity::STATUS_ONGOING) {
            throw new \Exception('活动未进行中，无法发券');
        }

        // ✅ 双重检查：防止活动时间结束但状态未更新的边缘情况
        if (strtotime($activity->end_time) < time()) {
            throw new \Exception('活动已结束，无法发券');
        }

        $tickets = [];
        $maxRetries = 10;  // 最大重试次数
        $reservedSequences = []; // ✅ 记录预留的Redis序列号

        try {
            Db::beginTransaction();

            for ($i = 0; $i < $actualCount; $i++) {  // ✅ 使用调整后的数量
                $retry = 0;
                $ticket = null;

                while ($retry < $maxRetries) {
                    try {
                        // ✅ 生成唯一编号（使用Redis原子递增）
                        $sequence = Redis::incr("lottery_activity:{$activityId}:ticket_sequence");
                        $reservedSequences[] = $sequence; // 记录预留的序列号

                        // 检查是否超过上限
                        if ($sequence > 999999) {
                            throw new \Exception('活动奖券编号已用尽（超过100万张）');
                        }

                        $ticketNo = str_pad($sequence, 6, '0', STR_PAD_LEFT);

                        // 创建奖券（数据库唯一约束会防止重复）
                        $ticket = LotteryTicket::create([
                            'activity_id' => $activityId,
                            'player_id' => $playerId,
                            'department_id' => $activity->department_id,
                            'ticket_no' => $ticketNo,
                            'status' => LotteryTicket::STATUS_UNUSED,  // ✅ 使用正确的常量
                            'source' => $source,
                            'expired_at' => $activity->end_time,
                        ]);

                        break;  // 成功，跳出重试循环

                    } catch (\Illuminate\Database\QueryException $e) {
                        // 检查是否是唯一约束冲突
                        if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                            $retry++;

                            if ($retry >= $maxRetries) {
                                throw new \Exception("编号冲突重试{$maxRetries}次仍失败: {$ticketNo}");
                            }

                            Log::warning('[摸奖券] 编号冲突，重试', [
                                'activity_id' => $activityId,
                                'ticket_no' => $ticketNo ?? 'unknown',
                                'retry' => $retry,
                                'sequence' => $sequence
                            ]);

                            continue;  // 重试（会在下次循环中生成新的序列号）
                        }

                        // 其他数据库错误直接抛出
                        throw $e;
                    }
                }

                if (!$ticket) {
                    throw new \Exception("无法生成唯一奖券编号，已重试{$maxRetries}次");
                }

                $tickets[] = $ticket;
            }

            Db::commit();

            Log::info('[摸奖券] 发放成功', [
                'activity_id' => $activityId,
                'player_id' => $playerId,
                'requested_count' => $count,
                'actual_count' => $actualCount,
                'reserved_sequences' => count($reservedSequences), // ✅ 记录预留数
                'source' => $source
            ]);

            // 清除玩家有效奖券缓存
            $this->clearPlayerTicketCache($playerId);

            // ⭐ 推送发券通知给客户端
            // ⚠️ 不传 totalTickets 参数，让推送服务重新查询最新数据，确保数据一致性
            $message = sprintf('您在活動「%s」中獲得了 %d 張摸獎券！', $activity->name, $actualCount);
            LotteryTicketPushService::pushPlayerTicketsUpdate($playerId, $message);

            return $tickets;

        } catch (\Exception $e) {
            Db::rollBack();

            // ✅ 记录浪费的序列号（用于监控）
            Log::error('[摸奖券] 发放失败，序列号已浪费', [
                'activity_id' => $activityId,
                'player_id' => $playerId,
                'requested_count' => $count,
                'wasted_sequences' => $reservedSequences,
                'wasted_count' => count($reservedSequences),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 检查活动奖券剩余容量
     *
     * @param int $activityId
     * @return int 剩余可发放数量
     */
    public function getRemainingCapacity(int $activityId): int
    {
        $issued = $this->getIssuedCount($activityId);
        return max(0, 999999 - $issued);
    }

    /**
     * 获取活动已发放的奖券数量
     * ✅ 优化：Redis失效时从数据库读取，确保准确性
     *
     * @param int $activityId
     * @return int
     */
    public function getIssuedCount(int $activityId): int
    {
        $key = "lottery_activity:{$activityId}:ticket_sequence";
        $redisCount = Redis::get($key);

        // ✅ Redis有值，直接返回
        if ($redisCount !== false && $redisCount !== null) {
            return (int)$redisCount;
        }

        // ✅ Redis失效，从数据库读取
        $dbCount = LotteryTicket::where('activity_id', $activityId)->count();

        // ✅ 回写Redis（避免缓存击穿）
        if ($dbCount > 0) {
            Redis::set($key, $dbCount);

            Log::warning('[摸奖券] Redis序列号失效，已从数据库恢复', [
                'activity_id' => $activityId,
                'db_count' => $dbCount
            ]);
        }

        return $dbCount;
    }

    /**
     * 清除玩家有效奖券缓存
     *
     * @param int $playerId
     */
    private function clearPlayerTicketCache(int $playerId)
    {
        try {
            $cacheKey = "player:{$playerId}:valid_ticket_count";
            Redis::del($cacheKey);
        } catch (\Exception $e) {
            Log::warning('[摸奖券] 清除缓存失败', [
                'player_id' => $playerId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 批量发放奖券（性能优化版）
     * ⭐ 预分配Redis序列号，批量插入数据库
     *
     * @param int $activityId 活动ID
     * @param int $playerId 玩家ID
     * @param int $count 发放数量
     * @param string $source 来源
     * @return array ['count' => 实际发放数, 'first_ticket_no' => 首张券号, 'last_ticket_no' => 末张券号]
     * @throws \Exception
     */
    public function issueTicketsBatch(int $activityId, int $playerId, int $count, string $source = LotteryTicket::SOURCE_BETTING): array
    {
        if ($count <= 0) {
            throw new \Exception('发放数量必须大于0');
        }

        // 检查活动剩余容量
        $remaining = $this->getRemainingCapacity($activityId);
        if ($remaining <= 0) {
            throw new \Exception('活动奖券编号已用尽，无法发放');
        }

        $actualCount = min($count, $remaining);

        // 获取活动信息
        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity) {
            throw new \Exception('活动不存在');
        }

        if ($activity->status !== LotteryTicketActivity::STATUS_ONGOING) {
            throw new \Exception('活动未进行中，无法发券');
        }

        // ⭐ 预分配Redis序列号（批量，原子操作）
        $key = "lottery_activity:{$activityId}:ticket_sequence";
        $baseSequence = Redis::incrby($key, $actualCount);  // 一次性分配多个序列号
        $startSequence = $baseSequence - $actualCount + 1;

        // 检查是否超过上限
        if ($baseSequence > 999999) {
            // 回退Redis计数
            Redis::decrby($key, $actualCount);
            throw new \Exception('活动奖券编号已用尽（超过100万张）');
        }

        try {
            Db::beginTransaction();

            // 批量准备券数据
            $ticketsData = [];
            $now = date('Y-m-d H:i:s');

            for ($i = 0; $i < $actualCount; $i++) {
                $sequence = $startSequence + $i;
                $ticketNo = str_pad($sequence, 6, '0', STR_PAD_LEFT);

                $ticketsData[] = [
                    'activity_id' => $activityId,
                    'player_id' => $playerId,
                    'department_id' => $activity->department_id,
                    'ticket_no' => $ticketNo,
                    'status' => LotteryTicket::STATUS_UNUSED,
                    'source' => $source,
                    'expired_at' => $activity->end_time,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // 批量插入（失败会抛出异常）
            LotteryTicket::insert($ticketsData);

            Db::commit();

            Log::info('[摸奖券] 批量发放成功', [
                'activity_id' => $activityId,
                'player_id' => $playerId,
                'count' => $actualCount,
                'sequence_range' => "{$startSequence}-{$baseSequence}",
                'source' => $source
            ]);

            // 清除玩家有效奖券缓存
            $this->clearPlayerTicketCache($playerId);

            // ⭐ 推送发券通知给客户端
            // ⚠️ 不传 totalTickets 参数，让推送服务重新查询最新数据，确保数据一致性
            $message = sprintf('您在活動「%s」中獲得了 %d 張摸獎券！', $activity->name, $actualCount);
            LotteryTicketPushService::pushPlayerTicketsUpdate($playerId, $message);

            // ⭐ 只返回必要信息，避免查询和返回大量数据
            return [
                'count' => $actualCount,
                'first_ticket_no' => str_pad($startSequence, 6, '0', STR_PAD_LEFT),
                'last_ticket_no' => str_pad($baseSequence, 6, '0', STR_PAD_LEFT),
            ];

        } catch (\Exception $e) {
            Db::rollBack();

            // 回退Redis序列号（避免序列号浪费）
            Redis::decrby($key, $actualCount);

            Log::error('[摸奖券] 批量发放失败，已回退Redis序列号', [
                'activity_id' => $activityId,
                'player_id' => $playerId,
                'count' => $actualCount,
                'sequence_range' => "{$startSequence}-{$baseSequence}",
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 生成唯一奖券编号（使用Redis序列号）
     *
     * @param int $activityId
     * @return string 6位数编号
     * @throws \Exception
     */
    private function generateUniqueTicketNo(int $activityId): string
    {
        $key = "lottery_activity:{$activityId}:ticket_sequence";

        // Redis原子递增
        $sequence = Redis::incr($key);

        // 检查是否超过上限（6位数最大999999）
        if ($sequence > 999999) {
            Log::error('[摸奖券] 编号已用尽', [
                'activity_id' => $activityId,
                'sequence' => $sequence
            ]);

            throw new \Exception('活动奖券编号已用尽（超过100万张），请联系管理员');
        }

        // 格式化为6位数字符串
        return str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }
}
