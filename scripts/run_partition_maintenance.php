#!/usr/bin/env php
<?php
/**
 * 手動執行分區維護腳本
 *
 * 功能：歸檔舊分區到歷史表 → 刪除舊分區 → 建立下個月分區
 *
 * 執行方式：
 *   php scripts/run_partition_maintenance.php
 *   php scripts/run_partition_maintenance.php --dry-run
 *   php scripts/run_partition_maintenance.php --retention=3
 *
 * 參數：
 *   --retention  保留月數（預設 3，與 PartitionMaintenanceTask::RETENTION_MONTHS 一致）
 *   --dry-run    僅顯示計畫，不實際執行
 */

// ─── 顏色 ────────────────────────────────────────────────────────────────────
define('C_GREEN',  "\033[32m");
define('C_RED',    "\033[31m");
define('C_YELLOW', "\033[33m");
define('C_CYAN',   "\033[36m");
define('C_BOLD',   "\033[1m");
define('C_RESET',  "\033[0m");

function cinfo(string $msg): void { echo C_CYAN   . $msg . C_RESET . PHP_EOL; }
function cok(string $msg): void   { echo C_GREEN  . $msg . C_RESET . PHP_EOL; }
function cwarn(string $msg): void { echo C_YELLOW . $msg . C_RESET . PHP_EOL; }
function cerr(string $msg): void  { echo C_RED    . $msg . C_RESET . PHP_EOL; }
function cbold(string $msg): void { echo C_BOLD   . $msg . C_RESET . PHP_EOL; }

// ─── 解析參數 ─────────────────────────────────────────────────────────────────
$opts          = getopt('', ['retention:', 'dry-run']);
$retentionMonths = (int)($opts['retention'] ?? 3);
$dryRun        = isset($opts['dry-run']);

if ($retentionMonths < 1 || $retentionMonths > 24) {
    cerr("--retention 範圍須在 1~24 之間");
    exit(1);
}

// ─── Bootstrap ───────────────────────────────────────────────────────────────
require_once __DIR__ . '/../vendor/autoload.php';

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string {
        return __DIR__ . '/../' . $path;
    }
}

$envFile = base_path('.env');
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $v = trim($v, " \t\n\r\0\x0B\"'");
        putenv(trim($k) . '=' . $v);
        $_ENV[trim($k)] = $v;
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed {
        $val = getenv($key);
        return ($val === false) ? $default : $val;
    }
}

use Webman\Config;
Config::load(base_path('config'), ['route']);

use Illuminate\Database\Capsule\Manager as DB;
$capsule = new DB;
foreach (config('database.connections') as $name => $cfg) {
    $capsule->addConnection($cfg, $name);
}
$capsule->getDatabaseManager()->setDefaultConnection(config('database.default', 'mysql'));
$capsule->setAsGlobal();
$capsule->bootEloquent();

$db = DB::connection();

// ─── 計算目標分區 ──────────────────────────────────────────────────────────────
$archiveMonth    = date('Ym',   strtotime("-{$retentionMonths} month"));   // e.g. 202608
$archiveDate     = date('Y-m-01', strtotime("-{$retentionMonths} month")); // e.g. 2026-08-01
$archiveEndDate  = date('Y-m-01', strtotime('-' . ($retentionMonths - 1) . ' month')); // e.g. 2026-09-01
$archivePartition = "p{$archiveMonth}";

$nextMonth       = date('Ym',   strtotime('+1 month'));                    // e.g. 202612
$nextMonthStart  = date('Y-m-01', strtotime('+1 month'));
$nextMonthEnd    = date('Y-m-01', strtotime('+2 month'));
$nextPartition   = "p{$nextMonth}";

// ─── 標題 ────────────────────────────────────────────────────────────────────
cbold(str_repeat('─', 60));
cbold('  分區維護腳本（手動補跑）');
cbold(str_repeat('─', 60));
cinfo("  保留月數     : {$retentionMonths}");
cinfo("  歸檔分區     : {$archivePartition}（{$archiveDate} ~ {$archiveEndDate}）");
cinfo("  建立分區     : {$nextPartition}（{$nextMonthStart} ~ {$nextMonthEnd}）");
cinfo("  Dry-run 模式 : " . ($dryRun ? '是（不實際執行）' : '否'));
cbold(str_repeat('─', 60));
echo PHP_EOL;

// ─── Step 1：歸檔舊分區 → 歷史表 ────────────────────────────────────────────
cbold("[ Step 1 ] 歸檔 {$archivePartition} → play_game_record_history");

