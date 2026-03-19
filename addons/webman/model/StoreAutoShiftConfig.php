<?php

namespace addons\webman\model;

use support\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 自动交班配置模型
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
        return $this->belongsTo(\addons\webman\model\AdminUser::class, 'bind_admin_user_id', 'id');
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
