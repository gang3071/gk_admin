#!/usr/bin/env php
<?php
/**
 * 實體機台積分補償腳本
 *
 * 用途：補償指定期間，所有線下實體機台玩家打碼應得但未入帳的積分。
 *
 * 補償來源：PlayerGameLog.chip_amount（打碼量） × 0.0006
 * 補償目標：player_points.available_points
 * 記錄追蹤：player_points_record（source='offline_machine_compensation'）
 *
 * 執行方式：
 *   php scripts/compensate_offline_machine_points.php [--dry-run]
 *   php scripts/compensate_offline_machine_points.php --start=2026-10-01 --end=2026-10-27
 *   php scripts/compensate_offline_machine_points.php --start=2026-10-01 --end=2026-10-27 --dry-run
 *
 * 參數：
 *   --start    開始日期（含），格式 Y-m-d，預設 2026-10-01
 *   --end      結束日期（含），格式 Y-m-d，預設 2026-10-27
 *   --dry-run  僅列印計劃，不實際執行
 */

// ─── 顏色常數 ────────────────────────────────────────────────────────────────
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

// ─── 解析命令列參數 ──────────────────────────────────────────────────────────
$opts = getopt('', ['start:', 'end:', 'dry-run']);

$startDate = $opts['start'] ?? '2026-10-01';
$endDate   = $opts['end']   ?? '2026-10-27';
$dryRun    = isset($opts['dry-run']);

foreach ([['start', $startDate], ['end', $endDate]] as [$label, $date]) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        cerr("參數 --{$label} 格式錯誤（需 Y-m-d）：{$date}");
        exit(1);
    }
}

$startTs = $startDate . ' 00:00:00';
$endTs   = $endDate   . ' 23:59:59';

// ─── Bootstrap ───────────────────────────────────────────────────────────────
require_once __DIR__ . '/../vendor/autoload.php';

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string {
        return __DIR__ . '/../' . $path;
    }
}

// 載入 .env
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

// 初始化 Redis（直接用 PHP Redis 擴充）
$redisCfg = config('redis.default', []);
$redis = new \Redis();
$redis->connect(
    $redisCfg['host']     ?? env('REDIS_HOST', '127.0.0.1'),
    (int)($redisCfg['port'] ?? env('REDIS_PORT', 6379))
);
$redisPwd = $redisCfg['password'] ?? env('REDIS_PASSWORD', '');
if (!empty($redisPwd)) $redis->auth($redisPwd);
$redisDb = (int)($redisCfg['database'] ?? env('REDIS_DB', 0));
if ($redisDb > 0) $redis->select($redisDb);

// ─── 常數 ────────────────────────────────────────────────────────────────────
const ACTION_LEAVE           = 2;
const ACTION_DOWN            = 3;
const MACHINE_SOURCE_OFFLINE = 2;
const COMPENSATION_SOURCE    = 'offline_machine_compensation';
const POINTS_CACHE_PREFIX = 'gk_api:player_points:';  // 與 PlayerPointsService::syncPointsCache 一致
const POINTS_RATIO        = 0.0006;                    // 打碼量 → 積分：chip_amount × 0.0006
const BATCH_SIZE          = 100;

// ─── 標題 ────────────────────────────────────────────────────────────────────
cbold(str_repeat('─', 60));
cbold('  實體機台積分補償腳本（補積分）');
cbold(str_repeat('─', 60));
cinfo("  範圍：{$startTs} ～ {$endTs}");
cinfo('  條件：所有線下機台（machine_source=2，含 MEI/SONG 全工控類型）');
cinfo('  積分：chip_amount × ' . POINTS_RATIO . ' → player_points.available_points');
if ($dryRun) {
    cwarn('  模式：DRY-RUN（僅列印，不實際入帳）');
} else {
    cok('  模式：LIVE（實際入帳）');
}
echo PHP_EOL;

// ─── 確認 ────────────────────────────────────────────────────────────────────
if (!$dryRun) {
    echo C_YELLOW . '⚠  即將對玩家積分帳戶實際入帳，確認繼續？[yes/no] ' . C_RESET;
    $confirm = trim(fgets(STDIN));
    if (strtolower($confirm) !== 'yes') {
        cwarn('已取消。如需試跑請加 --dry-run 參數。');
        exit(0);
    }
    echo PHP_EOL;
}

// ─── Step 1：一次性查出所有待補償的 gl.id ────────────────────────────────────
// 使用 whereNotExists 子查詢代替 LEFT JOIN + WHERE NULL，
// 避免 chunk 分頁因本腳本插入記錄後偏移量算錯而跳過記錄。
cinfo('正在查詢待補償記錄 ID 清單…');

