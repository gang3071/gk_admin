<?php

namespace addons\webman\controller;

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
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\Divider;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\support\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * 玩家报表
 */
class ChannelPlayerReportController
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
        $baseQuery = Player::query()->withTrashed();
        $playGameRecordBaseQuery = PlayGameRecord::query()
            ->where('play_game_record.settlement_status', PlayGameRecord::SETTLEMENT_STATUS_SETTLED)  // ✅ 只统计已结算记录
            ->when(!empty($exAdminFilter['uuid']) || !empty($exAdminFilter['real_name']) || !empty($exAdminFilter['phone']) || !empty($exAdminFilter['recommend_promoter']['name']) || (!empty($exAdminFilter['search_is_promoter']) && in_array($exAdminFilter['search_is_promoter'],
                        [0, 1])) || !empty($exAdminFilter['search_type']), function (Builder $q) use ($exAdminFilter) {
                $q->leftjoin('player', 'play_game_record.player_id', '=', 'player.id');
            });
        $playerDeliveryRecordBaseQuery = PlayerDeliveryRecord::query()->leftjoin('player',
            'player_delivery_record.player_id', '=', 'player.id');
        if (!empty($exAdminFilter)) {
            // UUID 筛选
            if (!empty($exAdminFilter['uuid'])) {
                $baseQuery->where('player.uuid', 'like', '%' . $exAdminFilter['uuid'] . '%');
                $playGameRecordBaseQuery->where('player.uuid', 'like', '%' . $exAdminFilter['uuid'] . '%');
                $playerDeliveryRecordBaseQuery->where('player.uuid', 'like', '%' . $exAdminFilter['uuid'] . '%');
            }
            if (!empty($exAdminFilter['phone'])) {
                $baseQuery->where('player.phone', 'like', '%' . $exAdminFilter['phone'] . '%');
                $playGameRecordBaseQuery->where('player.phone', 'like', '%' . $exAdminFilter['phone'] . '%');
                $playerDeliveryRecordBaseQuery->where('player.phone', 'like', '%' . $exAdminFilter['phone'] . '%');
            }
            // 推广员筛选（优化：三层 whereHas → 两层 JOIN）
            if (!empty($exAdminFilter['recommend_promoter']['name'])) {
                // baseQuery: player.recommend_id → player.id
                $baseQuery
                    ->leftJoin('player_promoter as bp', 'player.recommend_id', '=', 'bp.player_id')
                    ->leftJoin('player as rp', 'bp.player_id', '=', 'rp.id')
                    ->where(function ($q) use ($exAdminFilter) {
                        $q->where('rp.uuid', 'like', '%' . $exAdminFilter['recommend_promoter']['name'] . '%')
                            ->orWhere('rp.name', 'like', '%' . $exAdminFilter['recommend_promoter']['name'] . '%');
                    });

                // playGameRecordBaseQuery: play_game_record.parent_player_id → player_promoter.player_id → player.id
                $playGameRecordBaseQuery
                    ->leftJoin('player_promoter as pgr_bp', 'play_game_record.parent_player_id', '=', 'pgr_bp.player_id')
                    ->leftJoin('player as pgr_rp', 'pgr_bp.player_id', '=', 'pgr_rp.id')
                    ->where(function ($q) use ($exAdminFilter) {
                        $q->where('pgr_rp.uuid', 'like', '%' . $exAdminFilter['recommend_promoter']['name'] . '%')
                            ->orWhere('pgr_rp.name', 'like', '%' . $exAdminFilter['recommend_promoter']['name'] . '%');
                    });

                // playerDeliveryRecordBaseQuery: 优化三层 whereHas → 两层 JOIN
                $playerDeliveryRecordBaseQuery
                    ->leftJoin('player_promoter as pdr_bp', 'player.recommend_id', '=', 'pdr_bp.player_id')
                    ->leftJoin('player as pdr_rp', 'pdr_bp.player_id', '=', 'pdr_rp.id')
                    ->where(function ($q) use ($exAdminFilter) {
                        $q->where('pdr_rp.uuid', 'like', '%' . $exAdminFilter['recommend_promoter']['name'] . '%')
                            ->orWhere('pdr_rp.name', 'like', '%' . $exAdminFilter['recommend_promoter']['name'] . '%');
                    });
            }
            // is_promoter 筛选（优化：whereHas → WHERE）
            if (!empty($exAdminFilter['search_is_promoter']) && in_array($exAdminFilter['search_is_promoter'], [0, 1])) {
                $baseQuery->where('player.is_promoter', $exAdminFilter['search_is_promoter']);
                $playGameRecordBaseQuery->where('player.is_promoter', $exAdminFilter['search_is_promoter']);
                $playerDeliveryRecordBaseQuery->where('player.is_promoter', $exAdminFilter['search_is_promoter']);
            }
            if (!empty($exAdminFilter['search_type'])) {
                $baseQuery->where('player.is_test', $exAdminFilter['search_type']);
                $playGameRecordBaseQuery->where('player.is_test', $exAdminFilter['search_type']);
                $playerDeliveryRecordBaseQuery->where('player.is_test', $exAdminFilter['search_type']);
            }
        }
        $totalQuery = $baseQuery->clone()->count('*');
        if (!empty($exAdminFilter)) {
            if (!empty($exAdminFilter['created_at_start'])) {
                $playGameRecordBaseQuery->where('play_game_record.created_at', '>=',
                    $exAdminFilter['created_at_start']);
                $playerDeliveryRecordBaseQuery->where('player_delivery_record.created_at', '>=',
                    $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $playGameRecordBaseQuery->where('play_game_record.created_at', '<=', $exAdminFilter['created_at_end']);
                $playerDeliveryRecordBaseQuery->where('player_delivery_record.created_at', '<=',
                    $exAdminFilter['created_at_end']);
            }
            if (!empty($exAdminFilter['type'])) {
                $playerDeliveryRecordBaseQuery->where('player_delivery_record.type', $exAdminFilter['type']);
            }
            if (isset($exAdminFilter['date_type'])) {
                $playGameRecordBaseQuery->where(getDateWhere($exAdminFilter['date_type'],
                    'play_game_record.created_at'));
                $playerDeliveryRecordBaseQuery->where(getDateWhere($exAdminFilter['date_type'],
                    'player_delivery_record.created_at'));
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

        // ⚡ 性能优化：统计数据改为异步加载（参考 jin 分支）
        // 好处：
        //   1. 减少主查询的响应时间（列表加载更快）
        //   2. 统计数据按需加载（用户展开面板时才查询）
        //   3. 支持独立刷新统计数据（不需要重新加载整个列表）
        // 统计逻辑已移至 Login::totalInfo() 的 PlayerReport case

        // ⚡ 性能优化：简化 SELECT 语句，减少重复计算（参考 jin 分支）
        // 原逻辑：SELECT 中重复计算 winn_los_total（重复使用 machine_down_total、machine_up_total 等）
        // 优化后：在 PHP 中计算衍生字段（更高效，逻辑更清晰）
        $baseQuery
            ->selectRaw("
            player.*,
            -- 基础统计
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD . " THEN player_delivery_record.amount ELSE 0 END) AS modified_total,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_RECHARGE . " THEN player_delivery_record.amount ELSE 0 END) AS recharge_total,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS . " THEN player_delivery_record.amount ELSE 0 END) AS activity_total,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_LOTTERY . " THEN player_delivery_record.amount ELSE 0 END) AS lottery_total,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MACHINE_UP . " THEN player_delivery_record.amount ELSE 0 END) AS machine_up_total,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MACHINE_DOWN . " THEN player_delivery_record.amount ELSE 0 END) AS machine_down_total,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_PRESENT_IN . " THEN player_delivery_record.amount ELSE 0 END) AS coin_withdraw,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_PRESENT_OUT . " THEN player_delivery_record.amount ELSE 0 END) AS coin_transfer,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_MACHINE . " THEN player_delivery_record.amount ELSE 0 END) AS machine_chip_total,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " and player_delivery_record.withdraw_status = " . PlayerWithdrawRecord::STATUS_SUCCESS . " THEN -player_delivery_record.amount ELSE 0 END) AS withdrawal_total,

            -- 细分充提统计
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_RECHARGE . " and player_delivery_record.source in ('self_recharge','gb_recharge') THEN player_delivery_record.amount ELSE 0 END) AS self_recharge_total,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_RECHARGE . " and player_delivery_record.source = 'artificial_recharge' THEN player_delivery_record.amount ELSE 0 END) AS artificial_recharge_total,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " and player_delivery_record.source in ('channel_withdrawal', 'gb_withdrawal') and player_delivery_record.withdraw_status = " . PlayerWithdrawRecord::STATUS_SUCCESS . " THEN -player_delivery_record.amount ELSE 0 END) AS channel_withdrawal_total,
            SUM(CASE WHEN player_delivery_record.type = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " and player_delivery_record.source = 'artificial_withdrawal' and player_delivery_record.withdraw_status = " . PlayerWithdrawRecord::STATUS_SUCCESS . " THEN -player_delivery_record.amount ELSE 0 END) AS artificial_withdrawal_total
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

        // ✅ 边界条件：空列表时不执行查询
        $playGameRecord = [];
        if (!empty($list)) {
            $formattedRecords = $playGameRecordBaseQuery
                ->whereIn('player_id', array_column($list, 'id'))
                ->selectRaw('player_id,SUM(bet) AS bet_total,SUM(diff) AS diff_total')
                ->groupBy('play_game_record.player_id')
                ->get()
                ->toArray();

            foreach ($formattedRecords as $record) {
                $playGameRecord[$record['player_id']] = $record;
            }
        }

        $total = $totalQuery ?? 0;

        // ⚡ 性能优化：在 PHP 中计算衍生字段，避免 SQL 中重复计算（参考 jin 分支）
        // 原逻辑：SELECT 中计算 winn_los_total 和 total_amount（重复计算 machine_down_total 等）
        // 优化后：在 PHP 中计算（更高效，逻辑更清晰）
        foreach ($list as &$player) {
            // 机台盈利 = 机台下分 - 机台上分 - 彩金 - 活动奖励
            $player['winn_los_total'] =
                $player['machine_down_total'] -
                $player['machine_up_total'] -
                $player['lottery_total'] -
                $player['activity_total'];

            // 总计金额 = 充值 + 投钞 + 提现
            $player['total_amount'] =
                $player['recharge_total'] +
                $player['machine_chip_total'] +
                $player['withdrawal_total'];
        }
        unset($player); // 释放引用
        return Grid::create($list, function (Grid $grid) use ($total, $list, $playGameRecord, $exAdminFilter) {
            $grid->bordered(true);
            $grid->autoHeight();
            $grid->title(admin_trans('player.player_report'));

            // ⚡ 使用 Vue 组件异步加载统计数据（参考 jin 分支）
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($exAdminFilter) {
                $row->gutter([10, 0]);
                // 使用 Vue 组件异步加载统计数据（参考 ChannelPlayGameRecordController）
                $row->column(admin_view(plugin()->webman->getPath() . '/views/total_info.vue')->attrs([
                    'ex_admin_filter' => $exAdminFilter,
                    'type' => 'PlayerReport',
                    'department_id' => Admin::user()->department_id,
                    'admin_user_id' => Admin::user()->id,
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
            $grid->column('type', admin_trans('player.fields.type'))->display(function ($val, $data) {
                return Html::create()->content([
                    $data['is_test'] == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                ]);
            })->align('center');
            $grid->column('name', admin_trans('player.fields.device_name'))->align('center');
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
                        return admin_trans('player.no_promoter');
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
            // 投钞总额
            $grid->column('machine_chip_total', admin_trans('player.machine_chip_total'))
                ->display(function ($value) {
                    return Html::create(number_format($value, 2))->style(['color' => 'green']);
                })
                ->align('center')->sortable();
            // 机台盈利
            $grid->column('winn_los_total', admin_trans('player.winn_los_total'))
                ->display(function ($value) {
                    return Html::create(number_format($value, 2))->style(['color' => $value >= 0 ? 'green' : 'red']);
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
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });

            // 使用 Arrays driver（必需，因为数据是手动查询的，不是通过 model()）
            // 导出器的 save() 返回相对路径 /storage/xxx.xlsx，Arrays driver 会包装成 download URL
            $grid->attr('is_mongo', true);
            $grid->attr('is_mongo_total', $total);
            $grid->attr('mongo_model', $list);

            // 导出功能（权限通过 @auth true 控制）
            $grid->export(new \addons\webman\grid\ChannelPlayerReportExporter())
                ->filename('player_report_' . date('YmdHis'));
        });
    }

    /**
     * 导出玩家报表
     * @auth true
     * @return void
     */
    public function export()
    {
        // 此方法仅用于权限控制，实际导出由 Grid 的 export 功能处理
        // ExAdmin 会自动调用 ChannelPlayerReportExporter
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
