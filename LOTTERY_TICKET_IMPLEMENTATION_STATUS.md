# 摸奖券系统 - 实施进度

## ✅ 已完成工作

### 第一步：渠道配置增加摸奖券开关

#### 1.1 数据库迁移
- ✅ 文件：`D:\gk_api\db\migrations\20260602061101_add_lottery_ticket_enabled_to_channel_table.php`
- ✅ 字段：`lottery_ticket_enabled` TINYINT(1) DEFAULT 0
- ✅ 位置：channel表，位于lottery_status字段之后

#### 1.2 后端模型更新
- ✅ `D:\gk_admin\addons\webman\model\Channel.php` (Line 49)
  - PHPDoc注释：`@property int lottery_ticket_enabled 摸奖券功能开关(0:禁用,1:启用)`
- ✅ `D:\gk_api\app\model\Channel.php` (Line 49)
  - 同步PHPDoc注释

#### 1.3 控制器更新
- ✅ `D:\gk_admin\addons\webman\controller\ChannelController.php`
  - Line 619: 新建渠道时设置status=1
  - Line 637: 保存lottery_ticket_enabled字段
  - Line 784: 编辑渠道时保存lottery_ticket_enabled字段

#### 1.4 API接口更新
- ✅ `D:\gk_api\app\api\controller\v1\IndexController.php`
  - Line 852: 添加status字段防御性检查
  - Lines 926-927: 返回lottery_ticket_enabled给客户端

#### 1.5 多语言翻译
- ✅ `D:\gk_admin\addons\webman\lang\zh-TW\channel.php` - '摸獎券功能'
- ✅ `D:\gk_admin\addons\webman\lang\zh-CN\channel.php` - '摸奖券功能'
- ✅ `D:\gk_admin\addons\webman\lang\en\channel.php` - 'Lottery Ticket Function'
- ✅ `D:\gk_admin\addons\webman\lang\jp\channel.php` - '抽選券機能'

#### 1.6 Bug修复
- ✅ 问题：新创建的渠道status字段不存在
- ✅ 修复：
  - ChannelController::save() 设置 `$channel->status = 1;`
  - IndexController::getChannel() 添加 `($channel['status'] ?? 1)` 防御性检查
- ✅ 文档：`BUGFIX_CHANNEL_STATUS.md`

---

### 第二步：渠道后台增加摸奖券管理菜单

#### 2.1 数据库设计（4+1张表）

**核心表：**

1. ✅ **lottery_ticket_activity** - 活动表
   - 文件：`20260602150000_create_lottery_ticket_activity_table.php`
   - 状态：待开始(0)、进行中(1)、开奖中(2)、已结束(3)
   - 配置：bet_config(JSON)、live_stream_url

2. ✅ **lottery_ticket** - 摸奖券表
   - 文件：`20260602150001_create_lottery_ticket_table.php`
   - 唯一编号：ticket_no (6位数字)
   - 来源：bet/recharge/manual
   - 状态：未使用(0)、已使用(1)、已过期(2)

3. ✅ **lottery_ticket_record** - 中奖记录表
   - 文件：`20260602150002_create_lottery_ticket_record_table.php`
   - 奖品类型：cash/bonus/item/empty
   - 发放状态：pending/granted/failed

4. ✅ **lottery_ticket_prize_level** - 中奖等级配置表 ⭐ 新增
   - 文件：`20260602150003_create_lottery_ticket_prize_level_table.php`
   - 支持最多10个奖金段
   - 奖品类型：cash/bonus/item/points
   - 中奖概率配置
   - 唯一索引：(activity_id, level_rank)

**统计表：**

5. ✅ **player_bet_accumulate** - 打码累计表
   - 统计玩家打码量用于发放摸奖券
   - 使用Redis缓存 + 定时同步MySQL

#### 2.2 数据模型类

