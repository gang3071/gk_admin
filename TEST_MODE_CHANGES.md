# 测试模式修改记录

## 📅 修改日期
2026-06-18

---

## 🎯 修改内容

### 移除活动时长最小限制（测试阶段）

**文件：** `addons/webman/controller/ChannelLotteryTicketActivityController.php`

**行号：** 389-400

---

## 📝 修改详情

### 修改前（生产限制）

```php
// 验证活动时长（至少1小时，最多30天）
$duration = $endTime - $startTime;
$minDuration = 3600; // 1小时
$maxDuration = 30 * 24 * 3600; // 30天

if ($duration < $minDuration) {
    throw new \Exception(admin_trans('lottery_ticket.error.duration_too_short', null, ['min' => '1小时']));
}

if ($duration > $maxDuration) {
    throw new \Exception(admin_trans('lottery_ticket.error.duration_too_long', null, ['max' => '30天']));
}
```

**限制：**
- ❌ 最小时长：1小时（3600秒）
- ✅ 最大时长：30天

---

### 修改后（测试模式）

```php
// 验证活动时长（测试阶段无最小时长限制，最多30天）
$duration = $endTime - $startTime;
// ⚠️ 测试阶段：已移除最小1小时限制
// $minDuration = 3600; // 1小时
$maxDuration = 30 * 24 * 3600; // 30天

// ⚠️ 测试阶段：已禁用最小时长检查
// if ($duration < $minDuration) {
//     throw new \Exception(admin_trans('lottery_ticket.error.duration_too_short', null, ['min' => '1小时']));
// }

if ($duration > $maxDuration) {
    throw new \Exception(admin_trans('lottery_ticket.error.duration_too_long', null, ['max' => '30天']));
}
```

**限制：**
- ✅ 最小时长：**无限制**（可以创建几分钟的测试活动）
- ✅ 最大时长：30天

---

## 🎯 效果

### 修改前

创建活动时，必须满足：
- ✅ 开始时间 < 结束时间
- ❌ 活动时长 ≥ 1小时
- ✅ 活动时长 ≤ 30天

**示例：**
```
开始时间：2026-06-18 10:00:00
结束时间：2026-06-18 10:30:00
时长：30分钟
结果：❌ 报错 "活动时长至少需要1小时"
```

---

### 修改后

创建活动时，只需满足：
- ✅ 开始时间 < 结束时间
- ✅ 活动时长 ≤ 30天

**示例：**
```
开始时间：2026-06-18 10:00:00
结束时间：2026-06-18 10:10:00
时长：10分钟
结果：✅ 成功创建
```

---

## 📊 测试场景

### 现在可以创建的测试活动

#### 场景 1：10分钟测试活动

```
活动名称：测试活动-10分钟
开始时间：2026-06-18 14:00:00
结束时间：2026-06-18 14:10:00
时长：10分钟
状态：✅ 允许创建
```

#### 场景 2：5分钟快速测试

```
活动名称：快速测试
开始时间：2026-06-18 14:00:00
结束时间：2026-06-18 14:05:00
时长：5分钟
状态：✅ 允许创建
```

#### 场景 3：1分钟极限测试

```
活动名称：极限测试
开始时间：2026-06-18 14:00:00
结束时间：2026-06-18 14:01:00
时长：1分钟
状态：✅ 允许创建（但不推荐，时间太短）
```

---

## ⚠️ 注意事项

### 1. 仅用于测试环境

**这个修改仅适用于测试环境！**

生产环境上线前**必须恢复**最小时长限制：

```php
// 恢复生产限制
$minDuration = 3600; // 1小时

if ($duration < $minDuration) {
    throw new \Exception(admin_trans('lottery_ticket.error.duration_too_short', null, ['min' => '1小时']));
}
```

---

### 2. 短时长活动的风险

创建过短的活动可能导致：

- ⚠️ 玩家来不及参与
- ⚠️ 打码进度更新延迟（定时任务每分钟扫描一次）
- ⚠️ 状态流转异常（定时任务每分钟检查一次）

**建议测试时长：**
- ✅ 推荐：≥ 5分钟
- ⚠️ 谨慎：1-5分钟
- ❌ 不推荐：< 1分钟

---

### 3. 定时任务频率

相关定时任务：

| 任务 | 频率 | Crontab | 说明 |
|------|------|---------|------|
| **打码进度扫描** | 每分钟 | `23 * * * * *` | 扫描游戏记录，更新打码进度 |
| **活动状态流转** | 每分钟 | `0 */1 * * * *` | 检查时间节点，自动更新状态 |

**影响：**
- 活动时长 < 2分钟：可能状态还没来得及流转就结束了
- 活动时长 < 5分钟：打码进度可能只扫描1-2次

---

## 🔄 恢复生产限制

### 何时恢复

- ✅ 测试完成后
- ✅ 准备上生产环境前
- ✅ 发现测试活动影响数据统计时

---

### 如何恢复

**文件：** `addons/webman/controller/ChannelLotteryTicketActivityController.php`

```php
// 验证活动时长（至少1小时，最多30天）
$duration = $endTime - $startTime;
$minDuration = 3600; // 1小时  ✅ 取消注释
$maxDuration = 30 * 24 * 3600; // 30天

if ($duration < $minDuration) {  ✅ 取消注释
    throw new \Exception(admin_trans('lottery_ticket.error.duration_too_short', null, ['min' => '1小时']));
}

if ($duration > $maxDuration) {
    throw new \Exception(admin_trans('lottery_ticket.error.duration_too_long', null, ['max' => '30天']));
}
```

**或者使用环境变量控制：**

```php
// 验证活动时长
$duration = $endTime - $startTime;
$maxDuration = 30 * 24 * 3600; // 30天

// ✅ 根据环境变量决定是否检查最小时长
if (env('APP_ENV') === 'production') {
    $minDuration = 3600; // 生产环境：最小1小时
    if ($duration < $minDuration) {
        throw new \Exception(admin_trans('lottery_ticket.error.duration_too_short', null, ['min' => '1小时']));
    }
}
// 测试环境：无最小时长限制

if ($duration > $maxDuration) {
    throw new \Exception(admin_trans('lottery_ticket.error.duration_too_long', null, ['max' => '30天']));
}
```

---

## 📋 验证修改

### 测试步骤

1. **创建短时长活动**
   ```
   登录后台 → 摸奖券活动管理 → 创建活动
   开始时间：当前时间
   结束时间：当前时间 + 10分钟
   ```

2. **预期结果**
   - ✅ 创建成功
   - ✅ 不再提示"活动时长至少需要1小时"
   - ✅ 活动正常显示

3. **验证活动流转**
   ```bash
   # 观察日志
   tail -f runtime/logs/webman.log | grep "摸奖券活动"
   
   # 应该看到：
   # [时间] 摸奖券活动开始 {...}
   # [时间+10分钟] 活动状态流转 (如果手动开奖)
   ```

---

## 🎉 修改完成

### 修改内容

✅ **已移除最小时长限制**
- 测试阶段可创建任意时长活动
- 仅保留最大30天限制

### 影响范围

- ✅ 创建活动表单验证
- ✅ 编辑活动表单验证
- ❌ 不影响其他业务逻辑

### 建议

- ⚠️ 仅用于测试环境
- ⚠️ 生产环境上线前恢复限制
- ⚠️ 测试活动建议时长 ≥ 5分钟

---

**修改者：** Claude Code  
**修改日期：** 2026-06-18  
**生产恢复：** ⏳ 待上线前执行
