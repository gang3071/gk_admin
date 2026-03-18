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
            $detail->item('name', '代理名称');
            $detail->item('player.uuid', 'UUID')->copy();
            $detail->item('ratio', '上缴比例')->append('%');
            $detail->item('machine_put_point', '总投钞(充值)')->display(function () use ($info) {
                return $info['machine_put_point'] ?? '--';
            });
            $detail->item('present_out_amount', '转出(洗分)')->display(function () use ($info) {
                return $info['present_out_amount'] ?? '--';
            });
            $detail->item('present_in_amount', '总转入(开分)')->display(function () use ($info) {
                return $info['present_in_amount'] ?? '--';
            });
            $detail->item('total_point', '总营收')->display(function () use ($info) {
                return $info['total_point'] ?? '--';
            });
            $detail->item('settlement_amount', '已结算金额');
            $detail->item('created_at', '创建时间')->display(function ($val) {
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
            $detail->item('end_time', '最近结算时间')->display(function ($val, PlayerPromoter $data) {
                /** @var StoreAgentProfitRecord $storeAgentProfitRecord */
                $storeAgentProfitRecord = StoreAgentProfitRecord::query()->where('player_id',
                    $data->player_id)->orderBy('id', 'desc')->first();
                if ($storeAgentProfitRecord) {
                    return '开始时间 : ' . date('Y-m-d H:i:s',
                            strtotime($storeAgentProfitRecord->start_time)) . ' 结束时间 : ' . date('Y-m-d H:i:s',
                            strtotime($storeAgentProfitRecord->end_time));
                }
                return '开始时间 : 无 结束时间 : 无';
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
            $grid->title('下线分润结算记录');
            // TODO: 需要添加 admin_user_id 字段到 store_agent_profit_record 表
            // 暂时使用 player_id 匹配 admin_user_id（需要数据迁移）
            $grid->model()->with(['agent_promoter'])
                ->orderBy('id', 'desc')
                ->where('admin_user_id', Admin::user()->id);
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->column('id', 'ID')->fixed(true)->align('center');
            $grid->column('agent_promoter.name', '代理/店家')
                ->display(function ($value, StoreAgentProfitRecord $data) {
                    $value = !empty($value) ? $value : $data->player->uuid;
                    return Html::create(Str::of($value)->limit(20, ' (...)'))
                        ->style(['cursor' => 'pointer', 'color' => 'rgb(24, 144, 255)'])
                        ->modal([$this, 'playerInfo'], ['player_id' => $data['player_id']])
                        ->width('60%')->title('代理账号' . ':' . $value);
                })
                ->fixed(true)->align('center')->width(120)->ellipsis(true);
            $grid->column('settlement_tradeno', '结算单号')->align('center')->ellipsis(true)->copy();
            $grid->column('adjust_amount', '分润调整金额')->display(function ($val) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('actual_amount', '实际分润金额')->display(function ($val) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('profit_amount', '分润金额')->display(function ($val) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('ratio', '分润比例')->append('%')->align('center')->width(120)->ellipsis(true);
            $grid->column('sub_name', '上缴对象')
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
                                ->width('60%')->title('代理账号' . ':' . $value);
                        case StoreAgentProfitRecord::TYPE_AGENT:
                            $tag = Tag::create('渠道')->color('#f50');
                            return Html::create()->content([
                                $tag,
                            ]);
                    }
                });
            $grid->column('sub_profit_amount', '上缴金额')->display(function ($val) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('sub_ratio', '上缴比例')->append('%')->align('center')->width(120)->ellipsis(true);
            $grid->column('total_bet', '总押注')->align('center')->width(120)->ellipsis(true);
            $grid->column('total_diff', '总输赢')->align('center')->width(120)->ellipsis(true);
            $grid->column('machine_point', '投钞点数')->align('center')->width(120)->ellipsis(true);
            $grid->column('total_income', '总营收')->display(function ($val) {
                return Html::create()->content([$val > 0 ? '+' . $val : $val,])
                    ->style(['color' => ($val < 0 ? '#cd201f' : 'green')]);
            })->align('center')->width(120)->ellipsis(true);
            $grid->column('total_in', '转入(开分)')->align('center')->width(120)->ellipsis(true);
            $grid->column('total_out', '转出(洗分)')->align('center')->width(120)->ellipsis(true);
            $grid->column('user_name', '管理员')->align('center');
            $grid->column('type', admin_trans('admin.fields.type'))
                ->display(function ($value) {
                    $tag = '';
                    switch ($value) {
                        case StoreAgentProfitRecord::TYPE_STORE:
                            $tag = Tag::create('店家')->color('#108ee9');
                            break;
                        case StoreAgentProfitRecord::TYPE_AGENT:
                            $tag = Tag::create('代理')->color('#f50');
                            break;
                    }
                    return Html::create()->content([
                        $tag,
                    ]);
                })->sortable();
            $grid->column('start_time', '结算开始时间')->align('center')->fixed('right')->ellipsis(true);
            $grid->column('end_time', '结算结束时间')->align('center')->fixed('right')->ellipsis(true);
            $grid->column('created_at', '创建时间')->align('center')->fixed('right')->ellipsis(true);
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('tradeno')->placeholder('结算单号');
                $filter->like()->text('agent_promoter.name')->placeholder('代理/店家');
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
