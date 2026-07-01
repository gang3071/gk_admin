<?php
// 测试 PDO 连接状态累积

require_once __DIR__ . '/vendor/autoload.php';

use support\Db;

echo "=== 测试 PDO 连接状态累积 ===" . PHP_EOL;

// 获取当前连接
$pdo = Db::connection()->getPdo();

echo "PDO 属性检查：" . PHP_EOL;
echo "- ATTR_EMULATE_PREPARES: " . ($pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) ? 'true (模拟)' : 'false (真实)') . PHP_EOL;
echo "- ATTR_PERSISTENT: " . ($pdo->getAttribute(PDO::ATTR_PERSISTENT) ? 'true (持久)' : 'false (非持久)') . PHP_EOL;
echo "- ATTR_STATEMENT_CLASS: " . print_r($pdo->getAttribute(PDO::ATTR_STATEMENT_CLASS), true);
echo "- ATTR_DEFAULT_FETCH_MODE: " . $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE) . PHP_EOL;

// 模拟多次查询
echo PHP_EOL . "执行 100 次查询，观察内存变化：" . PHP_EOL;
$memBefore = memory_get_usage(true);
echo "初始内存: " . round($memBefore / 1024 / 1024, 2) . " MB" . PHP_EOL;

for ($i = 0; $i < 100; $i++) {
    // 模拟典型的业务查询
    $result = Db::table('yjb_player')
        ->select(['id', 'name', 'uuid', 'department_id'])
        ->where('status', 1)
        ->limit(100)
        ->get();

    // 不释放 $result，模拟可能的泄露
    unset($result); // 如果注释掉这行，看内存是否增长

    if ($i % 20 == 0) {
        $memCurrent = memory_get_usage(true);
        $leaked = $memCurrent - $memBefore;
        echo "  第 {$i} 次: " . round($memCurrent / 1024 / 1024, 2) . " MB (+". round($leaked / 1024 / 1024, 2) ." MB)" . PHP_EOL;
    }
}

$memAfter = memory_get_usage(true);
$totalLeaked = $memAfter - $memBefore;
echo PHP_EOL . "总泄露: " . round($totalLeaked / 1024 / 1024, 2) . " MB" . PHP_EOL;
echo "平均每次: " . round($totalLeaked / 100 / 1024, 2) . " KB" . PHP_EOL;

// 检查连接对象内部状态
echo PHP_EOL . "=== 连接对象状态检查 ===" . PHP_EOL;
$connection = Db::connection();
$reflection = new ReflectionObject($connection);

// 检查是否有查询日志
$loggingProp = $reflection->getProperty('loggingQueries');
$loggingProp->setAccessible(true);
echo "- loggingQueries: " . ($loggingProp->getValue($connection) ? 'true' : 'false') . PHP_EOL;

if ($loggingProp->getValue($connection)) {
    $queryLogProp = $reflection->getProperty('queryLog');
    $queryLogProp->setAccessible(true);
    $queryLog = $queryLogProp->getValue($connection);
    echo "- queryLog 条数: " . count($queryLog) . PHP_EOL;
}

// 检查事务计数
try {
    $transactionsProp = $reflection->getProperty('transactions');
    $transactionsProp->setAccessible(true);
    echo "- transactions: " . $transactionsProp->getValue($connection) . PHP_EOL;
} catch (Exception $e) {
    echo "- transactions: 无法访问" . PHP_EOL;
}

echo PHP_EOL . "测试完成！" . PHP_EOL;
