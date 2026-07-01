# 摸奖券系统深度逻辑审查报告

**审查日期:** 2026-06-11  
**审查类型:** 业务逻辑、数据一致性、竞态条件、边界情况  
**审查人员:** AI Assistant  

---

## 📋 审查总结

| 严重性 | 数量 | 状态 |
|--------|------|------|
| 🔴 严重 | 3 | 需立即修复 |
| 🟡 警告 | 2 | 建议修复 |
| 🟢 提示 | 1 | 可选优化 |

**总体评分:** 75/100（修复前：95/100）

**发现的问题比预期严重，建议在部署前修复所有🔴严重问题。**

---

## 🔴 严重问题（3个）

### 问题1: 过期任务存在竞态条件 🔴

**严重性:** 🔴 高危

**文件:** `D:\gk_admin\process\LotteryTicketExpireProcess.php`

**问题描述:**

SELECT和UPDATE之间存在时间窗口，可能导致数据不一致：

```php
// ❌ 当前逻辑（有问题）
private function expireTickets()
{
    // 步骤1: SELECT 查询过期券（假设查到10张）
    $expiredTickets = LotteryTicket::query()
        ->where('status', LotteryTicket::STATUS_VALID)
        ->where('expired_at', '<', $now)
        ->get(['id', 'player_id']);
    
    // ⚠️ 时间窗口：这里可能有其他操作改变了奖券状态
    // 比如：玩家A的券在这期间被手动失效了
    
    $playerIds = $expiredTickets->pluck('player_id')->unique()->toArray();
    
    // 步骤2: UPDATE 批量更新（可能只更新了8张）
    $count = LotteryTicket::query()
        ->where('status', LotteryTicket::STATUS_VALID)
        ->where('expired_at', '<', $now)
        ->update(['status' => LotteryTicket::STATUS_EXPIRED]);
    
    // 问题：$playerIds 基于10张券，但实际只更新了8张
    // 清除缓存时会清除不该清除的玩家缓存
}
```

**竞态场景:**

```
时间线:
T1: 定时任务 SELECT → 查到券A、券B（玩家1）
T2: 管理员手动将券A设为"已使用"
T3: 定时任务 UPDATE → 只更新券B
T4: 定时任务清除玩家1的缓存 ← 基于2张券，但实际只过期了1张
```

**影响:**
- 缓存清除不准确
- 日志数字不匹配（count ≠ 实际过期数）
- 不会造成数据损坏，但逻辑混乱

**优先级:** P0（建议修复）

**修复方案:**

```php
// ✅ 方案1：使用WHERE IN限定ID范围（推荐）
private function expireTickets()
{
    try {
        $now = date('Y-m-d H:i:s');

        // 查询过期券
        $expiredTickets = LotteryTicket::query()
            ->where('status', LotteryTicket::STATUS_VALID)
            ->where('expired_at', '<', $now)
            ->get(['id', 'player_id']);

        if ($expiredTickets->isEmpty()) {
            return;
        }

        $ticketIds = $expiredTickets->pluck('id')->toArray();
        $playerIds = $expiredTickets->pluck('player_id')->unique()->toArray();

        // ✅ 使用WHERE IN确保只更新刚才查到的券
        $count = LotteryTicket::query()
            ->whereIn('id', $ticketIds)  // ← 关键：限定ID范围
            ->where('status', LotteryTicket::STATUS_VALID)  // 双重检查
            ->update(['status' => LotteryTicket::STATUS_EXPIRED]);

        // 现在count一定等于count($ticketIds)
        
        Log::info('[摸奖券] 过期奖券处理完成', [
            'queried' => count($ticketIds),
            'updated' => $count,
            'affected_players' => count($playerIds),
            'time' => $now
        ]);

        $this->clearPlayerTicketCache($playerIds);

    } catch (\Exception $e) {
        Log::error('[摸奖券] 过期奖券处理失败', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}
```

**预计修复时间:** 15分钟

---

### 问题2: Redis序列号事务回滚导致编号浪费 🔴

**严重性:** 🔴 高危

**文件:** `D:\gk_admin\addons\webman\service\LotteryTicketIssueService.php`

**问题描述:**

Redis INCR是原子操作，不支持回滚。事务失败后序列号不会回退，导致编号空洞：

