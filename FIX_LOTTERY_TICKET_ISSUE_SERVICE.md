# 摸奖券发券服务统一修复方案

## 问题诊断

### 两套并行的发券逻辑

1. **打码进度自动发券**：`LotteryTicketBetProgressService::issueTickets()`
   - 使用数据库字段 `LotteryTicketActivity::current_ticket_no`
   - 批量插入，锁定活动记录

2. **手动/活动发券**：`LotteryTicketIssueService::issueTickets()`
   - 使用Redis序列号 `lottery_activity:{id}:ticket_sequence`
   - 单条插入，唯一约束重试

### 导致的数据不一致

| 字段 | 打码发券 | 手动发券 | 结果 |
|------|----------|----------|------|
| `current_ticket_no` | ✅ 更新 | ❌ 不更新 | 不准确 |
| Redis序列号 | ❌ 不用 | ✅ 更新 | 不准确 |
| 实际券数 | ✅ 插入DB | ✅ 插入DB | **唯一准确的来源** |

### 当前状态验证

```sql
-- 查看活动2的券号分配情况
SELECT 
    a.id,
    a.current_ticket_no AS 数据库字段,
    COUNT(t.id) AS 实际券数,
    MAX(CAST(t.ticket_no AS UNSIGNED)) + 1 AS 实际最大序列号
FROM yjb_lottery_ticket_activity a
LEFT JOIN yjb_lottery_ticket t ON t.activity_id = a.id
WHERE a.id = 2
GROUP BY a.id;

-- 查看Redis序列号
redis-cli GET lottery_activity:2:ticket_sequence
```

## 解决方案

### 方案A：统一使用Redis序列号（推荐）

**优势：**
- ✅ 高性能（Redis原子操作）
- ✅ 并发安全（无需锁表）
- ✅ 批量插入友好（预分配序列号）
- ✅ 容量检查简单

**实现：**

1. **修改 `LotteryTicketBetProgressService::issueTickets()`**
   - 从调用 `LotteryTicket::insert()` 改为调用 `LotteryTicketIssueService::issueTickets()`
   - 移除活动锁定和 `current_ticket_no` 更新

2. **优化 `LotteryTicketIssueService::issueTickets()`**
   - 支持批量发券（预分配Redis序列号）
   - 保留唯一约束重试机制

3. **废弃 `LotteryTicketActivity::current_ticket_no`**
   - 标记为废弃字段（数据库迁移添加注释）
   - 所有容量检查使用Redis序列号

### 方案B：统一使用数据库字段（不推荐）

**劣势：**
- ❌ 需要锁定活动记录（并发瓶颈）
- ❌ 批量插入需要手动分配序列号
- ❌ 容量检查需要查询数据库

## 实施计划

### 第1步：修复 `LotteryTicketBetProgressService`

**文件：** `addons/webman/service/LotteryTicketBetProgressService.php`

**修改点：**

```php
// 原代码（行206）
$issueResult = self::issueTickets($progress, $ticketsToIssue);

// 修改为：
$issueService = new LotteryTicketIssueService();
$tickets = $issueService->issueTicketsBatch(
    $progress->activity_id,
    $progress->player_id,
    $ticketsToIssue,
    'betting'  // 来源：打码
);
$issueResult = [
    'issued_count' => count($tickets),
    'first_ticket_no' => $tickets[0]->ticket_no ?? null
];
```

### 第2步：新增批量发券方法

**文件：** `addons/webman/service/LotteryTicketIssueService.php`

**新增方法：**

