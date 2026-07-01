# 摸奖券系统关键问题快速修复指南

## 🔴 P0级问题 - 立即修复

### 问题1：券号生成可能重复

**修复文件：** `D:\gk_admin\addons\webman\service\LotteryTicketBetProgressService.php`

**步骤1：替换券号生成方法**

找到第276-281行，替换为：

```php
/**
 * 生成唯一券号
 * 格式：14位数字（玩家ID后4位 + 微秒时间戳6位 + 随机4位）
 * 带重试机制保证唯一性
 *
 * @param int $playerId 玩家ID
 * @return string
 */
protected static function generateTicketNo(int $playerId): string
{
    $maxRetries = 5;
    
    for ($i = 0; $i < $maxRetries; $i++) {
        // 组成：玩家ID后4位 + 微秒时间戳6位 + 随机4位
        $playerSuffix = str_pad($playerId % 10000, 4, '0', STR_PAD_LEFT);
        $microtime = str_pad((int)(microtime(true) * 10000) % 1000000, 6, '0', STR_PAD_LEFT);
        $random = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $ticketNo = $playerSuffix . $microtime . $random;
        
        // 检查唯一性
        if (!LotteryTicket::where('ticket_no', $ticketNo)->exists()) {
            return $ticketNo;
        }
        
        // 微秒级延迟后重试
        usleep(100);
    }
    
    // 失败回退：使用时间戳 + UUID
    return substr(time(), -6) . substr(md5(uniqid($playerId, true)), 0, 8);
}
```

**步骤2：修改调用处**

找到第242行，替换为：

```php
'ticket_no' => self::generateTicketNo($progress->player_id), // 传入玩家ID
```

---

### 问题2：状态检查逻辑不一致

**修复文件1：** `D:\gk_admin\addons\webman\service\LotteryTicketBetProgressService.php`

找到第130行，替换为：

```php
// 检查活动是否仍在进行中（支持新旧两种状态）
$activity = $progress->activity;
if (!$activity || !in_array($activity->status, [
    LotteryTicketActivity::STATUS_ONGOING,  // 旧状态：进行中
    LotteryTicketActivity::STATUS_BETTING,  // 新状态：打码中
])) {
    continue;
}
```

**修复文件2：** `D:\gk_admin\process\LotteryBetProgressScanTask.php`

找到第61-62行，替换为：

```php
// 获取所有进行中的活动（支持新旧两种状态）
$activities = LotteryTicketActivity::whereIn('status', [
    LotteryTicketActivity::STATUS_ONGOING,  // 旧状态
    LotteryTicketActivity::STATUS_BETTING,  // 新状态
])->get();
```

---

## 🟡 P1级问题 - 1周内修复

### 问题3：添加数据库索引

**修复方式：** 直接执行SQL（如果已上线）

```sql
-- 为打码进度表添加性能索引
ALTER TABLE `lottery_ticket_bet_progress`
ADD INDEX `idx_status` (`status`),
ADD INDEX `idx_department` (`department_id`),
ADD INDEX `idx_activity_status` (`activity_id`, `status`);

-- 验证索引
SHOW INDEX FROM `lottery_ticket_bet_progress`;
```

**或创建新的迁移文件：** （如果未上线）

文件：`D:\gk_api\db\migrations\20260610000000_add_indexes_to_lottery_ticket_bet_progress.php`

```php
<?php

use Phinx\Migration\AbstractMigration;

class AddIndexesToLotteryTicketBetProgress extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('lottery_ticket_bet_progress');
        
        // 添加性能索引
        $table->addIndex(['status'], ['name' => 'idx_status'])
              ->addIndex(['department_id'], ['name' => 'idx_department'])
              ->addIndex(['activity_id', 'status'], ['name' => 'idx_activity_status'])
              ->update();
    }
}
```

运行迁移：

```bash
cd D:/gk_api
vendor/bin/phinx migrate
```

---

