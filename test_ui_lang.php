<?php
// 测试UI语言配置
require_once __DIR__ . '/vendor/autoload.php';

echo "=== UI语言配置测试 ===" . PHP_EOL . PHP_EOL;

// 1. 测试plugin()->webman->config('ui.lang')
echo "【1】plugin()->webman->config('ui.lang')：" . PHP_EOL;
try {
    $uiLang = plugin()->webman->config('ui.lang');
    if ($uiLang) {
        echo "  - default: " . ($uiLang['default'] ?? 'NULL') . PHP_EOL;
        echo "  - list: " . json_encode($uiLang['list'] ?? [], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        echo "  ❌ 返回 NULL" . PHP_EOL;
    }
} catch (\Exception $e) {
    echo "  ❌ 错误: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;

// 2. 测试admin_config('ui.lang')
echo "【2】admin_config('ui.lang')：" . PHP_EOL;
try {
    $adminLang = admin_config('ui.lang');
    if ($adminLang) {
        echo "  - default: " . ($adminLang['default'] ?? 'NULL') . PHP_EOL;
        echo "  - list: " . json_encode($adminLang['list'] ?? [], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        echo "  ❌ 返回 NULL" . PHP_EOL;
    }
} catch (\Exception $e) {
    echo "  ❌ 错误: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;

// 3. 测试config('app.locale')
echo "【3】config('app.locale')：" . PHP_EOL;
try {
    $appLocale = config('app.locale');
    echo "  - " . ($appLocale ?: 'NULL') . PHP_EOL;
} catch (\Exception $e) {
    echo "  ❌ 错误: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;

// 4. 测试Container translator locale
echo "【4】Container translator locale：" . PHP_EOL;
try {
    $container = \ExAdmin\ui\support\Container::getInstance();
    if (isset($container->translator)) {
        $locale = $container->translator->getLocale();
        echo "  - " . $locale . PHP_EOL;
    } else {
        echo "  ❌ translator未初始化" . PHP_EOL;
    }
} catch (\Exception $e) {
    echo "  ❌ 错误: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "测试完成！" . PHP_EOL;
