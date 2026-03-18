<?php

namespace addons\webman\controller;

use addons\webman\model\PlayGameRecord;
use addons\webman\model\PromoterProfitGameRecord;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\grid\ToolTip;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\support\Request;


/**
 * 游戏游玩记录
 */
class ChannelPromoterProfitGameRecordController
{
    protected $model;
    protected $playGameRecord;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.promoter_profit_game_record_model');
        $this->playGameRecord = plugin()->webman->config('database.play_game_record_model');
    }

    /**
     * 游戏平台分润
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $grid->title(admin_trans('promoter_profit_game_record.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->hideDelete();
            $grid->hideDeleteSelection();
            $grid->model()->with(['player'])->orderBy('id', 'desc');
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            if (!empty($exAdminFilter['player']['uuid'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('uuid', 'like', '%' . $exAdminFilter['player']['uuid'] . '%');
                });
            }
            if (!empty($exAdminFilter['player']['name'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('name', 'like', '%' . $exAdminFilter['player']['name'] . '%');
                });
            }
            if (!empty($exAdminFilter['player']['phone'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('phone', 'like', '%' . $exAdminFilter['player']['phone'] . '%');
                });
            }
            if (!empty($exAdminFilter['platform_id'])) {
                $grid->model()->where('platform_id', $exAdminFilter['platform_id']);
            }
            if (isset($exAdminFilter['date_type'])) {
                $grid->model()->where(getDateWhere($exAdminFilter['date_type'],'date'));
            }
            $query = clone $grid->model();
            $totalData = $query->selectRaw('sum(total_bet) as all_bet, sum(total_diff) as all_diff, sum(game_amount) as all_game_amount')->first();
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['all_bet']) ? floatval($totalData['all_bet']) : 0)->prefix(admin_trans('promoter_profit_game_record.fields.total_bet'))->valueStyle([
                            'font-size' => '14px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 4);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['all_diff']) ? floatval($totalData['all_diff']) : 0)->prefix(admin_trans('promoter_profit_game_record.fields.total_diff'))->valueStyle([
                            'font-size' => '14px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 4);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['all_game_amount']) ? floatval($totalData['all_game_amount']) : 0)->prefix(admin_trans('promoter_profit_game_record.fields.game_amount'))->valueStyle([
                            'font-size' => '14px',
                            'font-weight' => '500',
                            'text-align' => 'center'
                        ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 4);
            })->style(['background' => '#fff']);
            $grid->tools([$layout]);
            $grid->column('id', admin_trans('promoter_profit_game_record.fields.id'))->fixed(true)->align('center');
            $grid->column('player.phone', admin_trans('player.fields.phone'))->display(function (
                $val,
                PromoterProfitGameRecord $data
            ) {
                $image = $data->player->avatar ? Avatar::create()->src(is_numeric($data->player->avatar) ? config('def_avatar.' . $data->player->avatar) : $data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($data->player->phone),
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : ''
                ]);
            })->fixed(true)->align('center');
            $grid->column('platform_name', admin_trans('game_platform.fields.name'))->display(function (
                $val,
                PromoterProfitGameRecord $data
            ) {
                return Html::create()->content([
                    Html::div()->content($data->gamePlatform->name),
                ]);
            })->align('center');
            $grid->column('total_bet', admin_trans('promoter_profit_game_record.fields.total_bet'))->display(function (
                $val
            ) {
                return Html::create()->content([(float)$val])->style(['color' => '#cd201f']);
            })->align('center');
            $grid->column('total_diff',
                admin_trans('promoter_profit_game_record.fields.total_diff'))->display(function ($val) {
                if ((float)$val > 0) {
                    return Html::create()->content(['+', (float)$val])->style(['color' => 'green']);
                }
                return Html::create()->content([(float)$val])->style(['color' => '#cd201f']);
            })->header(Html::create(admin_trans('promoter_profit_game_record.fields.total_diff'))->content(
                ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                    'marginLeft' => '5px',
                    'cursor' => 'pointer'
                ]))->title(admin_trans('promoter_profit_game_record.total_diff_tip'))
            ))->align('center');
            $grid->column('total_reward',
                admin_trans('promoter_profit_game_record.fields.total_reward'))->display(function ($val) {
                return Html::create()->content((float)$val)->style(['color' => 'green']);
            })->align('center');
            $grid->column('game_amount', admin_trans('promoter_profit_game_record.fields.game_amount'))
                ->display(function ($val) {
                    if ((float)$val > 0) {
                        return Html::create()->content(['+', (float)$val])->style(['color' => 'green']);
                    }
                    return Html::create()->content([(float)$val])->style(['color' => '#cd201f']);
                })
                ->header(Html::create(admin_trans('promoter_profit_game_record.fields.game_amount'))->content(
                    ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                        'marginLeft' => '5px',
                        'cursor' => 'pointer'
                    ]))->title(admin_trans('promoter_profit_game_record.game_amount_tip'))
                ))
                ->align('center');
            $grid->column('game_platform_ratio',
                admin_trans('promoter_profit_game_record.fields.game_platform_ratio'))->append('%')->align('center');
            $grid->column('date', admin_trans('promoter_profit_game_record.fields.date'))->align('center');
            $grid->actions(function (Actions $actions, PromoterProfitGameRecord $data) {
                $actions->hideDel();
                $actions->prepend([
                    Button::create(admin_trans('promoter_profit_game_record.play_game_record'))
                        ->icon(Icon::create('UnorderedListOutlined'))
                        ->type('primary')
                        ->size('small')
                        ->modal([$this, 'playGameRecordList'],
                            ['date' => $data->date, 'playerId' => $data->player_id, 'platformId' => $data->platform_id])
                        ->width('70%')
                ]);
            });
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player.phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->eq()->select('platform_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('game_platform.fields.name'))
                    ->remoteOptions(admin_url([
                        'addons-webman-controller-GamePlatformController',
                        'getGamePlatformOptions'
                    ]));
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
                $filter->form()->dateRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
            $grid->expandFilter();
        });
    }

    /**
     * 玩家游戏记录
     * @group channel
     * @auth true
     */
    public function playGameRecordList($date = '', $playerId = 0, $platformId = 0): Grid
    {
        return Grid::create(new $this->playGameRecord, function (Grid $grid) use ($date, $playerId, $platformId) {
            $grid->title(admin_trans('play_game_record.title'));
            $grid->model()->when(!empty($date), function ($query) use ($date) {
                $query->whereDate('created_at', $date);
            })->when(!empty($playerId), function ($query) use ($playerId) {
                $query->where('player_id', $playerId);
            })->when(!empty($platformId), function ($query) use ($platformId) {
                $query->where('platform_id', $platformId);
            });
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->whereDate('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->whereDate('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->hideAction();
            $grid->hideDelete();
            $grid->hideDeleteSelection();
            $grid->hideSelection();
            $grid->column('id', admin_trans('play_game_record.fields.id'))->fixed(true)->align('center');
            $grid->column('player.name', admin_trans('player.fields.name'))->display(function (
                $val,
                PlayGameRecord $data
            ) {
                $image = $data->player->avatar ? Avatar::create()->src(is_numeric($data->player->avatar) ? config('def_avatar.' . $data->player->avatar) : $data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val),
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : ''
                ]);
            })->fixed(true)->align('center');
            $grid->column('channel.name', admin_trans('channel.fields.name'))->align('center');
            $grid->column('platform_name', admin_trans('game_platform.fields.name'))->display(function (
                $val,
                PlayGameRecord $data
            ) {
                return Html::create()->content([
                    Html::div()->content($data->gamePlatform->code),
                ]);
            })->align('center');
            $grid->column('order_no', admin_trans('play_game_record.fields.order_no'))->copy();
            $grid->column('game_code', admin_trans('play_game_record.fields.game_code'))->copy();
            $grid->column('status', admin_trans('play_game_record.fields.status'))->display(function (
                $val,
                PlayGameRecord $data
            ) {
                switch ($data->status) {
                    case PlayGameRecord::STATUS_UNSETTLED:
                        return Tag::create(admin_trans('play_game_record.status.' . PlayGameRecord::STATUS_UNSETTLED))->color('red');
                    case PlayGameRecord::STATUS_SETTLED:
                        return Tag::create(admin_trans('play_game_record.status.' . PlayGameRecord::STATUS_SETTLED))->color('blue');
                    default:
                        return '';
                }
            })->align('center');
            $grid->column('bet', admin_trans('play_game_record.fields.bet'))->display(function ($val) {
                return Html::create()->content(['-' . $val])->style(['color' => '#cd201f']);
            })->align('center');
            $grid->column('diff',
                admin_trans('play_game_record.fields.diff'))->display(function ($val) {
                if ((float)$val > 0) {
                    return Html::create()->content(['+', (float)$val])->style(['color' => 'green']);
                }
                return Html::create()->content([(float)$val])->style(['color' => '#cd201f']);
            })->align('center');
            $grid->column('reward', admin_trans('play_game_record.fields.reward'))->display(function ($val) {
                return Html::create()->content(['+' . $val])->style(['color' => 'green']);
            })->align('center');
            $grid->column('created_at', admin_trans('play_game_record.fields.create_at'))->align('center');
            $grid->column('action_at', admin_trans('play_game_record.fields.action_at'))->align('center');
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player.name')->placeholder(admin_trans('player.fields.name'));
                $filter->like()->text('order_no')->placeholder(admin_trans('play_game_record.fields.order_no'));
                $filter->like()->text('game_code')->placeholder(admin_trans('play_game_record.fields.game_code'));
                $filter->eq()->select('status')
                    ->placeholder(admin_trans('admin.fields.status'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        PlayGameRecord::STATUS_UNSETTLED => admin_trans('play_game_record.status.' . PlayGameRecord::STATUS_UNSETTLED),
                        PlayGameRecord::STATUS_SETTLED => admin_trans('play_game_record.status.' . PlayGameRecord::STATUS_SETTLED)
                    ]);
                $filter->eq()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('channel.fields.department_name'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
                $filter->eq()->select('platform_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('game.fields.platform_name'))
                    ->remoteOptions(admin_url([
                        'addons-webman-controller-GamePlatformController',
                        'getGamePlatformOptions'
                    ]));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
            $grid->expandFilter();
        });
    }
}
