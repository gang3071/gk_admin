# 代码风格统一完成

## ✅ 统一内容

将所有数据库查询从 `Db::table()` 方式改为 **Eloquent 模型** 方式。

---

## 📝 修改的文件

### 1. `process/LotteryBetProgressScanTask.php`

**修改前（Db::table 方式）：**
```php
$results = Db::table('player_game_log')
    ->select(['player_id', Db::raw('SUM(chip_amount) as total_chip')])
    ->where('department_id', $departmentId)
    ->groupBy('player_id')
    ->get();
```

**修改后（Eloquent 模型方式）：**
```php
use addons\webman\model\PlayerGameLog;
use addons\webman\model\PlayGameRecord;

$machineGameResults = PlayerGameLog::selectRaw('player_id, SUM(chip_amount) as total_chip')
    ->where('department_id', $departmentId)
    ->groupBy('player_id')
    ->get();

$onlineGameResults = PlayGameRecord::selectRaw('player_id, SUM(bet) as total_bet')
    ->where('department_id', $departmentId)
    ->whereIn('settlement_status', [
        PlayGameRecord::SETTLEMENT_STATUS_UNSETTLED,
        PlayGameRecord::SETTLEMENT_STATUS_SETTLED,
    ])
    ->groupBy('player_id')
    ->get();
```

---

### 2. `addons/webman/service/LotteryTicketBetProgressService.php`

**添加 use 语句：**
```php
use addons\webman\model\PlayGameRecord;
```

**统一使用短类名：**
```php
// 修改前
$onlineBet = \addons\webman\model\PlayGameRecord::where('player_id', $playerId)
    ->whereIn('settlement_status', [
        \addons\webman\model\PlayGameRecord::SETTLEMENT_STATUS_UNSETTLED,
        \addons\webman\model\PlayGameRecord::SETTLEMENT_STATUS_SETTLED,
    ])
    ->sum('bet') ?? 0;

// 修改后
$onlineBet = PlayGameRecord::where('player_id', $playerId)
    ->whereIn('settlement_status', [
        PlayGameRecord::SETTLEMENT_STATUS_UNSETTLED,
        PlayGameRecord::SETTLEMENT_STATUS_SETTLED,
    ])
    ->sum('bet') ?? 0;
```

---

## 🎯 统一后的优势

### 1. **类型提示更好**
```php
// Eloquent 模型返回 Collection
$results = PlayerGameLog::selectRaw('...')->get();
// IDE 可以提示 Collection 的方法

// Db::table 返回 stdClass
$results = Db::table('player_game_log')->get();
// 没有类型提示
```

### 2. **可以使用模型常量**
```php
// ✅ 清晰易懂
PlayGameRecord::SETTLEMENT_STATUS_UNSETTLED
PlayGameRecord::SETTLEMENT_STATUS_SETTLED

// ❌ 魔法数字
->whereIn('settlement_status', [0, 1])
```

### 3. **可以利用模型关联**
```php
// 如果未来需要关联查询
$records = PlayerGameLog::with('player', 'machine')->get();
```

### 4. **可以利用模型的数据权限**
```php
// PlayerGameLog 模型有 DataPermissions trait
// 自动过滤 department_id
```

### 5. **代码更易维护**
```php
// 表名变更时只需修改模型类
// Db::table('player_game_log') ← 需要全局搜索替换
// PlayerGameLog:: ← 只需修改模型类的 $table 属性
```

---

## 📋 完整的 use 语句清单

### LotteryBetProgressScanTask.php
```php
use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\PlayerGameLog;
use addons\webman\model\PlayGameRecord;  // ⭐ 新增
use addons\webman\service\LotteryTicketBetProgressService;
use support\Cache;
use support\Db;
use support\Log;
use Workerman\Crontab\Crontab;
use Workerman\Worker;
```

### LotteryTicketBetProgressService.php
```php
use addons\webman\model\LotteryTicket;
use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\LotteryTicketBetProgress;
use addons\webman\model\LotteryTicketVipConfig;
use addons\webman\model\Player;
use addons\webman\model\PlayerGameLog;
use addons\webman\model\PlayGameRecord;  // ⭐ 新增
use support\Db;
use support\Log;
```

---

## ✅ 验证

代码统一后，功能保持不变：

```bash
# 运行测试脚本
php test_bet_amount_calculation.php 17

# 重启服务
php windows.php restart

# 验证是否正常工作
echo "SELECT COUNT(*) FROM lottery_ticket_bet_progress WHERE activity_id = 17;" | mysql -h127.0.0.1 -uroot -proot yjb
```

---

## 📅 修改日期

- **日期：** 2026-06-22
- **版本：** v1.1
- **修改类型：** 代码风格统一（功能不变）
- **影响范围：** 打码量统计逻辑
