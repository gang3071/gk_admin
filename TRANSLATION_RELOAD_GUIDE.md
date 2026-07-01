# 翻译文件重新加载指南

## 问题描述
ChannelStoreProfitReportController/index 页面中的翻译没有生效，显示的是翻译键而不是翻译后的文本。

## 原因分析
Webman 是常驻内存框架，翻译文件在服务启动时加载到内存中。修改翻译文件后，**必须重启服务**才能重新加载。

## 解决方案

### 1. 重启 Webman 服务

**Linux 环境：**
```bash
# 方式 1：优雅重启（推荐）
php start.php reload

# 方式 2：完全重启
php start.php restart

# 方式 3：停止后重启
php start.php stop
php start.php start -d
```

**Windows 环境：**
```bash
# 停止服务（Ctrl+C）
# 然后重新启动
php windows.php start
```

### 2. 验证翻译文件

确认以下 4 个翻译文件都存在且完整：

```
✅ addons/webman/lang/zh-TW/channel_store_profit.php
✅ addons/webman/lang/zh-CN/channel_store_profit.php
✅ addons/webman/lang/en/channel_store_profit.php
✅ addons/webman/lang/jp/channel_store_profit.php
```

**验证命令：**
```bash
ls -la addons/webman/lang/*/channel_store_profit.php
```

### 3. 检查翻译文件语法

确保翻译文件没有 PHP 语法错误：

```bash
php -l addons/webman/lang/zh-TW/channel_store_profit.php
php -l addons/webman/lang/zh-CN/channel_store_profit.php
php -l addons/webman/lang/en/channel_store_profit.php
php -l addons/webman/lang/jp/channel_store_profit.php
```

应该输出：`No syntax errors detected in ...`

### 4. 清除 OPcache（可选）

如果重启后仍然有问题，尝试清除 PHP OPcache：

```bash
# 方式 1：重启 PHP-FPM（如果使用）
systemctl restart php-fpm

# 方式 2：在代码中清除（临时）
# 在控制器 index() 方法顶部添加：
if (function_exists('opcache_reset')) {
    opcache_reset();
}
```

## 翻译键对照表

以下是 ChannelStoreProfitReportController 使用的所有翻译键：

### 标题
- `channel_store_profit.title` → "店家分潤報表"

### 字段（fields）
- `channel_store_profit.fields.store_name` → "店家名稱"
- `channel_store_profit.fields.store_username` → "登錄賬號"
- `channel_store_profit.fields.agent_name` → "所屬代理"
- `channel_store_profit.fields.recharge_amount` → "累計開分"
- `channel_store_profit.fields.withdraw_amount` → "累計洗分"
- `channel_store_profit.fields.machine_put_point` → "投钞"
- `channel_store_profit.fields.lottery_amount` → "彩金"
- `channel_store_profit.fields.subtotal` → "小計"
- `channel_store_profit.fields.agent_commission` → "代理抽成比例"
- `channel_store_profit.fields.agent_profit` → "代理分潤"
- `channel_store_profit.fields.channel_commission` → "渠道抽成比例"
- `channel_store_profit.fields.channel_profit` → "渠道分潤"

### 筛选器（filter）
- `channel_store_profit.filter.select_agent` → "選擇代理"
- `channel_store_profit.filter.all_agents` → "全部代理"
- `channel_store_profit.filter.select_store` → "選擇店家"
- `channel_store_profit.filter.all_stores` → "全部店家"
- `channel_store_profit.filter.time_range` → "時間範圍"
- `channel_store_profit.filter.start_time` → "開始時間"
- `channel_store_profit.filter.end_time` → "結束時間"

### 统计数据（stats）
- `channel_store_profit.stats.total_recharge` → "總開分"
- `channel_store_profit.stats.total_withdraw` → "總洗分"
- `channel_store_profit.stats.total_machine_put` → "總投钞"
- `channel_store_profit.stats.total_lottery` → "總彩金"
- `channel_store_profit.stats.total_subtotal` → "總小計"
- `channel_store_profit.stats.total_agent_profit` → "總代理分潤"
- `channel_store_profit.stats.total_channel_profit` → "總渠道分潤"

## 调试步骤

如果重启后仍然有问题，按以下步骤调试：

### 1. 检查当前语言设置

在控制器 `index()` 方法顶部添加调试代码：

```php
public function index(): Grid
{
    // 调试：检查当前语言
    $currentLang = \ExAdmin\ui\support\Container::getInstance()->translator->getLocale();
    \support\Log::info('Current language: ' . $currentLang);
    \support\Log::info('Translation test: ' . admin_trans('channel_store_profit.title'));

    // 原有代码...
}
```

查看日志：
```bash
tail -f runtime/logs/webman.log
```

### 2. 手动测试翻译函数

创建测试路由：

```php
// config/route.php
Route::get('/test-translation', function () {
    $keys = [
        'channel_store_profit.title',
        'channel_store_profit.fields.store_name',
        'channel_store_profit.stats.total_recharge',
    ];

    $result = [];
    foreach ($keys as $key) {
        $result[$key] = admin_trans($key);
    }

    return json($result);
});
```

访问：`http://your-domain/test-translation`

### 3. 检查翻译文件权限

```bash
ls -la addons/webman/lang/zh-TW/channel_store_profit.php
```

确保文件权限正确（644 或 755）：
```bash
chmod 644 addons/webman/lang/*/channel_store_profit.php
```

## 常见问题

### Q1: 重启后还是没有翻译？
**A:** 检查浏览器是否缓存了旧页面，按 Ctrl+F5 强制刷新。

### Q2: 只有部分翻译生效？
**A:** 检查翻译文件中是否有拼写错误或缺失的键。

### Q3: 翻译键显示为空白？
**A:** 检查翻译文件中对应的键是否存在，以及是否有 PHP 语法错误。

### Q4: 不同语言的翻译不一致？
**A:** 确保 4 个语言文件（zh-TW, zh-CN, en, jp）的键名完全一致。

## 快速验证命令

```bash
# 1. 检查所有翻译文件是否存在
ls -la addons/webman/lang/*/channel_store_profit.php

# 2. 检查语法错误
for file in addons/webman/lang/*/channel_store_profit.php; do
    echo "Checking $file..."
    php -l "$file"
done

# 3. 统计翻译键数量（应该都一致）
for file in addons/webman/lang/*/channel_store_profit.php; do
    echo "$file: $(grep -c "=>" "$file") keys"
done

# 4. 重启服务
php start.php restart
```

## 总结

**最常见的问题就是忘记重启服务。**

翻译文件修改后，**必须执行以下操作之一**：
- `php start.php reload`（推荐）
- `php start.php restart`
- Windows: 重新启动 `php windows.php start`

重启后清除浏览器缓存（Ctrl+F5），翻译应该就能正常显示了。
