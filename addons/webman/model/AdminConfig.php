<?php

namespace addons\webman\model;

use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;

/**
 * 系统配置模型
 *
 * @property int $id 主键ID
 * @property string $name 配置名称
 * @property string $value 配置值
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 *
 * @package addons\webman\model
 * @table admin_configs
 */
class AdminConfig extends Model
{
    use HasDateTimeFormatter;
    protected $fillable = ['name', 'value'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(plugin()->webman->config('database.config_table'));
    }
}
