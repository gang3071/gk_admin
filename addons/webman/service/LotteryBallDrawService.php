<?php

namespace addons\webman\service;

use addons\webman\model\LotteryTicket;
use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketPrizeLevel;
use addons\webman\model\LotteryTicketRecord;
use support\Db;
use support\Log;

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
     * 执行摇球开奖
     *
     * @param int $activityId 活动ID
     * @return array 开奖结果
     */
    public static function performDraw(int $activityId): array
    {
        $activity = LotteryTicketActivity::find($activityId);
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

        // 检查是否有发放的券
        $totalTickets = $activity->current_ticket_no;
        if ($totalTickets <= 0) {
            return ['success' => false, 'message' => '活动未发放任何摸奖券，无法开奖'];
        }

        // 计算最大券号（已发券数-1，因为从000000开始）
        $maxTicketNo = $totalTickets - 1;

        // 摇球
        $ballResult = self::drawBalls($maxTicketNo);

        // 根据摇球结果匹配中奖券号
        $winningTickets = self::matchWinningTickets($activity, $ballResult);

        // 保存开奖结果
        Db::beginTransaction();
        try {
            // 保存摇球结果
            $activity->ball_result = json_encode($ballResult, JSON_UNESCAPED_UNICODE);
            $activity->save();

            // 创建中奖记录
            $recordsCreated = 0;
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
                    'status' => LotteryTicketRecord::STATUS_PENDING,
                ]);

                // 更新摸奖券状态
                LotteryTicket::where('id', $winData['ticket_id'])
                    ->update(['status' => LotteryTicket::STATUS_USED]);

                $recordsCreated++;
            }

            // 更新活动已使用券数
            $activity->used_tickets = $activity->used_tickets + $recordsCreated;
            $activity->save();

            Db::commit();

            Log::info('摸奖券摇球开奖完成', [
                'activity_id' => $activity->id,
                'activity_name' => $activity->name,
                'total_tickets' => $totalTickets,
                'max_ticket_no' => $maxTicketNo,
                'ball_result' => $ballResult,
                'winning_count' => $recordsCreated,
            ]);

            return [
                'success' => true,
                'message' => "开奖成功，共产生 {$recordsCreated} 个中奖券",
                'data' => [
                    'ball_result' => $ballResult,
                    'winning_count' => $recordsCreated,
                    'winning_tickets' => $winningTickets,
                ],
            ];

        } catch (\Exception $e) {
            Db::rollBack();
            Log::error('摇球开奖失败', [
                'activity_id' => $activityId,
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
            if ($matchDigits <= 0 || $matchDigits > 6) {
                $matchDigits = 6; // 默认全匹配
            }

            // 截取中奖号码的后N位
            $matchPattern = substr($winningTicketNo, -$matchDigits);

            // 查找匹配的摸奖券
            $matchedTickets = LotteryTicket::where('activity_id', $activity->id)
                ->where('status', LotteryTicket::STATUS_UNUSED)
                ->where(Db::raw('RIGHT(ticket_no, ' . $matchDigits . ')'), '=', $matchPattern)
                ->limit($prizeCount)
                ->get();

            foreach ($matchedTickets as $ticket) {
                $winningData[] = [
                    'ticket_id' => $ticket->id,
                    'ticket_no' => $ticket->ticket_no,
                    'player_id' => $ticket->player_id,
                    'prize_type' => $prizeLevel->prize_type,
                    'prize_name' => $prizeLevel->level_name,
                    'prize_amount' => $prizeLevel->prize_amount,
                    'match_digits' => $matchDigits,
                ];
            }
        }

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
}
