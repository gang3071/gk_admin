<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerLotteryRecord;
use addons\webman\model\PlayerWithdrawRecord;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Request;

/**
 * 代理后台 - 店家分润报表
 * @group agent
 */
class AgentStoreProfitReportController
{
    /**
     * 店家分润报表列表
     * @group agent
     * @auth true
     */
    public function index(): Grid
    {
        /** @var AdminUser $admin */
        $admin = Admin::user();

        // 获取筛选参数
        $exAdminFilter = Request::input('ex_admin_filter', []);
        $selectedStoreId = $exAdminFilter['store_id'] ?? null;

        // ========== 1. 批量查询店家信息（1次查询替代 N 次） ==========
        $storeTable = (new AdminUser())->getTable();
        $stores = AdminUser::query()
            ->where("{$storeTable}.parent_admin_id", $admin->id)
            ->where("{$storeTable}.type", AdminUser::TYPE_STORE)
            ->where("{$storeTable}.status", 1)
            ->when(!empty($selectedStoreId), function ($query) use ($selectedStoreId, $storeTable) {
                $query->where("{$storeTable}.id", $selectedStoreId);
            })
            ->select([
                "{$storeTable}.id",
                "{$storeTable}.nickname",
                "{$storeTable}.username",
                "{$storeTable}.agent_commission",
                "{$storeTable}.channel_commission",
            ])
            ->orderBy("{$storeTable}.id", 'desc')
            ->get();

        if ($stores->isEmpty()) {
            return $this->buildEmptyGrid($exAdminFilter);
        }

        // 店家ID列表
        $storeIds = $stores->pluck('id')->toArray();

        // ========== 2. 批量查询所有玩家（1次查询替代 N 次） ==========
        $players = Player::query()
            ->whereIn('store_admin_id', $storeIds)
            ->where('is_promoter', 0)
            ->select(['id', 'store_admin_id'])
            ->get();

        // 按店家ID分组玩家ID
        $playersByStore = $players->groupBy('store_admin_id');
        // 所有玩家ID
        $allPlayerIds = $players->pluck('id')->toArray();

        // ========== 3. 批量查询统计数据（2次查询替代 2N 次） ==========
        // 时间条件闭包
        $applyTimeFilter = function ($query) use ($exAdminFilter) {
            $dateType = $exAdminFilter['date_type'] ?? null;
            if (!empty($dateType)) {
                $query->where(getDateWhere($dateType, 'created_at'));
            } else {
                if (!empty($exAdminFilter['created_at_start'])) {
                    $query->where('created_at', '>=', $exAdminFilter['created_at_start']);
                }
                if (!empty($exAdminFilter['created_at_end'])) {
                    $query->where('created_at', '<=', $exAdminFilter['created_at_end']);
                }
            }
        };

        // 开分/洗分/投钞统计（按player_id分组）
        $deliveryStats = [];
        if (!empty($allPlayerIds)) {
            $deliveryStats = PlayerDeliveryRecord::query()
                ->whereIn('player_id', $allPlayerIds)
                ->when(true, $applyTimeFilter)
                ->selectRaw("
                    `player_id`,
                    SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_RECHARGE . " THEN `amount` ELSE 0 END) AS recharge_amount,
                    SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " AND `withdraw_status` = " . PlayerWithdrawRecord::STATUS_SUCCESS . " THEN `amount` ELSE 0 END) AS withdraw_amount,
                    SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_MACHINE . " THEN `amount` ELSE 0 END) AS machine_put_point
                ")
                ->groupBy('player_id')
                ->get()
                ->keyBy('player_id');
        }

        // 彩金统计（按player_id分组）
        $lotteryStats = [];
        if (!empty($allPlayerIds)) {
            $lotteryStats = PlayerLotteryRecord::query()
                ->whereIn('player_id', $allPlayerIds)
                ->where('status', PlayerLotteryRecord::STATUS_COMPLETE)
                ->when(true, $applyTimeFilter)
                ->selectRaw("`player_id`, SUM(`amount`) as lottery_amount")
                ->groupBy('player_id')
                ->get()
                ->keyBy('player_id');
        }

        // ========== 4. 按玩家ID映射统计数据 ==========
        $deliveryByPlayer = [];
        foreach ($deliveryStats as $playerId => $stat) {
            $deliveryByPlayer[$playerId] = [
                'recharge_amount' => floatval($stat->recharge_amount ?? 0),
                'withdraw_amount' => floatval($stat->withdraw_amount ?? 0),
                'machine_put_point' => floatval($stat->machine_put_point ?? 0),
            ];
        }

        $lotteryByPlayer = [];
        foreach ($lotteryStats as $playerId => $stat) {
            $lotteryByPlayer[$playerId] = floatval($stat->lottery_amount ?? 0);
        }

        // ========== 5. 组装报表数据（PHP计算，无查询） ==========
        $reportData = [];

        foreach ($stores as $store) {
            // 获取该店家的玩家ID列表
            $storePlayers = $playersByStore->get($store->id, collect());
            $playerIds = $storePlayers->pluck('id')->toArray();
            $deviceCount = count($playerIds);

            // 汇总该店家的统计数据
            $rechargeAmount = 0;
            $withdrawAmount = 0;
            $machinePutPoint = 0;
            $lotteryAmount = 0;

            foreach ($playerIds as $playerId) {
                $delivery = $deliveryByPlayer[$playerId] ?? null;
                if ($delivery) {
                    $rechargeAmount += $delivery['recharge_amount'];
                    $withdrawAmount += $delivery['withdraw_amount'];
                    $machinePutPoint += $delivery['machine_put_point'];
                }
                $lotteryAmount += ($lotteryByPlayer[$playerId] ?? 0);
            }

            // 计算小计 = (开分 + 投钞) - 洗分
            $totalIn = bcadd($rechargeAmount, $machinePutPoint, 2);
            $subtotal = bcsub($totalIn, $withdrawAmount, 2);

            // 计算代理分润
            $agentCommission = floatval($store->agent_commission ?? 0);
            $agentProfit = bcmul($subtotal, bcdiv($agentCommission, 100, 4), 2);

            // 计算渠道分润
            $channelCommission = floatval($store->channel_commission ?? 0);
            $channelProfit = bcmul($subtotal, bcdiv($channelCommission, 100, 4), 2);

            $reportData[] = [
                'id' => $store->id,
                'store_name' => $store->nickname,
                'store_username' => $store->username,
                'device_count' => $deviceCount,
                'agent_commission' => $agentCommission,
                'channel_commission' => $channelCommission,
                'recharge_amount' => $rechargeAmount,
                'withdraw_amount' => $withdrawAmount,
                'machine_put_point' => $machinePutPoint,
                'lottery_amount' => $lotteryAmount,
                'subtotal' => $subtotal,
                'agent_profit' => $agentProfit,
                'channel_profit' => $channelProfit,
            ];
        }

        // ========== 6. 筛选器选项 ==========
        $storeOptions = $admin->childStores()
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

        return Grid::create($reportData, function (Grid $grid) use ($exAdminFilter, $reportData, $storeOptions) {
            $grid->title(admin_trans('agent_store_profit.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 使用异步加载的统计组件
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($exAdminFilter) {
                $row->gutter([10, 0]);
                $row->column(admin_view(plugin()->webman->getPath() . '/views/total_info.vue')->attrs([
                    'ex_admin_filter' => $exAdminFilter,
                    'type' => 'StoreProfitReport',
                    'department_id' => Admin::user()->department_id,
                    'admin_user_id' => Admin::user()->id,
                    'url' => admin_url(['addons-webman-controller-AgentStoreProfitReportController', 'totalInfo']),
                ]));
            })->style(['background' => '#fff']);
            $grid->header($layout);

            $grid->column('id', 'ID')->width(80)->align('center');

            $grid->column('store_name', admin_trans('agent_store_profit.fields.store_name'))->width(150)->align('center');

            $grid->column('device_count', admin_trans('agent_store_profit.fields.device_count'))->width(100)->align('center');

            $grid->column('store_username', admin_trans('agent_store_profit.fields.store_username'))->width(120)->align('center');

            $grid->column('machine_put_point', admin_trans('agent_store_profit.fields.machine_put_point'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            $grid->column('recharge_amount', admin_trans('agent_store_profit.fields.recharge_amount'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            $grid->column('withdraw_amount', admin_trans('agent_store_profit.fields.withdraw_amount'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            $grid->column('lottery_amount', admin_trans('agent_store_profit.fields.lottery_amount'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            $grid->column('subtotal', admin_trans('agent_store_profit.fields.subtotal'))->display(function ($value) {
                $color = $value >= 0 ? '#3f8600' : '#cf1322';
                return Html::create(number_format(floatval($value), 2))->style(['color' => $color, 'fontWeight' => 'bold']);
            })->width(120)->align('center');

            $grid->column('agent_commission', admin_trans('agent_store_profit.fields.agent_commission'))->display(function ($value) {
                return $value . '%';
            })->width(100)->align('center');

            $grid->column('agent_profit', admin_trans('agent_store_profit.fields.agent_profit'))->display(function ($value) {
                $color = $value >= 0 ? '#1890ff' : '#fa8c16';
                return Html::create(number_format(floatval($value), 2))->style(['color' => $color, 'fontWeight' => 'bold']);
            })->width(120)->align('center');

            $grid->column('channel_commission', admin_trans('agent_store_profit.fields.channel_commission'))->display(function ($value) {
                return $value . '%';
            })->width(100)->align('center');

            $grid->column('channel_profit', admin_trans('agent_store_profit.fields.channel_profit'))->display(function ($value) {
                $color = $value >= 0 ? '#52c41a' : '#f5222d';
                return Html::create(number_format(floatval($value), 2))->style(['color' => $color, 'fontWeight' => 'bold']);
            })->width(120)->align('center');

            // 筛选器
            $grid->filter(function (Filter $filter) use ($storeOptions) {
                // 店家下拉选择
                $filter->eq()->select('store_id')
                    ->placeholder(admin_trans('agent_store_profit.filter.select_store'))
                    ->options(['' => admin_trans('agent_store_profit.filter.all_stores')] + $storeOptions)
                    ->style(['width' => '300px']);

                // 结算周期下拉选择
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
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', admin_trans('agent_store_profit.filter.time_range'))->placeholder([
                    admin_trans('agent_store_profit.filter.start_time'),
                    admin_trans('agent_store_profit.filter.end_time')
                ]);
            });

            $grid->hideAction();
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideAdd();
            $grid->expandFilter();
            // 使用 Arrays driver
            $grid->attr('is_mongo', true);
            $grid->attr('is_mongo_total', count($reportData));
            $grid->attr('mongo_model', $reportData);

            // 导出功能
            $grid->export(new \addons\webman\grid\AgentStoreProfitMonthlyExporter())
                ->filename('monthly_business_report_' . date('YmdHis'));
        });
    }

    /**
     * 构建空数据Grid
     * @param array $exAdminFilter
     * @return Grid
     */
    protected function buildEmptyGrid(array $exAdminFilter = []): Grid
    {
        return Grid::create([], function (Grid $grid) use ($exAdminFilter) {
            $grid->title(admin_trans('agent_store_profit.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 使用异步加载的统计组件
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($exAdminFilter) {
                $row->gutter([10, 0]);
                $row->column(admin_view(plugin()->webman->getPath() . '/views/total_info.vue')->attrs([
                    'ex_admin_filter' => $exAdminFilter,
                    'type' => 'StoreProfitReport',
                    'department_id' => Admin::user()->department_id,
                    'admin_user_id' => Admin::user()->id,
                    'url' => admin_url(['addons-webman-controller-AgentStoreProfitReportController', 'totalInfo']),
                ]));
            })->style(['background' => '#fff']);
            $grid->header($layout);

            $grid->column('id', 'ID')->width(80)->align('center');
            $grid->column('store_name', admin_trans('agent_store_profit.fields.store_name'));
            $grid->column('device_count', admin_trans('agent_store_profit.fields.device_count'));
            $grid->column('store_username', admin_trans('agent_store_profit.fields.store_username'));
            $grid->column('machine_put_point', admin_trans('agent_store_profit.fields.machine_put_point'));
            $grid->column('recharge_amount', admin_trans('agent_store_profit.fields.recharge_amount'));
            $grid->column('withdraw_amount', admin_trans('agent_store_profit.fields.withdraw_amount'));
            $grid->column('lottery_amount', admin_trans('agent_store_profit.fields.lottery_amount'));
            $grid->column('subtotal', admin_trans('agent_store_profit.fields.subtotal'));
            $grid->column('agent_commission', admin_trans('agent_store_profit.fields.agent_commission'));
            $grid->column('agent_profit', admin_trans('agent_store_profit.fields.agent_profit'));
            $grid->column('channel_commission', admin_trans('agent_store_profit.fields.channel_commission'));
            $grid->column('channel_profit', admin_trans('agent_store_profit.fields.channel_profit'));

            $grid->hideAction();
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideAdd();
            $grid->expandFilter();
        });
    }

    /**
     * 异步加载统计数据
     * @group agent
     * @auth true
     * @return Response
     */
    public function totalInfo(): Response
    {
        $request = Request::input();
        $exAdminFilter = $request['ex_admin_filter'] ?? [];
        $adminUserId = $request['admin_user_id'] ?? 0;

        $admin = AdminUser::query()->find($adminUserId);
        if (!$admin) {
            return Response::success([]);
        }

        // 获取筛选参数
        $selectedStoreId = $exAdminFilter['store_id'] ?? null;

        // 获取代理下的所有店家
        $stores = AdminUser::query()
            ->where('parent_admin_id', $admin->id)
            ->where('type', AdminUser::TYPE_STORE)
            ->where('status', 1)
            ->when(!empty($selectedStoreId), function ($query) use ($selectedStoreId) {
                $query->where('id', $selectedStoreId);
            })
            ->pluck('id')
            ->toArray();

        if (empty($stores)) {
            return Response::success([]);
        }

        // 批量查询所有玩家
        $allPlayerIds = Player::query()
            ->whereIn('store_admin_id', $stores)
            ->where('is_promoter', 0)
            ->pluck('id')
            ->toArray();

        if (empty($allPlayerIds)) {
            return Response::success($this->buildEmptyStats());
        }

        // 时间条件闭包
        $applyTimeFilter = function ($query) use ($exAdminFilter) {
            $dateType = $exAdminFilter['date_type'] ?? null;
            if (!empty($dateType)) {
                $query->where(getDateWhere($dateType, 'created_at'));
            } else {
                if (!empty($exAdminFilter['created_at_start'])) {
                    $query->where('created_at', '>=', $exAdminFilter['created_at_start']);
                }
                if (!empty($exAdminFilter['created_at_end'])) {
                    $query->where('created_at', '<=', $exAdminFilter['created_at_end']);
                }
            }
        };

        // 开分/洗分/投钞统计
        $deliveryData = PlayerDeliveryRecord::query()
            ->whereIn('player_id', $allPlayerIds)
            ->when(true, $applyTimeFilter)
            ->selectRaw("
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_RECHARGE . " THEN `amount` ELSE 0 END) AS recharge_amount,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_WITHDRAWAL . " AND `withdraw_status` = " . PlayerWithdrawRecord::STATUS_SUCCESS . " THEN `amount` ELSE 0 END) AS withdraw_amount,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_MACHINE . " THEN `amount` ELSE 0 END) AS machine_put_point
            ")
            ->first();

        // 彩金统计
        $lotteryData = PlayerLotteryRecord::query()
            ->whereIn('player_id', $allPlayerIds)
            ->where('status', PlayerLotteryRecord::STATUS_COMPLETE)
            ->when(true, $applyTimeFilter)
            ->selectRaw("SUM(`amount`) as lottery_amount")
            ->first();

        // 提取数据
        $rechargeAmount = floatval($deliveryData->recharge_amount ?? 0);
        $withdrawAmount = floatval($deliveryData->withdraw_amount ?? 0);
        $machinePutPoint = floatval($deliveryData->machine_put_point ?? 0);
        $lotteryAmount = floatval($lotteryData->lottery_amount ?? 0);

        // 计算小计
        $totalIn = bcadd($rechargeAmount, $machinePutPoint, 2);
        $subtotal = bcsub($totalIn, $withdrawAmount, 2);

        $data = [
            [
                'title' => admin_trans('agent_store_profit.stats.total_recharge'),
                'number' => $rechargeAmount,
                'prefix' => '',
                'suffix' => ''
            ],
            [
                'title' => admin_trans('agent_store_profit.stats.total_withdraw'),
                'number' => $withdrawAmount,
                'prefix' => '',
                'suffix' => ''
            ],
            [
                'title' => admin_trans('agent_store_profit.stats.total_machine_put'),
                'number' => $machinePutPoint,
                'prefix' => '',
                'suffix' => ''
            ],
            [
                'title' => admin_trans('agent_store_profit.stats.total_lottery'),
                'number' => $lotteryAmount,
                'prefix' => '',
                'suffix' => ''
            ],
            [
                'title' => admin_trans('agent_store_profit.stats.total_subtotal'),
                'number' => floatval($subtotal),
                'prefix' => '',
                'suffix' => ''
            ],
        ];

        return Response::success($data);
    }

    /**
     * 构建空统计数据
     * @return array
     */
    protected function buildEmptyStats(): array
    {
        return [
            [
                'title' => admin_trans('agent_store_profit.stats.total_recharge'),
                'number' => 0,
                'prefix' => '',
                'suffix' => ''
            ],
            [
                'title' => admin_trans('agent_store_profit.stats.total_withdraw'),
                'number' => 0,
                'prefix' => '',
                'suffix' => ''
            ],
            [
                'title' => admin_trans('agent_store_profit.stats.total_machine_put'),
                'number' => 0,
                'prefix' => '',
                'suffix' => ''
            ],
            [
                'title' => admin_trans('agent_store_profit.stats.total_lottery'),
                'number' => 0,
                'prefix' => '',
                'suffix' => ''
            ],
            [
                'title' => admin_trans('agent_store_profit.stats.total_subtotal'),
                'number' => 0,
                'prefix' => '',
                'suffix' => ''
            ],
        ];
    }

    /**
     * 导出月度营业状况报表
     * @group agent
     * @auth true
     * @return void
     */
    public function export()
    {
        // 此方法仅用于权限控制，实际导出由 Grid 的 export 功能处理
        // ExAdmin 会自动调用 AgentStoreProfitMonthlyExporter
    }
}
