<?php

namespace addons\webman\model;

use addons\webman\traits\DataPermissions;
use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 店家交班记录模型
 *
 * @property int $id 主键ID
 * @property int $department_id 部门/渠道ID
 * @property int $bind_admin_user_id 绑定的管理员用户ID（代理/店家）
 * @property string $start_time 统计开始时间
 * @property string $end_time 统计结束时间
 * @property float $machine_amount 投钞金额（纸币金额）
 * @property int $machine_point 投钞点数
 * @property float $total_in 总收入（送分金额）
 * @property float $total_out 总支出（取分金额）
 * @property float $lottery_amount 彩金发放金额（TYPE_LOTTERY=13）
 * @property float $total_profit_amount 总利润（总收入 - 总支出）
 * @property int $is_auto_shift 是否自动交班（0=手动交班，1=自动交班）
 * @property int $auto_shift_log_id 自动交班日志ID（关联 store_auto_shift_log.id，仅自动交班时有值）
 * @property int $user_id 审核人员ID（手动交班时的操作人）
 * @property string $user_name 审核人员名称
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 *
 * @property-read AdminUser $user 关联的审核人员（管理员）
 * @property-read AdminUser $bindAdminUser 关联的绑定管理员用户（代理/店家）
 * @property-read Channel $channel 关联的部门/渠道
 *
 * @package addons\webman\model
 */
class StoreAgentShiftHandoverRecord extends Model
{
    use HasDateTimeFormatter, DataPermissions;
    
    //数据权限字段
    protected $dataAuth = ['department_id' => 'department_id'];
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        $this->setTable(plugin()->webman->config('database.store_agent_shift_handover_record_table'));
    }
    
    /**
     * 管理员用户
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(plugin()->webman->config('database.user_model'), 'user_id')->withTrashed();
    }
    
    /**
     * 渠道信息
     * @return BelongsTo
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(plugin()->webman->config('database.channel_model'), 'department_id',
            'department_id')->withTrashed();
    }

    /**
     * 绑定的代理/店家
     * @return BelongsTo
     */
    public function bindAdminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'bind_admin_user_id');
    }
}
