<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerWalletTransfer;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\support\Request;
use Illuminate\Support\Carbon;


/**
 * 玩家钱包转入/出
 */
class ChannelPlayerWalletTransferController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_wallet_transfer_model');

    }

    /**
     * 转入/出记录
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $grid->title(admin_trans('player_wallet_transfer.title'));
            $exAdminFilter = Request::input('ex_admin_filter', []);
            $grid->model()->orderBy('id', 'desc');

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
                $grid->model()->where('created_at', '>=',
                    Carbon::parse($exAdminFilter['created_at_start'])->startOfDay()->toDateTimeString());
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=',
                    Carbon::parse($exAdminFilter['created_at_end'])->endOfDay()->toDateTimeString());
            }
            if (!empty($exAdminFilter['platform_id'])) {
                $grid->model()->where('platform_id', $exAdminFilter['platform_id']);
            }
            if (!empty($exAdminFilter['platform_no'])) {
                $grid->model()->where('platform_no', $exAdminFilter['platform_no']);
            }
            if (!empty($exAdminFilter['tradeno'])) {
                $grid->model()->where('tradeno', $exAdminFilter['tradeno']);
            }
            if (!empty($exAdminFilter['type'])) {
                $grid->model()->where('type', $exAdminFilter['type']);
            }
            if (!empty($exAdminFilter['player']['name'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('name', 'like', '%' . $exAdminFilter['player']['name'] . '%');
                });
            }
            if (!empty($exAdminFilter['player']['uuid'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('uuid', 'like', '%' . $exAdminFilter['player']['uuid'] . '%');
                });
            }
            if (isset($exAdminFilter['date_type'])) {
                $grid->model()->where(getDateWhere($exAdminFilter['date_type'], 'created_at'));
            }
            if (isset($exAdminFilter['search_type'])) {
                $grid->model()->whereHas('player', function ($query) use ($exAdminFilter) {
                    $query->where('is_test', $exAdminFilter['search_type']);
                });
            }
            $query = clone $grid->model();
            $totalData = $query->selectRaw('sum(if(`type`=1,`amount`,0)) as all_out_amount, sum(if(`type`=2,`amount`,0)) as all_in_amount')->first();
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['all_out_amount']) ? floatval($totalData['all_out_amount']) : 0)->prefix(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_GAME_PLATFORM_OUT))->valueStyle([
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
                    , 6);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()->value(!empty($totalData['all_in_amount']) ? floatval($totalData['all_in_amount']) : 0)->prefix(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_GAME_PLATFORM_IN))->valueStyle([
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
                    , 6);
            })->style(['background' => '#fff']);
            $grid->tools([$layout]);
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->hideAction();
            $grid->hideDelete();
            $grid->hideDeleteSelection();
            $grid->hideSelection();
            $grid->column('id', admin_trans('player_wallet_transfer.fields.id'))->fixed(true)->align('center')->sortable();
            $grid->column('player.name', admin_trans('player.fields.account'))->display(function (
                $val,
                PlayerWalletTransfer $data
            ) {
                $image = $data->player->avatar ? Avatar::create()->src(is_numeric($data->player->avatar) ? config('def_avatar.' . $data->player->avatar) : $data->player->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val)
                ]);
            })->fixed(true)->align('center');
            $grid->column('player.uuid',
                admin_trans('player.fields.uuid'))->fixed(true)->ellipsis(true)->align('center');
            $grid->column('player.type', admin_trans('player.fields.type'))->display(function ($val, PlayerWalletTransfer $data) {
                return Html::create()->content([
                    $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : Tag::create(admin_trans('player.player'))->color('green')
                ]);
            })->fixed(true)->align('center');
            $grid->column('channel.name',
                admin_trans('player_wallet_transfer.fields.department_name'))->align('center');
            $grid->column('platform_name',
                admin_trans('player_wallet_transfer.fields.platform_name'))->display(function (
                $val,
                PlayerWalletTransfer $data
            ) {
                return Html::create()->content([
                    Html::div()->content($data->gamePlatform->code),
                ]);
            })->fixed(true)->align('center');
            $grid->column('tradeno', admin_trans('player_wallet_transfer.fields.tradeno'))->copy()->sortable();
            $grid->column('platform_no', admin_trans('player_wallet_transfer.fields.platform_no'))->copy()->sortable();
            $grid->column('amount', admin_trans('player_wallet_transfer.fields.amount'))->display(function (
                $val,
                PlayerWalletTransfer $data
            ) {
                switch ($data->type) {
                    case PlayerWalletTransfer::TYPE_OUT:
                        return Html::create()->content(['-' . $val])->style(['color' => '#cd201f']);
                    default:
                        return Html::create()->content(['+' . $val])->style(['color' => 'green']);
                }
            })->align('center');
            $grid->column('game_amount', admin_trans('player_wallet_transfer.fields.game_amount'))->align('center')->sortable();
            $grid->column('player_amount', admin_trans('player_wallet_transfer.fields.player_amount'))->align('center')->sortable();
            $grid->column('created_at', admin_trans('player_wallet_transfer.fields.create_at'))->align('center')->sortable();
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->like()->text('platform_no')->placeholder(admin_trans('player_wallet_transfer.fields.platform_no'));
                $filter->like()->text('tradeno')->placeholder(admin_trans('player_wallet_transfer.fields.tradeno'));
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
                    ->placeholder(admin_trans('player_wallet_transfer.fields.platform_name'))
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
                $filter->eq()->select('type')
                    ->placeholder(admin_trans('player_delivery_record.fields.type'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        PlayerWalletTransfer::TYPE_OUT => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_GAME_PLATFORM_OUT),
                        PlayerWalletTransfer::TYPE_IN => admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_GAME_PLATFORM_IN),
                    ]);
            });
            $grid->expandFilter();
        });
    }
}
