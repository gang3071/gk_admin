<?php

namespace addons\webman\model;

use addons\webman\traits\DataPermissions;
use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class LotteryTicket
 *
 * @property int $id 主键ID
 * @property int $player_id 玩家ID
 * @property int $department_id 所属渠道部门ID
 * @property int $activity_id 所属活动ID
 * @property string $ticket_no 摸奖券编号
 * @property int $status 状态(0:未使用,1:已使用,2:已过期)
 * @property string $source 来源
 * @property int $source_id 来源记录ID
 * @property string $used_at 使用时间
 * @property string $expired_at 过期时间
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 *
 * @property Player $player
 * @property LotteryTicketActivity $activity
 *
 * @package addons\webman\model
 */
class LotteryTicket extends Model
{
    use HasDateTimeFormatter, DataPermissions;

    // 状态常量
    const STATUS_UNUSED = 0;    // 未使用
    const STATUS_USED = 1;      // 已使用
    const STATUS_EXPIRED = 2;   // 已过期

    // 来源常量
    const SOURCE_BETTING = 'betting';    // 打码获得
    const SOURCE_RECHARGE = 'recharge';  // 充值赠送
    const SOURCE_ACTIVITY = 'activity';  // 活动赠送
    const SOURCE_MANUAL = 'manual';      // 手动发放

    protected $table = 'lottery_ticket';

    protected $guarded = [];

    /**
     * 数据权限配置
     * @var array
     */
    protected $dataAuth = ['department_id' => 'department_id'];

    /**
     * 生成摸奖券编号
     * @return string
     */
    public static function generateTicketNo(): string
    {
        return 'LT' . date('YmdHis') . mt_rand(1000, 9999);
    }

    /**
     * 获取状态文本
     * @param int $status
     * @return string
     */
    public static function getStatusText(int $status): string
    {
        $statusMap = [
            self::STATUS_UNUSED => admin_trans('lottery_ticket.ticket_status.unused'),
            self::STATUS_USED => admin_trans('lottery_ticket.ticket_status.used'),
            self::STATUS_EXPIRED => admin_trans('lottery_ticket.ticket_status.expired'),
        ];

        return $statusMap[$status] ?? admin_trans('lottery_ticket.ticket_status.unknown');
    }

    /**
     * 获取来源文本
     * @param string $source
     * @return string
     */
    public static function getSourceText(string $source): string
    {
        $sourceMap = [
            self::SOURCE_BETTING => admin_trans('lottery_ticket.source.betting'),
            self::SOURCE_RECHARGE => admin_trans('lottery_ticket.source.recharge'),
            self::SOURCE_ACTIVITY => admin_trans('lottery_ticket.source.activity'),
            self::SOURCE_MANUAL => admin_trans('lottery_ticket.source.manual'),
        ];

        return $sourceMap[$source] ?? admin_trans('lottery_ticket.source.unknown');
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
}