$pendingIds = DB::table('player_game_log as gl')
    ->join('machine as m', 'm.id', '=', 'gl.machine_id')
    ->whereNotExists(function ($q) {
        // 已有補償記錄 → 排除
        $q->select(DB::raw(1))
          ->from('player_points_record as pr')
          ->whereColumn('pr.player_id', 'gl.player_id')
          ->where('pr.source', COMPENSATION_SOURCE)
          ->whereRaw("pr.remark LIKE CONCAT('% #', gl.id, ' %')");
    })
    ->where('gl.player_id', '>', 0)
    ->whereIn('gl.action', [ACTION_LEAVE, ACTION_DOWN])
    ->where('gl.chip_amount', '>', 0)
    ->where('m.machine_source', MACHINE_SOURCE_OFFLINE)
    ->whereBetween('gl.created_at', [$startTs, $endTs])
    ->whereNull('gl.deleted_at')
    ->orderBy('gl.id')
    ->pluck('gl.id')
    ->toArray();

$total = count($pendingIds);

if ($total === 0) {
    cok('無待補償記錄，結束。');
    exit(0);
}

// 預覽統計（基於 ID 清單查實際資料）
$previewRows = DB::table('player_game_log as gl')
    ->join('machine as m', 'm.id', '=', 'gl.machine_id')
    ->whereIn('gl.id', array_slice($pendingIds, 0, 10))
    ->select(['gl.id', 'gl.player_id', 'gl.chip_amount', 'gl.action', 'gl.created_at', 'm.code as machine_code'])
    ->orderBy('gl.id')
    ->get();

$totalChipAmt = DB::table('player_game_log')
    ->whereIn('id', $pendingIds)
    ->sum('chip_amount');
$totalPoints = round($totalChipAmt * POINTS_RATIO, 4);

cinfo("找到 {$total} 筆待補償記錄，合計打碼量 = " . number_format($totalChipAmt, 2)
    . "，折算積分 = " . number_format($totalPoints, 4));

echo PHP_EOL;
printf("  %-8s %-10s %-12s %-14s %-14s %-10s %s\n",
    'LogID', 'PlayerID', 'MachineCode', 'ChipAmount', 'Points(×0.0006)', 'Action', 'CreatedAt');
echo '  ' . str_repeat('-', 84) . PHP_EOL;
foreach ($previewRows as $row) {
    $pts = round((float)$row->chip_amount * POINTS_RATIO, 4);
    printf("  %-8d %-10d %-12s %-14.2f %-16.4f %-10s %s\n",
        $row->id, $row->player_id,
        $row->machine_code ?? '-',
        $row->chip_amount,
        $pts,
        $row->action == ACTION_LEAVE ? 'leave' : 'down',
        $row->created_at
    );
}
if ($total > 10) echo "  … 以及另外 " . ($total - 10) . " 筆\n";
echo PHP_EOL;

if ($dryRun) {
    cbold('DRY-RUN 完成。移除 --dry-run 即可實際執行。');
    exit(0);
}

// ─── Step 2：分批處理補償 ────────────────────────────────────────────────────
// 按靜態 ID 陣列分批（不依賴 offset 分頁），腳本執行中新插入記錄不影響批次邊界。
cbold('開始逐筆補償積分…');
echo PHP_EOL;

$success = 0;
$failed  = 0;
$skipped = 0;
$errors  = [];

$batches = array_chunk($pendingIds, BATCH_SIZE);

