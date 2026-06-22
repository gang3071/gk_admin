<?php

declare(strict_types=1);

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\TicketRecord;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
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

            // 添加出票机控制按钮（与工具栏同一排）
            $grid->tools(
                Button::create(admin_trans('ticket_machine.title'))
                    ->modal([ChannelIndexController::class, 'ticketMachineControl'])
                    ->type('primary')
                    ->icon('PrinterOutlined')
            );

            // 只显示当前店家的数据
            $grid->model()
                ->where('store_admin_id', $admin->id)
                ->orderBy('created_at', 'desc');

            // 统计数据（基于当前筛选条件）
            $query = clone $grid->model();
            $totalData = $query->selectRaw(
                'sum(score) as total_score, count(*) as total_count, sum(IF(status = 3, 1, 0)) as used_count, sum(IF(status = 3, score, 0)) as used_score'
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

            $grid->tools($layout);

            // 列定义
            $grid->column('id', 'ID')->align('center')->width(80);
            $grid->column('order_id', admin_trans('ticket_machine.record.order_id'))->copy();
            $grid->column('store_name', admin_trans('ticket_machine.record.store_name'));
            $grid->column('machine_no', admin_trans('ticket_machine.record.machine_no'))->align('center');
            $grid->column('score', admin_trans('ticket_machine.record.score'))->align('right');
            $grid->column('ticket_type', admin_trans('ticket_machine.record.ticket_type'))->display(function ($val) {
                return $val == TicketRecord::TYPE_RECHARGE
                    ? Tag::create(admin_trans('ticket_machine.record.type_recharge'))->color('blue')
                    : Tag::create(admin_trans('ticket_machine.record.type_withdraw'))->color('green');
            });
            $grid->column('qr_code_no', admin_trans('ticket_machine.record.qr_code_no'))->copy();
            $grid->column('status', admin_trans('ticket_machine.record.status'))->display(function ($val) {
                return match ($val) {
                    TicketRecord::STATUS_DISABLED => Tag::create(admin_trans('ticket_machine.record.status_disabled'))->color('default'),
                    TicketRecord::STATUS_NORMAL => Tag::create(admin_trans('ticket_machine.record.status_normal'))->color('blue'),
                    TicketRecord::STATUS_PRINTED => Tag::create(admin_trans('ticket_machine.record.status_printed'))->color('green'),
                    TicketRecord::STATUS_USED => Tag::create(admin_trans('ticket_machine.record.status_used'))->color('orange'),
                    default => Tag::create(admin_trans('ticket_machine.record.status_unknown'))->color('default'),
                };
            });
            $grid->column('created_at', admin_trans('ticket_machine.record.created_at'))->sortable();

            // 筛选器
            $grid->filter(function (Filter $filter) {
                $filter->like('order_id', admin_trans('ticket_machine.record.order_id'));
                $filter->like('qr_code_no', admin_trans('ticket_machine.record.qr_code_no'));
                $filter->eq()->select('ticket_type')
                    ->placeholder(admin_trans('ticket_machine.record.ticket_type'))
                    ->options([
                        '' => admin_trans('public_msg.all'),
                        TicketRecord::TYPE_RECHARGE => admin_trans('ticket_machine.record.type_recharge'),
                        TicketRecord::TYPE_WITHDRAW => admin_trans('ticket_machine.record.type_withdraw'),
                    ])
                    ->style(['width' => '150px']);
                $filter->eq()->select('status')
                    ->placeholder(admin_trans('ticket_machine.record.status'))
                    ->options([
                        '' => admin_trans('public_msg.all'),
                        TicketRecord::STATUS_DISABLED => admin_trans('ticket_machine.record.status_disabled'),
                        TicketRecord::STATUS_NORMAL => admin_trans('ticket_machine.record.status_normal'),
                        TicketRecord::STATUS_PRINTED => admin_trans('ticket_machine.record.status_printed'),
                        TicketRecord::STATUS_USED => admin_trans('ticket_machine.record.status_used'),
                    ])
                    ->style(['width' => '150px']);
                $filter->between('created_at', admin_trans('ticket_machine.record.created_at'))->datetime();
            });

            // 关闭新增按钮
            $grid->disableCreateButton();

            // 隐藏清空数据按钮
            $grid->hideDelete();

            // 操作列
            $grid->actions(function ($actions, $data) {
                $actions->hideEdit();
                $actions->hideDel();

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
                            ->type('danger')
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
                return $val == TicketRecord::TYPE_RECHARGE
                    ? admin_trans('ticket_machine.record.type_recharge')
                    : admin_trans('ticket_machine.record.type_withdraw');
            });
            $form->desc('qr_code', admin_trans('ticket_machine.record.qr_code'));
            $form->desc('qr_code_no', admin_trans('ticket_machine.record.qr_code_no'));
            $form->desc('status', admin_trans('ticket_machine.record.status'))->display(function ($val) {
                return match ($val) {
                    TicketRecord::STATUS_DISABLED => admin_trans('ticket_machine.record.status_disabled'),
                    TicketRecord::STATUS_NORMAL => admin_trans('ticket_machine.record.status_normal'),
                    TicketRecord::STATUS_PRINTED => admin_trans('ticket_machine.record.status_printed'),
                    TicketRecord::STATUS_USED => admin_trans('ticket_machine.record.status_used'),
                    default => admin_trans('ticket_machine.record.status_unknown'),
                };
            });
            $form->desc('print_count', admin_trans('ticket_machine.record.print_count'));
            $form->desc('last_print_time', admin_trans('ticket_machine.record.last_print_time'));
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
}
