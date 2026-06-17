<?php

return [
    // 菜单
    'menu' => [
        'main' => '抽選チケット管理',
        'dashboard' => '進行中のアクティビティ',
        'history' => '過去のアクティビティ記録',
        'records' => '当選記録',
    ],

    // 标题
    'title' => [
        'main' => '抽選チケット管理',
        'activity_detail' => 'アクティビティ詳細',
        'ticket_list' => '摸奖券列表',
        'record_list' => '中奖记录',
    ],

    // 字段
    'fields' => [
        'id' => 'ID',
        'name' => 'アクティビティ名',
        'activity_name' => 'アクティビティ名',
        'description' => '説明',
        'cover_image' => 'アクティビティカバー',
        'cover_image_upload' => 'アクティビティカバー画像',
        'live_url' => 'ライブURL',
        'start_time' => '開始時間',
        'end_time' => '終了時間',
        'status' => 'ステータス',
        'total_tickets' => 'チケット総数',
        'used_tickets' => '使用済チケット数',
        'usage_rate' => '使用率',
        'pending_count' => '未配布',
        'max_ticket_no' => '最大チケット番号',  // ⭐ 新規追加：抽選時のボール最大番号
        'operation' => '操作',  // ⭐ 新增
        'prize_config' => '賞品設定',
        'created_at' => '作成日時',
        'prize_level_config' => '賞品レベル設定',
        'total_probability' => '確率合計',
        'level' => 'レベル',
        'time_range' => '期間',
        'player_account' => 'プレイヤーアカウント',
        'prize_level' => '当選レベル',
        'remark' => '備考',
        'distribution_remark' => '配布備考',
        'vip_level' => 'VIPレベル',
        'bet_amount_required' => '必要ベット額',
        'ticket_count' => 'チケット数',
        'prize_amount' => '賞金額',
        'prize_count' => '賞品数',
        'ticket_no_input' => '当選チケット番号',

        // 中奖记录
        'player_name' => 'プレイヤー名',
        'player_phone' => 'プレイヤー電話',
        'ticket_no' => 'チケット番号',
        'prize_type' => '賞品タイプ',
        'prize_name' => '賞品名',
        'record_status' => '配布ステータス',
        'created_time_range' => '作成時間範囲',
        'source' => '来源',
        'used_at' => '使用日時',
        'expired_at' => '期限日時',
    ],

    // 占位符
    'placeholder' => [
        'name' => 'アクティビティ名を入力してください',
        'description' => '説明を入力してください',
        'start_time' => '開始時間を選択してください',
        'end_time' => '終了時間を選択してください',
        'level_rank' => 'レベルランクを選択してください',
        'prize_type' => '賞品タイプを選択してください',
        'player_account' => 'プレイヤーアカウント/電話番号/UUIDを入力してください',
        'prize_level' => '当選レベルを選択してください',
        'remark' => '任意入力、当選詳細を備考可能',
        'live_url' => '例: rtmp://live.example.com/stream/12345',
        'ticket_no' => '6桁のチケット番号を入力してください',
        'distribute_remark' => '任意入力、配布説明を備考可能',
    ],

    // 模态框
    'modal' => [
        'record_win_title' => '当選記録を入力',
        'live_url_title' => 'ライブURLを追加',
        'live_url_prompt' => 'ライブストリームURLを入力:',
        'live_url_required' => 'ライブURLを入力してください',
        'batch_distribute_title' => '一括賞品配布',
        'distribute_by_ticket_title' => 'チケット番号入力で賞品配布',
        'ticket_list_title' => 'チケット配布リスト',
    ],

    // 活动状态
    'status' => [
        'all' => 'すべて',
        'not_started' => '未開始',
        'ongoing' => '進行中',
        'ended' => '終了',
        'closed' => 'クローズ済',
        'preheating' => 'プレヒート期',
        'betting' => 'ベット中',
        'drawing' => '抽選中',
        'drawn' => '抽選完了、配布待ち', // ⭐ 新增
        'unknown' => '不明',
    ],

    // 直播状态
    'live_status' => [
        'not_started' => '未配信',
        'ongoing' => '配信中',
        'ended' => '終了',
        'unknown' => '不明',
    ],

    // 摸奖券状态
    'ticket_status' => [
        'unused' => '未使用',
        'used' => '使用済',
        'expired' => '期限切れ',
        'unknown' => '不明',
    ],

    // 来源
    'source' => [
        'recharge' => 'チャージ特典',
        'activity' => 'アクティビティ特典',
        'manual' => '手動配布',
        'unknown' => '不明な来源',
    ],

    // 中奖记录状态
    'record_status' => [
        'pending' => '保留中',
        'claimed' => '配布済', // ⭐ 更新
        'expired' => '期限切れ', // ⭐ 新增
        'cancelled' => 'キャンセル済', // ⭐ 新增
        'processing' => '処理中', // ⭐ 新增
        'failed' => '配布失敗',
        'granted' => '配布済', // 兼容舊代码
        'unknown' => '不明',
    ],

    // 奖品类型
    'prize_type' => [
        'cash' => '現金',
        'bonus' => 'ボーナス',
        'item' => '実物',
        'points' => '積分',
        'empty' => '賞なし',
        'unknown' => '不明なタイプ',
    ],

    // 中奖等级名称
    'level_name' => [
        'special' => '特等賞',
        'first' => '一等賞',
        'second' => '二等賞',
        'third' => '三等賞',
        'fourth' => '四等賞',
        'fifth' => '五等賞',
        'sixth' => '六等賞',
        'seventh' => '七等賞',
        'eighth' => '八等賞',
        'ninth' => '九等賞',
        'default' => 'レベル:rank',
    ],

    // 中奖记录字段 ⭐ 追加
    'record_fields' => [
        'prize_type' => '賞品タイプ',
        'prize_level_name' => '賞品レベル',
        'created_at' => '作成日時',
    ],

    // 中奖等级字段
    'prize_level_fields' => [
        'level_rank' => 'レベルランク',
        'level_name' => 'レベル名',
        'prize_type' => '賞品タイプ',
        'prize_amount' => '賞金額',
        'prize_item_name' => '実物名',
        'prize_item_image' => '実物画像',
        'prize_count' => '賞品数',
        'won_count' => '当選数',  // ⭐ 追加
        'remaining_count' => '残数',  // ⭐ 追加
        'win_probability' => '当選確率(%)',
        'description' => '賞品説明',
    ],

    // 操作
    'action' => [
        'create' => 'アクティビティを作成',
        'create_first' => '今すぐ作成',
        'edit' => 'アクティビティ編集',
        'view' => '詳細を表示',
        'view_detail' => '詳細を表示',
        'operation' => '操作',  // ⭐ 新增
        'prize_config' => '賞品設定',
        'close' => 'アクティビティをクローズ',
        'export' => '記録をエクスポート',
        'add_prize_level' => '賞品レベルを追加',
        'record_win' => '当選を記録',
        'start_drawing' => '抽選を開始',
        'stop_drawing' => '抽選を停止',
        'add_live_url' => 'ライブURLを追加',
        'expand' => '展開',
        'collapse' => '折りたたむ',
        'distribute' => '配布',
        'batch_distribute' => '一括配布',
        'batch_distribute_selected' => '選択を一括配布',
        'distribute_by_ticket' => 'チケットで配布',
        'distribute_all_pending' => '賞品を配布',  // ⭐ 新規追加: すべての未配布記録を一括配布
        'view_ticket_list' => '配布リストを表示',
        'add_ticket' => 'チケットを追加',
        'select_image' => '画像を選択',
        'confirm_distribute' => '配布を確認',
    ],

    // 统计
    'stats' => [
        'total_activities' => '総アクティビティ数',
        'ongoing_activities' => '進行中アクティビティ',
        'total_draws' => '総抽選回数',
        'total_winners' => '総当選者数',
        'total_prize_amount' => '総賞金額',
        'pending_count' => '配布待ち記録',       // ⭐ 新增
        'pending_amount' => '配布待ち金額',      // ⭐ 新增
        'claimed_count' => '配布済記録',       // ⭐ 新增
        'claimed_amount' => '配布済金額',      // ⭐ 新增
    ],

    // 消息
    'message' => [
        'create_success' => 'アクティビティ作成成功',
        'update_success' => 'アクティビティ更新成功',
        'close_success' => 'アクティビティクローズ成功',
        'activity_not_found' => 'アクティビティが存在しません',
        'activity_closed' => 'アクティビティはクローズ済',
        'activity_not_ongoing' => '進行中のアクティビティのみクローズ可能',
        'time_conflict' => 'アクティビティ時間が衝突',
        'prize_level_saved' => '賞品レベル保存成功',
        'prize_level_deleted' => '賞品レベル削除成功',
        'no_activities' => 'アクティビティなし',
        'no_prize_config' => '賞品レベル未設定',
        'prize_level_hint' => '最大10個の賞品レベルを設定可能、当選確率合計は100%を超えることはできません',
        'upload_success' => 'アップロード成功',
        'image_upload_success' => '画像アップロード成功',
        'image_upload_failed' => '画像アップロード失敗',
        'distribute_success' => '配布成功',
        'distribute_failed' => '配布失敗',
        'batch_complete' => '一括配布完了：成功 {success} 件、失敗 {fail} 件',
        'batch_distribute_selected' => '選択記録を一括配布',
        'export_in_development' => 'エクスポート機能開発中',
        'live_url_updated' => 'ライブURL設定成功',
        'live_url_generated' => 'ライブURL生成成功',
        'player_config_loaded' => 'プレーヤー設定読み込み成功',
        'player_config_loaded_with_region' => 'プレーヤー設定読み込み成功（{region}ドメインを使用）',
        'record_success' => '当選記録入力成功',
        'record_success_count' => '{count}件の当選記録を記録しました',
        'live_started' => 'ライブが開始されました',
        'live_ended' => 'ライブが終了しました',
        'select_tencent_config' => 'テンセントクラウド設定を選択してください',
        'stream_name_required' => 'ストリーム名は必須です',
        'tencent_config_not_found' => 'テンセントクラウド設定が見つかりません',
        'status_updated' => 'ステータス更新成功',
        'admin_manual_update' => '管理者手動更新',
        'fetch_failed' => 'アクティビティリスト取得失敗',
        'fetch_detail_failed' => 'アクティビティ詳細取得失敗',
        'close_activity_failed' => 'アクティビティ閉鎖失敗',
        'min_one_ticket' => '少なくとも1つのチケット番号を入力してください',
        'please_input_ticket' => 'チケット番号を入力してください',
        'ticket_must_6_digits' => 'チケット番号は6桁の数字である必要があります',
        'ticket_format_error' => 'チケット番号の形式が正しくありません。数字のみで6文字以内にしてください',
        'no_prize_level' => 'このアクティビティはまだ賞品レベルが設定されていません',
        'distribute_hint' => '当選チケット番号を入力してください、システムは番号に基づいて賞品レベルを自動識別し配布します',
        'drawing_started' => '抽選開始、抽選段階に入りました',
        'activity_ended' => 'アクティビティが終了しました',
    ],

    // 错误信息
    'error' => [
        'record_not_found' => '記録が存在しません',
        'live_url_required' => 'ライブURLを入力してください',
        'live_url_too_long' => 'ライブURLが長すぎます（最大500文字）',
        'cannot_start_drawing' => 'アクティビティ進行中は抽選を開始できません。終了まで待ってください（終了済みステータスのみ抽選開始可能）',
        'cannot_stop_drawing' => '現在のステータスでは抽選を停止できません（抽選中のアクティビティのみ終了可能）',
        'cannot_record_win_in_current_status' => '現在のステータスでは当選を記録できません（進行中または抽選中のステータスのみ記録可能）',
        // 输入驗證
        'invalid_record_id' => 'パラメータエラー：記録IDが無効',
        'invalid_activity_id' => 'パラメータエラー：アクティビティIDが無効',
        'invalid_record_ids' => 'パラメータエラー：記録IDは配列である必要があります',
        'invalid_record_id_value' => 'パラメータエラー：記録IDに不正な値が含まれています',
        'note_too_long' => '配布備考は255文字を超えることはできません',
        'no_selection' => 'アクティビティIDを指定するか記録を選択してください',
        'no_pending_records' => '配布待ちの記録がありません',
        // 业务邏輯驗證
        'invalid_status' => '記録ステータスが正しくありません',
        'status_changed' => 'ステータスが変更されました',
        'empty_prize' => '賞なし、配布不要',
        'invalid_amount' => '賞金額は0より大きくする必要があります',
        'player_not_found' => 'プレイヤーが見つかりません',
        'player_disabled' => 'プレイヤーは無効化されています',
        'activity_not_found' => 'アクティビティが存在しません',
        'activity_invalid_status' => 'アクティビティステータスエラー',
        'activity_not_in_drawing_status' => 'アクティビティステータスエラー、抽選中または終了ステータスでのみ配布可能',
        'amount_exceeded' => '配布金額が総賞金額を超えています',
        'ticket_not_found_or_used' => 'チケット {ticket_no} が存在しないか使用済みです',
        'prize_level_not_found_for_ticket' => 'チケット {ticket_no} の賞品レベルが存在しません',
        'invalid_ticket_format' => 'チケット {ticket_no} の形式が正しくありません。数字のみで6文字以内にしてください',
        'bet_progress_not_found' => 'ベット進捗記録が見つかりません',
        // 其他
        'too_many_levels' => '最大 {max} 個の賞品レベルまで設定可能',
        'no_prize_levels' => '少なくとも1つの賞品レベルを設定してください',
        'no_prizes' => '賞品数は0にできません',
        'probability_exceed' => '確率合計は100%を超えることはできません、現在：{total}%',
        'level_rank_exists' => 'そのレベルランクは既に存在します',
        'invalid_prize_type' => '無効な賞品タイプ',
        'name_required' => 'アクティビティ名を入力してください',
        'time_required' => 'アクティビティ期間を選択してください',
        'invalid_time' => '終了時刻は開始時刻より後である必要があります',
        'invalid_time_format' => '時間形式エラー、有効な日時を選択してください',
        'end_before_start' => '終了時刻は開始時刻より後である必要があります',
        'start_time_in_past' => '開始時刻は現在時刻より前にできません',
        'duration_too_short' => 'アクティビティ期間が短すぎます、最小 {min}',
        'duration_too_long' => 'アクティビティ期間が長すぎます、最大 {max}',
        'cannot_edit_started' => '未開始のアクティビティのみ編集可能',
        'invalid_file' => '無効なファイル',
        'invalid_image_type' => 'jpg、png形式のみ対応',
        'file_too_large' => 'ファイルサイズは2MBを超えることはできません',
        'upload_failed' => 'アップロード失敗、再試行してください',
        'invalid_params' => 'パラメータエラー',
        'activity_not_ongoing' => '進行中のアクティビティのみ当選記録可能',
        'prize_level_not_found' => '賞品レベルが存在しません',
        'live_url_required' => '先にライブURLを設定してください',
        'invalid_status_value' => '無効なステータス値',
    ],

    // 帮助文本
    'help' => [
        'cover_image' => '推奨：750x400px、jpg/png、最大2MB',
        'cover_alt' => 'アクティビティカバー',
        'cover_preview' => 'カバープレビュー',
        'vip_config_hint' => '各VIPレベルでベット額達成時に発行するチケット数を設定',
        'prize_config_hint' => '賞品レベルと賞金額を設定（現金のみ）',
        'input_ticket_no' => 'チケット番号を入力:',
    ],

    // 详情视图标签
    'view' => [
        'detail_title' => '当選記録詳細',
        'basic_info' => '基本情報',
        'prize_info' => '賞品情報',
        'distribution_info' => '配布情報',
        'activity_name' => 'アクティビティ名',
        'ticket_no' => 'チケット番号',
        'player_name' => 'プレイヤー',
        'player_phone' => '電話番号',
        'prize_name' => '賞品名',
        'prize_type' => '賞品タイプ',
        'prize_amount' => '賞金額',
        'status' => 'ステータス',
        'distributed_at' => '配布時刻',
        'distributed_by' => '配布者',
        'distribution_note' => '配布備考',
        'created_at' => '作成日時',
        'updated_at' => '更新日時',
    ],

    // 确认对话框
    'confirm' => [
        'distribute' => 'この賞品をプレイヤーアカウントに配布しますか？',
        'distribute_all_pending' => 'このアクティビティの記録済み未配布の賞品をすべて配布しますか？\nこの操作によりすべての未配布記録が一括配布されます。慎重に操作してください。',  // ⭐ 新規追加
    ],

    // 表单标签
    'form' => [
        'select_activity' => 'アクティビティを選択',
        'select_activity_help' => '抽選完了、配布待ちのアクティビティのみ表示',
        'distribution_note' => '配布備考',
        'distribution_note_placeholder' => '配布備考を入力（任意）',
        'vip_config_section' => 'VIPレベルベット額設定',
        'prize_config_section' => '賞品レベル設定',
        'no_vip_data' => 'VIPレベルデータがありません',
        'no_vip_config' => 'VIPレベル未設定',
    ],

    // フィルター ⭐ 追加
    'filter' => [
        'time_range' => '時間範囲',
        'create_time_range' => '作成時間範囲',
        'activity_time_range' => 'アクティビティ時間範囲',
    ],

    // 验证消息
    'validation' => [
        'name_required' => 'アクティビティ名を入力してください',
        'name_max_length' => 'アクティビティ名は100文字を超えることはできません',
        'start_time_required' => '開始時間を選択してください',
        'end_time_required' => '終了時間を選択してください',
        'ticket_no_required' => 'チケット番号を入力してください',
        'image_format_error' => 'JPG/PNG形式の画像のみアップロード可能！',
        'image_size_error' => '画像サイズは2MBを超えることはできません！',
    ],

    // 其他文本
    'ui' => [
        'total_records' => '合計 {total} 件',
        'yuan' => '円',
        'upload_failed' => 'アップロード失敗',
    ],
];
