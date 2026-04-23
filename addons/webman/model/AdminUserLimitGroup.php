<?php

namespace addons\webman\model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 店家限红分配模型
 *
 * @property int $id
 * @property int $admin_user_id
 * @property int $limit_group_id
 * @property int $platform_id
 * @property string $platform_code
 * @property int $assigned_by
 * @property string $assigned_at
 * @property string $remark
 * @property int $status
 */
class AdminUserLimitGroup extends Model
{
    use SoftDeletes;

    protected $table = 'admin_user_limit_group';

    protected $fillable = [
        'admin_user_id',
        'limit_group_id',
        'platform_id',
        'platform_code',
        'assigned_by',
        'assigned_at',
        'remark',
        'status',
    ];

    protected $casts = [
        'admin_user_id' => 'integer',
        'limit_group_id' => 'integer',
        'platform_id' => 'integer',
        'assigned_by' => 'integer',
        'status' => 'integer',
        'assigned_at' => 'datetime',
    ];

    /**
     * 所属店家
     */
    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }

    /**
     * 所属限红组
     */
    public function limitGroup(): BelongsTo
    {
        return $this->belongsTo(PlatformLimitGroup::class, 'limit_group_id');
    }

    /**
     * 游戏平台
     */
    public function gamePlatform(): BelongsTo
    {
        return $this->belongsTo(GamePlatform::class, 'platform_id');
    }

    /**
     * 分配人
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'assigned_by');
    }

    /**
     * 模型启动事件
     */
    protected static function boot()
    {
        parent::boot();

        // 保存/更新后清理缓存
        static::saved(function ($model) {
            self::clearLimitGroupCache($model);
        });

        // 删除后清理缓存
        static::deleted(function ($model) {
            self::clearLimitGroupCache($model);
        });
    }

    /**
     * 清理限红组相关缓存
     * @param AdminUserLimitGroup $model
     */
    private static function clearLimitGroupCache(AdminUserLimitGroup $model)
    {
        try {
            $redis = \support\Redis::connection('default')->client();

            $adminUserId = $model->admin_user_id;
            $platformId = $model->platform_id;
            $platformCode = $model->platform_code;

            if (!$adminUserId || !$platformId) {
                return;
            }

            // 1. 清理该店家下所有玩家的限红组配置缓存（使用SCAN避免阻塞）
            $pattern = "limit_group_config:{$platformId}:*:{$adminUserId}";
            $cursor = null;

            while (false !== ($keys = $redis->scan($cursor, $pattern, 100))) {
                if (!empty($keys)) {
                    $redis->del(...$keys);
                }
            }

            // 2. 清理平台限红组配置缓存（ATG/RSG/DG等平台）
            if (in_array(strtoupper($platformCode), ['ATG', 'RSG', 'DG'])) {
                $platformLimitConfigKey = "platform_limit_configs:{$platformId}";
                $redis->del($platformLimitConfigKey);
            }

            \support\Log::info('店家限红组缓存已清理', [
                'admin_user_id' => $adminUserId,
                'platform_id' => $platformId,
                'platform_code' => $platformCode,
                'limit_group_id' => $model->limit_group_id,
            ]);
        } catch (\Exception $e) {
            \support\Log::error('清理店家限红组缓存失败', [
                'error' => $e->getMessage(),
                'admin_user_id' => $adminUserId ?? null,
                'platform_id' => $platformId ?? null,
            ]);
        }
    }
}
