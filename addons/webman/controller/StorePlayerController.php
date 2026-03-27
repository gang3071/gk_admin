<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Player;
use addons\webman\model\PlayerLotteryRecord;
use addons\webman\model\PlayerPlatformCash;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
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

            \support\Log::info(admin_trans('player.device_list_query_log'), [
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
            if (isset($requestFilter['is_crashed']) && in_array($requestFilter['is_crashed'], [0, 1])) {
                $query->where('cash.is_crashed', $requestFilter['is_crashed']);
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
                'cash.is_crashed',
                'cash.machine_crash_amount',
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
            $grid->title(admin_trans('player.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            $grid->column('id', admin_trans('player.fields.id'))->width(80)->align('center');

            $grid->column('name', admin_trans('player.fields.device_name'))->display(function ($val, $data) {
                $avatar = !empty($data['avatar'])
                    ? Avatar::create()->src(is_numeric($data['avatar']) ? config('def_avatar.' . $data['avatar']) : $data['avatar'])
                    : Avatar::create()->text(mb_substr($val ?: 'U', 0, 1));
                return Html::create()->content([
                    $avatar,
                    Html::div()->content($val ?: admin_trans('player.unnamed'))->style(['margin-left' => '8px'])
                ]);
            })->width(150);

            $grid->column('phone', admin_trans('player.fields.phone'))->width(120)->align('center');

            $grid->column('wallet_money', admin_trans('player_platform_cash.platform_name.' . PlayerPlatformCash::PLATFORM_SELF))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            $grid->column('is_crashed', admin_trans('player.is_crashed'))->display(function ($val, $data) {
                if ($val == 1) {
                    $crashAmount = isset($data['machine_crash_amount']) ? number_format(floatval($data['machine_crash_amount']), 2) : '0.00';
                    $text = admin_trans('player.crashed') . ' (' . admin_trans('player.fields.machine_crash_amount') . ': ' . $crashAmount . ')';
                    return Tag::create($text)->color('red');
                } else {
                    return Tag::create(admin_trans('player.normal'))->color('green');
                }
            })->width(180)->align('center');

            $grid->column('recharge_amount', admin_trans('player_extend.recharge_amount'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            $grid->column('withdraw_amount', admin_trans('player_extend.withdraw_amount'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            $grid->column('machine_put_point', admin_trans('player_extend.machine_put_point'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            $grid->column('lottery_amount', admin_trans('player_lottery_record.lottery_amount'))->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            $grid->column('subtotal', admin_trans('player_extend.subtotal'))->display(function ($value) {
                $color = $value >= 0 ? '#3f8600' : '#cf1322';
                return Html::create(number_format(floatval($value), 2))->style(['color' => $color, 'fontWeight' => 'bold']);
            })->width(120)->align('center');

            $grid->column('status', admin_trans('player.fields.status'))->display(function ($value) {
                return match ($value) {
                    0 => Tag::create(admin_trans('admin.close'))->color('red'),
                    1 => Tag::create(admin_trans('admin.open'))->color('green'),
                    default => Tag::create(admin_trans('admin.unknown'))->color('default'),
                };
            })->width(80)->align('center');

            $grid->column('created_at', admin_trans('player.fields.created_at'))->width(160)->align('center');

            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('status')
                    ->placeholder(admin_trans('player.fields.status'))
                    ->options([
                        1 => admin_trans('admin.open'),
                        0 => admin_trans('admin.close')
                    ])
                    ->style(['width' => '150px']);

                $filter->eq()->select('is_crashed')
                    ->placeholder(admin_trans('player.is_crashed'))
                    ->options([
                        '' => admin_trans('public_msg.all'),
                        0 => admin_trans('player.normal'),
                        1 => admin_trans('player.crashed')
                    ])
                    ->style(['width' => '150px']);

                $filter->like()->text('phone')->placeholder(admin_trans('player.fields.phone'));
                $filter->like()->text('name')->placeholder(admin_trans('player.fields.device_name'));

                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
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

                $grid->emptyText(admin_trans('player.no_device_data'));
            }
        });
    }
}
