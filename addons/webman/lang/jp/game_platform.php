<?php

return [
    'title' => 'ゲームプラットフォームリスト',
    'fields' => [
        'id' => 'ID',
        'code' => 'ゲームプラットフォームコード',
        'name' => 'ゲームプラットフォーム名',
        'cate_id' => 'ゲームタイプ',
        'display_mode' => 'ゲーム表示モード',
        'logo' => 'Logo',
        'status' => '状態',
        'ratio' => 'ゲーム決済比',
        'has_lobby' => 'ロビーに入るかどうか',
        'picture' => 'クライアントのグラフ',
    ],
    'display_mode' => [
        1 => '横画面',
        2 => '縦画面',
        3 => 'すべて対応',
    ],
    'display_mode_help' => 'ゲーム表示方向を選択（横画面/縦画面/すべて）',
    'game_platform' => 'ゲームベンダー情報',
    'action_error' => '操作に失敗しました',
    'action_success' => '操作成功',
    'enter_game' => 'ゲームホールに入る',
    'enter_game_confirm' => '本当にこのゲームメーカーのホールに入りますか？',
    'ratio_help' => '電子ゲームプラットフォームの決済比、残りは普及員としての分潤基数',
    'ratio_placeholder' => '電子ゲームプラットフォームの決済比を記入してください',
    'view_game' => 'ゲームの表示',
    'player_not_fount' => '後管理プレイヤーアカウントが設定されていません',
    'disable' => 'ゲームプラットフォームが無効になっています',
];
