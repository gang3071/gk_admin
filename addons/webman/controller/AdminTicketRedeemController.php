<?php

declare(strict_types=1);

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\TicketRecord;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;

/**
 * 核销记录管理（总后台）
 * @group admin
 */
class AdminTicketRedeemController
{
    /**
     * 核销记录列表
     * @group admin
     * @auth true
     * @return Grid
     */
    public function index(): Grid
    {
        return Grid::create(new TicketRecord(), function (Grid $grid) {
            $grid->title(admin_trans('ticket_machine.redeem.title'));
            $grid->autoHeight();

            // 只显示洗分类型数据
            $grid->model()
                ->select(['qr_ticket_record.*', 'player.avatar as player_avatar'])
                ->leftJoin('player', 'qr_ticket_record.player_id', '=', 'player.id')
                ->where('qr_ticket_record.ticket_type', TicketRecord::TYPE_WITHDRAW)
                ->orderBy('qr_ticket_record.created_at', 'desc');

            // 统计数据
            $totalData = TicketRecord::query()
                ->where('ticket_type', TicketRecord::TYPE_WITHDRAW)
                ->selectRaw(
                    'sum(score) as total_score, count(*) as total_count, sum(IF(status = ' . TicketRecord::STATUS_USED . ', 1, 0)) as used_count, sum(IF(status = ' . TicketRecord::STATUS_USED . ', score, 0)) as used_score'
                )
                ->first();

            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
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
                    , 6);
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
                    , 6);
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
                    , 6);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(!empty($totalData['used_score']) ? floatval($totalData['used_score']) : 0)
                            ->prefix(admin_trans('ticket_machine.redeem.used_score'))
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

            $grid->tools([$layout]);

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
            $grid->column('qr_code_no', admin_trans('ticket_machine.redeem.qr_code_no'))->copy();
            $grid->column('status', admin_trans('ticket_machine.redeem.status'))->display(function ($val) {
                return match ($val) {
                    TicketRecord::STATUS_DISABLED => Tag::create(admin_trans('ticket_machine.redeem.status_disabled'))->color('default'),
                    TicketRecord::STATUS_NORMAL => Tag::create(admin_trans('ticket_machine.redeem.status_normal'))->color('blue'),
                    TicketRecord::STATUS_USED => Tag::create(admin_trans('ticket_machine.redeem.status_used'))->color('orange'),
                    default => Tag::create(admin_trans('ticket_machine.redeem.status_unknown'))->color('default'),
                };
            });
            $grid->column('created_at', admin_trans('ticket_machine.redeem.created_at'))->sortable();

            // 筛选器
            $grid->filter(function (Filter $filter) {
                $filter->expand();
                $filter->like('order_id', admin_trans('ticket_machine.redeem.order_id'));
                $filter->like('qr_code_no', admin_trans('ticket_machine.redeem.qr_code_no'));
                $filter->like('machine_no', admin_trans('ticket_machine.redeem.machine_no'));
                $filter->eq()->select('store_name')
                    ->placeholder(admin_trans('ticket_machine.redeem.store_name'))
                    ->options(['' => admin_trans('public_msg.all')])
                    ->style(['width' => '150px']);
                $filter->eq()->select('status')
                    ->placeholder(admin_trans('ticket_machine.redeem.status'))
                    ->options([
                        '' => admin_trans('public_msg.all'),
                        TicketRecord::STATUS_DISABLED => admin_trans('ticket_machine.redeem.status_disabled'),
                        TicketRecord::STATUS_NORMAL => admin_trans('ticket_machine.redeem.status_normal'),
                        TicketRecord::STATUS_USED => admin_trans('ticket_machine.redeem.status_used'),
                    ])
                    ->style(['width' => '150px']);
                $filter->between('created_at', admin_trans('ticket_machine.redeem.created_at'))->datetime();
            });

            $grid->disableCreateButton();
            $grid->hideDelete();

            // 操作列 - 只保留查看
            $grid->actions(function ($actions) {
                $actions->hideEdit();
                $actions->hideDel();
            });
        });
    }

    /**
     * 查看详情
     * @group admin
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
                    TicketRecord::STATUS_USED => admin_trans('ticket_machine.redeem.status_used'),
                    default => admin_trans('ticket_machine.redeem.status_unknown'),
                };
            });
            $form->desc('print_count', admin_trans('ticket_machine.redeem.print_count'));
            $form->desc('last_print_time', admin_trans('ticket_machine.redeem.last_print_time'));
            $form->desc('remark', admin_trans('ticket_machine.redeem.remark'));
            $form->desc('created_at', admin_trans('ticket_machine.redeem.created_at'));

            $form->disableSubmit();
        });
    }
}
