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
 * 渠道后台 - 门店财务汇总
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
        /** @var AdminUser $admin */
        $admin = Admin::user();
        $storeOptions = $this->getStoreOptions($admin->department_id);

        return admin_view(plugin()->webman->getPath() . '/views/store_finance_summary.vue')->attrs([
            'store_options' => $storeOptions,
            'search_url'    => 'ex-admin/addons-webman-controller-ChannelStoreFinanceSummaryController/search',
        ]);
    }

    /**
     * 查询门店财务汇总数据
     * @group channel
     * @auth true
     */
    public function search(): Response
    {
        /** @var AdminUser $admin */
        $admin   = Admin::user();
        $storeId = (int)Request::input('store_id', 0);
        $start   = Request::input('start_date', '');
        $end     = Request::input('end_date', '');

        if (empty($storeId)) {
            return json(['code' => 400, 'msg' => '请选择门店']);
        }

        $store = AdminUser::query()
            ->where('id', $storeId)
            ->where('department_id', $admin->department_id)
            ->where('type', AdminUser::TYPE_STORE)
            ->first();

        if (!$store) {
            return json(['code' => 403, 'msg' => '无权限访问该门店']);
        }

        $playerIds = Player::query()
            ->where('store_admin_id', $storeId)
            ->where('is_promoter', 0)
            ->pluck('id')
            ->toArray();

        if (empty($playerIds)) {
            return json(['code' => 200, 'data' => $this->buildEmptyData()]);
        }

        $overviewData     = $this->queryOverviewData($playerIds, $start, $end);
        $welfareData      = $this->queryWelfareData($playerIds, $start, $end);
        $couponData       = $this->queryCouponData($storeId, $start, $end);
        $machineDelivery  = $this->queryMachineDeliveryData($playerIds, $start, $end);
        $electronicGame   = $this->queryElectronicGameData($playerIds, $start, $end);
        $electronicLottery = $this->queryElectronicLotteryData($playerIds, $start, $end);
        $machineLottery   = $this->queryMachineLotteryData($playerIds, $start, $end);
        $slotPressure     = $this->querySlotPressureScoreData($playerIds, $start, $end);

        return json(['code' => 200, 'data' => $this->buildReportData(
            $overviewData, $welfareData, $couponData, $machineDelivery,
            $electronicGame, $electronicLottery, $machineLottery, $slotPressure
        )]);
    }

    // ========== 查询方法 ==========

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
     * 查询机台上分/下分数据，按 game_type 和 machine_source 分组
     * @return array [game_type][machine_source][type] => float
     */
    private function queryMachineDeliveryData(array $playerIds, string $start, string $end): array
    {
        $pdrTable  = plugin()->webman->config('database.player_delivery_record_table');
        $machTable = plugin()->webman->config('database.machine_table');
        $cateTable = plugin()->webman->config('database.machine_category_table');

        $query = Db::table("{$pdrTable} as pdr")
            ->join("{$machTable} as m", 'pdr.machine_id', '=', 'm.id')
            ->join("{$cateTable} as mc", 'm.cate_id', '=', 'mc.id')
            ->whereIn('pdr.player_id', $playerIds)
            ->whereIn('pdr.type', [PlayerDeliveryRecord::TYPE_MACHINE_UP, PlayerDeliveryRecord::TYPE_MACHINE_DOWN])
            ->where('pdr.machine_id', '>', 0)
            ->selectRaw('mc.game_id, mc.machine_source, pdr.type, SUM(pdr.amount) as total_amount')
            ->groupBy('mc.game_id', 'mc.machine_source', 'pdr.type');

        if (!empty($start)) {
            $query->where('pdr.created_at', '>=', $start);
        }
        if (!empty($end)) {
            $query->where('pdr.created_at', '<=', $end);
        }

        $result = [];
        foreach ($query->get() as $row) {
            $gameType      = (int)$row->game_id;
            $machineSource = (int)$row->machine_source;
            $type          = (int)$row->type;

            if (!isset($result[$gameType])) {
                $result[$gameType] = [];
            }
            if (!isset($result[$gameType][$machineSource])) {
                $result[$gameType][$machineSource] = [];
            }

            $result[$gameType][$machineSource][$type] = (float)$row->total_amount;
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

            if (!isset($result[$gameType])) {
                $result[$gameType] = [];
            }

            $result[$gameType][$machineSource] = (float)$row->total_amount;
        }

        return $result;
    }

    /**
     * 查询斯洛机台壓分/得分数据，按 machine_source 分组
     * @return array [machine_source] => ['pressure' => float, 'score' => float]
     */
    private function querySlotPressureScoreData(array $playerIds, string $start, string $end): array
    {
        $pglTable  = plugin()->webman->config('database.player_game_log_table');
        $machTable = plugin()->webman->config('database.machine_table');
        $cateTable = plugin()->webman->config('database.machine_category_table');

        $query = Db::table("{$pglTable} as pgl")
            ->join("{$machTable} as m", 'pgl.machine_id', '=', 'm.id')
            ->join("{$cateTable} as mc", 'm.cate_id', '=', 'mc.id')
            ->whereIn('pgl.player_id', $playerIds)
            ->where('mc.game_id', GameType::TYPE_SLOT)
            ->selectRaw('mc.machine_source, COALESCE(SUM(pgl.pressure), 0) as total_pressure, COALESCE(SUM(pgl.score), 0) as total_score')
            ->groupBy('mc.machine_source');

        if (!empty($start)) {
            $query->where('pgl.created_at', '>=', $start);
        }
        if (!empty($end)) {
            $query->where('pgl.created_at', '<=', $end);
        }

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

    // ========== 数据组装 ==========

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
        $vipBirthday    = $welfareData[PlayerDeliveryRecord::TYPE_BIRTHDAY_BONUS]        ?? 0.0;
        $vipUpgrade     = $welfareData[PlayerDeliveryRecord::TYPE_VIP_UPGRADE_BONUS]     ?? 0.0;
        $vipDailyLogin  = $welfareData[PlayerDeliveryRecord::TYPE_VIP_DAILY_LOGIN_BONUS] ?? 0.0;
        $activityGive   = $welfareData[PlayerDeliveryRecord::TYPE_ACTIVITY_GIVE]         ?? 0.0;
        $lotteryTicket  = $welfareData[PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD] ?? 0.0;
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
                'total_bet'    => $totalBet,
                'total_win'    => $totalWin,
                'player_diff'  => $totalDiff,
                'lottery'      => $electronicLottery,
                'rtp'          => $this->calcRtp($totalWin, $totalBet),
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
                    'up_point'          => $slotOfflineUp,
                    'down_point'        => $slotOfflineDown,
                    'subtotal'          => (float)$slotOfflineSubtotal,
                    'lottery'           => $slotOfflineLottery,
                    'total_profit'      => (float)$slotOfflineProfit,
                    'up_down_rtp'       => $this->calcRtp($slotOfflineDown, $slotOfflineUp),
                    'pressure'          => $slotOfflinePressure,
                    'score'             => $slotOfflineScore,
                    'press_score_profit'=> (float)$slotOfflinePressProfit,
                    'press_score_rtp'   => $this->calcRtp($slotOfflineScore, $slotOfflinePressure),
                ],
                'online' => [
                    'up_point'          => $slotOnlineUp,
                    'down_point'        => $slotOnlineDown,
                    'subtotal'          => (float)$slotOnlineSubtotal,
                    'lottery'           => $slotOnlineLottery,
                    'total_profit'      => (float)$slotOnlineProfit,
                    'up_down_rtp'       => $this->calcRtp($slotOnlineDown, $slotOnlineUp),
                    'pressure'          => $slotOnlinePressure,
                    'score'             => $slotOnlineScore,
                    'press_score_profit'=> (float)$slotOnlinePressProfit,
                    'press_score_rtp'   => $this->calcRtp($slotOnlineScore, $slotOnlinePressure),
                ],
            ],
        ];
    }

    // ========== 工具方法 ==========

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
}
