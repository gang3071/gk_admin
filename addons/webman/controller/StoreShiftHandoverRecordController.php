<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\grid\ShiftReportExporter;
use addons\webman\model\AdminUser;
use addons\webman\model\StoreAgentShiftHandoverRecord;
use addons\webman\model\StoreShiftDeviceDetail;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\response\Response;
use support\Cache;
use support\Log;

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
        // 导出口径的设备明细汇总缓存：shift_record_id => Σ store_shift_device_detail
        // 按需惰性查询并缓存，同一次请求内列表格、展开行共用，每条交班只查一次
        $aggCache = [];

        return Grid::create(new StoreAgentShiftHandoverRecord(), function (Grid $grid) use (&$aggCache) {
            $grid->title(admin_trans('shift_handover.record.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            /** @var AdminUser $admin */
            $admin = Admin::user();

            // 数据权限：只能看到自己的交班记录
            $grid->model()
                ->where('bind_admin_user_id', $admin->id)
                ->orderBy('id', 'desc');

            $grid->column('id', 'ID')->width(80)->align('center');

            $grid->column('time_range', admin_trans('shift_handover.shift_time'))->display(function ($val, $data) {
                return Html::create()->content([
                    Html::div()->content(admin_trans('shift_handover.label.start') . $data['start_time']),
                    Html::div()->content(admin_trans('shift_handover.label.end') . $data['end_time'])
                ]);
            })->width(200);

            $grid->column('is_auto_shift', admin_trans('shift_handover.record.shift_type'))->display(function ($value) {
                return $value == 1
                    ? Tag::create(admin_trans('shift_handover.record.auto_shift'))->color('blue')
                    : Tag::create(admin_trans('shift_handover.record.manual_shift'))->color('default');
            })->width(100)->align('center');

            $grid->column('machine_point', admin_trans('shift_handover.record.machine_point'))->width(100)->align('center');
            $grid->column('total_in', admin_trans('shift_handover.record.total_in'))->width(100)->align('center')
                ->display(function ($value, $data) use (&$aggCache) {
                    return self::alignedStats($data, $aggCache)['total_in'];
                });
            $grid->column('total_out', admin_trans('shift_handover.record.total_out'))->width(100)->align('center')
                ->display(function ($value, $data) use (&$aggCache) {
                    return self::alignedStats($data, $aggCache)['total_out'];
                });
            $grid->column('lottery_amount', admin_trans('shift_handover.lottery_amount'))->width(100)->align('center');
            $grid->column('activity_bonus_amount', admin_trans('shift_handover.auto.activity_bonus_amount'))->width(100)->align('center')
                ->display(function ($value, $data) use (&$aggCache) {
                    // 自动交班不写主表 activity_bonus_amount，只能取设备明细汇总
                    return self::alignedStats($data, $aggCache)['activity_bonus_amount'];
                });
            $grid->column('lottery_ticket_reward_amount', admin_trans('shift_handover.auto.lottery_ticket_reward_amount'))->width(120)->align('center');
            $grid->column('birthday_bonus_amount', admin_trans('shift_handover.record.birthday_bonus_amount'))->width(120)->align('center');
            $grid->column('upgrade_bonus_amount', admin_trans('shift_handover.record.upgrade_bonus_amount'))->width(120)->align('center');
            $grid->column('electronic_game_bet_amount', admin_trans('shift_handover.record.electronic_game_bet_amount'))->width(120)->align('center');
            $grid->column('machine_bet_amount', admin_trans('shift_handover.record.machine_bet_amount'))->width(120)->align('center');
            // 与导出「开票」列同口径：Σ(store_shift_device_detail.ticket_open_score_amount) + 柜台开票
            $grid->column('ticket_record_total_score', admin_trans('shift_handover.record.ticket_record_total_score'))->width(120)->align('center')
                ->display(function ($value, $data) use (&$aggCache) {
                    return self::alignedStats($data, $aggCache)['ticket_open_score_amount'];
                });
            // 与导出「核销」列同口径：Σ(store_shift_device_detail.redeem_amount) + 柜台核销
            $grid->column('ticket_redeem_backend_used_score', admin_trans('shift_handover.record.ticket_redeem_backend_used_score'))->width(120)->align('center')
                ->display(function ($value, $data) use (&$aggCache) {
                    return self::alignedStats($data, $aggCache)['redeem_amount'];
                });
            // 未核销：与导出「未核销」列同口径（逐台 max(·,0) 后累加）
            $grid->column('ticket_unredeemed_amount', admin_trans('shift_handover.ticket_unredeemed_amount'))->width(100)->align('center')
                ->display(function ($value, $data) use (&$aggCache) {
                    $unredeemed = self::alignedStats($data, $aggCache)['ticket_unredeemed_amount'];
                    $color = $unredeemed >= 0 ? '#3f8600' : '#cf1322';
                    return Html::create(number_format($unredeemed, 2))->style(['color' => $color]);
                });
            $grid->column('total_profit_amount', admin_trans('shift_handover.record.total_profit'))->width(100)->align('center')
                ->display(function ($value, $data) use (&$aggCache) {
                    $profit = self::alignedStats($data, $aggCache)['profit'];
                    $color = $profit >= 0 ? '#3f8600' : '#cf1322';
                    return Html::create(number_format($profit, 2))->style(['color' => $color]);
                });

            $grid->column('created_at', admin_trans('shift_handover.record.created_at'))->width(180)->align('center');

            // 操作列 - 查看设备明细
            $grid->column('device_detail', admin_trans('shift_handover.action.operation'))->display(function ($val, $data) {
                return Button::create(admin_trans('shift_handover.action.view_detail'))
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
            $grid->expandRow(function ($row) use (&$aggCache) {
                $stats = self::alignedStats($row, $aggCache);
                $profitColor = $stats['profit'] >= 0 ? '#3f8600' : '#cf1322';

                return Card::create([
                    Html::div()->content([
                        Html::create(admin_trans('shift_handover.record.detail_title'))->tag('h4')->style(['marginBottom' => '10px']),
                        Html::create()->content([
                            // 第1行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.label.shift_type'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create($row['is_auto_shift'] ? admin_trans('shift_handover.record.auto_shift') : admin_trans('shift_handover.record.manual_shift'))
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.label.log_id'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create($row['auto_shift_log_id'] ?? '-')
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第2行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.label.start'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create($row['start_time'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.label.end'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create($row['end_time'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第3行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.label.machine_amount'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create($row['machine_amount'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.label.machine_point'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create($row['machine_point'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第4行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.label.total_in'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create($stats['total_in'])->style(['color' => '#3f8600'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.label.total_out'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create($stats['total_out'])->style(['color' => '#cf1322'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第5行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.label.electronic_game_bet_amount'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create(number_format($row['electronic_game_bet_amount'], 2))
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.label.machine_bet_amount'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create(number_format($row['machine_bet_amount'], 2))
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第6行 - 出票核销
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.label.ticket_record_total_score'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create(number_format($stats['ticket_open_score_amount'], 2))->style(['color' => '#3f8600'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.label.ticket_redeem_backend_used_score'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create(number_format($stats['redeem_amount'], 2))->style(['color' => '#cf1322'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第7行 - 礼金
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.label.birthday_bonus_amount'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create(number_format($row['birthday_bonus_amount'] ?? 0, 2))->style(['color' => '#eb2f96'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.label.upgrade_bonus_amount'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create(number_format($row['upgrade_bonus_amount'] ?? 0, 2))->style(['color' => '#722ed1'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第8行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.label.ticket_unredeemed_amount'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create(number_format($stats['ticket_unredeemed_amount'], 2))->style(['color' => $stats['ticket_unredeemed_amount'] >= 0 ? '#3f8600' : '#cf1322'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.label.total_profit'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create(number_format($stats['profit'], 2))->style(['color' => $profitColor])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第8行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.label.created_at'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '150px']),
                                    Html::create($row['created_at'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '100%'])
                            ])
                        ])
                    ])->style(['padding' => '20px'])
                ]);
            });

            // 筛选
            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('is_auto_shift')
                    ->placeholder(admin_trans('shift_handover.record.filter_shift_type'))
                    ->options([
                        1 => admin_trans('shift_handover.record.auto_shift'),
                        0 => admin_trans('shift_handover.record.manual_shift')
                    ]);

                $filter->form()->hidden('start_date');
                $filter->form()->hidden('end_date');
                $filter->form()->dateRange('start_date', 'end_date', admin_trans('shift_handover.filter.time_range'))
                    ->placeholder([admin_trans('shift_handover.filter.start_time'), admin_trans('shift_handover.filter.end_time')]);
            });

            // 工具栏 - 选择导出栏位按钮（暂时隐藏）
            // $grid->tools([
            //     Button::create(admin_trans('shift_handover.export.select_columns'))
            //         ->type('default')
            //         ->modal(
            //             admin_url([
            //                 'addons-webman-controller-StoreShiftHandoverRecordController',
            //                 'exportConfig'
            //             ])
            //         )
            //         ->width('50%')
            // ]);

            // 操作列
            $grid->actions(function (Actions $actions) {
                $actions->hideEdit();
                $actions->hideDel();
            });

            $grid->hideDelete();
            $grid->expandFilter(false);
            $grid->hideDeleteSelection();

            // 导出功能（权限通过 store_node.php 和 @auth true 控制）
            $exporter = new ShiftReportExporter();

            // 暂时隐藏缓存逻辑，固定使用默认导出栏位
            // $adminId = Admin::id();
            // $cacheKey = "export_columns_{$adminId}";
            $defaultColumns = [
                'player_name',
                'player_phone',
                'open_score_amount',
                'ticket_open_score_amount',
                'redeem_amount',
                'experience_coupon_amount',
                'welfare_coupon_amount',
                'electronic_game_bet_amount',
                'machine_bet_amount',
                'total_in',
                'total_out',
                'profit',
                'ticket_unredeemed_amount',
                'activity_give_amount',
            ];
            // $selectedColumns = Cache::get($cacheKey, $defaultColumns);
            //
            // Log::info("index 读取导出列缓存", [
            //     'admin_id' => $adminId,
            //     'cache_key' => $cacheKey,
            //     'selected_columns' => $selectedColumns,
            //     'has_cache' => Cache::has($cacheKey),
            // ]);

            $exporter->setSelectedColumns($defaultColumns);

            $grid->export($exporter)
                ->filename('shift_report_' . date('YmdHis'));
        });
    }

    /**
     * 导出交班记录
     * @group store
     * @auth true
     * @return void
     */
    public function export()
    {
        // 此方法仅用于权限控制，实际导出由 Grid 的 export 功能处理
    }

    /**
     * 导出配置 - 选择导出列
     * @group store
     * @auth true
     */
    public function exportConfig(): Form
    {
        return Form::create([], function (Form $form) {
            $form->title(admin_trans('shift_handover.export.select_columns'));

            // 获取所有可导出的列
            $exporter = new ShiftReportExporter();
            $columns = $exporter->getAvailableColumns();

            // 获取用户之前的选择（默认指定列）
            $adminId = Admin::id();
            $cacheKey = "export_columns_{$adminId}";
            $defaultColumns = [
                'player_name',
                'player_phone',
                'open_score_amount',
                'ticket_open_score_amount',
                'redeem_amount',
                'experience_coupon_amount',
                'welfare_coupon_amount',
                'electronic_game_bet_amount',
                'machine_bet_amount',
                'total_in',
                'total_out',
                'profit',
                'ticket_unredeemed_amount',
                'activity_give_amount',
            ];
            $selectedColumns = Cache::get($cacheKey, $defaultColumns);

            // 复选框组选择列
            $form->checkbox('columns', admin_trans('shift_handover.export.columns'))
                ->options($columns)
                ->default($selectedColumns)
                ->required()
                ->help(admin_trans('shift_handover.export.select_columns_help'));

            $form->saving(function (Form $form) use ($cacheKey) {
                $input = $form->input();
                $selectedColumns = $input['columns'] ?? [];


                if (empty($selectedColumns)) {
                    return message_error(admin_trans('shift_handover.export.no_column_selected'));
                }

                // 保存到缓存（永久）
                Cache::set($cacheKey, $selectedColumns);

                return message_success(admin_trans('shift_handover.export.config_saved'));
            });
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
                $grid->title(admin_trans('shift_handover.device.detail_not_found'));
                return;
            }

            $grid->title(admin_trans('shift_handover.device.detail_title') . ' - ' . $shiftRecord->start_time . ' ~ ' . $shiftRecord->end_time);
            $grid->autoHeight();
            $grid->bordered(true);

            // 查询该交班记录的设备明细
            $grid->model()
                ->where('shift_record_id', $shiftRecordId)
                ->orderBy('profit', 'desc');

            $grid->column('id', 'ID')->width(80)->align('center');

            $grid->column('player_name', admin_trans('shift_handover.device_name'))->width(150);

            $grid->column('player_phone', admin_trans('shift_handover.device_number'))->width(120)->align('center');

            $grid->column('machine_point', admin_trans('shift_handover.machine_point'))->width(100)->align('center');

            $grid->column('recharge_amount', admin_trans('shift_handover.recharge_amount'))->width(100)->align('center')
                ->display(function ($value) {
                    return number_format($value, 2);
                });

            $grid->column('withdrawal_amount', admin_trans('shift_handover.withdrawal_amount'))->width(100)->align('center')
                ->display(function ($value) {
                    return number_format($value, 2);
                });

            $grid->column('modified_add_amount', admin_trans('shift_handover.modified_add_amount'))->width(100)->align('center')
                ->display(function ($value) {
                    return number_format($value, 2);
                });

            $grid->column('modified_deduct_amount', admin_trans('shift_handover.modified_deduct_amount'))->width(100)->align('center')
                ->display(function ($value) {
                    return number_format($value, 2);
                });

            $grid->column('lottery_amount', admin_trans('shift_handover.lottery_amount'))->width(100)->align('center')
                ->display(function ($value) {
                    return number_format($value, 2);
                });

            $grid->column('activity_bonus_amount', admin_trans('shift_handover.auto.activity_bonus_amount_detail'))->width(100)->align('center')
                ->display(function ($value) {
                    return number_format($value, 2);
                });

            $grid->column('lottery_ticket_reward_amount', admin_trans('shift_handover.auto.lottery_ticket_reward_amount_detail'))->width(120)->align('center')
                ->display(function ($value) {
                    return number_format($value, 2);
                });

            $grid->column('birthday_bonus_amount', admin_trans('shift_handover.record.birthday_bonus_amount'))->width(120)->align('center')
                ->display(function ($value) {
                    return number_format($value ?? 0, 2);
                });

            $grid->column('upgrade_bonus_amount', admin_trans('shift_handover.record.upgrade_bonus_amount'))->width(120)->align('center')
                ->display(function ($value) {
                    return number_format($value ?? 0, 2);
                });

            $grid->column('electronic_game_bet_amount', admin_trans('shift_handover.device.electronic_game_bet_amount'))->width(120)->align('center')
                ->display(function ($value) {
                    return number_format($value, 2);
                });

            $grid->column('machine_bet_amount', admin_trans('shift_handover.device.machine_bet_amount'))->width(120)->align('center')
                ->display(function ($value) {
                    return number_format($value, 2);
                });

            $grid->column('total_in', admin_trans('shift_handover.total_in'))->width(100)->align('center')
                ->display(function ($value) {
                    $color = '#3f8600';
                    return Html::create(number_format($value, 2))->style(['color' => $color]);
                });

            $grid->column('total_out', admin_trans('shift_handover.total_out'))->width(100)->align('center')
                ->display(function ($value) {
                    $color = '#cf1322';
                    return Html::create(number_format($value, 2))->style(['color' => $color]);
                });

            $grid->column('profit', admin_trans('shift_handover.profit'))->width(120)->align('center')
                ->display(function ($value) {
                    $color = $value >= 0 ? '#3f8600' : '#cf1322';
                    $formatted = number_format($value, 2);
                    return Html::create($formatted)->style(['color' => $color, 'fontWeight' => 'bold']);
                });

            // 统计卡片
            $grid->header(function () use ($shiftRecord) {
                $deviceCount = StoreShiftDeviceDetail::where('shift_record_id', $shiftRecord->id)->count();

                return Row::make([
                    Statistic::make(admin_trans('shift_handover.device.device_count'), $deviceCount)->span(4),
                    Statistic::make(admin_trans('shift_handover.machine_point'), number_format($shiftRecord->machine_point))->span(4),
                    Statistic::make(admin_trans('shift_handover.total_in'), number_format($shiftRecord->total_in, 2))
                        ->valueStyle(['color' => '#3f8600'])
                        ->span(4),
                    Statistic::make(admin_trans('shift_handover.total_out'), number_format($shiftRecord->total_out, 2))
                        ->valueStyle(['color' => '#cf1322'])
                        ->span(4),
                    Statistic::make(admin_trans('shift_handover.lottery_amount'), number_format($shiftRecord->lottery_amount, 2))
                        ->valueStyle(['color' => '#fa8c16'])
                        ->span(4),
                    Statistic::make(admin_trans('shift_handover.profit'), number_format($shiftRecord->total_profit_amount, 2))
                        ->valueStyle(['color' => $shiftRecord->total_profit_amount >= 0 ? '#3f8600' : '#cf1322'])
                        ->span(4),
                ])->gutter(16)->style(['marginBottom' => '16px']);
            });

            // 行展开 - 显示详细分类数据
            $grid->expandRow(function ($row) {
                return Card::create([
                    Html::div()->content([
                        Html::create(admin_trans('shift_handover.device.detail_data'))->tag('h4')->style(['marginBottom' => '10px']),
                        Html::create()->content([
                            // 第1行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.device.label.device_name'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create($row['player_name'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.device.label.device_number'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create($row['player_phone'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第2行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.device.label.machine_point'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create($row['machine_point'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.device.label.recharge_amount'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create(number_format($row['recharge_amount'], 2))
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第3行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.device.label.withdrawal_amount'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create(number_format($row['withdrawal_amount'], 2))
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.device.label.backend_add_amount'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create(number_format($row['modified_add_amount'], 2))
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第4行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.device.label.backend_deduct_amount'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create(number_format($row['modified_deduct_amount'], 2))
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.device.label.lottery_amount'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create(number_format($row['lottery_amount'], 2))
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第5行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.device.label.total_in'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create(number_format($row['total_in'], 2))->style(['color' => '#3f8600'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.device.label.total_out'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create(number_format($row['total_out'], 2))->style(['color' => '#cf1322'])
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第6行
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.device.label.electronic_game_bet_amount'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create(number_format($row['electronic_game_bet_amount'], 2))
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%']),
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.device.label.machine_bet_amount'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
                                    Html::create(number_format($row['machine_bet_amount'], 2))
                                ])->style(['padding' => '8px', 'display' => 'inline-block', 'width' => '50%'])
                            ]),
                            // 第7行 - 利润
                            Html::div()->content([
                                Html::create()->content([
                                    Html::create(admin_trans('shift_handover.device.label.device_profit'))->style(['fontWeight' => 'bold', 'display' => 'inline-block', 'width' => '120px']),
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
            $grid->hideSelection();
            $grid->hideDelete();
        });
    }

    /**
     * 导出口径的交班汇总展示值
     *
     * 交班导出的小计行 = Σ(store_shift_device_detail) + 柜台行
     * （见 ShiftReportExporter::write() 的柜台行 / 小计行）：
     *   柜台行把 counter_ticket_amount 计入「开票」和「总收入」，
     *   把 counter_redeem_amount 计入「核销」和「总支出」，
     *   利润 = 总收入 - 总支出。
     *
     * 页面取值与导出逐列同源同公式；没有设备明细行的旧记录回落到主表快照字段。
     *
     * @param array|object $row 交班记录行（Grid display/expandRow 传入的模型或数组）
     * @param array $cache 同一次请求内的汇总缓存，key = shift_record_id
     * @return array{total_in: float, total_out: float, profit: float,
     *              ticket_open_score_amount: float, redeem_amount: float,
     *              ticket_unredeemed_amount: float, activity_bonus_amount: float}
     */
    private static function alignedStats($row, array &$cache): array
    {
        $data = is_object($row) && method_exists($row, 'getAttributes')
            ? $row->getAttributes()
            : (array)$row;

        $id = (int)($data['id'] ?? 0);
        if (!array_key_exists($id, $cache)) {
            $cache[$id] = self::detailAggregates($id);
        }
        $agg = $cache[$id];

        if ($agg === null) {
            // 没有设备明细行：主表快照本身已含柜台金额
            $totalIn = round((float)($data['total_in'] ?? 0), 2);
            $totalOut = round((float)($data['total_out'] ?? 0), 2);
            return [
                'total_in' => $totalIn,
                'total_out' => $totalOut,
                'profit' => round($totalIn - $totalOut, 2),
                'ticket_open_score_amount' => round((float)($data['ticket_open_score_amount'] ?? 0), 2),
                'redeem_amount' => round((float)($data['redeem_amount'] ?? 0), 2),
                'ticket_unredeemed_amount' => round((float)($data['ticket_unredeemed_amount'] ?? 0), 2),
                'activity_bonus_amount' => round((float)($data['activity_bonus_amount'] ?? 0), 2),
            ];
        }

        $counterTicket = round((float)($data['counter_ticket_amount'] ?? 0), 2);
        $counterRedeem = round((float)($data['counter_redeem_amount'] ?? 0), 2);
        $totalIn = round($agg['total_in'] + $counterTicket, 2);
        $totalOut = round($agg['total_out'] + $counterRedeem, 2);

        return [
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'profit' => round($totalIn - $totalOut, 2),
            'ticket_open_score_amount' => round($agg['ticket_open'] + $counterTicket, 2),
            'redeem_amount' => round($agg['redeem'] + $counterRedeem, 2),
            // 导出对未核销逐台取 max(·, 0) 再累加（ShiftReportExporter::getColumnValue），柜台行不参与该列
            'ticket_unredeemed_amount' => round($agg['unredeemed'], 2),
            'activity_bonus_amount' => round($agg['activity_bonus'], 2),
        ];
    }

    /**
     * 汇总一条交班的 store_shift_device_detail（与导出同源）
     *
     * @param int $shiftRecordId 交班记录ID
     * @return array|null null 表示该交班没有设备明细行
     */
    private static function detailAggregates(int $shiftRecordId): ?array
    {
        $row = StoreShiftDeviceDetail::query()
            ->where('shift_record_id', $shiftRecordId)
            ->selectRaw('
                COUNT(*) AS agg_count,
                IFNULL(SUM(total_in), 0) AS agg_total_in,
                IFNULL(SUM(total_out), 0) AS agg_total_out,
                IFNULL(SUM(ticket_open_score_amount), 0) AS agg_ticket_open,
                IFNULL(SUM(redeem_amount), 0) AS agg_redeem,
                IFNULL(SUM(GREATEST(IFNULL(ticket_unredeemed_amount, 0), 0)), 0) AS agg_unredeemed,
                IFNULL(SUM(activity_bonus_amount), 0) AS agg_activity_bonus
            ')
            ->toBase()
            ->first();

        if (!$row || (int)$row->agg_count === 0) {
            return null;
        }

        return [
            'total_in' => (float)$row->agg_total_in,
            'total_out' => (float)$row->agg_total_out,
            'ticket_open' => (float)$row->agg_ticket_open,
            'redeem' => (float)$row->agg_redeem,
            'unredeemed' => (float)$row->agg_unredeemed,
            'activity_bonus' => (float)$row->agg_activity_bonus,
        ];
    }

}
