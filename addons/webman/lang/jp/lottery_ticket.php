<?php

return [
    'title' => '抽選券管理',

    // メニュー
    'menu' => [
        'main' => '抽選券管理',
        'dashboard' => '実施中のキャンペーン',
        'history' => 'キャンペーン履歴',
        'records' => '当選記録',
    ],

    // フィールド
    'fields' => [
        'id' => 'ID',
        'name' => 'キャンペーン名',
        'activity_name' => 'キャンペーン名',
        'description' => '説明',
        'start_time' => '開始時間',
        'end_time' => '終了時間',
        'status' => 'ステータス',
        'total_tickets' => '総発行数',
        'used_tickets' => '使用済み数',
        'usage_rate' => '使用率',
        'prize_config' => '賞品設定',
        'created_at' => '作成日時',

        // 当選記録
        'player_name' => 'プレイヤー名',
        'player_phone' => '電話番号',
        'ticket_no' => '抽選券番号',
        'prize_type' => '賞品タイプ',
        'prize_name' => '賞品名',
        'prize_amount' => '賞品金額',
        'record_status' => '付与状態',
        'remark' => '備考',
        'draw_time' => '抽選時間',
    ],

    // アクティビティステータス
    'status' => [
        'not_started' => '未開始',
        'ongoing' => '実施中',
        'ended' => '終了',
        'closed' => '閉鎖',
        'unknown' => '不明',
    ],

    // チケットステータス
    'ticket_status' => [
        'unused' => '未使用',
        'used' => '使用済み',
        'expired' => '期限切れ',
        'unknown' => '不明',
    ],

    // ソース
    'source' => [
        'recharge' => 'チャージボーナス',
        'activity' => 'キャンペーンボーナス',
        'manual' => '手動付与',
        'unknown' => '不明',
    ],

    // 付与ステータス
    'record_status' => [
        'pending' => '付与待ち',
        'granted' => '付与済み',
        'failed' => '失敗',
        'unknown' => '不明',
    ],

    // 賞品タイプ
    'prize_type' => [
        'cash' => '現金',
        'bonus' => 'ボーナス',
        'item' => '実物',
        'points' => 'ポイント',
        'empty' => 'ハズレ',
        'unknown' => '不明',
    ],

    // 賞品レベル名
    'level_name' => [
        'special' => '特等賞',
        'first' => '1等賞',
        'second' => '2等賞',
        'third' => '3等賞',
        'fourth' => '4等賞',
        'fifth' => '5等賞',
        'sixth' => '6等賞',
        'seventh' => '7等賞',
        'eighth' => '8等賞',
        'ninth' => '9等賞',
    ],

    // 賞品レベルフィールド
    'prize_level_fields' => [
        'level_rank' => 'ランク',
        'level_name' => 'レベル名',
        'prize_type' => '賞品タイプ',
        'prize_amount' => '金額',
        'prize_item_name' => '商品名',
        'prize_item_image' => '商品画像',
        'prize_count' => '数量',
        'win_probability' => '当選確率(%)',
        'description' => '説明',
    ],

    // アクション
    'action' => [
        'create' => 'キャンペーン作成',
        'edit' => 'キャンペーン編集',
        'view' => '詳細表示',
        'close' => 'キャンペーン終了',
        'export' => '記録エクスポート',
        'grant' => '賞品付与',
    ],

    // 統計
    'stats' => [
        'total_activities' => '総キャンペーン数',
        'ongoing_activities' => '実施中キャンペーン',
        'total_draws' => '総抽選回数',
        'total_winners' => '総当選者数',
        'total_prize_amount' => '総賞金額',
    ],

    // メッセージ
    'message' => [
        'create_success' => 'キャンペーンを作成しました',
        'update_success' => 'キャンペーンを更新しました',
        'close_success' => 'キャンペーンを終了しました',
        'close_confirm' => 'このキャンペーンを終了してもよろしいですか？',
        'activity_not_found' => 'キャンペーンが見つかりません',
        'activity_closed' => 'キャンペーンは終了しました',
        'time_conflict' => '時間の競合',
        'prize_level_saved' => '賞品レベルを保存しました',
        'prize_level_deleted' => '賞品レベルを削除しました',
    ],

    // エラーメッセージ
    'error' => [
        'too_many_levels' => '賞品レベルは最大{max}個まで設定できます',
        'no_prize_levels' => '少なくとも1つの賞品レベルを設定してください',
        'no_prizes' => '賞品数量はゼロにできません',
        'probability_exceed' => '当選確率の合計は100%を超えることはできません。現在：{total}%',
        'level_rank_exists' => 'このランクは既に存在します',
        'invalid_prize_type' => '無効な賞品タイプ',
    ],
];
