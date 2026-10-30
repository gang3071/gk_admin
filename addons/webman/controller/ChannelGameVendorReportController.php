<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\PlayGameRecord;
use addons\webman\model\Player;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\ToolTip;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\support\Request;
use Carbon\Carbon;

/**
 * 子站 - 厂商数据报表
 * @group channel
 */
class ChannelGameVendorReportController
{
    /**
     * 厂商数据报表
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        $page          = max(1, (int)Request::input('ex_admin_page', 1));
        $size          = max(1, (int)Request::input('ex_admin_size', 50));
        $exAdminFilter = Request::input('ex_admin_filter', []);

        $platformModel = plugin()->webman->config('database.game_platform_model');

        // 一次分组聚合出全部厂商行；总数、分页、统计汇总都在这份结果上推导，
        // 避免同一请求对大表做两遍聚合（原 count 查询也是完整的 GROUP BY 扫描）
        $rows    = $this->groupedRows($exAdminFilter);
        $total   = count($rows);
        $list    = array_slice($rows, ($page - 1) * $size, $size);
        $summary = $this->buildSummary($rows);

        $platformOptions = $this->platformOptions($platformModel);
        $storeOptions    = $this->storeOptions();

        return Grid::create($list, function (Grid $grid) use ($total, $list, $platformOptions, $storeOptions, $exAdminFilter, $summary) {
            $grid->title(admin_trans('game_vendor_report.title'));

            // 厂商数据统计面板（点击展开，随搜索条件实时刷新）
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($exAdminFilter, $summary) {
                $row->gutter([10, 0]);
                $row->column(admin_view(plugin()->webman->getPath() . '/views/game_vendor_total_info.vue')->attrs([
                    'ex_admin_filter' => $exAdminFilter,
                    // 首屏即带出汇总数据（与列表同一份聚合结果），展开面板无需再发请求
                    'initial_data' => $summary,
                    'api_url' => 'ex-admin/addons-webman-controller-ChannelGameVendorReportController/totalInfo',
                    'department_id' => Admin::user()->department_id,
                    'admin_user_id' => Admin::user()->id,
                    'trans' => [
                        'panelHeader' => admin_trans('game_vendor_report.stats.panel_header'),
                        'loading' => admin_trans('game_vendor_report.stats.loading'),
                        'refresh' => admin_trans('game_vendor_report.stats.refresh'),
                        'loadError' => admin_trans('game_vendor_report.stats.load_error'),
                        'retry' => admin_trans('game_vendor_report.stats.retry'),
                        'clickToView' => admin_trans('game_vendor_report.stats.click_to_view'),
                        'loadFailedMsg' => admin_trans('game_vendor_report.stats.load_failed_msg'),
                    ],
                ]));
            })->style(['background' => '#fff']);
            $grid->header($layout);

            $grid->bordered(true);
            $grid->autoHeight();
            $grid->driver()->setPk('platform_id');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->expandFilter();

            $grid->actions(function (Actions $action) {
                $action->hideDel();
                $action->hideEdit();
            });

            $grid->column('platform_name', admin_trans('game_vendor_report.vendor_name'))
                ->align('center');

            $grid->column('valid_bet', admin_trans('game_vendor_report.valid_bet'))
                ->display(fn($val) => number_format(floatval($val), 2))
                ->align('right')->sortable();

            $grid->column('total_bet', admin_trans('game_vendor_report.total_bet'))
                ->display(fn($val) => number_format(floatval($val), 2))
                ->align('right')->sortable();

            $winLossLabel = Html::create()->content([
                admin_trans('game_vendor_report.player_win_loss') . ' ',
                ToolTip::create(Icon::create('QuestionCircleOutlined'))
                    ->title(admin_trans('game_vendor_report.player_win_loss_tip'))
                    ->placement('top'),
            ]);
            $grid->column('player_win_loss', admin_trans('game_vendor_report.player_win_loss'))
                ->header($winLossLabel)
                ->display(function ($val) {
                    $num = floatval($val) * -1;
                    $style = $num > 0 ? ['color' => 'green'] : ($num < 0 ? ['color' => 'red'] : []);
                    return Html::create(number_format($num, 2))->style($style);
                })
                ->align('right')->sortable();

            $grid->column('gift_amount', admin_trans('game_vendor_report.gift_amount'))
                ->display(fn($val) => number_format(floatval($val), 2))
                ->align('right')->sortable();

            $grid->column('gift_count', admin_trans('game_vendor_report.gift_count'))
                ->align('right')->sortable();

            $grid->column('total_win', admin_trans('game_vendor_report.total_win'))
                ->display(fn($val) => number_format(floatval($val), 2))
                ->align('right')->sortable();

            $grid->filter(function (Filter $filter) use ($platformOptions, $storeOptions) {
                $filter->select('date_type')
                    ->placeholder(admin_trans('machine_report.fields.date_type'))
                    ->showSearch()
                    ->dropdownMatchSelectWidth()
                    ->style(['width' => '160px'])
                    ->options([
                        1 => admin_trans('machine_report.date_type.1'),
                        2 => admin_trans('machine_report.date_type.2'),
                        3 => admin_trans('machine_report.date_type.3'),
                        4 => admin_trans('machine_report.date_type.4'),
                        5 => admin_trans('machine_report.date_type.5'),
                        6 => admin_trans('machine_report.date_type.6'),
                    ]);
                $filter->hidden('start_date');
                $filter->hidden('end_date');
                $filter->form()->dateRange('start_date', 'end_date', '')->placeholder([
                    admin_trans('public_msg.date_start'),
                    admin_trans('public_msg.date_end'),
                ]);
                $filter->eq()->select('platform_id')
                    ->placeholder(admin_trans('game_vendor_report.platform'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options($platformOptions);
                $filter->eq()->select('store_id')
                    ->placeholder(admin_trans('game_vendor_report.store'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options($storeOptions);
            });

            $grid->attr('is_mongo', true);
            $grid->attr('is_mongo_total', $total);
            $grid->attr('mongo_model', $list);
        });
    }

    /**
     * 按搜索条件构建厂商数据基础查询（列表与统计共用）
     * @param array $exAdminFilter
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildBaseQuery(array $exAdminFilter)
    {
        $departmentId = Admin::user()->department_id;

        $modelClass    = plugin()->webman->config('database.play_game_record_model');
        $platformModel = plugin()->webman->config('database.game_platform_model');
        $pgr           = (new $modelClass)->getTable();
        $gp            = (new $platformModel)->getTable();

        $query = (new $modelClass)->newQuery()
            ->join($gp, "$gp.id", '=', "$pgr.platform_id")
            ->whereNull("$gp.deleted_at")
            ->whereIn("$pgr.type", [PlayGameRecord::TYPE_BET, PlayGameRecord::TYPE_GIFT])
            ->where("$pgr.department_id", $departmentId);

        if (!empty($exAdminFilter)) {
            if (!empty($exAdminFilter['start_date'])) {
                $query->where("$pgr.created_at", '>=', $exAdminFilter['start_date']);
            }
            if (!empty($exAdminFilter['end_date'])) {
                $query->where("$pgr.created_at", '<=', $exAdminFilter['end_date']);
            }
            if (!empty($exAdminFilter['date_type'])) {
                $query->where(getDateWhere8am($exAdminFilter['date_type'], "$pgr.created_at"));
            }
            if (!empty($exAdminFilter['platform_id'])) {
                $query->where("$pgr.platform_id", $exAdminFilter['platform_id']);
            }
            if (!empty($exAdminFilter['store_id'])) {
                $query->whereIn("$pgr.player_id", Player::query()
                    ->where('store_admin_id', $exAdminFilter['store_id'])
                    ->select('id'));
            }
        }

        return $query;
    }

    /**
     * 今日业务日区间，与「今日打码量」同口径（ChannelIndexController 同款分界）
     * 今日 = 早上 08:00 ~ 隔天早上 08:00；凌晨 08:00 前算上一个业务日（昨日 08:00 ~ 今日 08:00）
     * @return array [start, end] 日期时间字符串
     */
    protected function todayRange(): array
    {
        $now = Carbon::now();
        $today8am = Carbon::today()->setTime(8, 0, 0);

        if ($now->gte($today8am)) {
            $start = $today8am;
            $end   = Carbon::tomorrow()->setTime(8, 0, 0);
        } else {
            // 当前在今日 08:00 之前（凌晨），今日区间 = 昨日 08:00 ~ 今日 08:00
            $start = Carbon::yesterday()->setTime(8, 0, 0);
            $end   = $today8am;
        }

        // end_date 在 buildBaseQuery 走 <= 比较，取 07:59:59 使区间等价于 [08:00, 隔天 08:00)
        return [$start->toDateTimeString(), $end->copy()->subSecond()->toDateTimeString()];
    }

