<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\StoreAgentShiftHandoverRecord;
use addons\webman\model\StoreShiftDeviceDetail;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\Row;

/**
 * 店家后台 - 交班记录
 * @group store
 */
class StoreShiftHandoverRecordController
{
    /**
     * 交班记录列表
     * @group store
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new StoreAgentShiftHandoverRecord(), function (Grid $grid) {
            $grid->title('交班记录');
            $grid->autoHeight();
            $grid->bordered(true);

            /** @var AdminUser $admin */
            $admin = Admin::user();

            // 数据权限：只能看到自己的交班记录
            $grid->model()
                ->where('bind_admin_user_id', $admin->id)
                ->orderBy('id', 'desc');

            $grid->column('id', 'ID')->width(80)->align('center');

            $grid->column('time_range', '交班时间')->display(function ($val, $data) {
                return Html::create()->content([
                    Html::div()->content('开始：' . $data['start_time']),
                    Html::div()->content('结束：' . $data['end_time'])
                ]);
            })->width(200);

            $grid->column('is_auto_shift', '交班类型')->display(function ($value) {
                return $value == 1
                    ? Tag::create('自动交班')->color('blue')
                    : Tag::create('手动交班')->color('default');
            })->width(100)->align('center');

            $grid->column('machine_point', '投钞点数')->width(100)->align('center');
            $grid->column('total_in', '总收入')->width(100)->align('center');
            $grid->column('total_out', '总支出')->width(100)->align('center');
            $grid->column('lottery_amount', '彩金')->width(100)->align('center');
            $grid->column('total_profit_amount', '总利润')->width(100)->align('center')
                ->display(function ($value) {
                    $color = $value >= 0 ? '#3f8600' : '#cf1322';
                    return Html::create($value)->style(['color' => $color]);
                });

            $grid->column('created_at', '创建时间')->width(180)->align('center');

            // 操作列 - 查看设备明细
            $grid->column('device_detail', '操作')->display(function ($val, $data) {
                return Button::create('查看明细')
                    ->type('primary')
                    ->size('small')
                    ->modal(
                        admin_url([
                            'addons-webman-controller-StoreShiftHandoverRecordController',
                            'deviceDetails'
                        ]),
                        ['shift_record_id' => $data['id']]
                    )->width('80%');
            })->width(120)->align('center');

