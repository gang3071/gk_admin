# 摸奖券系统深度审核报告

## 📋 审核信息

**审核日期:** 2026-06-10  
**审核类型:** 深度逻辑检查 + 代码错误扫描  
**审核范围:** 完整摸奖券系统（发券、开奖、状态流转）

---

## 🎟️ 券号发放规则详解

### 核心规则

**券号格式:** 6位数字字符串（`000000` ~ `999999`）

**发放规则:**
1. **自增序列:** 从 `000000` 开始依次递增
2. **活动隔离:** 每个活动独立计数
3. **并发安全:** 行锁保证唯一性
4. **最大上限:** 每活动最多 1,000,000 张券

---

### 详细发放流程

#### 第1张券发放

```
活动初始状态：
- current_ticket_no = 0  (已发0张)
- max_ticket_no = 1000000

玩家A打码达标 → 应发1张券

执行流程：
1. 锁定活动记录 (lockForUpdate)
2. 读取 current_ticket_no = 0
3. 生成券号：str_pad(0, 6, '0', STR_PAD_LEFT) = "000000"
4. 插入数据库：
   INSERT INTO lottery_ticket (
     ticket_no = "000000",
     player_id = A,
     activity_id = 1,
     status = 0,  -- 未使用
     source = 'betting',
     ...
   )
5. 更新活动：
   current_ticket_no = 0 + 1 = 1
   total_tickets = 0 + 1 = 1
6. 提交事务
7. 返回：issued_count = 1, first_ticket_no = "000000"

结果：
玩家A获得券号 "000000"
下一张券号将是 "000001"
```

---

#### 批量发券（一次5张）

```
当前状态：
- current_ticket_no = 100 (已发100张)
- 上一张券号 = "000099"

玩家B打码达标 → 应发5张券

执行流程：
1. 锁定活动记录
2. 读取 current_ticket_no = 100
3. 批量生成券号：
   for (i = 0; i < 5; i++) {
     ticket_no = str_pad(100 + i, 6, '0', STR_PAD_LEFT)
     // 100 → "000100"
     // 101 → "000101"
     // 102 → "000102"
     // 103 → "000103"
     // 104 → "000104"
   }
4. 批量插入（1次SQL）：
   INSERT INTO lottery_ticket (ticket_no, ...) VALUES
   ("000100", ...),
   ("000101", ...),
   ("000102", ...),
   ("000103", ...),
   ("000104", ...);
5. 更新活动：
   current_ticket_no = 100 + 5 = 105
   total_tickets = 100 + 5 = 105
6. 提交事务

结果：
玩家B获得券号：000100, 000101, 000102, 000103, 000104
下一张券号将是：000105
```

---

#### 并发发券场景

```
时刻T0：
- current_ticket_no = 200

时刻T1：
玩家A和玩家B同时打码达标
- 玩家A应发2张
- 玩家B应发3张

并发执行（串行化）：

线程1（玩家A）：
  T1: Db::beginTransaction()
  T2: lockForUpdate() → 获得锁，current = 200
  T3: 生成券号 200, 201 → "000200", "000201"
  T4: 插入数据库
  T5: current_ticket_no = 202
  T6: Db::commit() → 释放锁

线程2（玩家B）：
  T1: Db::beginTransaction()
  T2: lockForUpdate() → 等待锁...
  T7: lockForUpdate() → 获得锁，current = 202 ✅
  T8: 生成券号 202, 203, 204 → "000202", "000203", "000204"
  T9: 插入数据库
  T10: current_ticket_no = 205
  T11: Db::commit() → 释放锁

最终结果：
- 玩家A：000200, 000201
- 玩家B：000202, 000203, 000204
- current_ticket_no = 205
- ✅ 无券号重复
- ✅ 无券号跳号
- ✅ 严格自增
```

---

#### 券号不足场景

