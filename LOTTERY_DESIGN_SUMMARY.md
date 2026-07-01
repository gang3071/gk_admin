# 摸奖券系统设计概要

**版本：** v1.0 | **日期：** 2026-06-05 | **设计：** Claude

---

## 📋 需求总结

| 需求点 | 说明 |
|-------|------|
| 渠道开关 | 每个渠道可独立启用/关闭摸奖券功能 |
| 活动管理 | 渠道后台创建活动，设置时间和规则 |
| 活动状态 | 预热期 → 进行中 → 开奖中 → 已结束（自动流转） |
| VIP规则 | 不同VIP等级设置不同打码量获取摸奖券 |
| 自动发放 | 打码达标自动发放，延迟<10秒 |
| 开奖直播 | 开奖期间支持直播流URL |
| 中奖录入 | 后台手动录入中奖号码（支持批量导入） |
| 实时通知 | WebSocket推送中奖消息 + 跑马灯展示 |
| 进程部署 | 所有后台任务部署在gk_admin（避免gk_work负载） |

---

## 🏗️ 数据库设计

### 8张核心表

#### 1. `channel` 扩展
```sql
ALTER TABLE channel 
ADD COLUMN lottery_ticket_enabled TINYINT(1) NOT NULL DEFAULT 0 
COMMENT '摸奖券功能开关（0=关闭，1=开启）';
```

#### 2. `lottery_activity` - 摸奖活动表
| 字段 | 类型 | 说明 |
|------|------|------|
| id | BIGINT | 主键 |
| department_id | INT | 渠道ID |
| name | VARCHAR(100) | 活动名称 |
| preheat_start_at | DATETIME | 预热开始时间 |
| activity_start_at | DATETIME | 活动开始时间 |
| activity_end_at | DATETIME | 活动结束时间 |
| draw_start_at | DATETIME | 开奖开始时间 |
| draw_end_at | DATETIME | 开奖结束时间 |
| status | TINYINT | 0待开始/1预热中/2进行中/3开奖中/4已结束 |
| live_stream_url | VARCHAR(500) | 直播流URL |
| total_tickets_issued | INT | 已发放摸奖券总数 |

#### 3. `lottery_activity_bet_rule` - 活动打码规则表
| 字段 | 类型 | 说明 |
|------|------|------|
| id | BIGINT | 主键 |
| activity_id | BIGINT | 活动ID |
| vip_level_id | INT | VIP等级ID（0=所有等级） |
| bet_amount_required | DECIMAL(15,2) | 所需打码量 |
| tickets_awarded | INT | 奖励摸奖券数量 |
| max_tickets_per_player | INT | 每人最多获得（NULL=不限制） |

#### 4. `lottery_ticket` - 摸奖券表
| 字段 | 类型 | 说明 |
|------|------|------|
| id | BIGINT | 主键 |
| ticket_no | VARCHAR(32) | 摸奖券编号（唯一） |
| activity_id | BIGINT | 活动ID |
| player_id | INT | 玩家ID |
| vip_level_id | INT | 获得时的VIP等级 |
| issue_type | TINYINT | 发放类型：1打码达标/2手动/3赠送 |
| is_winner | TINYINT | 是否中奖 |
| won_at | DATETIME | 中奖时间 |
| status | TINYINT | 0已作废/1有效/2已使用 |

#### 5. `lottery_winner` - 中奖记录表
| 字段 | 类型 | 说明 |
|------|------|------|
| id | BIGINT | 主键 |
| activity_id | BIGINT | 活动ID |
| ticket_id | BIGINT | 中奖摸奖券ID |
| ticket_no | VARCHAR(32) | 摸奖券编号 |
| player_id | INT | 中奖玩家ID |
| prize_name | VARCHAR(100) | 奖品名称 |
| prize_type | TINYINT | 1现金/2积分/3实物 |
| prize_value | DECIMAL(15,2) | 奖品价值 |
| is_notified | TINYINT | 是否已通知 |

#### 6. `player_bet_counter` - 玩家打码计数器表
| 字段 | 类型 | 说明 |
|------|------|------|
| id | BIGINT | 主键 |
| activity_id | BIGINT | 活动ID |
| player_id | INT | 玩家ID |
| total_bet_amount | DECIMAL(15,2) | 累计打码量 |
| last_bet_amount | DECIMAL(15,2) | 上次发券时的打码量 |
| tickets_issued | INT | 已发放摸奖券数 |

#### 7. `lottery_ticket_issue_log` - 摸奖券发放日志表
发放历史追溯，成功/失败记录。

#### 8. `lottery_marquee_message` - 跑马灯消息表
存储跑马灯消息，支持优先级和时间范围控制。

---

## 🔄 核心业务流程

### 1. 活动状态自动流转

```
定时任务（每5分钟检查）
    ↓
检查活动时间节点
    ↓
更新状态（0→1→2→3→4）
    ↓
维护Redis进行中活动集合
```

**进程：** `LotteryActivityStatusProcess` (gk_admin)

### 2. 摸奖券发放流程