$partitionInfo = $db->select("
    SELECT PARTITION_NAME, TABLE_ROWS, DATA_LENGTH
    FROM INFORMATION_SCHEMA.PARTITIONS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'play_game_record'
      AND PARTITION_NAME = ?
", [$archivePartition]);

if (empty($partitionInfo)) {
    cwarn("  分區 {$archivePartition} 不存在，跳過歸檔步驟");
} else {
    $partitionRows = $partitionInfo[0]->TABLE_ROWS;
    cinfo("  分區行數（估算）: {$partitionRows}");

    // 檢查歷史表已有數量
    $existingCount = $db->selectOne("
        SELECT COUNT(*) AS cnt
        FROM play_game_record_history
        WHERE created_at >= ? AND created_at < ?
    ", [$archiveDate, $archiveEndDate])->cnt ?? 0;

    cinfo("  歷史表現有（按 created_at）: {$existingCount} 筆");

    if (!$dryRun) {
        try {
            $t = microtime(true);
            $db->statement("
                INSERT IGNORE INTO play_game_record_history
                SELECT * FROM play_game_record PARTITION ({$archivePartition})
            ");
            $elapsed = round(microtime(true) - $t, 2);

            $afterCount = $db->selectOne("
                SELECT COUNT(*) AS cnt
                FROM play_game_record_history
                WHERE created_at >= ? AND created_at < ?
            ", [$archiveDate, $archiveEndDate])->cnt ?? 0;

            cok("  ✅ 歸檔完成（用時 {$elapsed}s，歷史表現有 {$afterCount} 筆）");
        } catch (\Exception $e) {
            cerr("  ❌ 歸檔失敗，中止後續步驟：" . $e->getMessage());
            exit(1);
        }
    } else {
        cwarn("  [DRY-RUN] INSERT IGNORE INTO play_game_record_history SELECT * FROM play_game_record PARTITION ({$archivePartition})");
    }
}

echo PHP_EOL;

// ─── Step 2：刪除舊分區 ───────────────────────────────────────────────────────
cbold("[ Step 2 ] 刪除分區 {$archivePartition}");

$partitionExists = $db->select("
    SELECT PARTITION_NAME
    FROM INFORMATION_SCHEMA.PARTITIONS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'play_game_record'
      AND PARTITION_NAME = ?
", [$archivePartition]);

if (empty($partitionExists)) {
    cwarn("  分區 {$archivePartition} 不存在，跳過刪除");
} else {
    // 刪除前再次確認歷史表有數據
    $historyCount = $db->selectOne("
        SELECT COUNT(*) AS cnt
        FROM play_game_record_history
        WHERE created_at >= ? AND created_at < ?
    ", [$archiveDate, $archiveEndDate])->cnt ?? 0;

    if ($dryRun) {
        cwarn("  [DRY-RUN] ALTER TABLE play_game_record DROP PARTITION {$archivePartition}");
        cwarn("  （正式執行前會再次確認歷史表有數據才刪除）");
    } elseif ($historyCount === 0) {
        cerr("  ⛔ 歷史表中 {$archiveDate} ~ {$archiveEndDate} 無任何數據，拒絕刪除分區（防止數據丟失）");
        cerr("     請先確認歸檔是否成功，或手動檢查歷史表");
        exit(1);
    } else {
        cinfo("  歷史表已確認有 {$historyCount} 筆，繼續刪除");
        try {
            $t = microtime(true);
            $db->statement("ALTER TABLE play_game_record DROP PARTITION {$archivePartition}");
            $elapsed = round(microtime(true) - $t, 2);
            cok("  ✅ 分區 {$archivePartition} 已刪除（用時 {$elapsed}s）");
        } catch (\Exception $e) {
            cerr("  ❌ 刪除分區失敗：" . $e->getMessage());
            exit(1);
        }
    }
}

echo PHP_EOL;

// ─── Step 3：建立下個月分區 ───────────────────────────────────────────────────
cbold("[ Step 3 ] 建立分區 {$nextPartition}（{$nextMonthStart} ~ {$nextMonthEnd}）");

$nextExists = $db->select("
    SELECT PARTITION_NAME
    FROM INFORMATION_SCHEMA.PARTITIONS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'play_game_record'
      AND PARTITION_NAME = ?
", [$nextPartition]);

if (!empty($nextExists)) {
    cwarn("  分區 {$nextPartition} 已存在，跳過建立");
} else {
    if (!$dryRun) {
        try {
            $t = microtime(true);
            $db->statement("
                ALTER TABLE play_game_record
                REORGANIZE PARTITION p_future INTO (
                    PARTITION {$nextPartition} VALUES LESS THAN (TO_DAYS('{$nextMonthEnd}')),
                    PARTITION p_future VALUES LESS THAN MAXVALUE
                )
            ");
            $elapsed = round(microtime(true) - $t, 2);
            cok("  ✅ 分區 {$nextPartition} 建立成功（用時 {$elapsed}s）");
        } catch (\Exception $e) {
            cerr("  ❌ 建立分區失敗：" . $e->getMessage());
            exit(1);
        }
    } else {
        cwarn("  [DRY-RUN] REORGANIZE PARTITION p_future → {$nextPartition} + p_future");
    }
}

echo PHP_EOL;
cbold(str_repeat('─', 60));
cok('  全部步驟完成');
cbold(str_repeat('─', 60));
echo PHP_EOL;
