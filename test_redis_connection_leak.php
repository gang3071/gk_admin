<?php
// 测试 Redis 连接累积问题

require_once __DIR__ . '/vendor/autoload.php';

echo "=== Redis 连接累积内存测试 ===" . PHP_EOL . PHP_EOL;

// 模拟 Webman 环境下的持久连接
$memStart = memory_get_usage(true);
echo "初始内存: " . round($memStart / 1024 / 1024, 2) . " MB" . PHP_EOL . PHP_EOL;

// 测试 1: 检查 Redis 原生持久连接
echo "【测试 1】Redis 原生持久连接：" . PHP_EOL;

$redisConfig = require __DIR__ . '/config/redis.php';
$defaultConfig = $redisConfig['redis']['default'];

$redis = new Redis();
$connectMethod = $defaultConfig['persistent'] ? 'pconnect' : 'connect';
$persistentId = $defaultConfig['persistent'] ? 'webman_redis' : null;

echo "  连接方式: {$connectMethod}" . PHP_EOL;
echo "  持久ID: " . ($persistentId ?: '无') . PHP_EOL;

if ($persistentId) {
    $redis->pconnect(
        $defaultConfig['host'],
        $defaultConfig['port'],
        $defaultConfig['timeout'] ?? 0,
        $persistentId
    );
} else {
    $redis->connect(
        $defaultConfig['host'],
        $defaultConfig['port'],
        $defaultConfig['timeout'] ?? 0
    );
}

if (isset($defaultConfig['password'])) {
    $redis->auth($defaultConfig['password']);
}
if (isset($defaultConfig['database'])) {
    $redis->select($defaultConfig['database']);
}

echo "  连接状态: " . ($redis->ping() ? '正常' : '异常') . PHP_EOL;
echo "  持久连接ID: " . ($redis->getPersistentID() ?: '无') . PHP_EOL . PHP_EOL;

// 测试 2: 模拟大量缓存操作（类似实际请求）
echo "【测试 2】模拟 100 次请求的缓存操作：" . PHP_EOL;

$memBefore = memory_get_usage(true);
$connectionInfo = [];

for ($request = 1; $request <= 100; $request++) {
    // 每次"请求"执行典型的缓存操作

    // 1. DataPermissions 缓存操作（每个请求都会触发）
    $roleKey = 'data_perm:role_user:' . rand(1, 10);
    $roleData = [
        'id' => rand(1, 100),
        'data_type' => rand(0, 4),
        'department_ids' => array_fill(0, rand(5, 20), rand(1, 500)),
    ];
    $redis->setex($roleKey, 3600, serialize($roleData));
    $redis->get($roleKey);

    // 2. Admin::permission 缓存操作
    $permKey = 'ADMIN_PERMISSIONS_' . rand(1, 5);
    $permData = array_fill(0, rand(50, 150), 'node_' . rand(1, 1000));
    $redis->set($permKey, serialize($permData));
    $redis->get($permKey);

    // 3. 部门数据缓存
    $deptKey = 'data_perm:dept:' . rand(1, 30);
    $deptData = array_fill(0, rand(10, 50), rand(1, 300));
    $redis->setex($deptKey, 3600, serialize($deptData));
    $redis->get($deptKey);

    // 4. 业务缓存（模拟 player、machine 等）
    for ($i = 0; $i < 5; $i++) {
        $bizKey = 'biz:' . ['player', 'machine', 'game', 'channel', 'agent'][rand(0, 4)] . ':' . rand(1, 1000);
        $bizData = str_repeat('x', rand(1000, 5000)); // 1-5KB 数据
        $redis->setex($bizKey, 600, $bizData);
        $redis->get($bizKey);
    }

    // 记录内存变化
    if ($request % 10 == 0) {
        $memCurrent = memory_get_usage(true);
        $leaked = $memCurrent - $memBefore;

        // 获取 Redis 连接信息
        $info = $redis->info('stats');
        $connectionInfo[$request] = [
            'memory_mb' => round($memCurrent / 1024 / 1024, 2),
            'leaked_mb' => round($leaked / 1024 / 1024, 2),
            'total_connections' => $info['total_connections_received'] ?? 'N/A',
            'connected_clients' => $info['connected_clients'] ?? 'N/A',
        ];

        echo "  请求 {$request}: " . round($memCurrent / 1024 / 1024, 2) . " MB";
        echo " (+". round($leaked / 1024 / 1024, 2) ." MB)";
        echo " | 连接数: " . ($info['total_connections_received'] ?? 'N/A');
        echo PHP_EOL;
    }
}

