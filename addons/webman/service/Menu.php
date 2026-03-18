<?php

namespace addons\webman\service;


use addons\webman\Admin;
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
    public function all(): array
    {
        $departmentId = Admin::user()->department_id;
        /** @var Channel $channel */
        if (Admin::user()->type == AdminDepartment::TYPE_CHANNEL) {
            $channel = Channel::where('department_id', $departmentId)->first();
        }
        return $this->model::where('status', 1)
            ->where('type', Admin::user()->type)
            ->when(plugin()->webman->config('admin_auth_id') != Admin::id(), function ($query) {
                $model = plugin()->webman->config('database.role_menu_model');
                $menuIds = $model::whereIn('role_id', Admin::role())->pluck('menu_id');
                $query->whereIn('id', $menuIds);
            })
            ->when(!empty($channel) && $channel->withdraw_status == 0, function ($query) {
                $query->where('id', '!=', 59);
            })
            ->when(!empty($channel) && $channel->promotion_status == 0, function ($query) {
                $query->whereNotIn('id', [74, 75, 76, 111, 73]);
            })
            ->when(!empty($channel) && $channel->coin_status == 0, function ($query) {
                $query->whereNotIn('id', [37, 38, 39, 40, 156]);
            })
            ->when(!empty($channel) && $channel->is_offline == 1, function ($query) {
                $query->whereNotIn('id', [74, 75, 76, 111, 73]);
            })
            ->when(!empty($channel) && $channel->is_offline == 0, function ($query) {
                $query->whereNotIn('id', [176, 177, 178, 186, 187]);
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
