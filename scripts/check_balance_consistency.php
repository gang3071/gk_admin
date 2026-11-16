#!/usr/bin/env php
<?php
/**
 * 余额一致性检查脚本
 *
 * 三项检查：
 *   [1] 账变记录内部一致性：amount_before + amount ≈ amount_after
 *   [2] 相邻账变连续性：前条 amount_after ≈ 后条 amount_before（检测隐性余额扣减）
 *   [3] 电子游戏账变（下注/结算）与游戏记录 diff 交叉比对
 *
 * 用法：
 *   php scripts/check_balance_consistency.php
 *   php scripts/check_balance_consistency.php --date=2026-11-15
 *   php scripts/check_balance_consistency.php --start="2026-11-01 00:00:00" --end="2026-11-15 23:59:59"
 *   php scripts/check_balance_consistency.php --player-id=17
 *   php scripts/check_balance_consistency.php --player-id=17 --date=2026-11-15
 *   php scripts/check_balance_consistency.php --skip-game
 *
 * 参数：
 *   --date        检查日期 YYYY-MM-DD（默认昨天）
 *   --start       开始时间 YYYY-MM-DD HH:mm:ss（与 --end 配合，优先于 --date）
 *   --end         结束时间 YYYY-MM-DD HH:mm:ss
 *   --player-id   只检查指定玩家
 *   --tolerance   金额误差容忍值（默认 0.0001）
 *   --limit       连续性检查最多扫描多少名玩家（默认 500）
 *   --skip-game   跳过电子游戏交叉比对（检查[3]）
 */

// ─── 颜色输出 ─────────────────────────────────────────────────────────────────
define('C_GREEN',  "\033[32m");
define('C_RED',    "\033[31m");
define('C_YELLOW', "\033[33m");
define('C_CYAN',   "\033[36m");
define('C_BOLD',   "\033[1m");
define('C_RESET',  "\033[0m");
define('C_DIM',    "\033[2m");

function cinfo(string $msg): void  { echo C_CYAN   . $msg . C_RESET . PHP_EOL; }
function cok(string $msg): void    { echo C_GREEN  . $msg . C_RESET . PHP_EOL; }
function cwarn(string $msg): void  { echo C_YELLOW . $msg . C_RESET . PHP_EOL; }
function cerr(string $msg): void   { echo C_RED    . $msg . C_RESET . PHP_EOL; }
function cbold(string $msg): void  { echo C_BOLD   . $msg . C_RESET . PHP_EOL; }
function cdim(string $msg): void   { echo C_DIM    . $msg . C_RESET . PHP_EOL; }

// ─── 解析命令行参数 ───────────────────────────────────────────────────────────
$opts      = getopt('', ['date:', 'start:', 'end:', 'player-id:', 'player_id:', 'tolerance:', 'limit:', 'skip-game']);
$playerId  = $opts['player-id'] ?? $opts['player_id'] ?? null;
$tolerance = (float)($opts['tolerance'] ?? 0.0001);
$limit     = (int)($opts['limit']       ?? 500);
$skipGame  = isset($opts['skip-game']);

// 时间范围
if (isset($opts['start'], $opts['end'])) {
    $start = $opts['start'];
    $end   = $opts['end'];
    $label = "{$start} ～ {$end}";
} else {
    $date  = $opts['date'] ?? date('Y-m-d', strtotime('-1 day'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        cerr("--date 格式错误（需 YYYY-MM-DD）：{$date}");
        exit(1);
    }
    $start = $date . ' 00:00:00';
    $end   = $date . ' 23:59:59';
    $label = $date;
}

// ─── Bootstrap ───────────────────────────────────────────────────────────────
require_once __DIR__ . '/../vendor/autoload.php';

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string {
        return rtrim(__DIR__ . '/../' . $path, '/');
    }
}

// 加载 .env
$envFile = __DIR__ . '/../.env';
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

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed {
        return Config::get($key, $default);
    }
}

use Illuminate\Database\Capsule\Manager as Capsule;
$capsule = new Capsule;
foreach (config('database.connections') as $name => $cfg) {
    $capsule->addConnection($cfg, $name);
}
$capsule->getDatabaseManager()->setDefaultConnection(config('database.default', 'mysql'));
$capsule->setAsGlobal();
$capsule->bootEloquent();

// ─── 表名与类型常量（直接硬编码，避免实例化带 DataPermissions trait 的 Eloquent 模型）────
define('TBL_DELIVERY',   'player_delivery_record');
define('TBL_GAME',       'play_game_record');
// 游戏相关账变类型：下注/取消下注/打赏/注单结算/重新结算
define('GAME_DELIVERY_TYPES', [26, 27, 28, 29, 30]);
define('SETTLEMENT_SETTLED',  1);

