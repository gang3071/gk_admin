# 🎯 摸奖券功能优先修复清单（修订版）

**修订时间:** 2026-06-12  
**用户反馈:** 活动历史API、中奖名单API - 不需要  
**剩余问题:** 重新评估优先级

---

## ✅ 用户反馈确认

### 不需要的功能（已移除）

| # | 功能 | 原评级 | 用户反馈 | 理由分析 |
|---|------|--------|---------|---------|
| 1 | 活动历史列表API | P1 | ❌ 不需要 | 玩家只关心当前活动 |
| 2 | 中奖名单公示API | P1 | ❌ 不需要 | 涉及隐私，业务不需要 |

**分析合理性:**
- ✅ **活动历史API** - 确实可能不需要，玩家主要关注当前活动，历史查询需求低
- ✅ **中奖名单公示** - 可能涉及玩家隐私，不公开也是合理的商业决策

---

## 🔴 P0 - 必须修复（核心功能缺失）

### 1. ⭐ 添加开奖结果查询API

**严重性:** 🔴 高  
**影响:** 玩家无法看到开奖的球号，透明度不足  
**工作量:** 30分钟

#### 问题描述

摇球开奖是核心玩法，但玩家端API没有返回开奖结果（6个球号）。

**当前状态:**
- ✅ 后台有完整的摇球逻辑
- ✅ 数据库存储了 `ball_result`
- ❌ 客户端API完全没返回

**影响:**
- 玩家不知道开出的球号是什么
- 无法验证自己的券号是否应该中奖
- 类似彩票开奖不公布号码

#### 修复代码

**文件:** `D:/gk_api/app/api/controller/v1/LotteryTicketController.php`

```php
private function buildActivityResponse(LotteryTicketActivity $activity, $player): Response
{
    // ... 现有代码

    $responseData = [
        'has_activity' => true,
        'activity' => [
            'id' => $activity->id,
            'name' => $activity->name,
            'status' => $activity->status,
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
            'cover_image' => $activity->cover_image,
            'live_url' => $activity->live_url,
            
            // ✅ 新增：开奖结果（重要！）
            'ball_result' => $activity->ball_result 
                ? json_decode($activity->ball_result, true) 
                : null,
            
            // ✅ 新增：开奖时间
            'draw_time' => $activity->draw_time ?? null,
            
            // 现有字段...
            'prize_levels' => $prizeLevels,
            'player_bet_progress' => $betProgressData,
            'my_tickets_count' => $myTicketsCount,
        ],
    ];

    return jsonSuccessResponse('success', $responseData);
}
```

**前端展示示例:**
```
┌────────────────────────┐
│  第1期摸奖券活动        │
│  状态: 已开奖           │
│                        │
│  开奖号码:              │
│  ┌──┬──┬──┬──┬──┬──┐  │
│  │1 │2 │3 │4 │5 │6 │  │
│  └──┴──┴──┴──┴──┴──┘  │
│                        │
│  我的券号: 123456       │
│  中奖情况: 未中奖       │
└────────────────────────┘
```

---

### 2. ⭐ 打码进度实时更新机制

**严重性:** 🔴 高  
**影响:** 玩家打码后不能及时获得奖券  
**工作量:** 2小时

#### 问题描述

代码中有 `LotteryTicketBetProgressService::updateProgress()` 方法，但没有找到调用入口：

**检查结果:**
- ❌ 没有定时任务调用
- ❌ 没有游戏日志监听
- ❌ 没有实时触发逻辑

**影响:**
- 玩家打码后不知道何时能获得奖券
- 可能遗漏打码数据
- 用户体验差

#### 建议修复方案

请确认您希望使用哪种方案：

**方案A: 实时更新（推荐）**

在玩家游戏结算时立即更新进度：

