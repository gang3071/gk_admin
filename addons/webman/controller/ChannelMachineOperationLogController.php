<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Grid;

/**
 * 渠道机台操作日志
 *
 * 与总后台同一页，差别只在按当前登录人的 department_id 收窄数据。
 * 旧版 actionsList 漏了这层过滤，导致渠道端能看全渠道数据，本次补上。
 */
class ChannelMachineOperationLogController extends MachineOperationLogController
{
    /**
     * 渠道机台操作日志
     * @auth true
     * @group channel
     * @return Card
     */
    public function index(): Card
    {
        return parent::index();
    }

    /**
     * 管理员操作（上分/下分）
     * @group channel
     * @return Grid
     */
    public function actionsList(): Grid
    {
        return parent::actionsList();
    }

    /**
     * 渠道数据隔离：只看本渠道
     */
    protected function applyDepartmentScope(Grid $grid): void
    {
        $departmentId = Admin::user()->department_id ?? 0;
        if ($departmentId > 0) {
            $grid->model()->where('department_id', $departmentId);
        }
    }
}
