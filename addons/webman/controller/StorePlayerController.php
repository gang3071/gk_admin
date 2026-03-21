<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\Player;
use addons\webman\model\PlayerPlatformCash;
use addons\webman\model\PlayerExtend;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\common\Html;

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
        $storeAdminId = Admin::user()->id;
        $departmentId = Admin::user()->department_id;

        return Grid::create(new Player(), function (Grid $grid) use ($storeAdminId, $departmentId) {
            $grid->title('设备列表');
            $grid->autoHeight();
            $grid->bordered(true);

            // 查询条件：店家管理的玩家（设备）
            $grid->model()
                ->leftJoin('player_platform_cash as cash', function ($join) {
                    $join->on('player.id', '=', 'cash.player_id')
                        ->where('cash.platform_id', PlayerPlatformCash::PLATFORM_SELF);
                })
                ->leftJoin('player_extend', 'player.id', '=', 'player_extend.player_id')
                ->where('player.department_id', $departmentId)
                ->where('player.store_admin_id', $storeAdminId)
                ->where('player.is_promoter', 0)
                ->select([
                    'player.*',
                    'cash.money as wallet_money',
                    'player_extend.present_in_amount',
                    'player_extend.present_out_amount'
                ])
                ->orderBy('player.id', 'desc');

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

            $grid->column('present_in_amount', '累计开分')->display(function ($value) {
                return number_format(floatval($value), 2);
            })->width(120)->align('center');

            $grid->column('present_out_amount', '累计洗分')->display(function ($value) {
                return number_format(floatval($value), 2);
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
                $filter->eq()->select('player.status')
                    ->placeholder('状态')
                    ->options([
                        1 => '正常',
                        0 => '已禁用'
                    ])
                    ->style(['width' => '150px']);

                $filter->like()->text('player.phone')->placeholder('设备编号');
                $filter->like()->text('player.name')->placeholder('设备名称');

                $filter->between()->dateTimeRange('player.created_at')
                    ->placeholder(['开始时间', '结束时间']);
            });

            $grid->actions(function (Actions $actions) {
                $actions->hideEdit();
                $actions->hideDel();
            });

            $grid->hideAdd();
            $grid->expandFilter();
        });
    }
}
