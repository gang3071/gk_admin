#!/usr/bin/env php
<?php
/**
 * 手动执行分区维护脚本（简化版 - 直接使用 PDO）
 *
 * 用途：立即清理主表，从 3 个月数据减少到 2 个月
 * 执行：php scripts/manual_partition_cleanup_simple.php
 */

// ANSI 颜色代码
const COLOR_GREEN = "\033[32m";
const COLOR_RED = "\033[31m";
const COLOR_YELLOW = "\033[33m";
const COLOR_BLUE = "\033[34m";
const COLOR_RESET = "\033[0m";

echo COLOR_BLUE . "
╔═══════════════════════════════════════════════════════════╗
║         分区维护任务 - 手动执行工具                        ║
║   将主表从 3 个月数据清理到 2 个月（立即执行）              ║
╚═══════════════════════════════════════════════════════════╝
" . COLOR_RESET . "\n";

// ===================================================
// 初始化数据库连接
// ===================================================
require_once __DIR__ . '/../vendor/autoload.php';

// 加载 .env 文件
if (class_exists('Dotenv\Dotenv') && file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createUnsafeMutable(__DIR__ . '/..');
    $dotenv->load();
}

// 读取数据库配置
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: 3306;
$dbName = getenv('DB_DATABASE') ?: 'yjb_gamebox';
$dbUser = getenv('DB_USERNAME') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    echo COLOR_GREEN . "✅ 数据库连接成功\n" . COLOR_RESET;
} catch (PDOException $e) {
    echo COLOR_RED . "❌ 数据库连接失败: {$e->getMessage()}\n" . COLOR_RESET;
    exit(1);
}

echo COLOR_YELLOW . "⚠️  重要提示：\n";
echo "1. 此操作将归档并删除超过 2 个月的旧分区\n";
echo "2. 数据会先归档到 play_game_record_history 表\n";
echo "3. 确认归档成功后才会删除分区\n";
echo "4. 整个过程预计耗时 2-5 分钟\n" . COLOR_RESET . "\n";

// ===================================================
// 步骤 1: 检查当前分区状态
// ===================================================
echo COLOR_BLUE . "\n📊 步骤 1/5: 检查当前分区状态...\n" . COLOR_RESET;

