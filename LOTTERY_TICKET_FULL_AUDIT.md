# 摸奖券系统全流程审查报告

**审查日期:** 2026-06-10  
**审查范围:** 后台管理 → 业务逻辑 → API → 客户端  
**审查目标:** 需求符合性、逻辑完整性、性能问题、安全隐患

---

## 📋 目录

1. [业务流程完整性审查](#业务流程完整性审查)
2. [数据模型审查](#数据模型审查)
3. [逻辑漏洞审查](#逻辑漏洞审查)
4. [性能问题审查](#性能问题审查)
5. [安全问题审查](#安全问题审查)
6. [边界情况审查](#边界情况审查)
7. [问题汇总与建议](#问题汇总与建议)

---

## 🔍 业务流程完整性审查

### 完整业务流程

```
┌─────────────────────────────────────────────────────────────┐
│                    1. 后台管理（gk_admin）                    │
└─────────────────────────────────────────────────────────────┘
                           │
                           ▼
    ┌──────────────────────────────────────────┐
    │ 1.1 渠道创建活动                          │
    │ ├─ 设置活动名称、时间、封面               │
    │ ├─ 配置奖品等级（一等奖、二等奖...）      │
    │ ├─ 配置VIP打码规则                       │
    │ └─ 状态: NOT_STARTED (0)                │
    └──────────────────────────────────────────┘
                           │
                           ▼
    ┌──────────────────────────────────────────┐
    │ 1.2 活动预热                              │
    │ ├─ 手动修改状态 → PREHEATING (1)         │
    │ └─ 玩家端开始显示倒计时                   │
    └──────────────────────────────────────────┘
                           │
                           ▼
    ┌──────────────────────────────────────────┐
    │ 1.3 活动进行中                            │
    │ ├─ 手动修改状态 → ONGOING (3)            │
    │ └─ 玩家开始打码获取奖券                   │
    └──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                    2. 业务逻辑层（gk_admin）                  │
└─────────────────────────────────────────────────────────────┘
                           │
                           ▼
    ┌──────────────────────────────────────────┐
    │ 2.1 玩家打码（自动触发）                   │
    │ ├─ 监听玩家游戏记录                       │
    │ ├─ 累计打码金额                           │
    │ ├─ 达到阈值自动发券                       │
    │ └─ Service: LotteryTicketBetProgressService│
    └──────────────────────────────────────────┘
                           │
                           ▼
    ┌──────────────────────────────────────────┐
    │ 2.2 奖券发放                              │
    │ ├─ 生成6位数编号（000000-999999）         │
    │ ├─ 状态: VALID (0)                       │
    │ ├─ 设置过期时间 = activity.end_time      │
    │ └─ WebSocket推送通知玩家                 │
    └──────────────────────────────────────────┘
                           │
                           ▼
    ┌──────────────────────────────────────────┐
    │ 2.3 活动结束 & 开奖                       │
    │ ├─ 渠道管理员点击"开奖"                   │
    │ ├─ 修改状态 → DRAWING (4)                │
    │ ├─ 调用摸球服务一次性摸出所有中奖号        │
    │ ├─ 更新中奖奖券状态 → WINNING (3)         │
    │ ├─ 创建中奖记录                           │
    │ ├─ 自动发放奖金到玩家账户                 │
    │ ├─ WebSocket推送中奖通知                 │
    │ └─ 修改状态 → ENDED (5)                  │
    └──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                    3. 玩家端API（gk_api）                     │
└─────────────────────────────────────────────────────────────┘
                           │
                           ▼
    ┌──────────────────────────────────────────┐
    │ 3.1 玩家登录后                            │
    │ ├─ 调用: GET /api/v1/player-info        │
    │ ├─ 返回: valid_lottery_ticket_count     │
    │ └─ 更新悬浮按钮红标                       │
    └──────────────────────────────────────────┘
                           │
                           ▼
    ┌──────────────────────────────────────────┐
    │ 3.2 点击悬浮按钮                          │
    │ ├─ 调用: POST /current-activity          │
    │ ├─ 后端智能返回活动（按优先级）            │
    │ └─ 前端展示对应状态弹窗                   │
    └──────────────────────────────────────────┘
                           │
                           ▼
    ┌──────────────────────────────────────────┐
    │ 3.3 查看我的奖券                          │
    │ ├─ 调用: POST /my-tickets                │
    │ └─ 显示所有奖券（包括中奖的）              │
    └──────────────────────────────────────────┘
                           │
                           ▼
    ┌──────────────────────────────────────────┐
    │ 3.4 开奖后查看结果                        │
    │ ├─ WebSocket推送中奖通知                 │
    │ ├─ 调用: POST /winning-records           │
    │ └─ 显示中奖记录和奖金                     │
    └──────────────────────────────────────────┘
```

---

## ⚠️ 发现的问题

### 🔴 严重问题（P0 - 必须修复）

#### 问题1: 有效摸奖券统计存在性能问题

**位置:** `PlayerController::playerInfo()`

**问题代码:**
```php
$validLotteryTicketCount = LotteryTicket::query()
    ->where('player_id', $player->id)
    ->whereIn('status', [
        LotteryTicket::STATUS_VALID,
        LotteryTicket::STATUS_WINNING
    ])
    ->where('expired_at', '>', date('Y-m-d H:i:s'))
    ->whereHas('activity', function($query) {
        $query->where('status', '!=', LotteryTicketActivity::STATUS_CLOSED);
    })
    ->count();
```

**问题:**
- ❌ **N+1查询风险**: `whereHas('activity')` 对每条记录都可能触发子查询
- ❌ **每次请求都查询**: playerInfo()是高频接口，每次都实时查询数据库
- ❌ **无索引优化**: 多字段组合查询，缺少复合索引

**影响:**
- 玩家信息接口QPS下降
- 数据库连接池占用
- 页面加载变慢

**建议修复:**
```php
// 方案1: 使用JOIN代替whereHas
$validLotteryTicketCount = LotteryTicket::query()
    ->join('lottery_ticket_activity as a', 'lottery_ticket.activity_id', '=', 'a.id')
    ->where('lottery_ticket.player_id', $player->id)
    ->whereIn('lottery_ticket.status', [
        LotteryTicket::STATUS_VALID,
        LotteryTicket::STATUS_WINNING
    ])
    ->where('lottery_ticket.expired_at', '>', date('Y-m-d H:i:s'))
    ->where('a.status', '!=', LotteryTicketActivity::STATUS_CLOSED)
    ->count('lottery_ticket.id');

// 方案2: 使用Redis缓存（推荐）
$cacheKey = "player:{$player->id}:valid_ticket_count";
$validLotteryTicketCount = Cache::remember($cacheKey, 300, function() use ($player) {
    return LotteryTicket::query()
        ->join('lottery_ticket_activity as a', 'lottery_ticket.activity_id', '=', 'a.id')
        ->where('lottery_ticket.player_id', $player->id)
        ->whereIn('lottery_ticket.status', [0, 3])
        ->where('lottery_ticket.expired_at', '>', date('Y-m-d H:i:s'))
        ->where('a.status', '!=', 6)
        ->count('lottery_ticket.id');
});

// 方案3: 数据库索引
// ALTER TABLE lottery_ticket ADD INDEX idx_player_status_expired (player_id, status, expired_at);
```

---

#### 问题2: 智能活动查询存在5次数据库查询

**位置:** `LotteryTicketController::getSmartActivity()`

**问题代码:**
```php
private function getSmartActivity(int $departmentId): ?LotteryTicketActivity
{
    // 查询1: 开奖中
    $activity = LotteryTicketActivity::query()
        ->where('department_id', $departmentId)
        ->where('status', 4)
        ->first();
    if ($activity) return $activity;
    
    // 查询2: 进行中
    $activity = LotteryTicketActivity::query()
        ->where('department_id', $departmentId)
        ->where('status', 3)
        ->first();
    if ($activity) return $activity;
    
    // 查询3: 预热中 ...
    // 查询4: 即将开始 ...
    // 查询5: 已结束 ...
}
```

**问题:**
- ❌ **最坏情况5次查询**: 如果都没有，执行全部5次
- ❌ **没有缓存**: 每次调用都查数据库
- ❌ **重复查询相同表**: 可以一次查询完成

**影响:**
- 点击悬浮按钮响应慢
- 数据库负载高

**建议修复:**
```php
// 方案1: 一次查询全部活动，在PHP内存中排序
private function getSmartActivity(int $departmentId): ?LotteryTicketActivity
{
    $now = date('Y-m-d H:i:s');
    $sevenDaysLater = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    // 一次查询获取所有可能的活动
    $activities = LotteryTicketActivity::query()
        ->where('department_id', $departmentId)
        ->where(function($query) use ($now, $sevenDaysLater) {
            $query->whereIn('status', [1, 2, 3, 4, 5])  // 预热、打码、进行、开奖、结束
                  ->orWhere(function($q) use ($now, $sevenDaysLater) {
                      $q->where('status', 0)  // 未开始
                        ->where('start_time', '<=', $sevenDaysLater);
                  });
        })
        ->get();
    
    if ($activities->isEmpty()) {
        return null;
    }
    
    // PHP内存中按优先级排序
    $priorities = [4 => 1, 3 => 2, 1 => 3, 2 => 3, 0 => 4, 5 => 5];
    
    $sorted = $activities->sortBy(function($activity) use ($priorities) {
        return $priorities[$activity->status] ?? 999;
    });
    
    return $sorted->first();
}

// 方案2: 使用Redis缓存（推荐）
private function getSmartActivity(int $departmentId): ?LotteryTicketActivity
{
    $cacheKey = "lottery_activity:smart:{$departmentId}";
    
    return Cache::remember($cacheKey, 60, function() use ($departmentId) {
        // 优先级查询（但缓存后影响小）
        foreach ([4, 3, [1,2], 0, 5] as $status) {
            $query = LotteryTicketActivity::query()
                ->where('department_id', $departmentId);
            
            if (is_array($status)) {
                $query->whereIn('status', $status);
            } else {
                $query->where('status', $status);
            }
            
            if ($status === 0) {
                $query->where('start_time', '<=', date('Y-m-d H:i:s', strtotime('+7 days')));
            }
            
            $activity = $query->first();
            if ($activity) {
                return $activity;
            }
        }
        return null;
    });
}
```

---

#### 问题3: buildActivityResponse()存在N+1查询

**位置:** `LotteryTicketController::buildActivityResponse()`

**问题代码:**
```php
private function buildActivityResponse(LotteryTicketActivity $activity, $player): Response
{
    // 查询1: 奖品等级
    $prizeLevels = LotteryTicketPrizeLevel::query()
        ->where('activity_id', $activity->id)
        ->orderBy('level_rank')
        ->get();

    // 查询2: 我的奖券数量
    $myTicketCount = LotteryTicket::query()
        ->where('activity_id', $activity->id)
        ->where('player_id', $player->id)
        ->whereIn('status', [0, 1, 3, 4])
        ->count();

    // 查询3: 我的中奖数量
    $myWinCount = LotteryTicket::query()
        ->where('activity_id', $activity->id)
        ->where('player_id', $player->id)
        ->where('status', 3)
        ->count();

    // 查询4: 打码进度
    $betProgress = LotteryTicketBetProgress::query()
        ->where('activity_id', $activity->id)
        ->where('player_id', $player->id)
        ->where('vip_level_id', $player->vip_level_id)
        ->first();
}
```

**问题:**
- ❌ **4次独立查询**: 每次调用都执行4次SELECT
- ❌ **重复条件**: activity_id和player_id在多个查询中重复
- ❌ **可以合并**: 奖券数量和中奖数量可以一次查询

**建议修复:**
```php
private function buildActivityResponse(LotteryTicketActivity $activity, $player): Response
{
    // 优化1: 并行查询（伪并行，但代码清晰）
    // 奖品等级（缓存，因为不常变）
    $cacheKey = "lottery_activity:{$activity->id}:prize_levels";
    $prizeLevels = Cache::remember($cacheKey, 3600, function() use ($activity) {
        return LotteryTicketPrizeLevel::query()
            ->where('activity_id', $activity->id)
            ->orderBy('level_rank')
            ->get(['level_rank', 'level_name', 'prize_type', 'prize_amount', 'prize_count'])
            ->toArray();
    });

    // 优化2: 合并奖券统计查询
    $ticketStats = LotteryTicket::query()
        ->selectRaw('
            COUNT(CASE WHEN status IN (0,1,3,4) THEN 1 END) as total_count,
            COUNT(CASE WHEN status = 3 THEN 1 END) as win_count
        ')
        ->where('activity_id', $activity->id)
        ->where('player_id', $player->id)
        ->first();

    $myTicketCount = $ticketStats->total_count ?? 0;
    $myWinCount = $ticketStats->win_count ?? 0;

    // 优化3: 打码进度查询（已经比较优化）
    $betProgress = LotteryTicketBetProgress::query()
        ->where('activity_id', $activity->id)
        ->where('player_id', $player->id)
        ->where('vip_level_id', $player->vip_level_id)
        ->first();

    // ... 其余逻辑
}
```

---

### 🟡 重要问题（P1 - 建议修复）

#### 问题4: 缺少奖券编号唯一性检查

**位置:** 奖券发放逻辑

**问题:**
- ❌ **6位数编号有限**: 000000-999999 只有100万个
- ❌ **无唯一性约束**: 数据库表没有UNIQUE索引
- ❌ **并发发券风险**: 多个玩家同时达标可能生成重复编号

**影响:**
- 活动参与人数超过100万时编号冲突
- 开奖时出现重复号码
- 中奖纠纷

**建议修复:**
```sql
-- 1. 添加唯一约束
ALTER TABLE lottery_ticket 
ADD UNIQUE INDEX uk_activity_ticket_no (activity_id, ticket_no);

-- 2. 修改发券逻辑
```

```php
// LotteryTicketService::issueTicket()
public function issueTicket($activityId, $playerId, $count)
{
    $tickets = [];
    $maxRetries = 10;
    
    for ($i = 0; $i < $count; $i++) {
        $retry = 0;
        
        while ($retry < $maxRetries) {
            $ticketNo = $this->generateTicketNo();
            
            try {
                // 使用数据库唯一约束防止重复
                $ticket = LotteryTicket::create([
                    'activity_id' => $activityId,
                    'player_id' => $playerId,
                    'ticket_no' => $ticketNo,
                    'status' => LotteryTicket::STATUS_VALID,
                    // ...
                ]);
                
                $tickets[] = $ticket;
                break;  // 成功，跳出重试循环
                
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() == 23000) {  // 唯一约束冲突
                    $retry++;
                    continue;  // 重试
                }
                throw $e;  // 其他错误直接抛出
            }
        }
        
        if ($retry >= $maxRetries) {
            throw new \Exception("无法生成唯一奖券编号，活动奖券已满");
        }
    }
    
    return $tickets;
}

// 优化：使用活动范围内的序列号
private function generateTicketNo($activityId)
{
    // 使用Redis原子递增
    $key = "lottery_activity:{$activityId}:ticket_sequence";
    $sequence = Redis::incr($key);
    
    if ($sequence > 999999) {
        throw new \Exception("活动奖券编号已用尽");
    }
    
    return str_pad($sequence, 6, '0', STR_PAD_LEFT);
}
```

---

#### 问题5: 开奖逻辑缺少并发控制

**位置:** 开奖服务

**问题:**
- ❌ **无分布式锁**: 多个管理员同时点击开奖
- ❌ **状态检查不足**: 可能重复开奖
- ❌ **事务不完整**: 摸球和更新状态未在一个事务中

**影响:**
- 重复开奖导致奖金重复发放
- 数据不一致

**建议修复:**
```php
// LotteryBallDrawService::draw()
public function draw(LotteryTicketActivity $activity): array
{
    $lockKey = "lottery_draw_lock:{$activity->id}";
    
    // 获取分布式锁（10秒超时）
    $lock = Cache::lock($lockKey, 10);
    
    if (!$lock->get()) {
        throw new \Exception('开奖正在进行中，请勿重复操作');
    }
    
    try {
        // 开始事务
        Db::beginTransaction();
        
        // 重新查询活动状态（避免并发问题）
        $activity = LotteryTicketActivity::lockForUpdate()->find($activity->id);
        
        // 状态检查
        if ($activity->status !== LotteryTicketActivity::STATUS_ONGOING) {
            throw new \Exception('活动状态不允许开奖');
        }
        
        // 检查是否已开奖
        $hasDrawn = LotteryTicket::query()
            ->where('activity_id', $activity->id)
            ->whereIn('status', [
                LotteryTicket::STATUS_WINNING,
                LotteryTicket::STATUS_LOSING
            ])
            ->exists();
        
        if ($hasDrawn) {
            throw new \Exception('该活动已开奖，请勿重复操作');
        }
        
        // 修改状态为开奖中
        $activity->status = LotteryTicketActivity::STATUS_DRAWING;
        $activity->save();
        
        // 执行摸球
        $winningTickets = $this->drawAllTickets($activity);
        
        // 批量更新奖券状态
        $this->updateTicketStatus($winningTickets);
        
        // 创建中奖记录
        $this->createWinningRecords($winningTickets);
        
        // 发放奖金
        $this->grantPrizes($winningTickets);
        
        // 修改状态为已结束
        $activity->status = LotteryTicketActivity::STATUS_ENDED;
        $activity->save();
        
        Db::commit();
        
        // 推送通知（事务外）
        $this->pushDrawCompleteNotification($activity->id, $winningTickets);
        
        return $winningTickets;
        
    } catch (\Exception $e) {
        Db::rollBack();
        throw $e;
    } finally {
        $lock->release();
    }
}
```

---

#### 问题6: 缺少奖券过期自动处理

**位置:** 系统级任务

**问题:**
- ❌ **无定时任务**: 没有自动将过期奖券标记为失效
- ❌ **依赖查询时过滤**: expired_at过滤散落在各处查询中
- ❌ **统计不准确**: 有效奖券统计可能包含已过期但状态未更新的

**影响:**
- 数据不一致
- 统计数据不准确
- 查询性能下降（需要每次都过滤expired_at）

**建议修复:**
```php
// 创建定时任务
// D:\gk_admin\process\LotteryTicketExpireProcess.php

<?php
namespace process;

use app\model\LotteryTicket;
use Workerman\Timer;
use support\Log;

class LotteryTicketExpireProcess
{
    public function onWorkerStart()
    {
        // 每5分钟执行一次
        Timer::add(300, function() {
            $this->expireTickets();
        });
    }

    private function expireTickets()
    {
        try {
            $now = date('Y-m-d H:i:s');
            
            // 批量更新过期奖券
            $count = LotteryTicket::query()
                ->where('status', LotteryTicket::STATUS_VALID)
                ->where('expired_at', '<', $now)
                ->update(['status' => LotteryTicket::STATUS_EXPIRED]);
            
            if ($count > 0) {
                Log::info("过期奖券处理完成", ['count' => $count]);
                
                // 清除相关缓存
                $this->clearRelatedCache();
            }
            
        } catch (\Exception $e) {
            Log::error("过期奖券处理失败", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    private function clearRelatedCache()
    {
        // 清除玩家有效奖券数量缓存
        // Cache::tags(['lottery_ticket'])->flush();
    }
}
```

```php
// config/process.php
return [
    // ... 其他进程
    
    'lottery_ticket_expire' => [
        'handler' => process\LotteryTicketExpireProcess::class,
        'count' => 1,  // 只需要1个进程
    ],
];
```

---

### 🟢 轻微问题（P2 - 可优化）

#### 问题7: 倒计时计算每次都重新计算

**位置:** `LotteryTicketController::calculateCountdown()`

**问题:**
- ❌ **每次请求都计算**: 时间戳转换和格式化
- ❌ **可以缓存**: 倒计时变化不频繁（分钟级）

**建议优化:**
```php
private function calculateCountdown(LotteryTicketActivity $activity): ?array
{
    // 缓存1分钟（倒计时按分钟计算，1分钟内返回相同结果）
    $cacheKey = "lottery_countdown:{$activity->id}:" . floor(time() / 60);
    
    return Cache::remember($cacheKey, 60, function() use ($activity) {
        $now = time();
        // ... 原有计算逻辑
    });
}
```

---

#### 问题8: 活动状态文本硬编码

**位置:** `LotteryTicketController::getActivityStatusText()`

**问题:**
- ❌ **硬编码中文**: 不支持多语言
- ❌ **重复定义**: 可能在多处定义

**建议优化:**
```php
// 使用语言包
private function getActivityStatusText(int $status): string
{
    $key = "lottery_ticket.activity_status.{$status}";
    return trans($key);
}

// lang/zh-CN/lottery_ticket.php
return [
    'activity_status' => [
        0 => '即將開始',
        1 => '活動預熱',
        2 => '打碼中',
        3 => '進行中',
        4 => '開獎中',
        5 => '已結束',
        6 => '已關閉',
    ]
];
```

---

## 🔒 安全问题审查

### 问题9: 缺少活动访问权限检查

**位置:** 所有API接口

**问题:**
- ❌ **未验证department_id**: 玩家可能访问其他渠道的活动
- ❌ **activity_id直接使用**: 恶意用户可能遍历活动ID

**影响:**
- 跨渠道数据泄露
- 安全隐患

**建议修复:**
```php
// 在所有使用activity_id的接口中增加验证
public function myTickets(Request $request): Response
{
    $player = checkPlayer();
    $data = $request->all();
    
    // ✅ 验证活动归属
    $activity = LotteryTicketActivity::query()
        ->where('id', $data['activity_id'])
        ->where('department_id', $player->department_id)  // ← 关键检查
        ->first();
    
    if (!$activity) {
        return jsonFailResponse('活动不存在或无权访问');
    }
    
    // 继续原有逻辑...
}
```

---

### 问题10: WebSocket推送缺少授权验证

**位置:** WebSocket推送服务

**问题:**
- ❌ **推送给所有在线用户**: 可能泄露其他玩家中奖信息
- ❌ **缺少频道隔离**: 不同渠道的玩家可能收到其他渠道的推送

**建议修复:**
```php
// LotteryTicketPushService::pushTicketIssued()
public static function pushTicketIssued(int $playerId, array $data)
{
    // ✅ 推送到玩家专属频道
    $channel = "player:{$playerId}";
    
    Client::publish($channel, [
        'event' => 'ticket_issued',
        'data' => $data
    ]);
}

// ✅ 开奖完成推送到渠道频道
public static function pushDrawComplete(int $activityId, array $winningTickets)
{
    $activity = LotteryTicketActivity::find($activityId);
    
    // 推送到渠道频道（该渠道所有在线玩家）
    $channel = "department:{$activity->department_id}:lottery";
    
    Client::publish($channel, [
        'event' => 'draw_complete',
        'activity_id' => $activityId,
        'total_winning_count' => count($winningTickets)
        // 不推送具体中奖号码，玩家自己查询
    ]);
}
```

---

## 📊 数据模型审查

### 问题11: 缺少必要的数据库索引

**当前索引情况:**

```sql
-- lottery_ticket表（推测）
PRIMARY KEY (id)
-- 缺少复合索引
```

**建议添加索引:**

```sql
-- 1. 玩家查询奖券（高频）
ALTER TABLE lottery_ticket 
ADD INDEX idx_player_status (player_id, status, expired_at);

-- 2. 活动查询奖券（开奖时）
ALTER TABLE lottery_ticket 
ADD INDEX idx_activity_status (activity_id, status);

-- 3. 唯一约束（防重复）
ALTER TABLE lottery_ticket 
ADD UNIQUE INDEX uk_activity_ticket_no (activity_id, ticket_no);

-- 4. 打码进度查询
ALTER TABLE lottery_ticket_bet_progress 
ADD UNIQUE INDEX uk_activity_player_vip (activity_id, player_id, vip_level_id);

-- 5. 中奖记录查询
ALTER TABLE lottery_ticket_record 
ADD INDEX idx_player_activity (player_id, activity_id, status);

-- 6. 活动智能查询
ALTER TABLE lottery_ticket_activity 
ADD INDEX idx_department_status (department_id, status, start_time);
```

---

### 问题12: 缺少数据一致性约束

**问题:**
- ❌ **无外键约束**: activity_id、player_id等无外键
- ❌ **允许NULL**: 部分字段应该NOT NULL

**建议修复:**
```sql
-- 1. 添加外键约束（如果支持InnoDB）
ALTER TABLE lottery_ticket 
ADD CONSTRAINT fk_ticket_activity 
FOREIGN KEY (activity_id) REFERENCES lottery_ticket_activity(id) ON DELETE CASCADE;

ALTER TABLE lottery_ticket 
ADD CONSTRAINT fk_ticket_player 
FOREIGN KEY (player_id) REFERENCES yjb_player(id) ON DELETE CASCADE;

-- 2. NOT NULL约束
ALTER TABLE lottery_ticket 
MODIFY COLUMN activity_id INT NOT NULL,
MODIFY COLUMN player_id INT NOT NULL,
MODIFY COLUMN ticket_no VARCHAR(6) NOT NULL,
MODIFY COLUMN status TINYINT NOT NULL DEFAULT 0,
MODIFY COLUMN expired_at DATETIME NOT NULL;
```

---

## 🧪 边界情况审查

### 问题13: 未处理的边界情况

#### 边界1: 活动结束但玩家仍在打码

**场景:**
```
活动时间: 2026-01-01 ~ 2026-01-31
玩家在 01-31 23:59 时打码满额
此时活动已结束，但触发发券逻辑
```

**问题:**
- 是否还发券？
- 奖券是否立即过期？

**建议处理:**
```php
// LotteryTicketBetProgressService::checkAndIssueTicket()
public function checkAndIssueTicket($player, $betAmount)
{
    $activity = LotteryTicketActivity::query()
        ->where('department_id', $player->department_id)
        ->where('status', LotteryTicketActivity::STATUS_ONGOING)
        ->where('end_time', '>', date('Y-m-d H:i:s'))  // ← 增加时间检查
        ->first();
    
    if (!$activity) {
        // 活动已结束，不发券
        Log::info("活动已结束，停止发券", ['player_id' => $player->id]);
        return;
    }
    
    // 继续发券逻辑...
}
```

---

#### 边界2: 玩家VIP等级变更

**场景:**
```
玩家开始打码时是VIP3（每10万送2张）
打码到5万时升级到VIP4（每5万送3张）
如何计算？
```

**当前逻辑:**
- 可能按照当前VIP等级查询进度
- 升级后之前的进度可能丢失

**建议处理:**
```php
// 方案1: 锁定VIP等级（活动开始时）
// 玩家参与活动时记录当时的VIP等级，活动期间不变

// 方案2: 动态调整（复杂）
// 升级时重新计算已发券数量和剩余进度
public function handleVipUpgrade($player, $oldVipId, $newVipId)
{
    $activity = $this->getCurrentActivity($player->department_id);
    if (!$activity) return;
    
    // 查询旧VIP进度
    $oldProgress = LotteryTicketBetProgress::query()
        ->where('activity_id', $activity->id)
        ->where('player_id', $player->id)
        ->where('vip_level_id', $oldVipId)
        ->first();
    
    if ($oldProgress) {
        // 迁移到新VIP等级
        $newProgress = LotteryTicketBetProgress::create([
            'activity_id' => $activity->id,
            'player_id' => $player->id,
            'vip_level_id' => $newVipId,
            'current_bet_amount' => $oldProgress->current_bet_amount,
            // ... 重新计算周期和奖券数
        ]);
        
        // 删除旧进度
        $oldProgress->delete();
    }
}
```

---

#### 边界3: 0张奖券时的显示

**场景:**
- 玩家还未参与活动，奖券数量=0
- 悬浮按钮是否显示？
- 弹窗如何展示？

**建议处理:**
```javascript
// 前端逻辑
function updateFloatingBadge(count) {
    if (count > 0) {
        // 显示红标数字
        badge.show();
        badge.text(count);
    } else {
        // 隐藏红标，或显示"0"
        badge.hide();  // 或 badge.text('0')
    }
}
```

---

#### 边界4: 100万张奖券用尽

**场景:**
- 活动超级火爆，6位编号（000000-999999）用尽
- 无法再发券

**当前处理:**
- 可能会重复生成编号导致冲突
- 或者无限循环

**建议处理:**
```php
// 使用Redis序列号，并设置上限
private function generateTicketNo($activityId)
{
    $key = "lottery_activity:{$activityId}:ticket_sequence";
    $sequence = Redis::incr($key);
    
    // 检查上限
    if ($sequence > 999999) {
        // 记录日志
        Log::error("活动奖券编号已用尽", [
            'activity_id' => $activityId,
            'sequence' => $sequence
        ]);
        
        // 通知管理员
        $this->notifyAdminTicketExhausted($activityId);
        
        throw new \Exception("活动奖券编号已用尽，请联系管理员");
    }
    
    return str_pad($sequence, 6, '0', STR_PAD_LEFT);
}
```

---

## 📈 性能优化建议总结

### 数据库优化

```sql
-- 1. 添加索引（执行优先级：高）
ALTER TABLE lottery_ticket ADD INDEX idx_player_status (player_id, status, expired_at);
ALTER TABLE lottery_ticket ADD INDEX idx_activity_status (activity_id, status);
ALTER TABLE lottery_ticket ADD UNIQUE INDEX uk_activity_ticket_no (activity_id, ticket_no);
ALTER TABLE lottery_ticket_bet_progress ADD UNIQUE INDEX uk_activity_player_vip (activity_id, player_id, vip_level_id);
ALTER TABLE lottery_ticket_activity ADD INDEX idx_department_status (department_id, status, start_time);

-- 2. 添加NOT NULL约束
ALTER TABLE lottery_ticket 
MODIFY COLUMN activity_id INT NOT NULL,
MODIFY COLUMN player_id INT NOT NULL,
MODIFY COLUMN status TINYINT NOT NULL DEFAULT 0;
```

### 缓存策略

```php
// 1. 玩家有效奖券数量（5分钟缓存）
Cache::remember("player:{$playerId}:valid_ticket_count", 300, ...);

// 2. 智能活动查询（1分钟缓存）
Cache::remember("lottery_activity:smart:{$departmentId}", 60, ...);

// 3. 奖品等级（1小时缓存，活动期间不变）
Cache::remember("lottery_activity:{$activityId}:prize_levels", 3600, ...);

// 4. 倒计时（1分钟缓存）
Cache::remember("lottery_countdown:{$activityId}:" . floor(time()/60), 60, ...);
```

### 查询优化

```php
// 1. 使用JOIN代替whereHas
// ❌ 之前
->whereHas('activity', function($q) {...})

// ✅ 之后
->join('lottery_ticket_activity as a', 'lottery_ticket.activity_id', '=', 'a.id')
->where('a.status', '!=', 6)

// 2. 合并统计查询
// ❌ 之前：2次COUNT查询
$myTicketCount = LotteryTicket::where(...)->count();
$myWinCount = LotteryTicket::where(...)->count();

// ✅ 之后：1次查询
$stats = LotteryTicket::selectRaw('
    COUNT(CASE WHEN status IN (0,1,3,4) THEN 1 END) as total_count,
    COUNT(CASE WHEN status = 3 THEN 1 END) as win_count
')->where(...)->first();
```

---

## 🎯 问题优先级汇总

### 🔴 P0 - 必须修复（上线前）

| # | 问题 | 位置 | 影响 | 修复工作量 |
|---|------|------|------|-----------|
| 1 | 有效奖券统计性能问题 | PlayerController | 高频接口慢 | 1小时 |
| 2 | 智能活动5次查询 | LotteryTicketController | 点击慢 | 1小时 |
| 3 | buildActivityResponse N+1查询 | LotteryTicketController | 响应慢 | 1小时 |
| 4 | 奖券编号唯一性 | 发券逻辑 | 数据冲突 | 2小时 |
| 5 | 开奖并发控制 | 开奖服务 | 重复开奖 | 2小时 |
| 6 | 奖券过期处理 | 定时任务 | 数据不一致 | 1小时 |
| 9 | 活动访问权限 | 所有API | 安全隐患 | 2小时 |
| 11 | 数据库索引 | 数据表 | 查询慢 | 0.5小时 |

**预计总工作量:** 1.5天

---

### 🟡 P1 - 建议修复（上线后）

| # | 问题 | 位置 | 影响 | 修复工作量 |
|---|------|------|------|-----------|
| 10 | WebSocket授权 | 推送服务 | 信息泄露 | 1小时 |
| 12 | 数据一致性约束 | 数据表 | 数据质量 | 0.5小时 |
| 13 | 边界情况处理 | 业务逻辑 | 异常情况 | 2小时 |

**预计总工作量:** 0.5天

---

### 🟢 P2 - 可优化（有时间）

| # | 问题 | 位置 | 影响 | 修复工作量 |
|---|------|------|------|-----------|
| 7 | 倒计时缓存 | LotteryTicketController | 微小性能 | 0.5小时 |
| 8 | 多语言支持 | 状态文本 | 国际化 | 1小时 |

---

## ✅ 修复建议执行顺序

### 第一批（上线前必须完成）

1. **添加数据库索引** (0.5小时)
2. **修复有效奖券统计性能** (1小时)
3. **优化智能活动查询** (1小时)
4. **优化buildActivityResponse** (1小时)
5. **增加活动访问权限检查** (2小时)
6. **添加奖券编号唯一性约束** (2小时)
7. **添加开奖并发控制** (2小时)
8. **创建奖券过期定时任务** (1小时)

**总计:** 10.5小时 ≈ 1.5个工作日

---

### 第二批（上线后优化）

1. **完善WebSocket授权** (1小时)
2. **添加数据库约束** (0.5小时)
3. **处理边界情况** (2小时)

**总计:** 3.5小时 ≈ 0.5个工作日

---

## 📋 测试清单

### 功能测试

- [ ] 玩家登录后红标显示正确
- [ ] 点击悬浮按钮智能展示活动
- [ ] 5种活动状态正确展示
- [ ] 打码满额自动发券
- [ ] 奖券编号唯一不重复
- [ ] 开奖摸球正常
- [ ] 中奖奖金正常发放
- [ ] WebSocket推送正常

### 性能测试

- [ ] playerInfo接口 < 100ms
- [ ] getCurrentActivity接口 < 200ms
- [ ] 并发发券1000/s无错误
- [ ] 开奖并发控制有效
- [ ] 数据库慢查询监控

### 安全测试

- [ ] 跨渠道数据隔离
- [ ] activity_id越权访问防护
- [ ] WebSocket频道隔离
- [ ] 并发开奖防护

### 边界测试

- [ ] 0张奖券显示正常
- [ ] 活动结束后不再发券
- [ ] VIP升级进度处理
- [ ] 100万编号上限处理
- [ ] 过期奖券自动失效

---

## 📊 总体评估

| 维度 | 评分 | 说明 |
|------|------|------|
| **需求符合性** | 90% | 核心流程完整，缺少部分边界处理 |
| **逻辑完整性** | 85% | 主流程正确，存在并发和边界问题 |
| **性能表现** | 70% | 存在N+1查询和缺少索引/缓存 |
| **安全性** | 75% | 缺少权限检查和推送授权 |
| **可维护性** | 80% | 代码结构清晰，缺少边界处理 |
| **可扩展性** | 75% | 100万编号限制，需要考虑扩展 |

**总体评分:** 79/100

---

## 🎯 结论与建议

### 核心问题

1. ✅ **功能完整**: 核心业务流程完整可用
2. ⚠️ **性能优化**: 存在明显的性能瓶颈，需优化
3. ⚠️ **安全加固**: 缺少权限检查，需补充
4. ⚠️ **边界处理**: 部分异常场景未考虑

### 上线建议

**可以上线，但必须先修复P0问题:**

1. ✅ 添加数据库索引
2. ✅ 优化查询性能（缓存+JOIN）
3. ✅ 增加权限检查
4. ✅ 添加并发控制
5. ✅ 创建过期定时任务

**预计修复时间:** 1.5个工作日

**修复后可达到:** 85分以上，可安全上线

---

**审查完成时间:** 2026-06-10  
**审查人:** AI Assistant  
**状态:** ⚠️ 需修复P0问题后上线
