<?php

namespace addons\webman\controller;

use addons\webman\model\ActivityContent;
use addons\webman\model\PlayerMoneyEditLog;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\FilterColumn;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\grid\ToolTip;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\support\Request;
use Illuminate\Support\Str;
use support\Db;

/**
 * 玩家钱包操作日志
 */
class PlayerMoneyEditLogController
{
    protected $model;
    
    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_money_edit_log_model');
        
    }
    
    /**
     * 玩家钱包操作日志
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $grid->model()->with(['player', 'user'])->where('user_id', '!=', 0)->orderBy('id', 'desc');
            $requestFilter = Request::input('ex_admin_filter', []);
            $quickSearch = Request::input('quickSearch', []);
            if (!empty($requestFilter)) {
                if (!empty($requestFilter['created_at_start'])) {
                    $grid->model()->where('created_at', '>=', $requestFilter['created_at_start']);
                }
                if (!empty($requestFilter['created_at_end'])) {
                    $grid->model()->where('created_at', '<=', $requestFilter['created_at_end']);
                }
            }
            
            if (isset($requestFilter['date_type'])) {
                $grid->model()->where(getDateWhere($requestFilter['date_type'], 'created_at'));
            }
            
            if (isset($requestFilter['activity'])) {
                $grid->model()->where('activity', $requestFilter['activity']);
            }
            if (isset($requestFilter['search_type'])) {
                $grid->model()->whereHas('player', function ($query) use ($requestFilter) {
                    $query->where('is_test', $requestFilter['search_type']);
                });
            }
            
            $query = clone $grid->model();
            $where = [];
            if (!empty($requestFilter['remark'])) {
                $where[] = ['remark', 'like', "%{$requestFilter['remark']}%"];
            }
            if (!empty($requestFilter['user_id'])) {
                $field = 'user_id';
                $ids = $requestFilter['user_id'];
                $where[] = [
                    function ($query) use ($field, $ids) {
                        $query->whereIn($field, $ids);
                    }
                ];
            }
            if (isset($requestFilter['action'])) {
                $where[] = ['action', '=', $requestFilter['action']];
            }
            $totalData = $query->selectRaw('
                sum(IF(action = 0, money, 0)) as total_recharge,
                sum(IF(action = 1, money, 0)) as total_vip_recharge,
                sum(IF(action = 2, money, 0)) as total_testing_machine,
                sum(IF(action = 3, money, 0)) as total_other,
                sum(IF(action = 4, money, 0)) as total_activity_give,
                sum(IF(action = 5, money, 0)) as total_triple_seven_give,
                sum(IF(action = 6, money, 0)) as total_composite_machine_give,
                sum(IF(action = 7, money, 0)) as total_real_person_give,
                sum(IF(action = 8, money, 0)) as total_electronic_give,
                sum(IF(action = 9, money, 0)) as total_admin_deduct,
                sum(IF(action = 11, money, 0)) as total_activity,
                sum(IF(action = 12, money, 0)) as total_coin_deduct,
                sum(IF(action = 13, money, 0)) as total_coin_increase,
                sum(IF(action = 15, money, 0)) as total_coin_recharge,
                sum(IF(action = 16, money, 0)) as total_coin_withdrawal
                ')
                ->where($where)
                ->where(function ($query) use ($requestFilter, $quickSearch) {
                    if (!empty($requestFilter['player']['name'])) {
                        $query->whereHas('player', function ($query) use ($requestFilter) {
                            $query->where('name', 'like', "%{$requestFilter['player']['name']}%");
                        });
                    }
                    if (!empty($requestFilter['player']['uuid'])) {
                        $query->whereHas('player', function ($query) use ($requestFilter) {
                            $query->where('uuid', '=', $requestFilter['player']['uuid']);
                        });
                    }
                    if (!empty($requestFilter['department_id'])) {
                        $query->where('department_id', $requestFilter['department_id']);
                    }
                    if (!empty($quickSearch)) {
                        $query->whereHas('player', function ($query) use ($quickSearch) {
                            $query->where([
                                ['name', 'like', '%' . $quickSearch . '%', 'or'],
                                ['uuid', 'like', '%' . $quickSearch . '%', 'or'],
                                ['phone', 'like', '%' . $quickSearch . '%', 'or'],
                            ]);
                        })->orWhere('id', $quickSearch)
                            ->orWhere(function ($query) use ($quickSearch) {
                                $query->whereHas('user', function ($query) use ($quickSearch) {
                                    $query->where([
                                        ['username', 'like', '%' . $quickSearch . '%', 'or'],
                                        ['nickname', 'like', '%' . $quickSearch . '%', 'or'],
                                    ]);
                                });
                            });
                    }
                })
                ->first();
            
            $grid->model()
                ->addSelect([
                    '*', // 保留默认字段
                    DB::raw('CASE WHEN type = 1 THEN money ELSE -money END AS money')
                ]);
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->title(admin_trans('player_money_edit_log.total_data.total_activity_give'))
                            ->value(!empty($totalData['total_activity_give']) ? floatval($totalData['total_activity_give']) : 0)
                            ->valueStyle([
                                'font-size' => '15px',
                                'text-align' => 'center'
                            ])->style([
                                'font-size' => '10px',
                                'text-align' => 'center'
                            ])),
                    ])->bodyStyle(['display' => 'flex', 'align-items' => 'center', 'height' => '72px'])->hoverable()
                        ->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 3);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->title(admin_trans('player_money_edit_log.action.' . PlayerMoneyEditLog::COIN_RECHARGE))
                            ->value(!empty($totalData['total_coin_recharge']) ? floatval($totalData['total_coin_recharge']) : 0)
                            ->valueStyle([
                                'font-size' => '15px',
                                'text-align' => 'center'
                            ])->style([
                                'font-size' => '10px',
                                'text-align' => 'center'
                            ])),
                    ])->bodyStyle(['display' => 'flex', 'align-items' => 'center', 'height' => '72px'])->hoverable()
                        ->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 3);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->title(admin_trans('player_money_edit_log.action.' . PlayerMoneyEditLog::COIN_WITHDRAWAL))
                            ->value(!empty($totalData['total_coin_withdrawal']) ? floatval($totalData['total_coin_withdrawal']) : 0)
                            ->valueStyle([
                                'font-size' => '15px',
                                'text-align' => 'center'
                            ])->style([
                                'font-size' => '10px',
                                'text-align' => 'center'
                            ])),
                    ])->bodyStyle(['display' => 'flex', 'align-items' => 'center', 'height' => '72px'])->hoverable()
                        ->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 3);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->title(admin_trans('player_money_edit_log.total_data.total_triple_seven_give'))
                            ->value(!empty($totalData['total_triple_seven_give']) ? floatval($totalData['total_triple_seven_give']) : 0)
                            ->valueStyle([
                                'font-size' => '15px',
                                'text-align' => 'center'
                            ])->style([
                                'font-size' => '10px',
                                'text-align' => 'center'
                            ])),
                    ])->bodyStyle(['display' => 'flex', 'align-items' => 'center', 'height' => '72px'])->hoverable()
                        ->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 3);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->title(admin_trans('player_money_edit_log.total_data.total_composite_machine_give'))
                            ->value(!empty($totalData['total_composite_machine_give']) ? floatval($totalData['total_composite_machine_give']) : 0)
                            ->valueStyle([
                                'font-size' => '15px',
                                'text-align' => 'center'
                            ])->style([
                                'font-size' => '10px',
                                'text-align' => 'center'
                            ])),
                    ])->bodyStyle(['display' => 'flex', 'align-items' => 'center', 'height' => '72px'])->hoverable()
                        ->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 3);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->title(admin_trans('player_money_edit_log.total_data.total_real_person_give'))
                            ->value(!empty($totalData['total_real_person_give']) ? floatval($totalData['total_real_person_give']) : 0)
                            ->valueStyle([
                                'font-size' => '15px',
                                'text-align' => 'center'
                            ])->style([
                                'font-size' => '10px',
                                'text-align' => 'center'
                            ])),
                    ])->bodyStyle(['display' => 'flex', 'align-items' => 'center', 'height' => '72px'])->hoverable()
                        ->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 3);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->title(admin_trans('player_money_edit_log.total_data.total_electronic_give'))
                            ->value(!empty($totalData['total_electronic_give']) ? floatval($totalData['total_electronic_give']) : 0)
                            ->valueStyle([
                                'font-size' => '15px',
                                'text-align' => 'center'
                            ])->style([
                                'font-size' => '10px',
                                'text-align' => 'center'
                            ])),
                    ])->bodyStyle(['display' => 'flex', 'align-items' => 'center', 'height' => '72px'])->hoverable()
                        ->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 3);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->title(admin_trans('player_money_edit_log.total_data.total_other'))
                            ->value(!empty($totalData['total_other']) ? floatval($totalData['total_other']) : 0)
                            ->valueStyle([
                                'font-size' => '15px',
                                'text-align' => 'center'
                            ])->style([
                                'font-size' => '10px',
                                'text-align' => 'center'
                            ])),
                    ])->bodyStyle(['display' => 'flex', 'align-items' => 'center', 'height' => '72px'])->hoverable()
                        ->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 3);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->title(admin_trans('player_money_edit_log.total_data.total_admin_deduct'))
                            ->value(!empty($totalData['total_admin_deduct']) ? floatval($totalData['total_admin_deduct']) : 0)
                            ->valueStyle([
                                'font-size' => '15px',
                                'text-align' => 'center',
                                'color' => 'magenta'
                            ])->style([
                                'font-size' => '10px',
                                'text-align' => 'center'
                            ])),
                    ])->bodyStyle(['display' => 'flex', 'align-items' => 'center', 'height' => '72px'])->hoverable()
                        ->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 3)->style(['margin-top' => '5px']);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->title(admin_trans('player_money_edit_log.total_data.total_activity'))
                            ->value(!empty($totalData['total_activity']) ? floatval($totalData['total_activity']) : 0)
                            ->valueStyle([
                                'font-size' => '15px',
                                'text-align' => 'center',
                                'color' => 'magenta'
                            ])->style([
                                'font-size' => '10px',
                                'text-align' => 'center'
                            ])),
                    ])->bodyStyle(['display' => 'flex', 'align-items' => 'center', 'height' => '72px'])->hoverable()
                        ->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 3)->style(['margin-top' => '5px']);
            })->style(['background' => '#fff']);
            $grid->header($layout);
            
            $grid->title(admin_trans('player_money_edit_log.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('player_money_edit_log.fields.id'))->align('center');
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))->display(function (
                $val,
                PlayerMoneyEditLog $data
            ) {
                return Html::create()->content([
                    Html::div()->content($val)
                ]);
            })->align('center');
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function (
                $val,
                PlayerMoneyEditLog $data
            ) {
                return Html::create()->content([
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                ]);
            })->fixed(true)->align('center');
            $grid->column('player.name', admin_trans('player.fields.name'))->display(function (
                $val,
                PlayerMoneyEditLog $data
            ) {
                return $data->player->name;
            })->align('center');
            $grid->column('player.phone', admin_trans('player.fields.phone'))->display(function (
                $val,
                PlayerMoneyEditLog $data
            ) {
                $image = $data->player->avatar ? Avatar::create()
                    ->src(is_numeric($data->player->avatar) ? config('def_avatar.' . $data->player->avatar) : $data->player->avatar) : Avatar::create()
                    ->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($data->player->phone),
                ]);
            })->align('center')->filter(
                FilterColumn::like()->text('player.phone')
            );
            $grid->column('player_money_edit_log.player.channel.name',
                admin_trans('player.fields.department_id'))->display(function (
                $val,
                PlayerMoneyEditLog $data
            ) {
                return $data->player->channel->name;
            })->width('150px')->align('center');
            $grid->column('action', admin_trans('player_money_edit_log.fields.action'))->display(function (
                $val,
                PlayerMoneyEditLog $data
            ) {
                switch ($val) {
                    case PlayerMoneyEditLog::RECHARGE:
                    case PlayerMoneyEditLog::VIP_RECHARGE:
                    case PlayerMoneyEditLog::COIN_INCREASE:
                    case PlayerMoneyEditLog::COIN_RECHARGE:
                        return Tag::create(admin_trans('player_money_edit_log.action.' . $val))->color('green');
                    case PlayerMoneyEditLog::TESTING_MACHINE:
                    case PlayerMoneyEditLog::OTHER:
                    case PlayerMoneyEditLog::SPECIAL:
                        return Tag::create(admin_trans('player_money_edit_log.action.' . $val))->color('purple');
                    case PlayerMoneyEditLog::ACTIVITY_GIVE:
                    case PlayerMoneyEditLog::TRIPLE_SEVEN_GIVE:
                        return Tag::create(admin_trans('player_money_edit_log.action.' . $val))->color('cyan');
                    case PlayerMoneyEditLog::COMPOSITE_MACHINE_GIVE:
                    case PlayerMoneyEditLog::REAL_PERSON_GIVE:
                    case PlayerMoneyEditLog::COIN_WITHDRAWAL:
                        return Tag::create(admin_trans('player_money_edit_log.action.' . $val))->color('red');
                    case PlayerMoneyEditLog::ELECTRONIC_GIVE:
                    case PlayerMoneyEditLog::ADMIN_DEDUCT:
                    case PlayerMoneyEditLog::ADMIN_DEDUCT_OTHER:
                    case PlayerMoneyEditLog::COIN_DEDUCT:
                        return Tag::create(admin_trans('player_money_edit_log.action.' . $val))->color('processing');
                    case PlayerMoneyEditLog::ACTIVITY:
                        return Tag::create(admin_trans('player.wallet.wallet_type.' . $val) . '(' . $this->getActivity($data->activity) . ')')
                            ->color('processing');
                    default:
                        return '';
                }
            })->align('center');
            $grid->column('money', admin_trans('player_money_edit_log.fields.money'))->display(function (
                $val,
                PlayerMoneyEditLog $data
            ) {
                return Html::create()->content([
                    $data->type == PlayerMoneyEditLog::TYPE_INCREASE ? '+' . $val : $val,
                ])->style(['color' => ($data->type == PlayerMoneyEditLog::TYPE_INCREASE ? 'green' : '#cd201f')]);
            })->sortable()->align('center');
            $grid->column('after_money', admin_trans('player_money_edit_log.fields.after_money'))->display(function (
                $val
            ) {
                return Html::create()->content([
                    $val,
                ])->style(['color' => '#cd201f']);
            })->sortable()->align('center');
            $grid->column('origin_money', admin_trans('player_money_edit_log.fields.origin_money'))->display(function (
                $val
            ) {
                return Html::create()->content([
                    $val,
                ])->style(['color' => 'green']);
            })->sortable()->align('center');
            $grid->column('user.username', admin_trans('admin.admin_user'))->display(function (
                $val,
                PlayerMoneyEditLog $data
            ) {
                $image = Image::create()
                    ->width(30)
                    ->height(30)
                    ->style(['border-radius' => '50%', 'objectFit' => 'cover'])
                    ->src($data->user->avatar);
                return Html::create()->content([
                    $image,
                    Html::div()->content($data->user->nickname)
                ]);
            })->align('center');
            $grid->column('remark', admin_trans('player_money_edit_log.fields.remark'))->display(function ($value) {
                return ToolTip::create(Str::of($value)->limit(30, ' (...)'))->title($value);
            })->width('150px')->align('center');
            $grid->column('created_at', admin_trans('player_money_edit_log.fields.create_at'))->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
            $grid->expandFilter();
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player.name')->placeholder(admin_trans('player.fields.name'));
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->like()->text('remark')->placeholder(admin_trans('player_money_edit_log.fields.remark'));
                $filter->in()->select('user_id')
                    ->showSearch()
                    ->style(['min-width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('admin.admin_user'))
                    ->options(getAdminUserListOptions())
                    ->multiple();
                $filter->select('search_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.type'))
                    ->options([
                        0 => admin_trans('player.player'),
                        1 => admin_trans('player.fields.is_test'),
                    ]);
                $filter->eq()->select('action')
                    ->showSearch()
                    ->style(['min-width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player_money_edit_log.fields.action'))
                    ->options([
                        PlayerMoneyEditLog::RECHARGE => admin_trans('player.wallet.wallet_type.' . PlayerMoneyEditLog::RECHARGE),
                        PlayerMoneyEditLog::VIP_RECHARGE => admin_trans('player.wallet.wallet_type.' . PlayerMoneyEditLog::VIP_RECHARGE),
                        PlayerMoneyEditLog::TESTING_MACHINE => admin_trans('player.wallet.wallet_type.' . PlayerMoneyEditLog::TESTING_MACHINE),
                        PlayerMoneyEditLog::OTHER => admin_trans('player.wallet.wallet_type.' . PlayerMoneyEditLog::OTHER),
                        PlayerMoneyEditLog::ACTIVITY_GIVE => admin_trans('player.wallet.wallet_type.' . PlayerMoneyEditLog::ACTIVITY_GIVE),
                        PlayerMoneyEditLog::TRIPLE_SEVEN_GIVE => admin_trans('player.wallet.wallet_type.' . PlayerMoneyEditLog::TRIPLE_SEVEN_GIVE),
                        PlayerMoneyEditLog::COMPOSITE_MACHINE_GIVE => admin_trans('player.wallet.wallet_type.' . PlayerMoneyEditLog::COMPOSITE_MACHINE_GIVE),
                        PlayerMoneyEditLog::REAL_PERSON_GIVE => admin_trans('player.wallet.wallet_type.' . PlayerMoneyEditLog::REAL_PERSON_GIVE),
                        PlayerMoneyEditLog::ELECTRONIC_GIVE => admin_trans('player.wallet.wallet_type.' . PlayerMoneyEditLog::ELECTRONIC_GIVE),
                        PlayerMoneyEditLog::ADMIN_DEDUCT => admin_trans('player.wallet.wallet_type.' . PlayerMoneyEditLog::ADMIN_DEDUCT),
                        PlayerMoneyEditLog::ADMIN_DEDUCT_OTHER => admin_trans('player.wallet.wallet_type.' . PlayerMoneyEditLog::ADMIN_DEDUCT_OTHER),
                        PlayerMoneyEditLog::ACTIVITY => admin_trans('player.wallet.wallet_type.' . PlayerMoneyEditLog::ACTIVITY),
                        PlayerMoneyEditLog::SPECIAL => admin_trans('player.wallet.wallet_type.' . PlayerMoneyEditLog::SPECIAL),
                        PlayerMoneyEditLog::COIN_RECHARGE => admin_trans('player.wallet.wallet_type.' . PlayerMoneyEditLog::COIN_RECHARGE),
                        PlayerMoneyEditLog::COIN_WITHDRAWAL => admin_trans('player.wallet.wallet_type.' . PlayerMoneyEditLog::COIN_WITHDRAWAL),
                    ]);
                
                $list = ActivityContent::query()->where('lang', Str::replace('_', '-', locale()))->pluck('name',
                    'id')
                    ->toArray();
                $filter->eq()->select('activity')
                    ->showSearch()
                    ->style(['min-width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('activity.title'))
                    ->options($list);
                
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
                $filter->eq()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('channel.fields.name'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('player_money_edit_log.created_at_start'),
                    admin_trans('player_money_edit_log.created_at_end')
                ]);
            });
        });
    }
    
    private function getActivity($id)
    {
        return ActivityContent::query()->where('id', $id)->value('name');
    }
}
