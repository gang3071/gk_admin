<?php
/**
 * 深度内存泄漏诊断脚本
 *
 * 功能：
 * 1. 检查Eloquent事件监听器数量
 * 2. 检查数据库连接状态
 * 3. 检查全局变量和静态属性
 * 4. 检查对象引用计数
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "========================================\n";
echo "深度内存泄漏诊断\n";
echo "========================================\n\n";

// 1. 检查Eloquent事件分发器
echo "📋 检查 Eloquent 事件监听器：\n";
try {
    $dispatcher = \Illuminate\Database\Eloquent\Model::getEventDispatcher();
    if ($dispatcher) {
        // 使用反射获取监听器
        $reflection = new \ReflectionClass($dispatcher);
        $listenersProperty = $reflection->getProperty('listeners');
        $listenersProperty->setAccessible(true);
        $listeners = $listenersProperty->getValue($dispatcher);

        $totalListeners = 0;
        foreach ($listeners as $event => $eventListeners) {
            $count = is_array($eventListeners) ? count($eventListeners) : 0;
            $totalListeners += $count;
            if ($count > 0) {
                echo "  - 事件 '$event': {$count} 个监听器\n";
            }
        }
        echo "  ✅ 总共 {$totalListeners} 个事件监听器\n";
        if ($totalListeners > 100) {
            echo "  ⚠️  警告：监听器数量过多，可能导致内存泄漏！\n";
        }
    } else {
        echo "  ℹ️  未检测到事件分发器\n";
    }
} catch (\Exception $e) {
    echo "  ❌ 检查失败：{$e->getMessage()}\n";
}
echo "\n";

// 2. 检查数据库连接
echo "📋 检查数据库连接状态：\n";
try {
    $db = \support\Db::connection();
    echo "  ✅ 数据库连接正常\n";

    // 检查是否持久连接
    $pdo = $db->getPdo();
    $isPersistent = $pdo->getAttribute(\PDO::ATTR_PERSISTENT);
    echo "  - 持久连接: " . ($isPersistent ? "是" : "否") . "\n";

    // 检查预处理语句缓存
    try {
        $stmtCacheSize = $pdo->getAttribute(\PDO::ATTR_STATEMENT_CACHE_SIZE);
        echo "  - 预处理语句缓存大小: {$stmtCacheSize}\n";
    } catch (\PDOException $e) {
        echo "  - 预处理语句缓存: 不支持或未启用\n";
    }
} catch (\Exception $e) {
    echo "  ❌ 数据库连接检查失败：{$e->getMessage()}\n";
}
echo "\n";

// 3. 检查已加载的类数量
echo "📋 检查已加载的类：\n";
$declaredClasses = get_declared_classes();
$modelClasses = array_filter($declaredClasses, function($class) {
    return strpos($class, 'addons\\webman\\model\\') === 0;
});
echo "  - 已加载的模型类: " . count($modelClasses) . " 个\n";
if (count($modelClasses) > 50) {
    echo "  ⚠️  警告：加载了大量模型类，可能影响内存\n";
    echo "  提示：前20个模型类：\n";
    foreach (array_slice($modelClasses, 0, 20) as $class) {
        echo "    * {$class}\n";
    }
}
echo "\n";

// 4. 检查全局变量
echo "📋 检查全局变量：\n";
$globalVars = $GLOBALS;
$suspiciousGlobals = [];
foreach ($globalVars as $key => $value) {
    if (is_array($value) && count($value) > 100) {
        $suspiciousGlobals[$key] = count($value);
    } elseif (is_object($value)) {
        $suspiciousGlobals[$key] = get_class($value);
    }
}
if (empty($suspiciousGlobals)) {
    echo "  ✅ 未发现可疑的全局变量\n";
} else {
    echo "  ⚠️  发现可疑的全局变量：\n";
    foreach ($suspiciousGlobals as $key => $info) {
        echo "    - \${$key}: " . (is_int($info) ? "{$info} 个元素" : "对象 {$info}") . "\n";
    }
}
echo "\n";

// 5. 检查当前内存使用
echo "📋 当前内存使用：\n";
$memUsage = memory_get_usage(true);
$memPeak = memory_get_peak_usage(true);
echo "  - 当前内存: " . round($memUsage / 1024 / 1024, 2) . " MB\n";
echo "  - 峰值内存: " . round($memPeak / 1024 / 1024, 2) . " MB\n";
echo "  - 内存限制: " . ini_get('memory_limit') . "\n";
echo "\n";

// 6. 检查加载的扩展
echo "📋 检查相关PHP扩展：\n";
$extensions = ['pdo', 'pdo_mysql', 'redis', 'opcache', 'xdebug'];
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo "  - {$ext}: " . ($loaded ? "✅ 已加载" : "❌ 未加载") . "\n";
}
echo "\n";

// 7. 检查OPcache状态（如果启用）
if (extension_loaded('opcache') && function_exists('opcache_get_status')) {
    echo "📋 OPcache 状态：\n";
    $opcache = opcache_get_status();
    if ($opcache) {
        echo "  - 已使用内存: " . round($opcache['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
        echo "  - 可用内存: " . round($opcache['memory_usage']['free_memory'] / 1024 / 1024, 2) . " MB\n";
        echo "  - 缓存的脚本数: {$opcache['opcache_statistics']['num_cached_scripts']}\n";
    }
    echo "\n";
}

echo "========================================\n";
echo "诊断完成\n";
echo "========================================\n";
