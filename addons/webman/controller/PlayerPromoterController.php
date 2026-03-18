<?php

namespace addons\webman\controller;

use addons\webman\model\ActivityContent;
use addons\webman\model\Player;
use addons\webman\model\PlayerActivityPhaseRecord;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerGameRecord;
use addons\webman\model\PlayerLotteryRecord;
use addons\webman\model\PlayerPlatformCash;
use addons\webman\model\PlayerPromoter;
use addons\webman\model\PlayerRechargeRecord;
use addons\webman\model\PlayerReverseWaterDetail;
use addons\webman\model\PlayerWithdrawRecord;
use addons\webman\model\PlayGameRecord;
use addons\webman\model\PromoterProfitRecord;
use addons\webman\model\PromoterProfitSettlementRecord;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\form\field\Switches;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\FilterColumn;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tabs\Tabs;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\grid\ToolTip;
use ExAdmin\ui\support\Container;
use ExAdmin\ui\support\Request;
use Illuminate\Support\Str;

/**
 * 渠道推广员管理
 * @group channel
 */
class PlayerPromoterController
{
    protected $model;

    protected $promoterProfitRecord;

    protected $player;

    protected $settlement;

    protected $playerGameRecord;

    protected $playerActivityPhaseRecord;

    protected $playerLotteryRecord;

    protected $playerRechargeRecord;

    protected $playerWithdrawRecord;

    protected $playerDeliveryRecord;

