<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use app\service\store\AutoShiftService;
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
            $form->title(admin_trans('shift_handover.auto.title'));
            $form->layout('vertical');

            // 显示执行统计
            if ($config && $config->is_enabled) {
                $stats = $service->getExecutionStats($admin->department_id, $admin->id, 7);

                $form->push(Card::create()
                    ->title(admin_trans('shift_handover.auto.stats_title'))
                    ->style(['margin-bottom' => '20px'])
                    ->content(
                        Row::create()->gutter(16)->content([
                            Row::col(6)->content(
                                Statistic::create()
                                    ->title(admin_trans('shift_handover.auto.stats_total'))
                                    ->value($stats['total'] ?? 0)
                                    ->suffix(admin_trans('shift_handover.auto.stats_times'))
                            ),
                            Row::col(6)->content(
                                Statistic::create()
                                    ->title(admin_trans('shift_handover.auto.stats_success'))
                                    ->value($stats['success'] ?? 0)
                                    ->suffix(admin_trans('shift_handover.auto.stats_times'))
                                    ->valueStyle(['color' => '#3f8600'])
                            ),
                            Row::col(6)->content(
                                Statistic::create()
                                    ->title(admin_trans('shift_handover.auto.stats_failed'))
                                    ->value($stats['failed'] ?? 0)
                                    ->suffix(admin_trans('shift_handover.auto.stats_times'))
                                    ->valueStyle(['color' => '#cf1322'])
                            ),
                            Row::col(6)->content(
                                Statistic::create()
                                    ->title(admin_trans('shift_handover.auto.stats_success_rate'))
                                    ->value($stats['total'] > 0 ? round(($stats['success'] / $stats['total']) * 100, 2) : 0)
                                    ->suffix('%')
                            ),
                        ])
                    ));
            }

            // 基础配置
            $form->push(Divider::create(admin_trans('shift_handover.auto.config_title')));

            $form->switch('is_enabled', admin_trans('shift_handover.auto.enable'))
                ->checkedValue(1)
                ->unCheckedValue(0)
                ->help(admin_trans('shift_handover.auto.enable_help'));

            $form->time('shift_time_1', admin_trans('shift_handover.auto.shift_time_1'))
                ->default('08:00:00')
                ->help(admin_trans('shift_handover.auto.shift_time_1_help'));

            $form->time('shift_time_2', admin_trans('shift_handover.auto.shift_time_2'))
                ->default('16:00:00')
                ->help(admin_trans('shift_handover.auto.shift_time_2_help'));

            $form->time('shift_time_3', admin_trans('shift_handover.auto.shift_time_3'))
                ->default('00:00:00')
                ->help(admin_trans('shift_handover.auto.shift_time_3_help'));

            // 显示下次交班时间
            if ($config && $config->next_shift_time) {
                $form->push(Card::create([
                    Html::div()->content(admin_trans('shift_handover.auto.next_shift_time') . '：' . $config->next_shift_time)
                ])->title(admin_trans('shift_handover.auto.exec_info')));
            } else {
                $form->push(Card::create([
                    Html::div()->content(admin_trans('shift_handover.auto.config_save_hint'))->style(['color' => '#999'])
                ])->title(admin_trans('shift_handover.auto.exec_info')));
            }

            // 快捷操作
            if ($config) {
                $form->push(Card::create([
                    Button::create(admin_trans('shift_handover.auto.view_logs'))
                        ->type('default')
                        ->modal([$this, 'logs'])->width('80%')
                ])->title(admin_trans('shift_handover.auto.quick_actions')));
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
                ];

                $result = $service->saveConfig($data);
                return message_success($data);
                if ($result['code'] === 0) {
                    return message_success($result['msg'] ?? admin_trans('shift_handover.auto.save_success'));
                } else {
                    return message_error($result['msg'] ?? admin_trans('shift_handover.auto.save_failed'));
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
            $grid->title(admin_trans('shift_handover.auto.logs_title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 数据权限过滤
            $grid->model()
                ->where('department_id', $admin->department_id)
                ->where('bind_admin_user_id', $admin->id)
                ->orderBy('id', 'desc');

            $grid->column('id', admin_trans('shift_handover.auto.log_id'))->width(80)->align('center');

            $grid->column('execute_time', admin_trans('shift_handover.auto.execute_time'))->width(180)->align('center');

            $grid->column('time_range', admin_trans('shift_handover.auto.time_range'))->display(function ($val, $data) {
                return Html::create()->content([
                    Html::div()->content(admin_trans('shift_handover.auto.time_start') . '：' . $data['start_time']),
                    Html::div()->content(admin_trans('shift_handover.auto.time_end') . '：' . $data['end_time'])
                ]);
            })->width(200);

            $grid->column('status', admin_trans('shift_handover.auto.status'))->display(function ($value) {
                return match ($value) {
                    StoreAutoShiftLog::STATUS_SUCCESS => Tag::create(admin_trans('shift_handover.auto.status_success'))->color('success'),
                    StoreAutoShiftLog::STATUS_FAILED => Tag::create(admin_trans('shift_handover.auto.status_failed'))->color('error'),
                    StoreAutoShiftLog::STATUS_PARTIAL_SUCCESS => Tag::create(admin_trans('shift_handover.auto.status_partial'))->color('warning'),
                    default => Tag::create(admin_trans('shift_handover.auto.status_unknown'))->color('default'),
                };
            })->width(100)->align('center');

            $grid->column('machine_point', admin_trans('shift_handover.auto.machine_point'))->width(100)->align('center');
            $grid->column('total_in', admin_trans('shift_handover.auto.total_in'))->width(100)->align('center');
            $grid->column('total_out', admin_trans('shift_handover.auto.total_out'))->width(100)->align('center');
            $grid->column('lottery_amount', admin_trans('shift_handover.auto.lottery_amount'))->width(100)->align('center');
            $grid->column('total_profit', admin_trans('shift_handover.auto.total_profit'))->width(100)->align('center')
                ->display(function ($value) {
                    $color = $value >= 0 ? '#3f8600' : '#cf1322';
                    return Html::create('<span style="color: ' . $color . '">' . $value . '</span>');
                });

            $grid->column('execution_duration', admin_trans('shift_handover.auto.execution_duration'))->width(100)->align('center')
                ->display(function ($value) {
                    return round($value / 1000, 2) . 's';
                });

            $grid->column('error_message', admin_trans('shift_handover.auto.error_message'))->ellipsis(true)->width(200);

            // 行展开 - 显示详细信息
            $grid->expandRow(function ($row) {
                return Card::create([
                    Html::create('<div style="padding: 20px;">
                        <h4>' . admin_trans('shift_handover.auto.detail_title') . '</h4>
                        <table style="width: 100%; margin-top: 10px;">
                            <tr>
                                <td style="padding: 8px; width: 150px; font-weight: bold;">' . admin_trans('shift_handover.auto.config_id') . '：</td>
                                <td style="padding: 8px;">' . $row['config_id'] . '</td>
                                <td style="padding: 8px; width: 150px; font-weight: bold;">' . admin_trans('shift_handover.auto.shift_record_id') . '：</td>
                                <td style="padding: 8px;">' . ($row['shift_record_id'] ?? '-') . '</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; font-weight: bold;">' . admin_trans('shift_handover.auto.time_range_start') . '：</td>
                                <td style="padding: 8px;">' . $row['start_time'] . '</td>
                                <td style="padding: 8px; font-weight: bold;">' . admin_trans('shift_handover.auto.time_range_end') . '：</td>
                                <td style="padding: 8px;">' . $row['end_time'] . '</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; font-weight: bold;">' . admin_trans('shift_handover.auto.machine_amount') . '：</td>
                                <td style="padding: 8px;">' . $row['machine_amount'] . '</td>
                                <td style="padding: 8px; font-weight: bold;">' . admin_trans('shift_handover.auto.machine_point_detail') . '：</td>
                                <td style="padding: 8px;">' . $row['machine_point'] . '</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; font-weight: bold;">' . admin_trans('shift_handover.auto.total_in_detail') . '：</td>
                                <td style="padding: 8px;" style="color: #3f8600;">' . $row['total_in'] . '</td>
                                <td style="padding: 8px; font-weight: bold;">' . admin_trans('shift_handover.auto.total_out_detail') . '：</td>
                                <td style="padding: 8px;" style="color: #cf1322;">' . $row['total_out'] . '</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; font-weight: bold;">' . admin_trans('shift_handover.auto.lottery_amount_detail') . '：</td>
                                <td style="padding: 8px;">' . $row['lottery_amount'] . '</td>
                                <td style="padding: 8px; font-weight: bold;">' . admin_trans('shift_handover.auto.total_profit_detail') . '：</td>
                                <td style="padding: 8px; color: ' . ($row['total_profit'] >= 0 ? '#3f8600' : '#cf1322') . ';">' . $row['total_profit'] . '</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px; font-weight: bold;">' . admin_trans('shift_handover.auto.execute_time_detail') . '：</td>
                                <td style="padding: 8px;">' . $row['execute_time'] . '</td>
                                <td style="padding: 8px; font-weight: bold;">' . admin_trans('shift_handover.auto.execution_duration_detail') . '：</td>
                                <td style="padding: 8px;">' . round($row['execution_duration'] / 1000, 2) . ' ' . admin_trans('shift_handover.auto.seconds') . '</td>
                            </tr>
                            ' . ($row['error_message'] ? '
                            <tr>
                                <td style="padding: 8px; font-weight: bold; vertical-align: top;">' . admin_trans('shift_handover.auto.error_message_detail') . '：</td>
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
                    ->placeholder(admin_trans('shift_handover.auto.filter_status'))
                    ->options([
                        StoreAutoShiftLog::STATUS_SUCCESS => admin_trans('shift_handover.auto.status_success'),
                        StoreAutoShiftLog::STATUS_FAILED => admin_trans('shift_handover.auto.status_failed'),
                        StoreAutoShiftLog::STATUS_PARTIAL_SUCCESS => admin_trans('shift_handover.auto.status_partial')
                    ]);

                $filter->form()->hidden('start_date');
                $filter->form()->hidden('end_date');
                $filter->form()->dateRange('start_date', 'end_date', admin_trans('shift_handover.auto.filter_execute_time'))
                    ->placeholder([admin_trans('shift_handover.auto.filter_date_start'), admin_trans('shift_handover.auto.filter_date_end')]);
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
            return json(['code' => 1, 'msg' => admin_trans('shift_handover.auto.log_not_found')]);
        }

        // 关联数据
        $log->load(['config', 'shiftRecord']);

        return json([
            'code' => 0,
            'data' => $log
        ]);
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
            return json(['code' => 1, 'msg' => admin_trans('shift_handover.auto.config_not_found')]);
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
