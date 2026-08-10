<?php

return [
    'title' => '线上玩家彩金',

    // Tab 标题
    'tab' => [
        'game_online_players' => '电子游戏在线玩家',
        'machine_online_players' => '实体机台在线玩家',
    ],

    // Card 标题
    'card' => [
        'game_title' => '电子游戏在线玩家 ({count}人在线)',
        'machine_title' => '实体机台在线玩家 ({count}人在线)',
    ],

    // Tag 文本
    'tag' => [
        'realtime_update' => '实时更新',
        'last_update' => '最后更新: {time}',
        'playing' => '游戏中',
        'seconds_ago' => '{seconds}秒前',
    ],

    // 按钮文本
    'button' => [
        'refresh' => '刷新',
        'grant_lottery' => '发放彩金',
    ],

    // 空状态描述
    'empty' => [
        'no_online_players' => '暂无在线玩家（最近1分钟内无押注记录）',
    ],

    // 表格列标题
    'columns' => [
        'id' => 'ID',
        'player_info' => '玩家信息',
        'uuid' => 'UUID',
        'current_machine' => '当前机台',
        'current_platform' => '当前平台',
        'last_bet_time' => '最后押注时间',
        'total_pressure' => '累计押注',
        'total_bet' => '累计押注',
        'status' => '状态',
        'action' => '操作',
    ],

    // 其他显示文本
    'display' => [
        'code_prefix' => '编号: {code}',
    ],

    // Modal 标题和表单
    'modal' => [
        'grant_lottery' => '发放彩金',
        'player_info' => '玩家信息',
        'select_lottery' => '选择彩金',
        'grant_amount' => '发放金额',
        'remark' => '备注',
    ],

    // Placeholder
    'placeholder' => [
        'select_lottery' => '请选择彩金类型',
        'input_amount' => '请输入发放金额',
        'input_remark' => '请输入发放原因或备注信息',
    ],

    // 验证消息
    'validation_msg' => [
        'select_lottery' => '请选择彩金类型',
        'input_valid_amount' => '请输入有效的发放金额',
        'grant_success' => '彩金发放成功',
        'grant_failed' => '彩金发放失败',
    ],

    // 默认值
    'default' => [
        'not_updated' => '未更新',
    ],

    // 彩金池
    'lottery_pool' => '彩池',

    'validation' => [
        'parameter_error' => '参数错误',
        'player_not_exist' => '玩家不存在',
        'lottery_not_exist' => '彩金不存在',
        'insufficient_lottery_balance' => '彩金池余额不足，当前余额：{balance}',
    ],

    'notice' => [
        'lottery_payout_title' => '彩金派彩',
        'lottery_payout_content' => '恭喜您获得{lottery_name}的彩金奖励，金额：{amount}',
    ],

    'log' => [
        'send_socket_message_failed' => '发送彩金Socket消息失败：{message}',
        'manual_payout_success' => '手动发放彩金成功',
        'manual_payout_failed' => '手动发放彩金失败：{message}',
    ],

    'message' => [
        'payout_success' => '彩金发放成功',
        'payout_failed' => '彩金发放失败：{message}',
    ],
];
