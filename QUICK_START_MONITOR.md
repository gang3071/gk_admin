# 内存监控快速启动指南

**5分钟完成部署和验证** 🚀

---

## 🎯 立即开始（3种方式）

### 方式1: 自动监控进程（推荐 ⭐）

**最省心，自动分析问题原因**

```bash
# 1. 重启Webman服务（启动监控进程）
cd D:\gk_admin
php start.php restart

# 2. 确认监控进程已启动
php start.php status
# 应该看到: memory_monitor 进程正在运行

# 3. 实时查看监控输出
tail -f runtime/logs/webman.log | grep "内存监控"

# 或者只看重要信息
tail -f runtime/logs/webman.log | grep -E "⚠️|🔴|修复验证"
```

**监控频率：**
- 每 60 秒 - 内存检查
- 每 10 分钟 - 趋势分析报告
- 检测到问题 - 立即分析原因

**优点：**
- ✅ 全自动，无需手动操作
- ✅ 智能分析泄漏原因
- ✅ 自动验证修复是否生效
- ✅ 生成趋势图和紧急报告

---

### 方式2: 手动监控脚本（简单快速）

**Windows用户使用 .bat 文件**

```bash
# 双击运行，或在命令行：
D:\gk_admin\monitor_memory.bat
```

**Linux/Mac用户使用 .sh 文件**

```bash
# 赋予执行权限
chmod +x D:\gk_admin\monitor_memory.sh

# 运行
./monitor_memory.sh
```

**特点：**
- ✅ 彩色输出，易读
- ✅ 实时显示趋势
- ✅ 按Ctrl+C停止后自动生成汇总
- ⚠️ 需要保持窗口打开

---

### 方式3: 一行命令监控（极简）

**Windows (PowerShell):**

```powershell
while($true) {
    Get-Date -Format "yyyy-MM-dd HH:mm:ss";
    Get-Process php | Select-Object Id, @{Name="Memory(MB)";Expression={[math]::Round($_.WS/1MB, 2)}} | Where-Object {$_.'Memory(MB)' -gt 30};
    Start-Sleep -Seconds 60
}
```

**Linux/Mac:**

```bash
watch -n 60 'date && ps aux | grep webman | grep -v grep | awk "{printf \"PID: %s Memory: %.2f MB\n\", \$2, \$6/1024}"'
```

---

## 📊 预期看到的输出

### 修复生效的标志 ✅

```
[2026-05-28 14:30:00] 内存监控报告
✅ PID: 12345 | 内存: 85.32 MB | 增长: +1.20 MB/分 | 趋势: ↑ | 状态: 正常
✅ PID: 12346 | 内存: 92.45 MB | 增长: +1.55 MB/分 | 趋势: ↑ | 状态: 正常
📊 汇总 | 平均: 88.89 MB

修复验证:
  ✅ 修复已生效 - 单次请求内存正常（< 3 MB）
```

**特征：**
- 平均内存 < 100 MB
- 增长率 < 3 MB/分钟
- 趋势: ↑ 或 ━
- 状态: 正常

---

### 修复部分生效 ⚠️

```
[2026-05-28 14:30:00] 内存监控报告
⚠️ PID: 12347 | 内存: 256.78 MB | 增长: +4.30 MB/分 | 趋势: ↑↑ | 状态: 警告
📊 汇总 | 平均: 156.23 MB

修复验证:
  ⚠️ 修复部分生效 - 单次请求内存偏高（3-5 MB）
  建议检查是否还有其他未优化的查询
```

**特征：**
- 平均内存 100-200 MB
- 增长率 3-8 MB/分钟
- 趋势: ↑↑
- 状态: 警告

**处理：** 继续观察，可能需要进一步优化

---

### 修复未生效 ❌

```
[2026-05-28 14:30:00] 内存监控报告
🔴 PID: 12348 | 内存: 856.78 MB | 增长: +15.50 MB/分 | 趋势: ↑↑↑ | 状态: 危险
📊 汇总 | 平均: 456.78 MB

⚡ 检测到异常内存增长
🔍 内存泄漏分析:
  可能原因:
    • 严重泄漏 - 可能存在全量数据加载（get()）未释放
    • ❌ 修复未生效或存在其他泄漏源
    请检查:
      1. 代码是否已正确部署
      2. 服务是否已重启
      3. 是否还有其他未修复的泄漏点
```

**特征：**
- 平均内存 > 300 MB
- 增长率 > 10 MB/分钟
- 趋势: ↑↑↑
- 状态: 危险