foreach ($batches as $batchIds) {
    // 批次取完整資料
    $batchLogs = DB::table('player_game_log as gl')
        ->join('machine as m', 'm.id', '=', 'gl.machine_id')
        ->whereIn('gl.id', $batchIds)
        ->select([
            'gl.id', 'gl.player_id', 'gl.department_id',
            'gl.chip_amount', 'gl.action', 'gl.created_at',
            'm.code as machine_code',
        ])
        ->orderBy('gl.id')
        ->get();

    foreach ($batchLogs as $log) {
        $logId    = (int)$log->id;
        $playerId = (int)$log->player_id;
        $deptId   = (int)($log->department_id ?? 0);
        $chipAmt  = (float)$log->chip_amount;
        $points   = round($chipAmt * POINTS_RATIO, 4);

        // 跳過計算後積分為 0 的記錄（chip_amount 過小）
        if ($points <= 0) {
            cwarn("  [SKIP] LogID={$logId} chip={$chipAmt} 折算積分=0，跳過");
            $skipped++;
            continue;
        }

        // 逐筆二次去重確認（防止腳本重跑或並發）
        $alreadyDone = DB::table('player_points_record')
            ->where('player_id', $playerId)
            ->where('source', COMPENSATION_SOURCE)
            ->where('remark', 'LIKE', "% #{$logId} %")
            ->exists();

        if ($alreadyDone) {
            cwarn("  [SKIP] LogID={$logId} Player={$playerId} 已補償，跳過");
            $skipped++;
            continue;
        }

        // 樂觀鎖最多重試 3 次
        $maxRetries = 3;
        $retryCount = 0;
        $done       = false;

        while ($retryCount < $maxRetries && !$done) {
            try {
                DB::beginTransaction();

                // 1. 鎖定積分主表
                $pp = DB::table('player_points')
                    ->where('player_id', $playerId)
                    ->lockForUpdate()
                    ->first();

                $now = date('Y-m-d H:i:s');

                if (!$pp) {
                    // 首次建立
                    DB::table('player_points')->insert([
                        'player_id'        => $playerId,
                        'department_id'    => $deptId,
                        'available_points' => $points,
                        'total_points'     => $points,
                        'frozen_points'    => 0,
                        'used_points'      => 0,
                        'version'          => 1,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ]);
                    $pointsBefore = 0.0;
                    $pointsAfter  = $points;
                } else {
                    $currentVersion = (int)$pp->version;
                    $pointsBefore   = (float)$pp->available_points;
                    $pointsAfter    = round($pointsBefore + $points, 4);

                    $affected = DB::table('player_points')
                        ->where('id',      $pp->id)
                        ->where('version', $currentVersion)
                        ->update([
                            'available_points' => DB::raw('available_points + ' . $points),
                            'total_points'     => DB::raw('total_points + '     . $points),
                            'version'          => $currentVersion + 1,
                            'updated_at'       => $now,
                        ]);

                    if ($affected === 0) {
                        // 樂觀鎖衝突，回滾後重試
                        DB::rollBack();
                        $retryCount++;
                        usleep(50000);
                        continue;
                    }
                }

                // 2. 同一事務內插入積分變動記錄（去重依據）
                //    必須在 commit 前插入，確保 player_points 更新與記錄建立原子性。
                DB::table('player_points_record')->insert([
                    'player_id'     => $playerId,
                    'department_id' => $deptId,
                    'type'          => 7,   // TYPE_POINTS_ADD
                    'source'        => COMPENSATION_SOURCE,
                    'points'        => $points,
                    'points_before' => $pointsBefore,
                    'points_after'  => $pointsAfter,
                    'remark'        => "實體機台積分補償 #{$logId} 機台:{$log->machine_code} 打碼:{$chipAmt}×0.0006",
                    'admin_id'      => null,
                    'admin_name'    => '補償腳本',
                    'admin_ip'      => null,
                    'created_at'    => $now,
                ]);

                DB::commit();

                // 3. 同步 Redis 快取（commit 後，失敗不影響資料一致性）
                try {
                    $freshPp = DB::table('player_points')
                        ->where('player_id', $playerId)
                        ->first();
                    if ($freshPp) {
                        $cacheKey = POINTS_CACHE_PREFIX . $playerId;
                        $redis->hMSet($cacheKey, [
                            'available_points' => (int)$freshPp->available_points,
                            'frozen_points'    => (int)$freshPp->frozen_points,
                            'total_points'     => (int)$freshPp->total_points,
                            'last_update'      => time(),
                        ]);
                        $redis->expire($cacheKey, 86400 * 365);
                    }
                } catch (\Throwable $re) {
                    cwarn("  [REDIS WARN] LogID={$logId} Redis同步失敗：" . $re->getMessage());
                }

                cok("  [OK]  LogID={$logId} Player={$playerId}"
                    . " chip={$chipAmt} +{$points}pt（{$pointsBefore} → {$pointsAfter}）");
                $success++;
                $done = true;

            } catch (\Throwable $e) {
                DB::rollBack();
                $retryCount++;
                usleep(50000);
                if ($retryCount >= $maxRetries) {
                    cerr("  [FAIL] LogID={$logId} Player={$playerId}: " . $e->getMessage());
                    $errors[] = "LogID={$logId}: " . $e->getMessage();
                    $failed++;
                    $done = true;
                }
            }
        } // end while retry
    } // end foreach batchLogs
} // end foreach batches

// ─── 結果摘要 ────────────────────────────────────────────────────────────────
echo PHP_EOL;
cbold(str_repeat('─', 60));
cbold('  補償結果摘要');
cbold(str_repeat('─', 60));
cok("  成功補償：{$success} 筆");
if ($skipped > 0) cwarn("  已跳過：{$skipped} 筆");
if ($failed  > 0) {
    cerr("  失敗：{$failed} 筆");
    foreach ($errors as $e) cerr("    - {$e}");
}
cbold(str_repeat('─', 60));
echo PHP_EOL;

exit($failed > 0 ? 1 : 0);
