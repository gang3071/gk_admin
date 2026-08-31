<?php

return [
    'title' => '储值机购分配置',
    'score_settings' => '购分选项配置',
    'player_exists' => '该店家已存在购分配置',
    'at_least_one_score' => '至少需要配置一个购分选项',
    'reset_to_default' => '恢复默认配置',
    'reset_confirm_message' => '确定要将购分配置恢复为默认值吗？',
    'reset_success' => '已成功恢复为默认配置',
    'reset_failed' => '恢复默认配置失败',
    'not_set' => '未设置',
    'fields' => [
        'id' => 'ID',
        'store_admin_id' => '店机',
        'store_admin_name' => '店机名称',
        'scores' => '当前购分选项',
        'default_scores' => '默认购分数',
        'system_default' => '系统预设购分',
        'score_1' => '购分选项1',
        'score_2' => '购分选项2',
        'score_3' => '购分选项3',
        'score_4' => '购分选项4',
        'score_5' => '购分选项5',
        'score_6' => '购分选项6',
        'created_at' => '创建时间',
        'updated_at' => '更新时间',
    ],
    'help' => [
        'store_admin_id' => '选择需要配置购分的店机',
        'score' => '请输入购分金额，0表示不启用该选项',
        'default_scores' => '设置默认购分数值，用于恢复默认配置时使用',
    ],
    'error' => [
        'must_be_positive_integer' => ':field 必须是正整数',
    ],
];
