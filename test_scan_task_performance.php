<?php
/**
 * 测试 LotteryBetProgressScanTask 性能
 *
 * 使用方法：
 * php test_scan_task_performance.php
 */

require_once __DIR__ . '/support/bootstrap.php';

use addons\webman\model\LotteryTicketActivity;
use addons\webman\model\PlayerGameLog;
use addons\webman\model\PlayGameRecord;
use support\Db;

echo "\n========================================\n";
echo "LotteryBetProgressScanTask 性能测试\n";
echo "========================================\n\n";

// 1. 检查进行中的活动
echo "【1】检查进行中的活动\n";
$activities = LotteryTicketActivity::query()
    ->where('status', 1)
    ->get();

if ($activities->isEmpty()) {
    echo "❌ 没有进行中的活动\n";
    exit(1);
}

echo "找到 {$activities->count()} 个进行中的活动：\n";
foreach ($activities as $activity) {
    echo "  - ID: {$activity->id}, 名称: {$activity->name}\n";
}
echo "\n";

// 2. 模拟扫描（测试查询性能）
$testActivity = $activities->first();
echo "【2】测试查询性能（活动ID: {$testActivity->id}）\n";

$startTime = date('Y-m-d H:i:s', strtotime('-1 hour'));
$endTime = date('Y-m-d H:i:s');

echo "时间范围: {$startTime} ~ {$endTime}\n\n";

// 2.1 机台游戏查询
echo "  (1) 机台游戏打码量查询...\n";
$machineStart = microtime(true);

$machineSql = "
    SELECT player_id, SUM(chip_amount) as total_chip
    FROM player_game_log
    WHERE department_id = ?
      AND created_at >= ?
      AND created_at < ?
      AND chip_amount > 0
    GROUP BY player_id
";

$machineResults = Db::select($machineSql, [$testActivity->department_id, $startTime, $endTime]);
$machineDuration = (microtime(true) - $machineStart) * 1000;

echo "      结果: " . count($machineResults) . " 个玩家\n";
echo "      耗时: " . round($machineDuration, 2) . " ms\n";

if ($machineDuration > 1000) {
    echo "      ⚠️ 查询较慢，建议添加索引\n";
} else {
    echo "      ✅ 查询速度正常\n";
}
echo "\n";

// 2.2 电子游戏查询
echo "  (2) 电子游戏打码量查询...\n";
$onlineStart = microtime(true);

$onlineSql = "
    SELECT player_id, SUM(bet) as total_bet
    FROM play_game_record
    WHERE department_id = ?
      AND created_at >= ?
      AND created_at < ?
      AND bet > 0
      AND settlement_status < 2
    GROUP BY player_id
";

$onlineResults = Db::select($onlineSql, [$testActivity->department_id, $startTime, $endTime]);
$onlineDuration = (microtime(true) - $onlineStart) * 1000;

echo "      结果: " . count($onlineResults) . " 个玩家\n";
echo "      耗时: " . round($onlineDuration, 2) . " ms\n";

if ($onlineDuration > 1000) {
    echo "      ⚠️ 查询较慢，建议添加索引\n";
} else {
    echo "      ✅ 查询速度正常\n";
}
echo "\n";

// 3. 检查索引
echo "【3】检查数据库索引\n";

// 3.1 检查 player_game_log 索引
$machineIndexes = Db::select("
    SHOW INDEX FROM player_game_log
    WHERE Key_name LIKE '%lottery%' OR Key_name LIKE '%dept%time%'
");

echo "  player_game_log 表索引:\n";
if (empty($machineIndexes)) {
    echo "    ⚠️ 未找到相关索引，建议运行迁移添加索引\n";
} else {
    foreach ($machineIndexes as $index) {
        echo "    ✅ {$index->Key_name} ({$index->Column_name})\n";
    }
}
echo "\n";

// 3.2 检查 play_game_record 索引
$onlineIndexes = Db::select("
    SHOW INDEX FROM play_game_record
    WHERE Key_name LIKE '%lottery%' OR Key_name LIKE '%dept%time%status%'
");

echo "  play_game_record 表索引:\n";
if (empty($onlineIndexes)) {
    echo "    ⚠️ 未找到相关索引，建议运行迁移添加索引\n";
} else {
    foreach ($onlineIndexes as $index) {
        echo "    ✅ {$index->Key_name} ({$index->Column_name})\n";
    }
}
echo "\n";

// 4. 汇总性能评估
echo "【4】性能评估\n";

$totalQueryTime = $machineDuration + $onlineDuration;
echo "总查询时间: " . round($totalQueryTime, 2) . " ms\n";

// 估算更新时间（假设使用批量更新）
$totalPlayers = count($machineResults) + count($onlineResults);
$estimatedUpdateTime = $totalPlayers > 0 ? ($totalPlayers / 500) * 100 : 0; // 批量更新，每500个约100ms

$estimatedTotal = $totalQueryTime + $estimatedUpdateTime;

echo "估算更新时间: " . round($estimatedUpdateTime, 2) . " ms (批量更新)\n";
echo "================================\n";
echo "预计总耗时: " . round($estimatedTotal, 2) . " ms\n\n";

// 性能等级
if ($estimatedTotal < 1000) {
    echo "✅ 性能等级: 优秀 (< 1秒)\n";
} elseif ($estimatedTotal < 5000) {
    echo "✅ 性能等级: 良好 (1-5秒)\n";
} elseif ($estimatedTotal < 10000) {
    echo "⚠️ 性能等级: 一般 (5-10秒)\n";
    echo "   建议: 添加数据库索引\n";
} else {
    echo "❌ 性能等级: 较差 (> 10秒)\n";
    echo "   建议:\n";
    echo "   1. 添加数据库索引（必须）\n";
    echo "   2. 检查数据表是否过大\n";
    echo "   3. 考虑数据归档策略\n";
}

echo "\n========================================\n";
echo "测试完成\n";
echo "========================================\n\n";

// 5. 提供优化建议
echo "【优化建议】\n\n";

if (empty($machineIndexes)) {
    echo "1. 添加索引（运行迁移）：\n";
    echo "   vendor/bin/phinx migrate\n\n";
}

if ($totalQueryTime > 3000) {
    echo "2. 查询性能优化：\n";
    echo "   - 确认索引已创建\n";
    echo "   - 检查数据表大小\n";
    echo "   - 考虑分表策略\n\n";
}

if ($totalPlayers > 1000) {
    echo "3. 批量更新优化：\n";
    echo "   - 使用优化后的 LotteryBetProgressScanTask\n";
    echo "   - 批量SQL更新替代逐个更新\n";
    echo "   - 预计可提升 95% 以上性能\n\n";
}

echo "测试脚本执行完成！\n\n";
