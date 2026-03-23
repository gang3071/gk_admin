<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use app\service\store\AutoShiftService;
use addons\webman\model\StoreAutoShiftConfig;
use addons\webman\model\StoreAutoShiftLog;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\component\layout\Divider;
use support\Request;
use support\Response;

/**
 * 自动交班控制器
 * @group store
 */
class ChannelAutoShiftController
{
    /**
     * 配置页面
     * @group store
     * @auth true
     */
    public function config(): Form
    {
        $admin = Admin::user();
        $service = new AutoShiftService();

        $config = $service->getConfig($admin->department_id, $admin->id);

        return Form::create($config ? $config->toArray() : [], function (Form $form) use ($admin, $config, $service) {
            $form->title('自动交班配置');
            $form->layout('vertical');

            // 显示执行统计
            if ($config && $config->is_enabled) {
                $stats = $service->getExecutionStats($admin->department_id, $admin->id, 7);

                $form->push(Card::create([
                    Html::create('<h4>最近7天执行统计</h4>')->tag('div'),
                    Row::create()->gutter(16)->content([
                        Row::col(6)->content(
                            Statistic::create()
                                ->title('总执行次数')
                                ->value($stats['total'] ?? 0)
                                ->suffix('次')
                        ),
                        Row::col(6)->content(
                            Statistic::create()
                                ->title('成功次数')
                                ->value($stats['success'] ?? 0)
                                ->suffix('次')
                                ->valueStyle(['color' => '#3f8600'])
                        ),
                        Row::col(6)->content(
                            Statistic::create()
                                ->title('失败次数')
                                ->value($stats['failed'] ?? 0)
                                ->suffix('次')
                                ->valueStyle(['color' => '#cf1322'])
                        ),
                        Row::col(6)->content(
                            Statistic::create()
                                ->title('成功率')
                                ->value($stats['total'] > 0 ? round(($stats['success'] / $stats['total']) * 100, 2) : 0)
                                ->suffix('%')
                        ),
                    ])
                ])->title('执行统计')->style(['margin-bottom' => '20px']));
            }

            // 显示下次交班时间
            if ($config && $config->next_shift_time) {
                $form->push(Card::create([
                    Html::create('<p>下次交班时间：<strong>' . $config->next_shift_time . '</strong></p>')->tag('div'),
                ])->title('执行信息'));
            } else {
                $form->push(Card::create([
                    Html::create('<p style="color: #999;">配置保存后，系统将自动计算下次交班时间</p>')->tag('div')
                ])->title('执行信息'));
            }

            // 快捷操作
            if ($config) {
                $manualTriggerUrl = admin_url('addons-webman-controller-ChannelAutoShiftController-manualTrigger');
                $logsUrl = admin_url('addons-webman-controller-ChannelAutoShiftController-logs');

                $form->push(Card::create([
                    Html::create('
                        <div style="padding: 10px 0;">
                            <a href="' . $logsUrl . '" class="ant-btn ant-btn-default" target="_blank" style="margin-right: 10px;">
                                <span>查看执行日志</span>
                            </a>
                            ' . ($config->is_enabled ? '
                            <a href="' . $manualTriggerUrl . '"
                               class="ant-btn ant-btn-primary"
                               onclick="return confirm(\'确定要立即执行一次自动交班吗？\n\n这不会影响定时执行计划。\')"
                               style="margin-right: 10px;">
                                <span>手动触发一次</span>
                            </a>
                            ' : '') . '
                        </div>
                    ')->tag('div')
                ])->title('快捷操作'));
            }

            // 处理表单提交
            $form->saving(function (Form $form) use ($admin, $service) {
                $data = [
                    'department_id' => $admin->department_id,
                    'bind_admin_user_id' => $admin->id,
                    'is_enabled' => $form->input('is_enabled', 0),
                    'shift_time_1' => $form->input('shift_time_1', '08:00:00'),
                    'shift_time_2' => $form->input('shift_time_2', '16:00:00'),
                    'shift_time_3' => $form->input('shift_time_3', '00:00:00'),
                    'auto_settlement' => 1, // 总是自动结算
                ];

                $result = $service->saveConfig($data);

                if ($result['code'] === 0) {
                    return message_success($result['msg'] ?? '保存成功');
                } else {
                    return message_error($result['msg'] ?? '保存失败');
                }
            });
        });
    }

    /**
     * 保存配置
     * @group store
     * @auth true
     */
    public function saveConfig(Request $request): Response
    {
        $admin = Admin::user();

        $data = [
            'department_id' => $admin->department_id,
            'bind_admin_user_id' => $admin->id,
            'is_enabled' => $request->post('is_enabled', 0),
            'shift_time_1' => $request->post('shift_time_1', '08:00:00'),
            'shift_time_2' => $request->post('shift_time_2', '16:00:00'),
            'shift_time_3' => $request->post('shift_time_3', '00:00:00'),
            'auto_settlement' => 1, // 总是自动结算
        ];

        $service = new AutoShiftService();
        $result = $service->saveConfig($data);

        return json($result);
    }

    /**
     * 执行日志列表
     * @group store
     * @auth true
     */
    public function logs(): Grid
    {
        $admin = Admin::user();

        return Grid::create(new StoreAutoShiftLog(), function (Grid $grid) use ($admin) {
            $grid->title('自动交班执行日志');
            $grid->autoHeight();
            $grid->bordered(true);

            // 数据权限过滤
            $grid->model()
                ->where('department_id', $admin->department_id)
                ->where('bind_admin_user_id', $admin->id)
                ->orderBy('id', 'desc');

            $grid->column('id', 'ID')->width(80)->align('center');

            $grid->column('execute_time', '执行时间')->width(180)->align('center');

            $grid->column('time_range', '统计时间段')->display(function ($val, $data) {
                return Html::create()->content([
                    Html::div()->content('开始：' . $data['start_time']),
                    Html::div()->content('结束：' . $data['end_time'])
                ]);
            })->width(200);

            $grid->column('status', '执行状态')->display(function ($value) {
                return match ($value) {
                    StoreAutoShiftLog::STATUS_SUCCESS => Tag::create('成功')->color('success'),
                    StoreAutoShiftLog::STATUS_FAILED => Tag::create('失败')->color('error'),
                    StoreAutoShiftLog::STATUS_PARTIAL_SUCCESS => Tag::create('部分成功')->color('warning'),
                    default => Tag::create('未知')->color('default'),
                };
            })->width(100)->align('center');

            $grid->column('machine_point', '投钞点数')->width(100)->align('center');
            $grid->column('total_in', '总收入')->width(100)->align('center');
            $grid->column('total_out', '总支出')->width(100)->align('center');
            $grid->column('lottery_amount', '彩金金额')->width(100)->align('center');
            $grid->column('total_profit', '总利润')->width(100)->align('center')
                ->display(function ($value) {
                    $color = $value >= 0 ? '#3f8600' : '#cf1322';
                    return Html::create('<span style="color: ' . $color . '">' . $value . '</span>');
                });

            $grid->column('execution_duration', '执行耗时')->width(100)->align('center')
                ->display(function ($value) {
                    return round($value / 1000, 2) . 's';
                });

            $grid->column('error_message', '错误信息')->ellipsis(true)->width(200);

            // 行展开 - 显示详细信息
            $grid->expandRow(function ($row) {
                return Card::create([
                    Html::create('<div style="padding: 20px;">
                        <h4>执行详情</h4>
                        <table style="width: 100%; margin-top: 10px;">
                            <tr>
                                <td style="padding: 8px; width: 150px; font-weight: bold;">配置ID：</td>
                                <td style="padding: 8px;">' . $row['config_id'] . '</td>
                                <td style="padding: 8px; width: 150px; font-weight: bold;">交班记录ID：</td>
                                <td style="padding: 8px;">' . ($row['shift_record_id'] ?? '-') . '</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; font-weight: bold;">统计开始时间：</td>
                                <td style="padding: 8px;">' . $row['start_time'] . '</td>
                                <td style="padding: 8px; font-weight: bold;">统计结束时间：</td>
                                <td style="padding: 8px;">' . $row['end_time'] . '</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; font-weight: bold;">机台投钞金额：</td>
                                <td style="padding: 8px;">' . $row['machine_amount'] . '</td>
                                <td style="padding: 8px; font-weight: bold;">机台投钞点数：</td>
                                <td style="padding: 8px;">' . $row['machine_point'] . '</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; font-weight: bold;">总收入（送分）：</td>
                                <td style="padding: 8px;" style="color: #3f8600;">' . $row['total_in'] . '</td>
                                <td style="padding: 8px; font-weight: bold;">总支出（取分）：</td>
                                <td style="padding: 8px;" style="color: #cf1322;">' . $row['total_out'] . '</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; font-weight: bold;">彩金发放：</td>
                                <td style="padding: 8px;">' . $row['lottery_amount'] . '</td>
                                <td style="padding: 8px; font-weight: bold;">总利润：</td>
                                <td style="padding: 8px; color: ' . ($row['total_profit'] >= 0 ? '#3f8600' : '#cf1322') . ';">' . $row['total_profit'] . '</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; font-weight: bold;">执行时间：</td>
                                <td style="padding: 8px;">' . $row['execute_time'] . '</td>
                                <td style="padding: 8px; font-weight: bold;">执行耗时：</td>
                                <td style="padding: 8px;">' . round($row['execution_duration'] / 1000, 2) . ' 秒</td>
                            </tr>
                            ' . ($row['error_message'] ? '
                            <tr>
                                <td style="padding: 8px; font-weight: bold; vertical-align: top;">错误信息：</td>
                                <td colspan="3" style="padding: 8px; color: #cf1322;">' . nl2br(htmlspecialchars($row['error_message'])) . '</td>
                            </tr>
                            ' : '') . '
                        </table>
                    </div>')->tag('div')
                ]);
            });

            // 筛选
            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('status')
                    ->placeholder('执行状态')
                    ->options([
                        StoreAutoShiftLog::STATUS_SUCCESS => '成功',
                        StoreAutoShiftLog::STATUS_FAILED => '失败',
                        StoreAutoShiftLog::STATUS_PARTIAL_SUCCESS => '部分成功'
                    ]);

                $filter->form()->hidden('start_date');
                $filter->form()->hidden('end_date');
                $filter->form()->dateRange('start_date', 'end_date', '执行时间')
                    ->placeholder(['开始日期', '结束日期']);
            });

            // 操作
            $grid->actions(function (Actions $actions) {
                $actions->hideEdit();
                $actions->hideDel();
            });

            $grid->hideDelete();
            $grid->expandFilter();
        });
    }