### 问题4：优化事务范围 + 批量插入

**修复文件：** `D:\gk_admin\addons\webman\service\LotteryTicketBetProgressService.php`

**完整替换 `updateBetProgress` 方法：**

```php
/**
 * 更新玩家的打码进度（优化版）
 *
 * @param int $playerId 玩家ID
 * @param float $chipAmount 本次打码量
 * @param int|null $activityId 指定活动ID
 * @return array 更新结果
 */
public static function updateBetProgress(int $playerId, float $chipAmount, ?int $activityId = null): array
{
    if ($chipAmount <= 0) {
        return ['success' => false, 'message' => '打码量必须大于0'];
    }
    
    $player = Player::find($playerId);
    if (!$player) {
        return ['success' => false, 'message' => '玩家不存在'];
    }
    
    // 查找该玩家参与的所有进行中的活动
    $query = LotteryTicketBetProgress::where('player_id', $playerId)
        ->where('status', LotteryTicketBetProgress::STATUS_ACTIVE);
    
    if ($activityId) {
        $query->where('activity_id', $activityId);
    }
    
    $progressRecords = $query->get();
    
    if ($progressRecords->isEmpty()) {
        // 如果没有进度记录，尝试为玩家创建
        if ($activityId) {
            self::createProgressForPlayer($activityId, $playerId);
            $progressRecords = LotteryTicketBetProgress::where('player_id', $playerId)
                ->where('activity_id', $activityId)
                ->where('status', LotteryTicketBetProgress::STATUS_ACTIVE)
                ->get();
        }
        
        if ($progressRecords->isEmpty()) {
            return ['success' => false, 'message' => '玩家未参与任何进行中的摸奖券活动'];
        }
    }
    
    $results = [];
    
    foreach ($progressRecords as $progress) {
        // 检查活动是否仍在进行中
        $activity = $progress->activity;
        if (!$activity || !in_array($activity->status, [
            LotteryTicketActivity::STATUS_ONGOING,
            LotteryTicketActivity::STATUS_BETTING,
        ])) {
            continue;
        }
        
        // 计算应发券数（事务外计算）
        $oldBetAmount = $progress->current_bet_amount;
        $newBetAmount = $oldBetAmount + $chipAmount;
        $ticketsToIssue = 0;
        
        if ($progress->bet_amount_required > 0) {
            $oldCycles = floor($oldBetAmount / $progress->bet_amount_required);
            $newCycles = floor($newBetAmount / $progress->bet_amount_required);
            $cyclesToIssue = $newCycles - $oldCycles;
            $ticketsToIssue = $cyclesToIssue * $progress->ticket_count_per_cycle;
        }
        
        // 缩小事务范围：只包含关键写操作
        Db::beginTransaction();
        try {
            // 1. 更新进度
            $progress->current_bet_amount = $newBetAmount;
            
            if ($ticketsToIssue > 0) {
                $newCycles = floor($newBetAmount / $progress->bet_amount_required);
                $progress->cycles_completed = $newCycles;
                $progress->total_tickets_issued += $ticketsToIssue;
                $progress->last_issued_at = date('Y-m-d H:i:s');
            }
            
            $progress->save();
            
            // 2. 批量插入摸奖券（一次性插入）
            if ($ticketsToIssue > 0) {
                $now = date('Y-m-d H:i:s');
                $ticketsData = [];
                
                for ($i = 0; $i < $ticketsToIssue; $i++) {
                    $ticketsData[] = [
                        'activity_id' => $progress->activity_id,
                        'player_id' => $progress->player_id,
                        'department_id' => $progress->department_id,
                        'ticket_no' => self::generateTicketNo($progress->player_id),
                        'source' => 'betting',
                        'status' => LotteryTicket::STATUS_UNUSED,
                        'expires_at' => $activity->end_time,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                
                LotteryTicket::insert($ticketsData);
            }
            
            Db::commit();
            
            // 3. 事务外执行推送（不阻塞事务）
            if ($ticketsToIssue > 0) {
                try {
                    $firstTicket = LotteryTicket::where('player_id', $progress->player_id)
                        ->where('activity_id', $progress->activity_id)
                        ->orderBy('id', 'desc')
                        ->first();
                    
                    if ($firstTicket) {
                        LotteryTicketPushService::pushTicketIssued($firstTicket, $ticketsToIssue);
                    }
                } catch (\Exception $e) {
                    Log::warning('推送通知失败（不影响发券）', [
                        'player_id' => $playerId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            $results[] = [
                'activity_id' => $progress->activity_id,
                'activity_name' => $activity->name,
                'tickets_issued' => $ticketsToIssue,
                'total_tickets' => $progress->total_tickets_issued,
            ];
            
        } catch (\Exception $e) {
            Db::rollBack();
            Log::error('更新打码进度失败', [
                'player_id' => $playerId,
                'activity_id' => $progress->activity_id,
                'chip_amount' => $chipAmount,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }
    
    return [
        'success' => true,
        'message' => '打码进度更新成功',
        'results' => $results
    ];
}
```

