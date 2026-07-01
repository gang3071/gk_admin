<?php
// 测试 Redis Cache 状态和内存使用

require_once __DIR__ . '/vendor/autoload.php';

use support\Cache;
use support\Redis;

// 加载配置
config([
    'redis' => require __DIR__ . '/config/redis.php',
    'database' => require __DIR__ . '/config/database.php',
]);

echo "=== Redis Cache 内存状态分析 ===" . PHP_EOL . PHP_EOL;

// 1. 检查 Cache::$instances 静态数组
echo "【1】Cache::$instances 状态：" . PHP_EOL;
$reflection = new ReflectionClass(Cache::class);
$instancesProp = $reflection->getProperty('instances');
$instancesProp->setAccessible(true);
$instances = $instancesProp->getValue();
echo "  - 实例数量: " . count($instances) . PHP_EOL;
echo "  - 实例键名: " . implode(', ', array_keys($instances)) . PHP_EOL . PHP_EOL;

// 2. 获取 Redis 连接信息
echo "【2】Redis 连接信息：" . PHP_EOL;
try {
    $redis = Redis::connection()->client();
    echo "  - Redis 类: " . get_class($redis) . PHP_EOL;

    if ($redis instanceof \Redis) {
        echo "  - 持久连接ID: " . ($redis->getPersistentID() ?: '否') . PHP_EOL;
        echo "  - 连接状态: " . ($redis->ping() ? '正常' : '异常') . PHP_EOL;

        // 获取 Redis info
        $info = $redis->info('memory');
        if ($info) {
            echo "  - Redis 服务器内存: " . ($info['used_memory_human'] ?? 'N/A') . PHP_EOL;
            echo "  - Redis 峰值内存: " . ($info['used_memory_peak_human'] ?? 'N/A') . PHP_EOL;
        }
    }
} catch (Exception $e) {
    echo "  - 错误: " . $e->getMessage() . PHP_EOL;
}
echo PHP_EOL;

// 3. 分析缓存键的分布
echo "【3】缓存键分析：" . PHP_EOL;
try {
    $redis = Redis::connection()->client();

    // 扫描所有键
    $iterator = null;
    $allKeys = [];
    while (false !== ($keys = $redis->scan($iterator, '*', 100))) {
        if (is_array($keys)) {
            $allKeys = array_merge($allKeys, $keys);
        }
        if ($iterator === 0) {
            break;
        }
    }

    echo "  - 总键数: " . count($allKeys) . PHP_EOL;

    // 按前缀分组
    $prefixGroups = [];
    foreach ($allKeys as $key) {
        // 提取前缀（第一个冒号之前）
        $prefix = strstr($key, ':', true) ?: $key;
        if (!isset($prefixGroups[$prefix])) {
            $prefixGroups[$prefix] = 0;
        }
        $prefixGroups[$prefix]++;
    }

    arsort($prefixGroups);
    echo "  - 键前缀分布（Top 10）：" . PHP_EOL;
    $count = 0;
    foreach ($prefixGroups as $prefix => $num) {
        if ($count++ >= 10) break;
        echo "    * {$prefix}: {$num} 个键" . PHP_EOL;
    }

    // 检查 DataPermissions 相关缓存
    echo PHP_EOL . "  - DataPermissions 相关缓存：" . PHP_EOL;
    $dataPermKeys = array_filter($allKeys, function($key) {
        return str_contains($key, 'data_perm') || str_contains($key, 'ADMIN_PERMISSIONS');
    });
    echo "    * 数量: " . count($dataPermKeys) . PHP_EOL;

    if (count($dataPermKeys) > 0) {
        echo "    * 示例键（前5个）：" . PHP_EOL;
        $samples = array_slice($dataPermKeys, 0, 5);
        foreach ($samples as $key) {
            $ttl = $redis->ttl($key);
            $size = strlen(serialize($redis->get($key)));
            echo "      - {$key}" . PHP_EOL;
            echo "        TTL: " . ($ttl > 0 ? $ttl . '秒' : '永久') . ', 大小: ' . round($size / 1024, 2) . ' KB' . PHP_EOL;
        }
    }

} catch (Exception $e) {
    echo "  - 错误: " . $e->getMessage() . PHP_EOL;
}
echo PHP_EOL;