**处理：** 立即检查代码部署和服务重启

---

## ⚡ 快速验证修复是否生效

**步骤：**

1. **部署修复代码（如果还没做）**
   ```bash
   # 确认文件已修改
   grep -n "lazy(500)" addons/webman/controller/StorePlayerController.php
   # 应该在第241行看到 lazy(500)

   grep -c "whereExists" addons/webman/common/Login.php
   # 应该看到至少 3 个
   ```

2. **重启服务**
   ```bash
   php start.php restart
   ```

3. **开始监控（选择上面3种方式之一）**

4. **等待10-20分钟，观察趋势**

5. **判断结果**
   - ✅ **成功**: 平均内存 < 100 MB，增长 < 3 MB/分
   - ⚠️ **部分**: 平均内存 100-200 MB，增长 3-8 MB/分
   - ❌ **失败**: 平均内存 > 300 MB，增长 > 10 MB/分

---

## 🔍 问题排查

### Q: 没有看到监控输出？

**检查：**

```bash
# 1. 确认进程配置
cat config/process.php | grep memory_monitor

# 2. 查看所有进程
php start.php status

# 3. 查看启动日志
tail -n 100 runtime/logs/webman.log | grep -i "memory"
```

**解决：**

```bash
# 重启服务
php start.php stop
php start.php start -d
```

---

### Q: 看到 "未检测到Webman进程"？

**原因：** Webman服务未启动或已停止

**解决：**

```bash
# 启动Webman
php start.php start -d

# Windows用户
php windows.php start
```

---

### Q: 日志太多，如何筛选？

**只看重要信息：**

```bash
# 方法1: 只看警告和危险
tail -f runtime/logs/webman.log | grep -E "⚠️|🔴"

# 方法2: 只看修复验证
tail -f runtime/logs/webman.log | grep "修复验证"

# 方法3: 只看汇总
tail -f runtime/logs/webman.log | grep "📊 汇总"

# 方法4: 只看趋势报告
tail -f runtime/logs/webman.log | grep -A 20 "趋势分析"
```

---

### Q: Windows下无法使用tail命令？

**替代方案：**

**方案1: 使用Git Bash**
```bash
# Git Bash内置tail命令
tail -f runtime/logs/webman.log | grep "内存监控"
```

**方案2: 使用PowerShell**
```powershell
Get-Content runtime/logs/webman.log -Wait -Tail 50 | Select-String "内存监控"
```

**方案3: 直接打开日志文件**
```
用文本编辑器打开: D:\gk_admin\runtime\logs\webman.log
搜索: "内存监控"
```

**方案4: 使用批处理脚本（推荐）**
```bash
# 已提供，直接运行
monitor_memory.bat
```

---

## 📈 监控时间建议

| 目的 | 监控时长 | 频率 |
|------|---------|------|
| **快速验证** | 10-20 分钟 | 每分钟 |
| **稳定性测试** | 1-2 小时 | 每分钟 |
| **长期监控** | 持续运行 | 每分钟 |

---

## 🎯 成功标准

**修复前（当前问题）：**
- 100次请求后: **1.38 GB**
- 平均每请求: **13 MB**
- 增长率: **13 MB/分钟**

**修复后（预期目标）：**
- 100次请求后: **< 300 MB** ✅
- 平均每请求: **< 3 MB** ✅
- 增长率: **< 3 MB/分钟** ✅

---

## 📞 需要帮助？

如果遇到问题，请提供：

1. **监控输出片段**（复制日志中的一段）
2. **截图**（如果有图形界面）
3. **系统信息**：
   ```bash
   # PHP版本
   php -v

   # Webman进程数
   php start.php status

   # 服务器内存
   free -m  # Linux
   # 或
   wmic OS get TotalVisibleMemorySize,FreePhysicalMemory  # Windows
   ```

---

## 🚀 下一步

监控开始后：

1. ✅ **前20分钟** - 观察内存趋势
2. ✅ **第1小时** - 查看趋势报告
3. ✅ **第2小时** - 确认内存稳定
4. 📊 **生成报告** - 验证修复效果

**如果验证成功：**
- 标记问题已解决
- 继续长期监控（可选）
- 更新文档记录修复方案

**如果验证失败：**
- 查看紧急报告: `runtime/logs/memory_emergency_*.log`
- 检查是否有其他泄漏源
- 联系开发团队进行深度分析

---

**准备好了吗？选择上面3种方式之一，立即开始监控！** 🎯