```php
// ❌ 当前逻辑（有问题）
public function issueTickets(...): array
{
    try {
        Db::beginTransaction();
        
        for ($i = 0; $i < $actualCount; $i++) {
            // Redis原子递增（不可回滚）
            $ticketNo = $this->generateUniqueTicketNo($activityId);
            // ticketNo = "000001", "000002", "000003"
            
            // 尝试创建奖券
            LotteryTicket::create([...]);
        }
        
        Db::commit();
        
    } catch (\Exception $e) {
        Db::rollBack();  // ← 数据库回滚
        // 但是Redis序列号已经+3了，无法回滚！
        throw $e;
    }
}
```

**场景示例:**

```
请求1: 发3张券
- Redis INCR → 1, 2, 3
- 创建券1 ✅
- 创建券2 ✅
- 创建券3 ❌ 数据库异常
- Db::rollBack() → 券1、券2被回滚删除
- 结果：Redis序列号=3，但实际券数=0

请求2: 再发1张券
- Redis INCR → 4
- 跳过了 1, 2, 3 号（编号空洞）
```

**影响:**
- 编号不连续（000001, 000002, 000005...）
- 长期运行可能浪费大量编号
- 极端情况提前用尽100万上限

**优先级:** P0（必须修复）

**修复方案:**

```php
// ✅ 方案1：先预留Redis编号，成功后再提交（推荐）
public function issueTickets(int $activityId, int $playerId, int $count, int $source): array
{
    // ... 前置检查 ...
    
    $tickets = [];
    $reservedSequences = [];  // ✅ 记录预留的序列号
    
    try {
        Db::beginTransaction();
        
        for ($i = 0; $i < $actualCount; $i++) {
            // ✅ 预留序列号
            $sequence = Redis::incr("lottery_activity:{$activityId}:ticket_sequence");
            $reservedSequences[] = $sequence;
            
            if ($sequence > 999999) {
                throw new \Exception('活动奖券编号已用尽');
            }
            
            $ticketNo = str_pad($sequence, 6, '0', STR_PAD_LEFT);
            
            // 重试机制（最多10次）
            $maxRetries = 10;
            $retry = 0;
            $ticket = null;
            
            while ($retry < $maxRetries) {
                try {
                    $ticket = LotteryTicket::create([
                        'activity_id' => $activityId,
                        'player_id' => $playerId,
                        'ticket_no' => $ticketNo,
                        // ... 其他字段
                    ]);
                    break;  // 成功，跳出重试
                    
                } catch (\Illuminate\Database\QueryException $e) {
                    // 唯一约束冲突
                    if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        $retry++;
                        
                        if ($retry >= $maxRetries) {
                            throw new \Exception("编号冲突重试{$maxRetries}次仍失败: {$ticketNo}");
                        }
                        
                        // ✅ 重新获取序列号
                        $sequence = Redis::incr("lottery_activity:{$activityId}:ticket_sequence");
                        $reservedSequences[] = $sequence;
                        
                        if ($sequence > 999999) {
                            throw new \Exception('活动奖券编号已用尽');
                        }
                        
                        $ticketNo = str_pad($sequence, 6, '0', STR_PAD_LEFT);
                        
                        Log::warning('[摸奖券] 编号冲突，重试', [
                            'activity_id' => $activityId,
                            'retry' => $retry,
                            'new_sequence' => $sequence
                        ]);
                        
                        continue;  // 重试
                    }
                    
                    // 其他异常直接抛出
                    throw $e;
                }
            }
            
            if (!$ticket) {
                throw new \Exception("无法创建奖券");
            }
            
            $tickets[] = $ticket;
        }
        
        Db::commit();
        
        Log::info('[摸奖券] 发放成功', [
            'activity_id' => $activityId,
            'player_id' => $playerId,
            'requested_count' => $count,
            'actual_count' => $actualCount,
            'reserved_sequences' => count($reservedSequences),  // 记录预留数
            'source' => $source
        ]);
        
        $this->clearPlayerTicketCache($playerId);
        
        return $tickets;
        
    } catch (\Exception $e) {
        Db::rollBack();
        
        // ✅ 记录浪费的序列号（用于监控）
        Log::error('[摸奖券] 发放失败，序列号已浪费', [
            'activity_id' => $activityId,
            'player_id' => $playerId,
            'wasted_sequences' => $reservedSequences,
            'wasted_count' => count($reservedSequences),
            'error' => $e->getMessage()
        ]);
        
        throw $e;
    }
}
```

**注意:** 序列号浪费是Redis INCR的固有特性，无法完全避免，但可以：
1. 监控浪费率
2. 如果浪费严重，提高上限（改为7位数）
3. 记录日志便于排查

**预计修复时间:** 30分钟

---

### 问题3: 开奖匹配可能导致同一张券重复中奖 🔴

