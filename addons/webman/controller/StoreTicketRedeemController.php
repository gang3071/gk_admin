<?php

declare(strict_types=1);

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\StoreAgentShiftHandoverRecord;
use addons\webman\model\TicketRecord;
use addons\webman\model\Player;
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
 * 核销记录管理
 * @group store
 */
class StoreTicketRedeemController
{
    /**
     * 核销记录列表
     * @group store
     * @auth true
     * @return Grid
     */
    public function index(): Grid
    {
        $admin = Admin::user();

        return Grid::create(new TicketRecord(), function (Grid $grid) use ($admin) {
            $grid->title(admin_trans('ticket_machine.redeem.title'));
            $grid->autoHeight();

            // 使用子查询获取玩家头像，避免 join 导致字段冲突
            $grid->model()
                ->selectRaw('qr_ticket_record.*, (SELECT avatar FROM player WHERE player.id = qr_ticket_record.player_id LIMIT 1) as player_avatar')
                ->with(['player:id,name,uuid'])
                ->where('store_admin_id', $admin->id)
                ->where('ticket_type', TicketRecord::TYPE_WITHDRAW)
                ->orderBy('created_at', 'desc');

            // 统计数据（使用独立查询，避免 join 和 group by 问题，排除禁用状态）
            $totalData = TicketRecord::query()
                ->where('store_admin_id', $admin->id)
                ->where('ticket_type', TicketRecord::TYPE_WITHDRAW)
                ->where('status', '!=', TicketRecord::STATUS_DISABLED)
                ->selectRaw(
                    'sum(score) as total_score, count(*) as total_count, '
                    . 'sum(IF(status IN (' . TicketRecord::STATUS_BACKEND_USED . ',' . TicketRecord::STATUS_MACHINE_USED . '), 1, 0)) as used_count, '
                    . 'sum(IF(status = ' . TicketRecord::STATUS_BACKEND_USED . ', score, 0)) as backend_used_score, '
                    . 'sum(IF(status = ' . TicketRecord::STATUS_MACHINE_USED . ', score, 0)) as machine_used_score'
                )
                ->first();

            // 获取当前班次统计（从上次交班时间到现在）
            $lastShiftRecord = StoreAgentShiftHandoverRecord::query()
                ->where('bind_admin_user_id', $admin->id)
                ->orderBy('id', 'desc')
                ->first();
            $lastShiftTime = $lastShiftRecord ? $lastShiftRecord->end_time : null;

            $currentShiftQuery = TicketRecord::query()
                ->where('store_admin_id', $admin->id)
                ->where('ticket_type', TicketRecord::TYPE_WITHDRAW)
                ->where('status', '!=', TicketRecord::STATUS_DISABLED)
                ->when($lastShiftTime, function ($query) use ($lastShiftTime) {
                    $query->where('scanned_at', '>', $lastShiftTime);
                });
            $currentShiftData = $currentShiftQuery->selectRaw(
                'sum(score) as total_score, count(*) as total_count, '
                . 'sum(IF(status IN (' . TicketRecord::STATUS_BACKEND_USED . ',' . TicketRecord::STATUS_MACHINE_USED . '), 1, 0)) as used_count, '
                . 'sum(IF(status = ' . TicketRecord::STATUS_BACKEND_USED . ', score, 0)) as backend_used_score, '
                . 'sum(IF(status = ' . TicketRecord::STATUS_MACHINE_USED . ', score, 0)) as machine_used_score'
            )->first();

            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                // 总金额
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(!empty($totalData['total_score']) ? floatval($totalData['total_score']) : 0)
                            ->prefix(admin_trans('ticket_machine.redeem.total_score'))
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
                    , 5);
                // 总核销次数
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(!empty($totalData['total_count']) ? intval($totalData['total_count']) : 0)
                            ->prefix(admin_trans('ticket_machine.redeem.total_count'))
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
                    , 5);
                // 已使用数量
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(!empty($totalData['used_count']) ? intval($totalData['used_count']) : 0)
                            ->prefix(admin_trans('ticket_machine.redeem.used_count'))
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
                    , 4);
                // 后台使用金额
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(!empty($totalData['backend_used_score']) ? floatval($totalData['backend_used_score']) : 0)
                            ->prefix(admin_trans('ticket_machine.redeem.backend_used_score'))
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
                    , 5);
                // 机台使用金额
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(!empty($totalData['machine_used_score']) ? floatval($totalData['machine_used_score']) : 0)
                            ->prefix(admin_trans('ticket_machine.redeem.machine_used_score'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#722ed1'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 5);
            })->style(['background' => '#fff']);

            // 当前班次统计
            $layout->row(function (Row $row) use ($currentShiftData, $lastShiftTime) {
                $row->gutter([10, 0]);
                // 当前班次标题
                $row->column(
                    Card::create([
                        Html::create(admin_trans('ticket_machine.redeem.current_shift') . ($lastShiftTime ? ' (' . $lastShiftTime . ')' : ''))
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
                // 当前班次总核销金额
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(!empty($currentShiftData['total_score']) ? floatval($currentShiftData['total_score']) : 0)
                            ->prefix(admin_trans('ticket_machine.redeem.total_score'))
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
                    , 4);
                // 当前班次总核销次数
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(!empty($currentShiftData['total_count']) ? intval($currentShiftData['total_count']) : 0)
                            ->prefix(admin_trans('ticket_machine.redeem.total_count'))
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
                    , 4);
                // 当前班次已使用数量
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(!empty($currentShiftData['used_count']) ? intval($currentShiftData['used_count']) : 0)
                            ->prefix(admin_trans('ticket_machine.redeem.used_count'))
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
                    , 4);
                // 当前班次后台使用金额
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(!empty($currentShiftData['backend_used_score']) ? floatval($currentShiftData['backend_used_score']) : 0)
                            ->prefix(admin_trans('ticket_machine.redeem.backend_used_score'))
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
                    , 4);
                // 当前班次机台使用金额
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(!empty($currentShiftData['machine_used_score']) ? floatval($currentShiftData['machine_used_score']) : 0)
                            ->prefix(admin_trans('ticket_machine.redeem.machine_used_score'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center',
                                'color' => '#722ed1'
                            ])),
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px',
                        'background' => '#f6ffed'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                    , 4);
            })->style(['background' => '#f6ffed']);

            // 添加核销按钮和统计布局
            $grid->tools([
                Button::create(admin_trans('ticket_machine.redeem.redeem_btn'))
                    ->type('primary')
                    ->modal([$this, 'scanRedeem'])
                    ->width('700px'),
                $layout
            ]);

            // 列定义
            $grid->column('id', 'ID')->align('center')->width(80);
            $grid->column('player.name', admin_trans('ticket_machine.redeem.player_id'))->display(function ($val, $data) {
                $playerName = $val ?? ($data['player_name'] ?? '');
                if (!empty($data['player_id'])) {
                    $avatar = !empty($data['player']['avatar'])
                        ? Avatar::create()->src(is_numeric($data['player']['avatar']) ? config('def_avatar.' . $data['player']['avatar']) : $data['player']['avatar'])->size(32)
                        : Avatar::create()->content(mb_substr($playerName ?: 'U', 0, 1))->size(32);
                    return Html::create()->content([
                        $avatar,
                        Html::div()->content($playerName ?: admin_trans('ticket_machine.redeem.unnamed'))->style([
                            'marginLeft' => '8px',
                            'fontSize' => '13px',
                            'fontWeight' => '500',
                            'color' => '#303133',
                        ]),
                    ])->style(['display' => 'flex', 'alignItems' => 'center']);
                }
                return Html::create(admin_trans('ticket_machine.redeem.no_player'))->style(['color' => '#999']);
            })->width(150);
            $grid->column('order_id', admin_trans('ticket_machine.redeem.order_id'))->copy();
            $grid->column('store_name', admin_trans('ticket_machine.redeem.store_name'));
            $grid->column('machine_no', admin_trans('ticket_machine.redeem.machine_no'))->align('center');
            $grid->column('score', admin_trans('ticket_machine.redeem.score'))->align('right');
            $grid->column('status', admin_trans('ticket_machine.redeem.status'))->display(function ($val) {
                return match ($val) {
                    TicketRecord::STATUS_DISABLED => Tag::create(admin_trans('ticket_machine.redeem.status_disabled'))->color('default'),
                    TicketRecord::STATUS_NORMAL => Tag::create(admin_trans('ticket_machine.redeem.status_normal'))->color('blue'),
                    TicketRecord::STATUS_BACKEND_USED => Tag::create(admin_trans('ticket_machine.redeem.status_backend_used'))->color('orange'),
                    TicketRecord::STATUS_MACHINE_USED => Tag::create(admin_trans('ticket_machine.redeem.status_machine_used'))->color('purple'),
                    TicketRecord::STATUS_SPLIT => Tag::create(admin_trans('ticket_machine.redeem.status_split'))->color('cyan'),
                    TicketRecord::STATUS_MERGED => Tag::create(admin_trans('ticket_machine.redeem.status_merged'))->color('geekblue'),
                    default => Tag::create(admin_trans('ticket_machine.redeem.status_unknown'))->color('default'),
                };
            });

            // 来源（根据 source_type 判断）
            $grid->column('source_type', admin_trans('ticket_machine.redeem.source_type'))
                ->width(100)
                ->align('center')
                ->display(function ($val) {
                    return match ($val) {
                        TicketRecord::SOURCE_TYPE_PURCHASE => Tag::create(admin_trans('ticket_machine.redeem.source_purchase'))->color('green'),
                        TicketRecord::SOURCE_TYPE_SPLIT => Tag::create(admin_trans('ticket_machine.redeem.source_split'))->color('cyan'),
                        TicketRecord::SOURCE_TYPE_MERGE => Tag::create(admin_trans('ticket_machine.redeem.source_merge'))->color('geekblue'),
                        default => Tag::create(admin_trans('ticket_machine.redeem.source_machine_wash'))->color('blue'),
                    };
                });

            $grid->column('created_at', admin_trans('ticket_machine.redeem.created_at'))->sortable();
            $grid->column('scanned_at', admin_trans('ticket_machine.redeem.scanned_at'))
                ->display(function ($val) {
                    return $val ?: '-';
                });
            $grid->column('scanned_by', admin_trans('ticket_machine.redeem.scanned_by'))
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
            $grid->column('remark', admin_trans('ticket_machine.redeem.remark'))
                ->display(function ($value) {
                    return $value ?: '-';
                })
                ->editable(
                    (new Editable)->text('remark')->maxlength(255)
                )
                ->width(150)->ellipsis(true);

            // 筛选器（默认展开）
            $adminId = $admin->id;

            // 获取店名下拉选项
            $storeOptions = ['' => admin_trans('public_msg.all')];
            $stores = TicketRecord::query()
                ->where('store_admin_id', $adminId)
                ->where('ticket_type', TicketRecord::TYPE_WITHDRAW)
                ->distinct()
                ->pluck('store_name')
                ->toArray();
            foreach ($stores as $store) {
                $storeOptions[$store] = $store;
            }

            $grid->expandFilter();
            $grid->filter(function (Filter $filter) use ($storeOptions) {
                $filter->like()->text('order_id')->placeholder(admin_trans('ticket_machine.redeem.order_id'));
                // 玩家姓名筛选（使用关系）
                $filter->like()->text('player.name')->placeholder(admin_trans('ticket_machine.redeem.player_name'));
                // 玩家UUID筛选（使用关系）
                $filter->like()->text('player.uuid')->placeholder(admin_trans('ticket_machine.redeem.player_uuid'));
                $filter->like()->text('machine_no')->placeholder(admin_trans('ticket_machine.redeem.machine_no'));
                $filter->like()->text('remark')->placeholder(admin_trans('ticket_machine.redeem.remark'));
                $filter->eq()->select('store_name')
                    ->placeholder(admin_trans('ticket_machine.redeem.store_name'))
                    ->options($storeOptions)
                    ->style(['width' => '150px']);
                $filter->eq()->select('status')
                    ->placeholder(admin_trans('ticket_machine.redeem.status'))
                    ->options([
                        '' => admin_trans('public_msg.all'),
                        TicketRecord::STATUS_DISABLED => admin_trans('ticket_machine.redeem.status_disabled'),
                        TicketRecord::STATUS_NORMAL => admin_trans('ticket_machine.redeem.status_normal'),
                        TicketRecord::STATUS_BACKEND_USED => admin_trans('ticket_machine.redeem.status_backend_used'),
                        TicketRecord::STATUS_MACHINE_USED => admin_trans('ticket_machine.redeem.status_machine_used'),
                        TicketRecord::STATUS_SPLIT => admin_trans('ticket_machine.redeem.status_split'),
                        TicketRecord::STATUS_MERGED => admin_trans('ticket_machine.redeem.status_merged'),
                    ])
                    ->style(['width' => '150px']);
                $filter->eq()->select('source_type')
                    ->placeholder(admin_trans('ticket_machine.redeem.source_type'))
                    ->options([
                        '' => admin_trans('public_msg.all'),
                        'null' => admin_trans('ticket_machine.redeem.source_machine_wash'),
                        TicketRecord::SOURCE_TYPE_PURCHASE => admin_trans('ticket_machine.redeem.source_purchase'),
                        TicketRecord::SOURCE_TYPE_SPLIT => admin_trans('ticket_machine.redeem.source_split'),
                        TicketRecord::SOURCE_TYPE_MERGE => admin_trans('ticket_machine.redeem.source_merge'),
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

                // 核销按钮（放在最前面）
                if ($data['status'] == TicketRecord::STATUS_NORMAL) {
                    $actions->prepend(
                        Button::create(admin_trans('ticket_machine.redeem.redeem'))
                            ->modal([$this, 'redeemModal'], ['id' => $data['id']])
                            ->type('primary')
                            ->size('small')
                    );
                }

                if ($data['status'] == TicketRecord::STATUS_DISABLED) {
                    // 已禁用 - 显示恢复按钮
                    $actions->prepend(
                        Button::create(admin_trans('ticket_machine.redeem.restore'))
                            ->confirm(admin_trans('ticket_machine.redeem.restore_confirm'), [$this, 'restoreRecord'], ['id' => $data['id']])
                            ->type('primary')
                            ->size('small')
                            ->gridRefresh()
                    );
                } elseif ($data['status'] == TicketRecord::STATUS_NORMAL) {
                    // 正常状态 - 显示禁用按钮
                    $actions->prepend(
                        Button::create(admin_trans('ticket_machine.redeem.disable'))
                            ->confirm(admin_trans('ticket_machine.redeem.delete_confirm'), [$this, 'disableRecord'], ['id' => $data['id']])
                            ->type('danger')
                            ->size('small')
                            ->gridRefresh()
                    );
                }
            });
        });
    }

    /**
     * 扫码核销页面
     * @group store
     * @auth true
     * @return mixed
     */
    public function scanRedeem()
    {
        return admin_view(plugin()->webman->getPath() . '/views/scan_redeem.vue')->attrs([
            'query_url' => 'ex-admin/addons-webman-controller-StoreTicketRedeemController/getRecordByQrCode',
            'redeem_url' => 'ex-admin/addons-webman-controller-StoreTicketRedeemController/redeemById',
            'labels' => [
                'input_qr_code' => admin_trans('ticket_machine.redeem.input_qr_code'),
                'scan_qr_code_placeholder' => admin_trans('ticket_machine.redeem.scan_qr_code_placeholder'),
                'order_id' => admin_trans('ticket_machine.redeem.order_id'),
                'store_name' => admin_trans('ticket_machine.redeem.store_name'),
                'machine_no' => admin_trans('ticket_machine.redeem.machine_no'),
                'score' => admin_trans('ticket_machine.redeem.score'),
                'status' => admin_trans('ticket_machine.redeem.status'),
                'ticket_type' => admin_trans('ticket_machine.redeem.ticket_type'),
                'qr_code_no' => admin_trans('ticket_machine.redeem.qr_code_no'),
                'created_at' => admin_trans('ticket_machine.redeem.created_at'),
                'redeem_confirm' => admin_trans('ticket_machine.redeem.redeem_confirm'),
                'player_name' => admin_trans('ticket_machine.redeem.player_name'),
                'player_id' => admin_trans('ticket_machine.redeem.player_id'),
            ],
        ]);
    }

    /**
     * 根据ID核销记录
     * @group store
     * @auth true
     * @return mixed
     */
    public function redeemById()
    {
        $id = request()->input('id', 0);

        if (empty($id)) {
            return json_encode(['code' => -1, 'msg' => admin_trans('common.invalid_parameter')]);
        }

        $admin = Admin::user();
        $record = TicketRecord::query()
            ->where('id', $id)
            ->where('store_admin_id', $admin->id)
            ->where('ticket_type', TicketRecord::TYPE_WITHDRAW)
            ->where('status', TicketRecord::STATUS_NORMAL)
            ->first();

        if (empty($record)) {
            return json_encode(['code' => -1, 'msg' => admin_trans('ticket_machine.redeem.record_not_found')]);
        }

        // 更新状态为已使用（后台核销）
        $record->update([
            'status' => TicketRecord::STATUS_BACKEND_USED,
            'scanned_at' => date('Y-m-d H:i:s'),
            'scanned_by' => $admin->id,
        ]);

        return json_encode(['code' => 0, 'msg' => admin_trans('ticket_machine.redeem.redeem_success')]);
    }

    /**
     * 根据二维码查询记录
     * @group store
     * @auth true
     * @return mixed
     */
    public function getRecordByQrCode()
    {
        $qrCodeNo = request()->input('qr_code_no', '');

        if (empty($qrCodeNo)) {
            return json_encode(['code' => -1, 'msg' => admin_trans('ticket_machine.redeem.qr_code_required')]);
        }

        $admin = Admin::user();

        // 直接使用 order_id 查询
        $record = TicketRecord::query()
            ->where('order_id', $qrCodeNo)
            ->where('store_admin_id', $admin->id)
            ->first();

        // 如果未找到，回退到 qr_code_no 查询
        if (empty($record)) {
            $record = TicketRecord::query()
                ->where('qr_code_no', $qrCodeNo)
                ->where('store_admin_id', $admin->id)
                ->first();
        }

        // 尝试通过 encrypted_content 字段匹配
        if (empty($record)) {
            $record = TicketRecord::query()
                ->where('encrypted_content', $qrCodeNo)
                ->where('store_admin_id', $admin->id)
                ->first();
        }

        if (empty($record)) {
            return json_encode(['code' => -1, 'msg' => admin_trans('ticket_machine.redeem.record_not_found')]);
        }

        // 更新扫码信息
        $record->update([
            'scanned_at' => date('Y-m-d H:i:s'),
            'scanned_by' => $admin->id,
        ]);

        // 通过 player_id 关联获取玩家信息
        $playerName = '';
        $playerAvatar = '';
        $playerUuid = '';

        if (!empty($record->player_id)) {
            $player = Player::query()
                ->where('id', $record->player_id)
                ->first();

            if ($player) {
                $playerName = $player->name ?? '';
                $playerUuid = $player->uuid ?? '';
                // 处理头像URL
                $avatar = $player->avatar ?? '';
                if (!empty($avatar)) {
                    $playerAvatar = is_numeric($avatar) ? config('def_avatar.' . $avatar) : $avatar;
                }
            }
        }

        return json_encode([
            'code' => 0,
            'data' => [
                'id' => $record->id,
                'order_id' => $record->order_id,
                'store_name' => $record->store_name,
                'machine_no' => $record->machine_no,
                'score' => $record->score,
                'ticket_type' => $record->ticket_type,
                'ticket_type_name' => $record->ticket_type_name,
                'status' => $record->status,
                'status_name' => $record->status_name,
                'qr_code_no' => $record->qr_code_no,
                'encrypted_content' => $record->encrypted_content,
                'created_at' => $record->created_at instanceof \DateTimeInterface ? $record->created_at->format('Y-m-d H:i:s') : (string) ($record->created_at ?? ''),
                'player_id' => $record->player_id,
                'player_name' => $playerName,
                'player_avatar' => $playerAvatar,
                'player_uuid' => $playerUuid,
            ]
        ]);
    }

    /**
     * 根据ID查询记录
     * @group store
     * @auth true
     * @return mixed
     */
    public function getRecordById()
    {
        $id = request()->input('id', 0);

        if (empty($id)) {
            return json_encode(['code' => -1, 'msg' => admin_trans('common.invalid_parameter')]);
        }

        $admin = Admin::user();
        $record = TicketRecord::query()
            ->where('id', $id)
            ->where('store_admin_id', $admin->id)
            ->first();

        if (empty($record)) {
            return json_encode(['code' => -1, 'msg' => admin_trans('ticket_machine.redeem.record_not_found')]);
        }

        // 通过 player_id 关联获取玩家信息
        $playerName = '';
        $playerAvatar = '';
        $playerUuid = '';

        if (!empty($record->player_id)) {
            $player = Player::query()
                ->where('id', $record->player_id)
                ->first();

            if ($player) {
                $playerName = $player->name ?? '';
                $playerUuid = $player->uuid ?? '';
                $avatar = $player->avatar ?? '';
                if (!empty($avatar)) {
                    $playerAvatar = is_numeric($avatar) ? config('def_avatar.' . $avatar) : $avatar;
                }
            }
        }

        return json_encode([
            'code' => 0,
            'data' => [
                'id' => $record->id,
                'order_id' => $record->order_id,
                'store_name' => $record->store_name,
                'machine_no' => $record->machine_no,
                'score' => $record->score,
                'ticket_type' => $record->ticket_type,
                'ticket_type_name' => $record->ticket_type_name,
                'status' => $record->status,
                'status_name' => $record->status_name,
                'qr_code_no' => $record->qr_code_no,
                'encrypted_content' => $record->encrypted_content ?? '',
                'created_at' => $record->created_at instanceof \DateTimeInterface ? $record->created_at->format('Y-m-d H:i:s') : (string) ($record->created_at ?? ''),
                'player_id' => $record->player_id,
                'player_name' => $playerName,
                'player_avatar' => $playerAvatar,
                'player_uuid' => $playerUuid,
            ]
        ]);
    }

    /**
     * 通过二维码核销
     * @group store
     * @auth true
     * @return mixed
     */
    public function redeemByQrCode()
    {
        $qrCodeNo = request()->input('qr_code_no', '');

        if (empty($qrCodeNo)) {
            return message_error(admin_trans('ticket_machine.redeem.qr_code_required'));
        }

        $admin = Admin::user();

        // 直接使用 order_id 查询
        $record = TicketRecord::query()
            ->where('order_id', $qrCodeNo)
            ->where('store_admin_id', $admin->id)
            ->where('ticket_type', TicketRecord::TYPE_WITHDRAW)
            ->where('status', TicketRecord::STATUS_NORMAL)
            ->first();

        // 如果未找到，回退到 qr_code_no 查询
        if (empty($record)) {
            $record = TicketRecord::query()
                ->where('qr_code_no', $qrCodeNo)
                ->where('store_admin_id', $admin->id)
                ->where('ticket_type', TicketRecord::TYPE_WITHDRAW)
                ->where('status', TicketRecord::STATUS_NORMAL)
                ->first();
        }

        if (empty($record)) {
            return message_error(admin_trans('ticket_machine.redeem.record_not_found'));
        }

        // 更新状态为已使用（后台核销）
        $record->update([
            'status' => TicketRecord::STATUS_BACKEND_USED,
            'scanned_at' => date('Y-m-d H:i:s'),
            'scanned_by' => $admin->id,
        ]);

        return message_success(admin_trans('ticket_machine.redeem.redeem_success'));
    }

    /**
     * 核销弹窗（从列表点击）
     * @group store
     * @auth true
     * @return Html
     */
    public function redeemModal()
    {
        $id = request()->input('id', 0);

        return admin_view(plugin()->webman->getPath() . '/views/scan_redeem.vue')->attrs([
            'query_url' => 'ex-admin/addons-webman-controller-StoreTicketRedeemController/getRecordById',
            'redeem_url' => 'ex-admin/addons-webman-controller-StoreTicketRedeemController/redeemById',
            'record_id' => $id,
            'labels' => [
                'input_qr_code' => admin_trans('ticket_machine.redeem.input_qr_code'),
                'scan_qr_code_placeholder' => admin_trans('ticket_machine.redeem.scan_qr_code_placeholder'),
                'order_id' => admin_trans('ticket_machine.redeem.order_id'),
                'store_name' => admin_trans('ticket_machine.redeem.store_name'),
                'machine_no' => admin_trans('ticket_machine.redeem.machine_no'),
                'score' => admin_trans('ticket_machine.redeem.score'),
                'status' => admin_trans('ticket_machine.redeem.status'),
                'ticket_type' => admin_trans('ticket_machine.redeem.ticket_type'),
                'qr_code_no' => admin_trans('ticket_machine.redeem.qr_code_no'),
                'created_at' => admin_trans('ticket_machine.redeem.created_at'),
                'redeem_confirm' => admin_trans('ticket_machine.redeem.redeem_confirm'),
                'player_name' => admin_trans('ticket_machine.redeem.player_name'),
                'player_id' => admin_trans('ticket_machine.redeem.player_id'),
            ],
        ]);
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

            $form->desc('order_id', admin_trans('ticket_machine.redeem.order_id'));
            $form->desc('store_name', admin_trans('ticket_machine.redeem.store_name'));
            $form->desc('machine_no', admin_trans('ticket_machine.redeem.machine_no'));
            $form->desc('score', admin_trans('ticket_machine.redeem.score'));
            $form->desc('ticket_type', admin_trans('ticket_machine.redeem.ticket_type'))->display(function ($val) {
                return admin_trans('ticket_machine.redeem.type_redeem');
            });
            $form->desc('qr_code', admin_trans('ticket_machine.redeem.qr_code'));
            $form->desc('qr_code_no', admin_trans('ticket_machine.redeem.qr_code_no'));
            $form->desc('status', admin_trans('ticket_machine.redeem.status'))->display(function ($val) {
                return match ($val) {
                    TicketRecord::STATUS_DISABLED => admin_trans('ticket_machine.redeem.status_disabled'),
                    TicketRecord::STATUS_NORMAL => admin_trans('ticket_machine.redeem.status_normal'),
                    TicketRecord::STATUS_BACKEND_USED => admin_trans('ticket_machine.redeem.status_backend_used'),
                    TicketRecord::STATUS_MACHINE_USED => admin_trans('ticket_machine.redeem.status_machine_used'),
                    TicketRecord::STATUS_SPLIT => admin_trans('ticket_machine.redeem.status_split'),
                    TicketRecord::STATUS_MERGED => admin_trans('ticket_machine.redeem.status_merged'),
                    default => admin_trans('ticket_machine.redeem.status_unknown'),
                };
            });
            $form->desc('source_type', admin_trans('ticket_machine.redeem.source_type'))->display(function ($val) {
                return match ($val) {
                    TicketRecord::SOURCE_TYPE_PURCHASE => admin_trans('ticket_machine.redeem.source_purchase'),
                    TicketRecord::SOURCE_TYPE_SPLIT => admin_trans('ticket_machine.redeem.source_split'),
                    TicketRecord::SOURCE_TYPE_MERGE => admin_trans('ticket_machine.redeem.source_merge'),
                    default => admin_trans('ticket_machine.redeem.source_machine_wash'),
                };
            });
            $form->desc('print_count', admin_trans('ticket_machine.redeem.print_count'));
            $form->desc('last_print_time', admin_trans('ticket_machine.redeem.last_print_time'));
            $form->desc('scanned_at', admin_trans('ticket_machine.redeem.scanned_at'));
            $form->desc('remark', admin_trans('ticket_machine.redeem.remark'));
            $form->desc('created_at', admin_trans('ticket_machine.redeem.created_at'));

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
            ->where('ticket_type', TicketRecord::TYPE_WITHDRAW)
            ->first();

        if (empty($record)) {
            return message_error(admin_trans('common.data_not_found'));
        }

        $record->update(['status' => TicketRecord::STATUS_DISABLED]);

        return message_success(admin_trans('ticket_machine.redeem.delete_success'));
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
            ->where('ticket_type', TicketRecord::TYPE_WITHDRAW)
            ->first();

        if (empty($record)) {
            return message_error(admin_trans('common.data_not_found'));
        }

        $record->update(['status' => TicketRecord::STATUS_NORMAL]);

        return message_success(admin_trans('ticket_machine.redeem.restore_success'));
    }

    /**
     * 核销记录
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
            ->where('ticket_type', TicketRecord::TYPE_WITHDRAW)
            ->where('status', TicketRecord::STATUS_NORMAL)
            ->first();

        if (empty($record)) {
            return message_error(admin_trans('ticket_machine.redeem.record_not_found'));
        }

        // 更新状态为已使用（后台核销）
        $record->update([
            'status' => TicketRecord::STATUS_BACKEND_USED,
            'scanned_at' => date('Y-m-d H:i:s'),
            'scanned_by' => $admin->id,
        ]);

        return message_success(admin_trans('ticket_machine.redeem.redeem_success'));
    }
}
