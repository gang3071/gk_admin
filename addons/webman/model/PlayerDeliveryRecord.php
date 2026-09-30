<?php

namespace addons\webman\model;

use addons\webman\traits\DataPermissions;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webman\Event\Event;

/**
 * Class PlayerDeliveryRecord
 * @property int id 主键
 * @property int player_id 玩家id
 * @property string target 质料表
 * @property int target_id 质料id
 * @property int department_id 部门/渠道id
 * @property int machine_id 机台id
 * @property int platform_id 电子游戏平台id
 * @property string machine_name 机台名称
 * @property int machine_type 机台类型
 * @property string code 机台编号
 * @property int user_id 管理员id
 * @property int user_name 管理员名称
 * @property int type 类型
 * @property int withdraw_status 提现状态 1=提现中(待审核), 2=成功, 3=失败 , 4=待打款, 5=不通过, 6=玩家取消, 7=系统取消
 * @property string source 来源
 * @property float amount 点数
 * @property float amount_before 異動前金額
 * @property float amount_after 異動后金額
 * @property string tradeno 单号
 * @property string remark 备注
 * @property string created_at 创建时间
 * @property string updated_at 最后一次修改时间
 *
 * @property Player player 玩家
 * @property Machine machine 机台
 * @property GamePlatform gamePlatform 平台信息
 * @package addons\webman\model
 */
class PlayerDeliveryRecord extends Model
{
    use DataPermissions;

    //数据权限字段
    protected $dataAuth = ['department_id' => 'department_id'];

    const TYPE_MODIFIED_AMOUNT_ADD = 1; // (管理后台)加点
    const TYPE_PRESENT_IN = 2; // 玩家转入
    const TYPE_PRESENT_OUT = 3; // 币商转出
    const TYPE_MACHINE_UP = 4; // 机台上分
    const TYPE_MACHINE_DOWN = 5; // 机台下分
    const TYPE_RECHARGE = 6; // 充值
    const TYPE_WITHDRAWAL = 7; // 提现
    const TYPE_MODIFIED_AMOUNT_DEDUCT = 8; // (管理后台)扣点
    const TYPE_WITHDRAWAL_BACK = 9; // 提现失败返还
    const TYPE_ACTIVITY_BONUS = 10; // 活动奖金
    const TYPE_REGISTER_PRESENT = 11; // 注册赠送
    const TYPE_PROFIT = 12; // 推广员分润
    const TYPE_LOTTERY = 13; // 彩金中奖
    const TYPE_GAME_PLATFORM_OUT = 14; // 转出到电子游戏
    const TYPE_GAME_PLATFORM_IN = 15; // 电子游戏转入
    const TYPE_NATIONAL_INVITE = 16; // 全民代理邀请奖励
    const TYPE_RECHARGE_REWARD = 17; // 全民代理首充奖励
    const TYPE_DAMAGE_REBATE = 18; // 全民代理客损返佣
    const TYPE_REVERSE_WATER = 19; // 电子游戏反水
    const COIN_ADD = 20; // 币商加点
    const COIN_DEDUCT = 21; // 币商扣点
    const TYPE_SPECIAL = 22; // 特殊类型
    const TYPE_MACHINE = 23; // 投钞类型
    const TYPE_AGENT_OUT = 24; // 代理玩家转出
    const TYPE_AGENT_IN = 25; // 代理玩家转入

    const TYPE_BET = 26; //用户下注
    const TYPE_CANCEL_BET = 27; //用户取消下注
    const TYPE_GIFT = 28; //用户打赏

    const TYPE_SETTLEMENT = 29; //注单结算

    const TYPE_RE_SETTLEMENT = 30; //重新结算

    const TYPE_PREPAY = 31; //预扣金额
    const TYPE_REFUND = 32; //退款
    const TYPE_LOTTERY_TICKET_REWARD = 33; // ⭐ 摸奖券中奖奖励 (支出类型)
    const TYPE_BIRTHDAY_BONUS = 34; // VIP生日礼金
    const TYPE_VIP_UPGRADE_BONUS = 35; // VIP升级礼金
    const TYPE_REVERSE_WATER_POOL = 36; // 反水池反水领取
    const TYPE_VIP_DAILY_LOGIN_BONUS = 37; // VIP每日登录奖励
    const TYPE_ACTIVITY_GIVE = 38; // 活动外增

