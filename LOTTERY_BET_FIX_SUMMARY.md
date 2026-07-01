# 摸奖券打码量统计修复总结

## 🐛 问题描述

**原始问题：** 摸奖券活动没有累加打码量，也没有发放摸奖券。

**根本原因：** 打码量统计逻辑只包含了**机台游戏**（`player_game_log`），遗漏了**电子游戏**（`play_game_record`）的打码量。

---

## ✅ 修复方案

### 修改的文件

1. **`process/LotteryBetProgressScanTask.php`**
   - 方法：`getPlayerBetAmounts()`
   - 修改：同时统计机台游戏和电子游戏的打码量

2. **`addons/webman/service/LotteryTicketBetProgressService.php`**
   - 方法：`calculateTotalBetAmount()`
   - 修改：同时统计机台游戏和电子游戏的打码量

---

## 📊 打码量统计规则

### 机台游戏（player_game_log）

```sql
SELECT SUM(chip_amount) 
FROM player_game_log
WHERE department_id = ?
  AND created_at >= ?
  AND created_at < ?
  AND chip_amount > 0
```

**字段：** `chip_amount`（打码量）

---

### 电子游戏（play_game_record）

```sql
SELECT SUM(bet)
FROM play_game_record
WHERE department_id = ?
  AND created_at >= ?
  AND created_at < ?
  AND bet > 0
  AND settlement_status IN (0, 1)  -- 未结算 + 已结算
```

**字段：** `bet`（押注金额）

**状态过滤：**
- ✅ `0` - 未结算（SETTLEMENT_STATUS_UNSETTLED）
- ✅ `1` - 已结算（SETTLEMENT_STATUS_SETTLED）
- ❌ `2` - 已取消（SETTLEMENT_STATUS_CANCELLED）- 不计入

**为什么包含未结算？**
- 玩家已经完成下注，打码量应该立即计入
- 未结算只是平台还没有确认输赢，不影响打码进度
- 如果订单最终被取消（status=2），会从总打码量中扣除

---

## 🔄 工作流程

### 修复前

```
后台任务 LotteryBetProgressScanTask
  ↓
查询 player_game_log（仅机台游戏）
  ↓
累加打码量
  ↓
达标后发券
```

### 修复后

```
后台任务 LotteryBetProgressScanTask
  ↓
查询 player_game_log（机台游戏）
  +
查询 play_game_record（电子游戏）
  ↓
合并两个数据源的打码量
  ↓
累加总打码量
  ↓
达标后发券
```

---

## 🧪 测试验证

### 1. 运行测试脚本

```bash
php test_bet_amount_calculation.php 17
```

**预期输出：**
- ✅ 机台游戏打码量 > 0（如果有机台游戏记录）
- ✅ 电子游戏打码量 > 0（如果有电子游戏记录）
- ✅ 总打码量 = 机台 + 电子

---

### 2. 重启服务

```bash
php windows.php restart
```

重启后，后台任务会自动应用新的统计逻辑。

---

### 3. 验证自动发券

等待1-2分钟后检查：

```sql
-- 检查打码进度是否累加
SELECT player_id, 
       current_bet_amount,
       bet_amount_required,
       total_tickets_issued
FROM lottery_ticket_bet_progress
WHERE activity_id = 17
  AND current_bet_amount > 0
ORDER BY current_bet_amount DESC
LIMIT 10;

-- 检查是否发放了摸奖券
SELECT COUNT(*) as ticket_count,
       COUNT(DISTINCT player_id) as player_count
FROM lottery_ticket
WHERE activity_id = 17;
```

---

## 📝 代码修改详情

### LotteryBetProgressScanTask.php

**修改前：**
```php
protected function getPlayerBetAmounts(int $departmentId, string $startTime, string $endTime): array
{
    $results = Db::table('player_game_log')
        ->select(['player_id', Db::raw('SUM(chip_amount) as total_chip')])
        ->where('department_id', $departmentId)
        ->where('created_at', '>=', $startTime)
        ->where('created_at', '<', $endTime)
        ->where('chip_amount', '>', 0)
        ->groupBy('player_id')
        ->get();

    $playerBetAmounts = [];
    foreach ($results as $row) {
        $playerBetAmounts[$row->player_id] = floatval($row->total_chip);
    }

    return $playerBetAmounts;
}
```

**修改后：**
```php
protected function getPlayerBetAmounts(int $departmentId, string $startTime, string $endTime): array
{
    $playerBetAmounts = [];

    // 1. 统计机台游戏打码量
    $machineGameResults = Db::table('player_game_log')
        ->select(['player_id', Db::raw('SUM(chip_amount) as total_chip')])
        ->where('department_id', $departmentId)
        ->where('created_at', '>=', $startTime)
        ->where('created_at', '<', $endTime)
        ->where('chip_amount', '>', 0)
        ->groupBy('player_id')
        ->get();

    foreach ($machineGameResults as $row) {
        $playerBetAmounts[$row->player_id] = floatval($row->total_chip);
    }

    // 2. 统计电子游戏打码量（未结算 + 已结算）
    $onlineGameResults = Db::table('play_game_record')
        ->select(['player_id', Db::raw('SUM(bet) as total_bet')])
        ->where('department_id', $departmentId)
        ->where('created_at', '>=', $startTime)
        ->where('created_at', '<', $endTime)
        ->where('bet', '>', 0)
        ->whereIn('settlement_status', [0, 1])
        ->groupBy('player_id')
        ->get();

    foreach ($onlineGameResults as $row) {
        $playerId = $row->player_id;
        $betAmount = floatval($row->total_bet);

        if (isset($playerBetAmounts[$playerId])) {
            $playerBetAmounts[$playerId] += $betAmount;
        } else {
            $playerBetAmounts[$playerId] = $betAmount;
        }
    }

    return $playerBetAmounts;
}
```

---

## ⚠️ 注意事项

1. **历史数据不受影响**
   - 已经发放的摸奖券不会被重复发放
   - 只影响未来的打码量累加逻辑

2. **订单取消处理**
   - 如果电子游戏订单被取消（settlement_status = 2）
   - 该订单的打码量不会计入
   - 如果之前已经累加，需要手动调整

3. **数据一致性**
   - 两个统计方法保持一致
   - `LotteryBetProgressScanTask::getPlayerBetAmounts()`
   - `LotteryTicketBetProgressService::calculateTotalBetAmount()`

---

## 🎯 修复后的预期行为

1. ✅ 玩家在机台游戏打码 → 累加到进度
2. ✅ 玩家在电子游戏打码 → 累加到进度
3. ✅ 总打码量 = 机台打码 + 电子打码
4. ✅ 达到配置的打码量要求后，自动发放摸奖券

---

## 🔧 故障排查

如果修复后仍然不发券，检查：

1. **Webman 是否重启？**
   ```bash
   php windows.php restart
   ```

2. **是否有打码记录？**
   ```bash
   php test_bet_amount_calculation.php 17
   ```

3. **VIP配置是否匹配？**
   ```bash
   php debug_why_no_progress.php 17
   ```

4. **后台任务是否运行？**
   ```bash
   tail -50 runtime/logs/webman.log | grep "摸奖"
   ```

---

## 📅 修改日期

- **日期：** 2026-06-22
- **版本：** v1.0
- **影响范围：** 摸奖券打码量统计逻辑
- **兼容性：** 向后兼容，不影响现有数据
