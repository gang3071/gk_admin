<?php
/**
 * 轻量级诊断：为什么没有自动创建打码进度记录（不依赖Webman）
 *
 * 使用方法：
 * php debug_why_no_progress_lite.php 17
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
    die("数据库连接失败: " . $e->getMessage() . "\n");
}

$activityId = $argv[1] ?? 17;

echo "\n========================================\n";
echo "深度诊断：为什么没有自动创建打码进度\n";
echo "活动ID: $activityId\n";
echo "========================================\n\n";

// 1. 检查活动状态
echo "【检查点1】活动状态\n";
$stmt = $pdo->prepare("SELECT * FROM lottery_ticket_activity WHERE id = ?");
$stmt->execute([$activityId]);
$activity = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$activity) {
    echo "❌ 致命错误：活动不存在\n";
    exit(1);
}

echo "活动名称: {$activity['name']}\n";
echo "活动状态: {$activity['status']} ";

if ($activity['status'] != 1) {
    echo "❌ 错误！必须是 1（进行中）\n";
    echo "   当前状态不是进行中，createProgressForPlayer() 会在第312行返回null\n\n";
} else {
    echo "✅ 正确（进行中）\n";
}

// 2. 检查时间范围
echo "\n【检查点2】时间范围\n";
$now = date('Y-m-d H:i:s');
echo "当前时间: {$now}\n";
echo "开始时间: {$activity['start_time']}\n";
echo "结束时间: {$activity['end_time']}\n";

if ($now >= $activity['end_time']) {
    echo "❌ 错误！当前时间已超过结束时间\n";
    echo "   createProgressForPlayer() 会在第318行返回null\n\n";
} else if ($now < $activity['start_time']) {
    echo "⚠️ 警告：活动尚未开始\n\n";
} else {
    echo "✅ 正确（活动进行中）\n";
}

// 3. 检查是否有游戏记录（机台+电子游戏）
echo "\n【检查点3】游戏记录\n";

// 3.1 机台游戏（player_game_log）
echo "A. 机台游戏（player_game_log）：\n";
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_count,
        COUNT(DISTINCT player_id) as player_count,
        SUM(chip_amount) as total_chip
    FROM player_game_log
    WHERE department_id = ?
        AND created_at >= ?
        AND created_at <= ?
        AND chip_amount > 0
");
$stmt->execute([$activity['department_id'], $activity['start_time'], $now]);
$machineStats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "  记录数: {$machineStats['total_count']}\n";
echo "  玩家数: {$machineStats['player_count']}\n";
echo "  打码量: " . number_format($machineStats['total_chip'] ?? 0, 2) . "\n";

// 3.2 电子游戏（play_game_record）
echo "\nB. 电子游戏（play_game_record）：\n";
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_count,
        COUNT(DISTINCT player_id) as player_count,
        SUM(bet) as total_bet
    FROM play_game_record
    WHERE department_id = ?
        AND created_at >= ?
        AND created_at <= ?
        AND bet > 0
        AND settlement_status < 2
");
$stmt->execute([$activity['department_id'], $activity['start_time'], $now]);
$onlineStats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "  记录数: {$onlineStats['total_count']}\n";
echo "  玩家数: {$onlineStats['player_count']}\n";
echo "  打码量: " . number_format($onlineStats['total_bet'] ?? 0, 2) . "\n";

// 汇总
$totalCount = $machineStats['total_count'] + $onlineStats['total_count'];
$totalChip = ($machineStats['total_chip'] ?? 0) + ($onlineStats['total_bet'] ?? 0);

echo "\nC. 汇总：\n";
echo "  总记录数: {$totalCount}\n";
echo "  总打码量: " . number_format($totalChip, 2) . "\n";

if ($totalCount == 0) {
    echo "\n❌ 错误！活动期间该渠道没有游戏记录\n";

    // 检查是否有其他渠道的记录
    echo "\n正在检查其他渠道...\n";
    $stmt = $pdo->prepare("
        SELECT department_id, COUNT(*) as cnt, SUM(bet) as total
        FROM play_game_record
        WHERE created_at >= ?
            AND created_at <= ?
        GROUP BY department_id
        ORDER BY cnt DESC
        LIMIT 5
    ");
    $stmt->execute([$activity['start_time'], $now]);
    $otherDepts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($otherDepts)) {
        echo "发现其他渠道有记录：\n";
        foreach ($otherDepts as $dept) {
            echo "  渠道ID {$dept['department_id']}: {$dept['cnt']} 条记录, 打码 " . number_format($dept['total'] ?? 0, 2) . "\n";
        }
        echo "\n⚠️ 可能原因：玩家所属渠道ID({$otherDepts[0]['department_id']}) ≠ 活动配置的渠道ID({$activity['department_id']})\n\n";
    }

    exit(0);
} else {
    echo "\n✅ 正确（有游戏记录）\n";
}

// 4. 获取有打码的玩家列表（机台+电子游戏）
echo "\n【检查点4】玩家详细信息（前10名）\n";

// 合并机台和电子游戏的打码量
$stmt = $pdo->prepare("
    SELECT player_id, SUM(total_chip) as total_chip
    FROM (
        SELECT player_id, SUM(chip_amount) as total_chip
        FROM player_game_log
        WHERE department_id = ?
            AND created_at >= ?
            AND created_at <= ?
            AND chip_amount > 0
        GROUP BY player_id

        UNION ALL

        SELECT player_id, SUM(bet) as total_chip
        FROM play_game_record
        WHERE department_id = ?
            AND created_at >= ?
            AND created_at <= ?
            AND bet > 0
            AND settlement_status < 2
        GROUP BY player_id
    ) combined
    GROUP BY player_id
    ORDER BY total_chip DESC
    LIMIT 10
");
$stmt->execute([
    $activity['department_id'], $activity['start_time'], $now,
    $activity['department_id'], $activity['start_time'], $now
]);
$playerBets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$successCount = 0;
$failureReasons = [];

foreach ($playerBets as $bet) {
    $playerId = $bet['player_id'];
    $chipAmount = $bet['total_chip'];

    echo "\n玩家ID: {$playerId}, 打码量: " . number_format($chipAmount, 2) . "\n";

    // 检查玩家是否存在
    $stmt = $pdo->prepare("SELECT * FROM player WHERE id = ?");
    $stmt->execute([$playerId]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        echo "  ❌ 失败：玩家不存在（createProgressForPlayer 第323行返回null）\n";
        $failureReasons['player_not_found'] = ($failureReasons['player_not_found'] ?? 0) + 1;
        continue;
    }

    echo "  玩家名称: {$player['name']}\n";
    echo "  玩家UUID: {$player['uuid']}\n";
    echo "  VIP等级ID: {$player['vip_level_id']}\n";
    echo "  玩家状态: {$player['status']}\n";

    // 检查VIP配置
    $stmt = $pdo->prepare("
        SELECT * FROM lottery_ticket_vip_config
        WHERE activity_id = ?
            AND vip_level_id = ?
            AND status = 1
    ");
    $stmt->execute([$activityId, $player['vip_level_id']]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        echo "  ❌ 失败：该玩家的VIP等级({$player['vip_level_id']})没有配置打码量\n";
        echo "     createProgressForPlayer 第333行返回null\n";
        echo "     解决方法：在活动配置中添加VIP等级 {$player['vip_level_id']} 的配置\n";

        if (!isset($failureReasons['vip_config_missing'])) {
            $failureReasons['vip_config_missing'] = [];
        }
        $failureReasons['vip_config_missing'][$player['vip_level_id']] =
            ($failureReasons['vip_config_missing'][$player['vip_level_id']] ?? 0) + 1;
        continue;
    }

    echo "  ✅ VIP配置存在：打码要求 {$config['bet_amount_required']}, 发券数 {$config['ticket_count']}\n";

    // 检查是否已有进度记录
    $stmt = $pdo->prepare("
        SELECT * FROM lottery_ticket_bet_progress
        WHERE activity_id = ? AND player_id = ?
    ");
    $stmt->execute([$activityId, $playerId]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($progress) {
        echo "  ℹ️ 已存在进度记录（当前打码: {$progress['current_bet_amount']}）\n";
    } else {
        echo "  ⚠️ 没有进度记录，理论上应该自动创建\n";
    }

    $successCount++;
}

// 5. 汇总分析
echo "\n========================================\n";
echo "诊断结果汇总\n";
echo "========================================\n\n";

if (empty($failureReasons)) {
    if ($successCount > 0) {
        echo "✅ 所有检查点通过！理论上应该能自动创建进度记录\n\n";
        echo "可能的原因：\n";
        echo "1. 后台任务 LotteryBetProgressScanTask 没有运行\n";
        echo "   检查方法：tail -50 runtime/logs/webman.log | grep '摸奖券打码进度'\n";
        echo "2. 后台任务执行时出现异常但被 try-catch 捕获\n";
        echo "   检查方法：tail -100 runtime/logs/webman.log | grep 'ERROR'\n";
        echo "3. Redis缓存锁导致任务跳过\n";
        echo "   检查方法：redis-cli GET lottery_bet_scan_status\n\n";

        echo "建议操作：\n";
        echo "1. 重启 Webman 服务：php windows.php restart\n";
        echo "2. 等待1-2分钟后检查日志：tail -50 runtime/logs/webman.log\n";
        echo "3. 如果还是不行，手动初始化：php init_lottery_progress.php {$activityId}\n";
    }
} else {
    echo "❌ 发现以下问题：\n\n";

    if (isset($failureReasons['vip_config_missing'])) {
        echo "【严重】缺少VIP配置：\n";
        foreach ($failureReasons['vip_config_missing'] as $vipId => $count) {
            echo "  - VIP等级 {$vipId}：{$count} 个玩家无法创建进度\n";
        }
        echo "\n解决方法（执行以下SQL）：\n";
        foreach (array_keys($failureReasons['vip_config_missing']) as $vipId) {
            echo "INSERT INTO lottery_ticket_vip_config (activity_id, vip_level_id, bet_amount_required, ticket_count, status, created_at, updated_at) VALUES ({$activityId}, {$vipId}, 10.00, 1, 1, NOW(), NOW());\n";
        }
        echo "\n";
    }

    if (isset($failureReasons['player_not_found'])) {
        echo "【警告】{$failureReasons['player_not_found']} 个玩家ID在player表中不存在\n";
        echo "这可能是数据不一致导致的，需要清理 player_game_log 表\n\n";
    }
}

echo "诊断完成！\n\n";