    protected $playGameRecord;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_promoter_model');
        $this->promoterProfitRecord = plugin()->webman->config('database.promoter_profit_record_model');
        $this->player = plugin()->webman->config('database.player_model');
        $this->settlement = plugin()->webman->config('database.promoter_profit_settlement_record_model');
        $this->playerGameRecord = plugin()->webman->config('database.player_game_record_model');
        $this->playerActivityPhaseRecord = plugin()->webman->config('database.player_activity_phase_record_model');
        $this->playerLotteryRecord = plugin()->webman->config('database.player_lottery_record_model');
        $this->playerRechargeRecord = plugin()->webman->config('database.player_recharge_record_model');
        $this->playerWithdrawRecord = plugin()->webman->config('database.player_withdraw_record_model');
        $this->playerDeliveryRecord = plugin()->webman->config('database.player_delivery_record_model');
        $this->playGameRecord = plugin()->webman->config('database.play_game_record_model');
    }

    /**
     * 推广员
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $grid->title(admin_trans('player_promoter.title'));
            $orderBy = 'id desc';
            $grid->model()->whereHas('player', function ($query) {
                $query->whereNull('deleted_at');
            })->with([
                'player',
                'parent_promoter',
                'parent_promoter.player',
                'parent_promoter.player.player_extend',
                'player.player_extend'
            ])->whereHas('channel', function ($query) {
                $query->where('is_offline', 0);
            });
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            if (isset($exAdminFilter['pid'])) {
                if (!empty($exAdminFilter['pid'])) {
                    $orderBy = 'if (player_id = ' . $exAdminFilter['pid'] . ', 1, 0) desc';
                    $grid->model()->where([
                        ['player_id', '=', $exAdminFilter['pid'], 'or'],
                        ['recommend_id', '=', $exAdminFilter['pid'], 'or']
                    ]);
                }
                if ($exAdminFilter['pid'] == '') {
                    $grid->model()->where('recommend_id', 0);
                }
            }
            if (!empty($exAdminFilter['department_id'])) {
                $grid->model()->where('department_id', $exAdminFilter['department_id']);
            }
            $grid->model()->orderByRaw($orderBy);
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('player_promoter.fields.id'))->align('center')->fixed(true)->ellipsis(true);
            $grid->column('player.uuid', admin_trans('player.fields.uuid'))
                ->display(function ($val, PlayerPromoter $data) {
                    return Html::create()->content([
                        Html::div()->content($val),
                        $data->player->is_test == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : ''
                    ]);
                })
                ->align('center')->fixed(true)->ellipsis(true);
            $grid->column('name', admin_trans('player_promoter.fields.name'))->display(function (
                $value,
                PlayerPromoter $data
            ) {
                $value = !empty($value) ? $value : $data->player->phone;
                return Html::create(Str::of($value)->limit(20, ' (...)'))
                    ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                    ->modal([$this, 'playerInfo'], ['player_id' => $data->player_id])
                    ->width('60%')->title(admin_trans('player.fields.phone') . ':' . $data->player->phone);
            })->fixed(true)->align('center')->width(120)->ellipsis(true);
            $grid->column('parent_promoter.name',
                admin_trans('player_promoter.fields.parent_promoter_name'))->display(function (
                $value,
                PlayerPromoter $data
            ) {
                if (isset($data->parent_promoter->player) && !empty($data->parent_promoter->player)) {
                    $value = !empty($value) ? $value : $data->parent_promoter->player->phone;
                    return Html::create(Str::of($value)->limit(20, ' (...)'))
                        ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                        ->modal([$this, 'playerInfo'], ['player_id' => $data->parent_promoter->player_id])
                        ->width('60%')->title(admin_trans('player.fields.phone') . ':' . $data->parent_promoter->player->phone);
                }
                return '';
            })->align('center')->width(120)->ellipsis(true);

            $grid->column('channel.name', admin_trans('player.fields.department_id'))->width('150px')->align('center');
            $grid->column('ratio', admin_trans('player_promoter.fields.ratio'))->append('%')->align('center');
            $grid->column('player_num', admin_trans('player_promoter.fields.player_num'))
                ->display(function ($val, PlayerPromoter $data) {
                    return Statistic::create()
                        ->value($val)
                        ->valueStyle(['fontSize' => '14px'])
                        ->precision(0)
                        ->prefix(Icon::create('far fa-user')->style(['fontSize' => '14px']))
                        ->style(['cursor' => 'pointer'])
                        ->modal([$this, 'promoterPlayers'], ['id' => $data->player_id])
                        ->width('80%')->title(admin_trans('player_promoter.promoter_players'));
                })
                ->align('center')
                ->width(120)
                ->ellipsis(true)
                ->sortable();
            $grid->column('team_num', admin_trans('player_promoter.fields.team_num'))
                ->display(function ($val, PlayerPromoter $data) {
                    return Statistic::create()
                        ->value($val)
                        ->valueStyle(['fontSize' => '14px'])
                        ->precision(0)
                        ->prefix(Icon::create('TeamOutlined')->style(['fontSize' => '14px']))
                        ->style(['cursor' => 'pointer'])
                        ->modal([$this, 'promoterTeam'], ['id' => $data->player_id])
                        ->width('80%')->title(admin_trans('player_promoter.promoter_team'));
                })
                ->align('center')
                ->width(120)
                ->ellipsis(true)
                ->sortable();

            $grid->column('adjust_amount', admin_trans('player_promoter.fields.adjust_amount'))->display(function ($val
            ) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            })->header(Html::create(admin_trans('player_promoter.fields.adjust_amount'))->content(
                ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                    'marginLeft' => '5px',
                    'cursor' => 'pointer'
                ]))->title(admin_trans('player_promoter.adjust_amount_tip'))
            ))->align('center')->width(120)->ellipsis(true);

            $grid->column('can_settlement_amount',
                admin_trans('player_promoter.fields.can_settlement_amount'))->display(function (
                $val,
                PlayerPromoter $data
            ) {
                $amount = bcadd($data->profit_amount, $data->adjust_amount, 2);
                return Html::create()->content([$amount > 0 ? '+' . $amount : $amount])
                    ->style(['color' => ($amount < 0 ? '#cd201f' : ($amount == 0 ? 'green' : 'orange'))]);
            })->header(Html::create(admin_trans('player_promoter.fields.can_settlement_amount'))->content(
                ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                    'marginLeft' => '5px',
                    'cursor' => 'pointer'
                ]))->title(admin_trans('player_promoter.can_settlement_amount_tip'))
            ))->align('center')->width(140)->ellipsis(true);
            $grid->column('real_settlement_amount',
                admin_trans('player_promoter.fields.real_settlement_amount'))->display(function (
                $val,
                PlayerPromoter $data
            ) {
                $amount = bcsub(bcadd($data->profit_amount, $data->adjust_amount, 2), $data->total_commission, 2);
                return Html::create()->content([$amount > 0 ? '+' . $amount : $amount])
                    ->style(['color' => ($amount < 0 ? '#cd201f' : ($amount == 0 ? 'green' : 'orange'))]);
            })->header(Html::create(admin_trans('player_promoter.fields.real_settlement_amount'))->content(
                ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                    'marginLeft' => '5px',
                    'cursor' => 'pointer'
                ]))->title(admin_trans('player_promoter.real_settlement_amount_tip'))
            ))->align('center')->width(140)->ellipsis(true);
            $grid->column('player_profit_amount',
                admin_trans('player_promoter.fields.player_profit_amount'))->display(function (
                $val,
                PlayerPromoter $data
            ) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style([
                        'color' => ($val < 0 ? '#cd201f' : ($val == 0 ? 'green' : 'orange')),
                        'cursor' => 'pointer'
                    ])
                    ->modal([$this, 'promoterProfitRecord'],
                        ['id' => $data->player_id, 'type' => 'player_profit_amount'])
                    ->width('80%')->title(admin_trans('player_promoter.fields.player_profit_amount'));
            })->align('center')->width(140)->ellipsis(true)->sortable();
            $grid->column('profit_amount_team',
                admin_trans('player_promoter.fields.profit_amount_team'))->display(function (
                $val,
                PlayerPromoter $data
            ) {
                $amount = bcsub($data->profit_amount, $data->player_profit_amount, 2);
                return Html::create()->content([$amount > 0 ? '+' . $amount : $amount,])
                    ->style([
                        'color' => ($amount < 0 ? '#cd201f' : ($amount == 0 ? 'green' : 'orange')),
                        'cursor' => 'pointer'
                    ])
                    ->modal([$this, 'promoterProfitRecord'], ['id' => $data->player_id, 'type' => 'profit_amount_team'])
                    ->width('80%')->title(admin_trans('player_promoter.fields.profit_amount_team'));
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('profit_amount', admin_trans('player_promoter.fields.profit_amount'))->display(function (
                $val,
                PlayerPromoter $data
            ) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style([
                        'color' => ($val < 0 ? '#cd201f' : ($val == 0 ? 'green' : 'orange')),
                        'cursor' => 'pointer'
                    ])
                    ->modal([$this, 'promoterProfitRecord'], ['id' => $data->player_id, 'type' => 'profit_amount'])
                    ->width('80%')->title(admin_trans('player_promoter.fields.profit_amount'));
            })->align('center')->width(120)->ellipsis(true)->sortable();
            $grid->column('team_recharge_total_amount',
                admin_trans('player_promoter.fields.team_recharge_total_amount'))->align('center')->width(120)->ellipsis(true);
            $grid->column('total_commission',
                admin_trans('player_promoter.fields.total_commission'))->align('center')->width(120)->ellipsis(true);
            $grid->column('team_withdraw_total_amount',
                admin_trans('player_promoter.fields.team_withdraw_total_amount'))->align('center')->width(120)->ellipsis(true);
            $grid->column('last_profit_amount',
                admin_trans('player_promoter.fields.last_profit_amount'))->display(function (
                $val,
                PlayerPromoter $data
            ) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val])
                    ->style([
                        'color' => ($val < 0 ? '#cd201f' : ($val == 0 ? 'green' : 'orange')),
                        'cursor' => 'pointer'
                    ])
                    ->modal([$this, 'promoterProfitRecord'], ['id' => $data->player_id, 'type' => 'last_profit_amount'])
                    ->width('80%')->title(admin_trans('player_promoter.fields.last_profit_amount'));
            })->align('center')->width(120)->ellipsis(true)->sortable();
            $grid->column('status', admin_trans('player_promoter.fields.status'))->switch()->align('center');
            $grid->column('settlement_amount',
                admin_trans('player_promoter.fields.settlement_amount'))->display(function (
                $val,
                PlayerPromoter $data
            ) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val])
                    ->style([
                        'color' => ($val < 0 ? '#cd201f' : ($val == 0 ? 'green' : 'orange')),
                        'cursor' => 'pointer'
                    ])
                    ->modal([$this, 'settlementAmount'], ['id' => $data->player_id])
                    ->width('80%')->title(admin_trans('player_promoter.fields.settlement_amount'));
            })->header(Html::create(admin_trans('player_promoter.fields.settlement_amount'))->content(
                ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                    'marginLeft' => '5px',
                    'cursor' => 'pointer'
                ]))->title(admin_trans('player_promoter.settlement_amount_tip'))
            ))->align('center')->width(120)->fixed('right')->ellipsis(true);

            $grid->column('last_settlement_time',
                admin_trans('player_promoter.fields.last_settlement_time'))->align('center')->fixed('right')->ellipsis(true);
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('name')->placeholder(admin_trans('player_promoter.fields.name'));
                $filter->like()->text('player.phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->select('status')
                    ->placeholder(admin_trans('player_promoter.fields.status'))
                    ->showSearch()
                    ->dropdownMatchSelectWidth()
                    ->style(['width' => '200px'])
                    ->options([
                        0 => admin_trans('player_promoter.status.0'),
                        1 => admin_trans('player_promoter.status.1'),
                    ]);
                $filter->eq()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('announcement.fields.department_id'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
            $promoterTree = [];
            $promoterList = (new $this->model)::whereHas('player', function ($query) {
                $query->whereNull('deleted_at');
            })->whereHas('channel', function ($query) {
                $query->where('is_offline', 0);
            })->get();
            /** @var PlayerPromoter $value */
            foreach ($promoterList as $value) {
                $promoterTree[] = [
                    'id' => $value->id,
                    'player_id' => $value->player_id,
                    'name' => $value->name,
                    'pid' => $value->recommend_id
                ];
            }

            $grid->sidebar('pid', $promoterTree, 'name', 'player_id')
                ->tree('player_id', 'pid')
                ->hideAdd()
                ->hideDel()
                ->searchPlaceholder(admin_trans('admin.search_department'));
            $grid->expandFilter();
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions, PlayerPromoter $data) {
                $dropdown = $actions->dropdown();
                $dropdown->item(admin_trans('player_promoter.promoter_profit_record'), 'fas fa-table')
                    ->modal([$this, 'promoterProfitRecord'], ['id' => $data->player_id, 'type' => 'profit_amount'])
                    ->width('80%');
                $dropdown->item(admin_trans('player_promoter.promoter_profit_record_detail'), 'fas fa-list')
                    ->modal([$this, 'promoterProfitRecordDetail'], ['id' => $data->player_id])
                    ->width('80%');
                $actions->hideDel();
            });
        });
    }

    /**
     * 分润明细
     * @param $id
     * @return Card
     */
    public function settlementAmount($id): Card
    {
        $tabs = Tabs::create()
            ->pane(admin_trans('menu.titles.profit_settlement_record'), $this->settlementList($id))
            ->pane(admin_trans('menu.titles.profit_record'), $this->reportList($id))
            ->animated(true)
            ->type('card');

        return Card::create($tabs);
    }

    /**
     * 分润结算记录
     * @auth true
     */
    public function settlementList($playerId = 0): Grid
    {
        return Grid::create(new $this->settlement(), function (Grid $grid) use ($playerId) {
            $grid->title(admin_trans('promoter_profit_settlement_record.title'));
            $grid->model()->with([
                'player_promoter',
                'promoter',
                'promoter.player',
                'player_promoter.player_extend',
                'user'
            ])->orderBy('id', 'desc');
            if (!empty($playerId)) {
                $grid->model()->where('promoter_player_id', $playerId);
            }
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            if (!empty($exAdminFilter['department_id'])) {
                $grid->model()->where('department_id', $exAdminFilter['department_id']);
            }
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id',
                admin_trans('promoter_profit_settlement_record.fields.id'))->fixed(true)->align('center');
            $grid->column('promoter.name', admin_trans('player_promoter.fields.name'))->display(function (
                $value,
                PromoterProfitSettlementRecord $data
            ) {
                $value = !empty($value) ? $value : $data->promoter->player->phone;
                return Html::create(Str::of($value)->limit(20, ' (...)'))
                    ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                    ->modal([$this, 'playerInfo'], ['player_id' => $data->promoter->player_id])
                    ->width('60%')->title(admin_trans('player.fields.phone') . ':' . $data->promoter->player->phone);
            })->fixed(true)->align('center')->width(120)->ellipsis(true);
            $grid->column('department_id', admin_trans('player.fields.department_id'))->display(function ($val, PromoterProfitSettlementRecord $data) {
                return $data->promoter->channel->name;
            })->width('150px')->align('center');
            $grid->column(function (Grid $grid) {
                $grid->column('total_profit_amount',
                    admin_trans('promoter_profit_settlement_record.fields.total_profit_amount'))->display(function (
                    $val,
                    PromoterProfitSettlementRecord $data
                ) {
                    $amount = bcadd($data->total_profit_amount, $data->adjust_amount, 2);
                    return Html::create()->content([$amount > 0 ? '+' . $amount : $amount,])
                        ->style(['color' => ($amount < 0 ? '#cd201f' : 'green'), 'cursor' => 'pointer'])
                        ->modal([$this, 'promoterProfitRecord'], ['id' => $data->id, 'type' => 'total_profit_amount'])
                        ->width('80%')->title(admin_trans('promoter_profit_settlement_record.fields.total_profit_amount'));
                })->align('center')->width(120)->ellipsis(true);

                $grid->column('actual_amount',
                    admin_trans('promoter_profit_settlement_record.fields.actual_amount'))->display(function ($val) {
                    return Html::create()->content([$val > 0 ? '+' . $val : '0.00'])->style(['color' => 'green']);
                })->align('center')->width(120)->ellipsis(true);

                $grid->column('adjust_amount',
                    admin_trans('promoter_profit_settlement_record.fields.adjust_amount'))->display(function ($val) {
                    return Html::create()->content([$val > 0 ? '+' . $val : $val])->style(['color' => $val < 0 ? '#cd201f' : 'green']);
                })->align('center')->width(120)->ellipsis(true);

                $grid->column('last_profit_amount',
                    admin_trans('promoter_profit_settlement_record.fields.last_profit_amount'))->display(function (
                    $val,
                    PromoterProfitSettlementRecord $data
                ) {
                    return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                        ->style(['color' => ($val < 0 ? '#cd201f' : 'green'), 'cursor' => 'pointer'])
                        ->modal([$this, 'promoterProfitRecord'],
                            ['id' => $data->promoter_player_id, 'type' => 'last_profit_amount'])
                        ->width('80%')->title(admin_trans('promoter_profit_settlement_record.fields.last_profit_amount'));
                })->align('center')->width(120)->ellipsis(true);

            }, admin_trans('promoter_profit_settlement_record.profit_settlement_info'))->ellipsis(true);
            $grid->column('user.username', admin_trans('admin.admin_user'))->display(function (
                $val,
                PromoterProfitSettlementRecord $data
            ) {
                $image = Image::create()
                    ->width(30)
                    ->height(30)
                    ->style(['border-radius' => '50%', 'objectFit' => 'cover'])
                    ->src($data->user->avatar ?? '');
                return Html::create()->content([
                    $image,
                    Html::div()->content($data->user->nickname ?? '')
                ]);
            })->align('center');
            $grid->column('tradeno',
                admin_trans('promoter_profit_settlement_record.fields.tradeno'))->display(function (
                $val,
                PromoterProfitSettlementRecord $data
            ) {
                return Html::create($val)
                    ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                    ->modal([$this, 'settlementDataList'], ['id' => $data->id])
                    ->width('80%')->title(admin_trans('promoter_profit_record.fields.settlement_tradeno') . ':' . $data->tradeno);
            })->align('center')->ellipsis(true)->copy();
            $grid->column(function (Grid $grid) {
                $grid->column('total_machine_up_amount',
                    admin_trans('promoter_profit_settlement_record.fields.total_machine_up_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('total_machine_down_amount',
                    admin_trans('promoter_profit_settlement_record.fields.total_machine_down_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('total_bonus_amount',
                    admin_trans('promoter_profit_settlement_record.fields.total_bonus_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('total_lottery_amount',
                    admin_trans('promoter_profit_settlement_record.fields.total_lottery_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('total_withdraw_amount',
                    admin_trans('promoter_profit_settlement_record.fields.total_withdraw_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('total_recharge_amount',
                    admin_trans('promoter_profit_settlement_record.fields.total_recharge_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('total_commission_amount',
                    admin_trans('promoter_profit_settlement_record.fields.total_commission_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('total_admin_deduct_amount',
                    admin_trans('promoter_profit_settlement_record.fields.total_admin_deduct_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('total_admin_add_amount',
                    admin_trans('promoter_profit_settlement_record.fields.total_admin_add_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('total_present_amount',
                    admin_trans('promoter_profit_settlement_record.fields.total_present_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('total_game_amount',
                    admin_trans('promoter_profit_settlement_record.fields.total_game_amount'))->align('center')->width(120)->ellipsis(true);
            }, admin_trans('promoter_profit_settlement_record.settlement_data'))->ellipsis(true);
            $grid->column('type',
                admin_trans('promoter_profit_settlement_record.fields.type'))->sortable()->display(function ($val) {
                $tag = '';
                switch ($val) {
                    case PromoterProfitSettlementRecord::TYPE_SETTLEMENT:
                        $tag = Tag::create(admin_trans('promoter_profit_settlement_record.type.' . PromoterProfitSettlementRecord::TYPE_SETTLEMENT))->color('#108ee9');
                        break;
                    case PromoterProfitSettlementRecord::TYPE_CLEAR:
                        $tag = Tag::create(admin_trans('promoter_profit_settlement_record.type.' . PromoterProfitSettlementRecord::TYPE_CLEAR))->color('#f50');
                }
                return Html::create()->content([
                    $tag
                ]);
            })->align('center');
            $grid->column('created_at',
                admin_trans('promoter_profit_settlement_record.fields.created_at'))->align('center')->fixed('right')->ellipsis(true);
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('tradeno')->placeholder(admin_trans('promoter_profit_settlement_record.fields.tradeno'));
                $filter->like()->text('promoter.name')->placeholder(admin_trans('player_promoter.fields.name'));
                $filter->like()->text('player_promoter.phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('player_promoter.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->select('type')
                    ->placeholder(admin_trans('promoter_profit_settlement_record.fields.type'))
                    ->showSearch()
                    ->dropdownMatchSelectWidth()
                    ->style(['width' => '200px'])
                    ->options([
                        PromoterProfitSettlementRecord::TYPE_SETTLEMENT => admin_trans('promoter_profit_settlement_record.type.' . PromoterProfitSettlementRecord::TYPE_SETTLEMENT),
                        PromoterProfitSettlementRecord::TYPE_CLEAR => admin_trans('promoter_profit_settlement_record.type.' . PromoterProfitSettlementRecord::TYPE_CLEAR),
                    ]);
                $filter->eq()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('announcement.fields.department_id'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);;
            });
            $grid->expandFilter();
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions, PromoterProfitSettlementRecord $data) {
                $actions->hideDel();
                $actions->prepend(
                    Button::create(admin_trans('promoter_profit_settlement_record.settlement_detail'))
                        ->type('primary')
                        ->modal([$this, 'settlementDataList'], ['id' => $data->id])
                        ->width('80%')->title(admin_trans('promoter_profit_settlement_record.fields.tradeno') . ':' . $data->tradeno)
                );
            });
        });
    }

    /**
     * 分润报表
     * @auth true
     */
    public function reportList($playerId = 0): Grid
    {
        return Grid::create(new $this->promoterProfitRecord, function (Grid $grid) use ($playerId) {
            $grid->title(admin_trans('promoter_profit_record.title'));
            $grid->model()->with([
                'promoter',
                'player_promoter.player_extend',
                'player_promoter',
                'player',
                'player.player_extend'
            ])->orderBy('id', 'desc');
            if (!empty($playerId)) {
                $grid->model()->where('promoter_player_id', $playerId)->where('status',
                    PromoterProfitRecord::STATUS_COMPLETED);
            }
            $this->profitRecordGrid($grid);
            $grid->export();
        });
    }

    /**
     * @param Grid $grid
     * @return void
     */
    protected function profitRecordGrid(Grid $grid): void
    {
        $exAdminFilter = Request::input('ex_admin_filter', []);
        if (!empty($exAdminFilter['date_start'])) {
            $grid->model()->where('date', '>=', $exAdminFilter['date_start']);
        }
        if (!empty($exAdminFilter['date_end'])) {
            $grid->model()->where('date', '<=', $exAdminFilter['date_end']);
        }
        if (!empty($exAdminFilter['settlement_time_start'])) {
            $grid->model()->where('settlement_time', '>=', $exAdminFilter['settlement_time_start']);
        }
        if (!empty($exAdminFilter['settlement_time_end'])) {
            $grid->model()->where('settlement_time', '<=', $exAdminFilter['settlement_time_end']);
        }
        if (!empty($exAdminFilter['department_id'])) {
            $grid->model()->where('department_id', $exAdminFilter['department_id']);
        }
        $grid->autoHeight();
        $grid->bordered(true);
        $grid->column('id', admin_trans('promoter_profit_record.fields.id'))->fixed(true)->align('center');
        $grid->column('promoter.name', admin_trans('player_promoter.fields.name'))->display(function (
            $value,
            PromoterProfitRecord $data
        ) {
            $value = !empty($value) ? $value : $data->promoter->name;
            return Html::create(Str::of($value)->limit(20, ' (...)'))
                ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                ->modal([$this, 'playerInfo'], ['player_id' => $data->promoter_player_id])
                ->width('60%')->title(admin_trans('player.fields.phone') . ':' . $data->player_promoter->phone);
        })->fixed(true)->align('center')->width(120)->ellipsis(true);

        $grid->column('player.name', admin_trans('promoter_profit_record.player_info'))->display(function (
            $value,
            PromoterProfitRecord $data
        ) {
            $value = !empty($value) ? $value : ($data->player->phone ?? '');
            return Html::create(Str::of($value)->limit(20, ' (...)'))
                ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                ->modal([$this, 'playerInfo'], ['player_id' => $data->player_id])
                ->width('60%')->title(admin_trans('player.fields.phone') . ':' . ($data->player->phone ?? ''));
        })->align('center')->width(120)->ellipsis(true);

        $grid->column('department_id', admin_trans('player.fields.department_id'))->display(function ($val, PromoterProfitRecord $data) {
            return $data->promoter->channel->name;
        })->width('150px')->align('center');
        $grid->column('status', admin_trans('promoter_profit_record.fields.status'))->display(function ($val) {
            return Tag::create(admin_trans('promoter_profit_record.status.' . $val))->color($val == 0 ? 'orange' : 'cyan');
        })->align('center')->ellipsis(true);
        $grid->column('settlement_tradeno',
            admin_trans('promoter_profit_record.fields.settlement_tradeno'))->display(function (
            $val,
            PromoterProfitRecord $data
        ) {
            return Html::create($val)
                ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                ->modal([$this, 'settlementDataList'], ['id' => $data->settlement_id])
                ->width('60%')->title(admin_trans('promoter_profit_record.fields.settlement_tradeno') . ':' . $data->settlement_tradeno);
        })->align('center')->ellipsis(true)->copy();
        $grid->column('ratio', admin_trans('promoter_profit_record.fields.ratio'))->display(function ($val) {
            return floatval($val);
        })->align('center')->ellipsis(true)->append('%')->sortable();
        $grid->column('actual_ratio', admin_trans('promoter_profit_record.fields.actual_ratio'))->display(function ($val
        ) {
            return floatval($val);
        })->align('center')->ellipsis(true)->append('%')->sortable();
        $grid->column(function (Grid $grid) {
            $grid->column('profit_amount',
                admin_trans('promoter_profit_record.fields.profit_amount'))->display(function (
                $val,
                PromoterProfitRecord $data
            ) {
                return Html::create()->content([
                    $val > 0 ? '+' . $val : $val,
                ])->style(['color' => ($val < 0 ? '#cd201f' : 'green'), 'cursor' => 'pointer'])
                    ->modal([$this, 'promoterProfitRecordDetail'], [
                        'id' => $data->player_id,
                        'date' => $data->date,
                        'active_key' => '1',
                        'data' => $data->toArray(),
                    ])
                    ->width('80%')->title(Html::create()->content([
                        admin_trans('promoter_profit_record.fields.profit_amount'),
                        ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                            'margin-left' => '4px',
                            'cursor' => 'pointer'
                        ]))->title(admin_trans('promoter_profit_record.profit_amount_tip'))
                    ]));
            })->align('center')->width(120)->ellipsis(true)->sortable();

            $grid->column('machine_up_amount',
                admin_trans('promoter_profit_record.fields.machine_up_amount'))->display(function (
                $val,
                PromoterProfitRecord $data
            ) {
                return Html::create()->content([$val != 0 ? floatval($val) : ''])
                    ->style(['color' => 'blue', 'cursor' => 'pointer'])
                    ->modal([$this, 'promoterProfitRecordDetail'], [
                        'id' => $data->player_id,
                        'date' => $data->date,
                        'active_key' => '1',
                        'data' => $data->toArray(),
                    ])
                    ->width('80%')->title(admin_trans('promoter_profit_record.fields.machine_up_amount'));

            })->align('center')->width(120)->ellipsis(true);

            $grid->column('machine_down_amount',
                admin_trans('promoter_profit_record.fields.machine_down_amount'))->display(function (
                $val,
                PromoterProfitRecord $data
            ) {
                return Html::create()->content([$val != 0 ? floatval($val) : ''])
                    ->style(['color' => 'blue', 'cursor' => 'pointer'])
                    ->modal([$this, 'promoterProfitRecordDetail'], [
                        'id' => $data->player_id,
                        'date' => $data->date,
                        'active_key' => '1',
                        'data' => $data->toArray(),
                    ])
                    ->width('80%')->title(admin_trans('promoter_profit_record.fields.machine_down_amount'));
            })->align('center')->width(120)->ellipsis(true);

            $grid->column('bonus_amount', admin_trans('promoter_profit_record.fields.bonus_amount'))->display(function (
                $val,
                PromoterProfitRecord $data
            ) {
                return Html::create()->content([$val != 0 ? floatval($val) : ''])
                    ->style(['color' => 'blue', 'cursor' => 'pointer'])
                    ->modal([$this, 'promoterProfitRecordDetail'], [
                        'id' => $data->player_id,
                        'date' => $data->date,
                        'active_key' => '2',
                        'data' => $data->toArray(),
                    ])
                    ->width('80%')->title(admin_trans('promoter_profit_record.fields.bonus_amount'));
            })->align('center')->width(120)->ellipsis(true);

            $grid->column('lottery_amount',
                admin_trans('promoter_profit_record.fields.lottery_amount'))->display(function (
                $val,
                PromoterProfitRecord $data
            ) {
                return Html::create()->content([$val != 0 ? floatval($val) : ''])
                    ->style(['color' => 'blue', 'cursor' => 'pointer'])
                    ->modal([$this, 'promoterProfitRecordDetail'], [
                        'id' => $data->player_id,
                        'date' => $data->date,
                        'active_key' => '3',
                        'data' => $data->toArray(),
                    ])
                    ->width('80%')->title(admin_trans('promoter_profit_record.fields.lottery_amount'));
            })->align('center')->width(120)->ellipsis(true);

            $grid->column('withdraw_amount',
                admin_trans('promoter_profit_record.fields.withdraw_amount'))->display(function (
                $val,
                PromoterProfitRecord $data
            ) {
                return Html::create()->content([$val != 0 ? floatval($val) : ''])
                    ->style(['color' => 'blue', 'cursor' => 'pointer'])
                    ->modal([$this, 'promoterProfitRecordDetail'], [
                        'id' => $data->player_id,
                        'date' => $data->date,
                        'active_key' => '5',
                        'data' => $data->toArray(),
                    ])
                    ->width('80%')->title(admin_trans('promoter_profit_record.fields.withdraw_amount'));
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('recharge_amount',
                admin_trans('promoter_profit_record.fields.recharge_amount'))->display(function (
                $val,
                PromoterProfitRecord $data
            ) {
                return Html::create()->content([$val != 0 ? floatval($val) : ''])
                    ->style(['color' => 'blue', 'cursor' => 'pointer'])
                    ->modal([$this, 'promoterProfitRecordDetail'], [
                        'id' => $data->player_id,
                        'date' => $data->date,
                        'active_key' => '4',
                        'data' => $data->toArray(),
                    ])
                    ->width('80%')->title(admin_trans('promoter_profit_record.fields.recharge_amount'));
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('commission_ratio', admin_trans('promoter_profit_record.fields.commission_ratio'))->append('%')->align('center')->width(120)->ellipsis(true);
            $grid->column('commission', admin_trans('promoter_profit_record.fields.commission'))->align('center')->width(120)->ellipsis(true);
            $grid->column('admin_add_amount',
                admin_trans('promoter_profit_record.fields.admin_add_amount'))->display(function (
                $val,
                PromoterProfitRecord $data
            ) {
                return Html::create()->content([$val != 0 ? floatval($val) : ''])
                    ->style(['color' => 'blue', 'cursor' => 'pointer'])
                    ->modal([$this, 'promoterProfitRecordDetail'], [
                        'id' => $data->player_id,
                        'date' => $data->date,
                        'active_key' => '6',
                        'data' => $data->toArray(),
                    ])
                    ->width('80%')->title(admin_trans('promoter_profit_record.fields.admin_add_amount'));
            })->align('center')->width(120)->ellipsis(true);

            $grid->column('admin_deduct_amount',
                admin_trans('promoter_profit_record.fields.admin_deduct_amount'))->display(function (
                $val,
                PromoterProfitRecord $data
            ) {
                return Html::create()->content([$val != 0 ? floatval($val) : ''])
                    ->style(['color' => 'blue', 'cursor' => 'pointer'])
                    ->modal([$this, 'promoterProfitRecordDetail'], [
                        'id' => $data->player_id,
                        'date' => $data->date,
                        'active_key' => '6',
                        'data' => $data->toArray(),
                    ])
                    ->width('80%')->title(admin_trans('promoter_profit_record.fields.admin_deduct_amount'));

            })->align('center')->width(120)->ellipsis(true);

            $grid->column('present_amount',
                admin_trans('promoter_profit_record.fields.present_amount'))->display(function (
                $val,
                PromoterProfitRecord $data
            ) {
                return Html::create()->content([$val != 0 ? floatval($val) : ''])
                    ->style(['color' => 'blue', 'cursor' => 'pointer'])
                    ->modal([$this, 'promoterProfitRecordDetail'], [
                        'id' => $data->player_id,
                        'date' => $data->date,
                        'active_key' => '7',
                        'data' => $data->toArray(),
                    ])
                    ->width('80%')->title(admin_trans('promoter_profit_record.fields.present_amount'));
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('game_amount', admin_trans('promoter_profit_record.fields.game_amount'))->display(function (
                $val,
                PromoterProfitRecord $data
            ) {
                return Html::create()->content([$val != 0 ? floatval($val) : ''])
                    ->style(['color' => 'blue', 'cursor' => 'pointer'])
                    ->modal([$this, 'promoterProfitRecordDetail'], [
                        'id' => $data->player_id,
                        'date' => $data->date,
                        'active_key' => '8',
                        'data' => $data->toArray(),
                    ])
                    ->width('80%')->title(admin_trans('promoter_profit_record.fields.game_amount'));
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('water_amount', admin_trans('promoter_profit_record.fields.water_amount'))->display(function (
                $val,
                PromoterProfitRecord $data
            ) {
                return Html::create()->content([$val != 0 ? floatval($val) : ''])
                    ->style(['color' => 'blue', 'cursor' => 'pointer'])
                    ->modal([$this, 'promoterProfitRecordDetail'], [
                        'id' => $data->player_id,
                        'date' => $data->date,
                        'active_key' => '9',
                        'data' => $data->toArray(),
                    ])
                    ->width('80%')->title(admin_trans('promoter_profit_record.fields.water_amount'));
            })->align('center')->width(120)->ellipsis(true);
        }, admin_trans('promoter_profit_record.profit_record'))->ellipsis(true);

        $grid->column('date', admin_trans('promoter_profit_record.fields.date'))
            ->header(Html::create(admin_trans('promoter_profit_record.fields.date'))->content(
                ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                    'marginLeft' => '5px',
                    'cursor' => 'pointer'
                ]))->title(admin_trans('promoter_profit_record.date_tip'))
            ))->width(150)->fixed('right')->align('center')->ellipsis(true);
        $grid->column('settlement_time',
            admin_trans('promoter_profit_record.fields.settlement_time'))->fixed('right')->align('center')->ellipsis(true);
        $grid->filter(function (Filter $filter) {
            $filter->eq()->select('department_id')
                ->showSearch()
                ->style(['width' => '200px'])
                ->dropdownMatchSelectWidth()
                ->placeholder(admin_trans('announcement.fields.department_id'))
                ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
            $filter->like()->text('promoter.name')->placeholder(admin_trans('player_promoter.fields.name'));
            $filter->like()->text('player_promoter.phone')->placeholder(admin_trans('promoter_profit_record.player_promoter.phone'));
            $filter->like()->text('player_promoter.uuid')->placeholder(admin_trans('promoter_profit_record.player_promoter.uuid'));
            $filter->like()->text('player.name')->placeholder(admin_trans('player.fields.name'));
            $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
            $filter->like()->text('player.phone')->placeholder(admin_trans('player.fields.phone'));
            $filter->like()->text('settlement_tradeno')->placeholder(admin_trans('promoter_profit_record.fields.settlement_tradeno'));
            $filter->select('status')
                ->placeholder(admin_trans('promoter_profit_record.fields.status'))
                ->showSearch()
                ->dropdownMatchSelectWidth()
                ->style(['width' => '200px'])
                ->options([
                    PromoterProfitRecord::STATUS_UNCOMPLETED => admin_trans('promoter_profit_record.status.' . PromoterProfitRecord::STATUS_UNCOMPLETED),
                    PromoterProfitRecord::STATUS_COMPLETED => admin_trans('promoter_profit_record.status.' . PromoterProfitRecord::STATUS_COMPLETED),
                ]);
            $filter->form()->hidden('date_start');
            $filter->form()->hidden('date_end');
            $filter->form()->dateRange('date_start', 'date_end', '')->placeholder([
                admin_trans('public_msg.date_start'),
                admin_trans('public_msg.date_end')
            ]);
            $filter->form()->hidden('settlement_time_start');
            $filter->form()->hidden('settlement_time_end');
            $filter->form()->dateRange('settlement_time_start', 'settlement_time_end', '')->placeholder([
                admin_trans('promoter_profit_record.settlement_time_start'),
                admin_trans('promoter_profit_record.settlement_time_end')
            ]);
        });
        $grid->tools([
            ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                'margin-left' => '10px',
                'margin-top' => '4px',
                'line-height' => '28px',
                'font-size' => '15px',
                'cursor' => 'pointer'
            ]))->title(admin_trans('promoter_profit_record.date_tip'))
        ]);
        $grid->hideDelete();
        $grid->hideSelection();
        $grid->hideTrashed();
        $grid->actions(function (Actions $actions, PromoterProfitRecord $data) {
            $actions->hideDel();
            if ($data->status == PromoterProfitRecord::STATUS_COMPLETED && !empty($data->settlement_id)) {
                $actions->prepend(
                    Button::create(admin_trans('promoter_profit_record.settlement_detail'))
                        ->type('primary')
                        ->modal([$this, 'settlementDetail'], ['id' => $data->settlement_id])
                        ->width('60%')->title(admin_trans('promoter_profit_record.fields.settlement_tradeno') . ':' . $data->settlement_tradeno)
                );
            }
        });
        $grid->expandFilter();
    }

    /**
     * 分润明细
     * @param $id
     * @param string $date
     * @param string $active_key
     * @param array $data
     * @return Card
     */
    public function promoterProfitRecordDetail($id, string $date = '', string $active_key = '1', array $data = []): Card
    {
        $tabs = Tabs::create($active_key)
            ->pane(
                Html::create()->content([
                    admin_trans('promoter_profit_record.player_game_record'),
                    (isset($data['machine_down_amount']) && $data['machine_down_amount'] > 0) || (isset($data['machine_up_amount']) && $data['machine_up_amount'] > 0) ? ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                        'margin-left' => '2px',
                        'margin-top' => '4px',
                        'cursor' => 'pointer'
                    ]))->title($data['machine_up_amount'] . ' / ' . $data['machine_down_amount']) : ''
                ]), $this->playerGameRecord($id, $date), '1')
            ->pane(
                Html::create()->content([
                    admin_trans('promoter_profit_record.player_activity_phase_record'),
                    isset($data['bonus_amount']) && $data['bonus_amount'] > 0 ? ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                        'margin-left' => '2px',
                        'margin-top' => '4px',
                        'cursor' => 'pointer'
                    ]))->title($data['bonus_amount']) : ''
                ])
                , $this->playerActivityPhaseRecord($id, $date), '2')
            ->pane(
                Html::create()->content([
                    admin_trans('promoter_profit_record.player_lottery_record'),
                    isset($data['lottery_amount']) && $data['lottery_amount'] > 0 ? ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                        'margin-left' => '2px',
                        'margin-top' => '4px',
                        'cursor' => 'pointer'
                    ]))->title($data['lottery_amount']) : ''
                ])
                , $this->playerLotteryRecord($id, $date), '3')
            ->pane(
                Html::create()->content([
                    admin_trans('promoter_profit_record.player_recharge_record'),
                    isset($data['recharge_amount']) && $data['recharge_amount'] > 0 ? ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                        'margin-left' => '2px',
                        'margin-top' => '4px',
                        'cursor' => 'pointer'
                    ]))->title($data['recharge_amount']) : ''
                ])
                , $this->playerRechargeRecord($id, $date), '4')
            ->pane(
                Html::create()->content([
                    admin_trans('promoter_profit_record.player_withdraw_record'),
                    isset($data['withdraw_amount']) && $data['withdraw_amount'] > 0 ? ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                        'margin-left' => '2px',
                        'margin-top' => '4px',
                        'cursor' => 'pointer'
                    ]))->title($data['withdraw_amount']) : ''
                ])
                , $this->playerWithdrawRecord($id, $date), '5')
            ->pane(
                Html::create()->content([
                    admin_trans('promoter_profit_record.player_delivery_record'),
                    (isset($data['admin_add_amount']) && $data['admin_add_amount'] > 0) || (isset($data['admin_deduct_amount']) && $data['admin_deduct_amount'] > 0) ? ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                        'margin-left' => '2px',
                        'margin-top' => '4px',
                        'cursor' => 'pointer'
                    ]))->title($data['admin_add_amount'] . ' / ' . $data['admin_deduct_amount']) : ''
                ])
                , $this->playerDeliveryRecord($id, $date), '6')
            ->pane(
                Html::create()->content([
                    admin_trans('promoter_profit_record.player_present_record'),
                    isset($data['present_amount']) && $data['present_amount'] > 0 ? ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                        'margin-left' => '2px',
                        'margin-top' => '4px',
                        'cursor' => 'pointer'
                    ]))->title($data['present_amount']) : ''
                ])
                , $this->playerDeliveryRecord($id, $date, [PlayerDeliveryRecord::TYPE_REGISTER_PRESENT]), '7')
            ->pane(
                Html::create()->content([
                    admin_trans('promoter_profit_record.game_record'),
                    isset($data['present_amount']) && $data['present_amount'] > 0 ? ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                        'margin-left' => '2px',
                        'margin-top' => '4px',
                        'cursor' => 'pointer'
                    ]))->title($data['game_amount']) : ''
                ])
                , $this->playGameRecord($id, $date), '8')
            ->pane(
                Html::create()->content([
                    admin_trans('promoter_profit_record.water_record'),
                    isset($data['water_amount']) && $data['water_amount'] > 0 ? ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                        'margin-left' => '2px',
                        'margin-top' => '4px',
                        'cursor' => 'pointer'
                    ]))->title($data['water_amount']) : ''
                ])
                , $this->reverseWaterRecord($id, $date), '9')
            ->animated(true)
            ->tabBarExtraContent(Html::create()->content([
                $data ? ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                    'margin-left' => '2px',
                    'margin-top' => '4px',
                    'cursor' => 'pointer'
                ]))->title(admin_trans('player_promoter.formula_tip', [], [
                    '{up}' => $data['machine_up_amount'] ?? 0,
                    '{admin_sub}' => $data['admin_deduct_amount'] ?? 0,
                    '{activity}' => $data['bonus_amount'] ?? 0,
                    '{present}' => $data['present_amount'] ?? 0,
                    '{admin_add}' => $data['admin_add_amount'] ?? 0,
                    '{down}' => $data['machine_down_amount'] ?? 0,
                    '{lottery}' => $data['lottery_amount'] ?? 0,
                    '{ratio}' => $data['actual_ratio'] ?? 0,
                    '{profit_amount}' => $data['profit_amount'] ?? 0,
                ])) : ''
            ]))
            ->type('card');

        return Card::create($tabs);
    }
    
    /**
     * 反水奖励记录
     * @auth true
     * @param $id
     * @param string $date
     * @return Grid
     */
    public function reverseWaterRecord($id, string $date = ''): Grid
    {
        return Grid::create(new (plugin()->webman->config('database.player_reverse_water_detail_model')),
            function (Grid $grid) use ($id, $date) {
                $grid->title(admin_trans('reverse_water.title'));
                $grid->model()
                    ->where('status', PlayerReverseWaterDetail::STATUS_RECEIVED)
                    ->where('player_id', $id)
                    ->whereDate('receive_time', $date)
                    ->orderBy('id', 'desc');
                $grid->autoHeight();
                $grid->bordered(true);
                $grid->hideAction();
                $grid->hideDelete();
                $grid->hideDeleteSelection();
                $grid->hideSelection();
                $grid->column('id', admin_trans('player_wallet_transfer.fields.id'))->align('center');
                $grid->column('date', admin_trans('reverse_water.fields.date'))->align('center');
                $grid->column('player.uuid', admin_trans('player.fields.uuid'))->align('center');
                $grid->column('player.phone', admin_trans('player.fields.phone'))->align('center');
                $grid->column('player.real_name', admin_trans('player.fields.real_name'))->align('center');
                $grid->column('platform.name', admin_trans('game_platform.fields.name'))->align('center');
                $grid->column('point', admin_trans('reverse_water.fields.point'))->align('center');
                $grid->column('all_diff', admin_trans('reverse_water.fields.all_diff'))->align('center');
                $grid->column('platform_ratio', admin_trans('reverse_water.fields.platform_ratio'))->align('center');
                $grid->column('level_ratio', admin_trans('reverse_water.fields.level_ratio'))->align('center');
                $grid->column('reverse_water', admin_trans('reverse_water.fields.reverse_water'))->align('center');
                $grid->column('real_reverse_water',
                    admin_trans('reverse_water.fields.real_reverse_water'))->align('center');
                $grid->column('switch', admin_trans('reverse_water.fields.switch'))->switch()->align('center');
                $grid->column('created_at', admin_trans('reverse_water.fields.created_at'))->align('center');
                $grid->column('receive_time', admin_trans('reverse_water.fields.receive_time'))->align('center');
                $grid->column('admin_id', admin_trans('reverse_water.fields.admin_id'))->display(function ($value) {
                    if ($value == 0) {
                        return admin_trans('menu.titles.system');
                    }
                    return $value;
                })->align('center');
            });
    }
    
    /**
     * 机台操作
     * @param $id
     * @param string $date
     * @return Grid
     */
    public function playerGameRecord($id, string $date = ''): Grid
    {
        return Grid::create(new $this->playerGameRecord, function (Grid $grid) use ($id, $date) {
            $grid->title(admin_trans('promoter_profit_record.player_game_record_title'));
            if (empty($date)) {
                $db = PromoterProfitRecord::whereRaw('promoter_player_id = ' . $id . ' and status = ' . PromoterProfitRecord::STATUS_UNCOMPLETED)
                    ->select('date');
                $grid->model()
                    ->where('status', PlayerGameRecord::STATUS_END)
                    ->where('open_point', '!=', 'wash_point')
                    ->whereIn('player_id', function ($query) use ($id) {
                        $query->select('player_id')
                            ->from('promoter_profit_record')
                            ->where('promoter_player_id', $id)
                            ->where('status', PromoterProfitRecord::STATUS_UNCOMPLETED);
                    })
                    ->whereRaw("(FROM_UNIXTIME(UNIX_TIMESTAMP(created_at), '%Y-%m-%d')" . " IN (" . $db->toSql() . "))")
                    ->orderBy('id', 'desc');
            } else {
                $grid->model()
                    ->where('status', PlayerGameRecord::STATUS_END)
                    ->where('open_point', '!=', 'wash_point')
                    ->where('player_id', $id)
                    ->whereDate('created_at', $date)
                    ->orderBy('id', 'desc');
            }
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('promoter_profit_record.fields.id'))->fixed(true)->align('center');
            $grid->column('player.name', admin_trans('player.fields.name'))->display(function (
                $value,
                PlayerGameRecord $data
            ) {
                $value = !empty($data->player->name) ? $data->player->name : $data->player->phone;
                return Html::create(Str::of($value)->limit(20, ' (...)'))
                    ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                    ->modal([$this, 'playerInfo'], ['player_id' => $data->player_id])
                    ->width('60%')->title(admin_trans('player.fields.phone') . ':' . $data->player->phone);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('machine.code', admin_trans('machine.fields.code'))->display(function (
                $val,
                PlayerGameRecord $data
            ) {
                if ($data->machine) {
                    return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal([
                        'addons-webman-controller-PlayerDeliveryRecordController',
                        'machineInfo'
                    ],
                        ['data' => $data->machine->toArray()])->width('60%')->title($data->machine->code . ' ' . $data->machine->name);
                }
                return '';
            })->align('center');
            $grid->column('open_point', admin_trans('promoter_profit_record.fields.open_point'))->align('center');
            $grid->column('wash_point', admin_trans('promoter_profit_record.fields.wash_point'))->align('center');
            $grid->column('total_amount', admin_trans('promoter_profit_record.fields.total_amount'))->display(function (
                $val,
                PlayerGameRecord $data
            ) {
                $val = $data->open_point - $data->wash_point;
                return Html::create()->content([
                    $val > 0 ? '+' . floatval($val) : floatval($val),
                ])->style(['color' => ($val < 0 ? '#cd201f' : '#3b5999')]);
            })->align('center');
            $grid->column('created_at',
                admin_trans('promoter_profit_record.fields.date'))->align('center')->display(function ($val) {
                return date('Y-m-d', strtotime($val));
            })->ellipsis(true);
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
        });
    }

    /**
     * 活动奖励
     * @param $id
     * @param string $date
     * @return Grid
     */
    public function playerActivityPhaseRecord($id, string $date = ''): Grid
    {
        return Grid::create(new $this->playerDeliveryRecord, function (Grid $grid) use ($id, $date) {
            $lang = Container::getInstance()->translator->getLocale();
            $grid->title(admin_trans('promoter_profit_record.player_activity_phase_record_title'));
            if (empty($date)) {
                $db = PromoterProfitRecord::whereRaw('promoter_player_id = ' . $id . ' and status = ' . PromoterProfitRecord::STATUS_UNCOMPLETED)
                    ->select('date');
                $grid->model()
                    ->where('type', PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS)
                    ->whereIn('player_id', function ($query) use ($id) {
                        $query->select('player_id')
                            ->from('promoter_profit_record')
                            ->where('promoter_player_id', $id)
                            ->where('status', PromoterProfitRecord::STATUS_UNCOMPLETED);
                    })
                    ->whereRaw("(FROM_UNIXTIME(UNIX_TIMESTAMP(updated_at), '%Y-%m-%d')" . " IN (" . $db->toSql() . "))")
                    ->orderBy('id', 'desc');
            } else {
                $grid->model()
                    ->where('type', PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS)
                    ->where('player_id', $id)
                    ->whereDate('updated_at', $date)
                    ->orderBy('id', 'desc');
            }

            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('player_delivery_record.fields.id'))->align('center');
            $grid->column('player.name', admin_trans('player.fields.name'))->display(function (
                $value,
                PlayerDeliveryRecord $data
            ) {
                $value = !empty($data->player->name) ? $data->player->name : $data->player->phone;
                return Html::create(Str::of($value)->limit(20, ' (...)'))
                    ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                    ->modal([$this, 'playerInfo'], ['player_id' => $data->player_id])
                    ->width('60%')->title(admin_trans('player.fields.phone') . ':' . $data->player->phone);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('activity_content_name', admin_trans('activity_content.fields.name'))->display(function (
                $val,
                PlayerDeliveryRecord $data
            ) use ($lang) {
                /** @var PlayerActivityPhaseRecord $playerActivityPhaseRecord */
                $playerActivityPhaseRecord = PlayerActivityPhaseRecord::find($data->target_id);
                /** @var ActivityContent $activityContent */
                $activityContent = $playerActivityPhaseRecord->activity->activity_content->where('lang',
                    $lang)->first();
                return Html::create($activityContent->name)->style([
                    'cursor' => 'pointer',
                    'color' => 'rgb(24, 144, 255)'
                ])->modal(['addons-webman-controller-ActivityController', 'details'],
                    ['id' => $activityContent->activity_id])->width('60%');
            })->align('center');
            $grid->column('machine.code', admin_trans('machine.fields.code'))->display(function (
                $val,
                PlayerDeliveryRecord $data
            ) {
                /** @var PlayerActivityPhaseRecord $playerActivityPhaseRecord */
                $playerActivityPhaseRecord = PlayerActivityPhaseRecord::find($data->target_id);
                if ($playerActivityPhaseRecord->machine) {
                    return Tag::create($playerActivityPhaseRecord->machine->code)->color('orange')->style(['cursor' => 'pointer'])->modal([
                        'addons-webman-controller-PlayerDeliveryRecordController',
                        'machineInfo'
                    ],
                        ['data' => $playerActivityPhaseRecord->machine->toArray()])->width('60%')->title($playerActivityPhaseRecord->machine->code . ' ' . $playerActivityPhaseRecord->machine->name);
                }
                return '';
            })->align('center');
            $grid->column('amount', admin_trans('promoter_profit_record.fields.total_amount'))->display(function ($val
            ) {
                return Html::create()->content([
                    '+' . floatval($val),
                ])->style(['color' => '#3b5999']);
            })->align('center');
            $grid->column('created_at',
                admin_trans('promoter_profit_record.fields.date'))->align('center')->display(function ($val) {
                return date('Y-m-d', strtotime($val));
            })->ellipsis(true);
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
        });
    }

    /**
     * 彩金奖励
     * @param $id
     * @param string $date
     * @return Grid
     */
    public function playerLotteryRecord($id, string $date = ''): Grid
    {
        return Grid::create(new $this->playerLotteryRecord, function (Grid $grid) use ($id, $date) {
            $grid->title(admin_trans('promoter_profit_record.player_lottery_record_title'));
            if (empty($date)) {
                $db = PromoterProfitRecord::whereRaw('promoter_player_id = ' . $id . ' and status = ' . PromoterProfitRecord::STATUS_UNCOMPLETED)
                    ->select('date');
                $grid->model()
                    ->where('status', PlayerLotteryRecord::STATUS_COMPLETE)
                    ->whereIn('player_id', function ($query) use ($id) {
                        $query->select('player_id')
                            ->from('promoter_profit_record')
                            ->where('promoter_player_id', $id)
                            ->where('status', PromoterProfitRecord::STATUS_UNCOMPLETED);
                    })
                    ->whereRaw("(FROM_UNIXTIME(UNIX_TIMESTAMP(updated_at), '%Y-%m-%d')" . " IN (" . $db->toSql() . "))")
                    ->orderBy('id', 'desc');
            } else {
                $grid->model()
                    ->where('status', PlayerLotteryRecord::STATUS_COMPLETE)
                    ->where('player_id', $id)
                    ->whereDate('updated_at', $date)
                    ->orderBy('id', 'desc');
            }

            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('promoter_profit_record.fields.id'))->align('center');
            $grid->column('player.name', admin_trans('player.fields.name'))->display(function (
                $value,
                PlayerLotteryRecord $data
            ) {
                $value = !empty($data->player->name) ? $data->player->name : $data->player->phone;
                return Html::create(Str::of($value)->limit(20, ' (...)'))
                    ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                    ->modal([$this, 'playerInfo'], ['player_id' => $data->player_id])
                    ->width('60%')->title(admin_trans('player.fields.phone') . ':' . $data->player->phone);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('lottery_name', admin_trans('player_lottery_record.fields.lottery_name'))->align('center');
            $grid->column('machine_code', admin_trans('player_lottery_record.fields.machine_code'))
                ->display(function ($val, PlayerLotteryRecord $data) {
                    return Tag::create($data->machine->code)->color('orange')->style(['cursor' => 'pointer'])->modal([
                        'addons-webman-controller-PlayerDeliveryRecordController',
                        'machineInfo'
                    ],
                        ['data' => $data->machine->toArray()])->width('60%')->title($data->machine->code . ' ' . $data->machine->name);
                })
                ->align('center');

            $grid->column('amount', admin_trans('promoter_profit_record.fields.total_amount'))->display(function ($val
            ) {
                return Html::create()->content([
                    '+' . floatval($val),
                ])->style(['color' => '#3b5999']);
            })->align('center');
            $grid->column('updated_at',
                admin_trans('promoter_profit_record.fields.date'))->align('center')->display(function ($val) {
                return date('Y-m-d', strtotime($val));
            })->ellipsis(true);
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
        });
    }

    /**
     * 充值记录
     * @param $id
     * @param string $date
     * @return Grid
     */
    public function playerRechargeRecord($id, string $date = ''): Grid
    {
        return Grid::create(new $this->playerRechargeRecord, function (Grid $grid) use ($id, $date) {
            $grid->title(admin_trans('promoter_profit_record.player_recharge_record_title'));
            if (empty($date)) {
                $db = PromoterProfitRecord::whereRaw('promoter_player_id = ' . $id . ' and status = ' . PromoterProfitRecord::STATUS_UNCOMPLETED)
                    ->select('date');
                $grid->model()
                    ->where('status', PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)
                    ->whereIn('player_id', function ($query) use ($id) {
                        $query->select('player_id')
                            ->from('promoter_profit_record')
                            ->where('promoter_player_id', $id)
                            ->where('status', PromoterProfitRecord::STATUS_UNCOMPLETED);
                    })
                    ->whereRaw("(FROM_UNIXTIME(UNIX_TIMESTAMP(updated_at), '%Y-%m-%d')" . " IN (" . $db->toSql() . "))")
                    ->orderBy('id', 'desc');
            } else {
                $grid->model()
                    ->where('status', PlayerRechargeRecord::STATUS_RECHARGED_SUCCESS)
                    ->where('player_id', $id)
                    ->whereDate('updated_at', $date)
                    ->orderBy('id', 'desc');
            }

            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('promoter_profit_record.fields.id'))->align('center');
            $grid->column('player.name', admin_trans('player.fields.name'))->display(function (
                $value,
                PlayerRechargeRecord $data
            ) {
                $value = !empty($data->player->name) ? $data->player->name : $data->player->phone;
                return Html::create(Str::of($value)->limit(20, ' (...)'))
                    ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                    ->modal([$this, 'playerInfo'], ['player_id' => $data->player_id])
                    ->width('60%')->title(admin_trans('player.fields.phone') . ':' . $data->player->phone);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('tradeno', admin_trans('player_recharge_record.fields.tradeno'))->align('center');
            $grid->column('talk_tradeno', admin_trans('player_recharge_record.fields.talk_tradeno'))->align('center');
            $grid->column('channel.name', admin_trans('player_recharge_record.fields.department_id'))->align('center');
            $grid->column('type', admin_trans('player_recharge_record.fields.type'))->display(function ($val) {
                switch ($val) {
                    case PlayerRechargeRecord::TYPE_THIRD:
                        return Tag::create(admin_trans('player_recharge_record.type.' . $val))
                            ->color('#55acee');
                    case PlayerRechargeRecord::TYPE_SELF:
                        return Tag::create(admin_trans('player_recharge_record.type.' . $val))
                            ->color('#3b5999');
                    case PlayerRechargeRecord::TYPE_BUSINESS:
                        return Tag::create(admin_trans('player_recharge_record.type.' . $val))
                            ->color('#cd201f');
                    default:
                        return '';
                }
            })->align('center');
            $grid->column('point', admin_trans('promoter_profit_record.fields.total_amount'))->display(function ($val) {
                return Html::create()->content([
                    '+' . floatval($val),
                ])->style(['color' => '#3b5999']);
            })->align('center');
            $grid->column('updated_at',
                admin_trans('promoter_profit_record.fields.date'))->align('center')->display(function ($val) {
                return date('Y-m-d', strtotime($val));
            })->ellipsis(true);
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
        });
    }

    /**
     * 提现记录
     * @param $id
     * @param string $date
     * @return Grid
     */
    public function playerWithdrawRecord($id, string $date = ''): Grid
    {
        return Grid::create(new $this->playerWithdrawRecord, function (Grid $grid) use ($id, $date) {
            $grid->title(admin_trans('promoter_profit_record.player_withdraw_record_title'));
            if (empty($date)) {
                $db = PromoterProfitRecord::whereRaw('promoter_player_id = ' . $id . ' and status = ' . PromoterProfitRecord::STATUS_UNCOMPLETED)
                    ->select('date');
                $grid->model()
                    ->where('status', PlayerWithdrawRecord::STATUS_SUCCESS)
                    ->whereIn('player_id', function ($query) use ($id) {
                        $query->select('player_id')
                            ->from('promoter_profit_record')
                            ->where('promoter_player_id', $id)
                            ->where('status', PromoterProfitRecord::STATUS_UNCOMPLETED);
                    })
                    ->whereRaw("(FROM_UNIXTIME(UNIX_TIMESTAMP(created_at), '%Y-%m-%d')" . " IN (" . $db->toSql() . "))")
                    ->orderBy('id', 'desc');

            } else {
                $grid->model()
                    ->where('status', PlayerWithdrawRecord::STATUS_SUCCESS)
                    ->where('player_id', $id)
                    ->whereDate('created_at', $date)
                    ->orderBy('id', 'desc');
            }
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('player_withdraw_record.fields.id'))->align('center');
            $grid->column('player.name', admin_trans('player.fields.name'))->display(function (
                $value,
                PlayerWithdrawRecord $data
            ) {
                $value = !empty($data->player->name) ? $data->player->name : $data->player->phone;
                return Html::create(Str::of($value)->limit(20, ' (...)'))
                    ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                    ->modal([$this, 'playerInfo'], ['player_id' => $data->player_id])
                    ->width('60%')->title(admin_trans('player.fields.phone') . ':' . $data->player->phone);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('type', admin_trans('player_withdraw_record.fields.type'))->display(function ($val) {
                switch ($val) {
                    case PlayerWithdrawRecord::TYPE_THIRD:
                        return Tag::create(admin_trans('player_withdraw_record.type.' . $val))
                            ->color('#55acee');
                    case PlayerWithdrawRecord::TYPE_SELF:
                        return Tag::create(admin_trans('player_withdraw_record.type.' . $val))
                            ->color('#3b5999');
                    case PlayerWithdrawRecord::TYPE_ARTIFICIAL:
                        return Tag::create(admin_trans('player_withdraw_record.type.' . $val))
                            ->color('#cd201f');
                    default:
                        return '';
                }
            })->align('center');
            $grid->column('tradeno', admin_trans('player_withdraw_record.fields.tradeno'))->align('center');
            $grid->column('talk_tradeno', admin_trans('player_withdraw_record.fields.talk_tradeno'))->align('center');
            $grid->column('point', admin_trans('promoter_profit_record.fields.total_amount'))->display(function ($val) {
                return Html::create()->content([
                    '-' . floatval($val),
                ])->style(['color' => '#cd201f']);
            })->align('center');
            $grid->column('updated_at',
                admin_trans('promoter_profit_record.fields.date'))->align('center')->display(function ($val) {
                return date('Y-m-d', strtotime($val));
            })->ellipsis(true);
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
        });
    }

    /**
     * 钱包操作
     * @param $id
     * @param string $date
     * @param array $type
     * @return Grid
     */
    public function playerDeliveryRecord(
        $id,
        string $date = '',
        array $type = [
            PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD,
            PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT,
        ]
    ): Grid {
        return Grid::create(new $this->playerDeliveryRecord, function (Grid $grid) use ($id, $date, $type) {
            $lang = Container::getInstance()->translator->getLocale();
            $grid->title(admin_trans('promoter_profit_record.player_activity_phase_record_title'));
            if (empty($date)) {
                $db = PromoterProfitRecord::whereRaw('promoter_player_id = ' . $id . ' and status = ' . PromoterProfitRecord::STATUS_UNCOMPLETED)
                    ->select('date');
                $grid->model()
                    ->whereIn('type', $type)
                    ->whereIn('player_id', function ($query) use ($id) {
                        $query->select('player_id')
                            ->from('promoter_profit_record')
                            ->where('promoter_player_id', $id)
                            ->where('status', PromoterProfitRecord::STATUS_UNCOMPLETED);
                    })
                    ->whereRaw("(FROM_UNIXTIME(UNIX_TIMESTAMP(updated_at), '%Y-%m-%d')" . " IN (" . $db->toSql() . "))")
                    ->orderBy('id', 'desc');
            } else {
                $grid->model()
                    ->whereIn('type', [
                        PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD,
                        PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT,
                        PlayerDeliveryRecord::TYPE_REGISTER_PRESENT,
                    ])
                    ->where('player_id', $id)
                    ->whereDate('updated_at', $date)
                    ->orderBy('id', 'desc');
            }

            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('player_delivery_record.fields.id'))->align('center');
            $grid->column('player.name', admin_trans('player.fields.name'))->display(function (
                $value,
                PlayerDeliveryRecord $data
            ) {
                $value = !empty($data->player->name) ? $data->player->name : $data->player->phone;
                return Html::create(Str::of($value)->limit(20, ' (...)'))
                    ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                    ->modal([$this, 'playerInfo'], ['player_id' => $data->player_id])
                    ->width('60%')->title(admin_trans('player.fields.phone') . ':' . $data->player->phone);
            })->align('center')->width(120)->ellipsis(true);

            $grid->column('source', admin_trans('player_delivery_record.fields.source'))->display(function (
                $val,
                PlayerDeliveryRecord $data
            ) use ($lang) {
                switch ($data->type) {
                    case PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD:
                    case PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT:
                        return Tag::create(trans($val, [], 'message', $lang))->color('red');
                    case PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS:
                        return Tag::create(trans($val, [], 'message', $lang))->color('blue');
                    default:
                        return '';
                }
            })->align('center');
            $grid->column('type', admin_trans('player_delivery_record.fields.type'))
                ->display(function ($value) {
                    switch ($value) {
                        case PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD))->color('#2db7f5');
                            break;
                        case PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT))->color('#108ee9');
                            break;
                        case PlayerDeliveryRecord::TYPE_REGISTER_PRESENT:
                            $tag = Tag::create(admin_trans('player_delivery_record.type.' . PlayerDeliveryRecord::TYPE_REGISTER_PRESENT))->color('#CC6600');
                            break;
                        default:
                            $tag = '';
                    }
                    return Html::create()->content([
                        $tag
                    ]);
                })->align('center')->sortable();
            $grid->column('amount', admin_trans('promoter_profit_record.fields.total_amount'))->display(function (
                $val,
                PlayerDeliveryRecord $data
            ) {
                if ($data->amount == 0) {
                    return Html::create()->content([$val])->style(['color' => 'green']);
                }
                switch ($data->type) {
                    case PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT:
                        return Html::create()->content(['+' . $val])->style(['color' => 'green']);
                    default:
                        return Html::create()->content(['-' . $val])->style(['color' => '#cd201f']);
                }
            })->align('center');
            $grid->column('user_name', admin_trans('player_delivery_record.fields.user_name'))->display(function (
                $val,
                PlayerDeliveryRecord $data
            ) {
                $name = '--';
                if (in_array($data->type, [
                    PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD,
                    PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT
                ])) {
                    $name = $data->user_name ?? '管理员';
                }
                return Html::create()->content([
                    Html::div()->content($name),
                ]);
            });
            $grid->column('created_at',
                admin_trans('promoter_profit_record.fields.date'))->align('center')->display(function ($val) {
                return date('Y-m-d', strtotime($val));
            })->ellipsis(true);
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
        });
    }

    /**
     * 电子游戏记录
     * @param $id
     * @param string $date
     * @return Grid
     */
    public function playGameRecord($id, string $date = ''): Grid
    {
        return Grid::create(new $this->playGameRecord, function (Grid $grid) use ($id, $date) {
            $grid->title(admin_trans('play_game_record.title'));
            $grid->model()
                ->when(!empty($id), function ($query) use ($id) {
                    $query->where('player_id', $id);
                })->when(!empty($date), function ($query) use ($date) {
                    $query->whereDate('updated_at', $date);
                });
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
                    Html::div()->content($data->player->uuid),
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
            })->align('center');
            $grid->column('diff',
                admin_trans('play_game_record.fields.diff'))->display(function ($val) {
                if ((float)$val > 0) {
                    return Html::create()->content(['+', (float)$val])->style(['color' => 'green']);
                }
                return Html::create()->content([(float)$val])->style(['color' => '#cd201f']);
            })->align('center');
            $grid->column('reward', admin_trans('play_game_record.fields.reward'))->display(function ($val) {
                return Html::create()->content(['+' . (float)$val])->style(['color' => 'green']);
            })->align('center');
            $grid->column('created_at', admin_trans('play_game_record.fields.create_at'))->align('center');
            $grid->column('action_at', admin_trans('play_game_record.fields.action_at'))->align('center');
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->like()->text('player.phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('order_no')->placeholder(admin_trans('play_game_record.fields.order_no'));
                $filter->like()->text('game_code')->placeholder(admin_trans('play_game_record.fields.game_code'));
                $filter->eq()->select('platform_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('game_platform.fields.name'))
                    ->remoteOptions(admin_url([
                        'addons-webman-controller-GamePlatformController',
                        'getGamePlatformOptions'
                    ]));
            });
        });
    }

    /**
     * 玩家详情
     * @param $player_id
     * @return Detail
     */
    public function playerInfo($player_id): Detail
    {
        $player = Player::query()->where('id', $player_id)->withTrashed()->first();
        return Detail::create($player, function (Detail $detail) {
            $detail->item('name', admin_trans('player.fields.name'));
            $detail->item('phone', admin_trans('player.fields.phone'));
            $detail->item('uuid', admin_trans('player.fields.uuid'));
            $detail->item('is_promoter', admin_trans('player.fields.is_promoter'))->display(function (
                $value,
                Player $data
            ) {
                return Html::create()->content([
                    Tag::create($value == 1 ? admin_trans('player.promoter') : admin_trans('player.not_promoter'))->color($value == 1 ? 'red' : 'orange'),
                    $data->player_promoter->name ?? ''
                ]);
            });
            $detail->item('player_promoter.ratio', admin_trans('player_promoter.fields.ratio'))->display(function (
                $value,
                Player $data
            ) {
                return $data->is_promoter == 1 ? floatval($value) . ' %' : '';
            });
            $detail->item('recommend_promoter.name',
                admin_trans('player_promoter.fields.recommend_promoter_name'))->display(function (
                $value,
                Player $data
            ) {
                if (isset($data->recommend_promoter) && !empty($data->recommend_promoter)) {
                    return Html::create(Str::of($value)->limit(20, ' (...)'))
                        ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                        ->modal([$this, 'playerInfo'], ['player_id' => $data->recommend_promoter->player_id])
                        ->width('60%')->title(admin_trans('player.fields.phone') . ':' . $data->recommend_promoter->player->phone);
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
            $detail->item('id', admin_trans('player.title'))->display(function ($val) {
                //展示所属下级
                return Html::create(admin_trans('machine_operation_log.view'))
                    ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                    ->modal([$this, 'promoterPlayers'], ['id' => $val])
                    ->width('80%')->title(admin_trans('player.title'));
            });
        })->bordered();
    }

    /**
     * 玩家详情
     * @param array $data
     * @return Detail
     */
    public function playerDetail(array $data): Detail
    {
        return Detail::create($data, function (Detail $detail) {
            $detail->item('name', admin_trans('player.fields.name'));
            $detail->item('address', admin_trans('player_extend.fields.address'));
            $detail->item('email', admin_trans('player_extend.fields.email'));
            $detail->item('phone', admin_trans('player.fields.phone'));
            $detail->item('line', admin_trans('player_extend.fields.line'));
            $detail->item('created_at', admin_trans('player.fields.created_at'));
        })->bordered();
    }

    /**
     * 结算详情
     * @param $id
     * @return Detail
     */
    public function settlementDetail($id): Detail
    {
        $settlementRecord = PromoterProfitSettlementRecord::find($id);
        return Detail::create($settlementRecord, function (Detail $detail) {
            $detail->item('tradeno',
                admin_trans('promoter_profit_settlement_record.fields.tradeno'))->display(function (
                $val,
                PromoterProfitSettlementRecord $data
            ) {
                return Html::create($val)
                    ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                    ->modal([$this, 'settlementDataList'], ['id' => $data->id])
                    ->width('60%')->title(admin_trans('promoter_profit_settlement_record.fields.tradeno') . ':' . $data->tradeno);
            })->copy();
            $detail->item('promoter.name', admin_trans('player_promoter.fields.name'));
            $detail->item('user_name', admin_trans('promoter_profit_settlement_record.fields.user_name'))->copy();
            $detail->item('total_profit_amount',
                admin_trans('promoter_profit_settlement_record.fields.total_profit_amount'))->display(function ($val) {
                return Html::create()->content([
                    $val > 0 ? '+' . $val : $val,
                ])->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            });
            $detail->item('last_profit_amount',
                admin_trans('promoter_profit_settlement_record.fields.last_profit_amount'))->display(function ($val) {
                return Html::create()->content([
                    $val > 0 ? '+' . $val : $val,
                ])->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            });
            $detail->item('total_machine_up_amount',
                admin_trans('promoter_profit_settlement_record.fields.total_machine_up_amount'));
            $detail->item('total_machine_down_amount',
                admin_trans('promoter_profit_settlement_record.fields.total_machine_down_amount'));
            $detail->item('total_bonus_amount',
                admin_trans('promoter_profit_settlement_record.fields.total_bonus_amount'));
            $detail->item('total_lottery_amount',
                admin_trans('promoter_profit_settlement_record.fields.total_lottery_amount'));
            $detail->item('total_withdraw_amount',
                admin_trans('promoter_profit_settlement_record.fields.total_withdraw_amount'));
            $detail->item('total_recharge_amount',
                admin_trans('promoter_profit_settlement_record.fields.total_recharge_amount'));
            $detail->item('total_present_amount',
                admin_trans('promoter_profit_settlement_record.fields.total_present_amount'));
            $detail->item('total_admin_add_amount',
                admin_trans('promoter_profit_settlement_record.fields.total_admin_add_amount'));
            $detail->item('total_admin_deduct_amount',
                admin_trans('promoter_profit_settlement_record.fields.total_admin_deduct_amount'));
            $detail->item('created_at',
                admin_trans('promoter_profit_settlement_record.fields.created_at'))->display(function ($val) {
                return $val->toDateTimeString() ?? '';
            });
        })->bordered();
    }

    /**
     * 结算详情
     * @param $id
     * @auth true
     * @return Grid
     */
    public function settlementDataList($id): Grid
    {
        return Grid::create(new $this->promoterProfitRecord, function (Grid $grid) use ($id) {
            $grid->title(admin_trans('promoter_profit_record.title'));
            $grid->model()->with(['player_promoter.player_extend', 'player_promoter', 'player'])->where('settlement_id',
                $id)->orderBy('id', 'desc');
            $this->profitRecordGrid($grid);
        });
    }

    /**
     * 分润报表
     */
    public function promoterProfitRecord($id, $type): Grid
    {
        return Grid::create(new $this->promoterProfitRecord, function (Grid $grid) use ($id, $type) {
            /** @var PlayerPromoter $playerPromoter */
            $grid->title(admin_trans('promoter_profit_record.title'));
            $grid->model()->with([
                'promoter',
                'player_promoter',
                'player_promoter.player_extend',
                'player',
                'player.player_extend'
            ])->orderBy('id', 'desc');
            switch ($type) {
                case 'profit_amount':
                    $grid->model()->where('promoter_player_id', $id)->where('status',
                        PromoterProfitRecord::STATUS_UNCOMPLETED);
                    break;
                case 'total_profit_amount':
                    $grid->model()->where('settlement_id', $id);
                    break;
                case 'profit_amount_team':
                    $grid->model()->where('promoter_player_id', $id)->where('source_player_id', '!=',
                        $id)->where('status', PromoterProfitRecord::STATUS_UNCOMPLETED);
                    break;
                case 'player_profit_amount':
                    $grid->model()->where('promoter_player_id', $id)->where('source_player_id', $id)->where('status',
                        PromoterProfitRecord::STATUS_UNCOMPLETED);
                    break;
                case 'settlement_amount':
                    $grid->model()->where('promoter_player_id', $id)->where('status',
                        PromoterProfitRecord::STATUS_COMPLETED);
                    break;
                case 'team_total_profit_amount':
                    $grid->model()->whereHas('promoter', function ($query) use ($id) {
                        $query->whereRaw("FIND_IN_SET({$id},path)");
                    });
                    break;
                case 'team_profit_amount':
                    $grid->model()->whereHas('promoter', function ($query) use ($id) {
                        $query->whereRaw("FIND_IN_SET({$id},path)");
                    })->where('status', PromoterProfitRecord::STATUS_UNCOMPLETED);
                    break;
                case 'team_settlement_amount':
                    $grid->model()->whereHas('promoter', function ($query) use ($id) {
                        $query->whereRaw("FIND_IN_SET({$id},path)");
                    })->where('status', PromoterProfitRecord::STATUS_COMPLETED);
                    break;
                case 'last_profit_amount':
                    /** @var PromoterProfitSettlementRecord $record */
                    $record = PromoterProfitSettlementRecord::where('promoter_player_id', $id)->orderBy('created_at',
                        'desc')->first();
                    $grid->model()->where('promoter_player_id', $id)->where('settlement_id',
                        $record->id ?? 0)->where('status', PromoterProfitRecord::STATUS_COMPLETED);
                    break;
                default:
                    $grid->model()->where('promoter_player_id', $id);
                    break;
            }
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            if (!empty($exAdminFilter['settlement_time_start'])) {
                $grid->model()->where('settlement_time', '>=', $exAdminFilter['settlement_time_start']);
            }
            if (!empty($exAdminFilter['settlement_time_end'])) {
                $grid->model()->where('settlement_time', '<=', $exAdminFilter['settlement_time_end']);
            }
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('promoter_profit_record.fields.id'))->fixed(true)->align('center');
            if ($type == 'profit_amount_team') {
                $grid->column('source_promoter.name',
                    admin_trans('player_promoter.source_promoter_name'))->display(function (
                    $value,
                    PromoterProfitRecord $data
                ) {
                    $value = !empty($value) ? $value : $data->source_promoter->name;
                    return Html::create(Str::of($value)->limit(20, ' (...)'))
                        ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                        ->modal([$this, 'playerInfo'], ['player_id' => $data->source_player_id])
                        ->width('60%')->title(admin_trans('player.fields.phone') . ':' . $data->source_promoter->player->phone);
                })->fixed(true)->align('center')->ellipsis(true);
            } else {
                $grid->column('promoter.name', admin_trans('player_promoter.fields.name'))->display(function (
                    $value,
                    PromoterProfitRecord $data
                ) {
                    $value = !empty($value) ? $value : $data->promoter->name;
                    return Html::create(Str::of($value)->limit(20, ' (...)'))
                        ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                        ->modal([$this, 'playerInfo'], ['player_id' => $data->promoter_player_id])
                        ->width('60%')->title(admin_trans('player.fields.phone') . ':' . $data->player_promoter->phone);
                })->fixed(true)->align('center')->ellipsis(true);
            }
            $grid->column('player.name', admin_trans('promoter_profit_record.player_info'))->display(function (
                $value,
                PromoterProfitRecord $data
            ) {
                $value = !empty($value) ? $value : $data->player->phone;
                return Html::create(Str::of($value)->limit(20, ' (...)'))
                    ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                    ->modal([$this, 'playerInfo'], ['player_id' => $data->player_id])
                    ->width('60%')->title(admin_trans('player.fields.phone') . ':' . $data->player->phone);
            })->fixed(true)->align('center')->ellipsis(true);

            $grid->column('status', admin_trans('promoter_profit_record.fields.status'))->display(function ($val) {
                return Tag::create(admin_trans('promoter_profit_record.status.' . $val))->color($val == 0 ? 'orange' : 'cyan');
            })->align('center')->ellipsis(true);
            $grid->column('actual_ratio', admin_trans('promoter_profit_record.fields.actual_ratio'))->display(function (
                $val
            ) {
                return floatval($val);
            })->align('center')->append('%');
            $grid->column(function (Grid $grid) {
                $grid->column('profit_amount',
                    admin_trans('promoter_profit_record.fields.profit_amount'))->display(function (
                    $val,
                    PromoterProfitRecord $data
                ) {
                    return Html::create()->content([
                        $val > 0 ? '+' . $val : $val,
                    ])->style(['color' => ($val < 0 ? '#cd201f' : 'green'), 'cursor' => 'pointer'])
                        ->modal([$this, 'promoterProfitRecordDetail'], [
                            'id' => $data->player_id,
                            'date' => $data->date,
                            'active_key' => '1',
                            'data' => $data->toArray(),
                        ])
                        ->width('80%')->title(Html::create()->content([
                            admin_trans('promoter_profit_record.fields.profit_amount'),
                            ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                                'margin-left' => '4px',
                                'cursor' => 'pointer'
                            ]))->title(admin_trans('promoter_profit_record.profit_amount_tip'))
                        ]));
                })->align('center')->width(120)->ellipsis(true)->sortable();

                $grid->column('machine_up_amount',
                    admin_trans('promoter_profit_record.fields.machine_up_amount'))->display(function (
                    $val,
                    PromoterProfitRecord $data
                ) {
                    return Html::create()->content([$val != 0 ? floatval($val) : ''])
                        ->style(['color' => 'blue', 'cursor' => 'pointer'])
                        ->modal([$this, 'promoterProfitRecordDetail'], [
                            'id' => $data->player_id,
                            'date' => $data->date,
                            'active_key' => '1',
                            'data' => $data->toArray(),
                        ])
                        ->width('80%')->title(admin_trans('promoter_profit_record.fields.machine_up_amount'));

                })->align('center')->width(120)->ellipsis(true);

                $grid->column('machine_down_amount',
                    admin_trans('promoter_profit_record.fields.machine_down_amount'))->display(function (
                    $val,
                    PromoterProfitRecord $data
                ) {
                    return Html::create()->content([$val != 0 ? floatval($val) : ''])
                        ->style(['color' => 'blue', 'cursor' => 'pointer'])
                        ->modal([$this, 'promoterProfitRecordDetail'], [
                            'id' => $data->player_id,
                            'date' => $data->date,
                            'active_key' => '1',
                            'data' => $data->toArray(),
                        ])
                        ->width('80%')->title(admin_trans('promoter_profit_record.fields.machine_down_amount'));
                })->align('center')->width(120)->ellipsis(true);

                $grid->column('bonus_amount',
                    admin_trans('promoter_profit_record.fields.bonus_amount'))->display(function (
                    $val,
                    PromoterProfitRecord $data
                ) {
                    return Html::create()->content([$val != 0 ? floatval($val) : ''])
                        ->style(['color' => 'blue', 'cursor' => 'pointer'])
                        ->modal([$this, 'promoterProfitRecordDetail'], [
                            'id' => $data->player_id,
                            'date' => $data->date,
                            'active_key' => '2',
                            'data' => $data->toArray(),
                        ])
                        ->width('80%')->title(admin_trans('promoter_profit_record.fields.bonus_amount'));
                })->align('center')->width(120)->ellipsis(true);

                $grid->column('lottery_amount',
                    admin_trans('promoter_profit_record.fields.lottery_amount'))->display(function (
                    $val,
                    PromoterProfitRecord $data
                ) {
                    return Html::create()->content([$val != 0 ? floatval($val) : ''])
                        ->style(['color' => 'blue', 'cursor' => 'pointer'])
                        ->modal([$this, 'promoterProfitRecordDetail'], [
                            'id' => $data->player_id,
                            'date' => $data->date,
                            'active_key' => '3',
                            'data' => $data->toArray(),
                        ])
                        ->width('80%')->title(admin_trans('promoter_profit_record.fields.lottery_amount'));
                })->align('center')->width(120)->ellipsis(true);

                $grid->column('withdraw_amount',
                    admin_trans('promoter_profit_record.fields.withdraw_amount'))->display(function (
                    $val,
                    PromoterProfitRecord $data
                ) {
                    return Html::create()->content([$val != 0 ? floatval($val) : ''])
                        ->style(['color' => 'blue', 'cursor' => 'pointer'])
                        ->modal([$this, 'promoterProfitRecordDetail'], [
                            'id' => $data->player_id,
                            'date' => $data->date,
                            'active_key' => '5',
                            'data' => $data->toArray(),
                        ])
                        ->width('80%')->title(admin_trans('promoter_profit_record.fields.withdraw_amount'));
                })->align('center')->width(120)->ellipsis(true);
                $grid->column('recharge_amount',
                    admin_trans('promoter_profit_record.fields.recharge_amount'))->display(function (
                    $val,
                    PromoterProfitRecord $data
                ) {
                    return Html::create()->content([$val != 0 ? floatval($val) : ''])
                        ->style(['color' => 'blue', 'cursor' => 'pointer'])
                        ->modal([$this, 'promoterProfitRecordDetail'], [
                            'id' => $data->player_id,
                            'date' => $data->date,
                            'active_key' => '4',
                            'data' => $data->toArray(),
                        ])
                        ->width('80%')->title(admin_trans('promoter_profit_record.fields.recharge_amount'));
                })->align('center')->width(120)->ellipsis(true);

                $grid->column('admin_add_amount',
                    admin_trans('promoter_profit_record.fields.admin_add_amount'))->display(function (
                    $val,
                    PromoterProfitRecord $data
                ) {
                    return Html::create()->content([$val != 0 ? floatval($val) : ''])
                        ->style(['color' => 'blue', 'cursor' => 'pointer'])
                        ->modal([$this, 'promoterProfitRecordDetail'], [
                            'id' => $data->player_id,
                            'date' => $data->date,
                            'active_key' => '6',
                            'data' => $data->toArray(),
                        ])
                        ->width('80%')->title(admin_trans('promoter_profit_record.fields.admin_add_amount'));
                })->align('center')->width(120)->ellipsis(true);

                $grid->column('admin_deduct_amount',
                    admin_trans('promoter_profit_record.fields.admin_deduct_amount'))->display(function (
                    $val,
                    PromoterProfitRecord $data
                ) {
                    return Html::create()->content([$val != 0 ? floatval($val) : ''])
                        ->style(['color' => 'blue', 'cursor' => 'pointer'])
                        ->modal([$this, 'promoterProfitRecordDetail'], [
                            'id' => $data->player_id,
                            'date' => $data->date,
                            'active_key' => '6',
                            'data' => $data->toArray(),
                        ])
                        ->width('80%')->title(admin_trans('promoter_profit_record.fields.admin_deduct_amount'));
                })->align('center')->width(120)->ellipsis(true);

                $grid->column('present_amount',
                    admin_trans('promoter_profit_record.fields.present_amount'))->display(function (
                    $val,
                    PromoterProfitRecord $data
                ) {
                    return Html::create()->content([$val != 0 ? floatval($val) : ''])
                        ->style(['color' => 'blue', 'cursor' => 'pointer'])
                        ->modal([$this, 'promoterProfitRecordDetail'], [
                            'id' => $data->player_id,
                            'date' => $data->date,
                            'active_key' => '7',
                            'data' => $data->toArray(),
                        ])
                        ->width('80%')->title(admin_trans('promoter_profit_record.fields.present_amount'));
                })->align('center')->width(120)->ellipsis(true);

                $grid->column('game_amount',
                    admin_trans('promoter_profit_record.fields.game_amount'))->display(function (
                    $val,
                    PromoterProfitRecord $data
                ) {
                    return Html::create()->content([$val != 0 ? floatval($val) : ''])
                        ->style(['color' => 'blue', 'cursor' => 'pointer'])
                        ->modal([$this, 'promoterProfitRecordDetail'], [
                            'id' => $data->player_id,
                            'date' => $data->date,
                            'active_key' => '8',
                            'data' => $data->toArray(),
                        ])
                        ->width('80%')->title(admin_trans('promoter_profit_record.fields.game_amount'));
                })->align('center')->width(120)->ellipsis(true);
            }, admin_trans('promoter_profit_record.profit_record'))->ellipsis(true);

            $grid->column('date',
                admin_trans('promoter_profit_record.fields.date'))->fixed('right')->align('center')->ellipsis(true);
            $grid->column('settlement_time',
                admin_trans('promoter_profit_record.fields.settlement_time'))->fixed('right')->align('center')->ellipsis(true);
            $grid->filter(function (Filter $filter) use ($type) {
                if ($type == 'team_total_profit_amount' || $type == 'team_profit_amount') {
                    $filter->like()->text('promoter.name')->placeholder(admin_trans('player_promoter.fields.name'));
                }
                $filter->like()->text('player_promoter.phone')->placeholder(admin_trans('promoter_profit_record.player_promoter.phone'));
                $filter->like()->text('player_promoter.uuid')->placeholder(admin_trans('promoter_profit_record.player_promoter.uuid'));
                $filter->like()->text('player.name')->placeholder(admin_trans('player.fields.name'));
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->like()->text('player.phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->select('status')
                    ->placeholder(admin_trans('promoter_profit_record.fields.status'))
                    ->showSearch()
                    ->dropdownMatchSelectWidth()
                    ->style(['width' => '200px'])
                    ->options([
                        PromoterProfitRecord::STATUS_UNCOMPLETED => admin_trans('promoter_profit_record.status.' . PromoterProfitRecord::STATUS_UNCOMPLETED),
                        PromoterProfitRecord::STATUS_COMPLETED => admin_trans('promoter_profit_record.status.' . PromoterProfitRecord::STATUS_COMPLETED),
                    ]);
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
                $filter->form()->hidden('settlement_time_start');
                $filter->form()->hidden('settlement_time_end');
                $filter->form()->dateRange('settlement_time_start', 'settlement_time_end', '')->placeholder([
                    admin_trans('promoter_profit_record.settlement_time_start'),
                    admin_trans('promoter_profit_record.settlement_time_end')
                ]);
            });
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->export();
            $grid->actions(function (Actions $actions, PromoterProfitRecord $data) {
                $actions->hideDel();
                if ($data->status == PromoterProfitRecord::STATUS_COMPLETED && !empty($data->settlement_id)) {
                    $actions->prepend(
                        Button::create(admin_trans('promoter_profit_record.settlement_detail'))
                            ->type('primary')
                            ->modal([$this, 'settlementDataList'], ['id' => $data->settlement_id])
                            ->width('60%')->title(admin_trans('promoter_profit_record.fields.settlement_tradeno') . ':' . $data->settlement_tradeno)
                    );
                }
            });
        });
    }

    /**
     * 直系团队
     * @param $id
     * @return Grid
     */
    public function promoterTeam($id): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) use ($id) {
            $grid->title(admin_trans('player_promoter.title'));
            $grid->model()->where('recommend_id', $id)->orderBy('id', 'desc');
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('player_promoter.fields.id'))->align('center');

            $grid->column('name', admin_trans('player_promoter.fields.name'))->display(function (
                $value,
                PlayerPromoter $data
            ) {
                $value = !empty($value) ? $value : $data->player->phone;
                return Html::create(Str::of($value)->limit(20, ' (...)'))
                    ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                    ->modal([$this, 'playerInfo'], ['player_id' => $data->player_id])
                    ->width('60%')->title(admin_trans('player.fields.phone') . ':' . $data->player->phone);
            })->fixed(true)->align('center')->width(120)->ellipsis(true);

            $grid->column('status', admin_trans('player_promoter.fields.status'))->display(function ($val) {
                return Tag::create(admin_trans('public_msg.status.' . $val))->color($val == 0 ? 'orange' : 'cyan');
            })->align('center')->ellipsis(true);
            $grid->column('ratio', admin_trans('player_promoter.fields.ratio'))->display(function ($val) {
                return floatval($val);
            })->align('center')->append('%');

            $grid->column(function (Grid $grid) {
                $grid->column('player_num', admin_trans('player_promoter.fields.player_num'))
                    ->display(function ($val, PlayerPromoter $data) {
                        return Statistic::create()
                            ->value($val)
                            ->valueStyle(['fontSize' => '14px'])
                            ->precision(0)
                            ->prefix(Icon::create('far fa-user')->style(['fontSize' => '14px']))
                            ->style(['cursor' => 'pointer'])
                            ->modal([$this, 'promoterPlayers'], ['id' => $data->player_id])
                            ->width('80%')->title(admin_trans('player_promoter.promoter_players'));
                    })
                    ->align('center')
                    ->width(120)
                    ->ellipsis(true);
                $grid->column('team_num', admin_trans('player_promoter.fields.team_num'))
                    ->display(function ($val, PlayerPromoter $data) {
                        return Statistic::create()
                            ->value($val)
                            ->valueStyle(['fontSize' => '14px'])
                            ->precision(0)
                            ->prefix(Icon::create('TeamOutlined')->style(['fontSize' => '14px']))
                            ->style(['cursor' => 'pointer'])
                            ->modal([$this, 'promoterTeam'], ['id' => $data->player_id])
                            ->width('80%')->title(admin_trans('player_promoter.promoter_team'));
                    })
                    ->align('center')
                    ->width(120)
                    ->ellipsis(true);

                $grid->column('total_profit_amount',
                    admin_trans('player_promoter.fields.total_profit_amount'))->display(function (
                    $val,
                    PlayerPromoter $data
                ) {
                    return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                        ->style(['color' => ($val < 0 ? '#cd201f' : 'green'), 'cursor' => 'pointer'])
                        ->modal([$this, 'promoterProfitRecord'],
                            ['id' => $data->player_id, 'type' => 'total_profit_amount'])
                        ->width('80%')->title(admin_trans('player_promoter.fields.total_profit_amount'));
                })->align('center')->width(120)->ellipsis(true);

                $grid->column('profit_amount', admin_trans('player_promoter.fields.profit_amount'))->display(function (
                    $val,
                    PlayerPromoter $data
                ) {
                    return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                        ->style(['color' => ($val < 0 ? '#cd201f' : 'green'), 'cursor' => 'pointer'])
                        ->modal([$this, 'promoterProfitRecord'], ['id' => $data->player_id, 'type' => 'profit_amount'])
                        ->width('80%')->title(admin_trans('player_promoter.fields.profit_amount'));
                })->align('center')->width(120)->ellipsis(true);

            }, admin_trans('player_promoter.promoter_info'))->ellipsis(true);

            $grid->column(function (Grid $grid) {

                $grid->column('team_total_profit_amount',
                    admin_trans('player_promoter.fields.team_total_profit_amount'))->display(function (
                    $val,
                    PlayerPromoter $data
                ) {
                    return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                        ->style(['color' => ($val < 0 ? '#cd201f' : 'green'), 'cursor' => 'pointer'])
                        ->modal([$this, 'promoterProfitRecord'],
                            ['id' => $data->player_id, 'type' => 'team_total_profit_amount'])
                        ->width('80%')->title(admin_trans('player_promoter.fields.team_total_profit_amount'));
                })->align('center')->width(120)->ellipsis(true);

                $grid->column('team_profit_amount',
                    admin_trans('player_promoter.fields.team_profit_amount'))->display(function (
                    $val,
                    PlayerPromoter $data
                ) {
                    return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                        ->style(['color' => ($val < 0 ? '#cd201f' : 'green'), 'cursor' => 'pointer'])
                        ->modal([$this, 'promoterProfitRecord'],
                            ['id' => $data->player_id, 'type' => 'team_profit_amount'])
                        ->width('80%')->title(admin_trans('player_promoter.fields.team_profit_amount'));
                })->align('center')->width(120)->ellipsis(true);

            }, admin_trans('player_promoter.promoter_team_info'))->ellipsis(true);

            $grid->column('created_at',
                admin_trans('player_promoter.fields.created_at'))->align('center')->ellipsis(true);
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('name')->placeholder(admin_trans('player_promoter.fields.name'));
                $filter->like()->text('player.phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);;
            });
            $grid->expandFilter();
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
        });
    }

    /**
     * 直系玩家
     * @param $id
     * @return Grid
     */
    public function promoterPlayers($id = ''): Grid
    {
        return Grid::create(new $this->player(), function (Grid $grid) use ($id) {
            $grid->title(admin_trans('player.title'));
            $requestFilter = Request::input('ex_admin_filter', []);
            $grid->model()->where('recommend_id', $id)->orderBy('created_at', 'desc');
            if (!empty($requestFilter['search_type'])) {
                $grid->model()->where('is_coin', $requestFilter['search_type']);
            }
            if (!empty($requestFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $requestFilter['created_at_start']);
            }
            if (!empty($requestFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $requestFilter['created_at_end']);
            }
            $grid->autoHeight();
            $grid->bordered();
            $grid->column('id', admin_trans('player.fields.id'))->ellipsis(true)->align('center')->fixed(true);
            $grid->column('phone', admin_trans('player.fields.phone'))->display(function ($val, Player $data) {
                $image = $data->avatar ? Avatar::create()->src(is_numeric($data->avatar) ? config('def_avatar.' . $data->avatar) : $data->avatar) : Avatar::create()->icon(Icon::create('UserOutlined'));
                return Html::create()->content([
                    $image,
                    Html::div()->content($val),
                ]);
            })->ellipsis(true)->fixed(true)->align('center')->filter(
                FilterColumn::like()->text('phone')
            );
            $grid->column('name', admin_trans('player.fields.name'))->ellipsis(true)->align('center')->fixed(true);
            $grid->column('uuid', admin_trans('player.fields.uuid'))->ellipsis(true)->align('center');
            $grid->column('player_extend.email', admin_trans('player_extend.fields.email'))->align('center');
            $grid->column('type', admin_trans('player.fields.type'))->display(function ($val, Player $data) {
                $tags[] = Tag::create(admin_trans('player.player'))->color('green');
                if ($data->is_coin == 1) {
                    $tags[] = Tag::create(admin_trans('player.coin_merchant'))->color('#3b5999');
                }
                if ($data->is_promoter == 1) {
                    $tags[] = Tag::create(admin_trans('player.promoter'))->color('purple');
                }
                return Html::create()->content($tags)->style(['display' => 'inline-flex', 'text-align' => 'center']);
            })->ellipsis(true)->width(200)->align('center');
            $grid->column('status', admin_trans('player.fields.status'))->display(function ($val) {
                return Tag::create(admin_trans('public_msg.status.' . $val))->color($val == 0 ? 'orange' : 'cyan');
            })->align('center')->ellipsis(true);
            $grid->column('machine_wallet.money',
                admin_trans('player_platform_cash.platform_name.' . PlayerPlatformCash::PLATFORM_SELF))->display(function (
                $val,
                Player $data
            ) {
                return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal([
                    ChannelPlayerController::class,
                    'playerRecord'
                ], ['id' => $data->id])->width('70%')->title($data->name . ' ' . $data->uuid);
            })->ellipsis(true)->align('center');
            $grid->column('status_national', admin_trans('player.fields.status_national'))
                ->display(function ($value,$data)use($grid) {
                    if($data['is_promoter'] == 0){
                        return Switches::create(null, $value)
                            ->options([[1 => admin_trans('admin.open')], [0 => admin_trans('admin.close')]])
                            ->url($grid->attr('url'))
                            ->field('status_national')
                            ->params([
                                'ex_admin_action' => 'update',
                                'ids' => [$data[$grid->driver()->getPk()]],
                            ]);
                    }else{
                        return '';
                    }
                })->ellipsis(true)->align('center');
            $grid->column('created_at', admin_trans('player.fields.created_at'))->display(function (
                $val,
                Player $data
            ) {
                return Html::create()->content([
                    Html::div()->content(date('Y-m-d H:i:s', strtotime($val))),
                ]);
            })->ellipsis(true)->align('center');
            $grid->column('the_last_player_login_record.created_at',
                admin_trans('player.fields.player_login_record'))->display(function ($val, Player $data) {
                return Html::create()->content([
                    Html::div()->content($val ? date('Y-m-d H:i:s', strtotime($val)) : ''),
                ]);
            })->ellipsis(true)->align('center');
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->like()->text('name')->placeholder(admin_trans('player.fields.name'));
                $filter->like()->text('player_extend.email')->placeholder(admin_trans('player_extend.fields.email'));
                $filter->select('search_type')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.type'))
                    ->options([
                        0 => admin_trans('player.player'),
                        1 => admin_trans('player.coin_merchant'),
                    ]);
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
        });
    }
}
