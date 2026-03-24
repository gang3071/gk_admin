<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Currency;
use addons\webman\model\GameType;
use addons\webman\model\mongo\MachineOperationLog;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerGameLog;
use addons\webman\model\PlayerLoginRecord;
use addons\webman\model\PlayerLotteryRecord;
use addons\webman\model\PlayerPresentRecord;
use addons\webman\model\PlayerRechargeRecord;
use addons\webman\model\PlayerWithdrawRecord;
use addons\webman\model\PlayGameRecord;
use addons\webman\model\StoreAgentShiftHandoverRecord;
use addons\webman\model\StoreAutoShiftConfig;
use addons\webman\model\StoreAutoShiftLog;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\echart\BarChart;
use ExAdmin\ui\component\echart\LineChart;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\layout\Divider;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\component\navigation\dropdown\Dropdown;
use ExAdmin\ui\support\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use MongoDB\BSON\UTCDateTime;
use support\Db;
use support\Log;
use support\Response;

/**
 * 数据中心
 * @group channel
 */
class ChannelIndexController
{
    /**
     * 数据中心
     * @group channel
     * @auth true
     */
    public function index($data_type = null): Layout
    {
        $rechargeData = $this->rechargeData($data_type);
        $withdrawData = $this->withdrawData($data_type);
        $playerData = $this->playerData();
        $loginData = $this->loginData();

        // 获取当前渠道下的玩家ID
        $departmentId = Admin::user()->department_id;
        $playerIds = Player::query()
            ->where('department_id', $departmentId)
            ->where('is_promoter', 0)
            ->pluck('id');

        // 运营统计数据（受时间筛选影响）
        $operationStatisticsQuery = PlayerDeliveryRecord::query()
            ->when(!empty($playerIds), function ($query) use ($playerIds) {
                $query->whereIn('player_id', $playerIds);
            })
            ->when($data_type && $data_type !== 'all', function ($query) use ($data_type) {
                $this->applyDateWhere($query, $data_type, 'created_at');
            })
            ->whereIn('type', [
                PlayerDeliveryRecord::TYPE_PRESENT_IN,
                PlayerDeliveryRecord::TYPE_PRESENT_OUT,
                PlayerDeliveryRecord::TYPE_MACHINE,
            ])
            ->selectRaw("
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_PRESENT_IN . " THEN `amount` ELSE 0 END) AS present_in_amount,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_PRESENT_OUT . " THEN `amount` ELSE 0 END) AS present_out_amount,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_MACHINE . " THEN `amount` ELSE 0 END) AS machine_put_point
            ")
            ->first();

        $operationStatistics = [
            'present_in_amount' => $operationStatisticsQuery->present_in_amount ?? 0,
            'present_out_amount' => $operationStatisticsQuery->present_out_amount ?? 0,
            'machine_put_point' => $operationStatisticsQuery->machine_put_point ?? 0,
        ];

        // 拉彩统计数据（受时间筛选影响）
        $lotteryStatisticsQuery = PlayerLotteryRecord::query()
            ->when(!empty($playerIds), function ($query) use ($playerIds) {
                $query->whereIn('player_id', $playerIds);
            })
            ->when($data_type && $data_type !== 'all', function ($query) use ($data_type) {
                $this->applyDateWhere($query, $data_type, 'created_at');
            })
            ->where('status', PlayerLotteryRecord::STATUS_COMPLETE)
            ->selectRaw("
                COUNT(*) as lottery_count,
                SUM(`amount`) as lottery_amount
            ")
            ->first();

        $lotteryStatistics = [
            'lottery_count' => $lotteryStatisticsQuery->lottery_count ?? 0,
            'lottery_amount' => $lotteryStatisticsQuery->lottery_amount ?? 0,
        ];

        $dropdown = Dropdown::create(
            Button::create(admin_trans('data_center.data_cycle') . ' (' . admin_trans('data_center.data_type.' . ($data_type == null || $data_type == 'all' ? 'all' : $data_type)) . ')')
        )->trigger(['click']);
        $dropdown->item(admin_trans('data_center.data_type.all'))->redirect([$this, 'index'], ['data_type' => 'all']);
        $dropdown->item(admin_trans('data_center.data_type.today'))->redirect([$this, 'index'],
            ['data_type' => 'today']);
        $dropdown->item(admin_trans('data_center.data_type.yesterday'))->redirect([$this, 'index'],
            ['data_type' => 'yesterday']);
        $dropdown->item(admin_trans('data_center.data_type.week'))->redirect([$this, 'index'], ['data_type' => 'week']);
        $dropdown->item(admin_trans('data_center.data_type.last_week'))->redirect([$this, 'index'],
            ['data_type' => 'last_week']);
        $dropdown->item(admin_trans('data_center.data_type.month'))->redirect([$this, 'index'],
            ['data_type' => 'month']);
        $dropdown->item(admin_trans('data_center.data_type.last_month'))->redirect([$this, 'index'],
            ['data_type' => 'last_month']);
        $layout = Layout::create();
        $layout->row(function (Row $row) use ($rechargeData, $withdrawData, $playerData, $loginData, $dropdown, $operationStatistics, $lotteryStatistics) {
            $row->gutter([10, 10]);
            // 计算运营统计的小计（基于时间筛选的数据）
            $subtotal = bcsub(
                $operationStatistics['present_in_amount'] ?? 0,
                $operationStatistics['present_out_amount'] ?? 0,
                2
            );

            // 数据周期筛选
            $row->column(
                Card::create([
                    Row::create()->column($dropdown, 4),
                ])->bodyStyle([
                    'padding' => '13px',
                    'display' => 'flex',
                    'alignItems' => 'center',
                    'height' => '54px'
                ])
            , 4);

            // 总开分
            $row->column(
                Card::create([
                    Row::create()->column([
                        Html::create(admin_trans('data_center.total_recharge'))->style([
                            'fontSize' => '14px',
                            'color' => '#909399',
                            'marginRight' => 'auto'
                        ]),
                        Html::create(number_format(floatval($operationStatistics['present_in_amount'] ?? 0), 2))->style([
                            'fontSize' => '20px',
                            'fontWeight' => '600',
                            'color' => '#67C23A'
                        ])
                    ])->style([
                        'display' => 'flex',
                        'alignItems' => 'center',
                        'justifyContent' => 'space-between',
                        'width' => '100%'
                    ])
                ])->bodyStyle([
                    'padding' => '13px',
                    'display' => 'flex',
                    'alignItems' => 'center',
                    'height' => '54px'
                ])
            , 4);

            // 总洗分
            $row->column(
                Card::create([
                    Row::create()->column([
                        Html::create(admin_trans('data_center.total_withdraw'))->style([
                            'fontSize' => '14px',
                            'color' => '#909399',
                            'marginRight' => 'auto'
                        ]),
                        Html::create(number_format(floatval($operationStatistics['present_out_amount'] ?? 0), 2))->style([
                            'fontSize' => '20px',
                            'fontWeight' => '600',
                            'color' => '#F56C6C'
                        ])
                    ])->style([
                        'display' => 'flex',
                        'alignItems' => 'center',
                        'justifyContent' => 'space-between',
                        'width' => '100%'
                    ])
                ])->bodyStyle([
                    'padding' => '13px',
                    'display' => 'flex',
                    'alignItems' => 'center',
                    'height' => '54px'
                ])
            , 4);

            // 总投钞
            $row->column(
                Card::create([
                    Row::create()->column([
                        Html::create(admin_trans('data_center.total_machine_put'))->style([
                            'fontSize' => '14px',
                            'color' => '#909399',
                            'marginRight' => 'auto'
                        ]),
                        Html::create(number_format(floatval($operationStatistics['machine_put_point'] ?? 0), 2))->style([
                            'fontSize' => '20px',
                            'fontWeight' => '600',
                            'color' => '#409EFF'
                        ])
                    ])->style([
                        'display' => 'flex',
                        'alignItems' => 'center',
                        'justifyContent' => 'space-between',
                        'width' => '100%'
                    ])
                ])->bodyStyle([
                    'padding' => '13px',
                    'display' => 'flex',
                    'alignItems' => 'center',
                    'height' => '54px'
                ])
            , 4);

            // 总拉彩
            $row->column(
                Card::create([
                    Row::create()->column([
                        Html::create('总拉彩')->style([
                            'fontSize' => '14px',
                            'color' => '#909399',
                            'marginRight' => 'auto'
                        ]),
                        Html::create(number_format(floatval($lotteryStatistics['lottery_amount'] ?? 0), 2))->style([
                            'fontSize' => '20px',
                            'fontWeight' => '600',
                            'color' => '#E6A23C'
                        ])
                    ])->style([
                        'display' => 'flex',
                        'alignItems' => 'center',
                        'justifyContent' => 'space-between',
                        'width' => '100%'
                    ])
                ])->bodyStyle([
                    'padding' => '13px',
                    'display' => 'flex',
                    'alignItems' => 'center',
                    'height' => '54px'
                ])
            , 4);

            // 盈余小计
            $row->column(
                Card::create([
                    Row::create()->column([
                        Html::create('盈余小计')->style([
                            'fontSize' => '14px',
                            'color' => '#909399',
                            'marginRight' => 'auto'
                        ]),
                        Html::create(number_format(floatval($subtotal), 2))->style([
                            'fontSize' => '20px',
                            'fontWeight' => '600',
                            'color' => floatval($subtotal) >= 0 ? '#67C23A' : '#F56C6C'
                        ])
                    ])->style([
                        'display' => 'flex',
                        'alignItems' => 'center',
                        'justifyContent' => 'space-between',
                        'width' => '100%'
                    ])
                ])->bodyStyle([
                    'padding' => '13px',
                    'display' => 'flex',
                    'alignItems' => 'center',
                    'height' => '54px'
                ])
            , 4);

            $row->column(
                Card::create([
                    Row::create()->column(Icon::create('fas fa-globe')->style([
                        'fontSize' => '45px',
                        'color' => 'rgb(0,154,97)',
                        'marginRight' => '20px'
                    ]), 4),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.department_id'))->value(Admin::user()->department->id ?? '')
                        ->valueStyle([
                            'font-size' => '20px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 6),
                    Divider::create()->type('vertical')->style(['height' => '4.9em']),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.department_name'))->value(Admin::user()->department->name ?? '')
                        ->valueStyle([
                            'font-size' => '20px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 8),
                ])->bodyStyle([
                    'display' => 'flex',
                    'align-items' => 'center'
                ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                , 12);
            $row->column(
                Card::create([
                    Row::create()->column(Icon::create('fas fa-user')->style([
                        'fontSize' => '45px',
                        'color' => '#409eff',
                        'marginRight' => '20px'
                    ]), 6),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.today_add_player'))->value($playerData['today'])
                        ->valueStyle([
                            'font-size' => '20px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 8),
                    Divider::create()->type('vertical')->style(['height' => '4.9em']),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.player_all'))->value($playerData['all'])
                        ->valueStyle([
                            'font-size' => '20px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 8),
                ])->bodyStyle([
                    'display' => 'flex',
                    'align-items' => 'center'
                ])->hoverable()->extra(Icon::create('MoreOutlined')->redirect('ex-admin/addons-webman-controller-ChannelPlayerController/index'))->headStyle([
                    'height' => '0px',
                    'border-bottom' => '0px',
                    'min-height' => '0px'
                ])
                , 6);
            $row->column(
                Card::create([
                    Row::create()->column(Icon::create('fas fa-user')->style([
                        'fontSize' => '45px',
                        'color' => '#e91e63',
                        'marginRight' => '20px'
                    ]), 6),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.today_active_player'))->value($loginData['today'])
                        ->valueStyle([
                            'font-size' => '20px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 8),
                    Divider::create()->type('vertical')->style(['height' => '4.9em']),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.mouth_active_player'))->value($loginData['month'])
                        ->valueStyle([
                            'font-size' => '20px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 8)
                ])->bodyStyle(['display' => 'flex', 'align-items' => 'center'])->hoverable()
                , 6);
            $row->column(
                Card::create([
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.recharge_all'))->value(floatval($rechargeData['all']))
                        ->valueStyle([
                            'font-size' => '20px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 4),
                    Divider::create()->type('vertical')->style(['height' => '4.9em']),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.recharge_self'))->value(floatval($rechargeData['self']))
                        ->valueStyle([
                            'font-size' => '20px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 4),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.recharge_third'))->value(floatval($rechargeData['third']))
                        ->valueStyle([
                            'font-size' => '20px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 4),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.recharge_artificial'))->value(floatval($rechargeData['artificial']))
                        ->valueStyle([
                            'font-size' => '20px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 4),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.recharge_business'))->value(floatval($rechargeData['business']))
                        ->valueStyle([
                            'font-size' => '20px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 4),
                    Row::create()->column(Statistic::create()->title(admin_trans('player_extend.fields.machine_put_amount'))->value(floatval($rechargeData['machine_put_amount']))
                        ->valueStyle([
                            'font-size' => '20px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 3),
                ])->bodyStyle([
                    'display' => 'flex',
                    'align-items' => 'center'
                ])->hoverable()->extra(Icon::create('MoreOutlined')->redirect('ex-admin/addons-webman-controller-ChannelRechargeRecordController/index'))->headStyle([
                    'height' => '0px',
                    'border-bottom' => '0px',
                    'min-height' => '0px'
                ])
                , 12);

            $row->column(
                Card::create([
                    Row::create()->column(Icon::create('fas fa-money-bill-alt')->style([
                        'fontSize' => '45px',
                        'color' => '#ff9800',
                        'marginRight' => '20px'
                    ]), 3),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.withdraw_all'))->value(floatval($withdrawData['all']))
                        ->valueStyle([
                            'font-size' => '20px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 4),
                    Divider::create()->type('vertical')->style(['height' => '4.9em']),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.withdraw_self'))->value(floatval($withdrawData['self']))
                        ->valueStyle([
                            'font-size' => '20px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 4),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.withdraw_third'))->value(floatval($withdrawData['third']))
                        ->valueStyle([
                            'font-size' => '20px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 4),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.withdraw_artificial'))->value(floatval($withdrawData['artificial']))
                        ->valueStyle([
                            'font-size' => '20px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 4),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.withdraw_business'))->value(floatval($withdrawData['business']))
                        ->valueStyle([
                            'font-size' => '20px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 4),
                ])->bodyStyle([
                    'display' => 'flex',
                    'align-items' => 'center'
                ])->hoverable()->extra(Icon::create('MoreOutlined')->redirect('ex-admin/addons-webman-controller-ChannelWithdrawRecordController/index'))->headStyle([
                    'height' => '0px',
                    'border-bottom' => '0px',
                    'min-height' => '0px'
                ])
                , 12);

            $row->column(Card::create($this->rechargeChart())->hoverable(), 12);
            $row->column(Card::create($this->withdrawChart())->hoverable(), 12);
            $row->column(Card::create($this->playerChart())->hoverable(), 12);
            $row->column(Card::create($this->machineChart())->hoverable(), 12);
        });

        return $layout;
    }

    /**
     * 获取充值数据
     * @param $data_type
     * @return array
     */
    public function rechargeData($data_type): array
    {
        return [
            'all' => PlayerRechargeRecord::where('status',
                PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)->whereIn('type', [
                PlayerRechargeRecord::TYPE_THIRD,
                PlayerRechargeRecord::TYPE_SELF,
                PlayerRechargeRecord::TYPE_ARTIFICIAL,
                PlayerRechargeRecord::TYPE_GB,
                PlayerRechargeRecord::TYPE_MACHINE,
            ])->when($data_type, function (Builder $q, $value) {
                switch ($value) {
                    case 'today': // 今天
                        $q->where('finish_time', '>=', Carbon::today()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->endOfDay());
                        break;
                    case 'yesterday': // 昨天
                        $q->where('finish_time', '>=', Carbon::yesterday()->startOfDay())->where('finish_time', '<=',
                            Carbon::yesterday()->endOfDay());
                        break;
                    case 'week': // 本周
                        $q->where('finish_time', '>=',
                            Carbon::today()->startOfWeek()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->endOfWeek()->endOfDay());
                        break;
                    case 'last_week': // 上周
                        $q->where('finish_time', '>=',
                            Carbon::today()->subWeek()->startOfWeek()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->subWeek()->endOfWeek()->endOfDay());
                        break;
                    case 'month': // 本月
                        $q->where('finish_time', '>=',
                            Carbon::today()->firstOfMonth()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->endOfMonth()->endOfDay());
                        break;
                    case 'last_month': // 上月
                        $startDate = Carbon::create(
                            Carbon::now()->subMonthNoOverflow()->year,
                            Carbon::now()->subMonthNoOverflow()->month,
                            1, // 第一天
                            0, 0, 0
                        );

                        $endDate = Carbon::create(
                            Carbon::now()->subMonthNoOverflow()->year,
                            Carbon::now()->subMonthNoOverflow()->month,
                            Carbon::now()->subMonthNoOverflow()->daysInMonth,
                            23, 59, 59
                        );

                        $q->whereBetween('finish_time', [$startDate, $endDate]);
                        break;
                    default:
                        break;
                }
            })->sum('point'),
            'self' => PlayerRechargeRecord::where('status',
                PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)->where('type',
                PlayerRechargeRecord::TYPE_SELF)->when($data_type, function (Builder $q, $value) {
                switch ($value) {
                    case 'today': // 今天
                        $q->where('finish_time', '>=', Carbon::today()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->endOfDay());
                        break;
                    case 'yesterday': // 昨天
                        $q->where('finish_time', '>=', Carbon::yesterday()->startOfDay())->where('finish_time', '<=',
                            Carbon::yesterday()->endOfDay());
                        break;
                    case 'week': // 本周
                        $q->where('finish_time', '>=',
                            Carbon::today()->startOfWeek()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->endOfWeek()->endOfDay());
                        break;
                    case 'last_week': // 上周
                        $q->where('finish_time', '>=',
                            Carbon::today()->subWeek()->startOfWeek()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->subWeek()->endOfWeek()->endOfDay());
                        break;
                    case 'month': // 本月
                        $q->where('finish_time', '>=',
                            Carbon::today()->firstOfMonth()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->endOfMonth()->endOfDay());
                        break;
                    case 'last_month': // 上月
                        $startDate = Carbon::create(
                            Carbon::now()->subMonthNoOverflow()->year,
                            Carbon::now()->subMonthNoOverflow()->month,
                            1, // 第一天
                            0, 0, 0
                        );

                        $endDate = Carbon::create(
                            Carbon::now()->subMonthNoOverflow()->year,
                            Carbon::now()->subMonthNoOverflow()->month,
                            Carbon::now()->subMonthNoOverflow()->daysInMonth,
                            23, 59, 59
                        );

                        $q->whereBetween('finish_time', [$startDate, $endDate]);
                        break;
                    default:
                        break;
                }
            })->sum('point'),
            'third' => PlayerRechargeRecord::where('status',
                PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)->whereIn('type',
                [
                    PlayerRechargeRecord::TYPE_THIRD,
                    PlayerRechargeRecord::TYPE_GB,
                ])->when($data_type, function (Builder $q, $value) {
                switch ($value) {
                    case 'today': // 今天
                        $q->where('finish_time', '>=', Carbon::today()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->endOfDay());
                        break;
                    case 'yesterday': // 昨天
                        $q->where('finish_time', '>=', Carbon::yesterday()->startOfDay())->where('finish_time', '<=',
                            Carbon::yesterday()->endOfDay());
                        break;
                    case 'week': // 本周
                        $q->where('finish_time', '>=',
                            Carbon::today()->startOfWeek()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->endOfWeek()->endOfDay());
                        break;
                    case 'last_week': // 上周
                        $q->where('finish_time', '>=',
                            Carbon::today()->subWeek()->startOfWeek()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->subWeek()->endOfWeek()->endOfDay());
                        break;
                    case 'month': // 本月
                        $q->where('finish_time', '>=',
                            Carbon::today()->firstOfMonth()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->endOfMonth()->endOfDay());
                        break;
                    case 'last_month': // 上月
                        $startDate = Carbon::create(
                            Carbon::now()->subMonthNoOverflow()->year,
                            Carbon::now()->subMonthNoOverflow()->month,
                            1, // 第一天
                            0, 0, 0
                        );

                        $endDate = Carbon::create(
                            Carbon::now()->subMonthNoOverflow()->year,
                            Carbon::now()->subMonthNoOverflow()->month,
                            Carbon::now()->subMonthNoOverflow()->daysInMonth,
                            23, 59, 59
                        );

                        $q->whereBetween('finish_time', [$startDate, $endDate]);
                        break;
                    default:
                        break;
                }
            })->sum('point'),
            'artificial' => PlayerRechargeRecord::where('status',
                PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)->where('type',
                PlayerRechargeRecord::TYPE_ARTIFICIAL)->when($data_type, function (Builder $q, $value) {
                switch ($value) {
                    case 'today': // 今天
                        $q->where('finish_time', '>=', Carbon::today()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->endOfDay());
                        break;
                    case 'yesterday': // 昨天
                        $q->where('finish_time', '>=', Carbon::yesterday()->startOfDay())->where('finish_time', '<=',
                            Carbon::yesterday()->endOfDay());
                        break;
                    case 'week': // 本周
                        $q->where('finish_time', '>=',
                            Carbon::today()->startOfWeek()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->endOfWeek()->endOfDay());
                        break;
                    case 'last_week': // 上周
                        $q->where('finish_time', '>=',
                            Carbon::today()->subWeek()->startOfWeek()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->subWeek()->endOfWeek()->endOfDay());
                        break;
                    case 'month': // 本月
                        $q->where('finish_time', '>=',
                            Carbon::today()->firstOfMonth()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->endOfMonth()->endOfDay());
                        break;
                    case 'last_month': // 上月
                        $startDate = Carbon::create(
                            Carbon::now()->subMonthNoOverflow()->year,
                            Carbon::now()->subMonthNoOverflow()->month,
                            1, // 第一天
                            0, 0, 0
                        );

                        $endDate = Carbon::create(
                            Carbon::now()->subMonthNoOverflow()->year,
                            Carbon::now()->subMonthNoOverflow()->month,
                            Carbon::now()->subMonthNoOverflow()->daysInMonth,
                            23, 59, 59
                        );

                        $q->whereBetween('finish_time', [$startDate, $endDate]);
                        break;
                    default:
                        break;
                }
            })->sum('point'),
            'business' => PlayerPresentRecord::where('type', PlayerPresentRecord::TYPE_OUT)->when($data_type,
                function (Builder $q, $value) {
                    switch ($value) {
                        case 'today': // 今天
                            $q->where('created_at', '>=', Carbon::today()->startOfDay())->where('created_at', '<=',
                                Carbon::today()->endOfDay());
                            break;
                        case 'yesterday': // 昨天
                            $q->where('created_at', '>=', Carbon::yesterday()->startOfDay())->where('created_at', '<=',
                                Carbon::yesterday()->endOfDay());
                            break;
                        case 'week': // 本周
                            $q->where('created_at', '>=',
                                Carbon::today()->startOfWeek()->startOfDay())->where('created_at', '<=',
                                Carbon::today()->endOfWeek()->endOfDay());
                            break;
                        case 'last_week': // 上周
                            $q->where('created_at', '>=',
                                Carbon::today()->subWeek()->startOfWeek()->startOfDay())->where('created_at', '<=',
                                Carbon::today()->subWeek()->endOfWeek()->endOfDay());
                            break;
                        case 'month': // 本月
                            $q->where('created_at', '>=',
                                Carbon::today()->firstOfMonth()->startOfDay())->where('created_at', '<=',
                                Carbon::today()->endOfMonth()->endOfDay());
                            break;
                        case 'last_month': // 上月
                            $startDate = Carbon::create(
                                Carbon::now()->subMonthNoOverflow()->year,
                                Carbon::now()->subMonthNoOverflow()->month,
                                1, // 第一天
                                0, 0, 0
                            );

                            $endDate = Carbon::create(
                                Carbon::now()->subMonthNoOverflow()->year,
                                Carbon::now()->subMonthNoOverflow()->month,
                                Carbon::now()->subMonthNoOverflow()->daysInMonth,
                                23, 59, 59
                            );

                            $q->whereBetween('created_at', [$startDate, $endDate]);
                            break;
                        default:
                            break;
                    }
                })->sum('amount'),
            'machine_put_amount' => PlayerDeliveryRecord::where('type',
                PlayerDeliveryRecord::TYPE_MACHINE)->when($data_type,
                function (Builder $q, $value) {
                    switch ($value) {
                        case 'today': // 今天
                            $q->where('created_at', '>=', Carbon::today()->startOfDay())->where('created_at', '<=',
                                Carbon::today()->endOfDay());
                            break;
                        case 'yesterday': // 昨天
                            $q->where('created_at', '>=', Carbon::yesterday()->startOfDay())->where('created_at', '<=',
                                Carbon::yesterday()->endOfDay());
                            break;
                        case 'week': // 本周
                            $q->where('created_at', '>=',
                                Carbon::today()->startOfWeek()->startOfDay())->where('created_at', '<=',
                                Carbon::today()->endOfWeek()->endOfDay());
                            break;
                        case 'last_week': // 上周
                            $q->where('created_at', '>=',
                                Carbon::today()->subWeek()->startOfWeek()->startOfDay())->where('created_at', '<=',
                                Carbon::today()->subWeek()->endOfWeek()->endOfDay());
                            break;
                        case 'month': // 本月
                            $q->where('created_at', '>=',
                                Carbon::today()->firstOfMonth()->startOfDay())->where('created_at', '<=',
                                Carbon::today()->endOfMonth()->endOfDay());
                            break;
                        case 'last_month': // 上月
                            $startDate = Carbon::create(
                                Carbon::now()->subMonthNoOverflow()->year,
                                Carbon::now()->subMonthNoOverflow()->month,
                                1, // 第一天
                                0, 0, 0
                            );

                            $endDate = Carbon::create(
                                Carbon::now()->subMonthNoOverflow()->year,
                                Carbon::now()->subMonthNoOverflow()->month,
                                Carbon::now()->subMonthNoOverflow()->daysInMonth,
                                23, 59, 59
                            );

                            $q->whereBetween('created_at', [$startDate, $endDate]);
                            break;
                        default:
                            break;
                    }
                })->sum('amount'),
        ];
    }

    /**
     * 获取提现数据
     * @param $data_type
     * @return array
     */
    public function withdrawData($data_type): array
    {
        return [
            'all' => PlayerWithdrawRecord::where('status', PlayerWithdrawRecord::STATUS_SUCCESS)->when($data_type,
                function (Builder $q, $value) {
                    switch ($value) {
                        case 'today': // 今天
                            $q->where('finish_time', '>=', Carbon::today()->startOfDay())->where('finish_time', '<=',
                                Carbon::today()->endOfDay());
                            break;
                        case 'yesterday': // 昨天
                            $q->where('finish_time', '>=', Carbon::yesterday()->startOfDay())->where('finish_time',
                                '<=',
                                Carbon::yesterday()->endOfDay());
                            break;
                        case 'week': // 本周
                            $q->where('finish_time', '>=',
                                Carbon::today()->startOfWeek()->startOfDay())->where('finish_time', '<=',
                                Carbon::today()->endOfWeek()->endOfDay());
                            break;
                        case 'last_week': // 上周
                            $q->where('finish_time', '>=',
                                Carbon::today()->subWeek()->startOfWeek()->startOfDay())->where('finish_time', '<=',
                                Carbon::today()->subWeek()->endOfWeek()->endOfDay());
                            break;
                        case 'month': // 本月
                            $q->where('finish_time', '>=',
                                Carbon::today()->firstOfMonth()->startOfDay())->where('finish_time', '<=',
                                Carbon::today()->endOfMonth()->endOfDay());
                            break;
                        case 'last_month': // 上月
                            $startDate = Carbon::create(
                                Carbon::now()->subMonthNoOverflow()->year,
                                Carbon::now()->subMonthNoOverflow()->month,
                                1, // 第一天
                                0, 0, 0
                            );

                            $endDate = Carbon::create(
                                Carbon::now()->subMonthNoOverflow()->year,
                                Carbon::now()->subMonthNoOverflow()->month,
                                Carbon::now()->subMonthNoOverflow()->daysInMonth,
                                23, 59, 59
                            );

                            $q->whereBetween('finish_time', [$startDate, $endDate]);
                            break;
                        default:
                            break;
                    }
                })->sum('money'),
            'self' => PlayerWithdrawRecord::where('status', PlayerWithdrawRecord::STATUS_SUCCESS)->where('type',
                PlayerWithdrawRecord::TYPE_SELF)->when($data_type, function (Builder $q, $value) {
                switch ($value) {
                    case 'today': // 今天
                        $q->where('finish_time', '>=', Carbon::today()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->endOfDay());
                        break;
                    case 'yesterday': // 昨天
                        $q->where('finish_time', '>=', Carbon::yesterday()->startOfDay())->where('finish_time', '<=',
                            Carbon::yesterday()->endOfDay());
                        break;
                    case 'week': // 本周
                        $q->where('finish_time', '>=',
                            Carbon::today()->startOfWeek()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->endOfWeek()->endOfDay());
                        break;
                    case 'last_week': // 上周
                        $q->where('finish_time', '>=',
                            Carbon::today()->subWeek()->startOfWeek()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->subWeek()->endOfWeek()->endOfDay());
                        break;
                    case 'month': // 本月
                        $q->where('finish_time', '>=',
                            Carbon::today()->firstOfMonth()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->endOfMonth()->endOfDay());
                        break;
                    case 'last_month': // 上月
                        $startDate = Carbon::create(
                            Carbon::now()->subMonthNoOverflow()->year,
                            Carbon::now()->subMonthNoOverflow()->month,
                            1, // 第一天
                            0, 0, 0
                        );

                        $endDate = Carbon::create(
                            Carbon::now()->subMonthNoOverflow()->year,
                            Carbon::now()->subMonthNoOverflow()->month,
                            Carbon::now()->subMonthNoOverflow()->daysInMonth,
                            23, 59, 59
                        );

                        $q->whereBetween('finish_time', [$startDate, $endDate]);
                        break;
                    default:
                        break;
                }
            })->sum('money'),
            'third' => PlayerWithdrawRecord::where('status', PlayerWithdrawRecord::STATUS_SUCCESS)->whereIn('type',
                [
                    PlayerWithdrawRecord::TYPE_THIRD,
                    PlayerWithdrawRecord::TYPE_GB,
                ])->when($data_type, function (Builder $q, $value) {
                switch ($value) {
                    case 'today': // 今天
                        $q->where('finish_time', '>=', Carbon::today()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->endOfDay());
                        break;
                    case 'yesterday': // 昨天
                        $q->where('finish_time', '>=', Carbon::yesterday()->startOfDay())->where('finish_time', '<=',
                            Carbon::yesterday()->endOfDay());
                        break;
                    case 'week': // 本周
                        $q->where('finish_time', '>=',
                            Carbon::today()->startOfWeek()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->endOfWeek()->endOfDay());
                        break;
                    case 'last_week': // 上周
                        $q->where('finish_time', '>=',
                            Carbon::today()->subWeek()->startOfWeek()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->subWeek()->endOfWeek()->endOfDay());
                        break;
                    case 'month': // 本月
                        $q->where('finish_time', '>=',
                            Carbon::today()->firstOfMonth()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->endOfMonth()->endOfDay());
                        break;
                    case 'last_month': // 上月
                        $startDate = Carbon::create(
                            Carbon::now()->subMonthNoOverflow()->year,
                            Carbon::now()->subMonthNoOverflow()->month,
                            1, // 第一天
                            0, 0, 0
                        );

                        $endDate = Carbon::create(
                            Carbon::now()->subMonthNoOverflow()->year,
                            Carbon::now()->subMonthNoOverflow()->month,
                            Carbon::now()->subMonthNoOverflow()->daysInMonth,
                            23, 59, 59
                        );

                        $q->whereBetween('finish_time', [$startDate, $endDate]);
                        break;
                    default:
                        break;
                }
            })->sum('money'),
            'artificial' => PlayerWithdrawRecord::where('status', PlayerWithdrawRecord::STATUS_SUCCESS)->where('type',
                PlayerWithdrawRecord::TYPE_ARTIFICIAL)->when($data_type, function (Builder $q, $value) {
                switch ($value) {
                    case 'today': // 今天
                        $q->where('finish_time', '>=', Carbon::today()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->endOfDay());
                        break;
                    case 'yesterday': // 昨天
                        $q->where('finish_time', '>=', Carbon::yesterday()->startOfDay())->where('finish_time', '<=',
                            Carbon::yesterday()->endOfDay());
                        break;
                    case 'week': // 本周
                        $q->where('finish_time', '>=',
                            Carbon::today()->startOfWeek()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->endOfWeek()->endOfDay());
                        break;
                    case 'last_week': // 上周
                        $q->where('finish_time', '>=',
                            Carbon::today()->subWeek()->startOfWeek()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->subWeek()->endOfWeek()->endOfDay());
                        break;
                    case 'month': // 本月
                        $q->where('finish_time', '>=',
                            Carbon::today()->firstOfMonth()->startOfDay())->where('finish_time', '<=',
                            Carbon::today()->endOfMonth()->endOfDay());
                        break;
                    case 'last_month': // 上月
                        $startDate = Carbon::create(
                            Carbon::now()->subMonthNoOverflow()->year,
                            Carbon::now()->subMonthNoOverflow()->month,
                            1, // 第一天
                            0, 0, 0
                        );

                        $endDate = Carbon::create(
                            Carbon::now()->subMonthNoOverflow()->year,
                            Carbon::now()->subMonthNoOverflow()->month,
                            Carbon::now()->subMonthNoOverflow()->daysInMonth,
                            23, 59, 59
                        );

                        $q->whereBetween('finish_time', [$startDate, $endDate]);
                        break;
                    default:
                        break;
                }
            })->sum('point'),
            'business' => PlayerPresentRecord::where('type', PlayerPresentRecord::TYPE_IN)->when($data_type,
                function (Builder $q, $value) {
                    switch ($value) {
                        case 'today': // 今天
                            $q->where('created_at', '>=', Carbon::today()->startOfDay())->where('created_at', '<=',
                                Carbon::today()->endOfDay());
                            break;
                        case 'yesterday': // 昨天
                            $q->where('created_at', '>=', Carbon::yesterday()->startOfDay())->where('created_at', '<=',
                                Carbon::yesterday()->endOfDay());
                            break;
                        case 'week': // 本周
                            $q->where('created_at', '>=',
                                Carbon::today()->startOfWeek()->startOfDay())->where('created_at', '<=',
                                Carbon::today()->endOfWeek()->endOfDay());
                            break;
                        case 'last_week': // 上周
                            $q->where('created_at', '>=',
                                Carbon::today()->subWeek()->startOfWeek()->startOfDay())->where('created_at', '<=',
                                Carbon::today()->subWeek()->endOfWeek()->endOfDay());
                            break;
                        case 'month': // 本月
                            $q->where('created_at', '>=',
                                Carbon::today()->firstOfMonth()->startOfDay())->where('created_at', '<=',
                                Carbon::today()->endOfMonth()->endOfDay());
                            break;
                        case 'last_month': // 上月
                            $startDate = Carbon::create(
                                Carbon::now()->subMonthNoOverflow()->year,
                                Carbon::now()->subMonthNoOverflow()->month,
                                1, // 第一天
                                0, 0, 0
                            );

                            $endDate = Carbon::create(
                                Carbon::now()->subMonthNoOverflow()->year,
                                Carbon::now()->subMonthNoOverflow()->month,
                                Carbon::now()->subMonthNoOverflow()->daysInMonth,
                                23, 59, 59
                            );

                            $q->whereBetween('created_at', [$startDate, $endDate]);
                            break;
                        default:
                            break;
                    }
                })->sum('amount'),
        ];
    }

    /**
     * 获取玩家数据
     * @return array
     */
    public function playerData(): array
    {
        return [
            'all' => Player::count('*'),
            'today' => Player::whereDate('created_at', date('Y-m-d'))->count(),
        ];
    }

    /**
     * 获取活跃玩家数据
     * @return array
     */
    public function loginData(): array
    {
        return [
            'month' => PlayerLoginRecord::whereYear('created_at', date('Y'))->whereMonth('created_at',
                date('m'))->distinct('player_id')->count(),
            'today' => PlayerLoginRecord::whereDate('created_at', date('Y-m-d'))->distinct('player_id')->count(),
        ];
    }

    /**
     * 充值趋势图
     * @return LineChart
     */
    public function rechargeChart(): LineChart
    {
        $range = Carbon::now()->subDays(15)->format('Y-m-d');
        $data = PlayerRechargeRecord::whereDate('created_at', '>=', $range)
            ->where('status', PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)
            ->whereIn('type', [
                PlayerRechargeRecord::TYPE_THIRD,
                PlayerRechargeRecord::TYPE_SELF,
                PlayerRechargeRecord::TYPE_ARTIFICIAL,
                PlayerRechargeRecord::TYPE_GB,
            ])
            ->groupBy('date')
            ->orderBy('date', 'DESC')
            ->get([
                DB::raw('Date(`created_at`) as date'),
                DB::raw('SUM(`point`) as value')
            ])
            ->toArray();
        $data = $data ? array_column($data, 'value', 'date') : [];
        $xAxis = [];
        $yAxis = [];
        for ($i = 14; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $xAxis[] = $date;
            $yAxis[] = $data[$date] ?? 0;
        }

        return LineChart::create()
            ->height('280px')
            ->hideDateFilter()
            ->header(Html::create(admin_trans('data_center.recharge_chart'))->tag('h2')->style(['text-align' => 'center']))
            ->xAxis($xAxis)
            ->data(admin_trans('data_center.recharge_amount'), $yAxis);
    }

    /**
     * 提现趋势图
     * @return LineChart
     */
    public function withdrawChart(): LineChart
    {
        $range = Carbon::now()->subDays(15)->format('Y-m-d');
        $data = PlayerWithdrawRecord::whereDate('created_at', '>=', $range)
            ->where('status', PlayerWithdrawRecord::STATUS_SUCCESS)
            ->groupBy('date')
            ->orderBy('date', 'DESC')
            ->get([
                DB::raw('Date(`created_at`) as date'),
                DB::raw('SUM(`point`) as value')
            ])
            ->toArray();
        $data = $data ? array_column($data, 'value', 'date') : [];
        $xAxis = [];
        $yAxis = [];

        for ($i = 14; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $xAxis[] = $date;
            $yAxis[] = $data[$date] ?? 0;
        }

        return LineChart::create()
            ->height('280px')
            ->hideDateFilter()
            ->header(Html::create(admin_trans('data_center.withdraw_chart'))->tag('h2')->style(['text-align' => 'center']))
            ->xAxis($xAxis)
            ->data(admin_trans('data_center.withdraw_amount'), $yAxis);
    }

    /**
     * 新增玩家
     * @return BarChart
     */
    public function playerChart(): BarChart
    {
        $range = Carbon::now()->subDays(15)->format('Y-m-d');
        $data = Player::whereDate('created_at', '>=', $range)
            ->groupBy('date')
            ->orderBy('date', 'DESC')
            ->get([
                DB::raw('Date(`created_at`) as date'),
                DB::raw('COUNT(`id`) as value')
            ])
            ->toArray();
        $data = $data ? array_column($data, 'value', 'date') : [];
        $xAxis = [];
        $yAxis = [];
        for ($i = 14; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $xAxis[] = $date;
            $yAxis[] = $data[$date] ?? 0;
        }

        return BarChart::create()
            ->height('280px')
            ->hideDateFilter()
            ->header(Html::create(admin_trans('data_center.player_chart'))->tag('h2')->style(['text-align' => 'center']))
            ->xAxis($xAxis)
            ->data(admin_trans('data_center.player_amount'), $yAxis);
    }

    /**
     * 机台操作
     * @return LineChart
     */
    public function machineChart(): LineChart
    {
        $startDate = Carbon::now()->subHours(24);
        $logs = MachineOperationLog::raw(function ($collection) use ($startDate) {
            return $collection->aggregate([
                [
                    '$match' => [
                        'created_at' => [
                            '$gte' => new UTCDateTime($startDate->timestamp * 1000),
                        ],
                        'status' => 1,
                        'department_id' => Admin::user()->department->id,
                    ]
                ],
                [
                    '$group' => [
                        '_id' => [
                            'date' => [
                                '$dateToString' => [
                                    'format' => '%Y-%m-%d %H',
                                    'date' => '$created_at',
                                    'timezone' => 'Asia/Shanghai'
                                ]
                            ],
                            'hour' => ['$hour' => '$created_at']
                        ],
                        'count' => ['$sum' => 1]
                    ]
                ],
                [
                    '$sort' => [
                        '_id.date' => 1
                    ]
                ]
            ]);
        })->toArray();
        $data = [];
        foreach ($logs as $log) {
            $data[$log['_id']->date] = $log['count'];
        }
        $xAxis = [];
        $yAxis = [];
        for ($i = 23; $i >= 0; $i--) {
            $time = Carbon::now()->subHours($i)->format('Y-m-d H');
            $xAxis[] = $time;
            $yAxis[] = $data[$time] ?? 0;
        }
        return LineChart::create()
            ->height('280px')
            ->hideDateFilter()
            ->header(Html::create(admin_trans('data_center.machine_24_chart'))->tag('h2')->style(['text-align' => 'center']))
            ->xAxis($xAxis)
            ->data(admin_trans('data_center.machine_amount'), $yAxis);
    }

    /**
     * 代理数据中心
     * @group channel
     * @auth true
     */
    public function agentIndex($data_type = null): Layout
    {
        /** @var \addons\webman\model\AdminUser $agent */
        $agent = Admin::user();

        // 1. 查询下级店家数量
        $storeNum = $agent->childStores()->where('type', \addons\webman\model\AdminUser::TYPE_STORE)->count();

        // 2. 获取下级店家ID列表
        $storeIds = $agent->childStores()->where('type', \addons\webman\model\AdminUser::TYPE_STORE)->pluck('id');

        // 3. 查询下级玩家数量（通过店家关联）
        $playerNum = Player::query()
            ->where('department_id', $agent->department_id)
            ->whereIn('store_admin_id', $storeIds)
            ->where('is_promoter', 0)
            ->count();

        // 4. 构建玩家筛选闭包（通过店家关联）
        $playerFilter = function ($query) use ($storeIds) {
            $query->whereHas('player', function ($q) use ($storeIds) {
                $q->whereIn('store_admin_id', $storeIds)
                    ->where('is_promoter', 0);
            });
        };

        // 5. 构建时间筛选闭包
        $timeFilter = function ($query) use ($data_type) {
            if ($data_type) {
                switch ($data_type) {
                    case 'today':
                        $query->where('created_at', '>=', Carbon::today()->startOfDay())
                            ->where('created_at', '<=', Carbon::today()->endOfDay());
                        break;
                    case 'yesterday':
                        $query->where('created_at', '>=', Carbon::yesterday()->startOfDay())
                            ->where('created_at', '<=', Carbon::yesterday()->endOfDay());
                        break;
                    case 'week':
                        $query->where('created_at', '>=', Carbon::today()->startOfWeek()->startOfDay())
                            ->where('created_at', '<=', Carbon::today()->endOfWeek()->endOfDay());
                        break;
                    case 'last_week':
                        $query->where('created_at', '>=', Carbon::today()->subWeek()->startOfWeek()->startOfDay())
                            ->where('created_at', '<=', Carbon::today()->subWeek()->endOfWeek()->endOfDay());
                        break;
                    case 'month':
                        $query->where('created_at', '>=', Carbon::today()->firstOfMonth()->startOfDay())
                            ->where('created_at', '<=', Carbon::today()->endOfMonth()->endOfDay());
                        break;
                    case 'last_month':
                        $startDate = Carbon::create(
                            Carbon::now()->subMonthNoOverflow()->year,
                            Carbon::now()->subMonthNoOverflow()->month,
                            1, 0, 0, 0
                        );
                        $endDate = Carbon::create(
                            Carbon::now()->subMonthNoOverflow()->year,
                            Carbon::now()->subMonthNoOverflow()->month,
                            Carbon::now()->subMonthNoOverflow()->daysInMonth,
                            23, 59, 59
                        );
                        $query->where('created_at', '>=', $startDate)
                            ->where('created_at', '<=', $endDate);
                        break;
                }
            }
        };

        // 6. 查询全部历史数据（充值、提现、投钞）
        // 查询充值数据（不包括投钞）
        $rechargeAmount = PlayerRechargeRecord::query()
            ->when(true, $playerFilter)
            ->where('status', PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)
            ->whereIn('type', [
                PlayerRechargeRecord::TYPE_THIRD,
                PlayerRechargeRecord::TYPE_SELF,
                PlayerRechargeRecord::TYPE_BUSINESS,
                PlayerRechargeRecord::TYPE_ARTIFICIAL,
                PlayerRechargeRecord::TYPE_GB,
                PlayerRechargeRecord::TYPE_EH,
            ])
            ->when($data_type, $timeFilter)
            ->sum('point') ?? 0;

        // 查询投钞数据
        $machinePutPoint = PlayerRechargeRecord::query()
            ->when(true, $playerFilter)
            ->where('status', PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)
            ->where('type', PlayerRechargeRecord::TYPE_MACHINE)
            ->when($data_type, $timeFilter)
            ->sum('point') ?? 0;

        // 查询提现数据
        $withdrawAmount = PlayerWithdrawRecord::query()
            ->when(true, $playerFilter)
            ->where('status', PlayerWithdrawRecord::STATUS_SUCCESS)
            ->when($data_type, $timeFilter)
            ->sum('point') ?? 0;

        // 查询电子游戏总押注
        $electronicBetTotal = PlayGameRecord::query()
            ->when(true, $playerFilter)
            ->when($data_type, $timeFilter)
            ->sum('bet') ?? 0;

        // 查询实体机押注 - 钢珠
        $steelBallBetTotal = PlayerGameLog::query()
            ->whereHas('player', function ($q) use ($storeIds) {
                $q->whereIn('store_admin_id', $storeIds)
                    ->where('is_promoter', 0);
            })
            ->whereHas('machine.machineCategory.gameType', function ($query) {
                $query->where('type', GameType::TYPE_STEEL_BALL);
            })
            ->when($data_type, $timeFilter)
            ->sum('pressure') ?? 0;

        // 查询实体机押注 - Slot
        $slotBetTotal = PlayerGameLog::query()
            ->whereHas('player', function ($q) use ($storeIds) {
                $q->whereIn('store_admin_id', $storeIds)
                    ->where('is_promoter', 0);
            })
            ->whereHas('machine.machineCategory.gameType', function ($query) {
                $query->where('type', GameType::TYPE_SLOT);
            })
            ->when($data_type, $timeFilter)
            ->sum('pressure') ?? 0;

        $statisticsData = [
            'recharge_amount' => $rechargeAmount,
            'withdraw_amount' => $withdrawAmount,
            'machine_put_point' => $machinePutPoint,
            'electronic_bet' => $electronicBetTotal,
            'steel_ball_bet' => $steelBallBetTotal,
            'slot_bet' => $slotBetTotal,
        ];

        // 7. 查询当期数据（按下级店家分组统计）
        $totalData = PlayerDeliveryRecord::query()
            ->join('player', 'player_delivery_record.player_id', '=', 'player.id')
            ->join('admin_users', 'player.store_admin_id', '=', 'admin_users.id')
            ->whereIn('player.store_admin_id', $storeIds)
            ->where('player.is_promoter', 0)
            ->when(!empty($agent->last_settlement_timestamp), function ($query) use ($agent) {
                $query->where('player_delivery_record.created_at', '>=', date('Y-m-d H:i:s', $agent->last_settlement_timestamp));
            })
            ->selectRaw('
                        player.store_admin_id,
                        admin_users.ratio,
                        sum(IF(player_delivery_record.type = ' . PlayerDeliveryRecord::TYPE_PRESENT_IN . ', player_delivery_record.amount, 0)) as total_in,
                        sum(IF(player_delivery_record.type = ' . PlayerDeliveryRecord::TYPE_PRESENT_OUT . ', player_delivery_record.amount, 0)) as total_out,
                        sum(IF(player_delivery_record.type = ' . PlayerDeliveryRecord::TYPE_MACHINE . ', player_delivery_record.amount, 0)) as total_point
                    ')
            ->groupBy('player.store_admin_id', 'admin_users.ratio')
            ->get();

        // 7. 计算当期各项金额和分润
        $presentInAmount = 0;
        $machinePutPoint = 0;
        $presentOutAmount = 0;
        $selfProfitAmount = 0;
        $totalPoint = 0;

        foreach ($totalData as $data) {
            // 营收 = 投钞 + 转入 - 转出
            $revenue = bcsub(bcadd($data['total_point'], $data['total_in'], 2), $data['total_out'], 2);
            $totalPoint = bcadd($totalPoint, $revenue, 2);

            // 分润计算：(店家ratio - 代理ratio) * 营收
            $storeRatio = $data['ratio'] ?? 0;
            if ($storeRatio - $agent->ratio > 0) {
                $selfProfitAmount = bcadd($selfProfitAmount,
                    bcmul($revenue, ($storeRatio - $agent->ratio) / 100, 2), 2);
            }

            $presentInAmount = bcadd($presentInAmount, bcadd(0, $data['total_in'] ?? 0, 2), 2);
            $machinePutPoint = bcadd($machinePutPoint, bcadd(0, $data['total_point'] ?? 0, 2), 2);
            $presentOutAmount = bcadd($presentOutAmount, bcadd(0, $data['total_out'] ?? 0, 2), 2);
        }

        $ratio = $agent->ratio ?? 0;
        $info['present_in_amount'] = $presentInAmount;
        $info['present_out_amount'] = $presentOutAmount;
        $info['machine_put_point'] = $machinePutPoint;
        $info['self_profit_amount'] = bcadd($selfProfitAmount, $agent->adjust_amount ?? 0, 2);
        $info['total_point'] = $totalPoint;
        $info['adjust_amount'] = $agent->adjust_amount ?? 0;
        $info['profit_amount'] = bcmul($totalPoint, $ratio / 100, 2);
        $info['ratio'] = $ratio;

        // 日期筛选下拉框
        $dropdown = Dropdown::create(
            Button::create(admin_trans('data_center.data_cycle') . ' (' . admin_trans('data_center.data_type.' . ($data_type == null || $data_type == 'all' ? 'all' : $data_type)) . ')')
        )->trigger(['click']);
        $dropdown->item(admin_trans('data_center.data_type.all'))->redirect([$this, 'agentIndex'], ['data_type' => 'all']);
        $dropdown->item(admin_trans('data_center.data_type.today'))->redirect([$this, 'agentIndex'], ['data_type' => 'today']);
        $dropdown->item(admin_trans('data_center.data_type.yesterday'))->redirect([$this, 'agentIndex'], ['data_type' => 'yesterday']);
        $dropdown->item(admin_trans('data_center.data_type.week'))->redirect([$this, 'agentIndex'], ['data_type' => 'week']);
        $dropdown->item(admin_trans('data_center.data_type.last_week'))->redirect([$this, 'agentIndex'], ['data_type' => 'last_week']);
        $dropdown->item(admin_trans('data_center.data_type.month'))->redirect([$this, 'agentIndex'], ['data_type' => 'month']);
        $dropdown->item(admin_trans('data_center.data_type.last_month'))->redirect([$this, 'agentIndex'], ['data_type' => 'last_month']);

        // 计算盈余小计（充值 - 提现）
        $subtotal = bcsub($statisticsData['recharge_amount'] ?? 0, $statisticsData['withdraw_amount'] ?? 0, 2);

        $layout = Layout::create();
        $layout->row(function (Row $row) use (
            $agent,
            $storeNum,
            $playerNum,
            $statisticsData,
            $storeIds,
            $info,
            $dropdown,
            $subtotal
        ) {
            $row->gutter([10, 10]);
            $row->column(
                Card::create([
                    Row::create()->column(
                        Html::create()->content([
                            Html::create(admin_trans('admin.agent') . '：')->tag('span')->style([
                                'font-size' => '16px',
                                'font-weight' => 'bold',
                                'margin-right' => '10px'
                            ]),
                            Html::create($agent->nickname ?? $agent->username)->tag('span')->style([
                                'font-size' => '16px',
                                'color' => '#409eff'
                            ])
                        ])
                    , 9),
                    Row::create()->column($dropdown, 15),
                ])->bodyStyle([
                    'padding' => '13px',
                    'display' => 'flex',
                    'align-items' => 'center',
                    'justify-content' => 'space-between'
                ])
            );
            $row->column(
                Card::create([
                    Row::create()->column(Icon::create('fas fa-globe')->style([
                        'fontSize' => '45px',
                        'color' => '#409eff',
                        'marginRight' => '20px'
                    ]), 6),
                    Row::create()->column(Statistic::create()->title('总店家数')->value(floatval($storeNum))
                        ->valueStyle([
                            'fontSize' => '20px',
                            'fontWeight' => '500',
                            'textAlign' => 'center'
                        ])->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 18),
                ])->bodyStyle([
                    'display' => 'flex',
                    'align-items' => 'center'
                ])->hoverable()->headStyle([
                    'height' => '0px',
                    'border-bottom' => '0px',
                    'min-height' => '0px'
                ])
                , 6);

            $row->column(
                Card::create([
                    Row::create()->column(Icon::create('fas fa-users')->style([
                        'fontSize' => '45px',
                        'color' => '#409eff',
                        'marginRight' => '20px'
                    ]), 6),
                    Row::create()->column(Statistic::create()->title('总设备数')->value(floatval($playerNum))
                        ->valueStyle([
                            'fontSize' => '20px',
                            'fontWeight' => '500',
                            'textAlign' => 'center'
                        ])->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 18),
                ])->bodyStyle([
                    'display' => 'flex',
                    'align-items' => 'center'
                ])->hoverable()->headStyle([
                    'height' => '0px',
                    'border-bottom' => '0px',
                    'min-height' => '0px'
                ])
                , 6);

            $row->column(
                Card::create([
                    Row::create()->column(Icon::create('fas fa-money-bill')->style([
                        'fontSize' => '45px',
                        'color' => '#409eff',
                        'marginRight' => '20px'
                    ]), 4),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.total_recharge'))->value(floatval($statisticsData['recharge_amount'] ?? 0))
                        ->valueStyle([
                            'fontSize' => '20px',
                            'fontWeight' => '500',
                            'textAlign' => 'center'
                        ])->style([
                            'fontSize' => '45px',
                            'textAlign' => 'center'
                        ]), 10),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.total_withdraw'))->value(floatval($statisticsData['withdraw_amount'] ?? 0))
                        ->valueStyle([
                            'fontSize' => '20px',
                            'fontWeight' => '500',
                            'textAlign' => 'center'
                        ])->style([
                            'fontSize' => '45px',
                            'textAlign' => 'center'
                        ]), 10),
                ])->bodyStyle([
                    'display' => 'flex',
                    'align-items' => 'center'
                ])->hoverable()->headStyle([
                    'height' => '0px',
                    'border-bottom' => '0px',
                    'min-height' => '0px'
                ])
                , 6);

            $row->column(
                Card::create([
                    Row::create()->column(Icon::create('fas fa-money-bill-alt')->style([
                        'fontSize' => '45px',
                        'color' => '#e91e63',
                        'marginRight' => '20px'
                    ]), 6),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.total_machine_put'))->value(floatval($statisticsData['machine_put_point'] ?? 0))
                        ->valueStyle([
                            'fontSize' => '20px',
                            'fontWeight' => '500',
                            'textAlign' => 'center'
                        ])->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 18),
                ])->bodyStyle(['display' => 'flex', 'align-items' => 'center'])->hoverable()
                , 6);

            // 电子游戏总押注
            $row->column(
                Card::create([
                    Row::create()->column(Icon::create('fas fa-gamepad')->style([
                        'fontSize' => '45px',
                        'color' => '#1890ff',
                        'marginRight' => '20px'
                    ]), 6),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.electronic_bet'))->value(floatval($statisticsData['electronic_bet'] ?? 0))
                        ->valueStyle([
                            'fontSize' => '20px',
                            'fontWeight' => '500',
                            'textAlign' => 'center'
                        ])->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 18),
                ])->bodyStyle(['display' => 'flex', 'align-items' => 'center'])->hoverable()->headStyle([
                    'height' => '0px',
                    'border-bottom' => '0px',
                    'min-height' => '0px'
                ])
                , 6);

            // 实体机押注 - 钢珠
            $row->column(
                Card::create([
                    Row::create()->column(Icon::create('fas fa-circle')->style([
                        'fontSize' => '45px',
                        'color' => '#52c41a',
                        'marginRight' => '20px'
                    ]), 6),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.steel_ball_bet'))->value(floatval($statisticsData['steel_ball_bet'] ?? 0))
                        ->valueStyle([
                            'fontSize' => '20px',
                            'fontWeight' => '500',
                            'textAlign' => 'center'
                        ])->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 18),
                ])->bodyStyle(['display' => 'flex', 'align-items' => 'center'])->hoverable()->headStyle([
                    'height' => '0px',
                    'border-bottom' => '0px',
                    'min-height' => '0px'
                ])
                , 6);

            // 实体机押注 - Slot
            $row->column(
                Card::create([
                    Row::create()->column(Icon::create('fas fa-square')->style([
                        'fontSize' => '45px',
                        'color' => '#faad14',
                        'marginRight' => '20px'
                    ]), 6),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.slot_bet'))->value(floatval($statisticsData['slot_bet'] ?? 0))
                        ->valueStyle([
                            'fontSize' => '20px',
                            'fontWeight' => '500',
                            'textAlign' => 'center'
                        ])->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 18),
                ])->bodyStyle(['display' => 'flex', 'align-items' => 'center'])->hoverable()->headStyle([
                    'height' => '0px',
                    'border-bottom' => '0px',
                    'min-height' => '0px'
                ])
                , 6);

            //盈余小计
            $row->column(
                Card::create([
                    Row::create()->column(Icon::create('fas fa-balance-scale')->style([
                        'fontSize' => '45px',
                        'color' => floatval($subtotal) >= 0 ? '#67C23A' : '#F56C6C',
                        'marginRight' => '20px'
                    ]), 6),
                    Row::create()->column(Statistic::create()->title('盈余小计')->value(number_format(floatval($subtotal), 2))
                        ->valueStyle([
                            'fontSize' => '20px',
                            'fontWeight' => '500',
                            'textAlign' => 'center',
                            'color' => floatval($subtotal) >= 0 ? '#67C23A' : '#F56C6C'
                        ])->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 18),
                ])->bodyStyle(['display' => 'flex', 'align-items' => 'center'])->hoverable()->headStyle([
                    'height' => '0px',
                    'border-bottom' => '0px',
                    'min-height' => '0px'
                ])
                , 6);

            $row->column(Card::create($this->openWashChart($storeIds))->hoverable(), 16);
            $row->column(Card::create($this->moneyChart($storeIds))->hoverable(), 8);
            $row->column(Card::create($this->revenueChart($storeIds))->hoverable(), 12);
            $row->column(Card::create($this->profitLossChart($storeIds))->hoverable(), 12);
        });

        return $layout;
    }

    /**
     * 充值/提现趋势
     * @param $storeIds
     * @return LineChart
     */
    public function openWashChart($storeIds = []): LineChart
    {
        $range = Carbon::now()->subDays(15)->format('Y-m-d');
        // 如果没有店家ID，返回空数据
        if (empty($storeIds) || (is_object($storeIds) && $storeIds->isEmpty())) {
            $dataA = [];
            $dataB = [];
        } else {
            // 查询充值数据（不包括投钞）
            $rechargeData = PlayerRechargeRecord::query()
                ->whereDate('created_at', '>=', $range)
                ->whereHas('player', function ($query) use ($storeIds) {
                    $query->whereIn('store_admin_id', $storeIds)
                        ->where('is_promoter', 0);
                })
                ->where('status', PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)
                ->whereIn('type', [
                    PlayerRechargeRecord::TYPE_THIRD,
                    PlayerRechargeRecord::TYPE_SELF,
                    PlayerRechargeRecord::TYPE_BUSINESS,
                    PlayerRechargeRecord::TYPE_ARTIFICIAL,
                    PlayerRechargeRecord::TYPE_GB,
                    PlayerRechargeRecord::TYPE_EH,
                ])
                ->groupBy('date')
                ->orderBy('date', 'DESC')
                ->get([
                    DB::raw('Date(`created_at`) as date'),
                    DB::raw('SUM(`point`) as recharge_amount')
                ])
                ->toArray();

            // 查询提现数据
            $withdrawData = PlayerWithdrawRecord::query()
                ->whereDate('created_at', '>=', $range)
                ->whereHas('player', function ($query) use ($storeIds) {
                    $query->whereIn('store_admin_id', $storeIds)
                        ->where('is_promoter', 0);
                })
                ->where('status', PlayerWithdrawRecord::STATUS_SUCCESS)
                ->groupBy('date')
                ->orderBy('date', 'DESC')
                ->get([
                    DB::raw('Date(`created_at`) as date'),
                    DB::raw('SUM(`point`) as withdraw_amount')
                ])
                ->toArray();

            $dataA = $rechargeData ? array_column($rechargeData, 'recharge_amount', 'date') : [];
            $dataB = $withdrawData ? array_column($withdrawData, 'withdraw_amount', 'date') : [];
        }

        $xAxis = [];
        $yAxisA = [];
        $yAxisB = [];
        for ($i = 14; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $xAxis[] = $date;
            $yAxisA[] = $dataA[$date] ?? 0;
            $yAxisB[] = $dataB[$date] ?? 0;
        }

        return LineChart::create()
            ->height('280px')
            ->hideDateFilter()
            ->header(Html::create(admin_trans('data_center.recharge_withdraw_trend'))->tag('h2')->style(['text-align' => 'center']))
            ->xAxis($xAxis)
            ->data(admin_trans('data_center.recharge'), $yAxisA)
            ->data(admin_trans('data_center.withdraw'), $yAxisB);
    }

    /**
     * 投钞
     * @param $storeIds
     * @return LineChart
     */
    public function moneyChart($storeIds = []): LineChart
    {
        $range = Carbon::now()->subDays(15)->format('Y-m-d');
        // 如果没有店家ID，返回空数据
        if (empty($storeIds) || (is_object($storeIds) && $storeIds->isEmpty())) {
            $dataA = [];
        } else {
            // 查询投钞数据
            $data = PlayerRechargeRecord::query()
                ->whereDate('created_at', '>=', $range)
                ->whereHas('player', function ($query) use ($storeIds) {
                    $query->whereIn('store_admin_id', $storeIds)
                        ->where('is_promoter', 0);
                })
                ->where('status', PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)
                ->where('type', PlayerRechargeRecord::TYPE_MACHINE)
                ->groupBy('date')
                ->orderBy('date', 'DESC')
                ->get([
                    DB::raw('Date(`created_at`) as date'),
                    DB::raw('SUM(`point`) as machine_amount')
                ])
                ->toArray();

            $dataA = $data ? array_column($data, 'machine_amount', 'date') : [];
        }

        $xAxis = [];
        $yAxis = [];
        for ($i = 14; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $xAxis[] = $date;
            $yAxis[] = $dataA[$date] ?? 0;
        }

        return LineChart::create()
            ->height('280px')
            ->hideDateFilter()
            ->header(Html::create(admin_trans('data_center.machine_put_trend'))->tag('h2')->style(['text-align' => 'center']))
            ->xAxis($xAxis)
            ->data(admin_trans('data_center.machine_put'), $yAxis);
    }

    /**
     * 营收趋势
     * @param $storeIds
     * @return BarChart
     */
    public function revenueChart($storeIds = []): BarChart
    {
        $range = Carbon::now()->subDays(15)->format('Y-m-d');
        // 如果没有店家ID，返回空数据
        if (empty($storeIds) || (is_object($storeIds) && $storeIds->isEmpty())) {
            $dataA = [];
            $dataB = [];
        } else {
            // 查询充值数据（不包括投钞）
            $rechargeData = PlayerRechargeRecord::query()
                ->whereDate('created_at', '>=', $range)
                ->whereHas('player', function ($query) use ($storeIds) {
                    $query->whereIn('store_admin_id', $storeIds)
                        ->where('is_promoter', 0);
                })
                ->where('status', PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)
                ->whereIn('type', [
                    PlayerRechargeRecord::TYPE_THIRD,
                    PlayerRechargeRecord::TYPE_SELF,
                    PlayerRechargeRecord::TYPE_BUSINESS,
                    PlayerRechargeRecord::TYPE_ARTIFICIAL,
                    PlayerRechargeRecord::TYPE_GB,
                    PlayerRechargeRecord::TYPE_EH,
                ])
                ->groupBy('date')
                ->orderBy('date', 'DESC')
                ->get([
                    DB::raw('Date(`created_at`) as date'),
                    DB::raw('SUM(`point`) as recharge_amount')
                ])
                ->toArray();

            // 查询投钞数据
            $machineData = PlayerRechargeRecord::query()
                ->whereDate('created_at', '>=', $range)
                ->whereHas('player', function ($query) use ($storeIds) {
                    $query->whereIn('store_admin_id', $storeIds)
                        ->where('is_promoter', 0);
                })
                ->where('status', PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)
                ->where('type', PlayerRechargeRecord::TYPE_MACHINE)
                ->groupBy('date')
                ->orderBy('date', 'DESC')
                ->get([
                    DB::raw('Date(`created_at`) as date'),
                    DB::raw('SUM(`point`) as machine_amount')
                ])
                ->toArray();

            $dataA = $rechargeData ? array_column($rechargeData, 'recharge_amount', 'date') : [];
            $dataB = $machineData ? array_column($machineData, 'machine_amount', 'date') : [];
        }

        $xAxis = [];
        $yAxisA = [];
        $yAxisB = [];
        for ($i = 14; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $xAxis[] = $date;
            $yAxisA[] = $dataA[$date] ?? 0;
            $yAxisB[] = $dataB[$date] ?? 0;
        }
        return BarChart::create()
            ->height('280px')
            ->hideDateFilter()
            ->header(Html::create(admin_trans('data_center.revenue_trend'))->tag('h2')->style(['text-align' => 'center']))
            ->xAxis($xAxis)
            ->data(admin_trans('data_center.recharge'), $yAxisA)
            ->data(admin_trans('data_center.machine_put'), $yAxisB);
    }

    /**
     * 盈亏分析
     * @param $storeIds
     * @return BarChart
     */
    public function profitLossChart($storeIds = []): BarChart
    {
        $range = Carbon::now()->subDays(15)->format('Y-m-d');
        // 如果没有店家ID，返回空数据
        if (empty($storeIds) || (is_object($storeIds) && $storeIds->isEmpty())) {
            $dataA = [];
            $dataB = [];
            $dataC = [];
        } else {
            // 查询充值数据（不包括投钞）
            $rechargeData = PlayerRechargeRecord::query()
                ->whereDate('created_at', '>=', $range)
                ->whereHas('player', function ($query) use ($storeIds) {
                    $query->whereIn('store_admin_id', $storeIds)
                        ->where('is_promoter', 0);
                })
                ->where('status', PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)
                ->whereIn('type', [
                    PlayerRechargeRecord::TYPE_THIRD,
                    PlayerRechargeRecord::TYPE_SELF,
                    PlayerRechargeRecord::TYPE_BUSINESS,
                    PlayerRechargeRecord::TYPE_ARTIFICIAL,
                    PlayerRechargeRecord::TYPE_GB,
                    PlayerRechargeRecord::TYPE_EH,
                ])
                ->groupBy('date')
                ->orderBy('date', 'DESC')
                ->get([
                    DB::raw('Date(`created_at`) as date'),
                    DB::raw('SUM(`point`) as recharge_amount')
                ])
                ->toArray();

            // 查询投钞数据
            $machineData = PlayerRechargeRecord::query()
                ->whereDate('created_at', '>=', $range)
                ->whereHas('player', function ($query) use ($storeIds) {
                    $query->whereIn('store_admin_id', $storeIds)
                        ->where('is_promoter', 0);
                })
                ->where('status', PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)
                ->where('type', PlayerRechargeRecord::TYPE_MACHINE)
                ->groupBy('date')
                ->orderBy('date', 'DESC')
                ->get([
                    DB::raw('Date(`created_at`) as date'),
                    DB::raw('SUM(`point`) as machine_amount')
                ])
                ->toArray();

            // 查询提现数据
            $withdrawData = PlayerWithdrawRecord::query()
                ->whereDate('created_at', '>=', $range)
                ->whereHas('player', function ($query) use ($storeIds) {
                    $query->whereIn('store_admin_id', $storeIds)
                        ->where('is_promoter', 0);
                })
                ->where('status', PlayerWithdrawRecord::STATUS_SUCCESS)
                ->groupBy('date')
                ->orderBy('date', 'DESC')
                ->get([
                    DB::raw('Date(`created_at`) as date'),
                    DB::raw('SUM(`point`) as withdraw_amount')
                ])
                ->toArray();

            $dataA = $rechargeData ? array_column($rechargeData, 'recharge_amount', 'date') : [];
            $dataB = $withdrawData ? array_column($withdrawData, 'withdraw_amount', 'date') : [];
            $dataC = $machineData ? array_column($machineData, 'machine_amount', 'date') : [];
        }

        $xAxis = [];
        $yAxis = [];
        for ($i = 14; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $xAxis[] = $date;
            $rechargeAmount = $dataA[$date] ?? 0;
            $withdrawAmount = $dataB[$date] ?? 0;
            $machinePutPoint = $dataC[$date] ?? 0;
            $yAxis[] = $rechargeAmount + $machinePutPoint - $withdrawAmount;
        }
        return BarChart::create()
            ->height('280px')
            ->hideDateFilter()
            ->header(Html::create('盈亏分析')->tag('h2')->style(['text-align' => 'center']))
            ->xAxis($xAxis)
            ->data('盈亏', $yAxis);
    }

    /**
     * 上传
     * @return Response|void
     */
    public function myEditorUpload()
    {
        $file = request()->file('file');
        if ($file && $file->isValid()) {
            $size = $file->getSize();
            if ($file->getSize() >= 1024 * 1024) {
                return jsonFailResponse(trans('image_upload_size_fail', ['{size}' => '1M'], 'message'));
            }
            $extension = $file->getUploadExtension();
            if (!in_array($extension, ['png', 'jpg', 'jpeg'])) {
                return jsonFailResponse(trans('image_upload_size_fail', ['{size}' => '1M'], 'message'));
            }
            $uploadName = $file->getUploadName();
            $basePath = public_path() . '/storage/' . date('Ymd') . DIRECTORY_SEPARATOR;
            $baseUrl = env('APP_URL', 'http://127.0.0.1:8787') . '/storage/' . date('Ymd') . '/';
            $uniqueId = hash_file('md5', $file->getPathname());
            $saveFilename = $uniqueId . '.' . $file->getUploadExtension();
            $savePath = $basePath . $saveFilename;
            $file->move($savePath);

            return jsonSuccessResponse('success', [
                'origin_name' => $uploadName,
                'save_name' => $saveFilename,
                'save_path' => $savePath,
                'url' => $baseUrl . $saveFilename,
                'unique_id' => $uniqueId,
                'size' => $size,
                'mime_type' => $file->getUploadMimeType(),
                'extension' => $extension,
            ]);
        }
    }

    /**
     * 店家中心
     * @group channel
     * @auth true
     */
    public function storeIndex(): Layout
    {
        /** @var \addons\webman\model\AdminUser $store */
        $store = Admin::user();

        // 获取筛选参数
        $exAdminFilter = Request::input('ex_admin_filter', []);
        $dateType = isset($exAdminFilter['date_type']) && $exAdminFilter['date_type'] !== '' ? intval($exAdminFilter['date_type']) : null;

        // 获取自动交班配置状态
        /** @var StoreAutoShiftConfig|null $autoShiftConfig */
        $autoShiftConfig = StoreAutoShiftConfig::query()
            ->where('department_id', $store->department_id)
            ->where('bind_admin_user_id', $store->id)
            ->first();

        $autoShiftEnabled = $autoShiftConfig && $autoShiftConfig->is_enabled == 1;
        $autoShiftStatusText = $autoShiftEnabled ? '自动交班：已开启' : '自动交班：已关闭';
        $autoShiftStatusColor = $autoShiftEnabled ? '#67C23A' : '#909399';

        // 查询店家下的玩家（使用 store_admin_id）
        $playerNum = Player::query()
            ->where('department_id', $store->department_id)
            ->where('store_admin_id', $store->id)
            ->where('is_promoter', 0)
            ->count();
        $playerIds = Player::query()
            ->where('department_id', $store->department_id)
            ->where('store_admin_id', $store->id)
            ->where('is_promoter', 0)
            ->get()
            ->pluck('id');

        // 运营统计数据（受时间筛选影响）
        $operationStatisticsQuery = PlayerDeliveryRecord::query()
            ->when(!empty($playerIds), function ($query) use ($playerIds) {
                $query->whereIn('player_id', $playerIds);
            })
            ->when($dateType !== null && $dateType > 0, function ($query) use ($dateType) {
                $query->where(getDateWhere($dateType, 'created_at'));
            })
            ->whereIn('type', [
                PlayerDeliveryRecord::TYPE_PRESENT_IN,
                PlayerDeliveryRecord::TYPE_PRESENT_OUT,
                PlayerDeliveryRecord::TYPE_MACHINE,
            ])
            ->selectRaw("
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_PRESENT_IN . " THEN `amount` ELSE 0 END) AS present_in_amount,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_PRESENT_OUT . " THEN `amount` ELSE 0 END) AS present_out_amount,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_MACHINE . " THEN `amount` ELSE 0 END) AS machine_put_point
            ")
            ->first();

        $operationStatistics = [
            'present_in_amount' => $operationStatisticsQuery->present_in_amount ?? 0,
            'present_out_amount' => $operationStatisticsQuery->present_out_amount ?? 0,
            'machine_put_point' => $operationStatisticsQuery->machine_put_point ?? 0,
        ];

        // 拉彩统计数据（受时间筛选影响）
        $lotteryStatisticsQuery = PlayerLotteryRecord::query()
            ->when(!empty($playerIds), function ($query) use ($playerIds) {
                $query->whereIn('player_id', $playerIds);
            })
            ->when($dateType !== null && $dateType > 0, function ($query) use ($dateType) {
                $query->where(getDateWhere($dateType, 'created_at'));
            })
            ->where('status', PlayerLotteryRecord::STATUS_COMPLETE)
            ->selectRaw("
                COUNT(*) as lottery_count,
                SUM(`amount`) as lottery_amount
            ")
            ->first();

        $lotteryStatistics = [
            'lottery_count' => $lotteryStatisticsQuery->lottery_count ?? 0,
            'lottery_amount' => $lotteryStatisticsQuery->lottery_amount ?? 0,
        ];

        // 总数据统计（不受时间筛选影响，用于"总转入"、"总转出"、"总投钞"卡片）
        $totalStatisticsQuery = PlayerDeliveryRecord::query()
            ->when(!empty($playerIds), function ($query) use ($playerIds) {
                $query->whereIn('player_id', $playerIds);
            })
            ->whereIn('type', [
                PlayerDeliveryRecord::TYPE_PRESENT_IN,
                PlayerDeliveryRecord::TYPE_PRESENT_OUT,
                PlayerDeliveryRecord::TYPE_MACHINE,
            ])
            ->selectRaw("
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_PRESENT_IN . " THEN `amount` ELSE 0 END) AS present_in_amount,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_PRESENT_OUT . " THEN `amount` ELSE 0 END) AS present_out_amount,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_MACHINE . " THEN `amount` ELSE 0 END) AS machine_put_point
            ")
            ->first();

        $playerDeliveryRecord = [
            [
                'present_in_amount' => $totalStatisticsQuery->present_in_amount ?? 0,
                'present_out_amount' => $totalStatisticsQuery->present_out_amount ?? 0,
                'machine_put_point' => $totalStatisticsQuery->machine_put_point ?? 0,
            ]
        ];
        $selfProfitAmount = 0;
        // 店家的配置直接从 AdminUser 读取
        $ratio = $store->ratio ?? 0;
        $adjustAmount = $store->adjust_amount ?? 0;
        $lastSettlementTimestamp = $store->last_settlement_timestamp;

        // 统计交易数据
        $totalData = PlayerDeliveryRecord::query()
            ->whereIn('player_id', $playerIds)
            ->when(!empty($lastSettlementTimestamp), function ($query) use ($lastSettlementTimestamp) {
                $query->where('created_at', '>=', $lastSettlementTimestamp);
            })->selectRaw('
                    sum(IF(type = ' . PlayerDeliveryRecord::TYPE_PRESENT_IN . ', amount, 0)) as total_in,
                    sum(IF(type = ' . PlayerDeliveryRecord::TYPE_PRESENT_OUT . ', amount, 0)) as total_out,
                    sum(IF(type = ' . PlayerDeliveryRecord::TYPE_MACHINE . ', amount, 0)) as total_point
                ')->first();

        $presentInAmount = bcadd(0, $totalData['total_in'] ?? 0, 2);
        $machinePutPoint = bcadd(0, $totalData['total_point'] ?? 0, 2);
        $presentOutAmount = bcadd(0, $totalData['total_out'] ?? 0, 2);
        $totalPoint = bcsub(bcadd($machinePutPoint, $presentInAmount, 2), $presentOutAmount, 2);
        if (100 - $ratio > 0) {
            $selfProfitAmount = bcmul($totalPoint, (100 - $ratio) / 100, 2);
        }
        $info['present_in_amount'] = $presentInAmount;
        $info['present_out_amount'] = $presentOutAmount;
        $info['machine_put_point'] = $machinePutPoint;
        $info['self_profit_amount'] = bcadd($selfProfitAmount, $adjustAmount, 2);
        $info['total_point'] = $totalPoint;
        $info['adjust_amount'] = $adjustAmount;
        $info['profit_amount'] = $ratio > 0 ? bcmul($totalPoint, $ratio / 100, 2) : 0;
        $info['ratio'] = $ratio;

        // 店家不再有 PlayerPlatformCash，设为 null
        $storePlatformCash = null;
        // 创建时间筛选下拉菜单
        $dateTypeLabels = [
            null => '全部',
            1 => '今日',
            2 => '昨天',
            3 => '本周',
            4 => '上周',
            5 => '本月',
            6 => '上月'
        ];
        $currentLabel = $dateTypeLabels[$dateType] ?? '全部';
        $timeDropdown = Dropdown::create(
            Button::create($currentLabel)->size('mini')
        )->trigger(['click']);
        $timeDropdown->item('全部')->redirect([$this, 'storeIndex'], ['ex_admin_filter' => []]);
        $timeDropdown->item('今日')->redirect([$this, 'storeIndex'], ['ex_admin_filter' => ['date_type' => 1]]);
        $timeDropdown->item('昨天')->redirect([$this, 'storeIndex'], ['ex_admin_filter' => ['date_type' => 2]]);
        $timeDropdown->item('本周')->redirect([$this, 'storeIndex'], ['ex_admin_filter' => ['date_type' => 3]]);
        $timeDropdown->item('上周')->redirect([$this, 'storeIndex'], ['ex_admin_filter' => ['date_type' => 4]]);
        $timeDropdown->item('本月')->redirect([$this, 'storeIndex'], ['ex_admin_filter' => ['date_type' => 5]]);
        $timeDropdown->item('上月')->redirect([$this, 'storeIndex'], ['ex_admin_filter' => ['date_type' => 6]]);

        $layout = Layout::create();
        $layout->row(function (Row $row) use (
            $playerNum,
            $operationStatistics,
            $lotteryStatistics,
            $playerIds,
            $store,
            $info,
            $dateType,
            $timeDropdown,
            $autoShiftStatusText,
            $autoShiftStatusColor
        ) {
            /** @var StoreAgentShiftHandoverRecord $storeAgentShiftHandoverRecord */
            $storeAgentShiftHandoverRecord = StoreAgentShiftHandoverRecord::query()->where('bind_admin_user_id',
                Admin::user()->id)->orderBy('id', 'desc')->first();
            $row->gutter([10, 10]);
            // 计算运营统计的小计（基于时间筛选的数据）
            $subtotal = bcsub(
                $operationStatistics['present_in_amount'] ?? 0,
                $operationStatistics['present_out_amount'] ?? 0,
                2
            );

            $row->column(
                Card::create([
                    Row::create()->column([
                        Button::create('交班')->modal([$this, 'shiftHandover']),
                        Html::create(admin_trans('shift_handover.start_time') . ': ' . ($storeAgentShiftHandoverRecord->end_time ?? admin_trans('shift_handover.none')))
                            ->style([
                                'color' => 'rgb(26 148 169)',
                                'fontSize' => '16px',
                                'marginLeft' => '20px',
                                'fontWeight' => 'bold',
                            ]),
                        Html::create()->content([
                            Icon::create('clock-circle')->style(['marginRight' => '4px', 'fontSize' => '14px']),
                            Html::create($autoShiftStatusText)->style([
                                'fontSize' => '13px',
                                'color' => $autoShiftStatusColor
                            ])
                        ])->style([
                            'display' => 'flex',
                            'alignItems' => 'center',
                            'marginLeft' => '20px'
                        ])
                    ])->style([
                        'display' => 'flex',
                        'alignItems' => 'center'
                    ])
                ])->bodyStyle([
                    'padding' => '13px',
                    'display' => 'flex',
                    'alignItems' => 'center',
                    'height' => '54px'
                ])
            , 5);

            // 运营统计标题和筛选
            $row->column(
                Card::create([
                    Row::create()->column([
                        Html::create('运营统计')->style([
                            'fontSize' => '14px',
                            'fontWeight' => 'bold',
                            'color' => '#303133',
                            'marginRight' => '10px'
                        ]),
                        $timeDropdown
                    ])->style([
                        'display' => 'flex',
                        'alignItems' => 'center'
                    ])
                ])->bodyStyle([
                    'padding' => '13px',
                    'display' => 'flex',
                    'alignItems' => 'center',
                    'height' => '54px'
                ])
            , 3);

            // 上分总和（运营统计，受时间筛选影响）
            $row->column(
                Card::create([
                    Row::create()->column([
                        Html::create('上分总和')->style([
                            'fontSize' => '14px',
                            'color' => '#909399',
                            'marginRight' => 'auto'
                        ]),
                        Html::create(number_format(floatval($operationStatistics['present_in_amount'] ?? 0), 2))->style([
                            'fontSize' => '20px',
                            'fontWeight' => '600',
                            'color' => '#67C23A'
                        ])
                    ])->style([
                        'display' => 'flex',
                        'alignItems' => 'center',
                        'justifyContent' => 'space-between',
                        'width' => '100%'
                    ])
                ])->bodyStyle([
                    'padding' => '13px',
                    'display' => 'flex',
                    'alignItems' => 'center',
                    'height' => '54px'
                ])
            , 4);

            // 下分总和（运营统计，受时间筛选影响）
            $row->column(
                Card::create([
                    Row::create()->column([
                        Html::create('下分总和')->style([
                            'fontSize' => '14px',
                            'color' => '#909399',
                            'marginRight' => 'auto'
                        ]),
                        Html::create(number_format(floatval($operationStatistics['present_out_amount'] ?? 0), 2))->style([
                            'fontSize' => '20px',
                            'fontWeight' => '600',
                            'color' => '#F56C6C'
                        ])
                    ])->style([
                        'display' => 'flex',
                        'alignItems' => 'center',
                        'justifyContent' => 'space-between',
                        'width' => '100%'
                    ])
                ])->bodyStyle([
                    'padding' => '13px',
                    'display' => 'flex',
                    'alignItems' => 'center',
                    'height' => '54px'
                ])
            , 4);

            // 投钞总和（运营统计，受时间筛选影响）
            $row->column(
                Card::create([
                    Row::create()->column([
                        Html::create(admin_trans('data_center.machine_put_total'))->style([
                            'fontSize' => '14px',
                            'color' => '#909399',
                            'marginRight' => 'auto'
                        ]),
                        Html::create(number_format(floatval($operationStatistics['machine_put_point'] ?? 0), 2))->style([
                            'fontSize' => '20px',
                            'fontWeight' => '600',
                            'color' => '#409EFF'
                        ])
                    ])->style([
                        'display' => 'flex',
                        'alignItems' => 'center',
                        'justifyContent' => 'space-between',
                        'width' => '100%'
                    ])
                ])->bodyStyle([
                    'padding' => '13px',
                    'display' => 'flex',
                    'alignItems' => 'center',
                    'height' => '54px'
                ])
            , 4);

            // 小计（上分 - 下分）
            $row->column(
                Card::create([
                    Row::create()->column([
                        Html::create('小计')->style([
                            'fontSize' => '14px',
                            'color' => '#909399',
                            'marginRight' => 'auto'
                        ]),
                        Html::create(number_format(floatval($subtotal), 2))->style([
                            'fontSize' => '20px',
                            'fontWeight' => '600',
                            'color' => floatval($subtotal) >= 0 ? '#67C23A' : '#F56C6C'
                        ])
                    ])->style([
                        'display' => 'flex',
                        'alignItems' => 'center',
                        'justifyContent' => 'space-between',
                        'width' => '100%'
                    ])
                ])->bodyStyle([
                    'padding' => '13px',
                    'display' => 'flex',
                    'alignItems' => 'center',
                    'height' => '54px'
                ])
            , 4);

            // 拉彩统计标题
            $row->column(
                Card::create([
                    Row::create()->column([
                        Html::create('拉彩统计')->style([
                            'fontSize' => '14px',
                            'fontWeight' => 'bold',
                            'color' => '#303133',
                            'marginRight' => '10px'
                        ])
                    ])->style([
                        'display' => 'flex',
                        'alignItems' => 'center'
                    ])
                ])->bodyStyle([
                    'padding' => '13px',
                    'display' => 'flex',
                    'alignItems' => 'center',
                    'height' => '54px'
                ])
            , 6);

            // 拉彩次数
            $row->column(
                Card::create([
                    Row::create()->column([
                        Html::create('拉彩次数')->style([
                            'fontSize' => '14px',
                            'color' => '#909399',
                            'marginRight' => 'auto'
                        ]),
                        Html::create(number_format(intval($lotteryStatistics['lottery_count'] ?? 0)))->style([
                            'fontSize' => '20px',
                            'fontWeight' => '600',
                            'color' => '#E6A23C'
                        ])
                    ])->style([
                        'display' => 'flex',
                        'alignItems' => 'center',
                        'justifyContent' => 'space-between',
                        'width' => '100%'
                    ])
                ])->bodyStyle([
                    'padding' => '13px',
                    'display' => 'flex',
                    'alignItems' => 'center',
                    'height' => '54px'
                ])
            , 9);

            // 拉彩金额
            $row->column(
                Card::create([
                    Row::create()->column([
                        Html::create('拉彩分数')->style([
                            'fontSize' => '14px',
                            'color' => '#909399',
                            'marginRight' => 'auto'
                        ]),
                        Html::create(number_format(floatval($lotteryStatistics['lottery_amount'] ?? 0), 2))->style([
                            'fontSize' => '20px',
                            'fontWeight' => '600',
                            'color' => '#E6A23C'
                        ])
                    ])->style([
                        'display' => 'flex',
                        'alignItems' => 'center',
                        'justifyContent' => 'space-between',
                        'width' => '100%'
                    ])
                ])->bodyStyle([
                    'padding' => '13px',
                    'display' => 'flex',
                    'alignItems' => 'center',
                    'height' => '54px'
                ])
            , 9);

            $row->column(
                Card::create([
                    Row::create()->column(Icon::create('fas fa-television')->style([
                        'fontSize' => '45px',
                        'color' => '#409eff',
                        'marginRight' => '20px'
                    ]), 4),
                    Row::create()->column(Statistic::create()->title('总设备数')->value(floatval($playerNum))
                        ->valueStyle([
                            'fontSize' => '20px',
                            'fontWeight' => '500',
                            'textAlign' => 'center'
                        ])->style([
                            'fontSize' => '45px',
                            'textAlign' => 'center'
                        ]), 10),
                    Row::create()->column(Statistic::create()->title('绑定代理')->value($store->parent_admin_id ? (\addons\webman\model\AdminUser::find($store->parent_admin_id)->username ?? '') : '')
                        ->valueStyle([
                            'fontSize' => '20px',
                            'fontWeight' => '500',
                            'textAlign' => 'center'
                        ])->style([
                            'fontSize' => '45px',
                            'textAlign' => 'center'
                        ]), 10),
                ])->bodyStyle([
                    'display' => 'flex',
                    'align-items' => 'center'
                ])->hoverable()->headStyle([
                    'height' => '0px',
                    'border-bottom' => '0px',
                    'min-height' => '0px'
                ])
                , 12);

            $row->column(
                Card::create([
                    Row::create()->column(Icon::create('fas fa-money-bill')->style([
                        'fontSize' => '45px',
                        'color' => '#409eff',
                        'marginRight' => '20px'
                    ]), 4),
                    Row::create()->column(Statistic::create()->title('当期上缴金额')->value(floatval($info['profit_amount'] ?? 0))
                        ->valueStyle([
                            'fontSize' => '20px',
                            'fontWeight' => '500',
                            'textAlign' => 'center'
                        ])->style([
                            'fontSize' => '45px',
                            'textAlign' => 'center'
                        ]), 10),
                    Row::create()->column(Statistic::create()->title('上缴比例')->value(floatval($info['ratio'] ?? 0) . '%')
                        ->valueStyle([
                            'fontSize' => '20px',
                            'fontWeight' => '500',
                            'textAlign' => 'center'
                        ])->style([
                            'fontSize' => '45px',
                            'textAlign' => 'center'
                        ]), 10),
                ])->bodyStyle([
                    'display' => 'flex',
                    'align-items' => 'center'
                ])->hoverable()->headStyle([
                    'height' => '0px',
                    'border-bottom' => '0px',
                    'min-height' => '0px'
                ])
                , 12);
            $row->column(Card::create($this->openWashChart([$store->id]))->hoverable(), 16);
            $row->column(Card::create($this->moneyChart([$store->id]))->hoverable(), 8);
            $row->column(Card::create($this->revenueChart([$store->id]))->hoverable(), 12);
            $row->column(Card::create($this->profitLossChart([$store->id]))->hoverable(), 12);
        });

        return $layout;
    }

    /**
     * 绑定代理
     * @group channel
     * @return Form
     */
    public function shiftHandover(): Form
    {
        // 检查是否启用自动交班
        $admin = Admin::user();
        $autoShiftService = new \app\service\store\AutoShiftService();

        if ($autoShiftService->isAutoShiftEnabled($admin->department_id, $admin->id)) {
            // 如果启用了自动交班，显示提示信息并阻止手动交班
            return Form::create([], function (Form $form) {
                $form->layout('vertical');
                $form->push(Html::div()->content(
                    Html::create()->content([
                        Icon::create('info-circle')->style([
                            'fontSize' => '48px',
                            'color' => '#1890ff'
                        ]),
                        Html::div()->content('已开启自动交班')->style([
                            'marginTop' => '20px',
                            'color' => '#1890ff',
                            'fontSize' => '20px',
                            'fontWeight' => 'bold'
                        ]),
                        Html::div()->content('系统已启用自动交班功能，无法进行手动交班操作。')->style([
                            'color' => '#666',
                            'marginTop' => '10px'
                        ]),
                        Html::div()->content('如需手动交班，请先到【自动交班配置】中关闭自动交班功能。')->style([
                            'color' => '#999',
                            'marginTop' => '5px'
                        ]),
                        Html::div()->content(
                            Button::create('前往自动交班配置')->redirect([\addons\webman\controller\ChannelAutoShiftController::class, 'config'])
                        )->style(['marginTop' => '30px'])
                    ])
                )->style([
                    'textAlign' => 'center',
                    'padding' => '40px 0'
                ]));

                // 禁用提交按钮
                $form->disableSubmit();
            });
        }

        return Form::create([], function (Form $form) {
            /** @var StoreAgentShiftHandoverRecord $storeAgentShiftHandoverRecord */
            $storeAgentShiftHandoverRecord = StoreAgentShiftHandoverRecord::query()->where('bind_admin_user_id',
                Admin::user()->id)->orderBy('id', 'desc')->first();
            if (!empty($storeAgentShiftHandoverRecord)) {
                if (is_string($storeAgentShiftHandoverRecord->end_time)) {
                    $endTime = Carbon::parse($storeAgentShiftHandoverRecord->end_time);
                } else {
                    $endTime = $storeAgentShiftHandoverRecord->end_time;
                }
                $form->date('end_time', admin_trans('shift_handover.shift_time'))->bindFunction('disabledDate', "
                    var date = new Date(time);
                    var Month = date.getMonth() + 1;
                    var Day = date.getDate();
                    var Y = date.getFullYear() + '-';
                    var M = Month < 10 ? '0' + Month + '-' : Month + '-';
                    var D = Day < 10 ? '0' + Day : Day;
                    var newDateStr = Y + M + D;
                    var condition1 = newDateStr < '" . $endTime->subDay()->format('Y-m-d') . "';
                    var condition2 = newDateStr > '" . Carbon::today()->format('Y-m-d') . "';
                    return condition2 || condition1;", ['time'])
                    ->valueFormat('YYYY-MM-DD HH:mm:ss')
                    ->showTime(true)->help(admin_trans('shift_handover.shift_time_help'))
                    ->style([
                        'width' => '100%',
                    ]);
            } else {
                $form->dateTimeRange('start_time', 'end_time', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ])->style([
                    'width' => '100%',
                ]);
            }
            $form->layout('vertical');
            $form->push(Card::create([
                Html::create(admin_trans('shift_handover.start_time') . ': ' . ($storeAgentShiftHandoverRecord->start_time ?? admin_trans('shift_handover.none')))->tag('p'),
                Html::create(admin_trans('shift_handover.end_time') . ': ' . ($storeAgentShiftHandoverRecord->end_time ?? admin_trans('shift_handover.none')))->tag('p'),
            ])->title(admin_trans('shift_handover.last_shift_time')));
            $form->saving(function (Form $form) {
                $transactionStarted = false;
                try {
                    $admin = Admin::user();
                    $endTime = $form->input('end_time');

                    // 1. 查询最后一条交班记录（在事务外）
                    /** @var StoreAgentShiftHandoverRecord $storeAgentShiftHandover */
                    $storeAgentShiftHandover = StoreAgentShiftHandoverRecord::query()
                        ->where('bind_admin_user_id', $admin->id)
                        ->orderBy('id', 'desc')
                        ->first();

                    // 2. 确定开始时间
                    if (!empty($storeAgentShiftHandover)) {
                        $startTime = $storeAgentShiftHandover->end_time;
                    } else {
                        $startTime = $form->input('start_time');
                    }

                    // 3. 时间验证（在事务外）
                    $start = Carbon::parse($startTime);
                    $end = Carbon::parse($endTime);
                    $now = Carbon::now();

                    // 验证结束时间不能超过当前时间
                    if ($end->gt($now)) {
                        return message_error(admin_trans('shift_handover.error.end_time_future'));
                    }

                    // 验证第一次交班时开始时间不能是未来
                    if (empty($storeAgentShiftHandover) && $start->gt($now)) {
                        return message_error(admin_trans('shift_handover.error.start_time_future'));
                    }

                    // 验证时间顺序（修复并发导致时间倒置的问题）
                    if ($start->gte($end)) {
                        return message_error(admin_trans('shift_handover.error.start_gte_end'));
                    }

                    // 验证时间跨度不超过30天
                    $diffInDays = $start->diffInDays($end);
                    if ($diffInDays > 30) {
                        return message_error(admin_trans('shift_handover.error.time_range_too_long'));
                    }

                    // 4. 检查是否存在重复的交班记录（在事务外）
                    $exists = StoreAgentShiftHandoverRecord::query()
                        ->where('bind_admin_user_id', $admin->id)
                        ->where(function($query) use ($startTime, $endTime) {
                            // 检查时间范围是否有重叠
                            $query->where(function($q) use ($startTime, $endTime) {
                                // 新记录的开始时间在已有记录的时间范围内
                                $q->where('start_time', '<=', $startTime)
                                  ->where('end_time', '>', $startTime);
                            })->orWhere(function($q) use ($startTime, $endTime) {
                                // 新记录的结束时间在已有记录的时间范围内
                                $q->where('start_time', '<', $endTime)
                                  ->where('end_time', '>=', $endTime);
                            })->orWhere(function($q) use ($startTime, $endTime) {
                                // 新记录完全包含已有记录
                                $q->where('start_time', '>=', $startTime)
                                  ->where('end_time', '<=', $endTime);
                            });
                        })
                        ->exists();

                    if ($exists) {
                        return message_error(admin_trans('shift_handover.error.duplicate_record'));
                    }

                    // 5. 统计数据（在事务外执行，避免长时间持锁）
                    $result = PlayerDeliveryRecord::query()
                        ->join('player', 'player_delivery_record.player_id', '=', 'player.id')
                        ->where('player.department_id', $admin->department_id)
                        ->where('player.store_admin_id', $admin->id)
                        ->where('player.is_promoter', 0)
                        ->whereIn('player_delivery_record.type', [
                            PlayerDeliveryRecord::TYPE_MACHINE,
                            PlayerDeliveryRecord::TYPE_LOTTERY,
                            PlayerDeliveryRecord::TYPE_RECHARGE,            // 开分
                            PlayerDeliveryRecord::TYPE_WITHDRAWAL,          // 洗分
                            PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD, // 后台加点
                            PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT, // 后台扣点
                        ])
                        ->where('player_delivery_record.created_at', '>', $startTime)  // 修复边界问题：用 > 而不是 >=
                        ->where('player_delivery_record.created_at', '<=', $endTime)
                        ->selectRaw("
                            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MACHINE . "
                                THEN player_delivery_record.amount ELSE 0 END) AS machine_put_point,
                            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_LOTTERY . "
                                THEN player_delivery_record.amount ELSE 0 END) AS lottery_amount,
                            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_RECHARGE . "
                                THEN player_delivery_record.amount ELSE 0 END) AS recharge_amount,
                            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . "
                                THEN player_delivery_record.amount ELSE 0 END) AS withdrawal_amount,
                            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD . "
                                THEN player_delivery_record.amount ELSE 0 END) AS modified_add_amount,
                            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT . "
                                THEN player_delivery_record.amount ELSE 0 END) AS modified_deduct_amount
                        ")
                        ->first();

                    // 6. 安全处理查询结果（防止null错误）
                    $playerDeliveryRecord = $result ? $result->toArray() : [
                        'machine_put_point' => 0,
                        'lottery_amount' => 0,
                        'recharge_amount' => 0,
                        'withdrawal_amount' => 0,
                        'modified_add_amount' => 0,
                        'modified_deduct_amount' => 0,
                    ];

                    // 7. 获取货币配置并验证（在事务外）
                    // 验证管理员关联数据
                    if (!$admin->department) {
                        Log::error('交班失败：管理员未关联部门', [
                            'user_id' => $admin->id,
                            'department_id' => $admin->department_id
                        ]);
                        return message_error('交班失败：管理员未关联部门');
                    }

                    if (!$admin->department->channel) {
                        Log::error('交班失败：部门未关联渠道', [
                            'user_id' => $admin->id,
                            'department_id' => $admin->department_id
                        ]);
                        return message_error('交班失败：部门未关联渠道');
                    }

                    $currencyCode = $admin->department->channel->currency;
                    if (!$currencyCode) {
                        Log::error('交班失败：渠道未配置货币', [
                            'user_id' => $admin->id,
                            'department_id' => $admin->department_id,
                            'channel_id' => $admin->department->channel->id
                        ]);
                        return message_error('交班失败：渠道未配置货币');
                    }

                    /** @var Currency $currency */
                    $currency = Currency::query()
                        ->where('identifying', $currencyCode)
                        ->first();

                    if (!$currency) {
                        Log::error('交班失败：货币配置不存在', [
                            'currency_code' => $currencyCode,
                            'department_id' => $admin->department_id,
                            'user_id' => $admin->id
                        ]);
                        return message_error('交班失败：货币配置不存在(' . $currencyCode . ')');
                    }

                    // 8. 开启事务，快速完成写入操作
                    DB::beginTransaction();
                    $transactionStarted = true;

                    // 9. 再次加锁查询最后一条记录（防止并发）
                    $storeAgentShiftHandoverLocked = StoreAgentShiftHandoverRecord::query()
                        ->where('bind_admin_user_id', $admin->id)
                        ->orderBy('id', 'desc')
                        ->lockForUpdate()
                        ->first();

                    // 验证开始时间未被其他交班占用
                    if ($storeAgentShiftHandoverLocked && $storeAgentShiftHandoverLocked->end_time != $startTime && !$form->input('start_time')) {
                        DB::rollBack();
                        return message_error(admin_trans('shift_handover.error.concurrent_shift'));
                    }

                    // 10. 创建交班记录
                    $storeAgentShiftHandoverRecord = new StoreAgentShiftHandoverRecord();
                    $storeAgentShiftHandoverRecord->department_id = $admin->department_id;
                    $storeAgentShiftHandoverRecord->machine_amount =
                        ($playerDeliveryRecord['machine_put_point'] ?? 0) * $currency->ratio;
                    $storeAgentShiftHandoverRecord->machine_point =
                        $playerDeliveryRecord['machine_put_point'] ?? 0;

                    // 计算总收入（开分 + 后台加点）
                    $storeAgentShiftHandoverRecord->total_in = bcadd(
                        $playerDeliveryRecord['recharge_amount'] ?? 0,
                        $playerDeliveryRecord['modified_add_amount'] ?? 0,
                        2
                    );

                    // 计算总支出（洗分 + 后台扣点）
                    $storeAgentShiftHandoverRecord->total_out = bcadd(
                        $playerDeliveryRecord['withdrawal_amount'] ?? 0,
                        $playerDeliveryRecord['modified_deduct_amount'] ?? 0,
                        2
                    );

                    $storeAgentShiftHandoverRecord->lottery_amount =
                        $playerDeliveryRecord['lottery_amount'] ?? 0;
                    $storeAgentShiftHandoverRecord->start_time = $startTime;
                    $storeAgentShiftHandoverRecord->end_time = $endTime;
                    $storeAgentShiftHandoverRecord->user_id = $admin->id;
                    $storeAgentShiftHandoverRecord->user_name = $admin->username;
                    $storeAgentShiftHandoverRecord->bind_admin_user_id = $admin->id;
                    $storeAgentShiftHandoverRecord->is_auto_shift = 0;

                    // 计算利润（投钞 + 总收入 - 总支出 - 彩金）
                    $storeAgentShiftHandoverRecord->total_profit_amount = bcsub(
                        bcsub(
                            bcadd($storeAgentShiftHandoverRecord->machine_point,
                                  $storeAgentShiftHandoverRecord->total_in, 2),
                            $storeAgentShiftHandoverRecord->total_out,
                            2
                        ),
                        $storeAgentShiftHandoverRecord->lottery_amount,
                        2
                    );
                    $storeAgentShiftHandoverRecord->save();

                    // 9. 创建执行日志（与自动交班保持一致）
                    $manualLog = new StoreAutoShiftLog();
                    $manualLog->config_id = 0; // 手动交班没有配置ID
                    $manualLog->department_id = $admin->department_id;
                    $manualLog->bind_admin_user_id = $admin->id;
                    $manualLog->shift_record_id = $storeAgentShiftHandoverRecord->id;
                    $manualLog->start_time = $startTime;
                    $manualLog->end_time = $endTime;
                    $manualLog->execute_time = Carbon::now();
                    $manualLog->status = StoreAutoShiftLog::STATUS_SUCCESS;
                    $manualLog->execution_duration = 0; // 手动交班无执行时长
                    $manualLog->machine_amount = $storeAgentShiftHandoverRecord->machine_amount;
                    $manualLog->machine_point = $storeAgentShiftHandoverRecord->machine_point;
                    $manualLog->total_in = $storeAgentShiftHandoverRecord->total_in;
                    $manualLog->total_out = $storeAgentShiftHandoverRecord->total_out;
                    $manualLog->lottery_amount = $storeAgentShiftHandoverRecord->lottery_amount;
                    $manualLog->total_profit = $storeAgentShiftHandoverRecord->total_profit_amount;
                    $manualLog->save();

                    // 10. 关联日志ID
                    $storeAgentShiftHandoverRecord->auto_shift_log_id = $manualLog->id;
                    $storeAgentShiftHandoverRecord->save();

                    // 11. 更新自动交班配置（实现无缝切换）
                    /** @var StoreAutoShiftConfig|null $autoShiftConfig */
                    $autoShiftConfig = StoreAutoShiftConfig::query()
                        ->where('department_id', $admin->department_id)
                        ->where('bind_admin_user_id', $admin->id)
                        ->first();

                    if ($autoShiftConfig) {
                        // 更新最后交班时间，确保下次自动交班从这里开始
                        $autoShiftConfig->last_shift_time = $endTime;

                        // 重新计算下次交班时间
                        $service = new \app\service\store\AutoShiftService();
                        $autoShiftConfig->next_shift_time = $service->calculateNextShiftTime($autoShiftConfig);
                        $autoShiftConfig->save();
                    }

                    // 12. 记录日志
                    Log::info('店家手动交班成功', [
                        'record_id' => $storeAgentShiftHandoverRecord->id,
                        'log_id' => $manualLog->id,
                        'bind_admin_user_id' => $admin->id,
                        'user_id' => $admin->id,
                        'user_name' => $admin->username,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'machine_point' => $storeAgentShiftHandoverRecord->machine_point,
                        'total_in' => $storeAgentShiftHandoverRecord->total_in,
                        'total_out' => $storeAgentShiftHandoverRecord->total_out,
                        'lottery_amount' => $storeAgentShiftHandoverRecord->lottery_amount,
                        'total_profit_amount' => $storeAgentShiftHandoverRecord->total_profit_amount,
                        'is_auto_shift' => 0,
                        'auto_shift_updated' => $autoShiftConfig ? true : false,
                        'next_shift_time' => $autoShiftConfig ? $autoShiftConfig->next_shift_time : null,
                        // 详细分类数据
                        'detail' => [
                            'recharge' => $playerDeliveryRecord['recharge_amount'] ?? 0,
                            'withdrawal' => $playerDeliveryRecord['withdrawal_amount'] ?? 0,
                            'modified_add' => $playerDeliveryRecord['modified_add_amount'] ?? 0,
                            'modified_deduct' => $playerDeliveryRecord['modified_deduct_amount'] ?? 0,
                        ]
                    ]);

                    DB::commit();
                    return message_success(admin_trans('shift_handover.error.shift_success'));

                } catch (\Exception $e) {
                    if ($transactionStarted) {
                        DB::rollBack();
                    }
                    Log::error('手动交班失败', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'user_id' => Admin::id(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'start_time' => $startTime ?? null,
                        'end_time' => $endTime ?? null
                    ]);
                    return message_error('交班失败：' . $e->getMessage());
                }
            });
        });
    }

    /**
     * 应用日期筛选条件到查询
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $data_type
     * @param string $field
     */
    private function applyDateWhere($query, $data_type, $field = 'created_at')
    {
        switch ($data_type) {
            case 'today': // 今天
                $query->where($field, '>=', Carbon::today()->startOfDay());
                break;
            case 'yesterday': // 昨天
                $query->where($field, '>=', Carbon::yesterday()->startOfDay())
                      ->where($field, '<=', Carbon::yesterday()->endOfDay());
                break;
            case 'week': // 本周
                $query->where($field, '>=', Carbon::today()->startOfWeek()->startOfDay());
                break;
            case 'last_week': // 上周
                $query->where($field, '>=', Carbon::today()->subWeek()->startOfWeek()->startOfDay())
                      ->where($field, '<=', Carbon::today()->subWeek()->endOfWeek()->endOfDay());
                break;
            case 'month': // 本月
                $query->where($field, '>=', Carbon::today()->firstOfMonth()->startOfDay());
                break;
            case 'last_month': // 上月
                $startDate = Carbon::create(
                    Carbon::now()->subMonthNoOverflow()->year,
                    Carbon::now()->subMonthNoOverflow()->month,
                    1,
                    0, 0, 0
                );
                $endDate = Carbon::create(
                    Carbon::now()->subMonthNoOverflow()->year,
                    Carbon::now()->subMonthNoOverflow()->month,
                    Carbon::now()->subMonthNoOverflow()->daysInMonth,
                    23, 59, 59
                );
                $query->where($field, '>=', $startDate)
                      ->where($field, '<=', $endDate);
                break;
        }
    }
}