try {
    $stmt = $pdo->query("
        SELECT
            PARTITION_NAME,
            TABLE_ROWS,
            ROUND(DATA_LENGTH / 1024 / 1024, 2) AS data_mb,
            ROUND(INDEX_LENGTH / 1024 / 1024, 2) AS index_mb,
            PARTITION_DESCRIPTION
        FROM INFORMATION_SCHEMA.PARTITIONS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'play_game_record'
          AND PARTITION_NAME IS NOT NULL
          AND PARTITION_NAME != 'p_future'
        ORDER BY PARTITION_ORDINAL_POSITION
    ");

    $partitions = $stmt->fetchAll();

    if (empty($partitions)) {
        echo COLOR_RED . "❌ 错误：未找到分区表，请先执行分区改造\n" . COLOR_RESET;
        exit(1);
    }

    echo COLOR_GREEN . "✅ 当前分区列表：\n" . COLOR_RESET;
    printf("%-15s %-15s %-15s %-15s\n", "分区名", "数据行数", "数据大小", "索引大小");
    echo str_repeat("-", 70) . "\n";

    foreach ($partitions as $partition) {
        printf(
            "%-15s %-15s %-15s %-15s\n",
            $partition->PARTITION_NAME,
            number_format($partition->TABLE_ROWS),
            $partition->data_mb . ' MB',
            $partition->index_mb . ' MB'
        );
    }

} catch (PDOException $e) {
    echo COLOR_RED . "❌ 检查分区失败: {$e->getMessage()}\n" . COLOR_RESET;
    exit(1);
}

// ===================================================
// 步骤 2: 计算需要归档和删除的分区
// ===================================================
echo COLOR_BLUE . "\n📋 步骤 2/5: 计算需要清理的分区...\n" . COLOR_RESET;

// 当前时间
$now = new DateTime();
echo "当前时间: " . $now->format('Y-m-d H:i:s') . "\n";

// 计算 2 个月前的月份（新保留策略）
$twoMonthsAgo = (clone $now)->modify('-2 months');
$partitionToDelete = 'p' . $twoMonthsAgo->format('Ym');

echo "保留策略: 只保留最近 2 个月数据\n";
echo "分界线: " . $twoMonthsAgo->format('Y-m-01') . "\n";
echo COLOR_YELLOW . "需要归档并删除的分区: {$partitionToDelete}\n" . COLOR_RESET;

// 检查分区是否存在
$targetPartition = null;
foreach ($partitions as $p) {
    if ($p->PARTITION_NAME === $partitionToDelete) {
        $targetPartition = $p;
        break;
    }
}

if (!$targetPartition) {
    echo COLOR_GREEN . "✅ 分区 {$partitionToDelete} 不存在，无需清理\n" . COLOR_RESET;
    echo COLOR_GREEN . "✅ 主表已经只保留 2 个月数据\n" . COLOR_RESET;
    exit(0);
}

echo COLOR_YELLOW . "\n目标分区详情:\n";
echo "  - 分区名: {$targetPartition->PARTITION_NAME}\n";
echo "  - 数据行数: " . number_format($targetPartition->TABLE_ROWS) . " 条\n";
echo "  - 数据大小: {$targetPartition->data_mb} MB\n";
echo "  - 索引大小: {$targetPartition->index_mb} MB\n";
echo "  - 释放空间: " . ($targetPartition->data_mb + $targetPartition->index_mb) . " MB\n" . COLOR_RESET;

// ===================================================
// 步骤 3: 用户确认
// ===================================================
echo COLOR_BLUE . "\n⚠️  步骤 3/5: 确认操作...\n" . COLOR_RESET;
echo COLOR_YELLOW . "即将执行以下操作:\n";
echo "1. 归档分区 {$partitionToDelete} 的数据到历史表 (约 " . number_format($targetPartition->TABLE_ROWS) . " 条)\n";
echo "2. 删除分区 {$partitionToDelete} (释放 " . ($targetPartition->data_mb + $targetPartition->index_mb) . " MB)\n";
echo "\n是否继续？ (yes/no): " . COLOR_RESET;

$handle = fopen("php://stdin", "r");
$confirmation = trim(fgets($handle));
fclose($handle);

if (strtolower($confirmation) !== 'yes') {
    echo COLOR_YELLOW . "❌ 操作已取消\n" . COLOR_RESET;
    exit(0);
}

// ===================================================
// 步骤 4: 归档数据到历史表
// ===================================================
echo COLOR_BLUE . "\n📦 步骤 4/5: 归档数据到历史表...\n" . COLOR_RESET;

$archiveStartTime = microtime(true);

try {
    // 检查是否已归档
    $archiveDate = $twoMonthsAgo->format('Y-m-01');
    $archiveEndDate = (clone $twoMonthsAgo)->modify('+1 month')->format('Y-m-01');

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS count
        FROM play_game_record_history
        WHERE created_at >= ? AND created_at < ?
    ");
    $stmt->execute([$archiveDate, $archiveEndDate]);
    $existingCount = $stmt->fetchColumn();

    if ($existingCount > 0) {
        echo COLOR_GREEN . "✅ 数据已归档 ({$existingCount} 条)，跳过归档步骤\n" . COLOR_RESET;
    } else {
        echo "正在归档数据...\n";

        // 归档分区数据到历史表
        $pdo->exec("
            INSERT INTO play_game_record_history
            SELECT * FROM play_game_record PARTITION ({$partitionToDelete})
        ");

        $archiveDuration = round(microtime(true) - $archiveStartTime, 2);

        // 验证归档结果
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS count
            FROM play_game_record_history
            WHERE created_at >= ? AND created_at < ?
        ");
        $stmt->execute([$archiveDate, $archiveEndDate]);
        $archivedCount = $stmt->fetchColumn();

        echo COLOR_GREEN . "✅ 归档完成\n";
        echo "  - 归档行数: " . number_format($archivedCount) . " 条\n";
        echo "  - 耗时: {$archiveDuration} 秒\n" . COLOR_RESET;

        if ($archivedCount === 0) {
            echo COLOR_RED . "❌ 警告: 归档后历史表为空，中止删除操作\n" . COLOR_RESET;
            exit(1);
        }
    }

} catch (PDOException $e) {
    echo COLOR_RED . "❌ 归档失败: {$e->getMessage()}\n" . COLOR_RESET;
    echo COLOR_YELLOW . "⚠️  数据未丢失，分区仍在主表中\n" . COLOR_RESET;
    exit(1);
}

// ===================================================
// 步骤 5: 删除分区
// ===================================================
echo COLOR_BLUE . "\n🗑️  步骤 5/5: 删除分区...\n" . COLOR_RESET;

$deleteStartTime = microtime(true);

try {
    echo "正在删除分区 {$partitionToDelete}...\n";

    $pdo->exec("ALTER TABLE play_game_record DROP PARTITION {$partitionToDelete}");

    $deleteDuration = round(microtime(true) - $deleteStartTime, 2);

    echo COLOR_GREEN . "✅ 删除成功\n";
    echo "  - 删除行数: " . number_format($targetPartition->TABLE_ROWS) . " 条\n";
    echo "  - 释放空间: " . ($targetPartition->data_mb + $targetPartition->index_mb) . " MB\n";
    echo "  - 耗时: {$deleteDuration} 秒 ⚡\n" . COLOR_RESET;

} catch (PDOException $e) {
    echo COLOR_RED . "❌ 删除失败: {$e->getMessage()}\n" . COLOR_RESET;
    echo COLOR_YELLOW . "⚠️  数据已归档到历史表，您可以稍后手动删除分区\n" . COLOR_RESET;
    exit(1);
}

// ===================================================
// 完成报告
// ===================================================
$totalDuration = round(microtime(true) - $archiveStartTime, 2);

echo COLOR_GREEN . "\n╔═══════════════════════════════════════════════════════════╗\n";
echo "║                    🎉 执行完成！                           ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n" . COLOR_RESET;

echo "\n执行摘要:\n";
echo "  - 归档分区: {$partitionToDelete}\n";
echo "  - 归档行数: " . number_format($archivedCount ?? $existingCount) . " 条\n";
echo "  - 释放空间: " . ($targetPartition->data_mb + $targetPartition->index_mb) . " MB\n";
echo "  - 总耗时: {$totalDuration} 秒\n";

// 显示当前分区状态
echo COLOR_BLUE . "\n📊 当前分区状态:\n" . COLOR_RESET;

try {
    $stmt = $pdo->query("
        SELECT
            PARTITION_NAME,
            TABLE_ROWS,
            ROUND(DATA_LENGTH / 1024 / 1024, 2) AS data_mb
        FROM INFORMATION_SCHEMA.PARTITIONS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'play_game_record'
          AND PARTITION_NAME IS NOT NULL
          AND PARTITION_NAME != 'p_future'
        ORDER BY PARTITION_ORDINAL_POSITION
    ");

    $currentPartitions = $stmt->fetchAll();
    $totalRows = 0;
    $totalSize = 0;

    foreach ($currentPartitions as $partition) {
        echo "  - {$partition->PARTITION_NAME}: " . number_format($partition->TABLE_ROWS) . " 条, {$partition->data_mb} MB\n";
        $totalRows += $partition->TABLE_ROWS;
        $totalSize += $partition->data_mb;
    }

    echo COLOR_GREEN . "\n✅ 主表总计: " . number_format($totalRows) . " 条, {$totalSize} MB\n" . COLOR_RESET;
    echo COLOR_GREEN . "✅ 保留期已调整为: 最近 2 个月数据\n" . COLOR_RESET;

} catch (PDOException $e) {
    echo COLOR_YELLOW . "⚠️  无法获取当前分区状态: {$e->getMessage()}\n" . COLOR_RESET;
}

echo COLOR_BLUE . "\n📋 下一步:\n" . COLOR_RESET;
echo "  1. ✅ 主表已清理，只保留最近 2 个月数据\n";
echo "  2. ✅ 旧数据已安全归档到 play_game_record_history\n";
echo "  3. ✅ 下次自动维护: 每月 1 日凌晨 1:00\n";
echo "  4. ℹ️  可以使用以下 SQL 查询历史数据:\n";
echo "     SELECT * FROM play_game_record_history WHERE created_at >= '{$archiveDate}';\n";

echo "\n";
exit(0);
