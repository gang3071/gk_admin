<?php

namespace addons\webman\model;

use Illuminate\Database\Eloquent\Collection;
use support\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 自动交班配置模型
 *
 * @property int $id 主键ID
 * @property int $department_id 部门/渠道ID
 * @property int $bind_admin_user_id 绑定的管理员用户ID（代理/店家）
 * @property int $is_enabled 是否启用（0=未启用，1=已启用）
 * @property string $shift_time_1 早班交班时间（08:00）
 * @property string $shift_time_2 中班交班时间（16:00）
 * @property string $shift_time_3 晚班交班时间（00:00）
 * @property int $auto_settlement 是否自动结算（0=否，1=是）
 * @property string|null $last_shift_time 上次交班时间
 * @property string|null $next_shift_time 下次交班时间（系统自动计算）
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 * @property string|null $deleted_at 软删除时间
 *
 * @property-read string $is_enabled_text 启用状态文本（访问器）
 *
 * @property-read AdminDepartment $department 关联的部门
 * @property-read AdminUser $bindAdminUser 关联的绑定管理员用户
 * @property-read Collection|StoreAutoShiftLog[] $logs 关联的执行日志
 */
class StoreAutoShiftConfig extends Model
{
    use SoftDeletes;

    protected $table = 'store_auto_shift_config';
    protected $primaryKey = 'id';

    /**
     * 可批量赋值的属性
     * @var array
     */
    protected $fillable = [
        'department_id',
        'bind_admin_user_id',
        'is_enabled',
        'shift_time_1',
        'shift_time_2',
        'shift_time_3',
        'auto_settlement',
        'last_shift_time',
        'next_shift_time',
    ];

    /**
     * 追加到模型数组的访问器
     * @var array
     */
    protected $appends = [
        'is_enabled_text'
    ];

    /**
     * 关联店家
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    /**
     * 关联绑定的代理/店家
     */
    public function bindAdminUser()
    {
        return $this->belongsTo(AdminUser::class, 'bind_admin_user_id', 'id');
    }

    /**
     * 关联执行日志
     */
    public function logs()
    {
        return $this->hasMany(StoreAutoShiftLog::class, 'config_id', 'id');
    }

    /**
     * 获取启用状态文本
     */
    public function getIsEnabledTextAttribute()
    {
        return $this->is_enabled ? '已启用' : '未启用';
    }
}
