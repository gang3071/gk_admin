<?php

namespace addons\webman\model;

use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;

class PlayerIdCardBlacklist extends Model
{
    use HasDateTimeFormatter;

    protected $fillable = [
        'id_number',
        'player_id',
        'player_name',
        'admin_id',
        'admin_name',
        'remark',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(plugin()->webman->config('database.player_id_card_blacklist_table'));
    }
}