- ✅ `D:\gk_admin\addons\webman\model\LotteryTicketActivity.php`
  - 4种状态常量
  - 关系：channel()、tickets()、records()、prizeLevels()
  - 方法：getStatusText()、getUsageRateAttribute()

- ✅ `D:\gk_admin\addons\webman\model\LotteryTicket.php`
  - 3种状态、3种来源
  - 唯一券号生成：generateTicketNo()
  - 关系：activity()、player()

- ✅ `D:\gk_admin\addons\webman\model\LotteryTicketRecord.php`
  - 4种奖品类型、3种状态
  - 关系：activity()、player()、ticket()

- ✅ `D:\gk_admin\addons\webman\model\LotteryTicketPrizeLevel.php` ⭐ 新增
  - 最大10个等级：MAX_LEVELS = 10
  - 奖品类型：PRIZE_TYPE_CASH/BONUS/ITEM/POINTS
  - 验证方法：validateActivityPrizeLevels()
  - 等级名称选项：getLevelNameOptions() (特等奖~九等奖)
  - 关系：activity()

#### 2.3 多语言翻译

- ✅ `D:\gk_admin\addons\webman\lang\zh-TW\lottery_ticket.php` (繁体中文 - 完整)
  - 菜单：摸獎券管理、進行中的活動、歷史活動記錄、中獎記錄
  - 字段：活動名稱、開始時間、結束時間、獎品配置等
  - 状态：未開始、進行中、已結束、已關閉
  - 奖品类型：現金、紅利、實物、積分
  - 中奖等级：特等獎~九等獎 ⭐ 新增
  - 错误信息：最多10个等级、概率超过100%等 ⭐ 新增

- ⚠️ **待完成**：zh-CN、en、jp翻译文件（可从zh-TW复制后翻译）

#### 2.4 菜单权限控制 ⭐ 新增

**中间件：**
- ✅ `D:\gk_admin\addons\webman\middleware\LotteryTicketFeatureCheck.php`
  - 检查channel.lottery_ticket_enabled字段
  - 未开启返回403错误
  - 错误信息：`lottery_ticket.error.feature_not_enabled`

**使用方式：**
```php
/**
 * @middleware LotteryTicketFeatureCheck
 */
class ChannelLotteryTicketActivityController
{
    // 所有方法自动受保护
}
```

**设计文档：**
- ✅ `LOTTERY_TICKET_ADDITIONAL_FEATURES.md`
  - 中奖等级配置详细说明
  - 菜单权限控制三种方案
  - UI设计示例
  - API接口规范

---

## 📋 待完成工作

### 优先级1：核心功能实现

#### 控制器开发
- ⏳ `ChannelLotteryTicketActivityController.php` - 活动管理
  - index() - 活动列表Grid
  - save() - 创建/编辑活动Form
  - dashboard() - 进行中的活动面板
  - savePrizeLevel() - 保存中奖等级 ⭐ 新增
  - deletePrizeLevel() - 删除中奖等级 ⭐ 新增
  - **应用中间件**：@middleware LotteryTicketFeatureCheck

- ⏳ `ChannelLotteryTicketRecordController.php` - 中奖记录
  - index() - 中奖记录列表Grid
  - detail() - 中奖详情
  - inputWinners() - 录入中奖号码
  - **应用中间件**：@middleware LotteryTicketFeatureCheck

#### 菜单权限配置
- ⏳ `config/channel_node.php` - 添加菜单配置
  ```php
  [
      'id' => 'addons\webman\controller\ChannelLotteryTicketActivityController-',
      'pid' => 0,
      'group' => 'channel',
      'title' => admin_trans('lottery_ticket.menu.main'),
      'children' => [
          ['action' => 'dashboard', 'title' => '进行中的活动'],
          ['action' => 'index', 'title' => '历史活动记录'],
          // 中奖记录控制器...
      ],
  ]
  ```

