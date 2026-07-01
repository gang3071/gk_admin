# 摸奖券打码进度集成指南（gk_work 集成）

## 🎯 核心问题

**问题:** 游戏记录在 gk_work 项目批量插入数据库，gk_admin 的模型事件监听无法触发。

**解决:** 在 gk_work 批量插入完成后，调用打码进度更新服务。

---

## 📋 集成方案对比

### 方案 A：gk_work 批量处理后直接调用（推荐）⭐⭐⭐⭐⭐

**优点:**
- ✅ 实时性好（批量插入后立即处理）
- ✅ 性能好（批量聚合，单次处理）
- ✅ 可靠性高（同一事务）
- ✅ 无额外依赖

**实现方式:**

```php
// 在 gk_work 项目中，批量插入游戏记录后

namespace app\service;

use addons\webman\model\PlayerGameLog;
use support\Db;

class GameLogService
{
    /**
     * 批量插入游戏记录
     */
    public function batchInsertGameLogs(array $gameLogs)
    {
        Db::beginTransaction();
        try {
            // 1. 批量插入游戏记录
            PlayerGameLog::insert($gameLogs);
            
            // 2. 聚合玩家打码量
            $playerBetAmounts = $this->aggregatePlayerBetAmount($gameLogs);
            
            // 3. 批量更新打码进度
            $this->updateLotteryBetProgress($playerBetAmounts);
            
            Db::commit();
        } catch (\Exception $e) {
            Db::rollBack();
            throw $e;
        }
    }
    
    /**
     * 聚合玩家打码量
     * @param array $gameLogs
     * @return array ['player_id' => total_chip_amount]
     */
    protected function aggregatePlayerBetAmount(array $gameLogs): array
    {
        $result = [];
        foreach ($gameLogs as $log) {
            $playerId = $log['player_id'];
            $chipAmount = $log['chip_amount'] ?? 0;
            
            if ($chipAmount > 0) {
                if (!isset($result[$playerId])) {
                    $result[$playerId] = 0;
                }
                $result[$playerId] += $chipAmount;
            }
        }
        return $result;
    }
    
    /**
     * 批量更新摸奖券打码进度
     * @param array $playerBetAmounts
     */
    protected function updateLotteryBetProgress(array $playerBetAmounts)
    {
        if (empty($playerBetAmounts)) {
            return;
        }
        
        // 方式1: 直接调用服务（需要引入 gk_admin 的代码）
        foreach ($playerBetAmounts as $playerId => $totalChipAmount) {
            \addons\webman\service\LotteryTicketBetProgressService::updateBetProgress(
                $playerId,
                $totalChipAmount
            );
        }
        
        // 方式2: 发送到 Redis 队列（推荐，解耦）
        foreach ($playerBetAmounts as $playerId => $totalChipAmount) {
            \Webman\RedisQueue\Client::send('lottery-bet-progress', [
                'player_id' => $playerId,
                'chip_amount' => $totalChipAmount,
                'batch' => true,  // 标记为批量处理
            ]);
        }
    }
}
```

**优化：批量聚合处理**

```php
// 示例：100条游戏记录，涉及10个玩家
$gameLogs = [
    ['player_id' => 1, 'chip_amount' => 1000],
    ['player_id' => 1, 'chip_amount' => 2000],  // 同一玩家
    ['player_id' => 2, 'chip_amount' => 500],
    ['player_id' => 1, 'chip_amount' => 1500],  // 同一玩家
    // ... 100条
];

// 聚合后只需10次更新，而不是100次
$aggregated = [
    1 => 4500,  // 玩家1总打码
    2 => 500,   // 玩家2总打码
    // ... 10个玩家
];
```

---

### 方案 B：定时任务扫描增量数据 ⭐⭐⭐

**优点:**
- ✅ 完全解耦，不修改 gk_work
- ✅ 失败可重试
- ✅ 可控制执行频率

