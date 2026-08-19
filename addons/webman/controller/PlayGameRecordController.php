<?php

namespace addons\webman\controller;

use addons\webman\model\PlayGameRecord;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\response\Notification;
use ExAdmin\ui\support\Request;


/**
 * 游戏游玩记录
 */
class PlayGameRecordController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.play_game_record_model');

    }

    /**
     * 玩家游戏记录
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $grid->title(admin_trans('play_game_record.title'));
            $exAdminFilter = Request::input('ex_admin_filter', []);

            // ✅ 添加结算状态筛选
            if (isset($exAdminFilter['settlement_status']) && $exAdminFilter['settlement_status'] !== '') {
                $grid->model()->where('settlement_status', $exAdminFilter['settlement_status']);
            }

            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            if (!empty($exAdminFilter['platform_id'])) {
                $grid->model()->where('platform_id', $exAdminFilter['platform_id']);
            }
            if (!empty($exAdminFilter['department_id'])) {
                $grid->model()->where('department_id', $exAdminFilter['department_id']);
            }
            if (!empty($exAdminFilter['game_code'])) {
                $grid->model()->where('game_code', $exAdminFilter['game_code']);
            }
            if (!empty($exAdminFilter['order_no'])) {
                $grid->model()->where('order_no', $exAdminFilter['order_no']);
            }
            if (!empty($exAdminFilter['player_uuid'])) {
                $grid->model()->where('player_uuid', 'like', $exAdminFilter['player_uuid'] . '%');
            }
            if (!empty($exAdminFilter['player_name'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('name', 'like', '%' . $exAdminFilter['player_name'] . '%');
                });
            }
            if (!empty($exAdminFilter['player_phone'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('phone', 'like', '%' . $exAdminFilter['player_phone'] . '%');
                });
            }

            if (isset($exAdminFilter['date_type'])) {
                $grid->model()->where(getDateWhere($exAdminFilter['date_type'], 'updated_at'));
            }
            if (!empty($exAdminFilter['action_at_start'])) {
                $grid->model()->where('platform_action_at', '>=', $exAdminFilter['action_at_start']);
            }
            if (!empty($exAdminFilter['action_at_end'])) {
                $grid->model()->where('platform_action_at', '<=', $exAdminFilter['action_at_end']);
            }
            if (isset($exAdminFilter['search_type'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('player_source', $exAdminFilter['search_type']);
                });
            }

            // 预加载关联数据，避免 N+1 查询问题
            $grid->model()->with(['player', 'channel', 'gamePlatform'])->orderBy('id', 'desc');
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($exAdminFilter) {
                $row->gutter([10, 0]);
                $row->column(admin_view(plugin()->webman->getPath() . '/views/total_info.vue')->attrs([
                    'ex_admin_filter' => $exAdminFilter,
                    'type' => 'PlayGameRecord',
                ]));
            })->style(['background' => '#fff']);
            $grid->header($layout);
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->hideDelete();
            $grid->hideDeleteSelection();
            $grid->hideSelection();
            $grid->column('id', admin_trans('play_game_record.fields.id'))->fixed(true)->align('center');
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))->display(function (
                $val,
                PlayGameRecord $data
            ) {
                if (!$data->player) {
                    return Html::create()->content([
                        Html::div()->content(admin_trans('common.data_not_found'))->style(['color' => '#999'])
                    ]);
                }
                return $data->player->uuid;
            })->align('center');
            $grid->column('player.name', admin_trans('player.fields.name'))->align('center')->display(function ($val, PlayGameRecord $data) {
                return $data->player ? $data->player->name : '';
            });
            $grid->column('player.phone', admin_trans('player.fields.phone'))->align('center')->display(function ($val, PlayGameRecord $data) {
                return $data->player ? $data->player->phone : '';
            });

            $grid->column('player.player_source', admin_trans('player.fields.player_source'))->display(function ($val, PlayGameRecord $data) {
                if (!$data->player) {
                    return Html::create()->content([
                        Tag::create(admin_trans('common.data_not_found'))->color('default')
                    ]);
                }

                return Html::create()->content([
                    $data->player->player_source == 1
                        ? Tag::create(admin_trans('player.fields.player_source_online'))->color('green')
                        : Tag::create(admin_trans('player.fields.player_source_offline'))->color('blue')
                ]);
            })->align('center');
            $grid->column('channel.name', admin_trans('channel.fields.name'))->align('center');
            $grid->column('platform_name', admin_trans('game_platform.fields.name'))->display(function (
                $val,
                PlayGameRecord $data
            ) {
                // 防御性编程：检查关联数据是否存在
                if (!$data->gamePlatform) {
                    return Html::create()->content([
                        Html::div()->content(admin_trans('common.data_not_found'))->style(['color' => '#999'])
                    ]);
                }

                return Html::create()->content([
                    Html::div()->content($data->gamePlatform->name),
                ]);
            })->align('center');
            $grid->column('order_no', admin_trans('play_game_record.fields.order_no'))->copy();
            $grid->column('game_code', admin_trans('play_game_record.fields.game_code'))->copy();
            $grid->column('bet', admin_trans('play_game_record.fields.bet'))->display(function ($val) {
                return Html::create()->content(['-' . $val])->style(['color' => '#cd201f']);
            })->sortable()->align('center');
            $grid->column('diff',
                admin_trans('play_game_record.fields.diff'))->display(function ($val) {
                if ((float)$val > 0) {
                    return Html::create()->content(['+', (float)$val])->style(['color' => 'green']);
                }
                return Html::create()->content([(float)$val])->style(['color' => '#cd201f']);
            })->sortable()->align('center');
            $grid->column('balance_before', admin_trans('play_game_record.fields.balance_before'))->display(function ($val) {
                return $val !== null ? (float)$val : 0;
            })->align('center');
            $grid->column('balance_after', admin_trans('play_game_record.fields.balance_after'))->display(function ($val) {
                return $val !== null ? (float)$val : 0;
            })->align('center');
            $grid->column('reward', admin_trans('play_game_record.fields.reward'))->display(function ($val) {
                return Html::create()->content(['+' . (float)$val])->style(['color' => 'green']);
            })->align('center');

            // VIP等级
            $grid->column('vip_level_id', admin_trans('play_game_record.fields.vip_level_id'))->display(function ($val, PlayGameRecord $data) {
                if ($data->player && $data->player->vipLevel) {
                    return Tag::create($data->player->vipLevel->name)->color('purple');
                }
                return $val ? Tag::create('VIP' . $val)->color('purple') : '-';
            })->align('center');

            // 反水比例
            $grid->column('cashback_ratio', admin_trans('play_game_record.fields.cashback_ratio'))->display(function ($val) {
                if ($val !== null && $val > 0) {
                    return number_format((float)$val, 4) . '%';
                }
                return '-';
            })->align('center');

            // 反水金额
            $grid->column('cashback_amount', admin_trans('play_game_record.fields.cashback_amount'))->display(function ($val) {
                if ($val !== null && $val > 0) {
                    return Html::create()->content(['+' . number_format((float)$val, 4)])->style(['color' => 'green']);
                }
                return '-';
            })->align('center');

            // ✅ 添加结算状态显示列
            $grid->column('settlement_status', admin_trans('play_game_record.fields.settlement_status'))->display(function ($val) {
                switch ($val) {
                    case PlayGameRecord::SETTLEMENT_STATUS_SETTLED:
                        return Tag::create(admin_trans('play_game_record.settlement_status.' . PlayGameRecord::SETTLEMENT_STATUS_SETTLED))->color('success');
                    case PlayGameRecord::SETTLEMENT_STATUS_UNSETTLED:
                        return Tag::create(admin_trans('play_game_record.settlement_status.' . PlayGameRecord::SETTLEMENT_STATUS_UNSETTLED))->color('warning');
                    case PlayGameRecord::SETTLEMENT_STATUS_CANCELLED:
                        return Tag::create(admin_trans('play_game_record.settlement_status.' . PlayGameRecord::SETTLEMENT_STATUS_CANCELLED))->color('default');
                    case PlayGameRecord::SETTLEMENT_STATUS_CONFIRM:
                        return Tag::create(admin_trans('play_game_record.settlement_status.' . PlayGameRecord::SETTLEMENT_STATUS_CONFIRM))->color('processing');
                    default:
                        return Tag::create(admin_trans('common.unknown'))->color('error');
                }
            })->align('center');

            $grid->column('platform_action_at', admin_trans('play_game_record.fields.platform_action_at'))->display(function ($val, PlayGameRecord $data) {
                if($data->platform_id == 31){
                    $val = date('Y-m-d H:i:s', strtotime($data->platform_action_at));
                }
                return $val;
            })->align('center');
            $grid->column('created_at', admin_trans('play_game_record.fields.create_at'))->align('center');
            $grid->column('action_at', admin_trans('play_game_record.fields.action_at'))->align('center');
            $grid->actions(function (Actions $action,$data) {
                $action->hideDel();
                // 回放功能
                if (!empty($data->gamePlatform)) {
                    try {
                        $service = new \addons\webman\service\GamePlatformService();
                        $url = $service->replay($data->id);
                        if (!empty($url)) {
                            $action->prepend(
                                Button::create(admin_trans('play_game_record.replay'))->ajax([$this, 'replay'],
                                    ['url' => $url])
                            );
                        }
                    } catch (\Exception $e) {
                        // 如果平台不支持回放，忽略错误
                    }
                }
            });
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player_uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->like()->text('player_name')->placeholder(admin_trans('player.fields.name'));
                $filter->like()->text('player_phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('order_no')->placeholder(admin_trans('play_game_record.fields.order_no'));
                $filter->like()->text('game_code')->placeholder(admin_trans('play_game_record.fields.game_code'));

                // ✅ 修改为结算状态筛选
                $filter->eq()->select('settlement_status')
                    ->placeholder(admin_trans('play_game_record.fields.settlement_status'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        PlayGameRecord::SETTLEMENT_STATUS_UNSETTLED => admin_trans('play_game_record.settlement_status.' . PlayGameRecord::SETTLEMENT_STATUS_UNSETTLED),
                        PlayGameRecord::SETTLEMENT_STATUS_SETTLED => admin_trans('play_game_record.settlement_status.' . PlayGameRecord::SETTLEMENT_STATUS_SETTLED),
                        PlayGameRecord::SETTLEMENT_STATUS_CANCELLED => admin_trans('play_game_record.settlement_status.' . PlayGameRecord::SETTLEMENT_STATUS_CANCELLED),
                        PlayGameRecord::SETTLEMENT_STATUS_CONFIRM => admin_trans('play_game_record.settlement_status.' . PlayGameRecord::SETTLEMENT_STATUS_CONFIRM)
                    ]);
                $filter->select('search_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.player_source'))
                    ->options([
                        1 => admin_trans('player.fields.player_source_online'),
                        2 => admin_trans('player.fields.player_source_offline'),
                    ]);
                $filter->eq()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('channel.fields.name'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
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
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
                $filter->form()->dateTimeRange('action_at_start', 'action_at_end', '')->placeholder([
                    admin_trans('public_msg.action_at_start'),
                    admin_trans('public_msg.action_at_end')
                ]);
            });
            $grid->expandFilter();
        });
    }

    /**
     * 回放
     * @auth true
     * @param $url
     * @return Notification
     */
    public function replay($url) : Notification
    {
        return notification_success(admin_trans('admin.success'),
            admin_trans('game_platform.action_success'))->redirect($url);
    }
}
