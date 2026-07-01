<?php
/**
 * 验证全局作用域修复效果
 *
 * 功能：检查模型实例化多次后，全局作用域是否仍然只有一个
 */

// 设置环境（模拟 Webman 环境）
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/support/bootstrap.php';

echo "========================================\n";
echo "全局作用域修复验证\n";
echo "========================================\n\n";

// 测试：实例化同一个模型10次
echo "📋 测试1：实例化 Player 模型 10 次\n";
for ($i = 1; $i <= 10; $i++) {
    new \addons\webman\model\Player();
    echo "  - 第 {$i} 次实例化完成\n";
}

echo "\n📋 测试2：检查全局作用域数量\n";

// 使用反射检查全局作用域数量
try {
    $reflection = new \ReflectionClass(\addons\webman\model\Player::class);

    // 获取 globalScopes 静态属性
    $properties = $reflection->getStaticProperties();

    if (isset($properties['globalScopes']) && isset($properties['globalScopes']['dataAuth'])) {
        $scopeCount = is_array($properties['globalScopes']['dataAuth'])
            ? count($properties['globalScopes']['dataAuth'])
            : 1;

        echo "  - 全局作用域 'dataAuth' 数量: {$scopeCount}\n";

        if ($scopeCount === 1) {
            echo "  ✅ 修复成功！只有1个全局作用域（正确）\n";
        } else {
            echo "  ❌ 修复失败！有 {$scopeCount} 个全局作用域（应该只有1个）\n";
            echo "  ⚠️  这意味着每次实例化都重复注册了作用域\n";
        }
    } else {
        echo "  ℹ️  未检测到 'dataAuth' 全局作用域（可能是权限检查跳过）\n";
        echo "  提示：请使用真实的 Admin 登录状态测试\n";
    }
} catch (\Exception $e) {
    echo "  ❌ 检查失败：{$e->getMessage()}\n";
}

echo "\n📋 测试3：多个模型测试\n";
$models = [
    'Player',
    'PlayerGameLog',
    'PlayerRechargeRecord',
    'Machine',
];

foreach ($models as $modelName) {
    $class = "\\addons\\webman\\model\\{$modelName}";
    if (class_exists($class)) {
        // 实例化5次
        for ($i = 0; $i < 5; $i++) {
            new $class();
        }
        echo "  ✅ {$modelName} - 实例化5次完成\n";
    } else {
        echo "  ⚠️  {$modelName} - 类不存在\n";
    }
}

echo "\n📋 测试4：内存使用情况\n";
$memUsage = memory_get_usage(true);
$memPeak = memory_get_peak_usage(true);
echo "  - 当前内存: " . round($memUsage / 1024 / 1024, 2) . " MB\n";
echo "  - 峰值内存: " . round($memPeak / 1024 / 1024, 2) . " MB\n";

echo "\n========================================\n";
echo "验证完成\n";
echo "========================================\n";
echo "\n提示：\n";
echo "1. 如果显示 '修复成功'，说明全局作用域不再重复注册\n";
echo "2. 如果显示 '修复失败'，请检查 DataPermissions.php 的修改\n";
echo "3. 重启服务后监控实际运行效果：\n";
echo "   php start.php stop\n";
echo "   php start.php start -d\n";
echo "   watch -n 300 'php start.php status | grep webman'\n";
echo "\n";
