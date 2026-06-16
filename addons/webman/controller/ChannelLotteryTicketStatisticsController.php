<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\LotteryTicket;
use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketBetProgress;
use addons\webman\model\LotteryTicketRecord;
use addons\webman\model\PlayerGameLog;
use ExAdmin\ui\response\Msg;
use ExAdmin\ui\response\Response;
use support\Db;
use support\Request;

/**
 * 渠道后台-摸奖券数据统计
 * @group channel
 */
class ChannelLotteryTicketStatisticsController
{
    /**
     * 获取活动详细统计数据
     * @auth true
     * @group channel
     * @return Msg|Response
     */
    public function getActivityStats()
    {
        $activityId = Request::input('activity_id');
        $departmentId = Admin::user()->department_id;

        $activity = LotteryTicketActivity::where('id', $activityId)
            ->where('department_id', $departmentId)
            ->first();

        if (!$activity) {
            return message_error(admin_trans('lottery_ticket.message.activity_not_found'));
        }

        // 核心统计数据
        $stats = [
            // 基本信息
            'activity_id' => $activity->id,
            'activity_name' => $activity->name,
            'status' => $activity->status,
            'status_text' => LotteryTicketActivity::getStatusText($activity->status),

            // 时间信息
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
            'time_progress' => $this->calculateTimeProgress($activity),

            // 参与统计
            'total_players' => $this->getTotalPlayers($activityId),
            'active_players' => $this->getActivePlayers($activityId),
            'player_growth' => $this->getPlayerGrowth($activityId),

            // 券号统计
            'total_tickets' => $activity->total_tickets,
            'used_tickets' => $activity->used_tickets,
            'unused_tickets' => $activity->total_tickets - $activity->used_tickets,
            'ticket_usage_rate' => $activity->total_tickets > 0
                ? round(($activity->used_tickets / $activity->total_tickets) * 100, 2)
                : 0,
            'current_ticket_no' => $activity->current_ticket_no,
            'max_ticket_no' => $activity->current_ticket_no > 0 ? str_pad($activity->current_ticket_no - 1, 6, '0', STR_PAD_LEFT) : '000000',

            // 打码统计
            'total_bet_amount' => $this->getTotalBetAmount($activityId),
            'daily_avg_bet' => $this->getDailyAvgBet($activityId),
            'player_avg_bet' => $this->getPlayerAvgBet($activityId),
            'bet_completion_rate' => $this->getBetCompletionRate($activityId),

            // 发券统计
            'tickets_by_source' => $this->getTicketsBySource($activityId),
            'tickets_by_vip' => $this->getTicketsByVipLevel($activityId),
        ];

        // ✅ 使用合并查询获取中奖统计（4次查询 → 1次查询）
        $winningStats = $this->getWinningStats($activityId);

        $stats = array_merge($stats, [
            // 中奖统计（使用合并查询结果）
            'winning_count' => $winningStats['winning_count'],
            'winning_players' => $winningStats['winning_players'],
            'total_prize_amount' => $winningStats['total_prize_amount'],
            'granted_prize_amount' => $winningStats['granted_prize_amount'],
            'prize_by_level' => $this->getPrizeByLevel($activityId),

            // 开奖状态（线下摇球，无ball_result字段）
            'has_drawn' => in_array($activity->status, [
                LotteryTicketActivity::STATUS_DRAWING,
                LotteryTicketActivity::STATUS_ENDED,
            ]),
        ]);

        return Response::success($stats);
    }