    /**
     * 日志详情
     * @group store
     * @auth true
     */
    public function logDetail(Request $request): Response
    {
        $admin = Admin::user();
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
     * @group store
     * @auth true
     */
    public function manualTrigger(Request $request): Response
    {
        $admin = Admin::user();
        $service = new AutoShiftService();

        $config = $service->getConfig($admin->department_id, $admin->id);

        if (!$config) {
            return redirect(admin_url('addons-webman-controller-ChannelAutoShiftController-config'))
                ->with('error', '未找到自动交班配置，请先完成配置');
        }

        if (!$config->is_enabled) {
            return redirect(admin_url('addons-webman-controller-ChannelAutoShiftController-config'))
                ->with('error', '自动交班未启用，请先启用后再手动触发');
        }

        \Log::info('手动触发自动交班', [
            'admin_id' => $admin->id,
            'department_id' => $admin->department_id,
            'config_id' => $config->id
        ]);

        $result = $service->executeAutoShift($config);

        // 重定向到日志页面
        if ($result['code'] === 0) {
            return redirect(admin_url('addons-webman-controller-ChannelAutoShiftController-logs'))
                ->with('success', '手动触发成功！交班已完成，请查看执行日志。');
        } else {
            return redirect(admin_url('addons-webman-controller-ChannelAutoShiftController-config'))
                ->with('error', '手动触发失败：' . ($result['msg'] ?? '未知错误'));
        }
    }

    /**
     * 切换启用状态
     * @group store
     * @auth true
     */
    public function toggleEnabled(Request $request): Response
    {
        $admin = Admin::user();
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
            'shift_time_1' => $config->shift_time_1 ?? '08:00:00',
            'shift_time_2' => $config->shift_time_2 ?? '16:00:00',
            'shift_time_3' => $config->shift_time_3 ?? '00:00:00',
            'auto_settlement' => 1, // 总是自动结算
        ]);

        return json($result);
    }

    /**
     * 获取执行统计
     * @group store
     * @auth true
     */
    public function stats(Request $request): Response
    {
        $admin = Admin::user();
        $days = $request->get('days', 7);

        $service = new AutoShiftService();
        $stats = $service->getExecutionStats($admin->department_id, $admin->id, $days);

        return json([
            'code' => 0,
            'data' => $stats
        ]);
    }
}
