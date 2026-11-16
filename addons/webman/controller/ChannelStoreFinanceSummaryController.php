<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\GameType;
use addons\webman\model\MachineCategory;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerGameLog;
use addons\webman\model\PlayerLotteryRecord;
use addons\webman\model\PlayGameRecord;
use addons\webman\model\TicketRecord;
use ExAdmin\ui\support\Request;
use support\Db;
use support\Response;

/**
 * 渠道后台 - 门店财务汇总报表
 * @group channel
 */
class ChannelStoreFinanceSummaryController
{
    /**
     * 门店财务汇总页面
     * @group channel
     * @auth true
     */
    public function finance()
    {
        $t     = fn(string $key) => admin_trans('channel_store_finance_summary.' . $key);
        $admin = Admin::user();

        return admin_view(plugin()->webman->getPath() . '/views/store_finance_summary.vue')
            ->attrs([
                // ── Tab 1：多店概覽（原有功能）──
                'trans' => [
                    'filter' => [
                        'placeholder_agent'  => $t('filter.placeholder_agent'),
                        'placeholder_stores' => $t('filter.placeholder_stores'),
                        'placeholder_date'   => $t('filter.placeholder_date'),
                        'start_time'         => $t('filter.start_time'),
                        'end_time'           => $t('filter.end_time'),
                        'query'              => $t('filter.query'),
                        'reset'              => $t('filter.reset'),
                    ],
                    'date_types' => [
                        ['value' => 1, 'label' => $t('date_types.today')],
                        ['value' => 2, 'label' => $t('date_types.yesterday')],
                        ['value' => 3, 'label' => $t('date_types.this_week')],
                        ['value' => 4, 'label' => $t('date_types.last_week')],
                        ['value' => 5, 'label' => $t('date_types.this_month')],
                        ['value' => 6, 'label' => $t('date_types.last_month')],
                    ],
                    'panel' => [
                        'machine_win_loss'    => $t('panel.machine_win_loss'),
                        'electronic_win_loss' => $t('panel.electronic_win_loss'),
                        'net_revenue'         => $t('panel.net_revenue'),
                        'operating_cost'      => $t('panel.operating_cost'),
                        'cost_detail'         => $t('panel.cost_detail'),
                        'wallet_detail'       => $t('panel.wallet_detail'),
                        'total'               => $t('panel.total'),
                        'profit'              => $t('panel.profit'),
                        'loss'                => $t('panel.loss'),
                        'even'                => $t('panel.even'),
                    ],
                    'states' => [
                        'hint'          => $t('states.hint'),
                        'search_failed' => $t('states.search_failed'),
                        'retry'         => $t('states.retry'),
                        'no_data'       => $t('states.no_data'),
                    ],
                    'cost_types' => [
                        // 运营成本：平台额外发放给玩家的奖励（计入净营收扣除项）
                        12 => $t('delivery_types.12'),  // 推广员分润
                        13 => $t('delivery_types.13'),  // 彩金中奖
                        33 => $t('delivery_types.33'),  // 摸奖券奖励
                        34 => $t('delivery_types.34'),  // VIP生日礼金
                        35 => $t('delivery_types.35'),  // VIP升级礼金
                        36 => $t('delivery_types.36'),  // 反水池返水
                        37 => $t('delivery_types.37'),  // VIP每日登录
                        38 => $t('delivery_types.38'),  // 活动外增
                        39 => $t('delivery_types.39'),  // 排行榜奖励
                    ],
                    'wallet_types' => [
                        // 钱包流水参考：余额变动记录，不计入营收计算
                        1  => $t('delivery_types.1'),   // 管理员加点
                        4  => $t('delivery_types.4'),   // 机台上分
                        5  => $t('delivery_types.5'),   // 机台下分
                        6  => $t('delivery_types.6'),   // 充值(开分)
                        7  => $t('delivery_types.7'),   // 提现(洗分)
                        8  => $t('delivery_types.8'),   // 管理员扣点
                        22 => $t('delivery_types.22'),  // 特殊类型
                        23 => $t('delivery_types.23'),  // 投钞
                        31 => $t('delivery_types.31'),  // 预扣
                        32 => $t('delivery_types.32'),  // 退款
                    ],
                    'desc_panel' => [
                        'title'      => $t('desc_panel.title'),
                        'click_hint' => $t('desc_panel.click_hint'),
                    ],
                ],
                // ── Tab 2：單店詳細報表（新功能）──
                'store_options'     => $this->getDetailStoreOptions($admin->department_id),
                'detail_search_url' => 'ex-admin/addons-webman-controller-ChannelStoreFinanceSummaryController/searchDetail',
            ]);
    }

