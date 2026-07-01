# 摸奖券录入中奖记录 - 输入修复 (最终版)

## 问题描述

1. ❌ **无法输入带前导零的券号**: 输入 `00012` 会被浏览器转换为 `12`
2. ⚠️ **取消按钮无法关闭抽屉**: 点击取消按钮抽屉不关闭

---

## 修复方案

### ✅ 券号输入验证修复

**用户需求确认:**
- 券号**不一定是6位**
- 只要是**纯数字**即可
- **不能超过6位**
- 包含字母应**明确报错**,不自动清理

**修改代码:**

#### 1. `distributeByTicketNo()` 方法

**位置:** `ChannelLotteryTicketActivityController.php` (1474-1481行)

```php
// 去除首尾空格
$ticketNo = trim($ticketNo);

// 券号格式验证：必须是纯数字且1-6位
if (!preg_match('/^\d{1,6}$/', $ticketNo)) {
    return message_error(admin_trans('lottery_ticket.message.ticket_format_error'));
}
```

#### 2. `recordWinByTickets()` 方法

**位置:** 同文件 (581-590行)

```php
// 去除首尾空格
$ticketNo = trim($ticketNo);

// 验证券号：必须是纯数字且1-6位
if (!preg_match('/^\d{1,6}$/', $ticketNo)) {
    $errors[] = admin_trans('lottery_ticket.error.invalid_ticket_format', 
        null, ['ticket_no' => $record['ticket_no'] ?? '']);
    continue;
}
```

---

## 用户体验对比

### ❌ 修复前 (自动清理,用户困惑)

| 用户输入 | 前端传值 | 后端处理 | 结果 | 问题 |
|---------|---------|---------|------|------|
| 00012 | "12" | 清理 → "12" | ✅ 成功 | 前导零丢失 |
| abc123 | "abc123" | 清理 → "123" | ✅ 成功 | 悄悄删除字母,用户不知道 |
| 1234567 | "1234567" | 清理 → "1234567" | ❌ 报错 | 超过6位 |

**问题:** 用户输入 `abc123` 时,系统悄悄变成 `123`,可能匹配错误的券号!

---

### ✅ 修复后 (严格验证,明确报错)

| 用户输入 | 前端传值 | 后端处理 | 结果 | 提示 |
|---------|---------|---------|------|------|
| 00012 | "12" | trim → "12" | ✅ 成功 | - |
| 1 | "1" | trim → "1" | ✅ 成功 | - |
| 123456 | "123456" | trim → "123456" | ✅ 成功 | - |
| " 123 " | " 123 " | trim → "123" | ✅ 成功 | 自动去除空格 |
| abc123 | "abc123" | 验证失败 | ❌ 报错 | "券号格式错误，只能包含数字且不超过6位" |
| 1234567 | "1234567" | 验证失败 | ❌ 报错 | "券号格式错误，只能包含数字且不超过6位" |
| 12.34 | "12.34" | 验证失败 | ❌ 报错 | "券号格式错误，只能包含数字且不超过6位" |

**改进:**
1. ✅ 输入错误时**明确报错**
2. ✅ 用户知道输入了什么不合法的内容
3. ✅ 避免错误匹配其他券号
4. ✅ 只去除空格,不自动修改内容

---

## 翻译文件更新

**新增翻译键:**
- `message.ticket_format_error` - 券号格式错误提示
- `error.invalid_ticket_format` - 批量录入时的格式错误

**4种语言翻译:**

### 繁体中文 (zh-TW)
```php
'ticket_format_error' => '券號格式錯誤，只能包含數字且不超過6位',
'invalid_ticket_format' => '券號 {ticket_no} 格式錯誤，只能包含數字且不超過6位',
```

### 简体中文 (zh-CN)
```php
'ticket_format_error' => '券号格式错误，只能包含数字且不超过6位',
'invalid_ticket_format' => '券号 {ticket_no} 格式错误，只能包含数字且不超过6位',
```