// 4. 模拟多次缓存操作，观察内存变化
echo "【4】模拟缓存操作内存测试：" . PHP_EOL;
$memBefore = memory_get_usage(true);
echo "  - 初始内存: " . round($memBefore / 1024 / 1024, 2) . " MB" . PHP_EOL;

// 模拟 DataPermissions 的缓存操作
for ($i = 0; $i < 100; $i++) {
    // 模拟角色数据缓存（类似 DataPermissions::getRoleUser）
    $cacheKey = 'data_perm:role_user:' . ($i % 10); // 模拟10个不同用户
    $roleData = [
        'id' => $i,
        'name' => 'Role ' . $i,
        'data_type' => rand(0, 4),
        'department_ids' => array_fill(0, rand(1, 10), rand(1, 100)),
    ];

    Cache::set($cacheKey, $roleData, 3600);

    // 模拟部门数据缓存（类似 DataPermissions::getDepartmentsByDataType）
    if ($i % 20 == 0) {
        $deptKey = 'data_perm:dept:' . rand(1, 50);
        $deptData = array_fill(0, rand(10, 50), rand(1, 500));
        Cache::set($deptKey, $deptData, 3600);
    }

    // 模拟权限数据缓存（类似 Admin::permission）
    if ($i % 10 == 0) {
        $permKey = 'ADMIN_PERMISSIONS_' . ($i % 5);
        $permData = array_fill(0, rand(50, 200), 'node_' . rand(1, 1000));
        Cache::set($permKey, $permData);
    }

    // 读取缓存
    Cache::get($cacheKey);

    if ($i % 20 == 0) {
        $memCurrent = memory_get_usage(true);
        $leaked = $memCurrent - $memBefore;
        echo "  - 第 {$i} 次: " . round($memCurrent / 1024 / 1024, 2) . " MB (+". round($leaked / 1024 / 1024, 2) ." MB)" . PHP_EOL;
    }
}

$memAfter = memory_get_usage(true);
$totalLeaked = $memAfter - $memBefore;
echo "  - 最终内存: " . round($memAfter / 1024 / 1024, 2) . " MB" . PHP_EOL;
echo "  - 总泄露: " . round($totalLeaked / 1024 / 1024, 2) . " MB" . PHP_EOL;
echo "  - 平均每次: " . round($totalLeaked / 100 / 1024, 2) . " KB" . PHP_EOL . PHP_EOL;

// 5. 检查 Cache::$instances 的内存占用
echo "【5】Cache::$instances 内存占用：" . PHP_EOL;
$instancesSize = strlen(serialize($instances));
echo "  - 序列化大小: " . round($instancesSize / 1024, 2) . " KB" . PHP_EOL;

// 深度分析第一个实例
if (count($instances) > 0) {
    $firstInstance = reset($instances);
    echo "  - 第一个实例类: " . get_class($firstInstance) . PHP_EOL;

    // 检查内部状态
    $psr16Reflection = new ReflectionClass($firstInstance);
    $poolProp = $psr16Reflection->getProperty('pool');
    $poolProp->setAccessible(true);
    $pool = $poolProp->getValue($firstInstance);

    echo "  - Pool 类: " . get_class($pool) . PHP_EOL;

    // 检查 RedisAdapter 内部状态
    $adapterReflection = new ReflectionObject($pool);
    $properties = $adapterReflection->getProperties();
    echo "  - Adapter 属性数: " . count($properties) . PHP_EOL;
}

echo PHP_EOL . "测试完成！" . PHP_EOL;
