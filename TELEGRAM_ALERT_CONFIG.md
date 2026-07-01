# Telegram 警报配置说明

## 当前配置

为了避免 Telegram 消息轰炸，系统已添加**智能冷却机制**：

### 1. MemoryTracker 中间件（请求级别监控）

**触发条件：**
- 单次请求内存消耗 ≥ 10 MB

**冷却机制：**
- **同一个控制器 30 分钟内只发送 1 次 Telegram 警报**
- 冷却期内的后续请求只记录 WARNING 日志（不发送 Telegram）

**配置位置：**
```php
// addons/webman/middleware/MemoryTracker.php
const TELEGRAM_COOLDOWN = 1800; // 30 分钟（单位：秒）
```

**示例：**
```
第 1 次：ChannelIndexController::storeIndex 消耗 12 MB → 发送 Telegram 🔔
第 2 次：ChannelIndexController::storeIndex 消耗 15 MB (5分钟后) → 不发送（冷却中）⏳
第 3 次：ChannelIndexController::storeIndex 消耗 11 MB (15分钟后) → 不发送（冷却中）⏳
第 4 次：ChannelIndexController::storeIndex 消耗 13 MB (35分钟后) → 发送 Telegram 🔔
```

---

### 2. MemoryMonitor 进程（进程级别监控）

**触发条件：**
- 进程内存 ≥ 150 MB（危险进程）
- 或增长率 ≥ 20 MB/分钟（严重泄漏）

**冷却机制：**
- **危险进程警报：30 分钟内只发送 1 次**
- **紧急报告生成：1 小时内只生成 1 次**

**配置位置：**
```php
// process/MemoryMonitor.php
const DANGER_ALERT_COOLDOWN = 1800; // 30 分钟（危险进程警报）
const EMERGENCY_REPORT_INTERVAL = 3600; // 1 小时（紧急报告）
```

---

## Telegram 消息级别

只有 **ERROR 级别** 的日志会发送到 Telegram（配置在 `config/log.php`）。

| 场景 | 第一次 | 冷却期内 | 冷却后 |
|------|--------|----------|--------|
| 极高内存请求（≥10MB） | ✅ ERROR → Telegram | ⚠️ WARNING → 仅日志 | ✅ ERROR → Telegram |
| 危险进程（≥150MB） | ✅ ERROR → Telegram | ⚠️ WARNING → 仅日志 | ✅ ERROR → Telegram |
| 严重泄漏（≥20MB/分钟） | ✅ ERROR → Telegram | ⚠️ WARNING → 仅日志 | ✅ ERROR → Telegram |
| 高内存请求（5-10MB） | ⚠️ WARNING → 仅日志 | ⚠️ WARNING → 仅日志 | ⚠️ WARNING → 仅日志 |
| 普通监控 | ℹ️ INFO → 仅日志 | ℹ️ INFO → 仅日志 | ℹ️ INFO → 仅日志 |

---

## 调整冷却时间

### 如果 Telegram 消息还是太多：

**延长冷却时间（减少通知频率）：**

```php
// 方案 1：延长到 1 小时
const TELEGRAM_COOLDOWN = 3600; // MemoryTracker
const DANGER_ALERT_COOLDOWN = 3600; // MemoryMonitor

// 方案 2：延长到 2 小时
const TELEGRAM_COOLDOWN = 7200;
const DANGER_ALERT_COOLDOWN = 7200;
```

### 如果想更及时收到通知：

**缩短冷却时间（增加通知频率）：**

```php
// 方案：缩短到 15 分钟
const TELEGRAM_COOLDOWN = 900;
const DANGER_ALERT_COOLDOWN = 900;
```

### 如果只想收到真正严重的问题：

**提高阈值：**

```php
// MemoryTracker.php - 提高极高内存阈值到 20 MB
const CRITICAL_MEMORY_THRESHOLD = 20; // 默认 10

// MemoryMonitor.php - 提高危险进程阈值到 200 MB
const DANGER_THRESHOLD = 200; // 默认 150
```

---

## 修改后的操作步骤

1. **编辑配置文件：**
   ```bash
   # MemoryTracker
   nano addons/webman/middleware/MemoryTracker.php
   
   # MemoryMonitor
   nano process/MemoryMonitor.php
   ```

2. **重启服务：**
   ```bash
   php start.php restart
   ```

3. **观察效果：**
   ```bash
   # 查看日志中的冷却提示
   tail -f runtime/logs/webman-$(date +%Y-%m-%d).log | grep "已抑制"
   ```

---

## 完全禁用 Telegram（仅记录日志）

如果临时不想收到任何 Telegram 通知：

**方法 1：修改 config/log.php**

```php
// 注释掉 Telegram Handler
// if (env('TELEGRAM_BOT_TOKEN') && env('TELEGRAM_CHAT_ID')) {
//     $handlers[] = [
//         'class' => app\service\TelegramService::class,
//         ...
//     ];
// }
```

**方法 2：清空 .env 中的 Telegram 配置**

```bash
# 临时禁用
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=
```

然后重启服务：`php start.php restart`

---

## 推荐配置

**生产环境推荐：**

```php
// MemoryTracker
const CRITICAL_MEMORY_THRESHOLD = 15; // 15 MB 才发送警报
const TELEGRAM_COOLDOWN = 3600; // 1 小时冷却

// MemoryMonitor
const DANGER_THRESHOLD = 200; // 200 MB 才发送警报
const DANGER_ALERT_COOLDOWN = 3600; // 1 小时冷却
const EMERGENCY_REPORT_INTERVAL = 7200; // 2 小时生成一次报告
```

**开发/调试环境推荐：**

```php
// MemoryTracker
const CRITICAL_MEMORY_THRESHOLD = 10; // 10 MB 就发送警报
const TELEGRAM_COOLDOWN = 1800; // 30 分钟冷却

// MemoryMonitor
const DANGER_THRESHOLD = 150; // 150 MB 就发送警报
const DANGER_ALERT_COOLDOWN = 1800; // 30 分钟冷却
const EMERGENCY_REPORT_INTERVAL = 3600; // 1 小时生成一次报告
```

---

## 检查当前配置

```bash
# 查看 MemoryTracker 配置
grep -A 2 "TELEGRAM_COOLDOWN\|CRITICAL_MEMORY_THRESHOLD" addons/webman/middleware/MemoryTracker.php

# 查看 MemoryMonitor 配置
grep -A 2 "DANGER_ALERT_COOLDOWN\|DANGER_THRESHOLD\|EMERGENCY_REPORT_INTERVAL" process/MemoryMonitor.php
```

---

## 冷却机制工作原理

系统会记住每个控制器/进程上次发送警报的时间：

```
内存中的记录：
{
  "ChannelIndexController::storeIndex": 1717056000,  // 上次警报时间戳
  "AgentPlayerController::index": 1717059600,
  ...
}
```

当再次检测到问题时：
1. 检查距离上次警报是否 ≥ 冷却时间
2. 如果是 → 发送新警报，更新时间戳
3. 如果否 → 只记录 WARNING 日志，显示剩余冷却时间

---

**修改完成后，请重启服务并观察 Telegram 消息频率！**