// ─── 标题 ────────────────────────────────────────────────────────────────────
cbold(str_repeat('─', 68));
cbold('  余额一致性检查脚本');
cbold(str_repeat('─', 68));
cinfo("  时间范围：{$label}");
if ($playerId) cinfo("  玩家ID：{$playerId}");
cinfo("  误差容忍：{$tolerance}");
echo PHP_EOL;

$totalErrors = 0;
$totalErrors += checkDeliveryInternal($start, $end, $playerId, $tolerance);
$totalErrors += checkDeliverySequential($start, $end, $playerId, $tolerance, $limit);
if (!$skipGame) {
    $totalErrors += checkGameVsDelivery($start, $end, $playerId, $tolerance);
}

echo PHP_EOL;
cbold(str_repeat('─', 68));
if ($totalErrors === 0) {
    cok("  全部检查通过，未发现异常");
} else {
    cerr("  共发现 {$totalErrors} 处异常，请人工核查");
}
cbold(str_repeat('─', 68));

exit($totalErrors > 0 ? 1 : 0);

// ═════════════════════════════════════════════════════════════════════════════
// [1] 账变记录内部一致性：amount_before + amount ≈ amount_after
// ═════════════════════════════════════════════════════════════════════════════
function checkDeliveryInternal(string $start, string $end, ?string $playerId, float $tolerance): int
{
    cbold('[1] 账变记录内部一致性（变更前 + 变更额 ≈ 变更后）');

    $rows    = [];
    $lastId  = 0;

    // 用游标分批，避免实例化带 DataPermissions trait 的 Eloquent 模型
    while (true) {
        $query = Capsule::table(TBL_DELIVERY)
            ->select('id', 'player_id', 'type', 'amount_before', 'amount', 'amount_after', 'created_at')
            ->whereBetween('created_at', [$start, $end])
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->limit(2000);

        if ($playerId !== null) {
            $query->where('player_id', $playerId);
        }

        $batch = $query->get();
        if ($batch->isEmpty()) break;

        foreach ($batch as $r) {
            $expected = round((float) $r->amount_before + (float) $r->amount, 6);
            $actual   = round((float) $r->amount_after, 6);
            $diff     = $actual - $expected;

            if (abs($diff) > $tolerance) {
                $rows[] = [
                    $r->id,
                    $r->player_id,
                    $r->type,
                    number_format((float) $r->amount_before, 4),
                    number_format((float) $r->amount,        4),
                    number_format((float) $r->amount_after,  4),
                    sprintf('%+.6f', $diff),
                    $r->created_at,
                ];
            }
            $lastId = $r->id;
        }

        if ($batch->count() < 2000) break;
    }

    if (empty($rows)) {
        cok("  ✓ 全部通过");
        echo PHP_EOL;
        return 0;
    }

    printTable(
        ['记录ID', '玩家ID', '类型', '变更前', '变更额', '变更后', '差异', '时间'],
        $rows
    );
    $count = count($rows);
    cerr("  ✗ 发现 {$count} 条内部不一致");
    echo PHP_EOL;
    return $count;
}

// ═════════════════════════════════════════════════════════════════════════════
// [2] 相邻账变连续性（核心检查）
// 同一玩家：records[n].amount_after ≈ records[n+1].amount_before
// 差值存在 → 可能存在未记录的隐性余额扣减
// ═════════════════════════════════════════════════════════════════════════════
function checkDeliverySequential(string $start, string $end, ?string $playerId, float $tolerance, int $limit): int
{
    cbold('[2] 账变连续性（相邻记录：前条变更后 ≈ 后条变更前，差值疑为隐性扣减）');

    if ($playerId !== null) {
        $pidsQuery = collect([$playerId]);
    } else {
        $pidsQuery = Capsule::table(TBL_DELIVERY)
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('player_id')
            ->limit($limit)
            ->pluck('player_id');
    }

    if ($pidsQuery->isEmpty()) {
        cinfo("  该时间段无账变记录");
        echo PHP_EOL;
        return 0;
    }

    $rows    = [];
    $checked = 0;

    foreach ($pidsQuery as $pid) {
        // 取截止 $end 最近 1000 条，含区间前的最后一条用于跨段检查
        $records = Capsule::table(TBL_DELIVERY)
            ->select('id', 'type', 'amount_before', 'amount_after', 'created_at')
            ->where('player_id', $pid)
            ->where('created_at', '<=', $end)
            ->orderBy('id', 'desc')
            ->limit(1000)
            ->get()
            ->reverse()
            ->values();

        $checked++;
        $prev = null;

        foreach ($records as $r) {
            if ($prev !== null && $r->created_at >= $start) {
                $gap = round((float) $r->amount_before - (float) $prev->amount_after, 6);

                if (abs($gap) > $tolerance) {
                    $rows[] = [
                        "#{$prev->id}→#{$r->id}",
                        $pid,
                        "type={$prev->type}",
                        "type={$r->type}",
                        number_format((float) $prev->amount_after, 4),
                        number_format((float) $r->amount_before,  4),
                        sprintf('%+.4f', $gap),
                        $prev->created_at,
                        $r->created_at,
                    ];
                }
            }
            $prev = $r;
        }
    }

    cdim("  共检查 {$checked} 名玩家");

    if (empty($rows)) {
        cok("  ✓ 全部通过，未发现连续性断裂");
        echo PHP_EOL;
        return 0;
    }

    printTable(
        ['记录区间', '玩家ID', '前条类型', '后条类型', '前条变更后', '后条变更前', '差额（疑似扣减）', '前条时间', '后条时间'],
        $rows
    );
    $count = count($rows);
    cerr("  ✗ 发现 {$count} 处连续性断裂（疑似存在未记录的余额扣减）");
    echo PHP_EOL;
    return $count;
}

