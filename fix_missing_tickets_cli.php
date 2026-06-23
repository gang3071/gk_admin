<?php
/**
 * 补发遗漏的奖券（CLI版本，不依赖Webman框架）
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

$activityId = $argv[1] ?? null;

echo "\n========================================\n";
echo "补发遗漏的奖券\n";
if ($activityId) {
    echo "活动ID: {$activityId}\n";
} else {
    echo "扫描所有活动\n";
}
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

} catch (Exception $e) {
    die("❌ Redis连接失败: " . $e->getMessage() . "\n");
}

try {
    // 查询所有异常进度记录
    $sql = "
        SELECT
            p.id,
            p.activity_id,
            p.player_id,
            p.department_id,
            p.current_bet_amount,
            p.bet_amount_required,
            p.cycles_completed,
            p.ticket_count_per_cycle,
            p.total_tickets_issued,
            a.end_time,
            pl.name as player_name
        FROM lottery_ticket_bet_progress p
        JOIN lottery_ticket_activity a ON a.id = p.activity_id
        LEFT JOIN player pl ON pl.id = p.player_id
        WHERE p.status = 1
          AND p.current_bet_amount >= p.bet_amount_required
          AND p.total_tickets_issued < FLOOR(p.current_bet_amount / p.bet_amount_required) * p.ticket_count_per_cycle
    ";

    $params = [];
    if ($activityId) {
        $sql .= " AND p.activity_id = ?";
        $params[] = $activityId;
    }

    $sql .= " ORDER BY p.id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $abnormalRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($abnormalRecords)) {
        echo "✅ 没有发现需要补发的记录\n\n";
        exit(0);
    }

    echo "发现 " . count($abnormalRecords) . " 条需要补发的记录\n\n";

    $totalIssued = 0;
    $successCount = 0;
    $errors = [];

    foreach ($abnormalRecords as $progress) {
        try {
            // 计算应发券数
            $shouldCycles = floor($progress['current_bet_amount'] / $progress['bet_amount_required']);
            $shouldTickets = $shouldCycles * $progress['ticket_count_per_cycle'];
            $missingTickets = $shouldTickets - $progress['total_tickets_issued'];

            if ($missingTickets <= 0) {
                continue;
            }

            echo "进度ID {$progress['id']}, 玩家 {$progress['player_name']} (ID {$progress['player_id']}):\n";
            echo "  打码: {$progress['current_bet_amount']} / {$progress['bet_amount_required']}\n";
            echo "  应发券: {$shouldTickets}, 已发: {$progress['total_tickets_issued']}, 缺少: {$missingTickets}\n";

            // 开始补发
            $pdo->beginTransaction();
            try {
                // 1. 预分配Redis序列号
                $key = "lottery_activity:{$progress['activity_id']}:ticket_sequence";
                $baseSequence = $redis->incrBy($key, $missingTickets);
                $startSequence = $baseSequence - $missingTickets + 1;

                // 检查是否超过上限
                if ($baseSequence > 999999) {
                    $redis->decrBy($key, $missingTickets);
                    throw new Exception('活动奖券编号已用尽（超过100万张）');
                }

                // 2. 批量准备券数据
                $now = date('Y-m-d H:i:s');
                $ticketsData = [];

                for ($i = 0; $i < $missingTickets; $i++) {
                    $sequence = $startSequence + $i;
                    $ticketNo = str_pad($sequence, 6, '0', STR_PAD_LEFT);

                    $ticketsData[] = "({$progress['activity_id']}, {$progress['player_id']}, {$progress['department_id']}, '{$ticketNo}', 0, 'betting', '{$progress['end_time']}', '{$now}', '{$now}')";
                }

                // 3. 批量插入
                $valuesSql = implode(',', $ticketsData);
                $insertSql = "
                    INSERT INTO lottery_ticket
                    (activity_id, player_id, department_id, ticket_no, status, source, expired_at, created_at, updated_at)
                    VALUES {$valuesSql}
                ";
                $pdo->exec($insertSql);

                // 4. 更新进度记录
                $updateSql = "
                    UPDATE lottery_ticket_bet_progress
                    SET cycles_completed = ?,
                        total_tickets_issued = total_tickets_issued + ?,
                        last_issued_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([$shouldCycles, $missingTickets, $progress['id']]);

                $pdo->commit();

                echo "  ✅ 成功补发 {$missingTickets} 张券（券号：" . str_pad($startSequence, 6, '0', STR_PAD_LEFT) . " ~ " . str_pad($baseSequence, 6, '0', STR_PAD_LEFT) . "）\n\n";

                $totalIssued += $missingTickets;
                $successCount++;

            } catch (Exception $e) {
                $pdo->rollBack();

                // 回退Redis序列号
                if (isset($key) && isset($missingTickets)) {
                    $redis->decrBy($key, $missingTickets);
                }

                throw $e;
            }

        } catch (Exception $e) {
            $errors[] = "进度ID {$progress['id']}: " . $e->getMessage();
            echo "  ❌ 补发失败: {$e->getMessage()}\n\n";
        }
    }

    echo "========================================\n";
    echo "补发完成\n";
    echo "========================================\n\n";

    echo "处理记录数: " . count($abnormalRecords) . "\n";
    echo "成功: {$successCount}\n";
    echo "失败: " . count($errors) . "\n";
    echo "总补发券数: {$totalIssued}\n";

    if (!empty($errors)) {
        echo "\n失败详情:\n";
        foreach ($errors as $error) {
            echo "  - {$error}\n";
        }
    }

    echo "\n";

} catch (Exception $e) {
    echo "❌ 补发失败: {$e->getMessage()}\n";
    echo "文件: {$e->getFile()}:{$e->getLine()}\n\n";
    exit(1);
}
