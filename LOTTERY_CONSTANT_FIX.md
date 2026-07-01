# 状态常量错误修复

**修复日期:** 2026-06-10  
**发现人:** 用户  
**严重性:** 🔴 高（会导致SQL错误）

---

## 🐛 问题描述

在P3优化中，错误使用了不存在的常量 `LotteryTicketRecord::STATUS_CLAIMED`。

**错误代码:**
```php
// ❌ 错误
SUM(CASE WHEN status = ? THEN prize_amount ELSE 0 END) as claimed_prize_amount
', [LotteryTicketRecord::STATUS_CLAIMED])  // 常量不存在！
```

**实际常量定义（来自模型）:**
```php
// LotteryTicketRecord.php
const STATUS_PENDING = 0;   // 待发放
const STATUS_GRANTED = 1;   // 已发放 ✅ 应该用这个
const STATUS_FAILED = 2;    // 发放失败
```

---

## ✅ 修复内容

### 1. 控制器文件
**文件:** `addons/webman/controller/ChannelLotteryTicketStatisticsController.php`

**修复点1: getWinningStats() 方法**
```php
// 修复前
SUM(CASE WHEN status = ? THEN prize_amount ELSE 0 END) as claimed_prize_amount
', [LotteryTicketRecord::STATUS_CLAIMED])  // ❌

// 修复后
SUM(CASE WHEN status = ? THEN prize_amount ELSE 0 END) as granted_prize_amount
', [LotteryTicketRecord::STATUS_GRANTED])  // ✅
```

**修复点2: 返回数组键名**
```php
// 修复前
return [
    'claimed_prize_amount' => $stats->claimed_prize_amount ?? 0,  // ❌
];

// 修复后
return [
    'granted_prize_amount' => $stats->granted_prize_amount ?? 0,  // ✅
];
```

**修复点3: getActivityStats() 使用**
```php
// 修复前
$stats = array_merge($stats, [
    'claimed_prize_amount' => $winningStats['claimed_prize_amount'],  // ❌
]);

// 修复后
$stats = array_merge($stats, [
    'granted_prize_amount' => $winningStats['granted_prize_amount'],  // ✅
]);
```

**修复点4: 兼容方法重命名**
```php
// 修复前
protected function getClaimedPrizeAmount(int $activityId): float
{
    return LotteryTicketRecord::where('activity_id', $activityId)
        ->where('status', LotteryTicketRecord::STATUS_CLAIMED)  // ❌
        ->sum('prize_amount') ?? 0;
}

// 修复后
protected function getGrantedPrizeAmount(int $activityId): float
{
    return LotteryTicketRecord::where('activity_id', $activityId)
        ->where('status', LotteryTicketRecord::STATUS_GRANTED)  // ✅
        ->sum('prize_amount') ?? 0;
}
```

---

### 2. 文档文件

**文件1:** `LOTTERY_CODE_FIXES.md`
- ✅ 已修复所有 `STATUS_CLAIMED` → `STATUS_GRANTED`

**文件2:** `LOTTERY_CODE_AUDIT_REPORT.md`
- ✅ 已修复所有 `STATUS_CLAIMED` → `STATUS_GRANTED`
- ✅ 已修复 `claimed_prize_amount` → `granted_prize_amount`

---

## 📊 影响分析

### API返回字段名称变化

**修复前:**
```json
{
  "claimed_prize_amount": 160000
}
```

**修复后:**
```json
{
  "granted_prize_amount": 160000
}
```

**⚠️ 注意:** 这是一个**破坏性变更**，如果前端已经使用了 `claimed_prize_amount` 字段，需要同步修改。

---

## 🔍 验证方法

### 1. 检查常量是否存在
```bash
# 搜索是否还有 STATUS_CLAIMED
grep -r "STATUS_CLAIMED" addons/webman/

# 预期: 无结果
```

### 2. 测试SQL查询
```php
// 在控制器中测试
$stats = LotteryTicketRecord::where('activity_id', 1)
    ->selectRaw('
        SUM(CASE WHEN status = ? THEN prize_amount ELSE 0 END) as granted
    ', [LotteryTicketRecord::STATUS_GRANTED])
    ->first();

var_dump($stats->granted);
// 预期: 正常返回数值，不报错
```

### 3. 测试API
```bash
# 调用统计API
curl "http://localhost:8789/ex-admin/.../getActivityStats?activity_id=1"

# 检查返回
{
  "granted_prize_amount": 160000  // ✅ 字段名正确
}
```

---

## 🚨 前端兼容性处理

如果前端已经使用了旧字段名，有两种处理方案：

### 方案A: 前端同步修改（推荐）
```javascript
// 修改前
const claimedAmount = data.claimed_prize_amount;

// 修改后
const grantedAmount = data.granted_prize_amount;
```

### 方案B: 后端兼容（临时）
```php
// 在控制器返回前添加兼容字段
$stats['claimed_prize_amount'] = $stats['granted_prize_amount'];  // 向后兼容

return response()->json([
    'code' => 200,
    'data' => $stats
]);
```

---

## ✅ 修复验证清单

- [x] 检查所有 `STATUS_CLAIMED` 已替换为 `STATUS_GRANTED`
- [x] 检查所有 `claimed_prize_amount` 键名已更新
- [x] 修复文档中的示例代码
- [x] 验证SQL查询不报错
- [ ] 通知前端开发者字段名变化
- [ ] 前端同步修改或添加兼容处理

---

## 📝 经验教训

1. **使用IDE常量提示** - 避免手写常量名
2. **检查模型定义** - 使用常量前先查看模型
3. **单元测试覆盖** - 如果有测试会立即发现
4. **Code Review** - 同事审查可发现此类错误

---

**修复状态:** ✅ 已完成  
**测试状态:** ⏳ 待验证  
**前端通知:** ⏳ 待通知  