```
当前状态：
- current_ticket_no = 999995 (已发999995张)
- max_ticket_no = 1000000
- 剩余券数 = 1000000 - 999995 = 5张

玩家C打码达标 → 应发10张券

执行流程：
1. 锁定活动记录
2. 读取：current = 999995, max = 1000000
3. 计算可用数量：
   availableCount = 1000000 - 999995 = 5
   actualCount = min(10, 5) = 5 ✅ 限制在可用范围
4. 生成券号：999995 ~ 999999
   "999995", "999996", "999997", "999998", "999999"
5. 批量插入
6. 更新：current_ticket_no = 1000000
7. 提交

结果：
- 玩家C获得5张券（不是10张）
- 应发：10张
- 实发：5张
- 未发：5张（不足部分丢弃）
- 活动券已用完：current_ticket_no = max_ticket_no

下次再有玩家达标：
- 检查：current_ticket_no (1000000) >= max_ticket_no (1000000)
- 返回：issued_count = 0, first_ticket_no = null
- 日志：Log::warning('摸奖券已发放完毕')
```

---

#### 券号范围示例

| 已发券数 | current_ticket_no | 最后券号 | 下一张券号 | 券号范围 |
|---------|------------------|---------|-----------|---------|
| 0 | 0 | - | 000000 | - |
| 1 | 1 | 000000 | 000001 | 000000 |
| 15 | 15 | 000014 | 000015 | 000000~000014 |
| 100 | 100 | 000099 | 000100 | 000000~000099 |
| 1000 | 1000 | 000999 | 001000 | 000000~000999 |
| 10000 | 10000 | 009999 | 010000 | 000000~009999 |
| 100000 | 100000 | 099999 | 100000 | 000000~099999 |
| 999999 | 999999 | 999998 | 999999 | 000000~999998 |
| 1000000 | 1000000 | 999999 | 无（已满） | 000000~999999 |

---

### 券号唯一性保证

**三重保护机制:**

1. **数据库唯一索引**
   ```sql
   UNIQUE INDEX idx_activity_ticket_no (activity_id, ticket_no)
   ```
   - 防止同一活动重复券号
   - 数据库层面强制约束

2. **行锁 (lockForUpdate)**
   ```php
   $activity = LotteryTicketActivity::where('id', $activityId)
       ->lockForUpdate()
       ->first();
   ```
   - 事务内排他锁
   - 并发请求串行化

3. **原子更新**
   ```php
   $activity->current_ticket_no = $currentNo + $actualCount;
   $activity->save();
   ```
   - 单次UPDATE语句
   - 原子性保证

**结论:** ✅ 券号100%唯一，无重复风险

---

## 🔴 发现的严重问题

### 问题1: 扫描任务状态检查遗漏 ⚠️

**严重等级:** 🟡 中等

**位置:** `LotteryBetProgressScanTask.php:61`

**问题代码:**
```php
// 只检查 STATUS_ONGOING，遗漏 STATUS_BETTING
$activities = LotteryTicketActivity::where('status', LotteryTicketActivity::STATUS_ONGOING)
    ->get();
```

**问题分析:**
- 扫描任务只处理 `STATUS_ONGOING (1)` 的活动
- 新增的 `STATUS_BETTING (5)` 状态被忽略
- 导致打码中的活动不会扫描打码记录

**影响:**
- 活动流转到 STATUS_BETTING 后，打码不再统计
- 玩家打码无法发券
- 财务数据缺失

**修复方案:**
```php
// 修复后
$activities = LotteryTicketActivity::whereIn('status', [
    LotteryTicketActivity::STATUS_ONGOING,
    LotteryTicketActivity::STATUS_BETTING,  // ✅ 添加
])->get();
```

---

### 问题2: 摇球前未验证是否有发券 🟡

**严重等级:** 🟡 中等

**位置:** `LotteryBallDrawService.php:49-52`

**问题代码:**
```php
// 检查是否有发放的券
$totalTickets = $activity->current_ticket_no;
if ($totalTickets <= 0) {
    return ['success' => false, 'message' => '活动未发放任何摸奖券，无法开奖'];
}
```

