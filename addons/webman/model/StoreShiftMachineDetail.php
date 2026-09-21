<?php

namespace addons\webman\model;

use addons\webman\traits\DataPermissions;
use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 交班实体机台明细模型
 *
 * @property int $id 主键ID
 * @property int $shift_record_id 交班记录ID
 * @property int $department_id 部门/渠道ID
 * @property int $bind_admin_user_id 绑定的管理员用户ID
 * @property int $machine_id 机台ID
 * @property string $machine_code 机台编号
 * @property string $machine_name 机台名称
 * @property int $type 类型
 * @property float $open_point 上分
 * @property float $wash_point 下分
 * @property float $profit 利润（上分-下分）
 * @property float $pressure 押分
 * @property float $score 得分
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 *
 * @property-read StoreAgentShiftHandoverRecord $shiftRecord 关联的交班记录
 * @property-read AdminUser $bindAdminUser 关联的管理员
 *
 * @package addons\webman\model
 */
class StoreShiftMachineDetail extends Model
{
    use HasDateTimeFormatter, DataPermissions;

    protected $table = 'store_shift_machine_detail';

    //数据权限字段
    protected $dataAuth = ['department_id' => 'department_id'];

    protected $fillable = [
        'shift_record_id',
        'department_id',
        'bind_admin_user_id',
        'machine_id',
        'machine_code',
        'machine_name',
        'type',
        'open_point',
        'wash_point',
        'profit',
        'pressure',
        'score',
    ];

    protected $casts = [
        'machine_id' => 'integer',
        'type' => 'integer',
        'open_point' => 'float',
        'wash_point' => 'float',
        'profit' => 'float',
        'pressure' => 'float',
        'score' => 'float',
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
     * 关联管理员
     * @return BelongsTo
     */
    public function bindAdminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'bind_admin_user_id');
    }
}
