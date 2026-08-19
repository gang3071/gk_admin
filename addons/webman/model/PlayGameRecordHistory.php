<?php

namespace addons\webman\model;

use addons\webman\traits\DataPermissions;
use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class PlayGameRecordHistory
 * 游戏记录历史表模型（存储 45 天前的数据）
 *
 * @property int $id 主键
 * @property int $player_id 玩家id
 * @property int $parent_player_id 上级玩家id
 * @property int $agent_player_id 代理玩家id
 * @property int $platform_id 平台id
 * @property string $game_code 游戏编号
 * @property int $department_id 渠道id
 * @property int $status 状态
 * @property int $settlement_status 结算状态 0=未结算 1=已结算 2=取消
 * @property float $bet 押注
 * @property float $win 输赢
 * @property float $diff 玩家输赢额度
 * @property float $reward 奖金(不计入输赢)
 * @property string $order_no 单号
 * @property string $original_data 原始数据
 * @property string $action_data 取消/结算原始数据
 * @property string $action_at 结算时间
 * @property string $platform_action_at 结算时间(游戏平台)
 * @property string $created_at 创建时间
 * @property string $updated_at 最后一次修改时间
 * @property int $is_reverse 是否计算反水 0=未计算 1=计算
 * @property int $is_rebet 重结算订单 0=否 1=是
 * @property int $type 订单类型 1=下注 2=打赏 3=预扣 4=退款
 * @property int $national_promoter_action 全民代理反润结算 0=未结算 1=已结算
 * @property float $national_damage_ratio 全民代理返佣比例
 * @property int $vip_level_id VIP等级ID
 * @property float $cashback_ratio 反水比例
 * @property float $cashback_amount 反水金额
 *
 * @property Channel $channel 渠道
 * @property Player $player 玩家
 * @property GamePlatform $gamePlatform 平台信息
 *
 * @package addons\webman\model
 */
class PlayGameRecordHistory extends Model
{
    use HasDateTimeFormatter, DataPermissions;

    /**
     * 表名
     *
     * @var string
     */
    protected $table = 'play_game_record_history';

    /**
     * 数据权限字段
     *
     * @var array<string, string>
     */
    protected $dataAuth = ['department_id' => 'department_id'];

    /**
     * 状态常量
     */
    const STATUS_UNSETTLED = 0; // 未分佣
    const STATUS_SETTLED = 1; // 已分佣

    /**
     * 结算状态常量
     */
    const SETTLEMENT_STATUS_UNSETTLED = 0; // 未结算
    const SETTLEMENT_STATUS_SETTLED = 1; // 已结算
    const SETTLEMENT_STATUS_CANCELLED = 2; // 已取消
    const SETTLEMENT_STATUS_CONFIRM = 3; // 确认

    /**
     * 订单类型常量
     */
    const TYPE_BET = 1; // 下注
    const TYPE_GIFT = 2; // 打赏
    const TYPE_PREPAY = 3; // 预扣款
    const TYPE_REFUND = 4; // 退款

    /**
     * 可批量赋值字段
     *
     * @var array<string>
     */
    protected $fillable = [
        'player_id',
        'parent_player_id',
        'player_uuid',
        'platform_id',
        'game_code',
        'department_id',
        'bet',
        'win',
        'diff',
        'reward',
        'order_no',
        'original_data',
        'national_damage_ratio',
        'agent_player_id',
        'platform_action_at',
        'order_time',
        'settlement_status',
        'action_data',
        'type',
    ];

    /**
     * 渠道信息
     *
     * @return BelongsTo
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'department_id', 'department_id')
            ->withTrashed();
    }

    /**
     * 玩家信息
     *
     * @return BelongsTo
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id')
            ->withTrashed();
    }

    /**
     * 平台信息
     *
     * @return BelongsTo
     */
    public function gamePlatform(): BelongsTo
    {
        return $this->belongsTo(GamePlatform::class, 'platform_id')
            ->withTrashed();
    }
}
