# 摸奖券券号发放规则完整说明

## 📜 券号格式规范

**格式:** 6位数字字符串  
**范围:** `000000` ~ `999999`  
**字符集:** 纯数字（0-9）  
**编码:** UTF-8（无特殊字符）

**示例:**
```
000000  ← 第1张券
000001  ← 第2张券
000014  ← 第15张券
000999  ← 第1000张券
099999  ← 第100000张券
999999  ← 第1000000张券（最后一张）
```

---

## 🎯 核心规则

### 规则1: 严格自增

**原则:** 每个活动从 `000000` 开始，依次递增，**永不回退**

**实现:**
```php
// 活动初始状态
$activity->current_ticket_no = 0;  // 已发0张

// 发放第1张
$ticketNo = str_pad(0, 6, '0', STR_PAD_LEFT);  // "000000"
$activity->current_ticket_no = 1;  // 更新为1

// 发放第2张
$ticketNo = str_pad(1, 6, '0', STR_PAD_LEFT);  // "000001"
$activity->current_ticket_no = 2;  // 更新为2

// ...依此类推
```

**保证:**
- ✅ 无跳号：每个号码都会被使用
- ✅ 无重复：每个号码只发一次
- ✅ 可预测：知道已发数量就知道下一张券号

---

### 规则2: 活动隔离

**原则:** 每个活动独立计数，互不影响

**示例:**
```
活动A:
- current_ticket_no = 150
- 已发券：000000~000149
- 下一张：000150

活动B:
- current_ticket_no = 5
- 已发券：000000~000004
- 下一张：000005  ← 与活动A无关

结论：
- 不同活动可以有相同券号
- 通过 (activity_id, ticket_no) 联合唯一
```

**数据库保证:**
```sql
UNIQUE INDEX idx_activity_ticket_no (activity_id, ticket_no)
```

---

### 规则3: 并发安全

**原则:** 多人同时打码，券号绝对不重复

**机制:** 数据库行锁 + 原子更新

**流程:**
```
玩家A和玩家B同时打码达标：

时间线：
T1: 玩家A开始事务 → 锁定活动记录 → current_no = 100
T2: 玩家B开始事务 → 尝试锁定 → 等待...
T3: 玩家A生成券号：100, 101 → 保存 → current_no = 102
T4: 玩家A提交事务 → 释放锁
T5: 玩家B获得锁 → current_no = 102 (最新值)
T6: 玩家B生成券号：102, 103, 104 → 保存 → current_no = 105
T7: 玩家B提交事务 → 释放锁

最终：
- 玩家A：000100, 000101
- 玩家B：000102, 000103, 000104
- current_no = 105
- ✅ 无重复
- ✅ 连续自增
```

**代码实现:**
```php
Db::beginTransaction();

// 锁定活动记录（关键）
$activity = LotteryTicketActivity::where('id', $activityId)
    ->lockForUpdate()  // ← 排他锁
    ->first();

$currentNo = $activity->current_ticket_no;

// 生成券号
for ($i = 0; $i < $count; $i++) {
    $ticketNo = str_pad($currentNo + $i, 6, '0', STR_PAD_LEFT);
    // ...
}

// 原子更新
$activity->current_ticket_no = $currentNo + $count;
$activity->save();

Db::commit();  // 释放锁
```

---

### 规则4: 上限控制

**原则:** 每个活动最多发放 1,000,000 张券

**配置:**
```php
$activity->max_ticket_no = 1000000;  // 默认值
```

**限制逻辑:**
```php
// 检查是否还有券号
$currentNo = $activity->current_ticket_no;  // 例如：999995
$maxNo = $activity->max_ticket_no;          // 1000000

if ($currentNo >= $maxNo) {
    // 券已用完
    return ['issued_count' => 0, 'first_ticket_no' => null];
}

// 计算可发数量
$availableCount = $maxNo - $currentNo;  // 1000000 - 999995 = 5
$actualCount = min($requestCount, $availableCount);  // min(10, 5) = 5

// 只发5张，不是10张
```