#### 多语言补充
- ⏳ `addons/webman/lang/zh-CN/lottery_ticket.php` - 简体中文
- ⏳ `addons/webman/lang/en/lottery_ticket.php` - 英文
- ⏳ `addons/webman/lang/jp/lottery_ticket.php` - 日文

---

### 优先级2：后台进程

所有进程运行在 **gk_admin** 项目中（不在gk_work）

#### 进程1：打码累计进程
- ⏳ `D:\gk_admin\process\LotteryTicketBetAccumulateProcess.php`
- 功能：从Redis读取玩家打码，累计到阈值后发放摸奖券
- 频率：每秒执行
- Redis结构：HASH `lottery_bet_acc:{player_id}`

#### 进程2：活动状态切换进程
- ⏳ `D:\gk_admin\process\LotteryTicketStatusSwitchProcess.php`
- 功能：根据时间自动切换活动状态（待开始→进行中→开奖中→已结束）
- 频率：每分钟执行

#### 进程3：中奖推送进程
- ⏳ `D:\gk_admin\process\LotteryTicketWinnerNotifyProcess.php`
- 功能：录入中奖号码后，推送WebSocket消息给中奖玩家
- 触发：管理员录入中奖号码后

#### 进程4：跑马灯推送进程
- ⏳ `D:\gk_admin\process\LotteryTicketMarqueePushProcess.php`
- 功能：推送中奖信息到客户端跑马灯
- 频率：实时推送

#### 进程5：数据同步进程
- ⏳ `D:\gk_admin\process\LotteryTicketDataSyncProcess.php`
- 功能：Redis缓存数据同步到MySQL
- 频率：每5分钟执行

#### 配置文件
- ⏳ `config/process.php` - 注册所有进程
  ```php
  'lottery_ticket_bet_accumulate' => [
      'handler' => \process\LotteryTicketBetAccumulateProcess::class,
  ],
  // ... 其他进程
  ```

---

### 优先级3：API接口（gk_api项目）

#### 客户端API
- ⏳ `D:\gk_api\app\api\controller\v1\LotteryTicketController.php`
  - getActiveActivities() - 获取进行中的活动
  - getMyTickets() - 获取我的摸奖券
  - getDrawLiveStream() - 获取直播流地址
  - getWinnerList() - 获取中奖名单

#### 模型同步
- ⏳ 将4个模型类从gk_admin复制到gk_api
  - `app/model/LotteryTicketActivity.php`
  - `app/model/LotteryTicket.php`
  - `app/model/LotteryTicketRecord.php`
  - `app/model/LotteryTicketPrizeLevel.php` ⭐ 新增

---

### 优先级4：数据库迁移执行

#### 执行迁移
```bash
cd D:\gk_api
vendor/bin/phinx migrate

# 预期创建5张新表：
# - lottery_ticket_activity
# - lottery_ticket
# - lottery_ticket_record
# - lottery_ticket_prize_level ⭐ 新增
# - player_bet_accumulate
```

#### 验证迁移
```sql
-- 检查表是否创建成功
SHOW TABLES LIKE 'lottery_ticket%';
SHOW TABLES LIKE 'player_bet_accumulate';

-- 检查channel表字段
DESC channel;
-- 应包含：lottery_ticket_enabled TINYINT(1)
```

---

## 📊 进度统计

### 数据库
- ✅ 已完成：5/5 迁移文件（含新增的prize_level表）
- ⏳ 待执行：迁移执行

### 模型
- ✅ 已完成：4/4 模型类（含LotteryTicketPrizeLevel）
- ⏳ 待同步：gk_api项目模型

### 控制器
- ✅ 已完成：1/3 (ChannelController更新)
- ⏳ 待开发：2/3 (Activity、Record控制器)

### 进程
- ⏳ 待开发：0/5

### 翻译
- ✅ 已完成：1/4 (zh-TW完整，含新增内容)
- ⏳ 待补充：3/4 (zh-CN、en、jp)

### 中间件
- ✅ 已完成：1/1 (LotteryTicketFeatureCheck)

