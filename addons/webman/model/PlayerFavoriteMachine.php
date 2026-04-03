<?php

namespace addons\webman\model;

use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class Slider
 * @property int id 主键
 * @property int machine_id 機台id
 * @property int player_id 玩家id
 * @property string created_at 创建时间
 * @property string updated_at 最后一次修改时间
 *
 * @property Player player 玩家信息
 * @property Machine machine 機台信息
 * @package addons\webman\model
 */
class PlayerFavoriteMachine extends Model
{
    use HasDateTimeFormatter;
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(plugin()->webman->config('database.player_favorite_machine_table'));
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
     * 機台信息
     * @return BelongsTo
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(plugin()->webman->config('database.machine_model'),'machine_id');
    }

    /**
     * 模型的 "booted" 方法
     * @return void
     */
    protected static function booted()
    {
        static::created(function (PlayerFavoriteMachine $playerFavoriteMachine) {
            // 增加机台收藏数（使用 save() 确保触发模型事件和数据一致性）
            $machine = $playerFavoriteMachine->machine;
            $machine->favorite_num = ($machine->favorite_num ?? 0) + 1;
            $machine->save();
        });

        static::deleted(function (PlayerFavoriteMachine $playerFavoriteMachine) {
            // 减少机台收藏数（使用 save() 确保触发模型事件和数据一致性）
            $machine = $playerFavoriteMachine->machine;
            $machine->favorite_num = max(($machine->favorite_num ?? 0) - 1, 0);
            $machine->save();
        });
    }
}
