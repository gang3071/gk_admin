<?php

namespace addons\webman\model;

use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * VIP等级反水比例
 * @property int id 主键
 * @property int vip_level_id VIP等级ID
 * @property int platform_id 游戏平台ID
 * @property float cashback_ratio 反水比例
 * @property int status 状态
 * @property string created_at 创建时间
 * @property string updated_at 更新时间
 *
 * @property VipLevel vipLevel VIP等级
 * @property GamePlatform gamePlatform 游戏平台
 * @package addons\webman\model
 */
class VipLevelCashback extends Model
{
    use HasDateTimeFormatter;

    protected $table = 'vip_level_cashback';

    protected $fillable = [
        'vip_level_id',
        'platform_id',
        'cashback_ratio',
        'status',
    ];

    /**
     * VIP等级
     * @return BelongsTo
     */
    public function vipLevel(): BelongsTo
    {
        return $this->belongsTo(VipLevel::class, 'vip_level_id');
    }

    /**
     * 游戏平台
     * @return BelongsTo
     */
    public function gamePlatform(): BelongsTo
    {
        return $this->belongsTo(GamePlatform::class, 'platform_id');
    }

    // =========================================================================
    // DB 查询方法
    // =========================================================================

    /**
     * 获取指定VIP等级和平台的反水比例
     * @param int $vipLevelId
     * @param int $platformId
     * @return float
     */
    public static function getCashbackRatio(int $vipLevelId, int $platformId): float
    {
        $cashback = static::query()
            ->where('vip_level_id', $vipLevelId)
            ->where('platform_id', $platformId)
            ->where('status', 1)
            ->first();

        return static::extractCashbackRatio($cashback);
    }

    // =========================================================================
    // 纯函数方法（不依赖数据库，可单元测试）
    // =========================================================================

    /**
     * 从查询结果提取反水比例
     *
     * @param VipLevelCashback|null $cashback
     * @return float
     */
    public static function extractCashbackRatio(?VipLevelCashback $cashback): float
    {
        return $cashback ? $cashback->cashback_ratio : 0;
    }

    /**
     * 计算反水金额
     *
     * @param float $bet 下注金额
     * @param float $cashbackRatio 反水比例
     * @return float
     */
    public static function calculateCashbackAmount(float $bet, float $cashbackRatio): float
    {
        if ($bet <= 0 || $cashbackRatio <= 0) {
            return 0;
        }
        return round($bet * $cashbackRatio / 100, 4);
    }

    /**
     * 格式化反水数据用于存储
     *
     * @param float $cashbackRatio 反水比例
     * @param float $cashbackAmount 反水金额
     * @return array [cashback_ratio, cashback_amount] 存储值（0值转为null）
     */
    public static function formatForStorage(float $cashbackRatio, float $cashbackAmount): array
    {
        return [
            'cashback_ratio' => $cashbackRatio > 0 ? $cashbackRatio : null,
            'cashback_amount' => $cashbackAmount > 0 ? $cashbackAmount : null,
        ];
    }
}
