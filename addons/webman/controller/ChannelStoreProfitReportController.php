<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerLotteryRecord;
use addons\webman\model\PlayerWithdrawRecord;
use addons\webman\model\StoreAgentShiftHandoverRecord;
use addons\webman\model\TicketRecord;
use addons\webman\model\StoreShiftDeviceDetail;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\support\Request;

/**
 * 渠道后台 - 店家分润报表
 * @group channel
 */
class ChannelStoreProfitReportController
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
     * @group channel
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
        $selectedAgentId = $exAdminFilter['agent_id'] ?? null;
        $remarkKeyword = $exAdminFilter['remark'] ?? null;
        $selectedShift = $exAdminFilter['shift'] ?? null;

        // 获取渠道下的所有店家
        // 渠道可以看到自己的直属店家 + 下属代理的店家
        $allStoresQuery = AdminUser::query()
            ->where('department_id', $admin->department_id)
            ->where('type', AdminUser::TYPE_STORE)
            ->where('status', 1)
            ->orderBy('id', 'desc');

        // 如果选择了特定代理，只查询该代理下的店家
        if (!empty($selectedAgentId)) {
            $allStoresQuery->where('parent_admin_id', $selectedAgentId);
        }

        // 如果选择了特定店家，添加店家ID筛选
        if (!empty($selectedStoreId)) {
            $allStoresQuery->where('id', $selectedStoreId);
        }

        // 如果输入了备注关键词，进行模糊搜索
        if (!empty($remarkKeyword)) {
            $allStoresQuery->where('remark', 'like', '%' . $remarkKeyword . '%');
        }

        // 获取符合所有筛选条件的店家ID列表
        $storeIds = $allStoresQuery->pluck('id')->toArray();

        // 构建报表数据
        $reportData = [];

        foreach ($storeIds as $storeId) {
            $store = AdminUser::find($storeId);
            if (!$store) {
                continue;
            }

            // 获取代理信息
            $agent = AdminUser::find($store->parent_admin_id);
            $agentName = $agent ? ($agent->nickname ?: $agent->username) : '-';

            // 获取该店家下的所有玩家
            $playerIds = Player::query()
                ->where('store_admin_id', $storeId)
                ->where('is_promoter', 0)
                ->pluck('id')
                ->toArray();

            // 统计设备数量（玩家数量）
            $deviceCount = count($playerIds);

            if (empty($playerIds)) {
                // 没有玩家也要显示店家信息
                $reportData[] = [
                    'id' => $store->id,
                    'store_name' => $store->nickname,
                    'store_username' => $store->username,
                    'agent_name' => $agentName,
                    'device_count' => $deviceCount,
                    'agent_commission' => $store->agent_commission ?? 0,
                    'channel_commission' => $store->channel_commission ?? 0,
                    'remark' => $store->remark ?? '',
                    'recharge_amount' => 0,
                    'open_score_amount' => 0,
                    'withdraw_amount' => 0,
                    'machine_put_point' => 0,
                    'lottery_amount' => 0,
                    'activity_total' => 0,
                    'electronic_game_bet_amount' => 0,
                    'machine_bet_amount' => 0,
                    'incoming_ticket_amount' => 0,
                    'ticket_redeem_amount' => 0,
                    'ticket_open_score_amount' => 0,
                    'redeem_amount' => 0,
                    'ticket_unredeemed_amount' => 0,
                    'experience_coupon_amount' => 0,
                    'welfare_coupon_amount' => 0,
                    'subtotal' => 0,
                    'agent_profit' => 0,
                    'channel_profit' => 0,
                    'total_income' => 0,
                    'total_expense' => 0,
                    'total_profit' => 0,
                ];
                continue;
            }

            // 查询开分、洗分、投钞数据
            $deliveryQuery = PlayerDeliveryRecord::query()
                ->whereIn('player_id', $playerIds);

            // 时间筛选：优先使用结算周期，否则使用手动时间范围
            if (!empty($dateType)) {
                $deliveryQuery->where(getDateWhere($dateType, 'created_at'));
            } else {
                if (!empty($createdAtStart)) {
                    $deliveryQuery->where('created_at', '>=', $createdAtStart);
                }
                if (!empty($createdAtEnd)) {
                    $deliveryQuery->where('created_at', '<=', $createdAtEnd);
                }
            }

            // 班次筛选
            if (!empty($selectedShift)) {
                $this->applyShiftFilter($deliveryQuery, $selectedShift);
            }

            $deliveryData = $deliveryQuery->selectRaw("
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_RECHARGE . " THEN `amount` ELSE 0 END) AS recharge_amount,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_RECHARGE . " AND `source` = 'artificial_recharge' THEN `amount` ELSE 0 END) AS open_score_amount,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " AND `source` = 'channel_withdrawal' THEN `amount` ELSE 0 END) AS withdraw_amount,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " AND `source` = 'ticket_redeem' THEN `amount` ELSE 0 END) AS ticket_redeem_amount,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_MACHINE . " THEN `amount` ELSE 0 END) AS machine_put_point,
                SUM(CASE WHEN `type` IN (" . PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS . "," . PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD . ") THEN `amount` ELSE 0 END) AS activity_total
            ")->first();

            // 查询票务数据（入票、出卷、开票、核销、未核销、体验券、福利券）
            $ticketQuery = TicketRecord::query()
                ->whereIn('player_id', $playerIds);

            // 时间筛选：优先使用结算周期，否则使用手动时间范围
            if (!empty($dateType)) {
                $ticketQuery->where(getDateWhere($dateType, 'created_at'));
            } else {
                if (!empty($createdAtStart)) {
                    $ticketQuery->where('created_at', '>=', $createdAtStart);
                }
                if (!empty($createdAtEnd)) {
                    $ticketQuery->where('created_at', '<=', $createdAtEnd);
                }
            }

            // 班次筛选
            if (!empty($selectedShift)) {
                $this->applyShiftFilter($ticketQuery, $selectedShift);
            }

            $ticketData = $ticketQuery->selectRaw("
                SUM(CASE WHEN `ticket_type` = " . TicketRecord::TYPE_RECHARGE . " THEN `score` ELSE 0 END) AS ticket_open_score_amount,
                SUM(CASE WHEN `ticket_type` = " . TicketRecord::TYPE_RECHARGE . " AND `status` = " . TicketRecord::STATUS_MACHINE_USED . " THEN `score` ELSE 0 END) AS ticket_open_score_used_amount,
                SUM(CASE WHEN `ticket_type` = " . TicketRecord::TYPE_WITHDRAW . " AND `status` = " . TicketRecord::STATUS_BACKEND_USED . " THEN `score` ELSE 0 END) AS redeem_amount,
                SUM(CASE WHEN `ticket_type` = " . TicketRecord::TYPE_WITHDRAW . " AND `status` = " . TicketRecord::STATUS_MACHINE_USED . " THEN `score` ELSE 0 END) AS redeem_machine_amount,
                SUM(CASE WHEN `ticket_type` = " . TicketRecord::TYPE_EXPERIENCE . " AND `status` IN (" . TicketRecord::STATUS_BACKEND_USED . "," . TicketRecord::STATUS_MACHINE_USED . ") THEN `score` ELSE 0 END) AS experience_coupon_amount,
                SUM(CASE WHEN `ticket_type` = " . TicketRecord::TYPE_WELFARE . " AND `status` IN (" . TicketRecord::STATUS_BACKEND_USED . "," . TicketRecord::STATUS_MACHINE_USED . ") THEN `score` ELSE 0 END) AS welfare_coupon_amount
            ")->first();

            // 查询拉彩数据
            $lotteryQuery = PlayerLotteryRecord::query()
                ->whereIn('player_id', $playerIds)
                ->where('status', PlayerLotteryRecord::STATUS_COMPLETE);

            // 时间筛选：优先使用结算周期，否则使用手动时间范围
            if (!empty($dateType)) {
                $lotteryQuery->where(getDateWhere($dateType, 'created_at'));
            } else {
                if (!empty($createdAtStart)) {
                    $lotteryQuery->where('created_at', '>=', $createdAtStart);
                }
                if (!empty($createdAtEnd)) {
                    $lotteryQuery->where('created_at', '<=', $createdAtEnd);
                }
            }

            // 班次筛选
            if (!empty($selectedShift)) {
                $this->applyShiftFilter($lotteryQuery, $selectedShift);
            }

            $lotteryData = $lotteryQuery->selectRaw("
                SUM(`amount`) as lottery_amount
            ")->first();

            // 查询电子游戏打码量和机器打码量
            $shiftTable = (new StoreAgentShiftHandoverRecord())->getTable();
            $betQuery = StoreShiftDeviceDetail::query()
                ->join($shiftTable, 'store_shift_device_detail.shift_record_id', '=', $shiftTable . '.id')
                ->where($shiftTable . '.bind_admin_user_id', $storeId);

            // 时间筛选：优先使用结算周期，否则使用手动时间范围
            if (!empty($dateType)) {
                $betQuery->where(getDateWhere($dateType, $shiftTable . '.created_at'));
            } else {
                if (!empty($createdAtStart)) {
                    $betQuery->where($shiftTable . '.created_at', '>=', $createdAtStart);
                }
                if (!empty($createdAtEnd)) {
                    $betQuery->where($shiftTable . '.created_at', '<=', $createdAtEnd);
                }
            }

            // 班次筛选
            if (!empty($selectedShift)) {
                $this->applyShiftFilter($betQuery, $selectedShift, $shiftTable);
            }

            $betData = $betQuery->selectRaw("
                SUM(store_shift_device_detail.electronic_game_bet_amount) as electronic_game_bet_amount,
                SUM(store_shift_device_detail.machine_bet_amount) as machine_bet_amount
            ")->first();

            // 提取数据
            $rechargeAmount = floatval($deliveryData->recharge_amount ?? 0);
            $openScoreAmount = floatval($deliveryData->open_score_amount ?? 0);          // 开分（人工储值）
            $withdrawAmount = floatval($deliveryData->withdraw_amount ?? 0);
            $ticketRedeemAmount = floatval($deliveryData->ticket_redeem_amount ?? 0);    // 出卷（从账变记录查询）
            $machinePutPoint = floatval($deliveryData->machine_put_point ?? 0);
            $activityTotal = floatval($deliveryData->activity_total ?? 0);
            $lotteryAmount = floatval($lotteryData->lottery_amount ?? 0);
            $electronicGameBetAmount = floatval($betData->electronic_game_bet_amount ?? 0);
            $machineBetAmount = floatval($betData->machine_bet_amount ?? 0);

            // 票务数据
            $ticketOpenScoreUsedAmount = floatval($ticketData->ticket_open_score_used_amount ?? 0); // 开票机台使用
            $ticketOpenScoreAmount = floatval($ticketData->ticket_open_score_amount ?? 0); // 开票（未使用）
            $redeemAmount = floatval($ticketData->redeem_amount ?? 0);                   // 核销（后台核销）
            $redeemMachineAmount = floatval($ticketData->redeem_machine_amount ?? 0);     // 核销（机台核销）
            // 入票 = 开票机台使用 + 核销机台使用（与导出报表逻辑一致）
            $incomingTicketAmount = bcadd($ticketOpenScoreUsedAmount, $redeemMachineAmount, 2);
            // 未核销 = 出卷 - 核销（后台核销）
            $ticketUnredeemedAmount = bcsub($ticketRedeemAmount, $redeemAmount, 2);
            $experienceCouponAmount = floatval($ticketData->experience_coupon_amount ?? 0); // 体验券
            $welfareCouponAmount = floatval($ticketData->welfare_coupon_amount ?? 0);     // 福利券

            // 从开分中扣除福利券和体验券的金额
            $ticketAmount = bcadd($experienceCouponAmount, $welfareCouponAmount, 2);
            $rechargeAmount = bcsub($rechargeAmount, $ticketAmount, 2);

            // 计算小计 = (开分 + 投钞) - 洗分
            $totalIn = bcadd($rechargeAmount, $machinePutPoint, 2);
            $subtotal = bcsub($totalIn, $withdrawAmount, 2);

            // 计算总收入 = 开分 + 开票（与导出报表一致）
            $totalIncome = bcadd($openScoreAmount, $ticketOpenScoreAmount, 2);

            // 计算总支出 = 洗分 + 核销金额（后台核销）
            $totalExpense = bcadd($withdrawAmount, $redeemAmount, 2);

            // 计算总利润 = 总收入 - 总支出
            $totalProfit = bcsub($totalIncome, $totalExpense, 2);

            // 计算代理分润：利润 * 代理抽成比例
            $agentCommission = floatval($store->agent_commission ?? 0);
            $agentProfit = bcmul($totalProfit, bcdiv($agentCommission, 100, 4), 2);

            // 计算渠道分润：利润 * 渠道抽成比例
            $channelCommission = floatval($store->channel_commission ?? 0);
            $channelProfit = bcmul($totalProfit, bcdiv($channelCommission, 100, 4), 2);

            $reportData[] = [
                'id' => $store->id,
                'store_name' => $store->nickname,
                'store_username' => $store->username,
                'agent_name' => $agentName,
                'device_count' => $deviceCount,
                'agent_commission' => $agentCommission,
                'channel_commission' => $channelCommission,
                'remark' => $store->remark ?? '',
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
            $totalStats['total_activity'] = bcadd($totalStats['total_activity'] ?? 0, $item['activity_total'], 2);
            $totalStats['total_agent_profit'] = bcadd($totalStats['total_agent_profit'], $item['agent_profit'], 2);
            $totalStats['total_channel_profit'] = bcadd($totalStats['total_channel_profit'], $item['channel_profit'], 2);
            $totalStats['total_income'] = bcadd($totalStats['total_income'], $item['total_income'], 2);
            $totalStats['total_expense'] = bcadd($totalStats['total_expense'], $item['total_expense'], 2);
            $totalStats['total_profit'] = bcadd($totalStats['total_profit'], $item['total_profit'], 2);
        }

        // 获取店家选项列表用于筛选器下拉选择
        $storeOptions = AdminUser::query()
            ->where('department_id', $admin->department_id)
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

        // 获取代理选项列表用于筛选器下拉选择
        $agentOptions = AdminUser::query()
            ->where('department_id', $admin->department_id)
            ->where('type', AdminUser::TYPE_AGENT)
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->get(['id', 'nickname', 'username'])
            ->mapWithKeys(function ($agent) {
                $label = $agent->nickname ?: $agent->username;
                $label .= " ({$agent->username})";
                return [$agent->id => $label];
            })
            ->toArray();

        return Grid::create($reportData, function (Grid $grid) use ($exAdminFilter, $reportData, $storeOptions, $agentOptions, $totalStats) {
            $grid->title(admin_trans('channel_store_profit.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 统计卡片
            $layout = Layout::create()->style(['background' => '#fff', 'padding' => '10px']);
            $layout->row(function (Row $row) use ($totalStats) {
                $row->gutter([10, 10]);

                // 累计总收入
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(floatval($totalStats['total_income']))
                            ->precision(2)
                            ->prefix(admin_trans('channel_store_profit.stats.total_income'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#52c41a'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 8);

                // 累计总支出
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(floatval($totalStats['total_expense']))
                            ->precision(2)
                            ->prefix(admin_trans('channel_store_profit.stats.total_expense'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#f5222d'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 8);

                // 累计总利润
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(floatval($totalStats['total_profit']))
                            ->precision(2)
                            ->prefix(admin_trans('channel_store_profit.stats.total_profit'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => floatval($totalStats['total_profit']) >= 0 ? '#3f8600' : '#cf1322'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 8);
            });

            $layout->row(function (Row $row) use ($totalStats) {
                $row->gutter([10, 0]);

                // 投钞
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(floatval($totalStats['total_machine_put']))
                            ->precision(2)
                            ->prefix(admin_trans('channel_store_profit.stats.total_machine_put'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#1890ff'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    ->style(['margin-top' => '10px'])
                    , 5);

                // 彩金
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(floatval($totalStats['total_lottery']))
                            ->precision(2)
                            ->prefix(admin_trans('channel_store_profit.stats.total_lottery'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#eb2f96'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                        ->style(['margin-top' => '10px'])
                    , 5);

                // 活动奖励
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(floatval($totalStats['total_activity']))
                            ->precision(2)
                            ->prefix(admin_trans('channel_store_profit.stats.total_activity'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#fa8c16'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                        ->style(['margin-top' => '10px'])
                    , 5);

                // 代理分润
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(floatval($totalStats['total_agent_profit']))
                            ->precision(2)
                            ->prefix(admin_trans('channel_store_profit.stats.total_agent_profit'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#722ed1'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                        ->style(['margin-top' => '10px'])
                    , 5);

                // 渠道分润
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(floatval($totalStats['total_channel_profit']))
                            ->precision(2)
                            ->prefix(admin_trans('channel_store_profit.stats.total_channel_profit'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#13c2c2'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                        ->style(['margin-top' => '10px'])
                    , 4);
            });
            $grid->tools([$layout]);

            // ID
            $grid->column('id', 'ID')->width(80)->align('center');

            // 店家名称
            $grid->column('store_name', admin_trans('channel_store_profit.fields.store_name'))->width(150)->align('center');

            // 设备数量
            $grid->column('device_count', admin_trans('channel_store_profit.fields.device_count'))->width(100)->align('center');

            // 登录账号
            $grid->column('store_username', admin_trans('channel_store_profit.fields.store_username'))->width(120)->align('center');

            // 所属代理
            $grid->column('agent_name', admin_trans('channel_store_profit.fields.agent_name'))->width(120)->align('center');

            // 开分
            $grid->column('recharge_amount', admin_trans('channel_store_profit.fields.recharge_amount'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            // 洗分
            $grid->column('withdraw_amount', admin_trans('channel_store_profit.fields.withdraw_amount'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            // 投钞
            $grid->column('machine_put_point', admin_trans('channel_store_profit.fields.machine_put_point'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            // 入票
            $grid->column('incoming_ticket_amount', admin_trans('channel_store_profit.fields.incoming_ticket_amount'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            // 出卷
            $grid->column('ticket_redeem_amount', admin_trans('channel_store_profit.fields.ticket_redeem_amount'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            // 开票
            $grid->column('ticket_open_score_amount', admin_trans('channel_store_profit.fields.ticket_open_score_amount'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            // 核销
            $grid->column('redeem_amount', admin_trans('channel_store_profit.fields.redeem_amount'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            // 未核销
            $grid->column('ticket_unredeemed_amount', admin_trans('channel_store_profit.fields.ticket_unredeemed_amount'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            // 体验券
            $grid->column('experience_coupon_amount', admin_trans('channel_store_profit.fields.experience_coupon_amount'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            // 福利券
            $grid->column('welfare_coupon_amount', admin_trans('channel_store_profit.fields.welfare_coupon_amount'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            // 彩金
            $grid->column('lottery_amount', admin_trans('channel_store_profit.fields.lottery_amount'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            // 活动奖励
            $grid->column('activity_total', admin_trans('channel_store_profit.fields.activity_total'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            // 电子打码量
            $grid->column('electronic_game_bet_amount', admin_trans('channel_store_profit.fields.electronic_game_bet_amount'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            // 机器打码量
            $grid->column('machine_bet_amount', admin_trans('channel_store_profit.fields.machine_bet_amount'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            // 总收入
            $grid->column('total_income', admin_trans('channel_store_profit.fields.total_income'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            // 总支出
            $grid->column('total_expense', admin_trans('channel_store_profit.fields.total_expense'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            // 利润
            $grid->column('total_profit', admin_trans('channel_store_profit.fields.total_profit'))->display(function ($value) {
                $color = $value >= 0 ? '#3f8600' : '#cf1322';
                return Html::create(number_format(floatval($value), 2))->style(['color' => $color, 'fontWeight' => 'bold']);
            })->width(120)->align('center');

            // 代理抽成比例
            $grid->column('agent_commission', admin_trans('channel_store_profit.fields.agent_commission'))->display(function ($value) {
                return $value . '%';
            })->width(100)->align('center');

            // 代理分润
            $grid->column('agent_profit', admin_trans('channel_store_profit.fields.agent_profit'))->display(function ($value) {
                $color = $value >= 0 ? '#1890ff' : '#fa8c16';
                return Html::create(number_format(floatval($value), 2))->style(['color' => $color, 'fontWeight' => 'bold']);
            })->width(120)->align('center');

            // 渠道抽成比例
            $grid->column('channel_commission', admin_trans('channel_store_profit.fields.channel_commission'))->display(function ($value) {
                return $value . '%';
            })->width(100)->align('center');

            // 渠道分润
            $grid->column('channel_profit', admin_trans('channel_store_profit.fields.channel_profit'))->display(function ($value) {
                $color = $value >= 0 ? '#52c41a' : '#f5222d';
                return Html::create(number_format(floatval($value), 2))->style(['color' => $color, 'fontWeight' => 'bold']);
            })->width(120)->align('center');

            // 备注
            $grid->column('remark', admin_trans('channel_store_profit.fields.remark'))
                ->editable(
                    (new Editable)
                        ->textarea('remark')
                        ->showCount()
                        ->maxLength(500)
                        ->rows(3)
                )->width(200)->align('center')->ellipsis(true);

            // 筛选器
            $grid->filter(function (Filter $filter) use ($storeOptions, $agentOptions) {
                // 代理下拉选择
                $filter->eq()->select('agent_id')
                    ->placeholder(admin_trans('channel_store_profit.filter.select_agent'))
                    ->options(['' => admin_trans('channel_store_profit.filter.all_agents')] + $agentOptions)
                    ->style(['width' => '250px']);

                // 店家下拉选择
                $filter->eq()->select('store_id')
                    ->placeholder(admin_trans('channel_store_profit.filter.select_store'))
                    ->options(['' => admin_trans('channel_store_profit.filter.all_stores')] + $storeOptions)
                    ->style(['width' => '300px']);

                // 班次下拉选择
                $filter->eq()->select('shift')
                    ->placeholder(admin_trans('channel_store_profit.filter.select_shift'))
                    ->options([
                        '' => admin_trans('channel_store_profit.filter.all_shifts'),
                        'morning' => admin_trans('channel_store_profit.shift.morning'),
                        'afternoon' => admin_trans('channel_store_profit.shift.afternoon'),
                        'night' => admin_trans('channel_store_profit.shift.night'),
                    ])
                    ->style(['width' => '200px']);

                // 备注模糊搜索
                $filter->like()->text('remark')
                    ->placeholder(admin_trans('channel_store_profit.filter.remark_placeholder'))
                    ->style(['width' => '200px']);

                // 结算周期下拉选择
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
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', admin_trans('channel_store_profit.filter.time_range'))->placeholder([
                    admin_trans('channel_store_profit.filter.start_time'),
                    admin_trans('channel_store_profit.filter.end_time')
                ]);
            });

            // 处理列表编辑更新
            $grid->updateing(function ($ids, $data) {
                if (isset($ids[0]) && isset($data['remark'])) {
                    if (AdminUser::query()->where('id', $ids[0])->update(['remark' => $data['remark']])) {
                        return message_success(admin_trans('channel_store_profit.message.update_success'));
                    }
                }
            });

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

    /**
     * 应用班次筛选条件
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $shift 班次类型: morning/afternoon/night
     * @return void
     */
    private function applyShiftFilter($query, string $shift, string $tablePrefix = ''): void
    {
        if (!isset(self::SHIFT_RANGES[$shift])) {
            return;
        }

        $range = self::SHIFT_RANGES[$shift];
        $startHour = $range['start'];
        $endHour = $range['end'];
        $column = $tablePrefix ? $tablePrefix . '.created_at' : 'created_at';

        if ($startHour < $endHour) {
            // 正常时间范围（如早班 08-16）
            $query->whereRaw("HOUR({$column}) >= ? AND HOUR({$column}) < ?", [$startHour, $endHour]);
        } else {
            // 跨午夜时间范围（如晚班 00-08，实际是 0-8）
            // 这里 startHour=0, endHour=8，所以是正常范围
            $query->whereRaw("HOUR({$column}) >= ? AND HOUR({$column}) < ?", [$startHour, $endHour]);
        }
    }
}
