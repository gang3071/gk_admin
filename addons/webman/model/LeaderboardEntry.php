<?php

namespace addons\webman\model;

use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int id 主鍵
 * @property int leaderboard_id 對應 leaderboard.id
 * @property int player_id 玩家ID
 * @property int store_admin_id 店家ID
 * @property int rank 名次
 * @property float score 分數（遊戲量，元）
 * @property float prize_amount 可領獎勵金額（元；沒獎=0）
 * @property int grant_status 發放狀態：0=未發 1=已發
 * @property string granted_at 發放時間
 * @property string created_at 建立時間
 * @property string updated_at 更新時間
 *
 * @package addons\webman\model
 */
class LeaderboardEntry extends Model
{
    use HasDateTimeFormatter;

    const GRANT_STATUS_UNISSUED = 0;  // 未發放
    const GRANT_STATUS_ISSUED = 1;  // 已發放

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(plugin()->webman->config('database.leaderboard_entry_table'));
    }

    /**
     * 玩家
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id')->withTrashed();
    }
}
