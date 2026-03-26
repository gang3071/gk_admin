<?php

return [
    'title' => '機台類別',
    'fields' => [
        'id' => 'ID',
        'name' => '類別名稱',
        'game_id' => '遊戲類別',
        'type' => '機台類型',
        'picture_url' => '圖片',
        'status' => '狀態',
        'sort' => '排序',
        /** TODO翻譯*/
        'keep_minutes' => '每轉/壓保留幾秒',
        'minutes' => '分鐘',
        'second' => '秒',
        'lottery_point' => '每轉/壓轉新增彩金池點數',
        'lottery_rate' => '固定門檻派彩係數',
        'lottery_add_status' => '彩金累加狀態',
        'lottery_assign_status' => '彩金分配狀態',
        'turn_used_point' => '每轉消耗遊戲點數',
    ],
    'opening_gift' => [
        'opening_gift' => '開分贈點',
        'give_rule_num' => '每日次數',
        'open_num' => '開分點數',
        'give_num' => '贈送分數',
        'condition' => '滿足完成條件',
    ],
    'delete_has_machine_error' => '改分類下已添加機台,請先删除機台',
    'lottery_setting' => '彩金配寘',
    'lottery_point_help' => '遊戲的過程中,斯洛的每一次壓分,或者鋼珠的每一轉,累積一部分金額到對應的彩金池',
    'lottery_rate_help' => '派彩時將乘以派彩係數為實際活動金額',
    'lottery_add_status_help' => '該類型是否參與彩金池的累計',
    'lottery_assign_status_help' => '該類型是否參與彩金',
    'form' => [
        'category_name' => '類別名稱',
        'multilingual_config' => '多語言配寘',
    ],
    'validation' => [
        'please_fill_category_name' => '請填寫類別名稱',
        'please_fill_simplified_chinese_name' => '請填寫中文簡體名稱',
    ],
];
