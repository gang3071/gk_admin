<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminUser;
use addons\webman\model\Player;
use addons\webman\model\PlayGameRecord;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\common\Icon;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\ToolTip;
use ExAdmin\ui\component\layout\layout\Layout;
use ExAdmin\ui\component\layout\Row;
use ExAdmin\ui\support\Request;

/**
 * 代理后台 - 厂商数据报表
 * @group agent
 */
class AgentGameVendorReportController
{
    /**
     * 厂商数据报表
     * @group agent
     * @auth true
     */
    public function index(): Grid
    {
        $page          = Request::input('ex_admin_page', 1);
        $size          = Request::input('ex_admin_size', 50);
        $exAdminFilter = Request::input('ex_admin_filter', []);

        $modelClass    = plugin()->webman->config('database.play_game_record_model');
        $platformModel = plugin()->webman->config('database.game_platform_model');
        $pgr           = (new $modelClass)->getTable();
        $gp            = (new $platformModel)->getTable();

        $query = $this->buildBaseQuery($exAdminFilter);

        $total = (clone $query)
            ->selectRaw("$pgr.platform_id, $gp.cate_id")
            ->groupBy("$pgr.platform_id", "$gp.cate_id")
            ->get()->count();

        $list = $query
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
            ->forPage($page, $size)
            ->get()
            ->toArray();

        $platformOptions = $this->platformOptions($platformModel);
        $storeOptions    = $this->storeOptions();

        return Grid::create($list, function (Grid $grid) use ($total, $list, $platformOptions, $storeOptions, $exAdminFilter) {
            $grid->title(admin_trans('game_vendor_report.title'));

            // 厂商数据统计面板（点击展开，随搜索条件实时刷新）
            $layout = Layout::create();
            $layout->row(function (Row $row) use ($exAdminFilter) {
                $row->gutter([10, 0]);
                $row->column(admin_view(plugin()->webman->getPath() . '/views/game_vendor_total_info.vue')->attrs([
                    'ex_admin_filter' => $exAdminFilter,
                    'api_url' => 'ex-admin/addons-webman-controller-AgentGameVendorReportController/totalInfo',
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
     * 代理范围：下级店家的玩家记录
     * @param array $exAdminFilter
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildBaseQuery(array $exAdminFilter)
    {
        /** @var AdminUser $admin */
        $admin     = Admin::user();
        $storeIds  = $admin->childStores()->where('type', AdminUser::TYPE_STORE)->pluck('id');
        $playerIds = Player::query()->whereIn('store_admin_id', $storeIds)->pluck('id');

        $modelClass    = plugin()->webman->config('database.play_game_record_model');
        $platformModel = plugin()->webman->config('database.game_platform_model');
        $pgr           = (new $modelClass)->getTable();
        $gp            = (new $platformModel)->getTable();

        $query = (new $modelClass)->newQuery()
            ->join($gp, "$gp.id", '=', "$pgr.platform_id")
            ->whereNull("$gp.deleted_at")
            ->whereIn("$pgr.type", [PlayGameRecord::TYPE_BET, PlayGameRecord::TYPE_GIFT])
            ->whereIn("$pgr.player_id", $playerIds);

        if (!empty($exAdminFilter)) {
            if (!empty($exAdminFilter['start_date'])) {
                $query->where("$pgr.created_at", '>=', $exAdminFilter['start_date']);
            }
            if (!empty($exAdminFilter['end_date'])) {
                $query->where("$pgr.created_at", '<=', $exAdminFilter['end_date']);
            }
            if (!empty($exAdminFilter['date_type'])) {
                $query->where(getDateWhere($exAdminFilter['date_type'], "$pgr.created_at"));
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
     * 厂商数据统计（独立接口，不走通用 totalInfo）
     * 汇总口径与列表列一致，随搜索条件实时变化
     * @group agent
     * @auth true
     * @return \support\Response
     */
    public function totalInfo(): \support\Response
    {
        $exAdminFilter = Request::input('ex_admin_filter', []);

        $modelClass = plugin()->webman->config('database.play_game_record_model');
        $pgr        = (new $modelClass)->getTable();

        $query = $this->buildBaseQuery($exAdminFilter);

        $totalData = $query->selectRaw("
                SUM(CASE WHEN $pgr.type=1 AND $pgr.settlement_status=1  THEN $pgr.bet  ELSE 0 END) as valid_bet,
                SUM(CASE WHEN $pgr.type=1 AND $pgr.settlement_status!=2 THEN $pgr.bet  ELSE 0 END) as total_bet,
                SUM(CASE WHEN $pgr.type=1 AND $pgr.settlement_status=1  THEN $pgr.diff ELSE 0 END) as player_win_loss,
                SUM(CASE WHEN $pgr.type=2 THEN $pgr.bet ELSE 0 END) as gift_amount,
                SUM(CASE WHEN $pgr.type=2 THEN 1          ELSE 0 END) as gift_count,
                SUM(CASE WHEN $pgr.type=1 AND $pgr.settlement_status=1 THEN $pgr.win ELSE 0 END) as total_win")
            ->first();

        // 玩家输赢：与列表展示口径一致，取反后正数=庄家赢
        $playerWinLoss = $totalData && $totalData->player_win_loss !== null
            ? floatval($totalData->player_win_loss) * -1
            : 0.0;

        $data = [
            [
                'title'  => admin_trans('game_vendor_report.valid_bet'),
                'number' => $totalData && $totalData->valid_bet !== null ? floatval($totalData->valid_bet) : 0.0,
                'prefix' => '',
                'suffix' => ''
            ],
            [
                'title'  => admin_trans('game_vendor_report.total_bet'),
                'number' => $totalData && $totalData->total_bet !== null ? floatval($totalData->total_bet) : 0.0,
                'prefix' => '',
                'suffix' => ''
            ],
            [
                'title'  => admin_trans('game_vendor_report.player_win_loss'),
                'number' => $playerWinLoss,
                'prefix' => '',
                'suffix' => ''
            ],
            [
                'title'  => admin_trans('game_vendor_report.gift_amount'),
                'number' => $totalData && $totalData->gift_amount !== null ? floatval($totalData->gift_amount) : 0.0,
                'prefix' => '',
                'suffix' => ''
            ],
            [
                'title'  => admin_trans('game_vendor_report.gift_count'),
                'number' => $totalData && $totalData->gift_count !== null ? floatval($totalData->gift_count) : 0,
                'prefix' => '',
                'suffix' => ''
            ],
            [
                'title'  => admin_trans('game_vendor_report.total_win'),
                'number' => $totalData && $totalData->total_win !== null ? floatval($totalData->total_win) : 0.0,
                'prefix' => '',
                'suffix' => ''
            ],
        ];

        return json(['code' => 200, 'data' => $data]);
    }

    /**
     * 店家选项（代理后台：下级店家）
     * @return array
     */
    protected function storeOptions(): array
    {
        /** @var AdminUser $admin */
        $admin = Admin::user();

        return AdminUser::query()
            ->whereIn('id', $admin->childStores()->where('type', AdminUser::TYPE_STORE)->pluck('id'))
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
