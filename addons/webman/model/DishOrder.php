<?php

namespace addons\webman\model;

use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int id
 * @property string order_no 訂單編號
 * @property int player_id 玩家ID
 * @property int department_id 部門ID
 * @property int admin_user_id 門店ID
 * @property float total_amount 訂單總金額(積分)
 * @property int status 狀態（0=待確認 1=已確認 2=製作中 3=已完成 4=已取消）
 * @property string remark 備註
 * @property string created_at
 * @property string updated_at
 *
 * @package addons\webman\model
 */
class DishOrder extends Model
{
    use HasDateTimeFormatter;

    const STATUS_PENDING = 0;    // 待確認
    const STATUS_CONFIRMED = 1;  // 已確認
    const STATUS_COOKING = 2;    // 製作中
    const STATUS_COMPLETED = 3;  // 已完成
    const STATUS_CANCELLED = 4;  // 已取消

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(plugin()->webman->config('database.dish_order_table'));
    }

    /**
     * 玩家
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id')->withTrashed();
    }

    /**
     * 訂單明細
     */
    public function items(): HasMany
    {
        return $this->hasMany(DishOrderItem::class, 'order_id');
    }

    /**
     * 狀態描述
     */
    public static function getStatusDescription(): array
    {
        return [
            self::STATUS_PENDING => admin_trans('dish_order.status.' . self::STATUS_PENDING),
            self::STATUS_CONFIRMED => admin_trans('dish_order.status.' . self::STATUS_CONFIRMED),
            self::STATUS_COOKING => admin_trans('dish_order.status.' . self::STATUS_COOKING),
            self::STATUS_COMPLETED => admin_trans('dish_order.status.' . self::STATUS_COMPLETED),
            self::STATUS_CANCELLED => admin_trans('dish_order.status.' . self::STATUS_CANCELLED)
        ];
    }
}
