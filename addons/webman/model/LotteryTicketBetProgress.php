<?php

namespace addons\webman\model;

use addons\webman\traits\DataPermissions;
use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 摸奖券打码进度模型
 *
 * @property int $id 主键ID
 * @property int $activity_id 活动ID
 * @property int $player_id 玩家ID
 * @property int $department_id 所属渠道部门ID
 * @property int $vip_level_id VIP等级ID
 * @property float $bet_amount_required 基础打码量要求
 * @property float $current_bet_amount 当前累计打码量
 * @property int $ticket_count_per_cycle 每次达标发放的券数
 * @property int $cycles_completed 已完成的周期数
 * @property int $total_tickets_issued 总共已发放的券数
 * @property string $last_issued_at 最后发券时间
 * @property int $status 状态(0:已结束,1:进行中)
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 *
 * @property LotteryTicketActivity $activity
 * @property Player $player
 * @property VipLevel $vipLevel
 *
 * @package addons\webman\model
 */
class LotteryTicketBetProgress extends Model
{
    use HasDateTimeFormatter, DataPermissions;

    // 状态常量
    const STATUS_ENDED = 0;     // 已结束
    const STATUS_ACTIVE = 1;    // 进行中

    protected $table = 'lottery_ticket_bet_progress';

    protected $guarded = [];

    protected $casts = [
        'bet_amount_required' => 'float',
        'current_bet_amount' => 'float',
        'ticket_count_per_cycle' => 'integer',
        'cycles_completed' => 'integer',
        'total_tickets_issued' => 'integer',
    ];

    /**
     * 数据权限配置
     * @var array
     */
    protected $dataAuth = ['department_id' => 'department_id'];

    /**
     * 计算当前进度百分比
     * @return float
     */
    public function getProgressPercentAttribute(): float
    {
        if ($this->bet_amount_required <= 0) {
            return 0;
        }

        // 计算当前周期内的进度
        $currentCycleAmount = fmod($this->current_bet_amount, $this->bet_amount_required);
        if ($currentCycleAmount == 0 && $this->current_bet_amount > 0) {
            $currentCycleAmount = $this->bet_amount_required;
        }

        return min(100, round(($currentCycleAmount / $this->bet_amount_required) * 100, 2));
    }

    /**
     * 计算距离下次发券还需打码量
     * @return float
     */
    public function getRemainingBetAmountAttribute(): float
    {
        $currentCycleAmount = fmod($this->current_bet_amount, $this->bet_amount_required);
        return max(0, $this->bet_amount_required - $currentCycleAmount);
    }

    /**
     * 检查是否可以发券
     * @return bool
     */
    public function canIssueTickets(): bool
    {
        if ($this->status != self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->bet_amount_required <= 0) {
            return false;
        }

        // 检查是否达到了新的周期
        $newCycles = floor($this->current_bet_amount / $this->bet_amount_required);
        return $newCycles > $this->cycles_completed;
    }

    /**
     * 计算应该发放的券数
     * @return int
     */
    public function getTicketsToIssue(): int
    {
        if (!$this->canIssueTickets()) {
            return 0;
        }

        $newCycles = floor($this->current_bet_amount / $this->bet_amount_required);
        $cyclesToIssue = $newCycles - $this->cycles_completed;

        return $cyclesToIssue * $this->ticket_count_per_cycle;
    }

    /**
     * 所属活动
     * @return BelongsTo
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(LotteryTicketActivity::class, 'activity_id');
    }

    /**
     * 所属玩家
     * @return BelongsTo
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
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
