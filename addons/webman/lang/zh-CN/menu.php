<?php

use addons\webman\model\AdminDepartment;

return [
    'add' => '添加菜单',
    'title' => '系统菜单管理',
    'fields' => [
        'top' => '顶级菜单',
        'pid' => '上级菜单',
        'name' => '菜单名称',
        'url' => '菜单链接',
        'icon' => '菜单图标',
        'sort' => '排序',
        'status' => '状态',
        'open' => '菜单展开',
        'super_status' => '超级管理员状态',
        'type' => '菜单类型',
    ],
    'options' => [
        'admin_visible' => [
            [1 => '显示'],
            [0 => '隐藏']
        ]
    ],
    'type' => [
        AdminDepartment::TYPE_DEPARTMENT => '总站菜单',
        AdminDepartment::TYPE_CHANNEL => '渠道菜单',
        AdminDepartment::TYPE_AGENT => '代理菜单',
        AdminDepartment::TYPE_STORE => '店家菜单',
    ],
    'titles' => [
        'home' => '首页',
        'system' => '系统',
        'system_manage' => '系统管理',
        'config_manage' => '配置管理',
        'attachment_manage' => '附件管理',
        'permissions_manage' => '权限管理',
        'admin' => '用户管理',
        'role_manage' => '角色管理',
        'menu_manage' => '菜单管理',
        'plug_manage' => '插件管理',
        'department_manage' => '部门管理',
        'post_manage' => '岗位管理',
         /** 总后台 */
        'admin_manage' => '总后台',
        'data_center' => '数据中心',
        //用户管理
        'user_manage' => '玩家管理',
        'user_manage_list' => '设备列表',
        'accounting_change_records' => '账变记录',
        //游戏管理
        'game_manager' => '游戏管理',
        'game_category' => '游戏类别',
        'machine_category' => '机台类别',
        'machine_list' => '机台列表',
        'machine_information' => '机台资讯',
        //财务数据
        'financial_data' => '财务数据',
        'transfer_point_record' => '转点记录',
        'recharge_record' => '开分记录',
        'withdrawal_records' => '洗分记录',
        //报表中心
        'report_center' => '报表中心',
        'machine_report' => '机台报表',
        'up_and_down_report' => '上下分报表',
        'store_profit_report' => '店家分润报表',
        //客户端管理
        'client_manager' => '客户端管理',
        'rotation_chart_manager' => '轮播图管理',
        'announcement_manager' => '公告管理',
        'system_settings' => '系统设置',
        //渠道管理
        'channel_manager' => '渠道管理',
        'channel_list' => '渠道列表',
        'currency_manager' => '货币管理',
        /** 渠道后台 */
        'channel_manage' => '渠道后台',
        'channel_data_center' => '数据中心',
        //币商管理
        'channel_coin_merchant_manage' => '币商管理',
        'channel_coin_merchant_list' => '币商列表',
        'channel_coin_merchant_recharge_records' => '币商开分记录',
        'channel_coin_merchant_transaction_records' => '币商交易记录',
        //玩家管理
        'channel_player_manage' => '玩家管理',
        'channel_player_list' => '设备列表',
        'channel_player_accounting_change_records' => '账变记录',
        //机台管理
        'channel_machine_manage' => '机台管理',
        'channel_machine_information' => '机台资讯',
        'channel_machine_report' => '机台报表',
        'channel_up_and_down_report' => '上下分报表',
        'machine_strategy_list' => '机台攻略',
        'machine_producer' => '厂家列表',
        //前端配置
        'channel_client_manager' => '客户端管理',
        'channel_rotation_chart_manager' => '轮播图管理',
        'channel_marquee_manager' => '系统配置',
        'channel_announcement_manager' => '公告管理',
        //财务管理
        'channel_financial_manager' => '财务管理',
        'channel_recharge_review' => '开分审核',
        'channel_withdrawal_review' => '洗分审核',
        'channel_withdrawal_and_payment' => '洗分打款',
        'channel_recharge_record' => '开分记录',
        'channel_talk_recharge_records' => 'QTalk开分记录',
        'channel_withdrawal_records' => '洗分记录',
        'channel_talk_withdrawal_records' => 'QTalk洗分记录',
        'channel_recharge_channel_configuration' => '开分渠道配置',
        'channel_financial_operation_records' => '财务操作记录',
        //权限管理
        'channel_auth_manager' => '权限管理',
        'channel_admin_user_manager' => '用户管理',
        'channel_post_manager' => '岗位管理',
        /** TODO start 翻译 */
        //活动管理
        'activity' => '活动管理',
        'activity_index' => '活动列表',
        'player_activity_record' => '活动参与记录',
        'player_activity_record_examine' => '奖励领取审核',
        'player_activity_record_receive' => '活动领取记录',
        //推广管理
        'promotion_management' => '推广管理',
        'promoter_list' => '推广员列表',
        'profit_record' => '分润报表',
        'profit_settlement_record' => '分润结算记录',
        //彩金管理
        'lottery_management' => '彩金管理',
        'lottery_list' => '彩金列表',
        'lottery_audit_list' => '彩金审核',
        'lottery_records' => '彩金领取记录',
        //币商管理
        'coin_management' => '币商管理',
        'coin_list' => '币商列表',
        'coin_recharge_record' => '币商开分记录',
        //日志中心
        'log_center' => '日志中心',
        'machine_keeping_log' => '机台保留日志',
        'player_edit_log' => '玩家资料修改日志',
        'machine_operation_log' => '机台操作日志',
        'machine_edit_log' => '机台异动日志',
        'lottery_add_log' => '彩金奖池累积日志',
        'player_money_edit_log' => '钱包操作日志',
        /** TODO end 翻译 */
        'computer_game' => '电子游戏',
        'game_platforms' => '游戏平台列表',
        'game_transfer_record' => '转账记录',
        'play_game_record' => '游戏记录',
        'game_platform_profit' => '游戏平台分润',
        //店家配置
        'store_setting_manage' => '店家系统配置',
        'store_open_score_setting' => '店家开分配置',
        'store_shift_handover_record' => '交班记录',
        //代理管理
        'agent_management' => '代理管理',
        //店家管理
        'store_management' => '店家管理',
        //代理彩金管理
        'agent_lottery_management' => '彩金管理',
        //店家彩金管理
        'store_lottery_management' => '彩金管理',
        //开分满赠管理（管理后台）
        'deposit_bonus_manage' => '开分满赠管理',
        'deposit_bonus_activity' => '活动管理',
        'deposit_bonus_qrcode' => '订单管理',
        'deposit_bonus_statistics' => '统计报表',
        'deposit_bonus_bet_detail' => '押码明细',
        //渠道后台开分满赠管理
        'channel_deposit_bonus_manage' => '开分满赠管理',
        'channel_deposit_bonus_activity' => '活动管理',
        'channel_deposit_bonus_order' => '订单管理',
        'channel_deposit_bonus_statistics' => '统计报表',
        'channel_deposit_bonus_bet_detail' => '押码明细',
        //代理/店家上下分报表
        'agent_game_log' => '上下分报表',
        'agent_game_log_list' => '报表列表',
        'store_game_log' => '上下分报表',
        'store_game_log_list' => '报表列表',
        //代理后台财务管理
        'agent_financial_management' => '财务管理',
        'agent_recharge_record' => '开分记录',
        'agent_withdraw_record' => '洗分记录',
        //代理后台电子游戏管理
        'agent_game_management' => '电子游戏管理',
        'agent_game_record' => '游戏记录',
        //代理后台开分满赠管理
        'agent_deposit_bonus_manage' => '开分满赠管理',
        'agent_deposit_bonus_activity' => '活动管理',
        'agent_deposit_bonus_qrcode' => '订单管理',
        'agent_deposit_bonus_order' => '订单管理',
        'agent_deposit_bonus_statistics' => '统计报表',
        'agent_deposit_bonus_bet_detail' => '押码明细',
        'agent_deposit_bonus_task' => '打码量任务',
        //代理后台分润管理
        'agent_profit' => '分润管理',
        'agent_profit_statistics' => '分润统计',
        'agent_profit_records' => '分润记录',
        'agent_settlement_records' => '结算记录',
        //店机后台设备管理
        'store_player' => '设备管理',
        'store_player_list' => '设备列表',
        //店机后台开分满赠管理
        'store_deposit_bonus_manage' => '开分满赠管理',
        'store_deposit_bonus_activity' => '活动管理',
        'store_deposit_bonus_order' => '订单管理',
        'store_deposit_bonus_statistics' => '统计报表',
        'store_deposit_bonus_bet_detail' => '押码明细',
        'store_deposit_bonus_task' => '打码量任务',
        //店机后台财务管理
        'store_financial_manage' => '开洗分中心',
        'store_recharge_record' => '开分记录',
        'store_withdraw_record' => '洗分记录',
        //店机后台电子游戏管理
        'store_game_manage' => '电子游戏管理',
        'store_game_record' => '游戏记录',
        'store_deposit_bonus_qrcode' => '订单管理',
        //自动交班管理
        'auto_shift_management' => '自动交班',
        'auto_shift_config' => '交班配置',
        'auto_shift_logs' => '执行日志',
        //限红组管理（总后台）
        'limit_group' => '限红管理',
        'limit_group_list' => '限红组管理',
        'limit_group_config' => '平台配置',
        //渠道限红管理
        'channel_limit_group' => '限红管理',
        'channel_admin_limit_group' => '店家限红分配',
        //渠道后台店家分润报表
        'channel_store_profit_report' => '店家分润报表',
    ]
];
