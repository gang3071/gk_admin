<?php

namespace addons\webman\constant;

/**
 * 菜单常量类
 *
 * 用于统一管理需要根据渠道功能开关动态显示/隐藏的菜单名称
 *
 * @package addons\webman\constant
 */
class MenuConstant
{
    // ==========================================
    // 渠道功能开关相关菜单
    // ==========================================

    /**
     * 提现功能菜单
     * 控制字段：channel.withdraw_status
     */
    const WITHDRAW_MENUS = [
        'channel_recharge_channel_configuration',  // 充值渠道配置
    ];

    /**
     * 推广功能菜单
     * 控制字段：channel.promotion_status
     */
    const PROMOTION_MENUS = [
        'promotion_management',          // 推广管理（父菜单）
        'promoter_list',                 // 推广员列表
        'profit_record',                 // 分润记录
        'profit_settlement_record',      // 分润结算记录
        'game_platform_profit',          // 游戏平台分润
    ];

    /**
     * 金币商户功能菜单
     * 控制字段：channel.coin_status
     */
    const COIN_MENUS = [
        'channel_coin_merchant_manage',           // 金币商户管理（父菜单）
        'channel_coin_merchant_list',             // 金币商户列表
        'channel_coin_merchant_recharge_records', // 金币商户充值记录
        'channel_coin_merchant_transaction_records', // 金币商户交易记录
        'coin_withdraw_record',                   // 金币提现记录
    ];

    /**
     * 线下渠道排除菜单（隐藏推广相关）
     * 控制字段：channel.is_offline = 1
     */
    const OFFLINE_EXCLUDE_MENUS = [
        'promotion_management',          // 推广管理
        'promoter_list',                 // 推广员列表
        'profit_record',                 // 分润记录
        'profit_settlement_record',      // 分润结算记录
        'game_platform_profit',          // 游戏平台分润
    ];

    /**
     * 线上渠道排除菜单（隐藏线下机台相关）
     * 控制字段：channel.is_offline = 0
     */
    const ONLINE_EXCLUDE_MENUS = [
        'offline_agent',                 // 线下代理（父菜单）
        'agent_manage',                  // 代理管理
        'store_machine_manage',          // 店家机台管理
        'agent_center',                  // 代理中心
        'agent_deposit_bonus_manage',    // 代理充值满赠
        'store_manage',                  // 店家管理
    ];

    /**
     * 彩票功能菜单
     * 控制字段：channel.lottery_status
     */
    const LOTTERY_MENUS = [
        'lottery_management',            // 彩票管理（父菜单）
        'lottery_records',               // 彩票记录
        'lottery_audit_list',            // 彩票审核列表
    ];

    /**
     * 活动功能菜单
     * 控制字段：channel.activity_status
     */
    const ACTIVITY_MENUS = [
        'activity',                      // 活动（父菜单）
        'player_activity_record_receive', // 活动领取记录
        'player_activity_record_examine', // 活动审核记录
        'player_activity_record',        // 活动记录
        'activity_index',                // 活动列表
    ];

    /**
     * 摸奖券功能菜单
     * 控制字段：channel.lottery_ticket_enabled
     *
     * ⚠️ 注意：菜单ID需要在admin_menu表中创建后才能生效
     *
     * 渠道后台菜单:
     * - lottery_ticket_manage (父菜单)
     * - lottery_ticket_dashboard (进行中的活动)
     * - lottery_ticket_history (历史活动记录)
     * - lottery_ticket_records (中奖记录)
     *
     * 代理后台菜单:
     * - agent_lottery_ticket_management (父菜单)
     * - agent_lottery_ticket_activity_list (摸奖券活动)
     * - agent_lottery_ticket_list (摸奖券列表)
     * - agent_lottery_ticket_record_list (中奖记录)
     *
     * 店家后台菜单:
     * - store_lottery_ticket_management (父菜单)
     * - store_lottery_ticket_activity_list (摸奖券活动)
     * - store_lottery_ticket_list (摸奖券列表)
     * - store_lottery_ticket_record_list (中奖记录)
     */
    const LOTTERY_TICKET_MENUS = [
        // 渠道后台
        'lottery_ticket_manage',                // 摸奖券管理（父菜单）
        'lottery_ticket_dashboard',             // 进行中的活动
        'lottery_ticket_history',               // 历史活动记录
        'lottery_ticket_records',               // 中奖记录
        // 代理后台
        'agent_lottery_ticket_management',      // 摸奖券管理（父菜单）
        'agent_lottery_ticket_activity_list',   // 摸奖券活动
        'agent_lottery_ticket_list',            // 摸奖券列表
        'agent_lottery_ticket_record_list',     // 中奖记录
        // 店家后台
        'store_lottery_ticket_management',      // 摸奖券管理（父菜单）
        'store_lottery_ticket_activity_list',   // 摸奖券活动
        'store_lottery_ticket_list',            // 摸奖券列表
        'store_lottery_ticket_record_list',     // 中奖记录
    ];