**场景示例:**
```
活动配置：max_ticket_no = 1000000

状态1：已发999990张
- current_ticket_no = 999990
- 剩余：10张
- 玩家A应发20张 → 实发10张 ✅

状态2：已发1000000张
- current_ticket_no = 1000000
- 剩余：0张
- 玩家B应发5张 → 实发0张 ✅
- 日志：Log::warning('摸奖券已发放完毕')
```

---

## 🔢 券号计算公式

### 公式1: 已发券数 → 券号范围

```
已发券数 = current_ticket_no

券号范围 = [
    str_pad(0, 6, '0', STR_PAD_LEFT),
    str_pad(current_ticket_no - 1, 6, '0', STR_PAD_LEFT)
]

示例：
current_ticket_no = 15
券号范围 = ["000000", "000014"]  (15张券)
```

### 公式2: 下一张券号

```
下一张券号 = str_pad(current_ticket_no, 6, '0', STR_PAD_LEFT)

示例：
current_ticket_no = 15
下一张券号 = "000015"
```

### 公式3: 最大券号（已发券）

```
最大券号 = str_pad(current_ticket_no - 1, 6, '0', STR_PAD_LEFT)

特殊情况：
if (current_ticket_no == 0) {
    最大券号 = null  (未发任何券)
}

示例：
current_ticket_no = 100
最大券号 = "000099"
```

### 公式4: 剩余券数

```
剩余券数 = max_ticket_no - current_ticket_no

示例：
max_ticket_no = 1000000
current_ticket_no = 50000
剩余券数 = 950000
```

---

## 🎲 摇球开奖规则

### 摇球前提

**条件:** 必须有已发放的券

**验证:**
```php
if ($activity->current_ticket_no <= 0) {
    return '活动未发放任何摸奖券，无法开奖';
}
```

---

### 摇球范围计算

**原理:** 每个球的范围由已发券的最大券号决定

**计算方法:**
```php
// 1. 获取最大券号（数字）
$maxTicketNo = $activity->current_ticket_no - 1;  // 例如：14

// 2. 转为6位字符串
$maxTicketStr = str_pad($maxTicketNo, 6, '0', STR_PAD_LEFT);  // "000014"

// 3. 拆分为数组
$maxDigits = str_split($maxTicketStr);  // [0, 0, 0, 0, 1, 4]

// 4. 确定每个球的范围
球6（十万位）：0 ~ $maxDigits[0]  → 0 ~ 0  ✅
球5（万位）：  0 ~ $maxDigits[1]  → 0 ~ 0  ✅
球4（千位）：  0 ~ $maxDigits[2]  → 0 ~ 0  ✅
球3（百位）：  0 ~ $maxDigits[3]  → 0 ~ 0  ✅
球2（十位）：  0 ~ $maxDigits[4]  → 0 ~ 1  ✅
球1（个位）：  0 ~ $maxDigits[5]  → 0 ~ 4  ✅
```

---

### 摇球示例

**示例1: 已发15张券（000000~000014）**

```
maxTicketNo = 14
maxTicketStr = "000014"
maxDigits = [0, 0, 0, 0, 1, 4]

摇球范围：
球6: 0~0  (只能是0)
球5: 0~0  (只能是0)
球4: 0~0  (只能是0)
球3: 0~0  (只能是0)
球2: 0~1  (可能是0或1)
球1: 0~4  (可能是0, 1, 2, 3, 4)

可能结果：
- 球6=0, 球5=0, 球4=0, 球3=0, 球2=0, 球1=0 → "000000" ✅
- 球6=0, 球5=0, 球4=0, 球3=0, 球2=0, 球1=4 → "000004" ✅
- 球6=0, 球5=0, 球4=0, 球3=0, 球2=1, 球1=3 → "000013" ✅
- 球6=0, 球5=0, 球4=0, 球3=0, 球2=1, 球1=4 → "000014" ✅ (最大值)

所有结果都在 000000~000014 范围内！
```

