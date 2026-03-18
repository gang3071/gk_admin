<?php

return [
    'title' => '遊戲平臺清單',
    'fields' => [
        'id' => 'ID',
        'code' => '遊戲平臺編碼',
        'name' => '遊戲平臺名稱',
        'cate_id' => '遊戲類型',
        'display_mode' => '遊戲展示模式',
        'logo' => 'Logo',
        'status' => '狀態',
        'ratio' => '電子遊戲結算比值',
        'has_lobby' => '是否進入大廳',
        'picture' => '用戶端大圖',
    ],
    'display_mode' => [
        1 => '橫版',
        2 => '豎版',
        3 => '全部支持',
    ],
    'display_mode_help' => '選擇遊戲展示方向（橫屏/豎屏/全部）',
    'game_platform' => '遊戲供應商資訊',
    'action_error' => '操作失敗',
    'action_success' => '操作成功',
    'enter_game' => '進入遊戲大廳',
    'enter_game_confirm' => '您確定要進入該遊戲廠商大廳嗎？',
    'ratio_help' => '電子遊戲平臺結算比值，剩餘作為推廣員分潤基數',
    'ratio_placeholder' => '請填寫電子遊戲平臺結算比值',
    'view_game' => '查看遊戲',
    'player_not_fount' => '未設定後管玩家帳號',
    'disable' => '遊戲平臺已被禁用',
];
