# 代理店家分润月报导出功能实现指南

## 📋 功能概述

在 `AgentStoreProfitReportController/index` 中添加了导出月报功能，允许代理导出所有店家的分润数据到 Excel 文件。

---

## 🎯 实现内容

### 1. 创建的文件

#### ✅ `addons/webman/grid/AgentStoreProfitReportExporter.php`

专业的 Excel 导出器类，包含以下功能：

**特性：**
- ✅ **无参构造函数**（符合 ExAdmin 要求）
- ✅ 从 `Request::input()` 获取筛选参数
- ✅ 复用控制器的查询逻辑
- ✅ 专业的 Excel 样式设计

**导出内容：**
1. **标题区域**
   - 报表标题："店家分润月报"
   - 代理信息：代理名称和账号
   - 统计时间范围
   - 导出时间戳

2. **统计汇总区域**
   - 总开分、总洗分、总投钞
   - 总彩金、总小计
   - 总代理分润、总渠道分润

3. **数据表格**
   - 店家 ID、名称、账号
   - 累计开分、累计洗分、投钞
   - 彩金、小计
   - 代理抽成比例、代理分润
   - 渠道抽成比例、渠道分润

4. **合计行**
   - 汇总所有店家的总计数据

**Excel 样式：**
- 蓝色标题栏（#1890FF）
- 交替行背景色（白色/浅灰）
- 小计/分润列根据正负值显示不同颜色
- 专业的边框和对齐方式
- 合理的列宽设置

---

### 2. 修改的文件

#### ✅ `addons/webman/controller/AgentStoreProfitReportController.php`

**第 196-198 行**：添加导出功能

```php
// 添加导出功能
$grid->export(new \addons\webman\grid\AgentStoreProfitReportExporter())
    ->filename(admin_trans('agent_store_profit.export.filename') . date('YmdHis'));
```

**效果：**
- Grid 列表右上角会自动显示"导出"按钮
- 点击后会导出当前筛选条件下的所有数据
- 文件名格式：`店家分润月报_20260401123456.xlsx`

---

#### ✅ 翻译文件（4 个语言）

**添加的翻译键：**

| 键名 | 繁体中文 (zh-TW) | 简体中文 (zh-CN) | English | 日本語 |
|------|----------------|----------------|---------|--------|
| `export.filename` | 店家分潤月報_ | 店家分润月报_ | store_profit_report_ | 店舗分潤月報_ |
| `export.title` | 店家分潤月報 | 店家分润月报 | Store Profit Monthly Report | 店舗分潤月報 |
| `export.agent_info` | 代理： | 代理： | Agent: | 代理： |
| `export.time_range` | 統計時間： | 统计时间： | Time Range: | 統計期間： |
| `export.start_from` | 起始時間： | 起始时间： | Start From: | 開始時間： |
| `export.end_at` | 截止時間： | 截止时间： | End At: | 終了時間： |
| `export.all_time` | 全部時間 | 全部时间 | All Time | 全期間 |
| `export.export_time` | 導出時間： | 导出时间： | Export Time: | エクスポート時間： |
| `export.summary_title` | 統計匯總 | 统计汇总 | Summary | 統計集計 |
| `export.total` | 合計 | 合计 | Total | 合計 |

**文件位置：**
- `addons/webman/lang/zh-TW/agent_store_profit.php` ✅
- `addons/webman/lang/zh-CN/agent_store_profit.php` ✅
- `addons/webman/lang/en/agent_store_profit.php` ✅
- `addons/webman/lang/jp/agent_store_profit.php` ✅

---

#### ✅ `config/agent_node.php`

**第 146-153 行**：添加导出权限节点

```php
[
    'id' => 'addons\webman\controller\AgentStoreProfitReportController\export',
    'pid' => 'addons\webman\controller\AgentStoreProfitReportController\index',
    'action' => 'export',
    'method' => 'get',
    'group' => 'agent',
    'url' => 'ex-admin/addons-webman-controller-AgentStoreProfitReportController/export',
    'title' => '导出店家分润报表',
],
```

