<?php

namespace addons\webman\model;

use support\Model;

/**
 * 自动交班执行日志模型
 *
 * @property int $id 主键ID
 * @property int $config_id 配置ID（关联 store_auto_shift_config.id）
 * @property int $department_id 部门/渠道ID
 * @property int $bind_player_id 绑定玩家ID（已废弃，请使用 bind_admin_user_id）
 * @property int $bind_admin_user_id 绑定的管理员用户ID（代理/店家）
 * @property int $shift_record_id 交班记录ID（关联 store_agent_shift_handover_record.id）
 * @property string $start_time 统计开始时间
 * @property string $end_time 统计结束时间
 * @property string $execute_time 执行时间（交班任务实际执行的时间）
 * @property int $status 执行状态（1=成功，2=失败，3=部分成功）
 * @property string $error_message 错误信息（失败时记录）
 * @property int $execution_duration 执行耗时（单位：毫秒）
 * @property float $machine_amount 投钞金额（纸币金额）
 * @property int $machine_point 投钞点数
 * @property float $total_in 总收入（送分金额）
 * @property float $total_out 总支出（取分金额）
 * @property float $lottery_amount 彩金发放金额（TYPE_LOTTERY=13）
 * @property float $total_profit 总利润（总收入 - 总支出）
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 *
 * @property-read string $status_text 状态文本（访问器）
 * @property-read string $status_badge 状态标签HTML（访问器）
 * @property-read string $execution_duration_text 执行耗时文本（访问器，如：1.23s）
 * @property-read string $time_range 时间范围文本（访问器，如：2026-03-21 00:00:00 ~ 2026-03-22 00:00:00）
 * @property-read bool $is_success 是否成功（访问器）
 * @property-read bool $is_failed 是否失败（访问器）
 *
 * @property-read StoreAutoShiftConfig $config 关联的配置
 * @property-read AdminDepartment $department 关联的部门
 * @property-read Player $bindPlayer 关联的绑定玩家（已废弃）
 * @property-read AdminUser $bindAdminUser 关联的绑定管理员用户
 * @property-read StoreAgentShiftHandoverRecord $shiftRecord 关联的交班记录
 */
class StoreAutoShiftLog extends Model
{
    protected $table = 'store_auto_shift_log';
    protected $pk = 'id';

    // 执行状态常量
    const STATUS_SUCCESS = 1;          // 成功
    const STATUS_FAILED = 2;           // 失败
    const STATUS_PARTIAL_SUCCESS = 3;  // 部分成功

    /**
     * 关联配置
     */
    public function config()
    {
        return $this->belongsTo(StoreAutoShiftConfig::class, 'config_id', 'id');
    }

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
     * 关联交班记录
     */
    public function shiftRecord()
    {
        return $this->belongsTo(StoreAgentShiftHandoverRecord::class, 'shift_record_id', 'id');
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $statuses = [
            self::STATUS_SUCCESS => '成功',
            self::STATUS_FAILED => '失败',
            self::STATUS_PARTIAL_SUCCESS => '部分成功'
        ];
        return $statuses[$data['status']] ?? '未知';
    }

    /**
     * 获取状态标签
     */
    public function getStatusBadgeAttr($value, $data)
    {
        $badges = [
            self::STATUS_SUCCESS => '<span class="badge badge-success">成功</span>',
            self::STATUS_FAILED => '<span class="badge badge-danger">失败</span>',
            self::STATUS_PARTIAL_SUCCESS => '<span class="badge badge-warning">部分成功</span>'
        ];
        return $badges[$data['status']] ?? '<span class="badge badge-secondary">未知</span>';
    }

    /**
     * 获取执行耗时文本（秒）
     */
    public function getExecutionDurationTextAttr($value, $data)
    {
        if (empty($data['execution_duration'])) {
            return '-';
        }
        $seconds = round($data['execution_duration'] / 1000, 2);
        return $seconds . 's';
    }

    /**
     * 获取时间范围
     */
    public function getTimeRangeAttr($value, $data)
    {
        return $data['start_time'] . ' ~ ' . $data['end_time'];
    }

    /**
     * 是否成功
     */
    public function getIsSuccessAttr($value, $data)
    {
        return $data['status'] == self::STATUS_SUCCESS;
    }

    /**
     * 是否失败
     */
    public function getIsFailedAttr($value, $data)
    {
        return $data['status'] == self::STATUS_FAILED;
    }
}
