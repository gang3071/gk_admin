<?php

namespace addons\webman\model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 玩家积分主表模型
 *
 * @property int $id
 * @property int $player_id
 * @property int $department_id
 * @property float $total_points
 * @property float $available_points
 * @property float $frozen_points
 * @property float $used_points
 * @property int $version
 * @property string $created_at
 * @property string $updated_at
 */
class PlayerPoints extends Model
{
    protected $table = 'player_points';

    protected $fillable = [
        'player_id',
        'department_id',
        'total_points',
        'available_points',
        'frozen_points',
        'used_points',
        'version',
    ];

    protected $casts = [
        'player_id' => 'integer',
        'department_id' => 'integer',
        // 积分保留4位小数（decimal(14,4)），必须用 float，integer 会在读取时截断小数
        'total_points' => 'float',
        'available_points' => 'float',
        'frozen_points' => 'float',
        'used_points' => 'float',
        'version' => 'integer',
    ];

    /**
     * 关联玩家
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id', 'id');
    }

    /**
     * 获取或创建玩家积分记录
     */
    public static function getOrCreate(int $playerId, int $departmentId = 0): PlayerPoints
    {
        return static::firstOrCreate(
            ['player_id' => $playerId],
            [
                'department_id' => $departmentId,
                'total_points' => 0,
                'available_points' => 0,
                'frozen_points' => 0,
                'used_points' => 0,
                'version' => 0,
            ]
        );
    }
}
