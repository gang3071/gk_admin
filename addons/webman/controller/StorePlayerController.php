<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Player;
use addons\webman\model\PlayerPlatformCash;
use addons\webman\model\PlayerExtend;
use addons\webman\model\PlayerLotteryRecord;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\support\Request;

/**
 * 店机后台 - 设备列表
 * @group store
 */
class StorePlayerController
{
    /**
     * 设备列表
     * @auth true
     * @group store
     */
    public function index(): Grid
    {
        $admin = Admin::user();
        $storeAdminId = $admin->id;
        $departmentId = $admin->department_id;

        // 调试信息（显示查询条件）
        if (request()->input('debug') == 1) {
            $count = Player::query()
                ->where('department_id', $departmentId)
                ->where('store_admin_id', $storeAdminId)
                ->where('is_promoter', 0)
                ->count();

            \support\Log::info('设备列表查询', [
                'admin_id' => $admin->id,
                'department_id' => $departmentId,
                'store_admin_id' => $storeAdminId,
                'player_count' => $count
            ]);
        }

        // 获取筛选条件
        $requestFilter = Request::input('ex_admin_filter', []);

        // 查询条件：店家管理的玩家（设备）
        // 关闭数据权限，因为我们手动控制了权限（department_id + store_admin_id）
        $query = Player::query()
            ->leftJoin('player_platform_cash as cash', function ($join) {
                $join->on('player.id', '=', 'cash.player_id')
                    ->where('cash.platform_id', PlayerPlatformCash::PLATFORM_SELF);
            })
            ->leftJoin('player_extend', 'player.id', '=', 'player_extend.player_id')
            ->where('player.department_id', $departmentId)
            ->where('player.store_admin_id', $storeAdminId)
            ->where('player.is_promoter', 0);

        // 应用筛选条件
        if (!empty($requestFilter)) {
            if (isset($requestFilter['status'])) {
                $query->where('player.status', $requestFilter['status']);
            }
            if (!empty($requestFilter['phone'])) {
                $query->where('player.phone', 'like', '%' . $requestFilter['phone'] . '%');
            }
            if (!empty($requestFilter['name'])) {
                $query->where('player.name', 'like', '%' . $requestFilter['name'] . '%');
            }
            if (!empty($requestFilter['created_at_start'])) {
                $query->where('player.created_at', '>=', $requestFilter['created_at_start']);
            }
            if (!empty($requestFilter['created_at_end'])) {
                $query->where('player.created_at', '<=', $requestFilter['created_at_end']);
            }
        }

        // 计算筛选后的总数
        $totalQuery = clone $query;
        $playerCount = $totalQuery->count();

        $list = $query->select([
                'player.*',
                'cash.money as wallet_money',
                'player_extend.recharge_amount',
                'player_extend.withdraw_amount',
                'player_extend.machine_put_point'
            ])
            ->orderBy('player.id', 'desc')
            ->get()
            ->toArray();

        // 计算每个设备的彩金和小计
        foreach ($list as &$item) {
            // 查询该设备的累计彩金
            $lotteryAmount = PlayerLotteryRecord::query()
                ->where('player_id', $item['id'])
                ->where('status', PlayerLotteryRecord::STATUS_COMPLETE)
                ->sum('amount') ?? 0;

            $item['lottery_amount'] = $lotteryAmount;

            // 计算小计 = (开分 + 投钞) - (洗分 + 彩金)
            $rechargeAmount = floatval($item['recharge_amount'] ?? 0);
            $machinePutPoint = floatval($item['machine_put_point'] ?? 0);
            $withdrawAmount = floatval($item['withdraw_amount'] ?? 0);

            $totalIn = bcadd($rechargeAmount, $machinePutPoint, 2);
            $totalOut = bcadd($withdrawAmount, $lotteryAmount, 2);
            $item['subtotal'] = bcsub($totalIn, $totalOut, 2);
        }

        return Grid::create($list, function (Grid $grid) use ($storeAdminId, $departmentId, $admin, $playerCount, $list) {
            $grid->title('设备列表 (当前店家: ' . $admin->username . ' | 设备数: ' . $playerCount . ')');
            $grid->autoHeight();
            $grid->bordered(true);

            $grid->column('id', 'ID')->width(80)->align('center');

            $grid->column('name', '设备名称')->display(function ($val, $data) {
                $avatar = !empty($data['avatar'])
                    ? Avatar::create()->src(is_numeric($data['avatar']) ? config('def_avatar.' . $data['avatar']) : $data['avatar'])
                    : Avatar::create()->text(mb_substr($val ?: 'U', 0, 1));
                return Html::create()->content([
                    $avatar,
                    Html::div()->content($val ?: '未命名')->style(['margin-left' => '8px'])
                ]);
            })->width(150);

            $grid->column('phone', '设备编号')->width(120)->align('center');

            $grid->column('wallet_money', '余额')->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

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

            $grid->column('status', '状态')->display(function ($value) {
                return match ($value) {
                    0 => Tag::create('已禁用')->color('red'),
                    1 => Tag::create('正常')->color('green'),
                    default => Tag::create('未知')->color('default'),
                };
            })->width(80)->align('center');

            $grid->column('created_at', '创建时间')->width(160)->align('center');

            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('status')
                    ->placeholder('状态')
                    ->options([
                        1 => '正常',
                        0 => '已禁用'
                    ])
                    ->style(['width' => '150px']);

                $filter->like()->text('phone')->placeholder('设备编号');
                $filter->like()->text('name')->placeholder('设备名称');

                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    '开始时间',
                    '结束时间'
                ]);
            });

            $grid->actions(function (Actions $actions) {
                $actions->hideEdit();
                $actions->hideDel();
            });

            $grid->hideAdd();
            $grid->hideDelete();
            $grid->hideDeleteSelection();
            $grid->hideSelection();
            $grid->expandFilter();
            $grid->attr('is_mongo', true);
            $grid->attr('is_mongo_total', $playerCount);
            $grid->attr('mongo_model', $list);

            // 如果没有数据，显示提示信息
            if ($playerCount == 0) {
                $totalPlayers = Player::query()
                    ->where('department_id', $departmentId)
                    ->where('is_promoter', 0)
                    ->count();

                $grid->emptyText('暂无设备数据<br><small style="color: #999;">部门ID: ' . $departmentId . ' | 店家ID: ' . $storeAdminId . '<br>该部门下总玩家数: ' . $totalPlayers . ' | 分配给当前店家: 0</small>');
            }
        });
    }
}
