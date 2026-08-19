<?php

namespace addons\webman\model;

use addons\webman\traits\DataPermissions;
use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 交班设备明细模型
 *
 * @property int $id 主键ID
 * @property int $shift_record_id 交班记录ID
 * @property int $department_id 部门/渠道ID
 * @property int $bind_admin_user_id 绑定的管理员用户ID
 * @property int $player_id 设备ID
 * @property string $player_name 设备名称
 * @property string $player_phone 设备编号
 * @property int $machine_point 投钞点数
 * @property float $recharge_amount 开分金额
 * @property float $open_score_amount 开分金额(source=artificial_recharge)
 * @property float $ticket_open_score_amount 开票金额(source=ticket_open_score)
 * @property float $incoming_ticket_amount 入票金额(原开票+后台核销)
 * @property float $redeem_amount 核销金额(后台核销)
 * @property float $withdrawal_amount 洗分金额
 * @property float $channel_withdrawal_amount 洗分金额(source=channel_withdrawal)
 * @property float $ticket_redeem_amount 出卷金额(source=ticket_redeem)
 * @property float $ticket_unredeemed_amount 未核销金额(出卷-核销)
 * @property float $experience_coupon_amount 体验券金额
 * @property float $welfare_coupon_amount 福利券金额
 * @property float $modified_add_amount 后台加点
 * @property float $modified_deduct_amount 后台扣点
 * @property float $lottery_amount 彩金发放
 * @property float $activity_bonus_amount 活动奖励金额
 * @property float $lottery_ticket_reward_amount 摸奖券中奖奖励金额
 * @property float $birthday_bonus_amount VIP生日礼金金额
 * @property float $upgrade_bonus_amount VIP升级礼金金额
 * @property float $electronic_game_bet_amount 电子游戏打码量
 * @property float $machine_bet_amount 机器打码量
 * @property float $total_in 总收入
 * @property float $total_out 总支出
 * @property float $profit 利润
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 *
 * @property-read StoreAgentShiftHandoverRecord $shiftRecord 关联的交班记录
 * @property-read Player $player 关联的设备
 * @property-read AdminUser $bindAdminUser 关联的管理员
 *
 * @package addons\webman\model
 */
class StoreShiftDeviceDetail extends Model
{
    use HasDateTimeFormatter, DataPermissions;

    protected $table = 'store_shift_device_detail';

    //数据权限字段
    protected $dataAuth = ['department_id' => 'department_id'];

    protected $fillable = [
        'shift_record_id',
        'department_id',
        'bind_admin_user_id',
        'player_id',
        'player_name',
        'player_phone',
        'machine_point',
        'recharge_amount',
        'open_score_amount',
        'ticket_open_score_amount',
        'incoming_ticket_amount',
        'redeem_amount',
        'withdrawal_amount',
        'channel_withdrawal_amount',
        'ticket_redeem_amount',
        'ticket_unredeemed_amount',
        'experience_coupon_amount',
        'welfare_coupon_amount',
        'modified_add_amount',
        'modified_deduct_amount',
        'lottery_amount',
        'activity_bonus_amount',
        'lottery_ticket_reward_amount',
        'birthday_bonus_amount',
        'upgrade_bonus_amount',
        'electronic_game_bet_amount',
        'machine_bet_amount',
        'total_in',
        'total_out',
        'profit',
    ];

    protected $casts = [
        'machine_point' => 'integer',
        'recharge_amount' => 'float',
        'open_score_amount' => 'float',
        'ticket_open_score_amount' => 'float',
        'incoming_ticket_amount' => 'float',
        'redeem_amount' => 'float',
        'withdrawal_amount' => 'float',
        'channel_withdrawal_amount' => 'float',
        'ticket_redeem_amount' => 'float',
        'ticket_unredeemed_amount' => 'float',
        'experience_coupon_amount' => 'float',
        'welfare_coupon_amount' => 'float',
        'modified_add_amount' => 'float',
        'modified_deduct_amount' => 'float',
        'lottery_amount' => 'float',
        'activity_bonus_amount' => 'float',
        'lottery_ticket_reward_amount' => 'float',
        'birthday_bonus_amount' => 'float',
        'upgrade_bonus_amount' => 'float',
        'electronic_game_bet_amount' => 'float',
        'machine_bet_amount' => 'float',
        'total_in' => 'float',
        'total_out' => 'float',
        'profit' => 'float',
    ];

    /**
     * 关联交班记录
     * @return BelongsTo
     */
    public function shiftRecord(): BelongsTo
    {
        return $this->belongsTo(StoreAgentShiftHandoverRecord::class, 'shift_record_id');
    }

    /**
     * 关联设备（玩家）
     * @return BelongsTo
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    /**
     * 关联管理员
     * @return BelongsTo
     */
    public function bindAdminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'bind_admin_user_id');
    }
}
