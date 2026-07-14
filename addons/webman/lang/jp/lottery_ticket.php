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
        'stream_name' => 'ストリーム名',
        'push_url' => 'プッシュURL',
        'push_server' => 'プッシュサーバー',
        'stream_key' => 'ストリームキー',
        'expire_time' => '有効期限',
        'start_time' => '開始時間',
        'end_time' => '終了時間',
        'status' => 'ステータス',
        'total_tickets' => 'チケット総数',
        'used_tickets' => '使用済チケット数',
        'usage_rate' => '使用率',
        'pending_count' => '払い出し数',
        'max_ticket_no' => '発行済最大チケット番号',  // ⭐ 現在発行済の最大チケット番号（例：000123）
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
        'distribution_remark' => '払い出し備考',
        'vip_level' => 'VIPレベル',
        'bet_amount_required' => '必要ベット額',
        'ticket_count' => 'チケット数',
        'prize_amount' => '賞金額',
        'prize_count' => '賞品数',
        'ticket_no_input' => '当選チケット番号',

        // 中奖记录
        'player_name' => 'プレイヤー名',
        'player_uuid' => 'プレイヤーUUID',
        'player_username' => 'プレイヤーアカウント',
        'ticket_no' => 'チケット番号',
        'prize_type' => '賞品タイプ',
        'prize_name' => '賞品名',
        'record_status' => '払い出しステータス',
        'created_time_range' => '作成時間範囲',
        'source' => '来源',
        'used_at' => '使用日時',
        'expired_at' => '期限日時',

        // 統計フィールド
        'total_winners' => '当選者総数',           // ⭐ 履歴一覧ページ
        'total_prize_amount' => '賞金総額',        // ⭐ 履歴一覧ページ
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
        'distribute_remark' => '任意入力、払い出し説明を備考可能',
    ],

    // 模态框
    'modal' => [
        'record_win_title' => '当選記録を入力',
        'live_url_title' => 'ライブURLを追加',
        'live_url_prompt' => 'ライブストリームURLを入力:',
        'live_url_required' => 'ライブURLを入力してください',
        'batch_distribute_title' => '一括賞品払い出し',
        'distribute_by_ticket_title' => 'チケット番号入力で賞品払い出し',
        'ticket_list_title' => 'チケット払い出しリスト',
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
        'pending_draw' => '抽選待ち',          // ⭐ 新増：活動終了、抽選待機中
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
        'betting' => 'ベット進捗で獲得',
        'recharge' => 'チャージ特典',
        'activity' => 'アクティビティ特典',
        'manual' => '手動払い出し',
        'unknown' => '不明な来源',
    ],

    // 中奖记录状态
    'record_status' => [
        'pending' => '保留中',
        'claimed' => '払い出し済', // ⭐ 更新
        'expired' => '期限切れ', // ⭐ 新增
        'cancelled' => 'キャンセル済', // ⭐ 新增
        'processing' => '処理中', // ⭐ 新增
        'failed' => '払い出し失敗',
        'granted' => '払い出し済', // 兼容舊代码
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
        'distribute' => '払い出し',
        'batch_distribute' => '一括払い出し',
        'batch_distribute_selected' => '選択を一括払い出し',
        'distribute_by_ticket' => 'チケットで払い出し',
        'distribute_all_pending' => '賞品を払い出し',  // ⭐ 新規追加: すべての未払い出し記録を一括払い出し
        'view_ticket_list' => '払い出しリストを表示',
        'add_ticket' => 'チケットを追加',
        'select_image' => '画像を選択',
        'confirm_distribute' => '払い出しを確認',
    ],

    // 统计
    'stats' => [
        'total_activities' => '総アクティビティ数',
        'ongoing_activities' => '進行中アクティビティ',
        'total_draws' => '総抽選回数',
        'total_winners' => '総当選者数',
        'total_prize_amount' => '総賞金額',
        'pending_count' => '払い出し記録',
        'pending_amount' => '払い出し金額',
        'claimed_count' => '払い出し済記録',
        'claimed_amount' => '払い出し済金額',
        'count_suffix' => '件',
        'panel_header' => '統計データを表示',
        'loading' => 'データ読み込み中...',
        'refresh' => '更新',
        'load_error' => 'データ読み込み失敗',
        'retry' => '再試行',
        'click_to_view' => 'クリックして統計データを表示',
        'load_failed_msg' => 'データ読み込みに失敗しました。再試行してください',
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
        'distribute_success' => '払い出し成功',
        'distribute_failed' => '払い出し失敗',
        'batch_complete' => '一括払い出し完了：成功 {success} 件、失敗 {fail} 件',
        'batch_distribute_selected' => '選択記録を一括払い出し',
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
        'distribute_hint' => '当選チケット番号を入力してください、システムは番号に基づいて賞品レベルを自動識別し払い出しします',
        'drawing_started' => '抽選開始、抽選段階に入りました',
        'activity_ended' => 'アクティビティが終了しました',
    ],

    // 错误信息
    'error' => [
        // 時間競合
        'time_conflict_with_activity' => 'アクティビティ時間が既存のアクティビティ「{name}」と競合しています（{start_time} ~ {end_time}）。同一時間帯には1つのアクティビティのみ進行可能です。',
        'record_not_found' => '記録が存在しません',
        'live_url_required' => 'ライブURLを入力してください',
        'live_url_too_long' => 'ライブURLが長すぎます（最大500文字）',
        'live_already_started' => 'ライブはすでに開始されています、再度開始できません',
        'live_already_ended' => 'ライブはすでに終了しました',
        'live_not_started' => 'ライブがまだ開始されていません、終了できません',
        'cannot_start_drawing' => 'アクティビティ進行中は抽選を開始できません。終了まで待ってください（終了済みステータスのみ抽選開始可能）',
        'cannot_stop_drawing' => '現在のステータスでは抽選を停止できません（抽選中のアクティビティのみ終了可能）',
        'cannot_record_win_in_current_status' => '現在のステータスでは当選を記録できません（進行中または抽選中のステータスのみ記録可能）',
        // 输入驗證
        'invalid_record_id' => 'パラメータエラー：記録IDが無効',
        'invalid_activity_id' => 'パラメータエラー：アクティビティIDが無効',
        'invalid_record_ids' => 'パラメータエラー：記録IDは配列である必要があります',
        'invalid_record_id_value' => 'パラメータエラー：記録IDに不正な値が含まれています',
        'note_too_long' => '払い出し備考は255文字を超えることはできません',
        'no_selection' => 'アクティビティIDを指定するか記録を選択してください',
        'no_pending_records' => '払い出し待ちの記録がありません',
        // 业务邏輯驗證
        'invalid_status' => '記録ステータスが正しくありません',
        'status_changed' => 'ステータスが変更されました',
        'empty_prize' => '賞なし、払い出し不要',
        'invalid_amount' => '賞金額は0より大きくする必要があります',
        'player_not_found' => 'プレイヤーが見つかりません',
        'player_disabled' => 'プレイヤーは無効化されています',
        'activity_not_found' => 'アクティビティが存在しません',
        'activity_invalid_status' => 'アクティビティステータスエラー',
        'activity_not_in_drawing_status' => 'アクティビティステータスエラー、抽選中または終了ステータスでのみ払い出し可能',
        'amount_exceeded' => '払い出し金額が総賞金額を超えています',
        'ticket_not_found_or_used' => 'チケット {ticket_no} が存在しないか使用済みです',
        'ticket_already_won' => 'チケット {ticket_no} は既に当選記録として登録されています',
        'prize_level_not_found_for_ticket' => 'チケット {ticket_no} の賞品レベルが存在しません',
        'invalid_ticket_format' => 'チケット {ticket_no} の形式が正しくありません。数字のみで6文字以内にしてください',
        'bet_progress_not_found' => 'ベット進捗記録が見つかりません',
        // 単一入力プレイヤー照会
        'activity_id_required' => 'アクティビティIDを入力してください',
        'ticket_no_required' => 'チケット番号を入力してください',
        'activity_not_exist' => 'アクティビティが存在しません',
        'ticket_not_exist_or_not_belong' => 'チケットが存在しないか、このアクティビティに属していません',
        'ticket_already_used' => 'このチケットは既に使用されており、当選記録できません',
        'ticket_already_recorded' => 'このチケットは既に記録されています。重複入力はできません',
        'player_not_found_for_ticket' => 'このチケットに対応するプレイヤーが見つかりません',
        // 抽選停止確認
        'stop_drawing_no_records_confirm' => '⚠️ まだ当選チケット番号が記録されていません！\n\n抽選を停止すると、当選チケット番号を記録できなくなります。アクティビティは終了ステータスになります。\n\n抽選を停止してもよろしいですか？',
        'stop_drawing_with_records_confirm' => '抽選停止を確認しますか？\n\n📊 当選統計：\n• 記録済チケット数：{count} 枚\n• 賞金総額：{amount}ポイント\n• 払い出し待ち：{pending} 件\n• 払い出し済：{granted} 件\n\n⚠️ 停止後はチケットを記録できません',
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
        'activity_name_hint' => 'アクティビティ名はプレイヤー画面に表示されます。簡潔に',
        'description_hint' => 'アクティビティ説明はリッチテキスト形式で画像・表などが追加可能',
        'start_time_hint' => 'プレイヤーがベットでくじチケットを獲得開始できる時刻',
        'end_time_hint' => 'アクティビティ終了後、自動的に抽選待ち状態に移行',
        'vip_config_detail' => 'アクティビティ期間中、プレイヤーが指定ベット額に達すると、システムが自動的にチケットを発行',
        'prize_config_detail' => '最大10個の賞品レベルを追加可能。抽選時、当選チケット番号から指定数の賞品を抽出してプレイヤーに配布',
        'prize_name_hint' => 'カスタム賞品名、最大20文字',
        'prize_amount_hint' => '当選プレイヤーが獲得する現金報酬',
        'prize_count_hint' => 'この賞品レベルの総数量',
    ],

    // 详情视图标签
    'view' => [
        'detail_title' => '当選記録詳細',
        'basic_info' => '基本情報',
        'prize_info' => '賞品情報',
        'distribution_info' => '払い出し情報',
        'activity_name' => 'アクティビティ名',
        'ticket_no' => 'チケット番号',
        'player_name' => 'プレイヤー',
        'player_phone' => '電話番号',
        'prize_name' => '賞品名',
        'prize_type' => '賞品タイプ',
        'prize_amount' => '賞金額',
        'status' => 'ステータス',
        'distributed_at' => '払い出し時刻',
        'distributed_by' => '払い出し者',
        'distribution_note' => '払い出し備考',
        'created_at' => '作成日時',
        'updated_at' => '更新日時',
    ],

    // 确认对话框
    'confirm' => [
        'distribute' => 'この賞品をプレイヤーアカウントに払い出ししますか？',
        'distribute_all_pending' => 'このアクティビティの記録済み未払い出しの賞品をすべて払い出ししますか？\nこの操作によりすべての未払い出し記録が一括払い出しされます。慎重に操作してください。',  // ⭐ 新規追加
    ],

    // リスク警告
    'warning' => [
        'batch_distribute_title' => '一括払い出しリスク警告',
        'batch_distribute_point1' => 'この操作により、選択したアクティビティのすべての未払い出し記録が自動的に払い出しされます。手動選択は不要です',
        'batch_distribute_point2' => '払い出し成功後、プレイヤーウォレット残高が即座に増加し、取り消すことはできません',
        'batch_distribute_point3' => '操作前に当選記録と金額が正しいことを確認することをお勧めします',
        'batch_distribute_point4' => '高額賞金の場合は、慎重に確認してから操作してください',
    ],

    // 表单标签
    'form' => [
        'select_activity' => 'アクティビティを選択',
        'select_activity_help' => '抽選完了、払い出し待ちのアクティビティのみ表示',
        'distribution_note' => '払い出し備考',
        'distribution_note_placeholder' => '払い出し備考を入力（任意）',
        'vip_config_section' => 'VIPレベルベット額設定',
        'prize_config_section' => '賞品レベル設定',
        'no_vip_data' => 'VIPレベルデータがありません',
        'no_vip_config' => 'VIPレベル未設定',
        // ⭐ 第二批：フォームラベル（2026-07-01）
        'cover_image' => 'アクティビティカバー画像',
        'vip_level' => 'VIPレベル',
        'bet_amount_required' => '必要ベット額',
        'ticket_count' => 'チケット数',
        'prize_amount_label' => '賞金額',
        'prize_count' => '賞品数',
        'level_label' => 'レベル',
        'vip_config_hint' => '各VIPレベルで指定ベット額に達した後に発行するくじチケット数を設定',
        'prize_config_hint' => '賞品レベルと賞金額を設定（現金のみ）',
        'prize_name_label' => '賞品名',
        'prize_name_placeholder' => '例：特等賞、一等賞',
        'input_ticket_hint' => '数字を入力、例：12 または 000012',
        'add_ticket_no' => 'チケット番号を追加',
        'cover_alt' => 'アクティビティカバー',
        'end_live_confirm_content_full' => 'ライブ配信を終了しますか？終了後、プレイヤーは視聴できなくなります。',
        'confirm_end' => '終了確認',
        'live_stream_name_required' => 'ライブストリーム名を入力してください',
        'at_least_one_ticket' => '少なくとも1つのチケット番号を入力してください',
    ],

    // フィルター ⭐ 追加
    'filter' => [
        'time_range' => '時間範囲',
        'create_time_range' => '作成時間範囲',
        'activity_time_range' => 'アクティビティ時間範囲',
    ],

    // テーブル列見出し ⭐ 第二批（2026-07-01）
    'table' => [
        'level' => 'レベル',
        'vip_level' => 'VIPレベル',
        'bet_amount_required' => '必要ベット額',
        'ticket_count' => 'チケット数',
        'prize_amount' => '賞金額',
        'created_at' => '配布時間',
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
        // ⭐ 第一批：按钮、菜单、状态（2026-07-01）
        'create_from_scratch' => 'ゼロから作成',
        'create_from_history' => '履歴から作成',
        'live_streaming' => '配信中',
        'not_live' => '未配信',
        'preview_live' => 'ライブプレビュー',
        'start_live' => 'ライブ開始',
        'end_live' => 'ライブ終了',
        'view_ticket_list' => 'チケットリストを見る',
        'select_image' => '画像選択',
        'no_history_activities' => '履歴がありません',
        'select_history_activity' => '履歴を選択',
        'end_live_confirm_title' => 'ライブ終了',
        'end_live_confirm_content' => 'ライブ配信を終了しますか？終了後、プレイヤーは視聴できなくなります。',
        'ending_live' => 'ライブを終了しています...',
        'start_live_failed' => 'ライブ開始失敗',
        'end_live_failed' => 'ライブ終了失敗',
        'ticket_list_title' => 'くじチケットリスト',
        'search' => '検索',
        'reset' => 'リセット',
        // ⭐ 第三批：ヘルプテキスト、ヒント（2026-07-01）
        'cover_hint' => '推奨サイズ：750x400px、jpg/png対応、最大2MB',
        'cover_preview_alt' => 'カバープレビュー',
        'no_vip_data_desc' => 'VIPレベルデータがありません',
        'set_live_stream_title' => 'ライブストリーム名設定',
        'live_stream_hint' => '💡 ストリーム名を入力するだけで、システムが自動的にTencent Cloudライブ配信URLを生成します',
        'live_stream_label' => 'ライブストリーム名',
        'live_stream_placeholder' => '例：mojiangjuan',
        'live_stream_name_hint' => '英字、数字、アンダースコアの使用を推奨。OBS配信設定と一致させる必要があります',
        'live_preview_title' => 'ライブプレビュー - {name}',
        'live_url_label' => 'ライブURL：',
        'copy_url' => 'URLをコピー',
        'open_new_window' => '新しいウィンドウで開く',
        'rtmp_protocol_warning' => 'RTMPプロトコル再生の注意',
        'no_live_url' => 'ライブURLが取得できません',
        'generating_live_url' => 'ライブURLを生成中...',
        'live_url_copied' => 'ライブURLをクリップボードにコピーしました',
        'stream_name_format_error' => 'ストリーム名は英字、数字、アンダースコアのみ使用できます',
        'stream_name_too_long' => 'ストリーム名は50文字を超えることはできません',
        'activity_no_live_url' => 'このアクティビティはまだライブストリーム名が設定されていません',
        'generate_live_url_failed' => 'ライブURL生成に失敗しました',
        'edit_live_url' => 'ライブURL編集',
        'cancel_enter_win' => 'キャンセル、先に当選者を入力',
    ],
];