### 文档
- ✅ 主设计文档：LOTTERY_TICKET_SYSTEM_DESIGN.md (85KB)
- ✅ 新增功能文档：LOTTERY_TICKET_ADDITIONAL_FEATURES.md ⭐
- ✅ 数据库迁移指南：DATABASE_MIGRATION_GUIDE.md
- ✅ Bug修复文档：BUGFIX_CHANNEL_STATUS.md
- ✅ 进度文档：LOTTERY_TICKET_IMPLEMENTATION_STATUS.md (本文档)

---

## 🎯 下一步行动建议

### 立即可执行
1. **执行数据库迁移**（5分钟）
   ```bash
   cd D:\gk_api
   vendor/bin/phinx migrate
   ```

2. **补充多语言翻译**（30分钟）
   - 复制zh-TW到zh-CN并转换为简体
   - 翻译en、jp

3. **创建活动管理控制器**（2小时）
   - ChannelLotteryTicketActivityController
   - 实现index()、save()、dashboard()
   - 实现savePrizeLevel()、deletePrizeLevel() ⭐ 新增
   - 应用LotteryTicketFeatureCheck中间件

4. **创建中奖记录控制器**（1小时）
   - ChannelLotteryTicketRecordController
   - 实现index()、detail()、inputWinners()
   - 应用LotteryTicketFeatureCheck中间件

5. **添加菜单权限配置**（30分钟）
   - 编辑config/channel_node.php
   - 配置3个子菜单

### 后续任务
6. **开发5个后台进程**（1-2天）
7. **gk_api接口开发**（1天）
8. **模型同步到gk_api**（30分钟）
9. **集成测试**（1天）
10. **性能优化**（1天）

---

## ⚠️ 注意事项

### 新增功能相关
1. **中奖等级配置**
   - 每个活动最多10个等级
   - 中奖概率总和不能超过100%
   - 等级排名(level_rank)在同一活动内必须唯一
   - 支持4种奖品类型：现金、红利、实物、积分

2. **菜单权限控制**
   - 所有摸奖券控制器必须应用LotteryTicketFeatureCheck中间件
   - 前端应根据channel.lottery_ticket_enabled隐藏菜单
   - 错误提示使用翻译key：`lottery_ticket.error.feature_not_enabled`

### 原有注意事项
3. **数据库迁移统一管理**
   - 所有迁移文件在gk_api/db/migrations
   - 表名无yjb_前缀
   - 三个项目共享同一数据库

4. **进程分布**
   - 摸奖券进程全部在gk_admin
   - 不在gk_work中运行（避免影响iGaming系统）

5. **实时性保证**
   - Redis原子操作（HINCRBY）
   - WebSocket推送（gk_api:3131/3232）
   - 每秒/每分钟执行的进程

6. **多语言支持**
   - 默认使用zh-TW（繁体中文）
   - 所有用户可见文本必须使用admin_trans()
   - 4个语言文件必须保持同步

---

## 📚 参考文档

- [主设计文档](LOTTERY_TICKET_SYSTEM_DESIGN.md) - 完整的系统设计、业务流程、进程代码
- [新增功能设计](LOTTERY_TICKET_ADDITIONAL_FEATURES.md) - 中奖等级配置、菜单权限控制 ⭐
- [数据库迁移指南](DATABASE_MIGRATION_GUIDE.md) - 迁移规范、模板、最佳实践
- [Bug修复记录](BUGFIX_CHANNEL_STATUS.md) - channel.status字段缺失问题
- [项目说明](CLAUDE.md) - 三项目架构、ExAdmin框架、权限系统

---

**最后更新时间：** 2026-06-02 15:30:00

**新增功能完成状态：**
- ✅ 中奖等级配置数据库表
- ✅ LotteryTicketPrizeLevel模型类
- ✅ 等级相关翻译（zh-TW）
- ✅ 菜单权限控制中间件
- ✅ 新增功能设计文档
