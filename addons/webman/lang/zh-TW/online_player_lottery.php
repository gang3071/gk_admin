<?php

return [
    'title' => '線上玩家彩金',

    // Tab 标题
    'tab' => [
        'game_online_players' => '電子遊戲在線玩家',
        'machine_online_players' => '實體機台在線玩家',
    ],

    // Card 标题
    'card' => [
        'game_title' => '電子遊戲在線玩家 ({count}人在線)',
        'machine_title' => '實體機台在線玩家 ({count}人在線)',
    ],

    // Tag 文本
    'tag' => [
        'realtime_update' => '實時更新',
        'last_update' => '最後更新: {time}',
        'playing' => '遊戲中',
        'seconds_ago' => '{seconds}秒前',
    ],

    // 按钮文本
    'button' => [
        'refresh' => '刷新',
        'grant_lottery' => '發放彩金',
    ],

    // 空状态描述
    'empty' => [
        'no_online_players' => '暫無在線玩家（最近1分鐘內無押注記錄）',
    ],

    // 表格列标题
    'columns' => [
        'id' => 'ID',
        'player_info' => '玩家信息',
        'uuid' => 'UUID',
        'current_machine' => '當前機台',
        'current_platform' => '當前平台',
        'last_bet_time' => '最後押注時間',
        'total_pressure' => '累計押注',
        'total_bet' => '累計押注',
        'status' => '狀態',
        'action' => '操作',
    ],

    // 其他显示文本
    'display' => [
        'code_prefix' => '編號: {code}',
    ],

    // Modal 标题和表单
    'modal' => [
        'grant_lottery' => '發放彩金',
        'player_info' => '玩家信息',
        'select_lottery' => '選擇彩金',
        'grant_amount' => '發放金額',
        'remark' => '備註',
    ],

    // Placeholder
    'placeholder' => [
        'select_lottery' => '請選擇彩金類型',
        'input_amount' => '請輸入發放金額',
        'input_remark' => '請輸入發放原因或備註信息',
    ],

    // 验证消息
    'validation_msg' => [
        'select_lottery' => '請選擇彩金類型',
        'input_valid_amount' => '請輸入有效的發放金額',
        'grant_success' => '彩金發放成功',
        'grant_failed' => '彩金發放失敗',
    ],

    // 默认值
    'default' => [
        'not_updated' => '未更新',
    ],

    // 彩金池
    'lottery_pool' => '彩池',

    'validation' => [
        'parameter_error' => '參數錯誤',
        'player_not_exist' => '玩家不存在',
        'lottery_not_exist' => '彩金不存在',
        'insufficient_lottery_balance' => '彩金池餘額不足，當前餘額：{balance}',
    ],

    'notice' => [
        'lottery_payout_title' => '彩金派彩',
        'lottery_payout_content' => '恭喜您獲得{lottery_name}的彩金獎勵，金額：{amount}',
    ],

    'log' => [
        'send_socket_message_failed' => '發送彩金Socket消息失敗：{message}',
        'manual_payout_success' => '手動發放彩金成功',
        'manual_payout_failed' => '手動發放彩金失敗：{message}',
    ],

    'message' => [
        'payout_success' => '彩金發放成功',
        'payout_failed' => '彩金發放失敗：{message}',
    ],
];
