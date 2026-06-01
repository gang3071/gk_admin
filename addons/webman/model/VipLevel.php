<?php

namespace addons\webman\model;

use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;

/**
 * VIP等级
 * @property int id 主键
 * @property string name 等级名称
 * @property int upgrade_limit_days 升级限制时间（天数）
 * @property int retain_level_days 保级时间（天数）
 * @property float retain_level_bet_amount 保级所需打码量
 * @property float upgrade_bet_amount 升级所需打码量
 * @property float min_claim_amount 最小领取额
 * @property float birthday_bonus 生日礼金
 * @property int sort 排序
 * @property int status 状态
 * @property string created_at 创建时间
 * @property string updated_at 更新时间
 * @package addons\webman\model
 */
class VipLevel extends Model
{
    use HasDateTimeFormatter;

    protected $table = 'vip_level';

    const STATUS_DISABLED = 0; // 禁用
    const STATUS_ENABLED = 1;  // 启用

    protected $fillable = [
        'name',
        'upgrade_limit_days',
        'retain_level_days',
        'retain_level_bet_amount',
        'upgrade_bet_amount',
        'min_claim_amount',
        'birthday_bonus',
        'sort',
        'status',
    ];

    // =========================================================================
    // DB 查询方法
    // =========================================================================

    /**
     * 获取下一等级
     * @return VipLevel|null
     */
    public function getNextLevel(): ?VipLevel
    {
        return static::query()
            ->where('status', self::STATUS_ENABLED)
            ->where('sort', '>', $this->sort)
            ->orderBy('sort', 'asc')
            ->first();
    }

    /**
     * 获取上一等级
     * @return VipLevel|null
     */
    public function getPrevLevel(): ?VipLevel
    {
        return static::query()
            ->where('status', self::STATUS_ENABLED)
            ->where('sort', '<', $this->sort)
            ->orderBy('sort', 'desc')
            ->first();
    }

    /**
     * 获取最高等级
     * @return VipLevel|null
     */
    public static function getMaxLevel(): ?VipLevel
    {
        return static::query()
            ->where('status', self::STATUS_ENABLED)
            ->orderBy('sort', 'desc')
            ->first();
    }

    /**
     * 获取最低等级
     * @return VipLevel|null
     */
    public static function getMinLevel(): ?VipLevel
    {
        return static::query()
            ->where('status', self::STATUS_ENABLED)
            ->orderBy('sort', 'asc')
            ->first();
    }

    // =========================================================================
    // 纯函数方法（不依赖数据库，可单元测试）
    // =========================================================================

    /**
     * 从等级列表中查找下一等级
     *
     * @param array $levels 等级列表（按 sort 排序）
     * @param int $currentSort 当前等级的 sort
     * @return VipLevel|null
     */
    public static function findNextLevel(array $levels, int $currentSort): ?VipLevel
    {
        foreach ($levels as $level) {
            if ($level->status == self::STATUS_ENABLED && $level->sort > $currentSort) {
                return $level;
            }
        }
        return null;
    }

    /**
     * 从等级列表中查找上一等级
     *
     * @param array $levels 等级列表（按 sort 排序）
     * @param int $currentSort 当前等级的 sort
     * @return VipLevel|null
     */
    public static function findPrevLevel(array $levels, int $currentSort): ?VipLevel
    {
        $prev = null;
        foreach ($levels as $level) {
            if ($level->status == self::STATUS_ENABLED && $level->sort < $currentSort) {
                $prev = $level;
            }
        }
        return $prev;
    }

    /**
     * 从等级列表中获取最高等级
     *
     * @param array $levels 等级列表
     * @return VipLevel|null
     */
    public static function findMaxLevel(array $levels): ?VipLevel
    {
        $max = null;
        foreach ($levels as $level) {
            if ($level->status == self::STATUS_ENABLED) {
                if ($max === null || $level->sort > $max->sort) {
                    $max = $level;
                }
            }
        }
        return $max;
    }

    /**
     * 从等级列表中获取最低等级
     *
     * @param array $levels 等级列表
     * @return VipLevel|null
     */
    public static function findMinLevel(array $levels): ?VipLevel
    {
        $min = null;
        foreach ($levels as $level) {
            if ($level->status == self::STATUS_ENABLED) {
                if ($min === null || $level->sort < $min->sort) {
                    $min = $level;
                }
            }
        }
        return $min;
    }

    /**
     * 检查是否满足升级条件
     *
     * @param float $periodBetAmount 周期内打码量
     * @param float $upgradeBetAmount 升级所需打码量
     * @return bool
     */
    public static function isUpgradeQualified(float $periodBetAmount, float $upgradeBetAmount): bool
    {
        return $periodBetAmount >= $upgradeBetAmount;
    }

    /**
     * 检查是否满足保级条件
     *
     * @param float $periodBetAmount 周期内打码量
     * @param float $retainBetAmount 保级所需打码量
     * @return bool
     */
    public static function isRetainQualified(float $periodBetAmount, float $retainBetAmount): bool
    {
        return $periodBetAmount >= $retainBetAmount;
    }
}