$memAfter = memory_get_usage(true);
$totalLeak = $memAfter - $memBefore;

echo PHP_EOL;
echo "  总泄露: " . round($totalLeak / 1024 / 1024, 2) . " MB" . PHP_EOL;
echo "  平均每请求: " . round($totalLeak / 100 / 1024, 2) . " KB" . PHP_EOL . PHP_EOL;

// 测试 3: 检查 Redis 客户端内部状态
echo "【测试 3】Redis 客户端内部状态：" . PHP_EOL;

try {
    $reflection = new ReflectionObject($redis);
    $properties = $reflection->getProperties();

    echo "  - 属性数量: " . count($properties) . PHP_EOL;

    // 检查可能累积的内部缓冲区
    foreach ($properties as $prop) {
        $prop->setAccessible(true);
        $value = $prop->getValue($redis);

        if (is_array($value) && count($value) > 0) {
            echo "  - {$prop->getName()}: array(" . count($value) . ")" . PHP_EOL;
        } elseif (is_string($value) && strlen($value) > 100) {
            echo "  - {$prop->getName()}: string(" . strlen($value) . " bytes)" . PHP_EOL;
        }
    }
} catch (Exception $e) {
    echo "  无法反射: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;

// 测试 4: 比较持久连接 vs 非持久连接
echo "【测试 4】持久连接 vs 非持久连接对比：" . PHP_EOL . PHP_EOL;

// 4.1 非持久连接测试
echo "  4.1 非持久连接（每次新建）：" . PHP_EOL;
$memBefore = memory_get_usage(true);

for ($i = 0; $i < 50; $i++) {
    $tempRedis = new Redis();
    $tempRedis->connect($defaultConfig['host'], $defaultConfig['port']);
    if (isset($defaultConfig['password'])) {
        $tempRedis->auth($defaultConfig['password']);
    }

    // 执行操作
    $tempRedis->set('test_key_' . $i, str_repeat('x', 2000));
    $tempRedis->get('test_key_' . $i);

    // 关闭连接
    $tempRedis->close();

    if ($i % 10 == 0 && $i > 0) {
        $memCurrent = memory_get_usage(true);
        echo "    {$i} 次: " . round(($memCurrent - $memBefore) / 1024, 2) . " KB" . PHP_EOL;
    }
}

$memAfterNonPersistent = memory_get_usage(true);
$leakNonPersistent = $memAfterNonPersistent - $memBefore;
echo "    总计: " . round($leakNonPersistent / 1024, 2) . " KB (平均 " . round($leakNonPersistent / 50, 2) . " bytes/次)" . PHP_EOL . PHP_EOL;

// 4.2 持久连接测试（复用同一个）
echo "  4.2 持久连接（复用）：" . PHP_EOL;
$memBefore = memory_get_usage(true);

$persistRedis = new Redis();
$persistRedis->pconnect($defaultConfig['host'], $defaultConfig['port'], 0, 'test_persist');
if (isset($defaultConfig['password'])) {
    $persistRedis->auth($defaultConfig['password']);
}

for ($i = 0; $i < 50; $i++) {
    // 复用同一个连接
    $persistRedis->set('test_key_persist_' . $i, str_repeat('x', 2000));
    $persistRedis->get('test_key_persist_' . $i);

    if ($i % 10 == 0 && $i > 0) {
        $memCurrent = memory_get_usage(true);
        echo "    {$i} 次: " . round(($memCurrent - $memBefore) / 1024, 2) . " KB" . PHP_EOL;
    }
}

$memAfterPersistent = memory_get_usage(true);
$leakPersistent = $memAfterPersistent - $memBefore;
echo "    总计: " . round($leakPersistent / 1024, 2) . " KB (平均 " . round($leakPersistent / 50, 2) . " bytes/次)" . PHP_EOL . PHP_EOL;

echo "  对比结果：" . PHP_EOL;
echo "  - 非持久连接总泄露: " . round($leakNonPersistent / 1024, 2) . " KB" . PHP_EOL;
echo "  - 持久连接总泄露: " . round($leakPersistent / 1024, 2) . " KB" . PHP_EOL;
echo "  - 差异: " . round(($leakPersistent - $leakNonPersistent) / 1024, 2) . " KB" . PHP_EOL;

if ($leakPersistent > $leakNonPersistent * 2) {
    echo "  ⚠️  持久连接泄露明显高于非持久连接！" . PHP_EOL;
}

echo PHP_EOL . "测试完成！" . PHP_EOL;
