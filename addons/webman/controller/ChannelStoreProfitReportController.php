<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerLotteryRecord;
use addons\webman\model\PlayerWithdrawRecord;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\support\Request;

/**
 * 渠道后台 - 店家分润报表
 * @group channel
 */
class ChannelStoreProfitReportController
{
    /**
     * 店家分润报表列表
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        /** @var AdminUser $admin */
        $admin = Admin::user();

        // 获取筛选参数
        $exAdminFilter = Request::input('ex_admin_filter', []);
        $createdAtStart = $exAdminFilter['created_at_start'] ?? null;
        $createdAtEnd = $exAdminFilter['created_at_end'] ?? null;
        $selectedStoreId = $exAdminFilter['store_id'] ?? null;
        $selectedAgentId = $exAdminFilter['agent_id'] ?? null;
        $remarkKeyword = $exAdminFilter['remark'] ?? null;

        // 获取渠道下的所有店家
        // 渠道可以看到自己的直属店家 + 下属代理的店家
        $allStoresQuery = AdminUser::query()
            ->where('department_id', $admin->department_id)
            ->where('type', AdminUser::TYPE_STORE)
            ->where('status', 1);

        // 如果选择了特定代理，只查询该代理下的店家
        if (!empty($selectedAgentId)) {
            $allStoresQuery->where('parent_admin_id', $selectedAgentId);
        }

        // 如果选择了特定店家，添加店家ID筛选
        if (!empty($selectedStoreId)) {
            $allStoresQuery->where('id', $selectedStoreId);
        }

        // 如果输入了备注关键词，进行模糊搜索
        if (!empty($remarkKeyword)) {
            $allStoresQuery->where('remark', 'like', '%' . $remarkKeyword . '%');
        }

        // 获取符合所有筛选条件的店家ID列表
        $storeIds = $allStoresQuery->pluck('id')->toArray();

        // 构建报表数据
        $reportData = [];