**问题分析:**
- 使用 `current_ticket_no` 判断是否有发券
- ✅ 逻辑正确（current_ticket_no = 已发券数）
- ⚠️ 但未检查实际数据库记录

**潜在风险场景:**
```
异常情况（数据不一致）：
- activity.current_ticket_no = 100 (记录显示已发100张)
- 但数据库实际券数 = 0 (券记录丢失/删除)

摇球执行：
- 检查通过：current_ticket_no > 0 ✅
- 计算最大券号：99
- 摇球结果：000050
- 匹配中奖：查询 ticket_no = "000050" → 无结果！
- 结果：摇球成功，但0人中奖（数据异常）
```

**修复建议:**
```php
// 增强验证
$totalTickets = $activity->current_ticket_no;
if ($totalTickets <= 0) {
    return ['success' => false, 'message' => '活动未发放任何摸奖券，无法开奖'];
}

// ✅ 添加：验证实际券数
$actualTickets = LotteryTicket::where('activity_id', $activity->id)->count();
if ($actualTickets == 0) {
    Log::error('摇球数据不一致', [
        'activity_id' => $activity->id,
        'current_ticket_no' => $totalTickets,
        'actual_tickets' => $actualTickets,
    ]);
    return ['success' => false, 'message' => '数据异常：活动券记录丢失'];
}

if ($actualTickets != $totalTickets) {
    Log::warning('摇球数据不一致（数量不匹配）', [
        'activity_id' => $activity->id,
        'current_ticket_no' => $totalTickets,
        'actual_tickets' => $actualTickets,
    ]);
}
```

---

### 问题3: 摇球匹配逻辑边界问题 ⚠️

**严重等级:** 🟡 低

**位置:** `LotteryBallDrawService.php:205-208`

**问题代码:**
```php
// 计算匹配位数
$matchDigits = 7 - $prizeLevel->level_rank;
if ($matchDigits <= 0 || $matchDigits > 6) {
    $matchDigits = 6; // 默认全匹配
}
```

**问题分析:**

**测试边界值：**
```
level_rank = 1: matchDigits = 7 - 1 = 6 ✅ (特等奖6位)
level_rank = 2: matchDigits = 7 - 2 = 5 ✅ (一等奖5位)
level_rank = 6: matchDigits = 7 - 6 = 1 ✅ (五等奖1位)
level_rank = 7: matchDigits = 7 - 7 = 0 ⚠️ → 被修正为6
level_rank = 8: matchDigits = 7 - 8 = -1 ⚠️ → 被修正为6
level_rank = 0: matchDigits = 7 - 0 = 7 ⚠️ → 被修正为6
```

**潜在问题:**
- 如果配置错误（level_rank > 6 或 <= 0）
- 会默认匹配6位（特等奖级别）
- 可能导致低等奖按特等奖匹配

**修复建议:**
```php
// 计算匹配位数
$matchDigits = 7 - $prizeLevel->level_rank;

// ✅ 严格验证
if ($matchDigits <= 0 || $matchDigits > 6) {
    Log::error('奖品等级配置错误', [
        'activity_id' => $activity->id,
        'prize_level_id' => $prizeLevel->id,
        'level_rank' => $prizeLevel->level_rank,
        'match_digits' => $matchDigits,
    ]);
    continue; // ✅ 跳过错误配置，而非默认为6
}
```

---

### 问题4: 摇球范围计算逻辑验证 ✅

**位置:** `LotteryBallDrawService.php:139-152`

**代码审查:**
```php
protected static function drawBalls(int $maxTicketNo): array
{
    // 将最大券号转为6位数组
    $maxDigits = str_split(str_pad($maxTicketNo, 6, '0', STR_PAD_LEFT));
    
    $balls = [];
    
    // 从右往左摇球（个位 -> 十万位）
    for ($position = 5; $position >= 0; $position--) {
        $maxDigit = (int)$maxDigits[$position];
        
        // 该位的范围：0 ~ maxDigit
        $balls[$position] = mt_rand(0, $maxDigit);
    }
    
    return [
        'ball1' => $balls[5], // 个位
        'ball2' => $balls[4], // 十位
        'ball3' => $balls[3], // 百位
        'ball4' => $balls[2], // 千位
        'ball5' => $balls[1], // 万位
        'ball6' => $balls[0], // 十万位
        'winning_no' => sprintf('%d%d%d%d%d%d', $balls[0], $balls[1], $balls[2], $balls[3], $balls[4], $balls[5]),
    ];
}
```