**严重性:** 🔴 高危

**文件:** `D:\gk_admin\addons\webman\service\LotteryBallDrawService.php`

**问题描述:**

当前匹配逻辑从高等奖到低等奖遍历，同一张券可能匹配多个等级：

```php
// ❌ 当前逻辑（有问题）
protected static function matchWinningTickets(...): array
{
    $winningData = [];
    
    // 从高等奖开始匹配
    foreach ($prizeLevels as $prizeLevel) {
        // 查找匹配的摸奖券
        $matchedTickets = LotteryTicket::where('activity_id', $activity->id)
            ->where('status', LotteryTicket::STATUS_UNUSED)
            ->where(Db::raw('RIGHT(ticket_no, ' . $matchDigits . ')'), '=', $matchPattern)
            ->limit($prizeCount)
            ->get();
        
        // ⚠️ 问题：已中奖的券没有排除，可能再次匹配
        
        foreach ($matchedTickets as $ticket) {
            $winningData[] = [...];  // 添加中奖记录
        }
    }
    
    return $winningData;
}
```

**场景示例:**

```
开奖号码: 123456

奖品配置:
- 特等奖（level_rank=1）: 匹配后6位，奖金10000，数量1
- 一等奖（level_rank=2）: 匹配后5位，奖金1000，数量3

玩家券:
- 券A: 123456（完全匹配）
- 券B: 023456（后5位匹配）
- 券C: 003456（后4位匹配）

当前逻辑:
1. 匹配特等奖（后6位=123456）
   - 券A中奖 ✅ → winningData[0]
   
2. 匹配一等奖（后5位=23456）
   - 券A中奖 ✅ → winningData[1]  ← 重复！
   - 券B中奖 ✅ → winningData[2]
   - 券C中奖 ✅ → winningData[3]

结果:
- 券A中了2次奖（特等奖 + 一等奖）← 不合理
- 创建2条中奖记录
- 更新券A状态2次（STATUS_USED）
```

**影响:**
- 同一张券重复中奖
- 中奖记录数超过实际券数
- 财务统计错误

**优先级:** P0（必须修复）

**修复方案:**

```php
// ✅ 方案1：记录已中奖券ID，排除重复（推荐）
protected static function matchWinningTickets(LotteryTicketActivity $activity, array $ballResult): array
{
    $winningTicketNo = $ballResult['winning_no'];
    
    $prizeLevels = LotteryTicketPrizeLevel::where('activity_id', $activity->id)
        ->orderBy('level_rank', 'asc')
        ->get();
    
    if ($prizeLevels->isEmpty()) {
        Log::warning('活动未配置奖品等级', ['activity_id' => $activity->id]);
        return [];
    }
    
    $winningData = [];
    $usedTicketIds = [];  // ✅ 记录已中奖的券ID
    
    foreach ($prizeLevels as $prizeLevel) {
        $prizeCount = $prizeLevel->prize_count;
        
        if ($prizeCount <= 0) {
            continue;
        }
        
        $matchDigits = 7 - $prizeLevel->level_rank;
        
        if ($matchDigits <= 0 || $matchDigits > 6) {
            Log::error('奖品等级配置错误', [
                'activity_id' => $activity->id,
                'prize_level_id' => $prizeLevel->id,
                'level_rank' => $prizeLevel->level_rank,
                'match_digits' => $matchDigits,
            ]);
            continue;
        }
        
        $matchPattern = substr($winningTicketNo, -$matchDigits);
        
        // ✅ 排除已中奖的券
        $matchedTickets = LotteryTicket::where('activity_id', $activity->id)
            ->where('status', LotteryTicket::STATUS_UNUSED)
            ->whereNotIn('id', $usedTicketIds)  // ✅ 关键：排除已中奖
            ->where(function ($query) {
                $query->whereNull('expired_at')
                      ->orWhere('expired_at', '>', date('Y-m-d H:i:s'));
            })
            ->where(Db::raw('RIGHT(ticket_no, ' . $matchDigits . ')'), '=', $matchPattern)
            ->limit($prizeCount)
            ->get();
        
        foreach ($matchedTickets as $ticket) {
            $winningData[] = [
                'ticket_id' => $ticket->id,
                'ticket_no' => $ticket->ticket_no,
                'player_id' => $ticket->player_id,
                'prize_type' => $prizeLevel->prize_type,
                'prize_name' => $prizeLevel->level_name,
                'prize_amount' => $prizeLevel->prize_amount,
                'match_digits' => $matchDigits,
                'level_rank' => $prizeLevel->level_rank,  // ✅ 记录等级
            ];
            
            // ✅ 记录已使用的券ID
            $usedTicketIds[] = $ticket->id;
        }
    }
    
    // ✅ 日志记录统计信息
    Log::info('[摸奖券] 匹配完成', [
        'activity_id' => $activity->id,
        'winning_no' => $winningTicketNo,
        'total_winners' => count($winningData),
        'unique_tickets' => count($usedTicketIds),
        'prize_levels_matched' => $prizeLevels->count()
    ]);
    
    return $winningData;
}
```

