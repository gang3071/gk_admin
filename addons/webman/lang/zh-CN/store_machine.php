<?php

return [
    'title' => '店家管理',
    'offline_only' => '此功能仅限线下渠道使用',
    'create_success' => '店家 {name} 创建成功！登录账号：{username}，{agent_label}：{agent_name}',
    'create_failed' => '创建店家失败：{error}',
    'welcome_message' => '欢迎使用店家后台系统！',

    // 列名
    'fields' => [
        'id' => 'ID',
        'name' => '店家名称',
        'username' => '登录账号',
        'phone' => '联系电话',
        'department_name' => '部门名称',
        'agent_commission' => '代理抽成',
        'channel_commission' => '渠道抽成',
        'wash_point_config' => '洗分配置',
        'experience_bet_check_enabled' => '体验券打码判定',
        'status' => '状态',
        'created_at' => '创建时间',
        'parent_agent' => '上级代理',
        'password' => '登录密码',
        'password_confirmation' => '确认密码',
        'avatar' => '上传头像',
    ],

    // 状态
    'status' => [
        'normal' => '正常',
        'disabled' => '已禁用',
        'enabled' => '启用',
        'not_set' => '未设置',
    ],

    // 表单
    'form' => [
        'create_title' => '创建店家',
        'create_hint' => '创建店家后，该店家可登录店家后台',
        'section_account' => '账号信息',
        'section_parent_agent' => '上级代理',
        'section_avatar' => '头像配置',
        'section_password' => '密码配置',
        'select_parent_agent' => '选择上级代理',
    ],

    // 占位符
    'placeholder' => [
        'status' => '状态',
        'username' => '登录账号',
        'name' => '店家名称',
        'phone' => '联系电话',
        'start_time' => '开始时间',
        'end_time' => '结束时间',
    ],

    // 筛选器
    'filter' => [
        'select_store' => '选择店家',
    ],

    // 其他
    'all' => '全部',

    // 帮助文字
    'help' => [
        'phone' => '选填，用于联系',
        'username' => '必填，用于登录店家后台',
        'name' => '店家的显示名称',
        'parent_agent' => '选择该店家的上级代理',
        'avatar' => '支持jpg、png格式，建议尺寸200x200',
        'password' => '店家后台登录密码',
    ],

    // 验证规则
    'validation' => [
        'password_min' => '密码至少6位',
    ],

    // 错误消息
    'error' => [
        'offline_only' => '此功能仅限线下渠道使用',
        'avatar_required' => '请上传头像',
        'password_mismatch' => '两次密码输入不一致',
        'parent_agent_not_found' => '上级代理不存在',
        'username_exists' => '登录账号 {username} 已存在',
    ],

    // 自动交班配置
    'auto_shift' => [
        'morning_title' => '早班',
        'afternoon_title' => '中班',
        'night_title' => '晚班',
        'morning_desc' => '早班自动交班（08:00-16:00）',
        'afternoon_desc' => '中班自动交班（16:00-24:00）',
        'night_desc' => '晚班自动交班（00:00-08:00）',
    ],

    // 操作菜单
    'actions' => [
        'limit_group' => '限红组配置',
        'auto_shift_config' => '自动交班配置',
        'system_setting' => '系统配置',
        'open_score_setting' => '开分配置',
        'wash_point_setting' => '洗分配置',
        'activity_config' => '活动配置',
    ],

    // 活动配置
    'activity_config' => [
        'title' => '活动配置',
        'list_title' => '活动配置列表',
        'create_title' => '新增活动配置',
        'edit_title' => '编辑活动配置',
        'no_config' => '暂无活动配置',

        // 字段
        'fields' => [
            'id' => 'ID',
            'start_time' => '活动开始时间',
            'end_time' => '活动结束时间',
            'status' => '状态',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
        ],

        // 状态
        'status' => [
            '0' => '停用',
            '1' => '启用',
        ],

        // 区段标题
        'section' => [
            'basic' => '基本信息',
            'experience' => '体验券配置',
            'welfare' => '福利券配置',
            'order_prefix' => '订单前缀配置',
        ],

        // 字段标签
        'label' => [
            'start_time' => '开始时间',
            'end_time' => '结束时间',
            'activity_end_time' => '发放截止时间',
            'experience_enabled' => '启用体验券',
            'experience_register_after' => '新用户阈值',
            'experience_daily_limit' => '每日次数',
            'experience_total_limit' => '总次数',
            'experience_score' => '领取分数',
            'experience_expire_hours' => '有效时长(时)',
            'welfare_enabled' => '启用福利券',
            'welfare_daily_limit' => '每日次数',
            'welfare_rules' => '福利券档位规则',
            'welfare_expire_hours' => '有效时长(时)',
            'order_prefix_experience' => '体验券前缀',
            'order_prefix_welfare' => '福利券前缀',
            'order_prefix_recharge' => '开分前缀',
            'order_prefix_withdraw' => '洗分前缀',
        ],

        // 帮助文字
        'help' => [
            'start_time' => '活动开始时间，留空表示立即开始',
            'end_time' => '活动结束时间，留空表示不限制',
            'activity_end_time' => '到达此时间后，福利券和体验券暂停发放',
            'experience_register_after' => '大于等于此时间注册的用户视为新用户',
            'experience_daily_limit' => '每个用户每天可领取的次数',
            'experience_total_limit' => '每个用户总共可领取的次数',
            'experience_score' => '每次领取获得的分数',
            'experience_expire_hours' => '超过此时间后券自动失效',
            'welfare_daily_limit' => '0 表示不限制',
            'welfare_rules' => '配置福利券的打码量门槛，达到门槛即可领取',
            'welfare_expire_hours' => '超过此时间后券自动失效',
            'order_prefix_experience' => '体验券订单号前缀',
            'order_prefix_welfare' => '福利券订单号前缀',
            'order_prefix_recharge' => '开分订单号前缀',
            'order_prefix_withdraw' => '洗分订单号前缀',
        ],

        // 规则相关
        'rules' => [
            'bet_amount' => '打码量门槛',
            'score' => '领取分数',
            'add_rule' => '新增档位',
            'remove_rule' => '移除',
            'day_type' => '计算类型',
            'yesterday' => '昨日打码量',
            'today' => '今日打码量',
        ],

        // 验证
        'validation' => [
            'start_time_required' => '请填写活动开始时间',
            'end_time_after_start' => '结束时间必须晚于开始时间',
        ],

        // 错误消息
        'error' => [
            'already_exists' => '该店铺已存在活动配置，请直接编辑',
        ],

        // 消息
        'message' => [
            'create_success' => '活动配置创建成功',
            'update_success' => '活动配置更新成功',
            'delete_success' => '活动配置删除成功',
            'delete_confirm' => '确定要删除此活动配置吗？',
        ],
    ],
];
