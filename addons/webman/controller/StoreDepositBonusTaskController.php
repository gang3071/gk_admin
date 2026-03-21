<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\PlayerBonusTask;
use addons\webman\model\Player;
use addons\webman\model\AdminUser;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\grid\avatar\Avatar;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\grid\statistic\Statistic;
use ExAdmin\ui\component\grid\card\Card;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\support\Request;

/**
 * 打码量任务管理 - 店机后台
 * @group store
 */
class StoreDepositBonusTaskController
{
    protected $model;

    public function __construct()
    {
        $this->model = PlayerBonusTask::class;
    }

    /**
     * 打码量任务列表
     * @group store
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('deposit_bonus_task.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 只显示当前店家的任务
            $currentAdmin = Admin::user();
            $grid->model()->where('store_id', $currentAdmin->department_id)
                ->with(['player', 'order.activity'])
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
                sum(case when status = 0 then 1 else 0 end) as in_progress_count,
                sum(case when status = 1 then 1 else 0 end) as completed_count,
                sum(case when status = 2 then 1 else 0 end) as expired_count,
                sum(required_bet_amount) as total_required,
                sum(current_bet_amount) as total_current
            ')->first();

            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value($totalData['total_count'] ?? 0)
                            ->prefix(admin_trans('deposit_bonus_task.stats.total_count'))
                            ->valueStyle(['font-size' => '14px', 'font-weight' => '500', 'text-align' => 'center']))
                    ])->bodyStyle(['display' => 'flex', 'align-items' => 'center', 'height' => '30px', 'padding' => '0px'])
                        ->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                , 6);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value($totalData['in_progress_count'] ?? 0)
                            ->prefix(admin_trans('deposit_bonus_task.stats.in_progress'))
                            ->valueStyle(['font-size' => '14px', 'font-weight' => '500', 'text-align' => 'center', 'color' => '#1890ff']))
                    ])->bodyStyle(['display' => 'flex', 'align-items' => 'center', 'height' => '30px', 'padding' => '0px'])
                        ->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                , 6);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value($totalData['completed_count'] ?? 0)
                            ->prefix(admin_trans('deposit_bonus_task.stats.completed'))
                            ->valueStyle(['font-size' => '14px', 'font-weight' => '500', 'text-align' => 'center', 'color' => '#52c41a']))
                    ])->bodyStyle(['display' => 'flex', 'align-items' => 'center', 'height' => '30px', 'padding' => '0px'])
                        ->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                , 6);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value($totalData['expired_count'] ?? 0)
                            ->prefix(admin_trans('deposit_bonus_task.stats.expired'))
                            ->valueStyle(['font-size' => '14px', 'font-weight' => '500', 'text-align' => 'center', 'color' => '#ff4d4f']))
                    ])->bodyStyle(['display' => 'flex', 'align-items' => 'center', 'height' => '30px', 'padding' => '0px'])
                        ->hoverable()->headStyle(['height' => '0px', 'border-bottom' => '0px', 'min-height' => '0px'])
                , 6);
            });
            $grid->push($layout);

            $grid->column('id', admin_trans('deposit_bonus_task.fields.id'))->width(80)->align('center');

            $grid->column('player_id', admin_trans('deposit_bonus_task.fields.player'))
                ->display(function ($val, PlayerBonusTask $data) {
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

            $grid->column('activity_name', admin_trans('deposit_bonus_task.fields.activity'))
                ->display(function ($val, PlayerBonusTask $data) {
                    return $data->order->activity->activity_name ?? '-';
                })->align('center');

            $grid->column('bet_info', admin_trans('deposit_bonus_task.fields.bet_info'))
                ->display(function ($val, PlayerBonusTask $data) {
                    $html = '<div style="line-height: 1.6;">';
                    $html .= '<div><strong>' . admin_trans('deposit_bonus_task.required') . ':</strong> ' . number_format($data->required_bet_amount, 2) . '</div>';
                    $html .= '<div><strong>' . admin_trans('deposit_bonus_task.current') . ':</strong> ' . number_format($data->current_bet_amount, 2) . '</div>';
                    $html .= '<div><strong>' . admin_trans('deposit_bonus_task.remaining') . ':</strong> ' . number_format($data->getRemainingBetAmount(), 2) . '</div>';
                    $html .= '</div>';
                    return Html::create()->content($html);
                })->align('center');

            $grid->column('bet_progress', admin_trans('deposit_bonus_task.fields.progress'))
                ->display(function ($val, PlayerBonusTask $data) {
                    $progress = min(100, $data->bet_progress);
                    $color = $progress >= 100 ? 'success' : ($progress >= 50 ? 'normal' : 'exception');

                    return Html::create()->content(
                        '<div class="ant-progress ant-progress-line ant-progress-status-' . $color . '" style="width: 100px;">' .
                        '<div class="ant-progress-outer">' .
                        '<div class="ant-progress-inner">' .
                        '<div class="ant-progress-bg" style="width: ' . $progress . '%; height: 8px;"></div>' .
                        '</div></div>' .
                        '<span class="ant-progress-text">' . number_format($progress, 1) . '%</span>' .
                        '</div>'
                    );
                })->align('center');

            $grid->column('status', admin_trans('deposit_bonus_task.fields.status'))
                ->display(function ($val) {
                    switch ($val) {
                        case PlayerBonusTask::STATUS_IN_PROGRESS:
                            return Tag::create(admin_trans('deposit_bonus_task.status_in_progress'))->color('blue');
                        case PlayerBonusTask::STATUS_COMPLETED:
                            return Tag::create(admin_trans('deposit_bonus_task.status_completed'))->color('green');
                        case PlayerBonusTask::STATUS_EXPIRED:
                            return Tag::create(admin_trans('deposit_bonus_task.status_expired'))->color('red');
                        default:
                            return '-';
                    }
                })->width(100)->align('center');

            $grid->column('time_info', admin_trans('deposit_bonus_task.fields.time_info'))
                ->display(function ($val, PlayerBonusTask $data) {
                    $html = '<div style="line-height: 1.6;">';
                    $html .= '<div><strong>' . admin_trans('deposit_bonus_task.created_at') . ':</strong> ' . date('Y-m-d H:i', $data->created_at) . '</div>';
                    $html .= '<div><strong>' . admin_trans('deposit_bonus_task.expires_at') . ':</strong> ' . date('Y-m-d H:i', $data->expires_at) . '</div>';

                    if ($data->status == PlayerBonusTask::STATUS_IN_PROGRESS) {
                        $remainingDays = $data->getRemainingDays();
                        $color = $remainingDays > 3 ? '#52c41a' : ($remainingDays > 1 ? '#faad14' : '#ff4d4f');
                        $html .= '<div><strong>' . admin_trans('deposit_bonus_task.remaining_days') . ':</strong> <span style="color: ' . $color . ';">' . $remainingDays . admin_trans('deposit_bonus_activity.days') . '</span></div>';
                    }

                    if ($data->completed_at) {
                        $html .= '<div><strong>' . admin_trans('deposit_bonus_task.completed_at') . ':</strong> ' . date('Y-m-d H:i', $data->completed_at) . '</div>';
                    }

                    $html .= '</div>';
                    return Html::create()->content($html);
                })->align('center');

            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('status')
                    ->placeholder(admin_trans('deposit_bonus_task.fields.status'))
                    ->options([
                        PlayerBonusTask::STATUS_IN_PROGRESS => admin_trans('deposit_bonus_task.status_in_progress'),
                        PlayerBonusTask::STATUS_COMPLETED => admin_trans('deposit_bonus_task.status_completed'),
                        PlayerBonusTask::STATUS_EXPIRED => admin_trans('deposit_bonus_task.status_expired'),
                    ])
                    ->style(['width' => '150px']);

                $filter->like()->text('player.username')
                    ->placeholder(admin_trans('deposit_bonus_task.search_player'));

                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateRange('created_at_start', 'created_at_end', '')
                    ->placeholder([
                        admin_trans('deposit_bonus_activity.created_at_start'),
                        admin_trans('deposit_bonus_activity.created_at_end')
                    ]);
            });

            $grid->expandFilter();
            $grid->hideAdd();
            $grid->hideDelete();
            $grid->hideTrashed();
            $grid->actions(function ($actions) {
                $actions->hideEdit();
                $actions->hideDel();
                $actions->add(['addons-webman-controller-StoreDepositBonusTaskController', 'detail'], ['id' => '{id}'])
                    ->drawer()
                    ->content(admin_trans('deposit_bonus_task.view_detail'));
            })->align('center');
        });
    }

    /**
     * 任务详情
     * @group store
     * @auth true
     */
    public function detail(int $id): Detail
    {
        $currentAdmin = Admin::user();
        $task = PlayerBonusTask::where('id', $id)
            ->where('store_id', $currentAdmin->department_id)
            ->with(['player', 'order.activity', 'order.tier'])
            ->first();

        return Detail::create($task, function (Detail $detail) use ($task) {
            $detail->item('id', admin_trans('deposit_bonus_task.fields.id'));

            $detail->item('player_info', admin_trans('deposit_bonus_task.fields.player'))
                ->display(function ($val, PlayerBonusTask $data) {
                    $player = $data->player;
                    return $player ? ($player->username . ' (ID: ' . $player->id . ')') : '-';
                });

            $detail->item('activity_info', admin_trans('deposit_bonus_task.fields.activity'))
                ->display(function ($val, PlayerBonusTask $data) {
                    return $data->order->activity->activity_name ?? '-';
                });

            $detail->item('required_bet_amount', admin_trans('deposit_bonus_task.required'))
                ->display(function ($val) {
                    return number_format($val, 2);
                });

            $detail->item('current_bet_amount', admin_trans('deposit_bonus_task.current'))
                ->display(function ($val) {
                    return number_format($val, 2);
                });

            $detail->item('remaining', admin_trans('deposit_bonus_task.remaining'))
                ->display(function ($val, PlayerBonusTask $data) {
                    return number_format($data->getRemainingBetAmount(), 2);
                });

            $detail->item('bet_progress', admin_trans('deposit_bonus_task.fields.progress'))
                ->display(function ($val) {
                    return number_format($val, 2) . '%';
                });

            $detail->item('status', admin_trans('deposit_bonus_task.fields.status'))
                ->display(function ($val) {
                    switch ($val) {
                        case PlayerBonusTask::STATUS_IN_PROGRESS:
                            return Tag::create(admin_trans('deposit_bonus_task.status_in_progress'))->color('blue');
                        case PlayerBonusTask::STATUS_COMPLETED:
                            return Tag::create(admin_trans('deposit_bonus_task.status_completed'))->color('green');
                        case PlayerBonusTask::STATUS_EXPIRED:
                            return Tag::create(admin_trans('deposit_bonus_task.status_expired'))->color('red');
                        default:
                            return '-';
                    }
                });

            $detail->item('created_at', admin_trans('deposit_bonus_task.created_at'))
                ->display(function ($val) {
                    return date('Y-m-d H:i:s', $val);
                });

            $detail->item('expires_at', admin_trans('deposit_bonus_task.expires_at'))
                ->display(function ($val) {
                    return date('Y-m-d H:i:s', $val);
                });

            if ($task && $task->status == PlayerBonusTask::STATUS_IN_PROGRESS) {
                $detail->item('remaining_days', admin_trans('deposit_bonus_task.remaining_days'))
                    ->display(function ($val, PlayerBonusTask $data) {
                        return $data->getRemainingDays() . admin_trans('deposit_bonus_activity.days');
                    });
            }

            if ($task && $task->completed_at) {
                $detail->item('completed_at', admin_trans('deposit_bonus_task.completed_at'))
                    ->display(function ($val) {
                        return date('Y-m-d H:i:s', $val);
                    });
            }
        })->bordered()->layout('vertical');
    }
}
