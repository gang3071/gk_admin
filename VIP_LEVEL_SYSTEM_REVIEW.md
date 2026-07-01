# VIP等级系统评审文档

**评审时间**: 2026-06-04  
**评审人**: Claude Code  
**系统版本**: YJB Admin v1.0

---

## 📋 目录

1. [系统概述](#系统概述)
2. [数据库设计](#数据库设计)
3. [核心功能](#核心功能)
4. [代码架构](#代码架构)
5. [业务逻辑](#业务逻辑)
6. [问题与建议](#问题与建议)
7. [优化方案](#优化方案)

---

## 系统概述

### 功能定位
VIP等级系统是玩家激励体系的核心模块，通过打码量、时间等维度实现玩家等级的升降级管理，并配套返水、生日礼金等特权。

### 核心特性
- ✅ 多等级管理
- ✅ 基于打码量的升级/降级机制
- ✅ 保级时间限制
- ✅ 升级冷却期
- ✅ 分游戏平台的返水比例
- ✅ 生日礼金配置
- ✅ 最小领取额限制

### 涉及的数据表
1. `vip_level` - VIP等级主表
2. `vip_level_cashback` - VIP等级返水比例表（分平台）
3. `player` - 玩家表（新增vip相关字段）
4. `player_vip_period` - 玩家VIP周期数据表
5. `channel` - 渠道表（新增vip_level_enabled字段）

---

## 数据库设计

### 1. vip_level 表（VIP等级主表）

**迁移文件**: `20260522160000_create_vip_level_table.php`

| 字段名 | 类型 | 说明 | 约束 |
|--------|------|------|------|
| id | INT | 主键 | PK, AUTO_INCREMENT |
| name | VARCHAR(50) | 等级名称 | NOT NULL |
| upgrade_limit_days | INT | 升级限制时间（天数） | DEFAULT 0 |
| retain_level_days | INT | 保级时间（天数） | DEFAULT 0 |
| retain_level_bet_amount | DECIMAL(10,2) | 保级所需打码量 | DEFAULT 0.00 |
| upgrade_bet_amount | DECIMAL(10,2) | 升级所需打码量 | DEFAULT 0.00 |
| min_claim_amount | DECIMAL(10,2) | 最小领取额 | DEFAULT 0.00 |
| birthday_bonus | DECIMAL(10,2) | 生日礼金 | DEFAULT 0.00 |
| sort | INT | 排序（决定等级高低） | DEFAULT 0 |
| status | TINYINT | 状态(0:禁用,1:启用) | DEFAULT 1 |
| created_at | TIMESTAMP | 创建时间 | |
| updated_at | TIMESTAMP | 更新时间 | |

**索引**:
- PRIMARY KEY (`id`)
- INDEX `idx_sort` (`sort`)
- INDEX `idx_status` (`status`)

**设计评价**:
- ✅ 字段设计合理，涵盖升降级核心逻辑
- ✅ 使用 `sort` 字段控制等级顺序，灵活性高
- ⚠️ **缺少 `department_id`** - 无法实现渠道级别的VIP等级定制
- ⚠️ **缺少软删除** - 无 `deleted_at` 字段
- ⚠️ **缺少说明字段** - 无 `description` 或 `remark` 字段

---

### 2. vip_level_cashback 表（VIP返水比例表）

**迁移文件**: `20260526160000_create_vip_level_cashback_table.php`

| 字段名 | 类型 | 说明 | 约束 |
|--------|------|------|------|
| id | INT | 主键 | PK, AUTO_INCREMENT |
| vip_level_id | INT | VIP等级ID | NOT NULL |
| platform_id | INT | 游戏平台ID | NOT NULL |
| cashback_ratio | DECIMAL(5,2) | 返水比例(%) | DEFAULT 0.00 |
| status | TINYINT | 状态 | DEFAULT 1 |
| created_at | TIMESTAMP | 创建时间 | |
| updated_at | TIMESTAMP | 更新时间 | |

**索引**:
- PRIMARY KEY (`id`)
- UNIQUE KEY `uk_vip_platform` (`vip_level_id`, `platform_id`)
- INDEX `idx_vip_level_id` (`vip_level_id`)
- INDEX `idx_platform_id` (`platform_id`)

**外键**:
- `vip_level_id` → `vip_level.id` (CASCADE)
- `platform_id` → `game_platform.id` (CASCADE)

**设计评价**:
- ✅ 唯一键设计正确，避免重复配置
- ✅ 外键级联删除，数据一致性好
- ⚠️ **返水比例范围未约束** - 应该 CHECK (cashback_ratio >= 0 AND cashback_ratio <= 100)
- ⚠️ **缺少生效时间字段** - 无法实现"下周期生效"的返水调整

---

### 3. player 表新增字段

**迁移文件**: `20260526150000_add_vip_fields_to_player_and_create_period_table.php`

| 字段名 | 类型 | 说明 | 默认值 |
|--------|------|------|--------|
| vip_level_id | INT | 当前VIP等级ID | NULL |
| vip_upgrade_at | TIMESTAMP | 最近升级时间 | NULL |
| vip_retain_deadline | TIMESTAMP | 保级截止时间 | NULL |

**索引**:
- INDEX `idx_vip_level_id` (`vip_level_id`)
- INDEX `idx_vip_retain_deadline` (`vip_retain_deadline`)

**设计评价**:
- ✅ 核心字段齐全
- ⚠️ **缺少降级历史** - 无法追踪降级原因和时间
- ⚠️ **缺少周期打码量缓存** - 每次查询都需要计算打码量
- ⚠️ **缺少升级锁定标志** - 无法标记"升级冷却期"状态

---

### 4. player_vip_period 表（VIP周期数据）

**迁移文件**: `20260526150000_add_vip_fields_to_player_and_create_period_table.php`

| 字段名 | 类型 | 说明 |
|--------|------|------|
| id | INT | 主键 |
| player_id | INT | 玩家ID |
| period_start | TIMESTAMP | 周期开始时间 |
| period_end | TIMESTAMP | 周期结束时间 |
| bet_amount | DECIMAL(15,2) | 周期内打码量 |
| vip_level_id | INT | 周期VIP等级 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

**索引**:
- INDEX `idx_player_id` (`player_id`)
- INDEX `idx_period` (`period_start`, `period_end`)

**设计评价**:
- ✅ 支持周期性数据统计
- ⚠️ **缺少唯一约束** - 可能产生重复周期记录
- ⚠️ **缺少department_id** - 无法按渠道统计
- ⚠️ **缺少分平台打码量** - 无法区分不同平台的打码贡献

---

### 5. channel 表新增字段

**迁移文件**: `20260601150000_add_vip_level_status_to_channel.php`

| 字段名 | 类型 | 说明 | 默认值 |
|--------|------|------|--------|
| vip_level_enabled | TINYINT | VIP等级功能开关 | 0 |

**设计评价**:
- ✅ 支持渠道级别的功能开关
- ⚠️ **应配套 `default_vip_level_id`** - 新玩家默认等级
- ⚠️ **缺少VIP配置隔离** - 所有渠道共享VIP等级配置

---

## 核心功能

### 1. VIP等级管理 (`VipLevelController`)

**URL**: `/ex-admin/vip-level`

**功能点**:
- ✅ 等级列表展示（按sort排序）
- ✅ 等级增删改查
- ✅ 返水比例配置（Drawer抽屉形式）
- ✅ 状态启用/禁用

**实现方式**:
```php
public function index(): Grid
{
    // 列表展示
    $grid->model()->orderBy('sort', 'asc')->orderBy('id', 'asc');
}

public function cashback(int $vip_level_id): Form
{
    // 分平台返水比例配置
    foreach ($platforms as $platform) {
        $form->number('cashback_' . $platform->id, $platform->name)
            ->min(0)->max(100)->step(0.01);
    }
}
```

**评价**:
- ✅ 界面友好，返水配置直观
- ⚠️ **缺少批量操作** - 无法批量调整返水比例
- ⚠️ **缺少历史记录** - 无法追踪返水比例调整历史
- ⚠️ **缺少生效时间** - 返水调整立即生效，可能影响玩家预期

---

### 2. VIP升降级逻辑 (`VipLevel` 模型)

**核心方法**:

#### 2.1 获取相邻等级
```php
// 获取下一等级
public function getNextLevel(): ?VipLevel
{
    return static::query()
        ->where('status', self::STATUS_ENABLED)
        ->where('sort', '>', $this->sort)
        ->orderBy('sort', 'asc')
        ->first();
}

// 获取上一等级
public function getPrevLevel(): ?VipLevel
{
    return static::query()
        ->where('status', self::STATUS_ENABLED)
        ->where('sort', '<', $this->sort)
        ->orderBy('sort', 'desc')
        ->first();
}
```

**评价**:
- ✅ 逻辑清晰，基于 `sort` 字段排序
- ✅ 过滤禁用等级
- ⚠️ **未缓存** - 高频调用可能产生大量数据库查询

---

#### 2.2 升降级条件判断
```php
// 检查是否满足升级条件
public static function isUpgradeQualified(float $periodBetAmount, float $upgradeBetAmount): bool
{
    return $periodBetAmount >= $upgradeBetAmount;
}

// 检查是否满足保级条件
public static function isRetainQualified(float $periodBetAmount, float $retainBetAmount): bool
{
    return $periodBetAmount >= $retainBetAmount;
}
```

**评价**:
- ✅ 纯函数设计，可测试性强
- ⚠️ **逻辑过于简单** - 未考虑升级冷却期、时间限制
- ⚠️ **缺少降级逻辑** - 只有升级和保级，没有主动降级判断

---

#### 2.3 等级查找（纯函数版本）
```php
// 从等级列表中查找下一等级（不依赖数据库）
public static function findNextLevel(array $levels, int $currentSort): ?VipLevel
{
    foreach ($levels as $level) {
        if ($level->status == self::STATUS_ENABLED && $level->sort > $currentSort) {
            return $level;
        }
    }
    return null;
}
```

**评价**:
- ✅ **架构优秀** - 提供纯函数版本，方便单元测试和缓存优化
- ✅ 职责分离 - DB查询版本 vs 内存计算版本
- ⚠️ **未被实际使用** - 代码中未见调用这些纯函数方法

---

### 3. 周期打码量统计

**缺失功能** ⚠️

根据代码分析，**未发现周期打码量的自动统计逻辑**：
- ❌ 无定时任务更新 `player_vip_period` 表
- ❌ 无实时打码量累加逻辑
- ❌ 无自动升降级触发机制

**推测实现位置**:
可能在 `gk_work` 项目的后台进程中实现，需要进一步确认。

---

## 代码架构

### 优点

1. **模型设计优秀** ✅
   - 提供 DB 查询方法 + 纯函数方法双版本
   - 职责清晰：VipLevel 负责等级逻辑，VipLevelCashback 负责返水
   - 代码注释完善

2. **控制器简洁** ✅
   - 使用 ExAdmin 组件，代码量少
   - Drawer 抽屉形式配置返水，用户体验好

3. **翻译完整** ✅
   - 支持 4 种语言（zh-TW, zh-CN, en, jp）
   - 翻译键结构清晰（fields, help, status）

### 缺点

1. **缺少服务层** ⚠️
   - 升降级逻辑应封装为 `VipLevelService`
   - 打码量统计应封装为 `VipPeriodService`
   - 返水计算应封装为 `VipCashbackService`

2. **缺少事件系统** ⚠️
   - 升级/降级应触发事件：`VipLevelUpgraded`, `VipLevelDowngraded`
   - 方便通知、日志、统计等扩展功能

3. **缺少队列机制** ⚠️
   - 批量升降级应使用队列异步处理
   - 返水发放应使用队列避免阻塞

4. **缺少日志** ⚠️
   - 无升降级操作日志
   - 无返水调整日志
   - 无打码量异常日志

---

## 业务逻辑

### 升级流程（推测）

```
玩家下注 → 累加打码量 → 定时任务检查
    ↓
满足升级条件？
    ├─ 是 → 检查升级冷却期
    │        ├─ 未过冷却期 → 跳过
    │        └─ 已过冷却期 → 升级
    │                         ├─ 更新 player.vip_level_id
    │                         ├─ 更新 player.vip_upgrade_at
    │                         ├─ 更新 player.vip_retain_deadline
    │                         └─ 发送通知
    └─ 否 → 跳过
```

### 保级流程（推测）

```
定时任务（每日执行）
    ↓
查询保级截止时间 <= 今天的玩家
    ↓
遍历玩家 → 检查周期内打码量
    ├─ 满足保级条件 → 延长保级截止时间
    └─ 不满足 → 降级
                  ├─ 更新 player.vip_level_id
                  ├─ 更新 player.vip_retain_deadline
                  └─ 发送通知
```

### 返水计算流程（推测）

```
定时任务（每日/每周执行）
    ↓
遍历所有启用VIP的玩家
    ↓
查询周期内各平台投注记录
    ↓
按平台计算返水金额 = 投注额 × 返水比例
    ↓
累加总返水金额 → 发放到玩家账户
    ↓
记录返水发放日志
```

---

## 问题与建议

### 🔴 严重问题

#### 1. **缺少渠道隔离** 
**问题**: VIP等级配置是全局的，所有渠道共享同一套等级体系

**影响**:
- 不同渠道无法定制VIP政策
- A渠道想要10个等级，B渠道想要5个等级 → 无法实现

**建议**:
```sql
-- 方案1: 为 vip_level 表添加 department_id 字段
ALTER TABLE vip_level ADD COLUMN department_id INT DEFAULT 0 COMMENT '所属渠道ID(0=全局)';
ALTER TABLE vip_level ADD INDEX idx_department_id (department_id);

-- 方案2: 创建 channel_vip_level 中间表（更灵活）
CREATE TABLE channel_vip_level (
    id INT PRIMARY KEY AUTO_INCREMENT,
    department_id INT NOT NULL,
    vip_level_id INT NOT NULL,
    custom_upgrade_bet_amount DECIMAL(10,2),
    custom_retain_bet_amount DECIMAL(10,2),
    status TINYINT DEFAULT 1,
    UNIQUE KEY uk_dept_level (department_id, vip_level_id)
);
```

---

#### 2. **缺少升降级执行逻辑**
**问题**: 只有判断方法，没有实际执行升降级的代码

**影响**:
- VIP系统无法自动运转
- 需要手动或外部任务触发

**建议**: 创建后台定时任务
```php
// D:\gk_work\process\VipLevelUpgradeProcess.php

class VipLevelUpgradeProcess
{
    public function onWorkerStart($worker)
    {
        // 每小时执行一次
        Timer::add(3600, function() {
            $this->processUpgrade();
            $this->processRetain();
        });
    }

    private function processUpgrade()
    {
        $players = Player::query()
            ->whereNotNull('vip_level_id')
            ->where('vip_level_enabled', 1)
            ->get();

        foreach ($players as $player) {
            $service = new VipLevelService($player);
            $service->checkAndUpgrade();
        }
    }

    private function processRetain()
    {
        $players = Player::query()
            ->whereNotNull('vip_level_id')
            ->where('vip_retain_deadline', '<=', now())
            ->get();

        foreach ($players as $player) {
            $service = new VipLevelService($player);
            $service->checkAndRetain();
        }
    }
}
```

---

#### 3. **缺少打码量统计**
**问题**: `player_vip_period` 表存在但未见统计逻辑

**影响**:
- 无法判断玩家是否满足升级条件
- 无法计算返水金额

**建议**: 创建打码量统计任务
```php
// D:\gk_work\process\VipBetAmountProcess.php

class VipBetAmountProcess
{
    public function onWorkerStart($worker)
    {
        // 每10分钟执行一次
        Timer::add(600, function() {
            $this->updateBetAmount();
        });
    }

    private function updateBetAmount()
    {
        // 获取过去10分钟的游戏记录
        $records = PlayerGameLog::query()
            ->where('created_at', '>=', now()->subMinutes(10))
            ->get();

        foreach ($records as $record) {
            // 累加到玩家周期打码量
            $this->accumulateBetAmount($record->player_id, $record->bet_amount);
        }
    }
}
```

---

### ⚠️ 中等问题

#### 4. **缺少日志追踪**
**建议**: 创建 `vip_level_log` 表
```sql
CREATE TABLE vip_level_log (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    from_level_id INT,
    to_level_id INT NOT NULL,
    change_type ENUM('upgrade', 'downgrade', 'manual') NOT NULL,
    reason VARCHAR(255),
    operator_id INT COMMENT '操作员ID（手动调整时）',
    bet_amount DECIMAL(15,2) COMMENT '触发时的打码量',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_player_id (player_id),
    INDEX idx_created_at (created_at)
) COMMENT='VIP等级变更日志';
```

---

#### 5. **缺少返水发放记录**
**建议**: 创建 `vip_cashback_record` 表
```sql
CREATE TABLE vip_cashback_record (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    vip_level_id INT NOT NULL,
    platform_id INT NOT NULL,
    period_start TIMESTAMP NOT NULL,
    period_end TIMESTAMP NOT NULL,
    bet_amount DECIMAL(15,2) NOT NULL COMMENT '投注额',
    cashback_ratio DECIMAL(5,2) NOT NULL COMMENT '返水比例',
    cashback_amount DECIMAL(10,2) NOT NULL COMMENT '返水金额',
    status TINYINT DEFAULT 0 COMMENT '0:待发放,1:已发放,2:已取消',
    granted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_player_id (player_id),
    INDEX idx_status (status)
) COMMENT='VIP返水发放记录';
```

---

#### 6. **返水比例调整无生效时间**
**建议**: 为 `vip_level_cashback` 添加字段
```sql
ALTER TABLE vip_level_cashback 
ADD COLUMN effective_date DATE COMMENT '生效日期',
ADD COLUMN expired_date DATE COMMENT '失效日期';
```

---

### 💡 优化建议

#### 7. **缓存优化**
```php
// 缓存VIP等级列表（1小时）
public static function getCachedLevels(): Collection
{
    return Cache::remember('vip_levels:all', 3600, function() {
        return static::query()
            ->where('status', self::STATUS_ENABLED)
            ->orderBy('sort', 'asc')
            ->get();
    });
}

// 缓存玩家VIP等级（5分钟）
public function getCachedVipLevel(): ?VipLevel
{
    $key = "player:{$this->id}:vip_level";
    return Cache::remember($key, 300, function() {
        return $this->vipLevel;
    });
}
```

---

#### 8. **事件系统**
```php
// 定义事件
class VipLevelUpgraded
{
    public $player;
    public $fromLevel;
    public $toLevel;
    public $betAmount;

    public function __construct(Player $player, ?VipLevel $fromLevel, VipLevel $toLevel, float $betAmount)
    {
        $this->player = $player;
        $this->fromLevel = $fromLevel;
        $this->toLevel = $toLevel;
        $this->betAmount = $betAmount;
    }
}

// 监听器
class SendVipUpgradeNotification
{
    public function handle(VipLevelUpgraded $event)
    {
        // 发送通知
        // 记录日志
        // 发放升级礼包
    }
}

// 触发
event(new VipLevelUpgraded($player, $oldLevel, $newLevel, $betAmount));
```

---

#### 9. **服务层封装**
```php
class VipLevelService
{
    private Player $player;

    public function __construct(Player $player)
    {
        $this->player = $player;
    }

    /**
     * 检查并执行升级
     */
    public function checkAndUpgrade(): bool
    {
        $currentLevel = $this->player->vipLevel;
        if (!$currentLevel) {
            return false;
        }

        // 检查升级冷却期
        if ($this->isInCooldown()) {
            return false;
        }

        $nextLevel = $currentLevel->getNextLevel();
        if (!$nextLevel) {
            return false; // 已是最高等级
        }

        // 获取周期内打码量
        $periodBetAmount = $this->getPeriodBetAmount();

        // 检查是否满足升级条件
        if (!VipLevel::isUpgradeQualified($periodBetAmount, $nextLevel->upgrade_bet_amount)) {
            return false;
        }

        // 执行升级
        return $this->doUpgrade($nextLevel, $periodBetAmount);
    }

    /**
     * 检查并执行保级/降级
     */
    public function checkAndRetain(): bool
    {
        $currentLevel = $this->player->vipLevel;
        if (!$currentLevel) {
            return false;
        }

        // 获取周期内打码量
        $periodBetAmount = $this->getPeriodBetAmount();

        // 满足保级条件
        if (VipLevel::isRetainQualified($periodBetAmount, $currentLevel->retain_level_bet_amount)) {
            return $this->renewRetainDeadline();
        }

        // 不满足保级，执行降级
        $prevLevel = $currentLevel->getPrevLevel();
        if (!$prevLevel) {
            return $this->renewRetainDeadline(); // 已是最低等级，续期
        }

        return $this->doDowngrade($prevLevel, $periodBetAmount);
    }

    private function doUpgrade(VipLevel $toLevel, float $betAmount): bool
    {
        Db::beginTransaction();
        try {
            $fromLevel = $this->player->vipLevel;

            // 更新玩家等级
            $this->player->vip_level_id = $toLevel->id;
            $this->player->vip_upgrade_at = now();
            $this->player->vip_retain_deadline = now()->addDays($toLevel->retain_level_days);
            $this->player->save();

            // 记录日志
            VipLevelLog::create([
                'player_id' => $this->player->id,
                'from_level_id' => $fromLevel?->id,
                'to_level_id' => $toLevel->id,
                'change_type' => 'upgrade',
                'bet_amount' => $betAmount,
            ]);

            // 触发事件
            event(new VipLevelUpgraded($this->player, $fromLevel, $toLevel, $betAmount));

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollBack();
            Log::error("VIP升级失败: {$e->getMessage()}");
            return false;
        }
    }

    private function doDowngrade(VipLevel $toLevel, float $betAmount): bool
    {
        // 类似 doUpgrade 实现
    }

    private function isInCooldown(): bool
    {
        if (!$this->player->vip_upgrade_at) {
            return false;
        }

        $cooldownDays = $this->player->vipLevel->upgrade_limit_days ?? 0;
        $cooldownEnd = $this->player->vip_upgrade_at->addDays($cooldownDays);

        return now()->lt($cooldownEnd);
    }

    private function getPeriodBetAmount(): float
    {
        // 从 player_vip_period 表获取
        // 或从 player_game_log 实时统计
    }

    private function renewRetainDeadline(): bool
    {
        $this->player->vip_retain_deadline = now()->addDays($this->player->vipLevel->retain_level_days);
        return $this->player->save();
    }
}
```

---

## 优化方案

### 短期优化（1-2周）

1. ✅ **补充缺失的业务逻辑**
   - 创建 VipLevelService 服务类
   - 实现升降级自动执行
   - 实现打码量统计

2. ✅ **添加日志表**
   - vip_level_log（等级变更日志）
   - vip_cashback_record（返水发放记录）

3. ✅ **创建后台定时任务**
   - 打码量统计任务（10分钟）
   - 升级检查任务（1小时）
   - 保级检查任务（每日）

### 中期优化（1个月）

4. ✅ **渠道隔离**
   - 添加 department_id 支持
   - 支持渠道级VIP配置

5. ✅ **返水系统完善**
   - 添加生效时间支持
   - 创建返水发放任务
   - 添加返水发放记录

6. ✅ **事件系统**
   - 升降级事件
   - 返水发放事件
   - 通知系统集成

### 长期优化（3个月）

7. ✅ **性能优化**
   - Redis缓存等级列表
   - 缓存玩家等级
   - 打码量实时累加（Redis）

8. ✅ **监控告警**
   - 异常升级监控
   - 返水异常监控
   - 打码量异常监控

9. ✅ **数据分析**
   - VIP等级分布统计
   - 升降级趋势分析
   - 返水成本分析

---

## 总结

### ✅ 做得好的地方

1. **模型设计优秀** - 纯函数 + DB查询双版本
2. **翻译完整** - 4种语言全覆盖
3. **UI友好** - Drawer抽屉配置返水
4. **扩展性好** - 基于sort的等级排序

### ⚠️ 需要改进的地方

1. **缺少服务层** - 业务逻辑分散
2. **缺少定时任务** - 升降级无法自动执行
3. **缺少日志** - 无法追踪历史
4. **缺少渠道隔离** - 无法定制化
5. **缺少打码量统计** - 核心功能缺失

### 📊 评审结论

**当前状态**: 🟡 **基础框架完成，核心逻辑待实现**

- **数据库设计**: 70分 - 基础结构合理，缺少部分字段
- **代码质量**: 80分 - 架构清晰，缺少服务层
- **功能完整性**: 40分 - 管理界面完成，自动化逻辑缺失
- **可维护性**: 60分 - 缺少日志和事件系统

**建议优先级**:
1. 🔴 **P0**: 实现升降级执行逻辑（VipLevelService）
2. 🔴 **P0**: 实现打码量统计任务
3. 🟡 **P1**: 添加日志表和记录逻辑
4. 🟡 **P1**: 创建后台定时任务
5. 🟢 **P2**: 渠道隔离优化
6. 🟢 **P2**: 返水系统完善

---

**文档版本**: v1.0  
**最后更新**: 2026-06-04  
**评审人**: Claude Code
