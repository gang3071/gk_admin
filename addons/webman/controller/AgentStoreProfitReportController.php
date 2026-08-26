<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\Player;
use Carbon\Carbon;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerLotteryRecord;
use addons\webman\model\PlayerWithdrawRecord;
use addons\webman\model\StoreAgentShiftHandoverRecord;
use addons\webman\model\TicketRecord;
use addons\webman\model\StoreShiftDeviceDetail;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\collapse\Collapse;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\support\Request;

/**
 * 代理后台 - 店家分润报表
 * @group agent
 */
class AgentStoreProfitReportController
{
    /**
     * 班次时间范围定义
     * 早班: 08:00-16:00
     * 中班: 16:00-00:00
     * 晚班: 00:00-08:00
     */
    private const SHIFT_RANGES = [
        'morning' => ['start' => 8, 'end' => 16],
        'afternoon' => ['start' => 16, 'end' => 24],
        'night' => ['start' => 0, 'end' => 8],
    ];

    /**
     * 店家分润报表列表
     * @group agent
     * @auth true
     */
    public function index(): Grid
    {
        /** @var AdminUser $admin */
        $admin = Admin::user();

        // 获取筛选参数
        $exAdminFilter = Request::input('ex_admin_filter', []);
        $createdAtStart = $exAdminFilter['created_at_start'] ?? null;
        $createdAtEnd = $exAdminFilter['created_at_end'] ?? null;
        $dateType = $exAdminFilter['date_type'] ?? null;
        $selectedStoreId = $exAdminFilter['store_id'] ?? null;
        $selectedShift = $exAdminFilter['shift'] ?? null;

        // ========== 第1步：查询店家信息（1条SQL） ==========
        $storesQuery = $admin->childStores()
            ->select(['id', 'nickname', 'username', 'agent_commission', 'channel_commission'])
            ->where('type', AdminUser::TYPE_STORE)
            ->where('status', 1)
            ->orderBy('id', 'desc');

        if (!empty($selectedStoreId)) {
            $storesQuery->where('id', $selectedStoreId);
        }

        $stores = $storesQuery->get();
        $storeIds = $stores->pluck('id')->toArray();

        if (empty($storeIds)) {
            return $this->buildEmptyGrid();
        }

        // ========== 第2步：批量查询所有玩家，按店家分组（1条SQL） ==========
        $allPlayers = Player::query()
            ->whereIn('store_admin_id', $storeIds)
            ->where('is_promoter', 0)
            ->get(['id', 'store_admin_id']);

        $playersByStore = $allPlayers->groupBy('store_admin_id');
        $playerIdsByStore = [];
        foreach ($playersByStore as $storeId => $players) {
            $playerIdsByStore[$storeId] = $players->pluck('id')->toArray();
        }

        // ========== 第3步：获取班次日期范围 ==========
        $shiftDateRange = $this->getShiftDateRange($selectedShift);

        // ========== 第4步：批量查询各类数据 ==========
        $deliveryDataByStore = $this->batchQueryDeliveryData($playerIdsByStore, $selectedShift, $dateType, $createdAtStart, $createdAtEnd, $shiftDateRange);
        $ticketDataByStore = $this->batchQueryTicketData($storeIds, $selectedShift, $dateType, $createdAtStart, $createdAtEnd, $shiftDateRange);
        $redeemDataByStore = $this->batchQueryRedeemData($storeIds, $selectedShift, $dateType, $createdAtStart, $createdAtEnd, $shiftDateRange);
        $lotteryDataByStore = $this->batchQueryLotteryData($playerIdsByStore, $selectedShift, $dateType, $createdAtStart, $createdAtEnd, $shiftDateRange);
        $betDataByStore = $this->batchQueryBetData($storeIds, $selectedShift, $dateType, $createdAtStart, $createdAtEnd, $shiftDateRange);

        // ========== 第5步：组装报表数据 ==========
        $reportData = [];
        foreach ($stores as $store) {
            $storeId = $store->id;
            $playerIds = $playerIdsByStore[$storeId] ?? [];
            $deviceCount = count($playerIds);

            // 开分/洗分/投钞
            $deliveryData = $deliveryDataByStore[$storeId] ?? null;
            $rechargeAmount = floatval($deliveryData->recharge_amount ?? 0);
            $openScoreAmount = floatval($deliveryData->open_score_amount ?? 0);
            $withdrawAmount = floatval($deliveryData->withdraw_amount ?? 0);
            $ticketRedeemAmount = floatval($deliveryData->ticket_redeem_amount ?? 0);
            $machinePutPoint = floatval($deliveryData->machine_put_point ?? 0);
            $activityTotal = floatval($deliveryData->activity_total ?? 0);

            // 票务数据
            $ticketData = $ticketDataByStore[$storeId] ?? null;
            $ticketOpenScoreUsedAmount = floatval($ticketData->ticket_open_score_used_amount ?? 0);
            $ticketOpenScoreAmount = floatval($ticketData->ticket_open_score_amount ?? 0);
            $experienceCouponAmount = floatval($ticketData->experience_coupon_amount ?? 0);
            $welfareCouponAmount = floatval($ticketData->welfare_coupon_amount ?? 0);

            // 核销数据
            $redeemData = $redeemDataByStore[$storeId] ?? null;
            $redeemAmount = floatval($redeemData->redeem_amount ?? 0);
            $redeemMachineAmount = floatval($redeemData->redeem_machine_amount ?? 0);

            $incomingTicketAmount = bcadd($ticketOpenScoreUsedAmount, $redeemMachineAmount, 2);
            // 未核销 = 出卷 - 后台核销 - 机台核销
            $totalRedeem = bcadd($redeemAmount, $redeemMachineAmount, 2);
            $ticketUnredeemedAmount = bcsub($ticketRedeemAmount, $totalRedeem, 2);

            // 拉彩数据
            $lotteryData = $lotteryDataByStore[$storeId] ?? null;
            $lotteryAmount = floatval($lotteryData->lottery_amount ?? 0);

            // 打码量数据
            $betData = $betDataByStore[$storeId] ?? null;
            $electronicGameBetAmount = floatval($betData->electronic_game_bet_amount ?? 0);
            $machineBetAmount = floatval($betData->machine_bet_amount ?? 0);

            // 扣除福利券和体验券
            $ticketAmount = bcadd($experienceCouponAmount, $welfareCouponAmount, 2);
            $rechargeAmount = bcsub($rechargeAmount, $ticketAmount, 2);

            // 计算汇总
            $totalIn = bcadd($rechargeAmount, $machinePutPoint, 2);
            $subtotal = bcsub($totalIn, $withdrawAmount, 2);
            $totalIncome = bcadd($openScoreAmount, $ticketOpenScoreAmount, 2);
            $totalExpense = bcadd($withdrawAmount, $redeemAmount, 2);
            $totalProfit = bcsub($totalIncome, $totalExpense, 2);

            // 分润计算
            $agentCommission = floatval($store->agent_commission ?? 0);
            $agentProfit = bcmul($totalProfit, bcdiv($agentCommission, 100, 4), 2);
            $channelCommission = floatval($store->channel_commission ?? 0);
            $channelProfit = bcmul($totalProfit, bcdiv($channelCommission, 100, 4), 2);

            $reportData[] = [
                'id' => $store->id,
                'store_name' => $store->nickname,
                'store_username' => $store->username,
                'device_count' => $deviceCount,
                'agent_commission' => $agentCommission,
                'channel_commission' => $channelCommission,
                'recharge_amount' => $rechargeAmount,
                'open_score_amount' => $openScoreAmount,
                'withdraw_amount' => $withdrawAmount,
                'machine_put_point' => $machinePutPoint,
                'lottery_amount' => $lotteryAmount,
                'activity_total' => $activityTotal,
                'electronic_game_bet_amount' => $electronicGameBetAmount,
                'machine_bet_amount' => $machineBetAmount,
                'incoming_ticket_amount' => $incomingTicketAmount,
                'ticket_redeem_amount' => $ticketRedeemAmount,
                'ticket_open_score_amount' => $ticketOpenScoreAmount,
                'redeem_amount' => $redeemAmount,
                'redeem_machine_amount' => $redeemMachineAmount,
                'ticket_unredeemed_amount' => $ticketUnredeemedAmount,
                'experience_coupon_amount' => $experienceCouponAmount,
                'welfare_coupon_amount' => $welfareCouponAmount,
                'subtotal' => $subtotal,
                'agent_profit' => $agentProfit,
                'channel_profit' => $channelProfit,
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
                'total_profit' => $totalProfit,
            ];
        }

        // 计算统计数据
        $totalStats = [
            'total_machine_put' => 0,
            'total_lottery' => 0,
            'total_activity' => 0,
            'total_agent_profit' => 0,
            'total_channel_profit' => 0,
            'total_income' => 0,
            'total_expense' => 0,
            'total_profit' => 0,
        ];

        foreach ($reportData as $item) {
            $totalStats['total_machine_put'] = bcadd($totalStats['total_machine_put'], $item['machine_put_point'], 2);
            $totalStats['total_lottery'] = bcadd($totalStats['total_lottery'], $item['lottery_amount'], 2);
            $totalStats['total_activity'] = bcadd($totalStats['total_activity'], $item['activity_total'], 2);
            $totalStats['total_agent_profit'] = bcadd($totalStats['total_agent_profit'], $item['agent_profit'], 2);
            $totalStats['total_channel_profit'] = bcadd($totalStats['total_channel_profit'], $item['channel_profit'], 2);
            $totalStats['total_income'] = bcadd($totalStats['total_income'], $item['total_income'], 2);
            $totalStats['total_expense'] = bcadd($totalStats['total_expense'], $item['total_expense'], 2);
            $totalStats['total_profit'] = bcadd($totalStats['total_profit'], $item['total_profit'], 2);
        }

        // 获取店家选项
        $storeOptions = $this->getStoreOptions($admin);

        return Grid::create($reportData, function (Grid $grid) use ($reportData, $storeOptions, $totalStats) {
            $grid->title(admin_trans('agent_store_profit.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 统计卡片
            $this->buildStatisticCards($grid, $totalStats);

            // 列定义
            $this->buildGridColumns($grid);

            // 筛选器
            $this->buildGridFilter($grid, $storeOptions);

            $grid->hideAction();
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideAdd();
            $grid->expandFilter();
            $grid->attr('is_mongo', true);
            $grid->attr('is_mongo_total', count($reportData));
            $grid->attr('mongo_model', $reportData);

            // 导出功能
            $grid->export(new \addons\webman\grid\AgentStoreProfitMonthlyExporter())
                ->filename('monthly_business_report_' . date('YmdHis'));
        });
    }

    // ========== 批量查询方法 ==========

    private function batchQueryDeliveryData(array $playerIdsByStore, ?string $selectedShift, ?string $dateType, ?string $createdAtStart, ?string $createdAtEnd, ?array $shiftDateRange): array
    {
        $allPlayerIds = array_merge(...array_values($playerIdsByStore ?: [[]]));

        if (empty($allPlayerIds)) {
            return [];
        }

        $query = PlayerDeliveryRecord::query()->whereIn('player_id', $allPlayerIds);
        $this->applyTimeFilter($query, 'created_at', $selectedShift, $dateType, $createdAtStart, $createdAtEnd, $shiftDateRange);

        $deliveryData = $query->selectRaw("
            player_id,
            SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_RECHARGE . " THEN `amount` ELSE 0 END) AS recharge_amount,
            SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_RECHARGE . " AND `source` = 'artificial_recharge' THEN `amount` ELSE 0 END) AS open_score_amount,
            SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " AND `source` = 'channel_withdrawal' THEN `amount` ELSE 0 END) AS withdraw_amount,
            SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " AND `source` = 'ticket_redeem' THEN `amount` ELSE 0 END) AS ticket_redeem_amount,
            SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_MACHINE . " THEN `amount` ELSE 0 END) AS machine_put_point,
            SUM(CASE WHEN `type` IN (" . PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS . "," . PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD . ") THEN `amount` ELSE 0 END) AS activity_total
        ")->groupBy('player_id')->get();

        $dataByPlayer = $deliveryData->keyBy('player_id');

        $result = [];
        foreach ($playerIdsByStore as $storeId => $playerIds) {
            $storeData = (object)[
                'recharge_amount' => 0, 'open_score_amount' => 0, 'withdraw_amount' => 0,
                'ticket_redeem_amount' => 0, 'machine_put_point' => 0, 'activity_total' => 0,
            ];
            foreach ($playerIds as $playerId) {
                $d = $dataByPlayer->get($playerId);
                if ($d) {
                    $storeData->recharge_amount += floatval($d->recharge_amount);
                    $storeData->open_score_amount += floatval($d->open_score_amount);
                    $storeData->withdraw_amount += floatval($d->withdraw_amount);
                    $storeData->ticket_redeem_amount += floatval($d->ticket_redeem_amount);
                    $storeData->machine_put_point += floatval($d->machine_put_point);
                    $storeData->activity_total += floatval($d->activity_total);
                }
            }
            $result[$storeId] = $storeData;
        }

        return $result;
    }

    private function batchQueryTicketData(array $storeIds, ?string $selectedShift, ?string $dateType, ?string $createdAtStart, ?string $createdAtEnd, ?array $shiftDateRange): array
    {
        $query = TicketRecord::query()->whereIn('store_admin_id', $storeIds);
        $this->applyTimeFilter($query, 'created_at', $selectedShift, $dateType, $createdAtStart, $createdAtEnd, $shiftDateRange);

        $ticketData = $query->selectRaw("
            CAST(store_admin_id AS UNSIGNED) as store_admin_id,
            SUM(CASE WHEN `ticket_type` = " . TicketRecord::TYPE_RECHARGE . " THEN `score` ELSE 0 END) AS ticket_open_score_amount,
            SUM(CASE WHEN `ticket_type` = " . TicketRecord::TYPE_RECHARGE . " AND `status` = " . TicketRecord::STATUS_MACHINE_USED . " THEN `score` ELSE 0 END) AS ticket_open_score_used_amount,
            SUM(CASE WHEN `ticket_type` = " . TicketRecord::TYPE_EXPERIENCE . " AND `status` != " . TicketRecord::STATUS_DISABLED . " THEN `score` ELSE 0 END) AS experience_coupon_amount,
            SUM(CASE WHEN `ticket_type` = " . TicketRecord::TYPE_WELFARE . " AND `status` != " . TicketRecord::STATUS_DISABLED . " THEN `score` ELSE 0 END) AS welfare_coupon_amount
        ")->groupBy('store_admin_id')->get();

        $result = [];
        foreach ($ticketData as $item) {
            $result[(int)$item->store_admin_id] = $item;
        }
        return $result;
    }

    private function batchQueryRedeemData(array $storeIds, ?string $selectedShift, ?string $dateType, ?string $createdAtStart, ?string $createdAtEnd, ?array $shiftDateRange): array
    {
        $query = TicketRecord::query()->whereIn('store_admin_id', $storeIds);
        $this->applyTimeFilter($query, 'scanned_at', $selectedShift, $dateType, $createdAtStart, $createdAtEnd, $shiftDateRange);

        $redeemData = $query->selectRaw("
            CAST(store_admin_id AS UNSIGNED) as store_admin_id,
            SUM(CASE WHEN `ticket_type` = " . TicketRecord::TYPE_WITHDRAW . " AND `status` = " . TicketRecord::STATUS_BACKEND_USED . " THEN `score` ELSE 0 END) AS redeem_amount,
            SUM(CASE WHEN `ticket_type` = " . TicketRecord::TYPE_WITHDRAW . " AND `status` = " . TicketRecord::STATUS_MACHINE_USED . " THEN `score` ELSE 0 END) AS redeem_machine_amount
        ")->groupBy('store_admin_id')->get();

        $result = [];
        foreach ($redeemData as $item) {
            $result[(int)$item->store_admin_id] = $item;
        }
        return $result;
    }

    private function batchQueryLotteryData(array $playerIdsByStore, ?string $selectedShift, ?string $dateType, ?string $createdAtStart, ?string $createdAtEnd, ?array $shiftDateRange): array
    {
        $allPlayerIds = array_merge(...array_values($playerIdsByStore ?: [[]]));

        if (empty($allPlayerIds)) {
            return [];
        }

        $query = PlayerLotteryRecord::query()
            ->whereIn('player_id', $allPlayerIds)
            ->where('status', PlayerLotteryRecord::STATUS_COMPLETE);
        $this->applyTimeFilter($query, 'created_at', $selectedShift, $dateType, $createdAtStart, $createdAtEnd, $shiftDateRange);

        $lotteryData = $query->selectRaw("player_id, SUM(`amount`) as lottery_amount")
            ->groupBy('player_id')->get()->keyBy('player_id');

        $result = [];
        foreach ($playerIdsByStore as $storeId => $playerIds) {
            $lotteryAmount = 0;
            foreach ($playerIds as $playerId) {
                $d = $lotteryData->get($playerId);
                if ($d) {
                    $lotteryAmount += floatval($d->lottery_amount);
                }
            }
            $result[$storeId] = (object)['lottery_amount' => $lotteryAmount];
        }

        return $result;
    }

    private function batchQueryBetData(array $storeIds, ?string $selectedShift, ?string $dateType, ?string $createdAtStart, ?string $createdAtEnd, ?array $shiftDateRange): array
    {
        $shiftTable = (new StoreAgentShiftHandoverRecord())->getTable();

        $query = StoreShiftDeviceDetail::query()
            ->join($shiftTable, 'store_shift_device_detail.shift_record_id', '=', $shiftTable . '.id')
            ->whereIn($shiftTable . '.bind_admin_user_id', $storeIds);

        // 打码量使用交班记录的 start_time/end_time
        if (!empty($selectedShift) && $shiftDateRange) {
            $query->where($shiftTable . '.end_time', '>', $shiftDateRange['start'])
                  ->where($shiftTable . '.start_time', '<', $shiftDateRange['end']);
        } elseif (!empty($dateType)) {
            $query->where(getDateWhere($dateType, $shiftTable . '.start_time'));
        } else {
            if (!empty($createdAtStart)) {
                $query->where($shiftTable . '.end_time', '>', $createdAtStart);
            }
            if (!empty($createdAtEnd)) {
                $query->where($shiftTable . '.start_time', '<', $createdAtEnd);
            }
        }

        $betData = $query->selectRaw("
            CAST({$shiftTable}.bind_admin_user_id AS UNSIGNED) as store_admin_id,
            SUM(store_shift_device_detail.electronic_game_bet_amount) as electronic_game_bet_amount,
            SUM(store_shift_device_detail.machine_bet_amount) as machine_bet_amount
        ")->groupBy($shiftTable . '.bind_admin_user_id')->get();

        $result = [];
        foreach ($betData as $item) {
            $result[(int)$item->store_admin_id] = $item;
        }
        return $result;
    }

    // ========== 筛选条件方法 ==========

    private function getShiftDateRange(?string $shift): ?array
    {
        if (empty($shift) || !isset(self::SHIFT_RANGES[$shift])) {
            return null;
        }

        $range = self::SHIFT_RANGES[$shift];
        $startHour = $range['start'];
        $endHour = $range['end'];

        $now = Carbon::now();
        $today8am = Carbon::today()->setTime(8, 0, 0);
        $baseDate = $now->gte($today8am) ? Carbon::today() : Carbon::yesterday();

        if ($shift === 'night') {
            $shiftStart = $baseDate->copy()->addDay()->setTime($startHour, 0, 0);
            $shiftEnd = $baseDate->copy()->addDay()->setTime($endHour, 0, 0);
        } else {
            $shiftStart = $baseDate->copy()->setTime($startHour, 0, 0);
            $shiftEnd = $shift === 'afternoon'
                ? $baseDate->copy()->addDay()->setTime(0, 0, 0)
                : $baseDate->copy()->setTime($endHour, 0, 0);
        }

        return ['start' => $shiftStart, 'end' => $shiftEnd];
    }

    private function applyTimeFilter($query, string $column, ?string $selectedShift, ?string $dateType, ?string $createdAtStart, ?string $createdAtEnd, ?array $shiftDateRange): void
    {
        if (!empty($selectedShift) && $shiftDateRange) {
            $query->where($column, '>=', $shiftDateRange['start']->toDateTimeString())
                  ->where($column, '<', $shiftDateRange['end']->toDateTimeString());
        } elseif (!empty($dateType)) {
            $query->where(getDateWhere($dateType, $column));
        } else {
            if (!empty($createdAtStart)) {
                $query->where($column, '>=', $createdAtStart);
            }
            if (!empty($createdAtEnd)) {
                $query->where($column, '<=', $createdAtEnd);
            }
        }
    }

    // ========== UI 构建方法 ==========

    private function buildEmptyGrid(): Grid
    {
        return Grid::create([], function (Grid $grid) {
            $grid->title(admin_trans('agent_store_profit.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->hideAction();
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideAdd();
        });
    }

    private function buildStatisticCards(Grid $grid, array $totalStats): void
    {
        $exAdminFilter = Request::input('ex_admin_filter', []);

        $layout = Layout::create();
        $layout->row(function (Row $row) use ($exAdminFilter) {
            $row->gutter([10, 0]);
            $row->column(admin_view(plugin()->webman->getPath() . '/views/store_profit_total_info.vue')->attrs([
                'ex_admin_filter' => $exAdminFilter,
                'api_url' => 'ex-admin/addons-webman-controller-AgentStoreProfitReportController/totalInfo',
                'type' => 'AgentStoreProfitReport',
                'admin_user_id' => Admin::user()->id,
                'trans' => [
                    'panelHeader' => admin_trans('agent_store_profit.stats.panel_header'),
                    'loading' => admin_trans('agent_store_profit.stats.loading'),
                    'refresh' => admin_trans('agent_store_profit.stats.refresh'),
                    'loadError' => admin_trans('agent_store_profit.stats.load_error'),
                    'retry' => admin_trans('agent_store_profit.stats.retry'),
                    'clickToView' => admin_trans('agent_store_profit.stats.click_to_view'),
                    'loadFailedMsg' => admin_trans('agent_store_profit.stats.load_failed_msg'),
                ]
            ]));
        })->style(['background' => '#fff']);

        $grid->header($layout);
    }

    private function buildGridColumns(Grid $grid): void
    {
        $grid->column('id', 'ID')->width(80)->align('center');
        $grid->column('store_name', admin_trans('agent_store_profit.fields.store_name'))->width(150)->align('center');
        $grid->column('device_count', admin_trans('agent_store_profit.fields.device_count'))->width(100)->align('center');
        $grid->column('store_username', admin_trans('agent_store_profit.fields.store_username'))->width(120)->align('center');

        $amountColumns = [
            'open_score_amount', 'withdraw_amount', 'machine_put_point',
            'incoming_ticket_amount', 'ticket_redeem_amount', 'ticket_open_score_amount',
            'redeem_amount', 'redeem_machine_amount', 'ticket_unredeemed_amount', 'experience_coupon_amount',
            'welfare_coupon_amount', 'lottery_amount', 'activity_total',
            'electronic_game_bet_amount', 'machine_bet_amount',
            'total_income', 'total_expense',
        ];

        foreach ($amountColumns as $column) {
            $grid->column($column, admin_trans("agent_store_profit.fields.{$column}"))
                ->display(fn($value) => number_format(floatval($value), 2))
                ->width(120)->align('center');
        }

        $grid->column('total_profit', admin_trans('agent_store_profit.fields.total_profit'))
            ->display(function ($value) {
                $color = $value >= 0 ? '#3f8600' : '#cf1322';
                return Html::create(number_format(floatval($value), 2))->style(['color' => $color, 'fontWeight' => 'bold']);
            })->width(120)->align('center');

        $grid->column('agent_commission', admin_trans('agent_store_profit.fields.agent_commission'))
            ->display(fn($value) => $value . '%')->width(100)->align('center');

        $grid->column('agent_profit', admin_trans('agent_store_profit.fields.agent_profit'))
            ->display(function ($value) {
                $color = $value >= 0 ? '#1890ff' : '#fa8c16';
                return Html::create(number_format(floatval($value), 2))->style(['color' => $color, 'fontWeight' => 'bold']);
            })->width(120)->align('center');

        $grid->column('channel_commission', admin_trans('agent_store_profit.fields.channel_commission'))
            ->display(fn($value) => $value . '%')->width(100)->align('center');

        $grid->column('channel_profit', admin_trans('agent_store_profit.fields.channel_profit'))
            ->display(function ($value) {
                $color = $value >= 0 ? '#52c41a' : '#f5222d';
                return Html::create(number_format(floatval($value), 2))->style(['color' => $color, 'fontWeight' => 'bold']);
            })->width(120)->align('center');
    }

    private function buildGridFilter(Grid $grid, array $storeOptions): void
    {
        $grid->filter(function (Filter $filter) use ($storeOptions) {
            $filter->eq()->select('store_id')
                ->placeholder(admin_trans('agent_store_profit.filter.select_store'))
                ->options(['' => admin_trans('agent_store_profit.filter.all_stores')] + $storeOptions)
                ->style(['width' => '300px']);

            $filter->eq()->select('shift')
                ->placeholder(admin_trans('agent_store_profit.filter.select_shift'))
                ->options([
                    '' => admin_trans('agent_store_profit.filter.all_shifts'),
                    'morning' => admin_trans('agent_store_profit.shift.morning'),
                    'afternoon' => admin_trans('agent_store_profit.shift.afternoon'),
                    'night' => admin_trans('agent_store_profit.shift.night'),
                ])
                ->style(['width' => '200px']);

            $filter->select('date_type')
                ->placeholder(admin_trans('machine_report.fields.date_type'))
                ->showSearch()->dropdownMatchSelectWidth()
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
            $filter->form()->dateTimeRange('created_at_start', 'created_at_end', admin_trans('agent_store_profit.filter.time_range'))->placeholder([
                admin_trans('agent_store_profit.filter.start_time'),
                admin_trans('agent_store_profit.filter.end_time')
            ]);
        });
    }

    private function getStoreOptions(AdminUser $admin): array
    {
        return $admin->childStores()
            ->where('type', AdminUser::TYPE_STORE)
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->get(['id', 'nickname', 'username'])
            ->mapWithKeys(function ($store) {
                $label = $store->nickname ?: $store->username;
                $label .= " ({$store->username})";
                return [$store->id => $label];
            })
            ->toArray();
    }

    /**
     * 导出月度营业状况报表
     * @group agent
     * @auth true
     * @return void
     */
    public function export()
    {
        // 此方法仅用于权限控制，实际导出由 Grid 的 export 功能处理
    }

    /**
     * 获取统计数据（异步接口）
     * @group agent
     * @auth true
     * @return \support\Response
     */
    public function totalInfo(): \support\Response
    {
        $request = Request::input();
        $exAdminFilter = $request['ex_admin_filter'] ?? [];
        $admin = Admin::user();

        $createdAtStart = $exAdminFilter['created_at_start'] ?? null;
        $createdAtEnd = $exAdminFilter['created_at_end'] ?? null;
        $dateType = $exAdminFilter['date_type'] ?? null;
        $selectedStoreId = $exAdminFilter['store_id'] ?? null;
        $selectedShift = $exAdminFilter['shift'] ?? null;

        // 查询店家
        $storesQuery = $admin->childStores()
            ->where('type', AdminUser::TYPE_STORE)
            ->where('status', 1);

        if (!empty($selectedStoreId)) {
            $storesQuery->where('id', $selectedStoreId);
        }

        $storeIds = $storesQuery->pluck('id')->toArray();

        if (empty($storeIds)) {
            return json([
                'code' => 200,
                'data' => $this->getEmptyStatsData()
            ]);
        }

        // 查询玩家
        $allPlayers = Player::query()
            ->whereIn('store_admin_id', $storeIds)
            ->where('is_promoter', 0)
            ->get(['id', 'store_admin_id']);

        $playersByStore = $allPlayers->groupBy('store_admin_id');
        $playerIdsByStore = [];
        foreach ($playersByStore as $storeId => $players) {
            $playerIdsByStore[$storeId] = $players->pluck('id')->toArray();
        }

        $shiftDateRange = $this->getShiftDateRange($selectedShift);

        // 批量查询
        $deliveryDataByStore = $this->batchQueryDeliveryData($playerIdsByStore, $selectedShift, $dateType, $createdAtStart, $createdAtEnd, $shiftDateRange);
        $ticketDataByStore = $this->batchQueryTicketData($storeIds, $selectedShift, $dateType, $createdAtStart, $createdAtEnd, $shiftDateRange);
        $redeemDataByStore = $this->batchQueryRedeemData($storeIds, $selectedShift, $dateType, $createdAtStart, $createdAtEnd, $shiftDateRange);
        $lotteryDataByStore = $this->batchQueryLotteryData($playerIdsByStore, $selectedShift, $dateType, $createdAtStart, $createdAtEnd, $shiftDateRange);
        $betDataByStore = $this->batchQueryBetData($storeIds, $selectedShift, $dateType, $createdAtStart, $createdAtEnd, $shiftDateRange);

        // 汇总统计
        $totalStats = [
            'total_income' => 0,
            'total_expense' => 0,
            'total_profit' => 0,
            'total_machine_put' => 0,
            'total_lottery' => 0,
            'total_activity' => 0,
            'total_agent_profit' => 0,
            'total_channel_profit' => 0,
        ];

        foreach ($storeIds as $storeId) {
            $deliveryData = $deliveryDataByStore[$storeId] ?? null;
            $ticketData = $ticketDataByStore[$storeId] ?? null;
            $redeemData = $redeemDataByStore[$storeId] ?? null;
            $lotteryData = $lotteryDataByStore[$storeId] ?? null;

            $rechargeAmount = floatval($deliveryData->recharge_amount ?? 0);
            $openScoreAmount = floatval($deliveryData->open_score_amount ?? 0);
            $withdrawAmount = floatval($deliveryData->withdraw_amount ?? 0);
            $machinePutPoint = floatval($deliveryData->machine_put_point ?? 0);
            $activityTotal = floatval($deliveryData->activity_total ?? 0);

            $ticketOpenScoreUsedAmount = floatval($ticketData->ticket_open_score_used_amount ?? 0);
            $ticketOpenScoreAmount = floatval($ticketData->ticket_open_score_amount ?? 0);
            $experienceCouponAmount = floatval($ticketData->experience_coupon_amount ?? 0);
            $welfareCouponAmount = floatval($ticketData->welfare_coupon_amount ?? 0);

            $redeemAmount = floatval($redeemData->redeem_amount ?? 0);

            $lotteryAmount = floatval($lotteryData->lottery_amount ?? 0);

            $ticketAmount = bcadd($experienceCouponAmount, $welfareCouponAmount, 2);
            $rechargeAmount = bcsub($rechargeAmount, $ticketAmount, 2);

            $totalIn = bcadd($rechargeAmount, $machinePutPoint, 2);
            $totalIncome = bcadd($openScoreAmount, $ticketOpenScoreAmount, 2);
            $totalExpense = bcadd($withdrawAmount, $redeemAmount, 2);
            $totalProfit = bcsub($totalIncome, $totalExpense, 2);

            $store = AdminUser::find($storeId);
            $agentCommission = floatval($store->agent_commission ?? 0);
            $agentProfit = bcmul($totalProfit, bcdiv($agentCommission, 100, 4), 2);
            $channelCommission = floatval($store->channel_commission ?? 0);
            $channelProfit = bcmul($totalProfit, bcdiv($channelCommission, 100, 4), 2);

            $totalStats['total_income'] = bcadd($totalStats['total_income'], $totalIncome, 2);
            $totalStats['total_expense'] = bcadd($totalStats['total_expense'], $totalExpense, 2);
            $totalStats['total_profit'] = bcadd($totalStats['total_profit'], $totalProfit, 2);
            $totalStats['total_machine_put'] = bcadd($totalStats['total_machine_put'], $machinePutPoint, 2);
            $totalStats['total_lottery'] = bcadd($totalStats['total_lottery'], $lotteryAmount, 2);
            $totalStats['total_activity'] = bcadd($totalStats['total_activity'], $activityTotal, 2);
            $totalStats['total_agent_profit'] = bcadd($totalStats['total_agent_profit'], $agentProfit, 2);
            $totalStats['total_channel_profit'] = bcadd($totalStats['total_channel_profit'], $channelProfit, 2);
        }

        $data = [
            ['title' => admin_trans('agent_store_profit.stats.total_income'), 'number' => floatval($totalStats['total_income']), 'prefix' => '', 'suffix' => ''],
            ['title' => admin_trans('agent_store_profit.stats.total_expense'), 'number' => floatval($totalStats['total_expense']), 'prefix' => '', 'suffix' => ''],
            ['title' => admin_trans('agent_store_profit.stats.total_profit'), 'number' => floatval($totalStats['total_profit']), 'prefix' => '', 'suffix' => ''],
            ['title' => admin_trans('agent_store_profit.stats.total_machine_put'), 'number' => floatval($totalStats['total_machine_put']), 'prefix' => '', 'suffix' => ''],
            ['title' => admin_trans('agent_store_profit.stats.total_lottery'), 'number' => floatval($totalStats['total_lottery']), 'prefix' => '', 'suffix' => ''],
            ['title' => admin_trans('agent_store_profit.stats.total_activity'), 'number' => floatval($totalStats['total_activity']), 'prefix' => '', 'suffix' => ''],
            ['title' => admin_trans('agent_store_profit.stats.total_agent_profit'), 'number' => floatval($totalStats['total_agent_profit']), 'prefix' => '', 'suffix' => ''],
            ['title' => admin_trans('agent_store_profit.stats.total_channel_profit'), 'number' => floatval($totalStats['total_channel_profit']), 'prefix' => '', 'suffix' => ''],
        ];

        return json(['code' => 200, 'data' => $data]);
    }

    /**
     * 获取空统计数据
     * @return array
     */
    private function getEmptyStatsData(): array
    {
        return [
            ['title' => admin_trans('agent_store_profit.stats.total_income'), 'number' => 0, 'prefix' => '', 'suffix' => ''],
            ['title' => admin_trans('agent_store_profit.stats.total_expense'), 'number' => 0, 'prefix' => '', 'suffix' => ''],
            ['title' => admin_trans('agent_store_profit.stats.total_profit'), 'number' => 0, 'prefix' => '', 'suffix' => ''],
            ['title' => admin_trans('agent_store_profit.stats.total_machine_put'), 'number' => 0, 'prefix' => '', 'suffix' => ''],
            ['title' => admin_trans('agent_store_profit.stats.total_lottery'), 'number' => 0, 'prefix' => '', 'suffix' => ''],
            ['title' => admin_trans('agent_store_profit.stats.total_activity'), 'number' => 0, 'prefix' => '', 'suffix' => ''],
            ['title' => admin_trans('agent_store_profit.stats.total_agent_profit'), 'number' => 0, 'prefix' => '', 'suffix' => ''],
            ['title' => admin_trans('agent_store_profit.stats.total_channel_profit'), 'number' => 0, 'prefix' => '', 'suffix' => ''],
        ];
    }
}