---

**示例2: 已发12345张券（000000~012344）**

```
maxTicketNo = 12344
maxTicketStr = "012344"
maxDigits = [0, 1, 2, 3, 4, 4]

摇球范围：
球6: 0~0
球5: 0~1
球4: 0~2
球3: 0~3
球2: 0~4
球1: 0~4

可能结果：
- "000000" (最小)
- "012344" (最大)
- "005432" (随机中间值)
- ...所有结果都 <= 012344 ✅
```

---

### 为什么这样设计？

**目标:** 保证所有奖品必中

**分析:**
```
传统随机摇号问题：
- 随机生成6位数：000000~999999
- 已发券：000000~000014 (15张)
- 随机结果可能：888888 ← 无匹配！
- 结果：奖品流失

当前设计优势：
- 摇球结果：基于已发券最大值
- 已发券：000000~000014
- 摇球结果必然：000000~000014
- 结果：必有券匹配 ✅

数学证明：
设已发券范围：[0, N]
摇球范围：每位数字 ≤ N对应位
结果：组合数字 ≤ N
结论：必有券匹配
```

---

## 🏆 中奖匹配规则

### 匹配原则

**规则:** 按奖品等级，从后往前匹配N位数字

| 等级 | 名称 | 匹配位数 | 匹配规则 | 示例（中奖号：000012） |
|-----|------|---------|---------|---------------------|
| 1 | 特等奖 | 6位 | 全部匹配 | 000012 |
| 2 | 一等奖 | 5位 | 后5位 | X00012 |
| 3 | 二等奖 | 4位 | 后4位 | XX0012 |
| 4 | 三等奖 | 3位 | 后3位 | XXX012 |
| 5 | 四等奖 | 2位 | 后2位 | XXXX12 |
| 6 | 五等奖 | 1位 | 后1位 | XXXXX2 |

**X = 任意数字**

---

### 匹配示例

**场景:** 已发15张券（000000~000014），中奖号 = "000012"

**匹配过程:**

**等级1（特等奖）- 匹配6位**
```sql
SELECT * FROM lottery_ticket
WHERE activity_id = 1
  AND status = 0  -- 未使用
  AND RIGHT(ticket_no, 6) = '000012'
LIMIT 1;

结果：ticket_no = "000012" ✅
中奖名额：1人
```

**等级2（一等奖）- 匹配5位**
```sql
SELECT * FROM lottery_ticket
WHERE activity_id = 1
  AND status = 0
  AND RIGHT(ticket_no, 5) = '00012'
LIMIT 3;

结果：ticket_no = "000012" ✅ (已中特等奖，跳过)
实际中奖：0人（券已被特等奖占用）
```

**等级6（五等奖）- 匹配1位**
```sql
SELECT * FROM lottery_ticket
WHERE activity_id = 1
  AND status = 0
  AND RIGHT(ticket_no, 1) = '2'
LIMIT 10;

结果：
- ticket_no = "000002" ✅
- ticket_no = "000012" (已中特等奖，状态=已使用，不匹配)

实际中奖：1人（000002）
```

---

### 重复中奖防护

**机制:** 券一旦中奖，立即标记为已使用

**流程:**
```php
// 1. 查询中奖券（只查未使用）
$matchedTickets = LotteryTicket::where('status', STATUS_UNUSED)
    ->where(Db::raw('RIGHT(ticket_no, ' . $matchDigits . ')'), '=', $matchPattern)
    ->limit($prizeCount)
    ->get();

// 2. 创建中奖记录
foreach ($matchedTickets as $ticket) {
    LotteryTicketRecord::create([...]);
    
    // 3. 立即标记为已使用
    $ticket->update(['status' => STATUS_USED]);
}
```

