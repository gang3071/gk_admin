<?php

namespace addons\webman\model;

use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * VIP等级机台比例模型
 *
 * @property int $id 主键ID
 * @property int $vip_level_id VIP等级ID
 * @property int $machine_type 机台类型（1=斯洛，2=钢珠）
 * @property float $ratio 机台比例（100=100%，0.1=0.1%）
 * @property int $status 状态（0=禁用，1=启用）
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 *
 * @property-read VipLevel $vipLevel 关联的VIP等级
 *
 * @package addons\webman\model
 */
class VipLevelMachineRatio extends Model
{
    use HasDateTimeFormatter;

    // 机台类型常量
    const TYPE_SLOT = 1;        // 斯洛
    const TYPE_STEEL_BALL = 2;  // 钢珠

    protected $fillable = [
        'vip_level_id',
        'machine_type',
        'ratio',
        'status',
    ];

    protected $casts = [
        'vip_level_id' => 'integer',
        'machine_type' => 'integer',
        'ratio' => 'float',
        'status' => 'integer',
    ];

    /**
     * 关联VIP等级
     * @return BelongsTo
     */
    public function vipLevel(): BelongsTo
    {
        return $this->belongsTo(VipLevel::class, 'vip_level_id');
    }

    /**
     * 获取指定VIP等级和机台类型的比例
     * @param int $vipLevelId
     * @param int $machineType
     * @return float
     */
    public static function getMachineRatio(int $vipLevelId, int $machineType): float
    {
        $record = static::query()
            ->where('vip_level_id', $vipLevelId)
            ->where('machine_type', $machineType)
            ->where('status', 1)
            ->first();

        return $record ? (float)$record->ratio : 0;
    }

    /**
     * 计算机台反水金额
     * @param float $bet 打码量
     * @param float $ratio 比例
     * @return float
     */
    public static function calculateMachineAmount(float $bet, float $ratio): float
    {
        return round($bet * $ratio / 100, 4);
    }
}
