<?php

namespace addons\webman\model;

use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 摸奖券VIP等级配置模型
 *
 * @property int $id 主键ID
 * @property int $activity_id 活动ID
 * @property int $vip_level_id VIP等级ID
 * @property float $bet_amount_required 基础打码量（达到此打码量获得摸奖券）
 * @property int $ticket_count 摸奖券数量（每达到基础打码量获得的券数）
 * @property int $status 状态(0:禁用,1:启用)
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 *
 * @property LotteryTicketActivity $activity
 * @property VipLevel $vipLevel
 *
 * @package addons\webman\model
 */
class LotteryTicketVipConfig extends Model
{
    use HasDateTimeFormatter;

    // 状态常量
    const STATUS_DISABLED = 0;  // 禁用
    const STATUS_ENABLED = 1;   // 启用

    protected $table = 'lottery_ticket_vip_config';

    protected $guarded = [];

    protected $casts = [
        'bet_amount_required' => 'float',
        'ticket_count' => 'integer',
    ];

    /**
     * 所属活动
     * @return BelongsTo
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(LotteryTicketActivity::class, 'activity_id');
    }

    /**
     * 所属VIP等级
     * @return BelongsTo
     */
    public function vipLevel(): BelongsTo
    {
        return $this->belongsTo(VipLevel::class, 'vip_level_id');
    }
}