// ═════════════════════════════════════════════════════════════════════════════
// [3] 电子游戏账变与游戏记录 diff 交叉比对
// 游戏账变合计（type 26/27/28/29/30）≈ play_game_record.diff 合计
// ═════════════════════════════════════════════════════════════════════════════
function checkGameVsDelivery(string $start, string $end, ?string $playerId, float $tolerance): int
{
    cbold('[3] 电子游戏账变（下注/结算）与游戏记录 diff 交叉比对');

    $dQuery = Capsule::table(TBL_DELIVERY)
        ->whereBetween('created_at', [$start, $end])
        ->whereIn('type', GAME_DELIVERY_TYPES)
        ->selectRaw('player_id, SUM(amount) as delivery_total, COUNT(*) as delivery_count')
        ->groupBy('player_id');

    $gQuery = Capsule::table(TBL_GAME)
        ->whereBetween('created_at', [$start, $end])
        ->where('settlement_status', SETTLEMENT_SETTLED)
        ->selectRaw('player_id, SUM(diff) as game_total, COUNT(*) as game_count')
        ->groupBy('player_id');

    if ($playerId !== null) {
        $dQuery->where('player_id', $playerId);
        $gQuery->where('player_id', $playerId);
    }

    $deliveryStats = $dQuery->get()->keyBy('player_id');
    $gameStats     = $gQuery->get()->keyBy('player_id');

    $allPids = $deliveryStats->keys()->merge($gameStats->keys())->unique();

    if ($allPids->isEmpty()) {
        cinfo("  该时间段无电子游戏记录");
        echo PHP_EOL;
        return 0;
    }

    $rows = [];

    foreach ($allPids as $pid) {
        $deliveryTotal = round((float) ($deliveryStats->get($pid)?->delivery_total ?? 0), 4);
        $gameTotal     = round((float) ($gameStats->get($pid)?->game_total ?? 0), 4);
        $diff          = $deliveryTotal - $gameTotal;

        if (abs($diff) > $tolerance) {
            $rows[] = [
                $pid,
                $deliveryStats->get($pid)?->delivery_count ?? 0,
                $gameStats->get($pid)?->game_count ?? 0,
                number_format($deliveryTotal, 4),
                number_format($gameTotal,     4),
                sprintf('%+.4f', $diff),
            ];
        }
    }

    cdim("  共对比 {$allPids->count()} 名玩家");

    if (empty($rows)) {
        cok("  ✓ 全部通过，账变与游戏记录一致");
        echo PHP_EOL;
        return 0;
    }

    printTable(
        ['玩家ID', '账变条数', '游戏局数', '账变合计', '游戏diff合计', '差额'],
        $rows
    );
    $count = count($rows);
    cerr("  ✗ 发现 {$count} 名玩家账变与游戏记录不一致");
    echo PHP_EOL;
    return $count;
}

// ─── 简易表格输出 ─────────────────────────────────────────────────────────────
function printTable(array $headers, array $rows): void
{
    // 计算每列最大宽度
    $widths = array_map('mb_strlen', $headers);
    foreach ($rows as $row) {
        foreach ($row as $i => $cell) {
            $widths[$i] = max($widths[$i] ?? 0, mb_strlen((string) $cell));
        }
    }

    $line = function (array $cells) use ($widths): string {
        $parts = [];
        foreach ($cells as $i => $cell) {
            $parts[] = mb_str_pad((string) $cell, $widths[$i]);
        }
        return '  | ' . implode(' | ', $parts) . ' |';
    };

    $sep = '  +' . implode('+', array_map(fn ($w) => str_repeat('-', $w + 2), $widths)) . '+';

    echo PHP_EOL . $sep . PHP_EOL;
    echo C_BOLD . $line($headers) . C_RESET . PHP_EOL;
    echo $sep . PHP_EOL;
    foreach ($rows as $row) {
        echo C_RED . $line($row) . C_RESET . PHP_EOL;
    }
    echo $sep . PHP_EOL . PHP_EOL;
}
