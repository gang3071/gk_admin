<?php

use addons\webman\model\PlayerPresentRecord;

return [
    'title' => '转点记录',
    'user_info' => '发起者信息',
    'player_info' => '交易对象',
    'fields' => [
        'id' => 'ID',
        'user_id' => '发起者',
        'type' => '交易行为',
        'player_id' => '交易对象',
        'department_id' => '渠道',
        'tradeno' => '单号',
        'amount' => '交易金额',
        'user_origin_amount' => '转出前点数',
        'user_after_amount' => '转出后点数',
        'player_origin_amount' => '转入前点数',
        'player_after_amount' => '转入后点数',
        'created_at' => '交易时间',
    ],
    'type' => [
        PlayerPresentRecord::TYPE_IN => '转入',
        PlayerPresentRecord::TYPE_OUT => '转出'
    ],
    'search' => [
        'user_placeholder' => '请选择发起人',
        'player_placeholder' => '请选择交易对象',
        'placeholder' => '请输入关键词',
        'user_uuid' => '发起人UID',
        'user_name' => '发起人名称',
        'user_phone' => '发起人手机号',
        'player_uuid' => '交易对象UID',
        'player_name' => '交易对象名称',
        'player_phone' => '交易对象手机号',
    ],
    'total_data' => [
        'total_icon_amount' => '币商转出总游戏点',
        'total_player_amount' => '玩家转出总游戏点',
    ],
    'user_type' => '发起者类型',
    'player_type' => '交易对象类型',
];
