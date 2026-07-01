<?php
/**
 * 测试 Eloquent 全局作用域是否会被覆盖还是累积
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "========================================\n";
echo "测试全局作用域注册机制\n";
echo "========================================\n\n";

class TestScopeModel extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'test';
    public static $executionCount = 0;
}

// 注册第一个作用域
echo "📋 步骤1：注册第一个 'test' 作用域\n";
TestScopeModel::addGlobalScope('test', function ($builder) {
    TestScopeModel::$executionCount++;
    echo "  - 作用域1执行（执行次数：" . TestScopeModel::$executionCount . "）\n";
});

// 再次注册同名作用域
echo "\n📋 步骤2：再次注册 'test' 作用域（同名）\n";
TestScopeModel::addGlobalScope('test', function ($builder) {
    TestScopeModel::$executionCount++;
    echo "  - 作用域2执行（执行次数：" . TestScopeModel::$executionCount . "）\n";
});

// 第三次注册
echo "\n📋 步骤3：第三次注册 'test' 作用域（同名）\n";
TestScopeModel::addGlobalScope('test', function ($builder) {
    TestScopeModel::$executionCount++;
    echo "  - 作用域3执行（执行次数：" . TestScopeModel::$executionCount . "）\n";
});

echo "\n📋 步骤4：模拟查询（触发作用域执行）\n";
try {
    // 创建查询构建器（不实际执行查询，只看作用域是否执行）
    $query = TestScopeModel::query();

    // 获取全局作用域
    $model = new TestScopeModel();
    $reflection = new \ReflectionClass($model);

    // 尝试获取静态属性 globalScopes
    try {
        $property = $reflection->getProperty('globalScopes');
        $property->setAccessible(true);
        $scopes = $property->getValue();

        $className = TestScopeModel::class;
        if (isset($scopes[$className]['test'])) {
            $testScope = $scopes[$className]['test'];

            if (is_array($testScope)) {
                echo "  ⚠️  作用域被存储为数组，有 " . count($testScope) . " 个\n";
                echo "  ❌ 结论：多次注册同名作用域会累积（这会导致内存泄漏！）\n";
            } else {
                echo "  ✅ 作用域被覆盖，只有 1 个\n";
                echo "  ✅ 结论：多次注册同名作用域会覆盖旧的，不会累积\n";
            }
        }
    } catch (\Exception $e) {
        echo "  ℹ️  无法直接访问 globalScopes 属性\n";
    }

} catch (\Exception $e) {
    echo "  ❌ 测试失败：{$e->getMessage()}\n";
}

echo "\n========================================\n";
echo "结论\n";
echo "========================================\n";
echo "\n如果作用域被覆盖（只有1个）：\n";
echo "  - 修复的必要性：低（不会累积多个闭包对象）\n";
echo "  - 但仍有好处：避免重复调用 addGlobalScope() 的开销\n";
echo "\n如果作用域被累积（多个）：\n";
echo "  - 修复的必要性：高（会导致严重内存泄漏）\n";
echo "  - 修复效果：完全阻止闭包对象累积\n";
echo "\n";
