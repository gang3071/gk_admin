<?php

namespace addons\webman\service;

use addons\webman\model\LotteryTicket;
use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketPrizeLevel;
use addons\webman\model\LotteryTicketRecord;
use support\Cache;
use support\Db;
use support\Log;
use support\Redis;

/**
 * 摸奖券摇球开奖服务
 *
 * 开奖方式：6个球摇号
 * - 球的范围基于已发券的最大券号
 * - 例如：发了15张券（000000~000014）
 *   - 最大券号：000014
 *   - 1号球（个位）：0~4
 *   - 2号球（十位）：0~1
 *   - 3-6号球（百千万十万位）：都是0
 */
class LotteryBallDrawService
{
    /**
     * 执行摇球开奖（带并发控制）
     *
     * @param int $activityId 活动ID
     * @return array 开奖结果
     */
    public static function performDraw(int $activityId): array
    {
        // ✅ 获取分布式锁（10秒超时）
        $lockKey = "lottery_draw_lock:{$activityId}";
        $lock = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            return ['success' => false, 'message' => '开奖正在进行中，请勿重复操作'];
        }

        try {
            // 使用悲观锁重新查询活动（防止并发问题）
            $activity = LotteryTicketActivity::lockForUpdate()->find($activityId);
            if (!$activity) {
                return ['success' => false, 'message' => '活动不存在'];
            }

            // 检查活动状态
            if ($activity->status != LotteryTicketActivity::STATUS_DRAWING) {
                return ['success' => false, 'message' => '活动状态不正确，只有开奖中的活动才能摇球'];
            }

            // 检查是否已开奖
            if (!empty($activity->ball_result)) {
                return ['success' => false, 'message' => '活动已完成开奖，不能重复开奖'];
            }

            // 继续原有开奖逻辑...
            return self::executeDrawing($activity);

        } finally {
            // 确保释放锁
            $lock->release();
        }
    }

    /**
     * 执行开奖逻辑（从原performDraw中分离）
     *
     * @param LotteryTicketActivity $activity
     * @return array
     */
    private static function executeDrawing(LotteryTicketActivity $activity): array
    {

        // 检查是否有发放的券
        $totalTickets = $activity->current_ticket_no;
        if ($totalTickets <= 0) {
            return ['success' => false, 'message' => '活动未发放任何摸奖券，无法开奖'];
        }

        // 验证实际券数（防止数据不一致）
        $actualTickets = LotteryTicket::where('activity_id', $activity->id)->count();
        if ($actualTickets == 0) {
            Log::error('摇球数据不一致：券记录丢失', [
                'activity_id' => $activity->id,
                'current_ticket_no' => $totalTickets,
                'actual_tickets' => $actualTickets,
            ]);
            return ['success' => false, 'message' => '数据异常：活动券记录丢失，请联系管理员'];
        }

        if ($actualTickets != $totalTickets) {
            Log::warning('摇球数据不一致：券数不匹配', [
                'activity_id' => $activity->id,
                'current_ticket_no' => $totalTickets,
                'actual_tickets' => $actualTickets,
                'diff' => abs($actualTickets - $totalTickets),
            ]);
        }

        // 计算最大券号（已发券数-1，因为从000000开始）
        $maxTicketNo = $totalTickets - 1;

        // 摇球
        $ballResult = self::drawBalls($maxTicketNo);

        // 根据摇球结果匹配中奖券号
        $winningTickets = self::matchWinningTickets($activity, $ballResult);

        // 保存开奖结果（不自动发放奖励）⭐
        Db::beginTransaction();
        try {
            // 保存摇球结果
            $activity->ball_result = json_encode($ballResult, JSON_UNESCAPED_UNICODE);

            // ⭐ 更新活动状态为已开奖待发放
            $activity->status = LotteryTicketActivity::STATUS_DRAWN;
            $activity->draw_completed_at = date('Y-m-d H:i:s');

            $activity->save();

            // 创建中奖记录（status=PENDING 待发放）
            $recordsCreated = 0;
            $totalPrizeAmount = 0; // ⭐ 统计总奖金
            $winningTicketIds = []; // 收集中奖券ID

            foreach ($winningTickets as $winData) {
                LotteryTicketRecord::create([
                    'activity_id' => $activity->id,
                    'player_id' => $winData['player_id'],
                    'department_id' => $activity->department_id,
                    'ticket_id' => $winData['ticket_id'],
                    'ticket_no' => $winData['ticket_no'],
                    'prize_type' => $winData['prize_type'],
                    'prize_name' => $winData['prize_name'],
                    'prize_amount' => $winData['prize_amount'],
                    'status' => LotteryTicketRecord::STATUS_PENDING, // ⭐ 待发放，不自动转账
                ]);

                // 收集中奖券ID
                $winningTicketIds[] = $winData['ticket_id'];

                // ⭐ 累计总奖金
                $totalPrizeAmount += $winData['prize_amount'];

                $recordsCreated++;
            }

            // ⭐ 更新中奖券状态为USED(1) - 中奖券也是已使用状态
            if (!empty($winningTicketIds)) {
                LotteryTicket::whereIn('id', $winningTicketIds)
                    ->update(['status' => LotteryTicket::STATUS_USED]);  // ✅ 使用 STATUS_USED
            }

            // ⭐ 更新未中奖券状态为USED(1)
            LotteryTicket::where('activity_id', $activity->id)
                ->where('status', LotteryTicket::STATUS_UNUSED)  // ✅ 使用正确的常量
                ->whereNotIn('id', $winningTicketIds)
                ->update(['status' => LotteryTicket::STATUS_USED]);

            // ⭐ 更新活动总奖金金额
            $activity->total_prize_amount = $totalPrizeAmount;
            $activity->distributed_prize_amount = 0; // 已发放金额初始为0
            $activity->save();

            Db::commit();

            // ❌ 不再清除缓存、不推送通知（发放时才推送）

            Log::info('[摸奖券] 开奖完成，等待管理员发放', [
                'activity_id' => $activity->id,
                'activity_name' => $activity->name,
                'total_tickets' => $totalTickets,
                'max_ticket_no' => $maxTicketNo,
                'ball_result' => $ballResult,
                'winning_count' => $recordsCreated,
                'total_prize_amount' => $totalPrizeAmount,
                'status' => 'DRAWN (待发放)',
            ]);

            // ❌ 不推送开奖结果（发放时才推送中奖通知）
            // 旧代码已移除：
            // LotteryTicketPushService::pushDrawResult($activity, $ballResult, $recordsCreated);

            return [
                'success' => true,
                'message' => "开奖成功，共产生 {$recordsCreated} 个中奖记录（待发放），总奖金 ¥" . number_format($totalPrizeAmount, 2), // ⭐ 更新提示
                'data' => [
                    'ball_result' => $ballResult,
                    'winning_count' => $recordsCreated,
                    'total_prize_amount' => $totalPrizeAmount, // ⭐ 新增
                    'winning_tickets' => $winningTickets,
                    'status' => LotteryTicketActivity::STATUS_DRAWN, // ⭐ 新增
                ],
            ];

        } catch (\Exception $e) {
            Db::rollBack();
            Log::error('摇球开奖失败', [
                'activity_id' => $activity->id, // ⭐ 修复：使用$activity->id
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'success' => false,
                'message' => '开奖失败：' . $e->getMessage(),
            ];
        }
    }

    /**
     * 摇6个球
     *
     * @param int $maxTicketNo 最大券号（数字，不是字符串）
     * @return array 摇球结果
     */
    protected static function drawBalls(int $maxTicketNo): array
    {
        // 将最大券号转为6位数组
        $maxDigits = str_split(str_pad($maxTicketNo, 6, '0', STR_PAD_LEFT));

        $balls = [];

        // 从右往左摇球（个位 -> 十万位）
        for ($position = 5; $position >= 0; $position--) {
            $maxDigit = (int)$maxDigits[$position];

            // 该位的范围：0 ~ maxDigit
            $balls[$position] = mt_rand(0, $maxDigit);
        }

        return [
            'ball1' => $balls[5], // 个位
            'ball2' => $balls[4], // 十位
            'ball3' => $balls[3], // 百位
            'ball4' => $balls[2], // 千位
            'ball5' => $balls[1], // 万位
            'ball6' => $balls[0], // 十万位
            'winning_no' => sprintf('%d%d%d%d%d%d', $balls[0], $balls[1], $balls[2], $balls[3], $balls[4], $balls[5]),
        ];
    }

    /**
     * 根据摇球结果匹配中奖券号
     *
     * @param LotteryTicketActivity $activity 活动
     * @param array $ballResult 摇球结果
     * @return array 中奖数据
     */
    protected static function matchWinningTickets(LotteryTicketActivity $activity, array $ballResult): array
    {
        // 组合中奖券号
        $winningTicketNo = $ballResult['winning_no'];

        // 获取奖品等级配置（按等级排名从小到大，即奖金从大到小）
        $prizeLevels = LotteryTicketPrizeLevel::where('activity_id', $activity->id)
            ->orderBy('level_rank', 'asc')
            ->get();

        if ($prizeLevels->isEmpty()) {
            Log::warning('活动未配置奖品等级', ['activity_id' => $activity->id]);
            return [];
        }

        $winningData = [];
        $usedTicketIds = []; // ✅ 记录已中奖的券ID，防止重复中奖

        // 从高等奖开始匹配
        foreach ($prizeLevels as $prizeLevel) {
            $prizeCount = $prizeLevel->prize_count;

            if ($prizeCount <= 0) {
                continue;
            }

            // 根据等级排名决定匹配规则
            // 等级1（特等奖）：匹配6位全中
            // 等级2（一等奖）：匹配后5位
            // 等级3（二等奖）：匹配后4位
            // 等级4（三等奖）：匹配后3位
            // 等级5（四等奖）：匹配后2位
            // 等级6（五等奖）：匹配后1位

            $matchDigits = 7 - $prizeLevel->level_rank; // 匹配位数

            // 验证等级配置合法性
            if ($matchDigits <= 0 || $matchDigits > 6) {
                Log::error('奖品等级配置错误', [
                    'activity_id' => $activity->id,
                    'prize_level_id' => $prizeLevel->id,
                    'level_rank' => $prizeLevel->level_rank,
                    'match_digits' => $matchDigits,
                ]);
                continue; // 跳过错误配置
            }

            // 截取中奖号码的后N位
            $matchPattern = substr($winningTicketNo, -$matchDigits);

            // ✅ 查找匹配的摸奖券（排除过期券和已中奖券）
            $query = LotteryTicket::where('activity_id', $activity->id)
                ->where('status', LotteryTicket::STATUS_UNUSED)
                ->where(function ($query) {
                    // 排除已过期的券
                    $query->whereNull('expired_at')
                          ->orWhere('expired_at', '>', date('Y-m-d H:i:s'));
                })
                ->where(Db::raw('RIGHT(ticket_no, ' . $matchDigits . ')'), '=', $matchPattern);

            // ✅ 关键：排除已中奖的券
            if (!empty($usedTicketIds)) {
                $query->whereNotIn('id', $usedTicketIds);
            }

            $matchedTickets = $query->limit($prizeCount)->get();

            foreach ($matchedTickets as $ticket) {
                $winningData[] = [
                    'ticket_id' => $ticket->id,
                    'ticket_no' => $ticket->ticket_no,
                    'player_id' => $ticket->player_id,
                    'prize_type' => $prizeLevel->prize_type,
                    'prize_name' => $prizeLevel->level_name,
                    'prize_amount' => $prizeLevel->prize_amount,
                    'match_digits' => $matchDigits,
                    'level_rank' => $prizeLevel->level_rank, // ✅ 记录等级排名
                ];

                // ✅ 记录已使用的券ID
                $usedTicketIds[] = $ticket->id;
            }
        }

        // ✅ 日志记录匹配统计
        Log::info('[摸奖券] 中奖匹配完成', [
            'activity_id' => $activity->id,
            'winning_no' => $winningTicketNo,
            'total_winners' => count($winningData),
            'unique_tickets' => count($usedTicketIds),
            'prize_levels_count' => $prizeLevels->count()
        ]);

        return $winningData;
    }

    /**
     * 模拟摇球（用于前端展示动画）
     *
     * @param int $maxTicketNo 最大券号
     * @return array 每个球的范围
     */
    public static function getBallRanges(int $maxTicketNo): array
    {
        $maxDigits = str_split(str_pad($maxTicketNo, 6, '0', STR_PAD_LEFT));

        return [
            'ball1' => ['min' => 0, 'max' => (int)$maxDigits[5]], // 个位
            'ball2' => ['min' => 0, 'max' => (int)$maxDigits[4]], // 十位
            'ball3' => ['min' => 0, 'max' => (int)$maxDigits[3]], // 百位
            'ball4' => ['min' => 0, 'max' => (int)$maxDigits[2]], // 千位
            'ball5' => ['min' => 0, 'max' => (int)$maxDigits[1]], // 万位
            'ball6' => ['min' => 0, 'max' => (int)$maxDigits[0]], // 十万位
            'max_ticket_no' => str_pad($maxTicketNo, 6, '0', STR_PAD_LEFT),
        ];
    }

    /**
     * 清除中奖玩家的有效奖券缓存
     * @param array $playerIds 中奖玩家ID列表
     */
    private static function clearWinningPlayerCache(array $playerIds)
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

            Log::info('[摸奖券] 开奖后清除玩家缓存', [
                'winning_players' => count($playerIds),
                'cleared_count' => $clearedCount
            ]);

        } catch (\Exception $e) {
            Log::warning('[摸奖券] 缓存清除失败', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
