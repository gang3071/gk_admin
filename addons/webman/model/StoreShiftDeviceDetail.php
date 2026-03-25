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
 * @property float $withdrawal_amount 洗分金额
 * @property float $modified_add_amount 后台加点
 * @property float $modified_deduct_amount 后台扣点
 * @property float $lottery_amount 彩金发放
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
        'withdrawal_amount',
        'modified_add_amount',
        'modified_deduct_amount',
        'lottery_amount',
        'total_in',
        'total_out',
        'profit',
    ];

    protected $casts = [
        'machine_point' => 'integer',
        'recharge_amount' => 'float',
        'withdrawal_amount' => 'float',
        'modified_add_amount' => 'float',
        'modified_deduct_amount' => 'float',
        'lottery_amount' => 'float',
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