    // ================================================================
    // Tab 1：多店概覽 - 原有查詢
    // ================================================================

    /**
     * 多店概覽數據查詢（AJAX GET）
     * @group channel
     * @auth true
     */
    public function search(): Response
    {
        /** @var AdminUser $admin */
        $admin = Admin::user();

        $agentId   = Request::input('agent_id');
        $storeIds  = Request::input('store_ids');   // 逗号分隔字符串或数组
        $dateType  = Request::input('date_type');
        $startTime = Request::input('start_time');
        $endTime   = Request::input('end_time');

        // 解析多选店家 ID
        if (is_string($storeIds) && $storeIds !== '') {
            $storeIds = array_filter(array_map('intval', explode(',', $storeIds)));
        } elseif (!is_array($storeIds)) {
            $storeIds = [];
        } else {
            $storeIds = array_filter(array_map('intval', $storeIds));
        }

        $agents = $this->getAgentOptions($admin->department_id);
        $stores = $this->getStoreOptions($admin->department_id);

        $hasFilter = !empty($dateType) || !empty($startTime) || !empty($endTime)
                     || !empty($storeIds) || !empty($agentId);

        if (!$hasFilter) {
            return json(['code' => 200, 'data' => ['agents' => $agents, 'stores' => $stores, 'panels' => []]]);
        }

        // 查询门店
        $storesQuery = AdminUser::query()
            ->select(['id', 'nickname', 'username', 'parent_admin_id'])
            ->where('department_id', $admin->department_id)
            ->where('type', AdminUser::TYPE_STORE)
            ->where('status', 1)
            ->orderBy('id', 'desc');

        if (!empty($agentId)) {
            $storesQuery->where('parent_admin_id', $agentId);
        }
        if (!empty($storeIds)) {
            $storesQuery->whereIn('id', $storeIds);
        }

        $storeList = $storesQuery->get();

        if ($storeList->isEmpty()) {
            return json(['code' => 200, 'data' => ['agents' => $agents, 'stores' => $stores, 'panels' => []]]);
        }

        $storeIdList = $storeList->pluck('id')->toArray();

        // 代理名称
        $agentIds = $storeList->pluck('parent_admin_id')->filter()->unique()->values()->toArray();
        $agentMap = AdminUser::query()->whereIn('id', $agentIds)->get(['id', 'nickname', 'username'])->keyBy('id');

        // 玩家分组（按店家）
        $allPlayers = Player::query()
            ->whereIn('store_admin_id', $storeIdList)
            ->where('is_promoter', 0)
            ->get(['id', 'store_admin_id']);

        $playerIdsByStore = [];
        foreach ($allPlayers->groupBy('store_admin_id') as $sid => $players) {
            $playerIdsByStore[$sid] = $players->pluck('id')->toArray();
        }

        // 批量查询
        $deliveryByStore    = $this->batchQueryDeliveryTypes($playerIdsByStore, $dateType, $startTime, $endTime);
        $gameWinLossByStore = $this->batchQueryGameWinLoss($playerIdsByStore, $dateType, $startTime, $endTime);
        $machineWinLoss     = $this->calcMachineWinLoss($deliveryByStore);

        $panels = [];
        foreach ($storeList as $store) {
            $sid   = $store->id;
            $agent = $agentMap->get($store->parent_admin_id);

            $panels[] = [
                'store_id'                 => $sid,
                'store_name'               => $store->nickname ?: $store->username,
                'store_username'           => $store->username,
                'agent_name'               => $agent ? ($agent->nickname ?: $agent->username) : '-',
                // machine_win_loss 已是平台视角（正数=盈利），电子游戏取反（diff 为玩家视角）
                'machine_win_loss'         => $machineWinLoss[$sid] ?? '0.00',
                'electronic_game_win_loss' => bcsub('0', bcadd('0', (string)floatval($gameWinLossByStore[$sid] ?? 0), 2), 2),
                'delivery_types'           => $deliveryByStore[$sid] ?? [],
            ];
        }

        return json(['code' => 200, 'data' => [
            'agents' => $agents,
            'stores' => $stores,
            'panels' => $panels,
        ]]);
    }

    // ================================================================
    // Tab 2：單店詳細報表 - 新查詢
    // ================================================================

