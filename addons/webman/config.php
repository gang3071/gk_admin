<?php
// 精简版配置文件 - 延迟加载大型配置

// 缓存键名
$cacheKey = 'addon_webman_full_config';
$cacheTime = 300; // 5分钟缓存

// 尝试从缓存获取
if (function_exists('cache_get')) {
    $config = cache_get($cacheKey);
    if ($config !== null) {
        return $config;
    }
}

// 如果缓存不存在，从分拆的文件加载
$configFiles = [
    __DIR__ . '/config/core.php',
    __DIR__ . '/config/database.php',
    __DIR__ . '/config/ui.php',
    __DIR__ . '/config/upload.php',
];

$config = [];
foreach ($configFiles as $file) {
    if (file_exists($file)) {
        $config = array_merge($config, include $file);
    }
}

// 保存到缓存
if (function_exists('cache_set')) {
    cache_set($cacheKey, $config, $cacheTime);
}

return $config;