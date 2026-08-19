<?php

namespace addons\webman\model;

use support\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 店机活动配置模型（福利券/体验券）
 *
 * @property int $id 主键ID
 * @property int $admin_user_id 店机管理员用户ID
 * @property int $department_id 部门/渠道ID
 * @property string|null $start_time 活动开始时间
 * @property string|null $end_time 活动结束时间
 * @property int $status 状态（0=禁用，1=启用）
 * @property string|null $activity_end_time 券发放结束时间
 * @property int $experience_enabled 是否启用体验券
 * @property string|null $experience_register_after 新用户注册时间阈值
 * @property int $experience_daily_limit 体验券每天可领取次数
 * @property int $experience_total_limit 体验券总可领取次数
 * @property int $experience_score 体验券每次领取分数
 * @property int $experience_expire_hours 体验券有效时间（小时）
 * @property int $welfare_enabled 是否启用福利券
 * @property int $welfare_daily_limit 福利券每天可领取次数
 * @property array|null $welfare_rules 福利券档位规则（JSON）
 * @property int $welfare_expire_hours 福利券有效时间（小时）
 * @property string $order_prefix_experience 体验券订单号前缀
 * @property string $order_prefix_welfare 福利券订单号前缀
 * @property string $order_prefix_recharge 开分订单号前缀
 * @property string $order_prefix_withdraw 洗分订单号前缀
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 * @property string|null $deleted_at 软删除时间
 *
 * @property-read AdminUser $adminUser 关联的店机管理员
 * @property-read AdminDepartment $department 关联的部门
 */
class StoreActivityConfig extends Model
{
    use SoftDeletes;

    protected $table = 'store_activity_config';
    protected $primaryKey = 'id';

    /**
     * 可批量赋值的属性
     * @var array
     */
    protected $fillable = [
        'admin_user_id',
        'department_id',
        'start_time',
        'end_time',
        'status',
        'activity_end_time',
        'experience_enabled',
        'experience_register_after',
        'experience_daily_limit',
        'experience_total_limit',
        'experience_score',
        'experience_expire_hours',
        'welfare_enabled',
        'welfare_daily_limit',
        'welfare_rules',
        'welfare_expire_hours',
        'order_prefix_experience',
        'order_prefix_welfare',
        'order_prefix_recharge',
        'order_prefix_withdraw',
    ];

    /**
     * 应该被转换为原生类型的属性
     * @var array
     */
    protected $casts = [
        'welfare_rules' => 'array',
        'experience_enabled' => 'boolean',
        'welfare_enabled' => 'boolean',
    ];

    /**
     * 关联店机管理员
     */
    public function adminUser()
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id', 'id');
    }

    /**
     * 关联部门
     */
    public function department()
    {
        return $this->belongsTo(AdminDepartment::class, 'department_id', 'id');
    }

    /**
     * 获取福利规则（带默认值）
     */
    public function getWelfareRulesWithDefault(): array
    {
        return $this->welfare_rules ?? [
            ['day_type' => 'yesterday', 'bet_amount' => 100000, 'score' => 1000],
            ['day_type' => 'yesterday', 'bet_amount' => 300000, 'score' => 2000],
            ['day_type' => 'yesterday', 'bet_amount' => 500000, 'score' => 3000],
        ];
    }

    /**
     * 检查活动是否在有效期内
     */
    public function isActive(): bool
    {
        if (!$this->status) {
            return false;
        }

        $now = now();

        if ($this->start_time && $now < $this->start_time) {
            return false;
        }

        if ($this->end_time && $now > $this->end_time) {
            return false;
        }

        if ($this->activity_end_time && $now > $this->activity_end_time) {
            return false;
        }

        return true;
    }

    /**
     * 根据打码量获取匹配的福利券档位
     *
     * @param string $day_type 计算类型：yesterday/today
     * @param int $betAmount 打码量
     * @return int 匹配的分数，0表示无匹配
     */
    public function getMatchedWelfareScore(string $day_type, int $betAmount): int
    {
        $rules = $this->welfare_rules ?? [];
        $matchedScore = 0;

        foreach ($rules as $rule) {
            if (($rule['day_type'] ?? 'yesterday') === $day_type
                && $betAmount >= ($rule['bet_amount'] ?? 0)
                && ($rule['score'] ?? 0) > $matchedScore
            ) {
                $matchedScore = $rule['score'];
            }
        }

        return $matchedScore;
    }
}
