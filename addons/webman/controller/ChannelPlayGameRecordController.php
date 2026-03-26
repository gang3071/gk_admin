<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\PlayGameRecord;
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
 * 游戏游玩记录
 */
class ChannelPlayGameRecordController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.play_game_record_model');

    }

    /**
     * 玩家游戏记录
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $grid->title(admin_trans('play_game_record.title'));
            $grid->model()->with(['player'])->where('department_id', Admin::user()->department_id)->orderBy('id',
                'desc');
            $exAdminFilter = Request::input('ex_admin_filter', []);

            /** @var \addons\webman\model\AdminUser $admin */
            $admin = Admin::user();
            if ($admin->type === \addons\webman\model\AdminUser::TYPE_AGENT) {
                // 代理：查询所有下级店家的玩家记录
                $storeIds = $admin->childStores()->where('type', \addons\webman\model\AdminUser::TYPE_STORE)->pluck('id');
                $playerIds = \addons\webman\model\Player::query()->whereIn('store_admin_id', $storeIds)->pluck('id');
                $grid->model()->whereIn('player_id', $playerIds);
            } elseif ($admin->type === \addons\webman\model\AdminUser::TYPE_STORE) {
                // 店家：查询自己的玩家记录
                $playerIds = \addons\webman\model\Player::query()->where('store_admin_id', $admin->id)->pluck('id');
                $grid->model()->whereIn('player_id', $playerIds);
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
                $grid->model()->where('game_code', $exAdminFilter['game_code']);
            }
            if (!empty($exAdminFilter['order_no'])) {
                $grid->model()->where('order_no', $exAdminFilter['order_no']);
            }
            if (!empty($exAdminFilter['player_uuid'])) {
                $grid->model()->where('player_uuid', 'like', $exAdminFilter['player_uuid'] . '%');
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
                    $query->where('is_test', $exAdminFilter['search_type']);
                });
            }
            $grid->model()->orderBy('id', 'desc');
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($exAdminFilter) {
                $row->gutter([10, 0]);
                $row->column(admin_view(plugin()->webman->getPath() . '/views/total_info.vue')->attrs([
                    'ex_admin_filter' => $exAdminFilter,
                    'type' => 'PlayGameRecord',
                    'department_id' => Admin::user()->department_id,
                    'admin_user_id' => Admin::user()->id,
                ]));
            })->style(['background' => '#fff']);
            $grid->header($layout);
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->hideDelete();
            $grid->hideDeleteSelection();
            $grid->hideSelection();
            $grid->column('id', admin_trans('play_game_record.fields.id'))->fixed(true)->align('center');
            $grid->column('player.name', admin_trans('player.fields.account'))->display(function (
                $val,
                PlayGameRecord $data
            ) {
                $image = $data->player->avatar ? Avatar::create()->src(is_numeric($data->player->avatar) ? config('def_avatar.' . $data->player->avatar) : $data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($data->player->name)
                ]);
            })->fixed(true)->align('center');
            $grid->column('player.uuid',
                admin_trans('player.fields.uuid'))->fixed(true)->ellipsis(true)->align('center');
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function ($val, PlayGameRecord $data) {
                return Html::create()->content([
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                ]);
            })->fixed(true)->align('center');
            $grid->column('channel.name', admin_trans('channel.fields.name'))->align('center');
            $grid->column('platform_name', admin_trans('game_platform.fields.name'))->display(function (
                $val,
                PlayGameRecord $data
            ) {
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
            $grid->column('reward', admin_trans('play_game_record.fields.reward'))->display(function ($val) {
                return Html::create()->content(['+' . $val])->style(['color' => 'green']);
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
     * @group channel
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
