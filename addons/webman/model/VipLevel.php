<?php

namespace addons\webman\model;

use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;

/**
 * VIP等级
 * @property int id 主键
 * @property string name 等级名称
 * @property int upgrade_limit_days 升级限制时间（天数）
 * @property int retain_level_days 保级时间（天数）
 * @property float retain_level_bet_amount 保级所需打码量
 * @property float upgrade_bet_amount 升级所需打码量
 * @property float min_claim_amount 最小领取额
 * @property float birthday_bonus 生日礼金
 * @property int sort 排序
 * @property int status 状态
 * @property string created_at 创建时间
 * @property string updated_at 更新时间
 * @package addons\webman\model
 */
class VipLevel extends Model
{
    use HasDateTimeFormatter;

    protected $table = 'vip_level';

    protected $fillable = [
        'name',
        'upgrade_limit_days',
        'retain_level_days',
        'retain_level_bet_amount',
        'upgrade_bet_amount',
        'min_claim_amount',
        'birthday_bonus',
        'sort',
        'status',
    ];
}
