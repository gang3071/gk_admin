<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\DepositBonusActivity;
use addons\webman\model\DepositBonusOrder;
use addons\webman\model\Player;
use app\service\DepositBonusQrcodeService;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\support\Request;

/**
 * 充值满赠订单管理 - 代理后台
 * @group agent
 */
class AgentDepositBonusOrderController
{
    protected $model;
    protected $qrcodeService;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.deposit_bonus_order_model');
        $this->qrcodeService = new DepositBonusQrcodeService();
    }

    /**
     * 充值满赠订单列表
     * @group agent
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('deposit_bonus_order.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 只显示当前代理的订单
            $currentAdmin = Admin::user();
            $grid->model()->where('agent_id', $currentAdmin->id)
                ->with(['activity', 'tier', 'player'])
                ->orderBy('created_at', 'desc');

            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', strtotime($exAdminFilter['created_at_start']));
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', strtotime($exAdminFilter['created_at_end']));
            }

            $grid->column('id', admin_trans('deposit_bonus_order.fields.id'))->width(80)->align('center');

            $grid->column('order_no', admin_trans('deposit_bonus_order.fields.order_no'))
                ->display(function ($val, DepositBonusOrder $data) {
                    return Html::create($val)->style([
                        'cursor' => 'pointer',
                        'color' => 'rgb(24, 144, 255)'
                    ])->drawer(['addons-webman-controller-AgentDepositBonusOrderController', 'detail'], ['id' => $data->id]);
                })->align('center');

            $grid->column('player_id', admin_trans('deposit_bonus_order.fields.player'))
                ->display(function ($val, DepositBonusOrder $data) {
                    $player = $data->player;
                    if (!$player) return '-';

                    $avatar = !empty($player->avatar)
                        ? Avatar::create()->src($player->avatar)->size(30)
                        : Avatar::create()->text(mb_substr($player->username ?? 'U', 0, 1))->size(30);

                    return Html::create()->content([
                        $avatar,
                        Html::div()->content($player->username ?? '-')->style(['margin-left' => '8px'])
                    ]);
                })->width(150);

            $grid->column('activity_name', admin_trans('deposit_bonus_order.fields.activity'))
                ->display(function ($val, DepositBonusOrder $data) {
                    return $data->activity->activity_name ?? '-';
                })->align('center');

            $grid->column('amount_info', admin_trans('deposit_bonus_order.fields.amount_info'))
                ->display(function ($val, DepositBonusOrder $data) {
                    $html = '<div style="line-height: 1.6;">';
                    $html .= '<div><strong>' . admin_trans('deposit_bonus_order.deposit_amount') . ':</strong> ' . number_format($data->deposit_amount, 2) . '</div>';
                    $html .= '<div><strong>' . admin_trans('deposit_bonus_order.bonus_amount') . ':</strong> ' . number_format($data->bonus_amount, 2) . '</div>';
                    $html .= '</div>';
                    return Html::create()->content($html);
                })->align('center');

            $grid->column('bet_info', admin_trans('deposit_bonus_order.fields.bet_info'))
                ->display(function ($val, DepositBonusOrder $data) {
                    $html = '<div style="line-height: 1.6;">';
                    $html .= '<div><strong>' . admin_trans('deposit_bonus_order.required_bet') . ':</strong> ' . number_format($data->required_bet_amount, 2) . '</div>';
                    $html .= '<div><strong>' . admin_trans('deposit_bonus_order.current_bet') . ':</strong> ' . number_format($data->current_bet_amount, 2) . '</div>';
                    $html .= '<div><strong>' . admin_trans('deposit_bonus_order.progress') . ':</strong> ' . number_format($data->bet_progress, 1) . '%</div>';
                    $html .= '</div>';
                    return Html::create()->content($html);
                })->align('center');

            $grid->column('status', admin_trans('deposit_bonus_order.fields.status'))
                ->display(function ($val) {
                    switch ($val) {
                        case DepositBonusOrder::STATUS_PENDING:
                            return Tag::create(admin_trans('deposit_bonus_order.status_pending'))->color('orange');
                        case DepositBonusOrder::STATUS_VERIFIED:
                            return Tag::create(admin_trans('deposit_bonus_order.status_verified'))->color('blue');
                        case DepositBonusOrder::STATUS_COMPLETED:
                            return Tag::create(admin_trans('deposit_bonus_order.status_completed'))->color('green');
                        case DepositBonusOrder::STATUS_EXPIRED:
                            return Tag::create(admin_trans('deposit_bonus_order.status_expired'))->color('red');
                        case DepositBonusOrder::STATUS_CANCELLED:
                            return Tag::create(admin_trans('deposit_bonus_order.status_cancelled'))->color('default');
                        default:
                            return '-';
                    }
                })->width(100)->align('center');

            $grid->column('created_at', admin_trans('deposit_bonus_order.fields.created_at'))
                ->display(function ($val) {
                    return date('Y-m-d H:i:s', $val);
                })->align('center');

            $grid->filter(function (Filter $filter) {
                $filter->like()->text('order_no')
                    ->placeholder(admin_trans('deposit_bonus_order.search_order_no'));

                $filter->like()->text('player.username')
                    ->placeholder(admin_trans('deposit_bonus_order.search_player'));

                $filter->eq()->select('status')
                    ->placeholder(admin_trans('deposit_bonus_order.fields.status'))
                    ->options([
                        DepositBonusOrder::STATUS_PENDING => admin_trans('deposit_bonus_order.status_pending'),
                        DepositBonusOrder::STATUS_VERIFIED => admin_trans('deposit_bonus_order.status_verified'),
                        DepositBonusOrder::STATUS_COMPLETED => admin_trans('deposit_bonus_order.status_completed'),
                        DepositBonusOrder::STATUS_EXPIRED => admin_trans('deposit_bonus_order.status_expired'),
                        DepositBonusOrder::STATUS_CANCELLED => admin_trans('deposit_bonus_order.status_cancelled'),
                    ])
                    ->style(['width' => '150px']);

                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateRange('created_at_start', 'created_at_end', '')
                    ->placeholder([
                        admin_trans('deposit_bonus_activity.created_at_start'),
                        admin_trans('deposit_bonus_activity.created_at_end')
                    ]);
            });

            $grid->expandFilter();
            $grid->setForm()->drawer($this->form());
            $grid->hideEditButton();
            $grid->hideDelete();
            $grid->hideTrashed();
            $grid->actions(function ($actions) {
                $actions->hideEdit();
                $actions->hideDel();
                $actions->add(['addons-webman-controller-AgentDepositBonusOrderController', 'detail'], ['id' => '{id}'])
                    ->drawer()
                    ->content(admin_trans('deposit_bonus_order.view_detail'));
            })->align('center');
        });
    }

    /**
     * 生成订单表单
     * @group agent
     * @auth true
     */
    public function form(): Form
    {
        return Form::create([], function (Form $form) {
            $form->title(admin_trans('deposit_bonus_order.generate_order'));

            $currentAdmin = Admin::user();

            // 获取当前代理的活动
            $activities = DepositBonusActivity::where('agent_id', $currentAdmin->id)
                ->where('status', DepositBonusActivity::STATUS_ENABLED)
                ->where('start_time', '<=', time())
                ->where('end_time', '>=', time())
                ->get();

            if ($activities->isEmpty()) {
                $form->push(Html::markdown('><font size=3 color="#ff4d4f">暂无可用的充值满赠活动</font>'));
                return;
            }

            $activityOptions = [];
            foreach ($activities as $activity) {
                $activityOptions[$activity->id] = $activity->activity_name;
            }

            $form->select('activity_id', admin_trans('deposit_bonus_order.fields.activity'))
                ->options($activityOptions)
                ->required();

            // 根据活动加载档位（需要前端联动）
            $form->select('tier_id', admin_trans('deposit_bonus_order.fields.tier'))
                ->required();

            $form->text('player_username', admin_trans('deposit_bonus_order.player_username'))
                ->required()
                ->help('请输入玩家账号');

            $form->layout('vertical');

            $form->saving(function (Form $form) use ($currentAdmin) {
                $activityId = $form->input('activity_id');
                $tierId = $form->input('tier_id');
                $playerUsername = $form->input('player_username');

                // 查询玩家
                $player = Player::where('username', $playerUsername)->first();
                if (!$player) {
                    return message_error('玩家不存在');
                }

                try {
                    // 代理创建订单：agent_id为代理自己，store_id为0
                    $order = $this->qrcodeService->generateQrcodeOrder(
                        $activityId,
                        $tierId,
                        $player->id,
                        0,  // store_id: 代理创建订单时为0
                        $currentAdmin->id,  // created_by: 创建人
                        $currentAdmin->id   // agent_id: 代理自己的ID
                    );

                    return message_success('订单生成成功！订单号：' . $order->order_no);
                } catch (\Exception $e) {
                    return message_error($e->getMessage());
                }
            });
        });
    }

    /**
     * 订单详情
     * @group agent
     * @auth true
     */
    public function detail(int $id): Detail
    {
        $currentAdmin = Admin::user();
        $order = DepositBonusOrder::where('id', $id)
            ->where('agent_id', $currentAdmin->id)
            ->with(['activity', 'tier', 'player', 'task'])
            ->first();

        return Detail::create($order, function (Detail $detail) use ($order) {
            $detail->item('order_no', admin_trans('deposit_bonus_order.fields.order_no'));
            $detail->item('player_info', admin_trans('deposit_bonus_order.fields.player'))
                ->display(function ($val, DepositBonusOrder $data) {
                    $player = $data->player;
                    return $player ? ($player->username . ' (ID: ' . $player->id . ')') : '-';
                });
            $detail->item('activity_info', admin_trans('deposit_bonus_order.fields.activity'))
                ->display(function ($val, DepositBonusOrder $data) {
                    return $data->activity->activity_name ?? '-';
                });
            $detail->item('deposit_amount', admin_trans('deposit_bonus_order.deposit_amount'))
                ->display(function ($val) {
                    return number_format($val, 2);
                });
            $detail->item('bonus_amount', admin_trans('deposit_bonus_order.bonus_amount'))
                ->display(function ($val) {
                    return number_format($val, 2);
                });
            $detail->item('required_bet_amount', admin_trans('deposit_bonus_order.required_bet'))
                ->display(function ($val) {
                    return number_format($val, 2);
                });
            $detail->item('current_bet_amount', admin_trans('deposit_bonus_order.current_bet'))
                ->display(function ($val) {
                    return number_format($val, 2);
                });
            $detail->item('bet_progress', admin_trans('deposit_bonus_order.progress'))
                ->display(function ($val) {
                    return number_format($val, 2) . '%';
                });
            $detail->item('status', admin_trans('deposit_bonus_order.fields.status'))
                ->display(function ($val) {
                    switch ($val) {
                        case DepositBonusOrder::STATUS_PENDING:
                            return Tag::create(admin_trans('deposit_bonus_order.status_pending'))->color('orange');
                        case DepositBonusOrder::STATUS_VERIFIED:
                            return Tag::create(admin_trans('deposit_bonus_order.status_verified'))->color('blue');
                        case DepositBonusOrder::STATUS_COMPLETED:
                            return Tag::create(admin_trans('deposit_bonus_order.status_completed'))->color('green');
                        case DepositBonusOrder::STATUS_EXPIRED:
                            return Tag::create(admin_trans('deposit_bonus_order.status_expired'))->color('red');
                        case DepositBonusOrder::STATUS_CANCELLED:
                            return Tag::create(admin_trans('deposit_bonus_order.status_cancelled'))->color('default');
                        default:
                            return '-';
                    }
                });
            $detail->item('created_at', admin_trans('deposit_bonus_order.fields.created_at'))
                ->display(function ($val) {
                    return date('Y-m-d H:i:s', $val);
                });

            if ($order && $order->verified_at) {
                $detail->item('verified_at', admin_trans('deposit_bonus_order.verified_at'))
                    ->display(function ($val) {
                        return date('Y-m-d H:i:s', $val);
                    });
            }

            if ($order && $order->completed_at) {
                $detail->item('completed_at', admin_trans('deposit_bonus_order.completed_at'))
                    ->display(function ($val) {
                        return date('Y-m-d H:i:s', $val);
                    });
            }
        })->bordered()->layout('vertical');
    }
}
