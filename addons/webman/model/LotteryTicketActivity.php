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
    const STATUS_PREHEATING = 4;  // 预热期
    const STATUS_BETTING = 5;     // 打码中
    const STATUS_DRAWING = 6;     // 开奖中
    const STATUS_DRAWN = 7;       // 已开奖待发放 ⭐ 新增

    // 直播状态常量
    const LIVE_STATUS_NOT_STARTED = 0; // 未开播
    const LIVE_STATUS_ONGOING = 1;      // 直播中
    const LIVE_STATUS_ENDED = 2;        // 已结束

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
            self::STATUS_PREHEATING => admin_trans('lottery_ticket.status.preheating'),
            self::STATUS_BETTING => admin_trans('lottery_ticket.status.betting'),
            self::STATUS_DRAWING => admin_trans('lottery_ticket.status.drawing'),
            self::STATUS_DRAWN => admin_trans('lottery_ticket.status.drawn'), // 已开奖待发放 ⭐
        ];

        return $statusMap[$status] ?? admin_trans('lottery_ticket.status.unknown');
    }

    /**
     * 获取直播状态文本
     * @param int $status
     * @return string
     */
    public static function getLiveStatusText(int $status): string
    {
        $statusMap = [
            self::LIVE_STATUS_NOT_STARTED => admin_trans('lottery_ticket.live_status.not_started'),
            self::LIVE_STATUS_ONGOING => admin_trans('lottery_ticket.live_status.ongoing'),
            self::LIVE_STATUS_ENDED => admin_trans('lottery_ticket.live_status.ended'),
        ];

        return $statusMap[$status] ?? admin_trans('lottery_ticket.live_status.unknown');
    }

    /**
     * 记录状态变更历史
     * @param int $newStatus
     * @param string $reason
     * @return void
     */
    public function recordStatusChange(int $newStatus, string $reason = ''): void
    {
        $history = $this->status_history ? json_decode($this->status_history, true) : [];

        $history[] = [
            'from_status' => $this->status,
            'to_status' => $newStatus,
            'reason' => $reason,
            'changed_at' => date('Y-m-d H:i:s'),
        ];

        $this->status_history = json_encode($history, JSON_UNESCAPED_UNICODE);
        $this->status = $newStatus;
    }

    /**
     * 判断是否可以开始打码
     * @return bool
     */
    public function canStartBetting(): bool
    {
        $now = date('Y-m-d H:i:s');
        return $this->status === self::STATUS_PREHEATING
            && $now >= $this->start_time;
    }

    /**
     * 判断是否可以开奖
     * @return bool
     */
    public function canStartDrawing(): bool
    {
        $now = date('Y-m-d H:i:s');
        return in_array($this->status, [self::STATUS_BETTING, self::STATUS_ONGOING])
            && $this->draw_time
            && $now >= $this->draw_time;
    }

    /**
     * 判断是否应该结束
     * @return bool
     */
    public function shouldEnd(): bool
    {
        $now = date('Y-m-d H:i:s');
        return $this->status === self::STATUS_DRAWING
            && $now >= $this->end_time;
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