```php
// 文件: addons/webman/model/PlayerGameLog.php

protected static function booted()
{
    static::created(function ($log) {
        // 检查是否有进行中的摸奖券活动
        $activities = LotteryTicketActivity::where('department_id', $log->department_id)
            ->whereIn('status', [
                LotteryTicketActivity::STATUS_BETTING,
                LotteryTicketActivity::STATUS_ONGOING
            ])
            ->get();
        
        if ($activities->isEmpty()) {
            return;
        }
        
        // 异步更新打码进度（避免阻塞游戏结算）
        foreach ($activities as $activity) {
            \Webman\RedisQueue\Redis::send('lottery-ticket-progress', [
                'player_id' => $log->player_id,
                'activity_id' => $activity->id,
                'bet_amount' => $log->valid_bet_amount,
                'game_log_id' => $log->id,
            ]);
        }
    });
}
```

```php
// 文件: addons/webman/queue/LotteryTicketProgressQueue.php

<?php

namespace addons\webman\queue;

use addons\webman\service\LotteryTicketBetProgressService;
use Webman\RedisQueue\Consumer;

class LotteryTicketProgressQueue implements Consumer
{
    public $queue = 'lottery-ticket-progress';

    public function consume($data)
    {
        LotteryTicketBetProgressService::updateProgress(
            $data['player_id'],
            $data['activity_id'],
            $data['bet_amount']
        );
    }
}
```

**方案B: 定时任务（备选）**

每分钟扫描最近的游戏日志：

```php
// 文件: process/LotteryTicketProgressUpdateProcess.php

<?php

namespace process;

use addons\webman\model\PlayerGameLog;
use addons\webman\model\LotteryTicketActivity;
use addons\webman\service\LotteryTicketBetProgressService;
use Workerman\Timer;
use support\Log;

class LotteryTicketProgressUpdateProcess
{
    public function onWorkerStart()
    {
        // 每分钟执行一次
        Timer::add(60, function() {
            $this->updateRecentProgress();
        });
    }

    private function updateRecentProgress()
    {
        try {
            // 获取进行中的活动
            $activities = LotteryTicketActivity::whereIn('status', [
                LotteryTicketActivity::STATUS_BETTING,
                LotteryTicketActivity::STATUS_ONGOING
            ])->get();

            if ($activities->isEmpty()) {
                return;
            }

            // 获取最近1分钟的游戏日志（只查询有效投注）
            $logs = PlayerGameLog::where('created_at', '>', date('Y-m-d H:i:s', strtotime('-1 minute')))
                ->where('valid_bet_amount', '>', 0)
                ->get();

            foreach ($logs as $log) {
                foreach ($activities as $activity) {
                    if ($log->department_id == $activity->department_id) {
                        LotteryTicketBetProgressService::updateProgress(
                            $log->player_id,
                            $activity->id,
                            $log->valid_bet_amount
                        );
                    }
                }
            }

            Log::info('[摸奖券] 打码进度更新完成', [
                'logs_count' => count($logs),
                'activities_count' => count($activities)
            ]);

        } catch (\Exception $e) {
            Log::error('[摸奖券] 打码进度更新失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }
}
```

**需要配置进程（config/process.php）:**
```php
return [
    // ...
    'lottery_ticket_progress_update' => [
        'handler' => process\LotteryTicketProgressUpdateProcess::class,
        'count' => 1,
    ],
];
```

#### ❓ 请确认

您倾向于使用哪种方案？
- **方案A（实时）**: 响应快，但增加游戏结算耦合
- **方案B（定时）**: 解耦好，但有最多1分钟延迟

或者您已经有其他更新机制（比如外部系统调用）？

---

## 🟡 P1 - 建议修复（提升体验）

### 3. 开奖后立即推送通知

**严重性:** 🟡 中  
**影响:** 玩家不能第一时间知道中奖  
**工作量:** 30分钟

#### 问题描述

现在推送通知只在"发放奖励"时触发，但发放可能是开奖后几小时甚至几天。

**用户体验问题:**
- 玩家错过兴奋感的最佳时机
- 需要主动查看才知道中奖
- 不符合彩票类产品的体验习惯

