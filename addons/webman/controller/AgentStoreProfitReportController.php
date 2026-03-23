<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerLotteryRecord;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;
use Illuminate\Support\Facades\DB;

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
                    'present_in_amount' => 0,
                    'present_out_amount' => 0,
                    'machine_put_point' => 0,
                    'lottery_amount' => 0,
                    'profit' => 0,
                    'agent_profit' => 0,
                ];
                continue;
            }

            // 查询开分、洗分、投钞数据
            $deliveryQuery = PlayerDeliveryRecord::query()
                ->whereIn('player_id', $playerIds)
                ->whereIn('type', [
                    PlayerDeliveryRecord::TYPE_PRESENT_IN,
                    PlayerDeliveryRecord::TYPE_PRESENT_OUT,
                    PlayerDeliveryRecord::TYPE_MACHINE,
                ]);

            // 时间筛选
            if (!empty($createdAtStart)) {
                $deliveryQuery->where('created_at', '>=', $createdAtStart);
            }
            if (!empty($createdAtEnd)) {
                $deliveryQuery->where('created_at', '<=', $createdAtEnd);
            }

            $deliveryData = $deliveryQuery->selectRaw("
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_PRESENT_IN . " THEN `amount` ELSE 0 END) AS present_in_amount,
                SUM(CASE WHEN `type` = " . PlayerDeliveryRecord::TYPE_PRESENT_OUT . " THEN `amount` ELSE 0 END) AS present_out_amount,
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
            $presentInAmount = floatval($deliveryData->present_in_amount ?? 0);
            $presentOutAmount = floatval($deliveryData->present_out_amount ?? 0);
            $machinePutPoint = floatval($deliveryData->machine_put_point ?? 0);
            $lotteryAmount = floatval($lotteryData->lottery_amount ?? 0);

            // 计算盈余：(开分+投钞) - (洗分+彩金)
            $profit = ($presentInAmount + $machinePutPoint) - ($presentOutAmount + $lotteryAmount);

            // 计算代理分润：盈余 * 代理抽成比例
            $agentCommission = floatval($store->agent_commission ?? 0);
            $agentProfit = $profit * ($agentCommission / 100);

            $reportData[] = [
                'id' => $store->id,
                'store_name' => $store->nickname,
                'store_username' => $store->username,
                'agent_commission' => $agentCommission,
                'channel_commission' => $store->channel_commission ?? 0,
                'present_in_amount' => $presentInAmount,
                'present_out_amount' => $presentOutAmount,
                'machine_put_point' => $machinePutPoint,
                'lottery_amount' => $lotteryAmount,
                'profit' => $profit,
                'agent_profit' => $agentProfit,
            ];
        }

        return Grid::create($reportData, function (Grid $grid) use ($exAdminFilter) {
            $grid->title('店家分润报表');
            $grid->autoHeight();
            $grid->bordered(true);

            $grid->column('id', 'ID')->width(80)->align('center');

            $grid->column('store_name', '店家名称')->width(150)->align('center');

            $grid->column('store_username', '登录账号')->width(120)->align('center');

            $grid->column('present_in_amount', '开分')->display(function ($value) {
                return number_format($value, 2);
            })->width(120)->align('center');

            $grid->column('present_out_amount', '洗分')->display(function ($value) {
                return number_format($value, 2);
            })->width(120)->align('center');

            $grid->column('machine_put_point', '投钞')->display(function ($value) {
                return number_format($value, 2);
            })->width(120)->align('center');

            $grid->column('lottery_amount', '拉彩')->display(function ($value) {
                return number_format($value, 2);
            })->width(120)->align('center');

            $grid->column('profit', '盈余')->display(function ($value) {
                $color = $value >= 0 ? 'green' : 'red';
                return Tag::create(number_format($value, 2))->color($color);
            })->width(120)->align('center');

            $grid->column('agent_commission', '代理抽成比例')->display(function ($value) {
                return $value . '%';
            })->width(120)->align('center');

            $grid->column('agent_profit', '代理分润')->display(function ($value) {
                $color = $value >= 0 ? 'blue' : 'orange';
                return Tag::create(number_format($value, 2))->color($color);
            })->width(120)->align('center');

            $grid->column('channel_commission', '渠道抽成比例')->display(function ($value) {
                return $value . '%';
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
            $grid->hideAdd();
            $grid->expandFilter();
        });
    }
}
