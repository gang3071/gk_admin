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
    const STATUS_PENDING = 0;   // 待发放
    const STATUS_GRANTED = 1;   // 已发放
    const STATUS_FAILED = 2;    // 发放失败

    // 奖品类型常量
    const PRIZE_TYPE_CASH = 'cash';       // 现金
    const PRIZE_TYPE_BONUS = 'bonus';     // 红利
    const PRIZE_TYPE_ITEM = 'item';       // 实物
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
            self::STATUS_PENDING => admin_trans('lottery_ticket.record_status.pending'),
            self::STATUS_GRANTED => admin_trans('lottery_ticket.record_status.granted'),
            self::STATUS_FAILED => admin_trans('lottery_ticket.record_status.failed'),
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
}
