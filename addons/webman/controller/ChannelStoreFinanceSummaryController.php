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
        $t            = fn(string $key) => admin_trans('channel_store_finance_summary.' . $key);
        $admin        = Admin::user();
        $storeOptions = $this->getDetailStoreOptions($admin->department_id);
        $storeCount   = count($storeOptions);

        return admin_view(plugin()->webman->getPath() . '/views/store_finance_summary.vue')
            ->attr('ex_admin_title', admin_trans('channel_store_finance_summary.page_title', null, ['count' => $storeCount]))
            ->attr('ex_admin_description', admin_trans('channel_store_finance_summary.description'))
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
                'store_options'     => $storeOptions,
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
        $admin    = Admin::user();
        $storeId  = (int)Request::input('store_id', 0);
        $dateType = (string)Request::input('date_type', '');
        $start    = $dateType ? '' : (string)Request::input('start_date', '');
        $end      = $dateType ? '' : (string)Request::input('end_date', '');

        if (empty($storeId)) {
            return json(['code' => 400, 'msg' => '請選擇門店']);
        }

        if (empty($dateType) && (empty($start) || empty($end))) {
            return json(['code' => 400, 'msg' => '請選擇時間類型或查詢時間範圍']);
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

        $overviewData      = $this->queryOverviewData($playerIds, $storeId, $start, $end, $dateType);
        $welfareData       = $this->queryWelfareData($playerIds, $start, $end, $dateType);
        $couponData        = $this->queryCouponData($storeId, $start, $end, $dateType);
        $machineDelivery   = $this->queryMachineDeliveryData($playerIds, $storeId, $start, $end, $dateType);
        // 電子遊戲記錄按月分區，僅保留最近 3 個月；鉗制起始日期，避免查詢已刪除分區產生不完整數據
        $eStart = $start;
        if (!$dateType && !empty($eStart)) {
            $partitionCutoff = date('Y-m-01', strtotime('-2 months'));
            if ($eStart < $partitionCutoff) {
                $eStart = $partitionCutoff . ' 00:00:00';
            }
        }
        $electronicGame    = $this->queryElectronicGameData($playerIds, $eStart, $end, $dateType);
        $electronicLottery = $this->queryElectronicLotteryData($playerIds, $eStart, $end, $dateType);
        $machineLottery    = $this->queryMachineLotteryData($playerIds, $start, $end, $dateType);
        $slotPressure      = $this->querySlotPressureScoreData($playerIds, $storeId, $start, $end, $dateType);

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
            $query->where(getDateWhere8am($dateType, $column));
        } else {
            if (!empty($start)) $query->where($column, '>=', $start);
            if (!empty($end))   $query->where($column, '<=', $end);
        }
    }

    // ================================================================
    // Tab 2 私有查詢方法
    // ================================================================

    /**
     * 查询總覽数据，口径与店家分润报表 ChannelStoreProfitReportController 完全一致：
     * - 总入 = 开分 + 开票
     *   开分 = 人工开分(type=6 source=artificial_recharge) + 储值机储值(type=23 source=storage_recharge)
     *   开票 = 开分票出票额（ticket_type=1、有效状态、含玩家票与柜台/购票票）
     * - 总出 = 洗分 + 核销
     *   洗分 = type=7 source=channel_withdrawal；核销 = 所有票种 status=2 后台使用
     * - 未核销 = 出卷 - 后台核销 - 机台核销 + 柜台核销 - 已拆分/已合并
     *   出卷 = type=7 source=ticket_redeem（洗分出票）
     *
     * 时间归属：出票/出卷/拆并按 created_at，核销按 scanned_at（核销时间）。
     *
     * @return array ['total_in' => float, 'total_out' => float, 'unredeemed' => float]
     */
    private function queryOverviewData(array $playerIds, int $storeId, string $start, string $end, string $dateType = ''): array
    {
        // 钱包侧：开分 / 洗分 / 出卷
        $deliveryQuery = PlayerDeliveryRecord::query()
            ->whereIn('player_id', $playerIds)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN (`type` = " . PlayerDeliveryRecord::TYPE_RECHARGE . " AND `source` = 'artificial_recharge') OR (`type` = " . PlayerDeliveryRecord::TYPE_MACHINE . " AND `source` = 'storage_recharge') THEN `amount` ELSE 0 END), 0) AS open_score_amount,
                COALESCE(SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " AND `source` = 'channel_withdrawal' THEN `amount` ELSE 0 END), 0) AS channel_withdrawal_amount,
                COALESCE(SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " AND `source` = 'ticket_redeem' THEN `amount` ELSE 0 END), 0) AS ticket_redeem_amount
            ");
        $this->applyCreatedAtFilter($deliveryQuery, $start, $end, $dateType);
        $deliveryRow = $deliveryQuery->first();

        // 出票侧：开票 + 已拆分/已合并（按出票时间 created_at）
        $issueQuery = TicketRecord::query()
            ->where('store_admin_id', $storeId)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN `ticket_type` = " . TicketRecord::TYPE_RECHARGE . " AND `status` IN (" . TicketRecord::STATUS_NORMAL . "," . TicketRecord::STATUS_BACKEND_USED . "," . TicketRecord::STATUS_MACHINE_USED . ") AND ((`player_id` > 0) OR ((`player_id` = 0 OR `player_id` IS NULL) AND (`source_type` IS NULL OR `source_type` = '" . TicketRecord::SOURCE_TYPE_PURCHASE . "'))) THEN `score` ELSE 0 END), 0) AS ticket_open_score_amount,
                COALESCE(SUM(CASE WHEN `ticket_type` = " . TicketRecord::TYPE_WITHDRAW . " AND `status` IN (" . TicketRecord::STATUS_SPLIT . "," . TicketRecord::STATUS_MERGED . ") THEN `score` ELSE 0 END), 0) AS split_merged_amount
            ");
        $this->applyCreatedAtFilter($issueQuery, $start, $end, $dateType);
        $issueRow = $issueQuery->first();

        // 核销侧：后台核销 + 机台核销 + 柜台核销（按核销时间 scanned_at）
        $redeemQuery = TicketRecord::query()
            ->where('store_admin_id', $storeId)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN `status` = " . TicketRecord::STATUS_BACKEND_USED . " THEN `score` ELSE 0 END), 0) AS redeem_amount,
                COALESCE(SUM(CASE WHEN `ticket_type` = " . TicketRecord::TYPE_WITHDRAW . " AND `status` = " . TicketRecord::STATUS_MACHINE_USED . " THEN `score` ELSE 0 END), 0) AS redeem_machine_amount,
                COALESCE(SUM(CASE WHEN (`ticket_type` = " . TicketRecord::TYPE_RECHARGE . " AND `status` = " . TicketRecord::STATUS_BACKEND_USED . ") OR (`ticket_type` = " . TicketRecord::TYPE_WITHDRAW . " AND `status` = " . TicketRecord::STATUS_BACKEND_USED . " AND (`player_id` = 0 OR `player_id` IS NULL)) THEN `score` ELSE 0 END), 0) AS counter_redeem_amount
            ");
        $this->applyTimeFilter($redeemQuery, 'scanned_at', $dateType ?: null, $start, $end);
        $redeemRow = $redeemQuery->first();

        $openScoreAmount       = (float)($deliveryRow->open_score_amount ?? 0);
        $channelWithdrawal     = (float)($deliveryRow->channel_withdrawal_amount ?? 0);
        $ticketRedeemAmount    = (float)($deliveryRow->ticket_redeem_amount ?? 0);
        $ticketOpenScoreAmount = (float)($issueRow->ticket_open_score_amount ?? 0);
        $splitMergedAmount     = (float)($issueRow->split_merged_amount ?? 0);
        $redeemAmount          = (float)($redeemRow->redeem_amount ?? 0);
        $redeemMachineAmount   = (float)($redeemRow->redeem_machine_amount ?? 0);
        $counterRedeemAmount   = (float)($redeemRow->counter_redeem_amount ?? 0);

        // 总入 = 开分 + 开票；总出 = 洗分 + 核销
        $totalIn  = bcadd((string)$openScoreAmount, (string)$ticketOpenScoreAmount, 2);
        $totalOut = bcadd((string)$channelWithdrawal, (string)$redeemAmount, 2);

        // 未核销 = 出卷 - 后台核销 - 机台核销 + 柜台核销 - 已拆分/已合并
        $unredeemed = bcsub(
            bcadd(
                bcsub((string)$ticketRedeemAmount, bcadd((string)$redeemAmount, (string)$redeemMachineAmount, 2), 2),
                (string)$counterRedeemAmount,
                2
            ),
            (string)$splitMergedAmount,
            2
        );

        return [
            'total_in'   => (float)$totalIn,
            'total_out'  => (float)$totalOut,
            'unredeemed' => (float)$unredeemed,
        ];
    }

    /**
     * 查询福利赠点数据（types: 33=摸彩奖, 34=VIP生日, 35=VIP升级, 37=VIP每日登录, 38=活动外增）
     * @return array [type => float]
     */
    private function queryWelfareData(array $playerIds, string $start, string $end, string $dateType = ''): array
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

        $this->applyCreatedAtFilter($query, $start, $end, $dateType);

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
    private function queryCouponData(int $storeId, string $start, string $end, string $dateType = ''): array
    {
        $query = TicketRecord::query()
            ->where('store_admin_id', $storeId)
            ->whereIn('ticket_type', [TicketRecord::TYPE_EXPERIENCE, TicketRecord::TYPE_WELFARE])
            ->where('status', '!=', TicketRecord::STATUS_DISABLED)
            ->selectRaw('ticket_type, SUM(score) as total')
            ->groupBy('ticket_type');

        $this->applyCreatedAtFilter($query, $start, $end, $dateType);

        $result = [];
        foreach ($query->get() as $row) {
            $result[(int)$row->ticket_type] = (float)$row->total;
        }

        return $result;
    }

    /**
     * 查询实体机台上分/下分点数，按 game_type 和 machine_source 分组。
     *
     * 线下实体机台 player_game_log.player_id = 0（玩家直接投币，无账号），
     * 必须通过 channel_machine.store_admin_id 定位机台，再关联 player_game_log。
     * 线上机台 player_game_log.player_id 有值，通过 playerIds 过滤。
     * 两路 OR 合并，JOIN machine 获取 m.type（游戏类型）及 m.machine_source（线上/线下权威字段）。
     *
     * game_amount 符号因写入方不同而不统一，统一用 ABS 处理。
     *
     * @return array [game_type][machine_source][TYPE_MACHINE_UP|TYPE_MACHINE_DOWN] => float
     */
    private function queryMachineDeliveryData(array $playerIds, int $storeId, string $start, string $end, string $dateType = ''): array
    {
        $pglTable  = plugin()->webman->config('database.player_game_log_table');
        $machTable = plugin()->webman->config('database.machine_table');
        $cateTable = plugin()->webman->config('database.machine_category_table');
        $cmTable   = plugin()->webman->config('database.channel_machine_table');
        $openAction = PlayerGameLog::ACTION_OPEN;

        // 线下机台 ID 子查询（channel_machine.store_admin_id = storeId）
        $offlineMachineIds = Db::table($cmTable)
            ->where('store_admin_id', $storeId)
            ->whereNotNull('store_admin_id')
            ->pluck('machine_id')
            ->toArray();

        $query = Db::table("{$pglTable} as pgl")
            ->join("{$machTable} as m", 'pgl.machine_id', '=', 'm.id')
            ->leftJoin("{$cateTable} as mc", 'm.cate_id', '=', 'mc.id')
            ->where('pgl.machine_id', '>', 0)
            ->whereIn('m.type', [GameType::TYPE_SLOT, GameType::TYPE_STEEL_BALL])
            ->where(function ($q) use ($playerIds, $offlineMachineIds) {
                $q->whereIn('pgl.player_id', $playerIds);
                if (!empty($offlineMachineIds)) {
                    $q->orWhereIn('pgl.machine_id', $offlineMachineIds);
                }
            })
            ->selectRaw("m.type AS game_type, COALESCE(m.machine_source, 2) AS machine_source,
                         SUM(CASE WHEN pgl.action = {$openAction} THEN ABS(pgl.game_amount) ELSE 0 END) AS total_open,
                         SUM(CASE WHEN pgl.action <> {$openAction} THEN ABS(pgl.game_amount) ELSE 0 END) AS total_wash,
                         COALESCE(SUM(pgl.cashback_amount), 0) AS cashback_amount,
                         COALESCE(SUM(pgl.parent_cashback_amount), 0) AS parent_cashback_amount")
            ->groupBy('m.type', 'm.machine_source');

        $this->applyTimeFilter($query, 'pgl.created_at', $dateType ?: null, $start, $end);

        $result = [];
        foreach ($query->get() as $row) {
            $gameType      = (int)$row->game_type;
            $machineSource = (int)$row->machine_source;

            $result[$gameType][$machineSource][PlayerDeliveryRecord::TYPE_MACHINE_UP]   = (float)$row->total_open;
            $result[$gameType][$machineSource][PlayerDeliveryRecord::TYPE_MACHINE_DOWN] = (float)$row->total_wash;
            $result[$gameType][$machineSource]['cashback_amount']                       = (float)$row->cashback_amount;
            $result[$gameType][$machineSource]['parent_cashback_amount']                = (float)$row->parent_cashback_amount;
        }

        return $result;
    }

    /**
     * 查询电子游戏数据
     * @return object {total_bet, total_win, total_diff}
     */
    private function queryElectronicGameData(array $playerIds, string $start, string $end, string $dateType = ''): object
    {
        $query = PlayGameRecord::query()
            ->whereIn('player_id', $playerIds)
            ->where('settlement_status', PlayGameRecord::SETTLEMENT_STATUS_SETTLED)
            ->selectRaw('COALESCE(SUM(bet), 0) as total_bet, COALESCE(SUM(win), 0) as total_win, COALESCE(SUM(diff), 0) as total_diff, COALESCE(SUM(cashback_amount), 0) as cashback_amount, COALESCE(SUM(parent_cashback_amount), 0) as children_cashback_amount');

        $this->applyCreatedAtFilter($query, $start, $end, $dateType);

        return $query->first() ?? (object)['total_bet' => 0, 'total_win' => 0, 'total_diff' => 0, 'cashback_amount' => 0, 'children_cashback_amount' => 0];
    }

    /**
     * 查询电子游戏彩金总额
     */
    private function queryElectronicLotteryData(array $playerIds, string $start, string $end, string $dateType = ''): float
    {
        $query = PlayerLotteryRecord::query()
            ->whereIn('player_id', $playerIds)
            ->where('status', PlayerLotteryRecord::STATUS_COMPLETE)
            ->whereIn('source', [PlayerLotteryRecord::SOURCE_GAME, PlayerLotteryRecord::SOURCE_MANUAL]);

        $this->applyCreatedAtFilter($query, $start, $end, $dateType);

        return (float)$query->sum('amount');
    }

    /**
     * 查询实体机台彩金数据，按 game_type 和 machine_source 分组
     * @return array [game_type][machine_source] => float
     */
    private function queryMachineLotteryData(array $playerIds, string $start, string $end, string $dateType = ''): array
    {
        $plrTable  = plugin()->webman->config('database.player_lottery_record_table') ?? 'player_lottery_record';
        $machTable = plugin()->webman->config('database.machine_table');

        $query = Db::table("{$plrTable} as plr")
            ->join("{$machTable} as m", 'plr.machine_id', '=', 'm.id')
            ->whereIn('plr.player_id', $playerIds)
            ->where('plr.status', PlayerLotteryRecord::STATUS_COMPLETE)
            ->whereIn('plr.game_type', [GameType::TYPE_SLOT, GameType::TYPE_STEEL_BALL])
            ->where('plr.machine_id', '>', 0)
            ->selectRaw('plr.game_type, COALESCE(m.machine_source, 2) AS machine_source, SUM(plr.amount) as total_amount')
            ->groupBy('plr.game_type', 'm.machine_source');

        $this->applyTimeFilter($query, 'plr.created_at', $dateType ?: null, $start, $end);

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
     * 与 queryMachineDeliveryData 相同的 OR 策略：线上用 playerIds，线下用 channel_machine 机台 ID。
     * @return array [machine_source] => ['pressure' => float, 'score' => float]
     */
    private function querySlotPressureScoreData(array $playerIds, int $storeId, string $start, string $end, string $dateType = ''): array
    {
        $pglTable  = plugin()->webman->config('database.player_game_log_table');
        $machTable = plugin()->webman->config('database.machine_table');
        $cateTable = plugin()->webman->config('database.machine_category_table');
        $cmTable   = plugin()->webman->config('database.channel_machine_table');

        $offlineMachineIds = Db::table($cmTable)
            ->where('store_admin_id', $storeId)
            ->whereNotNull('store_admin_id')
            ->pluck('machine_id')
            ->toArray();

        $query = Db::table("{$pglTable} as pgl")
            ->join("{$machTable} as m", 'pgl.machine_id', '=', 'm.id')
            ->leftJoin("{$cateTable} as mc", 'm.cate_id', '=', 'mc.id')
            ->where('pgl.machine_id', '>', 0)
            ->where('m.type', GameType::TYPE_SLOT)
            ->where(function ($q) use ($playerIds, $offlineMachineIds) {
                $q->whereIn('pgl.player_id', $playerIds);
                if (!empty($offlineMachineIds)) {
                    $q->orWhereIn('pgl.machine_id', $offlineMachineIds);
                }
            })
            ->selectRaw('COALESCE(m.machine_source, 2) AS machine_source, COALESCE(SUM(pgl.pressure), 0) as total_pressure, COALESCE(SUM(pgl.score), 0) as total_score')
            ->groupBy('m.machine_source');

        $this->applyTimeFilter($query, 'pgl.created_at', $dateType ?: null, $start, $end);

        $result = [];
        foreach ($query->get() as $row) {
            $machineSource = (int)$row->machine_source;

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
        $steelOfflineLottery        = $machineLottery[$steelBall][$offline] ?? 0.0;
        $steelOnlineLottery         = $machineLottery[$steelBall][$online]  ?? 0.0;
        $steelOfflineCashback       = $machineDelivery[$steelBall][$offline]['cashback_amount']        ?? 0.0;
        $steelOfflineParentCashback = $machineDelivery[$steelBall][$offline]['parent_cashback_amount'] ?? 0.0;
        $steelOnlineCashback        = $machineDelivery[$steelBall][$online]['cashback_amount']         ?? 0.0;
        $steelOnlineParentCashback  = $machineDelivery[$steelBall][$online]['parent_cashback_amount']  ?? 0.0;

        $steelOfflineSubtotal = bcsub((string)$steelOfflineUp, (string)$steelOfflineDown, 2);
        $steelOnlineSubtotal  = bcsub((string)$steelOnlineUp, (string)$steelOnlineDown, 2);
        // 总利润 = 小计 - 彩金 - 反水 - 红利
        $steelOfflineProfit   = bcsub(
            bcsub((string)$steelOfflineSubtotal, (string)$steelOfflineLottery, 2),
            bcadd((string)$steelOfflineCashback, (string)$steelOfflineParentCashback, 2),
            2
        );
        $steelOnlineProfit    = bcsub(
            bcsub((string)$steelOnlineSubtotal, (string)$steelOnlineLottery, 2),
            bcadd((string)$steelOnlineCashback, (string)$steelOnlineParentCashback, 2),
            2
        );

        // --- 斯洛 ---
        $slotOfflineUp   = $machineDelivery[$slot][$offline][$upType]   ?? 0.0;
        $slotOfflineDown = $machineDelivery[$slot][$offline][$downType] ?? 0.0;
        $slotOnlineUp    = $machineDelivery[$slot][$online][$upType]    ?? 0.0;
        $slotOnlineDown  = $machineDelivery[$slot][$online][$downType]  ?? 0.0;
        $slotOfflineLottery        = $machineLottery[$slot][$offline] ?? 0.0;
        $slotOnlineLottery         = $machineLottery[$slot][$online]  ?? 0.0;
        $slotOfflineCashback       = $machineDelivery[$slot][$offline]['cashback_amount']        ?? 0.0;
        $slotOfflineParentCashback = $machineDelivery[$slot][$offline]['parent_cashback_amount'] ?? 0.0;
        $slotOnlineCashback        = $machineDelivery[$slot][$online]['cashback_amount']         ?? 0.0;
        $slotOnlineParentCashback  = $machineDelivery[$slot][$online]['parent_cashback_amount']  ?? 0.0;

        $slotOfflineSubtotal  = bcsub((string)$slotOfflineUp, (string)$slotOfflineDown, 2);
        $slotOnlineSubtotal   = bcsub((string)$slotOnlineUp, (string)$slotOnlineDown, 2);
        // 总利润 = 小计 - 彩金 - 反水 - 红利
        $slotOfflineProfit    = bcsub(
            bcsub((string)$slotOfflineSubtotal, (string)$slotOfflineLottery, 2),
            bcadd((string)$slotOfflineCashback, (string)$slotOfflineParentCashback, 2),
            2
        );
        $slotOnlineProfit     = bcsub(
            bcsub((string)$slotOnlineSubtotal, (string)$slotOnlineLottery, 2),
            bcadd((string)$slotOnlineCashback, (string)$slotOnlineParentCashback, 2),
            2
        );

        $slotOfflinePressure = $slotPressure[$offline]['pressure'] ?? 0.0;
        $slotOfflineScore    = $slotPressure[$offline]['score']    ?? 0.0;
        $slotOnlinePressure  = $slotPressure[$online]['pressure']  ?? 0.0;
        $slotOnlineScore     = $slotPressure[$online]['score']     ?? 0.0;

        $slotOfflinePressProfit = bcsub((string)$slotOfflinePressure, (string)$slotOfflineScore, 2);
        $slotOnlinePressProfit  = bcsub((string)$slotOnlinePressure, (string)$slotOnlineScore, 2);

        // --- 總覽：总入=开分+开票，总出=洗分+核销（店家分润报表口径） ---
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

        // 反水汇总 = 电子游戏反水 + 机台反水（钢珠线下/线上 + 斯洛线下/线上）
        $reverseWaterPool = bcadd(
            bcadd(
                bcadd((string)(float)($electronicGame->cashback_amount ?? 0), (string)$steelOfflineCashback, 2),
                (string)$steelOnlineCashback,
                2
            ),
            bcadd((string)$slotOfflineCashback, (string)$slotOnlineCashback, 2),
            2
        );

        // 推广红利汇总 = 电子游戏推广红利 + 机台推广红利（钢珠线下/线上 + 斯洛线下/线上）
        $childrenCashback = bcadd(
            bcadd(
                bcadd((string)(float)($electronicGame->children_cashback_amount ?? 0), (string)$steelOfflineParentCashback, 2),
                (string)$steelOnlineParentCashback,
                2
            ),
            bcadd((string)$slotOfflineParentCashback, (string)$slotOnlineParentCashback, 2),
            2
        );

        $experienceCoupon = $couponData[TicketRecord::TYPE_EXPERIENCE] ?? 0.0;
        $welfareCoupon    = $couponData[TicketRecord::TYPE_WELFARE]    ?? 0.0;

        $welfareSubtotal = bcadd(
            bcadd(
                bcadd(
                    bcadd(bcadd((string)$vipBirthday, (string)$vipUpgrade, 2), (string)$vipDailyLogin, 2),
                    bcadd((string)$activityGive, (string)$lotteryTicket, 2),
                    2
                ),
                bcadd((string)$reverseWaterPool, (string)$childrenCashback, 2),
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
                // 未核销 = 出卷 - 后台核销 - 机台核销 + 柜台核销 - 已拆分/已合并
                'unredeemed'    => (float)($overviewData['unredeemed'] ?? 0),
            ],
            'welfare' => [
                'vip_birthday'       => $vipBirthday,
                'vip_upgrade'        => $vipUpgrade,
                'vip_daily_login'    => $vipDailyLogin,
                'activity_give'      => $activityGive,
                'experience_coupon'  => $experienceCoupon,
                'welfare_coupon'     => $welfareCoupon,
                'lottery_ticket'     => $lotteryTicket,
                'reverse_water_pool' => $reverseWaterPool,
                'children_cashback'  => $childrenCashback,
                'subtotal'           => (float)$welfareSubtotal,
            ],
            'electronic' => [
                'total_bet'          => $totalBet,
                'total_win'          => $totalWin,
                'player_diff'        => $totalDiff,
                'lottery'            => $electronicLottery,
                'rtp'                => $this->calcRtp($totalWin, $totalBet),
                'reverse_water_pool' => $reverseWaterPool,
            ],
            'steel_ball' => [
                'offline' => [
                    'up_point'           => $steelOfflineUp,
                    'down_point'         => $steelOfflineDown,
                    'subtotal'           => (float)$steelOfflineSubtotal,
                    'lottery'            => $steelOfflineLottery,
                    'total_profit'       => (float)$steelOfflineProfit,
                    'rtp'                => $this->calcRtp($steelOfflineDown, $steelOfflineUp),
                    'reverse_water_pool' => $steelOfflineCashback,
                    'children_cashback'  => $steelOfflineParentCashback,
                ],
                'online' => [
                    'up_point'           => $steelOnlineUp,
                    'down_point'         => $steelOnlineDown,
                    'subtotal'           => (float)$steelOnlineSubtotal,
                    'lottery'            => $steelOnlineLottery,
                    'total_profit'       => (float)$steelOnlineProfit,
                    'rtp'                => $this->calcRtp($steelOnlineDown, $steelOnlineUp),
                    'reverse_water_pool' => $steelOnlineCashback,
                    'children_cashback'  => $steelOnlineParentCashback,
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
                    'reverse_water_pool' => $slotOfflineCashback,
                    'children_cashback'  => $slotOfflineParentCashback,
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
                    'reverse_water_pool' => $slotOnlineCashback,
                    'children_cashback'  => $slotOnlineParentCashback,
                ],
            ],
        ];
    }

    // ================================================================
    // 通用工具方法
    // ================================================================

    private function applyCreatedAtFilter($query, string $start, string $end, string $dateType = ''): void
    {
        $this->applyTimeFilter($query, 'created_at', $dateType ?: null, $start, $end);
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
            'reverse_water_pool' => 0, 'children_cashback' => 0,
        ];

        return [
            'overview'   => ['total_in' => 0, 'total_out' => 0, 'profit' => 0, 'total_lottery' => 0, 'rtp' => 0, 'unredeemed' => 0],
            'welfare'    => ['vip_birthday' => 0, 'vip_upgrade' => 0, 'vip_daily_login' => 0, 'activity_give' => 0, 'experience_coupon' => 0, 'welfare_coupon' => 0, 'lottery_ticket' => 0, 'reverse_water_pool' => 0, 'children_cashback' => 0, 'subtotal' => 0],
            'electronic' => ['total_bet' => 0, 'total_win' => 0, 'player_diff' => 0, 'lottery' => 0, 'rtp' => 0, 'reverse_water_pool' => 0],
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