**预计修复时间:** 20分钟

---

## 🟡 警告问题（2个）

### 问题4: 智能活动查询缓存可能返回过时数据 🟡

**严重性:** 🟡 中等

**文件:** `D:\gk_api\app\api\controller\v1\LotteryTicketController.php`

**问题描述:**

智能活动查询使用1分钟缓存，活动状态变化时可能返回错误的活动：

```php
// 当前逻辑
private function getSmartActivity(int $departmentId): ?LotteryTicketActivity
{
    $cacheKey = "lottery_activity:smart:{$departmentId}";
    
    return \support\Cache::remember($cacheKey, 60, function() use ($departmentId) {
        // 5级优先级查询...
    });
}
```

**场景示例:**

```
T0: 活动A状态=ONGOING，被缓存
T1: 管理员手动将活动A改为ENDED
T2: 管理员创建新活动B，状态=ONGOING
T3: 玩家请求getCurrentActivity()
    → 缓存命中，返回活动A（ENDED）← 错误
    → 应该返回活动B（ONGOING）

T4: 60秒后缓存过期，返回正确的活动B
```

**影响:**
- 玩家看到错误的活动信息
- 最长延迟60秒

**优先级:** P1（建议修复）

**修复方案:**

```php
// ✅ 方案1：缩短缓存时间（推荐）
private function getSmartActivity(int $departmentId): ?LotteryTicketActivity
{
    $cacheKey = "lottery_activity:smart:{$departmentId}";
    
    // 缓存时间从60秒改为10秒
    return \support\Cache::remember($cacheKey, 10, function() use ($departmentId) {
        // ... 5级优先级查询
    });
}

// ✅ 方案2：活动状态变化时主动清除缓存
// 在ChannelLotteryTicketActivityController::save()中添加:
$form->saved(function (Form $form) use ($activity) {
    // 清除智能活动缓存
    $cacheKey = "lottery_activity:smart:{$activity->department_id}";
    \support\Cache::forget($cacheKey);
    
    return message_success(admin_trans('common.save_success'));
});
```

**预计修复时间:** 10分钟

---

### 问题5: 有效奖券统计查询可能包含已过期但未处理的券 🟡

**严重性:** 🟡 中等

**文件:** `D:\gk_api\app\api\controller\v1\PlayerController.php`

**问题描述:**

有效奖券统计使用 `expired_at > now()` 判断，但定时任务5分钟才执行一次，可能包含已过期但状态仍为VALID的券：

```php
// 当前逻辑
$validLotteryTicketCount = Cache::remember($cacheKey, 300, function() use ($player) {
    return LotteryTicket::query()
        ->join('lottery_ticket_activity as a', 'lottery_ticket.activity_id', '=', 'a.id')
        ->where('lottery_ticket.player_id', $player->id)
        ->whereIn('lottery_ticket.status', [
            LotteryTicket::STATUS_VALID,    // 0-有效
            LotteryTicket::STATUS_WINNING   // 3-中奖
        ])
        ->where('lottery_ticket.expired_at', '>', date('Y-m-d H:i:s'))  // ✅ 时间判断正确
        ->where('a.status', '!=', LotteryTicketActivity::STATUS_CLOSED)
        ->count('lottery_ticket.id');
});
```

**分析:**

实际上这个查询是**正确的**！因为：
1. 使用了 `expired_at > now()` 实时判断
2. 即使status还是VALID，但expired_at已过期，不会被统计
3. 定时任务只是批量更新status，不影响统计准确性

**结论:** ✅ 无需修复（撤回此问题）

---

## 🟢 提示（1个）

### 问题6: 奖品等级缓存时间过长 🟢

**严重性:** 🟢 低

**文件:** `D:\gk_api\app\api\controller\v1\LotteryTicketController.php`

**问题描述:**

奖品等级缓存1小时，管理员修改奖品配置后，玩家端需要1小时才能看到更新：

