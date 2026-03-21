<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\DepositBonusBetDetail;
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
 * 押码量明细管理
 * @group channel
 */
class ChannelDepositBonusBetDetailController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.deposit_bonus_bet_detail_model');
    }

    /**
     * 押码量明细列表
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('deposit_bonus_bet_detail.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 只显示当前渠道的押码明细
            $grid->model()->where('store_id', Admin::user()->department_id)
                ->with(['order', 'player'])
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
                sum(bet_amount) as total_bet,
                sum(valid_bet_amount) as total_valid_bet,
                sum(win_amount) as total_win
            ')->first();

            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value($totalData['total_count'] ?? 0)
                            ->prefix(admin_trans('deposit_bonus_bet_detail.stats.total_count'))
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
                            ->value(floatval($totalData['total_bet'] ?? 0))
                            ->prefix(admin_trans('deposit_bonus_bet_detail.stats.total_bet'))
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
                            ->value(floatval($totalData['total_valid_bet'] ?? 0))
                            ->prefix(admin_trans('deposit_bonus_bet_detail.stats.total_valid_bet'))
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
                            ->value(floatval($totalData['total_win'] ?? 0))
                            ->prefix(admin_trans('deposit_bonus_bet_detail.stats.total_win'))
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

            $grid->column('id', admin_trans('deposit_bonus_bet_detail.fields.id'))->align('center');

            $grid->column('order.order_no', admin_trans('deposit_bonus_bet_detail.fields.order_no'))
                ->display(function ($val, DepositBonusBetDetail $data) {
                    if (!$data->order) {
                        return '-';
                    }
                    return Html::create($val)->style([
                        'cursor' => 'pointer',
                        'color' => 'rgb(24, 144, 255)'
                    ])->drawer(['addons-webman-controller-ChannelDepositBonusOrderController', 'detail'], ['id' => $data->order_id]);
                })->align('center');

            $grid->column('player.account', admin_trans('deposit_bonus_bet_detail.fields.player_account'))
                ->display(function ($val, DepositBonusBetDetail $data) {
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

            $grid->column('game_type', admin_trans('deposit_bonus_bet_detail.fields.game_type'))
                ->display(function ($val) {
                    $typeMap = [
                        DepositBonusBetDetail::GAME_TYPE_SLOT => ['text' => admin_trans('deposit_bonus_bet_detail.game_type_slot'), 'color' => 'blue'],
                        DepositBonusBetDetail::GAME_TYPE_ELECTRON => ['text' => admin_trans('deposit_bonus_bet_detail.game_type_electron'), 'color' => 'green'],
                        DepositBonusBetDetail::GAME_TYPE_BACCARAT => ['text' => admin_trans('deposit_bonus_bet_detail.game_type_baccarat'), 'color' => 'purple'],
                        DepositBonusBetDetail::GAME_TYPE_LOTTERY => ['text' => admin_trans('deposit_bonus_bet_detail.game_type_lottery'), 'color' => 'orange'],
                    ];
                    $type = $typeMap[$val] ?? ['text' => $val, 'color' => 'default'];
                    return Tag::create($type['text'])->color($type['color']);
                })->align('center');

            $grid->column('game_name', admin_trans('deposit_bonus_bet_detail.fields.game_name'))->align('center');

            $grid->column('bet_amount', admin_trans('deposit_bonus_bet_detail.fields.bet_amount'))
                ->display(function ($val) {
                    return number_format($val, 2);
                })->align('center')->sortable();

            $grid->column('valid_bet_amount', admin_trans('deposit_bonus_bet_detail.fields.valid_bet_amount'))
                ->display(function ($val) {
                    return Tag::create(number_format($val, 2))->color('blue');
                })->align('center')->sortable();

            $grid->column('win_amount', admin_trans('deposit_bonus_bet_detail.fields.win_amount'))
                ->display(function ($val) {
                    $color = $val > 0 ? 'green' : ($val < 0 ? 'red' : 'default');
                    return Tag::create(number_format($val, 2))->color($color);
                })->align('center')->sortable();

            $grid->column('new_accumulated_bet', admin_trans('deposit_bonus_bet_detail.fields.accumulated_bet'))
                ->display(function ($val, DepositBonusBetDetail $data) {
                    return Html::create()->content([
                        Html::span()->content(number_format($data->accumulated_bet, 2))->style(['color' => '#999']),
                        Html::span()->content(' → ')->style(['color' => '#999', 'margin' => '0 5px']),
                        Html::span()->content(number_format($val, 2))->style(['color' => '#1890ff', 'font-weight' => '500'])
                    ]);
                })->align('center');

            $grid->column('bet_time', admin_trans('deposit_bonus_bet_detail.fields.bet_time'))
                ->display(function ($val) {
                    return $val ? date('Y-m-d H:i:s', $val) : '-';
                })->align('center')->sortable();

            $grid->column('created_at', admin_trans('deposit_bonus_bet_detail.fields.created_at'))
                ->display(function ($val) {
                    return date('Y-m-d H:i:s', $val);
                })->align('center');

            $grid->filter(function (Filter $filter) {
                $filter->like()->text('order.order_no')->placeholder(admin_trans('deposit_bonus_bet_detail.fields.order_no'));
                $filter->like()->text('player.account')->placeholder(admin_trans('deposit_bonus_bet_detail.fields.player_account'));
                $filter->eq()->select('game_type')
                    ->style(['width' => '200px'])
                    ->placeholder(admin_trans('deposit_bonus_bet_detail.fields.game_type'))
                    ->options([
                        DepositBonusBetDetail::GAME_TYPE_SLOT => admin_trans('deposit_bonus_bet_detail.game_type_slot'),
                        DepositBonusBetDetail::GAME_TYPE_ELECTRON => admin_trans('deposit_bonus_bet_detail.game_type_electron'),
                        DepositBonusBetDetail::GAME_TYPE_BACCARAT => admin_trans('deposit_bonus_bet_detail.game_type_baccarat'),
                        DepositBonusBetDetail::GAME_TYPE_LOTTERY => admin_trans('deposit_bonus_bet_detail.game_type_lottery'),
                    ]);
                $filter->like()->text('game_name')->placeholder(admin_trans('deposit_bonus_bet_detail.fields.game_name'));
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
            $grid->hideAdd();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            })->align('center');
        });
    }

    /**
     * 明细详情
     * @group channel
     * @auth true
     * @param int $id
     * @return Detail
     */
    public function detail(int $id): Detail
    {
        $data = DepositBonusBetDetail::where('id', $id)
            ->where('store_id', Admin::user()->department_id)
            ->with(['order', 'player'])
            ->first();

        return Detail::create($data, function (Detail $detail) use ($data) {
            $detail->item('id', admin_trans('deposit_bonus_bet_detail.fields.id'));
            $detail->item('order.order_no', admin_trans('deposit_bonus_bet_detail.fields.order_no'));
            $detail->item('player_info', admin_trans('deposit_bonus_bet_detail.fields.player_info'))
                ->display(function ($val, DepositBonusBetDetail $data) {
                    if (!$data->player) {
                        return '-';
                    }
                    return ($data->player->account ?? '-') . ' (' . ($data->player->nickname ?? '-') . ')';
                });
            $detail->item('game_type', admin_trans('deposit_bonus_bet_detail.fields.game_type'))
                ->display(function ($val) {
                    $typeMap = [
                        DepositBonusBetDetail::GAME_TYPE_SLOT => admin_trans('deposit_bonus_bet_detail.game_type_slot'),
                        DepositBonusBetDetail::GAME_TYPE_ELECTRON => admin_trans('deposit_bonus_bet_detail.game_type_electron'),
                        DepositBonusBetDetail::GAME_TYPE_BACCARAT => admin_trans('deposit_bonus_bet_detail.game_type_baccarat'),
                        DepositBonusBetDetail::GAME_TYPE_LOTTERY => admin_trans('deposit_bonus_bet_detail.game_type_lottery'),
                    ];
                    return $typeMap[$val] ?? $val;
                });
            $detail->item('game_platform', admin_trans('deposit_bonus_bet_detail.fields.game_platform'));
            $detail->item('game_name', admin_trans('deposit_bonus_bet_detail.fields.game_name'));
            $detail->item('bet_amount', admin_trans('deposit_bonus_bet_detail.fields.bet_amount'))
                ->display(function ($val) {
                    return number_format($val, 2);
                });
            $detail->item('valid_bet_amount', admin_trans('deposit_bonus_bet_detail.fields.valid_bet_amount'))
                ->display(function ($val) {
                    return number_format($val, 2);
                });
            $detail->item('win_amount', admin_trans('deposit_bonus_bet_detail.fields.win_amount'))
                ->display(function ($val) {
                    return number_format($val, 2);
                });
            $detail->item('balance_before', admin_trans('deposit_bonus_bet_detail.fields.balance_before'))
                ->display(function ($val) {
                    return number_format($val, 2);
                });
            $detail->item('balance_after', admin_trans('deposit_bonus_bet_detail.fields.balance_after'))
                ->display(function ($val) {
                    return number_format($val, 2);
                });
            $detail->item('accumulated_bet', admin_trans('deposit_bonus_bet_detail.fields.accumulated_bet'))
                ->display(function ($val) {
                    return number_format($val, 2);
                });
            $detail->item('new_accumulated_bet', admin_trans('deposit_bonus_bet_detail.fields.new_accumulated_bet'))
                ->display(function ($val) {
                    return number_format($val, 2);
                });
            $detail->item('bet_time', admin_trans('deposit_bonus_bet_detail.fields.bet_time'))
                ->display(function ($val) {
                    return $val ? date('Y-m-d H:i:s', $val) : '-';
                });
            $detail->item('settle_time', admin_trans('deposit_bonus_bet_detail.fields.settle_time'))
                ->display(function ($val) {
                    return $val ? date('Y-m-d H:i:s', $val) : '-';
                });
            $detail->item('created_at', admin_trans('deposit_bonus_bet_detail.fields.created_at'))
                ->display(function ($val) {
                    return date('Y-m-d H:i:s', $val);
                });
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
}