    /**
     * 單店5段財務報表查詢（AJAX POST）
     * @group channel
     * @auth true
     */
    public function searchDetail(): Response
    {
        /** @var AdminUser $admin */
        $admin   = Admin::user();
        $storeId = (int)Request::input('store_id', 0);
        $start   = Request::input('start_date', '');
        $end     = Request::input('end_date', '');

        if (empty($storeId)) {
            return json(['code' => 400, 'msg' => '請選擇門店']);
        }

        $store = AdminUser::query()
            ->where('id', $storeId)
            ->where('department_id', $admin->department_id)
            ->where('type', AdminUser::TYPE_STORE)
            ->first();

        if (!$store) {
            return json(['code' => 403, 'msg' => '無權限訪問該門店']);
        }

        $playerIds = Player::query()
            ->where('store_admin_id', $storeId)
            ->where('is_promoter', 0)
            ->pluck('id')
            ->toArray();

        if (empty($playerIds)) {
            return json(['code' => 200, 'data' => $this->buildEmptyData()]);
        }

        $overviewData      = $this->queryOverviewData($playerIds, $start, $end);
        $welfareData       = $this->queryWelfareData($playerIds, $start, $end);
        $couponData        = $this->queryCouponData($storeId, $start, $end);
        $machineDelivery   = $this->queryMachineDeliveryData($playerIds, $start, $end);
        $electronicGame    = $this->queryElectronicGameData($playerIds, $start, $end);
        $electronicLottery = $this->queryElectronicLotteryData($playerIds, $start, $end);
        $machineLottery    = $this->queryMachineLotteryData($playerIds, $start, $end);
        $slotPressure      = $this->querySlotPressureScoreData($playerIds, $start, $end);

        return json(['code' => 200, 'data' => $this->buildReportData(
            $overviewData, $welfareData, $couponData, $machineDelivery,
            $electronicGame, $electronicLottery, $machineLottery, $slotPressure
        )]);
    }

    // ================================================================
    // Tab 1 私有查詢方法
    // ================================================================

    /**
     * 按 player_id + type 聚合所有账变类型，返回 [store_id => [type => amount_str]]
     */
    private function batchQueryDeliveryTypes(array $playerIdsByStore, ?string $dateType, ?string $start, ?string $end): array
    {
        $allPlayerIds = array_merge(...array_values($playerIdsByStore ?: [[]]));
        if (empty($allPlayerIds)) {
            return [];
        }

        $query = PlayerDeliveryRecord::query()->whereIn('player_id', $allPlayerIds);
        $this->applyTimeFilter($query, 'created_at', $dateType, $start, $end);

        $rows = $query->selectRaw('player_id, `type`, SUM(amount) AS total')
            ->groupBy(['player_id', 'type'])
            ->get();

        // 按 player_id 建立索引
        $byPlayer = [];
        foreach ($rows as $r) {
            $byPlayer[(int)$r->player_id][(int)$r->type] = floatval($r->total);
        }

        // 聚合到 store 层级
        $result = [];
        foreach ($playerIdsByStore as $storeId => $playerIds) {
            $typeTotals = [];
            foreach ($playerIds as $pid) {
                if (!isset($byPlayer[$pid])) continue;
                foreach ($byPlayer[$pid] as $type => $amount) {
                    $typeTotals[$type] = ($typeTotals[$type] ?? 0.0) + $amount;
                }
            }
            // 格式化为字符串，只保留非零项
            $formatted = [];
            foreach ($typeTotals as $type => $amount) {
                if (abs($amount) > 0.001) {
                    $formatted[$type] = number_format($amount, 2, '.', '');
                }
            }
            $result[$storeId] = $formatted;
        }
        return $result;
    }

    /**
     * 实体机台输赢（平台视角，正数=盈利）
     * 机台上分(type4)：扣玩家余额 = 玩家充值机台 = 平台收入
     * 机台下分(type5)：增玩家余额 = 机台返还玩家 = 平台支出
     * 平台盈利 = type4 - type5
     */
    private function calcMachineWinLoss(array $deliveryByStore): array
    {
        $result = [];
        foreach ($deliveryByStore as $storeId => $types) {
            $machineUp   = floatval($types[PlayerDeliveryRecord::TYPE_MACHINE_UP]   ?? 0); // 4=平台收入
            $machineDown = floatval($types[PlayerDeliveryRecord::TYPE_MACHINE_DOWN] ?? 0); // 5=平台支出
            $result[$storeId] = bcsub((string)$machineUp, (string)$machineDown, 2);
        }
        return $result;
    }

