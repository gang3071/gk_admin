<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\GameType;
use addons\webman\model\PlayGameRecord;
use ExAdmin\ui\support\Request;
use support\Response;

/**
 * 主站 - 厂商数据报表
 * @group department
 */
class AdminGameVendorReportController
{
    private array $cateNames = [
        GameType::CATE_COMPUTER_GAME  => '電子',
        GameType::CATE_LIVE_VIDEO     => '真人視訊',
        GameType::CATE_FISH           => '捕魚',
        GameType::CATE_TABLE          => '牌桌',
        GameType::CATE_P2P            => '棋牌',
        GameType::CATE_SLO            => '老虎機',
        GameType::CATE_ARCADE         => '街機',
        GameType::CATE_SPORT          => '體育',
        GameType::CATE_LOTTERY        => '彩票',
        GameType::CATE_PHYSICAL_MACHINE => '實體機台',
    ];

    /**
     * 厂商数据报表
     * @group department
     * @auth true
     */
    public function index()
    {
        $labels = [
            'vendor_name'     => '廠商名稱',
            'valid_bet'       => '有效投注',
            'total_bet'       => '投注金額',
            'player_win_loss' => '玩家輸贏',
            'gift_amount'     => '打賞總額',
            'gift_count'      => '打賞筆數',
            'start_date'      => '開始日期',
            'end_date'        => '結束日期',
            'search'          => '查詢',
            'reset'           => '重置',
        ];

        return admin_view(plugin()->webman->getPath() . '/views/game_vendor_report.vue')->attrs([
            'api_url' => 'ex-admin/addons-webman-controller-AdminGameVendorReportController/vendorData',
            'labels'  => $labels,
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
            ->whereIn("$pgr.type", [PlayGameRecord::TYPE_BET, PlayGameRecord::TYPE_GIFT])
            ->where("$pgr.settlement_status", '!=', PlayGameRecord::SETTLEMENT_STATUS_CANCELLED);

        if ($startDate) {
            $query->where("$pgr.created_at", '>=', $startDate . ' 00:00:00');
        }
        if ($endDate) {
            $query->where("$pgr.created_at", '<=', $endDate . ' 23:59:59');
        }

        $rows = $query
            ->groupBy("$pgr.platform_id", "$gp.cate_id", "$gp.name")
            ->selectRaw("$pgr.platform_id, $gp.cate_id, $gp.name as platform_name,
                SUM(CASE WHEN $pgr.type=1 AND $pgr.settlement_status=1 THEN $pgr.bet ELSE 0 END) as valid_bet,
                SUM(CASE WHEN $pgr.type=1 THEN $pgr.bet ELSE 0 END) as total_bet,
                SUM(CASE WHEN $pgr.type=1 AND $pgr.settlement_status=1 THEN $pgr.diff ELSE 0 END) as player_win_loss,
                SUM(CASE WHEN $pgr.type=2 THEN $pgr.bet ELSE 0 END) as gift_amount,
                SUM(CASE WHEN $pgr.type=2 THEN 1 ELSE 0 END) as gift_count")
            ->get();

        $data = $this->groupByCategory($rows);

        return json(['code' => 200, 'data' => $data]);
    }

    private function groupByCategory($rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $cateId   = (int)$row->cate_id;
            $cateName = $this->cateNames[$cateId] ?? '其他';

            if (!isset($grouped[$cateId])) {
                $grouped[$cateId] = [
                    'cate_id'          => $cateId,
                    'name'             => $cateName,
                    'valid_bet'        => 0,
                    'total_bet'        => 0,
                    'player_win_loss'  => 0,
                    'gift_amount'      => 0,
                    'gift_count'       => 0,
                    'platforms'        => [],
                ];
            }

            $grouped[$cateId]['valid_bet']       += (float)$row->valid_bet;
            $grouped[$cateId]['total_bet']        += (float)$row->total_bet;
            $grouped[$cateId]['player_win_loss']  += (float)$row->player_win_loss;
            $grouped[$cateId]['gift_amount']      += (float)$row->gift_amount;
            $grouped[$cateId]['gift_count']       += (int)$row->gift_count;
            $grouped[$cateId]['platforms'][]       = [
                'platform_id'      => (int)$row->platform_id,
                'name'             => $row->platform_name,
                'valid_bet'        => (float)$row->valid_bet,
                'total_bet'        => (float)$row->total_bet,
                'player_win_loss'  => (float)$row->player_win_loss,
                'gift_amount'      => (float)$row->gift_amount,
                'gift_count'       => (int)$row->gift_count,
            ];
        }

        return array_values($grouped);
    }
}
