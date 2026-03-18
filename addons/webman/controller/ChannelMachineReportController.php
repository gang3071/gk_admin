<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\GameType;
use addons\webman\model\MachineReport;
use ExAdmin\ui\component\common\Button;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\form\field\select\SelectGroup;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\FilterColumn;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\support\Request;

/**
 * 机台报表
 * @group channel
 */
class ChannelMachineReportController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.machine_report_model');
    }

    /**
     * 机台报表
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        $page = Request::input('ex_admin_page', 1);
        $size = Request::input('ex_admin_size', 20);
        $exAdminFilter = Request::input('ex_admin_filter', []);
        $machineReport = MachineReport::with(['machine', 'machine.producer', 'machine.machineLabel']);
        $exAdminSortBy = Request::input('ex_admin_sort_by', '');
        $exAdminSortField = Request::input('ex_admin_sort_field', '');
        if (!empty($exAdminFilter)) {
            if (!empty($exAdminFilter['date_start'])) {
                $machineReport->where('date', '>=', $exAdminFilter['date_start']);
            }
            if (!empty($exAdminFilter['date_end'])) {
                $machineReport->where('date', '<=', $exAdminFilter['date_end']);
            }
            if (!empty($exAdminFilter['machine'])) {
                $machineReport->whereHas('machine', function ($query) use ($exAdminFilter) {
                    if (!empty($exAdminFilter['machine']['name'])) {
                        $query->whereHas('machineLabel', function ($query) use ($exAdminFilter) {
                            $query->where('name', 'like', '%'.$exAdminFilter['machine']['name'].'%');
                        });
                    }
                    if (!empty($exAdminFilter['machine']['code'])) {
                        $query->where('code', $exAdminFilter['machine']['code']);
                    }
                    if (!empty($exAdminFilter['machine']['producer_id'])) {
                        $query->where('producer_id', $exAdminFilter['machine']['producer_id']);
                    }
                    if (!empty($exAdminFilter['machine']['cate_id'])) {
                        $query->whereIn('cate_id', $exAdminFilter['machine']['cate_id']);
                    }
                });
            }
        }
        $machineReport = $machineReport->selectRaw('
                        machine_id,
                        sum(open_amount) as open_amount,
                        sum(wash_amount) as wash_amount,
                        sum(total_amount) as total_amount,
                        sum(open_point) as open_point,
                        sum(wash_point) as wash_point,
                        sum(total_point) as total_point,
                        sum(pressure) as total_pressure,
                        sum(score) as total_score,
                        sum(pressure - score) as total_diff,
                        sum(turn_point) as total_turn_point,
                        sum(lottery_amount) as lottery_amount,
                        sum(activity_amount) as activity_amount,
                        sum(open_amount-wash_amount-lottery_amount-activity_amount) as machine_total_point,
                        odds');

        $where = [];
        if (isset($exAdminFilter['date_type'])) {
            $where = getWhereDate($exAdminFilter['date_type'], 'date');
        }
        $machineReport->where($where);
        $totalNum = clone $machineReport;
        $total = $totalNum->groupBy('machine_id', 'odds')->get()->count();
        $list = $machineReport
            ->forPage($page, $size)
            ->when(!empty($exAdminSortField) && !empty($exAdminSortBy),
                function ($query) use ($exAdminSortField, $exAdminSortBy) {
                    $query->orderBy($exAdminSortField, $exAdminSortBy);
                }, function ($query) {
                    $query->orderBy('machine_id', 'desc');
                })
            ->groupBy('machine_id', 'odds')
            ->get()
            ->toArray();
        return Grid::create($list, function (Grid $grid) use ($total, $list, $exAdminFilter) {
            $grid->title(admin_trans('machine_report.title'));
            $grid->bordered(true);
            $grid->autoHeight();
            $grid->autoHeight();
            $grid->driver()->setPk('machine_id');
            $grid->column('machine_id', admin_trans('machine_report.fields.machine_id'))->align('center');
            $grid->column('machine.code', admin_trans('machine.fields.code'))->display(function ($val, $data) {
                if ($data['machine']) {
                    return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal([
                        'addons-webman-controller-PlayerDeliveryRecordController',
                        'machineInfo'
                    ],
                        ['data' => $data['machine']])->width('60%')->title((!empty($data['machine']['machine_label']['name']) ? $data['machine']['machine_label']['name'] : '') . ':' . $data['machine']['name']);
                }
                return '';
            })->align('center');
            $grid->column('machine.code', admin_trans('machine.fields.code'))->align('center')
                ->filter(
                    FilterColumn::like()->text('machine.code')
                );
            $grid->column('type', admin_trans('machine_category.fields.type'))->display(function ($val, $data) {
                $tag = '';
                switch ($data['machine']['type']) {
                    case GameType::TYPE_STEEL_BALL:
                        $tag = Tag::create(admin_trans('game_type.game_type.' . $data['machine']['type']))->color('#f50');
                        break;
                    case GameType::TYPE_SLOT:
                        $tag = Tag::create(admin_trans('game_type.game_type.' . $data['machine']['type']))->color('#2db7f5');
                        break;
                }
                return Html::create()->content([
                    $tag
                ]);
            })->align('center');
            $grid->column('machine.name', admin_trans('machine.fields.name'))->display(function ($val, $data) {
                return Html::create()->content([
                    $data['machine']['machine_label']['name']
                ]);
            })->align('center');
            $grid->column('producer_id', admin_trans('machine.fields.producer_id'))->display(function ($val, $data) {
                return Html::create()->content([
                    !empty($data['machine']['producer']['name']) ? Tag::create($data['machine']['producer']['name'])->color('green') : ''
                ]);
            })->align('center');
            $grid->column('odds', admin_trans('machine_report.fields.odds'))->sortable()->align('center');
            $grid->column('open_amount',
                admin_trans('machine_report.fields.open_amount'))->sortable()->display(function ($val) {
                return floatval($val);
            })->align('center');
            $grid->column('wash_amount',
                admin_trans('machine_report.fields.wash_amount'))->sortable()->display(function ($val) {
                return floatval($val);
            })->align('center');
            $grid->column('total_amount',
                admin_trans('machine_report.fields.total_amount'))->sortable()->display(function ($val) {
                return Html::create()->content([
                    $val > 0 ? '+' . floatval($val) : floatval($val),
                ])->style(['color' => ($val < 0 ? '#cd201f' : '#3b5999')]);
            })->align('center');
            $grid->column('total_pressure',
                admin_trans('machine_report.fields.pressure'))->sortable()->display(function ($val) {
                return floatval($val);
            })->align('center');
            $grid->column('total_score', admin_trans('machine_report.fields.score'))->sortable()->display(function ($val
            ) {
                return floatval($val);
            })->align('center');
            $grid->column('total_diff', admin_trans('machine_report.fields.diff'))->sortable()->display(function ($val
            ) {
                return floatval($val);
            })->align('center');
            $grid->column('total_turn_point',
                admin_trans('machine_report.fields.turn_point'))->sortable()->display(function ($val) {
                return floatval($val);
            })->align('center');
            $grid->column('lottery_amount',
                admin_trans('machine_report.fields.total_lottery_amount'))->sortable()->display(function ($val) {
                return floatval($val);
            })->align('center');
            $grid->column('activity_amount',
                admin_trans('machine_report.fields.total_activity_amount'))->sortable()->display(function ($val) {
                return floatval($val);
            })->align('center');
            $grid->column('machine_total_point',
                admin_trans('machine_report.fields.total_point'))->sortable()->display(function($val) {
                return floatval($val);
            })->align('center');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->expandFilter();
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('machine.name')->placeholder(admin_trans('machine.fields.name'));
                $filter->like()->text('machine.code')->placeholder(admin_trans('machine.fields.code'));
                $filter->hidden('date_start');
                $filter->hidden('date_end');
                $filter->form()->dateRange('date_start', 'date_end',
                    '')->placeholder([admin_trans('public_msg.date_start'), admin_trans('public_msg.date_end')]);
                $filter->select('date_type')
                    ->placeholder(admin_trans('machine_report.fields.date_type'))
                    ->showSearch()
                    ->dropdownMatchSelectWidth()
                    ->style(['width' => '200px'])
                    ->options([
                        1 => admin_trans('machine_report.date_type.1'),
                        2 => admin_trans('machine_report.date_type.2'),
                        3 => admin_trans('machine_report.date_type.3'),
                        4 => admin_trans('machine_report.date_type.4'),
                        5 => admin_trans('machine_report.date_type.5'),
                        6 => admin_trans('machine_report.date_type.6'),
                    ]);
                $producer = plugin()->webman->config('database.machine_producer_model');
                $options = $producer::select(['id', 'name'])->pluck('name', 'id')->all();
                $filter->eq()->select('machine.producer_id')
                    ->placeholder(admin_trans('machine.fields.producer_id'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options($options);
                SelectGroup::create();
                $filter->in()->cascaderSingle('machine.cate_id')
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->placeholder(admin_trans('machine.fields.cate_id'))
                    ->options(getCateListOptions())
                    ->multiple();
            });
            $grid->actions(function (Actions $actions) {
                $actions->hideDel();
                $actions->hideEdit();
            });
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($exAdminFilter) {
                $row->gutter([10, 0]);
                $row->column(admin_view(plugin()->webman->getPath() . '/views/total_info.vue')->attrs([
                    'ex_admin_filter' => $exAdminFilter,
                    'type' => 'MachineReport',
                    'department_id' => Admin::user()->department_id,
                ]));
            })->style(['background' => '#fff']);
            $grid->header($layout);
            $grid->actions(function (Actions $action, $data) use ($grid) {
                $filterData = $grid->getFilter()->form()->input();
                $startDate = (!empty($filterData['date_start'])) ? $filterData['date_start'] : '';
                $endDate = (!empty($filterData['date_end'])) ? $filterData['date_end'] : '';
                $action->prepend([
                    Button::create(admin_trans('machine_report.details'))
                        ->icon(Icon::create('UnorderedListOutlined'))
                        ->type('primary')
                        ->size('small')
                        ->modal('ex-admin/addons-webman-controller-ChannelPlayerGameLogController/list', [
                            'machine_id' => $data['machine_id'],
                            'odds' => $data['machine']['odds_x'] . ':' . $data['machine']['odds_y'],
                            'date_type' => $filterData['date_type'],
                            'code' => $filterData['machine']['code'],
                            'name' => $filterData['machine']['name'],
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                        ])->width('70%')
                ]);
            });
            $grid->attr('is_mongo', true);
            $grid->attr('is_mongo_total', $total);
            $grid->attr('mongo_model', $list);
        });
    }
}
