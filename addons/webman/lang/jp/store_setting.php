<?php

return [
    'title' => '店舗システム設定',
    'all_stores' => 'すべての店舗',
    'fields' => [
        'feature' => '機能',
        'setting' => '設定',
        'status' => 'ステータス',
        'player' => '店舗を紐付け',

        // 設定項目
        'home_notice' => 'ホーム通知メッセージ',
        'store_marquee' => '店舗マーキー',
        'order_expiration' => '注文有効期限',
        'business_hours' => '営業時間',
        'enable_physical_machine' => '実機を有効にする',
        'enable_live_baccarat' => 'ライブバカラを有効にする',
        'machine_crash_amount' => 'クラッシュ金額',
    ],
    'home_notice_max_len' => 'ホーム通知メッセージは最大500文字',
    'store_marquee_max_len' => '店舗マーキーは最大500文字',
    'minutes' => '分',
    'time_range' => '時間範囲',
    'edit_business_hours' => '営業時間を編集',
    'enable' => '有効',
    'disable' => '無効',

    // 検証メッセージ
    'validation' => [
        'integer' => '整数でなければなりません',
        'numeric' => '数字でなければなりません',
        'max' => '{max} を超えることはできません',
        'min' => '{min} 未満にすることはできません',
    ],
];