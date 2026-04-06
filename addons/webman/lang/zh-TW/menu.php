<?php

use addons\webman\model\AdminDepartment;

return [
    'add' => '添加選單',
    'title' => '系統功能表管理',
    'fields' => [
        'top' => '頂級選單',
        'pid' => '上級選單',
        'name' => '選單名稱',
        'url' => '選單連結',
        'icon' => '功能表圖示',
        'sort' => '排序',
        'status' => '狀態',
        'open' => '選單展開',
        'super_status' => '超級管理員狀態',
        'type' => '選單類型',
    ],
    'options' => [
        'admin_visible' => [
            [1 => '顯示'],
            [0 => '隱藏']
        ]
    ],
    'type' => [
        AdminDepartment::TYPE_DEPARTMENT => '總站選單',
        AdminDepartment::TYPE_CHANNEL => '通路選單',
        AdminDepartment::TYPE_AGENT => '代理選單',
        AdminDepartment::TYPE_STORE => '店機選單',
    ],
    'titles' => [
        'home' => '首頁',
        'system' => '系統',
        'system_manage' => '系統管理',
        'config_manage' => '配置管理',
        'attachment_manage' => '附件管理',
        'permissions_manage' => '許可權管理',
        'admin' => '用戶管理',
        'role_manage' => '角色管理',
        'menu_manage' => '選單管理',
        'plug_manage' => '挿件管理',
        'department_manage' => '部門管理',
        'post_manage' => '崗位管理',
        /**總後臺*/
        'admin_manage' => '總後臺',
        'data_center' => '資料中心',
//用戶管理
        'user_manage' => '玩家管理',
        'user_manage_list' => '玩家清單',
        'accounting_change_records' => '賬變記錄',
//遊戲管理
        'game_manager' => '遊戲管理',
        'game_category' => '遊戲類別',
        'machine_category' => '機台類別',
        'machine_list' => '機台清單',
        'machine_information' => '機台資訊',
//財務資料
        'financial_data' => '財務資料',
        'transfer_point_record' => '轉點記錄',
        'recharge_record' => '開分記錄',
        'withdrawal_records' => '洗分記錄',
//報表中心
        'report_center' => '報表中心',
        'machine_report' => '機台報表',
        'up_and_down_report' => '上下分報表',
        'store_profit_report' => '店家分潤報表',
//用戶端管理
        'client_manager' => '用戶端管理',
        'rotation_chart_manager' => '輪播圖管理',
        'announcement_manager' => '公告管理',
        'system_settings' => '系統設置',
//管道管理
        'channel_manager' => '管道管理',
        'channel_list' => '通路清單',
        'currency_manager' => '貨幣管理',
        /**通路後臺*/
        'channel_manage' => '通路後臺',
        'channel_data_center' => '資料中心',
//幣商管理
        'channel_coin_merchant_manage' => '幣商管理',
        'channel_coin_merchant_list' => '幣商清單',
        'channel_coin_merchant_recharge_records' => '幣商開分記錄',
        'channel_coin_merchant_transaction_records' => '幣商交易記錄',
//玩家管理
        'channel_player_manage' => '玩家管理',
        'channel_player_list' => '玩家清單',
        'channel_player_accounting_change_records' => '賬變記錄',
//機台管理
        'channel_machine_manage' => '機台管理',
        'channel_machine_information' => '機台資訊',
        'channel_machine_report' => '機台報表',
        'channel_up_and_down_report' => '上下分報表',
        'machine_strategy_list' => '機台攻略',
        'machine_producer' => '廠家清單',
//前端配寘
        'channel_client_manager' => '用戶端管理',
        'channel_rotation_chart_manager' => '輪播圖管理',
        'channel_marquee_manager' => '系統配寘',
        'channel_announcement_manager' => '公告管理',
//財務管理
        'channel_financial_manager' => '財務管理',
        'channel_recharge_review' => '開分稽核',
        'channel_withdrawal_review' => '洗分稽核',
        'channel_withdrawal_and_payment' => '洗分打款',
        'channel_recharge_record' => '開分記錄',
        'channel_talk_recharge_records' => 'QTalk開分記錄',
        'channel_withdrawal_records' => '洗分記錄',
        'channel_talk_withdrawal_records' => 'QTalk洗分記錄',
        'channel_recharge_channel_configuration' => '開分通路配寘',
        'channel_financial_operation_records' => '財務操作記錄',
//許可權管理
        'channel_auth_manager' => '許可權管理',
        'channel_admin_user_manager' => '用戶管理',
        'channel_post_manager' => '崗位管理',
        /** TODO start翻譯*/
//活動管理
        'activity' => '活動管理',
        'activity_index' => '活動清單',
        'player_activity_record' => '活動參與記錄',
        'player_activity_record_examine' => '獎勵領取稽核',
        'player_activity_record_receive' => '活動領取記錄',
//推廣管理
        'promotion_management' => '推廣管理',
        'promoter_list' => '推廣員清單',
        'profit_record' => '分潤報表',
        'profit_settlement_record' => '分潤結算記錄',
//彩金管理
        'lottery_management' => '彩金管理',
        'lottery_list' => '彩金清單',
        'lottery_audit_list' => '彩金稽核',
        'lottery_records' => '彩金領取記錄',
//幣商管理
        'coin_management' => '幣商管理',
        'coin_list' => '幣商清單',
        'coin_recharge_record' => '幣商開分記錄',
//日誌中心
        'log_center' => '日誌中心',
        'machine_keeping_log' => '機台保留日誌',
        'player_edit_log' => '玩家資料修改日誌',
        'machine_operation_log' => '機台操作日誌',
        'machine_edit_log' => '機台異動日誌',
        'lottery_add_log' => '彩金獎池累積日誌',
        'player_money_edit_log' => '錢包操作日誌',
        'computer_game' => '電子遊戲',
        'game_platforms' => '遊戲平臺清單',
        'game_transfer_record' => '轉帳記錄',
        'play_game_record' => '遊戲記錄',
        'game_platform_profit' => '遊戲平臺分潤',
        //店機配置
        'store_setting_manage' => '店機系統配置',
        'store_open_score_setting' => '店機開分配置',
        'store_shift_handover_record' => '交班記錄',
        //代理管理
        'agent_management' => '代理管理',
        //店機管理
        'store_management' => '店機管理',
        //代理彩金管理
        'agent_lottery_management' => '彩金管理',
        //店機彩金管理
        'store_lottery_management' => '彩金管理',
        //開分滿贈管理（管理後台）
        'deposit_bonus_manage' => '開分滿贈管理',
        'deposit_bonus_activity' => '活動管理',
        'deposit_bonus_qrcode' => '訂單管理',
        'deposit_bonus_statistics' => '統計報表',
        'deposit_bonus_bet_detail' => '押碼明細',
        //渠道後台開分滿贈管理
        'channel_deposit_bonus_manage' => '開分滿贈管理',
        'channel_deposit_bonus_activity' => '活動管理',
        'channel_deposit_bonus_order' => '訂單管理',
        'channel_deposit_bonus_statistics' => '統計報表',
        'channel_deposit_bonus_bet_detail' => '押碼明細',
        //代理/店機上下分報表
        'agent_game_log' => '上下分報表',
        'agent_game_log_list' => '報表列表',
        'store_game_log' => '上下分報表',
        'store_game_log_list' => '報表列表',
        //代理後台財務管理
        'agent_financial_management' => '財務管理',
        'agent_recharge_record' => '開分記錄',
        'agent_withdraw_record' => '洗分記錄',
        //代理後台電子遊戲管理
        'agent_game_management' => '電子遊戲管理',
        'agent_game_record' => '遊戲記錄',
        //代理後台開分滿贈管理
        'agent_deposit_bonus_manage' => '開分滿贈管理',
        'agent_deposit_bonus_activity' => '活動管理',
        'agent_deposit_bonus_qrcode' => '訂單管理',
        'agent_deposit_bonus_order' => '訂單管理',
        'agent_deposit_bonus_statistics' => '統計報表',
        'agent_deposit_bonus_bet_detail' => '押碼明細',
        'agent_deposit_bonus_task' => '打碼量任務',
        //代理後台分潤管理
        'agent_profit' => '分潤管理',
        'agent_profit_statistics' => '分潤統計',
        'agent_profit_records' => '分潤記錄',
        'agent_settlement_records' => '結算記錄',
        //店機後台設備管理
        'store_player' => '設備管理',
        'store_player_list' => '設備列表',
        //店機後台開分滿贈管理
        'store_deposit_bonus_manage' => '開分滿贈管理',
        'store_deposit_bonus_activity' => '活動管理',
        'store_deposit_bonus_order' => '訂單管理',
        'store_deposit_bonus_statistics' => '統計報表',
        'store_deposit_bonus_bet_detail' => '押碼明細',
        'store_deposit_bonus_task' => '打碼量任務',
        //店機後台財務管理
        'store_financial_manage' => '開洗分中心',
        'store_recharge_record' => '開分記錄',
        'store_withdraw_record' => '洗分記錄',
        //店機後台電子遊戲管理
        'store_game_manage' => '電子遊戲管理',
        'store_game_record' => '遊戲記錄',
        'store_deposit_bonus_qrcode' => '訂單管理',
        //自動交班管理
        'auto_shift_management' => '自動交班',
        'auto_shift_config' => '交班配置',
        'auto_shift_logs' => '執行日誌',
        //限紅組管理（總後台）
        'limit_group' => '限紅管理',
        'limit_group_list' => '限紅組管理',
        'limit_group_config' => '平臺配置',
        //渠道限紅管理
        'channel_limit_group' => '限紅管理',
        'channel_admin_limit_group' => '店家限紅分配',
        //渠道後台店家分潤報表
        'channel_store_profit_report' => '店家分潤報表',
    ]
];