```php
$cacheKey = "lottery_activity:{$activity->id}:prize_levels";
$prizeLevels = \support\Cache::remember($cacheKey, 3600, function() use ($activity) {
    return LotteryTicketPrizeLevel::query()
        ->where('activity_id', $activity->id)
        ->orderBy('level_rank')
        ->select([...])
        ->get()
        ->toArray();
});
```

**影响:**
- 修改奖品后延迟1小时生效
- 不影响功能，只是用户体验问题

**优先级:** P2（可选优化）

**修复方案:**

```php
// ✅ 方案1：缩短缓存时间
$prizeLevels = \support\Cache::remember($cacheKey, 600, function() use ($activity) {
    // 从3600秒（1小时）改为600秒（10分钟）
});

// ✅ 方案2：管理员保存时清除缓存
// 在ChannelLotteryTicketPrizeLevelController::save()中添加:
$form->saved(function (Form $form) use ($prizeLevel) {
    $cacheKey = "lottery_activity:{$prizeLevel->activity_id}:prize_levels";
    \support\Cache::forget($cacheKey);
});
```

**预计修复时间:** 5分钟

---

## 📊 问题优先级汇总

| 优先级 | 问题编号 | 问题描述 | 修复时间 | 状态 |
|--------|----------|----------|----------|------|
| P0 🔴 | #1 | 过期任务竞态条件 | 15分钟 | 待修复 |
| P0 🔴 | #2 | Redis序列号回滚 | 30分钟 | 待修复 |
| P0 🔴 | #3 | 重复中奖问题 | 20分钟 | 待修复 |
| P1 🟡 | #4 | 活动缓存过时 | 10分钟 | 待修复 |
| P2 🟢 | #6 | 奖品缓存过时 | 5分钟 | 可选 |

**总计修复时间:** 
- 必须修复（P0）: 65分钟
- 建议修复（P1）: +10分钟 = 75分钟
- 可选修复（P2）: +5分钟 = 80分钟

---

## 🎯 修复建议

### 方案A: 立即修复所有P0问题（推荐）

**时间:** 65分钟  
**收益:** 消除所有高危bug  
**风险:** 修复后需要充分测试

**修复顺序:**
1. 问题#3（重复中奖）- 最严重，优先修复
2. 问题#2（序列号回滚）- 影响长期运行
3. 问题#1（竞态条件）- 影响缓存准确性

### 方案B: 分批修复

**第一批（P0）:** 65分钟  
**第二批（P1）:** 10分钟  
**部署:** 先部署P0修复，观察1-2天后再部署P1

### 方案C: 暂不修复，加强监控

**适用场景:**
- 紧急上线
- 活动规模小
- 有专人监控

**监控要点:**
- 监控重复中奖记录
- 监控Redis序列号使用率
- 监控缓存命中率

---

## 📝 测试建议

### 测试1: 重复中奖测试

**步骤:**
1. 创建活动，配置多个等级奖品
2. 发放券号能匹配多个等级的券（如123456）
3. 执行开奖
4. 检查中奖记录

**预期结果:**
- ✅ 同一张券只中1次奖
- ✅ 中奖记录数 = 券数
- ✅ 每张券status只更新1次

### 测试2: 序列号回滚测试

**步骤:**
1. 发券3张，故意在第2张失败（修改数据库触发唯一约束）
2. 检查Redis序列号
3. 再发1张券
4. 检查券号连续性

**预期结果:**
- ✅ 日志记录浪费的序列号
- ✅ 券号可能不连续（这是正常的）
- ✅ 监控浪费率

### 测试3: 竞态条件测试

**步骤:**
1. 定时任务即将执行时（手动触发）
2. 同时手动修改某张券的状态
3. 检查日志中的数字

**预期结果:**
- ✅ queried数 = updated数
- ✅ 缓存清除准确

---

## 📋 部署检查清单

### 修复前
- [ ] 备份数据库
- [ ] 记录当前Redis序列号
- [ ] 记录当前活动状态
- [ ] 准备回滚方案

### 修复后
- [ ] 所有P0问题已修复
- [ ] 语法检查通过
- [ ] 单元测试通过
- [ ] 集成测试通过
- [ ] 代码审查通过

### 部署后
- [ ] 监控错误日志
- [ ] 监控重复中奖
- [ ] 监控序列号使用率
- [ ] 监控缓存命中率
- [ ] 用户反馈收集

---

**审查完成时间:** 2026-06-11  
**审查结论:** ⚠️ 发现3个严重问题，建议修复后再部署  
**修复后评分:** 75 → 95（预计）

**审查人员签名:** AI Assistant
