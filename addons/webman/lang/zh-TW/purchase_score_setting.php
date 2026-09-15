<?php

return [
    'title' => '儲值機購分配置',
    'score_settings' => '購分選項配置',
    'player_exists' => '該店家已存在購分配置',
    'at_least_one_score' => '至少需要配置一個購分選項',
    'reset_to_default' => '恢復默認配置',
    'reset_confirm_message' => '確定要將購分配置恢復為默認值嗎？',
    'reset_success' => '已成功恢復為默認配置',
    'reset_failed' => '恢復默認配置失敗',
    'not_set' => '未設置',
    'fields' => [
        'id' => 'ID',
        'store_admin_id' => '店機',
        'store_admin_name' => '店機名稱',
        'scores' => '當前購分選項',
        'default_scores' => '默認購分數',
        'system_default' => '系統預設購分',
        'score_1' => '購分選項1',
        'score_2' => '購分選項2',
        'score_3' => '購分選項3',
        'score_4' => '購分選項4',
        'score_5' => '購分選項5',
        'score_6' => '購分選項6',
        'created_at' => '創建時間',
        'updated_at' => '更新時間',
    ],
    'help' => [
        'store_admin_id' => '選擇需要配置購分的店機',
        'score' => '請輸入購分金額，0表示不啟用該選項',
        'default_scores' => '設置默認購分數值，用於恢復默認配置時使用',
    ],
    'error' => [
        'must_be_positive_integer' => ':field 必須是正整數',
    ],
];
