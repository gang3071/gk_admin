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
}