    /**
     * 获取打码实时排行榜
     * @auth true
     * @group channel
     * @return Msg|Response
     */
    public function getBetRanking()
    {
        $activityId = Request::input('activity_id');
        $type = Request::input('type', 'today'); // today, all
        $limit = Request::input('limit', 10);

        // ✅ 限制范围 1-100，防止DOS攻击
        $limit = min(max(1, (int)$limit), 100);

        $departmentId = Admin::user()->department_id;

        $activity = LotteryTicketActivity::where('id', $activityId)
            ->where('department_id', $departmentId)
            ->first();

        if (!$activity) {
            return message_error(admin_trans('lottery_ticket.message.activity_not_found'));
        }

        $query = LotteryTicketBetProgress::where('activity_id', $activityId)
            ->with(['player:id,name,uuid,vip_level_id', 'vipLevel:id,name']);

        if ($type === 'today') {
            // TODO: 需要添加今日打码字段
            $query->orderBy('current_bet_amount', 'desc');
        } else {
            $query->orderBy('current_bet_amount', 'desc');
        }

        $rankings = $query->limit($limit)->get();

        $formattedRankings = $rankings->map(function ($progress, $index) {
            return [
                'rank' => $index + 1,
                'player_id' => $progress->player_id,
                'player_name' => $progress->player->name ?? '-',
                'player_uuid' => $progress->player->uuid ?? '-',
                'vip_level' => $progress->vipLevel->name ?? 'VIP' . $progress->vip_level_id,
                'total_bet_amount' => $progress->current_bet_amount,
                'progress_percent' => $progress->progress_percent,
                'total_tickets' => $progress->total_tickets_issued,
                'cycles_completed' => $progress->cycles_completed,
            ];
        });

        return Response::success([
            'type' => $type,
            'rankings' => $formattedRankings,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 获取最近发券记录
     * @auth true
     * @group channel
     * @return Msg|Response
     */
    public function getRecentTickets()
    {
        $activityId = Request::input('activity_id');
        $limit = Request::input('limit', 20);

        // ✅ 限制范围 1-100，防止DOS攻击
        $limit = min(max(1, (int)$limit), 100);

        $departmentId = Admin::user()->department_id;

        $activity = LotteryTicketActivity::where('id', $activityId)
            ->where('department_id', $departmentId)
            ->first();

        if (!$activity) {
            return message_error(admin_trans('lottery_ticket.message.activity_not_found'));
        }

        $tickets = LotteryTicket::where('activity_id', $activityId)
            ->with(['player:id,name,uuid'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $formattedTickets = $tickets->map(function ($ticket) {
            return [
                'ticket_id' => $ticket->id,
                'ticket_no' => $ticket->ticket_no,
                'player_name' => $ticket->player->name ?? '-',
                'player_uuid' => $ticket->player->uuid ?? '-',
                'source' => $ticket->source,
                'source_text' => $this->getSourceText($ticket->source),
                'status' => $ticket->status,
                'status_text' => $this->getTicketStatusText($ticket->status),
                'created_at' => $ticket->created_at,
            ];
        });

        return Response::success([
            'tickets' => $formattedTickets,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 获取打码趋势数据（按小时）
     * @auth true
     * @group channel
     * @return Msg|Response
     */
    public function getBetTrend()
    {
        $activityId = Request::input('activity_id');
        $date = Request::input('date', date('Y-m-d'));
        $departmentId = Admin::user()->department_id;

        $activity = LotteryTicketActivity::where('id', $activityId)
            ->where('department_id', $departmentId)
            ->first();

        if (!$activity) {
            return message_error(admin_trans('lottery_ticket.message.activity_not_found'));
        }

        // ✅ 优化查询：只使用 whereBetween，可以使用索引
        // 计算查询时间范围：取日期范围和活动时间范围的交集
        $startDate = $date . ' 00:00:00';
        $endDate = $date . ' 23:59:59';

        // 取交集：查询时间范围必须在活动时间范围内
        $queryStart = max($startDate, $activity->start_time);
        $queryEnd = min($endDate, $activity->end_time);

        // 如果查询范围无效（活动不在这一天），返回空数据
        if ($queryStart > $queryEnd) {
            $trend = collect();
        } else {
            // 按小时统计打码量
            $trend = PlayerGameLog::where('department_id', $departmentId)
                ->whereBetween('created_at', [$queryStart, $queryEnd])
                ->select(
                    Db::raw('HOUR(created_at) as hour'),
                    Db::raw('SUM(chip_amount) as total_bet'),
                    Db::raw('COUNT(*) as bet_count')
                )
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();
        }

        // 填充24小时数据
        $hourlyData = array_fill(0, 24, [
            'total_bet' => 0,
            'bet_count' => 0,
        ]);

        foreach ($trend as $item) {
            $hourlyData[$item->hour] = [
                'total_bet' => $item->total_bet,
                'bet_count' => $item->bet_count,
            ];
        }

        $formattedTrend = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $formattedTrend[] = [
                'hour' => sprintf('%02d:00', $hour),
                'total_bet' => $hourlyData[$hour]['total_bet'],
                'bet_count' => $hourlyData[$hour]['bet_count'],
            ];
        }

        return Response::success([
            'date' => $date,
            'trend' => $formattedTrend,
        ]);
    }

    /**
     * 获取仪表板数据（活动概览）
     * @auth true
     * @group channel
     * @return Msg|Response
     */
    public function getDashboard()
    {
        $departmentId = Admin::user()->department_id;

        // 统计各状态活动数量
        $statusCounts = LotteryTicketActivity::where('department_id', $departmentId)
            ->select('status', Db::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // 进行中的活动简要信息（简化后只有2个进行中状态）
        $ongoingActivities = LotteryTicketActivity::where('department_id', $departmentId)
            ->whereIn('status', [
                LotteryTicketActivity::STATUS_ONGOING,
                LotteryTicketActivity::STATUS_DRAWING,
            ])
            ->orderBy('start_time', 'desc')
            ->get(['id', 'name', 'status', 'start_time', 'end_time', 'total_tickets', 'used_tickets']);

        // ✅ 批量获取活动统计（避免N+1查询）
        $activityIds = $ongoingActivities->pluck('id')->toArray();

        // 玩家数统计
        $playerCounts = LotteryTicketBetProgress::whereIn('activity_id', $activityIds)
            ->select('activity_id', Db::raw('COUNT(*) as count'))
            ->groupBy('activity_id')
            ->pluck('count', 'activity_id')
            ->toArray();

        // 中奖数统计
        $winningCounts = LotteryTicketRecord::whereIn('activity_id', $activityIds)
            ->select('activity_id', Db::raw('COUNT(*) as count'))
            ->groupBy('activity_id')
            ->pluck('count', 'activity_id')
            ->toArray();

        $formattedActivities = $ongoingActivities->map(function ($activity) use ($playerCounts, $winningCounts) {
            return [
                'id' => $activity->id,
                'name' => $activity->name,
                'status' => $activity->status,
                'status_text' => LotteryTicketActivity::getStatusText($activity->status),
                'status_color' => $this->getStatusColor($activity->status),
                'start_time' => $activity->start_time,
                'end_time' => $activity->end_time,
                'total_players' => $playerCounts[$activity->id] ?? 0,
                'total_tickets' => $activity->total_tickets,
                'used_tickets' => $activity->used_tickets,
                'winning_count' => $winningCounts[$activity->id] ?? 0,
            ];
        });

        return Response::success([
            'status_counts' => [
                'not_started' => $statusCounts[LotteryTicketActivity::STATUS_NOT_STARTED] ?? 0,
                'ongoing' => $statusCounts[LotteryTicketActivity::STATUS_ONGOING] ?? 0,
                'drawing' => $statusCounts[LotteryTicketActivity::STATUS_DRAWING] ?? 0,
                'ended' => $statusCounts[LotteryTicketActivity::STATUS_ENDED] ?? 0,
                'closed' => $statusCounts[LotteryTicketActivity::STATUS_CLOSED] ?? 0,
            ],
            'ongoing_activities' => $formattedActivities,
        ]);
    }

    // ==================== 辅助方法 ====================

    /**
     * 计算时间进度
     */
    protected function calculateTimeProgress(LotteryTicketActivity $activity): array
    {
        $now = time();
        $start = strtotime($activity->start_time);
        $end = strtotime($activity->end_time);

        if ($now < $start) {
            $percent = 0;
            $remainingDays = ceil(($start - $now) / 86400);
            $message = "距离开始还有 {$remainingDays} 天";
        } elseif ($now > $end) {
            $percent = 100;
            $message = "活动已结束";
        } else {
            $total = $end - $start;
            $passed = $now - $start;
            $percent = round(($passed / $total) * 100, 2);
            $remainingDays = ceil(($end - $now) / 86400);
            $message = "距离结束还有 {$remainingDays} 天";
        }

        return [
            'percent' => $percent,
            'message' => $message,
            'remaining_days' => $remainingDays ?? 0,
        ];
    }

    /**
     * 获取参与总人数
     */
    protected function getTotalPlayers(int $activityId): int
    {
        return LotteryTicketBetProgress::where('activity_id', $activityId)->count();
    }

    /**
     * 获取活跃玩家数（有打码的）
     */
    protected function getActivePlayers(int $activityId): int
    {
        return LotteryTicketBetProgress::where('activity_id', $activityId)
            ->where('current_bet_amount', '>', 0)
            ->count();
    }

    /**
     * 获取玩家增长率
     */
    protected function getPlayerGrowth(int $activityId): float
    {
        // TODO: 实现玩家增长率计算
        return 0;
    }

    /**
     * 获取总打码量
     */
    protected function getTotalBetAmount(int $activityId): float
    {
        return LotteryTicketBetProgress::where('activity_id', $activityId)
            ->sum('current_bet_amount') ?? 0;
    }

    /**
     * 获取日均打码量
     */
    protected function getDailyAvgBet(int $activityId): float
    {
        $activity = LotteryTicketActivity::find($activityId);
        if (!$activity) {
            return 0;
        }

        $days = max(1, ceil((time() - strtotime($activity->start_time)) / 86400));
        $total = $this->getTotalBetAmount($activityId);

        return round($total / $days, 2);
    }

    /**
     * 获取人均打码量
     */
    protected function getPlayerAvgBet(int $activityId): float
    {
        $total = $this->getTotalBetAmount($activityId);
        $players = $this->getActivePlayers($activityId);

        return $players > 0 ? round($total / $players, 2) : 0;
    }

    /**
     * 获取打码完成率
     */
    protected function getBetCompletionRate(int $activityId): float
    {
        $total = LotteryTicketBetProgress::where('activity_id', $activityId)->count();
        if ($total == 0) {
            return 0;
        }

        $completed = LotteryTicketBetProgress::where('activity_id', $activityId)
            ->where('cycles_completed', '>', 0)
            ->count();

        return round(($completed / $total) * 100, 2);
    }

    /**
     * 按来源统计券数
     */
    protected function getTicketsBySource(int $activityId): array
    {
        return LotteryTicket::where('activity_id', $activityId)
            ->select('source', Db::raw('COUNT(*) as count'))
            ->groupBy('source')
            ->pluck('count', 'source')
            ->toArray();
    }

    /**
     * 按VIP等级统计券数
     */
    protected function getTicketsByVipLevel(int $activityId): array
    {
        $result = LotteryTicketBetProgress::where('activity_id', $activityId)
            ->select('vip_level_id', Db::raw('SUM(total_tickets_issued) as total'))
            ->groupBy('vip_level_id')
            ->get();

        $formatted = [];
        foreach ($result as $item) {
            $formatted['VIP' . $item->vip_level_id] = $item->total;
        }

        return $formatted;
    }

    /**
     * ✅ 获取中奖统计（一次查询）
     * 替代原来的4个单独查询方法
     *
     * @param int $activityId 活动ID
     * @return array
     */
    protected function getWinningStats(int $activityId): array
    {
        $stats = LotteryTicketRecord::where('activity_id', $activityId)
            ->selectRaw('
                COUNT(*) as winning_count,
                COUNT(DISTINCT player_id) as winning_players,
                SUM(prize_amount) as total_prize_amount,
                SUM(CASE WHEN status = ? THEN prize_amount ELSE 0 END) as granted_prize_amount
            ', [LotteryTicketRecord::STATUS_GRANTED])
            ->first();

        return [
            'winning_count' => $stats->winning_count ?? 0,
            'winning_players' => $stats->winning_players ?? 0,
            'total_prize_amount' => $stats->total_prize_amount ?? 0,
            'granted_prize_amount' => $stats->granted_prize_amount ?? 0,
        ];
    }

    /**
     * 获取中奖数量（兼容旧方法）
     */
    protected function getWinningCount(int $activityId): int
    {
        return LotteryTicketRecord::where('activity_id', $activityId)->count();
    }

    /**
     * 获取中奖人数（兼容旧方法）
     */
    protected function getWinningPlayers(int $activityId): int
    {
        return LotteryTicketRecord::where('activity_id', $activityId)
            ->distinct('player_id')
            ->count('player_id');
    }

    /**
     * 获取总奖金（兼容旧方法）
     */
    protected function getTotalPrizeAmount(int $activityId): float
    {
        return LotteryTicketRecord::where('activity_id', $activityId)
            ->sum('prize_amount') ?? 0;
    }

    /**
     * 获取已发放奖金（兼容旧方法）
     */
    protected function getGrantedPrizeAmount(int $activityId): float
    {
        return LotteryTicketRecord::where('activity_id', $activityId)
            ->where('status', LotteryTicketRecord::STATUS_GRANTED)
            ->sum('prize_amount') ?? 0;
    }

    /**
     * 按等级统计奖金
     */
    protected function getPrizeByLevel(int $activityId): array
    {
        return LotteryTicketRecord::where('activity_id', $activityId)
            ->select('prize_name', Db::raw('COUNT(*) as count'), Db::raw('SUM(prize_amount) as amount'))
            ->groupBy('prize_name')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->prize_name => [
                    'count' => $item->count,
                    'amount' => $item->amount,
                ]];
            })
            ->toArray();
    }

    /**
     * 获取券状态文本
     */
    protected function getTicketStatusText($status): string
    {
        $map = [
            LotteryTicket::STATUS_UNUSED => admin_trans('lottery_ticket.ticket_status.unused'),
            LotteryTicket::STATUS_USED => admin_trans('lottery_ticket.ticket_status.used'),
            LotteryTicket::STATUS_EXPIRED => admin_trans('lottery_ticket.ticket_status.expired'),
        ];
        return $map[$status] ?? admin_trans('lottery_ticket.ticket_status.unknown');
    }

    /**
     * 获取来源文本
     */
    protected function getSourceText($source): string
    {
        $map = [
            'recharge' => admin_trans('lottery_ticket.source.recharge'),
            'activity' => admin_trans('lottery_ticket.source.activity'),
            'betting' => admin_trans('lottery_ticket.source.betting'),
            'manual' => admin_trans('lottery_ticket.source.manual'),
        ];
        return $map[$source] ?? admin_trans('lottery_ticket.source.unknown');
    }

    /**
     * 获取状态颜色
     */
    protected function getStatusColor(int $status): string
    {
        $colors = [
            LotteryTicketActivity::STATUS_NOT_STARTED => 'blue',
            LotteryTicketActivity::STATUS_ONGOING => 'green',
            LotteryTicketActivity::STATUS_DRAWING => 'yellow',
            LotteryTicketActivity::STATUS_ENDED => 'orange',
            LotteryTicketActivity::STATUS_CLOSED => 'red',
        ];
        return $colors[$status] ?? 'default';
    }
}
