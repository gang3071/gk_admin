<?php
/**
 * 清理旧的内存紧急报告文件
 *
 * 用途：删除旧版本生成的大量带时间戳的紧急报告文件
 */

$logsDir = __DIR__ . '/runtime/logs';

echo "=== 清理旧的内存紧急报告 ===\n\n";

// 查找所有 memory_emergency_*.log 文件
$pattern = $logsDir . '/memory_emergency_*.log';
$files = glob($pattern);

if (empty($files)) {
    echo "✅ 没有找到旧的紧急报告文件\n";
    exit(0);
}

echo "找到 " . count($files) . " 个紧急报告文件:\n\n";

// 按文件名分组
$dailyReports = []; // memory_emergency_YYYYMMDD.log（每日报告，保留）
$timestampReports = []; // memory_emergency_YYYYMMDDHHiiss.log（旧版本，删除）

foreach ($files as $file) {
    $basename = basename($file);

    // 检查文件名格式
    if (preg_match('/memory_emergency_(\d{8})\.log$/', $basename, $matches)) {
        // 每日报告格式（8位数字：YYYYMMDD）
        $dailyReports[] = $file;
    } elseif (preg_match('/memory_emergency_(\d{14})\.log$/', $basename, $matches)) {
        // 时间戳格式（14位数字：YYYYMMDDHHiiss）
        $timestampReports[] = $file;
    } else {
        // 其他格式
        echo "  ⚠️ 未知格式: $basename\n";
    }
}

// 显示统计
echo "📊 统计:\n";
echo "  - 每日报告（保留）: " . count($dailyReports) . " 个\n";
echo "  - 时间戳报告（删除）: " . count($timestampReports) . " 个\n\n";

if (empty($timestampReports)) {
    echo "✅ 没有需要清理的旧报告文件\n";
    exit(0);
}

// 显示将要删除的文件
echo "将要删除的文件:\n";
$totalSize = 0;
foreach ($timestampReports as $file) {
    $size = filesize($file);
    $totalSize += $size;
    $sizeKB = round($size / 1024, 2);
    echo "  - " . basename($file) . " ({$sizeKB} KB)\n";
}

echo "\n总大小: " . round($totalSize / 1024, 2) . " KB\n\n";

// 确认删除
echo "确认删除这 " . count($timestampReports) . " 个文件? (y/n): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if (strtolower($line) !== 'y') {
    echo "\n❌ 已取消删除\n";
    exit(0);
}

// 执行删除
echo "\n开始删除...\n";
$deletedCount = 0;
$failedCount = 0;

foreach ($timestampReports as $file) {
    if (unlink($file)) {
        $deletedCount++;
        echo "  ✅ 已删除: " . basename($file) . "\n";
    } else {
        $failedCount++;
        echo "  ❌ 删除失败: " . basename($file) . "\n";
    }
}

echo "\n=== 清理完成 ===\n";
echo "成功删除: {$deletedCount} 个\n";
echo "删除失败: {$failedCount} 个\n";
echo "释放空间: " . round($totalSize / 1024, 2) . " KB\n";

if (count($dailyReports) > 0) {
    echo "\n保留的每日报告:\n";
    foreach ($dailyReports as $file) {
        echo "  - " . basename($file) . "\n";
    }
}

echo "\n✅ 清理完成！\n";
