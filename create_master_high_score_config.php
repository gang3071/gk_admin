<?php
/**
 * 为主配置（department_id = 0）创建高分广播阈值配置
 * 使用方法：php create_master_high_score_config.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use addons\webman\model\SystemSetting;
use support\Db;

echo "正在检查主配置的高分广播阈值...\n";

try {
    // 检查是否已存在
    $existing = SystemSetting::where('department_id', 0)
        ->where('feature', 'high_score_broadcast_threshold')
        ->first();

    if ($existing) {
        echo "✅ 配置已存在\n";
        echo "   部门ID: {$existing->department_id}\n";
        echo "   阈值: {$existing->num}\n";
        echo "   状态: " . ($existing->status ? '启用' : '禁用') . "\n";
        echo "\n是否要更新配置？(y/n): ";

        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);

        if (trim($line) !== 'y') {
            echo "已取消操作\n";
            exit(0);
        }

        // 更新配置
        echo "\n请输入新的阈值（当前: {$existing->num}）: ";
        $handle = fopen("php://stdin", "r");
        $threshold = trim(fgets($handle));
        fclose($handle);

        echo "是否启用？(y/n，当前: " . ($existing->status ? '启用' : '禁用') . "): ";
        $handle = fopen("php://stdin", "r");
        $enableInput = trim(fgets($handle));
        fclose($handle);

        $existing->num = $threshold ?: $existing->num;
        $existing->status = ($enableInput === 'y') ? 1 : 0;
        $existing->save();

        echo "\n✅ 配置已更新\n";
        echo "   阈值: {$existing->num}\n";
        echo "   状态: " . ($existing->status ? '启用' : '禁用') . "\n";
    } else {
        echo "❌ 配置不存在，正在创建...\n";

        // 创建新配置
        $setting = new SystemSetting();
        $setting->department_id = 0;
        $setting->feature = 'high_score_broadcast_threshold';
        $setting->num = 5000; // 默认阈值 5000 分
        $setting->content = '';
        $setting->status = 1; // 默认启用
        $setting->save();

        echo "✅ 配置创建成功\n";
        echo "   部门ID: 0（主配置）\n";
        echo "   阈值: 5000\n";
        echo "   状态: 启用\n";
        echo "\n提示：你可以在后台\"系统设置\"页面修改阈值\n";
    }

    // 清除缓存
    echo "\n正在清除缓存...\n";
    $cacheKey = 'setting-high_score_broadcast_threshold-0';
    \support\Cache::delete($cacheKey);
    echo "✅ 缓存已清除\n";

    echo "\n🎉 操作完成！\n";
    echo "\n使用说明：\n";
    echo "1. 当玩家在游戏中赢分 >= {$setting->num ?? 5000} 时，会触发全频道广播\n";
    echo "2. 可在后台\"系统设置\" -> \"主配置\"标签中修改阈值\n";
    echo "3. 设置为 0 可禁用高分广播功能\n";

} catch (Exception $e) {
    echo "\n❌ 错误：" . $e->getMessage() . "\n";
    echo "详细信息：\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