**缺点:**
- ⚠️ 有延迟（例如每分钟执行）
- ⚠️ 需要记录上次处理位置

**实现方式:**

```php
// gk_admin/process/LotteryBetProgressScanTask.php

namespace process;

use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketBetProgress;
use addons\webman\model\PlayerGameLog;
use addons\webman\service\LotteryTicketBetProgressService;
use support\Log;
use Workerman\Crontab\Crontab;
use Workerman\Timer;
use Workerman\Worker;

class LotteryBetProgressScanTask
{
    public function onWorkerStart(Worker $worker)
    {
        // 每分钟执行一次
        new Crontab('* * * * *', function () {
            $this->scanAndUpdateBetProgress();
        });
    }
    
    protected function scanAndUpdateBetProgress()
    {
        try {
            // 获取所有进行中的活动
            $activities = LotteryTicketActivity::where('status', LotteryTicketActivity::STATUS_ONGOING)
                ->get();
            
            if ($activities->isEmpty()) {
                return;
            }
            
            // 获取上次扫描时间（从缓存读取）
            $lastScanTime = \support\Cache::get('lottery_bet_scan_time') ?? date('Y-m-d H:i:s', strtotime('-5 minutes'));
            $currentTime = date('Y-m-d H:i:s');
            
            foreach ($activities as $activity) {
                // 查询该活动期间的新增游戏记录
                $gameLogs = PlayerGameLog::where('department_id', $activity->department_id)
                    ->where('created_at', '>=', max($lastScanTime, $activity->start_time))
                    ->where('created_at', '<', $currentTime)
                    ->where('chip_amount', '>', 0)
                    ->select(['player_id', \support\Db::raw('SUM(chip_amount) as total_chip')])
                    ->groupBy('player_id')
                    ->get();
                
                // 批量更新打码进度
                foreach ($gameLogs as $log) {
                    LotteryTicketBetProgressService::updateBetProgress(
                        $log->player_id,
                        $log->total_chip,
                        $activity->id
                    );
                }
                
                Log::info('摸奖券打码进度扫描完成', [
                    'activity_id' => $activity->id,
                    'players_updated' => $gameLogs->count(),
                    'time_range' => [$lastScanTime, $currentTime]
                ]);
            }
            
            // 更新扫描时间
            \support\Cache::set('lottery_bet_scan_time', $currentTime, 86400);
            
        } catch (\Exception $e) {
            Log::error('摸奖券打码进度扫描失败: ' . $e->getMessage());
        }
    }
}
```

**配置:**

```php
// config/process.php
'lottery_bet_scan' => [
    'handler' => process\LotteryBetProgressScanTask::class,
    'reloadable' => true,
],
```

---

### 方案 C：MySQL 触发器（不推荐）⚠️

**仅作参考，不建议使用**

```sql
-- 创建触发器（性能影响大）
DELIMITER $$

CREATE TRIGGER update_lottery_bet_progress
AFTER INSERT ON player_game_log
FOR EACH ROW
BEGIN
    -- 调用存储过程更新打码进度
    CALL update_bet_progress_proc(NEW.player_id, NEW.chip_amount);
END$$

DELIMITER ;
```

**缺点:**
- ❌ 严重影响插入性能
- ❌ 批量插入时触发器执行次数 = 插入行数
- ❌ 难以调试和维护
- ❌ 不支持复杂业务逻辑

---

## 🎯 推荐实施方案

### 阶段 1: 定时任务（快速实施）

**优先使用方案B**，立即可用：

1. 在 gk_admin 创建定时扫描任务
2. 每分钟扫描一次新增游戏记录
3. 批量聚合后更新打码进度
4. 延迟约 1-2 分钟（可接受）

**实施步骤:**

```bash
# 1. 创建任务文件
# 文件已在上面提供: process/LotteryBetProgressScanTask.php

# 2. 配置进程
# 在 config/process.php 添加配置

# 3. 重启服务
php start.php restart

# 4. 查看日志
tail -f runtime/logs/webman.log | grep "摸奖券打码进度扫描"
```

