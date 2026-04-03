<?php

return [
    // 通用错误消息
    'data_not_found' => '数据不存在',
    'player_not_exist' => '玩家不存在',
    'player_already_exists' => '玩家已存在',
    'recommended_player_not_exist' => '推荐玩家不存在',
    'game_not_exist' => '游戏不存在',
    'please_select_games' => '请选择要授权的游戏',
    'games_not_found' => '未找到选择的游戏',
    'offline_channel_only' => '此功能仅限线下渠道使用',
    'offline_channel_feature_only' => '该功能仅适用于线下渠道',
    'channel_no_game_platform' => '该渠道未开启任何电子游戏平台',
    'games_not_in_channel_scope' => '选择的游戏不在渠道允许的范围内',
    'game_not_in_channel_scope' => '该游戏不在渠道允许的范围内',
    'game_platform_not_in_channel_scope' => '选择的游戏平台不在渠道允许的范围内',
    'invalid_operation' => '无效的操作',
    'operation_failed' => '操作失败',
    'save_failed' => '保存失败',
    'player_id_required' => '玩家ID不能为空',
    'invalid_parameter' => '参数无效',
    'load_failed' => '加载失败',
    'invalid_game_points' => '转换后的游戏点数无效',
    'system_error' => '系统错误',
    'machine_in_test_mode' => '该机台作为新版工控测试机台使用中',
    'video_host_request_failed' => '请求视讯主机失败',
    'get_stream_info_failed' => '获取流信息失败',

    // 通用成功消息
    'settlement_success' => '结算成功',
    'operation_success' => '操作成功',
    'authentication_passed' => '认证通过',
    'batch_generation_failed' => '批量生成失败：{message}',
    'create_agent_failed' => '创建代理失败：{message}',
    'create_store_failed' => '创建店家失败：{message}',

    // 通用标签
    'administrator' => '管理员',
    'player' => '玩家',
    'total' => '合计',

    // 通用确认消息
    'confirm_save' => '确认保存？',

    // 登录相关
    'please_enter_credentials' => '请输入账号和密码',
    'account_not_exist' => '账号不存在',
    'password_incorrect' => '密码错误',
    'login_success' => '登录成功',
    'implement_login_logic' => '请在 CustomLoginController 中实现实际的登录逻辑',

    // 代理/店家相关
    'agent_commission_range_error' => '代理抽成比例必须在 0-100 之间',
    'channel_commission_range_error' => '渠道抽成比例必须在 0-100 之间',
    'please_upload_avatar' => '请上传头像',
    'password_mismatch' => '两次密码输入不一致',
    'username_exists' => '登录账号 {username} 已存在',
    'agent_create_success' => '代理 {name} 创建成功！登录账号：{username}',
    'parent_agent_not_exist' => '上级代理不存在',
    'please_select_settlement_targets' => '请选择需要结算的代理/店家',
    'settlement_end_time_error' => '结算结束时间不能超过当前时间',
    'store_ratio_less_than_agent' => '店家上缴比例设置不能小于代理 ({name}) 的上缴比例,{ratio}%',
    'agent_ratio_greater_than_store' => '代理上缴比例设置不能大于店家 ({name}) 的上缴比例,{ratio}%',

    // 游戏权限相关
    'game_permission_set_success' => '成功设置了 {count} 个游戏权限',
    'electronic_game_set_success' => '成功设置了 {count} 个电子游戏',

    // 交班相关
    'shift_handover_failed_no_department' => '交班失败：管理员未关联部门',
    'shift_handover_failed_no_currency' => '交班失败：系统货币配置缺失',

    // 彩池相关
    'pool_ratio_must_greater_than_zero' => '入池比值必须大于0',
    'pool_ratio_cannot_exceed_100' => '入池比值不能超过100%',
    'win_probability_must_greater_than_zero' => '中奖概率必须大于0',
    'win_probability_cannot_exceed_1' => '中奖概率不能超过1（100%）',
    'max_pool_amount_must_greater_than_zero' => '最大彩池金额必须大于0',
    'minimum_amount_must_greater_than_zero' => '启用保底金额后，保底金额必须大于0',
    'minimum_amount_cannot_exceed_max' => '保底金额不能大于最大彩池金额',
    'distribution_ratio_range_error' => '派发比例必须在0-100之间',

    // 机台相关
    'please_select_reset_hosts' => '请选择重设的视讯主机',
    'please_fill_zhcn_name' => '请填写中文简体名称',
    'please_upload_zhcn_image' => '请上传中文简体图',

    // 角色相关
    'builtin_role_cannot_modify_name' => '系统内置角色不允许修改名称',
    'builtin_role_cannot_modify_type' => '系统内置角色不允许修改类型',
    'role_not_exist' => '角色不存在',
    'builtin_role_cannot_delete' => '该角色为系统内置角色，不允许删除',

    // 批量生成相关
    'batch_generate_success' => '成功生成 {count} 个玩家账号',
    'batch_generate_partial_success' => '成功生成 {success} 个玩家账号，失败 {failed} 个：{accounts}',
    'account_exists' => '已存在',

    // 帮助文本
    'help' => [
        'account_format' => '账号格式：前缀+编号，例如：P0001',
        'number_auto_padding' => '编号将自动补齐为4位数字，例如：1 → 0001',
        'nickname_format' => '昵称格式：前缀+编号，例如：玩家0001',
        'number_auto_padding_simple' => '编号将自动补齐为4位数字',
        'all_players_use_this_avatar' => '所有生成的玩家将使用此头像',
        'avatar_format_recommendation' => '支持jpg、png格式，建议尺寸200x200，所有生成的玩家将使用此头像',
        'all_accounts_use_this_password' => '所有生成的账号将使用此密码',
        'avatar_format' => '支持jpg、png格式，建议尺寸200x200',
        'agent_login_password' => '代理后台登录密码，至少6位',
        'store_login_password' => '店家后台登录密码，至少6位',
        'agent_commission_help' => '代理从店家收益中抽取的比例，范围 0-100',
        'channel_commission_help' => '渠道从店家收益中抽取的比例，范围 0-100',
    ],

    // 提示文本
    'tips' => [
        'offline_channel_only_notice' => '><font size=3 color="#ff4d4f">此功能仅限线下渠道使用</font>',
        'batch_generate_bind_notice' => '><font size=2 color="#1890ff">批量生成的账号将自动绑定到指定的店家</font>',
    ],

    // 其他
    'divider' => [
        'commission_settings' => '抽成设置',
    ],

    // 默认文本
    'default' => [
        'admin' => '管理员',
        'no_agent' => '无代理',
        'not_filled' => '未填写',
        'welcome_agent_system' => '欢迎使用代理后台系统！',
        'welcome_store_system' => '欢迎使用店家后台系统！',
    ],

    // 日期筛选
    'date_filter' => [
        'all' => '全部',
        'today' => '今日',
        'yesterday' => '昨天',
        'this_week' => '本周',
        'last_week' => '上周',
        'this_month' => '本月',
        'last_month' => '上月',
    ],

    // 自动交班
    'auto_shift' => [
        'enabled' => '已开启自动交班',
        'manual_shift_success' => '店家手动交班成功',
        'manual_shift_failed' => '手动交班失败',
    ],

    // 班次
    'shift' => [
        'morning' => '早班',
        'morning_desc' => '早班自动交班（08:00-16:00）',
        'afternoon' => '中班',
        'afternoon_desc' => '中班自动交班（16:00-24:00）',
        'night' => '晚班',
        'night_desc' => '晚班自动交班（00:00-08:00）',
    ],
];
