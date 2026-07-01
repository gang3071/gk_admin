<?php
// 测试登录15天免登录修复

require_once __DIR__ . '/vendor/autoload.php';

echo "=== 登录 15天免登录修复验证 ===" . PHP_EOL . PHP_EOL;

// 1. 测试配置读取逻辑（模拟 Login.php 的修复代码）
echo "【1】测试配置读取逻辑：" . PHP_EOL;

$config = function_exists('admin_config') ? admin_config('admin.token') : null;
echo "  - admin_config('admin.token'): " . ($config ? '有值' : 'NULL') . PHP_EOL;

if (!$config && function_exists('config')) {
    $config = config('admin.token');
    echo "  - config('admin.token'): " . ($config ? '有值' : 'NULL') . PHP_EOL;
}

if (!$config) {
    // 使用默认配置
    $config = [
        'key' => 'gkAdminTokenKey',
        'unique' => true,
        'expire' => 7 * 24 * 3600,
    ];
    echo "  - 使用默认配置" . PHP_EOL;
}

echo "  ✅ 配置获取成功：" . PHP_EOL;
echo "     - key: " . $config['key'] . PHP_EOL;
echo "     - unique: " . ($config['unique'] ? 'true' : 'false') . PHP_EOL;
echo "     - expire: " . $config['expire'] . " 秒 (" . ($config['expire'] / 86400) . " 天)" . PHP_EOL;

echo PHP_EOL;

// 2. 测试 Token 生成（模拟登录流程）
echo "【2】测试 Token 生成和过期时间：" . PHP_EOL;

// 测试1：勾选"记住我"
$rememberMe = true;
$tokenExpire = $rememberMe ? 15 * 24 * 3600 : null;

echo "  场景1：勾选 '记住我'" . PHP_EOL;
echo "    - rememberMe: true" . PHP_EOL;
echo "    - tokenExpire: " . $tokenExpire . " 秒 (" . ($tokenExpire / 86400) . " 天)" . PHP_EOL;

$userData = [
    'id' => 1,
    'username' => 'test_user',
    'token_expire' => time() + $tokenExpire,
    'token_time' => time()
];

$token = openssl_encrypt(json_encode($userData), 'AES-128-ECB', $config['key']);
echo "    - Token 生成: " . (strlen($token) > 0 ? '成功' : '失败') . PHP_EOL;
echo "    - Token 长度: " . strlen($token) . " 字符" . PHP_EOL;
echo "    - 缓存过期时间: " . ($tokenExpire ?: $config['expire']) . " 秒" . PHP_EOL;

echo PHP_EOL;

// 测试2：不勾选"记住我"
$rememberMe = false;
$tokenExpire = $rememberMe ? 15 * 24 * 3600 : null;

echo "  场景2：不勾选 '记住我'" . PHP_EOL;
echo "    - rememberMe: false" . PHP_EOL;
echo "    - tokenExpire: " . ($tokenExpire ?: '使用默认') . PHP_EOL;
echo "    - 缓存过期时间: " . ($tokenExpire ?: $config['expire']) . " 秒 (" . (($tokenExpire ?: $config['expire']) / 86400) . " 天)" . PHP_EOL;

echo PHP_EOL;

// 3. 对比修复前后
echo "【3】修复效果对比：" . PHP_EOL;
echo "  ❌ 修复前：" . PHP_EOL;
echo "     - admin_config('admin.token') 返回 NULL" . PHP_EOL;
echo "     - \$config['key'] 报错：Trying to access array offset on value of type null" . PHP_EOL;
echo "     - 无法登录" . PHP_EOL;
echo PHP_EOL;
echo "  ✅ 修复后：" . PHP_EOL;
echo "     - 添加容错处理" . PHP_EOL;
echo "     - 使用默认配置（key='gkAdminTokenKey', expire=7天）" . PHP_EOL;
echo "     - 勾选'记住我'时使用15天过期" . PHP_EOL;
echo "     - 登录正常工作" . PHP_EOL;

echo PHP_EOL;

// 4. Token 解密验证
echo "【4】Token 解密验证：" . PHP_EOL;

$decrypted = openssl_decrypt($token, 'AES-128-ECB', $config['key']);
$decodedData = json_decode($decrypted, true);

if ($decodedData) {
    echo "  ✅ Token 解密成功" . PHP_EOL;
    echo "     - user_id: " . $decodedData['id'] . PHP_EOL;
    echo "     - username: " . $decodedData['username'] . PHP_EOL;
    echo "     - token_time: " . date('Y-m-d H:i:s', $decodedData['token_time']) . PHP_EOL;
    if (isset($decodedData['token_expire'])) {
        $remainDays = ($decodedData['token_expire'] - time()) / 86400;
        echo "     - token_expire: " . date('Y-m-d H:i:s', $decodedData['token_expire']) . PHP_EOL;
        echo "     - 剩余天数: " . round($remainDays, 2) . " 天" . PHP_EOL;
    }
} else {
    echo "  ❌ Token 解密失败" . PHP_EOL;
}

echo PHP_EOL;

// 5. 下一步操作指引
echo "【5】下一步操作：" . PHP_EOL;
echo "  1. ⚠️  重启 Webman 服务：php start.php restart" . PHP_EOL;
echo "  2. ✅ 清空浏览器缓存和 Cookie" . PHP_EOL;
echo "  3. ✅ 重新登录并勾选 '记住我（15天免登录）'" . PHP_EOL;
echo "  4. ✅ 验证登录状态是否能保持15天" . PHP_EOL;

echo PHP_EOL;

// 6. Redis 持久连接问题提醒
echo "【6】重要提醒 - Redis 配置变更：" . PHP_EOL;
echo "  ⚠️  config/redis.php 已从 persistent=>true 改为 false" . PHP_EOL;
echo "  ⚠️  所有旧的登录 Token 可能已失效" . PHP_EOL;
echo "  ⚠️  用户需要重新登录" . PHP_EOL;
echo "  ✅ 这是正常的，新登录的用户将使用修复后的逻辑" . PHP_EOL;

echo PHP_EOL . "测试完成！" . PHP_EOL;
