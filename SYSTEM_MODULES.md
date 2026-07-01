# gk_admin 系统模块分析

本文档详细分析gk_admin后台管理系统的各个功能模块、数据模型和业务逻辑。

## 目录

1. [核心架构](#核心架构)
2. [用户权限模块](#用户权限模块)
3. [玩家管理模块](#玩家管理模块)
4. [机台管理模块](#机台管理模块)
5. [游戏平台集成模块](#游戏平台集成模块)
6. [财务管理模块](#财务管理模块)
7. [营销活动模块](#营销活动模块)
8. [分润结算模块](#分润结算模块)
9. [彩金系统模块](#彩金系统模块)
10. [数据统计报表模块](#数据统计报表模块)

---

## 核心架构

### 三层权限架构（Multi-Tenant）

系统采用严格的三层隔离架构，每一层都有独立的管理界面和权限控制：

```
┌─────────────────────────────────────────────────────┐
│                  部门/渠道 (Department/Channel)       │
│                  - 最高管理层级                        │
│                  - 全局设置权限                        │
│                  - 查看所有下级数据                    │
└──────────────────┬──────────────────────────────────┘
                   │
        ┌──────────┴──────────┐
        │                     │
┌───────▼────────┐   ┌────────▼───────┐
│  代理 (Agent)   │   │  代理 (Agent)   │
│  - 管理多个店家  │   │  - 管理多个店家  │
│  - 查看店家数据  │   │  - 查看店家数据  │
└───────┬────────┘   └────────┬───────┘
        │                     │
   ┌────┴────┐           ┌────┴────┐
   │         │           │         │
┌──▼──┐  ┌──▼──┐     ┌──▼──┐  ┌──▼──┐
│店家1 │  │店家2 │     │店家3 │  │店家4 │
│     │  │     │     │     │  │     │
└──┬──┘  └──┬──┘     └──┬──┘  └──┬──┘
   │        │           │        │
 玩家组   玩家组       玩家组   玩家组
```

**关键特性：**
- 数据完全隔离（DataPermissions trait）
- 5种数据权限级别
- 基于department_id的租户隔离
- 每层都有专属管理员账号（AdminUser）

---

## 统计数据

### 控制器分布 (124个)

| 分类 | 数量 | 说明 |
|------|------|------|
| 渠道级控制器 (Channel*) | 33 | 渠道管理员使用 |
| 代理级控制器 (Agent*) | 11 | 代理管理员使用 |
| 店家级控制器 (Store*) | 12 | 店家管理员使用 |
| 通用控制器 | 68 | 系统级、跨层级功能 |

### 数据模型 (121个)

| 分类 | 数量 | 核心模型 |
|------|------|----------|
| 玩家相关 | 25+ | Player, PlayerExtend, PlayerBank, PlayerPromoter |
| 机台相关 | 20+ | Machine, MachineCategory, MachineLabel, MachineStrategy |
| 游戏相关 | 15+ | Game, GamePlatform, GameType, PlayGameRecord |
| 财务相关 | 20+ | PlayerRechargeRecord, PlayerWithdrawRecord, ChannelFinancialRecord |
| 权限相关 | 10+ | AdminUser, AdminRole, AdminPermission, AdminDepartment |
| 营销相关 | 10+ | Activity, DepositBonusActivity, Announcement |
| 分润相关 | 8+ | PromoterProfitRecord, ChannelProfitRecord, StoreAgentProfitRecord |
| 彩金相关 | 5+ | Lottery, PlayerLotteryRecord, MachineLotteryRecord |

---

## 1. 用户权限模块

### 1.1 模型

**核心模型：**
- `AdminUser` - 管理员账户
  - 类型：渠道管理员、代理管理员、店家管理员
  - 字段：`type`, `department_id`, `username`, `password`

- `AdminRole` - 角色
  - 数据权限类型（data_type）：0-4（全部/自定义/部门及以下/本部门/本人）
  - 内置角色：代理角色(18)、店家角色(19)

- `AdminPermission` - 权限节点
  - 节点格式：`controller\function` 或 `controller\function-method`
  - 存储在`admin_permission`表

- `AdminRoleUsers` - 用户角色关联
- `AdminRolePermission` - 角色权限关联
- `AdminDepartment` - 部门/渠道

**数据权限（DataPermissions Trait）：**

5种权限级别：
```php
0 - 全部数据权限（超级管理员）
1 - 自定义数据权限（指定部门）
2 - 本部门及以下数据权限
3 - 本部门数据权限
4 - 本人数据权限
```

### 1.2 控制器

- `AdminController` - 管理员管理
- `RoleController` - 角色管理
- `MenuController` - 菜单管理
- `DepartmentController` - 部门管理
- `ChannelAdminController` - 渠道管理员管理

### 1.3 关键功能

**权限检查流程：**
```
请求 → Permission中间件 → Admin::check() →
检查权限节点 → 检查数据权限 → 允许/拒绝
```

**缓存机制：**
- 用户权限缓存：`ADMIN_PERMISSIONS_{userId}`
- 角色信息缓存：`data_perm:role_user:{adminId}`
- 部门数据缓存：`data_perm:dept:{deptId}`
- TTL：1小时

---

## 2. 玩家管理模块

### 2.1 核心模型

**Player（玩家主表）**
```php
// 关键字段
id, uuid, name, phone, password, play_password
department_id, agent_admin_id, store_admin_id
type, player_type, status, is_test, is_promoter
recommend_id, recommend_code
currency, machine_play_num
```

**玩家类型（player_type）：**
- `PLAYER_TYPE_NORMAL (1)` - 普通玩家
- `PLAYER_TYPE_AGENT (2)` - 代理玩家
- `PLAYER_TYPE_STORE_MACHINE (3)` - 店家玩家

**关联模型：**
- `PlayerExtend` - 玩家扩展信息（地址、邮箱、备注等）
- `PlayerPlatformCash` - 玩家钱包（多平台余额）
- `PlayerBank` - 玩家银行卡
- `PlayerTag` - 玩家标签
- `PlayerPromoter` - 推广员信息（上缴比例、层级路径）
- `PlayerLoginRecord` - 登录记录
- `PlayerRegisterRecord` - 注册记录

### 2.2 控制器（按层级）

**渠道级：**
- `ChannelPlayerController` - 玩家管理
- `ChannelPlayerEditLogController` - 玩家编辑日志
- `ChannelPlayerMoneyEditLogController` - 余额变动日志
- `ChannelPlayerPromoterController` - 推广员管理
- `ChannelPlayerGameLogController` - 游戏日志
- `ChannelPlayerLotteryRecordController` - 彩金记录
- `ChannelPlayerWalletTransferController` - 钱包转账
- `ChannelPlayerActivityRecordController` - 活动记录
- `ChannelPlayerReportController` - 玩家报表

**代理级：**
- `AgentPlayerRechargeRecordController` - 充值记录（查看）
- `AgentPlayerWithdrawRecordController` - 提现记录（查看）
- `AgentPlayerGameLogController` - 游戏日志（查看）

**店家级：**
- `StorePlayerController` - 玩家管理
- `StorePlayerGameLogController` - 游戏日志
- `StorePlayerRechargeRecordController` - 充值记录
- `StorePlayerWithdrawRecordController` - 提现记录

**通用：**
- `PlayerController` - 玩家基础管理
- `PlayerPromoterController` - 推广员
- `PlayerEditLogController` - 编辑日志
- `PlayerMoneyEditLogController` - 余额日志
- `PlayerGameLogController` - 游戏日志
- `PlayerLotteryRecordController` - 彩金记录
- `PlayerWalletTransferController` - 钱包转账

### 2.3 关键功能

**1. 玩家创建流程（线下渠道）：**
```php
// 可创建三种类型
player_type = 1: 普通玩家
player_type = 2: 代理（自动设置is_promoter=1）
player_type = 3: 店家（需选择上级代理，自动设置is_promoter=1）

// 验证规则
- 店家上缴比例 >= 上级代理上缴比例
- 自动创建PlayerPromoter记录
- 设置path路径（层级关系）
```

**2. 推广员管理：**
- 上缴比例设置（ratio）
- 层级路径管理（path）
- 推广玩家统计
- 分润计算

**3. 玩家钱包操作：**
- 增加/扣除余额
- 转点功能（带/不带密码）
- 多平台钱包（PlayerPlatformCash）
- 钱包转账记录（PlayerWalletTransfer）

**4. 玩家权限控制：**
```php
status_withdraw      // 提现权限
status_transfer      // 转点权限
status_open_point    // 开赠权限
status_game_platform // 电子游戏权限
status_machine       // 实体机台权限
switch_shop          // 商城权限
```

---

## 3. 机台管理模块

### 3.1 核心模型

**Machine（机台主表）**
```php
// 基础信息
id, code, name, type, cate_id, producer_id, label_id
picture_url, domain, ip, port, currency

// 连接信息
auto_card_port, auto_card_domain  // 开分卡
identify_url  // 鱼机图像识别

// 状态字段
status, gaming, keeping, maintaining
gaming_user_id, keeping_user_id
is_live, is_use, is_special

// 分数相关
open_point, wash_point, odds_x, odds_y
min_point, max_point, control_open_point
seven_turn_point, seven_bead_point

// 控制类型
control_type: 1=双美(MEI), 2=小淞(SONG)
```

**机台类型（type）：**
- `TYPE_SLOT (1)` - 斯洛（老虎机）
- `TYPE_STEEL_BALL (2)` - 钢珠（柏青哥）
- `TYPE_FISH (3)` - 捕鱼机

**关联模型：**
- `MachineCategory` - 机台类别（影响赔率、规则）
- `MachineProducer` - 厂商
- `MachineLabel` - 标签
- `MachineStrategy` - 攻略
- `MachineMedia` - 媒体（视频流）
- `MachineKeepingLog` - 保留记录
- `MachineOperationLog` - 操作日志（MongoDB）
- `MachineReceiveLog` - 接收日志（MongoDB）
- `MachineLotteryRecord` - 彩金记录
- `MachineReport` - 报表

### 3.2 控制器（按层级）

**渠道级：**
- `ChannelMachineController` - 机台管理
- `ChannelMachineKeepingLogController` - 保留日志
- `ChannelMachineOperationLogController` - 操作日志
- `ChannelMachineReportController` - 机台报表

**店家级：**
- `StoreMachineController` - 机台管理（仅查看分配的机台）

**通用：**
- `MachineController` - 机台管理
- `MachineCategoryController` - 类别管理
- `MachineProducerController` - 厂商管理
- `MachineLabelController` - 标签管理
- `MachineStrategyController` - 攻略管理
- `MachineKeepingLogController` - 保留日志
- `MachineOperationLogController` - 操作日志
- `MachineReceiveLogController` - 接收日志
- `MachineEditLogController` - 编辑日志
- `MachineLotteryRecordController` - 彩金记录
- `MachineReportController` - 报表
- `MachineTencentPlayController` - 腾讯直播

### 3.3 关键功能

**1. 机台操作（helpers.php）：**

```php
// 开分（免扣点）
machineOpenAnyFree($player, $machine, $openScore)
- 验证上分限制
- 记录PlayerGameLog
- 发送工控指令
- 更新机台状态

// 洗分
machineWash($player, $machine, $path, $is_system, $hasLottery)
- 检查洗分限制
- 计算赠送点数
- 执行洗分指令
- 记录PlayerGameLog
- 彩金结算

// 重置机台
resetMachineTrans($machine, $player)
- 读取机台当前状态
- 计算转数/得分/压分
- 重置所有状态
- 记录日志
```

**2. 机台状态管理：**

```php
gaming = 1       // 游戏中
keeping = 1      // 保留中
maintaining = 1  // 维护中
is_opening = 1   // 开奖中
is_bonus = 1     // 拉彩中
```

**3. 工控通信（Services）：**
- `JackpotService` - 钢珠机服务
- `SlotService` - 斯洛机服务
- `FishServices` - 捕鱼机服务

**指令类型：**
```php
OPEN_ANY_POINT    // 上任意分
WASH_POINT        // 洗分
READ_SCORE        // 读取分数
MOVE_POINT_OFF    // 移分关闭
TURN_DOWN_ALL     // 下全部转数
SCORE_TO_POINT    // 得分转为点数
```

**4. 实时通信：**
- GatewayWorker协议
- 机台状态实时同步
- WebSocket推送到管理端

---

## 4. 游戏平台集成模块

### 4.1 支持的游戏平台（20+）

**电子游戏平台：**
1. RSG（Royal Slot Gaming）
2. JDB（JDB电子）
3. YZG（游戏平台）
4. MT（MT平台）
5. SP（SP电子）
6. T9 Slot（T9电子）

**真人平台：**
1. RSG Live（Royal Gaming真人）
2. DG（DG真人）
3. WM（WM真人）
4. SA（SA真人）

**体育平台：**
1. SPS（体育平台）
2. SPS_DY（体育平台单一钱包）
3. OB（体育平台）

**棋牌平台：**
1. KY（开元棋牌）
2. KYS（开元棋牌娱乐城）
3. T9（T9棋牌）

**其他平台：**
1. ATG
2. BTG
3. O8
4. KT

### 4.2 核心模型

**Game（游戏）**
```php
id, name, game_type_id, platform_id
picture_url, sort, status
```

**GamePlatform（游戏平台）**
```php
id, name, code, status, sort
platform_type  // 1=转账, 2=单一钱包
```

**GameType（游戏类型）**
```php
TYPE_SLOT = 1        // 老虎机
TYPE_STEEL_BALL = 2  // 钢珠
TYPE_FISH = 3        // 捕鱼
```

**PlayGameRecord（电子游戏记录）**
```php
player_id, platform_id, game_id
bet_amount, win_amount, valid_bet_amount
order_id, round_id, game_start_time, game_end_time
```

**PlayerDisabledGame（禁用游戏）**
```php
player_id, game_id, platform_id
// 控制玩家能玩哪些游戏
```

### 4.3 服务接口（app/service/game/）

**基类：**
- `GameServiceFactory` - 工厂类
- `GameServiceInterface` - 标准接口
- `SingleWalletServiceInterface` - 单一钱包接口

**平台实现（20个）：**
```
RSGServiceInterface.php        - RSG电子
RSGLiveServiceInterface.php    - RSG真人
DGServiceInterface.php         - DG真人
WMServiceInterface.php         - WM真人
JDBServiceInterface.php        - JDB电子
KYServiceInterface.php         - KY棋牌
SPServiceInterface.php         - SP电子
SAServiceInterface.php         - SA真人
MTServiceInterface.php         - MT平台
... 等20+个
```

**每个接口实现的方法：**
```php
// 转账钱包
lobbyLogin()           // 进入大厅
enterGame()            // 进入游戏
walletTransferIn()     // 转入
walletTransferOut()    // 转出
getBalance()           // 查询余额
getGameList()          // 游戏列表

// 单一钱包（回调接口）
balance()              // 余额查询
bet()                  // 下注
betResult()            // 结算
cancelBet()            // 取消
refund()               // 退款
```

### 4.4 控制器

**渠道级：**
- `ChannelPlayGameRecordController` - 游戏记录

**代理级：**
- `AgentPlayGameRecordController` - 游戏记录（查看）

**店家级：**
- `StorePlayGameRecordController` - 游戏记录（查看）

**通用：**
- `GameController` - 游戏管理
- `GamePlatformController` - 平台管理
- `GameTypeController` - 类型管理
- `PlayGameRecordController` - 游戏记录

### 4.5 关键功能

**1. 游戏平台代理模式：**

在`.env`中配置：
```env
GAME_PLATFORM_PROXY_HOST=10.140.0.10
GAME_PLATFORM_PROXY_PORT=8788
```

**请求流程：**
```
gk_admin → gk_work:8788/api/v1/enter-game → 外部游戏平台
```

**2. 单一钱包回调：**

游戏平台回调到gk_work：
```
游戏平台 → gk_work:8080/single-wallet/{platform}-channel/{action}
```

**3. 游戏记录同步：**
- 定时拉取游戏记录
- 存储到PlayGameRecord表
- 用于报表统计

---

## 5. 财务管理模块

### 5.1 核心模型

**充值相关：**
- `PlayerRechargeRecord` - 玩家充值记录
  - 充值方式：线下充值、在线支付、Q-talk
  - 状态：待审核、成功、失败

- `ChannelRechargeMethod` - 渠道充值方式
- `ChannelRechargeSetting` - 充值设置

**提现相关：**
- `PlayerWithdrawRecord` - 玩家提现记录
  - 状态：待审核、审核中、成功、拒绝
  - 银行卡信息
  - 手续费

**转点相关：**
- `PlayerPresentRecord` - 玩家转点记录
  - from_player_id, to_player_id
  - amount, status

**钱包相关：**
- `PlayerPlatformCash` - 多平台钱包
  - player_id, platform_id
  - money（余额）
  - PLATFORM_SELF = 0（平台钱包）

- `PlayerWalletTransfer` - 钱包转账记录
  - 平台钱包 ↔ 游戏平台

**财务统计：**
- `ChannelFinancialRecord` - 渠道财务记录
  - 日期、类型、金额、备注

- `PlayerMoneyEditLog` - 余额变动日志
  - 类型：增加/扣除
  - 操作：充值/提现/转点/活动/调整

### 5.2 控制器（按层级）

**渠道级：**
- `ChannelRechargeRecordController` - 充值记录
- `ChannelWithdrawRecordController` - 提现记录
- `ChannelPresentRecordController` - 转点记录
- `ChannelPlayerMoneyEditLogController` - 余额变动
- `ChannelFinancialRecordController` - 财务记录
- `ChannelRechargeController` - 充值管理（审核）

**代理级：**
- `AgentPlayerRechargeRecordController` - 充值记录（查看）
- `AgentPlayerWithdrawRecordController` - 提现记录（查看）

**店家级：**
- `StorePlayerRechargeRecordController` - 充值记录
- `StorePlayerWithdrawRecordController` - 提现记录

**通用：**
- `RechargeRecordController` - 充值记录
- `WithdrawRecordController` - 提现记录
- `PresentRecordController` - 转点记录
- `PlayerMoneyEditLogController` - 余额变动

### 5.3 关键功能

**1. 充值流程：**
```
玩家申请 → 生成充值单 → 管理员审核 → 到账（增加余额）
                                    ↓
                            记录PlayerMoneyEditLog
                            记录PlayerRechargeRecord
```

**2. 提现流程：**
```
玩家申请 → 验证支付密码 → 扣除余额 → 管理员审核 → 打款
                                              ↓
                                       记录PlayerWithdrawRecord
```

**3. 转点流程：**
```
玩家A → 扣除余额 → 验证密码 → 玩家B增加余额
           ↓
    记录PlayerPresentRecord
    记录双方PlayerMoneyEditLog
```

**4. 钱包转账（游戏平台）：**
```
平台钱包 → 游戏平台钱包（转出）
游戏平台钱包 → 平台钱包（转入）

记录PlayerWalletTransfer
调用gk_work代理API
```

---

## 6. 营销活动模块

### 6.1 核心模型

**活动系统：**
- `Activity` - 活动主表
  - 活动类型、名称、时间范围
  - 状态：进行中/已结束

- `ActivityContent` - 活动内容
  - 富文本内容、图片

- `ActivityPhase` - 活动阶段
  - 多阶段活动支持

- `PlayerActivityRecord` - 玩家活动记录
  - 参与记录、奖励发放

**存款优惠（Deposit Bonus）：**
- `DepositBonusActivity` - 存款优惠活动
  - 充值优惠配置
  - 返水比例

- `DepositBonusTier` - 优惠档位
  - 充值金额范围
  - 赠送比例

- `DepositBonusOrder` - 优惠订单
  - player_id, activity_id
  - 充值金额、赠送金额
  - 状态：待审核/已发放/已拒绝

- `DepositBonusStatistics` - 优惠统计

- `DepositBonusBetDetail` - 打码详情
  - 流水要求验证

- `PlayerBonusTask` - 玩家优惠任务

**公告系统：**
- `Announcement` - 公告
  - 标题、内容、类型
  - 置顶、滚动

**轮播图/跑马灯：**
- `Slider` - 轮播图
- `Marquee` - 跑马灯

### 6.2 控制器（按层级）

**渠道级：**
- `ChannelActivityController` - 活动管理
- `ChannelDepositBonusActivityController` - 存款优惠
- `ChannelDepositBonusOrderController` - 优惠订单
- `ChannelDepositBonusStatisticsController` - 优惠统计
- `ChannelDepositBonusBetDetailController` - 打码详情
- `ChannelAnnouncementController` - 公告管理
- `ChannelSliderController` - 轮播图
- `ChannelMarqueeController` - 跑马灯
- `ChannelPlayerActivityRecordController` - 活动记录

**代理级：**
- `AgentDepositBonusActivityController` - 存款优惠（查看）
- `AgentDepositBonusOrderController` - 优惠订单（查看）
- `AgentDepositBonusTaskController` - 优惠任务（查看）

**店家级：**
- `StoreDepositBonusActivityController` - 存款优惠（查看）
- `StoreDepositBonusOrderController` - 优惠订单（查看）
- `StoreDepositBonusTaskController` - 优惠任务（查看）

**通用：**
- `ActivityController` - 活动管理
- `DepositBonusActivityController` - 存款优惠
- `DepositBonusQrcodeController` - 优惠二维码
- `AnnouncementController` - 公告
- `SliderController` - 轮播图
- `PlayerActivityRecordController` - 活动记录

### 6.3 关键功能

**1. 存款优惠流程：**
```
创建活动 → 设置档位 → 玩家充值 → 自动匹配档位 → 生成订单
                                                  ↓
                                          验证打码要求
                                                  ↓
                                          发放优惠金额
```

**2. 活动参与记录：**
- 自动记录玩家参与
- 奖励自动发放
- 打码要求验证

**3. 公告推送：**
- 全站公告
- 渠道公告
- 弹窗公告

---

## 7. 分润结算模块

### 7.1 核心模型

**推广员分润：**
- `PromoterProfitRecord` - 推广员分润记录
  - player_id（推广员）
  - subordinate_player_id（下级玩家）
  - amount（分润金额）
  - ratio（分润比例）

- `PromoterProfitSettlementRecord` - 推广员结算记录
  - 批量结算记录
  - 结算时间、金额

- `PromoterProfitGameRecord` - 推广员游戏分润
  - 基于下级游戏输赢的分润

**渠道分润：**
- `ChannelProfitRecord` - 渠道分润记录
- `ChannelProfitSettlementRecord` - 渠道结算记录
- `ChannelChannelProfitRecord` - 渠道间分润

**店家代理分润：**
- `StoreAgentProfitRecord` - 店家/代理分润记录
  - agent_id, store_id
  - 分润金额、比例

**全民代理：**
- `NationalPromoter` - 全民代理
  - 等级、打码量
  - 待结算金额

- `NationalProfitRecord` - 全民代理分润记录
- `LevelList` - 等级配置
  - 打码要求、返佣比例

### 7.2 控制器（按层级）

**渠道级：**
- `ChannelAgentPromoterController` - 代理/店家管理（含分润设置）
- `ChannelProfitRecordController` - 分润记录
- `ChannelChannelProfitRecordController` - 渠道分润
- `ChannelStoreAgentProfitRecordController` - 店家代理分润
- `ChannelPromoterProfitGameRecordController` - 游戏分润
- `ChannelNationalPromoterController` - 全民代理
- `ChannelNationalPromoterReportController` - 全民代理报表

**代理级：**
- `AgentPromoterController` - 推广员管理（含分润设置）
- `AgentStoreProfitReportController` - 店家分润报表

**通用：**
- `PlayerPromoterController` - 推广员管理
- `ProfitRecordController` - 分润记录
- `NationalPromoterController` - 全民代理
- `NationalPromoterReportController` - 全民代理报表

### 7.3 关键功能

**1. 分润计算模式（config/app.php）：**
```php
'profit' => 'task'  // task=每日任务结算, event=实时结算
```

**2. 推广员层级分润：**
```
玩家输赢 → 计算上级推广员分润
         ↓
    按层级路径分配
         ↓
    记录PromoterProfitRecord
```

**3. 上缴比例验证：**
```php
// 代理上缴比例 < 店家上缴比例
if ($storeRatio < $agentRatio) {
    return message_error('店家上缴比例不能小于代理');
}
```

**4. 结算流程：**
```
每日定时任务 → 统计前一天数据 → 计算分润 → 生成结算单 → 发放到账
```

**5. 全民代理升级：**
```
累计打码量 → 达到等级要求 → 自动升级 → 提高返佣比例
```

---

## 8. 彩金系统模块

### 8.1 核心模型

**彩金配置：**
- `Lottery` - 彩金池配置
  - department_id
  - pool_ratio（入池比例）
  - win_probability（中奖概率）
  - minimum_amount（保底金额）
  - distribution_ratio（派发比例）

- `GameLottery` - 游戏彩金配置
  - game_id, lottery_id
  - 游戏与彩金池关联

**彩金记录：**
- `PlayerLotteryRecord` - 玩家彩金记录
  - player_id, machine_id
  - amount（彩金金额）
  - type（类型：机台彩金/游戏彩金）
  - status（状态：待发放/已发放/已完成）

- `MachineLotteryRecord` - 机台彩金记录
  - machine_id
  - 机台拉彩记录

### 8.2 控制器（按层级）

**渠道级：**
- `ChannelPlayerLotteryRecordController` - 彩金记录

**代理级：**
- `AgentLotteryController` - 彩金管理（查看）

**店家级：**
- `StoreLotteryController` - 彩金管理（查看）

**通用：**
- `LotteryController` - 彩金池配置
- `GameLotteryController` - 游戏彩金
- `PlayerLotteryRecordController` - 彩金记录
- `MachineLotteryRecordController` - 机台彩金记录
- `LotteryAddLogController` - 彩金添加日志
- `OnlinePlayerLotteryController` - 在线玩家彩金

### 8.3 关键功能

**1. 彩金累积：**
```
玩家投注 → 按入池比例累积 → 彩金池金额增加
```

**2. 彩金触发：**
```
机台游戏中 → 触发彩金条件 → 按中奖概率计算 → 发放彩金
                                            ↓
                                    扣除彩金池金额
                                    增加玩家余额
                                    记录PlayerLotteryRecord
```

**3. 保底机制：**
```php
if ($poolAmount < $minimumAmount) {
    // 彩金池低于保底金额，补充到保底金额
    $poolAmount = $minimumAmount;
}
```

**4. 派发比例：**
```php
// 实际派发金额 = 彩金池 × 派发比例
$actualAmount = $poolAmount * ($distributionRatio / 100);
```

---

## 9. 数据统计报表模块

### 9.1 核心模型

**报表相关：**
- `MachineReport` - 机台报表
  - 日期、机台、营收、成本

- `PlayerReport` - 玩家报表
  - 日期、玩家、充值、提现、输赢

- `ChannelReport` - 渠道报表

### 9.2 控制器（按层级）

**渠道级：**
- `ChannelMachineReportController` - 机台报表
- `ChannelPlayerReportController` - 玩家报表
- `ChannelNationalPromoterReportController` - 全民代理报表

**代理级：**
- `AgentStoreProfitReportController` - 店家分润报表

**通用：**
- `MachineReportController` - 机台报表
- `PlayerReportController` - 玩家报表
- `NationalPromoterReportController` - 全民代理报表

### 9.3 关键统计

**1. 机台统计：**
- 营业额
- 开分/洗分
- 彩金支出
- 净利润

**2. 玩家统计：**
- 充值金额
- 提现金额
- 游戏输赢
- 活跃度

**3. 财务统计：**
- 日/周/月报表
- 充值提现统计
- 分润统计
- 活动成本

---

## 10. 其他核心模块

### 10.1 自动交班模块

**模型：**
- `StoreAutoShiftConfig` - 自动交班配置
  - 早班/中班/晚班时间设置
  - 自动结算开关

- `StoreAgentShiftHandoverRecord` - 交班记录
  - 班次信息、设备数据
  - 营收统计

- `StoreShiftDeviceDetail` - 交班设备明细
  - 每台设备的详细数据

**控制器：**
- `ChannelAutoShiftController` - 自动交班配置
- `StoreShiftHandoverRecordController` - 交班记录

**功能：**
- 定时自动交班
- 设备数据快照
- 营收自动结算

### 10.2 设备管理模块

**模型：**
- `Device` - 设备（客户端设备）
  - device_id, device_name
  - 设备类型、状态

- `DeviceAccessLog` - 设备访问日志

**控制器：**
- `ChannelDeviceController` - 设备管理
- `ChannelDeviceAccessLogController` - 访问日志

### 10.3 系统配置模块

**模型：**
- `AdminConfig` - 系统配置
  - 键值对存储
  - 分组管理

- `Currency` - 货币设置
- `Bank` - 银行配置

**控制器：**
- `ConfigController` - 系统配置
- `SystemSettingController` - 系统设置
- `CurrencyController` - 货币管理
- `BankController` - 银行管理

### 10.4 内容管理模块

**模型：**
- `AdminPost` - 帖子
- `AdminFileAttachment` - 文件附件
- `AdminFileAttachmentCate` - 附件分类

**控制器：**
- `PostController` - 帖子管理
- `ChannelPostController` - 渠道帖子
- `AttachmentController` - 附件管理

---

## 数据流转图

### 玩家游戏完整流程

```
┌─────────────────────────────────────────────────────────┐
│                    玩家游戏完整流程                        │
└─────────────────────────────────────────────────────────┘

1. 玩家登录（gk_api）
   ↓
2. 选择机台（实体机台 OR 电子游戏）
   ↓
   ├─→ 实体机台流程：
   │   ├─ 保留机台（MachineKeepingLog）
   │   ├─ 开分（machineOpenAnyFree）
   │   │   └─→ 记录PlayerGameLog
   │   ├─ 游戏过程（GatewayWorker实时通信）
   │   │   └─→ 机台状态更新（MachineOperationLog）
   │   ├─ 触发彩金（可选）
   │   │   └─→ PlayerLotteryRecord
   │   └─ 洗分（machineWash）
   │       ├─→ 记录PlayerGameLog
   │       └─→ 更新余额（PlayerPlatformCash）
   │
   └─→ 电子游戏流程：
       ├─ 钱包转出到游戏平台
       │   └─→ PlayerWalletTransfer
       ├─ 通过gk_work代理进入游戏
       │   └─→ 调用GameServiceInterface
       ├─ 游戏平台回调（单一钱包）
       │   └─→ gk_work接收bet/result
       ├─ 游戏结束
       └─ 钱包转入回平台
           └─→ PlayerWalletTransfer

3. 后台结算（每日定时任务 - gk_work）
   ├─→ 推广员分润（PromoterProfitRecord）
   ├─→ 渠道分润（ChannelProfitRecord）
   ├─→ 全民代理返佣（NationalProfitRecord）
   └─→ 更新报表数据（MachineReport, PlayerReport）

4. WebSocket推送（实时）
   ├─→ 机台状态更新（gk_api Push → 管理端）
   ├─→ 彩金通知（gk_api Push → 玩家端）
   └─→ 余额变动（gk_api Push → 玩家端）
```

---

## 模块依赖关系

```
┌─────────────────────────────────────────────────────────┐
│                     模块依赖关系图                         │
└─────────────────────────────────────────────────────────┘

用户权限模块 (AdminUser, AdminRole)
    ↓ 控制访问权限
    ├─→ 玩家管理模块 (Player)
    │       ↓
    │       ├─→ 机台管理模块 (Machine)
    │       │       ↓
    │       │       └─→ 机台操作 (开分/洗分)
    │       │               ↓
    │       │               └─→ 彩金系统 (Lottery)
    │       │
    │       ├─→ 游戏平台集成 (GamePlatform)
    │       │       ↓
    │       │       └─→ 钱包转账 (PlayerWalletTransfer)
    │       │
    │       └─→ 财务管理 (Recharge/Withdraw)
    │               ↓
    │               └─→ 余额变动 (PlayerMoneyEditLog)
    │
    ├─→ 营销活动模块 (Activity, DepositBonus)
    │       ↓
    │       └─→ 玩家参与 (PlayerActivityRecord)
    │
    └─→ 分润结算模块 (PromoterProfit)
            ↓
            ├─→ 推广员层级 (PlayerPromoter)
            ├─→ 代理店家 (StoreAgentProfit)
            └─→ 全民代理 (NationalPromoter)

所有模块共享：
    - 数据权限系统 (DataPermissions)
    - 多语言系统 (admin_trans)
    - 缓存系统 (Redis)
    - 日志系统 (MongoDB)
```

---

## 开发建议

### 1. 新增功能模块

**步骤：**
1. 创建模型（`addons/webman/model/`）
2. 创建控制器（`addons/webman/controller/`）
3. 添加翻译（`addons/webman/lang/*/`）
4. 创建数据库迁移（`database/phinx_migrations/`）
5. 添加权限节点（通过菜单管理）
6. 测试数据权限

### 2. 修改现有模块

**注意事项：**
- 检查是否影响三个项目（gk_admin, gk_api, gk_work）
- 更新相关翻译文件（4种语言）
- 测试跨层级权限（渠道/代理/店家）
- 验证数据权限隔离
- 检查缓存是否需要清除

### 3. 性能优化点

**关键优化：**
1. 数据权限查询优化（使用索引）
2. 权限节点缓存（Redis 1小时）
3. Grid查询优化（select指定字段，避免N+1）
4. 大数据表分页（机台日志、游戏记录）
5. MongoDB日志异步写入

### 4. 安全注意事项

**重点：**
1. 所有用户输入必须验证
2. 数据权限严格检查department_id
3. 敏感操作记录日志
4. 资金操作使用事务
5. 防止SQL注入（使用Eloquent ORM）

---

## 总结

gk_admin系统是一个功能完整、架构清晰的多租户游戏管理平台，包含：

✅ **124个控制器** - 覆盖渠道、代理、店家三层级
✅ **121个数据模型** - 完整的业务数据结构
✅ **20+游戏平台** - 转账钱包和单一钱包双模式
✅ **5级数据权限** - 严格的多租户隔离
✅ **完整的财务系统** - 充值、提现、转点、分润
✅ **实时通信** - GatewayWorker + WebSocket
✅ **多语言支持** - zh-CN, en, jp, zh-TW

**核心竞争力：**
- 严格的三层权限架构
- 完善的机台管理系统
- 灵活的游戏平台集成
- 强大的分润结算能力
- 实时的数据推送机制
