<?php
/**
 * 简化版地址生成测试（不依赖框架）
 */

echo "\n========================================\n";
echo "  摸奖券直播地址生成测试\n";
echo "========================================\n\n";

// 测试参数
$streamName = 'M056_6a32775dd7fae';
$pullDomainCn = 'tencent2.kkcnw.cn'; // 大陆域名
$pullKeyCn = 'your_pull_key_here'; // 需要替换为实际的Key
$expireDays = 30;

echo "📋 测试参数:\n";
echo "  流名称: {$streamName}\n";
echo "  播放域名（大陆）: {$pullDomainCn}\n";
echo "  有效期: {$expireDays} 天\n\n";

// 计算过期时间
$currentTime = time();
$expireTimestamp = $currentTime + ($expireDays * 24 * 60 * 60);
$txTime = strtoupper(base_convert($expireTimestamp, 10, 16));

echo "⏰ 时间信息:\n";
echo "  当前时间: " . date('Y-m-d H:i:s', $currentTime) . "\n";
echo "  过期时间: " . date('Y-m-d H:i:s', $expireTimestamp) . "\n";
echo "  txTime (HEX): {$txTime}\n\n";

// 生成签名
$txSecret = md5($pullKeyCn . $streamName . $txTime);

echo "🔐 鉴权信息:\n";
echo "  txSecret: {$txSecret}\n\n";

// 构建URL
$authParams = http_build_query([
    'txSecret' => $txSecret,
    'txTime' => $txTime
]);

$urls = [
    'flv' => "http://{$pullDomainCn}/live/{$streamName}.flv?{$authParams}",
    'hls' => "http://{$pullDomainCn}/live/{$streamName}.m3u8?{$authParams}",
    'webrtc' => "webrtc://{$pullDomainCn}/live/{$streamName}?{$authParams}",
];

echo "🎬 生成的播放地址:\n\n";

echo "1️⃣ HTTP-FLV（推荐）\n";
echo "   {$urls['flv']}\n\n";

echo "2️⃣ HLS\n";
echo "   {$urls['hls']}\n\n";

echo "3️⃣ WebRTC\n";
echo "   {$urls['webrtc']}\n\n";

echo "========================================\n";
echo "✅ 完成\n\n";

// 如果提供了实际的License信息，生成完整测试URL
if (isset($argv[1]) && isset($argv[2])) {
    $licenseUrl = $argv[1];
    $licenseKey = $argv[2];

    $testUrl = "https://zi-test.5super9.com/lottery-live-player.html?" . http_build_query([
        'url' => $urls['flv'],
        'licenseUrl' => $licenseUrl,
        'licenseKey' => $licenseKey,
        'debug' => '1'
    ]);

    echo "🎬 完整测试地址:\n";
    echo "{$testUrl}\n\n";
}
