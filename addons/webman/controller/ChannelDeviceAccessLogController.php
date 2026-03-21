<?php

namespace addons\webman\controller;

use addons\webman\model\Device;
use addons\webman\model\DeviceAccessLog;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;

/**
 * 设备访问日志控制器
 */
class ChannelDeviceAccessLogController
{
    /**
     * 访问日志列表
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::make(new DeviceAccessLog(), function (Grid $grid) {
            $grid->title(admin_trans('device.access_log_title'));
            $grid->bordered(true);
            $grid->autoHeight();

            // 数据权限过滤
            $grid->model()->where(function ($query) {
                $adminUser = admin();
                if ($adminUser->isChannelAdmin()) {
                    $channelId = $adminUser->getChannelId();
                    $deviceIds = Device::where('channel_id', $channelId)->pluck('id');
                    $query->whereIn('device_id', $deviceIds);
                } elseif ($adminUser->isAgentAdmin() || $adminUser->isStoreAdmin()) {
                    $departmentId = $adminUser->department_id;
                    $deviceIds = Device::where('department_id', $departmentId)->pluck('id');
                    $query->whereIn('device_id', $deviceIds);
                }
            });

            $grid->model()->with('device')->orderBy('id', 'desc');

            // 列配置
            $grid->column('id', 'ID')->width(80);

            $grid->column('device.device_name', admin_trans('device.fields.device_name'))
                ->align('center');

            $grid->column('device_no', admin_trans('device.fields.device_no'))
                ->align('center')
                ->copyable();

            $grid->column('ip_address', admin_trans('device.fields.ip_address'))
                ->align('center')
                ->copyable();

            $grid->column('is_allowed', admin_trans('device.fields.is_allowed'))
                ->display(function ($value) {
                    return Tag::create($value == DeviceAccessLog::IS_ALLOWED_YES ? admin_trans('device.access_log.allowed') : admin_trans('device.access_log.rejected'))
                        ->color($value == DeviceAccessLog::IS_ALLOWED_YES ? 'green' : 'red');
                })
                ->align('center');

            $grid->column('reject_reason', admin_trans('device.fields.reject_reason'))
                ->limit(50)
                ->align('center');

            $grid->column('request_url', admin_trans('device.fields.request_url'))
                ->limit(100)
                ->align('center');

            $grid->column('user_agent', admin_trans('device.fields.user_agent'))
                ->limit(100)
                ->align('center');

            $grid->column('created_at', admin_trans('device.fields.created_at'))
                ->align('center')
                ->sortable();

            // 筛选
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('device_no')->placeholder(admin_trans('device.fields.device_no'));
                $filter->like()->text('ip_address')->placeholder(admin_trans('device.fields.ip_address'));
                $filter->eq()->select('is_allowed')
                    ->placeholder(admin_trans('device.fields.is_allowed'))
                    ->options([
                        DeviceAccessLog::IS_ALLOWED_NO => admin_trans('device.access_log.rejected'),
                        DeviceAccessLog::IS_ALLOWED_YES => admin_trans('device.access_log.allowed'),
                    ]);

                $filter->select('date_type')
                    ->placeholder(admin_trans('machine_report.fields.date_type'))
                    ->showSearch()
                    ->dropdownMatchSelectWidth()
                    ->style(['width' => '200px'])
                    ->options([
                        1 => admin_trans('machine_report.date_type.1'),
                        2 => admin_trans('machine_report.date_type.2'),
                        3 => admin_trans('machine_report.date_type.3'),
                        4 => admin_trans('machine_report.date_type.4'),
                        5 => admin_trans('machine_report.date_type.5'),
                        6 => admin_trans('machine_report.date_type.6'),
                    ]);

                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });

            // 隐藏操作按钮
            $grid->hideAction();
            $grid->hideDelete();
            $grid->hideSelection();

            $grid->export();
        });
    }
}
