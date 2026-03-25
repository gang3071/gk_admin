<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\PlayGameRecord;
use app\service\game\GameServiceFactory;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\response\Notification;
use ExAdmin\ui\support\Request;

/**
 * 代理后台 - 游戏记录
 * @group agent
 */
class AgentPlayGameRecordController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.play_game_record_model');
    }

    /**
     * 游戏记录列表
     * @group agent
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $grid->title(admin_trans('play_game_record.title'));
            $grid->bordered(true);
            $grid->autoHeight();

            /** @var \addons\webman\model\AdminUser $admin */
            $admin = Admin::user();

            // 代理：只查询所有下级店家的玩家记录
            $storeIds = $admin->childStores()->where('type', \addons\webman\model\AdminUser::TYPE_STORE)->pluck('id');
            $playerIds = \addons\webman\model\Player::query()->whereIn('store_admin_id', $storeIds)->pluck('id');

            $grid->model()->with(['player', 'gamePlatform'])
                ->whereIn('player_id', $playerIds)
                ->orderBy('id', 'desc');

            $exAdminFilter = Request::input('ex_admin_filter', []);

            // 所属店家筛选
            if (!empty($exAdminFilter['player']['store_admin_id'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('store_admin_id', $exAdminFilter['player']['store_admin_id']);
                });
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
            if (!empty($exAdminFilter['game_code'])) {
                $grid->model()->where('game_code', 'like', '%' . $exAdminFilter['game_code'] . '%');
            }
            if (!empty($exAdminFilter['order_no'])) {
                $grid->model()->where('order_no', 'like', '%' . $exAdminFilter['order_no'] . '%');
            }
            if (!empty($exAdminFilter['player']['name'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('name', 'like', '%' . $exAdminFilter['player']['name'] . '%');
                });
            }
            if (!empty($exAdminFilter['player']['uuid'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('uuid', 'like', $exAdminFilter['player']['uuid'] . '%');
                });
            }
            if (isset($exAdminFilter['status']) && $exAdminFilter['status'] != null) {
                $grid->model()->where('status', $exAdminFilter['status']);
            }
            if (isset($exAdminFilter['date_type'])) {
                $grid->model()->where(getDateWhere($exAdminFilter['date_type'], 'created_at'));
            }
            if (!empty($exAdminFilter['action_at_start'])) {
                $grid->model()->where('platform_action_at', '>=', $exAdminFilter['action_at_start']);
            }
            if (!empty($exAdminFilter['action_at_end'])) {
                $grid->model()->where('platform_action_at', '<=', $exAdminFilter['action_at_end']);
            }
            if (isset($exAdminFilter['search_type'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('is_test', $exAdminFilter['search_type']);
                });
            }

            // 统计数据
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($exAdminFilter, $admin) {
                $row->gutter([10, 0]);
                $row->column(admin_view(plugin()->webman->getPath() . '/views/total_info.vue')->attrs([
                    'ex_admin_filter' => $exAdminFilter,
                    'type' => 'PlayGameRecord',
                    'admin_user_id' => $admin->id,
                ]));
            })->style(['background' => '#fff']);
            $grid->header($layout);

            $grid->column('id', admin_trans('play_game_record.fields.id'))->fixed(true)->align('center')->width(80);
            $grid->column('player.name', admin_trans('player.fields.device_name'))->align('center')->width(120);
            $grid->column('player.uuid', admin_trans('player.fields.device_uuid'))->fixed(true)->copy()->align('center')->width(150);
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function ($val, PlayGameRecord $data) {
                return Html::create()->content([
                    $data->player->is_test == 1
                        ? Tag::create(admin_trans('player.fields.is_test'))->color('red')
                        : Tag::create(admin_trans('player.player'))->color('green')
                ]);
            })->fixed(true)->align('center')->width(100);
            $grid->column('player.storeAdmin.nickname', admin_trans('admin.store'))->display(function (
                $val,
                PlayGameRecord $data
            ) {
                return $data->player->storeAdmin->nickname ?? $data->player->storeAdmin->username ?? '';
            })->width(150)->align('center');
            $grid->column('platform_name', admin_trans('game_platform.fields.name'))->display(function (
                $val,
                PlayGameRecord $data
            ) {
                return Html::create()->content([
                    Html::div()->content($data->gamePlatform->name ?? ''),
                ]);
            })->align('center')->width(120);
            $grid->column('order_no', admin_trans('play_game_record.fields.order_no'))->copy()->width(200);
            $grid->column('game_code', admin_trans('play_game_record.fields.game_code'))->copy()->width(150);
            $grid->column('bet', admin_trans('play_game_record.fields.bet'))->display(function ($val) {
                return Html::create()->content(['-' . $val])->style(['color' => '#cd201f']);
            })->sortable()->align('center')->width(120);
            $grid->column('diff', admin_trans('play_game_record.fields.diff'))->display(function ($val) {
                if ((float)$val > 0) {
                    return Html::create()->content(['+', (float)$val])->style(['color' => 'green']);
                }
                return Html::create()->content([(float)$val])->style(['color' => '#cd201f']);
            })->sortable()->align('center')->width(120);
            $grid->column('reward', admin_trans('play_game_record.fields.reward'))->display(function ($val) {
                return Html::create()->content(['+' . $val])->style(['color' => 'green']);
            })->align('center')->width(120);
            $grid->column('platform_action_at', admin_trans('play_game_record.fields.platform_action_at'))->display(function ($val, PlayGameRecord $data) {
                if ($data->platform_id == 31) {
                    $val = date('Y-m-d H:i:s', strtotime($data->platform_action_at));
                }
                return $val;
            })->align('center')->width(160);
            $grid->column('created_at', admin_trans('play_game_record.fields.create_at'))->align('center')->width(160);
            $grid->column('action_at', admin_trans('play_game_record.fields.action_at'))->align('center')->width(160);

            $grid->filter(function (Filter $filter) use ($admin) {
                // 所属店家筛选
                $filter->eq()->select('player.store_admin_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('admin.store'))
                    ->remoteOptions(admin_url([ChannelAgentController::class, 'getStoreOptions']));

                $filter->like()->text('player.name')->placeholder(admin_trans('player.fields.device_name'));
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.device_uuid'));
                $filter->like()->text('order_no')->placeholder(admin_trans('play_game_record.fields.order_no'));
                $filter->like()->text('game_code')->placeholder(admin_trans('play_game_record.fields.game_code'));

                $filter->eq()->select('status')
                    ->placeholder(admin_trans('play_game_record.fields.status'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        PlayGameRecord::STATUS_UNSETTLED => admin_trans('play_game_record.status.' . PlayGameRecord::STATUS_UNSETTLED),
                        PlayGameRecord::STATUS_SETTLED => admin_trans('play_game_record.status.' . PlayGameRecord::STATUS_SETTLED)
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

                $filter->form()->hidden('action_at_start');
                $filter->form()->hidden('action_at_end');
                $filter->form()->dateTimeRange('action_at_start', 'action_at_end', '')->placeholder([
                    admin_trans('public_msg.action_at_start'),
                    admin_trans('public_msg.action_at_end')
                ]);
            });

            $grid->actions(function (Actions $action, $data) {
                $action->hideDel();
                $action->hideEdit();
                if (!empty($data->gamePlatform) && !empty($data->gamePlatform->code)) {
                    $url = GameServiceFactory::createService(strtoupper($data->gamePlatform->code))->replay($data->toArray());
                    if (!empty($url)) {
                        $action->prepend(
                            Button::create(admin_trans('play_game_record.replay'))->ajax([$this, 'replay'], ['url' => $url])
                        );
                    }
                }
            })->align('center');

            $grid->hideDelete();
            $grid->hideSelection();
            $grid->expandFilter();
        });
    }

    /**
     * 回放
     * @group agent
     * @auth true
     * @param $url
     * @return Notification
     */
    public function replay($url): Notification
    {
        return notification_success(admin_trans('admin.success'), admin_trans('game_platform.action_success'))->redirect($url);
    }
}
