<?php

declare(strict_types=1);

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\Player;
use addons\webman\model\StoreAgentShiftHandoverRecord;
use addons\webman\model\TicketRecord;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\grid\Editable;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;

/**
 * 出票记录管理
 * @group store
 */
class StoreTicketRecordController
{
    /**
     * 出票记录列表
     * @group store
     * @auth true
     * @return Grid
     */
    public function index(): Grid
    {
        $admin = Admin::user();

        return Grid::create(new TicketRecord(), function (Grid $grid) use ($admin) {
            $grid->title(admin_trans('ticket_machine.record.title'));
            $grid->autoHeight();

            // 只显示当前店家的出票类型数据（开分、体验卷、福利卷）
            $grid->model()
                ->where('store_admin_id', $admin->id)
                ->whereIn('ticket_type', [
                    TicketRecord::TYPE_RECHARGE,
                    TicketRecord::TYPE_EXPERIENCE,
                    TicketRecord::TYPE_WELFARE,
                ])
                ->orderBy('created_at', 'desc');

            // 统计数据（基于当前筛选条件，排除禁用状态）
            $query = clone $grid->model();
            $query->where('status', '!=', TicketRecord::STATUS_DISABLED);
            $totalData = $query->selectRaw(
                'sum(score) as total_score, count(*) as total_count, sum(IF(status IN (' . TicketRecord::STATUS_BACKEND_USED . ',' . TicketRecord::STATUS_MACHINE_USED . '), 1, 0)) as used_count, sum(IF(status IN (' . TicketRecord::STATUS_BACKEND_USED . ',' . TicketRecord::STATUS_MACHINE_USED . '), score, 0)) as used_score'
            )->first();

            // 获取当前班次统计（从上次交班时间到现在）
            $lastShiftRecord = StoreAgentShiftHandoverRecord::query()
                ->where('bind_admin_user_id', $admin->id)
                ->orderBy('id', 'desc')
                ->first();
            $lastShiftTime = $lastShiftRecord?->end_time;

            $currentShiftQuery = TicketRecord::query()
                ->where('store_admin_id', $admin->id)
                ->where('ticket_type', TicketRecord::TYPE_RECHARGE)
                ->where('status', '!=', TicketRecord::STATUS_DISABLED)
                ->when($lastShiftTime, function ($query) use ($lastShiftTime) {
                    $query->where('created_at', '>', $lastShiftTime);
                });
            $currentShiftData = $currentShiftQuery->selectRaw(
                'sum(score) as total_score, count(*) as total_count, sum(IF(status IN (' . TicketRecord::STATUS_BACKEND_USED . ',' . TicketRecord::STATUS_MACHINE_USED . '), 1, 0)) as used_count, sum(IF(status IN (' . TicketRecord::STATUS_BACKEND_USED . ',' . TicketRecord::STATUS_MACHINE_USED . '), score, 0)) as used_score'
            )->first();

            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                // 总金额
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(!empty($totalData['total_score']) ? floatval($totalData['total_score']) : 0)
                            ->prefix(admin_trans('ticket_machine.record.total_score'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#1890ff'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 6);
                // 总出票次数
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(!empty($totalData['total_count']) ? intval($totalData['total_count']) : 0)
                            ->prefix(admin_trans('ticket_machine.record.total_count'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#52c41a'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 6);
                // 已使用数量
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(!empty($totalData['used_count']) ? intval($totalData['used_count']) : 0)
                            ->prefix(admin_trans('ticket_machine.record.used_count'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#faad14'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 6);
                // 已使用金额
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(!empty($totalData['used_score']) ? floatval($totalData['used_score']) : 0)
                            ->prefix(admin_trans('ticket_machine.record.used_score'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#f5222d'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 6);
            })->style(['background' => '#fff']);

            // 当前班次统计
            $layout->row(function (Row $row) use ($currentShiftData, $lastShiftTime) {
                $row->gutter([10, 0]);
                // 当前班次标题
                $row->column(
                    Card::create([
                        Html::create(admin_trans('ticket_machine.record.current_shift') . ($lastShiftTime ? ' (' . $lastShiftTime . ')' : ''))
                            ->style([
                                'font-size' => '12px',
                                'font-weight' => 'bold',
                                'color' => '#67C23A',
                                'text-align' => 'center'
                            ]),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'justify-content' => 'center',
                        'height' => '30px',
                        'padding' => '0px',
                        'background' => '#f6ffed'
                    ])->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 4);
                // 当前班次总金额
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(!empty($currentShiftData['total_score']) ? floatval($currentShiftData['total_score']) : 0)
                            ->prefix(admin_trans('ticket_machine.record.total_score'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#1890ff'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px',
                        'background' => '#f6ffed'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 5);
                // 当前班次出票次数
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(!empty($currentShiftData['total_count']) ? intval($currentShiftData['total_count']) : 0)
                            ->prefix(admin_trans('ticket_machine.record.total_count'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#52c41a'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px',
                        'background' => '#f6ffed'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 5);
                // 当前班次已使用数量
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(!empty($currentShiftData['used_count']) ? intval($currentShiftData['used_count']) : 0)
                            ->prefix(admin_trans('ticket_machine.record.used_count'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#faad14'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px',
                        'background' => '#f6ffed'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 5);
                // 当前班次已使用金额
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(!empty($currentShiftData['used_score']) ? floatval($currentShiftData['used_score']) : 0)
                            ->prefix(admin_trans('ticket_machine.record.used_score'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#f5222d'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px',
                        'background' => '#f6ffed'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 5);
            })->style(['background' => '#f6ffed']);

            // 添加出票机控制按钮和统计布局（合并到一次调用）
            $grid->tools([
                Button::create(admin_trans('ticket_machine.title'))
                    ->modal([ChannelIndexController::class, 'ticketMachineControl'])
                    ->width('900px')
                    ->type('primary')
                    ->icon('PrinterOutlined'),
                $layout
            ]);

            // 列定义
            $grid->column('id', 'ID')->align('center')->width(80);
            $grid->column('order_id', admin_trans('ticket_machine.record.order_id'))->copy();
            $grid->column('player.name', admin_trans('ticket_machine.record.player_name'))->display(function ($val, $data) {
                $playerName = $val ?? ($data['player_name'] ?? '');
                if (!empty($data['player_id'])) {
                    $avatar = !empty($data['player']['avatar'])
                        ? Avatar::create()->src(is_numeric($data['player']['avatar']) ? config('def_avatar.' . $data['player']['avatar']) : $data['player']['avatar'])->size(24)
                        : Avatar::create()->content(mb_substr($playerName ?: 'U', 0, 1))->size(24);
                    return Html::create()->content([
                        $avatar,
                        Html::create($playerName ?: admin_trans('ticket_machine.record.unnamed'))->style([
                            'marginLeft' => '8px',
                            'fontSize' => '13px',
                            'color' => '#303133',
                        ]),
                    ])->style(['display' => 'flex', 'alignItems' => 'center']);
                }
                return Html::create(admin_trans('ticket_machine.record.no_player'))->style(['color' => '#999']);
            })->width(120);
            $grid->column('store_name', admin_trans('ticket_machine.record.store_name'));
            $grid->column('machine_no', admin_trans('ticket_machine.record.machine_no'))->align('center');
            $grid->column('score', admin_trans('ticket_machine.record.score'))->align('right');
            $grid->column('ticket_type', admin_trans('ticket_machine.record.ticket_type'))->display(function ($val) {
                return match ($val) {
                    TicketRecord::TYPE_RECHARGE => Tag::create(admin_trans('ticket_machine.record.type_recharge'))->color('blue'),
                    TicketRecord::TYPE_WITHDRAW => Tag::create(admin_trans('ticket_machine.record.type_withdraw'))->color('green'),
                    TicketRecord::TYPE_EXPERIENCE => Tag::create(admin_trans('ticket_machine.record.type_experience'))->color('purple'),
                    TicketRecord::TYPE_WELFARE => Tag::create(admin_trans('ticket_machine.record.type_welfare'))->color('orange'),
                    default => Tag::create(admin_trans('ticket_machine.record.status_unknown'))->color('default'),
                };
            });
            $grid->column('status', admin_trans('ticket_machine.record.status'))->display(function ($val, $data) {
                // 体验券和福利券：判断是否超过有效时间
                if ($val == TicketRecord::STATUS_NORMAL
                    && in_array($data['ticket_type'], [TicketRecord::TYPE_EXPERIENCE, TicketRecord::TYPE_WELFARE])) {
                    $voucherConfig = config('voucher');
                    $expireHours = $data['ticket_type'] == TicketRecord::TYPE_EXPERIENCE
                        ? ($voucherConfig['experience']['expire_hours'] ?? 24)
                        : ($voucherConfig['welfare']['expire_hours'] ?? 24);
                    $createdAt = $data['created_at'] instanceof \DateTimeInterface ? $data['created_at']->getTimestamp() : strtotime($data['created_at']);
                    $expireTime = $createdAt + ($expireHours * 3600);
                    if (time() > $expireTime) {
                        return Tag::create(admin_trans('ticket_machine.record.status_expired'))->color('default');
                    }
                }
                return match ($val) {
                    TicketRecord::STATUS_DISABLED => Tag::create(admin_trans('ticket_machine.record.status_disabled'))->color('default'),
                    TicketRecord::STATUS_NORMAL => Tag::create(admin_trans('ticket_machine.record.status_normal'))->color('blue'),
                    TicketRecord::STATUS_BACKEND_USED => Tag::create(admin_trans('ticket_machine.record.status_backend_used'))->color('orange'),
                    TicketRecord::STATUS_MACHINE_USED => Tag::create(admin_trans('ticket_machine.record.status_machine_used'))->color('purple'),
                    TicketRecord::STATUS_PRINT_FAILED => Tag::create(admin_trans('ticket_machine.record.status_print_failed'))->color('red'),
                    TicketRecord::STATUS_SPLIT => Tag::create(admin_trans('ticket_machine.record.status_split'))->color('cyan'),
                    TicketRecord::STATUS_MERGED => Tag::create(admin_trans('ticket_machine.record.status_merged'))->color('geekblue'),
                    default => Tag::create(admin_trans('ticket_machine.record.status_unknown'))->color('default'),
                };
            });
            // 来源（根据 source_type 判断）
            $grid->column('source_type', admin_trans('ticket_machine.record.source_type'))
                ->width(100)
                ->align('center')
                ->display(function ($val) {
                    return match ($val) {
                        TicketRecord::SOURCE_TYPE_PURCHASE => Tag::create(admin_trans('ticket_machine.record.source_purchase'))->color('green'),
                        TicketRecord::SOURCE_TYPE_SPLIT => Tag::create(admin_trans('ticket_machine.record.source_split'))->color('cyan'),
                        TicketRecord::SOURCE_TYPE_MERGE => Tag::create(admin_trans('ticket_machine.record.source_merge'))->color('geekblue'),
                        default => Tag::create(admin_trans('ticket_machine.record.source_backend'))->color('blue'),
                    };
                });
            $grid->column('created_at', admin_trans('ticket_machine.record.created_at'))->sortable();
            $grid->column('scanned_at', admin_trans('ticket_machine.record.scanned_at'))
                ->display(function ($val) {
                    return $val ?: '-';
                });
            $grid->column('scanned_by', admin_trans('ticket_machine.record.scanned_by'))
                ->display(function ($val, $data) {
                    if (empty($val)) {
                        return '-';
                    }
                    // 根据 status 区分核销来源
                    if ($data['status'] == TicketRecord::STATUS_BACKEND_USED) {
                        // 后台核销：查询 admin_user
                        $adminUser = AdminUser::query()->where('id', $val)->first();
                        return $adminUser
                            ? Tag::create($adminUser->username)->color('orange')
                            : Tag::create('ID:' . $val)->color('default');
                    } elseif ($data['status'] == TicketRecord::STATUS_MACHINE_USED) {
                        // 机台核销：查询 player
                        $player = Player::query()->where('id', $val)->first();
                        return $player
                            ? Tag::create($player->name ?? $player->uuid)->color('purple')
                            : Tag::create('ID:' . $val)->color('default');
                    }
                    return Tag::create('ID:' . $val)->color('default');
                });

            // 备注（可编辑）
            $grid->column('remark', admin_trans('ticket_machine.record.remark'))
                ->display(function ($value) {
                    return $value ?: '-';
                })
                ->editable(
                    (new Editable)->text('remark')->maxlength(255)
                )
                ->width(150)->ellipsis(true);

            // 筛选器
            $grid->expandFilter();
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('order_id')->placeholder(admin_trans('ticket_machine.record.order_id'));
                $filter->like()->text('machine_no')->placeholder(admin_trans('ticket_machine.record.machine_no'));
                $filter->like()->text('remark')->placeholder(admin_trans('ticket_machine.record.remark'));
                $filter->eq()->select('ticket_type')
                    ->placeholder(admin_trans('ticket_machine.record.ticket_type'))
                    ->options([
                        '' => admin_trans('public_msg.all'),
                        TicketRecord::TYPE_RECHARGE => admin_trans('ticket_machine.record.type_recharge'),
                        TicketRecord::TYPE_EXPERIENCE => admin_trans('ticket_machine.record.type_experience'),
                        TicketRecord::TYPE_WITHDRAW => admin_trans('ticket_machine.record.type_withdraw'),
                        TicketRecord::TYPE_WELFARE => admin_trans('ticket_machine.record.type_welfare'),
                    ])
                    ->style(['width' => '150px']);
                $filter->eq()->select('status')
                    ->placeholder(admin_trans('ticket_machine.record.status'))
                    ->options([
                        '' => admin_trans('public_msg.all'),
                        TicketRecord::STATUS_DISABLED => admin_trans('ticket_machine.record.status_disabled'),
                        TicketRecord::STATUS_NORMAL => admin_trans('ticket_machine.record.status_normal'),
                        TicketRecord::STATUS_BACKEND_USED => admin_trans('ticket_machine.record.status_backend_used'),
                        TicketRecord::STATUS_MACHINE_USED => admin_trans('ticket_machine.record.status_machine_used'),
                        TicketRecord::STATUS_PRINT_FAILED => admin_trans('ticket_machine.record.status_print_failed'),
                        TicketRecord::STATUS_SPLIT => admin_trans('ticket_machine.record.status_split'),
                        TicketRecord::STATUS_MERGED => admin_trans('ticket_machine.record.status_merged'),
                    ])
                    ->style(['width' => '150px']);
                $filter->eq()->select('source_type')
                    ->placeholder(admin_trans('ticket_machine.record.source_type'))
                    ->options([
                        '' => admin_trans('public_msg.all'),
                        'null' => admin_trans('ticket_machine.record.source_backend'),
                        TicketRecord::SOURCE_TYPE_PURCHASE => admin_trans('ticket_machine.record.source_purchase'),
                        TicketRecord::SOURCE_TYPE_SPLIT => admin_trans('ticket_machine.record.source_split'),
                        TicketRecord::SOURCE_TYPE_MERGE => admin_trans('ticket_machine.record.source_merge'),
                    ])
                    ->style(['width' => '150px']);
                $filter->between()->dateTimeRange('created_at')
                    ->placeholder([
                        admin_trans('common.start_time'),
                        admin_trans('common.end_time')
                    ]);
            });

            // 处理可编辑列的保存
            $grid->updateing(function ($ids, $data) {
                try {
                    if (isset($ids[0])) {
                        $admin = Admin::user();
                        $record = TicketRecord::query()
                            ->where('id', $ids[0])
                            ->where('store_admin_id', $admin->id)
                            ->first();

                        if (!$record) {
                            return message_error(admin_trans('common.data_not_found'));
                        }

                        if (array_key_exists('remark', $data)) {
                            $record->remark = $data['remark'];
                            $record->save();
                            return message_success(admin_trans('form.save_success'));
                        }
                    }
                    return message_error(admin_trans('form.save_fail'));
                } catch (\Exception $e) {
                    return message_error(admin_trans('form.save_fail') . ': ' . $e->getMessage());
                }
            });

            // 隐藏清空数据按钮
            $grid->hideDelete();

            // 操作列
            $grid->actions(function ($actions, $data) {
                $actions->hideEdit();
                $actions->hideDel();

                // 核销按钮（开分票且状态为正常时显示）
                if ($data['status'] == TicketRecord::STATUS_NORMAL && $data['ticket_type'] == TicketRecord::TYPE_RECHARGE) {
                    $actions->prepend(
                        Button::create(admin_trans('ticket_machine.record.redeem'))
                            ->confirm(admin_trans('ticket_machine.record.redeem_confirm'), [$this, 'redeemRecord'], ['id' => $data['id']])
                            ->type('primary')
                            ->size('small')
                            ->gridRefresh()
                    );
                }

                if ($data['status'] == TicketRecord::STATUS_DISABLED) {
                    // 已禁用 - 显示恢复按钮
                    $actions->prepend(
                        Button::create(admin_trans('ticket_machine.record.restore'))
                            ->confirm(admin_trans('ticket_machine.record.restore_confirm'), [$this, 'restoreRecord'], ['id' => $data['id']])
                            ->type('primary')
                            ->size('small')
                            ->gridRefresh()
                    );
                } elseif ($data['status'] == TicketRecord::STATUS_NORMAL) {
                    // 正常状态 - 显示禁用按钮
                    $actions->prepend(
                        Button::create(admin_trans('ticket_machine.record.disable'))
                            ->confirm(admin_trans('ticket_machine.record.delete_confirm'), [$this, 'disableRecord'], ['id' => $data['id']])
                            ->type('warning')
                            ->size('small')
                            ->gridRefresh()
                    );
                }
            });
        });
    }

    /**
     * 查看详情
     * @group store
     * @auth true
     * @return Form
     */
    public function detail(): Form
    {
        return Form::create(new TicketRecord(), function (Form $form) {
            $form->layout('vertical');

            $form->desc('order_id', admin_trans('ticket_machine.record.order_id'));
            $form->desc('store_name', admin_trans('ticket_machine.record.store_name'));
            $form->desc('machine_no', admin_trans('ticket_machine.record.machine_no'));
            $form->desc('score', admin_trans('ticket_machine.record.score'));
            $form->desc('ticket_type', admin_trans('ticket_machine.record.ticket_type'))->display(function ($val) {
                return match ($val) {
                    TicketRecord::TYPE_RECHARGE => admin_trans('ticket_machine.record.type_recharge'),
                    TicketRecord::TYPE_WITHDRAW => admin_trans('ticket_machine.record.type_withdraw'),
                    TicketRecord::TYPE_EXPERIENCE => admin_trans('ticket_machine.record.type_experience'),
                    TicketRecord::TYPE_WELFARE => admin_trans('ticket_machine.record.type_welfare'),
                    default => admin_trans('ticket_machine.record.status_unknown'),
                };
            });
            $form->desc('qr_code', admin_trans('ticket_machine.record.qr_code'));
            $form->desc('qr_code_no', admin_trans('ticket_machine.record.qr_code_no'));
            $form->desc('status', admin_trans('ticket_machine.record.status'))->display(function ($val) {
                return match ($val) {
                    TicketRecord::STATUS_DISABLED => admin_trans('ticket_machine.record.status_disabled'),
                    TicketRecord::STATUS_NORMAL => admin_trans('ticket_machine.record.status_normal'),
                    TicketRecord::STATUS_BACKEND_USED => admin_trans('ticket_machine.record.status_backend_used'),
                    TicketRecord::STATUS_MACHINE_USED => admin_trans('ticket_machine.record.status_machine_used'),
                    TicketRecord::STATUS_PRINT_FAILED => admin_trans('ticket_machine.record.status_print_failed'),
                    TicketRecord::STATUS_SPLIT => admin_trans('ticket_machine.record.status_split'),
                    TicketRecord::STATUS_MERGED => admin_trans('ticket_machine.record.status_merged'),
                    default => admin_trans('ticket_machine.record.status_unknown'),
                };
            });
            $form->desc('source_type', admin_trans('ticket_machine.record.source_type'))->display(function ($val) {
                return match ($val) {
                    TicketRecord::SOURCE_TYPE_PURCHASE => admin_trans('ticket_machine.record.source_purchase'),
                    TicketRecord::SOURCE_TYPE_SPLIT => admin_trans('ticket_machine.record.source_split'),
                    TicketRecord::SOURCE_TYPE_MERGE => admin_trans('ticket_machine.record.source_merge'),
                    default => admin_trans('ticket_machine.record.source_backend'),
                };
            });
            $form->desc('print_count', admin_trans('ticket_machine.record.print_count'));
            $form->desc('last_print_time', admin_trans('ticket_machine.record.last_print_time'));
            $form->desc('scanned_at', admin_trans('ticket_machine.record.scanned_at'));
            $form->desc('remark', admin_trans('ticket_machine.record.remark'));
            $form->desc('created_at', admin_trans('ticket_machine.record.created_at'));

            $form->disableSubmit();
        });
    }

    /**
     * 禁用记录
     * @group store
     * @auth true
     * @return mixed
     */
    public function disableRecord()
    {
        $id = request()->input('id', 0);

        if (empty($id)) {
            return message_error(admin_trans('common.invalid_parameter'));
        }

        $admin = Admin::user();
        $record = TicketRecord::query()
            ->where('id', $id)
            ->where('store_admin_id', $admin->id)
            ->first();

        if (empty($record)) {
            return message_error(admin_trans('common.data_not_found'));
        }

        $record->update(['status' => TicketRecord::STATUS_DISABLED]);

        return message_success(admin_trans('ticket_machine.record.delete_success'));
    }

    /**
     * 恢复记录
     * @group store
     * @auth true
     * @return mixed
     */
    public function restoreRecord()
    {
        $id = request()->input('id', 0);

        if (empty($id)) {
            return message_error(admin_trans('common.invalid_parameter'));
        }

        $admin = Admin::user();
        $record = TicketRecord::query()
            ->where('id', $id)
            ->where('store_admin_id', $admin->id)
            ->first();

        if (empty($record)) {
            return message_error(admin_trans('common.data_not_found'));
        }

        $record->update(['status' => TicketRecord::STATUS_NORMAL]);

        return message_success(admin_trans('ticket_machine.record.restore_success'));
    }

    /**
     * 核销记录（开分票）
     * @group store
     * @auth true
     * @return mixed
     */
    public function redeemRecord()
    {
        $id = request()->input('id', 0);

        if (empty($id)) {
            return message_error(admin_trans('common.invalid_parameter'));
        }

        $admin = Admin::user();
        $record = TicketRecord::query()
            ->where('id', $id)
            ->where('store_admin_id', $admin->id)
            ->where('ticket_type', TicketRecord::TYPE_RECHARGE)
            ->where('status', TicketRecord::STATUS_NORMAL)
            ->first();

        if (empty($record)) {
            return message_error(admin_trans('ticket_machine.record.record_not_found'));
        }

        // 更新状态为已使用（后台核销）
        $record->update([
            'status' => TicketRecord::STATUS_BACKEND_USED,
            'scanned_at' => date('Y-m-d H:i:s'),
            'scanned_by' => $admin->id,
        ]);

        return message_success(admin_trans('ticket_machine.record.redeem_success'));
    }

    /**
     * 物理删除记录
     * @group store
     * @auth true
     * @return mixed
     */
    public function forceDeleteRecord()
    {
        $id = request()->input('id', 0);

        if (empty($id)) {
            return message_error(admin_trans('common.invalid_parameter'));
        }

        $admin = Admin::user();
        $record = TicketRecord::query()
            ->where('id', $id)
            ->where('store_admin_id', $admin->id)
            ->first();

        if (empty($record)) {
            return message_error(admin_trans('common.data_not_found'));
        }

        // 物理删除
        $record->delete();

        return message_success(admin_trans('ticket_machine.record.force_delete_success'));
    }
}
