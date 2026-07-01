<?php
// 测试登录 Token 缓存是否正确设置过期时间

require_once __DIR__ . '/vendor/autoload.php';

echo "=== 登录 Token 缓存测试 ===" . PHP_EOL . PHP_EOL;

// 1. 测试 Support\Cache (Login.php 中使用的)
echo "【1】测试 Support\\Cache (Token驱动使用)：" . PHP_EOL;

try {
    $testToken = 'test_token_' . time();
    $testExpire = 15 * 24 * 3600; // 15天

    echo "  - 测试 Token: {$testToken}" . PHP_EOL;
    echo "  - 过期时间: {$testExpire} 秒 (15天)" . PHP_EOL;

    // 使用 Support\Cache (注意大写 S)
    $cacheKey = md5($testToken);
    $result = Support\Cache::set($cacheKey, $testToken, $testExpire);

    echo "  - 设置结果: " . ($result ? '成功' : '失败') . PHP_EOL;

    // 检查是否存在
    $exists = Support\Cache::has($cacheKey);
    echo "  - 存在检查: " . ($exists ? '存在' : '不存在') . PHP_EOL;

    // 获取值
    $value = Support\Cache::get($cacheKey);
    echo "  - 获取值: " . ($value === $testToken ? '正确' : '错误') . PHP_EOL;

} catch (Exception $e) {
    echo "  ❌ 错误: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;

// 2. 测试 support\Cache (Redis缓存)
echo "【2】测试 support\\Cache (Redis缓存)：" . PHP_EOL;

try {
    $testKey = 'test_redis_expire_' . time();
    $testValue = 'test_value';
    $testExpire = 15 * 24 * 3600;

    echo "  - 测试键: {$testKey}" . PHP_EOL;
    echo "  - 过期时间: {$testExpire} 秒 (15天)" . PHP_EOL;

    $result = support\Cache::set($testKey, $testValue, $testExpire);
    echo "  - 设置结果: " . ($result ? '成功' : '失败') . PHP_EOL;

    // 获取 Redis 客户端检查 TTL
    $redis = support\Redis::connection()->client();
    $ttl = $redis->ttl($testKey);

    echo "  - Redis TTL: " . $ttl . " 秒" . PHP_EOL;
    echo "  - 等于: " . round($ttl / 86400, 2) . " 天" . PHP_EOL;

    if ($ttl > 0 && $ttl < $testExpire + 10 && $ttl > $testExpire - 10) {
        echo "  ✅ TTL 设置正确！" . PHP_EOL;
    } else {
        echo "  ❌ TTL 不正确！预期约 {$testExpire} 秒，实际 {$ttl} 秒" . PHP_EOL;
    }

    // 清理测试数据
    support\Cache::delete($testKey);
    Support\Cache::delete(md5('test_token_' . time()));

} catch (Exception $e) {
    echo "  ❌ 错误: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;

// 3. 检查现有的登录 Token
echo "【3】检查现有登录 Token：" . PHP_EOL;

try {
    $redis = support\Redis::connection()->client();

    // 扫描所有 Token 相关的键
    $iterator = null;
    $tokenKeys = [];
    while (false !== ($keys = $redis->scan($iterator, 'last_auth_token_*', 100))) {
        if (is_array($keys)) {
            $tokenKeys = array_merge($tokenKeys, $keys);
        }
        if ($iterator === 0) {
            break;
        }
    }

    echo "  - 找到 " . count($tokenKeys) . " 个 Token 键" . PHP_EOL;

    if (count($tokenKeys) > 0) {
        echo "  - 检查前 3 个 Token 的 TTL：" . PHP_EOL;
        $count = 0;
        foreach ($tokenKeys as $key) {
            if ($count++ >= 3) break;
            $ttl = $redis->ttl($key);
            $days = round($ttl / 86400, 2);
            echo "    * {$key}: TTL = {$ttl} 秒 ({$days} 天)" . PHP_EOL;
        }
    } else {
        echo "  ℹ️  当前没有登录 Token" . PHP_EOL;
    }

} catch (Exception $e) {
    echo "  ❌ 错误: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;

// 4. 模拟完整的登录流程
echo "【4】模拟登录 Token 设置流程：" . PHP_EOL;

try {
    // 模拟 Login.php 的代码
    $rememberMe = true;
    $tokenExpire = $rememberMe ? 15 * 24 * 3600 : null;

    $testToken = openssl_encrypt(json_encode([
        'id' => 1,
        'username' => 'test',
        'token_expire' => time() + $tokenExpire,
        'token_time' => time()
    ]), 'AES-128-ECB', 'gkAdminTokenKey');

    echo "  - 生成测试 Token: " . substr($testToken, 0, 50) . "..." . PHP_EOL;
    echo "  - 记住我: " . ($rememberMe ? '是' : '否') . PHP_EOL;
    echo "  - 过期时间: " . $tokenExpire . " 秒 (15天)" . PHP_EOL;

    // 使用 Token 驱动保存
    $driver = new \addons\webman\token\driver\Cache();
    $result = $driver->set($testToken, $tokenExpire);

    echo "  - 保存结果: " . ($result ? '成功' : '失败') . PHP_EOL;

    // 检查是否存在
    $exists = $driver->has($testToken);
    echo "  - Token 存在: " . ($exists ? '是' : '否') . PHP_EOL;

    // 检查 Redis TTL
    $redis = support\Redis::connection()->client();
    $ttl = $redis->ttl(md5($testToken));
    echo "  - Redis TTL: " . $ttl . " 秒" . PHP_EOL;
    echo "  - 等于: " . round($ttl / 86400, 2) . " 天" . PHP_EOL;

    if ($ttl > 1290000 && $ttl <= 1296000) {
        echo "  ✅ 登录 Token 过期时间设置正确！" . PHP_EOL;
    } else {
        echo "  ❌ 登录 Token 过期时间不正确！" . PHP_EOL;
        echo "     预期: 约 1296000 秒 (15天)" . PHP_EOL;
        echo "     实际: {$ttl} 秒 (" . round($ttl / 86400, 2) . " 天)" . PHP_EOL;
    }

    // 清理测试数据
    $driver->delete($testToken);

} catch (Exception $e) {
    echo "  ❌ 错误: " . $e->getMessage() . PHP_EOL;
    echo "  堆栈: " . $e->getTraceAsString() . PHP_EOL;
}

echo PHP_EOL . "测试完成！" . PHP_EOL;
