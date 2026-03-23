<?php
/**
 * 店家后台功能权限配置
 * 角色: 线下渠道店家 (角色ID: 19)
 *
 * 店家主要功能：
 * 1. 管理玩家
 * 2. 充值/提现
 * 3. 上分/下分
 * 4. 查看账变记录
 * 5. 游戏记录
 */
return [
    // ========== 数据中心 ==========
    [
        'id' => 'addons\webman\controller\ChannelIndexController\storeIndex',
        'pid' => 0,
        'action' => 'storeIndex',
        'method' => 'get',
        'group' => 'store',
        'url' => 'ex-admin/addons-webman-controller-ChannelIndexController/storeIndex',
        'title' => '店家中心',
    ],

    // ========== 设备管理 ==========
    [
        'id' => 'addons\webman\controller\StorePlayerController-',
        'pid' => 0,
        'url' => '',
        'group' => 'store',
        'title' => '设备管理',
        'children' => [
            // 设备列表
            [
                'id' => 'addons\webman\controller\StorePlayerController\index',
                'pid' => 'addons\webman\controller\StorePlayerController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-StorePlayerController/index',
                'title' => '设备列表',
            ],
        ]
    ],

    // ========== 玩家管理 ==========
    [
        'id' => 'addons\webman\controller\ChannelPlayerController-',
        'pid' => 0,
        'url' => '',
        'group' => 'store',
        'title' => '玩家管理',
        'children' => [
            // 玩家列表
            [
                'id' => 'addons\webman\controller\ChannelPlayerController\index',
                'pid' => 'addons\webman\controller\ChannelPlayerController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-ChannelPlayerController/index',
                'title' => '设备列表',
            ],
            // 添加玩家
            [
                'id' => 'addons\webman\controller\ChannelPlayerController\form-post',
                'pid' => 'addons\webman\controller\ChannelPlayerController-',
                'action' => 'form',
                'method' => 'post',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-ChannelPlayerController/form',
                'title' => '添加玩家',
            ],
            // 修改玩家
            [
                'id' => 'addons\webman\controller\ChannelPlayerController\form-put',
                'pid' => 'addons\webman\controller\ChannelPlayerController-',
                'action' => 'form',
                'method' => 'put',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-ChannelPlayerController/form',
                'title' => '修改玩家',
            ],
            // 玩家钱包
            [
                'id' => 'addons\webman\controller\ChannelPlayerController\playerWallet',
                'pid' => 'addons\webman\controller\ChannelPlayerController-',
                'action' => 'playerWallet',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-ChannelPlayerController/playerWallet',
                'title' => '玩家钱包',
            ],
            // 人工充值
            [
                'id' => 'addons\webman\controller\ChannelPlayerController\artificialRecharge',
                'pid' => 'addons\webman\controller\ChannelPlayerController-',
                'action' => 'artificialRecharge',
                'method' => 'post',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-ChannelPlayerController/artificialRecharge',
                'title' => '人工充值',
            ],
            // 人工提现
            [
                'id' => 'addons\webman\controller\ChannelPlayerController\artificialWithdrawal',
                'pid' => 'addons\webman\controller\ChannelPlayerController-',
                'action' => 'artificialWithdrawal',
                'method' => 'post',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-ChannelPlayerController/artificialWithdrawal',
                'title' => '人工提现',
            ],
            // 全部下分
            [
                'id' => 'addons\webman\controller\ChannelPlayerController\withdrawAmountAll',
                'pid' => 'addons\webman\controller\ChannelPlayerController-',
                'action' => 'withdrawAmountAll',
                'method' => 'post',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-ChannelPlayerController/withdrawAmountAll',
                'title' => '全部下分',
            ],
            // 下分
            [
                'id' => 'addons\webman\controller\ChannelPlayerController\withdrawAmount',
                'pid' => 'addons\webman\controller\ChannelPlayerController-',
                'action' => 'withdrawAmount',
                'method' => 'post',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-ChannelPlayerController/withdrawAmount',
                'title' => '下分',
            ],
            // 上分
            [
                'id' => 'addons\webman\controller\ChannelPlayerController\depositAmount',
                'pid' => 'addons\webman\controller\ChannelPlayerController-',
                'action' => 'depositAmount',
                'method' => 'post',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-ChannelPlayerController/depositAmount',
                'title' => '上分',
            ],
            // 玩家银行卡
            [
                'id' => 'addons\webman\controller\ChannelPlayerController\playerBank',
                'pid' => 'addons\webman\controller\ChannelPlayerController-',
                'action' => 'playerBank',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-ChannelPlayerController/playerBank',
                'title' => '银行卡管理',
            ],
            // 玩家游戏钱包
            [
                'id' => 'addons\webman\controller\ChannelPlayerController\playerGameWallet',
                'pid' => 'addons\webman\controller\ChannelPlayerController-',
                'action' => 'playerGameWallet',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-ChannelPlayerController/playerGameWallet',
                'title' => '游戏钱包',
            ],
        ]
    ],

    // ========== 账变记录 ==========
    [
        'id' => 'addons\webman\controller\ChannelPlayerDeliveryRecordController-',
        'pid' => 0,
        'url' => '',
        'group' => 'store',
        'title' => '账变记录',
        'children' => [
            [
                'id' => 'addons\webman\controller\ChannelPlayerDeliveryRecordController\index',
                'pid' => 'addons\webman\controller\ChannelPlayerDeliveryRecordController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-ChannelPlayerDeliveryRecordController/index',
                'title' => '账变记录',
            ],
        ]
    ],

    // ========== 游戏记录 ==========
    [
        'id' => 'addons\webman\controller\StorePlayGameRecordController-',
        'pid' => 0,
        'url' => '',
        'group' => 'store',
        'title' => '游戏记录',
        'children' => [
            [
                'id' => 'addons\webman\controller\StorePlayGameRecordController\index',
                'pid' => 'addons\webman\controller\StorePlayGameRecordController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-StorePlayGameRecordController/index',
                'title' => '游戏记录',
            ],
            [
                'id' => 'addons\webman\controller\StorePlayGameRecordController\replay',
                'pid' => 'addons\webman\controller\StorePlayGameRecordController\index',
                'action' => 'replay',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-StorePlayGameRecordController/replay',
                'title' => '游戏回放',
            ],
        ]
    ],

    // ========== 充值记录 ==========
    [
        'id' => 'addons\webman\controller\StorePlayerRechargeRecordController-',
        'pid' => 0,
        'url' => '',
        'group' => 'store',
        'title' => '充值记录',
        'children' => [
            [
                'id' => 'addons\webman\controller\StorePlayerRechargeRecordController\index',
                'pid' => 'addons\webman\controller\StorePlayerRechargeRecordController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-StorePlayerRechargeRecordController/index',
                'title' => '充值记录',
            ],
        ]
    ],

    // ========== 提现记录 ==========
    [
        'id' => 'addons\webman\controller\StorePlayerWithdrawRecordController-',
        'pid' => 0,
        'url' => '',
        'group' => 'store',
        'title' => '提现记录',
        'children' => [
            [
                'id' => 'addons\webman\controller\StorePlayerWithdrawRecordController\index',
                'pid' => 'addons\webman\controller\StorePlayerWithdrawRecordController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-StorePlayerWithdrawRecordController/index',
                'title' => '提现记录',
            ],
        ]
    ],

    // ========== 财务报表 ==========
    [
        'id' => 'addons\webman\controller\ChannelPlayerReportController-',
        'pid' => 0,
        'url' => '',
        'group' => 'store',
        'title' => '财务报表',
        'children' => [
            [
                'id' => 'addons\webman\controller\ChannelPlayerReportController\index',
                'pid' => 'addons\webman\controller\ChannelPlayerReportController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-ChannelPlayerReportController/index',
                'title' => '玩家报表',
            ],
        ]
    ],

    // ========== 上下分报表 ==========
    [
        'id' => 'addons\webman\controller\StorePlayerGameLogController-',
        'pid' => 0,
        'url' => '',
        'group' => 'store',
        'title' => '报表中心',
        'children' => [
            [
                'id' => 'addons\webman\controller\StorePlayerGameLogController\index',
                'pid' => 'addons\webman\controller\StorePlayerGameLogController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-StorePlayerGameLogController/index',
                'title' => '上下分报表',
            ],
        ]
    ],

    // ========== 彩金管理 ==========
    [
        'id' => 'addons\webman\controller\StoreLotteryController-',
        'pid' => 0,
        'url' => '',
        'group' => 'store',
        'title' => '彩金管理',
        'children' => [
            // 彩金领取记录
            [
                'id' => 'addons\webman\controller\StoreLotteryController\index',
                'pid' => 'addons\webman\controller\StoreLotteryController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-StoreLotteryController/index',
                'title' => '彩金领取记录',
            ],
        ]
    ],

    // ========== 充值满赠管理 ==========
    [
        'id' => 'addons\webman\controller\StoreDepositBonusActivityController-',
        'pid' => 0,
        'url' => '',
        'group' => 'store',
        'title' => '充值满赠管理',
        'children' => [
            // 充值满赠活动列表（仅查看）
            [
                'id' => 'addons\webman\controller\StoreDepositBonusActivityController\index',
                'pid' => 'addons\webman\controller\StoreDepositBonusActivityController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-StoreDepositBonusActivityController/index',
                'title' => '充值满赠活动',
            ],
            // 活动详情
            [
                'id' => 'addons\webman\controller\StoreDepositBonusActivityController\detail',
                'pid' => 'addons\webman\controller\StoreDepositBonusActivityController\index',
                'action' => 'detail',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-StoreDepositBonusActivityController/detail',
                'title' => '充值满赠活动详情',
            ],
            // 充值满赠订单列表
            [
                'id' => 'addons\webman\controller\StoreDepositBonusOrderController\index',
                'pid' => 'addons\webman\controller\StoreDepositBonusActivityController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-StoreDepositBonusOrderController/index',
                'title' => '充值满赠订单',
            ],
            // 生成订单表单
            [
                'id' => 'addons\webman\controller\StoreDepositBonusOrderController\form-post',
                'pid' => 'addons\webman\controller\StoreDepositBonusOrderController\index',
                'action' => 'form',
                'method' => 'post',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-StoreDepositBonusOrderController/form',
                'title' => '生成充值满赠订单',
            ],
            [
                'id' => 'addons\webman\controller\StoreDepositBonusOrderController\form-get',
                'pid' => 'addons\webman\controller\StoreDepositBonusOrderController\index',
                'action' => 'form',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-StoreDepositBonusOrderController/form',
                'title' => '充值满赠订单表单',
            ],
            // 订单详情
            [
                'id' => 'addons\webman\controller\StoreDepositBonusOrderController\detail',
                'pid' => 'addons\webman\controller\StoreDepositBonusOrderController\index',
                'action' => 'detail',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-StoreDepositBonusOrderController/detail',
                'title' => '充值满赠订单详情',
            ],
            // 打码量任务列表
            [
                'id' => 'addons\webman\controller\StoreDepositBonusTaskController\index',
                'pid' => 'addons\webman\controller\StoreDepositBonusActivityController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-StoreDepositBonusTaskController/index',
                'title' => '打码量任务',
            ],
            // 打码量任务详情
            [
                'id' => 'addons\webman\controller\StoreDepositBonusTaskController\detail',
                'pid' => 'addons\webman\controller\StoreDepositBonusTaskController\index',
                'action' => 'detail',
                'method' => 'get',
                'group' => 'store',
                'url' => 'ex-admin/addons-webman-controller-StoreDepositBonusTaskController/detail',
                'title' => '打码量任务详情',
            ],
        ]
    ],

    // ========== 个人中心 ==========
    [
        'id' => 'addons\webman\controller\AdminController\updatePassword',
        'pid' => 0,
        'action' => 'updatePassword',
        'method' => '',
        'group' => 'all',
        'url' => 'ex-admin/addons-webman-controller-AdminController/updatePassword',
        'title' => '修改密码',
    ],
    [
        'id' => 'addons\webman\controller\AdminController\editInfo',
        'pid' => 0,
        'action' => 'editInfo',
        'method' => '',
        'group' => 'all',
        'url' => 'ex-admin/addons-webman-controller-AdminController/editInfo',
        'title' => '个人信息',
    ],

    // 自动交班管理
    [
        'id' => 'addons\webman\controller\ChannelAutoShiftController-',
        'pid' => 0,
        'url' => '',
        'group' => 'store',
        'title' => '自动交班管理',
        'children' => []
    ],
    [
        'id' => 'addons\webman\controller\ChannelAutoShiftController\config',
        'pid' => 'addons\webman\controller\ChannelAutoShiftController-',
        'action' => 'config',
        'method' => 'get',
        'group' => 'store',
        'url' => 'ex-admin/addons-webman-controller-ChannelAutoShiftController/config',
        'title' => '交班配置',
    ],
    [
        'id' => 'addons\webman\controller\ChannelAutoShiftController\config-post',
        'pid' => 'addons\webman\controller\ChannelAutoShiftController\config',
        'action' => 'config',
        'method' => 'post',
        'group' => 'store',
        'url' => 'ex-admin/addons-webman-controller-ChannelAutoShiftController/config',
        'title' => '保存交班配置',
    ],
    [
        'id' => 'addons\webman\controller\ChannelAutoShiftController\logs',
        'pid' => 'addons\webman\controller\ChannelAutoShiftController-',
        'action' => 'logs',
        'method' => 'get',
        'group' => 'store',
        'url' => 'ex-admin/addons-webman-controller-ChannelAutoShiftController/logs',
        'title' => '执行日志',
    ],
    [
        'id' => 'addons\webman\controller\ChannelAutoShiftController\logs-get',
        'pid' => 'addons\webman\controller\ChannelAutoShiftController\logs',
        'action' => 'logs',
        'method' => 'get',
        'group' => 'store',
        'url' => 'ex-admin/addons-webman-controller-ChannelAutoShiftController/logs',
        'title' => '查看日志详情',
    ],
    [
        'id' => 'addons\webman\controller\ChannelAutoShiftController\manualTrigger',
        'pid' => 'addons\webman\controller\ChannelAutoShiftController-',
        'action' => 'manualTrigger',
        'method' => 'post',
        'group' => 'store',
        'url' => 'ex-admin/addons-webman-controller-ChannelAutoShiftController/manualTrigger',
        'title' => '手动触发交班',
    ],
];