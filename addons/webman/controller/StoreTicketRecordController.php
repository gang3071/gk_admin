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
use ExAdmin\ui\component\grid\tag\Tag;

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
            $grid->column('print_count', admin_trans('ticket_machine.record.print_count'))->align('center');
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
            $grid->actions(function ($actions) {
                $actions->hideEdit();
                $actions->hideDel();
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
}