**保证:**
- ✅ 同一券不会被匹配2次
- ✅ 高等奖优先（先匹配特等奖）
- ✅ 低等奖自动排除高等奖中奖券

---

## 📊 发券统计规则

### 活动级统计

```php
// lottery_ticket_activity 表
current_ticket_no  // 已发券数（核心计数器）
total_tickets      // 总发放数（冗余字段，应与current_ticket_no一致）
used_tickets       // 已使用数（中奖+手动核销）
max_ticket_no      // 最大可发数（默认1000000）
```

**关系:**
```
current_ticket_no = total_tickets  (正常情况)
used_tickets <= current_ticket_no  (已使用 ≤ 已发放)
current_ticket_no <= max_ticket_no (已发放 ≤ 上限)
```

---

### 玩家级统计

```php
// lottery_ticket_bet_progress 表
total_tickets_issued  // 该玩家总共已发券数
cycles_completed      // 已完成周期数
ticket_count_per_cycle // 每周期发券数
```

**关系:**
```
total_tickets_issued = cycles_completed × ticket_count_per_cycle  (理想情况)

实际可能不等（券号用完导致少发）
```

---

## 🔒 安全保证机制

### 1. 唯一性保证

**数据库层:**
```sql
UNIQUE INDEX idx_activity_ticket_no (activity_id, ticket_no)
```

**应用层:**
```php
lockForUpdate()  // 行锁
```

**结果:** 100%唯一

---

### 2. 并发保证

**机制:** 悲观锁 + 事务

**伪代码:**
```php
BEGIN TRANSACTION;
  SELECT ... FOR UPDATE;  // 锁定
  UPDATE ...;              // 修改
COMMIT;                    // 释放
```

**结果:** 串行化并发请求

---

### 3. 原子性保证

**机制:** 数据库事务ACID

**操作:**
- 插入券记录
- 更新活动计数器
- 更新进度记录

**结果:** 全部成功或全部失败

---

## 📝 常见问题

### Q1: 券号会重复吗？

**A:** 不会。三重保护：
1. 数据库唯一索引
2. 行锁串行化
3. 自增算法

---

### Q2: 券号会跳号吗？

**A:** 不会。严格自增，无跳号。

---

### Q3: 券号用完怎么办？

**A:** 自动限制，超出部分不发放，记录日志。

---

### Q4: 能修改已发券号吗？

**A:** 不能。券号一旦生成不可修改（immutable）。

---

### Q5: 删除券会影响计数吗？

**A:** 会导致数据不一致。建议软删除或只标记状态。

---

### Q6: 中奖后券号会重复使用吗？

**A:** 不会。券一旦使用（status=1）不会再被匹配。

---

## 🎯 最佳实践

### 建议1: 合理设置上限

```php
// 小型活动
$activity->max_ticket_no = 10000;  // 1万张

// 中型活动
$activity->max_ticket_no = 100000;  // 10万张

// 大型活动
$activity->max_ticket_no = 1000000;  // 100万张（默认）
```

---

### 建议2: 监控券号使用率

```php
$usageRate = ($activity->current_ticket_no / $activity->max_ticket_no) * 100;

if ($usageRate > 90) {
    // 告警：券号即将用完
    Log::warning('摸奖券使用率超过90%', [
        'activity_id' => $activity->id,
        'usage_rate' => $usageRate,
    ]);
}
```

---

### 建议3: 定期验证数据一致性

```php
// 检查：current_ticket_no == 实际券数
$expected = $activity->current_ticket_no;
$actual = LotteryTicket::where('activity_id', $activity->id)->count();

if ($expected != $actual) {
    Log::error('券号数据不一致', [
        'activity_id' => $activity->id,
        'expected' => $expected,
        'actual' => $actual,
        'diff' => abs($expected - $actual),
    ]);
}
```

---

**文档版本:** v1.0  
**最后更新:** 2026-06-10  
**维护人:** Claude Code