#### 修复代码

**文件:** `addons/webman/service/LotteryBallDrawService.php`

```php
private static function executeDrawing(LotteryTicketActivity $activity): array
{
    // ... 开奖逻辑
    
    Db::commit();
    
    // ✅ 新增：开奖完成后立即推送中奖通知
    if (!empty($winningRecords)) {
        foreach ($winningRecords as $record) {
            // 推送"中奖通知"（尚未发放，只通知中奖）
            \Webman\RedisQueue\Redis::send('lottery-ticket-push', [
                'type' => 'winning_notice',
                'player_id' => $record->player_id,
                'activity_id' => $record->activity_id,
                'activity_name' => $activity->name,
                'prize_level' => $record->prize_level,
                'prize_level_name' => $record->prize_level_name,
                'prize_amount' => $record->prize_amount,
                'ticket_no' => $record->ticket_no,
            ]);
        }
        
        Log::info('[摸奖券] 中奖通知推送完成', [
            'activity_id' => $activity->id,
            'winning_count' => count($winningRecords)
        ]);
    }
    
    return ['success' => true, 'message' => '开奖成功'];
}
```

**推送消息示例:**
```
🎉 恭喜中奖！

您的券号 123456
在"第1期摸奖券活动"中
获得 二等奖！

奖金：5,000元
请等待管理员发放奖励
```

---

## 🟢 P2 - 可选优化（提升质量）

### 4. 活动状态自动流转

**严重性:** 🟢 低  
**影响:** 需要手动更改状态，运营成本高  
**工作量:** 2小时

目前活动状态流转需要手动操作：
- 未开始 → 进行中 （需手动）
- 进行中 → 开奖中 （需手动）

**建议:** 添加定时任务自动流转（可选）

---

### 5. 随机数算法优化

**严重性:** 🟢 低  
**影响:** 理论上的安全风险  
**工作量:** 10分钟

将 `mt_rand()` 改为密码学安全的 `random_int()`

---

### 6. 缓存击穿防护

**严重性:** 🟢 低  
**影响:** 高并发时可能的性能问题  
**工作量:** 30分钟

添加缓存锁防止缓存击穿

---

## 📊 修订后的优先级总结

| 优先级 | 问题 | 必要性 | 工作量 | 状态 |
|--------|------|--------|--------|------|
| **P0** | 开奖结果API | 🔴 必须 | 30分钟 | ⏳ 待修复 |
| **P0** | 打码进度更新 | 🔴 必须 | 2小时 | ⏳ 待确认方案 |
| **P1** | 开奖后推送 | 🟡 建议 | 30分钟 | ⏳ 待修复 |
| **P2** | 状态自动流转 | 🟢 可选 | 2小时 | ○ 可选 |
| **P2** | 随机数优化 | 🟢 可选 | 10分钟 | ○ 可选 |
| **P2** | 缓存防护 | 🟢 可选 | 30分钟 | ○ 可选 |
| ~~P1~~ | ~~活动历史API~~ | ❌ 不需要 | - | ✅ 已移除 |
| ~~P1~~ | ~~中奖名单API~~ | ❌ 不需要 | - | ✅ 已移除 |

---

## ❓ 需要您确认的问题

### 1. 打码进度更新方案

请告诉我：
- ✅ **方案A（实时）** - 游戏结算时立即更新
- ✅ **方案B（定时）** - 每分钟扫描更新
- ✅ **其他方案** - 您已有的更新机制

### 2. 其他修复建议

对于剩余的修复建议，您认为：
- **开奖结果API** - 是否需要添加？
- **开奖后推送** - 是否需要立即推送？
- **P2可选优化** - 是否需要实施？

---

**修订时间:** 2026-06-12  
**总工作量（必须项）:** 2.5-3小时  
**总工作量（含建议项）:** 3-3.5小时  
**总工作量（含可选项）:** 6小时
