# 摸奖券系统代码修复清单

## 📋 P1 - 高优先级修复（立即执行）

### 修复1: 队列消费者添加重试机制

**文件:** `addons/webman/queue/LotteryTicketPushQueue.php`

**修改前:**
```php
public function consume($data)
{
    try {
        // ... 推送逻辑
        if ($result) {
            Log::info('摸奖券推送成功');
        } else {
            Log::error('摸奖券推送失败');  // ❌ 不抛出异常
        }
    } catch (\Exception $e) {
        Log::error('异常');  // ❌ 不抛出异常
    }
}
```

**修改后:**
```php
public function consume($data)
{
    $channels = $data['channels'] ?? '';
    $content = $data['content'] ?? [];
    $from = $data['from'] ?? 'lottery_system';

    // 数据格式错误，不重试
    if (empty($channels) || empty($content)) {
        Log::warning('摸奖券推送队列：参数缺失', ['data' => $data]);
        return;  // 正常返回，队列删除此消息
    }

    // 调用推送
    $result = $this->sendSocketMessage($channels, $content, $from);

    if (!$result) {
        // ✅ 推送失败，抛出异常触发重试
        throw new \Exception('推送失败，触发队列重试机制');
    }

    Log::info('摸奖券推送成功', [
        'type' => $content['type'] ?? 'unknown',
        'player_id' => $this->extractPlayerId($channels),
    ]);
}

/**
 * 从频道名称提取玩家ID（用于日志）
 */
protected function extractPlayerId(string $channels): ?int
{
    if (preg_match('/player-(\d+)/', $channels, $matches)) {
        return (int)$matches[1];
    }
    return null;
}
```

---

### 修复2: 推送服务使用活动缓存

**文件:** `addons/webman/service/LotteryTicketPushService.php`

**添加静态缓存:**
```php
class LotteryTicketPushService
{
    // ... 现有常量

    /**
     * 活动缓存（避免批量推送时重复查询）
     * @var array
     */
    protected static $activityCache = [];

    /**
     * 获取活动（带缓存）
     *
     * @param int $activityId 活动ID
     * @return LotteryTicketActivity|null
     */
    protected static function getActivity(int $activityId): ?LotteryTicketActivity
    {
        if (!isset(self::$activityCache[$activityId])) {
            self::$activityCache[$activityId] = LotteryTicketActivity::find($activityId);
        }
        return self::$activityCache[$activityId];
    }

    /**
     * 清除活动缓存（可选，通常不需要）
     */
    public static function clearActivityCache(int $activityId = null)
    {
        if ($activityId) {
            unset(self::$activityCache[$activityId]);
        } else {
            self::$activityCache = [];
        }
    }
}
```

**修改推送方法:**
```php
public static function pushTicketIssued(LotteryTicket $ticket, int $count = 1): bool
{
    try {
        // ✅ 使用缓存
        $activity = self::getActivity($ticket->activity_id);
        if (!$activity) {
            return false;
        }

        $message = [
            'type' => 'ticket_issued',
            'title' => '恭喜獲得摸獎券',
            'message' => sprintf('您在活動「%s」中獲得了 %d 張摸獎券！', $activity->name, $count),
            'data' => [
                'activity_id' => $ticket->activity_id,
                'activity_name' => $activity->name,
                'ticket_id' => $ticket->id,
                'ticket_no' => $ticket->ticket_no,
                'count' => $count,
                'expires_at' => $ticket->expires_at,
            ],
        ];

        return self::pushToPlayer($ticket->player_id, 'lottery_ticket', $message);

    } catch (\Exception $e) {
        Log::error('摸奖券发放推送失败', [
            'ticket_id' => $ticket->id ?? null,
            'error' => $e->getMessage(),
        ]);
        return false;
    }
}

public static function pushWinNotification(LotteryTicketRecord $record): bool
{
    try {
        // ✅ 使用缓存
        $activity = self::getActivity($record->activity_id);
        if (!$activity) {
            return false;
        }

        $message = [
            'type' => 'lottery_win',
            'title' => '🎉 恭喜中獎！',
            'message' => sprintf(
                '您在活動「%s」中獲得 %s - %s 元！',
                $activity->name,
                $record->prize_name,
                number_format($record->prize_amount, 2)
            ),
            'data' => [
                'activity_id' => $record->activity_id,
                'activity_name' => $activity->name,
                'ticket_no' => $record->ticket_no,
                'prize_level' => $record->prize_name,
                'prize_type' => $record->prize_type,
                'prize_amount' => $record->prize_amount,
                'record_id' => $record->id,
            ],
        ];

        return self::pushToPlayer($record->player_id, 'lottery_win', $message);

    } catch (\Exception $e) {
        Log::error('中奖推送失败', [
            'record_id' => $record->id ?? null,
            'error' => $e->getMessage(),
        ]);
        return false;
    }
}
```

