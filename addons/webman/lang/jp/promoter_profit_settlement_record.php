<?php

use addons\webman\model\PromoterProfitRecord;
use addons\webman\model\PromoterProfitSettlementRecord;

return [
    /** TODO 翻译 */
    'title' => '決済記録',
    'fields' => [
        'id' => 'ID',
        'total_withdraw_amount' => '出金金額',
        'total_recharge_amount' => 'リチャージ金額',
        'total_bonus_amount' => 'アクティビティギフト額',
        'total_admin_deduct_amount' => '管理者控除ポイント',
        'total_admin_add_amount' => '管理者がポイントを追加',
        'total_present_amount' => 'ギフト金額',
        'total_machine_up_amount' => 'マシンポイントアップ',
        'total_machine_down_amount' => '機械下限点',
        'total_lottery_amount' => '宝くじボーナス',
        'total_profit_amount' => '決済利益分配',
        'total_game_amount' => 'ゲームの金額',
        'tradeno' => '決済注文番号',
        'type' => 'タイプ',
        'last_profit_amount' => '最終決済利益分配金(個人)',
        'adjust_amount' => '決済調整額',
        'actual_amount' => '実際に受け取った金額',
        'total_commission_amount' => 'チャージ手数料',
        'user_id' => 'マシンクリック',
        'user_name' => '決済管理者',
        'created_at' => '決済時間',
        'updated_at' => '更新時刻',
    ],
    'status' => [
        PromoterProfitRecord::STATUS_UNCOMPLETED => '未確定',
        PromoterProfitRecord::STATUS_COMPLETED => '決済済み',
    ],
    'type' => [
        PromoterProfitSettlementRecord::TYPE_SETTLEMENT => '決済',
        PromoterProfitSettlementRecord::TYPE_CLEAR => 'クリア',
    ],
    'player_promoter' => [
        'phone' => 'プロモーターの携帯電話番号',
        'uuid' => 'プロモーター UUID'
    ],
    'settlement_time_start' => '決済開始時刻',
    'settlement_time_end' => '決済終了時刻',
    'profit_settlement_info' => '利益分配データ',
    'settlement_data' => '決済データ',
    'settlement_detail' => '利益分配レポート',
    'channel_settlement_promoter_null' => '決済が必要なプロモーターはいません',
    'success' => 'プロモーター決済成功',
    'channel_promotion_closed' => 'プロモーター機能は終了しました',
    'channel_closed' => 'チャンネルは閉鎖されました',
];