```php
/**
 * 批量发放奖券（性能优化版）
 * 预分配Redis序列号，批量插入数据库
 *
 * @param int $activityId 活动ID
 * @param int $playerId 玩家ID
 * @param int $count 发放数量
 * @param string $source 来源
 * @return array 发放的奖券列表
 * @throws \Exception
 */
public function issueTicketsBatch(int $activityId, int $playerId, int $count, string $source = LotteryTicket::SOURCE_BETTING): array
{
    if ($count <= 0) {
        throw new \Exception('发放数量必须大于0');
    }

    // 检查活动剩余容量
    $remaining = $this->getRemainingCapacity($activityId);
    if ($remaining <= 0) {
        throw new \Exception('活动奖券编号已用尽，无法发放');
    }

    $actualCount = min($count, $remaining);

    // 获取活动信息
    $activity = LotteryTicketActivity::find($activityId);
    if (!$activity) {
        throw new \Exception('活动不存在');
    }

    if ($activity->status !== LotteryTicketActivity::STATUS_ONGOING) {
        throw new \Exception('活动未进行中，无法发券');
    }

    // ⭐ 预分配Redis序列号（批量，原子操作）
    $key = "lottery_activity:{$activityId}:ticket_sequence";
    $baseSequence = Redis::incrby($key, $actualCount);  // 一次性分配多个序列号
    $startSequence = $baseSequence - $actualCount + 1;

    // 检查是否超过上限
    if ($baseSequence > 999999) {
        // 回退Redis计数
        Redis::decrby($key, $actualCount);
        throw new \Exception('活动奖券编号已用尽（超过100万张）');
    }

    try {
        Db::beginTransaction();

        // 批量准备券数据
        $ticketsData = [];
        $now = date('Y-m-d H:i:s');

        for ($i = 0; $i < $actualCount; $i++) {
            $sequence = $startSequence + $i;
            $ticketNo = str_pad($sequence, 6, '0', STR_PAD_LEFT);

            $ticketsData[] = [
                'activity_id' => $activityId,
                'player_id' => $playerId,
                'department_id' => $activity->department_id,
                'ticket_no' => $ticketNo,
                'status' => LotteryTicket::STATUS_UNUSED,
                'source' => $source,
                'issued_at' => $now,
                'expired_at' => $activity->end_time,
                'prize_level' => null,
                'prize_amount' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // 批量插入（失败会抛出异常）
        LotteryTicket::insert($ticketsData);

        Db::commit();

        // 查询插入的奖券（用于返回模型对象）
        $tickets = LotteryTicket::query()
            ->where('activity_id', $activityId)
            ->where('player_id', $playerId)
            ->whereBetween('ticket_no', [
                str_pad($startSequence, 6, '0', STR_PAD_LEFT),
                str_pad($baseSequence, 6, '0', STR_PAD_LEFT)
            ])
            ->orderBy('ticket_no')
            ->get()
            ->toArray();

        Log::info('[摸奖券] 批量发放成功', [
            'activity_id' => $activityId,
            'player_id' => $playerId,
            'count' => $actualCount,
            'sequence_range' => "{$startSequence}-{$baseSequence}",
            'source' => $source
        ]);

        // 清除玩家有效奖券缓存
        $this->clearPlayerTicketCache($playerId);

        return $tickets;

    } catch (\Exception $e) {
        Db::rollBack();

        // 回退Redis序列号（避免序列号浪费）
        Redis::decrby($key, $actualCount);

        Log::error('[摸奖券] 批量发放失败，已回退Redis序列号', [
            'activity_id' => $activityId,
            'player_id' => $playerId,
            'count' => $actualCount,
            'sequence_range' => "{$startSequence}-{$baseSequence}",
            'error' => $e->getMessage()
        ]);

        throw $e;
    }
}
```

### 第3步：删除旧的发券方法

**文件：** `addons/webman/service/LotteryTicketBetProgressService.php`

**删除方法：** `protected static function issueTickets()` (行374-446)

### 第4步：数据库迁移（废弃字段）

**文件：** `database/phinx_migrations/20260623_deprecate_current_ticket_no.php`

```php
<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DeprecateCurrentTicketNo extends AbstractMigration
{
    public function change(): void
    {
        $this->table('yjb_lottery_ticket_activity')
            ->changeColumn('current_ticket_no', 'integer', [
                'comment' => '当前券号（已废弃，使用Redis序列号）',
                'signed' => false,
                'default' => 0,
            ])
            ->update();
    }
}
```

### 第5步：数据修复脚本

**目的：** 同步现有活动的Redis序列号与实际券数

```php
<?php
// database/fix_lottery_ticket_sequence.php

use addons\webman\model\LotteryTicket;
use addons\webman\model\LotteryTicketActivity;
use support\Redis;

$activities = LotteryTicketActivity::all();

foreach ($activities as $activity) {
    // 统计实际券数
    $actualCount = LotteryTicket::where('activity_id', $activity->id)->count();
    
    // 更新Redis序列号
    $key = "lottery_activity:{$activity->id}:ticket_sequence";
    Redis::set($key, $actualCount);
    
    echo "活动 {$activity->id}：实际券数 {$actualCount}，Redis序列号已更新\n";
}

echo "数据修复完成\n";
```

运行命令：
```bash
php -f database/fix_lottery_ticket_sequence.php
```

## 测试计划

### 测试1：打码进度自动发券

1. 启动活动，初始化玩家进度
2. 模拟玩家打码（调用 `updateBetProgress()`）
3. 验证：
   - ✅ 达标后自动发券
   - ✅ Redis序列号正确递增
   - ✅ 券号无重复
   - ✅ 进度记录正确更新

### 测试2：手动发券

1. 在后台手动发券
2. 验证：
   - ✅ Redis序列号正确递增
   - ✅ 与打码发券的券号不冲突

### 测试3：并发压测

1. 并发10个玩家同时打码
2. 验证：
   - ✅ 券号无重复
   - ✅ Redis序列号连续（允许事务回滚导致的跳号）

### 测试4：容量限制

1. 设置活动接近999999上限
2. 触发打码发券
3. 验证：
   - ✅ 超出容量时抛出异常
   - ✅ Redis序列号回退
   - ✅ 数据库无脏数据

## 回滚计划

如果修复后出现问题，可以快速回滚：

1. 恢复 `LotteryTicketBetProgressService::issueTickets()` 方法
2. 运行SQL更新 `current_ticket_no`：
   ```sql
   UPDATE yjb_lottery_ticket_activity a
   SET current_ticket_no = (
       SELECT COALESCE(MAX(CAST(t.ticket_no AS UNSIGNED)), 0)
       FROM yjb_lottery_ticket t
       WHERE t.activity_id = a.id
   );
   ```
3. 清除Redis序列号：
   ```bash
   redis-cli KEYS "lottery_activity:*:ticket_sequence" | xargs redis-cli DEL
   ```