---

### 修复3: 限制limit参数最大值

**文件:** `addons/webman/controller/ChannelLotteryTicketStatisticsController.php`

**修改所有使用limit的方法:**

```php
public function getBetRanking()
{
    $activityId = Request::input('activity_id');
    $type = Request::input('type', 'today');
    $limit = Request::input('limit', 10);

    // ✅ 限制范围 1-100
    $limit = min(max(1, (int)$limit), 100);

    $departmentId = Admin::user()->department_id;

    // ... 其余代码不变
}

public function getRecentTickets()
{
    $activityId = Request::input('activity_id');
    $limit = Request::input('limit', 20);

    // ✅ 限制范围 1-100
    $limit = min(max(1, (int)$limit), 100);

    $departmentId = Admin::user()->department_id;

    // ... 其余代码不变
}
```

---

## 📋 P2 - 中优先级修复（尽快执行）

### 修复4: 优化趋势图查询

**文件:** `addons/webman/controller/ChannelLotteryTicketStatisticsController.php`

**修改前:**
```php
$trend = PlayerGameLog::where('department_id', $departmentId)
    ->whereDate('created_at', $date)  // ❌ 函数索引
    ->whereBetween('created_at', [$activity->start_time, $activity->end_time])
    ->select(...)
    ->get();
```

**修改后:**
```php
public function getBetTrend()
{
    $activityId = Request::input('activity_id');
    $date = Request::input('date', date('Y-m-d'));
    $departmentId = Admin::user()->department_id;

    $activity = LotteryTicketActivity::where('id', $activityId)
        ->where('department_id', $departmentId)
        ->first();

    if (!$activity) {
        return response()->json([
            'code' => 404,
            'message' => admin_trans('lottery_ticket.message.activity_not_found')
        ]);
    }

    // ✅ 只使用 whereBetween，可以使用索引
    $startDate = $date . ' 00:00:00';
    $endDate = $date . ' 23:59:59';

    $trend = PlayerGameLog::where('department_id', $departmentId)
        ->whereBetween('created_at', [
            max($startDate, $activity->start_time),
            min($endDate, $activity->end_time)
        ])
        ->select(
            Db::raw('HOUR(created_at) as hour'),
            Db::raw('SUM(chip_amount) as total_bet'),
            Db::raw('COUNT(*) as bet_count')
        )
        ->groupBy('hour')
        ->orderBy('hour')
        ->get();

    // ... 其余代码不变
}
```

---

### 修复5: 批量推送按奖级排序

**文件:** `addons/webman/service/LotteryTicketPushService.php`

**修改前:**
```php
public static function batchPushWinNotifications(array $winnerRecords): int
{
    $successCount = 0;
    $delay = 0;

    foreach ($winnerRecords as $record) {
        // 直接推送，可能顺序混乱
        // ...
    }
}
```

**修改后:**
```php
public static function batchPushWinNotifications(array $winnerRecords): int
{
    // ✅ 按奖金额排序，大奖优先推送
    usort($winnerRecords, function($a, $b) {
        return $b->prize_amount <=> $a->prize_amount;
    });

    $successCount = 0;
    $delay = 0;

    foreach ($winnerRecords as $record) {
        // ✅ 大奖（>=10000）立即推送，小奖使用延迟
        if ($record->prize_amount >= 10000) {
            $pushDelay = 0;  // 立即推送大奖
        } else {
            $pushDelay = $delay;
        }

        if (self::pushWinNotificationWithDelay($record, $pushDelay)) {
            $successCount++;
            // 只对小奖增加延迟
            if ($record->prize_amount < 10000) {
                $delay = floor($successCount / 10);
            }
        }
    }

    Log::info('批量中奖通知已入队', [
        'total' => count($winnerRecords),
        'success' => $successCount,
        'failed' => count($winnerRecords) - $successCount,
        'max_delay' => $delay . 's',
    ]);

    return $successCount;
}
```

---

### 修复6: 打码进度推送频率限制

**文件:** `addons/webman/service/LotteryTicketBetProgressService.php`

**修改前:**
```php
// 统一提交
Db::commit();

// 4. 事务外推送（不阻塞事务）
try {
    // ❌ 每次打码都推送
    LotteryTicketPushService::pushBetProgressUpdate(...);
    
    // 如果发券了，推送发券通知
    if ($issuedCount > 0 && $firstTicketNo) {
        // ...
    }
}
```

