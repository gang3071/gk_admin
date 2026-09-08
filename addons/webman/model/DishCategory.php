<?php

namespace addons\webman\model;

use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int id
 * @property string title
 * @property int status 0:停用 1:啟用
 * @property int top 0:沒置頂 1:置頂
 * @property int sort
 * @property string remark 備註
 * @property string created_at
 * @property string updated_at
 *
 * @package addons\webman\model
 */
class DishCategory extends Model
{
    use HasDateTimeFormatter;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(plugin()->webman->config('database.dish_category_table'));
    }
}