---

### 问题6：扫描任务异常恢复机制

**修复文件：** `D:\gk_admin\process\LotteryBetProgressScanTask.php`

**完整替换 `scanAndUpdateBetProgress` 方法：**

```php
/**
 * 扫描并更新打码进度（带锁优化版）
 */
protected function scanAndUpdateBetProgress()
{
    $lockKey = self::CACHE_KEY_TASK_STATUS;
    $lockValue = uniqid('scan_', true);
    $lockTTL = 120; // 锁超时2分钟
    
    try {
        // 尝试获取分布式锁
        $locked = Cache::add($lockKey, $lockValue, $lockTTL);
        if (!$locked) {
            Log::warning('摸奖券打码进度扫描任务正在执行，跳过本次');
            return;
        }
        
        $startTime = microtime(true);
        
        // 获取所有进行中的活动
        $activities = LotteryTicketActivity::whereIn('status', [
            LotteryTicketActivity::STATUS_ONGOING,
            LotteryTicketActivity::STATUS_BETTING,
        ])->get();
        
        if ($activities->isEmpty()) {
            Log::debug('暂无进行中的摸奖券活动');
            return;
        }
        
        // 获取上次扫描时间
        $lastScanTime = Cache::get(self::CACHE_KEY_LAST_SCAN);
        if (!$lastScanTime) {
            $lastScanTime = date('Y-m-d H:i:s', strtotime('-5 minutes'));
        }
        
        $currentTime = date('Y-m-d H:i:s');
        $totalPlayersUpdated = 0;
        $totalTicketsIssued = 0;
        
        foreach ($activities as $activity) {
            // 确保只处理活动期间的数据
            $scanStart = max($lastScanTime, $activity->start_time);
            $scanEnd = min($currentTime, $activity->end_time);
            
            if ($scanStart >= $scanEnd) {
                continue;
            }
            
            // 批量查询并聚合玩家打码量
            $playerBetAmounts = $this->getPlayerBetAmounts(
                $activity->department_id,
                $scanStart,
                $scanEnd
            );
            
            if (empty($playerBetAmounts)) {
                continue;
            }
            
            // 批量更新打码进度
            $result = $this->batchUpdateProgress($activity->id, $playerBetAmounts);
            $totalPlayersUpdated += $result['players_count'];
            $totalTicketsIssued += $result['tickets_issued'];
            
            Log::info('摸奖券打码进度扫描 - 活动处理完成', [
                'activity_id' => $activity->id,
                'activity_name' => $activity->name,
                'players_updated' => $result['players_count'],
                'tickets_issued' => $result['tickets_issued'],
                'time_range' => [$scanStart, $scanEnd],
            ]);
        }
        
        // 更新扫描时间
        Cache::set(self::CACHE_KEY_LAST_SCAN, $currentTime, 86400);
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        Log::info('摸奖券打码进度扫描完成', [
            'activities_count' => $activities->count(),
            'players_updated' => $totalPlayersUpdated,
            'tickets_issued' => $totalTicketsIssued,
            'duration_ms' => $duration,
            'time_range' => [$lastScanTime, $currentTime],
        ]);
        
    } catch (\Exception $e) {
        Log::error('摸奖券打码进度扫描失败', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    } finally {
        // 只有持有锁的进程才能删除（防止误删）
        if (isset($lockValue) && Cache::get($lockKey) === $lockValue) {
            Cache::delete($lockKey);
        }
    }
}
```

