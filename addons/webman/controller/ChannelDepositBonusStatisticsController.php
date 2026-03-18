<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\DepositBonusActivity;
use addons\webman\model\DepositBonusStatistics;
use ExAdmin\ui\component\common\Html;
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
 * 充值满赠统计报表
 * @group channel
 */
class ChannelDepositBonusStatisticsController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.deposit_bonus_statistics_model');
    }

    /**
     * 充值满赠统计列表
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        return Grid::create(new $this->model(), function (Grid $grid) {
            $grid->title(admin_trans('deposit_bonus_statistics.title'));
            $grid->autoHeight();
            $grid->bordered(true);

            // 只显示当前渠道的统计数据
            $grid->model()->where('store_id', Admin::user()->department_id)
                ->with(['activity'])
                ->orderBy('stat_date', 'desc');

            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (!empty($exAdminFilter['stat_date_start'])) {
                $grid->model()->where('stat_date', '>=', $exAdminFilter['stat_date_start']);
            }
            if (!empty($exAdminFilter['stat_date_end'])) {
                $grid->model()->where('stat_date', '<=', $exAdminFilter['stat_date_end']);
            }

            // 汇总统计
            $query = clone $grid->model();
            $totalData = $query->selectRaw('
                sum(total_participants) as sum_participants,
                sum(total_orders) as sum_orders,
                sum(total_deposit_amount) as sum_deposit,
                sum(total_bonus_amount) as sum_bonus,
                sum(total_bet_amount) as sum_bet,
                sum(completed_orders) as sum_completed
            ')->first();

            $layout = Layout::create();
            $layout->row(function (Row $row) use ($totalData) {
                $row->gutter([10, 0]);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value($totalData['sum_participants'] ?? 0)
                            ->prefix(admin_trans('deposit_bonus_statistics.stats.total_participants'))
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
                , 4);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value($totalData['sum_orders'] ?? 0)
                            ->prefix(admin_trans('deposit_bonus_statistics.stats.total_orders'))
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
                , 4);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(floatval($totalData['sum_deposit'] ?? 0))
                            ->prefix(admin_trans('deposit_bonus_statistics.stats.total_deposit'))
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
                , 4);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(floatval($totalData['sum_bonus'] ?? 0))
                            ->prefix(admin_trans('deposit_bonus_statistics.stats.total_bonus'))
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
                , 4);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value(floatval($totalData['sum_bet'] ?? 0))
                            ->prefix(admin_trans('deposit_bonus_statistics.stats.total_bet'))
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
                , 4);
                $row->column(
                    Card::create([
                        Row::create()->column(Statistic::create()
                            ->value($totalData['sum_completed'] ?? 0)
                            ->prefix(admin_trans('deposit_bonus_statistics.stats.completed_orders'))
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
                , 4);
            })->style(['background' => '#fff']);

            $grid->tools([
                $layout
            ]);

            $grid->column('id', admin_trans('deposit_bonus_statistics.fields.id'))->align('center');
            $grid->column('stat_date', admin_trans('deposit_bonus_statistics.fields.stat_date'))
                ->display(function ($val) {
                    return Html::create($val)->style([
                        'font-weight' => '500',
                        'color' => '#1890ff'
                    ]);
                })->align('center')->sortable();

            $grid->column('activity.activity_name', admin_trans('deposit_bonus_statistics.fields.activity_name'))
                ->align('center');

            $grid->column('total_participants', admin_trans('deposit_bonus_statistics.fields.total_participants'))
                ->display(function ($val) {
                    return Tag::create($val)->color('blue');
                })->align('center')->sortable();

            $grid->column('total_orders', admin_trans('deposit_bonus_statistics.fields.total_orders'))
                ->align('center')->sortable();

            $grid->column('total_deposit_amount', admin_trans('deposit_bonus_statistics.fields.total_deposit_amount'))
                ->display(function ($val) {
                    return number_format($val, 2);
                })->align('center')->sortable();

            $grid->column('total_bonus_amount', admin_trans('deposit_bonus_statistics.fields.total_bonus_amount'))
                ->display(function ($val) {
                    return Tag::create(number_format($val, 2))->color('green');
                })->align('center')->sortable();

            $grid->column('total_bet_amount', admin_trans('deposit_bonus_statistics.fields.total_bet_amount'))
                ->display(function ($val) {
                    return number_format($val, 2);
                })->align('center')->sortable();

            $grid->column('completed_orders', admin_trans('deposit_bonus_statistics.fields.completed_orders'))
                ->display(function ($val, DepositBonusStatistics $data) {
                    $total = $data->total_orders;
                    $rate = $total > 0 ? round(($val / $total) * 100, 2) : 0;
                    return Html::create()->content([
                        Tag::create($val)->color('green'),
                        Html::span()->content(' (' . $rate . '%)')->style(['color' => '#999', 'font-size' => '12px'])
                    ]);
                })->align('center')->sortable();

            $grid->column('expired_orders', admin_trans('deposit_bonus_statistics.fields.expired_orders'))
                ->display(function ($val) {
                    return $val > 0 ? Tag::create($val)->color('red') : $val;
                })->align('center')->sortable();

            $grid->column('cancelled_orders', admin_trans('deposit_bonus_statistics.fields.cancelled_orders'))
                ->display(function ($val) {
                    return $val > 0 ? Tag::create($val)->color('gray') : $val;
                })->align('center')->sortable();

            $grid->column('updated_at', admin_trans('deposit_bonus_statistics.fields.updated_at'))
                ->display(function ($val) {
                    return $val ? date('Y-m-d H:i:s', $val) : '-';
                })->align('center');

            $grid->filter(function (Filter $filter) {
                $filter->eq()->select('activity_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->placeholder(admin_trans('deposit_bonus_statistics.fields.activity_name'))
                    ->options($this->getActivityOptions());
                $filter->form()->hidden('stat_date_start');
                $filter->form()->hidden('stat_date_end');
                $filter->form()->dateRange('stat_date_start', 'stat_date_end', '')->placeholder([
                    admin_trans('deposit_bonus_statistics.stat_date_start'),
                    admin_trans('deposit_bonus_statistics.stat_date_end')
                ]);
            });

            $grid->expandFilter();
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->hideCreateButton();
            $grid->hideTrashed();
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
                $actions->hideDetail();
            })->align('center');
        });
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
