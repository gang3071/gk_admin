# 最终代码风格统一

## ✅ 统一标准

**所有 Eloquent 模型查询统一使用 `Model::query()->where()` 格式**

---

## 📝 统一原因

### 问题：IDE 无法识别静态方法

```php
// ❌ IDE 可能无法正确提示方法
Player::where('id', 1)->get();

// ✅ IDE 可以正确识别 Builder 的所有方法
Player::query()->where('id', 1)->get();
```

### 原理

```php
// Model::where() 是魔术静态方法
// 通过 __callStatic 转发到 Builder
// IDE 可能无法识别这种动态调用

// Model::query() 显式返回 Builder 实例
// IDE 可以完美识别 Builder 的所有方法
public static function query(): Builder
{
    return (new static)->newQuery();
}
```

---

## 📋 修改的文件

### 1. `process/LotteryBetProgressScanTask.php`

```php
// ✅ 统一后的代码
protected function getPlayerBetAmounts(int $departmentId, string $startTime, string $endTime): array
{
    $playerBetAmounts = [];

    // 1. 统计机台游戏打码量
    $machineGameResults = PlayerGameLog::query()
        ->selectRaw('player_id, SUM(chip_amount) as total_chip')
        ->where('department_id', $departmentId)
        ->where('created_at', '>=', $startTime)
        ->where('created_at', '<', $endTime)
        ->where('chip_amount', '>', 0)
        ->groupBy('player_id')
        ->get();

    foreach ($machineGameResults as $row) {
        $playerBetAmounts[$row->player_id] = floatval($row->total_chip);
    }

    // 2. 统计电子游戏打码量
    $onlineGameResults = PlayGameRecord::query()
        ->selectRaw('player_id, SUM(bet) as total_bet')
        ->where('department_id', $departmentId)
        ->where('created_at', '>=', $startTime)
        ->where('created_at', '<', $endTime)
        ->where('bet', '>', 0)
        ->whereIn('settlement_status', [
            PlayGameRecord::SETTLEMENT_STATUS_UNSETTLED,  // 0: 未结算
            PlayGameRecord::SETTLEMENT_STATUS_SETTLED,    // 1: 已结算
        ])
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

### 2. `addons/webman/service/LotteryTicketBetProgressService.php`

```php
// ✅ 统一后的代码
public static function calculateTotalBetAmount(int $activityId, int $playerId): float
{
    $activity = LotteryTicketActivity::find($activityId);
    if (!$activity) {
        return 0;
    }

    // 1. 统计机台游戏打码量
    $machineChip = PlayerGameLog::query()
        ->where('player_id', $playerId)
        ->where('department_id', $activity->department_id)
        ->where('created_at', '>=', $activity->start_time)
        ->where('created_at', '<=', $activity->end_time)
        ->sum('chip_amount') ?? 0;

    // 2. 统计电子游戏打码量
    $onlineBet = PlayGameRecord::query()
        ->where('player_id', $playerId)
        ->where('department_id', $activity->department_id)
        ->where('created_at', '>=', $activity->start_time)
        ->where('created_at', '<=', $activity->end_time)
        ->whereIn('settlement_status', [
            PlayGameRecord::SETTLEMENT_STATUS_UNSETTLED,  // 0: 未结算
            PlayGameRecord::SETTLEMENT_STATUS_SETTLED,    // 1: 已结算
        ])
        ->sum('bet') ?? 0;

    // 3. 返回总打码量
    return floatval($machineChip) + floatval($onlineBet);
}
```

---

## 🎯 代码风格规范

### ✅ 推荐写法

```php
// 1. 查询构建器
$users = User::query()
    ->where('status', 1)
    ->orderBy('id', 'desc')
    ->get();

// 2. 聚合查询
$total = Order::query()
    ->where('status', 'paid')
    ->sum('amount');

// 3. 条件查询
$count = Player::query()
    ->whereIn('vip_level_id', [1, 2, 3])
    ->count();

// 4. 原始SQL
$results = PlayerGameLog::query()
    ->selectRaw('player_id, SUM(chip_amount) as total')
    ->groupBy('player_id')
    ->get();
```

### ❌ 不推荐写法

```php
// 直接调用静态方法（IDE 可能无法识别）
$users = User::where('status', 1)->get();

// 使用 Db::table（失去模型特性）
$users = Db::table('users')->where('status', 1)->get();
```

---

## 🔍 IDE 支持对比

### 使用 `Model::where()` 时

```php
Player::where('id', 1)
    ->orderBy('name')   // ⚠️ IDE 可能无法提示
    ->limit(10)         // ⚠️ IDE 可能无法提示
    ->get();            // ⚠️ IDE 可能无法提示
```

### 使用 `Model::query()->where()` 时

```php
Player::query()
    ->where('id', 1)
    ->orderBy('name')   // ✅ IDE 完美提示
    ->limit(10)         // ✅ IDE 完美提示
    ->get();            // ✅ IDE 完美提示
```

---

## 📚 其他推荐的查询写法

### 单条查询

```php
// ✅ 推荐
$player = Player::query()->find($id);
$player = Player::query()->where('uuid', $uuid)->first();

// ⚠️ 也可以，但不如上面明确
$player = Player::find($id);
```

### 统计查询

```php
// ✅ 推荐
$count = Player::query()
    ->where('status', 1)
    ->count();

$sum = PlayerGameLog::query()
    ->where('player_id', $playerId)
    ->sum('chip_amount');
```

### 关联查询

```php
// ✅ 推荐
$players = Player::query()
    ->with(['channel', 'vipLevel'])
    ->where('department_id', $departmentId)
    ->get();
```

### 批量更新

```php
// ✅ 推荐
Player::query()
    ->where('status', 0)
    ->update(['status' => 1]);
```

---

## ✅ 验证

代码风格统一后，功能保持不变：

```bash
# 运行测试脚本
php test_bet_amount_calculation.php 17

# 重启服务
php windows.php restart

# 验证功能正常
echo "SELECT COUNT(*) FROM lottery_ticket_bet_progress WHERE activity_id = 17;" | mysql -h127.0.0.1 -uroot -proot yjb
```

---

## 📅 修改记录

- **日期：** 2026-06-22
- **版本：** v2.0（最终版）
- **修改类型：** 代码风格统一
- **核心变更：** 所有模型查询改为 `Model::query()->where()` 格式
- **影响范围：** 
  - `process/LotteryBetProgressScanTask.php`
  - `addons/webman/service/LotteryTicketBetProgressService.php`
- **兼容性：** 完全向后兼容，功能不变
