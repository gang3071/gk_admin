<?php

namespace addons\webman\service;

use addons\webman\model\GameType;
use addons\webman\model\PlayerActivityPhaseRecord;
use addons\webman\model\PlayerGameLog;
use addons\webman\model\PlayerLotteryRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * 机台报表实时聚合
 *
 * 由明细表直接聚合，不再依赖 machine_report 预聚合表。
 *
 * 背景：machine_report 的 open_amount / wash_amount / open_point / wash_point
 * 唯一累加入口是 PlayerGameLog::booted()，该链路 2026-07-22 之后失效，
 * 导致机台报表上下点长期为 0，而上下分报表（player_game_log）数据正常。
 *
 * 口径与「上下分报表」的游戏点符号一致：
 *  - 开分（ACTION_OPEN）：钱从钱包流出，记「机器上点」
 *  - 其余（弃台 / 下分）：钱回到钱包，记「机器下点」
 *
 * 彩金点数 / 活动点数不在 player_game_log 上，分别取自
 * player_lottery_record / player_activity_phase_record。
 *
 * 机台盈亏（machine_total_point）= 机器上点 - 机器下点 - 活动点数。
 * 彩金走独立彩池，不计入机台盈亏，报表单独以「彩金点数」列展示。
 */
class MachineReportAggregator
{
    /**
     * 构建按 machine_id + odds 分组的机台报表查询
     *
     * @param array $exAdminFilter 后台筛选条件（date_start/date_end/date_type/department_id/is_test/machine[...]）
     * @param \Closure|null $extraFilter 额外过滤（数据权限等），签名 function($query, string $table): void
     * @return Builder
     */
    public static function build(array $exAdminFilter, ?\Closure $extraFilter = null): Builder
    {
        $openAction = PlayerGameLog::ACTION_OPEN;

        $gameLog = PlayerGameLog::query();
        static::applyFilters($gameLog, $exAdminFilter, 'player_game_log.created_at', $extraFilter);

        $gameLog->selectRaw("
            player_game_log.machine_id AS machine_id,
            player_game_log.odds AS odds,
            SUM(CASE WHEN player_game_log.action = {$openAction}
                     THEN ABS(player_game_log.game_amount) ELSE 0 END) AS open_amount,
            SUM(CASE WHEN player_game_log.action <> {$openAction} AND player_game_log.game_amount > 0
                     THEN player_game_log.game_amount ELSE 0 END) AS wash_amount,
            SUM(CASE WHEN player_game_log.action <> {$openAction}
                     THEN player_game_log.game_amount
                     ELSE -ABS(player_game_log.game_amount) END) AS total_amount,
            SUM(player_game_log.open_point) AS open_point,
            SUM(player_game_log.wash_point) AS wash_point,
            SUM(player_game_log.open_point - player_game_log.wash_point) AS total_point,
            SUM(player_game_log.pressure) AS total_pressure,
            SUM(player_game_log.score) AS total_score,
            SUM(player_game_log.pressure - player_game_log.score) AS total_diff,
            SUM(player_game_log.turn_point) AS total_turn_point,
            COALESCE(lot_sub.lottery_amount, 0) AS lottery_amount,
            COALESCE(act_sub.activity_amount, 0) AS activity_amount,
            SUM(CASE WHEN player_game_log.action = {$openAction}
                     THEN ABS(player_game_log.game_amount) ELSE 0 END)
              - SUM(CASE WHEN player_game_log.action <> {$openAction} AND player_game_log.game_amount > 0
                     THEN player_game_log.game_amount ELSE 0 END)
              - COALESCE(act_sub.activity_amount, 0) AS machine_total_point
        ");

        $gameLog->leftJoinSub(static::lotterySub($exAdminFilter, $extraFilter), 'lot_sub', function ($join) {
            $join->on('lot_sub.machine_id', '=', 'player_game_log.machine_id')
                ->on('lot_sub.odds', '=', 'player_game_log.odds');
        });
        $gameLog->leftJoinSub(static::activitySub($exAdminFilter, $extraFilter), 'act_sub', function ($join) {
            $join->on('act_sub.machine_id', '=', 'player_game_log.machine_id')
                ->on('act_sub.odds', '=', 'player_game_log.odds');
        });

        $gameLog->groupBy('player_game_log.machine_id', 'player_game_log.odds');

        return $gameLog;
    }

    /**
     * 彩金点数子查询：player_lottery_record
     */
    private static function lotterySub(array $exAdminFilter, ?\Closure $extraFilter): Builder
    {
        $sub = PlayerLotteryRecord::query();
        static::applyFilters($sub, $exAdminFilter, 'player_lottery_record.created_at', $extraFilter);
        $sub->where('player_lottery_record.source', PlayerLotteryRecord::SOURCE_MACHINE)
            ->where('player_lottery_record.status', PlayerLotteryRecord::STATUS_COMPLETE)
            ->selectRaw('
                player_lottery_record.machine_id AS machine_id,
                player_lottery_record.odds AS odds,
                SUM(player_lottery_record.amount) AS lottery_amount')
            ->groupBy('player_lottery_record.machine_id', 'player_lottery_record.odds');

        return $sub;
    }

    /**
     * 活动点数子查询：player_activity_phase_record
     *
     * 该表没有 odds 列，按 PlayerActivityPhaseRecord::booted() 的同一套规则
     * 从机台配置推导（钢珠取 machine_category.name，其余取 odds_x:odds_y）。
     */
    private static function activitySub(array $exAdminFilter, ?\Closure $extraFilter): Builder
    {
        $machineTable = (new \addons\webman\model\Machine())->getTable();
        $categoryTable = (new \addons\webman\model\MachineCategory())->getTable();

        $sub = PlayerActivityPhaseRecord::query();
        static::applyFilters($sub, $exAdminFilter, 'player_activity_phase_record.created_at', $extraFilter);
        $sub->where('player_activity_phase_record.status', PlayerActivityPhaseRecord::STATUS_COMPLETE)
            ->join($machineTable, $machineTable . '.id', '=', 'player_activity_phase_record.machine_id')
            ->leftJoin($categoryTable, $categoryTable . '.id', '=', $machineTable . '.cate_id')
            ->selectRaw("
                player_activity_phase_record.machine_id AS machine_id,
                CASE WHEN {$machineTable}.type = " . GameType::TYPE_STEEL_BALL . "
                     THEN {$categoryTable}.name
                     ELSE CONCAT({$machineTable}.odds_x, ':', {$machineTable}.odds_y) END AS odds,
                SUM(player_activity_phase_record.bonus) AS activity_amount")
            ->groupBy('player_activity_phase_record.machine_id', 'odds');

        return $sub;
    }

    /**
     * 筛选条件统一应用到三个明细查询
     *
     * 注意：明细表的 created_at 是 timestamp，起止都要按「整天」收边，
     * 否则 date_end 会把当天数据截掉。
     *
     * @param \Closure|null $extraFilter function($query, string $table): void
     */
    private static function applyFilters($query, array $exAdminFilter, string $timeColumn, ?\Closure $extraFilter): void
    {
        $table = explode('.', $timeColumn)[0];

        if ($extraFilter) {
            $extraFilter($query, $table);
        }

        if (!empty($exAdminFilter['date_start'])) {
            $query->where($timeColumn, '>=', Carbon::parse($exAdminFilter['date_start'])->startOfDay());
        }
        if (!empty($exAdminFilter['date_end'])) {
            $query->where($timeColumn, '<', Carbon::parse($exAdminFilter['date_end'])->addDay()->startOfDay());
        }
        if (!empty($exAdminFilter['department_id'])) {
            $query->where($table . '.department_id', $exAdminFilter['department_id']);
        }
        // is_test 只有游戏日志和彩金记录有；活动记录无此列
        if (!empty($exAdminFilter['is_test']) && in_array($table, ['player_game_log', 'player_lottery_record'], true)) {
            $query->where($table . '.is_test', '=', $exAdminFilter['is_test']);
        }
        if (isset($exAdminFilter['date_type'])) {
            static::applyDateType($query, $exAdminFilter['date_type'], $timeColumn);
        }
        if (!empty($exAdminFilter['machine'])) {
            $query->whereHas('machine', function ($q) use ($exAdminFilter) {
                if (!empty($exAdminFilter['machine']['name'])) {
                    $q->whereHas('machineLabel', function ($q) use ($exAdminFilter) {
                        $q->where('name', 'like', '%' . $exAdminFilter['machine']['name'] . '%');
                    });
                }
                if (!empty($exAdminFilter['machine']['code'])) {
                    $q->where('code', $exAdminFilter['machine']['code']);
                }
                if (!empty($exAdminFilter['machine']['producer_id'])) {
                    $q->where('producer_id', $exAdminFilter['machine']['producer_id']);
                }
                if (!empty($exAdminFilter['machine']['cate_id'])) {
                    $q->whereIn('cate_id', $exAdminFilter['machine']['cate_id']);
                }
            });
        }
    }

    /**
     * 结算周期筛选（timestamp 列需整天闭区间，不能直接用 getWhereDate）
     */
    private static function applyDateType($query, $type, string $timeColumn): void
    {
        [$from, $to] = match ((int)$type) {
            1 => [Carbon::today(), Carbon::today()],                                  // 今日
            2 => [Carbon::yesterday(), Carbon::yesterday()],                          // 昨日
            3 => [Carbon::today()->startOfWeek(), Carbon::today()->endOfWeek()],       // 本周
            4 => [Carbon::today()->subWeek()->startOfWeek(), Carbon::today()->subWeek()->endOfWeek()], // 上周
            5 => [Carbon::today()->firstOfMonth(), Carbon::today()->endOfMonth()],     // 本月
            6 => [Carbon::today()->subMonthNoOverflow()->firstOfMonth(), Carbon::today()->subMonthNoOverflow()->endOfMonth()], // 上月
            default => [null, null],
        };

        if ($from === null) {
            return;
        }

        $query->where($timeColumn, '>=', $from->startOfDay())
            ->where($timeColumn, '<', $to->copy()->addDay()->startOfDay());
    }
}