    /**
     * VIP等级功能菜单
     * 控制字段：channel.vip_level_status
     */
    const VIP_LEVEL_MENUS = [
        'vip_level_manage',              // VIP等级管理（父菜单）
        'vip_level_list',                // VIP等级列表
        'vip_level_cashback',            // VIP返水配置
    ];

    // ==========================================
    // 辅助方法
    // ==========================================

    /**
     * 根据功能类型获取对应的菜单名称数组
     *
     * @param string $feature 功能类型
     * @return array
     */
    public static function getMenusByFeature(string $feature): array
    {
        $featureMap = [
            'withdraw' => self::WITHDRAW_MENUS,
            'promotion' => self::PROMOTION_MENUS,
            'coin' => self::COIN_MENUS,
            'offline_exclude' => self::OFFLINE_EXCLUDE_MENUS,
            'online_exclude' => self::ONLINE_EXCLUDE_MENUS,
            'lottery' => self::LOTTERY_MENUS,
            'activity' => self::ACTIVITY_MENUS,
            'lottery_ticket' => self::LOTTERY_TICKET_MENUS,
            'vip_level' => self::VIP_LEVEL_MENUS,
        ];

        return $featureMap[$feature] ?? [];
    }

    /**
     * 检查菜单名称是否被控制
     *
     * @param string $menuName
     * @return bool
     */
    public static function isControlledMenu(string $menuName): bool
    {
        return in_array($menuName, self::getAllControlledMenus());
    }

    /**
     * 获取所有需要控制的菜单名称（用于验证）
     *
     * @return array
     */
    public static function getAllControlledMenus(): array
    {
        return array_merge(
            self::WITHDRAW_MENUS,
            self::PROMOTION_MENUS,
            self::COIN_MENUS,
            self::OFFLINE_EXCLUDE_MENUS,
            self::ONLINE_EXCLUDE_MENUS,
            self::LOTTERY_MENUS,
            self::ACTIVITY_MENUS,
            self::LOTTERY_TICKET_MENUS,
            self::VIP_LEVEL_MENUS
        );
    }

    /**
     * 获取菜单控制说明
     *
     * @return array
     */
    public static function getMenuControlDescription(): array
    {
        return [
            'withdraw' => [
                'field' => 'channel.withdraw_status',
                'menus' => self::WITHDRAW_MENUS,
                'description' => '提现功能开关（0:关闭，1:开启）',
            ],
            'promotion' => [
                'field' => 'channel.promotion_status',
                'menus' => self::PROMOTION_MENUS,
                'description' => '推广功能开关（0:关闭，1:开启）',
            ],
            'coin' => [
                'field' => 'channel.coin_status',
                'menus' => self::COIN_MENUS,
                'description' => '金币功能开关（0:关闭，1:开启）',
            ],
            'offline_exclude' => [
                'field' => 'channel.is_offline',
                'menus' => self::OFFLINE_EXCLUDE_MENUS,
                'description' => '线下渠道排除菜单（is_offline = 1 时隐藏）',
            ],
            'online_exclude' => [
                'field' => 'channel.is_offline',
                'menus' => self::ONLINE_EXCLUDE_MENUS,
                'description' => '线上渠道排除菜单（is_offline = 0 时隐藏）',
            ],
            'lottery' => [
                'field' => 'channel.lottery_status',
                'menus' => self::LOTTERY_MENUS,
                'description' => '彩票功能开关（0:关闭，1:开启）',
            ],
            'activity' => [
                'field' => 'channel.activity_status',
                'menus' => self::ACTIVITY_MENUS,
                'description' => '活动功能开关（0:关闭，1:开启）',
            ],
            'lottery_ticket' => [
                'field' => 'channel.lottery_ticket_enabled',
                'menus' => self::LOTTERY_TICKET_MENUS,
                'description' => '摸奖券功能开关（0:关闭，1:开启）',
            ],
            'vip_level' => [
                'field' => 'channel.vip_level_status',
                'menus' => self::VIP_LEVEL_MENUS,
                'description' => 'VIP等级功能开关（0:关闭，1:开启）',
            ],
        ];
    }
}
