<?php

namespace addons\webman\controller;

use addons\webman\model\PlayGameRecord;
use ExAdmin\ui\support\Request;
use support\Response;

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
    public function index()
    {
        $labels = [
            'vendor_name'     => admin_trans('game_vendor_report.vendor_name'),
            'valid_bet'       => admin_trans('game_vendor_report.valid_bet'),
            'total_bet'       => admin_trans('game_vendor_report.total_bet'),
            'player_win_loss' => admin_trans('game_vendor_report.player_win_loss'),
            'gift_amount'     => admin_trans('game_vendor_report.gift_amount'),
            'gift_count'      => admin_trans('game_vendor_report.gift_count'),
            'start_date'      => admin_trans('game_vendor_report.start_date'),
            'end_date'        => admin_trans('game_vendor_report.end_date'),
            'search'          => admin_trans('game_vendor_report.search'),
            'reset'           => admin_trans('game_vendor_report.reset'),
        ];

        $platformModel = plugin()->webman->config('database.game_platform_model');
        $platforms = (new $platformModel)->newQuery()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn($p) => ['id' => $p->id, 'name' => $p->name])
            ->values()->all();

        return admin_view(plugin()->webman->getPath() . '/views/game_vendor_report.vue')->attrs([
            'api_url'   => 'ex-admin/addons-webman-controller-AdminGameVendorReportController/vendorData',
            'labels'    => $labels,
            'platforms' => $platforms,
        ]);
    }

    /**
     * 厂商数据 API
     * @group department
     * @auth true
     */
    public function vendorData(): Response
    {
        $startDate = Request::input('start_date', '');
        $endDate   = Request::input('end_date', '');

        $modelClass    = plugin()->webman->config('database.play_game_record_model');
        $platformModel = plugin()->webman->config('database.game_platform_model');

        $pgr = (new $modelClass)->getTable();
        $gp  = (new $platformModel)->getTable();

        $query = (new $modelClass)->newQuery()
            ->join($gp, "$gp.id", '=', "$pgr.platform_id")
            ->whereNull("$gp.deleted_at")
            ->whereIn("$pgr.type", [PlayGameRecord::TYPE_BET, PlayGameRecord::TYPE_GIFT]);

        if ($startDate) {
            $query->where("$pgr.created_at", '>=', $startDate . ' 00:00:00');
        }
        if ($endDate) {
            $query->where("$pgr.created_at", '<=', $endDate . ' 23:59:59');
        }

        $rows = $query
            ->groupBy("$pgr.platform_id", "$gp.cate_id", "$gp.name")
            ->selectRaw("$pgr.platform_id, $gp.cate_id, $gp.name as platform_name,
                SUM(CASE WHEN $pgr.type=1 AND $pgr.settlement_status=1  THEN $pgr.bet  ELSE 0 END) as valid_bet,
                SUM(CASE WHEN $pgr.type=1 AND $pgr.settlement_status!=2 THEN $pgr.bet  ELSE 0 END) as total_bet,
                SUM(CASE WHEN $pgr.type=1 AND $pgr.settlement_status=1  THEN $pgr.diff ELSE 0 END) as player_win_loss,
                SUM(CASE WHEN $pgr.type=2 THEN $pgr.bet ELSE 0 END) as gift_amount,
                SUM(CASE WHEN $pgr.type=2 THEN 1          ELSE 0 END) as gift_count")
            ->get();

        $data = $rows->map(fn($r) => [
            'platform_id'     => (int)$r->platform_id,
            'cate_id'         => (int)$r->cate_id,
            'name'            => $r->platform_name,
            'valid_bet'       => (float)$r->valid_bet,
            'total_bet'       => (float)$r->total_bet,
            'player_win_loss' => (float)$r->player_win_loss,
            'gift_amount'     => (float)$r->gift_amount,
            'gift_count'      => (int)$r->gift_count,
        ])->values()->all();

        return json(['code' => 200, 'data' => $data]);
    }
}
