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
        $exAdminFilter = Request::input('ex_admin_filter', []);

        /** @var AdminUser $admin */
        $admin    = Admin::user();
        $storeIds = $admin->childStores()->where('type', AdminUser::TYPE_STORE)->pluck('id');
        // 使用子查询替代 pluck，避免超大 IN 列表撑爆内存和 MySQL packet
        $playerIds = Player::query()->whereIn('store_admin_id', $storeIds)->select('id');

        $modelClass    = plugin()->webman->config('database.play_game_record_model');
        $platformModel = plugin()->webman->config('database.game_platform_model');
        $pgr           = (new $modelClass)->getTable();

        // 查询1：获取有效厂商信息（数据极小，极快）
        $platforms = (new $platformModel)->newQuery()
            ->whereNull('deleted_at')
            ->select('id', 'cate_id', 'name')
            ->get()
            ->keyBy('id');

        // 指定厂商筛选时缩小 IN 列表，避免冗余条件
        $platformIn = !empty($exAdminFilter['platform_id'])
            ? [(int)$exAdminFilter['platform_id']]
            : $platforms->keys()->toArray();

        // 查询2：按 platform_id 聚合，无 JOIN，索引友好
        $query = (new $modelClass)->newQuery()
            ->whereIn("$pgr.platform_id", $platformIn)
            ->whereIn("$pgr.type", [PlayGameRecord::TYPE_BET, PlayGameRecord::TYPE_GIFT])
            ->whereIn("$pgr.player_id", $playerIds);

        $hasDateFilter = !empty($exAdminFilter['start_date'])
            || !empty($exAdminFilter['end_date'])
            || !empty($exAdminFilter['date_type']);

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
        }

        if (!$hasDateFilter) {
            $today = \now()->toDateString();
            $query->whereBetween("$pgr.created_at", [$today . ' 00:00:00', $today . ' 23:59:59']);
        }

        $aggregated = $query
            ->selectRaw("
                $pgr.platform_id,
                SUM(CASE WHEN $pgr.type=1 AND $pgr.settlement_status=1  THEN $pgr.bet  ELSE 0 END) as valid_bet,
                SUM(CASE WHEN $pgr.type=1 AND $pgr.settlement_status!=2 THEN $pgr.bet  ELSE 0 END) as total_bet,
                SUM(CASE WHEN $pgr.type=1 AND $pgr.settlement_status=1  THEN $pgr.diff ELSE 0 END) as player_win_loss,
                SUM(CASE WHEN $pgr.type=2 THEN $pgr.bet ELSE 0 END) as gift_amount,
                SUM(CASE WHEN $pgr.type=2 THEN 1        ELSE 0 END) as gift_count,
                SUM(CASE WHEN $pgr.type=1 AND $pgr.settlement_status=1 THEN $pgr.win  ELSE 0 END) as total_win
            ")
            ->groupBy("$pgr.platform_id")
            ->get();

        // PHP 合并厂商元数据（结果集 ≤ 30 行，O(n) 操作）
        $list = $aggregated->map(function ($row) use ($platforms) {
            $platform = $platforms->get($row->platform_id);
            if (!$platform) return null; // 跳过已软删除厂商的历史记录
            return array_merge($row->toArray(), [
                'cate_id'       => $platform->cate_id,
                'platform_name' => $platform->name,
            ]);
        })
        ->filter()
        ->sortBy(fn($item) => [$item['cate_id'], $item['platform_name']])
        ->values()
        ->toArray();

        $total = count($list);

        // 从已查询的 $platforms 推导筛选选项，无需第3次 DB 查询
        $platformOptions = $platforms->sortBy('name')->pluck('name', 'id')->all();

        return Grid::create($list, function (Grid $grid) use ($total, $list, $platformOptions) {
            $grid->title(admin_trans('game_vendor_report.title'));
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
                ->align('right');

            $grid->column('total_bet', admin_trans('game_vendor_report.total_bet'))
                ->display(fn($val) => number_format(floatval($val), 2))
                ->align('right');

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
                ->align('right');

            $grid->column('gift_amount', admin_trans('game_vendor_report.gift_amount'))
                ->display(fn($val) => number_format(floatval($val), 2))
                ->align('right');

            $grid->column('gift_count', admin_trans('game_vendor_report.gift_count'))
                ->align('right');

            $grid->column('total_win', admin_trans('game_vendor_report.total_win'))
                ->display(fn($val) => number_format(floatval($val), 2))
                ->align('right');

            $grid->filter(function (Filter $filter) use ($platformOptions) {
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
            });

            $grid->attr('is_mongo', true);
            $grid->attr('is_mongo_total', $total);
            $grid->attr('mongo_model', $list);
        });
    }
}
