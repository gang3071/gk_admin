<?php

namespace addons\webman\model;

use addons\webman\traits\DataPermissions;
use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class LotteryTicketRecord
 *
 * @property int $id 主键ID
 * @property int $activity_id 活动ID
 * @property int $player_id 玩家ID
 * @property int $department_id 所属渠道部门ID
 * @property int $ticket_id 使用的摸奖券ID
 * @property string $ticket_no 摸奖券编号
 * @property int $prize_level_id 奖品等级ID
 * @property string $prize_type 奖品类型
 * @property string $prize_name 奖品名称
 * @property float $prize_amount 奖品金额
 * @property int $status 状态(0:待发放,1:已发放,2:发放失败)
 * @property string $remark 备注
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 *
 * @property Player $player
 * @property LotteryTicketActivity $activity
 * @property LotteryTicket $ticket
 *
 * @package addons\webman\model
 */
class LotteryTicketRecord extends Model
{
    use HasDateTimeFormatter, DataPermissions;

    // 状态常量
    const STATUS_PENDING = 0;      // 待发放 ⭐ 含义变更：开奖后等待管理员发放
    const STATUS_CLAIMED = 1;      // 已发放 ⭐ 含义变更：管理员已发放
    const STATUS_EXPIRED = 2;      // 已过期
    const STATUS_CANCELLED = 3;    // 已取消
    const STATUS_PROCESSING = 4;   // 发放中 ⭐ 新增
    const STATUS_FAILED = 5;       // 发放失败 ⭐ 新增

    // 兼容旧常量
    const STATUS_GRANTED = 1;      // 已发放（兼容旧代码）

    // 奖品类型常量
    const PRIZE_TYPE_CASH = 'cash';       // 现金
    const PRIZE_TYPE_BONUS = 'bonus';     // 红利
    const PRIZE_TYPE_ITEM = 'item';       // 实物
    const PRIZE_TYPE_POINTS = 'points';   // 积分
    const PRIZE_TYPE_EMPTY = 'empty';     // 未中奖

    protected $table = 'lottery_ticket_record';

    protected $guarded = [];

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
            self::STATUS_PENDING => admin_trans('lottery_ticket.record_status.pending'),      // 待发放
            self::STATUS_CLAIMED => admin_trans('lottery_ticket.record_status.claimed'),      // 已发放
            self::STATUS_EXPIRED => admin_trans('lottery_ticket.record_status.expired'),      // 已过期
            self::STATUS_CANCELLED => admin_trans('lottery_ticket.record_status.cancelled'),  // 已取消
            self::STATUS_PROCESSING => admin_trans('lottery_ticket.record_status.processing'),// 发放中 ⭐
            self::STATUS_FAILED => admin_trans('lottery_ticket.record_status.failed'),        // 发放失败
        ];

        return $statusMap[$status] ?? admin_trans('lottery_ticket.record_status.unknown');
    }

    /**
     * 获取奖品类型文本
     * @param string $type
     * @return string
     */
    public static function getPrizeTypeText(string $type): string
    {
        $typeMap = [
            self::PRIZE_TYPE_CASH => admin_trans('lottery_ticket.prize_type.cash'),
            self::PRIZE_TYPE_BONUS => admin_trans('lottery_ticket.prize_type.bonus'),
            self::PRIZE_TYPE_ITEM => admin_trans('lottery_ticket.prize_type.item'),
            self::PRIZE_TYPE_EMPTY => admin_trans('lottery_ticket.prize_type.empty'),
        ];

        return $typeMap[$type] ?? admin_trans('lottery_ticket.prize_type.unknown');
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
     * 所属活动
     * @return BelongsTo
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(LotteryTicketActivity::class, 'activity_id');
    }

    /**
     * 使用的摸奖券
     * @return BelongsTo
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(LotteryTicket::class, 'ticket_id');
    }

    /**
     * 发放操作人
     * @return BelongsTo
     */
    public function distributedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'distributed_by');
    }

    /**
     * 修改操作人
     * @return BelongsTo
     */
    public function modifiedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'modified_by');
    }

    /**
     * 获取当前记录的状态文本（实例方法）
     * @return string
     */
    public function getStatusLabel(): string
    {
        return self::getStatusText($this->status);
    }

    /**
     * 获取当前记录的奖品类型文本（实例方法）
     * @return string
     */
    public function getPrizeTypeLabel(): string
    {
        return self::getPrizeTypeText($this->prize_type);
    }
}
