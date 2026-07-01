<?php
/**
 * 测试摸奖券直播地址生成
 *
 * 使用方法：
 * php test_lottery_live_url.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use support\bootstrap\Log;
use support\bootstrap\Container;
use Webman\Bootstrap\LaravelDb;

// 初始化日志
Log::load(config('log') ?: []);

// 初始化数据库
$database = config('database', []);
LaravelDb::start($database);

// 颜色输出函数
function colorOutput($text, $color = 'green') {
    $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'reset' => "\033[0m"
    ];
    echo $colors[$color] . $text . $colors['reset'] . PHP_EOL;
}

echo "\n";
colorOutput("========================================", 'blue');
colorOutput("  摸奖券直播地址生成测试", 'blue');
colorOutput("========================================", 'blue');
echo "\n";

try {
    // 1. 获取腾讯云配置
    colorOutput("📋 步骤 1: 读取腾讯云配置", 'yellow');

    $config = \addons\webman\model\MachineTencentPlay::query()->find(1);

    if (!$config) {
        colorOutput("❌ 错误: 腾讯云配置不存在 (ID=1)", 'red');
        exit(1);
    }

    colorOutput("✅ 配置已找到: " . $config->title, 'green');

    // 显示配置详情
    echo "\n";
    colorOutput("🔧 配置详情:", 'blue');
    echo "  推流域名: " . ($config->push_domain ?: '未配置') . PHP_EOL;
    echo "  推流Key: " . (substr($config->push_key ?? '', 0, 10)) . "..." . PHP_EOL;
    echo "\n";
    echo "  ⭐ 播放域名（大陆）: " . ($config->pull_domain_cn ?: '未配置') . PHP_EOL;
    echo "  ⭐ 播放Key（大陆）: " . (substr($config->pull_key_cn ?? '', 0, 10)) . "..." . PHP_EOL;
    echo "\n";
    echo "  播放域名（全球）: " . ($config->pull_domain ?: '未配置') . PHP_EOL;
    echo "  播放Key（全球）: " . (substr($config->pull_key ?? '', 0, 10)) . "..." . PHP_EOL;
    echo "\n";
    echo "  License URL: " . ($config->license ?: '未配置') . PHP_EOL;
    echo "  License Key: " . (substr($config->license_key ?? '', 0, 10)) . "..." . PHP_EOL;

    // 2. 生成播放地址
    echo "\n";
    colorOutput("📋 步骤 2: 生成播放地址", 'yellow');

    $streamName = 'M056_6a32775dd7fae'; // 测试流名称
    $expireDays = 30; // 30天有效期

    echo "  流名称: {$streamName}" . PHP_EOL;
    echo "  有效期: {$expireDays} 天" . PHP_EOL;
    echo "\n";

    $urls = generateLotteryLiveUrls(1, $streamName, $expireDays);

    colorOutput("✅ 地址生成成功", 'green');

    // 3. 显示生成结果
    echo "\n";
    colorOutput("🎬 生成的播放地址:", 'blue');
    echo "\n";

    colorOutput("  🌍 使用区域: " . $urls['region'], 'yellow');
    colorOutput("  🌐 播放域名: " . $urls['pull_domain'], 'yellow');
    colorOutput("  ⏰ 过期时间: " . $urls['expire_time'], 'yellow');
    colorOutput("  🔑 txTime: " . $urls['tx_time'], 'yellow');
    colorOutput("  🔒 txSecret: " . substr($urls['tx_secret'], 0, 20) . "...", 'yellow');

    echo "\n";
    colorOutput("📺 播放地址（按推荐顺序）:", 'blue');
    echo "\n";

    // FLV（推荐）
    colorOutput("  1️⃣ HTTP-FLV（推荐）", 'green');
    echo "     延迟: 2-5秒" . PHP_EOL;
    echo "     兼容性: ✅ 优秀" . PHP_EOL;
    echo "     TCPlayer支持: ✅ 完美" . PHP_EOL;
    echo "     地址: " . $urls['flv'] . PHP_EOL;
    echo "\n";

    // HLS
    colorOutput("  2️⃣ HLS（备选）", 'yellow');
    echo "     延迟: 10-30秒" . PHP_EOL;
    echo "     兼容性: ✅ 最佳" . PHP_EOL;
    echo "     TCPlayer支持: ✅ 完美" . PHP_EOL;
    echo "     地址: " . $urls['hls'] . PHP_EOL;
    echo "\n";

    // WebRTC
    colorOutput("  3️⃣ WebRTC（需特殊配置）", 'red');
    echo "     延迟: <1秒" . PHP_EOL;
    echo "     兼容性: ⚠️ 需要HTTPS" . PHP_EOL;
    echo "     TCPlayer支持: ❌ 不完全支持" . PHP_EOL;
    echo "     地址: " . $urls['webrtc'] . PHP_EOL;
    echo "\n";

    // RTMP（仅推流）
    colorOutput("  4️⃣ RTMP（仅用于推流）", 'blue');
    echo "     用途: OBS推流地址" . PHP_EOL;
    echo "     浏览器播放: ❌ 不支持" . PHP_EOL;
    echo "     地址: " . $urls['rtmp'] . PHP_EOL;
    echo "\n";

    // 4. 验证配置完整性
    echo "\n";
    colorOutput("📋 步骤 3: 验证配置完整性", 'yellow');
    echo "\n";

    $checks = [];

    // 检查大陆域名配置
    if (!empty($config->pull_domain_cn) && !empty($config->pull_key_cn)) {
        $checks[] = ['✅ 大陆播放域名配置', true];
    } else {
        $checks[] = ['⚠️ 大陆播放域名未配置（将使用全球域名）', false];
    }

    // 检查全球域名配置
    if (!empty($config->pull_domain) && !empty($config->pull_key)) {
        $checks[] = ['✅ 全球播放域名配置', true];
    } else {
        $checks[] = ['❌ 全球播放域名未配置', false];
    }

    // 检查License配置
    if (!empty($config->license) && !empty($config->license_key)) {
        $checks[] = ['✅ TCPlayer License配置', true];
    } else {
        $checks[] = ['⚠️ TCPlayer License未配置（可能导致Error 55）', false];
    }

    // 检查有效期
    if ($expireDays >= 30) {
        $checks[] = ['✅ 有效期配置合理（' . $expireDays . '天）', true];
    } else {
        $checks[] = ['⚠️ 有效期较短（' . $expireDays . '天）', false];
    }

    foreach ($checks as $check) {
        if ($check[1]) {
            colorOutput($check[0], 'green');
        } else {
            colorOutput($check[0], 'yellow');
        }
    }

    // 5. 生成测试URL
    echo "\n";
    colorOutput("📋 步骤 4: 生成测试播放器URL", 'yellow');
    echo "\n";

    $playerUrl = "https://zi-test.5super9.com/lottery-live-player.html";

    // FLV 测试URL
    $testUrlFlv = $playerUrl . '?' . http_build_query([
        'url' => $urls['flv'],
        'licenseUrl' => $config->license,
        'licenseKey' => $config->license_key,
        'debug' => '1'
    ]);

    colorOutput("🎬 FLV播放器测试地址（推荐）:", 'green');
    echo $testUrlFlv . PHP_EOL;
    echo "\n";

    // WebRTC 测试URL
    $testUrlWebrtc = $playerUrl . '?' . http_build_query([
        'url' => $urls['webrtc'],
        'licenseUrl' => $config->license,
        'licenseKey' => $config->license_key,
        'debug' => '1'
    ]);

    colorOutput("🎬 WebRTC播放器测试地址（不推荐）:", 'yellow');
    echo $testUrlWebrtc . PHP_EOL;
    echo "\n";

    // 6. 使用建议
    echo "\n";
    colorOutput("========================================", 'blue');
    colorOutput("  💡 使用建议", 'blue');
    colorOutput("========================================", 'blue');
    echo "\n";

    colorOutput("1. 推荐使用 HTTP-FLV 格式（延迟低、兼容性好）", 'green');
    colorOutput("2. 在活动管理后台保存 FLV 地址，不要保存 WebRTC", 'green');
    colorOutput("3. 确保已配置大陆播放域名（访问速度更快）", 'green');
    colorOutput("4. 检查腾讯云控制台「鉴权有效时间」≥ 3600秒", 'yellow');
    colorOutput("5. 使用上面的测试地址验证播放是否正常", 'yellow');

    echo "\n";

} catch (\Exception $e) {
    colorOutput("\n❌ 错误: " . $e->getMessage(), 'red');
    echo "\n";
    echo "文件: " . $e->getFile() . PHP_EOL;
    echo "行号: " . $e->getLine() . PHP_EOL;
    echo "\n";
    exit(1);
}

colorOutput("✅ 测试完成", 'green');
echo "\n";
