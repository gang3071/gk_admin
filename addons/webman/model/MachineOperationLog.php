<?php

namespace addons\webman\model;

use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class MachineOperationLog
 * @property int     $id
 * @property int     $department_id
 * @property int     $machine_id
 * @property int     $player_id
 * @property int     $user_id
 * @property string  $action
 * @property string  $content
 * @property int     $status
 * @property string  $created_at
 *
 * @property Machine      $machine
 * @property Player       $player
 * @property AdminUser    $user
 */
class MachineOperationLog extends Model
{
    use HasDateTimeFormatter;

    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(plugin()->webman->config('database.machine_operation_log_table'));
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(plugin()->webman->config('database.machine_model'), 'machine_id')->withTrashed();
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(plugin()->webman->config('database.player_model'), 'player_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(plugin()->webman->config('database.admin_user_model'), 'user_id')->withTrashed();
    }
}