    private function batchQueryGameWinLoss(array $playerIdsByStore, ?string $dateType, ?string $start, ?string $end): array
    {
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
            ->groupBy('player_id')->get()->keyBy('player_id');

        $result = [];
        foreach ($playerIdsByStore as $storeId => $playerIds) {
            $total = 0.0;
            foreach ($playerIds as $pid) {
                if ($row = $rows->get($pid)) {
                    $total += floatval($row->win_loss);
                }
            }
            $result[$storeId] = $total;
        }
        return $result;
    }

    private function applyTimeFilter($query, string $column, ?string $dateType, ?string $start, ?string $end): void
    {
        if (!empty($dateType)) {
            $query->where(getDateWhere($dateType, $column));
        } else {
            if (!empty($start)) $query->where($column, '>=', $start);
            if (!empty($end))   $query->where($column, '<=', $end);
        }
    }

    // ================================================================
    // Tab 2 私有查詢方法
    // ================================================================

    /**
     * 查询总入/总出数据：总入=投钞(type=23)+充值(type=6)，总出=提现(type=7)
     * @return array ['total_in' => float, 'total_out' => float]
     */
    private function queryOverviewData(array $playerIds, string $start, string $end): array
    {
        $query = PlayerDeliveryRecord::query()
            ->whereIn('player_id', $playerIds)
            ->whereIn('type', [
                PlayerDeliveryRecord::TYPE_MACHINE,    // 23 投钞
                PlayerDeliveryRecord::TYPE_RECHARGE,   // 6  充值/开分
                PlayerDeliveryRecord::TYPE_WITHDRAWAL, // 7  提现/洗分
            ])
            ->selectRaw('`type`, COALESCE(SUM(`amount`), 0) as total')
            ->groupBy('type');

        $this->applyCreatedAtFilter($query, $start, $end);

        $byType = [];
        foreach ($query->get() as $row) {
            $byType[(int)$row->type] = (float)$row->total;
        }

        $totalIn = bcadd(
            (string)($byType[PlayerDeliveryRecord::TYPE_MACHINE]   ?? 0.0),
            (string)($byType[PlayerDeliveryRecord::TYPE_RECHARGE]  ?? 0.0),
            2
        );
        $totalOut = (string)($byType[PlayerDeliveryRecord::TYPE_WITHDRAWAL] ?? 0.0);

        return [
            'total_in'  => (float)$totalIn,
            'total_out' => (float)$totalOut,
        ];
    }

    /**
     * 查询福利赠点数据（types: 33=摸彩奖, 34=VIP生日, 35=VIP升级, 37=VIP每日登录, 38=活动外增）
     * @return array [type => float]
     */
    private function queryWelfareData(array $playerIds, string $start, string $end): array
    {
        $welfareTypes = [
            PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD,
            PlayerDeliveryRecord::TYPE_BIRTHDAY_BONUS,
            PlayerDeliveryRecord::TYPE_VIP_UPGRADE_BONUS,
            PlayerDeliveryRecord::TYPE_VIP_DAILY_LOGIN_BONUS,
            PlayerDeliveryRecord::TYPE_ACTIVITY_GIVE,
        ];

        $query = PlayerDeliveryRecord::query()
            ->whereIn('player_id', $playerIds)
            ->whereIn('type', $welfareTypes)
            ->selectRaw('`type`, SUM(`amount`) as total')
            ->groupBy('type');

        $this->applyCreatedAtFilter($query, $start, $end);

        $result = [];
        foreach ($query->get() as $row) {
            $result[(int)$row->type] = (float)$row->total;
        }

        return $result;
    }

    /**
     * 查询体验券/福利券数据
     * @return array [ticket_type => float]
     */
    private function queryCouponData(int $storeId, string $start, string $end): array
    {
        $query = TicketRecord::query()
            ->where('store_admin_id', $storeId)
            ->whereIn('ticket_type', [TicketRecord::TYPE_EXPERIENCE, TicketRecord::TYPE_WELFARE])
            ->where('status', '!=', TicketRecord::STATUS_DISABLED)
            ->selectRaw('ticket_type, SUM(score) as total')
            ->groupBy('ticket_type');

        $this->applyCreatedAtFilter($query, $start, $end);

        $result = [];
        foreach ($query->get() as $row) {
            $result[(int)$row->ticket_type] = (float)$row->total;
        }

        return $result;
    }

