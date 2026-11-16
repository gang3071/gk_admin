<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\GameType;
use addons\webman\model\PlayGameRecord;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerLotteryRecord;
use addons\webman\model\PlayerReverseWaterDetail;
use addons\webman\model\TicketRecord;
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
        return admin_view(plugin()->webman->getPath() . '/views/store_finance_summary.vue')
            ->attrs([]);
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
        $storeId   = Request::input('store_id');
        $dateType  = Request::input('date_type');
        $startTime = Request::input('start_time');
        $endTime   = Request::input('end_time');

        // 下拉选项（每次都返回，供前端更新选择器）
        $agents = $this->buildSelectOptions($this->getAgentOptions($admin->department_id));
        $stores = $this->buildSelectOptions($this->getStoreOptions($admin->department_id));

        $hasFilter = !empty($dateType) || !empty($startTime) || !empty($endTime)
                     || !empty($storeId) || !empty($agentId);

        if (!$hasFilter) {
            return json(['code' => 200, 'data' => ['agents' => $agents, 'stores' => $stores, 'rows' => [], 'summary' => []]]);
        }

        // 查询店家
        $storesQuery = AdminUser::query()
            ->select(['id', 'nickname', 'username', 'parent_admin_id'])
            ->where('department_id', $admin->department_id)
            ->where('type', AdminUser::TYPE_STORE)
            ->where('status', 1)
            ->orderBy('id', 'desc');

        if (!empty($agentId)) {
            $storesQuery->where('parent_admin_id', $agentId);
        }
        if (!empty($storeId)) {
            $storesQuery->where('id', $storeId);
        }

        $storeList = $storesQuery->get();
        $storeIds  = $storeList->pluck('id')->toArray();

        if (empty($storeIds)) {
            return json(['code' => 200, 'data' => ['agents' => $agents, 'stores' => $stores, 'rows' => [], 'summary' => []]]);
        }

        // 代理名称
        $agentIds  = $storeList->pluck('parent_admin_id')->filter()->unique()->values()->toArray();
        $agentMap  = AdminUser::query()->whereIn('id', $agentIds)->get(['id', 'nickname', 'username'])->keyBy('id');

        // 玩家分组
        $allPlayers = Player::query()
            ->whereIn('store_admin_id', $storeIds)
            ->where('is_promoter', 0)
            ->get(['id', 'store_admin_id']);

        $playerIdsByStore = [];
        foreach ($allPlayers->groupBy('store_admin_id') as $sid => $players) {
            $playerIdsByStore[$sid] = $players->pluck('id')->toArray();
        }

        // 批量查询
        $deliveryByStore    = $this->batchQueryDeliveryData($playerIdsByStore, $dateType, $startTime, $endTime);
        $lotteryByStore     = $this->batchQueryLotteryData($playerIdsByStore, $dateType, $startTime, $endTime);
        $couponByStore      = $this->batchQueryCouponData($storeIds, $dateType, $startTime, $endTime);
        $gameWinLossByStore = $this->batchQueryGameWinLoss($playerIdsByStore, $dateType, $startTime, $endTime);
        $elecRebateByStore  = $this->batchQueryElecRebate($playerIdsByStore, $dateType, $startTime, $endTime);

        $rows    = [];
        $summary = array_fill_keys(['electronic_game_win_loss', 'machine_win_loss', 'lottery_ticket_sent',
            'electronic_jackpot', 'machine_jackpot', 'wallet_increase', 'wallet_decrease',
            'machine_rebate', 'electronic_rebate', 'coupon_sent'], '0');

        foreach ($storeList as $store) {
            $sid   = $store->id;
            $agent = $agentMap->get($store->parent_admin_id);
            $d     = $deliveryByStore[$sid] ?? null;
            $l     = $lotteryByStore[$sid] ?? null;
            $c     = $couponByStore[$sid] ?? null;
            $g     = $gameWinLossByStore[$sid] ?? null;
            $er    = $elecRebateByStore[$sid] ?? null;

            $machineWinLoss = bcsub(
                bcadd((string)floatval($d->open_score ?? 0), (string)floatval($d->machine_put ?? 0), 2),
                (string)floatval($d->withdraw ?? 0),
                2
            );

            $row = [
                'id'                       => $sid,
                'store_name'               => $store->nickname ?: $store->username,
                'store_username'           => $store->username,
                'agent_name'               => $agent ? ($agent->nickname ?: $agent->username) : '-',
                'electronic_game_win_loss' => bcadd('0', (string)floatval($g->win_loss ?? 0), 2),
                'machine_win_loss'         => $machineWinLoss,
                'lottery_ticket_sent'      => bcadd('0', (string)floatval($d->lottery_ticket_sent ?? 0), 2),
                'electronic_jackpot'       => bcadd('0', (string)floatval($l->electronic_jackpot ?? 0), 2),
                'machine_jackpot'          => bcadd('0', (string)floatval($l->machine_jackpot ?? 0), 2),
                'wallet_increase'          => bcadd('0', (string)floatval($d->wallet_increase ?? 0), 2),
                'wallet_decrease'          => bcadd('0', (string)floatval($d->wallet_decrease ?? 0), 2),
                'machine_rebate'           => '0.00',
                'electronic_rebate'        => bcadd('0', (string)floatval($er->reverse_water ?? 0), 2),
                'coupon_sent'              => bcadd('0', (string)floatval($c->coupon_sent ?? 0), 2),
            ];

            foreach (array_keys($summary) as $field) {
                $summary[$field] = bcadd($summary[$field], $row[$field], 2);
            }

            $rows[] = $row;
        }

        return json(['code' => 200, 'data' => [
            'agents'  => $agents,
            'stores'  => $stores,
            'rows'    => $rows,
            'summary' => $summary,
        ]]);
    }

    // ========== 批量查询方法 ==========

    private function batchQueryDeliveryData(array $playerIdsByStore, ?string $dateType, ?string $start, ?string $end): array
    {
        $allPlayerIds = array_merge(...array_values($playerIdsByStore ?: [[]]));
        if (empty($allPlayerIds)) {
            return [];
        }

        $increaseTypes = implode(',', [
            PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD,
            PlayerDeliveryRecord::TYPE_PRESENT_IN,
            PlayerDeliveryRecord::TYPE_WITHDRAWAL_BACK,
            PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS,
            PlayerDeliveryRecord::TYPE_REGISTER_PRESENT,
            PlayerDeliveryRecord::TYPE_PROFIT,
            PlayerDeliveryRecord::TYPE_NATIONAL_INVITE,
            PlayerDeliveryRecord::TYPE_RECHARGE_REWARD,
            PlayerDeliveryRecord::TYPE_DAMAGE_REBATE,
            PlayerDeliveryRecord::COIN_ADD,
            PlayerDeliveryRecord::TYPE_AGENT_IN,
        ]);
        $decreaseTypes = implode(',', [
            PlayerDeliveryRecord::TYPE_PRESENT_OUT,
            PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT,
            PlayerDeliveryRecord::COIN_DEDUCT,
            PlayerDeliveryRecord::TYPE_AGENT_OUT,
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
            $agg = (object)['open_score' => 0.0, 'machine_put' => 0.0, 'withdraw' => 0.0,
                            'lottery_ticket_sent' => 0.0, 'wallet_increase' => 0.0, 'wallet_decrease' => 0.0];
            foreach ($playerIds as $pid) {
                if ($row = $rows->get($pid)) {
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

    private function batchQueryLotteryData(array $playerIdsByStore, ?string $dateType, ?string $start, ?string $end): array
    {
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
            $elec = 0.0; $machine = 0.0;
            foreach ($playerIds as $pid) {
                if ($row = $rows->get($pid)) {
                    $elec    += floatval($row->electronic_jackpot);
                    $machine += floatval($row->machine_jackpot);
                }
            }
            $result[$storeId] = (object)['electronic_jackpot' => $elec, 'machine_jackpot' => $machine];
        }
        return $result;
    }

    private function batchQueryCouponData(array $storeIds, ?string $dateType, ?string $start, ?string $end): array
    {
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
            $result[$storeId] = (object)['win_loss' => $total];
        }
        return $result;
    }

    private function batchQueryElecRebate(array $playerIdsByStore, ?string $dateType, ?string $start, ?string $end): array
    {
        $allPlayerIds = array_merge(...array_values($playerIdsByStore ?: [[]]));
        if (empty($allPlayerIds)) {
            return [];
        }

        $query = PlayerReverseWaterDetail::query()->whereIn('player_id', $allPlayerIds);
        if (!empty($dateType)) {
            $query->where(getDateWhere($dateType, 'date'));
        } else {
            if (!empty($start)) $query->where('date', '>=', date('Y-m-d', strtotime($start)));
            if (!empty($end))   $query->where('date', '<=', date('Y-m-d', strtotime($end)));
        }

        $rows = $query->selectRaw('player_id, SUM(reverse_water) AS reverse_water')
            ->groupBy('player_id')->get()->keyBy('player_id');

        $result = [];
        foreach ($playerIdsByStore as $storeId => $playerIds) {
            $total = 0.0;
            foreach ($playerIds as $pid) {
                if ($row = $rows->get($pid)) {
                    $total += floatval($row->reverse_water);
                }
            }
            $result[$storeId] = (object)['reverse_water' => $total];
        }
        return $result;
    }

    private function applyTimeFilter($query, string $column, ?string $dateType, ?string $start, ?string $end): void
    {
        if (!empty($dateType)) {
            $query->where(getDateWhere($dateType, $column));
        } else {
            if (!empty($start)) $query->where($column, '>', $start);
            if (!empty($end))   $query->where($column, '<=', $end);
        }
    }

    private function buildSelectOptions(array $map): array
    {
        $options = [];
        foreach ($map as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }
        return $options;
    }

    private function getStoreOptions(int $departmentId): array
    {
        return AdminUser::query()
            ->where('department_id', $departmentId)
            ->where('type', AdminUser::TYPE_STORE)
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->get(['id', 'nickname', 'username'])
            ->mapWithKeys(fn($s) => [$s->id => ($s->nickname ?: $s->username) . " ({$s->username})"])
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
            ->mapWithKeys(fn($a) => [$a->id => ($a->nickname ?: $a->username) . " ({$a->username})"])
            ->toArray();
    }
}