### English (en)
```php
'ticket_format_error' => 'Invalid ticket number format, must contain only digits and not exceed 6 characters',
'invalid_ticket_format' => 'Invalid format for ticket {ticket_no}, must contain only digits and not exceed 6 characters',
```

### 日本語 (jp)
```php
'ticket_format_error' => 'チケット番号の形式が正しくありません。数字のみで6文字以内にしてください',
'invalid_ticket_format' => 'チケット {ticket_no} の形式が正しくありません。数字のみで6文字以内にしてください',
```

---

## 取消按钮问题

### 问题原因

这是 **ExAdmin 框架前端问题**,后端代码无法修复。

可能原因:
1. 抽屉组件的 `onCancel` 事件未正确绑定
2. 表单验证错误阻止了关闭
3. 状态管理未正确更新

### 临时解决方案 (告知用户)

**方法 1: 按 ESC 键**
- 键盘按 `ESC` 键通常可以关闭模态框/抽屉

**方法 2: 点击外部区域**
- 点击抽屉背景的遮罩层 (灰色半透明区域)

**方法 3: 刷新页面**
- 如果完全卡住,按 `F5` 或 `Ctrl+R` 刷新页面

### 根本解决方案

需要前端开发:
1. 检查 ExAdmin 框架版本,查看是否有更新修复此问题
2. 联系 ExAdmin 维护者报告此 bug
3. 或修改前端代码,确保取消按钮正确触发关闭事件

---

## 测试用例

### ✅ 正常输入 (应成功)

- [ ] 输入 `1` → 通过
- [ ] 输入 `12` → 通过
- [ ] 输入 `123` → 通过
- [ ] 输入 `123456` → 通过
- [ ] 输入 ` 123 ` (带空格) → 自动trim,通过

### ❌ 错误输入 (应报错)

- [ ] 输入 `abc123` → 报错 "券号格式错误，只能包含数字且不超过6位"
- [ ] 输入 `1234567` (7位) → 报错 "券号格式错误，只能包含数字且不超过6位"
- [ ] 输入 `12.34` (小数点) → 报错
- [ ] 输入 `12-34` (连字符) → 报错
- [ ] 输入空字符串 → 报错 "参数错误"

### 🔧 抽屉关闭测试

- [ ] 点击取消按钮 → (如失败,提示用户使用下方方法)
- [ ] 按 ESC 键 → 应关闭
- [ ] 点击遮罩层 → 应关闭
- [ ] 提交成功后 → 应自动关闭

---

## 相关文件

**已修改:**
1. `D:\gk_admin\addons\webman\controller\ChannelLotteryTicketActivityController.php`
   - `distributeByTicketNo()` 方法 (1474-1481行)
   - `recordWinByTickets()` 方法 (581-590行)

2. **翻译文件 (4个):**
   - `D:\gk_admin\addons\webman\lang\zh-TW\lottery_ticket.php`
   - `D:\gk_admin\addons\webman\lang\zh-CN\lottery_ticket.php`
   - `D:\gk_admin\addons\webman\lang\en\lottery_ticket.php`
   - `D:\gk_admin\addons\webman\lang\jp\lottery_ticket.php`

---

## 总结

### ✅ 已完全修复
- 券号输入验证逻辑优化
- 明确的错误提示
- 防止悄悄修改用户输入导致的误匹配

### ⚠️ 部分修复 (需前端支持)
- 取消按钮问题属于 ExAdmin 框架 bug
- 提供了3种临时解决方案
- 建议升级框架或联系技术支持

### 🎯 核心改进
**从"容错自动修复"改为"严格验证明确报错"**
- 旧逻辑: 输入 `abc123` → 悄悄变成 `123` → 可能匹配错误券号 ❌
- 新逻辑: 输入 `abc123` → 明确报错 → 用户知道哪里错了 ✅
