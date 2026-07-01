# MongoDB 清理后续修复

**修复日期**: 2026-04-02  
**问题**: 删除 MongoDB 后出现 `machineChart()` 方法未定义错误

---

## 🐛 问题描述

删除 MongoDB 功能后，访问数据中心页面时出现错误：

```
Call to undefined method addons\webman\controller\IndexController::machineChart()
```

**错误位置**:
- `IndexController.php:369`
- `ChannelIndexController.php:534`

**原因**: 虽然删除了 `machineChart()` 方法（该方法依赖 MongoDB 聚合查询），但在 `index()` 方法中仍然调用了该方法来显示"24小时机台操作图表"。

---

## ✅ 修复内容

### 1. IndexController.php

**修改位置**: 第 366-369 行

**修改前**:
```php
$row->column(Card::create($this->rechargeChart())->hoverable(), 12);
$row->column(Card::create($this->withdrawChart())->hoverable(), 12);
$row->column(Card::create($this->playerChart())->hoverable(), 12);
$row->column(Card::create($this->machineChart())->hoverable(), 12); // ❌ 调用已删除的方法
```

**修改后**:
```php
$row->column(Card::create($this->rechargeChart())->hoverable(), 12);
$row->column(Card::create($this->withdrawChart())->hoverable(), 12);
$row->column(Card::create($this->playerChart())->hoverable(), 12);
// machineChart() 已删除（依赖 MongoDB）
```

### 2. ChannelIndexController.php

**修改位置**: 第 531-534 行

**修改前**:
```php
$row->column(Card::create($this->rechargeChart())->hoverable(), 12);
$row->column(Card::create($this->withdrawChart())->hoverable(), 12);
$row->column(Card::create($this->playerChart())->hoverable(), 12);
$row->column(Card::create($this->machineChart())->hoverable(), 12); // ❌ 调用已删除的方法
```

**修改后**:
```php
$row->column(Card::create($this->rechargeChart())->hoverable(), 12);
$row->column(Card::create($this->withdrawChart())->hoverable(), 12);
$row->column(Card::create($this->playerChart())->hoverable(), 12);
// machineChart() 已删除（依赖 MongoDB）
```

---

## 📊 影响范围

### 数据中心页面布局变化

**总后台数据中心** (`IndexController::index()`):
- ✅ 充值趋势图表 (保留)
- ✅ 提现趋势图表 (保留)
- ✅ 玩家增长图表 (保留)
- ❌ 24小时机台操作图表 (已移除)

**渠道后台数据中心** (`ChannelIndexController::index()`):
- ✅ 充值趋势图表 (保留)
- ✅ 提现趋势图表 (保留)
- ✅ 玩家增长图表 (保留)
- ❌ 24小时机台操作图表 (已移除)

---

## 🔍 验证结果

**验证脚本**: `verify_mongodb_cleanup.sh` / `verify_mongodb_cleanup.bat`

**验证结果**: ✅ 所有 9 项检查通过

```
✓ MongoDB 模型目录已删除
✓ 4 个 MongoDB 控制器已删除
✓ vendor 目录已清理
✓ 代码中无 MongoDB 模型引用
✓ helpers.php 中的函数已删除
✓ composer.json 中无依赖
✓ Composer 包已移除
✓ 所有配置文件已清理
✓ 权限配置已清理
```

**全局检查**: 确认无其他 `machineChart()` 调用

```bash
grep -rn "machineChart" addons/webman/controller --include="*.php"
# 输出: 无结果 ✓
```

---

## 🚀 部署步骤

### 1. 更新代码

```bash
# 拉取最新代码（如果使用Git）
git pull origin master

# 或手动更新文件
# - IndexController.php
# - ChannelIndexController.php
```

### 2. 重启服务

```bash
# 重启 Webman
php start.php restart

# 检查状态
php start.php status
```

### 3. 验证修复

```bash
# 运行验证脚本
bash verify_mongodb_cleanup.sh

# 或 Windows
verify_mongodb_cleanup.bat
```

### 4. 测试功能

1. 访问总后台数据中心页面
2. 访问渠道后台数据中心页面
3. 确认页面正常加载，无报错
4. 确认三个图表正常显示（充值、提现、玩家）

---

## 📝 后续建议

### 如需恢复机台操作图表

**方案 1: 使用 MySQL 记录机台操作日志**

1. 创建 MySQL 表：
```sql
CREATE TABLE `yjb_machine_operation_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `department_id` int NOT NULL COMMENT '渠道ID',
  `machine_id` int NOT NULL COMMENT '机台ID',
  `action` varchar(50) COMMENT '操作类型',
  `status` tinyint DEFAULT 1 COMMENT '状态',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

2. 恢复 `saveMachineOperationLog()` 函数（使用 MySQL）

3. 重新实现 `machineChart()` 方法：
```php
public function machineChart(): LineChart
{
    $startDate = Carbon::now()->subHours(24);
    
    // 使用 MySQL 查询替代 MongoDB 聚合
    $logs = DB::table('yjb_machine_operation_log')
        ->select(DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d %H") as hour, COUNT(*) as count'))
        ->where('created_at', '>=', $startDate)
        ->where('status', 1)
        ->groupBy('hour')
        ->orderBy('hour')
        ->get();
    
    $data = [];
    foreach ($logs as $log) {
        $data[$log->hour] = $log->count;
    }
    
    $xAxis = [];
    $yAxis = [];
    for ($i = 23; $i >= 0; $i--) {
        $time = Carbon::now()->subHours($i)->format('Y-m-d H');
        $xAxis[] = $time;
        $yAxis[] = $data[$time] ?? 0;
    }
    
    return LineChart::create()
        ->height('280px')
        ->hideDateFilter()
        ->header(Html::create(admin_trans('data_center.machine_24_chart'))->tag('h2'))
        ->xAxis($xAxis)
        ->data(admin_trans('data_center.machine_amount'), $yAxis);
}
```

**方案 2: 使用应用日志文件**

使用 Webman 的日志系统记录机台操作，定期分析日志文件生成统计数据。

**方案 3: 使用 Redis + 定时归档**

实时数据存 Redis，定期归档到 MySQL，保留最近 N 天数据。

---

## ⚠️ 注意事项

1. **页面布局**: 数据中心页面现在只显示 3 个图表，布局可能需要调整
2. **缓存清理**: 清理浏览器缓存以确保看到最新页面
3. **监控告警**: 如果有监控系统，更新告警规则（移除机台操作相关指标）
4. **文档更新**: 更新用户手册，说明机台操作图表功能已移除

---

## 📚 相关文档

- **详细清理总结**: `MONGODB_CLEANUP_SUMMARY.md`
- **验证脚本**: 
  - Linux/Mac: `verify_mongodb_cleanup.sh`
  - Windows: `verify_mongodb_cleanup.bat`

---

**修复完成时间**: 2026-04-02  
**修复执行人**: Claude Code  
**验证状态**: ✅ 已完成并验证