**权限说明：**
- **父节点**：`AgentStoreProfitReportController\index`（报表列表页）
- **子节点**：`AgentStoreProfitReportController\export`（导出功能）
- **权限组**：`agent`（代理后台）
- **方法**：`get`

---

## 🚀 使用步骤

### 1. 重启 Webman 服务

```bash
php start.php restart
```

**原因：**
- 新增的 Exporter 类需要自动加载
- 权限配置需要重新加载

---

### 2. 分配权限（后台操作）

1. 登录超级管理员账号
2. 进入「角色管理」
3. 编辑「代理」角色（ID: 18）
4. 找到「店家分润报表」权限组
5. 勾选「导出店家分润报表」权限
6. 保存

**权限路径：**
```
财务管理
  └─ 店家分润报表 ✓
      ├─ 店家分润报表（列表） ✓
      └─ 导出店家分润报表 ✓  ← 新增权限
```

---

### 3. 使用导出功能

1. **登录代理账号**
2. **进入报表页面**：财务管理 → 店家分润报表
3. **设置筛选条件**（可选）：
   - 选择特定店家
   - 选择时间范围
4. **点击导出按钮**：列表右上角的"导出"按钮
5. **下载 Excel 文件**：浏览器会自动下载

**文件名示例：**
- `店家分润月报_20260401143055.xlsx`

---

## 📊 导出的 Excel 样式预览

```
┌──────────────────────────────────────────────────────────────┐
│                    店家分润月报                                │  ← 蓝色标题
├──────────────────────────────────────────────────────────────┤
│ 代理：张三 (agent001)                                          │  ← 灰色信息栏
│ 统计时间：2026-03-01 00:00:00 ~ 2026-03-31 23:59:59          │
│ 导出时间：2026-04-01 14:30:55                                │
├──────────────────────────────────────────────────────────────┤
│                      统计汇总                                  │  ← 浅蓝背景
├──────────────────────────────────────────────────────────────┤
│ 总开分: 125,680.00  │ 总洗分: 98,450.00  │ 总投钞: 35,200.00 │
│ 总彩金: 8,920.00    │ 总小计: 53,510.00  │ ...               │
├──────────────────────────────────────────────────────────────┤
│ ID │ 店家名称 │ 登录账号 │ 累计开分 │ ... │ 代理分润 │ ... │  ← 蓝色表头
├────┼──────────┼─────────┼──────────┼─────┼──────────┼─────┤
│ 1  │ 店家A    │ store001│ 25,680   │ ... │ 2,568    │ ... │  ← 白色行
├────┼──────────┼─────────┼──────────┼─────┼──────────┼─────┤
│ 2  │ 店家B    │ store002│ 38,950   │ ... │ 3,895    │ ... │  ← 灰色行
├────┼──────────┼─────────┼──────────┼─────┼──────────┼─────┤
│    │ 合计     │         │ 125,680  │ ... │ 12,568   │ ... │  ← 蓝色合计行
└────┴──────────┴─────────┴──────────┴─────┴──────────┴─────┘
```

**颜色说明：**
- **小计列**：正数显示绿色，负数显示红色
- **代理分润列**：正数显示蓝色，负数显示橙色
- **渠道分润列**：正数显示绿色，负数显示红色

---

## ✅ 验证功能

### 1. 检查导出按钮是否显示

```
访问：http://localhost:8789/admin
登录代理账号 → 财务管理 → 店家分润报表
预期：列表右上角有"导出"按钮 ✓
```

### 2. 测试导出功能

**场景 1：导出全部店家**
```
1. 不设置任何筛选条件
2. 点击"导出"
3. 检查 Excel 是否包含所有店家数据
```

**场景 2：导出特定店家**
```
1. 筛选器选择"店家A"
2. 点击"导出"
3. 检查 Excel 是否只包含店家A的数据
```

**场景 3：导出指定时间范围**
```
1. 选择时间范围：2026-03-01 ~ 2026-03-31
2. 点击"导出"
3. 检查 Excel 的"统计时间"行是否显示正确
4. 检查数据是否在该时间范围内
```

---

## 🐛 常见问题