**修改后:**
```php
// 统一提交
Db::commit();

// 4. 事务外推送（不阻塞事务）
try {
    $shouldPushProgress = false;

    // ✅ 达标发券时必须推送进度
    if ($issuedCount > 0) {
        $shouldPushProgress = true;

        // 推送发券通知
        $firstTicket = LotteryTicket::where('activity_id', $activity->id)
            ->where('player_id', $progress->player_id)
            ->where('ticket_no', $firstTicketNo)
            ->first();

        if ($firstTicket) {
            LotteryTicketPushService::pushTicketIssued($firstTicket, $issuedCount);
        }

        $results[] = [
            'activity_id' => $progress->activity_id,
            'activity_name' => $activity->name,
            'tickets_issued' => $issuedCount,
            'total_tickets' => $progress->total_tickets_issued,
        ];
    }
    // ✅ 或者进度变化超过5%
    else {
        $oldPercent = 0;
        if ($progress->bet_amount_required > 0) {
            $oldAmount = $progress->current_bet_amount - $chipAmount;
            $oldPercent = floor(($oldAmount / $progress->bet_amount_required) * 100);
        }
        $newPercent = floor($progress->progress_percent);

        if (abs($newPercent - $oldPercent) >= 5) {
            $shouldPushProgress = true;
        }
    }

    // 推送打码进度更新（静默推送）
    if ($shouldPushProgress) {
        LotteryTicketPushService::pushBetProgressUpdate(
            $progress->player_id,
            $activity->id,
            $progress->progress_percent,
            $progress->remaining_bet_amount
        );
    }

} catch (\Exception $e) {
    Log::warning('推送通知失败', [
        'player_id' => $progress->player_id,
        'activity_id' => $activity->id,
        'error' => $e->getMessage(),
    ]);
}
```

---

## 📋 P3 - 低优先级优化（后续执行）

### 优化1: 合并中奖统计查询

**文件:** `addons/webman/controller/ChannelLotteryTicketStatisticsController.php`

**添加新方法:**
```php
/**
 * 获取中奖统计（一次查询）
 */
protected function getWinningStats(int $activityId): array
{
    $stats = LotteryTicketRecord::where('activity_id', $activityId)
        ->selectRaw('
            COUNT(*) as winning_count,
            COUNT(DISTINCT player_id) as winning_players,
            SUM(prize_amount) as total_prize_amount,
            SUM(CASE WHEN status = ? THEN prize_amount ELSE 0 END) as claimed_prize_amount
        ', [LotteryTicketRecord::STATUS_GRANTED])
        ->first();

    return [
        'winning_count' => $stats->winning_count ?? 0,
        'winning_players' => $stats->winning_players ?? 0,
        'total_prize_amount' => $stats->total_prize_amount ?? 0,
        'claimed_prize_amount' => $stats->claimed_prize_amount ?? 0,
    ];
}
```

**修改 getActivityStats:**
```php
public function getActivityStats()
{
    // ... 前面代码不变

    // ✅ 一次查询获取中奖统计
    $winningStats = $this->getWinningStats($activityId);

    $stats = [
        // ... 其他统计

        // 中奖统计（使用合并查询的结果）
        'winning_count' => $winningStats['winning_count'],
        'winning_players' => $winningStats['winning_players'],
        'total_prize_amount' => $winningStats['total_prize_amount'],
        'claimed_prize_amount' => $winningStats['claimed_prize_amount'],
        'prize_by_level' => $this->getPrizeByLevel($activityId),

        // ... 其他统计
    ];

    return response()->json(['code' => 200, 'data' => $stats]);
}
```

---

## ✅ 修复验证清单

### P1 修复验证

- [ ] 队列消费失败时能看到异常日志
- [ ] Redis队列重试机制生效（检查 `max_attempts`）
- [ ] 批量100条推送只查询1次活动表
- [ ] API传入 `limit=9999` 时自动限制为100

### P2 修复验证

- [ ] 趋势图查询使用索引（EXPLAIN分析）
- [ ] 大奖通知比小奖先到达客户端
- [ ] 打码进度不是每次都推送（日志验证）

### P3 优化验证

- [ ] `getActivityStats` 减少1-2次数据库查询
- [ ] 成功日志简洁不占用磁盘

---

## 🚀 部署建议

### 部署顺序

1. **先修复P1问题**（代码逻辑问题）
2. **测试推送功能**（验证重试机制）
3. **修复P2问题**（性能优化）
4. **压力测试**（模拟高并发）
5. **P3优化**（持续改进）

### 回滚方案

如果修复后出现问题：

```bash
# 回滚到上一个版本
git revert HEAD

# 重启服务
php start.php restart
```

---

**修复清单版本:** v1.0  
**创建日期:** 2026-06-10  
**预计修复时间:** 2-4小时  
**风险评估:** 低（主要是优化，不改变核心逻辑）
