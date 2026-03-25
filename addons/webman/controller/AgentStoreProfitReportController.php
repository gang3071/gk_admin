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
        $createdAtStart = $exAdminFilter['created_at_start'] ?? null;
        $createdAtEnd = $exAdminFilter['created_at_end'] ?? null;

        // 获取代理下的所有店家
        $storeIds = $admin->childStores()
            ->where('type', AdminUser::TYPE_STORE)
            ->where('status', 1)
            ->pluck('id')
            ->toArray();

        // 构建报表数据
        $reportData = [];

        foreach ($storeIds as $storeId) {
            $store = AdminUser::find($storeId);
            if (!$store) {
                continue;
            }

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
                    'agent_commission' => $store->agent_commission ?? 0,
                    'channel_commission' => $store->channel_commission ?? 0,
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

        return Grid::create($reportData, function (Grid $grid) use ($exAdminFilter, $reportData) {
            $grid->title('店家分润报表');
            $grid->autoHeight();
            $grid->bordered(true);

            $grid->column('id', 'ID')->width(80)->align('center');

            $grid->column('store_name', '店家名称')->width(150)->align('center');

            $grid->column('store_username', '登录账号')->width(120)->align('center');

            $grid->column('recharge_amount', '累计开分')->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            $grid->column('withdraw_amount', '累计洗分')->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            $grid->column('machine_put_point', '投钞')->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            $grid->column('lottery_amount', '彩金')->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            $grid->column('subtotal', '小计')->display(function ($value) {
                $color = $value >= 0 ? '#3f8600' : '#cf1322';
                return Html::create(number_format(floatval($value), 2))->style(['color' => $color, 'fontWeight' => 'bold']);
            })->width(120)->align('center');

            $grid->column('agent_commission', '代理抽成比例')->display(function ($value) {
                return $value . '%';
            })->width(100)->align('center');

            $grid->column('agent_profit', '代理分润')->display(function ($value) {
                $color = $value >= 0 ? '#1890ff' : '#fa8c16';
                return Html::create(number_format(floatval($value), 2))->style(['color' => $color, 'fontWeight' => 'bold']);
            })->width(120)->align('center');

            $grid->column('channel_commission', '渠道抽成比例')->display(function ($value) {
                return $value . '%';
            })->width(100)->align('center');

            $grid->column('channel_profit', '渠道分润')->display(function ($value) {
                $color = $value >= 0 ? '#52c41a' : '#f5222d';
                return Html::create(number_format(floatval($value), 2))->style(['color' => $color, 'fontWeight' => 'bold']);
            })->width(120)->align('center');

            // 筛选器
            $grid->filter(function (Filter $filter) {
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '时间范围')->placeholder([
                    '开始时间',
                    '结束时间'
                ]);
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
