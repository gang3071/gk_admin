<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Channel;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerPlatformCash;
use addons\webman\model\PlayerPromoter;
use addons\webman\model\StoreAgentProfitRecord;
use app\service\OfflineProfitSettlementServices;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\Popover;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\grid\ToolTip;
use ExAdmin\ui\response\Msg;
use ExAdmin\ui\support\Request;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Webman\RedisQueue\Client;

/**
 * 渠道推广员管理
 * @group channel
 */
class ChannelAgentPromoterController
{
    protected $model;
    
    
    protected $player;
    
    protected $settlement;
    
    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_promoter_model');
        $this->player = plugin()->webman->config('database.player_model');
        $this->settlement = plugin()->webman->config('database.store_agent_profit_record_model');
    }
    
    /**
     * 线下代理列表
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        $page = Request::input('ex_admin_page', 1);
        $size = Request::input('ex_admin_size', 20);
        $exAdminSortBy = Request::input('ex_admin_sort_by', '');
        $requestFilter = Request::input('ex_admin_filter', []);
        $exAdminSortField = Request::input('ex_admin_sort_field', '');
        $query = PlayerPromoter::query()
            ->leftjoin('player', 'player.id', '=', 'player_promoter.player_id')
            ->leftjoin('player_platform_cash', 'player_promoter.player_id', '=', 'player_platform_cash.player_id')
            ->leftjoin('player_promoter as recommend_promoter', 'recommend_promoter.player_id', '=',
                'player_promoter.recommend_id')
            ->when(!empty($requestFilter['pid']), function (Builder $q) use ($requestFilter) {
                $q->where('player_promoter.recommend_id', $requestFilter['pid'])
                    ->orWhere('player_promoter.player_id', $requestFilter['pid']);
            })
            ->when(!empty($requestFilter['uuid']), function (Builder $q) use ($requestFilter) {
                $q->where('player.uuid', $requestFilter['uuid']);
            })
            ->when(!empty($requestFilter['phone']), function (Builder $q) use ($requestFilter) {
                $q->where('player.phone', $requestFilter['phone']);
            })
            ->when(!empty($requestFilter['status']), function (Builder $q) use ($requestFilter) {
                $q->where('player_promoter.status', $requestFilter['status']);
            })
            ->when(!empty($requestFilter['name']), function (Builder $q) use ($requestFilter) {
                $q->where('player_promoter.name', 'like', '%' . $requestFilter['name'] . '%');
            });
        $totalNum = clone $query;
        $total = $totalNum->count();
        $list = $query
            ->select([
                'player_promoter.*',
                'player.uuid',
                'player.is_test',
                'player.phone',
                'recommend_promoter.name as recommend_promoter_name',
                'recommend_promoter.ratio as recommend_promoter_ratio',
            ])
            ->forPage($page, $size)
            ->when(!empty($exAdminSortField) && !empty($exAdminSortBy),
                function ($query) use ($exAdminSortField, $exAdminSortBy) {
                    $query->orderBy($exAdminSortField, $exAdminSortBy);
                }, function ($query) {
                    $query->orderBy('player_promoter.id', 'asc');
                })
            ->get()
            ->toArray();
        foreach ($list as &$item) {
            if ($item['recommend_id'] == 0) {
                $children = Player::query()
                    ->where('recommend_id', $item['player_id'])
                    ->where('is_promoter', 1)
                    ->whereNull('deleted_at')
                    ->pluck('id')->toArray();
                $totalData = PlayerDeliveryRecord::query()
                    ->whereHas('player', function ($query) use ($children) {
                        $query->whereIn('recommend_id', $children);
                    })
                    ->when(!empty($item['last_settlement_timestamp']), function ($query) use ($item) {
                        $query->where('player_delivery_record.created_at', '>=', $item['last_settlement_timestamp']);
                    })
                    ->join('player', 'player_delivery_record.player_id', '=', 'player.id')
                    ->join('player_promoter', 'player.recommend_id', '=', 'player_promoter.player_id')
                    ->selectRaw('
                        player.recommend_id,
                        player_promoter.ratio,
                        sum(IF(player_delivery_record.type = ' . PlayerDeliveryRecord::TYPE_PRESENT_IN . ', player_delivery_record.amount, 0)) as total_in,
                        sum(IF(player_delivery_record.type = ' . PlayerDeliveryRecord::TYPE_PRESENT_OUT . ', player_delivery_record.amount, 0)) as total_out,
                        sum(IF(player_delivery_record.type = ' . PlayerDeliveryRecord::TYPE_MACHINE . ', player_delivery_record.amount, 0)) as total_point
                    ')
                    ->groupBy('player.recommend_id', 'player_promoter.ratio')
                    ->get();
                $presentInAmount = 0;
                $machinePutPoint = 0;
                $presentOutAmount = 0;
                $selfProfitAmount = 0;
                $totalPoint = 0;
                foreach ($totalData as $data) {
                    $totalPoint = bcadd($totalPoint,
                        bcsub(bcadd($data['total_point'], $data['total_in'], 2), $data['total_out'], 2), 2);
                    if ($data['ratio'] - $item['ratio'] > 0) {
                        $selfProfitAmount = bcadd($selfProfitAmount,
                            bcmul(bcsub(bcadd($data['total_point'], $data['total_in'], 2), $data['total_out'], 2),
                                ($data['ratio'] - $item['ratio']) / 100, 2), 2);
                    }
                    $presentInAmount = bcadd($presentInAmount, bcadd(0, $data['total_in'] ?? 0, 2), 2);
                    $machinePutPoint = bcadd($machinePutPoint, bcadd(0, $data['total_point'] ?? 0, 2), 2);
                    $presentOutAmount = bcadd($presentOutAmount, bcadd(0, $data['total_out'] ?? 0, 2), 2);
                }
                $ratio = $item['ratio'] ?? 0;
                $money = PlayerPlatformCash::query()
                    ->whereHas('player', function ($query) use ($children) {
                        $query->whereIn('recommend_id', $children);
                    })->sum('money');
            } else {
                $totalData = PlayerDeliveryRecord::query()
                    ->whereHas('player', function ($query) use ($item) {
                        $query->where('recommend_id', $item['player_id']);
                    })
                    ->when(!empty($item['last_settlement_timestamp']), function ($query) use ($item) {
                        $query->where('created_at', '>=', $item['last_settlement_timestamp']);
                    })->selectRaw('
                    sum(IF(type = ' . PlayerDeliveryRecord::TYPE_PRESENT_IN . ', amount, 0)) as total_in,
                    sum(IF(type = ' . PlayerDeliveryRecord::TYPE_PRESENT_OUT . ', amount, 0)) as total_out,
                    sum(IF(type = ' . PlayerDeliveryRecord::TYPE_MACHINE . ', amount, 0)) as total_point
                ')->first();
                
                $ratio = $item['ratio'] ?? 0;
                $presentInAmount = bcadd(0, $totalData['total_in'] ?? 0, 2);
                $machinePutPoint = bcadd(0, $totalData['total_point'] ?? 0, 2);
                $presentOutAmount = bcadd(0, $totalData['total_out'] ?? 0, 2);
                $money = PlayerPlatformCash::query()
                    ->whereHas('player', function ($query) use ($item) {
                        $query->where('recommend_id', $item['player_id']);
                    })->sum('money');
                $totalPoint = bcsub(bcadd($machinePutPoint, $presentInAmount, 2), $presentOutAmount, 2);
                $selfProfitAmount = bcmul($totalPoint, (100 - $ratio) / 100, 2);
            }
            
            $item['present_in_amount'] = $presentInAmount;
            $item['present_out_amount'] = $presentOutAmount;
            $item['machine_put_point'] = $machinePutPoint;
            $item['self_profit_amount'] = bcadd($selfProfitAmount, $item['adjust_amount'], 2);
            $item['money'] = $money;
            $item['total_point'] = $totalPoint;
            $item['profit_amount'] = bcmul($totalPoint, $ratio / 100, 2);
            $item['ratio'] = $ratio;
        }

        return Grid::create($list, function (Grid $grid) use ($total, $list) {
            $grid->title(admin_trans('agent_promoter.title'));
            $exAdminFilter = Request::input('ex_admin_filter', []);
            $page = Request::input('ex_admin_page', 1);
            $size = Request::input('ex_admin_size', 25);
            $param = [
                'size' => $size,
                'page' => $page,
                'ex_admin_filter' => $exAdminFilter,
            ];
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', 'ID')->align('center')->fixed(true)->ellipsis(true);
            $grid->column('uuid', admin_trans('agent_promoter.fields.bound_player_uuid'))
                ->display(function ($val, $data) {
                    return Html::create()->content([
                        Html::div()->content($val),
                        $data['is_test'] == 1 ? Tag::create(admin_trans('player.fields.is_test'))->color('red') : ''
                    ]);
                })
                ->align('center')->fixed(true)->ellipsis(true);
            $grid->column('name', admin_trans('agent_promoter.fields.agent_name'))
                ->display(function ($value, $data) {
                    $value = !empty($value) ? $value : $data['uuid'];
                    return Html::create(Str::of($value)->limit(20, ' (...)'))
                        ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                        ->modal([$this, 'playerInfo'], ['player_id' => $data['player_id']])
                        ->width('60%')->title(admin_trans('agent_promoter.label.agent_account_with_value', null, ['value' => $value]));
                })
                ->fixed(true)->align('center')->width(120)->ellipsis(true);
            $grid->column('ratio', admin_trans('agent_promoter.fields.payment_ratio'))->display(function (
                $val,
                $data
            ) use ($grid) {
                $form = Form::create();
                $form->style(['padding' => '0px', 'background' => 'none']);
                $form->layout('inline');
                $form->removeAttr('labelCol');
                $form->url($grid->attr('url'));
                $form->number('ratio')
                    ->min(1)
                    ->value($val)
                    ->max(isset($data['recommend_promoter_ratio']) ? (int)$data['recommend_promoter_ratio'] : 100)
                    ->precision(2)
                    ->controls(false)
                    ->addonAfter('%')
                    ->help(admin_trans('player_promoter.promoter_max_ratio', null,
                        ['{max_ratio}' => (isset($data['recommend_promoter_ratio']) ? (int)$data['recommend_promoter_ratio'] : 100)]));
                $form->method('PUT');
                $form->params($grid->getCall()['params'] + [
                        'ex_form_id' => 'id',
                        'ex_admin_form_action' => 'update',
                        'ids' => [$data['id']],
                    ]);
                $form->actions()->submitButton()->htmlType('submit');
                $popover = Popover::create(Html::create()->tag('i')->attr('class',
                    ['far fa-edit', 'editable-cell-icon']))
                    ->trigger('click')
                    ->destroyTooltipOnHide()
                    ->content($form);
                $visible = $popover->vModel('visible', null, false);
                return Html::create()->content([
                    floatval($val) . '%',
                    $popover
                ])->attr('class', 'ex-admin-editable-cell')
                    ->event('dblclick', [$visible => true]);
            })->align('center')->ellipsis(true);
            $grid->column('total_point', admin_trans('agent_promoter.fields.current_total_revenue'))->display(function ($val) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            })->align('center')->width(140)->ellipsis(true);
            $grid->column('self_profit_amount', admin_trans('agent_promoter.fields.agent_profit_amount'))->display(function ($val) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            })->align('center')->width(140)->ellipsis(true);
            $grid->column('adjust_amount', admin_trans('agent_promoter.fields.profit_adjust_amount'))
                ->display(function ($val) {
                    return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                        ->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
                })->header(Html::create(admin_trans('player_promoter.fields.adjust_amount'))->content(
                    ToolTip::create(Icon::create('QuestionCircleOutlined')->style([
                        'marginLeft' => '5px',
                        'cursor' => 'pointer'
                    ]))->title(admin_trans('player_promoter.adjust_amount_tip'))
                ))
                ->editable(
                    (new Editable)->number('adjust_amount')
                        ->required()
                        ->min(-10000000)
                        ->max(10000000)
                )
                ->align('center')->width(120)->ellipsis(true);
            $grid->column('profit_amount', admin_trans('agent_promoter.fields.current_payment_amount'))->display(function ($val) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('present_in_amount', admin_trans('agent_promoter.fields.current_transfer_in'))->align('center')->width(140)->ellipsis(true);
            $grid->column('machine_put_point', admin_trans('agent_promoter.fields.current_cash_in'))->align('center')->width(140)->ellipsis(true);
            $grid->column('present_out_amount', admin_trans('agent_promoter.fields.current_transfer_out'))->align('center')->width(140)->ellipsis(true);
            $grid->column('player_num', admin_trans('agent_promoter.fields.store_device_count'))->display(function ($val) {
                return Statistic::create()
                    ->value($val)
                    ->valueStyle(['fontSize' => '14px'])
                    ->precision(0)
                    ->prefix(Icon::create('far fa-user')->style(['fontSize' => '14px']))
                    ->style(['cursor' => 'pointer']);
            })->align('center')->width(120)->ellipsis(true)->sortable();
            $grid->column('team_num', admin_trans('agent_promoter.fields.device_count'))->display(function ($val) {
                return Statistic::create()
                    ->value($val)
                    ->valueStyle(['fontSize' => '14px'])
                    ->precision(0)
                    ->prefix(Icon::create('TeamOutlined')->style(['fontSize' => '14px']))
                    ->style(['cursor' => 'pointer']);
            })->align('center')->width(120)->ellipsis(true)->sortable();
            $grid->column('money', admin_trans('agent_promoter.fields.current_machine_score'))->align('center')->width(140)->ellipsis(true);
            $grid->column('status', admin_trans('player_promoter.fields.status'))->switch()->align('center');
            $grid->column('recommend_promoter_name', admin_trans('agent_promoter.fields.parent_agent'))->display(function ($value) {
                return Html::create(Str::of($value)->limit(20, ' (...)'))->style([
                    'cursor' => 'pointer',
                    'color' => 'rgb(24, 144, 255)'
                ]);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('settlement_amount',
                admin_trans('player_promoter.fields.settled_amount'))->align('center')->width(120)->fixed('right')->ellipsis(true);
            $grid->column('last_settlement_timestamp', admin_trans('agent_promoter.fields.last_settlement_time'))->align('center')->fixed('right')->ellipsis(true);
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('name')->placeholder(admin_trans('player_promoter.fields.name'));
                $filter->like()->text('phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->select('status')
                    ->placeholder(admin_trans('player_promoter.fields.status'))
                    ->showSearch()
                    ->dropdownMatchSelectWidth()
                    ->style(['width' => '200px'])
                    ->options([
                        0 => admin_trans('player_promoter.status.0'),
                        1 => admin_trans('player_promoter.status.1'),
                    ]);
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });
            $promoterTree = [];
            $promoterList = PlayerPromoter::query()->get();
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
                ->tree('player_id')
                ->hideAdd()
                ->hideDel()
                ->searchPlaceholder(admin_trans('admin.search_department'));
            $grid->expandFilter();
            $grid->hideDelete();
            $grid->hideDeleteSelection();
            $grid->hideTrashed();
            $grid->attr('is_mongo', true);
            $grid->attr('is_mongo_total', $total);
            $grid->attr('mongo_model', $list);
            $grid->actions(function (Actions $actions, $data) {
                $actions->hideDel();
                $value = !empty($data['name']) ? $data['name'] : $data['uuid'];
                $actions->prepend(
                    Button::create(admin_trans('agent_promoter.button.settlement'))
                        ->type('primary')
                        ->title(admin_trans('agent_promoter.label.agent_settlement_with_value', null, ['value' => $value]))
                        ->modal([$this, 'settlement'], ['id' => $data['player_id']])
                );
            });
            $grid->tools(
                Button::create(admin_trans('agent_promoter.button.batch_settlement'))
                    ->icon(Icon::create('fas fa-chalkboard'))
                    ->confirm(admin_trans('agent_promoter.confirm.batch_settlement'),
                        [
                            $this,
                            'batchSettlement?' . http_build_query($param)
                        ])
                    ->gridBatch()->gridRefresh()
            );
            $grid->updateing(function ($ids, $data) {
                try {
                    if (isset($ids[0]) && !empty($data)) {
                        if (isset($data['ratio'])) {
                            /** @var PlayerPromoter $playerPromoter */
                            $playerPromoter = PlayerPromoter::query()->where('id', $ids[0])->first();
                            if ($playerPromoter->recommend_id > 0) {
                                /** @var PlayerPromoter $recommendPromoter */
                                $recommendPromoter = PlayerPromoter::query()->where('player_id',
                                    $playerPromoter->recommend_id)->first();
                                if ($recommendPromoter->ratio > $data['ratio']) {
                                    return message_error(admin_trans('common.store_ratio_less_than_agent', null, [
                                        'name' => $recommendPromoter->name,
                                        'ratio' => $recommendPromoter->ratio
                                    ]));
                                }
                            } else {
                                /** @var PlayerPromoter $storePromoter */
                                $storePromoter = PlayerPromoter::query()->where('recommend_id',
                                    $playerPromoter->player_id)->orderBy('ratio', 'asc')->first();
                                if ($storePromoter->ratio < $data['ratio']) {
                                    return message_error(admin_trans('common.agent_ratio_greater_than_store', null, [
                                        'name' => $storePromoter->name,
                                        'ratio' => $storePromoter->ratio
                                    ]));
                                }
                            }
                        }
                        if (PlayerPromoter::query()->updateOrCreate(
                            ['id' => $ids[0]],
                            $data
                        )) {
                            return message_success(admin_trans('form.save_success'));
                        }
                    } else {
                        return message_error(admin_trans('form.save_fail'));
                    }
                } catch (Exception $e) {
                    return message_error(admin_trans('form.save_fail') . $e->getMessage());
                }
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
        /** @var PlayerPromoter $playerPromoter */
        $playerPromoter = PlayerPromoter::query()->where('player_id', $player_id)->first();
        if ($playerPromoter->recommend_id == 0) {
            $children = Player::query()
                ->where('recommend_id', $playerPromoter->player_id)
                ->where('is_promoter', 1)
                ->whereNull('deleted_at')
                ->pluck('id')->toArray();
            $totalData = PlayerDeliveryRecord::query()
                ->whereHas('player', function ($query) use ($children) {
                    $query->whereIn('recommend_id', $children);
                })
                ->selectRaw('
                    sum(IF(type = ' . PlayerDeliveryRecord::TYPE_PRESENT_IN . ', amount, 0)) as total_in,
                    sum(IF(type = ' . PlayerDeliveryRecord::TYPE_PRESENT_OUT . ', amount, 0)) as total_out,
                    sum(IF(type = ' . PlayerDeliveryRecord::TYPE_MACHINE . ', amount, 0)) as total_point
                ')->first();
        } else {
            $totalData = PlayerDeliveryRecord::query()
                ->whereHas('player', function ($query) use ($playerPromoter) {
                    $query->where('recommend_id', $playerPromoter->player_id);
                })
                ->selectRaw('
                    sum(IF(type = ' . PlayerDeliveryRecord::TYPE_PRESENT_IN . ', amount, 0)) as total_in,
                    sum(IF(type = ' . PlayerDeliveryRecord::TYPE_PRESENT_OUT . ', amount, 0)) as total_out,
                    sum(IF(type = ' . PlayerDeliveryRecord::TYPE_MACHINE . ', amount, 0)) as total_point
                ')->first();
        }
        $presentInAmount = bcadd(0, $totalData['total_in'] ?? 0, 2);
        $machinePutPoint = bcadd(0, $totalData['total_point'] ?? 0, 2);
        $presentOutAmount = bcadd(0, $totalData['total_out'] ?? 0, 2);
        $totalPoint = bcsub(bcadd($machinePutPoint, $presentInAmount, 2), $presentOutAmount, 2);
        $info['present_in_amount'] = $presentInAmount;
        $info['present_out_amount'] = $presentOutAmount;
        $info['machine_put_point'] = $machinePutPoint;
        $info['total_point'] = $totalPoint;
        $info['ratio'] = $playerPromoter->ratio ?? 0;
        return Detail::create($playerPromoter, function (Detail $detail) use ($info) {
            $detail->item('name', admin_trans('store_agent_profit_record.detail.agent_name'));
            $detail->item('player.uuid', admin_trans('store_agent_profit_record.detail.uuid'))->copy();
            $detail->item('ratio', admin_trans('store_agent_profit_record.detail.submit_ratio'))->append('%');
            $detail->item('machine_put_point', admin_trans('store_agent_profit_record.detail.machine_put_point'))->display(function () use ($info) {
                return $info['machine_put_point'] ?? '--';
            });
            $detail->item('present_out_amount', admin_trans('store_agent_profit_record.detail.present_out_amount'))->display(function () use ($info) {
                return $info['present_out_amount'] ?? '--';
            });
            $detail->item('present_in_amount', admin_trans('store_agent_profit_record.detail.present_in_amount'))->display(function () use ($info) {
                return $info['present_in_amount'] ?? '--';
            });
            $detail->item('total_point', admin_trans('store_agent_profit_record.detail.total_point'))->display(function () use ($info) {
                return $info['total_point'] ?? '--';
            });
            $detail->item('settlement_amount', admin_trans('store_agent_profit_record.detail.settlement_amount'));
            $detail->item('created_at', admin_trans('store_agent_profit_record.detail.created_at'))->display(function ($val) {
                return date('Y-m-d H:i:s', strtotime($val));
            });
            $detail->item('player.machine_wallet.money',
                admin_trans('player_platform_cash.platform_name.' . PlayerPlatformCash::PLATFORM_SELF))->display(function (
                $val,
                PlayerPromoter $data
            ) {
                return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal([
                    ChannelPlayerController::class,
                    'playerRecord'
                ], ['id' => $data->player_id])->width('70%')->title($data->name);
            });
            $detail->item('end_time', admin_trans('store_agent_profit_record.detail.last_settlement_time'))->display(function ($val, PlayerPromoter $data) {
                /** @var StoreAgentProfitRecord $storeAgentProfitRecord */
                $storeAgentProfitRecord = StoreAgentProfitRecord::query()->where('player_id',
                    $data->player_id)->orderBy('id', 'desc')->first();
                if ($storeAgentProfitRecord) {
                    return admin_trans('store_agent_profit_record.label.start_time') . ' : ' . date('Y-m-d H:i:s',
                            strtotime($storeAgentProfitRecord->start_time)) . ' ' . admin_trans('store_agent_profit_record.label.end_time') . ' : ' . date('Y-m-d H:i:s',
                            strtotime($storeAgentProfitRecord->end_time));
                }
                return admin_trans('store_agent_profit_record.label.start_time') . ' : ' . admin_trans('store_agent_profit_record.label.no_time') . ' ' . admin_trans('store_agent_profit_record.label.end_time') . ' : ' . admin_trans('store_agent_profit_record.label.no_time');
            });
        })->bordered();
    }
    
    /**
     * 分润结算记录
     * @group channel
     * @auth true
     */
    public function settlementList(): Grid
    {
        return Grid::create(new $this->settlement(), function (Grid $grid) {
            $grid->title(admin_trans('agent_promoter.settlement_records_title'));
            $grid->model()->with(['agent_promoter'])
                ->orderBy('id', 'desc');
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', 'ID')->fixed(true)->align('center');
            $grid->column('agent_promoter.name', admin_trans('agent_promoter.fields.agent_name'))
                ->display(function ($value, StoreAgentProfitRecord $data) {
                    $value = !empty($value) ? $value : $data->player->uuid;
                    return Html::create(Str::of($value)->limit(20, ' (...)'))
                        ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                        ->modal([$this, 'playerInfo'], ['player_id' => $data['player_id']])
                        ->width('60%')->title(admin_trans('agent_promoter.label.agent_account_with_value', null, ['value' => $value]));
                })
                ->fixed(true)->align('center')->width(120)->ellipsis(true);
            $grid->column('settlement_tradeno', admin_trans('agent_promoter.fields.settlement_tradeno'))->align('center')->ellipsis(true)->copy();
            $grid->column('adjust_amount', admin_trans('agent_promoter.fields.profit_adjust_amount'))->display(function ($val) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('actual_amount', admin_trans('agent_promoter.fields.actual_profit_amount'))->display(function ($val) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('profit_amount', admin_trans('agent_promoter.fields.profit_amount'))->display(function ($val) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('ratio', admin_trans('agent_promoter.fields.profit_ratio'))->append('%')->align('center')->width(120)->ellipsis(true);
            $grid->column('sub_name', admin_trans('agent_promoter.fields.payment_target'))
                ->display(function ($value, StoreAgentProfitRecord $data) {
                    switch ($data['type']) {
                        case StoreAgentProfitRecord::TYPE_STORE:
                            /** @var PlayerPromoter $playerPromoter */
                            $playerPromoter = PlayerPromoter::query()->where('player_id',
                                $data->agent_promoter->recommend_id)->first();
                            $value = !empty($playerPromoter->name) ? $playerPromoter->name : $playerPromoter->player->uuid;
                            return Html::create(Str::of($value)->limit(20, ' (...)'))
                                ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                                ->modal([$this, 'playerInfo'], ['player_id' => $playerPromoter->player_id])
                                ->width('60%')->title(admin_trans('agent_promoter.label.agent_account_with_value', null, ['value' => $value]));
                        case StoreAgentProfitRecord::TYPE_AGENT:
                            $tag = Tag::create(admin_trans('agent_promoter.tag.channel'))->color('#f50');
                            return Html::create()->content([
                                $tag,
                            ]);
                    }
                });
            $grid->column('sub_profit_amount', admin_trans('agent_promoter.fields.payment_amount'))->display(function ($val) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('sub_ratio', admin_trans('agent_promoter.fields.payment_ratio_percent'))->append('%')->align('center')->width(120)->ellipsis(true);
            $grid->column('total_bet', admin_trans('agent_promoter.fields.total_bet'))->align('center')->width(120)->ellipsis(true);
            $grid->column('total_diff', admin_trans('agent_promoter.fields.total_diff'))->align('center')->width(120)->ellipsis(true);
            $grid->column('machine_point', admin_trans('agent_promoter.fields.machine_point'))->align('center')->width(120)->ellipsis(true);
            $grid->column('total_income', admin_trans('agent_promoter.fields.total_income'))->display(function ($val) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('total_in', admin_trans('agent_promoter.fields.transfer_in'))->align('center')->width(120)->ellipsis(true);
            $grid->column('total_out', admin_trans('agent_promoter.fields.transfer_out'))->align('center')->width(120)->ellipsis(true);
            $grid->column('user_name', admin_trans('agent_promoter.fields.admin'))->align('center');
            $grid->column('type', admin_trans('admin.fields.type'))
                ->display(function ($value) {
                    $tag = '';
                    switch ($value) {
                        case StoreAgentProfitRecord::TYPE_STORE:
                            $tag = Tag::create(admin_trans('agent_promoter.tag.store'))->color('#108ee9');
                            break;
                        case StoreAgentProfitRecord::TYPE_AGENT:
                            $tag = Tag::create(admin_trans('agent_promoter.tag.agent'))->color('#f50');
                            break;
                    }
                    return Html::create()->content([
                        $tag,
                    ]);
                })->sortable();
            $grid->column('start_time', admin_trans('agent_promoter.fields.settlement_start_time'))->align('center')->fixed('right')->ellipsis(true);
            $grid->column('end_time', admin_trans('agent_promoter.fields.settlement_end_time'))->align('center')->fixed('right')->ellipsis(true);
            $grid->column('created_at', admin_trans('agent_promoter.fields.created_at'))->align('center')->fixed('right')->ellipsis(true);
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('tradeno')->placeholder(admin_trans('agent_promoter.placeholder.tradeno'));
                $filter->like()->text('agent_promoter.name')->placeholder(admin_trans('agent_promoter.placeholder.agent_promoter'));
                $filter->like()->text('agent_promoter.phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
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
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
            });
        });
    }
    
    /**
     * 执行线下代理结算
     * @param $selected
     * @return Msg
     * @auth true
     */
    public function batchSettlement($selected): Msg
    {
        if (!isset($selected)) {
            return message_error(admin_trans('common.please_select_settlement_targets'));
        }
        $playerPromoter = PlayerPromoter::query()
            ->whereIn('id', $selected)
            ->get();
        /** @var PlayerPromoter $item */
        foreach ($playerPromoter as $item) {
            if (!empty($item->last_settlement_timestamp)) {
                $startTime = $item->last_settlement_timestamp;
            } else {
                $startTime = $item->created_at;
            }
            Client::send('offline_profit_settlement',
                [
                    'id' => $item->player_id,
                    'department_id' => $item->department_id,
                    'agent_name' => $item->name,
                    'start_time' => $startTime,
                    'end_time' => Carbon::now()->format('Y-m-d H:i:s'),
                    'user_id' => Admin::id() ?? 0,
                    'user_name' => !empty(Admin::user()) ? Admin::user()->toArray()['username'] : '',
                ], 10);
        }
        return message_success(admin_trans('channel.add_machine_success'))->refresh();
    }
    
    /**
     * 分润结算
     * @group channel
     * @auth true
     * @param $id
     * @return Form
     */
    public function settlement($id): Form
    {
        
        return Form::create([], function (Form $form) use ($id) {
            /** @var PlayerPromoter $playerPromoter */
            $playerPromoter = PlayerPromoter::query()->where('player_id', $id)->first();
            /** @var StoreAgentProfitRecord $storeAgentProfitRecord */
            $storeAgentProfitRecord = StoreAgentProfitRecord::query()->where('player_id', $id)->orderBy('id',
                'desc')->first();
            if (!empty($storeAgentProfitRecord)) {
                $startTime = $storeAgentProfitRecord->start_time;
                $endTime = $storeAgentProfitRecord->end_time;
            } else {
                $startTime = admin_trans('store_agent_profit_record.label.no_time');
                $endTime = admin_trans('store_agent_profit_record.label.no_time');
            }
            if (!empty($playerPromoter->last_settlement_timestamp)) {
                if (is_string($playerPromoter->last_settlement_timestamp)) {
                    $endTime = Carbon::parse($playerPromoter->last_settlement_timestamp);
                } else {
                    $endTime = $playerPromoter->last_settlement_timestamp;
                }
                $form->date('end_time', admin_trans('agent_promoter.fields.settlement_end_time'))->bindFunction('disabledDate', "
                    var date = new Date(time);
                    var Month = date.getMonth() + 1;
                    var Day = date.getDate();
                    var Y = date.getFullYear() + '-';
                    var M = Month < 10 ? '0' + Month + '-' : Month + '-';
                    var D = Day < 10 ? '0' + Day : Day;
                    var newDateStr = Y + M + D;
                    var condition1 = newDateStr < '" . $endTime->format('Y-m-d') . "';
                    var condition2 = newDateStr > '" . Carbon::today()->format('Y-m-d') . "';
                    return condition2 || condition1;", ['time'])
                    ->valueFormat('YYYY-MM-DD HH:mm:ss')
                    ->showTime(true)->help(admin_trans('agent_promoter.help.settlement_time'))
                    ->style([
                        'width' => '100%',
                    ])->required();
                $form->push(Detail::create()->title(admin_trans('agent_promoter.help.settlement_range', null, ['start' => $endTime->format('Y-m-d H:i:s')])));
            } else {
                $createTime = Carbon::parse($playerPromoter->created_at);
                $form->push(Detail::create($playerPromoter, function (Detail $detail) {
                    $detail->item('created_at', admin_trans('player_promoter.fields.agent_created_at'))->display(function ($val) {
                        return Carbon::parse($val)->format('Y-m-d H:i:s');
                    });
                }));
                $form->date('end_time', admin_trans('agent_promoter.fields.settlement_end_time'))->bindFunction('disabledDate', "
                    var date = new Date(time);
                    var Month = date.getMonth() + 1;
                    var Day = date.getDate();
                    var Y = date.getFullYear() + '-';
                    var M = Month < 10 ? '0' + Month + '-' : Month + '-';
                    var D = Day < 10 ? '0' + Day : Day;
                    var newDateStr = Y + M + D;
                    var condition1 = newDateStr < '" . $createTime->format('Y-m-d') . "';
                    var condition2 = newDateStr > '" . Carbon::today()->format('Y-m-d') . "';
                    return condition1 || condition2;", ['time'])
                    ->valueFormat('YYYY-MM-DD HH:mm:ss')
                    ->showTime(true)
                    ->help(admin_trans('agent_promoter.help.settlement_time'))
                    ->style([
                        'width' => '100%',
                    ])->required();
                $form->push(Detail::create()->title(admin_trans('agent_promoter.help.settlement_range', null, ['start' => $createTime->format('Y-m-d H:i:s')])));
            }
            $form->layout('vertical');
            $form->push(Card::create([
                Html::create(admin_trans('agent_promoter.fields.start_time') . ': ' . ($startTime))->tag('p'),
                Html::create(admin_trans('agent_promoter.fields.settlement_end_time') . ': ' . ($endTime))->tag('p'),
            ])->title(admin_trans('agent_promoter.form.last_settlement_time')));
            $form->saving(function (Form $form) use ($id) {
                /** @var PlayerPromoter $playerPromoter */
                $playerPromoter = PlayerPromoter::query()->where('player_id', $id)->first();
                if (empty($playerPromoter)) {
                    return message_error(admin_trans('player_promoter.not_fount'));
                }
                if ($playerPromoter->status == 0) {
                    return message_error(admin_trans('player_promoter.has_disable'));
                }
                /** @var Channel $channel */
                $channel = Channel::query()->where('department_id',
                    Admin::user()->department_id)->whereNull('deleted_at')->first();
                if ($channel->promotion_status == 0) {
                    return message_error(admin_trans('player_promoter.promotion_function_disabled'));
                }
                if (!empty($playerPromoter->last_settlement_timestamp)) {
                    $startTime = $playerPromoter->last_settlement_timestamp;
                } else {
                    $startTime = $playerPromoter->created_at;
                }
                $endTime = $form->input('end_time');
                try {
                    $end = Carbon::parse($endTime);
                    $now = Carbon::now();
                    if ($end->gt($now)) {
                        return message_error(admin_trans('common.settlement_end_time_error'));
                    }
                } catch (Exception) {
                    return message_error(admin_trans('common.system_error'));
                }
                if (!empty($playerPromoter->last_settlement_timestamp)) {
                    $startTime = $playerPromoter->last_settlement_timestamp;
                }
                OfflineProfitSettlementServices::doProfitSettlement([
                    'id' => $playerPromoter->player_id,
                    'department_id' => $playerPromoter->department_id,
                    'agent_name' => $playerPromoter->name,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'user_id' => Admin::id() ?? 0,
                    'user_name' => !empty(Admin::user()) ? Admin::user()->toArray()['username'] : '',
                ]);
                return message_success(admin_trans('common.settlement_success'));
            });
        });
    }
}