    protected $fillable = [
        'player_id',
        'target',
        'target_id',
        'department_id',
        'type',
        'source',
        'amount',
        'amount_after',
        'amount_before',
        'amount_platform_before',
        'amount_platform_after',
        'tradeno',
        'remark',
        'operator_audit',
        'operator_withdraw',
        'created_at',
    ];

    /**
     * 时间转换
     * @param DateTimeInterface $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(plugin()->webman->config('database.player_delivery_record_table'));
    }

    /**
     * 玩家信息
     * @return BelongsTo
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(plugin()->webman->config('database.player_model'), 'player_id')->withTrashed();
    }

    /**
     * 机台信息
     * @return BelongsTo
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(plugin()->webman->config('database.machine_model'), 'machine_id')->withTrashed();
    }

    /**
     * 金额
     *
     * @param $value
     * @return float
     */
    public function getAmountAttribute($value): float
    {
        return floatval($value);
    }

    /**
     * 異動前金額
     *
     * @param $value
     * @return float
     */
    public function getAmountBeforeAttribute($value): float
    {
        return floatval($value);
    }

    /**
     * 異動后金額
     *
     * @param $value
     * @return float
     */
    public function getAmountAfterAttribute($value): float
    {
        return floatval($value);
    }

    /**
     * 账变类型 => 标签颜色
     * 新增类型时只需在此维护一处
     */
    public static function typeColor(int $type): string
    {
        $map = [
            self::TYPE_MODIFIED_AMOUNT_ADD => '#2db7f5',
            self::TYPE_PRESENT_IN => '#8D3514',
            self::TYPE_PRESENT_OUT => '#f50',
            self::TYPE_MACHINE_UP => '#9FCC84',
            self::TYPE_MACHINE_DOWN => '#668A50',
            self::TYPE_RECHARGE => '#3C87C9',
            self::TYPE_WITHDRAWAL => '#C98341',
            self::TYPE_MODIFIED_AMOUNT_DEDUCT => '#108ee9',
            self::TYPE_WITHDRAWAL_BACK => '#CC6600',
            self::TYPE_ACTIVITY_BONUS => '#CC6600',
            self::TYPE_REGISTER_PRESENT => '#CC6600',
            self::TYPE_PROFIT => '#e8c521',
            self::TYPE_LOTTERY => '#e8c521',
            self::TYPE_GAME_PLATFORM_OUT => '#CC6600',
            self::TYPE_GAME_PLATFORM_IN => '#108ee9',
            self::TYPE_NATIONAL_INVITE => '#e8c521',
            self::TYPE_RECHARGE_REWARD => '#e8c521',
            self::TYPE_DAMAGE_REBATE => '#e8c521',
            self::TYPE_REVERSE_WATER => '#e8c521',
            self::TYPE_REVERSE_WATER_POOL => '#722ed1',
            self::COIN_ADD => '#e8c521',
            self::COIN_DEDUCT => '#e8c521',
            self::TYPE_SPECIAL => '#e8c521',
            self::TYPE_MACHINE => '#e8c521',
            self::TYPE_AGENT_OUT => '#e8c521',
            self::TYPE_AGENT_IN => '#e8c521',
            self::TYPE_BET => '#1890ff',
            self::TYPE_CANCEL_BET => '#52c41a',
            self::TYPE_GIFT => '#eb2f96',
            self::TYPE_SETTLEMENT => '#13c2c2',
            self::TYPE_RE_SETTLEMENT => '#722ed1',
            self::TYPE_PREPAY => '#fa8c16',
            self::TYPE_REFUND => '#a0d911',
            self::TYPE_LOTTERY_TICKET_REWARD => '#CC6600',
            self::TYPE_BIRTHDAY_BONUS => '#eb2f96',
            self::TYPE_VIP_UPGRADE_BONUS => '#722ed1',
            self::TYPE_VIP_DAILY_LOGIN_BONUS => '#eb2f96',
            self::TYPE_ACTIVITY_GIVE => '#CC6600',
        ];
        return $map[$type] ?? 'gray';
    }