    /**
     * 未指定任何时间条件（start_date/end_date/date_type 都为空）时，默认只统计今日
     * 用户指定任一时间条件时按用户选择查询，不再套默认区间
     * @param array $exAdminFilter
     * @return array
     */
    protected function applyDefaultDateRange(array $exAdminFilter): array
    {
        if (!empty($exAdminFilter['start_date']) || !empty($exAdminFilter['end_date']) || !empty($exAdminFilter['date_type'])) {
            return $exAdminFilter;
        }

        [$exAdminFilter['start_date'], $exAdminFilter['end_date']] = $this->todayRange();

        return $exAdminFilter;
    }

    /**
     * 厂商分组聚合结果（列表/分页/统计共用）
     * 不带分页，返回全部分组行；分页与汇总数值在 PHP 侧从这份结果推导
     * @param array $exAdminFilter
     * @return array
     */
    protected function groupedRows(array $exAdminFilter): array
    {
        // 未指定时间条件时默认只查今日
        $exAdminFilter = $this->applyDefaultDateRange($exAdminFilter);

        $modelClass    = plugin()->webman->config('database.play_game_record_model');
        $platformModel = plugin()->webman->config('database.game_platform_model');
        $pgr           = (new $modelClass)->getTable();
        $gp            = (new $platformModel)->getTable();

        $rows = $this->buildBaseQuery($exAdminFilter)
            ->groupBy("$pgr.platform_id", "$gp.cate_id", "$gp.name")
            ->selectRaw("$pgr.platform_id, $gp.cate_id, $gp.name as platform_name,
                SUM(CASE WHEN $pgr.type=1 AND $pgr.settlement_status=1  THEN $pgr.bet  ELSE 0 END) as valid_bet,
                SUM(CASE WHEN $pgr.type=1 AND $pgr.settlement_status!=2 THEN $pgr.bet  ELSE 0 END) as total_bet,
                SUM(CASE WHEN $pgr.type=1 AND $pgr.settlement_status=1  THEN $pgr.diff ELSE 0 END) as player_win_loss,
                SUM(CASE WHEN $pgr.type=2 THEN $pgr.bet ELSE 0 END) as gift_amount,
                SUM(CASE WHEN $pgr.type=2 THEN 1          ELSE 0 END) as gift_count,
                SUM(CASE WHEN $pgr.type=1 AND $pgr.settlement_status=1 THEN $pgr.win ELSE 0 END) as total_win")
            ->orderBy("$gp.cate_id")
            ->orderBy("$gp.name")
            ->get()
            ->toArray();

        return $rows;
    }

    /**
     * 由分组聚合行累加出统计面板的 6 项汇总（口径与列表列一致）
     * 各列均为可加指标，按分组行求和 == 对明细行直接 SUM
     * @param array $rows
     * @return array
     */
    protected function buildSummary(array $rows): array
    {
        $sum = [
            'valid_bet'       => 0.0,
            'total_bet'       => 0.0,
            'player_win_loss' => 0.0,
            'gift_amount'     => 0.0,
            'gift_count'      => 0.0,
            'total_win'       => 0.0,
        ];
        foreach ($rows as $row) {
            foreach ($sum as $key => $value) {
                $sum[$key] = $value + (float)($row[$key] ?? 0);
            }
        }

        return [
            [
                'title'  => admin_trans('game_vendor_report.valid_bet'),
                'number' => $sum['valid_bet'],
                'prefix' => '',
                'suffix' => ''
            ],
            [
                'title'  => admin_trans('game_vendor_report.total_bet'),
                'number' => $sum['total_bet'],
                'prefix' => '',
                'suffix' => ''
            ],
            [
                // 玩家输赢：与列表展示口径一致，取反后正数=庄家赢
                'title'  => admin_trans('game_vendor_report.player_win_loss'),
                'number' => $sum['player_win_loss'] * -1,
                'prefix' => '',
                'suffix' => ''
            ],
            [
                'title'  => admin_trans('game_vendor_report.gift_amount'),
                'number' => $sum['gift_amount'],
                'prefix' => '',
                'suffix' => ''
            ],
            [
                'title'  => admin_trans('game_vendor_report.gift_count'),
                'number' => $sum['gift_count'],
                'prefix' => '',
                'suffix' => ''
            ],
            [
                'title'  => admin_trans('game_vendor_report.total_win'),
                'number' => $sum['total_win'],
                'prefix' => '',
                'suffix' => ''
            ],
        ];
    }

    /**
     * 厂商数据统计（独立接口，不走通用 totalInfo）
     * 汇总口径与列表列一致，随搜索条件实时变化；复用列表的分组聚合，不再单独扫大表
     * @group channel
     * @auth true
     * @return \support\Response
     */
    public function totalInfo(): \support\Response
    {
        $exAdminFilter = Request::input('ex_admin_filter', []);

        return json(['code' => 200, 'data' => $this->buildSummary($this->groupedRows($exAdminFilter))]);
    }

    /**
     * 店家选项（渠道后台：本渠道的店家）
     * @return array
     */
    protected function storeOptions(): array
    {
        return AdminUser::query()
            ->where('department_id', Admin::user()->department_id)
            ->where('type', AdminUser::TYPE_STORE)
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->get(['id', 'nickname', 'username'])
            ->mapWithKeys(function ($store) {
                $label = $store->nickname ?: $store->username;
                $label .= " ({$store->username})";
                return [$store->id => $label];
            })
            ->toArray();
    }

    protected function platformOptions(string $platformModel): array
    {
        return (new $platformModel)->newQuery()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
