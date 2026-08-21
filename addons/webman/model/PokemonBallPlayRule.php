<?php

namespace addons\webman\model;

use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class PokemonBallPlayRule
 * @property int id 主键
 * @property int machine_id 机台ID
 * @property int play_type 玩法类型 1=一球多燈 2=三球 3=三關
 * @property int light_count 灯数
 * @property float multiplier 倍率
 * @property int sort 排序
 * @property string created_at 创建时间
 * @property string updated_at 更新时间
 *
 * @property Machine machine
 * @package addons\webman\model
 */
class PokemonBallPlayRule extends Model
{
    use HasDateTimeFormatter;

    const PLAY_TYPE_ONE_BALL_MULTI_LIGHT = 1; // 一球多燈
    const PLAY_TYPE_THREE_BALL = 2; // 三球
    const PLAY_TYPE_THREE_GUAN = 3; // 三關

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable('pokemon_ball_play_rule');
    }

    /**
     * 机台
     * @return BelongsTo
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    /**
     * 获取玩法类型选项
     * @return array
     */
    public static function getPlayTypeOptions(): array
    {
        return [
            self::PLAY_TYPE_ONE_BALL_MULTI_LIGHT => admin_trans('pokemon_ball_play.play_type.one_ball_multi_light'),
            self::PLAY_TYPE_THREE_BALL => admin_trans('pokemon_ball_play.play_type.three_ball'),
            self::PLAY_TYPE_THREE_GUAN => admin_trans('pokemon_ball_play.play_type.three_guan'),
        ];
    }

    /**
     * 获取默认规则
     * @param int $playType
     * @return array
     */
    public static function getDefaultRules(int $playType): array
    {
        return match ($playType) {
            self::PLAY_TYPE_ONE_BALL_MULTI_LIGHT => [
                ['light_count' => 1, 'multiplier' => 8],
                ['light_count' => 2, 'multiplier' => 4],
                ['light_count' => 3, 'multiplier' => 3],
                ['light_count' => 4, 'multiplier' => 2],
                ['light_count' => 5, 'multiplier' => 1.7],
            ],
            self::PLAY_TYPE_THREE_BALL => [
                ['light_count' => 3, 'multiplier' => 38],
                ['light_count' => 4, 'multiplier' => 18],
                ['light_count' => 5, 'multiplier' => 6],
                ['light_count' => 6, 'multiplier' => 3],
            ],
            self::PLAY_TYPE_THREE_GUAN => [
                ['light_count' => 5, 'multiplier' => 10],
                ['light_count' => 4, 'multiplier' => 25],
                ['light_count' => 3, 'multiplier' => 70],
            ],
            default => [],
        };
    }
}
