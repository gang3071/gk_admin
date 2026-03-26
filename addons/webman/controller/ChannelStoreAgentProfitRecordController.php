<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerPlatformCash;
use addons\webman\model\PlayerPromoter;
use addons\webman\model\StoreAgentProfitRecord;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use Illuminate\Support\Str;

/**
 * 线下结算记录
 * @group channel
 */
class ChannelStoreAgentProfitRecordController
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
     * 线下结算记录
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->settlement(), function (Grid $grid) {
            $grid->title(admin_trans('store_agent_profit_record.title'));
            // TODO: 需要添加 admin_user_id 字段到 store_agent_profit_record 表
            // 暂时使用 player_id 匹配 admin_user_id（需要数据迁移）
            $grid->model()->with(['agent_promoter'])
                ->orderBy('id', 'desc')
                ->where('admin_user_id', Admin::user()->id);
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', 'ID')->fixed(true)->align('center');
            $grid->column('agent_promoter.name', admin_trans('store_agent_profit_record.label.agent_store'))
                ->display(function ($value, StoreAgentProfitRecord $data) {
                    $value = !empty($value) ? $value : $data->player->uuid;
                    return Html::create(Str::of($value)->limit(20, ' (...)'))
                        ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                        ->modal([$this, 'playerInfo'], ['player_id' => $data['player_id']])
                        ->width('60%')->title(admin_trans('store_agent_profit_record.label.agent_account') . ':' . $value);
                })
                ->fixed(true)->align('center')->width(120)->ellipsis(true);
            $grid->column('settlement_tradeno', admin_trans('store_agent_profit_record.fields.settlement_tradeno'))->align('center')->ellipsis(true)->copy();
            $grid->column('adjust_amount', admin_trans('store_agent_profit_record.fields.adjust_amount'))->display(function ($val) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('actual_amount', admin_trans('store_agent_profit_record.fields.actual_amount'))->display(function ($val) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('profit_amount', admin_trans('store_agent_profit_record.fields.profit_amount'))->display(function ($val) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('ratio', admin_trans('store_agent_profit_record.fields.ratio'))->append('%')->align('center')->width(120)->ellipsis(true);
            $grid->column('sub_name', admin_trans('store_agent_profit_record.fields.sub_name'))
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
                                ->width('60%')->title(admin_trans('store_agent_profit_record.label.agent_account') . ':' . $value);
                        case StoreAgentProfitRecord::TYPE_AGENT:
                            $tag = Tag::create(admin_trans('store_agent_profit_record.type.channel'))->color('#f50');
                            return Html::create()->content([
                                $tag,
                            ]);
                    }
                });
            $grid->column('sub_profit_amount', admin_trans('store_agent_profit_record.fields.sub_profit_amount'))->display(function ($val) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('sub_ratio', admin_trans('store_agent_profit_record.fields.sub_ratio'))->append('%')->align('center')->width(120)->ellipsis(true);
            $grid->column('total_bet', admin_trans('store_agent_profit_record.fields.total_bet'))->align('center')->width(120)->ellipsis(true);
            $grid->column('total_diff', admin_trans('store_agent_profit_record.fields.total_diff'))->align('center')->width(120)->ellipsis(true);
            $grid->column('machine_point', admin_trans('store_agent_profit_record.fields.machine_point'))->align('center')->width(120)->ellipsis(true);
            $grid->column('total_income', admin_trans('store_agent_profit_record.fields.total_income'))->display(function ($val) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('total_in', admin_trans('store_agent_profit_record.fields.total_in'))->align('center')->width(120)->ellipsis(true);
            $grid->column('total_out', admin_trans('store_agent_profit_record.fields.total_out'))->align('center')->width(120)->ellipsis(true);
            $grid->column('user_name', admin_trans('store_agent_profit_record.fields.user_name'))->align('center');
            $grid->column('type', admin_trans('admin.fields.type'))
                ->display(function ($value) {
                    $tag = '';
                    switch ($value) {
                        case StoreAgentProfitRecord::TYPE_STORE:
                            $tag = Tag::create(admin_trans('store_agent_profit_record.type.store'))->color('#108ee9');
                            break;
                        case StoreAgentProfitRecord::TYPE_AGENT:
                            $tag = Tag::create(admin_trans('store_agent_profit_record.type.agent'))->color('#f50');
                            break;
                    }
                    return Html::create()->content([
                        $tag,
                    ]);
                })->sortable();
            $grid->column('start_time', admin_trans('store_agent_profit_record.fields.start_time'))->align('center')->fixed('right')->ellipsis(true);
            $grid->column('end_time', admin_trans('store_agent_profit_record.fields.end_time'))->align('center')->fixed('right')->ellipsis(true);
            $grid->column('created_at', admin_trans('store_agent_profit_record.fields.created_at'))->align('center')->fixed('right')->ellipsis(true);
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('tradeno')->placeholder(admin_trans('store_agent_profit_record.placeholder.settlement_tradeno'));
                $filter->like()->text('agent_promoter.name')->placeholder(admin_trans('store_agent_profit_record.placeholder.agent_store'));
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
}
