<?php

namespace addons\webman\model;

use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class LotteryTicketPrizeLevel
 * 摸奖券奖品等级配置模型（仅支持现金奖励）
 *
 * @property int $id 主键ID
 * @property int $activity_id 活动ID
 * @property int $level_rank 等级排名(1-10)
 * @property string $level_name 等级名称
 * @property float $prize_amount 奖品金额（现金）
 * @property int $prize_count 奖品数量
 * @property float $win_probability 中奖概率（废弃字段，保留兼容）
 * @property int $sort_order 排序
 * @property int $status 状态
 * @property string $description 奖品描述（废弃字段，保留兼容）
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 *
 * @property LotteryTicketActivity $activity
 *
 * @package addons\webman\model
 */
class LotteryTicketPrizeLevel extends Model
{
    use HasDateTimeFormatter;

    // 奖品类型常量（废弃 - prize_type字段已删除，保留常量供LotteryTicketRecord使用）
    const PRIZE_TYPE_CASH = 'cash';       // 现金
    const PRIZE_TYPE_BONUS = 'bonus';     // 红利
    const PRIZE_TYPE_ITEM = 'item';       // 实物
    const PRIZE_TYPE_POINTS = 'points';   // 积分

    // 最大等级数
    const MAX_LEVELS = 10;

    // 状态常量
    const STATUS_DISABLED = 0;  // 禁用
    const STATUS_ENABLED = 1;   // 启用

    protected $table = 'lottery_ticket_prize_level';

    protected $guarded = [];

    protected $casts = [
        'prize_amount' => 'float',
        'win_probability' => 'float',
    ];

    /**
     * 获取等级名称选项
     * @return array
     */
    public static function getLevelNameOptions(): array
    {
        return [
            1 => admin_trans('lottery_ticket.level_name.special'),     // 特等奖
            2 => admin_trans('lottery_ticket.level_name.first'),       // 一等奖
            3 => admin_trans('lottery_ticket.level_name.second'),      // 二等奖
            4 => admin_trans('lottery_ticket.level_name.third'),       // 三等奖
            5 => admin_trans('lottery_ticket.level_name.fourth'),      // 四等奖
            6 => admin_trans('lottery_ticket.level_name.fifth'),       // 五等奖
            7 => admin_trans('lottery_ticket.level_name.sixth'),       // 六等奖
            8 => admin_trans('lottery_ticket.level_name.seventh'),     // 七等奖
            9 => admin_trans('lottery_ticket.level_name.eighth'),      // 八等奖
            10 => admin_trans('lottery_ticket.level_name.ninth'),      // 九等奖
        ];
    }

    /**
     * 验证活动的中奖等级配置
     * @param int $activityId
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validateActivityPrizeLevels(int $activityId): array
    {
        $levels = self::where('activity_id', $activityId)
            ->where('status', self::STATUS_ENABLED)
            ->orderBy('sort_order')
            ->get();

        // 检查是否超过10个等级
        if ($levels->count() > self::MAX_LEVELS) {
            return [
                'valid' => false,
                'message' => admin_trans('lottery_ticket.error.too_many_levels', null, ['max' => self::MAX_LEVELS])
            ];
        }

        // 检查是否有等级
        if ($levels->count() === 0) {
            return [
                'valid' => false,
                'message' => admin_trans('lottery_ticket.error.no_prize_levels')
            ];
        }

        // 检查奖品数量总和
        $totalPrizes = $levels->sum('prize_count');
        if ($totalPrizes === 0) {
            return [
                'valid' => false,
                'message' => admin_trans('lottery_ticket.error.no_prizes')
            ];
        }

        // 检查概率总和（如果设置了概率）
        $totalProbability = $levels->sum('win_probability');
        if ($totalProbability > 100) {
            return [
                'valid' => false,
                'message' => admin_trans('lottery_ticket.error.probability_exceed', null, ['total' => $totalProbability])
            ];
        }

        return [
            'valid' => true,
            'message' => 'OK',
            'total_prizes' => $totalPrizes,
            'total_probability' => $totalProbability,
        ];
    }

    /**
     * 所属活动
     * @return BelongsTo
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(LotteryTicketActivity::class, 'activity_id');
    }

    /**
     * 获取奖品展示名称（废弃 - prize_type字段已删除）
     * @deprecated 2026-06-17 保留方法体防止旧代码调用报错，但不再使用
     * @return string
     */
    public function getPrizeDisplayNameAttribute(): string
    {
        // prize_level表已不支持实物/积分等奖品类型，固定返回现金格式
        return admin_trans('lottery_ticket.prize_type.cash') . ' ' . $this->prize_amount;
    }

    /**
     * 获取奖品类型文本
     * @param string $type
     * @return string
     */
    public static function getPrizeTypeText(string $type): string
    {
        $typeMap = [
            self::PRIZE_TYPE_CASH => admin_trans('lottery_ticket.prize_type.cash'),
            self::PRIZE_TYPE_BONUS => admin_trans('lottery_ticket.prize_type.bonus'),
            self::PRIZE_TYPE_ITEM => admin_trans('lottery_ticket.prize_type.item'),
            self::PRIZE_TYPE_POINTS => admin_trans('lottery_ticket.prize_type.points'),
        ];

        return $typeMap[$type] ?? admin_trans('lottery_ticket.prize_type.unknown');
    }
}