    /**
     * 账变类型 => 名称（筛选下拉等）
     */
    public static function typeOptions(): array
    {
        $types = [
            self::TYPE_MODIFIED_AMOUNT_ADD,
            self::TYPE_PRESENT_IN,
            self::TYPE_PRESENT_OUT,
            self::TYPE_MACHINE_UP,
            self::TYPE_MACHINE_DOWN,
            self::TYPE_RECHARGE,
            self::TYPE_WITHDRAWAL,
            self::TYPE_MODIFIED_AMOUNT_DEDUCT,
            self::TYPE_WITHDRAWAL_BACK,
            self::TYPE_ACTIVITY_BONUS,
            self::TYPE_REGISTER_PRESENT,
            self::TYPE_PROFIT,
            self::TYPE_LOTTERY,
            self::TYPE_GAME_PLATFORM_OUT,
            self::TYPE_GAME_PLATFORM_IN,
            self::TYPE_NATIONAL_INVITE,
            self::TYPE_RECHARGE_REWARD,
            self::TYPE_DAMAGE_REBATE,
            self::TYPE_REVERSE_WATER,
            self::TYPE_REVERSE_WATER_POOL,
            self::COIN_ADD,
            self::COIN_DEDUCT,
            self::TYPE_SPECIAL,
            self::TYPE_MACHINE,
            self::TYPE_AGENT_OUT,
            self::TYPE_AGENT_IN,
            self::TYPE_BET,
            self::TYPE_CANCEL_BET,
            self::TYPE_GIFT,
            self::TYPE_SETTLEMENT,
            self::TYPE_RE_SETTLEMENT,
            self::TYPE_PREPAY,
            self::TYPE_REFUND,
            self::TYPE_LOTTERY_TICKET_REWARD,
            self::TYPE_BIRTHDAY_BONUS,
            self::TYPE_VIP_UPGRADE_BONUS,
            self::TYPE_VIP_DAILY_LOGIN_BONUS,
            self::TYPE_ACTIVITY_GIVE,
        ];
        $options = [];
        foreach ($types as $type) {
            $options[$type] = admin_trans('player_delivery_record.type.' . $type);
        }
        return $options;
    }

    /**
     * 模型的 "booted" 方法
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function (PlayerDeliveryRecord $deliveryRecord) {
            // 发送玩家信息消息(更新用户钱包)
            sendSocketMessage('player-' . $deliveryRecord->player_id, [
                'msg_type' => 'player_info',
                'player_id' => $deliveryRecord->player_id,
                'type' => $deliveryRecord->type,
                'amount' => $deliveryRecord->amount,
                'amount_before' => $deliveryRecord->amount_before,
                'amount_after' => $deliveryRecord->amount_after,
                'machine_name' => $deliveryRecord->machine_name,
                'machine_type' => $deliveryRecord->machine_type,
            ]);
            if (config('app.profit', 'task') == 'event') {
                // 发布分润事件
                switch ($deliveryRecord->type) {
                    case PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD: // 管理员加点
                        Event::emit('promotion.adminAdd', $deliveryRecord);
                        break;
                    case PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_DEDUCT: // 管理员扣点
                        Event::emit('promotion.adminDeduct', $deliveryRecord);
                        break;
                    case PlayerDeliveryRecord::TYPE_REGISTER_PRESENT: // 注册赠送
                        Event::emit('promotion.registerPresent', $deliveryRecord);
                        break;
                    case PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS: // 活动奖励
                        Event::emit('promotion.activityBonus', $deliveryRecord);
                        break;
                    case PlayerDeliveryRecord::TYPE_LOTTERY_TICKET_REWARD: // 摸奖券奖励
                        Event::emit('promotion.lotteryTicketReward', $deliveryRecord);
                        break;
                    default:
                        break;
                }
            }
        });
    }

    /**
     * 平台信息
     * @return BelongsTo
     */
    public function gamePlatform(): BelongsTo
    {
        return $this->belongsTo(plugin()->webman->config('database.game_platform_model'), 'platform_id')->withTrashed();
    }


    /**
     * 充值记录
     * @return mixed
     */
    public function recharge()
    {
        return $this->belongsTo(plugin()->webman->config('database.player_recharge_record_model'), 'target_id');
    }
}