    /**
     * 查询实体机台上分/下分点数，按 game_type 和 machine_source 分组。
     *
     * 不 JOIN machine/machine_category，直接用 player_game_log 自身的
     * game_id（1=斯洛/2=钢珠）和 source_type（1=线上/2=线下）字段，
     * 避免 machine_id=0 或 JOIN 路径断裂导致数据丢失。
     *
     * game_amount 符号因写入方不同而不统一（gk_work 上分写正、gk_api 写负、
     * 投钞写 0），统一用 ABS 处理。
     *
     * @return array [game_type][machine_source][TYPE_MACHINE_UP|TYPE_MACHINE_DOWN] => float
     */
    private function queryMachineDeliveryData(array $playerIds, string $start, string $end): array
    {
        $pglTable   = plugin()->webman->config('database.player_game_log_table');
        $openAction = PlayerGameLog::ACTION_OPEN; // 1

        $query = Db::table($pglTable)
            ->whereIn('player_id', $playerIds)
            ->where('machine_id', '>', 0)
            ->whereIn('game_id', [GameType::TYPE_SLOT, GameType::TYPE_STEEL_BALL])
            ->selectRaw("game_id, source_type,
                         SUM(CASE WHEN action = {$openAction} THEN ABS(game_amount) ELSE 0 END) AS total_open,
                         SUM(CASE WHEN action <> {$openAction} THEN ABS(game_amount) ELSE 0 END) AS total_wash")
            ->groupBy('game_id', 'source_type');

        if (!empty($start)) {
            $query->where('created_at', '>=', $start);
        }
        if (!empty($end)) {
            $query->where('created_at', '<=', $end);
        }

        // source_type 1=线上 / 2=线下，与 MachineCategory::MACHINE_SOURCE_* 值相同
        $result = [];
        foreach ($query->get() as $row) {
            $gameType      = (int)$row->game_id;
            $machineSource = (int)$row->source_type;

            $result[$gameType][$machineSource][PlayerDeliveryRecord::TYPE_MACHINE_UP]   = (float)$row->total_open;
            $result[$gameType][$machineSource][PlayerDeliveryRecord::TYPE_MACHINE_DOWN] = (float)$row->total_wash;
        }

        return $result;
    }

    /**
     * 查询电子游戏数据
     * @return object {total_bet, total_win, total_diff}
     */
    private function queryElectronicGameData(array $playerIds, string $start, string $end): object
    {
        $query = PlayGameRecord::query()
            ->whereIn('player_id', $playerIds)
            ->where('settlement_status', PlayGameRecord::SETTLEMENT_STATUS_SETTLED)
            ->selectRaw('COALESCE(SUM(bet), 0) as total_bet, COALESCE(SUM(win), 0) as total_win, COALESCE(SUM(diff), 0) as total_diff');

        $this->applyCreatedAtFilter($query, $start, $end);

        return $query->first() ?? (object)['total_bet' => 0, 'total_win' => 0, 'total_diff' => 0];
    }

    /**
     * 查询电子游戏彩金总额
     */
    private function queryElectronicLotteryData(array $playerIds, string $start, string $end): float
    {
        $query = PlayerLotteryRecord::query()
            ->whereIn('player_id', $playerIds)
            ->where('status', PlayerLotteryRecord::STATUS_COMPLETE)
            ->whereIn('source', [PlayerLotteryRecord::SOURCE_GAME, PlayerLotteryRecord::SOURCE_MANUAL]);

        $this->applyCreatedAtFilter($query, $start, $end);

        return (float)$query->sum('amount');
    }

    /**
     * 查询实体机台彩金数据，按 game_type 和 machine_source 分组
     * @return array [game_type][machine_source] => float
     */
    private function queryMachineLotteryData(array $playerIds, string $start, string $end): array
    {
        $plrTable  = plugin()->webman->config('database.player_lottery_record_table') ?? 'player_lottery_record';
        $machTable = plugin()->webman->config('database.machine_table');
        $cateTable = plugin()->webman->config('database.machine_category_table');

        $query = Db::table("{$plrTable} as plr")
            ->join("{$machTable} as m", 'plr.machine_id', '=', 'm.id')
            ->join("{$cateTable} as mc", 'm.cate_id', '=', 'mc.id')
            ->whereIn('plr.player_id', $playerIds)
            ->where('plr.status', PlayerLotteryRecord::STATUS_COMPLETE)
            ->whereIn('plr.game_type', [GameType::TYPE_SLOT, GameType::TYPE_STEEL_BALL])
            ->where('plr.machine_id', '>', 0)
            ->selectRaw('plr.game_type, mc.machine_source, SUM(plr.amount) as total_amount')
            ->groupBy('plr.game_type', 'mc.machine_source');

        if (!empty($start)) {
            $query->where('plr.created_at', '>=', $start);
        }
        if (!empty($end)) {
            $query->where('plr.created_at', '<=', $end);
        }

        $result = [];
        foreach ($query->get() as $row) {
            $gameType      = (int)$row->game_type;
            $machineSource = (int)$row->machine_source;

            $result[$gameType][$machineSource] = (float)$row->total_amount;
        }

        return $result;
    }

    /**
     * 查询斯洛机台壓分/得分数据，按 machine_source 分组。
     * 直接用 player_game_log.game_id 和 source_type，不 JOIN machine/machine_category。
     * @return array [machine_source] => ['pressure' => float, 'score' => float]
     */
    private function querySlotPressureScoreData(array $playerIds, string $start, string $end): array
    {
        $pglTable = plugin()->webman->config('database.player_game_log_table');

        $query = Db::table($pglTable)
            ->whereIn('player_id', $playerIds)
            ->where('machine_id', '>', 0)
            ->where('game_id', GameType::TYPE_SLOT)
            ->selectRaw('source_type, COALESCE(SUM(pressure), 0) as total_pressure, COALESCE(SUM(score), 0) as total_score')
            ->groupBy('source_type');

        if (!empty($start)) {
            $query->where('created_at', '>=', $start);
        }
        if (!empty($end)) {
            $query->where('created_at', '<=', $end);
        }

        // source_type 1=线上 / 2=线下，与 MachineCategory::MACHINE_SOURCE_* 值相同
        $result = [];
        foreach ($query->get() as $row) {
            $machineSource = (int)$row->source_type;

            $result[$machineSource] = [
                'pressure' => (float)$row->total_pressure,
                'score'    => (float)$row->total_score,
            ];
        }

        return $result;
    }

    // ================================================================
    // Tab 2 數據組裝
    // ================================================================

    private function buildReportData(
        array $overviewData,
        array $welfareData,
        array $couponData,
        array $machineDelivery,
        object $electronicGame,
        float $electronicLottery,
        array $machineLottery,
        array $slotPressure
    ): array {
        $slot      = GameType::TYPE_SLOT;
        $steelBall = GameType::TYPE_STEEL_BALL;
        $online    = MachineCategory::MACHINE_SOURCE_ONLINE;
        $offline   = MachineCategory::MACHINE_SOURCE_OFFLINE;
        $upType    = PlayerDeliveryRecord::TYPE_MACHINE_UP;
        $downType  = PlayerDeliveryRecord::TYPE_MACHINE_DOWN;

        // --- 鋼珠 ---
        $steelOfflineUp   = $machineDelivery[$steelBall][$offline][$upType]   ?? 0.0;
        $steelOfflineDown = $machineDelivery[$steelBall][$offline][$downType] ?? 0.0;
        $steelOnlineUp    = $machineDelivery[$steelBall][$online][$upType]    ?? 0.0;
        $steelOnlineDown  = $machineDelivery[$steelBall][$online][$downType]  ?? 0.0;
        $steelOfflineLottery = $machineLottery[$steelBall][$offline] ?? 0.0;
        $steelOnlineLottery  = $machineLottery[$steelBall][$online]  ?? 0.0;

        $steelOfflineSubtotal = bcsub((string)$steelOfflineUp, (string)$steelOfflineDown, 2);
        $steelOnlineSubtotal  = bcsub((string)$steelOnlineUp, (string)$steelOnlineDown, 2);
        $steelOfflineProfit   = bcsub((string)$steelOfflineSubtotal, (string)$steelOfflineLottery, 2);
        $steelOnlineProfit    = bcsub((string)$steelOnlineSubtotal, (string)$steelOnlineLottery, 2);

        // --- 斯洛 ---
        $slotOfflineUp   = $machineDelivery[$slot][$offline][$upType]   ?? 0.0;
        $slotOfflineDown = $machineDelivery[$slot][$offline][$downType] ?? 0.0;
        $slotOnlineUp    = $machineDelivery[$slot][$online][$upType]    ?? 0.0;
        $slotOnlineDown  = $machineDelivery[$slot][$online][$downType]  ?? 0.0;
        $slotOfflineLottery = $machineLottery[$slot][$offline] ?? 0.0;
        $slotOnlineLottery  = $machineLottery[$slot][$online]  ?? 0.0;

        $slotOfflineSubtotal  = bcsub((string)$slotOfflineUp, (string)$slotOfflineDown, 2);
        $slotOnlineSubtotal   = bcsub((string)$slotOnlineUp, (string)$slotOnlineDown, 2);
        $slotOfflineProfit    = bcsub((string)$slotOfflineSubtotal, (string)$slotOfflineLottery, 2);
        $slotOnlineProfit     = bcsub((string)$slotOnlineSubtotal, (string)$slotOnlineLottery, 2);

        $slotOfflinePressure = $slotPressure[$offline]['pressure'] ?? 0.0;
        $slotOfflineScore    = $slotPressure[$offline]['score']    ?? 0.0;
        $slotOnlinePressure  = $slotPressure[$online]['pressure']  ?? 0.0;
        $slotOnlineScore     = $slotPressure[$online]['score']     ?? 0.0;

        $slotOfflinePressProfit = bcsub((string)$slotOfflinePressure, (string)$slotOfflineScore, 2);
        $slotOnlinePressProfit  = bcsub((string)$slotOnlinePressure, (string)$slotOnlineScore, 2);

        // --- 總覽：总入=投钞(type=23)+充值(type=6)，总出=提现(type=7) ---
        $totalIn  = (string)($overviewData['total_in']  ?? 0.0);
        $totalOut = (string)($overviewData['total_out'] ?? 0.0);

        $machineLotteryTotal = bcadd(
            bcadd((string)$steelOfflineLottery, (string)$steelOnlineLottery, 2),
            bcadd((string)$slotOfflineLottery, (string)$slotOnlineLottery, 2),
            2
        );
        $totalLottery = bcadd((string)$electronicLottery, (string)$machineLotteryTotal, 2);
        $totalProfit  = bcsub($totalIn, $totalOut, 2);

        // --- 贈點福利小計 ---
        $vipBirthday      = $welfareData[PlayerDeliveryRecord::TYPE_BIRTHDAY_BONUS]        ?? 0.0;
        $vipUpgrade       = $welfareData[PlayerDeliveryRecord::TYPE_VIP_UPGRADE_BONUS]     ?? 0.0;
        $vipDailyLogin    = $welfareData[PlayerDeliveryRecord::TYPE_VIP_DAILY_LOGIN_BONUS] ?? 0.0;
        $activityGive     = $welfareData[PlayerDeliveryRecord::TYPE_ACTIVITY_GIVE]         ?? 0.0;
        $lotteryTicket    = $welfareData[PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD] ?? 0.0;
        $experienceCoupon = $couponData[TicketRecord::TYPE_EXPERIENCE] ?? 0.0;
        $welfareCoupon    = $couponData[TicketRecord::TYPE_WELFARE]    ?? 0.0;

        $welfareSubtotal = bcadd(
            bcadd(
                bcadd(bcadd((string)$vipBirthday, (string)$vipUpgrade, 2), (string)$vipDailyLogin, 2),
                bcadd((string)$activityGive, (string)$lotteryTicket, 2),
                2
            ),
            bcadd((string)$experienceCoupon, (string)$welfareCoupon, 2),
            2
        );

        // 電子遊戲數據
        $totalBet  = (float)($electronicGame->total_bet  ?? 0);
        $totalWin  = (float)($electronicGame->total_win  ?? 0);
        $totalDiff = (float)($electronicGame->total_diff ?? 0);

        return [
            'overview' => [
                'total_in'      => (float)$totalIn,
                'total_out'     => (float)$totalOut,
                'profit'        => (float)$totalProfit,
                'total_lottery' => (float)$totalLottery,
                'rtp'           => $this->calcRtp((float)$totalOut, (float)$totalIn),
            ],
            'welfare' => [
                'vip_birthday'      => $vipBirthday,
                'vip_upgrade'       => $vipUpgrade,
                'vip_daily_login'   => $vipDailyLogin,
                'activity_give'     => $activityGive,
                'experience_coupon' => $experienceCoupon,
                'welfare_coupon'    => $welfareCoupon,
                'lottery_ticket'    => $lotteryTicket,
                'subtotal'          => (float)$welfareSubtotal,
            ],
            'electronic' => [
                'total_bet'   => $totalBet,
                'total_win'   => $totalWin,
                'player_diff' => $totalDiff,
                'lottery'     => $electronicLottery,
                'rtp'         => $this->calcRtp($totalWin, $totalBet),
            ],
            'steel_ball' => [
                'offline' => [
                    'up_point'     => $steelOfflineUp,
                    'down_point'   => $steelOfflineDown,
                    'subtotal'     => (float)$steelOfflineSubtotal,
                    'lottery'      => $steelOfflineLottery,
                    'total_profit' => (float)$steelOfflineProfit,
                    'rtp'          => $this->calcRtp($steelOfflineDown, $steelOfflineUp),
                ],
                'online' => [
                    'up_point'     => $steelOnlineUp,
                    'down_point'   => $steelOnlineDown,
                    'subtotal'     => (float)$steelOnlineSubtotal,
                    'lottery'      => $steelOnlineLottery,
                    'total_profit' => (float)$steelOnlineProfit,
                    'rtp'          => $this->calcRtp($steelOnlineDown, $steelOnlineUp),
                ],
            ],
            'slot' => [
                'offline' => [
                    'up_point'           => $slotOfflineUp,
                    'down_point'         => $slotOfflineDown,
                    'subtotal'           => (float)$slotOfflineSubtotal,
                    'lottery'            => $slotOfflineLottery,
                    'total_profit'       => (float)$slotOfflineProfit,
                    'up_down_rtp'        => $this->calcRtp($slotOfflineDown, $slotOfflineUp),
                    'pressure'           => $slotOfflinePressure,
                    'score'              => $slotOfflineScore,
                    'press_score_profit' => (float)$slotOfflinePressProfit,
                    'press_score_rtp'    => $this->calcRtp($slotOfflineScore, $slotOfflinePressure),
                ],
                'online' => [
                    'up_point'           => $slotOnlineUp,
                    'down_point'         => $slotOnlineDown,
                    'subtotal'           => (float)$slotOnlineSubtotal,
                    'lottery'            => $slotOnlineLottery,
                    'total_profit'       => (float)$slotOnlineProfit,
                    'up_down_rtp'        => $this->calcRtp($slotOnlineDown, $slotOnlineUp),
                    'pressure'           => $slotOnlinePressure,
                    'score'              => $slotOnlineScore,
                    'press_score_profit' => (float)$slotOnlinePressProfit,
                    'press_score_rtp'    => $this->calcRtp($slotOnlineScore, $slotOnlinePressure),
                ],
            ],
        ];
    }

    // ================================================================
    // 通用工具方法
    // ================================================================

    private function applyCreatedAtFilter($query, string $start, string $end): void
    {
        if (!empty($start)) {
            $query->where('created_at', '>=', $start);
        }
        if (!empty($end)) {
            $query->where('created_at', '<=', $end);
        }
    }

    private function calcRtp(float $dividend, float $divisor): float
    {
        if ($divisor <= 0) {
            return 0.0;
        }

        return round($dividend / $divisor * 100, 2);
    }

    private function buildEmptyData(): array
    {
        $emptyMachineSection = [
            'up_point' => 0, 'down_point' => 0, 'subtotal' => 0,
            'lottery' => 0, 'total_profit' => 0, 'rtp' => 0,
        ];

        return [
            'overview'   => ['total_in' => 0, 'total_out' => 0, 'profit' => 0, 'total_lottery' => 0, 'rtp' => 0],
            'welfare'    => ['vip_birthday' => 0, 'vip_upgrade' => 0, 'vip_daily_login' => 0, 'activity_give' => 0, 'experience_coupon' => 0, 'welfare_coupon' => 0, 'lottery_ticket' => 0, 'subtotal' => 0],
            'electronic' => ['total_bet' => 0, 'total_win' => 0, 'player_diff' => 0, 'lottery' => 0, 'rtp' => 0],
            'steel_ball' => [
                'offline' => $emptyMachineSection,
                'online'  => $emptyMachineSection,
            ],
            'slot' => [
                'offline' => array_merge($emptyMachineSection, ['up_down_rtp' => 0, 'pressure' => 0, 'score' => 0, 'press_score_profit' => 0, 'press_score_rtp' => 0]),
                'online'  => array_merge($emptyMachineSection, ['up_down_rtp' => 0, 'pressure' => 0, 'score' => 0, 'press_score_profit' => 0, 'press_score_rtp' => 0]),
            ],
        ];
    }

    /** Tab 1：多选器用格式 [{value, label, agentId}] */
    private function getStoreOptions(int $departmentId): array
    {
        return AdminUser::query()
            ->where('department_id', $departmentId)
            ->where('type', AdminUser::TYPE_STORE)
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->get(['id', 'nickname', 'username', 'parent_admin_id'])
            ->map(fn($s) => [
                'value'   => $s->id,
                'label'   => ($s->nickname ?: $s->username) . " ({$s->username})",
                'agentId' => $s->parent_admin_id,
            ])
            ->values()
            ->toArray();
    }

    /** Tab 2：单选器用格式 {id => "label"} */
    private function getDetailStoreOptions(int $departmentId): array
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
            ->map(fn($a) => [
                'value' => $a->id,
                'label' => ($a->nickname ?: $a->username) . " ({$a->username})",
            ])
            ->values()
            ->toArray();
    }
}
