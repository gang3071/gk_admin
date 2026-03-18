<?php

namespace addons\webman\model;

use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;

/**
 * Class IpWhitelist
 * @property int id 主键
 * @property int ip_address ip地址
 * @property string created_at 创建时间
 * @property string updated_at 最后一次修改时间
 *
 * @package addons\webman\model
 */
class IpWhitelist extends Model
{
    use HasDateTimeFormatter;
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(plugin()->webman->config('database.ip_white_list_table'));
    }
}
