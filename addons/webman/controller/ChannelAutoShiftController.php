<?php

namespace addons\webman\controller;

use app\service\store\AutoShiftService;
use addons\webman\model\StoreAutoShiftConfig;
use addons\webman\model\StoreAutoShiftLog;
use support\Request;
use support\Response;

/**
 * 自动交班控制器
 */
class ChannelAutoShiftController extends ChannelBaseController
{
    /**
     * 配置页面
     */
    public function config(Request $request): Response
    {
        $admin = $this->admin();
        $service = new AutoShiftService();

        $config = $service->getConfig($admin->department_id, $admin->id);

        // 获取执行统计
        $stats = null;
        if ($config && $config->is_enabled) {
            $stats = $service->getExecutionStats($admin->department_id, $admin->id, 7);
        }

        return view('channel/auto-shift/config', [
            'config' => $config,
            'stats' => $stats
        ]);
    }

    /**
     * 保存配置
     */
    public function saveConfig(Request $request): Response
    {
        $admin = $this->admin();

        // 处理每周交班日期
        $weekdays = $request->post('weekdays');
        if (is_array($weekdays)) {
            $weekdays = implode(',', $weekdays);
        }

        $data = [
            'department_id' => $admin->department_id,
            'bind_admin_user_id' => $admin->id,
            'is_enabled' => $request->post('is_enabled', 0),
            'shift_mode' => $request->post('shift_mode', 1),
            'shift_time' => $request->post('shift_time', '02:00:00'),
            'shift_weekdays' => $weekdays,
            'shift_interval_hours' => $request->post('shift_interval_hours'),
            'auto_settlement' => $request->post('auto_settlement', 1),
            'notify_on_failure' => $request->post('notify_on_failure', 1),
            'notify_phones' => $request->post('notify_phones'),
        ];

        $service = new AutoShiftService();
        $result = $service->saveConfig($data);

        return json($result);
    }

    /**
     * 执行日志列表
     */
    public function logs(Request $request): Response
    {
        $admin = $this->admin();

        $query = StoreAutoShiftLog::query()
            ->where('department_id', $admin->department_id)
            ->where('bind_admin_user_id', $admin->id)
            ->orderBy('id', 'desc');

        // 状态筛选
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // 时间筛选
        if ($startDate = $request->get('start_date')) {
            $query->where('created_at', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate = $request->get('end_date')) {
            $query->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        $logs = $query->paginate(20);

        // 统计数据
        $statsQuery = StoreAutoShiftLog::query()
            ->where('department_id', $admin->department_id)
            ->where('bind_admin_user_id', $admin->id);

        if ($startDate) {
            $statsQuery->where('created_at', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate) {
            $statsQuery->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        $allLogs = $statsQuery->get();
        $stats = [
            'total' => $allLogs->count(),
            'success' => $allLogs->where('status', StoreAutoShiftLog::STATUS_SUCCESS)->count(),
            'failed' => $allLogs->where('status', StoreAutoShiftLog::STATUS_FAILED)->count(),
            'avg_duration' => round($allLogs->avg('execution_duration'), 2),
            'total_profit' => $allLogs->sum('total_profit'),
        ];

        return view('channel/auto-shift/logs', [
            'logs' => $logs,
            'stats' => $stats,
            'request' => $request
        ]);
    }

    /**
     * 日志详情
     */
    public function logDetail(Request $request): Response
    {
        $admin = $this->admin();
        $id = $request->get('id');

        $log = StoreAutoShiftLog::query()
            ->where('id', $id)
            ->where('department_id', $admin->department_id)
            ->where('bind_admin_user_id', $admin->id)
            ->first();

        if (!$log) {
            return json(['code' => 1, 'msg' => '日志不存在']);
        }

        // 关联数据
        $log->load(['config', 'shiftRecord']);

        return json([
            'code' => 0,
            'data' => $log
        ]);
    }

    /**
     * 手动触发一次
     */
    public function manualTrigger(Request $request): Response
    {
        $admin = $this->admin();
        $service = new AutoShiftService();

        $config = $service->getConfig($admin->department_id, $admin->id);

        if (!$config) {
            return json(['code' => 1, 'msg' => '未找到自动交班配置']);
        }

        if (!$config->is_enabled) {
            return json(['code' => 1, 'msg' => '自动交班未启用']);
        }

        \Log::info('手动触发自动交班', [
            'admin_id' => $admin->id,
            'department_id' => $admin->department_id,
            'config_id' => $config->id
        ]);

        $result = $service->executeAutoShift($config);

        return json($result);
    }

    /**
     * 切换启用状态
     */
    public function toggleEnabled(Request $request): Response
    {
        $admin = $this->admin();
        $enabled = $request->post('enabled', 0);

        $service = new AutoShiftService();
        $config = $service->getConfig($admin->department_id, $admin->id);

        if (!$config) {
            return json(['code' => 1, 'msg' => '未找到配置']);
        }

        $result = $service->saveConfig([
            'department_id' => $admin->department_id,
            'bind_admin_user_id' => $admin->id,
            'is_enabled' => $enabled,
            'shift_mode' => $config->shift_mode,
            'shift_time' => $config->shift_time,
            'shift_weekdays' => $config->shift_weekdays,
            'shift_interval_hours' => $config->shift_interval_hours,
            'auto_settlement' => $config->auto_settlement,
            'notify_on_failure' => $config->notify_on_failure,
            'notify_phones' => $config->notify_phones,
        ]);

        return json($result);
    }

    /**
     * 获取执行统计
     */
    public function stats(Request $request): Response
    {
        $admin = $this->admin();
        $days = $request->get('days', 7);

        $service = new AutoShiftService();
        $stats = $service->getExecutionStats($admin->department_id, $admin->id, $days);

        return json([
            'code' => 0,
            'data' => $stats
        ]);
    }
}