            // 行展开 - 显示详细信息
            $grid->expandRow(function ($row) {
                $profitColor = $row['total_profit_amount'] >= 0 ? '#3f8600' : '#cf1322';

                return Card::create([
                    Html::div()->content([
                        Html::create('交班详情')->tag('h4')->style(['marginBottom' => '10px']),
                        Html::create()->content([
                            // 第1行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create('交班类型：')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create($row['is_auto_shift'] ? '自动交班' : '手动交班')
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create('日志ID：')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create($row['auto_shift_log_id'] ?? '-')
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第2行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create('开始时间：')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create($row['start_time'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create('结束时间：')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create($row['end_time'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第3行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create('投钞金额：')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create($row['machine_amount'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create('投钞点数：')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create($row['machine_point'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第4行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create('总收入：')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create($row['total_in'])->style(['color' => '#3f8600'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create('总支出：')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create($row['total_out'])->style(['color' => '#cf1322'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第5行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create('总利润：')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create($row['total_profit_amount'])->style(['color' => $profitColor])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create('创建时间：')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create($row['created_at'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ])
                        ])
                    ])->style(['padding' => '20px'])
                ]);
            });

            // 筛选
            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('is_auto_shift')
                    ->placeholder('交班类型')
                    ->options([
                        1 => '自动交班',
                        0 => '手动交班'
                    ]);

                $filter->form()->hidden('start_date');
                $filter->form()->hidden('end_date');
                $filter->form()->dateRange('start_date', 'end_date', '时间范围')
                    ->placeholder(['开始时间', '结束时间']);
            });

            // 操作列
            $grid->actions(function (Actions $actions, $data) {
                $actions->hideEdit();
                $actions->hideDel();

                // 添加单独导出按钮
                $actions->prepend(
                    Button::create('导出')
                        ->type('primary')
                        ->size('small')
                        ->ajax([$this, 'exportSingleDownload'], ['id' => $data['id']])
                );
            });

            $grid->hideDelete();
            $grid->hideCreate();
            $grid->expandFilter();

            // 使用自定义导出驱动
            $grid->export(new \addons\webman\grid\ShiftReportExporter())
                ->filename('shift_report_' . date('YmdHis'));
        });
    }

    /**
     * 设备明细列表
     * @auth true
     * @group store
     */
    public function deviceDetails(int $shift_record_id): Grid
    {
        $shiftRecordId = $shift_record_id;

        return Grid::create(new StoreShiftDeviceDetail(), function (Grid $grid) use ($shiftRecordId) {
            // 获取交班记录信息
            $shiftRecord = StoreAgentShiftHandoverRecord::find($shiftRecordId);

            if (!$shiftRecord) {
                $grid->title('设备明细 - 记录不存在');
                return;
            }

            $grid->title('设备明细 - ' . $shiftRecord->start_time . ' ~ ' . $shiftRecord->end_time);
            $grid->autoHeight();
            $grid->bordered(true);

            // 查询该交班记录的设备明细
            $grid->model()
                ->where('shift_record_id', $shiftRecordId)
                ->orderBy('profit', 'desc');

            $grid->column('id', 'ID')->width(80)->align('center');

            $grid->column('player_name', '设备名称')->width(150);

            $grid->column('player_phone', '设备编号')->width(120)->align('center');

            $grid->column('machine_point', '投钞点数')->width(100)->align('center');

            $grid->column('recharge_amount', '开分')->width(100)->align('center')
                ->display(function ($value) {
                    return number_format($value, 2);
                });

            $grid->column('withdrawal_amount', '洗分')->width(100)->align('center')
                ->display(function ($value) {
                    return number_format($value, 2);
                });

            $grid->column('modified_add_amount', '后台加点')->width(100)->align('center')
                ->display(function ($value) {
                    return number_format($value, 2);
                });

            $grid->column('modified_deduct_amount', '后台扣点')->width(100)->align('center')
                ->display(function ($value) {
                    return number_format($value, 2);
                });

            $grid->column('lottery_amount', '彩金')->width(100)->align('center')
                ->display(function ($value) {
                    return number_format($value, 2);
                });

            $grid->column('total_in', '总收入')->width(100)->align('center')
                ->display(function ($value) {
                    $color = '#3f8600';
                    return Html::create(number_format($value, 2))->style(['color' => $color]);
                });

            $grid->column('total_out', '总支出')->width(100)->align('center')
                ->display(function ($value) {
                    $color = '#cf1322';
                    return Html::create(number_format($value, 2))->style(['color' => $color]);
                });

            $grid->column('profit', '利润')->width(120)->align('center')
                ->display(function ($value) {
                    $color = $value >= 0 ? '#3f8600' : '#cf1322';
                    $formatted = number_format($value, 2);
                    return Html::create($formatted)->style(['color' => $color, 'fontWeight' => 'bold']);
                });

            // 统计卡片
            $grid->header(function () use ($shiftRecord) {
                $deviceCount = StoreShiftDeviceDetail::where('shift_record_id', $shiftRecord->id)->count();

                return Row::make([
                    Statistic::make('设备数量', $deviceCount)->span(4),
                    Statistic::make('投钞点数', number_format($shiftRecord->machine_point))->span(4),
                    Statistic::make('总收入', number_format($shiftRecord->total_in, 2))
                        ->valueStyle(['color' => '#3f8600'])
                        ->span(4),
                    Statistic::make('总支出', number_format($shiftRecord->total_out, 2))
                        ->valueStyle(['color' => '#cf1322'])
                        ->span(4),
                    Statistic::make('彩金', number_format($shiftRecord->lottery_amount, 2))
                        ->valueStyle(['color' => '#fa8c16'])
                        ->span(4),
                    Statistic::make('总利润', number_format($shiftRecord->total_profit_amount, 2))
                        ->valueStyle(['color' => $shiftRecord->total_profit_amount >= 0 ? '#3f8600' : '#cf1322'])
                        ->span(4),
                ])->gutter(16)->style(['marginBottom' => '16px']);
            });

            // 行展开 - 显示详细分类数据
            $grid->expandRow(function ($row) {
                return Card::create([
                    Html::div()->content([
                        Html::create('设备详细数据')->tag('h4')->style(['marginBottom' => '10px']),
                        Html::create()->content([
                            // 第1行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create('设备名称：')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create($row['player_name'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create('设备编号：')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create($row['player_phone'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第2行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create('投钞点数：')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create($row['machine_point'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create('开分金额：')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create(number_format($row['recharge_amount'], 2))
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第3行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create('洗分金额：')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create(number_format($row['withdrawal_amount'], 2))
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create('后台加点：')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create(number_format($row['modified_add_amount'], 2))
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第4行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create('后台扣点：')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create(number_format($row['modified_deduct_amount'], 2))
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create('彩金发放：')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create(number_format($row['lottery_amount'], 2))
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第5行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create('总收入：')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create(number_format($row['total_in'], 2))->style(['color' => '#3f8600'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create('总支出：')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create(number_format($row['total_out'], 2))->style(['color' => '#cf1322'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第6行 - 利润
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create('设备利润：')->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create(number_format($row['profit'], 2))->style([
                                        'color' => $row['profit'] >= 0 ? '#3f8600' : '#cf1322',
                                        'fontWeight' => 'bold',
                                        'fontSize' => '16px'
                                    ])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '100%'])
                            ])
                        ])
                    ])->style(['padding' => '20px'])
                ]);
            });

            $grid->actions(function (Actions $actions) {
                $actions->hideEdit();
                $actions->hideDel();
            });

            $grid->hideDelete();
            $grid->hideCreate();
            $grid->disableSelection();

            // 添加工具栏导出按钮
            $grid->toolbars(function ($toolbars) use ($shiftRecordId) {
                $toolbars->prepend(
                    Button::create('导出设备明细')
                        ->type('primary')
                        ->icon('DownloadOutlined')
                        ->ajax([$this, 'exportDeviceDetailsDownload'], ['shift_record_id' => $shiftRecordId])
                );
            });
        });
    }

    /**
     * 导出单条交班记录（AJAX返回下载链接）
     * @group store
     * @auth true
     */
    public function exportSingleDownload(int $id)
    {
        try {
            /** @var AdminUser $admin */
            $admin = Admin::user();

            // 验证权限：只能导出自己的交班记录
            $record = StoreAgentShiftHandoverRecord::where('id', $id)
                ->where('bind_admin_user_id', $admin->id)
                ->first();

            if (!$record) {
                return message_error('记录不存在或无权限导出');
            }

            // 生成下载URL
            $downloadUrl = admin_url([
                'addons-webman-controller-StoreShiftHandoverRecordController',
                'exportSingle'
            ], ['id' => $id]);

            // 返回成功通知并打开新窗口下载
            return notification_success('开始导出', '正在生成Excel文件...')->redirect($downloadUrl);

        } catch (\Throwable $e) {
            \support\Log::error('导出交班记录失败: ' . $e->getMessage(), [
                'id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return message_error('导出失败: ' . $e->getMessage());
        }
    }

    /**
     * 导出单条交班记录（实际下载）
     * @group store
     * @auth true
     */
    public function exportSingle(int $id)
    {
        try {
            /** @var AdminUser $admin */
            $admin = Admin::user();

            // 验证权限：只能导出自己的交班记录
            $record = StoreAgentShiftHandoverRecord::where('id', $id)
                ->where('bind_admin_user_id', $admin->id)
                ->first();

            if (!$record) {
                return response('<script>alert("记录不存在或无权限导出");history.back();</script>');
            }

            // 使用独立的单条记录导出器
            $exporter = new \addons\webman\grid\SingleShiftReportExporter($record);

            // 导出到临时目录
            $savePath = runtime_path() . '/excel_export';
            $filePath = $exporter->export($savePath);

            if (!file_exists($filePath)) {
                return response('<script>alert("导出失败，文件生成失败");history.back();</script>');
            }

            // 读取文件内容
            $fileContent = file_get_contents($filePath);
            $filename = basename($filePath);

            // 删除临时文件
            @unlink($filePath);

            // 返回文件供下载
            $response = response($fileContent);
            $response->withHeaders([
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => strlen($fileContent),
                'Cache-Control' => 'max-age=0',
                'Pragma' => 'public'
            ]);

            return $response;

        } catch (\Throwable $e) {
            \support\Log::error('导出交班记录失败: ' . $e->getMessage(), [
                'id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response('<script>alert("导出失败: ' . addslashes($e->getMessage()) . '");history.back();</script>');
        }
    }

    /**
     * 导出设备明细（AJAX返回下载链接）
     * @group store
     * @auth true
     */
    public function exportDeviceDetailsDownload(int $shift_record_id)
    {
        try {
            /** @var AdminUser $admin */
            $admin = Admin::user();

            // 验证权限：只能导出自己的交班记录的设备明细
            $record = StoreAgentShiftHandoverRecord::where('id', $shift_record_id)
                ->where('bind_admin_user_id', $admin->id)
                ->first();

            if (!$record) {
                return message_error('记录不存在或无权限导出');
            }

            // 生成下载URL
            $downloadUrl = admin_url([
                'addons-webman-controller-StoreShiftHandoverRecordController',
                'exportDeviceDetails'
            ], ['shift_record_id' => $shift_record_id]);

            // 返回成功通知并打开新窗口下载
            return notification_success('开始导出', '正在生成设备明细Excel文件...')->redirect($downloadUrl);

        } catch (\Throwable $e) {
            \support\Log::error('导出设备明细失败: ' . $e->getMessage(), [
                'shift_record_id' => $shift_record_id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return message_error('导出失败: ' . $e->getMessage());
        }
    }

    /**
     * 导出设备明细（实际下载）
     * @group store
     * @auth true
     */
    public function exportDeviceDetails(int $shift_record_id)
    {
        try {
            /** @var AdminUser $admin */
            $admin = Admin::user();

            // 验证权限：只能导出自己的交班记录的设备明细
            $record = StoreAgentShiftHandoverRecord::where('id', $shift_record_id)
                ->where('bind_admin_user_id', $admin->id)
                ->first();

            if (!$record) {
                return response('<script>alert("记录不存在或无权限导出");history.back();</script>');
            }

            // 使用独立的单条记录导出器（已包含设备明细）
            $exporter = new \addons\webman\grid\SingleShiftReportExporter($record);

            // 导出到临时目录
            $savePath = runtime_path() . '/excel_export';
            $filePath = $exporter->export($savePath);

            if (!file_exists($filePath)) {
                return response('<script>alert("导出失败，文件生成失败");history.back();</script>');
            }

            // 读取文件内容
            $fileContent = file_get_contents($filePath);
            $filename = 'device_details_' . $shift_record_id . '_' . date('YmdHis') . '.xlsx';

            // 删除临时文件
            @unlink($filePath);

            // 返回文件供下载
            $response = response($fileContent);
            $response->withHeaders([
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => strlen($fileContent),
                'Cache-Control' => 'max-age=0',
                'Pragma' => 'public'
            ]);

            return $response;

        } catch (\Throwable $e) {
            \support\Log::error('导出设备明细失败: ' . $e->getMessage(), [
                'shift_record_id' => $shift_record_id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response('<script>alert("导出失败: ' . addslashes($e->getMessage()) . '");history.back();</script>');
        }
    }

}
