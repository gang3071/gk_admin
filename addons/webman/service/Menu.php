<?php

namespace addons\webman\service;


use addons\webman\Admin;
use addons\webman\constant\MenuConstant;
use addons\webman\model\AdminDepartment;
use addons\webman\model\Channel;
use ExAdmin\ui\contract\MenuAbstract;

class Menu extends MenuAbstract
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.menu_model');
    }

    /**
     * 菜单
     * @return array
     */
    /**
     * 获取菜单列表
     * 根据渠道功能开关动态过滤菜单
     *
     * @return array
     */
    public function all(): array
    {
        $departmentId = Admin::user()->department_id;
        /** @var Channel $channel */
        $channel = null;

        // 获取渠道信息（渠道用户和代理用户都需要检查所属渠道的功能开关）
        if (Admin::user()->type == AdminDepartment::TYPE_CHANNEL) {
            // 渠道用户：直接获取当前渠道
            $channel = Channel::where('department_id', $departmentId)->first();
        } elseif (Admin::user()->type == AdminDepartment::TYPE_AGENT) {
            // 代理用户：获取所属渠道（代理的 department_id 就是渠道的 department_id）
            $channel = Channel::where('department_id', $departmentId)->first();
        }

        return $this->model::where('status', 1)
            ->where('type', Admin::user()->type)
            // 非超级管理员需要检查角色菜单权限
            ->when(plugin()->webman->config('admin_auth_id') != Admin::id(), function ($query) {
                $model = plugin()->webman->config('database.role_menu_model');
                $menuIds = $model::whereIn('role_id', Admin::role())->pluck('menu_id');
                $query->whereIn('id', $menuIds);
            })
            // 提现功能开关
            ->when(!empty($channel) && $channel->withdraw_status == 0, function ($query) {
                $query->whereNotIn('name', MenuConstant::WITHDRAW_MENUS);
            })
            // 推广功能开关
            ->when(!empty($channel) && $channel->promotion_status == 0, function ($query) {
                $query->whereNotIn('name', MenuConstant::PROMOTION_MENUS);
            })
            // 金币功能开关
            ->when(!empty($channel) && $channel->coin_status == 0, function ($query) {
                $query->whereNotIn('name', MenuConstant::COIN_MENUS);
            })
            // 线下渠道排除推广菜单
            ->when(!empty($channel) && $channel->is_offline == 1, function ($query) {
                $query->whereNotIn('name', MenuConstant::OFFLINE_EXCLUDE_MENUS);
            })
            // 线上渠道排除线下机台菜单
            ->when(!empty($channel) && $channel->is_offline == 0, function ($query) {
                $query->whereNotIn('name', MenuConstant::ONLINE_EXCLUDE_MENUS);
            })
            // 摸奖券功能开关
            ->when(!empty($channel) && $channel->lottery_ticket_enabled == 0, function ($query) {
                $query->whereNotIn('name', MenuConstant::LOTTERY_TICKET_MENUS);
            })
            // VIP等级功能开关
            ->when(!empty($channel) && $channel->vip_level_status == 0, function ($query) {
                $query->whereNotIn('name', MenuConstant::VIP_LEVEL_MENUS);
            })
            ->orderBy('sort')->get()->toArray();
    }

    /**
     * 获取菜单
     * @param array $data
     * @return array
     */
    public function get($id)
    {
        return $this->model::find($id)->toArray();
    }

    /**
     * 更新菜单
     * @param int $id
     * @param array $data
     * @return mixed
     */
    public function update($id, $data)
    {
        $this->model::where('id', $id)->update($data);
    }

    /**
     * 创建菜单
     * @param array $data
     * @return int
     */
    public function create(array $data): int
    {
        $result = $this->model::create($data);
        return $result->id;
    }

    /**
     * 启用菜单
     * @param $plugin
     * @return mixed
     */
    public function enable($plugin)
    {
        $this->model::where('plugin', $plugin)->update(['status' => 1]);
    }

    /**
     * 禁用菜单
     * @param $plugin
     * @return mixed
     */
    public function disable($plugin)
    {
        $this->model::where('plugin', $plugin)->update(['status' => 0]);
    }

    /**
     * 删除菜单
     * @param $plugin
     * @return mixed
     */
    public function delete($plugin)
    {
        $this->model::where('plugin', $plugin)->delete();
    }
}