**测试验证:**

**测试1: 14张券（000000~000013）**
```
maxTicketNo = 13
str_pad(13, 6) = "000013"
str_split = [0, 0, 0, 0, 1, 3]
位置索引 = [5, 4, 3, 2, 1, 0] (从右往左)

摇球范围：
position=5 (个位): maxDigit = 3, 范围 0~3 ✅
position=4 (十位): maxDigit = 1, 范围 0~1 ✅
position=3 (百位): maxDigit = 0, 范围 0~0 ✅
position=2 (千位): maxDigit = 0, 范围 0~0 ✅
position=1 (万位): maxDigit = 0, 范围 0~0 ✅
position=0 (十万位): maxDigit = 0, 范围 0~0 ✅

可能结果示例：
ball1=2, ball2=1, ball3=0, ball4=0, ball5=0, ball6=0
winning_no = "000012" ✅ (在范围内)

极限测试：
ball1=3, ball2=1, ball3=0, ball4=0, ball5=0, ball6=0
winning_no = "000013" ✅ (最大值，在范围内)

ball1=0, ball2=0, ball3=0, ball4=0, ball5=0, ball6=0
winning_no = "000000" ✅ (最小值，在范围内)
```

**测试2: 999999张券（最大值）**
```
maxTicketNo = 999998 (已发999999张，最后一张是999998)
str_pad(999998, 6) = "999998"
str_split = [9, 9, 9, 9, 9, 8]

摇球范围：
position=5 (个位): 0~8 ✅
position=4 (十位): 0~9 ✅
position=3 (百位): 0~9 ✅
position=2 (千位): 0~9 ✅
position=1 (万位): 0~9 ✅
position=0 (十万位): 0~9 ✅

可能结果：
winning_no = "999998" (最大)
winning_no = "000000" (最小)
winning_no = "543218" (随机)
✅ 都在范围内
```

**测试3: 边界0（1张券）**
```
maxTicketNo = 0 (只发了1张：000000)
str_pad(0, 6) = "000000"
str_split = [0, 0, 0, 0, 0, 0]

摇球范围：
所有位：0~0

结果：
winning_no = "000000" ✅ (唯一结果)
```

**结论:** ✅ 摇球逻辑正确，所有场景都能保证中奖号在已发券范围内

---

### 问题5: 状态流转逻辑死锁风险 ⚠️

**严重等级:** 🟡 低（理论风险）

**位置:** `LotteryActivityStatusTransitionTask.php:95-124`

**问题代码:**
```php
protected function determineNewStatus(LotteryTicketActivity $activity, string $now): int
{
    // 已结束的活动不再流转
    if ($activity->status === LotteryTicketActivity::STATUS_ENDED) {
        return $activity->status;
    }
    
    // 1. 检查是否应该结束
    if ($now >= $activity->end_time) {
        return LotteryTicketActivity::STATUS_ENDED;
    }
    
    // 2. 检查是否应该进入开奖中
    if ($activity->draw_time && $now >= $activity->draw_time) {
        return LotteryTicketActivity::STATUS_DRAWING;
    }
    
    // 3. 检查是否应该进入打码中
    if ($now >= $activity->start_time) {
        return LotteryTicketActivity::STATUS_BETTING;
    }
    
    // 4. 检查是否应该进入预热期
    if ($activity->preheat_start_time && $now >= $activity->preheat_start_time) {
        return LotteryTicketActivity::STATUS_PREHEATING;
    }
    
    // 5. 默认保持未开始状态
    return LotteryTicketActivity::STATUS_NOT_STARTED;
}
```

