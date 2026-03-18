<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\DepositBonusActivity;
use addons\webman\model\DepositBonusTier;
use addons\webman\model\DepositBonusOrder;
use addons\webman\model\Player;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use support\Db;

/**
 * 充值满赠二维码管理
 */
class DepositBonusQrcodeController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.deposit_bonus_order_model');
    }

    /**
     * 充值满赠订单列表
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('deposit_bonus_order.title'));
            $grid->autoHeight();
            $grid->bordered(true);
            $grid->model()->with(['activity', 'tier', 'player'])->orderBy('created_at', 'desc');

            $grid->column('id', admin_trans('deposit_bonus_order.fields.id'))->align('center');
            $grid->column('order_no', admin_trans('deposit_bonus_order.fields.order_no'))
                ->display(function ($val, DepositBonusOrder $data) {
                    return Html::create($val)->style([
                        'cursor' => 'pointer',
                        'color' => 'rgb(24, 144, 255)'
                    ])->drawer(['addons-webman-controller-DepositBonusQrcodeController', 'detail'], ['id' => $data->id]);
                })->align('center');

            $grid->column('activity.activity_name', admin_trans('deposit_bonus_order.fields.activity_name'))->align('center');

            $grid->column('player.account', admin_trans('deposit_bonus_order.fields.player_account'))->align('center');

            $grid->column('deposit_amount', admin_trans('deposit_bonus_order.fields.deposit_amount'))
                ->display(function ($val) {
                    return '¥' . number_format($val, 2);
                })->align('center');

            $grid->column('bonus_amount', admin_trans('deposit_bonus_order.fields.bonus_amount'))
                ->display(function ($val) {
                    return Tag::create('¥' . number_format($val, 2))->color('green');
                })->align('center');

            $grid->column('bet_progress', admin_trans('deposit_bonus_order.fields.bet_progress'))
                ->display(function ($val, DepositBonusOrder $data) {
                    $progress = $data->bet_progress ?? 0;
                    $color = $progress >= 100 ? 'green' : ($progress >= 50 ? 'orange' : 'blue');
                    return Tag::create($progress . '%')->color($color);
                })->align('center');

            $grid->column('status', admin_trans('deposit_bonus_order.fields.status'))
                ->display(function ($val) {
                    $statusMap = [
                        DepositBonusOrder::STATUS_PENDING => ['text' => admin_trans('deposit_bonus_order.status_pending'), 'color' => 'orange'],
                        DepositBonusOrder::STATUS_VERIFIED => ['text' => admin_trans('deposit_bonus_order.status_verified'), 'color' => 'blue'],
                        DepositBonusOrder::STATUS_COMPLETED => ['text' => admin_trans('deposit_bonus_order.status_completed'), 'color' => 'green'],
                        DepositBonusOrder::STATUS_EXPIRED => ['text' => admin_trans('deposit_bonus_order.status_expired'), 'color' => 'red'],
                        DepositBonusOrder::STATUS_CANCELLED => ['text' => admin_trans('deposit_bonus_order.status_cancelled'), 'color' => 'gray'],
                    ];
                    $status = $statusMap[$val] ?? ['text' => admin_trans('deposit_bonus_order.status_unknown'), 'color' => 'default'];
                    return Tag::create($status['text'])->color($status['color']);
                })->align('center');

            $grid->column('expires_at', admin_trans('deposit_bonus_order.fields.expires_at'))
                ->display(function ($val) {
                    return $val ? date('Y-m-d H:i', $val) : '-';
                })->align('center');

            $grid->column('created_at', admin_trans('deposit_bonus_order.fields.created_at'))
                ->display(function ($val) {
                    return date('Y-m-d H:i:s', $val);
                })->align('center');

            $grid->filter(function (Filter $filter) {
                $filter->like()->text('order_no')->placeholder(admin_trans('deposit_bonus_order.fields.order_no'));
                $filter->like()->text('player.account')->placeholder(admin_trans('deposit_bonus_order.fields.player_account'));
                $filter->eq()->select('activity_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->placeholder(admin_trans('deposit_bonus_order.fields.activity_name'))
                    ->remoteOptions(admin_url(['addons-webman-controller-DepositBonusQrcodeController', 'getActivityOptions']));
                $filter->eq()->select('status')
                    ->style(['width' => '200px'])
                    ->placeholder(admin_trans('deposit_bonus_order.fields.status'))
                    ->options([
                        DepositBonusOrder::STATUS_PENDING => admin_trans('deposit_bonus_order.status_pending'),
                        DepositBonusOrder::STATUS_VERIFIED => admin_trans('deposit_bonus_order.status_verified'),
                        DepositBonusOrder::STATUS_COMPLETED => admin_trans('deposit_bonus_order.status_completed'),
                        DepositBonusOrder::STATUS_EXPIRED => admin_trans('deposit_bonus_order.status_expired'),
                        DepositBonusOrder::STATUS_CANCELLED => admin_trans('deposit_bonus_order.status_cancelled'),
                    ]);
            });

            $grid->expandFilter();
            $grid->setForm()->drawer($this->form());
            $grid->hideDelete();
            $grid->actions(function (Actions $actions) {
                $actions->hideEdit();
            })->align('center');
        });
    }

    /**
     * 生成二维码订单表单
     * @auth true
     * @return Form
     */
    public function form(): Form
    {
        return Form::create(new $this->model(), function (Form $form) {
            $form->title(admin_trans('deposit_bonus_order.generate_title'));

            $form->select('activity_id', admin_trans('deposit_bonus_order.fields.activity_id'))
                ->options($this->getActivityOptionsSimple())
                ->required()
                ->help(admin_trans('deposit_bonus_order.help.activity_id'));

            $form->number('deposit_amount', admin_trans('deposit_bonus_order.fields.deposit_amount'))
                ->min(0)
                ->step(0.01)
                ->required()
                ->help(admin_trans('deposit_bonus_order.help.deposit_amount'));

            $form->select('player_id', admin_trans('deposit_bonus_order.fields.player_id'))
                ->remoteOptions(admin_url(['addons-webman-controller-PlayerController', 'searchPlayer']))
                ->showSearch()
                ->required()
                ->help(admin_trans('deposit_bonus_order.help.player_id'));

            $form->layout('vertical');

            $form->saving(function (Form $form) {
                try {
                    // 只允许新增
                    if ($form->isEdit()) {
                        return message_error(admin_trans('deposit_bonus_order.cannot_edit'));
                    }

                    Db::beginTransaction();

                    $activityId = $form->input('activity_id');
                    $depositAmount = $form->input('deposit_amount');
                    $playerId = $form->input('player_id');

                    // 验证活动
                    $activity = DepositBonusActivity::find($activityId);
                    if (!$activity || !$activity->isValid()) {
                        Db::rollBack();
                        return message_error(admin_trans('deposit_bonus_order.activity_invalid'));
                    }

                    // 查找匹配的档位
                    $tier = DepositBonusTier::where('activity_id', $activityId)
                        ->where('deposit_amount', $depositAmount)
                        ->where('status', DepositBonusTier::STATUS_ENABLED)
                        ->first();
                    if (!$tier) {
                        Db::rollBack();
                        return message_error(admin_trans('deposit_bonus_order.tier_not_match'));
                    }

                    // 验证玩家
                    $player = Player::find($playerId);
                    if (!$player) {
                        Db::rollBack();
                        return message_error(admin_trans('deposit_bonus_order.player_not_found'));
                    }

                    // 检查玩家参与次数限制
                    if (!$activity->checkPlayerLimit($playerId)) {
                        Db::rollBack();
                        return message_error(admin_trans('deposit_bonus_order.player_limit_exceeded'));
                    }

                    // 创建订单
                    $order = new DepositBonusOrder();
                    $order->order_no = DepositBonusOrder::generateOrderNo();
                    $order->activity_id = $activity->id;
                    $order->tier_id = $tier->id;
                    $order->store_id = $activity->store_id;
                    $order->player_id = $playerId;
                    $order->deposit_amount = $tier->deposit_amount;
                    $order->bonus_amount = $tier->bonus_amount;
                    $order->required_bet_amount = $tier->calculateRequiredBet($activity->bet_multiple);
                    $order->current_bet_amount = 0;
                    $order->bet_progress = 0;
                    $order->qrcode_token = md5(uniqid() . $order->order_no . time());
                    $order->qrcode_expires_at = time() + 1800; // 30分钟有效期
                    $order->status = DepositBonusOrder::STATUS_PENDING;
                    $order->expires_at = time() + ($activity->valid_days * 86400);
                    $order->created_by = Admin::id();
                    $order->created_at = time();
                    $order->save();

                    Db::commit();
                    return message_success(admin_trans('deposit_bonus_order.generate_success'));

                } catch (\Exception $e) {
                    Db::rollBack();
                    return message_error(admin_trans('deposit_bonus_order.generate_fail') . ': ' . $e->getMessage());
                }
            });
        });
    }

    /**
     * 订单详情
     * @auth true
     * @param int $id
     * @return Detail
     */
    public function detail(int $id): Detail
    {
        $data = DepositBonusOrder::with(['activity', 'tier', 'player'])->find($id);
        return Detail::create($data, function (Detail $detail) use ($data) {
            $detail->item('order_no', admin_trans('deposit_bonus_order.fields.order_no'));
            $detail->item('activity.activity_name', admin_trans('deposit_bonus_order.fields.activity_name'));
            $detail->item('player_info', admin_trans('deposit_bonus_order.fields.player_info'))
                ->display(function ($val, DepositBonusOrder $data) {
                    return $data->player->account . ' (' . $data->player->nickname . ')';
                });
            $detail->item('deposit_amount', admin_trans('deposit_bonus_order.fields.deposit_amount'))
                ->display(function ($val) {
                    return '¥' . number_format($val, 2);
                });
            $detail->item('bonus_amount', admin_trans('deposit_bonus_order.fields.bonus_amount'))
                ->display(function ($val) {
                    return '¥' . number_format($val, 2);
                });
            $detail->item('required_bet_amount', admin_trans('deposit_bonus_order.fields.required_bet_amount'))
                ->display(function ($val) {
                    return '¥' . number_format($val, 2);
                });
            $detail->item('current_bet_amount', admin_trans('deposit_bonus_order.fields.current_bet_amount'))
                ->display(function ($val) {
                    return '¥' . number_format($val, 2);
                });
            $detail->item('bet_progress', admin_trans('deposit_bonus_order.fields.bet_progress'))
                ->display(function ($val, DepositBonusOrder $data) {
                    $progress = $data->bet_progress ?? 0;
                    return $progress . '%';
                });
            $detail->item('status', admin_trans('deposit_bonus_order.fields.status'))
                ->display(function ($val, DepositBonusOrder $data) {
                    return $data->getStatusText();
                });
            $detail->item('qrcode_token', admin_trans('deposit_bonus_order.fields.qrcode_token'))
                ->display(function ($val, DepositBonusOrder $data) {
                    if ($data->status == DepositBonusOrder::STATUS_PENDING) {
                        return $val;
                    }
                    return '-';
                });
            $detail->item('qrcode_expires_at', admin_trans('deposit_bonus_order.fields.qrcode_expires_at'))
                ->display(function ($val) {
                    return $val ? date('Y-m-d H:i:s', $val) : '-';
                });
            $detail->item('expires_at', admin_trans('deposit_bonus_order.fields.expires_at'))
                ->display(function ($val) {
                    return $val ? date('Y-m-d H:i:s', $val) : '-';
                });
            $detail->item('verified_at', admin_trans('deposit_bonus_order.fields.verified_at'))
                ->display(function ($val) {
                    return $val ? date('Y-m-d H:i:s', $val) : '-';
                });
            $detail->item('completed_at', admin_trans('deposit_bonus_order.fields.completed_at'))
                ->display(function ($val) {
                    return $val ? date('Y-m-d H:i:s', $val) : '-';
                });
            $detail->item('created_at', admin_trans('deposit_bonus_order.fields.created_at'))
                ->display(function ($val) {
                    return date('Y-m-d H:i:s', $val);
                });
            $detail->item('remark', admin_trans('deposit_bonus_order.fields.remark'));
        })->bordered()->layout('vertical');
    }

    /**
     * 获取活动选项（简化版）
     * @return array
     */
    protected function getActivityOptionsSimple(): array
    {
        $activities = DepositBonusActivity::where('status', DepositBonusActivity::STATUS_ENABLED)
            ->whereNull('deleted_at')
            ->where('end_time', '>', time())
            ->orderBy('created_at', 'desc')
            ->get();

        $data = [];
        foreach ($activities as $activity) {
            $data[$activity->id] = $activity->activity_name . ' (' . date('Y-m-d', $activity->start_time) . ' ~ ' . date('Y-m-d', $activity->end_time) . ')';
        }
        return $data;
    }

    /**
     * 获取活动选项（远程搜索）
     * @auth true
     * @return array
     */
    public function getActivityOptions(): array
    {
        $keyword = request()->input('keyword', '');

        $query = DepositBonusActivity::where('status', DepositBonusActivity::STATUS_ENABLED)
            ->whereNull('deleted_at')
            ->where('end_time', '>', time());

        if ($keyword) {
            $query->where('activity_name', 'like', '%' . $keyword . '%');
        }

        $activities = $query->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $data = [];
        foreach ($activities as $activity) {
            $data[] = [
                'label' => $activity->activity_name . ' (' . date('Y-m-d', $activity->start_time) . ' ~ ' . date('Y-m-d', $activity->end_time) . ')',
                'value' => $activity->id,
            ];
        }
        return $data;
    }
}