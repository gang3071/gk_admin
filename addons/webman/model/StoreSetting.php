<?php

namespace addons\webman\model;

use addons\webman\traits\DataPermissions;
use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Cache;

/**
 * Class StoreSetting
 * 店家/代理配置表
 *
 * @property int id 主键
 * @property int department_id 部门/渠道id
 * @property int admin_user_id 绑定的后台账号ID（代理/店家配置使用，0表示渠道配置）
 * @property string feature 功能名称
 * @property int num 数量
 * @property string content 内容
 * @property string date_start 开始时间
 * @property string date_end 结束时间
 * @property int status 状态
 * @property string created_at 创建时间
 * @property string updated_at 最后一次修改时间
 * @property string deleted_at 删除时间
 *
 * @package addons\webman\model
 */
class StoreSetting extends Model
{
    use HasDateTimeFormatter, DataPermissions, SoftDeletes;

    protected $table = 'store_setting';

    protected $fillable = ['department_id', 'admin_user_id', 'feature', 'num', 'content', 'date_start', 'date_end', 'status'];

    //数据权限字段
    protected $dataAuth = ['department_id' => 'department_id'];

    /**
     * 关联后台账号（代理/店家）
     */
    public function adminUser()
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id', 'id');
    }

    /**
     * 模型的 "booted" 方法
     *
     * @return void
     */
    protected static function booted()
    {
        // 【调试】监听更新前事件
        static::updating(function (StoreSetting $setting) {
            \support\Log::info('[StoreSetting] 准备更新', [
                'id' => $setting->id,
                'feature' => $setting->feature,
                'dirty' => $setting->getDirty(),
                'original' => $setting->getOriginal(),
            ]);
        });

        static::created(function (StoreSetting $setting) {
            \support\Log::info('[StoreSetting] 记录已创建', ['id' => $setting->id]);
            $cacheKey = self::getCacheKey($setting->department_id, $setting->admin_user_id, $setting->feature);
            Cache::delete($cacheKey);
        });

        static::deleted(function (StoreSetting $setting) {
            \support\Log::info('[StoreSetting] 记录已删除', ['id' => $setting->id]);
            $cacheKey = self::getCacheKey($setting->department_id, $setting->admin_user_id, $setting->feature);
            Cache::delete($cacheKey);
        });

        static::updated(function (StoreSetting $setting) {
            \support\Log::info('[StoreSetting] 记录已更新', [
                'id' => $setting->id,
                'feature' => $setting->feature,
                'changes' => $setting->getChanges(),
            ]);
            $cacheKey = self::getCacheKey($setting->department_id, $setting->admin_user_id, $setting->feature);
            Cache::delete($cacheKey);

            // 处理爆机金额配置的启用/关闭
            if ($setting->feature === 'machine_crash_amount') {
                // status 变化：启用/关闭
                if ($setting->wasChanged('status')) {
                    self::handleMachineCrashConfigChange($setting);
                }
                // num 变化且当前启用：爆机金额调整
                if ($setting->wasChanged('num') && $setting->status == 1) {
                    self::handleMachineCrashAmountChange($setting);
                }
            }
        });
    }

    /**
     * 获取缓存键名
     */
    protected static function getCacheKey($departmentId, $adminUserId, $feature)
    {
        $identifier = $adminUserId ?? 'all';
        return 'store-setting-' . $departmentId . '-' . $identifier . '-' . $feature;
    }

    /**
     * 处理爆机金额配置的启用/关闭
     *
     * @param StoreSetting $setting 配置对象
     * @return void
     */
    protected static function handleMachineCrashConfigChange(StoreSetting $setting): void
    {
        try {
            $previousStatus = $setting->getOriginal('status');
            $currentStatus = $setting->status;
            $crashAmount = floatval($setting->num ?? 0);

            // 配置无效（金额 <= 0）则跳过
            if ($crashAmount <= 0) {
                return;
            }

            // 获取该店家的所有设备
            if (!$setting->admin_user_id) {
                \support\Log::warning('StoreSetting: machine_crash_amount config has no admin_user_id', [
                    'setting_id' => $setting->id,
                ]);
                return;
            }

            $players = Player::query()
                ->where('store_admin_id', $setting->admin_user_id)
                ->where('is_promoter', 0)
                ->where('type', Player::TYPE_PLAYER)
                ->get();

            if ($players->isEmpty()) {
                return;
            }

            // 场景1：从关闭到启用（0 → 1）
            if ($previousStatus == 0 && $currentStatus == 1) {
                foreach ($players as $player) {
                    // 获取实体机钱包
                    $wallet = PlayerPlatformCash::query()
                        ->where('player_id', $player->id)
                        ->where('platform_id', PlayerPlatformCash::PLATFORM_SELF)
                        ->first();

                    if (!$wallet) {
                        continue;
                    }

                    $currentAmount = floatval($wallet->money);

                    // 检查是否达到爆机金额
                    if ($currentAmount >= $crashAmount) {
                        // 更新爆机状态
                        if ($wallet->is_crashed != 1) {
                            $wallet->withoutEvents(function () use ($wallet) {
                                $wallet->is_crashed = 1;
                                $wallet->save();
                            });

                            // 🚀 手动清除爆机缓存（因为使用了 withoutEvents）
                            clearMachineCrashCache($player->id);

                            // 发送爆机通知
                            $crashInfo = [
                                'crashed' => true,
                                'crash_amount' => $crashAmount,
                                'current_amount' => $currentAmount,
                            ];
                            notifyMachineCrash($player, $crashInfo);

                            \support\Log::info('StoreSetting: Player crashed after enabling config', [
                                'player_id' => $player->id,
                                'current_amount' => $currentAmount,
                                'crash_amount' => $crashAmount,
                            ]);
                        }
                    }
                }
            }

            // 场景2：从启用到关闭（1 → 0）
            if ($previousStatus == 1 && $currentStatus == 0) {
                foreach ($players as $player) {
                    // 获取实体机钱包
                    $wallet = PlayerPlatformCash::query()
                        ->where('player_id', $player->id)
                        ->where('platform_id', PlayerPlatformCash::PLATFORM_SELF)
                        ->first();

                    if (!$wallet) {
                        continue;
                    }

                    // 检查是否处于爆机状态
                    if ($wallet->is_crashed == 1) {
                        $currentAmount = floatval($wallet->money);

                        // 解锁爆机状态
                        $wallet->withoutEvents(function () use ($wallet) {
                            $wallet->is_crashed = 0;
                            $wallet->save();
                        });

                        // 🚀 手动清除爆机缓存（因为使用了 withoutEvents）
                        clearMachineCrashCache($player->id);

                        // 直接发送解锁通知（不能用 checkAndNotifyCrashUnlock，因为配置已关闭）
                        try {
                            // 玩家端消息
                            $playerMessage = [
                                'msg_type' => 'machine_crash_unlock',
                                'player_id' => $player->id,
                                'crash_amount' => $crashAmount,
                                'current_amount' => $currentAmount,
                                'message' => '✓ 您的设备爆机状态已解除，可继续正常使用。',
                                'timestamp' => time(),
                            ];
                            // 发送给玩家
                            $playerChannel = 'player-' . $player->id;
                            sendSocketMessage($playerChannel, $playerMessage);
                        } catch (\Exception $e) {
                            \support\Log::error('StoreSetting: Failed to send unlock notification', [
                                'player_id' => $player->id,
                                'error' => $e->getMessage(),
                            ]);
                        }

                        \support\Log::info('StoreSetting: Player unlocked after disabling config', [
                            'player_id' => $player->id,
                            'amount' => $currentAmount,
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            \support\Log::error('StoreSetting: Failed to handle machine crash config change', [
                'setting_id' => $setting->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 处理爆机金额调整
     *
     * @param StoreSetting $setting 配置对象
     * @return void
     */
    protected static function handleMachineCrashAmountChange(StoreSetting $setting): void
    {
        try {
            $previousAmount = floatval($setting->getOriginal('num') ?? 0);
            $currentAmount = floatval($setting->num ?? 0);

            // 配置无效（金额 <= 0）则跳过
            if ($currentAmount <= 0) {
                return;
            }

            // 获取该店家的所有设备
            if (!$setting->admin_user_id) {
                return;
            }

            $players = Player::query()
                ->where('store_admin_id', $setting->admin_user_id)
                ->where('is_promoter', 0)
                ->where('type', Player::TYPE_PLAYER)
                ->get();

            if ($players->isEmpty()) {
                return;
            }

            foreach ($players as $player) {
                // 获取实体机钱包
                $wallet = PlayerPlatformCash::query()
                    ->where('player_id', $player->id)
                    ->where('platform_id', PlayerPlatformCash::PLATFORM_SELF)
                    ->first();

                if (!$wallet) {
                    continue;
                }

                $balance = floatval($wallet->money);
                $wasCrashed = $wallet->is_crashed == 1;
                $shouldCrash = $balance >= $currentAmount;

                // 情况1：降低爆机金额，新设备需要爆机
                if ($currentAmount < $previousAmount && !$wasCrashed && $shouldCrash) {
                    $wallet->withoutEvents(function () use ($wallet) {
                        $wallet->is_crashed = 1;
                        $wallet->save();
                    });

                    // 🚀 手动清除爆机缓存（因为使用了 withoutEvents）
                    clearMachineCrashCache($player->id);

                    // 发送爆机通知
                    $crashInfo = [
                        'crashed' => true,
                        'crash_amount' => $currentAmount,
                        'current_amount' => $balance,
                    ];
                    notifyMachineCrash($player, $crashInfo);

                    \support\Log::info('StoreSetting: Player crashed after decreasing crash amount', [
                        'player_id' => $player->id,
                        'balance' => $balance,
                        'new_crash_amount' => $currentAmount,
                        'old_crash_amount' => $previousAmount,
                    ]);
                }

                // 情况2：提高爆机金额，已爆机设备需要解锁
                if ($currentAmount > $previousAmount && $wasCrashed && !$shouldCrash) {
                    $wallet->withoutEvents(function () use ($wallet) {
                        $wallet->is_crashed = 0;
                        $wallet->save();
                    });

                    // 🚀 手动清除爆机缓存（因为使用了 withoutEvents）
                    clearMachineCrashCache($player->id);

                    // 直接发送解锁通知
                    try {
                        // 玩家端消息
                        $playerMessage = [
                            'msg_type' => 'machine_crash_unlock',
                            'player_id' => $player->id,
                            'crash_amount' => $currentAmount,
                            'current_amount' => $balance,
                            'message' => '✓ 您的设备爆机状态已解除，可继续正常使用。',
                            'timestamp' => time(),
                        ];
                        // 发送给玩家
                        $playerChannel = 'player-' . $player->id;
                        sendSocketMessage($playerChannel, $playerMessage);
                    } catch (\Exception $e) {
                        \support\Log::error('StoreSetting: Failed to send unlock notification', [
                            'player_id' => $player->id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    \support\Log::info('StoreSetting: Player unlocked after increasing crash amount', [
                        'player_id' => $player->id,
                        'balance' => $balance,
                        'new_crash_amount' => $currentAmount,
                        'old_crash_amount' => $previousAmount,
                    ]);
                }
            }
        } catch (\Exception $e) {
            \support\Log::error('StoreSetting: Failed to handle machine crash amount change', [
                'setting_id' => $setting->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 获取配置（带优先级）
     * 优先级：具体代理/店家 > 渠道配置 > 总配置
     *
     * @param string $feature 功能名称
     * @param int $departmentId 部门ID
     * @param int|null $playerId 玩家ID（已废弃，保留参数兼容性）
     * @param int|null $adminUserId 后台账号ID（代理/店家）
     * @return StoreSetting|null
     */
    public static function getSetting($feature, $departmentId = 0, $playerId = null, $adminUserId = null)
    {
        $cacheKey = self::getCacheKey($departmentId, $adminUserId, $feature);

        // 尝试从缓存获取
        $setting = Cache::get($cacheKey);

        if ($setting !== null) {
            return $setting;
        }

        // 查询配置（按优先级排序）
        $setting = self::where('feature', $feature)
            ->where('status', 1)
            ->where(function($query) use ($departmentId, $adminUserId) {
                // 具体代理/店家配置（admin_user_id）
                $query->where(function($q) use ($departmentId, $adminUserId) {
                    if ($adminUserId) {
                        $q->where('department_id', $departmentId)
                          ->where('admin_user_id', $adminUserId);
                    }
                })
                // 渠道配置
                ->orWhere(function($q) use ($departmentId) {
                    if ($departmentId) {
                        $q->where('department_id', $departmentId)
                          ->where('admin_user_id', 0);
                    }
                })
                // 总配置
                ->orWhere(function($q) {
                    $q->where('department_id', 0)
                      ->where('admin_user_id', 0);
                });
            })
            ->orderByRaw("
                CASE
                    WHEN admin_user_id > 0 THEN 1
                    WHEN department_id != 0 THEN 2
                    ELSE 3
                END
            ")
            ->first();

        // 缓存结果（包括 null 值，避免缓存穿透）
        Cache::set($cacheKey, $setting, 3600);

        return $setting;
    }
}