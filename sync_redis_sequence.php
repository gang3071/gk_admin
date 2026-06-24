<?php
/**
 * 同步Redis序列号与数据库实际券数
 * 确保所有活动的Redis序列号正确
 */

// 加载环境变量
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

echo "\n========================================\n";
echo "同步Redis序列号与数据库实际券数\n";
echo "========================================\n\n";

// 数据库连接
$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? 3306;
$database = $_ENV['DB_DATABASE'] ?? 'gk_admin';
$username = $_ENV['DB_USERNAME'] ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("❌ 数据库连接失败: " . $e->getMessage() . "\n");
}

// Redis连接
$redisHost = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
$redisPort = $_ENV['REDIS_PORT'] ?? 6379;
$redisPassword = $_ENV['REDIS_PASSWORD'] ?? null;

try {
    $redis = new Redis();
    $redis->connect($redisHost, $redisPort);

    if ($redisPassword) {
        $redis->auth($redisPassword);
    }

    echo "✅ 已连接到 Redis ({$redisHost}:{$redisPort})\n\n";

} catch (Exception $e) {
    die("❌ Redis连接失败: " . $e->getMessage() . "\n");
}

try {
    // 获取所有活动
    $stmt = $pdo->query("SELECT id, name FROM lottery_ticket_activity ORDER BY id");
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($activities)) {
        echo "没有找到任何活动\n\n";
        exit(0);
    }

    echo "找到 " . count($activities) . " 个活动，开始同步...\n\n";

    $syncCount = 0;
    $errors = [];

    foreach ($activities as $activity) {
        try {
            // 统计实际券数
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM lottery_ticket WHERE activity_id = ?");
            $stmt->execute([$activity['id']]);
            $actualCount = $stmt->fetchColumn();

            // Redis键名
            $key = "lottery_activity:{$activity['id']}:ticket_sequence";

            // 更新Redis序列号
            $redis->set($key, $actualCount);

            echo "活动ID {$activity['id']} ({$activity['name']}): ";
            echo "实际券数 {$actualCount}，Redis序列号已更新\n";

            $syncCount++;

        } catch (Exception $e) {
            $errors[] = "活动ID {$activity['id']}: " . $e->getMessage();
            echo "活动ID {$activity['id']}: ❌ 同步失败 - {$e->getMessage()}\n";
        }
    }

    echo "\n========================================\n";
    echo "同步完成\n";
    echo "========================================\n\n";

    echo "成功同步: {$syncCount} 个活动\n";
    echo "失败: " . count($errors) . " 个活动\n";

    if (!empty($errors)) {
        echo "\n失败详情:\n";
        foreach ($errors as $error) {
            echo "  - {$error}\n";
        }
    }

    echo "\n";

} catch (Exception $e) {
    echo "❌ 同步失败: {$e->getMessage()}\n";
    echo "文件: {$e->getFile()}:{$e->getLine()}\n\n";
    exit(1);
}
