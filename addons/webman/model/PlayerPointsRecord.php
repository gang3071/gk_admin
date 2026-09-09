<?php

namespace addons\webman\model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 玩家积分变动记录模型
 *
 * @property int $id
 * @property int $player_id
 * @property int $department_id
 * @property int $type
 * @property string $source
 * @property int $points
 * @property int $points_before
 * @property int $points_after
 * @property string $remark
 * @property int|null $admin_id
 * @property string|null $admin_name
 * @property string|null $admin_ip
 * @property string $created_at
 */
class PlayerPointsRecord extends Model
{
    protected $table = 'player_points_record';

    public $timestamps = false;

    // 类型常量
    const TYPE_BETTING_SUMMARY = 1;  // 打码汇总
    const TYPE_EXCHANGE = 2;         // 兑换消耗
    const TYPE_EXPIRE = 3;           // 过期扣除
    const TYPE_ADMIN_ADJUST = 4;     // 后台调整
    const TYPE_ACTIVITY = 5;         // 活动奖励
    const TYPE_REFUND = 6;           // 订单退款

    // 来源常量
    const SOURCE_BETTING_SUMMARY = 'betting_summary';
    const SOURCE_EXCHANGE = 'exchange';
    const SOURCE_EXPIRE = 'expire';
    const SOURCE_ADMIN = 'admin';
    const SOURCE_ACTIVITY = 'activity';
    const SOURCE_REFUND = 'refund';

    protected $fillable = [
        'player_id',
        'department_id',
        'type',
        'source',
        'points',
        'points_before',
        'points_after',
        'remark',
        'admin_id',
        'admin_name',
        'admin_ip',
        'batch_id',
        'created_at',
    ];

    protected $casts = [
        'player_id' => 'integer',
        'department_id' => 'integer',
        'type' => 'integer',
        'points' => 'integer',
        'points_before' => 'integer',
        'points_after' => 'integer',
        'admin_id' => 'integer',
    ];

    protected $appends = ['type_desc'];

    /**
     * 类型描述
     */
    public function getTypeDescAttribute(): string
    {
        $types = [
            self::TYPE_BETTING_SUMMARY => admin_trans('player_points.type.' . self::TYPE_BETTING_SUMMARY),
            self::TYPE_EXCHANGE => admin_trans('player_points.type.' . self::TYPE_EXCHANGE),
            self::TYPE_EXPIRE => admin_trans('player_points.type.' . self::TYPE_EXPIRE),
            self::TYPE_ADMIN_ADJUST => admin_trans('player_points.type.' . self::TYPE_ADMIN_ADJUST),
            self::TYPE_ACTIVITY => admin_trans('player_points.type.' . self::TYPE_ACTIVITY),
            self::TYPE_REFUND => admin_trans('player_points.type.' . self::TYPE_REFUND),
        ];

        return $types[$this->type] ?? admin_trans('admin.unknown', [], 'Unknown');
    }

    /**
     * 关联玩家
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id', 'id');
    }
}
