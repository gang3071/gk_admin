<?php

namespace addons\webman\model;

use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;

/**
 * Class NationalInvite
 * @property int id 主键
 * @property int min 最低人数
 * @property int max 最高人数
 * @property int interval 奖励间隔
 * @property float money 奖励金额
 * @property int status 状态
 * @property string created_at 创建时间
 * @property string updated_at 最后一次修改时间
 * @package addons\webman\model
 */
class NationalInvite extends Model
{
    use HasDateTimeFormatter;
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(plugin()->webman->config('database.national_invite_table'));
    }
}
