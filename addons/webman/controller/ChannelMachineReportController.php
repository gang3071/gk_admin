<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\GameType;
use addons\webman\model\Machine;
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
use support\Db;

/**
 * 机台报表
 * @group channel
 */
class ChannelMachineReportController
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.machine_model');
    }

    /**
     * 机台报表
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        $page             = Request::input('ex_admin_page', 1);
        $size             = Request::input('ex_admin_size', 20);
        $exAdminFilter    = Request::input('ex_admin_filter', []);
        $exAdminSortBy    = Request::input('ex_admin_sort_by', '');
        $exAdminSortField = Request::input('ex_admin_sort_field', '');

        $pglTable     = plugin()->webman->config('database.player_game_log_table');
        $machineTable = (new Machine())->getTable();
        $departmentId = Admin::user()->department_id;

        // 构建 player_game_log 聚合子查询：日期、渠道筛选应用于此
        $pglSubquery = Db::table($pglTable)->selectRaw('
            machine_id,
            SUM(CASE WHEN action = 1 THEN ABS(game_amount) ELSE 0 END) as open_amount,
            SUM(CASE WHEN action = 3 THEN ABS(game_amount) ELSE 0 END) as wash_amount,
            SUM(CASE WHEN action = 3 THEN ABS(game_amount) WHEN action = 1 THEN -ABS(game_amount) ELSE 0 END) as total_amount,
            SUM(open_point) as open_point,
            SUM(wash_point) as wash_point,
            SUM(open_point - wash_point) as total_point,
            SUM(pressure) as total_pressure,
            SUM(score) as total_score,
            SUM(pressure - score) as total_diff,
            SUM(turn_point) as total_turn_point
        ');

        if (!empty($departmentId)) {
            $pglSubquery->where('department_id', $departmentId);
        }
        if (!empty($exAdminFilter)) {
            if (!empty($exAdminFilter['date_start'])) {
                $pglSubquery->whereDate('created_at', '>=', $exAdminFilter['date_start']);
            }
            if (!empty($exAdminFilter['date_end'])) {
                $pglSubquery->whereDate('created_at', '<=', $exAdminFilter['date_end']);
            }
        }
        if (!empty($exAdminFilter['date_type'])) {
            $pglSubquery->where(getDateWhere($exAdminFilter['date_type'], 'created_at'));
        }
        $pglSubquery->groupBy('machine_id');

        // 主查询：Machine LEFT JOIN 聚合子查询，没有上下分记录的机台也会显示（值为0）
        $query = Machine::with(['producer', 'machineLabel'])
            ->leftJoinSub($pglSubquery, 'pgl', "{$machineTable}.id", '=', 'pgl.machine_id')
            ->select("{$machineTable}.*")
            ->selectRaw('
                COALESCE(pgl.open_amount, 0)    as open_amount,
                COALESCE(pgl.wash_amount, 0)    as wash_amount,
                COALESCE(pgl.total_amount, 0)   as total_amount,
                COALESCE(pgl.open_point, 0)     as open_point,
                COALESCE(pgl.wash_point, 0)     as wash_point,
                COALESCE(pgl.total_point, 0)    as total_point,
                COALESCE(pgl.total_pressure, 0) as total_pressure,
                COALESCE(pgl.total_score, 0)    as total_score,
                COALESCE(pgl.total_diff, 0)     as total_diff,
                COALESCE(pgl.total_turn_point, 0) as total_turn_point,
                0 as lottery_amount,
                0 as activity_amount,
                COALESCE(pgl.open_amount, 0) - COALESCE(pgl.wash_amount, 0) as machine_total_point
            ');

        // 机台维度筛选
        if (!empty($exAdminFilter['machine'])) {
            if (!empty($exAdminFilter['machine']['name'])) {
                $query->whereHas('machineLabel', function ($q) use ($exAdminFilter) {
                    $q->where('name', 'like', '%' . $exAdminFilter['machine']['name'] . '%');
                });
            }
            if (!empty($exAdminFilter['machine']['code'])) {
                $query->where("{$machineTable}.code", $exAdminFilter['machine']['code']);
            }
            if (!empty($exAdminFilter['machine']['producer_id'])) {
                $query->where("{$machineTable}.producer_id", $exAdminFilter['machine']['producer_id']);
            }
            if (!empty($exAdminFilter['machine']['cate_id'])) {
                $query->whereIn("{$machineTable}.cate_id", $exAdminFilter['machine']['cate_id']);
            }
        }

        // count 不需要 pgl 聚合子查询，直接在 machine 表上计数以提升性能
        $total = Machine::query()
            ->when(!empty($exAdminFilter['machine']['name']), function ($q) use ($exAdminFilter) {
                $q->whereHas('machineLabel', function ($q) use ($exAdminFilter) {
                    $q->where('name', 'like', '%' . $exAdminFilter['machine']['name'] . '%');
                });
            })
            ->when(!empty($exAdminFilter['machine']['code']), function ($q) use ($exAdminFilter, $machineTable) {
                $q->where("{$machineTable}.code", $exAdminFilter['machine']['code']);
            })
            ->when(!empty($exAdminFilter['machine']['producer_id']), function ($q) use ($exAdminFilter, $machineTable) {
                $q->where("{$machineTable}.producer_id", $exAdminFilter['machine']['producer_id']);
            })
            ->when(!empty($exAdminFilter['machine']['cate_id']), function ($q) use ($exAdminFilter, $machineTable) {
                $q->whereIn("{$machineTable}.cate_id", $exAdminFilter['machine']['cate_id']);
            })
            ->count();
        $list  = $query
            ->when(!empty($exAdminSortField) && !empty($exAdminSortBy),
                function ($q) use ($exAdminSortField, $exAdminSortBy) {
                    $q->orderBy($exAdminSortField, $exAdminSortBy);
                }, function ($q) use ($machineTable) {
                    $q->orderBy("{$machineTable}.id", 'desc');
                })
            ->forPage($page, $size)
            ->get()
            ->toArray();

        return Grid::create($list, function (Grid $grid) use ($total, $list, $exAdminFilter) {
            $grid->title(admin_trans('machine_report.title'));
            $grid->bordered(true);
            $grid->autoHeight();
            $grid->driver()->setPk('id');

            $grid->column('id', admin_trans('machine_report.fields.machine_id'))->align('center');

            $grid->column('code', admin_trans('machine.fields.code'))->display(function ($val, $data) {
                return Tag::create($val)->color('orange')->style(['cursor' => 'pointer'])->modal([
                    'addons-webman-controller-PlayerDeliveryRecordController',
                    'machineInfo',
                ], ['data' => $data])->width('60%')->title(
                    (!empty($data['machine_label']['name']) ? $data['machine_label']['name'] : '') . ':' . $data['name']
                );
            })->align('center');

            $grid->column('code', admin_trans('machine.fields.code'))->align('center')
                ->filter(FilterColumn::like()->text('machine.code'));

            $grid->column('type', admin_trans('machine_category.fields.type'))->display(function ($val, $data) {
                $tag = '';
                switch ($data['type']) {
                    case GameType::TYPE_STEEL_BALL:
                        $tag = Tag::create(admin_trans('game_type.game_type.' . $data['type']))->color('#f50');
                        break;
                    case GameType::TYPE_SLOT:
                        $tag = Tag::create(admin_trans('game_type.game_type.' . $data['type']))->color('#2db7f5');
                        break;
                }
                return Html::create()->content([$tag]);
            })->align('center');

            $grid->column('name', admin_trans('machine.fields.name'))->display(function ($val, $data) {
                return Html::create()->content([$data['machine_label']['name'] ?? '']);
            })->align('center');

            $grid->column('producer_id', admin_trans('machine.fields.producer_id'))->display(function ($val, $data) {
                return Html::create()->content([
                    !empty($data['producer']['name']) ? Tag::create($data['producer']['name'])->color('green') : '',
                ]);
            })->align('center');

            $grid->column('odds_x', admin_trans('machine_report.fields.odds'))->display(function ($val, $data) {
                return $data['odds_x'] . ':' . $data['odds_y'];
            })->align('center');

            $grid->column('open_amount', admin_trans('machine_report.fields.open_amount'))->sortable()->display(function ($val) {
                return floatval($val);
            })->align('center');

            $grid->column('wash_amount', admin_trans('machine_report.fields.wash_amount'))->sortable()->display(function ($val) {
                return floatval($val);
            })->align('center');

            $grid->column('total_amount', admin_trans('machine_report.fields.total_amount'))->sortable()->display(function ($val) {
                return Html::create()->content([
                    $val > 0 ? '+' . floatval($val) : floatval($val),
                ])->style(['color' => ($val < 0 ? '#cd201f' : '#3b5999')]);
            })->align('center');

            $grid->column('total_pressure', admin_trans('machine_report.fields.pressure'))->sortable()->display(function ($val) {
                return floatval($val);
            })->align('center');

            $grid->column('total_score', admin_trans('machine_report.fields.score'))->sortable()->display(function ($val) {
                return floatval($val);
            })->align('center');

            $grid->column('total_diff', admin_trans('machine_report.fields.diff'))->sortable()->display(function ($val) {
                return floatval($val);
            })->align('center');

            $grid->column('total_turn_point', admin_trans('machine_report.fields.turn_point'))->sortable()->display(function ($val) {
                return floatval($val);
            })->align('center');

            $grid->column('lottery_amount', admin_trans('machine_report.fields.total_lottery_amount'))->sortable()->display(function ($val) {
                return floatval($val);
            })->align('center');

            $grid->column('activity_amount', admin_trans('machine_report.fields.total_activity_amount'))->sortable()->display(function ($val) {
                return floatval($val);
            })->align('center');

            $grid->column('machine_total_point', admin_trans('machine_report.fields.total_point'))->sortable()->display(function ($val) {
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
                $filter->form()->dateRange('date_start', 'date_end', '')->placeholder([
                    admin_trans('public_msg.date_start'),
                    admin_trans('public_msg.date_end'),
                ]);
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
                $options  = $producer::select(['id', 'name'])->pluck('name', 'id')->all();
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
                    'type'            => 'MachineReport',
                    'department_id'   => Admin::user()->department_id,
                ]));
            })->style(['background' => '#fff']);
            $grid->header($layout);
            $grid->actions(function (Actions $action, $data) use ($grid) {
                $filterData = $grid->getFilter()->form()->input();
                $startDate  = (!empty($filterData['date_start'])) ? $filterData['date_start'] : '';
                $endDate    = (!empty($filterData['date_end'])) ? $filterData['date_end'] : '';
                $action->prepend([
                    Button::create(admin_trans('machine_report.details'))
                        ->icon(Icon::create('UnorderedListOutlined'))
                        ->type('primary')
                        ->size('small')
                        ->modal('ex-admin/addons-webman-controller-ChannelPlayerGameLogController/list', [
                            'machine_id' => $data['id'],
                            'odds'       => $data['odds_x'] . ':' . $data['odds_y'],
                            'date_type'  => $filterData['date_type'] ?? '',
                            'code'       => $filterData['machine']['code'] ?? '',
                            'name'       => $filterData['machine']['name'] ?? '',
                            'start_date' => $startDate,
                            'end_date'   => $endDate,
                        ])->width('70%'),
                ]);
            });
            $grid->attr('is_mongo', true);
            $grid->attr('is_mongo_total', $total);
            $grid->attr('mongo_model', $list);
        });
    }
}