**潜在问题场景:**

**异常时间配置:**
```
配置错误情况：
- preheat_start_time = 2026-06-10 10:00
- start_time = 2026-06-10 08:00  ← 早于预热期！
- draw_time = 2026-06-10 12:00
- end_time = 2026-06-10 14:00

当前时间：2026-06-10 09:00

判断流程：
1. 检查结束：now < end_time → 跳过
2. 检查开奖：now < draw_time → 跳过
3. 检查打码：now >= start_time (09:00 >= 08:00) → ✅ 返回 STATUS_BETTING
4. 预热期检查被跳过

结果：
- 应该在预热期，但流转到打码期
- 逻辑混乱
```

**修复建议:**
```php
protected function determineNewStatus(LotteryTicketActivity $activity, string $now): int
{
    // 已结束的活动不再流转
    if ($activity->status === LotteryTicketActivity::STATUS_ENDED) {
        return $activity->status;
    }
    
    // ✅ 添加：时间配置合理性验证
    $this->validateActivityTimes($activity);
    
    // 优先级排序：结束 > 开奖 > 打码 > 预热 > 未开始
    // 注意：按时间倒序检查，避免逻辑冲突
    
    // 1. 检查是否应该结束
    if ($now >= $activity->end_time) {
        return LotteryTicketActivity::STATUS_ENDED;
    }
    
    // 2. 检查是否应该进入开奖中
    if ($activity->draw_time && $now >= $activity->draw_time) {
        return LotteryTicketActivity::STATUS_DRAWING;
    }
    
    // 3. 检查是否应该进入打码中
    if ($now >= $activity->start_time) {
        return LotteryTicketActivity::STATUS_BETTING;
    }
    
    // 4. 检查是否应该进入预热期
    if ($activity->preheat_start_time && $now >= $activity->preheat_start_time) {
        return LotteryTicketActivity::STATUS_PREHEATING;
    }
    
    // 5. 默认保持未开始状态
    return LotteryTicketActivity::STATUS_NOT_STARTED;
}

/**
 * 验证活动时间配置合理性
 */
protected function validateActivityTimes(LotteryTicketActivity $activity)
{
    $times = [
        'preheat' => $activity->preheat_start_time,
        'start' => $activity->start_time,
        'draw' => $activity->draw_time,
        'end' => $activity->end_time,
    ];
    
    // 验证：预热 < 开始 < 开奖 < 结束
    if ($times['preheat'] && $times['preheat'] >= $times['start']) {
        Log::warning('活动时间配置异常：预热期晚于开始时间', [
            'activity_id' => $activity->id,
            'preheat' => $times['preheat'],
            'start' => $times['start'],
        ]);
    }
    
    if ($times['draw'] && $times['draw'] <= $times['start']) {
        Log::warning('活动时间配置异常：开奖时间早于开始时间', [
            'activity_id' => $activity->id,
            'start' => $times['start'],
            'draw' => $times['draw'],
        ]);
    }
    
    if ($times['draw'] && $times['draw'] >= $times['end']) {
        Log::warning('活动时间配置异常：开奖时间晚于结束时间', [
            'activity_id' => $activity->id,
            'draw' => $times['draw'],
            'end' => $times['end'],
        ]);
    }
}
```

---

### 问题6: 摇球后券状态未验证 ⚠️

**严重等级:** 🟡 低

**位置:** `LotteryBallDrawService.php:214-218`

**问题代码:**
```php
// 查找匹配的摸奖券
$matchedTickets = LotteryTicket::where('activity_id', $activity->id)
    ->where('status', LotteryTicket::STATUS_UNUSED)  // ← 只检查未使用
    ->where(Db::raw('RIGHT(ticket_no, ' . $matchDigits . ')'), '=', $matchPattern)
    ->limit($prizeCount)
    ->get();
```

**潜在问题:**

