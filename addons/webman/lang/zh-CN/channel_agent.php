<?php

return [
    // 标题
    'title' => '店家/设备管理',
    'store_list' => '店家列表',
    'store_management' => '店家管理',
    'device_list' => '设备列表',
    'delivery_record' => '账变记录',
    'shift_handover_record' => '交班报表',

    // 字段
    'fields' => [
        'store_name' => '店家名称',
        'login_account' => '登录账号',
        'contact_phone' => '联系电话',
        'department_name' => '部门名称',
        'agent_commission' => '代理抽成',
        'channel_commission' => '渠道抽成',
        'status' => '状态',
        'created_at' => '创建时间',
        'recharge_amount' => '累计开分',
        'withdraw_amount' => '累计洗分',
        'machine_put_point' => '投钞',
        'lottery_amount' => '彩金',
        'subtotal' => '小计',
        'game_platform' => '游戏平台',
        'game_name' => '游戏名称',
        'game_category' => '游戏分类',
        'is_hot' => '热门',
        'is_new' => '新游戏',
        'sort' => '排序',
    ],

    // 旧的字段键（保留兼容性）
    'store_name' => '店家名称',
    'bind_player' => '绑定玩家',
    'account' => '账号',
    'recommend_id' => '所属店家',
    'select_store' => '所属店家',
    'last_settlement_time' => '上次结算时间',

    // 当期数据
    'current_data' => '当期数据',
    'present_in' => '转入(开分)',
    'present_out' => '转出(洗分)',
    'machine_put' => '投钞(开分)',
    'total_revenue' => '总计(营业额)',
    'store_profit_rate' => '店家分润',
    'store_profit_amount' => '店家拆账',
    'company_profit_rate' => '公司分润',
    'company_profit_amount' => '公司拆账',

    // 累计数据
    'total_data' => '累计数据',

    // 开分相关
    'open_score' => '开分',
    'recharge_amount' => '开分金额',
    'quick_amount' => '快捷金额',
    'custom_amount' => '自定义金额',
    'preset_amount' => '预设金额',
    'default_amount' => '默认金额',
    'device_balance' => '设备余额',
    'exchange_rate' => '汇率',
    'game_points' => '游戏点数',
    'reference' => '参考',
    'points' => '点',

    // 提示信息
    'tip_device_balance' => '提示: 设备余额 {balance} {currency} = {points} 游戏点数',
    'tip_exchange_rate' => '1 {currency} = {ratio} 游戏点数，请输入货币金额，系统会自动转换成游戏点数',
    'tip_select_preset' => '选择预设金额或自定义金额 (货币 → 游戏点数，1{currency} = {ratio}点)',
    'tip_reference' => '设备余额: {currency}{balance}，汇率：1{currency} = {ratio}游戏点数。{reference}',

    // 错误消息
    'error_amount_required' => '请输入金额',
    'error_amount_invalid' => '请输入有效的金额',
    'error_store_not_found' => '店家账号不存在',
    'error_currency_not_found' => '未找到货币信息',
    'error_ratio_invalid' => '汇率必须大于0',
    'error_device_not_found' => '设备不存在',
    'error_insufficient_balance' => '设备余额不足',
    'error_player_not_found' => '玩家不存在',
    'error_offline_channel_only' => '该功能仅适用于线下渠道',
    'error_no_game_platform' => '该渠道未开启任何电子游戏平台',

    // 状态选项
    'status_options' => [
        'normal' => '正常',
        'disabled' => '已禁用',
    ],

    // 标签
    'tag' => [
        'not_set' => '未设置',
        'disabled' => '已禁用',
        'normal' => '正常',
        'hot' => '热门',
        'new' => '新',
        'unknown_platform' => '未知平台',
    ],

    // 占位符
    'placeholder' => [
        'status' => '状态',
        'login_account' => '登录账号',
        'store_name' => '店家名称',
        'contact_phone' => '联系电话',
        'start_time' => '开始时间',
        'end_time' => '结束时间',
        'game_platform' => '游戏平台',
        'is_hot' => '是否热门',
        'is_new' => '是否新游戏',
    ],

    // 游戏选项
    'game_options' => [
        'hot_games' => '热门游戏',
        'normal_games' => '普通游戏',
        'new_games' => '新游戏',
        'old_games' => '旧游戏',
    ],

    // 按钮
    'button' => [
        'save_selected_games' => '保存选择的游戏',
    ],

    // 确认
    'confirm' => [
        'save_games' => '确认保存？',
    ],

    // 成功消息
    'success_open_score' => '开分成功',
    'success_save' => '保存成功',

    // 错误消息 - 追加
    'error_save_failed' => '保存失败',
    'error_operation_failed' => '操作失败',

    // 开分备注
    'remark_store_open_score' => '店家后台开分',

    // JavaScript 文本
    'js' => [
        'conversion_preview' => '转换预览：',
        'please_enter_amount' => '请输入金额',
        'points_unit' => '点',
        'exchange_rate_label' => '汇率：',
    ],

    // 游戏相关
    'game' => [
        'game_id' => '游戏 ID',
        'select_all_platform' => '【全选该平台所有游戏】',
        'game_list_title' => '{platform} - 游戏列表',
        'tip_select_games' => '提示: 选择该玩家可以使用的电子游戏。未选择的游戏将不会在客户端展示。',
    ],

    // 游戏权限管理
    'game_permission' => [
        'title' => '玩家游戏权限管理 - {name}',
    ],

    // 其他
    'remark' => '备注',
    'select_placeholder' => '请选择',
    'total' => '合计',
    'player_type_store' => '店家',
    'player_type_device' => '设备',
    'player' => '玩家',
    'admin' => '管理员',
    'type' => '类型',
    'all' => '全部',
    'total_profit_amount' => '总营收',
    'start_time' => '开始时间',
    'end_time' => '结束时间',
    'created_at' => '创建时间',
];