### Q1: 点击导出后没有反应？

**可能原因：**
1. 权限未分配
2. Webman 未重启
3. Exporter 类加载失败

**解决方法：**
```bash
# 1. 检查权限配置
cat config/agent_node.php | grep "AgentStoreProfitReportController"

# 2. 重启 Webman
php start.php restart

# 3. 检查 Exporter 文件是否存在
ls -la addons/webman/grid/AgentStoreProfitReportExporter.php

# 4. 查看日志
tail -f runtime/logs/webman.log
```

---

### Q2: 导出的 Excel 数据不对？

**可能原因：**
- 筛选条件传递有误
- 查询逻辑与控制器不一致

**解决方法：**
检查 `AgentStoreProfitReportExporter.php` 的查询逻辑是否与控制器 `index()` 方法一致。

---

### Q3: 导出文件无法打开？

**可能原因：**
- PhpSpreadsheet 版本过低
- Excel 文件损坏

**解决方法：**
```bash
# 更新 PhpSpreadsheet
composer update phpoffice/phpspreadsheet

# 检查 Composer 依赖
composer show phpoffice/phpspreadsheet
```

---

### Q4: 翻译不生效？

**可能原因：**
- 浏览器缓存
- 翻译文件未重新加载

**解决方法：**
```bash
# 1. 清除浏览器缓存（Ctrl+F5）
# 2. 重启 Webman
php start.php restart

# 3. 检查翻译文件
cat addons/webman/lang/zh-TW/agent_store_profit.php | grep "export"
```

---

## 🎨 自定义样式（可选）

### 修改 Excel 颜色

编辑 `addons/webman/grid/AgentStoreProfitReportExporter.php`：

```php
// 修改标题颜色（第 X 行）
'startColor' => ['rgb' => '1890FF']  // 改为你喜欢的颜色

// 修改表头颜色
'startColor' => ['rgb' => '1890FF']

// 修改合计行颜色
'startColor' => ['rgb' => '1890FF']
```

**常用颜色代码：**
- 蓝色：`1890FF`
- 绿色：`52C41A`
- 红色：`FF4D4F`
- 橙色：`FA8C16`
- 紫色：`722ED1`

---

### 修改列宽

编辑 `setColumnWidths()` 方法：

```php
$this->sheet->getColumnDimension('B')->setWidth(25); // 店家名称加宽
$this->sheet->getColumnDimension('D')->setWidth(18); // 累计开分加宽
```

---

## 📚 技术细节

### Exporter 设计模式

```php
class AgentStoreProfitReportExporter extends Excel
{
    // ✅ 必须：无参构造函数
    // ✅ 必须：实现 write() 方法
    // ✅ 推荐：从 Request::input() 获取参数
    // ✅ 推荐：复用控制器查询逻辑
}
```

### 权限检查流程

```
用户点击"导出"
    ↓
ExAdmin Grid Export 机制
    ↓
检查权限：AgentStoreProfitReportController\export
    ↓
调用 AgentStoreProfitReportExporter::write()
    ↓
生成 Excel 文件
    ↓
返回下载链接
```

---

## 🔗 相关文件

| 文件 | 说明 |
|------|------|
| `addons/webman/grid/AgentStoreProfitReportExporter.php` | 导出器类 |
| `addons/webman/controller/AgentStoreProfitReportController.php` | 控制器（第 196-198 行） |
| `addons/webman/lang/*/agent_store_profit.php` | 翻译文件（4 个语言） |
| `config/agent_node.php` | 权限配置（第 146-153 行） |

---

## ✨ 功能总结

**已实现：**
- ✅ 专业的 Excel 导出器
- ✅ 支持筛选条件（店家、时间范围）
- ✅ 统计汇总区域
- ✅ 数据表格和合计行
- ✅ 多语言支持（繁体/简体/英文/日文）
- ✅ 权限控制
- ✅ 精美的样式设计

**使用简单：**
- 1 个按钮（导出）
- 3 步操作（筛选 → 点击 → 下载）
- 0 配置（开箱即用）

---

**最后更新：** 2026-04-01
**版本：** v1.0
