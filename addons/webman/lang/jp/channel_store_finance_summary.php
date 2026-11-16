<?php

return [
    'title' => '店舗財務サマリー',

    'fields' => [
        'id'                       => 'ID',
        'store_name'               => '店舗名',
        'store_username'           => 'ログインID',
        'agent_name'               => '代理店',
        'electronic_game_win_loss' => 'ゲーム損益',
        'machine_win_loss'         => '実機損益',
        'lottery_ticket_sent'      => '宝くじ発行',
        'electronic_jackpot'       => 'ゲームジャックポット',
        'machine_jackpot'          => '実機ジャックポット',
        'wallet_increase'          => 'ウォレット増加',
        'wallet_decrease'          => 'ウォレット減少',
        'machine_rebate'           => '実機リベート',
        'electronic_rebate'        => 'ゲームリベート',
        'coupon_sent'              => '体験/福利クーポン',
    ],

    'filter' => [
        'select_agent'         => '代理店を選択',
        'all_agents'           => 'すべての代理店',
        'select_store'         => '店舗を選択',
        'all_stores'           => 'すべての店舗',
        'placeholder_stores'   => '店舗を選択（複数選択可）',
        'placeholder_agent'    => '代理店（全て）',
        'placeholder_date'     => '日付タイプ',
        'time_range'           => '期間',
        'start_time'           => '開始時間',
        'end_time'             => '終了時間',
        'query'                => '検索',
        'reset'                => 'リセット',
    ],

    'date_types' => [
        'today'      => '今日',
        'yesterday'  => '昨日',
        'this_week'  => '今週',
        'last_week'  => '先週',
        'this_month' => '今月',
        'last_month' => '先月',
    ],

    'panel' => [
        'machine_win_loss'    => '実機損益',
        'electronic_win_loss' => '電子ゲーム損益',
        'net_revenue'         => '純収益',
        'operating_cost'      => '運営コスト',
        'cost_detail'         => '運営コスト明細（タイプをクリックして説明を確認）',
        'wallet_detail'       => 'ウォレット流水参考（収益に含まず）',
        'total'               => '合計',
        'profit'              => '利益',
        'loss'                => '損失',
        'even'                => '均衡',
    ],

    'states' => [
        'hint'          => '店舗と日付範囲を選択して検索してください',
        'search_failed' => '検索失敗',
        'retry'         => '再試行',
        'no_data'       => '条件に一致するデータがありません',
    ],

    'delivery_types' => [
        1  => '管理者ポイント追加',
        4  => 'マシンスコアアップ',
        5  => 'マシンスコアダウン',
        6  => 'チャージ（スコアオープン）',
        7  => '出金（スコアウォッシュ）',
        8  => '管理者ポイント控除',
        12 => 'プロモーター手数料',
        13 => 'ジャックポット当選',
        16 => '招待報酬',
        22 => '特殊タイプ',
        23 => '現金投入',
        31 => '事前控除',
        32 => '払い戻し',
        33 => '抽選券報酬',
        34 => 'VIP誕生日ボーナス',
        35 => 'VIPアップグレードボーナス',
        36 => 'リベートプール報酬',
        37 => 'VIPデイリーログイン',
        38 => '外部活動ボーナス',
        39 => 'リーダーボード報酬',
    ],

    'desc_panel' => [
        'title'      => '口座変動タイプ説明',
        'click_hint' => "左側のタイプをクリックして\n詳細を確認",
    ],

    'summary' => [
        'row_label' => '合計',
    ],
];