---

### 阶段 2: gk_work 集成（长期方案）

在 gk_work 项目批量插入游戏记录的地方，添加集成代码：

**位置:** gk_work 批量插入游戏记录的方法

```php
// ========== 示例集成代码 ==========

// 批量插入游戏记录
PlayerGameLog::insert($gameLogs);

// 聚合玩家打码量
$playerBetAmounts = [];
foreach ($gameLogs as $log) {
    $playerId = $log['player_id'];
    $chipAmount = $log['chip_amount'] ?? 0;
    if ($chipAmount > 0) {
        $playerBetAmounts[$playerId] = ($playerBetAmounts[$playerId] ?? 0) + $chipAmount;
    }
}

// 发送到队列异步处理（推荐）
foreach ($playerBetAmounts as $playerId => $totalChip) {
    \Webman\RedisQueue\Client::send('lottery-bet-progress', [
        'player_id' => $playerId,
        'chip_amount' => $totalChip,
        'source' => 'gk_work_batch',
    ]);
}

// 或者直接调用服务（如果 gk_work 引入了 gk_admin 的代码）
// foreach ($playerBetAmounts as $playerId => $totalChip) {
//     \addons\webman\service\LotteryTicketBetProgressService::updateBetProgress($playerId, $totalChip);
// }
```

---

## 📊 方案对比总结

| 方案 | 实施难度 | 性能影响 | 实时性 | 可靠性 | 推荐度 |
|------|---------|---------|-------|-------|-------|
| **定时任务扫描** | 低 | 低 | 中（1-2分钟）| 高 | ⭐⭐⭐⭐ 立即可用 |
| **gk_work 集成** | 中 | 低 | 高（秒级）| 高 | ⭐⭐⭐⭐⭐ 长期方案 |
| **MySQL 触发器** | 中 | 高 | 高 | 低 | ⭐ 不推荐 |

---

## 🛠️ 监控和调试

### 1. 检查扫描任务是否运行

```bash
# 查看进程
php start.php status | grep lottery

# 查看日志
tail -f runtime/logs/webman.log | grep "摸奖券"
```

### 2. 手动触发扫描（调试用）

```php
// 在 gk_admin 项目中执行
php -r "
require_once __DIR__ . '/vendor/autoload.php';
\$task = new \process\LotteryBetProgressScanTask();
\$task->scanAndUpdateBetProgress();
"
```

### 3. 查看队列积压

```bash
redis-cli LLEN lottery-bet-progress
```

---

## ⚠️ 注意事项

### 1. 避免重复处理

定时任务需要记录上次扫描时间：

```php
// 使用缓存记录
$lastScanTime = Cache::get('lottery_bet_scan_time');
$currentTime = date('Y-m-d H:i:s');

// 只查询增量数据
->where('created_at', '>=', $lastScanTime)
->where('created_at', '<', $currentTime)

// 更新扫描时间
Cache::set('lottery_bet_scan_time', $currentTime);
```

### 2. 处理活动时间范围

```php
// 只处理活动期间的游戏记录
->where('created_at', '>=', $activity->start_time)
->where('created_at', '<=', $activity->end_time)
```

### 3. 性能优化

```php
// 批量聚合，减少数据库操作
SELECT player_id, SUM(chip_amount) as total_chip
FROM player_game_log
WHERE created_at >= ? AND created_at < ?
GROUP BY player_id
```

---

## ✅ 完成标记

- [ ] 实施定时任务扫描（方案B）
- [ ] 在 gk_work 添加集成代码（方案A）
- [ ] 配置队列消费者
- [ ] 测试验证
- [ ] 监控告警

---

**作者:** Claude Code  
**日期:** 2026-06-09  
**版本:** 2.0 - gk_work 集成方案
