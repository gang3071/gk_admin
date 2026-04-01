<?php
/**
 * 代理后台功能权限配置
 * 角色: 线下渠道代理 (角色ID: 18)
 *
 * 代理主要功能：
 * 1. 管理下级店家
 * 2. 开分/洗分
 * 3. 查看账变记录
 * 4. 查看交班报表
 * 5. 分润结算
 * 6. 财务管理（充值记录、提现记录）
 * 7. 电子游戏管理（游戏记录）
 */
return [
    // ========== 数据中心 ==========
    [
        'id' => 'addons\webman\controller\ChannelIndexController\agentIndex',
        'pid' => 0,
        'action' => 'agentIndex',
        'method' => 'get',
        'group' => 'agent',
        'url' => 'ex-admin/addons-webman-controller-ChannelIndexController/agentIndex',
        'title' => '数据中心',
    ],

    // ========== 店家管理 ==========
    [
        'id' => 'addons\webman\controller\ChannelAgentController-',
        'pid' => 0,
        'url' => '',
        'group' => 'agent',
        'title' => '店家管理',
        'children' => [
            // 店家列表
            [
                'id' => 'addons\webman\controller\ChannelAgentController\index',
                'pid' => 'addons\webman\controller\ChannelAgentController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-ChannelAgentController/index',
                'title' => '店家列表',
            ],
            // 设备列表
            [
                'id' => 'addons\webman\controller\ChannelAgentController\machineList',
                'pid' => 'addons\webman\controller\ChannelAgentController-',
                'action' => 'machineList',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-ChannelAgentController/machineList',
                'title' => '设备列表',
            ],
            // 无密码开分
            [
                'id' => 'addons\webman\controller\ChannelAgentController\presentNoPassword',
                'pid' => 'addons\webman\controller\ChannelAgentController-',
                'action' => 'presentNoPassword',
                'method' => 'post',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-ChannelAgentController/presentNoPassword',
                'title' => '无密码开分',
            ],
            // 玩家游戏列表
            [
                'id' => 'addons\webman\controller\ChannelAgentController\playerGameList',
                'pid' => 'addons\webman\controller\ChannelAgentController-',
                'action' => 'playerGameList',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-ChannelAgentController/playerGameList',
                'title' => '游戏权限管理',
            ],
            // 保存玩家游戏权限
            [
                'id' => 'addons\webman\controller\ChannelAgentController\savePlayerGames',
                'pid' => 'addons\webman\controller\ChannelAgentController-',
                'action' => 'savePlayerGames',
                'method' => 'post',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-ChannelAgentController/savePlayerGames',
                'title' => '保存游戏权限',
            ],
            // 管理电子游戏
            [
                'id' => 'addons\webman\controller\ChannelAgentController\managePlayerElectronicGame',
                'pid' => 'addons\webman\controller\ChannelAgentController-',
                'action' => 'managePlayerElectronicGame',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-ChannelAgentController/managePlayerElectronicGame',
                'title' => '电子游戏权限',
            ],
        ]
    ],

    // ========== 账变记录 ==========
    [
        'id' => 'addons\webman\controller\ChannelAgentController\deliveryRecord',
        'pid' => 0,
        'action' => 'deliveryRecord',
        'method' => 'get',
        'group' => 'agent',
        'url' => 'ex-admin/addons-webman-controller-ChannelAgentController/deliveryRecord',
        'title' => '账变记录',
    ],

    // ========== 交班报表 ==========
    [
        'id' => 'addons\webman\controller\ChannelAgentController\storeAgentShiftHandoverRecord',
        'pid' => 0,
        'action' => 'storeAgentShiftHandoverRecord',
        'method' => 'get',
        'group' => 'agent',
        'url' => 'ex-admin/addons-webman-controller-ChannelAgentController/storeAgentShiftHandoverRecord',
        'title' => '交班报表',
    ],

    // ========== 上下分报表 ==========
    [
        'id' => 'addons\webman\controller\AgentPlayerGameLogController-',
        'pid' => 0,
        'url' => '',
        'group' => 'agent',
        'title' => '报表中心',
        'children' => [
            [
                'id' => 'addons\webman\controller\AgentPlayerGameLogController\index',
                'pid' => 'addons\webman\controller\AgentPlayerGameLogController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-AgentPlayerGameLogController/index',
                'title' => '上下分报表',
            ],
            [
                'id' => 'addons\webman\controller\AgentStoreProfitReportController\index',
                'pid' => 'addons\webman\controller\AgentPlayerGameLogController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-AgentStoreProfitReportController/index',
                'title' => '店家分润报表',
            ],
            [
                'id' => 'addons\webman\controller\AgentStoreProfitReportController\export',
                'pid' => 'addons\webman\controller\AgentStoreProfitReportController\index',
                'action' => 'export',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-AgentStoreProfitReportController/export',
                'title' => '导出店家分润报表',
            ],
        ]
    ],

    // ========== 分润管理 ==========
    [
        'id' => 'addons\webman\controller\ChannelAgentPromoterController-',
        'pid' => 0,
        'url' => '',
        'group' => 'agent',
        'title' => '分润管理',
        'children' => [
            [
                'id' => 'addons\webman\controller\ChannelAgentPromoterController\index',
                'pid' => 'addons\webman\controller\ChannelAgentPromoterController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-ChannelAgentPromoterController/index',
                'title' => '分润列表',
            ],
            [
                'id' => 'addons\webman\controller\ChannelAgentPromoterController\settlementList',
                'pid' => 'addons\webman\controller\ChannelAgentPromoterController-',
                'action' => 'settlementList',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-ChannelAgentPromoterController/settlementList',
                'title' => '结算列表',
            ],
            [
                'id' => 'addons\webman\controller\ChannelAgentPromoterController\settlement',
                'pid' => 'addons\webman\controller\ChannelAgentPromoterController-',
                'action' => 'settlement',
                'method' => 'post',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-ChannelAgentPromoterController/settlement',
                'title' => '执行结算',
            ],
        ]
    ],

    // ========== 财务统计 ==========
    [
        'id' => 'addons\webman\controller\ChannelStoreAgentProfitRecordController-',
        'pid' => 0,
        'url' => '',
        'group' => 'agent',
        'title' => '财务统计',
        'children' => [
            [
                'id' => 'addons\webman\controller\ChannelStoreAgentProfitRecordController\index',
                'pid' => 'addons\webman\controller\ChannelStoreAgentProfitRecordController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-ChannelStoreAgentProfitRecordController/index',
                'title' => '收益报表',
            ],
        ]
    ],

    // ========== 充值满赠管理 ==========
    [
        'id' => 'addons\webman\controller\AgentDepositBonusActivityController-',
        'pid' => 0,
        'url' => '',
        'group' => 'agent',
        'title' => '充值满赠管理',
        'children' => [
            // 充值满赠活动列表
            [
                'id' => 'addons\webman\controller\AgentDepositBonusActivityController\index',
                'pid' => 'addons\webman\controller\AgentDepositBonusActivityController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-AgentDepositBonusActivityController/index',
                'title' => '充值满赠活动',
            ],
            // 活动表单
            [
                'id' => 'addons\webman\controller\AgentDepositBonusActivityController\form-post',
                'pid' => 'addons\webman\controller\AgentDepositBonusActivityController\index',
                'action' => 'form',
                'method' => 'post',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-AgentDepositBonusActivityController/form',
                'title' => '创建充值满赠活动',
            ],
            [
                'id' => 'addons\webman\controller\AgentDepositBonusActivityController\form-get',
                'pid' => 'addons\webman\controller\AgentDepositBonusActivityController\index',
                'action' => 'form',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-AgentDepositBonusActivityController/form',
                'title' => '充值满赠活动表单',
            ],
            [
                'id' => 'addons\webman\controller\AgentDepositBonusActivityController\form-put',
                'pid' => 'addons\webman\controller\AgentDepositBonusActivityController\index',
                'action' => 'form',
                'method' => 'put',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-AgentDepositBonusActivityController/form',
                'title' => '编辑充值满赠活动',
            ],
            // 活动详情
            [
                'id' => 'addons\webman\controller\AgentDepositBonusActivityController\detail',
                'pid' => 'addons\webman\controller\AgentDepositBonusActivityController\index',
                'action' => 'detail',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-AgentDepositBonusActivityController/detail',
                'title' => '充值满赠活动详情',
            ],
            // 充值满赠订单列表
            [
                'id' => 'addons\webman\controller\AgentDepositBonusOrderController\index',
                'pid' => 'addons\webman\controller\AgentDepositBonusActivityController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-AgentDepositBonusOrderController/index',
                'title' => '充值满赠订单',
            ],
            // 生成订单表单
            [
                'id' => 'addons\webman\controller\AgentDepositBonusOrderController\form-post',
                'pid' => 'addons\webman\controller\AgentDepositBonusOrderController\index',
                'action' => 'form',
                'method' => 'post',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-AgentDepositBonusOrderController/form',
                'title' => '生成充值满赠订单',
            ],
            [
                'id' => 'addons\webman\controller\AgentDepositBonusOrderController\form-get',
                'pid' => 'addons\webman\controller\AgentDepositBonusOrderController\index',
                'action' => 'form',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-AgentDepositBonusOrderController/form',
                'title' => '充值满赠订单表单',
            ],
            // 订单详情
            [
                'id' => 'addons\webman\controller\AgentDepositBonusOrderController\detail',
                'pid' => 'addons\webman\controller\AgentDepositBonusOrderController\index',
                'action' => 'detail',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-AgentDepositBonusOrderController/detail',
                'title' => '充值满赠订单详情',
            ],
            // 打码量任务列表
            [
                'id' => 'addons\webman\controller\AgentDepositBonusTaskController\index',
                'pid' => 'addons\webman\controller\AgentDepositBonusActivityController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-AgentDepositBonusTaskController/index',
                'title' => '打码量任务',
            ],
            // 打码量任务详情
            [
                'id' => 'addons\webman\controller\AgentDepositBonusTaskController\detail',
                'pid' => 'addons\webman\controller\AgentDepositBonusTaskController\index',
                'action' => 'detail',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-AgentDepositBonusTaskController/detail',
                'title' => '打码量任务详情',
            ],
        ]
    ],

    // ========== 彩金管理 ==========
    [
        'id' => 'addons\webman\controller\AgentLotteryController-',
        'pid' => 0,
        'url' => '',
        'group' => 'agent',
        'title' => '彩金管理',
        'children' => [
            // 彩金领取记录
            [
                'id' => 'addons\webman\controller\AgentLotteryController\index',
                'pid' => 'addons\webman\controller\AgentLotteryController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-AgentLotteryController/index',
                'title' => '彩金领取记录',
            ],
        ]
    ],

    // ========== 财务管理 ==========
    [
        'id' => 'addons\webman\controller\AgentPlayerRechargeRecordController-',
        'pid' => 0,
        'url' => '',
        'group' => 'agent',
        'title' => '财务管理',
        'children' => [
            // 充值记录
            [
                'id' => 'addons\webman\controller\AgentPlayerRechargeRecordController\index',
                'pid' => 'addons\webman\controller\AgentPlayerRechargeRecordController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-AgentPlayerRechargeRecordController/index',
                'title' => '充值记录',
            ],
            // 提现记录
            [
                'id' => 'addons\webman\controller\AgentPlayerWithdrawRecordController\index',
                'pid' => 'addons\webman\controller\AgentPlayerRechargeRecordController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-AgentPlayerWithdrawRecordController/index',
                'title' => '提现记录',
            ],
        ]
    ],

    // ========== 电子游戏管理 ==========
    [
        'id' => 'addons\webman\controller\AgentPlayGameRecordController-',
        'pid' => 0,
        'url' => '',
        'group' => 'agent',
        'title' => '电子游戏管理',
        'children' => [
            // 游戏记录
            [
                'id' => 'addons\webman\controller\AgentPlayGameRecordController\index',
                'pid' => 'addons\webman\controller\AgentPlayGameRecordController-',
                'action' => 'index',
                'method' => 'get',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-AgentPlayGameRecordController/index',
                'title' => '游戏记录',
            ],
            // 回放
            [
                'id' => 'addons\webman\controller\AgentPlayGameRecordController\replay',
                'pid' => 'addons\webman\controller\AgentPlayGameRecordController-',
                'action' => 'replay',
                'method' => 'post',
                'group' => 'agent',
                'url' => 'ex-admin/addons-webman-controller-AgentPlayGameRecordController/replay',
                'title' => '回放',
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
];