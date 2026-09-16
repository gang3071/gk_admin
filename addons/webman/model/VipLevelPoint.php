<?php

namespace addons\webman\model;

use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int id
 * @property int vip_level_id VIP等级ID
 * @property int platform_id 遊戲平台ID
 * @property int status 0=停用 1=啟用
 * @property float ratio_point 比例-積分
 * @property float ratio_bet_amount 比例-打碼量
 * @property float min_bet_amount 有效最小打碼量
 * @property string created_at
 * @property string updated_at
 *
 * @property VipLevel vipLevel VIP等级
 * @property GamePlatform gamePlatform 遊戲平台
 * @package addons\webman\model
 */
class VipLevelPoint extends Model
{
    use HasDateTimeFormatter;

    protected $table = 'vip_level_point';

    protected $fillable = [
        'vip_level_id',
        'platform_id',
        'status',
        'ratio_point',
        'ratio_bet_amount',
        'min_bet_amount',
        'created_at',
        'updated_at'
    ];
}
