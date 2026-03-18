<?php

return [
    'title' => '机台类别',
    'fields' => [
        'id' => 'ID',
        'name' => '类别名称',
        'game_id' => '游戏类别',
        'type' => '机台类型',
        'picture_url' => '图片',
        'status' => '状态',
        'sort' => '排序',
        /** TODO 翻译 */
        'keep_minutes' => '每转/压保留几秒',
        'minutes' => '分钟',
        'second' => '秒',
        'lottery_point' => '每转/压转增加彩金池点数',
        'lottery_rate' => '固定门槛派彩系数',
        'lottery_add_status' => '彩金累加状态',
        'lottery_assign_status' => '彩金分配状态',
        'turn_used_point' => '每转消耗游戏点数',
    ],
    'opening_gift' => [
        'opening_gift' => '开分赠点',
        'give_rule_num' => '每日次数',
        'open_num' => '开分点数',
        'give_num' => '赠送分数',
        'condition' => '满足完成条件',
    ],
    'delete_has_machine_error' => '改分类下已添加机台,请先删除机台',
    'lottery_setting' => '彩金配置',
    'lottery_point_help' => '游戏的过程中，斯洛的每一次压分，或者钢珠的每一转，累积一部分金额到对应的彩金池',
    'lottery_rate_help' => '派彩时将乘以派彩系数为实际活动金额',
    'lottery_add_status_help' => '该类型是否参与彩金池的累计',
    'lottery_assign_status_help' => '该类型是否参与彩金',
];
