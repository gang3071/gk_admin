<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminDevice;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;

/**
 * 店机后台 - 设备管理
 * @group store
 */
class StoreDeviceController
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

        return Grid::create(new AdminDevice(), function (Grid $grid) use ($storeAdminId) {
            $grid->title(admin_trans('device.title'));
            $grid->bordered(true);
            $grid->autoHeight();

            $grid->model()
                ->with(['currentPlayer.machine_wallet'])
                ->where('store_admin_id', $storeAdminId)
                ->where('status', AdminDevice::STATUS_ENABLED)
                ->orderBy('id', 'desc');

            $grid->expandFilter(false);

            // 只读，不允许新增/编辑/删除
            $grid->hideCreate();
            $grid->hideDelete();
            $grid->actions(function ($actions) {
                $actions->hideEdit();
                $actions->hideDetail();
                $actions->hideDel();
            });

            $grid->column('id', 'ID')->width(60)->sortable();

            $grid->column('device_name', admin_trans('device.fields.device_name'))
                ->align('center');

            $grid->column('device_no', admin_trans('device.fields.device_no'))
                ->align('center')
                ->copyable();

            $grid->column('device_type', admin_trans('device.fields.device_type'))
                ->width(100)
                ->align('center')
                ->display(function ($val) {
                    $types = AdminDevice::getDeviceTypeList();
                    $colors = [
                        AdminDevice::TYPE_GAME_MACHINE => 'blue',
                        AdminDevice::TYPE_VENDING_MACHINE => 'orange',
                    ];
                    return Tag::create($types[$val] ?? '-')
                        ->color($colors[$val] ?? 'default');
                });

            $grid->column('current_player_id', admin_trans('device.fields.current_player'))
                ->width(160)
                ->align('center')
                ->display(function ($val, $row) {
                    /** @var AdminDevice $row */
                    $player = $row->currentPlayer;
                    if (empty($player)) {
                        return Tag::create(admin_trans('device.no_player'))->color('default');
                    }
                    $name = $player->name ?: $player->phone;
                    return Html::create(
                        '<div style="line-height:1.6">' .
                        '<span>' . htmlspecialchars($name) . '</span><br>' .
                        '<span style="color:#8c8c8c;font-size:12px">' . htmlspecialchars($player->phone) . '</span>' .
                        '</div>'
                    );
                });

            $grid->column('current_player_id', admin_trans('device.fields.wallet_balance'))
                ->width(130)
                ->align('center')
                ->display(function ($val, $row) {
                    /** @var AdminDevice $row */
                    $player = $row->currentPlayer;
                    if (empty($player) || empty($player->machine_wallet)) {
                        return '—';
                    }
                    $balance = number_format($player->machine_wallet->money, 2);
                    return Html::create('<span style="color:#1890ff;font-weight:600">' . $balance . '</span>');
                });

            $grid->column('status', admin_trans('device.fields.status'))
                ->width(80)
                ->align('center')
                ->display(function ($val) {
                    return $val == AdminDevice::STATUS_ENABLED
                        ? Tag::create(admin_trans('device.status.enabled'))->color('green')
                        : Tag::create(admin_trans('device.status.disabled'))->color('red');
                });

            $grid->filter(function (Filter $filter) {
                $filter->like()->text('device_name')
                    ->placeholder(admin_trans('device.fields.device_name'));
                $filter->eq()->select('device_type')
                    ->placeholder(admin_trans('device.fields.device_type'))
                    ->options(AdminDevice::getDeviceTypeList());
            });
        });
    }
}
