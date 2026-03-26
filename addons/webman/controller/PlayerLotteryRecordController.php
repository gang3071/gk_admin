<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\GameLottery;
use addons\webman\model\GameType;
use addons\webman\model\Lottery;
use addons\webman\model\Notice;
use addons\webman\model\PlayerLotteryRecord;
use app\service\LotteryServices;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\component\navigation\dropdown\Dropdown;
use ExAdmin\ui\response\Msg;
use ExAdmin\ui\support\Request;
use Illuminate\Support\Str;
use support\Db;
use Webman\Push\PushException;

/**
 * 彩金中奖记录
 */
class PlayerLotteryRecordController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_lottery_record_model');
    }

    /**
     * 彩金领取
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('player_lottery_record.title'));
            $grid->bordered(true);
            $grid->autoHeight();
            $requestFilter = Request::input('ex_admin_filter', []);
            if (!empty($requestFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $requestFilter['created_at_start']);
            }
            if (!empty($requestFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $requestFilter['created_at_end']);
            }
            if (!empty($requestFilter['amount'])) {
                $grid->model()->where('amount', $requestFilter['amount']);
            }
            if (!empty($requestFilter['lottery_name'])) {
                $grid->model()->where('lottery_name', 'like', '%' . $requestFilter['lottery_name'] . '%');
            }
            if (!empty($requestFilter['lottery_type'])) {
                $grid->model()->where('lottery_type', $requestFilter['lottery_type']);
            }
            if (!empty($requestFilter['machine_code'])) {
                $grid->model()->where('machine_code', 'like', '%' . $requestFilter['machine_code'] . '%');
            }
            if (!empty($requestFilter['machine_name'])) {
                $grid->model()->where('machine_name', 'like', '%' . $requestFilter['machine_name'] . '%');
            }
            if (!empty($requestFilter['player_phone'])) {
                $grid->model()->where('player_phone', 'like', '%' . $requestFilter['player_phone'] . '%');
            }
            if (!empty($requestFilter['status'])) {
                $grid->model()->where('status', $requestFilter['status']);
            }
            if (!empty($requestFilter['uuid'])) {
                $grid->model()->where('uuid', $requestFilter['uuid']);
            }
            if (!empty($requestFilter['search_type'])) {
                $grid->model()->where('is_test', $requestFilter['search_type']);
            }
            if (!empty($requestFilter['search_is_promoter'])) {
                $grid->model()->where('is_promoter', $requestFilter['search_is_promoter']);
            }
            if (!empty($requestFilter['cate_id'])) {
                $cate_id = $requestFilter['cate_id'];
                $grid->model()->whereHas('machine', function ($query) use ($cate_id) {
                    $query->whereIn('cate_id', $cate_id);
                });
            }
            if (isset($requestFilter['date_type'])) {
                $grid->model()->where(getDateWhere($requestFilter['date_type'], 'created_at'));
            }
            if (!empty($requestFilter['department_id'])) {
                $grid->model()->where('department_id', $requestFilter['department_id']);
            }

            // 排序
            $grid->model()->orderBy('created_at', 'desc');

            // 使用 Vue 组件展示统计数据（支持折叠和刷新）
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($requestFilter) {
                $row->gutter([10, 0]);
                $row->column(admin_view(plugin()->webman->getPath() . '/views/total_info.vue')->attrs([
                    'ex_admin_filter' => $requestFilter,
                    'type' => 'PlayerLotteryRecord',
                ]));
            })->style(['background' => '#fff']);

            $grid->header($layout);
            $grid->column('id', admin_trans('player_lottery_record.fields.id'))->align('center')->fixed(true);
            $grid->column('uuid', admin_trans('player_lottery_record.fields.uuid'))
                ->display(function ($val, PlayerLotteryRecord $data) {
                    return Html::create()->content([
                        Html::div()->content($val),
                        $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : ''
                    ]);
                })
                ->align('center')->copy()->fixed(true);
            $grid->column('player_phone', admin_trans('player_lottery_record.fields.player_phone'))->display(function (
                $val,
                PlayerLotteryRecord $data
            ) {
                $image = isset($data->player->avatar) && !empty($data->player->avatar) ? Avatar::create()->src($data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val)
                ]);
            })->align('center')->fixed(true);
            $grid->column('type', admin_trans('player.fields.type'))->display(function ($val, $data) {
                if ($data['is_test'] == 1) {
                    $tags[] = Tag::create(admin_trans('player.fields.is_test'))->color('red');
                } else {
                    $tags[] = Tag::create(admin_trans('player.player'))->color('green');
                }
                if ($data['is_coin'] == 1) {
                    $tags[] = Tag::create(admin_trans('player.coin_merchant'))->color('#3b5999');
                }
                if ($data['is_promoter'] == 1) {
                    $tags[] = Tag::create(admin_trans('player.promoter'))->color('purple');
                }
                return Html::create()->content($tags)->style(['display' => 'inline-flex', 'text-align' => 'center']);
            })->ellipsis(true)->width(200)->align('center');
            $grid->column('status', admin_trans('player_lottery_record.fields.status'))->display(function ($val) {
                $tag = '';
                switch ($val) {
                    case PlayerLotteryRecord::STATUS_UNREVIEWED:
                        $tag = Tag::create(admin_trans('player_lottery_record.status.' . $val))->color('#108ee9');
                        break;
                    case PlayerLotteryRecord::STATUS_REJECT:
                        $tag = Tag::create(admin_trans('player_lottery_record.status.' . $val))->color('#f50');
                        break;
                    case PlayerLotteryRecord::STATUS_PASS:
                        $tag = Tag::create(admin_trans('player_lottery_record.status.' . $val))->color('#87d068');
                        break;
                    case PlayerLotteryRecord::STATUS_COMPLETE:
                        $tag = Tag::create(admin_trans('player_lottery_record.status.' . $val))->color('#cd201f');
                        break;
                }
                return Html::create()->content([
                    $tag
                ]);
            })->align('center')->align('center');
            $grid->column('channel.name', admin_trans('player.fields.department_id'))->width('150px')->align('center');
            $grid->column('lottery_type', admin_trans('lottery.fields.lottery_type'))->display(function ($val) {
                return Html::create()->content([
                    Tag::create(admin_trans('lottery.lottery_type.' . $val))->color($val == PlayerLotteryRecord::SOURCE_MACHINE ? '#108ee9' : '#f50')
                ]);
            })->align('center')->align('center');
            $grid->column('source', admin_trans('player_lottery_record.fields.source'))->display(function ($val) {
                return Html::create()->content([
                    Tag::create(admin_trans('player_lottery_record.source.' . $val))->color($val == Lottery::LOTTERY_TYPE_FIXED ? '#108ee9' : '#f50')
                ]);
            })->align('center')->align('center');
            $grid->column('machine_name', admin_trans('player_lottery_record.fields.machine_name'))
                ->display(function ($val, PlayerLotteryRecord $data) {
                    if ($data->machine) {
                        return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal([
                            PlayerDeliveryRecordController::class,
                            'machineInfo'
                        ],
                            ['data' => $data->machine->toArray()])->width('60%')->title($data->machine->code . ' ' . $data->machine->name);
                    }
                    return '';
                })
                ->align('center');
            $grid->column('machine_code',
                admin_trans('player_lottery_record.fields.machine_code'))->align('center')->sortable()->copy();
            $grid->column('odds', admin_trans('player_lottery_record.fields.odds'))->sortable()->align('center');
            $grid->column('lottery_name', admin_trans('player_lottery_record.fields.lottery_name'))->display(function (
                $value,
                PlayerLotteryRecord $data
            ) {
                $value = !empty($data->lottery_name) ? $data->lottery_name : '';
                return Html::create(Str::of($value)->limit(20, ' (...)'))
                    ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                    ->modal([$this, $data->source == PlayerLotteryRecord::SOURCE_MACHINE ? 'lotteryInfo' : 'gameLotteryInfo'], ['lottery_id' => $data->lottery_id])
                    ->width('60%')->title(admin_trans('player_lottery_record.fields.lottery_name') . ':' . $data->lottery_name);
            })->align('center');
            $grid->column('amount', admin_trans('player_lottery_record.fields.amount'))->display(function (
                $val,
                PlayerLotteryRecord $data
            ) {
                return Html::create()->content([
                    Html::div()->content($val),
                    $data->lottery_multiple > 1 ? Tag::create(admin_trans('player_lottery_record.double'))
                        ->color('success')->style(['margin' => '0 auto']) : '',
                    $data->is_max == 1 ? Tag::create(admin_trans('player_lottery_record.max_amount'))
                        ->color('red')->style(['margin' => '3 auto', 'margin-left' => '3px']) : ''
                ]);
            })->align('center')->sortable()->width('8%');
            $grid->column('lottery_pool_amount',
                admin_trans('player_lottery_record.fields.lottery_pool_amount'))->sortable()->align('center');
            $grid->column('lottery_rate', admin_trans('player_lottery_record.fields.lottery_rate'))->display(function (
                $val
            ) {
                return Html::create()->content([
                    Html::div()->content($val . '%')
                ]);
            })->sortable()->align('center');
            $grid->column('bet', admin_trans('play_game_record.fields.bet'))->display(function ($val, PlayerLotteryRecord $data) {
                return $data->source == PlayerLotteryRecord::SOURCE_GAME ? Html::create()->content(['-' . $val])->style(['color' => '#cd201f']) : '';
            })->sortable()->align('center');
            $grid->column('cate_rate', admin_trans('player_lottery_record.fields.cate_rate'))->align('center');
            $grid->column('reject_reason',
                admin_trans('player_lottery_record.fields.reject_reason'))->display(function ($value) {
                return Str::of($value)->limit(20, ' (...)');
            })->tip()->width('150px')->sortable()->align('center');
            $grid->column('user_name', admin_trans('player_lottery_record.fields.user_name'))->display(function (
                $val,
                PlayerLotteryRecord $data
            ) {
                return Html::create()->content([
                    Html::div()->content($data->user_name ?? ''),
                ]);
            })->align('center');
            $grid->column('audit_at',
                admin_trans('player_lottery_record.fields.audit_at'))->sortable()->align('center');
            $grid->column('created_at',
                admin_trans('player_lottery_record.fields.created_at'))->fixed('right')->sortable()->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('machine_name')->placeholder(admin_trans('player_lottery_record.fields.machine_name'));
                $filter->like()->text('machine_code')->placeholder(admin_trans('player_lottery_record.fields.machine_code'));
                $filter->like()->text('lottery_name')->placeholder(admin_trans('player_lottery_record.fields.lottery_name'));
                $filter->like()->text('player_phone')->placeholder(admin_trans('player_lottery_record.fields.player_phone'));
                $filter->like()->text('uuid')->placeholder(admin_trans('player_lottery_record.fields.uuid'));
                $filter->eq()->number('amount')->precision(2)->style(['width' => '150px'])->placeholder(admin_trans('player_lottery_record.fields.amount'));
                $filter->eq()->select('status')
                    ->placeholder(admin_trans('player_lottery_record.fields.status'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        PlayerLotteryRecord::STATUS_UNREVIEWED => admin_trans('player_lottery_record.status.' . PlayerLotteryRecord::STATUS_UNREVIEWED),
                        PlayerLotteryRecord::STATUS_REJECT => admin_trans('player_lottery_record.status.' . PlayerLotteryRecord::STATUS_REJECT),
                        PlayerLotteryRecord::STATUS_PASS => admin_trans('player_lottery_record.status.' . PlayerLotteryRecord::STATUS_PASS),
                        PlayerLotteryRecord::STATUS_COMPLETE => admin_trans('player_lottery_record.status.' . PlayerLotteryRecord::STATUS_COMPLETE),
                    ]);
                $filter->eq()->select('lottery_type')
                    ->placeholder(admin_trans('lottery.fields.lottery_type'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        Lottery::LOTTERY_TYPE_FIXED => admin_trans('lottery.lottery_type.' . Lottery::LOTTERY_TYPE_FIXED),
                        Lottery::LOTTERY_TYPE_RANDOM => admin_trans('lottery.lottery_type.' . Lottery::LOTTERY_TYPE_RANDOM),
                    ]);
                $filter->eq()->select('source')
                    ->placeholder(admin_trans('player_lottery_record.fields.source'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        Lottery::LOTTERY_TYPE_FIXED => admin_trans('player_lottery_record.source.' . PlayerLotteryRecord::SOURCE_MACHINE),
                        Lottery::LOTTERY_TYPE_RANDOM => admin_trans('player_lottery_record.source.' . PlayerLotteryRecord::SOURCE_GAME),
                    ]);
                $filter->in()->cascaderSingle('cate_id')
                    ->showSearch()
                    ->style(['width' => '150px'])
                    ->placeholder(admin_trans('machine.fields.cate_id'))
                    ->options(getCateListOptions())
                    ->multiple();
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
                $filter->select('search_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.type'))
                    ->options([
                        0 => admin_trans('player.player'),
                        1 => admin_trans('player.fields.is_test'),
                    ]);
                $filter->select('search_is_promoter')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.is_promoter'))
                    ->options([
                        0 => admin_trans('player.not_promoter'),
                        1 => admin_trans('player.promoter'),
                    ]);
                $filter->eq()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.department_id'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
            $grid->expandFilter();
        });
    }

    /**
     * 彩金信息
     * @param $lottery_id
     * @return Detail
     */
    public function lotteryInfo($lottery_id): Detail
    {
        $lottery = Lottery::find($lottery_id);
        return Detail::create($lottery, function (Detail $detail) {
            $detail->item('name', admin_trans('lottery.fields.name'));
            $detail->item('game_type', admin_trans('lottery.fields.name'))->display(function ($val) {
                switch ($val) {
                    case GameType::TYPE_STEEL_BALL:
                        $tag = Tag::create(admin_trans('game_type.game_type.' . $val))->color('#f50');
                        break;
                    case GameType::TYPE_SLOT:
                        $tag = Tag::create(admin_trans('game_type.game_type.' . $val))->color('#2db7f5');
                        break;
                }
                return $tag ?? '';
            });
            $detail->item('max_amount', admin_trans('lottery.fields.max_amount'));
            $detail->item('last_player_name', admin_trans('lottery.fields.last_player_name'))->display(function (
                $val,
                Lottery $data
            ) {
                if (!empty($data->player)) {
                    $image = $data->player->avatar ? Avatar::create()->src(is_numeric($data->player->avatar) ? config('def_avatar.' . $data->player->avatar) : $data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                    return Html::create()->content([
                        $image,
                        Html::div()->content($data->player->phone),
                    ]);
                }
                return '';
            });
            $detail->item('condition', admin_trans('lottery.fields.condition'))->display(function (
                $val,
                Lottery $data
            ) {
                if ($data->lottery_type == Lottery::LOTTERY_TYPE_FIXED) {
                    if ($data->game_type == GameType::TYPE_SLOT) {
                        return admin_trans('lottery.slot_condition_msg') . $val;
                    }
                    if ($data->game_type == GameType::TYPE_STEEL_BALL) {
                        return admin_trans('lottery.jac_condition_msg') . $val;
                    }
                } else {
                    return $data->random_num . ' ' . admin_trans('lottery.random_rang') . ' (' . $data->start_num . '~' . $data->end_num . ')';
                }
            });
            $detail->item('lottery_type', admin_trans('lottery.fields.lottery_type'))->display(function ($val) {
                return Html::create()->content([
                    Tag::create(admin_trans('lottery.lottery_type.' . $val))->color($val == Lottery::LOTTERY_TYPE_FIXED ? '#108ee9' : '#f50')
                ]);
            });
            $detail->item('rate', admin_trans('lottery.fields.rate'))->display(function ($val) {
                return Html::create()->content([
                    Html::div()->content(floatval($val) . '%')
                ]);
            });
            $detail->item('status', admin_trans('lottery.fields.status'))->display(function ($val, Lottery $data) {
                switch ($val) {
                    case 0:
                        $tag = Tag::create(admin_trans('admin.close'))->color('#f50');
                        break;
                    case 1:
                        $tag = Tag::create(admin_trans('admin.open'))->color('#2db7f5');
                        break;
                }
                return $tag ?? '';
            });
            $detail->item('created_at', admin_trans('lottery.fields.created_at'))->display(function ($val) {
                return date('Y-m-d H:i', strtotime($val));
            });
        })->bordered();
    }

    /**
     * 电子游戏彩金信息
     * @param $lottery_id
     * @return Detail
     */
    public function gameLotteryInfo($lottery_id): Detail
    {
        $lottery = GameLottery::find($lottery_id);
        return Detail::create($lottery, function (Detail $detail) {
            $detail->item('name', admin_trans('lottery.fields.name'));
            $detail->item('max_amount', admin_trans('lottery.fields.max_amount'));
            $detail->item('last_player_name', admin_trans('lottery.fields.last_player_name'))->display(function (
                $val,
                GameLottery $data
            ) {
                if (!empty($data->player)) {
                    $image = $data->player->avatar ? Avatar::create()->src(is_numeric($data->player->avatar) ? config('def_avatar.' . $data->player->avatar) : $data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                    return Html::create()->content([
                        $image,
                        Html::div()->content($data->player->phone),
                    ]);
                }
                return '';
            });
            $detail->item('lottery_type', admin_trans('lottery.fields.lottery_type'))->display(function ($val) {
                return Html::create()->content([
                    Tag::create(admin_trans('lottery.lottery_type.' . $val))->color($val == Lottery::LOTTERY_TYPE_FIXED ? '#108ee9' : '#f50')
                ]);
            });
            $detail->item('rate', admin_trans('lottery.fields.rate'))->display(function ($val) {
                return Html::create()->content([
                    Html::div()->content(floatval($val) . '%')
                ]);
            });
            $detail->item('win_ratio', admin_trans('lottery.fields.win_ratio'))->display(function ($val) {
                return Html::create()->content([
                    Html::div()->content(formatWinRatio(floatval($val)))
                ]);
            });
            $detail->item('pool_ratio', admin_trans('lottery.pool_ratio'))->display(function ($val) {
                return Html::create()->content([
                    Html::div()->content(floatval($val) . '%')
                ]);
            });
            $detail->item('status', admin_trans('lottery.fields.status'))->display(function ($val) {
                switch ($val) {
                    case 0:
                        $tag = Tag::create(admin_trans('admin.close'))->color('#f50');
                        break;
                    case 1:
                        $tag = Tag::create(admin_trans('admin.open'))->color('#2db7f5');
                        break;
                }
                return $tag ?? '';
            });
            $detail->item('created_at', admin_trans('lottery.fields.created_at'))->display(function ($val) {
                return date('Y-m-d H:i', strtotime($val));
            });
        })->bordered();
    }

    /**
     * 彩金审核
     * @auth true
     */
    public function auditList(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('player_lottery_record.audit_title'));
            $grid->bordered(true);
            $grid->autoHeight();
            $requestFilter = Request::input('ex_admin_filter', []);
            $grid->model()->with(['channel'])->where('lottery_type', Lottery::LOTTERY_TYPE_FIXED)->where('status',
                PlayerLotteryRecord::STATUS_UNREVIEWED)->orderBy('created_at', 'desc');
            if (!empty($requestFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $requestFilter['created_at_start']);
            }
            if (!empty($requestFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $requestFilter['created_at_end']);
            }
            $grid->column('id', admin_trans('player_lottery_record.fields.id'))
                ->align('center');
            $grid->column('uuid', admin_trans('player_lottery_record.fields.uuid'))
                ->display(function ($val, PlayerLotteryRecord $data) {
                    return Html::create()->content([
                        Html::div()->content($val),
                        $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : ''
                    ]);
                })
                ->align('center')->copy();
            $grid->column('player_phone', admin_trans('player_lottery_record.fields.player_phone'))->display(function (
                $val,
                PlayerLotteryRecord $data
            ) {
                $image = isset($data->player->avatar) && !empty($data->player->avatar) ? Avatar::create()->src($data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val)
                ]);
            })->align('center');
            $grid->column('channel.name', admin_trans('player.fields.department_id'))->width('150px')->align('center');
            $grid->column('machine_name', admin_trans('player_lottery_record.fields.machine_name'))->display(function (
                $val,
                PlayerLotteryRecord $data
            ) {
                if ($data->machine) {
                    return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal([
                        PlayerDeliveryRecordController::class,
                        'machineInfo'
                    ],
                        ['data' => $data->machine->toArray()])->width('60%')->title($data->machine->code . ' ' . $data->machine->name);
                }
                return '';
            })
                ->align('center');
            $grid->column('machine_code',
                admin_trans('player_lottery_record.fields.machine_code'))->align('center')->copy();
            $grid->column('odds', admin_trans('player_lottery_record.fields.odds'))->align('center');
            $grid->column('lottery_name', admin_trans('player_lottery_record.fields.lottery_name'))->display(function (
                $value,
                PlayerLotteryRecord $data
            ) {
                $value = !empty($data->lottery_name) ? $data->lottery_name : '';
                return Html::create(Str::of($value)->limit(20, ' (...)'))
                    ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                    ->modal([$this, 'lotteryInfo'], ['lottery_id' => $data->lottery_id])
                    ->width('60%')->title(admin_trans('player_lottery_record.fields.lottery_name') . ':' . $data->lottery_name);
            })->align('center');
            $grid->column('amount', admin_trans('player_lottery_record.fields.amount'))->display(function (
                $val,
                PlayerLotteryRecord $data
            ) {
                return Html::create()->content([
                    Html::div()->content($val),
                    $data->lottery_multiple > 1 ? Tag::create(admin_trans('player_lottery_record.double'))
                        ->color('success')->style(['margin' => '0 auto']) : '',
                    $data->is_max == 1 ? Tag::create(admin_trans('player_lottery_record.max_amount'))
                        ->color('red')->style(['margin' => '0 auto', 'margin-left' => '3px']) : ''
                ]);
            })->align('center');
            $grid->column('lottery_pool_amount',
                admin_trans('player_lottery_record.fields.lottery_pool_amount'))->align('center');
            $grid->column('lottery_rate', admin_trans('player_lottery_record.fields.lottery_rate'))->display(function (
                $val
            ) {
                return Html::create()->content([
                    Html::div()->content($val . '%')
                ]);
            })->align('center');
            $grid->column('cate_rate', admin_trans('player_lottery_record.fields.cate_rate'))->align('center');
            $grid->column('reject_reason',
                admin_trans('player_lottery_record.fields.reject_reason'))->display(function ($value) {
                return Str::of($value)->limit(20, ' (...)');
            })->tip()->width('150px')->align('center');
            $grid->column('created_at',
                admin_trans('player_lottery_record.fields.created_at'))->sortable()->align('center');
            $grid->hideDelete();
            $grid->hideDeleteSelection();
            $grid->actions(function (Actions $actions, PlayerLotteryRecord $data) {
                $actions->hideDel();
                $actions->hideEdit();
                $dropdown = Dropdown::create(
                    Button::create([
                        admin_trans('player_lottery_record.btn.action'),
                        Icon::create('DownOutlined')->style(['marginRight' => '5px'])
                    ]))->trigger(['click']);

                $dropdown->item(admin_trans('player_lottery_record.btn.examine_pass'), 'SafetyCertificateOutlined')
                    ->confirm(admin_trans('player_lottery_record.btn.examine_pass_confirm'), [$this, 'pass'],
                        ['id' => $data->id])->gridRefresh();

                $dropdown->item(admin_trans('player_lottery_record.btn.examine_reject'), 'WarningFilled')
                    ->modal([$this, 'reject'], ['id' => $data->id])->gridRefresh();
                $actions->prepend(
                    $dropdown
                );
            });
            $dropdown = Dropdown::create(
                Button::create(
                    [
                        admin_trans('player_lottery_record.bath_action'),
                        Icon::create('DownOutlined')->style(['marginRight' => '5px']),
                    ]
                )
            )->trigger(['click']);
            $dropdown->item(admin_trans('player_lottery_record.btn.examine_reject'), 'far fa-question-circle')
                ->modal([$this, 'bathReject'])
                ->gridBatch();
            $dropdown->item(admin_trans('player_lottery_record.btn.examine_pass'), 'far fa-check-circle')
                ->confirm(admin_trans('player_lottery_record.btn.examine_pass_confirm'), [$this, 'bathPass'])
                ->gridBatch();
            $grid->tools(
                $dropdown
            );
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('machine_name')->placeholder(admin_trans('player_lottery_record.fields.machine_name'));
                $filter->like()->text('machine_code')->placeholder(admin_trans('player_lottery_record.fields.machine_code'));
                $filter->like()->text('lottery_name')->placeholder(admin_trans('player_lottery_record.fields.lottery_name'));
                $filter->like()->text('player_phone')->placeholder(admin_trans('player_lottery_record.fields.player_phone'));
                $filter->like()->text('uuid')->placeholder(admin_trans('player_lottery_record.fields.uuid'));
                $filter->eq()->number('amount')->precision(2)->style(['width' => '150px'])->placeholder(admin_trans('player_lottery_record.fields.amount'));
                $filter->eq()->select('lottery_type')
                    ->placeholder(admin_trans('lottery.fields.lottery_type'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        Lottery::LOTTERY_TYPE_FIXED => admin_trans('lottery.lottery_type.' . Lottery::LOTTERY_TYPE_FIXED),
                        Lottery::LOTTERY_TYPE_RANDOM => admin_trans('lottery.lottery_type.' . Lottery::LOTTERY_TYPE_RANDOM),
                    ]);
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
            $grid->expandFilter();
        });
    }

    /**
     * 审核拒绝
     * @auth true
     * @param $id
     * @return Form
     */
    public function reject($id): Form
    {
        return Form::create(new $this->model(), function (Form $form) use ($id) {
            $form->textarea('reject_reason')->rows(5)->required();
            $form->saving(function (Form $form) use ($id) {
                /** @var PlayerLotteryRecord $playerLotteryRecord */
                $playerLotteryRecord = $this->model::find($id);
                if (empty($playerLotteryRecord)) {
                    return message_error(admin_trans('player_lottery_record.not_fount'));
                }
                if ($playerLotteryRecord->lottery_type != Lottery::LOTTERY_TYPE_FIXED) {
                    return message_error(admin_trans('player_lottery_record.lottery_record_error'));
                }
                switch ($playerLotteryRecord->status) {
                    case PlayerLotteryRecord::STATUS_PASS:
                        return message_warning(admin_trans('player_lottery_record.lottery_record_has_pass'));
                    case PlayerLotteryRecord::STATUS_REJECT:
                        return message_warning(admin_trans('player_lottery_record.lottery_record_has_reject'));
                    case PlayerLotteryRecord::STATUS_COMPLETE:
                        return message_warning(admin_trans('player_lottery_record.lottery_record_has_complete'));
                }
                DB::beginTransaction();
                try {
                    // 更新领取记录
                    $playerLotteryRecord->status = PlayerLotteryRecord::STATUS_REJECT;
                    $playerLotteryRecord->reject_reason = $form->input('reject_reason');
                    $playerLotteryRecord->audit_at = date('Y-m-d H:i:s');
                    $playerLotteryRecord->user_id = Admin::id() ?? 0;
                    $playerLotteryRecord->user_name = !empty(Admin::user()) ? Admin::user()->username : '';
                    $playerLotteryRecord->save();
                    // 奖金返回奖池（新版：返回到对应的 Lottery 独立彩池）
                    $lottery = Lottery::find($playerLotteryRecord->lottery_id);
                    if ($lottery) {
                        $lottery->amount = bcadd($lottery->amount, $playerLotteryRecord->amount, 4);
                        $lottery->save();
                        // 清除彩金缓存
                        LotteryServices::clearLotteryListCache($playerLotteryRecord->machine->type);
                    }
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    return message_error(admin_trans('player_lottery_record.action_error'));
                }
                return message_success(admin_trans('player_lottery_record.action_success'));
            });
        });
    }

    /**
     * 审核通过
     * @param $id
     * @return Msg
     * @throws PushException
     * @auth true
     */
    public function pass($id): Msg
    {
        /** @var PlayerLotteryRecord $playerLotteryRecord */
        $playerLotteryRecord = $this->model::find($id);
        if (empty($playerLotteryRecord)) {
            return message_error(admin_trans('player_lottery_record.not_fount'));
        }
        if ($playerLotteryRecord->lottery_type != Lottery::LOTTERY_TYPE_FIXED) {
            return message_error(admin_trans('player_lottery_record.lottery_record_error'));
        }
        switch ($playerLotteryRecord->status) {
            case PlayerLotteryRecord::STATUS_PASS:
                return message_warning(admin_trans('player_lottery_record.lottery_record_has_pass'));
            case PlayerLotteryRecord::STATUS_REJECT:
                return message_warning(admin_trans('player_lottery_record.lottery_record_has_reject'));
            case PlayerLotteryRecord::STATUS_COMPLETE:
                return message_warning(admin_trans('player_lottery_record.lottery_record_has_complete'));
        }
        DB::beginTransaction();
        try {
            // 更新领取记录
            $playerLotteryRecord->status = PlayerLotteryRecord::STATUS_PASS;
            $playerLotteryRecord->audit_at = date('Y-m-d H:i:s');
            $playerLotteryRecord->user_id = Admin::id() ?? 0;
            $playerLotteryRecord->user_name = !empty(Admin::user()) ? Admin::user()->username : '';
            $playerLotteryRecord->save();
            // 发送站内信
            $notice = new Notice();
            $notice->department_id = $playerLotteryRecord->player->department_id;
            $notice->player_id = $playerLotteryRecord->player_id;
            $notice->source_id = $playerLotteryRecord->id;
            $notice->type = Notice::TYPE_LOTTERY;
            $notice->receiver = Notice::RECEIVER_PLAYER;
            $notice->is_private = 1;
            $machineType = $playerLotteryRecord->machine->type == GameType::TYPE_SLOT
                ? admin_trans('player_lottery_record.machine_type.slot')
                : admin_trans('player_lottery_record.machine_type.steel_ball');
            $notice->title = admin_trans('player_lottery_record.notice.title');
            $notice->content = admin_trans('player_lottery_record.notice.content_machine', null, [
                '{machine_type}' => $machineType,
                '{machine_code}' => $playerLotteryRecord->machine->code,
                '{lottery_name}' => $playerLotteryRecord->lottery_name
            ]);
            $notice->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return message_error(admin_trans('player_lottery_record.action_error'));
        }
        // 发送站内消息
        sendSocketMessage('player-' . $playerLotteryRecord->player_id, [
            'msg_type' => 'player_notice',
            'player_id' => $playerLotteryRecord->player_id,
            'notice_type' => Notice::TYPE_LOTTERY,
            'notice_title' => $notice->title,
            'notice_content' => $notice->content,
            'amount' => $playerLotteryRecord->amount,
            'machine_name' => $playerLotteryRecord->machine_name,
            'machine_code' => $playerLotteryRecord->machine_code,
            'lottery_name' => $playerLotteryRecord->lottery_name,
            'lottery_type' => $playerLotteryRecord->lottery_type,
            'game_type' => $playerLotteryRecord->game_type,
            'lottery_multiple' => $playerLotteryRecord->lottery_multiple,
            'notice_num' => Notice::where('player_id', $playerLotteryRecord->player_id)->where('status', 0)->count('*')
        ]);
        return message_success(admin_trans('player_lottery_record.action_success'));
    }

    /**
     * 批量审核通过
     * @return Msg
     * @throws PushException
     * @auth true
     */
    public function bathPass(): Msg
    {
        $data = Request::input();
        $selected = $data['selected'] ?? [];
        if (!empty($selected)) {
            $playerLotteryRecords = $this->model::whereIn('id', $selected)->get();
            $notices = [];
            /** @var PlayerLotteryRecord $playerLotteryRecord */
            foreach ($playerLotteryRecords as $playerLotteryRecord) {
                if ($playerLotteryRecord->lottery_type != Lottery::LOTTERY_TYPE_FIXED) {
                    return message_error(admin_trans('player_lottery_record.bath_error') . $playerLotteryRecord->id . admin_trans('player_lottery_record.lottery_record_error'));
                }
                switch ($playerLotteryRecord->status) {
                    case PlayerLotteryRecord::STATUS_PASS:
                        return message_warning(admin_trans('player_lottery_record.bath_error') . $playerLotteryRecord->id . admin_trans('player_lottery_record.lottery_record_has_pass'));
                    case PlayerLotteryRecord::STATUS_REJECT:
                        return message_warning(admin_trans('player_lottery_record.bath_error') . $playerLotteryRecord->id . admin_trans('player_lottery_record.lottery_record_has_reject'));
                    case PlayerLotteryRecord::STATUS_COMPLETE:
                        return message_warning(admin_trans('player_lottery_record.bath_error') . $playerLotteryRecord->id . admin_trans('player_lottery_record.lottery_record_has_complete'));
                }
                $playerLotteryRecord->status = PlayerLotteryRecord::STATUS_PASS;
                $playerLotteryRecord->audit_at = date('Y-m-d H:i:s');
                $playerLotteryRecord->user_id = Admin::id() ?? 0;
                $playerLotteryRecord->user_name = !empty(Admin::user()) ? Admin::user()->username : '';
                $playerLotteryRecord->save();
                $notice = new Notice();
                $notice->department_id = $playerLotteryRecord->player->department_id;
                $notice->player_id = $playerLotteryRecord->player_id;
                $notice->source_id = $playerLotteryRecord->id;
                $notice->type = Notice::TYPE_LOTTERY;
                $notice->receiver = Notice::RECEIVER_PLAYER;
                $notice->is_private = 1;
                $machineType = $playerLotteryRecord->machine->type == GameType::TYPE_SLOT
                    ? admin_trans('player_lottery_record.machine_type.slot')
                    : admin_trans('player_lottery_record.machine_type.steel_ball');
                $notice->title = admin_trans('player_lottery_record.notice.title');
                $notice->content = admin_trans('player_lottery_record.notice.content_machine', null, [
                    '{machine_type}' => $machineType,
                    '{machine_code}' => $playerLotteryRecord->machine->code,
                    '{lottery_name}' => $playerLotteryRecord->lottery_name
                ]);
                $notices[] = $notice;
            }
            DB::beginTransaction();
            try {
                foreach ($notices as $notice) {
                    $notice->save();
                    sendSocketMessage('player-' . $notice->player_id, [
                        'msg_type' => 'player_notice',
                        'player_id' => $notice->player_id,
                        'notice_type' => Notice::TYPE_LOTTERY,
                        'notice_title' => $notice->title,
                        'notice_content' => $notice->content,
                        'amount' => $playerLotteryRecord->amount,
                        'machine_name' => $playerLotteryRecord->machine_name,
                        'machine_code' => $playerLotteryRecord->machine_code,
                        'lottery_name' => $playerLotteryRecord->lottery_name,
                        'lottery_type' => $playerLotteryRecord->lottery_type,
                        'game_type' => $playerLotteryRecord->game_type,
                        'lottery_multiple' => $playerLotteryRecord->lottery_multiple,
                        'notice_num' => Notice::where('player_id', $notice->player_id)->where('status', 0)->count('*')
                    ]);
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return message_error(admin_trans('player_lottery_record.action_error'));
            }
            return message_success(admin_trans('player_lottery_record.action_success'))->refresh();
        }
        return message_error(admin_trans('player_lottery_record.bath_not_found'));
    }

    /**
     * 批量审核拒绝
     * @auth true
     * @return Form
     */
    public function bathReject(): Form
    {
        return Form::create(new $this->model(), function (Form $form) {
            $data = Request::input();
            $selected = $data['selected'] ?? [];
            $form->textarea('reject_reason')->rows(5)->required();
            $form->hidden('selected')->value($selected);
            $form->saving(function (Form $form) {
                $selected = $form->input('selected');
                $playerLotteryRecords = $this->model::whereIn('id', $selected)->with('machine')->get();
                foreach ($playerLotteryRecords as $playerLotteryRecord) {
                    if ($playerLotteryRecord->lottery_type != Lottery::LOTTERY_TYPE_FIXED) {
                        return message_warning(admin_trans('player_lottery_record.bath_error') . $playerLotteryRecord->id . admin_trans('player_lottery_record.lottery_record_error'));
                    }
                    switch ($playerLotteryRecord->status) {
                        case PlayerLotteryRecord::STATUS_PASS:
                            return message_warning(admin_trans('player_lottery_record.bath_error') . $playerLotteryRecord->id . admin_trans('player_lottery_record.lottery_record_has_pass'));
                        case PlayerLotteryRecord::STATUS_REJECT:
                            return message_warning(admin_trans('player_lottery_record.bath_error') . $playerLotteryRecord->id . admin_trans('player_lottery_record.lottery_record_has_reject'));
                        case PlayerLotteryRecord::STATUS_COMPLETE:
                            return message_warning(admin_trans('player_lottery_record.bath_error') . $playerLotteryRecord->id . admin_trans('player_lottery_record.lottery_record_has_complete'));
                    }
                }
                $poolArr = [
                    GameType::TYPE_SLOT => 0,
                    GameType::TYPE_STEEL_BALL => 0,
                    GameType::TYPE_FISH => 0,
                ];
                // 统计每个彩金需要返回的金额
                $lotteryAmountArr = [];
                /** @var PlayerLotteryRecord $playerLotteryRecord */
                foreach ($playerLotteryRecords as $playerLotteryRecord) {
                    $poolArr[$playerLotteryRecord->game_type] += $playerLotteryRecord->amount;
                    if (!isset($lotteryAmountArr[$playerLotteryRecord->lottery_id])) {
                        $lotteryAmountArr[$playerLotteryRecord->lottery_id] = 0;
                    }
                    $lotteryAmountArr[$playerLotteryRecord->lottery_id] = bcadd(
                        $lotteryAmountArr[$playerLotteryRecord->lottery_id],
                        $playerLotteryRecord->amount,
                        4
                    );
                }
                DB::beginTransaction();
                try {
                    $this->model::whereIn('id', $selected)->update([
                        'status' => PlayerLotteryRecord::STATUS_REJECT,
                        'reject_reason' => $form->input('reject_reason'),
                        'audit_at' => date('Y-m-d H:i:s'),
                        'user_id' => Admin::id() ?? 0,
                        'user_name' => !empty(Admin::user()) ? Admin::user()->username : '',
                    ]);
                    // 奖金返回奖池（新版：返回到对应的 Lottery 独立彩池）
                    foreach ($lotteryAmountArr as $lotteryId => $amount) {
                        $lottery = Lottery::find($lotteryId);
                        if ($lottery) {
                            $lottery->amount = bcadd($lottery->amount, $amount, 4);
                            $lottery->save();
                        }
                    }
                    // 清除彩金缓存
                    LotteryServices::clearAllCache();

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    return message_error(admin_trans('player_lottery_record.action_error'));
                }

                return message_success(admin_trans('player_lottery_record.action_success'));
            });
        });
    }
}
