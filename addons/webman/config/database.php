<?php

return [
    'database' => [
        //用户表
        'user_table' => 'admin_users',
        'user_model' => addons\webman\model\AdminUser::class,
        //菜单表
        'menu_table' => 'admin_menus',
        'menu_model' => addons\webman\model\AdminMenu::class,
        //角色表
        'role_table' => 'admin_roles',
        'role_model' => addons\webman\model\AdminRole::class,
        //角色权限关联表
        'role_permission_table' => 'admin_role_permissions',
        'role_permission_model' => addons\webman\model\AdminRolePermission::class,
        //角色菜单关联表
        'role_menu_table' => 'admin_role_menus',
        'role_menu_model' => addons\webman\model\AdminRoleMenu::class,
        //角色用户关联表
        'role_user_table' => 'admin_role_users',
        'role_user_model' => addons\webman\model\AdminRoleUsers::class,
        //系统配置表
        'config_table' => 'admin_configs',
        'config_model' => addons\webman\model\AdminConfig::class,
        //系统附件分类表
        'attachment_cate_table' => 'admin_file_attachment_cates',
        'attachment_cate_model' => addons\webman\model\AdminFileAttachmentCate::class,
        //系统附件表
        'attachment_table' => 'admin_file_attachments',
        'attachment_model' => addons\webman\model\AdminFileAttachment::class,
        //部门表
        'department_table' => 'admin_department',
        'department_model' => addons\webman\model\AdminDepartment::class,
        //岗位表
        'post_table' => 'admin_post',
        'post_model' => addons\webman\model\AdminPost::class,
        //角色数据权限部门关联表
        'role_department_table' => 'admin_role_department',
        'role_department_model' => addons\webman\model\AdminRoleDepartment::class,
        //游戏类别表
        'game_type_table' => 'game_type',
        'game_type_model' => \addons\webman\model\GameType::class,
        //机台类型表
        'machine_category_table' => 'machine_category',
        'machine_category_model' => \addons\webman\model\MachineCategory::class,
        //机台表
        'machine_table' => 'machine',
        'machine_model' => \addons\webman\model\Machine::class,
        //玩家表
        'player_table' => 'player',
        'player_model' => \addons\webman\model\Player::class,
        //玩家扩展表
        'player_extend_table' => 'player_extend',
        'player_extend_model' => \addons\webman\model\PlayerExtend::class,
        //平台
        'player_platform_cash_table' => 'player_platform_cash',
        'player_platform_cash_model' => \addons\webman\model\PlayerPlatformCash::class,
        //玩家游戏记录
        'player_game_log_table' => 'player_game_log',
        'player_game_log_model' => \addons\webman\model\PlayerGameLog::class,
        //机台API记录
        'api_error_log_table' => 'api_error_log',
        'api_error_log_model' => \addons\webman\model\ApiErrorLog::class,
        //轮播图管理
        'slider_table' => 'slider',
        'slider_model' => \addons\webman\model\Slider::class,
        //玩家钱包编辑记录
        'player_money_edit_log_table' => 'player_money_edit_log',
        'player_money_edit_log_model' => \addons\webman\model\PlayerMoneyEditLog::class,
        //玩家资金记录
        'player_delivery_record_table' => 'player_delivery_record',
        'player_delivery_record_model' => \addons\webman\model\PlayerDeliveryRecord::class,
        //玩家登录记录
        'player_login_record_table' => 'player_login_record',
        'player_login_record_model' => \addons\webman\model\PlayerLoginRecord::class,
        //玩家注册记录
        'player_register_record_table' => 'player_register_record',
        'player_register_record_model' => \addons\webman\model\PlayerRegisterRecord::class,
        //机台保留日志
        'machine_keeping_log_table' => 'machine_keeping_log',
        'machine_keeping_log_model' => \addons\webman\model\MachineKeepingLog::class,
        //机台游戏日志
        'machine_gaming_log_table' => 'machine_gaming_log',
        'machine_gaming_log_model' => \addons\webman\model\MachineGamingLog::class,
        //机台游戏日志
        'system_setting_table' => 'system_setting',
        'system_setting_model' => \addons\webman\model\SystemSetting::class,
        //短信记录
        'phone_sms_log_table' => 'phone_sms_log',
        'phone_sms_log_model' => \addons\webman\model\PhoneSmsLog::class,
        //玩家转点记录
        'player_present_record_table' => 'player_present_record',
        'player_present_record_model' => \addons\webman\model\PlayerPresentRecord::class,
        //玩家踢出日志
        'machine_kick_log_table' => 'machine_kick_log',
        'machine_kick_log_model' => \addons\webman\model\MachineKickLog::class,
        //玩家充值记录
        'player_recharge_record_table' => 'player_recharge_record',
        'player_recharge_record_model' => \addons\webman\model\PlayerRechargeRecord::class,
        //玩家标签
        'player_tag_table' => 'player_tag',
        'player_tag_model' => \addons\webman\model\PlayerTag::class,
        //公告
        'announcement_table' => 'announcement',
        'announcement_model' => \addons\webman\model\Announcement::class,
        //玩家提现
        'player_withdraw_record_table' => 'player_withdraw_record',
        'player_withdraw_record_model' => \addons\webman\model\PlayerWithdrawRecord::class,
        //機台收藏記錄
        'player_favorite_machine_table' => 'player_favorite_machine',
        'player_favorite_machine_model' => \addons\webman\model\PlayerFavoriteMachine::class,
        //玩家游戏局
        'player_game_record_table' => 'player_game_record',
        'player_game_record_model' => \addons\webman\model\PlayerGameRecord::class,
        //机台报表数据
        'machine_report_table' => 'machine_report',
        'machine_report_model' => \addons\webman\model\MachineReport::class,
        //渠道
        'channel_table' => 'channel',
        'channel_model' => \addons\webman\model\Channel::class,
        //货币
        'currency_table' => 'currency',
        'currency_model' => \addons\webman\model\Currency::class,
        //渠道充值方式
        'channel_recharge_method_table' => 'channel_recharge_method',
        'channel_recharge_method_model' => \addons\webman\model\ChannelRechargeMethod::class,
        //渠道充值配置
        'channel_recharge_setting_table' => 'channel_recharge_setting',
        'channel_recharge_setting_model' => \addons\webman\model\ChannelRechargeSetting::class,
        //财务操作记录
        'channel_financial_record_table' => 'channel_financial_record',
        'channel_financial_record_model' => \addons\webman\model\ChannelFinancialRecord::class,
        //玩家银行卡
        'player_bank_table' => 'player_bank',
        'player_bank_model' => \addons\webman\model\PlayerBank::class,
        //机台媒体
        'machine_media_table' => 'machine_media',
        'machine_media_model' => \addons\webman\model\MachineMedia::class,
        //外部应用
        'external_app_table' => 'external_app',
        'external_app_model' => \addons\webman\model\ExternalApp::class,
        //活动
        'activity_table' => 'activity',
        'activity_model' => \addons\webman\model\Activity::class,
        //活动内容
        'activity_content_table' => 'activity_content',
        'activity_content_model' => \addons\webman\model\ActivityContent::class,
        //活动阶段
        'activity_phase_table' => 'activity_phase',
        'activity_phase_model' => \addons\webman\model\ActivityPhase::class,
        //玩家活动参与记录
        'player_activity_record_table' => 'player_activity_record',
        'player_activity_record_model' => \addons\webman\model\PlayerActivityRecord::class,
        //玩家活动领取记录
        'player_activity_phase_record_table' => 'player_activity_phase_record',
        'player_activity_phase_record_model' => \addons\webman\model\PlayerActivityPhaseRecord::class,
        //机台攻略
        'machine_strategy_table' => 'machine_strategy',
        'machine_strategy_model' => \addons\webman\model\MachineStrategy::class,
        //推广员
        'player_promoter_table' => 'player_promoter',
        'player_promoter_model' => \addons\webman\model\PlayerPromoter::class,
        //推广员分润记录
        'promoter_profit_record_table' => 'promoter_profit_record',
        'promoter_profit_record_model' => \addons\webman\model\PromoterProfitRecord::class,
        //推广员分润结算记录
        'promoter_profit_settlement_record_table' => 'promoter_profit_settlement_record',
        'promoter_profit_settlement_record_model' => \addons\webman\model\PromoterProfitSettlementRecord::class,
        //开分赠点
        'machine_category_give_rule_table' => 'machine_category_give_rule',
        'machine_category_give_rule_model' => \addons\webman\model\MachineCategoryGiveRule::class,
        //玩家增点记录
        'player_gift_record_table' => 'player_gift_record',
        'player_gift_record_model' => \addons\webman\model\PlayerGiftRecord::class,
        //玩家洗分记录
        'player_wash_record_table' => 'player_wash_record',
        'player_wash_record_model' => \addons\webman\model\PlayerWashRecord::class,
        //玩家信息修改日志
        'player_edit_log_table' => 'player_edit_log',
        'player_edit_log_model' => \addons\webman\model\PlayerEditLog::class,
        //机台异动日志
        'machine_edit_log_table' => 'machine_edit_log',
        'machine_edit_log_model' => \addons\webman\model\MachineEditLog::class,
        //机台开分卡更换
        'machine_open_card_table' => 'machine_open_card',
        'machine_open_card_model' => \addons\webman\model\MachineOpenCard::class,
        //彩金池
        'lottery_pool_table' => 'lottery_pool',
        'lottery_pool_model' => \addons\webman\model\LotteryPool::class,
        //彩金
        'lottery_table' => 'lottery',
        'lottery_model' => \addons\webman\model\Lottery::class,
        //玩家派彩记录
        'player_lottery_record_table' => 'player_lottery_record',
        'player_lottery_record_model' => \addons\webman\model\PlayerLotteryRecord::class,
        //消息
        'notice_table' => 'notice',
        'notice_model' => \addons\webman\model\Notice::class,
        //机台厂商
        'machine_producer_table' => 'machine_producer',
        'machine_producer_model' => \addons\webman\model\MachineProducer::class,
        //电子游戏平台
        'game_platform_table' => 'game_platform',
        'game_platform_model' => \addons\webman\model\GamePlatform::class,
        //玩家游戏平台账号
        'player_game_platform_table' => 'player_game_platform',
        'player_game_platform_model' => \addons\webman\model\PlayerGamePlatform::class,
        //玩家钱包转出/入记录
        'player_wallet_transfer_table' => 'player_wallet_transfer',
        'player_wallet_transfer_model' => \addons\webman\model\PlayerWalletTransfer::class,
        //玩家游戏记录
        'play_game_record_table' => 'play_game_record',
        'play_game_record_model' => \addons\webman\model\PlayGameRecord::class,
        //玩家游戏分润记录
        'promoter_profit_game_record_table' => 'promoter_profit_game_record',
        'promoter_profit_game_record_model' => \addons\webman\model\PromoterProfitGameRecord::class,
        //游戏
        'game_table' => 'game',
        'game_model' => \addons\webman\model\Game::class,
        //游戏内容
        'game_content_table' => 'game_content',
        'game_content_model' => \addons\webman\model\GameContent::class,
        //游戏扩展表
        'game_extend_table' => 'game_extend',
        'game_extend_model' => \addons\webman\model\GameExtend::class,
        //机台标签
        'machine_label_table' => 'machine_label',
        'machine_label_model' => \addons\webman\model\MachineLabel::class,
        //渠道机器
        'channel_machine_table' => 'channel_machine',
        'channel_machine_label_model' => \addons\webman\model\ChannelMachine::class,
        //机器中奖记录
        'machine_lottery_record_table' => 'machine_lottery_record',
        'machine_lottery_record_model' => \addons\webman\model\MachineLotteryRecord::class,
        //进入游戏记录
        'player_enter_game_record_table' => 'player_enter_game_record',
        'player_enter_game_record_model' => \addons\webman\model\PlayerEnterGameRecord::class,
        //全民代理
        'national_promoter_table' => 'national_promoter',
        'national_promoter_model' => \addons\webman\model\NationalPromoter::class,
        //全民代理等级
        'national_level_table' => 'national_level',
        'national_level_model' => \addons\webman\model\NationalLevel::class,
        //全民代理等级列表
        'level_list_table' => 'level_list',
        'level_list_model' => \addons\webman\model\LevelList::class,
        //邀请人奖励
        'national_invite_table' => 'national_invite',
        'national_invite_model' => \addons\webman\model\NationalInvite::class,
        //全民代理收益记录
        'national_profit_record_table' => 'national_profit_record',
        'national_profit_record_model' => \addons\webman\model\NationalProfitRecord::class,
        //银行列表
        'bank_table' => 'bank_list',
        'bank_model' => \addons\webman\model\Bank::class,
        //银行内容表
        'bank_content_table' => 'bank_content',
        'bank_content_model' => \addons\webman\model\BankContent::class,
        //电子游戏反水表
        'channel_platform_reverse_water_table' => 'channel_platform_reverse_water',
        'channel_platform_reverse_water_model' => \addons\webman\model\ChannelPlatformReverseWater::class,
        'channel_platform_reverse_water_setting_table' => 'channel_platform_reverse_water_setting',
        'channel_platform_reverse_water_setting_model' => \addons\webman\model\ChannelPlatformReverseWaterSetting::class,
        'player_reverse_water_detail_table' => 'player_reverse_water_detail',
        'player_reverse_water_detail_model' => \addons\webman\model\PlayerReverseWaterDetail::class,
        //视讯录制记录
        'machine_recording_table' => 'machine_recording',
        'machine_recording_model' => \addons\webman\model\MachineRecording::class,
        //推流记录
        'machine_media_push_table' => 'machine_media_push',
        'machine_media_push_model' => \addons\webman\model\MachineMediaPush::class,
        //腾讯云播放设置
        'machine_tencent_play_table' => 'machine_tencent_play',
        'machine_tencent_play_model' => \addons\webman\model\MachineTencentPlay::class,
        //代理玩家转账记录
        'agent_transfer_order_table' => 'agent_transfer_order',
        'agent_transfer_order_model' => \addons\webman\model\AgentTransferOrder::class,
        //渠道转入转出账目记录
        'channel_transfer_record_table' => 'channel_transfer_record',
        'channel_transfer_record_model' => \addons\webman\model\ChannelTransferRecord::class,
        //播放记录
        'play_history_table' => 'play_history',
        'play_history_model' => \addons\webman\model\PlayHistory::class,
        //渠道分润记录
        'channel_profit_record_table' => 'channel_profit_record',
        'channel_profit_record_model' => \addons\webman\model\ChannelProfitRecord::class,
        //渠道分润结算记录
        'channel_profit_settlement_record_table' => 'channel_profit_settlement_record',
        'channel_profit_settlement_record_model' => \addons\webman\model\ChannelProfitSettlementRecord::class,
        //渠道分润结算记录
        'store_agent_profit_record_table' => 'store_agent_profit_record',
        'store_agent_profit_record_model' => \addons\webman\model\StoreAgentProfitRecord::class,
        //渠道分润结算记录
        'store_agent_shift_handover_record_table' => 'store_agent_shift_handover_record',
        'store_agent_shift_handover_record_model' => \addons\webman\model\StoreAgentShiftHandoverRecord::class,
        //机台分类扩展信息
        'machine_category_extend_table' => 'machine_category_extend',
        'machine_category_extend_model' => \addons\webman\model\MachineCategoryExtend::class,
        //机台分类扩展信息
        'machine_label_extend_table' => 'machine_label_extend',
        'machine_label_extend_model' => \addons\webman\model\MachineLabelExtend::class,
        //机台分类扩展信息
        'ip_white_list_table' => 'ip_white_list',
        'ip_white_list_model' => \addons\webman\model\IpWhitelist::class,
        //渠道平台webid关联表
        'channel_game_web_table' => 'channel_game_web',
        'channel_game_web_model' => \addons\webman\model\ChannelGameWeb::class,
        //电子游戏彩金
        'game_lottery_table' => 'game_lottery',
        'game_lottery_model' => \addons\webman\model\GameLottery::class,
        //店家开分配置
        'open_score_setting_table' => 'open_score_setting',
        'open_score_setting_model' => \addons\webman\model\OpenScoreSetting::class,
        //充值满赠活动
        'deposit_bonus_activity_table' => 'deposit_bonus_activity',
        'deposit_bonus_activity_model' => \addons\webman\model\DepositBonusActivity::class,
        //充值满赠档位
        'deposit_bonus_tier_table' => 'deposit_bonus_tier',
        'deposit_bonus_tier_model' => \addons\webman\model\DepositBonusTier::class,
        //充值满赠订单
        'deposit_bonus_order_table' => 'deposit_bonus_order',
        'deposit_bonus_order_model' => \addons\webman\model\DepositBonusOrder::class,
        //充值满赠统计
        'deposit_bonus_statistics_table' => 'deposit_bonus_statistics',
        'deposit_bonus_statistics_model' => \addons\webman\model\DepositBonusStatistics::class,
        //充值满赠押码明细
        'deposit_bonus_bet_detail_table' => 'deposit_bonus_bet_detail',
        'deposit_bonus_bet_detail_model' => \addons\webman\model\DepositBonusBetDetail::class,
        //玩家押码任务
        'player_bonus_task_table' => 'player_bonus_task',
        'player_bonus_task_model' => \addons\webman\model\PlayerBonusTask::class,
        //充值满赠账变记录
        'player_money_edit_log_bonus_table' => 'player_money_edit_log_bonus',
        'player_money_edit_log_bonus_model' => \addons\webman\model\PlayerMoneyEditLogBonus::class,
    ],
];
