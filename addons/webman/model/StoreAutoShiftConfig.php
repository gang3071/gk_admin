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
 * @property int $bind_player_id 绑定玩家ID（已废弃，请使用 bind_admin_user_id）
 * @property int $bind_admin_user_id 绑定的管理员用户ID（代理/店家）
 * @property int $is_enabled 是否启用（0=未启用，1=已启用）
 * @property int $shift_mode 交班模式（1=每日，2=每周，3=自定义周期）
 * @property string $shift_time 交班时间（格式：HH:mm:ss，如：02:00:00）
 * @property string $shift_weekdays 每周交班日期（逗号分隔，0=周日，1=周一...6=周六，如：1,3,5）
 * @property int $shift_interval_hours 自定义交班周期（单位：小时，仅 shift_mode=3 时有效）
 * @property int $auto_settlement 是否自动结算（0=否，1=是）
 * @property int $notify_on_failure 失败时是否通知（0=否，1=是）
 * @property string $notify_phones 通知手机号（逗号分隔，如：13800138000,13900139000）
 * @property string $last_shift_time 上次交班时间
 * @property string $next_shift_time 下次交班时间（系统自动计算）
 * @property int $status 状态（1=正常，2=暂停，3=异常）
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 * @property string $deleted_at 软删除时间
 *
 * @property-read string $shift_mode_text 交班模式文本（访问器）
 * @property-read string $status_text 状态文本（访问器）
 * @property-read array $shift_weekdays_array 每周交班日期数组（访问器）
 * @property-read string $is_enabled_text 启用状态文本（访问器）
 *
 * @property-read AdminDepartment $department 关联的部门
 * @property-read Player $bindPlayer 关联的绑定玩家（已废弃）
 * @property-read AdminUser $bindAdminUser 关联的绑定管理员用户
 * @property-read Collection|StoreAutoShiftLog[] $logs 关联的执行日志
 */
class StoreAutoShiftConfig extends Model
{
    use SoftDeletes;

    protected $table = 'store_auto_shift_config';
    protected $primaryKey = 'id';

    /**
     * 追加到模型数组的访问器
     * @var array
     */
    protected $appends = [
        'shift_mode_text',
        'status_text',
        'shift_weekdays_array',
        'is_enabled_text'
    ];

    // 交班模式常量
    const MODE_DAILY = 1;      // 每日
    const MODE_WEEKLY = 2;     // 每周
    const MODE_CUSTOM = 3;     // 自定义周期

    // 状态常量
    const STATUS_NORMAL = 1;   // 正常
    const STATUS_PAUSED = 2;   // 暂停
    const STATUS_ERROR = 3;    // 异常

    /**
     * 关联店家
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    /**
     * 关联绑定玩家（旧方法，已废弃）
     * @deprecated 使用 bindAdminUser() 替代
     */
    public function bindPlayer()
    {
        return $this->belongsTo(Player::class, 'bind_player_id', 'id');
    }

    /**
     * 关联绑定的代理/店家（新方法）
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
     * 获取交班模式文本
     */
    public function getShiftModeTextAttribute()
    {
        $modes = [
            self::MODE_DAILY => '每日',
            self::MODE_WEEKLY => '每周',
            self::MODE_CUSTOM => '自定义周期'
        ];
        return $modes[$this->shift_mode] ?? '未知';
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute()
    {
        $statuses = [
            self::STATUS_NORMAL => '正常',
            self::STATUS_PAUSED => '暂停',
            self::STATUS_ERROR => '异常'
        ];
        return $statuses[$this->status] ?? '未知';
    }

    /**
     * 获取每周交班日期数组
     */
    public function getShiftWeekdaysArrayAttribute()
    {
        if (empty($this->shift_weekdays)) {
            return [];
        }
        return array_map('intval', explode(',', $this->shift_weekdays));
    }

    /**
     * 获取启用状态文本
     */
    public function getIsEnabledTextAttribute()
    {
        return $this->is_enabled ? '已启用' : '未启用';
    }
}