```
玩家游戏（gk_work）
    ↓
游戏结算更新Redis计数器
  HINCRBY lottery:bet_counter:{活动ID}:{玩家ID} total_bet_amount {金额}
    ↓
定时任务检查（每1分钟，gk_admin）
    ↓
扫描Redis计数器
    ↓
计算应发放数量 = floor((总打码-上次打码) / 所需打码)
    ↓
发放摸奖券（事务）
    ↓
WebSocket通知玩家
```

**关键点：**
- Redis原子操作保证数据一致性
- 数据库记录`last_bet_amount`防止重复发放（幂等性）
- 事务保证发放和计数器更新同时成功/失败

**进程：** `LotteryTicketIssueProcess` (gk_admin, 2个并发进程)

### 3. 中奖录入与通知流程

```
后台管理员录入中奖号码（批量导入Excel或单个录入）
    ↓
写入lottery_winner表
    ↓
更新lottery_ticket.is_winner=1
    ↓
异步队列任务
    ├─> WebSocket推送给中奖玩家（gk_api:3232）
    └─> 发布跑马灯消息（Redis Pub/Sub）
    ↓
客户端实时展示
```

---

## ⚡ 性能优化方案

### Redis数据结构

| Key | Type | 用途 | TTL |
|-----|------|------|-----|
| `lottery:bet_counter:{活动ID}:{玩家ID}` | Hash | 打码量计数器 | 90天 |
| `lottery:active_activities` | Set | 进行中的活动ID列表 | 无（手动维护） |
| `lottery:winners:{活动ID}` | Set | 中奖号码集合 | 7天 |
| `lottery:marquee:{渠道ID}` | List | 跑马灯消息队列 | 无（定期清理） |

### 计数器数据流

```
gk_work (游戏结算)
    ↓ HINCRBY（原子操作）
Redis Hash (实时计数)
    ↓ 每10分钟同步
MySQL (持久化存储)
```

**优势：**
- Redis原子操作保证并发安全
- 写入性能：10万QPS+
- 读取延迟：<1ms
- MySQL作为持久化备份，可用于数据恢复

### 性能指标

| 指标 | 目标值 |
|------|--------|
| 打码量统计延迟 | <1秒 |
| 摸奖券发放延迟 | <10秒 |
| API响应时间（P99） | <500ms |
| 并发玩家数 | 10万+ |
| Redis内存占用 | <2GB |

---

## 🔌 接口设计

