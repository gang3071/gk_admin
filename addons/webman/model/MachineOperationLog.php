<?php

namespace addons\webman\model;

use addons\webman\traits\DataPermissions;
use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 机台操作日志
 * Class MachineOperationLog
 * @property int id 主键
 * @property int department_id 部门/渠道id
 * @property int producer_id 机台厂商id
 * @property int machine_id 机台id
 * @property int machine_type 机台类型
 * @property int machine_cate 机台类别id
 * @property string machine_name 机台名
 * @property string machine_code 机台编号
 * @property string uuid 玩家uuid
 * @property int player_id 玩家id
 * @property string player_phone 玩家手机
 * @property string player_name 玩家名
 * @property int status 状态 1成功 0失败
 * @property int is_system 是否系统任务
 * @property string content 操作内容(工控返回JSON)
 * @property string action 操作指令码
 * @property int user_id 管理员id
 * @property string user_name 管理员名
 * @property int point 点数
 * @property string remark 备注
 * @property string created_at 创建时间
 * @property string updated_at 最后一次修改时间
 *
 * @property Player $player 玩家
 * @property Machine $machine 机台
 * @property AdminUser $user 管理员
 * @property Channel $channel 部门/渠道
 * @package addons\webman\model
 */
class MachineOperationLog extends Model
{
    use HasDateTimeFormatter, DataPermissions;

    //数据权限字段
    protected $dataAuth = ['department_id' => 'department_id'];

    protected $fillable = [
        'id',
        'department_id',
        'producer_id',
        'machine_id',
        'machine_type',
        'machine_cate',
        'machine_name',
        'machine_code',
        'uuid',
        'player_id',
        'player_phone',
        'player_name',
        'status',
        'is_system',
        'content',
        'action',
        'user_id',
        'user_name',
        'point',
        'remark',
    ];

    protected $casts = [
        'id' => 'integer',
        'department_id' => 'integer',
        'producer_id' => 'integer',
        'machine_id' => 'integer',
        'machine_type' => 'integer',
        'machine_cate' => 'integer',
        'player_id' => 'integer',
        'status' => 'integer',
        'is_system' => 'integer',
        'user_id' => 'integer',
        'point' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(plugin()->webman->config('database.machine_operation_log_table'));
    }

    /**
     * 机台信息
     * @return BelongsTo
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(plugin()->webman->config('database.machine_model'), 'machine_id')->withTrashed();
    }

    /**
     * 玩家信息
     * @return BelongsTo
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(plugin()->webman->config('database.player_model'), 'player_id')->withTrashed();
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
        return $this->belongsTo(plugin()->webman->config('database.channel_model'), 'department_id', 'department_id')->withTrashed();
    }
}
