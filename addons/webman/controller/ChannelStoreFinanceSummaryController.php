<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\grid\SummaryGrid;
use addons\webman\model\AdminUser;
use addons\webman\model\GameType;
use addons\webman\model\PlayGameRecord;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerLotteryRecord;
use addons\webman\model\PlayerReverseWaterDetail;
use addons\webman\model\TicketRecord;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\navigation\Pagination;
use ExAdmin\ui\support\Request;

/**
 * 渠道后台 - 门店财务汇总报表
 * @group channel
 */
class ChannelStoreFinanceSummaryController
{
    private const AMOUNT_COLUMNS = [
        'electronic_game_win_loss',
        'machine_win_loss',
        'lottery_ticket_sent',
        'electronic_jackpot',
        'machine_jackpot',
        'wallet_increase',
        'wallet_decrease',
        'machine_rebate',
        'electronic_rebate',
        'coupon_sent',
    ];

    /**
     * 门店财务汇总报表
     * @group channel
     * @auth true
     */
    public function finance(): Grid
    {
        /** @var AdminUser $admin */
        $admin = Admin::user();

        $exAdminFilter = Request::input('ex_admin_filter', []);
        $createdAtStart = $exAdminFilter['created_at_start'] ?? null;
        $createdAtEnd   = $exAdminFilter['created_at_end'] ?? null;
        $dateType       = $exAdminFilter['date_type'] ?? null;
        $selectedStoreId = $exAdminFilter['store_id'] ?? null;
        $selectedAgentId = $exAdminFilter['agent_id'] ?? null;

        // 查询店家（1条SQL）
        $storesQuery = AdminUser::query()
            ->select(['id', 'nickname', 'username', 'parent_admin_id'])
            ->where('department_id', $admin->department_id)
            ->where('type', AdminUser::TYPE_STORE)
            ->where('status', 1)
            ->orderBy('id', 'desc');

        if (!empty($selectedAgentId)) {
            $storesQuery->where('parent_admin_id', $selectedAgentId);
        }
        if (!empty($selectedStoreId)) {
            $storesQuery->where('id', $selectedStoreId);
        }

        $stores   = $storesQuery->get();
        $storeIds = $stores->pluck('id')->toArray();

        if (empty($storeIds)) {
            return $this->buildEmptyGrid();
        }

        // 批量查询代理名称（1条SQL）
        $agentIds = $stores->pluck('parent_admin_id')->filter()->unique()->values()->toArray();
        $agents = AdminUser::query()
            ->whereIn('id', $agentIds)
            ->get(['id', 'nickname', 'username'])
            ->keyBy('id');

        // 批量查询玩家（按店家分组，1条SQL）
        $allPlayers = Player::query()
            ->whereIn('store_admin_id', $storeIds)
            ->where('is_promoter', 0)
            ->get(['id', 'store_admin_id']);

        $playerIdsByStore = [];
        foreach ($allPlayers->groupBy('store_admin_id') as $storeId => $players) {
            $playerIdsByStore[$storeId] = $players->pluck('id')->toArray();
        }

        // 批量查询各类财务数据
        $deliveryByStore      = $this->batchQueryDeliveryData($playerIdsByStore, $dateType, $createdAtStart, $createdAtEnd);
        $lotteryByStore       = $this->batchQueryLotteryData($playerIdsByStore, $dateType, $createdAtStart, $createdAtEnd);
        $couponByStore        = $this->batchQueryCouponData($storeIds, $dateType, $createdAtStart, $createdAtEnd);
        $gameWinLossByStore   = $this->batchQueryGameWinLoss($playerIdsByStore, $dateType, $createdAtStart, $createdAtEnd);
        $elecRebateByStore    = $this->batchQueryElecRebate($playerIdsByStore, $dateType, $createdAtStart, $createdAtEnd);

        // 组装报表数据
        $reportData = [];
        foreach ($stores as $store) {
            $storeId = $store->id;
            $agent   = $agents->get($store->parent_admin_id);
            $d  = $deliveryByStore[$storeId] ?? null;
            $l  = $lotteryByStore[$storeId] ?? null;
            $c  = $couponByStore[$storeId] ?? null;
            $g  = $gameWinLossByStore[$storeId] ?? null;
            $er = $elecRebateByStore[$storeId] ?? null;

            // 实体机输赢 = 开分 + 投钞 - 洗分 (平台视角)
            $machineWinLoss = bcsub(
                bcadd(
                    (string)floatval($d->open_score ?? 0),
                    (string)floatval($d->machine_put ?? 0),
                    2
                ),
                (string)floatval($d->withdraw ?? 0),
                2
            );

            $reportData[] = [
                'id'                       => $store->id,
                'store_name'               => $store->nickname ?: $store->username,
                'store_username'           => $store->username,
                'agent_name'               => $agent ? ($agent->nickname ?: $agent->username) : '-',
                'electronic_game_win_loss' => floatval($g->win_loss ?? 0),
                'machine_win_loss'         => floatval($machineWinLoss),
                'lottery_ticket_sent'      => floatval($d->lottery_ticket_sent ?? 0),
                'electronic_jackpot'       => floatval($l->electronic_jackpot ?? 0),
                'machine_jackpot'          => floatval($l->machine_jackpot ?? 0),
                'wallet_increase'          => floatval($d->wallet_increase ?? 0),
                'wallet_decrease'          => floatval($d->wallet_decrease ?? 0),
                'machine_rebate'           => 0,
                'electronic_rebate'        => floatval($er->reverse_water ?? 0),
                'coupon_sent'              => floatval($c->coupon_sent ?? 0),
            ];
        }

        $storeOptions = $this->getStoreOptions($admin->department_id);
        $agentOptions = $this->getAgentOptions($admin->department_id);

        return SummaryGrid::create($reportData, function (SummaryGrid $grid) use ($reportData, $storeOptions, $agentOptions) {
            $grid->title(admin_trans('channel_store_finance_summary.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            $this->buildGridColumns($grid);
            $this->buildPageSummary($grid, $reportData);
            $this->buildGridFilter($grid, $storeOptions, $agentOptions);

            $grid->hideAction();
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideAdd();
            $grid->expandFilter();
            $grid->attr('is_mongo', true);
            $grid->attr('is_mongo_total', count($reportData));
            $grid->attr('mongo_model', $reportData);
        });
    }

    // ========== 批量查询方法 ==========

    /**
     * 批量查询账变数据（1条SQL覆盖所有所需字段）
     */
    private function batchQueryDeliveryData(
        array $playerIdsByStore,
        ?string $dateType,
        ?string $start,
        ?string $end
    ): array {
        $allPlayerIds = array_merge(...array_values($playerIdsByStore ?: [[]]));

        if (empty($allPlayerIds)) {
            return [];
        }

        $increaseTypes = implode(',', [
            PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD,  // 1 管理后台加点
            PlayerDeliveryRecord::TYPE_PRESENT_IN,            // 2 玩家转入
            PlayerDeliveryRecord::TYPE_WITHDRAWAL_BACK,       // 9 提现失败返还
            PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS,        // 10 活动奖金
            PlayerDeliveryRecord::TYPE_REGISTER_PRESENT,      // 11 注册赠送
            PlayerDeliveryRecord::TYPE_PROFIT,                // 12 推广员分润
            PlayerDeliveryRecord::TYPE_NATIONAL_INVITE,       // 16 全民代理邀请奖励
            PlayerDeliveryRecord::TYPE_RECHARGE_REWARD,       // 17 全民代理首充奖励
            PlayerDeliveryRecord::TYPE_DAMAGE_REBATE,         // 18 全民代理客损返佣
            PlayerDeliveryRecord::COIN_ADD,                   // 20 币商加点
            PlayerDeliveryRecord::TYPE_AGENT_IN,              // 25 代理玩家转入
        ]);

        $decreaseTypes = implode(',', [
            PlayerDeliveryRecord::TYPE_PRESENT_OUT,           // 3 币商转出
            PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT, // 8 管理后台扣点
            PlayerDeliveryRecord::COIN_DEDUCT,                // 21 币商扣点
            PlayerDeliveryRecord::TYPE_AGENT_OUT,             // 24 代理玩家转出
        ]);

        $query = PlayerDeliveryRecord::query()->whereIn('player_id', $allPlayerIds);
        $this->applyTimeFilter($query, 'created_at', $dateType, $start, $end);

        $rows = $query->selectRaw("
            player_id,
            SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_RECHARGE . " AND `source` = 'artificial_recharge' THEN `amount` ELSE 0 END) AS open_score,
            SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_MACHINE . " THEN `amount` ELSE 0 END) AS machine_put,
            SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " AND `source` = 'channel_withdrawal' THEN `amount` ELSE 0 END) AS withdraw,
            SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD . " THEN `amount` ELSE 0 END) AS lottery_ticket_sent,
            SUM(CASE WHEN `type` IN ({$increaseTypes}) THEN `amount` ELSE 0 END) AS wallet_increase,
            SUM(CASE WHEN `type` IN ({$decreaseTypes}) THEN `amount` ELSE 0 END) AS wallet_decrease
        ")->groupBy('player_id')->get()->keyBy('player_id');

        $result = [];
        foreach ($playerIdsByStore as $storeId => $playerIds) {
            $agg = (object)[
                'open_score'          => 0.0,
                'machine_put'         => 0.0,
                'withdraw'            => 0.0,
                'lottery_ticket_sent' => 0.0,
                'wallet_increase'     => 0.0,
                'wallet_decrease'     => 0.0,
            ];
            foreach ($playerIds as $pid) {
                $row = $rows->get($pid);
                if ($row) {
                    $agg->open_score          += floatval($row->open_score);
                    $agg->machine_put         += floatval($row->machine_put);
                    $agg->withdraw            += floatval($row->withdraw);
                    $agg->lottery_ticket_sent += floatval($row->lottery_ticket_sent);
                    $agg->wallet_increase     += floatval($row->wallet_increase);
                    $agg->wallet_decrease     += floatval($row->wallet_decrease);
                }
            }
            $result[$storeId] = $agg;
        }

        return $result;
    }

    /**
     * 批量查询彩金数据（1条SQL）
     */
    private function batchQueryLotteryData(
        array $playerIdsByStore,
        ?string $dateType,
        ?string $start,
        ?string $end
    ): array {
        $allPlayerIds = array_merge(...array_values($playerIdsByStore ?: [[]]));

        if (empty($allPlayerIds)) {
            return [];
        }

        $query = PlayerLotteryRecord::query()
            ->whereIn('player_id', $allPlayerIds)
            ->where('status', PlayerLotteryRecord::STATUS_COMPLETE);
        $this->applyTimeFilter($query, 'created_at', $dateType, $start, $end);

        $rows = $query->selectRaw("
            player_id,
            SUM(CASE WHEN `source` IN (" . PlayerLotteryRecord::SOURCE_GAME . ", " . PlayerLotteryRecord::SOURCE_MANUAL . ") THEN `amount` ELSE 0 END) AS electronic_jackpot,
            SUM(CASE WHEN `game_type` IN (" . GameType::TYPE_SLOT . ", " . GameType::TYPE_STEEL_BALL . ") THEN `amount` ELSE 0 END) AS machine_jackpot
        ")->groupBy('player_id')->get()->keyBy('player_id');

        $result = [];
        foreach ($playerIdsByStore as $storeId => $playerIds) {
            $elec    = 0.0;
            $machine = 0.0;
            foreach ($playerIds as $pid) {
                $row = $rows->get($pid);
                if ($row) {
                    $elec    += floatval($row->electronic_jackpot);
                    $machine += floatval($row->machine_jackpot);
                }
            }
            $result[$storeId] = (object)[
                'electronic_jackpot' => $elec,
                'machine_jackpot'    => $machine,
            ];
        }

        return $result;
    }

    /**
     * 批量查询体验/福利劵数据（1条SQL）
     */
    private function batchQueryCouponData(
        array $storeIds,
        ?string $dateType,
        ?string $start,
        ?string $end
    ): array {
        $query = TicketRecord::query()->whereIn('store_admin_id', $storeIds);
        $this->applyTimeFilter($query, 'created_at', $dateType, $start, $end);

        $rows = $query->selectRaw("
            CAST(store_admin_id AS UNSIGNED) AS store_admin_id,
            SUM(CASE WHEN `ticket_type` IN (" . TicketRecord::TYPE_EXPERIENCE . ", " . TicketRecord::TYPE_WELFARE . ")
                AND `status` != " . TicketRecord::STATUS_DISABLED . " THEN `score` ELSE 0 END) AS coupon_sent
        ")->groupBy('store_admin_id')->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row->store_admin_id] = $row;
        }
        return $result;
    }

    /**
     * 批量查询电子游戏输赢（来自 play_game_record.diff，1条SQL）
     * diff > 0 表示玩家输（平台盈利），diff < 0 表示玩家赢（平台亏损）
     */
    private function batchQueryGameWinLoss(
        array $playerIdsByStore,
        ?string $dateType,
        ?string $start,
        ?string $end
    ): array {
        $allPlayerIds = array_merge(...array_values($playerIdsByStore ?: [[]]));

        if (empty($allPlayerIds)) {
            return [];
        }

        $query = PlayGameRecord::query()
            ->whereIn('player_id', $allPlayerIds)
            ->where('type', PlayGameRecord::TYPE_BET)
            ->where('settlement_status', PlayGameRecord::SETTLEMENT_STATUS_SETTLED);
        $this->applyTimeFilter($query, 'created_at', $dateType, $start, $end);

        $rows = $query->selectRaw('player_id, SUM(diff) AS win_loss')
            ->groupBy('player_id')
            ->get()
            ->keyBy('player_id');

        $result = [];
        foreach ($playerIdsByStore as $storeId => $playerIds) {
            $total = 0.0;
            foreach ($playerIds as $pid) {
                $row = $rows->get($pid);
                if ($row) {
                    $total += floatval($row->win_loss);
                }
            }
            $result[$storeId] = (object)['win_loss' => $total];
        }

        return $result;
    }

    /**
     * 批量查询电子游戏返水（来自 player_reverse_water_detail，1条SQL）
     * 按 date 字段（日期格式）过滤
     */
    private function batchQueryElecRebate(
        array $playerIdsByStore,
        ?string $dateType,
        ?string $start,
        ?string $end
    ): array {
        $allPlayerIds = array_merge(...array_values($playerIdsByStore ?: [[]]));

        if (empty($allPlayerIds)) {
            return [];
        }

        $query = PlayerReverseWaterDetail::query()->whereIn('player_id', $allPlayerIds);

        if (!empty($dateType)) {
            $query->where(getDateWhere($dateType, 'date'));
        } else {
            if (!empty($start)) {
                $query->where('date', '>=', date('Y-m-d', strtotime($start)));
            }
            if (!empty($end)) {
                $query->where('date', '<=', date('Y-m-d', strtotime($end)));
            }
        }

        $rows = $query->selectRaw('player_id, SUM(reverse_water) AS reverse_water')
            ->groupBy('player_id')
            ->get()
            ->keyBy('player_id');

        $result = [];
        foreach ($playerIdsByStore as $storeId => $playerIds) {
            $total = 0.0;
            foreach ($playerIds as $pid) {
                $row = $rows->get($pid);
                if ($row) {
                    $total += floatval($row->reverse_water);
                }
            }
            $result[$storeId] = (object)['reverse_water' => $total];
        }

        return $result;
    }

    // ========== 筛选条件方法 ==========

    private function applyTimeFilter($query, string $column, ?string $dateType, ?string $start, ?string $end): void
    {
        if (!empty($dateType)) {
            $query->where(getDateWhere($dateType, $column));
        } else {
            if (!empty($start)) {
                $query->where($column, '>', $start);
            }
            if (!empty($end)) {
                $query->where($column, '<=', $end);
            }
        }
    }

    // ========== UI 构建方法 ==========

    private function buildEmptyGrid(): Grid
    {
        return Grid::create([], function (Grid $grid) {
            $grid->title(admin_trans('channel_store_finance_summary.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->hideAction();
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideAdd();
        });
    }

    private function buildGridColumns(SummaryGrid $grid): void
    {
        $grid->column('id', 'ID')->width(80)->align('center');
        $grid->column('store_name', admin_trans('channel_store_finance_summary.fields.store_name'))->width(150)->align('center');
        $grid->column('store_username', admin_trans('channel_store_finance_summary.fields.store_username'))->width(120)->align('center');
        $grid->column('agent_name', admin_trans('channel_store_finance_summary.fields.agent_name'))->width(120)->align('center');

        foreach (self::AMOUNT_COLUMNS as $column) {
            $grid->column($column, admin_trans("channel_store_finance_summary.fields.{$column}"))
                ->display(function ($value) {
                    $v     = floatval($value);
                    $color = $v >= 0 ? '#3f8600' : '#cf1322';
                    return Html::create(number_format($v, 2))->style(['color' => $color]);
                })->width(130)->align('center');
        }
    }

    private function buildPageSummary(SummaryGrid $grid, array $reportData): void
    {
        $page     = max(1, (int)Request::input('ex_admin_page', 1));
        $size     = max(1, (int)Request::input('ex_admin_size', Pagination::create()->attr('pageSize')));
        $pageData = array_slice($reportData, ($page - 1) * $size, $size);

        if (empty($pageData)) {
            return;
        }

        $totals = [];
        foreach (self::AMOUNT_COLUMNS as $field) {
            $total = '0';
            foreach ($pageData as $row) {
                $total = bcadd($total, (string)floatval($row[$field] ?? 0), 2);
            }
            $totals[$field] = floatval($total);
        }

        $summaryRow = [
            'id'             => 0,
            'store_name'     => admin_trans('channel_store_finance_summary.summary.row_label'),
            'store_username' => '',
            'agent_name'     => '',
        ];
        foreach ($totals as $field => $total) {
            $summaryRow[$field] = $total;
        }

        $grid->summaryRow($summaryRow, []);
    }

    private function buildGridFilter(SummaryGrid $grid, array $storeOptions, array $agentOptions): void
    {
        $grid->filter(function (Filter $filter) use ($storeOptions, $agentOptions) {
            $filter->eq()->select('agent_id')
                ->placeholder(admin_trans('channel_store_finance_summary.filter.select_agent'))
                ->options(['' => admin_trans('channel_store_finance_summary.filter.all_agents')] + $agentOptions)
                ->style(['width' => '250px']);

            $filter->eq()->select('store_id')
                ->placeholder(admin_trans('channel_store_finance_summary.filter.select_store'))
                ->options(['' => admin_trans('channel_store_finance_summary.filter.all_stores')] + $storeOptions)
                ->style(['width' => '300px']);

            $filter->select('date_type')
                ->placeholder(admin_trans('machine_report.fields.date_type'))
                ->showSearch()
                ->dropdownMatchSelectWidth()
                ->style(['width' => '200px'])
                ->options([
                    1 => admin_trans('machine_report.date_type.1'),
                    2 => admin_trans('machine_report.date_type.2'),
                    3 => admin_trans('machine_report.date_type.3'),
                    4 => admin_trans('machine_report.date_type.4'),
                    5 => admin_trans('machine_report.date_type.5'),
                    6 => admin_trans('machine_report.date_type.6'),
                ]);

            $filter->form()->hidden('created_at_start');
            $filter->form()->hidden('created_at_end');
            $filter->form()->dateTimeRange('created_at_start', 'created_at_end', admin_trans('channel_store_finance_summary.filter.time_range'))
                ->placeholder([
                    admin_trans('channel_store_finance_summary.filter.start_time'),
                    admin_trans('channel_store_finance_summary.filter.end_time'),
                ]);
        });
    }

    private function getStoreOptions(int $departmentId): array
    {
        return AdminUser::query()
            ->where('department_id', $departmentId)
            ->where('type', AdminUser::TYPE_STORE)
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->get(['id', 'nickname', 'username'])
            ->mapWithKeys(function ($store) {
                $label = ($store->nickname ?: $store->username) . " ({$store->username})";
                return [$store->id => $label];
            })
            ->toArray();
    }

    private function getAgentOptions(int $departmentId): array
    {
        return AdminUser::query()
            ->where('department_id', $departmentId)
            ->where('type', AdminUser::TYPE_AGENT)
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->get(['id', 'nickname', 'username'])
            ->mapWithKeys(function ($agent) {
                $label = ($agent->nickname ?: $agent->username) . " ({$agent->username})";
                return [$agent->id => $label];
            })
            ->toArray();
    }
}