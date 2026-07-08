<?php

namespace addons\webman\model;

use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class GamePlatform
 * @property int id 主键
 * @property string $code 游戏平台code
 * @property string name 平台名称
 * @property string config 配置
 * @property float ratio 电子游戏平台比值
 * @property string logo logo
 * @property string picture picture
 * @property string cate_id 游戏类型
 * @property int display_mode 展示模式
 * @property int status 状态
 * @property int has_lobby 是否进入大厅
 * @property int maintenance_week 维护星期
 * @property string maintenance_start_time 维护开始时间
 * @property string maintenance_end_time 维护结束时间
 * @property int maintenance_status 维护功能状态
 * @property string created_at 创建时间
 * @property string updated_at 最后一次修改时间
 * @property string deleted_at 删除时间
 *
 * @package addons\webman\model
 */
class GamePlatform extends Model
{
    use SoftDeletes, HasDateTimeFormatter;

    // 展示模式常量
    const DISPLAY_MODE_LANDSCAPE = 1; // 横版
    const DISPLAY_MODE_PORTRAIT = 2;  // 竖版
    const DISPLAY_MODE_ALL = 3;       // 全部支持

    // 游戏平台类型常量
    const TYPE_BTG = 'BTG';
    const TYPE_WM = 'WM';
    const TYPE_RSG = 'RSG';
    const TYPE_ATG = 'ATG';
    const TYPE_DG = 'DG';
    const TYPE_JDB = 'JDB';
    const TYPE_KY = 'KY';
    const TYPE_YZG = 'YZG';
    const TYPE_SP = 'SP';
    const TYPE_SA = 'SA';
    const TYPE_O8 = 'O8';
    const TYPE_O8_STM = 'STM';
    const TYPE_O8_HS = 'HS';
    const TYPE_TNINE_SLOT = 'TNINE_SLOT';
    const TYPE_KT = 'KT';
    const TYPE_QT = 'QT';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(plugin()->webman->config('database.game_platform_table'));
    }

    /**
     * 模型启动方法
     * 监听保存事件，自动清理缓存
     */
    protected static function boot()
    {
        parent::boot();

        // 监听保存后事件（包括创建和更新）
        static::saved(function ($model) {
            self::clearPlatformCache($model->code);
        });
    }

    /**
     * 清除游戏平台缓存
     * 当平台信息修改后（包括status、maintenance等），清除缓存
     */
    private static function clearPlatformCache(string $platformCode): void
    {
        try {
            $redis = \support\Redis::connection('default')->client();

            // 1. 清理游戏平台缓存（gk_work）
            $platformCacheKey = "game_platform:{$platformCode}";
            $redis->del($platformCacheKey);

            // 2. 如果是ATG/ATG2/ATG3/RSG/DG平台，清理相关限红组缓存
            if (in_array($platformCode, ['ATG', 'ATG2', 'ATG3', 'RSG', 'DG'])) {
                // 获取平台ID
                $platform = self::query()->where('code', $platformCode)->first(['id']);
                if ($platform) {
                    // 清理平台限红组配置缓存
                    $platformLimitConfigKey = "platform_limit_configs:{$platform->id}";
                    $redis->del($platformLimitConfigKey);

                    // 清理所有玩家的限红组配置缓存（使用SCAN避免阻塞）
                    $pattern = "limit_group_config:{$platform->id}:*";
                    $iterator = null;
                    while (false !== ($keys = $redis->scan($iterator, $pattern, 100))) {
                        if (!empty($keys)) {
                            $redis->del(...$keys);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            \support\Log::error('清除游戏平台缓存失败（Model事件）', [
                'platform_code' => $platformCode,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 默认限红组关联
     */
    public function defaultLimitGroup()
    {
        return $this->belongsTo(PlatformLimitGroup::class, 'default_limit_group_id', 'id');
    }
}
