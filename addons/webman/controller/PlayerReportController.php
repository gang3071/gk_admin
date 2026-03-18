<?php

namespace addons\webman\controller;

use addons\webman\model\Channel;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerPlatformCash;
use addons\webman\model\PlayerWithdrawRecord;
use addons\webman\model\PlayGameRecord;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\support\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * 充提记录
 */
class PlayerReportController
{
    protected $model;
    
    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_delivery_record_model');
    }
    
    
    /**
     * 玩家报表
     * @auth true
     */
    public function index(): Grid
    {
        $exAdminFilter = Request::input('ex_admin_filter', []);
        $exAdminSortBy = Request::input('ex_admin_sort_by', '');
        $exAdminSortField = Request::input('ex_admin_sort_field', '');
        $page = Request::input('ex_admin_page', '1');
        $size = Request::input('ex_admin_size', '20');
        $channelArr = [];
        $channelList = Channel::query()->get();
        /** @var Channel $channel */
        foreach ($channelList as $channel) {
            $channelArr[$channel->department_id] = $channel->name;
        }
        $baseQuery = Player::query()->withTrashed();
        $playGameRecordBaseQuery = PlayGameRecord::query()
            ->when(!empty($exAdminFilter['uuid']) || !empty($exAdminFilter['real_name']) || !empty($exAdminFilter['phone']) || !empty($exAdminFilter['recommend_promoter']['name']) || (!empty($exAdminFilter['search_is_promoter']) && in_array($exAdminFilter['search_is_promoter'],
                        [0, 1])) || !empty($exAdminFilter['department_id']) || !empty($exAdminFilter['search_type']),
                function (Builder $q) use ($exAdminFilter) {
                    $q->leftjoin('player', 'play_game_record.player_id', '=', 'player.id');
            });
        
        if (!empty($exAdminFilter)) {
            if (!empty($exAdminFilter['uuid'])) {
                $baseQuery->where('player.uuid', 'like', '%' . $exAdminFilter['uuid'] . '%');
                $playGameRecordBaseQuery->where('player.uuid', 'like', '%' . $exAdminFilter['uuid'] . '%');
            }
            if (!empty($exAdminFilter['uuid'])) {
                $baseQuery->where('player.uuid', 'like', '%' . $exAdminFilter['uuid'] . '%');
                $playGameRecordBaseQuery->where('player.uuid', 'like', '%' . $exAdminFilter['uuid'] . '%');
            }
            if (!empty($exAdminFilter['account'])) {
                $baseQuery->where('player.account', 'like', '%' . $exAdminFilter['account'] . '%');
                $playGameRecordBaseQuery->where('player.account', 'like', '%' . $exAdminFilter['account'] . '%');
            }
            if (!empty($exAdminFilter['phone'])) {
                $baseQuery->where('player.phone', 'like', '%' . $exAdminFilter['phone'] . '%');
                $playGameRecordBaseQuery->where('player.phone', 'like', '%' . $exAdminFilter['phone'] . '%');
            }
            if (!empty($exAdminFilter['recommend_promoter']['name'])) {
                $baseQuery->leftjoin('player as rp', 'player.recommend_id', '=', 'rp.id')
                    ->where(function ($q) use ($exAdminFilter) {
                        $q->where('rp.uuid', 'like', '%' . $exAdminFilter['recommend_promoter']['name'] . '%')
                            ->orWhere('rp.name', 'like', '%' . $exAdminFilter['recommend_promoter']['name'] . '%');
                    });
                $playGameRecordBaseQuery->leftjoin('player as rp', 'play_game_record.parent_player_id', '=', 'rp.id')
                    ->where(function ($q) use ($exAdminFilter) {
                        $q->where('rp.uuid', 'like', '%' . $exAdminFilter['recommend_promoter']['name'] . '%')
                            ->orWhere('rp.name', 'like', '%' . $exAdminFilter['recommend_promoter']['name'] . '%');
                    });
            }
            if (!empty($exAdminFilter['search_is_promoter']) && in_array($exAdminFilter['search_is_promoter'],
                    [0, 1])) {
                $baseQuery->where('player.is_promoter', $exAdminFilter['search_is_promoter']);
                $playGameRecordBaseQuery->whereHas('player', function ($q) use ($exAdminFilter) {
                    $q->where('is_promoter', $exAdminFilter['search_is_promoter']);
                });
            }
            if (!empty($exAdminFilter['department_id'])) {
                $baseQuery->where('player.department_id', $exAdminFilter['department_id']);
                $playGameRecordBaseQuery->where('play_game_record.department_id', $exAdminFilter['department_id']);
            }
            if (!empty($exAdminFilter['search_type'])) {
                $baseQuery->where('player.is_test', $exAdminFilter['search_type']);
                $playGameRecordBaseQuery->where('player.is_test', $exAdminFilter['search_type']);
            }
        }
        $totalQuery = $baseQuery->clone()->count('*');
        if (!empty($exAdminFilter)) {
            if (!empty($exAdminFilter['created_at_start'])) {
                $playGameRecordBaseQuery->where('play_game_record.created_at', '>=',
                    $exAdminFilter['created_at_start']);
                
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $playGameRecordBaseQuery->where('play_game_record.created_at', '<=', $exAdminFilter['created_at_end']);
            }
            if (isset($exAdminFilter['date_type'])) {
                $playGameRecordBaseQuery->where(getDateWhere($exAdminFilter['date_type'],
                    'play_game_record.created_at'));
            }
        }
        $baseQuery->leftJoin('player_delivery_record', function ($join) use ($exAdminFilter) {
            $join->on('player.id', '=', 'player_delivery_record.player_id')
                ->when(!empty($exAdminFilter['created_at_start']), function ($q) use ($exAdminFilter) {
                    $q->where('player_delivery_record.created_at', '>=', $exAdminFilter['created_at_start']);
                })
                ->when(!empty($exAdminFilter['created_at_end']), function ($q) use ($exAdminFilter) {
                    $q->where('player_delivery_record.created_at', '<=', $exAdminFilter['created_at_end']);
                })
                ->when(!empty($exAdminFilter['date_type']), function ($q) use ($exAdminFilter) {
                    $q->where(getDateWhere($exAdminFilter['date_type'], 'player_delivery_record.created_at'));
                })
                ->when(!empty($exAdminFilter['type']), function ($q) use ($exAdminFilter) {
                    $q->where('player_delivery_record.type', $exAdminFilter['type']);
                });
        });
        $baseQuery
            ->selectRaw("
            player.*,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD . " THEN player_delivery_record.amount ELSE 0 END) AS modified_total,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_RECHARGE . " THEN player_delivery_record.amount ELSE 0 END) AS recharge_total,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS . " THEN player_delivery_record.amount ELSE 0 END) AS activity_total,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_LOTTERY . " THEN player_delivery_record.amount ELSE 0 END) AS lottery_total,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MACHINE_UP . " THEN player_delivery_record.amount ELSE 0 END) AS machine_up_total,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MACHINE_DOWN . " THEN player_delivery_record.amount ELSE 0 END) AS machine_down_total,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_PRESENT_IN . " THEN player_delivery_record.amount ELSE 0 END) AS coin_withdraw,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_PRESENT_OUT . " THEN player_delivery_record.amount ELSE 0 END) AS coin_transfer,
            
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MACHINE_DOWN . " THEN player_delivery_record.amount ELSE 0 END) -
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MACHINE_UP . " THEN player_delivery_record.amount ELSE 0 END) -
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_LOTTERY . " THEN player_delivery_record.amount ELSE 0 END) -
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS . " THEN player_delivery_record.amount ELSE 0 END) AS winn_los_total,
            
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " and player_delivery_record.withdraw_status = " . PlayerWithdrawRecord::STATUS_SUCCESS . " THEN -player_delivery_record.amount ELSE 0 END) AS withdrawal_total,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_RECHARGE . " and player_delivery_record.source in ('self_recharge','gb_recharge') THEN player_delivery_record.amount ELSE 0 END) AS self_recharge_total,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_RECHARGE . " and player_delivery_record.source = 'artificial_recharge' THEN player_delivery_record.amount ELSE 0 END) AS artificial_recharge_total,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " and player_delivery_record.source in ('channel_withdrawal', 'gb_withdrawal') and player_delivery_record.withdraw_status = " . PlayerWithdrawRecord::STATUS_SUCCESS . " THEN -player_delivery_record.amount ELSE 0 END) AS channel_withdrawal_total,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " and player_delivery_record.source = 'artificial_withdrawal' and player_delivery_record.withdraw_status = " . PlayerWithdrawRecord::STATUS_SUCCESS . " THEN -player_delivery_record.amount ELSE 0 END) AS artificial_withdrawal_total,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_RECHARGE . " THEN player_delivery_record.amount ELSE 0 END) + SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " and player_delivery_record.withdraw_status = " . PlayerWithdrawRecord::STATUS_SUCCESS . " THEN -player_delivery_record.amount ELSE 0 END) AS total_amount
        ");
        $list = $baseQuery->with([
            'recommend_promoter.player',
            'national_promoter.level_list',
            'national_promoter.level_list.national_level'
        ])->forPage($page, $size)
            ->when(!empty($exAdminSortField) && !empty($exAdminSortBy),
                function ($query) use ($exAdminSortField, $exAdminSortBy) {
                    $query->orderBy($exAdminSortField, $exAdminSortBy);
                }, function ($query) {
                    $query->orderBy('player.id', 'desc');
                })
            ->groupBy('player.id')
            ->get()
            ->toArray();
        $formattedRecords = $playGameRecordBaseQuery
            ->whereIn('player_id', array_column($list, 'id'))
            ->selectRaw('player_id,SUM(bet) AS bet_total,SUM(diff) AS diff_total')
            ->groupBy('play_game_record.player_id')
            ->get()
            ->toArray();
        $total = $totalQuery ?? 0;
        $playGameRecord = [];
        foreach ($formattedRecords as $record) {
            $playGameRecord[$record['player_id']] = $record;
        }
        return Grid::create($list,
            function (Grid $grid) use ($total, $list, $playGameRecord, $channelArr, $exAdminFilter) {
                $grid->bordered(true);
                $grid->autoHeight();
                $grid->title(admin_trans('player.player_report'));
                $layout = Layout::create();
                $layout->row(function (Row $row) use ($exAdminFilter) {
                    $row->gutter([10, 0]);
                    $row->column(admin_view(plugin()->webman->getPath() . '/views/total_info.vue')->attrs([
                        'ex_admin_filter' => $exAdminFilter,
                        'type' => 'PlayerReport',
                    ]));
                })->style(['background' => '#fff']);
                $grid->header($layout);
                $grid->driver()->setPk('id');
                $grid->column('id', admin_trans('player.fields.id'))->align('center');
                $grid->column('phone', admin_trans('player.fields.phone'))->display(function ($val, $data) {
                    $image = isset($data['avatar'])
                        ? Avatar::create()->src($data['avatar'])
                        : Avatar::create()->icon(Icon::create('UserOutlined'));
                    return Html::create()->content([
                        $image,
                        Html::div()->content($data['phone'] ?? ''),
                    ]);
                })->align('center');
                $grid->column('uuid', admin_trans('player.fields.uuid'))->align('center');
                $grid->column('account', admin_trans('player.fields.account'))->align('center');
                $grid->column('department_id', admin_trans('player.fields.department_id'))->display(function ($val) use
                (
                    $channelArr
                ) {
                    return $channelArr[$val] ?? '';
                })->width('150px')->align('center');
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
                    return Html::create()->content($tags)->style([
                        'display' => 'inline-flex',
                        'text-align' => 'center'
                    ]);
                })->ellipsis(true)->width(200)->align('center');
                $grid->column('real_name', admin_trans('player.fields.real_name'))->align('center');
                $grid->column('level_list.national_level.name',
                    admin_trans('national_promoter.level_list.name'))->display(function ($value, $data) {
                    if (!empty($data['national_promoter']['level_list']['national_level'])) {
                        return $data['national_promoter']['level_list']['national_level']['name'] . $data['national_promoter']['level_list']['level'];
                    }
                    return '';
                });
                $grid->column('recommend_promoter.name', admin_trans('player.fields.recommend_promoter_name'))
                    ->display(function ($value, $data) {
                        if (isset($data['recommend_promoter'])) {
                            $promoterUuid = $data['recommend_promoter']['player']['uuid'] ?? '';
                            return Html::create(Str::limit($promoterUuid, 20, ' (...)'))
                                ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                                ->modal(
                                    [$this, 'playerInfo'],
                                    ['player_id' => $data['recommend_promoter']['player_id']]
                                )
                                ->width('60%')
                                ->title(admin_trans('player.fields.uuid') . ': ' . $promoterUuid);
                        } else {
                            return '暂无推广员';
                        }
                    })
                    ->align('center')->width(80);
                
                // 充值总点数
                $grid->column('recharge_total', admin_trans('player.recharge_total'))
                    ->display(function ($value) {
                        return Html::create(number_format($value, 2))->style(['color' => 'green']);
                    })
                    ->align('center')->sortable();
                // 玩家余额
                $grid->column('player_money', admin_trans('player_wallet_transfer.fields.player_amount'))
                    ->display(function ($value) {
                        return Html::create(number_format($value, 2))->style(['color' => 'green']);
                    })
                    ->align('center')->sortable();
                // 平台充值
                $grid->column('self_recharge_total', admin_trans('player.self_recharge_total'))
                    ->display(function ($value) {
                        return Html::create(number_format($value, 2))->style(['color' => 'green']);
                    })
                    ->align('center')->sortable();
                // 人工充值总点数
                $grid->column('artificial_recharge_total', admin_trans('player.artificial_recharge_total'))
                    ->display(function ($value) {
                        return Html::create(number_format($value, 2))->style(['color' => 'green']);
                    })
                    ->align('center')->sortable();
                // 提现总点数
                $grid->column('withdrawal_total', admin_trans('player.withdrawal_total'))
                    ->display(function ($value) {
                        return Html::create(number_format($value, 2))->style(['color' => 'red']);
                    })
                    ->align('center')->sortable();
                // 平台提现
                $grid->column('channel_withdrawal_total', admin_trans('player.channel_withdrawal_total'))
                    ->display(function ($value) {
                        return Html::create(number_format($value, 2))->style(['color' => 'red']);
                    })
                    ->align('center')->sortable();
                // 人工提现
                $grid->column('artificial_withdrawal_total', admin_trans('player.artificial_withdrawal_total'))
                    ->display(function ($value) {
                        return Html::create(number_format($value, 2))->style(['color' => 'red']);
                    })
                    ->align('center')->sortable();
                // 管理员加点
                $grid->column('modified_total', admin_trans('player.modified_total'))
                    ->display(function ($value) {
                        return Html::create(number_format($value, 2))->style(['color' => 'red']);
                    })
                    ->align('center')->sortable();
                // 总提现
                $grid->column('artificial_withdrawal_total', admin_trans('player.artificial_withdrawal_total'))
                    ->display(function ($value) {
                        return Html::create(number_format($value, 2))->style(['color' => 'red']);
                    })
                    ->align('center')->sortable();
                // 币商转入
                $grid->column('coin_transfer', admin_trans('player.coin_transfer'))
                    ->display(function ($value) {
                        return Html::create(number_format($value, 2))->style(['color' => 'green']);
                    })
                    ->align('center')->sortable();
                // 玩家转出
                $grid->column('coin_withdraw', admin_trans('player.coin_withdraw'))
                    ->display(function ($value) {
                        return Html::create(number_format($value, 2))->style(['color' => 'green']);
                    })
                    ->align('center')->sortable();
                // 机台上分
                $grid->column('machine_up_total', admin_trans('player.machine_up_total'))
                    ->display(function ($value) {
                        return Html::create(number_format($value, 2))->style(['color' => 'green']);
                    })
                    ->align('center')->sortable();
                // 机台下分
                $grid->column('machine_down_total', admin_trans('player.machine_down_total'))
                    ->display(function ($value) {
                        return Html::create(number_format($value, 2))->style(['color' => 'red']);
                    })
                    ->align('center')->sortable();
                // 机台盈利
                $grid->column('winn_los_total', admin_trans('player.winn_los_total'))
                    ->display(function ($value) {
                        return Html::create(number_format($value,
                            2))->style(['color' => $value >= 0 ? 'green' : 'red']);
                    })
                    ->align('center')->sortable();
                // 彩金
                $grid->column('lottery_total', admin_trans('player.lottery_total'))
                    ->display(function ($value) {
                        return Html::create(number_format($value, 2))->style(['color' => 'red']);
                    })
                    ->align('center')->sortable();
                // 活动
                $grid->column('activity_total', admin_trans('player.activity_total'))
                    ->display(function ($value) {
                        return Html::create(number_format($value, 2))->style(['color' => 'red']);
                    })
                    ->align('center')->sortable();
                // 总押注
                $grid->column('bet_total', admin_trans('player.artificial_withdrawal_total'))
                    ->display(function ($value) {
                        return Html::create(number_format($value, 2))->style(['color' => 'red']);
                    })
                    ->align('center')->sortable();
                // 电子游戏总押注
                $grid->column('bet_total', admin_trans('player.bet_total'))
                    ->display(function ($value, $data) use ($playGameRecord) {
                        $betTotal = 0;
                        if (!empty($playGameRecord[$data['id']])) {
                            $betTotal = $playGameRecord[$data['id']]['bet_total'];
                        }
                        return Html::create(number_format($betTotal, 2))->style(['color' => 'green']);
                    })
                    ->align('center');
                // 电子游戏输赢
                $grid->column('diff_total', admin_trans('player.diff_total'))
                    ->display(function ($value, $data) use ($playGameRecord) {
                        $diffTotal = 0;
                        if (!empty($playGameRecord[$data['id']])) {
                            $diffTotal = $playGameRecord[$data['id']]['diff_total'] * -1;
                        }
                        $color = $diffTotal <= 0 ? 'green' : 'red';
                        return Html::create(number_format($diffTotal, 2))->style(['color' => $color]);
                    })
                    ->align('center');
                // 总计金额
                $grid->column('total_amount', admin_trans('player.total_amount'))
                    ->display(function ($value) {
                        $color = $value >= 0 ? 'green' : 'red';
                        return Html::create(number_format($value, 2))->style(['color' => $color]);
                    })
                    ->align('center')->sortable();
                
                // 隐藏一些不需要的功能按钮
                $grid->hideDelete();
                $grid->hideSelection();
                $grid->expandFilter();
                $grid->actions(function (Actions $action, $data) use ($grid) {
                    $action->hideDel();
                    $action->hideEdit();
                    $action->prepend([
                        Button::create(admin_trans('machine_report.details'))
                            ->icon(Icon::create('UnorderedListOutlined'))
                            ->type('primary')
                            ->size('small')
                            ->modal('ex-admin/addons-webman-controller-ChannelPlayerController/playerRecord', [
                                ['id' => $data['id']],
                            ])->width('70%')->title(admin_trans('player.fields.uuid') . ': ' . $data['uuid'])
                    ]);
                });
                $grid->filter(function (Filter $filter) {
                    $filter->like()->text('uuid')->placeholder(admin_trans('player.fields.uuid'));
                    $filter->like()->text('phone')->placeholder(admin_trans('player.fields.phone'));
                    $filter->like()->text('real_name')->placeholder(admin_trans('player.fields.real_name'));
                    $filter->like()->text('account')->placeholder(admin_trans('player.fields.account'));
                    $filter->like()->text('recommend_promoter.name')->placeholder(admin_trans('player.fields.recommend_promoter_name'));
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
                    $filter->eq()->select('type')
                        ->placeholder(admin_trans('player_delivery_record.fields.type'))
                        ->showSearch()
                        ->style(['width' => '200px'])
                        ->dropdownMatchSelectWidth()
                        ->options([
                            PlayerDeliveryRecord::TYPE_RECHARGE => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_RECHARGE),
                            PlayerDeliveryRecord::TYPE_WITHDRAWAL => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_WITHDRAWAL),
                        ]);
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
                        ->placeholder(admin_trans('player.fields.department_id'))
                        ->remoteOptions(admin_url([
                            'addons-webman-controller-ChannelController',
                            'getDepartmentOptions'
                        ]));
                    $filter->form()->hidden('created_at_start');
                    $filter->form()->hidden('created_at_end');
                    $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                        admin_trans('public_msg.created_at_start'),
                        admin_trans('public_msg.created_at_end')
                    ]);
                });
                
                $grid->attr('is_mongo', true);
                $grid->attr('is_mongo_total', $total);
                $grid->attr('mongo_model', $list);
            });
    }
    
    /**
     * 玩家详情
     * @param $player_id
     * @return Detail
     */
    public function playerInfo($player_id): Detail
    {
        $player = Player::find($player_id);
        return Detail::create($player, function (Detail $detail) {
            $detail->item('name', admin_trans('player.fields.name'));
            $detail->item('phone', admin_trans('player.fields.phone'));
            $detail->item('uuid', admin_trans('player.fields.uuid'));
            $detail->item('is_promoter', admin_trans('player.fields.is_promoter'))->display(function (
                $value,
                Player $data
            ) {
                return Html::create()->content([
                    Tag::create($value == 1 ? admin_trans('player.promoter') : admin_trans('player.national_promoter'))->color($value == 1 ? 'red' : 'orange'),
                    $data->player_promoter->name ?? ''
                ]);
            });
            $detail->item('national_promoter.level_list.damage_rebate_ratio',
                admin_trans('national_promoter.level_list.damage_rebate_ratio'))->display(function (
                $value,
                Player $data
            ) {
                return floatval($value) . ' %';
            });
            $detail->item('national_promoter.level_list.recharge_ratio',
                admin_trans('national_promoter.level_list.recharge_ratio'))->display(function ($value, Player $data) {
                return floatval($value) . ' %';
            });
            $detail->item('recommend_player.name',
                admin_trans('player_promoter.fields.recommend_promoter_name'))->display(function (
                $value,
                Player $data
            ) {
                if (isset($data->recommend_player) && !empty($data->recommend_player)) {
                    return Html::create(Str::of($value)->limit(20, ' (...)'))
                        ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                        ->modal([$this, 'playerInfo'], ['player_id' => $data->recommend_player->id])
                        ->width('60%')->title(admin_trans('player.fields.phone') . ':' . $data->recommend_player->phone);
                }
                return '';
            });
            $detail->item('address', admin_trans('player_extend.fields.address'));
            $detail->item('line', admin_trans('player_extend.fields.line'));
            $detail->item('email', admin_trans('player_extend.fields.email'));
            $detail->item('created_at', admin_trans('player.fields.created_at'))->display(function ($val) {
                return date('Y-m-d H:i:s', strtotime($val));
            });
            $detail->item('machine_wallet.money',
                admin_trans('player_platform_cash.platform_name.' . PlayerPlatformCash::PLATFORM_SELF))->display(function (
                $val,
                Player $data
            ) {
                return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal([
                    ChannelPlayerController::class,
                    'playerRecord'
                ], ['id' => $data->id])->width('70%')->title($data->name . ' ' . $data->uuid);
            });
        })->bordered();
    }
}
