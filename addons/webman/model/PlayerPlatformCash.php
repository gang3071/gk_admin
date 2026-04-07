<?php

namespace addons\webman\model;

use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class PlayerPlatformCash
 * @property int id 主键
 * @property int player_id 玩家id
 * @property string player_account 玩家账户
 * @property int platform_id 平台id
 * @property string platform_name 平台名称
 * @property float money 点数
 * @property int status 遊戲平台狀態 0=鎖定 1=正常
 * @property bool is_crashed 是否爆机 0=正常 1=已爆机
 * @property string created_at 创建时间
 * @property string updated_at 最后一次修改时间
 *
 * @property Player player 玩家
 * @package addons\webman\model
 */
class PlayerPlatformCash extends Model
{
    use HasDateTimeFormatter;

    CONST PLATFORM_SELF = 1; // 实体机平台

    protected $fillable = ['player_id', 'platform_id', 'platform_name', 'money'];
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(plugin()->webman->config('database.player_platform_cash_table'));
    }

    /**
     * 点数
     *
     * @param $value
     * @return float
     */
    public function getMoneyAttribute($value): float
    {
        return floatval($value);
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
     * 模型的 "booted" 方法
     * 监听余额变化，自动检查爆机状态并同步 Redis 缓存
     *
     * @return void
     */
    protected static function booted()
    {
        // 监听余额更新事件
        static::updated(function (PlayerPlatformCash $wallet) {
            // 检查 money 字段是否变化
            if (!$wallet->wasChanged('money')) {
                return;
            }

            // ✅ 自动同步 Redis 缓存（所有平台）
            try {
                $cacheUpdated = \addons\webman\service\WalletService::updateCache(
                    $wallet->player_id,
                    $wallet->platform_id,
                    (float)$wallet->money
                );

                // 🚨 缓存同步失败告警
                if (!$cacheUpdated) {
                    \support\Log::critical('PlayerPlatformCash: Redis cache sync failed!', [
                        'player_id' => $wallet->player_id,
                        'platform_id' => $wallet->platform_id,
                        'old_balance' => $wallet->getOriginal('money'),
                        'new_balance' => $wallet->money,
                        'timestamp' => date('Y-m-d H:i:s'),
                    ]);
                }
            } catch (\Throwable $e) {
                // Redis 缓存同步异常
                \support\Log::critical('PlayerPlatformCash: Redis cache sync exception!', [
                    'player_id' => $wallet->player_id,
                    'platform_id' => $wallet->platform_id,
                    'new_balance' => $wallet->money,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            try {
                // 获取玩家信息
                $player = $wallet->player;
                if (!$player) {
                    return;
                }

                // ✅ 从 Redis 读取余额（唯一可信源）
                $previousAmount = floatval($wallet->getOriginal('money'));  // 数据库旧值（参考）

                try {
                    // ✅ 使用 Redis 余额判断爆机
                    $currentAmount = \addons\webman\service\WalletService::getBalance($wallet->player_id, 1);
                } catch (\Throwable $e) {
                    // Redis 异常时降级到数据库值
                    \support\Log::warning('爆机检测: Redis读取失败，降级到数据库', [
                        'player_id' => $wallet->player_id,
                        'error' => $e->getMessage(),
                    ]);
                    $currentAmount = floatval($wallet->money);
                }

                // 获取爆机配置
                $adminUserId = $player->store_admin_id ?? null;
                if (!$adminUserId) {
                    return;
                }

                $crashSetting = \addons\webman\model\StoreSetting::getSetting(
                    'machine_crash_amount',
                    $player->department_id,
                    null,
                    $adminUserId
                );

                // 如果没有配置或配置被禁用，不处理
                if (!$crashSetting || $crashSetting->status != 1) {
                    return;
                }

                $crashAmount = $crashSetting->num ?? 0;
                if ($crashAmount <= 0) {
                    return;
                }

                // 检查爆机状态变化
                $wasCrashed = $previousAmount >= $crashAmount;
                $isCrashed = $currentAmount >= $crashAmount;

                // 更新爆机状态字段（如果状态有变化）
                if ($wallet->is_crashed != $isCrashed) {
                    // 使用 withoutEvents 避免递归触发 updated 事件
                    $wallet->withoutEvents(function () use ($wallet, $isCrashed) {
                        $wallet->is_crashed = $isCrashed;
                        $wallet->save();
                    });

                    // 🚀 优化：清除爆机状态缓存，确保下次检查时使用最新状态
                    try {
                        clearMachineCrashCache($wallet->player_id);

                        \support\Log::info('PlayerPlatformCash: 自动清除爆机缓存', [
                            'player_id' => $wallet->player_id,
                            'old_status' => $wasCrashed ? '已爆机' : '未爆机',
                            'new_status' => $isCrashed ? '已爆机' : '未爆机',
                            'current_amount' => $currentAmount,
                            'crash_amount' => $crashAmount,
                        ]);
                    } catch (\Exception $e) {
                        \support\Log::error('PlayerPlatformCash: 清除爆机缓存失败', [
                            'player_id' => $wallet->player_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // 从未爆机变为爆机 -> 发送爆机通知
                if (!$wasCrashed && $isCrashed) {
                    $crashInfo = [
                        'crashed' => true,
                        'crash_amount' => $crashAmount,
                        'current_amount' => $currentAmount,
                    ];
                    notifyMachineCrash($player, $crashInfo);
                }

                // 从爆机变为未爆机 -> 发送解锁通知
                if ($wasCrashed && !$isCrashed) {
                    checkAndNotifyCrashUnlock($player, $previousAmount);
                }
            } catch (\Exception $e) {
                \support\Log::error('PlayerPlatformCash: Failed to check machine crash', [
                    'player_id' => $wallet->player_id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * 保存模型但不触发事件（用于从 Redis 同步到数据库时避免循环）
     *
     * @param array $options
     * @return bool
     */
    public function saveWithoutEvents(array $options = []): bool
    {
        return static::withoutEvents(function () use ($options) {
            return $this->save($options);
        });
    }
}
