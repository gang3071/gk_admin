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
        'drawing' => '抽選中',
        'drawn' => '抽選済み（配布待ち）', // ⭐ 新規
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
        'pending' => '配布待ち',
        'claimed' => '配布済み',
        'expired' => '期限切れ',
        'cancelled' => 'キャンセル',
        'processing' => '配布中', // ⭐ 新規
        'failed' => '失敗',
        'granted' => '配布済み', // レガシー
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
        'view_detail' => '詳細表示',
        'close' => 'キャンペーン終了',
        'export' => '記録エクスポート',
        'grant' => '賞品付与',
        'distribute' => '配布',
        'batch_distribute' => '一括配布',
        'batch_distribute_selected' => '選択したレコードを一括配布',
    ],

    // 統計
    'stats' => [
        'total_activities' => '総キャンペーン数',
        'ongoing_activities' => '実施中キャンペーン',
        'total_draws' => '総抽選回数',
        'total_winners' => '総当選者数',
        'total_prize_amount' => '総賞金額',
        'pending_count' => '配布待ちレコード',      // ⭐ 新規
        'pending_amount' => '配布待ち金額',        // ⭐ 新規
        'claimed_count' => '配布済みレコード',      // ⭐ 新規
        'claimed_amount' => '配布済み金額',        // ⭐ 新規
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
        'distribute_success' => '配布成功',
        'distribute_failed' => '配布失敗',
        'batch_complete' => 'バッチ配布完了：成功 {success} 件、失敗 {fail} 件',
        'batch_distribute_selected' => '選択したレコードを一括配布',
        'export_in_development' => 'エクスポート機能開発中',
        'admin_manual_update' => '管理者による手動更新',
    ],

    // エラーメッセージ
    'error' => [
        'record_not_found' => 'レコードが見つかりません',
        // 入力検証
        'invalid_record_id' => 'パラメータエラー：レコードIDが無効です',
        'invalid_activity_id' => 'パラメータエラー：アクティビティIDが無効です',
        'invalid_record_ids' => 'パラメータエラー：レコードIDは配列である必要があります',
        'invalid_record_id_value' => 'パラメータエラー：レコードIDに不正な値が含まれています',
        'note_too_long' => '配布メモは255文字を超えることはできません',
        'no_selection' => 'アクティビティIDを指定するか、レコードを選択してください',
        'no_pending_records' => '配布待ちのレコードがありません',
        // ビジネスロジック検証
        'invalid_status' => 'レコードのステータスが正しくありません。配布待ちのレコードのみ配布できます',
        'status_changed' => 'ステータスが変更されました',
        'empty_prize' => '空の賞品は配布不要です',
        'invalid_amount' => '賞品金額は0より大きい必要があります',
        'player_not_found' => 'プレイヤーが見つかりません',
        'player_disabled' => 'プレイヤーが無効化されているため、報酬を配布できません',
        'activity_not_found' => 'アクティビティが見つかりません',
        'activity_invalid_status' => 'アクティビティのステータスが間違っています。抽選済み配布待ちのアクティビティのみ配布できます',
        'amount_exceeded' => '配布金額が総賞金額を超えています',
        'ticket_not_found_or_used' => '券番号 {ticket_no} が見つからないか、既に使用されています',
        'prize_level_not_found_for_ticket' => '券番号 {ticket_no} の賞品レベルが見つかりません',
        'bet_progress_not_found' => 'ベット進行状況記録が見つかりません',
        // その他
        'too_many_levels' => '賞品レベルは最大{max}個まで設定できます',
        'no_prize_levels' => '少なくとも1つの賞品レベルを設定してください',
        'no_prizes' => '賞品数量はゼロにできません',
        'probability_exceed' => '当選確率の合計は100%を超えることはできません。現在：{total}%',
        'level_rank_exists' => 'このランクは既に存在します',
        'invalid_prize_type' => '無効な賞品タイプ',
    ],

    // 詳細ビューラベル
    'view' => [
        'detail_title' => '当選記録詳細',
        'basic_info' => '基本情報',
        'prize_info' => '賞品情報',
        'distribution_info' => '配布情報',
        'activity_name' => 'アクティビティ名',
        'ticket_no' => '券番号',
        'player_name' => 'プレイヤー',
        'player_phone' => '電話番号',
        'prize_name' => '賞品名',
        'prize_type' => '賞品タイプ',
        'prize_amount' => '賞品金額',
        'status' => 'ステータス',
        'distributed_at' => '配布時刻',
        'distributed_by' => '配布者',
        'distribution_note' => '配布メモ',
        'created_at' => '作成日時',
        'updated_at' => '更新日時',
    ],

    // 確認ダイアログ
    'confirm' => [
        'distribute' => 'この賞品をプレイヤーアカウントに配布してもよろしいですか？',
    ],

    // モーダルタイトル
    'modal' => [
        'record_win_title' => '当選記録入力',
        'live_url_title' => 'ライブ配信URL追加',
        'live_url_prompt' => 'ライブ配信URLを入力してください:',
        'live_url_required' => 'ライブ配信URLを入力してください',
        'batch_distribute_title' => '一括配布',
    ],

    // フォームラベル
    'form' => [
        'select_activity' => 'アクティビティを選択',
        'select_activity_help' => '抽選済み配布待ちのアクティビティのみ表示',
        'distribution_note' => '配布メモ',
        'distribution_note_placeholder' => '配布メモを入力してください（任意）',
    ],
];
