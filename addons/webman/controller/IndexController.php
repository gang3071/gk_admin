<?php

namespace addons\webman\controller;

use addons\webman\model\GameType;
use addons\webman\model\Machine;
use addons\webman\model\Player;
use addons\webman\model\PlayerGameLog;
use addons\webman\model\PlayerLoginRecord;
use addons\webman\model\PlayerPresentRecord;
use addons\webman\model\PlayerRechargeRecord;
use addons\webman\model\PlayerWithdrawRecord;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\echart\BarChart;
use ExAdmin\ui\component\echart\LineChart;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\layout\Divider;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\component\navigation\dropdown\Dropdown;
use ExAdmin\ui\response\Msg;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use support\Db;
use support\Response;

/**
 * 数据中心
 */
class IndexController
{
    /**
     * 数据中心
     * @auth true
     */
    public function index($data_type = null): Layout
    {
        $rechargeData = $this->rechargeData($data_type);
        $withdrawData = $this->withdrawData($data_type);
        $playerData = $this->playerData($data_type);
        $machineData = $this->machineData();
        $loginData = $this->loginData();
        $gameLogData = $this->gameLogData($data_type);
        $layout = Layout::create();
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
        $layout->row(function (Row $row) use (
            $rechargeData,
            $withdrawData,
            $playerData,
            $machineData,
            $loginData,
            $gameLogData,
            $dropdown
        ) {
            $row->gutter([10, 10]);
            $row->column(
                Card::create([
                    Row::create()->column($dropdown, 3),
                ])->bodyStyle([
                    'padding' => '13px',
                    'display' => 'block'
                ])
            );
            $row->column(
                Card::create([
                    Row::create()->column(Icon::create('fas fa-money-bill')->style([
                        'fontSize' => '45px',
                        'color' => '#409eff',
                        'marginRight' => '20px'
                    ]), 3),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.recharge_all'))->value(floatval($rechargeData['all']))
                        ->valueStyle([
                            'font-size' => '18px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 4),
                    Divider::create()->type('vertical')->style(['height' => '4.9em']),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.recharge_self'))->value(floatval($rechargeData['self']))
                        ->valueStyle([
                            'font-size' => '18px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 4),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.recharge_third'))->value(floatval($rechargeData['third']))
                        ->valueStyle([
                            'font-size' => '18px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 4),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.recharge_artificial'))->value(floatval($rechargeData['artificial']))
                        ->valueStyle([
                            'font-size' => '18px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 4),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.recharge_business'))->value(floatval($rechargeData['business']))
                        ->valueStyle([
                            'font-size' => '18px',
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
                ])->hoverable()->extra(Icon::create('MoreOutlined')->redirect('ex-admin/addons-webman-controller-RechargeRecordController/index'))->headStyle([
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
                            'font-size' => '18px',
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
                            'font-size' => '18px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 4),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.withdraw_third'))->value(floatval($withdrawData['third']))
                        ->valueStyle([
                            'font-size' => '18px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 4),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.withdraw_artificial'))->value(floatval($withdrawData['artificial']))
                        ->valueStyle([
                            'font-size' => '18px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 4),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.withdraw_business'))->value(floatval($withdrawData['business']))
                        ->valueStyle([
                            'font-size' => '18px',
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
                ])->hoverable()->extra(Icon::create('MoreOutlined')->redirect('ex-admin/addons-webman-controller-WithdrawRecordController/index'))->headStyle([
                    'height' => '0px',
                    'border-bottom' => '0px',
                    'min-height' => '0px'
                ])
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
                            'font-size' => '18px',
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
                            'font-size' => '18px',
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
                ])->hoverable()->extra(Icon::create('MoreOutlined')->redirect('ex-admin/addons-webman-controller-PlayerController/index'))->headStyle([
                    'height' => '0px',
                    'border-bottom' => '0px',
                    'min-height' => '0px'
                ])
                , 6);
            $row->column(
                Card::create([
                    Row::create()->column(Icon::create('fas fa-television')->style([
                        'fontSize' => '45px',
                        'color' => '#e91e63',
                        'marginRight' => '20px'
                    ]), 6),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.online_machine'))->value($machineData['all'])
                        ->valueStyle([
                            'font-size' => '18px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 8),
                    Divider::create()->type('vertical')->style(['height' => '4.9em']),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.gaming_machine'))->value($machineData['gaming'])
                        ->valueStyle([
                            'font-size' => '18px',
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
                ])->hoverable()->extra(Icon::create('MoreOutlined')->redirect('ex-admin/addons-webman-controller-MachineController/index'))->headStyle([
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
                            'font-size' => '18px',
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
                            'font-size' => '18px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 8)
                ])->bodyStyle(['display' => 'flex', 'align-items' => 'center'])->hoverable()
                , 6);
            $style = [
                'color' => 'green !important',
                'font-size' => '18px',
                'font-weight' => '500',
            ];
            $allStyle = [
                'color' => 'green !important',
                'font-size' => '18px',
                'font-weight' => '500',
            ];
            if ($gameLogData['today'] > 0) {
                $style = [
                    'color' => 'red !important',
                    'font-size' => '18px',
                    'font-weight' => '500',
                ];
            }
            if ($gameLogData['all'] > 0) {
                $allStyle = [
                    'color' => 'red !important',
                    'font-size' => '18px',
                    'font-weight' => '500',
                ];
            }
            $row->column(
                Card::create([
                    Row::create()->column(Icon::create('fas fa-window-restore')->style([
                        'fontSize' => '45px',
                        'color' => '#4caf50',
                        'marginRight' => '20px'
                    ]), 6),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.today_machine_profit_loss'))
                        ->value(-floatval($gameLogData['today']))->valueStyle($style)->style([
                            'fontSize' => '18px',
                            'text-align' => 'center'
                        ]), 8),
                    Divider::create()->type('vertical')->style(['height' => '4.9em']),
                    Row::create()->column(Statistic::create()->title(admin_trans('data_center.all_machine_profit_loss'))
                        ->value(-floatval($gameLogData['all']))->valueStyle($allStyle)
                        ->style([
                            'fontSize' => '45px',
                            'text-align' => 'center'
                        ]), 8),
                ])->bodyStyle(['display' => 'flex', 'align-items' => 'center'])->hoverable()
                , 6);
            
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
            'all' => PlayerRechargeRecord::query()
                ->where('status',
                    PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)->whereIn('type', [
                    PlayerRechargeRecord::TYPE_THIRD,
                    PlayerRechargeRecord::TYPE_SELF,
                    PlayerRechargeRecord::TYPE_ARTIFICIAL,
                    PlayerRechargeRecord::TYPE_GB,
                ])->when($data_type, function (Builder $q, $value) {
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
                })->sum('point'),
            'self' => PlayerRechargeRecord::query()
                ->where('status',
                    PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)->where('type',
                    PlayerRechargeRecord::TYPE_SELF)->when($data_type, function (Builder $q, $value) {
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
                })->sum('point'),
            'third' => PlayerRechargeRecord::query()
                ->where('status',
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
                })->sum('point'),
            'artificial' => PlayerRechargeRecord::query()
                ->where('status',
                    PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)->where('type',
                    PlayerRechargeRecord::TYPE_ARTIFICIAL)->when($data_type, function (Builder $q, $value) {
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
                })->sum('point'),
            'business' => PlayerPresentRecord::query()
                ->where('type', PlayerPresentRecord::TYPE_OUT)->when($data_type,
                    function (Builder $q, $value) {
                        switch ($value) {
                            case 'today': // 今天
                                $q->where('created_at', '>=', Carbon::today()->startOfDay())->where('created_at', '<=',
                                    Carbon::today()->endOfDay());
                                break;
                            case 'yesterday': // 昨天
                                $q->where('created_at', '>=', Carbon::yesterday()->startOfDay())->where('created_at',
                                    '<=', Carbon::yesterday()->endOfDay());
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
            'all' => PlayerWithdrawRecord::query()
                ->where('status', PlayerWithdrawRecord::STATUS_SUCCESS)
                ->when($data_type, function (Builder $q, $value) {
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
                })
                ->sum('point'),
            'self' => PlayerWithdrawRecord::query()
                ->where('status', PlayerWithdrawRecord::STATUS_SUCCESS)
                ->where('type',
                    PlayerWithdrawRecord::TYPE_SELF)->when($data_type, function (Builder $q, $value) {
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
                })->sum('point'),
            'third' => PlayerWithdrawRecord::query()
                ->where('status', PlayerWithdrawRecord::STATUS_SUCCESS)
                ->whereIn('type', [PlayerWithdrawRecord::TYPE_THIRD, PlayerWithdrawRecord::TYPE_GB])
                ->when($data_type, function (Builder $q, $value) {
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
                })
                ->sum('point'),
            'artificial' => PlayerWithdrawRecord::query()
                ->where('status', PlayerWithdrawRecord::STATUS_SUCCESS)
                ->where('type', PlayerWithdrawRecord::TYPE_ARTIFICIAL)
                ->when($data_type, function (Builder $q, $value) {
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
                })
                ->sum('point'),
            'business' => PlayerPresentRecord::query()
                ->where('type', PlayerPresentRecord::TYPE_IN)
                ->when($data_type, function (Builder $q, $value) {
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
     * @param $data_type
     * @return array
     */
    public function playerData($data_type): array
    {
        return [
            'all' => Player::query()
                ->when($data_type, function (Builder $q, $value) {
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
                })
                ->count('*'),
            'today' => Player::query()->whereDate('created_at', date('Y-m-d'))->count(),
        ];
    }
    
    /**
     * 获取机台数据
     * @return array
     */
    public function machineData(): array
    {
        return [
            'all' => Machine::whereHas('machineCategory', function ($query) {
                $query->where('status', 1)->whereHas('gameType', function ($query) {
                    $query->where('status', 1);
                });
            })
                ->whereNull('deleted_at')
                ->whereIn('type', [GameType::TYPE_SLOT, GameType::TYPE_STEEL_BALL, GameType::TYPE_FISH])
                ->count(),
            'gaming' => Machine::whereHas('machineCategory', function ($query) {
                $query->where('status', 1)->whereHas('gameType', function ($query) {
                    $query->where('status', 1);
                });
            })
                ->whereNull('deleted_at')
                ->where('gaming_user_id', '!=', 0)
                ->whereIn('type', [GameType::TYPE_SLOT, GameType::TYPE_STEEL_BALL, GameType::TYPE_FISH])
                ->count(),
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
     * 获取机台盈亏数据
     * @param $data_type
     * @return array
     */
    public function gameLogData($data_type): array
    {
        return [
            'all' => PlayerGameLog::query()
                ->when($data_type, function (Builder $q, $value) {
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
                })
                ->sum('game_amount'),
            'today' => PlayerGameLog::where('created_at', '>=', Carbon::today()->startOfDay())->where('created_at',
                '<=',
                Carbon::today()->endOfDay())->sum('game_amount'),
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
     * 活動圖片上傳
     * @return Msg|Response
     */
    public function activityUpload()
    {
        $file = request()->file('file');
        if ($file && $file->isValid()) {
            if ($file->getSize() >= 1024 * 1024 * 5) {
                return message_error(trans('image_upload_size_fail', ['{size}' => '5M'], 'message'));
            }
            $extension = $file->getUploadExtension();
            if (!in_array($extension, ['png', 'jpg', 'jpeg'])) {
                return message_error(trans('image_upload_fail', [], 'message'));
            }
            $basePath = public_path() . '/storage/' . date('Ymd') . DIRECTORY_SEPARATOR;
            $baseUrl = env('APP_URL', 'http://127.0.0.1:8787') . '/storage/' . date('Ymd') . '/';
            $uniqueId = hash_file('md5', $file->getPathname());
            $saveFilename = $uniqueId . '.' . $file->getUploadExtension();
            $savePath = $basePath . $saveFilename;
            $file->move($savePath);
            
            return jsonSuccessResponse('success', [$baseUrl . $saveFilename]);
        } else {
            return message_error(trans('image_upload_fail', [], 'message'));
        }
    }
}