<?php

namespace addons\webman\model;

use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 玩家VIP周期记录
 * @property int id 主键
 * @property int player_id 玩家ID
 * @property int vip_level_id VIP等级ID
 * @property string period_type 周期类型
 * @property float start_bet_amount 周期开始时的总打码量
 * @property string started_at 周期开始时间
 * @property int status 状态
 * @property string created_at 创建时间
 * @property string updated_at 更新时间
 *
 * @property Player player 玩家
 * @property VipLevel vipLevel VIP等级
 * @package addons\webman\model
 */
class PlayerVipPeriod extends Model
{
    use HasDateTimeFormatter;

    protected $table = 'player_vip_period';

    const PERIOD_TYPE_UPGRADE = 'upgrade';  // 升级周期
    const PERIOD_TYPE_RETAIN = 'retain';    // 保级周期

    const STATUS_EXPIRED = 0;   // 已过期
    const STATUS_ACTIVE = 1;    // 进行中
    const STATUS_COMPLETED = 2; // 已完成

    protected $fillable = [
        'player_id',
        'vip_level_id',
        'period_type',
        'start_bet_amount',
        'started_at',
        'status',
    ];

    /**
     * 玩家
     * @return BelongsTo
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(plugin()->webman->config('database.player_model'), 'player_id');
    }

    /**
     * VIP等级
     * @return BelongsTo
     */
    public function vipLevel(): BelongsTo
    {
        return $this->belongsTo(VipLevel::class, 'vip_level_id');
    }

    /**
     * 获取周期内打码量
     * @param float $currentTotalBetAmount 当前总打码量
     * @return float
     */
    public function getPeriodBetAmount(float $currentTotalBetAmount): float
    {
        return max(0, $currentTotalBetAmount - $this->start_bet_amount);
    }
}
