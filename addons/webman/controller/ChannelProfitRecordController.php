<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Channel;
use addons\webman\model\ChannelProfitRecord;
use addons\webman\model\ChannelProfitSettlementRecord;
use addons\webman\model\PromoterProfitSettlementRecord;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\image\Image;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\grid\ToolTip;
use ExAdmin\ui\response\Msg;
use ExAdmin\ui\support\Request;
use support\Db;

/**
 * 渠道分润
 * @group channel
 */
class ChannelProfitRecordController
{
    protected $model;
    
    protected $channelProfitRecord;
    
    protected $channelProfitSettlementRecord;
    
    
    public function __construct()
    {
        $this->model = plugin()->webman->config('database.channel_model');
        $this->channelProfitRecord = plugin()->webman->config('database.channel_profit_record_model');
        $this->channelProfitSettlementRecord = plugin()->webman->config('database.channel_profit_settlement_record_model');
    }
    
    /**
     * 分润报表
     * @auth true
     * @return Grid
     */
    public function index(): Grid
    {
        return Grid::create(new $this->channelProfitRecord, function (Grid $grid) {
            $grid->title(admin_trans('channel_profit_record.title'));
            $grid->model()->with([
                'channel'
            ])->orderBy('id', 'desc');
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
        $grid->autoHeight();
        $grid->bordered(true);
        $grid->column('id', admin_trans('channel_profit_record.fields.id'))->fixed(true)->align('center');
        $grid->column('channel.name', admin_trans('channel.fields.name'))->align('center')->ellipsis(true);
        $grid->column('status', admin_trans('channel_profit_record.fields.status'))->display(function ($val) {
            return Tag::create(admin_trans('channel_profit_record.status.' . $val))->color($val == 0 ? 'orange' : 'cyan');
        })->align('center')->ellipsis(true);
        $grid->column('settlement_tradeno',
            admin_trans('channel_profit_record.fields.settlement_tradeno'))->display(function (
            $val,
            ChannelProfitRecord $data
        ) {
            return Html::create($val)
                ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                ->modal([$this, 'settlementDataList'], ['id' => $data->settlement_id])
                ->width('60%')->title(admin_trans('channel_profit_record.fields.settlement_tradeno') . ':' . $data->settlement_tradeno);
        })->align('center')->ellipsis(true)->copy();
        $grid->column('ratio', admin_trans('channel_profit_record.fields.ratio'))->display(function ($val) {
            return floatval($val);
        })->align('center')->ellipsis(true)->append('%')->sortable();
        $grid->column('profit_amount',
            admin_trans('channel_profit_record.fields.profit_amount'))->display(function (
            $val,
        ) {
            return Html::create()->content([
                $val > 0 ? '+' . $val : $val,
            ])->style(['color' => ($val < 0 ? '#cd201f' : 'green'), 'cursor' => 'pointer']);
        })->align('center')->width(120)->ellipsis(true)->sortable();
        $grid->column('self_profit_amount',
            admin_trans('channel_profit_record.fields.self_profit_amount'))->display(function (
            $val,
        ) {
            return Html::create()->content([
                $val > 0 ? '+' . $val : $val,
            ])->style(['color' => ($val < 0 ? '#cd201f' : 'green'), 'cursor' => 'pointer']);
        })->align('center')->width(120)->ellipsis(true)->sortable();
        
        $grid->column('machine_up_amount',
            admin_trans('channel_profit_record.fields.machine_up_amount'))->align('center')->width(120)->ellipsis(true);
        $grid->column('machine_down_amount',
            admin_trans('channel_profit_record.fields.machine_down_amount'))->align('center')->width(120)->ellipsis(true);
        $grid->column('bonus_amount',
            admin_trans('channel_profit_record.fields.bonus_amount'))->align('center')->width(120)->ellipsis(true);
        $grid->column('lottery_amount',
            admin_trans('channel_profit_record.fields.lottery_amount'))->align('center')->width(120)->ellipsis(true);
        $grid->column('withdraw_amount',
            admin_trans('channel_profit_record.fields.withdraw_amount'))->align('center')->width(120)->ellipsis(true);
        $grid->column('recharge_amount',
            admin_trans('channel_profit_record.fields.recharge_amount'))->align('center')->width(120)->ellipsis(true);
        $grid->column('admin_add_amount',
            admin_trans('channel_profit_record.fields.admin_add_amount'))->align('center')->width(120)->ellipsis(true);
        $grid->column('admin_deduct_amount',
            admin_trans('channel_profit_record.fields.admin_deduct_amount'))->align('center')->width(120)->ellipsis(true);
        $grid->column('present_amount',
            admin_trans('channel_profit_record.fields.present_amount'))->align('center')->width(120)->ellipsis(true);
        $grid->column('game_amount',
            admin_trans('channel_profit_record.fields.game_amount'))->width(120)->ellipsis(true);
        $grid->column('water_amount',
            admin_trans('channel_profit_record.fields.water_amount'))->align('center')->width(120)->ellipsis(true);
        $grid->column('date', admin_trans('channel_profit_record.fields.date'))
            ->header(Html::create(admin_trans('channel_profit_record.fields.date'))->content(
                ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                    'marginLeft' => '5px',
                    'cursor' => 'pointer'
                ]))->title(admin_trans('channel_profit_record.date_tip'))
            ))->width(150)->fixed('right')->align('center')->ellipsis(true);
        $grid->column('settlement_time',
            admin_trans('channel_profit_record.fields.settlement_time'))->fixed('right')->align('center')->ellipsis(true);
        $grid->filter(function (Filter $filter) {
            $filter->eq()->select('department_id')
                ->showSearch()
                ->style(['width' => '200px'])
                ->dropdownMatchSelectWidth()
                ->placeholder(admin_trans('player.fields.department_id'))
                ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
            $filter->like()->text('settlement_tradeno')->placeholder(admin_trans('channel_profit_record.fields.settlement_tradeno'));
            $filter->select('status')
                ->placeholder(admin_trans('channel_profit_record.fields.status'))
                ->showSearch()
                ->dropdownMatchSelectWidth()
                ->style(['width' => '200px'])
                ->options([
                    ChannelProfitRecord::STATUS_UNCOMPLETED => admin_trans('channel_profit_record.status.' . ChannelProfitRecord::STATUS_UNCOMPLETED),
                    ChannelProfitRecord::STATUS_COMPLETED => admin_trans('channel_profit_record.status.' . ChannelProfitRecord::STATUS_COMPLETED),
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
                admin_trans('channel_profit_record.settlement_time_start'),
                admin_trans('channel_profit_record.settlement_time_end')
            ]);
        });
        $grid->actions(function (Actions $actions) {
            $actions->hideDel();
        });
        $grid->hideDelete();
        $grid->hideSelection();
        $grid->hideTrashed();
        $grid->expandFilter();
    }
    
    /**
     * 分润结算
     * @auth true
     * @return Grid
     */
    public function settlementIndex(): Grid
    {
        return Grid::create(new $this->model, function (Grid $grid) {
            $grid->title(admin_trans('channel.title'));
            $grid->model()->with(['department'])->where('type', '!=', Channel::TYPE_STORE)->orderBy('created_at',
                'desc');
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('department_id', admin_trans('channel.fields.id'))->align('center')->fixed(true);
            $grid->column('name', admin_trans('channel.fields.name'))->align('center')->fixed(true);
            $grid->column('player_num', admin_trans('channel.fields.player_num'))->display(function (
                $val,
                Channel $data
            ) {
                return $data->player->count();
            })->align('center');
            $grid->column('adjust_amount', admin_trans('player_promoter.fields.adjust_amount'))->display(function ($val
            ) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            })->header(Html::create(admin_trans('player_promoter.fields.adjust_amount'))->content(
                ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                    'marginLeft' => '5px',
                    'cursor' => 'pointer'
                ]))->title(admin_trans('player_promoter.adjust_amount_tip'))
            ))->editable(
                (new Editable)->number('adjust_amount')
                    ->min(-10000000)
                    ->max(10000000)
            )->align('center')->width(120)->ellipsis(true);
            $grid->column('profit_amount', admin_trans('channel.fields.profit_amount'))->align('center');
            $grid->column('total_profit_amount', admin_trans('channel.fields.total_profit_amount'))->align('center');
            $grid->column('ratio', admin_trans('channel.fields.ratio'))->display(function (
                $val
            ) use ($grid) {
                return Html::create()->content([
                    floatval($val) . '%',
                ]);
            })->align('center');
            $grid->column('created_at', admin_trans('channel.fields.create_at'))->align('center');
            $grid->hideDelete();
            $grid->filter(function (Filter $filter) {
                $filter->eq()->text('id')->placeholder(admin_trans('channel.fields.id'));
                $filter->like()->text('name')->placeholder(admin_trans('channel.fields.name'));
                $filter->eq()->select('status')
                    ->placeholder(admin_trans('channel.fields.status'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        1 => admin_trans('channel.normal'),
                        0 => admin_trans('channel.disable')
                    ]);
            });
            $grid->expandFilter();
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions, Channel $data) {
                $dropdown = $actions->dropdown();
                $dropdown->item(admin_trans('player_promoter.settlement'), 'fas fa-handshake')
                    ->modal([$this, 'settlement'], ['id' => $data->department_id]);
                $dropdown->item(admin_trans('player_promoter.promoter_profit_record_detail'), 'fas fa-list')
                    ->modal([$this, 'channelProfitRecordDetail'], ['id' => $data->department_id])
                    ->width('80%');
                $actions->hideDel();
            });
        });
    }
    
    /**
     * @param $id
     * @return Grid
     */
    public function channelProfitRecordDetail($id): Grid
    {
        return Grid::create(new $this->channelProfitRecord, function (Grid $grid) use ($id) {
            $grid->model()->where('department_id', $id)->where('status', ChannelProfitRecord::STATUS_UNCOMPLETED);
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
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', admin_trans('channel_profit_record.fields.id'))->fixed(true)->align('center');
            $grid->column('channel.name', admin_trans('channel.fields.name'))->align('center')->ellipsis(true);
            $grid->column('status', admin_trans('channel_profit_record.fields.status'))->display(function ($val) {
                return Tag::create(admin_trans('channel_profit_record.status.' . $val))->color($val == 0 ? 'orange' : 'cyan');
            })->align('center')->ellipsis(true);
            $grid->column('settlement_tradeno',
                admin_trans('channel_profit_record.fields.settlement_tradeno'))->display(function (
                $val,
                ChannelProfitRecord $data
            ) {
                return Html::create($val)
                    ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                    ->modal([$this, 'settlementDataList'], ['id' => $data->settlement_id])
                    ->width('60%')->title(admin_trans('channel_profit_record.fields.settlement_tradeno') . ':' . $data->settlement_tradeno);
            })->align('center')->ellipsis(true)->copy();
            $grid->column('ratio', admin_trans('channel_profit_record.fields.ratio'))->display(function ($val) {
                return floatval($val);
            })->align('center')->ellipsis(true)->append('%')->sortable();
            $grid->column('profit_amount',
                admin_trans('channel_profit_record.fields.profit_amount'))->display(function (
                $val,
            ) {
                return Html::create()->content([
                    $val > 0 ? '+' . $val : $val,
                ])->style(['color' => ($val < 0 ? '#cd201f' : 'green'), 'cursor' => 'pointer']);
            })->align('center')->width(120)->ellipsis(true)->sortable();
            
            $grid->column('machine_up_amount',
                admin_trans('channel_profit_record.fields.machine_up_amount'))->align('center')->width(120)->ellipsis(true);
            $grid->column('machine_down_amount',
                admin_trans('channel_profit_record.fields.machine_down_amount'))->align('center')->width(120)->ellipsis(true);
            $grid->column('bonus_amount',
                admin_trans('channel_profit_record.fields.bonus_amount'))->align('center')->width(120)->ellipsis(true);
            $grid->column('lottery_amount',
                admin_trans('channel_profit_record.fields.lottery_amount'))->align('center')->width(120)->ellipsis(true);
            $grid->column('withdraw_amount',
                admin_trans('channel_profit_record.fields.withdraw_amount'))->align('center')->width(120)->ellipsis(true);
            $grid->column('recharge_amount',
                admin_trans('channel_profit_record.fields.recharge_amount'))->align('center')->width(120)->ellipsis(true);
            $grid->column('admin_add_amount',
                admin_trans('channel_profit_record.fields.admin_add_amount'))->align('center')->width(120)->ellipsis(true);
            $grid->column('admin_deduct_amount',
                admin_trans('channel_profit_record.fields.admin_deduct_amount'))->align('center')->width(120)->ellipsis(true);
            $grid->column('present_amount',
                admin_trans('channel_profit_record.fields.present_amount'))->align('center')->width(120)->ellipsis(true);
            $grid->column('game_amount',
                admin_trans('channel_profit_record.fields.game_amount'))->width(120)->ellipsis(true);
            $grid->column('water_amount',
                admin_trans('channel_profit_record.fields.water_amount'))->align('center')->width(120)->ellipsis(true);
            $grid->column('date', admin_trans('channel_profit_record.fields.date'))
                ->header(Html::create(admin_trans('channel_profit_record.fields.date'))->content(
                    ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                        'marginLeft' => '5px',
                        'cursor' => 'pointer'
                    ]))->title(admin_trans('channel_profit_record.date_tip'))
                ))->width(150)->fixed('right')->align('center')->ellipsis(true);
            $grid->column('settlement_time',
                admin_trans('channel_profit_record.fields.settlement_time'))->fixed('right')->align('center')->ellipsis(true);
            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('department_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->placeholder(admin_trans('player.fields.department_id'))
                    ->remoteOptions(admin_url(['addons-webman-controller-ChannelController', 'getDepartmentOptions']));
                $filter->like()->text('settlement_tradeno')->placeholder(admin_trans('channel_profit_record.fields.settlement_tradeno'));
                $filter->select('status')
                    ->placeholder(admin_trans('channel_profit_record.fields.status'))
                    ->showSearch()
                    ->dropdownMatchSelectWidth()
                    ->style(['width' => '200px'])
                    ->options([
                        ChannelProfitRecord::STATUS_UNCOMPLETED => admin_trans('channel_profit_record.status.' . ChannelProfitRecord::STATUS_UNCOMPLETED),
                        ChannelProfitRecord::STATUS_COMPLETED => admin_trans('channel_profit_record.status.' . ChannelProfitRecord::STATUS_COMPLETED),
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
                    admin_trans('channel_profit_record.settlement_time_start'),
                    admin_trans('channel_profit_record.settlement_time_end')
                ]);
            });
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->expandFilter();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
        });
    }
    
    /**
     * 轮播图
     * @auth true
     * @return Form
     */
    public function settlement(): Form
    {
        return Form::create([], function (Form $form) {
            $form->title('渠道结算');
            $form->layout('vertical');
        });
    }
    
    /**
     * 分润结算
     * @auth true
     * @param $id
     * @return Msg
     */
    public function doSettlement($id): Msg
    {
        /** @var Channel $channel */
        $channel = Channel::query()->where('department_id', $id)->whereNull('deleted_at')->first();
        if ($channel->status == 0) {
            return message_error(admin_trans('channel.channel_disable'));
        }
        $channelProfitRecord = ChannelProfitRecord::query()
            ->where('status', ChannelProfitRecord::STATUS_UNCOMPLETED)
            ->where('department_id', $id)
            ->first([
                DB::raw('SUM(`withdraw_amount`) as total_withdraw_amount'),
                DB::raw('SUM(`recharge_amount`) as total_recharge_amount'),
                DB::raw('SUM(`bonus_amount`) as total_bonus_amount'),
                DB::raw('SUM(`admin_deduct_amount`) as total_admin_deduct_amount'),
                DB::raw('SUM(`admin_add_amount`) as total_admin_add_amount'),
                DB::raw('SUM(`present_amount`) as total_present_amount'),
                DB::raw('SUM(`machine_up_amount`) as total_machine_up_amount'),
                DB::raw('SUM(`machine_down_amount`) as total_machine_down_amount'),
                DB::raw('SUM(`lottery_amount`) as total_lottery_amount'),
                DB::raw('SUM(`profit_amount`) as total_profit_amount'),
                DB::raw('SUM(`game_amount`) as total_game_amount'),
                DB::raw('SUM(`machine_amount`) as total_machine_amount'),
                DB::raw('SUM(`machine_point`) as total_machine_point'),
            ])
            ->toArray();
        DB::beginTransaction();
        try {
            $channelProfitSettlementRecord = new ChannelProfitSettlementRecord();
            $channelProfitSettlementRecord->department_id = $channel->department_id;
            $channelProfitSettlementRecord->total_withdraw_amount = $channelProfitRecord['total_withdraw_amount'] ?? 0;
            $channelProfitSettlementRecord->total_recharge_amount = $channelProfitRecord['total_recharge_amount'] ?? 0;
            $channelProfitSettlementRecord->total_bonus_amount = $channelProfitRecord['total_bonus_amount'] ?? 0;
            $channelProfitSettlementRecord->total_admin_deduct_amount = $channelProfitRecord['total_admin_deduct_amount'] ?? 0;
            $channelProfitSettlementRecord->total_admin_add_amount = $channelProfitRecord['total_admin_add_amount'] ?? 0;
            $channelProfitSettlementRecord->total_present_amount = $channelProfitRecord['total_present_amount'] ?? 0;
            $channelProfitSettlementRecord->total_machine_up_amount = $channelProfitRecord['total_machine_up_amount'] ?? 0;
            $channelProfitSettlementRecord->total_machine_down_amount = $channelProfitRecord['total_machine_down_amount'] ?? 0;
            $channelProfitSettlementRecord->total_lottery_amount = $channelProfitRecord['total_lottery_amount'] ?? 0;
            $channelProfitSettlementRecord->total_profit_amount = $channelProfitRecord['total_profit_amount'] ?? 0;
            $channelProfitSettlementRecord->total_game_amount = $channelProfitRecord['total_game_amount'] ?? 0;
            $channelProfitSettlementRecord->total_machine_amount = $channelProfitRecord['total_machine_amount'] ?? 0;
            $channelProfitSettlementRecord->total_machine_point = $channelProfitRecord['total_machine_point'] ?? 0;
            $channelProfitSettlementRecord->last_profit_amount = $channel->last_profit_amount;
            $channelProfitSettlementRecord->adjust_amount = $channel->adjust_amount;
            $channelProfitSettlementRecord->type = PromoterProfitSettlementRecord::TYPE_SETTLEMENT;
            $channelProfitSettlementRecord->tradeno = createOrderNo();
            $channelProfitSettlementRecord->user_id = Admin::id() ?? 0;
            $channelProfitSettlementRecord->user_name = !empty(Admin::user()) ? Admin::user()->toArray()['username'] : '';
            $settlement = $amount = bcadd($channelProfitSettlementRecord->total_profit_amount,
                $channelProfitSettlementRecord->adjust_amount, 2);
            if ($amount > 0) {
                if ($channel->settlement_amount < 0) {
                    $diffAmount = bcadd($amount, $channel->settlement_amount, 2);
                    $settlement = max($diffAmount, 0);
                }
            }
            $channelProfitSettlementRecord->actual_amount = $settlement;
            $channelProfitSettlementRecord->save();
            // 更新结算报表
            ChannelProfitRecord::query()
                ->where('status', ChannelProfitRecord::STATUS_UNCOMPLETED)
                ->where('department_id', $id)
                ->update([
                    'status' => ChannelProfitRecord::STATUS_COMPLETED,
                    'settlement_time' => date('Y-m-d H:i:s'),
                    'settlement_tradeno' => $channelProfitSettlementRecord->tradeno,
                    'settlement_id' => $channelProfitSettlementRecord->id,
                ]);
            // 结算后这些数据清零
            $channel->profit_amount = 0;
            $channel->adjust_amount = 0;
            $channel->settlement_amount = bcadd($channel->settlement_amount, $amount, 2);
            $channel->last_profit_amount = $settlement;
            $channel->last_settlement_time = date('Y-m-d');
            $channel->last_settlement_timestamp = date('Y-m-d H:i:s');
            $channel->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return message_error($e->getMessage());
        }
        
        return message_success(admin_trans('machine.action.action_success'));
    }
    
    /**
     * 分润结算记录
     * @auth true
     */
    public function settlementRecord($playerId = 0): Grid
    {
        return Grid::create(new $this->channelProfitSettlementRecord(), function (Grid $grid) use ($playerId) {
            $grid->title(admin_trans('promoter_profit_settlement_record.title'));
            $grid->model()->with([
                'user',
                'channel'
            ])->orderBy('id', 'desc');
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
                admin_trans('channel_profit_settlement_record.fields.id'))->fixed(true)->align('center');
            $grid->column('department_id',
                admin_trans('channel.fields.name'))->display(function (
                $val,
                ChannelProfitSettlementRecord $data
            ) {
                return $data->channel->name;
            })->width('150px')->align('center');
            $grid->column(function (Grid $grid) {
                $grid->column('total_profit_amount',
                    admin_trans('channel_profit_settlement_record.fields.total_profit_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('actual_amount',
                    admin_trans('channel_profit_settlement_record.fields.actual_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('adjust_amount',
                    admin_trans('channel_profit_settlement_record.fields.adjust_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('last_profit_amount',
                    admin_trans('channel_profit_settlement_record.fields.last_profit_amount'))->align('center')->width(120)->ellipsis(true);
            }, admin_trans('channel_profit_settlement_record.profit_settlement_info'))->ellipsis(true);
            $grid->column('user.username', admin_trans('admin.admin_user'))->display(function (
                $val,
                ChannelProfitSettlementRecord $data
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
                admin_trans('channel_profit_settlement_record.fields.tradeno'))->align('center')->ellipsis(true)->copy();
            $grid->column(function (Grid $grid) {
                $grid->column('total_machine_up_amount',
                    admin_trans('channel_profit_settlement_record.fields.total_machine_up_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('total_machine_down_amount',
                    admin_trans('channel_profit_settlement_record.fields.total_machine_down_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('total_bonus_amount',
                    admin_trans('channel_profit_settlement_record.fields.total_bonus_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('total_lottery_amount',
                    admin_trans('channel_profit_settlement_record.fields.total_lottery_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('total_withdraw_amount',
                    admin_trans('channel_profit_settlement_record.fields.total_withdraw_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('total_recharge_amount',
                    admin_trans('channel_profit_settlement_record.fields.total_recharge_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('total_admin_deduct_amount',
                    admin_trans('channel_profit_settlement_record.fields.total_admin_deduct_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('total_admin_add_amount',
                    admin_trans('channel_profit_settlement_record.fields.total_admin_add_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('total_present_amount',
                    admin_trans('channel_profit_settlement_record.fields.total_present_amount'))->align('center')->width(120)->ellipsis(true);
                $grid->column('total_game_amount',
                    admin_trans('channel_profit_settlement_record.fields.total_game_amount'))->align('center')->width(120)->ellipsis(true);
            }, admin_trans('channel_profit_settlement_record.settlement_data'))->ellipsis(true);
            $grid->column('created_at',
                admin_trans('channel_profit_settlement_record.fields.created_at'))->align('center')->fixed('right')->ellipsis(true);
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('tradeno')->placeholder(admin_trans('channel_profit_settlement_record.fields.tradeno'));
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
            $grid->expandFilter();
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions, ChannelProfitSettlementRecord $data) {
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
     * 结算详情
     * @param $id
     * @auth true
     * @return Grid
     */
    public function settlementDataList($id): Grid
    {
        return Grid::create(new $this->channelProfitRecord, function (Grid $grid) use ($id) {
            $grid->title(admin_trans('promoter_profit_record.title'));
            $grid->model()->where('settlement_id', $id)->orderBy('id', 'desc');
            $this->profitRecordGrid($grid);
        });
    }
}