### gk_api客户端接口

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/v1/lottery/activities` | POST | 查询活动列表 |
| `/api/v1/lottery/my-tickets` | POST | 查询我的摸奖券 |
| `/api/v1/lottery/activity-rules` | POST | 查询活动规则+我的进度 |
| `/api/v1/lottery/winners` | POST | 查询中奖记录 |
| `/api/v1/lottery/marquee` | POST | 获取跑马灯消息 |
| `/api/v1/lottery/live-stream` | POST | 获取直播流URL |

### WebSocket推送协议

**频道：** `player_{player_id}`

**事件1：中奖通知**
```json
{
    "type": "lottery_winner",
    "activity_id": 1,
    "ticket_no": "LA000000012026060512345678901234",
    "prize_name": "iPhone 15 Pro",
    "won_at": "2026-07-01 20:15:00"
}
```

**事件2：摸奖券发放通知**
```json
{
    "type": "lottery_ticket_issued",
    "activity_id": 1,
    "tickets_count": 1
}
```

---

## 🔧 进程任务设计

所有进程部署在 **gk_admin**，配置文件：`config/process.php`

### 4个核心进程

| 进程名称 | 执行频率 | 用途 | 进程数 |
|---------|---------|------|--------|
| `LotteryActivityStatusProcess` | 每5分钟 | 检查并更新活动状态 | 1 |
| `LotteryTicketIssueProcess` | 每1分钟 | 检查打码量并发放摸奖券 | 2 |
| `LotteryCounterSyncProcess` | 每10分钟 | 同步Redis计数器到MySQL | 1 |
| `LotteryMarqueeClearProcess` | 每天凌晨2点 | 清理过期跑马灯消息 | 1 |

### 配置示例

```php
// config/process.php
return [
    'lottery_activity_status' => [
        'handler' => process\LotteryActivityStatusProcess::class,
        'count' => 1,
    ],
    'lottery_ticket_issue' => [
        'handler' => process\LotteryTicketIssueProcess::class,
        'count' => 2, // 并发处理提高效率
    ],
    'lottery_counter_sync' => [
        'handler' => process\LotteryCounterSyncProcess::class,
        'count' => 1,
    ],
    'lottery_marquee_clear' => [
        'handler' => process\LotteryMarqueeClearProcess::class,
        'count' => 1,
    ],
];
```

---

## 🎨 前端界面设计

### 后台管理（gk_admin）

#### 1. 活动列表页
- Grid展示所有活动
- 筛选：状态、时间范围
- 操作：查看、编辑、中奖录入、直播设置

#### 2. 创建/编辑活动
表单字段：
- 基本信息：名称、描述、封面图
- 时间设置：预热/活动/开奖时间
- 打码规则：VIP等级、所需打码量、每人限额

#### 3. 中奖录入
- 方式1：批量导入Excel（推荐）
- 方式2：单个录入

#### 4. 直播设置
- 开关：是否开启直播
- 直播流URL（HLS/RTMP）
- 实时预览

#### 5. 统计报表
- 活动总览
- 按活动统计
- 按渠道统计
- 按VIP等级统计

### 客户端界面（gk_api）

#### 1. 活动列表页
- 活动卡片（封面图、名称、状态、倒计时）
- 我的摸奖券数量角标

#### 2. 活动详情页
- 活动规则展示
- 我的打码进度条
- 我的摸奖券列表
- 中奖榜单
- 开奖直播（开奖期间显示）

#### 3. 跑马灯组件
位置：页面顶部或活动详情页内  
样式：横向滚动，渐变背景  
数据：WebSocket实时推送

---

## 🔒 安全性设计

### 防刷机制

1. **打码量防篡改**
   - 只能由gk_work游戏结算服务更新
   - HMAC签名验证
   - 异常检测（1分钟增长>10000自动冻结）

2. **摸奖券防伪**
   - 唯一索引约束
   - 号码格式校验（正则）
   - 数据库双重验证（ticket_no + activity_id）

3. **中奖录入权限控制**
   - 只有渠道管理员可录入
   - 操作日志审计
   - 二次确认（批量导入预览）

### 数据一致性

1. **摸奖券发放幂等性**
   - 数据库记录`last_bet_amount`
   - 增量计算：`betSinceLastIssue = totalBet - lastBet`
   - 分布式锁（Redis）
   - 事务处理

2. **Redis与MySQL同步**
   - 定时全量同步（每10分钟）
   - Redis为主，MySQL为从
   - 每日数据对账任务

---

## 📦 部署方案

### 部署清单

#### gk_admin
**新增文件：**
- `addons/webman/model/Lottery*.php` (7个模型)
- `addons/webman/controller/Lottery*.php` (4个控制器)
- `addons/webman/service/LotteryTicketService.php`
- `addons/webman/lang/*/lottery.php` (4个语言文件)
- `process/Lottery*.php` (4个进程)
- `database/phinx_migrations/*_lottery_*.php` (2个迁移)

**配置修改：**
- `config/process.php` (新增4个进程)
- `.env` (新增LOTTERY_SECRET)

#### gk_api
**新增文件：**
- `app/api/v1/LotteryController.php` (客户端API)
- `app/queue/SendWinnerNotificationJob.php` (中奖通知队列)

**路由配置：**
- `config/route.php` (新增6个路由)

#### gk_work
**修改文件：**
- `app/service/GameSettlementService.php` (新增打码量上报逻辑)

### 部署步骤

1. **备份数据库**
   ```bash
   mysqldump -u root -p yjb_platform > backup_$(date +%Y%m%d).sql
   ```

2. **执行数据库迁移**
   ```bash
   cd /www/wwwroot/gk_admin
   vendor/bin/phinx migrate
   ```

3. **上传代码并重启服务**
   ```bash
   # gk_admin
   ssh admin.server
   cd /www/wwwroot/gk_admin
   php start.php restart
   
   # gk_api
   ssh api.server
   cd /www/wwwroot/gk_api
   php start.php restart
   
   # gk_work
   ssh work.server
   cd /www/wwwroot/gk_work
   php start.php restart
   ```

4. **验证进程**
   ```bash
   php start.php status | grep lottery
   ```

5. **初始化Redis数据**
   ```bash
   redis-cli
   SADD lottery:active_activities 1 2 3
   ```

### 功能测试清单

✅ 创建活动  
✅ 状态自动流转  
✅ 打码量统计  
✅ 摸奖券自动发放  
✅ 中奖录入  
✅ WebSocket推送  
✅ 跑马灯展示  
✅ 直播功能  

---

## 📊 监控指标

| 指标 | 目标值 | 告警阈值 |
|------|--------|---------|
| 摸奖券发放延迟 | <10秒 | >30秒 |
| 打码量统计延迟 | <1秒 | >5秒 |
| API响应时间（P99） | <500ms | >1000ms |
| 摸奖券发放失败率 | 0% | >1% |
| Redis内存占用 | <2GB | >5GB |
| MySQL慢查询 | 0条 | >10条/分钟 |
| 进程存活状态 | 100% | <100% |

---

## 🔗 相关文档

- **详细设计文档（待生成）：** `LOTTERY_TICKET_SYSTEM_DESIGN.md` (完整版，包含所有代码示例)
- **VIP系统审查报告：** `VIP_SYSTEM_REVIEW.md`
- **数据库迁移文件：** `database/phinx_migrations/`
- **CLAUDE.md项目指南：** 开发规范和最佳实践

---

**文档状态：** ✅ 已完成  
**下一步：** 开始实现数据模型和迁移文件

**审查人：** Claude (Staff Engineer)  
**审查日期：** 2026-06-05
