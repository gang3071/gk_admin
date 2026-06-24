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
 * @property string|null $description 活动说明
 * @property string|null $cover_image 活动封面图片URL
 * @property string|null $live_url 直播流地址
 * @property int $live_status 直播状态 0=未开播 1=直播中 2=已结束
 * @property string|null $status_history 状态变更历史(JSON)
 * @property string $start_time 活动开始时间
 * @property string $end_time 活动结束时间
 * @property string $draw_method 开奖方式：ball=摇球，manual=手动录入
 * @property string|null $draw_completed_at 开奖完成时间
 * @property string|null $prize_distributed_at 奖励发放完成时间
 * @property float $total_prize_amount 总奖金金额
 * @property float $distributed_prize_amount 已发放奖金金额
 * @property int $auto_draw 是否自动开奖 0=否 1=是
 * @property int $status 活动状态(0:未开始,1:进行中,2:已结束,3:已关闭,5:待开奖,6:开奖中)
 * @property int $total_tickets 总发放摸奖券数量
 * @property int $used_tickets 已使用摸奖券数量
 * @property int $current_ticket_no 当前已发券数
 * @property int $max_ticket_no 最大可发券数（默认100万张）
 * @property array|string $prize_config 奖品配置(JSON格式)
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 * @property string|null $deleted_at 删除时间
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

    // 状态常量（6个核心状态）
    const STATUS_NOT_STARTED = 0;      // 未开始
    const STATUS_ONGOING = 1;          // 进行中（玩家打码获券阶段）
    const STATUS_ENDED = 2;            // 已结束（完全结束，所有流程完成）
    const STATUS_CLOSED = 3;           // 已关闭（手动关闭，异常终止）
    const STATUS_PENDING_DRAW = 5;     // 待开奖（end_time 到达，等待管理员开奖）
    const STATUS_DRAWING = 6;          // 开奖中（管理员摇球阶段）

    // 直播状态常量
    const LIVE_STATUS_NOT_STARTED = 0; // 未开播
    const LIVE_STATUS_ONGOING = 1;      // 直播中
    const LIVE_STATUS_ENDED = 2;        // 已结束

    protected $table = 'lottery_ticket_activity';

    protected $guarded = [];

    protected $casts = [
        'prize_config' => 'array',
        'status_history' => 'array',
        'total_prize_amount' => 'decimal:2',
        'distributed_prize_amount' => 'decimal:2',
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
            self::STATUS_PENDING_DRAW => admin_trans('lottery_ticket.status.pending_draw'),
            self::STATUS_DRAWING => admin_trans('lottery_ticket.status.drawing'),
            self::STATUS_ENDED => admin_trans('lottery_ticket.status.ended'),
            self::STATUS_CLOSED => admin_trans('lottery_ticket.status.closed'),
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
        // ✅ 使用 $casts 自动处理 JSON 编解码
        $history = $this->status_history ?? [];

        $history[] = [
            'from_status' => $this->status,
            'to_status' => $newStatus,
            'reason' => $reason,
            'changed_at' => date('Y-m-d H:i:s'),
        ];

        $this->status_history = $history;
        $this->status = $newStatus;
    }

    /**
     * 判断是否可以开奖
     * @return bool
     */
    public function canStartDrawing(): bool
    {
        // ✅ 更新业务规则：只有待开奖状态才能开奖
        // 新状态流转：ONGOING → 到达end_time → 自动变为PENDING_DRAW → 管理员手动开奖 → DRAWING
        //
        // 旧逻辑（已废弃）：STATUS_ENDED 才能开奖
        // 新逻辑：STATUS_PENDING_DRAW 才能开奖
        return $this->status === self::STATUS_PENDING_DRAW;
    }

    /**
     * 判断是否可以停止开奖（管理员手动触发）
     * @return bool
     */
    public function canStopDrawing(): bool
    {
        // ⭐ 线下摸奖流程：管理员线下摇球后可随时停止开奖
        // 只检查状态为 DRAWING，不检查 ball_result（线下摇球无此字段）
        return $this->status === self::STATUS_DRAWING;
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