---

## ✅ 修复验证清单

修复完成后，请按以下清单验证：

### 券号唯一性验证

```bash
# 1. 重启服务
php start.php restart

# 2. 在数据库中检查是否有重复券号
mysql -u root -p
```

```sql
SELECT ticket_no, COUNT(*) as count
FROM lottery_ticket
GROUP BY ticket_no
HAVING count > 1;
-- 应该返回空结果（无重复）
```

### 状态检查验证

```bash
# 查看日志，确认新状态也能触发发券
tail -f runtime/logs/webman.log | grep "摸奖券打码进度扫描"
```

### 索引性能验证

```sql
-- 查看查询是否使用索引
EXPLAIN SELECT * FROM lottery_ticket_bet_progress
WHERE activity_id = 1 AND status = 1;

-- 应该看到 key 列显示 'idx_activity_status'
```

### 事务优化验证

```bash
# 观察日志中的执行时间
tail -f runtime/logs/webman.log | grep "duration_ms"

# 发券数量较多时，duration_ms 应该明显降低
```

---

## 🚀 快速修复脚本（一键应用）

创建文件：`apply_critical_fixes.sh`

```bash
#!/bin/bash

echo "=== 摸奖券系统关键修复脚本 ==="
echo ""

# 1. 添加数据库索引
echo "[1/3] 添加数据库索引..."
mysql -u root -p <<SQL
USE yjb_platform;

ALTER TABLE \`lottery_ticket_bet_progress\`
ADD INDEX \`idx_status\` (\`status\`),
ADD INDEX \`idx_department\` (\`department_id\`),
ADD INDEX \`idx_activity_status\` (\`activity_id\`, \`status\`);

SELECT 'Indexes added successfully!' AS status;
SQL

echo "✅ 数据库索引添加完成"
echo ""

# 2. 重启 gk_admin 服务
echo "[2/3] 重启 gk_admin 服务..."
cd D:/gk_admin
php start.php restart

echo "✅ 服务重启完成"
echo ""

# 3. 验证修复
echo "[3/3] 验证修复结果..."

# 检查索引
mysql -u root -p -e "USE yjb_platform; SHOW INDEX FROM lottery_ticket_bet_progress WHERE Key_name LIKE 'idx_%';"

# 检查进程
php start.php status | grep lottery

echo ""
echo "=== 修复完成！==="
echo "请查看上方输出，确认："
echo "1. 索引已添加（idx_status, idx_department, idx_activity_status）"
echo "2. 进程正常运行（lottery_bet_progress_scan, lottery_activity_status_transition）"
```

---

## 📋 修复后验证报告

修复完成后，填写此表格：

| 修复项 | 状态 | 验证结果 | 备注 |
|--------|------|---------|------|
| 券号生成方法 | ☑️ / ☐ | | |
| 状态检查逻辑 | ☑️ / ☐ | | |
| 数据库索引 | ☑️ / ☐ | | |
| 事务优化 | ☑️ / ☐ | | |
| 异常恢复机制 | ☑️ / ☐ | | |

**验证人：** _____________  
**验证日期：** _____________  
**系统上线日期：** _____________

---

**作者:** Claude Code  
**创建日期:** 2026-06-09  
**版本:** 1.0
