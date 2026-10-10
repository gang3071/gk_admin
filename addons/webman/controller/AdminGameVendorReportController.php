<?php

namespace addons\webman\controller;

use addons\webman\model\GameType;
use addons\webman\model\PlayGameRecord;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\support\Request;

/**
 * 主站 - 厂商数据报表
 * @group department
 */
class AdminGameVendorReportController
{
    /**
     * 厂商数据报表
     * @group department
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

        $query = (new $modelClass)->newQuery()
            ->join($gp, "$gp.id", '=', "$pgr.platform_id")
            ->whereNull("$gp.deleted_at")
            ->whereIn("$pgr.type", [PlayGameRecord::TYPE_BET, PlayGameRecord::TYPE_GIFT]);

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
            if (!empty($exAdminFilter['cate_id'])) {
                $query->where("$gp.cate_id", $exAdminFilter['cate_id']);
            }
        }

        $total = (clone $query)->groupBy("$pgr.platform_id", "$gp.cate_id")->get()->count();

        $list = $query
            ->groupBy("$pgr.platform_id", "$gp.cate_id", "$gp.name")
            ->selectRaw("$pgr.platform_id, $gp.cate_id, $gp.name as platform_name,
                SUM(CASE WHEN $pgr.type=1 AND $pgr.settlement_status=1  THEN $pgr.bet  ELSE 0 END) as valid_bet,
                SUM(CASE WHEN $pgr.type=1 AND $pgr.settlement_status!=2 THEN $pgr.bet  ELSE 0 END) as total_bet,
                SUM(CASE WHEN $pgr.type=1 AND $pgr.settlement_status=1  THEN $pgr.diff ELSE 0 END) as player_win_loss,
                SUM(CASE WHEN $pgr.type=2 THEN $pgr.bet ELSE 0 END) as gift_amount,
                SUM(CASE WHEN $pgr.type=2 THEN 1          ELSE 0 END) as gift_count")
            ->orderBy("$gp.cate_id")
            ->orderBy("$gp.name")
            ->forPage($page, $size)
            ->get()
            ->toArray();

        $cateNames = $this->cateNames();
        $platformOptions = $this->platformOptions($platformModel);

        return Grid::create($list, function (Grid $grid) use ($total, $list, $cateNames, $platformOptions) {
            $grid->title(admin_trans('game_vendor_report.title'));
            $grid->bordered(true);
            $grid->autoHeight();
            $grid->driver()->setPk('platform_id');
            $grid->hideDelete();
            $grid->hideSelection();
            $grid->expandFilter();

            $grid->column('cate_id', admin_trans('game_vendor_report.cate_name'))
                ->display(fn($val) => $cateNames[$val] ?? $val)
                ->align('center');

            $grid->column('platform_name', admin_trans('game_vendor_report.vendor_name'))
                ->align('center');

            $grid->column('valid_bet', admin_trans('game_vendor_report.valid_bet'))
                ->display(fn($val) => number_format(floatval($val), 0))
                ->align('right')->sortable();

            $grid->column('total_bet', admin_trans('game_vendor_report.total_bet'))
                ->display(fn($val) => number_format(floatval($val), 0))
                ->align('right')->sortable();

            $grid->column('player_win_loss', admin_trans('game_vendor_report.player_win_loss'))
                ->display(function ($val) {
                    $num = floatval($val);
                    $style = $num > 0 ? ['color' => 'green'] : ($num < 0 ? ['color' => 'red'] : []);
                    return Html::create(number_format($num, 0))->style($style);
                })
                ->align('right')->sortable();

            $grid->column('gift_amount', admin_trans('game_vendor_report.gift_amount'))
                ->display(fn($val) => number_format(floatval($val), 0))
                ->align('right')->sortable();

            $grid->column('gift_count', admin_trans('game_vendor_report.gift_count'))
                ->align('right')->sortable();

            $grid->filter(function (Filter $filter) use ($cateNames, $platformOptions) {
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
                $filter->eq()->select('cate_id')
                    ->placeholder(admin_trans('game_vendor_report.cate_name'))
                    ->showSearch()
                    ->style(['width' => '160px'])
                    ->dropdownMatchSelectWidth()
                    ->options($cateNames);
            });

            $grid->attr('is_mongo', true);
            $grid->attr('is_mongo_total', $total);
            $grid->attr('mongo_model', $list);
        });
    }

    protected function cateNames(): array
    {
        return [
            GameType::CATE_PHYSICAL_MACHINE => '實體機台',
            GameType::CATE_COMPUTER_GAME    => '電子',
            GameType::CATE_LIVE_VIDEO       => '真人視訊',
            GameType::CATE_FISH             => '捕魚',
            GameType::CATE_TABLE            => '牌桌',
            GameType::CATE_P2P              => '棋牌',
            GameType::CATE_SLO              => '老虎機',
            GameType::CATE_ARCADE           => '街機',
            GameType::CATE_SPORT            => '體育',
            GameType::CATE_LOTTERY          => '彩票',
        ];
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
