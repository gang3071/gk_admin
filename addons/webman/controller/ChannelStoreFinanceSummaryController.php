<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\PlayGameRecord;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use ExAdmin\ui\support\Request;
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
        $t = fn(string $key) => admin_trans('channel_store_finance_summary.' . $key);

        return admin_view(plugin()->webman->getPath() . '/views/store_finance_summary.vue')
            ->attrs([
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
                        'delivery_detail'     => $t('panel.delivery_detail'),
                        'income'              => $t('panel.income'),
                        'expense'             => $t('panel.expense'),
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
                    'income_types' => [
                        1  => $t('delivery_types.1'),
                        4  => $t('delivery_types.4'),
                        6  => $t('delivery_types.6'),
                        12 => $t('delivery_types.12'),
                        13 => $t('delivery_types.13'),
                        16 => $t('delivery_types.16'),
                        23 => $t('delivery_types.23'),
                        32 => $t('delivery_types.32'),
                        33 => $t('delivery_types.33'),
                        34 => $t('delivery_types.34'),
                        35 => $t('delivery_types.35'),
                        36 => $t('delivery_types.36'),
                        37 => $t('delivery_types.37'),
                        38 => $t('delivery_types.38'),
                        39 => $t('delivery_types.39'),
                    ],
                    'expense_types' => [
                        5  => $t('delivery_types.5'),
                        7  => $t('delivery_types.7'),
                        8  => $t('delivery_types.8'),
                        22 => $t('delivery_types.22'),
                        31 => $t('delivery_types.31'),
                    ],
                    'desc_panel' => [
                        'title'      => $t('desc_panel.title'),
                        'click_hint' => $t('desc_panel.click_hint'),
                    ],
                ],
            ]);
    }

    /**
     * 门店财务汇总数据查询（AJAX）
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
        $deliveryByStore     = $this->batchQueryDeliveryTypes($playerIdsByStore, $dateType, $startTime, $endTime);
        $gameWinLossByStore  = $this->batchQueryGameWinLoss($playerIdsByStore, $dateType, $startTime, $endTime);
        $machineWinLoss      = $this->calcMachineWinLoss($deliveryByStore);

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
            'agents'  => $agents,
            'stores'  => $stores,
            'panels'  => $panels,
        ]]);
    }

    // ========== 批量查询方法 ==========

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