        foreach ($storeIds as $storeId) {
            $store = AdminUser::find($storeId);
            if (!$store) {
                continue;
            }

            // 获取代理信息
            $agent = AdminUser::find($store->parent_admin_id);
            $agentName = $agent ? ($agent->nickname ?: $agent->username) : '-';

            // 获取该店家下的所有玩家
            $playerIds = Player::query()
                ->where('store_admin_id', $storeId)
                ->where('is_promoter', 0)
                ->pluck('id')
                ->toArray();

            if (empty($playerIds)) {
                // 没有玩家也要显示店家信息
                $reportData[] = [
                    'id' => $store->id,
                    'store_name' => $store->nickname,
                    'store_username' => $store->username,
                    'agent_name' => $agentName,
                    'agent_commission' => $store->agent_commission ?? 0,
                    'channel_commission' => $store->channel_commission ?? 0,
                    'remark' => $store->remark ?? '',
                    'recharge_amount' => 0,
                    'withdraw_amount' => 0,
                    'machine_put_point' => 0,
                    'lottery_amount' => 0,
                    'subtotal' => 0,
                    'agent_profit' => 0,
                    'channel_profit' => 0,
                ];
                continue;
            }

            // 查询开分、洗分、投钞数据
            $deliveryQuery = PlayerDeliveryRecord::query()
                ->whereIn('player_id', $playerIds);

            // 时间筛选
            if (!empty($createdAtStart)) {
                $deliveryQuery->where('created_at', '>=', $createdAtStart);
            }
            if (!empty($createdAtEnd)) {
                $deliveryQuery->where('created_at', '<=', $createdAtEnd);
            }

            $deliveryData = $deliveryQuery->selectRaw("
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_RECHARGE . " THEN `amount` ELSE 0 END) AS recharge_amount,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " AND `withdraw_status` = " . PlayerWithdrawRecord::STATUS_SUCCESS . " THEN `amount` ELSE 0 END) AS withdraw_amount,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_MACHINE . " THEN `amount` ELSE 0 END) AS machine_put_point
            ")->first();

            // 查询拉彩数据
            $lotteryQuery = PlayerLotteryRecord::query()
                ->whereIn('player_id', $playerIds)
                ->where('status', PlayerLotteryRecord::STATUS_COMPLETE);

            // 时间筛选
            if (!empty($createdAtStart)) {
                $lotteryQuery->where('created_at', '>=', $createdAtStart);
            }
            if (!empty($createdAtEnd)) {
                $lotteryQuery->where('created_at', '<=', $createdAtEnd);
            }

            $lotteryData = $lotteryQuery->selectRaw("
                SUM(`amount`) as lottery_amount
            ")->first();

            // 提取数据
            $rechargeAmount = floatval($deliveryData->recharge_amount ?? 0);
            $withdrawAmount = floatval($deliveryData->withdraw_amount ?? 0);
            $machinePutPoint = floatval($deliveryData->machine_put_point ?? 0);
            $lotteryAmount = floatval($lotteryData->lottery_amount ?? 0);

            // 计算小计：(开分+投钞) - (洗分+彩金)
            $totalIn = bcadd($rechargeAmount, $machinePutPoint, 2);
            $totalOut = bcadd($withdrawAmount, $lotteryAmount, 2);
            $subtotal = bcsub($totalIn, $totalOut, 2);

            // 计算代理分润：小计 * 代理抽成比例
            $agentCommission = floatval($store->agent_commission ?? 0);
            $agentProfit = bcmul($subtotal, bcdiv($agentCommission, 100, 4), 2);

            // 计算渠道分润：小计 * 渠道抽成比例
            $channelCommission = floatval($store->channel_commission ?? 0);
            $channelProfit = bcmul($subtotal, bcdiv($channelCommission, 100, 4), 2);

            $reportData[] = [
                'id' => $store->id,
                'store_name' => $store->nickname,
                'store_username' => $store->username,
                'agent_name' => $agentName,
                'agent_commission' => $agentCommission,
                'channel_commission' => $channelCommission,
                'remark' => $store->remark ?? '',
                'recharge_amount' => $rechargeAmount,
                'withdraw_amount' => $withdrawAmount,
                'machine_put_point' => $machinePutPoint,
                'lottery_amount' => $lotteryAmount,
                'subtotal' => $subtotal,
                'agent_profit' => $agentProfit,
                'channel_profit' => $channelProfit,
            ];
        }

        // 计算统计数据
        $totalStats = [
            'total_recharge' => 0,
            'total_withdraw' => 0,
            'total_machine_put' => 0,
            'total_lottery' => 0,
            'total_subtotal' => 0,
            'total_agent_profit' => 0,
            'total_channel_profit' => 0,
        ];

        foreach ($reportData as $item) {
            $totalStats['total_recharge'] = bcadd($totalStats['total_recharge'], $item['recharge_amount'], 2);
            $totalStats['total_withdraw'] = bcadd($totalStats['total_withdraw'], $item['withdraw_amount'], 2);
            $totalStats['total_machine_put'] = bcadd($totalStats['total_machine_put'], $item['machine_put_point'], 2);
            $totalStats['total_lottery'] = bcadd($totalStats['total_lottery'], $item['lottery_amount'], 2);
            $totalStats['total_subtotal'] = bcadd($totalStats['total_subtotal'], $item['subtotal'], 2);
            $totalStats['total_agent_profit'] = bcadd($totalStats['total_agent_profit'], $item['agent_profit'], 2);
            $totalStats['total_channel_profit'] = bcadd($totalStats['total_channel_profit'], $item['channel_profit'], 2);
        }

        // 获取店家选项列表用于筛选器下拉选择
        $storeOptions = AdminUser::query()
            ->where('department_id', $admin->department_id)
            ->where('type', AdminUser::TYPE_STORE)
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->get(['id', 'nickname', 'username'])
            ->mapWithKeys(function ($store) {
                $label = $store->nickname ?: $store->username;
                $label .= " ({$store->username})";
                return [$store->id => $label];
            })
            ->toArray();

        // 获取代理选项列表用于筛选器下拉选择
        $agentOptions = AdminUser::query()
            ->where('department_id', $admin->department_id)
            ->where('type', AdminUser::TYPE_AGENT)
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->get(['id', 'nickname', 'username'])
            ->mapWithKeys(function ($agent) {
                $label = $agent->nickname ?: $agent->username;
                $label .= " ({$agent->username})";
                return [$agent->id => $label];
            })
            ->toArray();

        return Grid::create($reportData, function (Grid $grid) use ($exAdminFilter, $reportData, $storeOptions, $agentOptions, $totalStats) {
            $grid->title(admin_trans('channel_store_profit.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 统计卡片
            $layout = Layout::create()->style(['background' => '#fff', 'padding' => '10px']);
            $layout->row(function (Row $row) use ($totalStats) {
                $row->gutter([10, 10]);

                // 第一行：累计开分、累计洗分、投钞
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(floatval($totalStats['total_recharge']))
                            ->precision(2)
                            ->prefix(admin_trans('channel_store_profit.stats.total_recharge'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#52c41a'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 8);

                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(floatval($totalStats['total_withdraw']))
                            ->precision(2)
                            ->prefix(admin_trans('channel_store_profit.stats.total_withdraw'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#fa8c16'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 8);

                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(floatval($totalStats['total_machine_put']))
                            ->precision(2)
                            ->prefix(admin_trans('channel_store_profit.stats.total_machine_put'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#1890ff'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 8);
            });

            $layout->row(function (Row $row) use ($totalStats) {
                $row->gutter([10, 0]);

                // 第二行：彩金、小计、代理分润、渠道分润
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(floatval($totalStats['total_lottery']))
                            ->precision(2)
                            ->prefix(admin_trans('channel_store_profit.stats.total_lottery'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#eb2f96'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    ->style(['margin-top' => '10px'])
                    , 6);

                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(floatval($totalStats['total_subtotal']))
                            ->precision(2)
                            ->prefix(admin_trans('channel_store_profit.stats.total_subtotal'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => floatval($totalStats['total_subtotal']) >= 0 ? '#3f8600' : '#cf1322'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    ->style(['margin-top' => '10px'])
                    , 6);

                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(floatval($totalStats['total_agent_profit']))
                            ->precision(2)
                            ->prefix(admin_trans('channel_store_profit.stats.total_agent_profit'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#722ed1'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    ->style(['margin-top' => '10px'])
                    , 6);

                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(floatval($totalStats['total_channel_profit']))
                            ->precision(2)
                            ->prefix(admin_trans('channel_store_profit.stats.total_channel_profit'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#13c2c2'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    ->style(['margin-top' => '10px'])
                    , 6);
            });
            $grid->tools([$layout]);

            $grid->column('id', 'ID')->width(80)->align('center');

            $grid->column('store_name', admin_trans('channel_store_profit.fields.store_name'))->width(150)->align('center');

            $grid->column('store_username', admin_trans('channel_store_profit.fields.store_username'))->width(120)->align('center');

            $grid->column('agent_name', admin_trans('channel_store_profit.fields.agent_name'))->width(120)->align('center');

            $grid->column('machine_put_point', admin_trans('channel_store_profit.fields.machine_put_point'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            $grid->column('recharge_amount', admin_trans('channel_store_profit.fields.recharge_amount'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            $grid->column('withdraw_amount', admin_trans('channel_store_profit.fields.withdraw_amount'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');



            $grid->column('lottery_amount', admin_trans('channel_store_profit.fields.lottery_amount'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            $grid->column('subtotal', admin_trans('channel_store_profit.fields.subtotal'))->display(function ($value) {
                $color = $value >= 0 ? '#3f8600' : '#cf1322';
                return Html::create(number_format(floatval($value), 2))->style(['color' => $color, 'fontWeight' => 'bold']);
            })->width(120)->align('center');

            $grid->column('agent_commission', admin_trans('channel_store_profit.fields.agent_commission'))->display(function ($value) {
                return $value . '%';
            })->width(100)->align('center');

            $grid->column('agent_profit', admin_trans('channel_store_profit.fields.agent_profit'))->display(function ($value) {
                $color = $value >= 0 ? '#1890ff' : '#fa8c16';
                return Html::create(number_format(floatval($value), 2))->style(['color' => $color, 'fontWeight' => 'bold']);
            })->width(120)->align('center');

            $grid->column('channel_commission', admin_trans('channel_store_profit.fields.channel_commission'))->display(function ($value) {
                return $value . '%';
            })->width(100)->align('center');

            $grid->column('channel_profit', admin_trans('channel_store_profit.fields.channel_profit'))->display(function ($value) {
                $color = $value >= 0 ? '#52c41a' : '#f5222d';
                return Html::create(number_format(floatval($value), 2))->style(['color' => $color, 'fontWeight' => 'bold']);
            })->width(120)->align('center');

            $grid->column('remark', admin_trans('channel_store_profit.fields.remark'))
                ->editable(
                    (new Editable)
                        ->textarea('remark')
                        ->showCount()
                        ->maxLength(500)
                        ->rows(3)
                )->width(200)->align('center')->ellipsis(true);

            // 筛选器
            $grid->filter(function (Filter $filter) use ($storeOptions, $agentOptions) {
                // 代理下拉选择
                $filter->eq()->select('agent_id')
                    ->placeholder(admin_trans('channel_store_profit.filter.select_agent'))
                    ->options(['' => admin_trans('channel_store_profit.filter.all_agents')] + $agentOptions)
                    ->style(['width' => '250px']);

                // 店家下拉选择
                $filter->eq()->select('store_id')
                    ->placeholder(admin_trans('channel_store_profit.filter.select_store'))
                    ->options(['' => admin_trans('channel_store_profit.filter.all_stores')] + $storeOptions)
                    ->style(['width' => '300px']);

                // 备注模糊搜索
                $filter->like()->text('remark')
                    ->placeholder(admin_trans('channel_store_profit.filter.remark_placeholder'))
                    ->style(['width' => '200px']);

                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', admin_trans('channel_store_profit.filter.time_range'))->placeholder([
                    admin_trans('channel_store_profit.filter.start_time'),
                    admin_trans('channel_store_profit.filter.end_time')
                ]);
            });

            // 处理列表编辑更新
            $grid->updateing(function ($ids, $data) {
                if (isset($ids[0]) && isset($data['remark'])) {
                    if (AdminUser::query()->where('id', $ids[0])->update(['remark' => $data['remark']])) {
                        return message_success(admin_trans('channel_store_profit.message.update_success'));
                    }
                }
            });

            $grid->hideAction();
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideAdd();
            $grid->expandFilter();
            $grid->attr('is_mongo', true);
            $grid->attr('is_mongo_total', count($reportData));
            $grid->attr('mongo_model', $reportData);
        });
    }
}
