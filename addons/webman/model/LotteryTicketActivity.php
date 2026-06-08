<?php

namespace addons\webman\model;

use addons\webman\traits\DataPermissions;
use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class LotteryTicketActivity
 *
 * @property int $id 主键ID
 * @property int $department_id 所属渠道部门ID
 * @property string $name 活动名称
 * @property string $description 活动说明
 * @property string $start_time 活动开始时间
 * @property string $end_time 活动结束时间
 * @property int $status 活动状态(0:未开始,1:进行中,2:已结束,3:已关闭)
 * @property int $total_tickets 总发放摸奖券数量
 * @property int $used_tickets 已使用摸奖券数量
 * @property string $prize_config 奖品配置(JSON格式)
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 * @property string $deleted_at 删除时间
 *
 * @property Channel $channel
 * @property LotteryTicket[] $tickets
 * @property LotteryTicketRecord[] $records
 * @property LotteryTicketPrizeLevel[] $prizeLevels
 *
 * @package addons\webman\model
 */
class LotteryTicketActivity extends Model
{
    use HasDateTimeFormatter, SoftDeletes, DataPermissions;

    // 状态常量
    const STATUS_NOT_STARTED = 0; // 未开始
    const STATUS_ONGOING = 1;     // 进行中
    const STATUS_ENDED = 2;       // 已结束
    const STATUS_CLOSED = 3;      // 已关闭

    protected $table = 'lottery_ticket_activity';

    protected $guarded = [];

    protected $casts = [
        'prize_config' => 'array',
    ];

    /**
     * 数据权限配置
     * @var array
     */
    protected $dataAuth = ['department_id' => 'department_id'];

    /**
     * 获取状态文本
     * @param int $status
     * @return string
     */
    public static function getStatusText(int $status): string
    {
        $statusMap = [
            self::STATUS_NOT_STARTED => admin_trans('lottery_ticket.status.not_started'),
            self::STATUS_ONGOING => admin_trans('lottery_ticket.status.ongoing'),
            self::STATUS_ENDED => admin_trans('lottery_ticket.status.ended'),
            self::STATUS_CLOSED => admin_trans('lottery_ticket.status.closed'),
        ];

        return $statusMap[$status] ?? admin_trans('lottery_ticket.status.unknown');
    }

    /**
     * 所属渠道
     * @return BelongsTo
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'department_id', 'department_id');
    }

    /**
     * 摸奖券
     * @return HasMany
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(LotteryTicket::class, 'activity_id');
    }

    /**
     * 中奖记录
     * @return HasMany
     */
    public function records(): HasMany
    {
        return $this->hasMany(LotteryTicketRecord::class, 'activity_id');
    }

    /**
     * 中奖等级配置
     * @return HasMany
     */
    public function prizeLevels(): HasMany
    {
        return $this->hasMany(LotteryTicketPrizeLevel::class, 'activity_id')
            ->where('status', LotteryTicketPrizeLevel::STATUS_ENABLED)
            ->orderBy('sort_order')
            ->orderBy('level_rank');
    }

    /**
     * VIP等级配置
     * @return HasMany
     */
    public function vipConfigs(): HasMany
    {
        return $this->hasMany(LotteryTicketVipConfig::class, 'activity_id')
            ->where('status', LotteryTicketVipConfig::STATUS_ENABLED)
            ->orderBy('vip_level_id');
    }

    /**
     * 获取使用率
     * @return float
     */
    public function getUsageRateAttribute(): float
    {
        if ($this->total_tickets == 0) {
            return 0;
        }

        return round(($this->used_tickets / $this->total_tickets) * 100, 2);
    }
}
