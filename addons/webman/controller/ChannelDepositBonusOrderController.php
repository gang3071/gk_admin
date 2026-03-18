<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\DepositBonusActivity;
use addons\webman\model\DepositBonusTier;
use addons\webman\model\DepositBonusOrder;
use addons\webman\model\Player;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\support\Request;

/**
 * 充值满赠订单管理
 * @group channel
 */
class ChannelDepositBonusOrderController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.deposit_bonus_order_model');
    }

    /**
     * 充值满赠订单列表
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('deposit_bonus_order.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 只显示当前渠道的订单
            $grid->model()->where('store_id', Admin::user()->department_id)
                ->with(['activity', 'tier', 'player'])
                ->orderBy('created_at', 'desc');

            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', strtotime($exAdminFilter['created_at_start']));
            }
            if (!empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', strtotime($exAdminFilter['created_at_end']));
            }

            // 统计数据
            $query = clone $grid->model();
            $totalData = $query->selectRaw('
                count(*) as total_count,
                sum(deposit_amount) as total_deposit,
                sum(bonus_amount) as total_bonus,
                sum(IF(status = '. DepositBonusOrder::STATUS_COMPLETED .', 1, 0)) as completed_count
            ')->first();

            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value($totalData['total_count'] ?? 0)
                            ->prefix(admin_trans('deposit_bonus_order.stats.total_count'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center'
                            ]))
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
                            ->value(floatval($totalData['total_deposit'] ?? 0))
                            ->prefix(admin_trans('deposit_bonus_order.stats.total_deposit'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center'
                            ]))
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
                            ->value(floatval($totalData['total_bonus'] ?? 0))
                            ->prefix(admin_trans('deposit_bonus_order.stats.total_bonus'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center'
                            ]))
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
                            ->value($totalData['completed_count'] ?? 0)
                            ->prefix(admin_trans('deposit_bonus_order.stats.completed_count'))
                            ->valueStyle([
                                'font-size' => '14px',
                                'font-weight' => '500',
                                'text-align' => 'center'
                            ]))
                    ])->bodyStyle([
                        'display' => 'flex',
                        'align-items' => 'center',
                        'height' => '30px',
                        'padding' => '0px'
                    ])->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                , 6);
            })->style(['background' => '#fff']);

            $grid->tools([
                $layout
            ]);

            $grid->column('id', admin_trans('deposit_bonus_order.fields.id'))->align('center');
            $grid->column('order_no', admin_trans('deposit_bonus_order.fields.order_no'))
                ->display(function ($val, DepositBonusOrder $data) {
                    return Html::create($val)->style([
                        'cursor' => 'pointer',
                        'color' => 'rgb(24, 144, 255)'
                    ])->drawer(['addons-webman-controller-ChannelDepositBonusOrderController', 'detail'], ['id' => $data->id]);
                })->align('center');

            $grid->column('activity.activity_name', admin_trans('deposit_bonus_order.fields.activity_name'))->align('center');

            $grid->column('player.account', admin_trans('deposit_bonus_order.fields.player_account'))
                ->display(function ($val, DepositBonusOrder $data) {
                    if (!$data->player) {
                        return '-';
                    }
                    $image = !empty($data->player->avatar)
                        ? Avatar::create()->src($data->player->avatar)
                        : Avatar::create()->icon(Icon::create('UserOutlined'));
                    return Html::create()->content([
                        $image,
                        Html::div()->content($data->player->uuid ?? $val)
                    ])->style(['cursor' => 'pointer'])->modal($this->playerDetail([
                        'account' => $data->player->account ?? '',
                        'nickname' => $data->player->nickname ?? '',
                        'uuid' => $data->player->uuid ?? '',
                        'phone' => $data->player->phone ?? '',
                        'created_at' => !empty($data->player->created_at)
                            ? date('Y-m-d H:i:s', strtotime($data->player->created_at))
                            : '',
                    ]));
                })->align('center');

            $grid->column('deposit_amount', admin_trans('deposit_bonus_order.fields.deposit_amount'))
                ->display(function ($val) {
                    return number_format($val, 2);
                })->align('center');

            $grid->column('bonus_amount', admin_trans('deposit_bonus_order.fields.bonus_amount'))
                ->display(function ($val) {
                    return Tag::create(number_format($val, 2))->color('green');
                })->align('center');

            $grid->column('bet_progress', admin_trans('deposit_bonus_order.fields.bet_progress'))
                ->display(function ($val, DepositBonusOrder $data) {
                    $progress = $data->bet_progress ?? 0;
                    $color = $progress >= 100 ? 'green' : ($progress >= 50 ? 'orange' : 'blue');
                    $text = $progress . '% (' . number_format($data->current_bet_amount, 2) . '/' . number_format($data->required_bet_amount, 2) . ')';
                    return Tag::create($text)->color($color);
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
                    ->options($this->getActivityOptions());
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
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateTimeRange('created_at_start', 'created_at_end', '')->placeholder([
                    admin_trans('public_msg.created_at_start'),
                    admin_trans('public_msg.created_at_end')
                ]);
            });

            $grid->expandFilter();
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            })->align('center');
        });
    }

    /**
     * 订单详情
     * @group channel
     * @auth true
     * @param int $id
     * @return Detail
     */
    public function detail(int $id): Detail
    {
        $data = DepositBonusOrder::where('id', $id)
            ->where('store_id', Admin::user()->department_id)
            ->with(['activity', 'tier', 'player'])
            ->first();

        return Detail::create($data, function (Detail $detail) use ($data) {
            $detail->item('order_no', admin_trans('deposit_bonus_order.fields.order_no'));
            $detail->item('activity.activity_name', admin_trans('deposit_bonus_order.fields.activity_name'));
            $detail->item('player_info', admin_trans('deposit_bonus_order.fields.player_info'))
                ->display(function ($val, DepositBonusOrder $data) {
                    if (!$data->player) {
                        return '-';
                    }
                    return ($data->player->account ?? '-') . ' (' . ($data->player->nickname ?? '-') . ')';
                });
            $detail->item('deposit_amount', admin_trans('deposit_bonus_order.fields.deposit_amount'))
                ->display(function ($val) {
                    return number_format($val, 2);
                });
            $detail->item('bonus_amount', admin_trans('deposit_bonus_order.fields.bonus_amount'))
                ->display(function ($val) {
                    return number_format($val, 2);
                });
            $detail->item('required_bet_amount', admin_trans('deposit_bonus_order.fields.required_bet_amount'))
                ->display(function ($val) {
                    return number_format($val, 2);
                });
            $detail->item('current_bet_amount', admin_trans('deposit_bonus_order.fields.current_bet_amount'))
                ->display(function ($val) {
                    return number_format($val, 2);
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
                    if ($data->status == DepositBonusOrder::STATUS_PENDING && $val) {
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
     * 玩家详情
     * @param array $data
     * @return Detail
     */
    public function playerDetail(array $data): Detail
    {
        return Detail::create($data, function (Detail $detail) {
            $detail->item('account', admin_trans('player.fields.account'));
            $detail->item('nickname', admin_trans('player.fields.nickname'));
            $detail->item('uuid', admin_trans('player.fields.uuid'));
            $detail->item('phone', admin_trans('player.fields.phone'));
            $detail->item('created_at', admin_trans('player.fields.created_at'));
        })->layout('vertical');
    }

    /**
     * 获取活动选项
     * @return array
     */
    protected function getActivityOptions(): array
    {
        $activities = DepositBonusActivity::where('store_id', Admin::user()->department_id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get();

        $data = [];
        foreach ($activities as $activity) {
            $data[$activity->id] = $activity->activity_name;
        }
        return $data;
    }
}