**场景：重复开奖（异常情况）**
```
第1次开奖（正常）：
- 中奖券：000012
- 更新状态：STATUS_UNUSED → STATUS_USED ✅

第2次开奖（误操作/BUG）：
- 中奖券：000012
- 查询：where('status', STATUS_UNUSED)
- 结果：无匹配（已被标记为 STATUS_USED）
- 实际：没有产生重复中奖记录 ✅

结论：✅ 当前逻辑已防止重复中奖
```

**但存在另一个问题：过期券**
```
场景：券已过期但未使用

活动配置：
- end_time = 2026-06-10 23:59:59
- expires_at = 2026-06-10 23:59:59

券状态：
- ticket_no = "000012"
- status = STATUS_UNUSED (未使用)
- expires_at = 2026-06-10 23:59:59

开奖时间：2026-06-11 00:00:00

当前查询：
- where('status', STATUS_UNUSED) → 匹配 ✅
- 但券已过期！

问题：
- 过期券仍被匹配为中奖券
- 玩家无法领奖（已过期）
- 浪费奖品名额
```

**修复建议:**
```php
// 查找匹配的摸奖券（排除过期券）
$matchedTickets = LotteryTicket::where('activity_id', $activity->id)
    ->where('status', LotteryTicket::STATUS_UNUSED)
    ->where(function ($query) {
        // ✅ 添加：排除过期券
        $query->whereNull('expires_at')
              ->orWhere('expires_at', '>', date('Y-m-d H:i:s'));
    })
    ->where(Db::raw('RIGHT(ticket_no, ' . $matchDigits . ')'), '=', $matchPattern)
    ->limit($prizeCount)
    ->get();
```

---

## 📊 逻辑完整性检查

### ✅ 已通过的逻辑

1. ✅ **券号自增逻辑** - 严格递增，无跳号
2. ✅ **并发安全** - 行锁保证券号唯一
3. ✅ **券号不足处理** - 自动限制在可用范围
4. ✅ **摇球范围计算** - 保证中奖号在已发券范围
5. ✅ **重复开奖防护** - 已开奖活动无法再开
6. ✅ **进度记录并发** - 已修复（行锁）
7. ✅ **事务管理** - 已修复（无嵌套）

---

### ⚠️ 需要关注的边界情况

1. ⚠️ **扫描任务状态** - 需支持 STATUS_BETTING
2. ⚠️ **摇球数据验证** - 建议验证实际券数
3. ⚠️ **奖品等级配置** - 需验证 level_rank 合法性
4. ⚠️ **时间配置合理性** - 需验证时间顺序
5. ⚠️ **过期券处理** - 开奖时排除过期券

---

## 🎯 修复优先级

### P0 - 必须修复

- [x] ✅ 进度记录并发锁（已修复）
- [ ] ⏳ 扫描任务支持 STATUS_BETTING

### P1 - 建议修复

- [ ] ⏳ 摇球前验证实际券数
- [ ] ⏳ 奖品等级配置验证
- [ ] ⏳ 开奖排除过期券

### P2 - 可选优化

- [ ] ⏳ 时间配置合理性验证
- [ ] ⏳ 添加更多边界检查

---

## 📈 系统健壮性评分

| 维度 | 评分 | 说明 |
|-----|------|------|
| **券号唯一性** | ⭐⭐⭐⭐⭐ (5/5) | 三重保护，100%唯一 |
| **并发安全** | ⭐⭐⭐⭐⭐ (5/5) | 行锁保护，已修复 |
| **逻辑正确性** | ⭐⭐⭐⭐☆ (4/5) | 核心逻辑正确，有边界问题 |
| **容错能力** | ⭐⭐⭐⭐☆ (4/5) | 有防护，但可加强 |
| **数据一致性** | ⭐⭐⭐⭐⭐ (5/5) | 事务保护完善 |

**总体评分:** ⭐⭐⭐⭐☆ (4.5/5)

---

**审核人:** Claude Code  
**审核日期:** 2026-06-10  
**审核版本:** v3.0 - 深度审核版  
**结论:** 核心逻辑正确，券号系统完善，建议修复P0和P1问题
