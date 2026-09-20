<?php

namespace addons\webman\service;

use addons\webman\model\Player;
use addons\webman\model\PlayerPoints;
use addons\webman\model\PlayerPointsRecord;
use Exception;
use support\Db;
use support\Log;
use support\Redis;

/**
 * 玩家积分服务（gk_admin）
 *
 * 功能：
 * - 查询玩家积分
 * - 增加/扣除积分
 * - 冻结/解冻积分
 * - 查询积分记录
 *
 * @author Claude Code
 * @date 2026-09-08
 */
class PlayerPointsService
{
    /**
     * 获取玩家积分
     *
     * @param int $playerId
     * @return array
     */
    public static function getPlayerPoints(int $playerId): array
    {
        $playerPoints = PlayerPoints::where('player_id', $playerId)->first();

        if (!$playerPoints) {
            return [
                'available_points' => 0,
                'frozen_points' => 0,
                'total_points' => 0,
                'used_points' => 0,
            ];
        }

        return [
            'available_points' => $playerPoints->available_points,
            'frozen_points' => $playerPoints->frozen_points,
            'total_points' => $playerPoints->total_points,
            'used_points' => $playerPoints->used_points,
        ];
    }

    /**
     * 增加积分
     *
     * @param int $playerId 玩家ID
     * @param int $points 积分数量
     * @param string $remark 备注
     * @param array $adminInfo 操作人员信息 ['admin_id', 'admin_name', 'admin_ip']
     * @return array
     * @throws Exception
     */
    public static function addPoints(
        int $playerId,
        int $points,
        string $remark,
        array $adminInfo,
        ?int $type = null,
        ?string $source = null,
        bool $updateTotal = true
    ): array {
        $type = $type ?? PlayerPointsRecord::TYPE_POINTS_ADD;
        $source = $source ?? PlayerPointsRecord::SOURCE_POINTS;

        if ($points <= 0) {
            throw new Exception(admin_trans('player_points.message.invalid_points_amount'));
        }

        Db::beginTransaction();

        try {
            // 获取玩家信息
            $player = Player::find($playerId);
            if (!$player) {
                throw new Exception(admin_trans('player_points.message.player_not_found'));
            }

            // 获取或创建积分记录
            $playerPoints = PlayerPoints::getOrCreate($playerId, $player->department_id ?? 0);

            // 记录变动前积分
            $pointsBefore = $playerPoints->available_points;

            // 使用乐观锁更新
            $currentVersion = $playerPoints->version;
            $updateData = [
                'available_points' => Db::raw('available_points + ' . (int)$points),
                'version' => $currentVersion + 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($updateTotal) {
                $updateData['total_points'] = Db::raw('total_points + ' . (int)$points);
            }
            $affected = PlayerPoints::where('id', $playerPoints->id)
                ->where('version', $currentVersion)
                ->update($updateData);

            if ($affected === 0) {
                throw new Exception(admin_trans('player_points.message.add_failed'));
            }

            // 刷新数据
            $playerPoints->refresh();

            // 创建记录
            PlayerPointsRecord::create([
                'player_id' => $playerId,
                'department_id' => $playerPoints->department_id,
                'type' => $type,
                'source' => $source,
                'points' => $points,
                'points_before' => $pointsBefore,
                'points_after' => $playerPoints->available_points,
                'remark' => $remark,
                'admin_id' => $adminInfo['admin_id'] ?? null,
                'admin_name' => $adminInfo['admin_name'] ?? null,
                'admin_ip' => $adminInfo['admin_ip'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Db::commit();

            // 同步 Redis 缓存
            self::syncPointsCache($playerId);

            return [
                'points_added' => $points,
                'total_points' => $playerPoints->total_points,
                'available_points' => $playerPoints->available_points,
            ];

        } catch (Exception $e) {
            Db::rollBack();
            Log::error('[积分] 增加积分失败', [
                'player_id' => $playerId,
                'points' => $points,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 扣除积分
     *
     * @param int $playerId
     * @param int $points
     * @param string $remark
     * @param array $adminInfo
     * @return bool
     * @throws Exception
     */
    public static function deductPoints(
        int $playerId,
        int $points,
        string $remark,
        array $adminInfo,
        ?int $type = null,
        ?string $source = null
    ): bool {
        $type = $type ?? PlayerPointsRecord::TYPE_POINTS_DEDUCT;
        $source = $source ?? PlayerPointsRecord::SOURCE_POINTS;

        if ($points <= 0) {
            throw new Exception(admin_trans('player_points.message.invalid_points_amount'));
        }

        Db::beginTransaction();

        try {
            $playerPoints = PlayerPoints::where('player_id', $playerId)->first();

            if (!$playerPoints) {
                throw new Exception(admin_trans('player_points.message.points_not_found'));
            }

            if ($playerPoints->available_points < $points) {
                throw new Exception(admin_trans('player_points.message.insufficient_points'));
            }

            $pointsBefore = $playerPoints->available_points;

            // 使用乐观锁更新
            $currentVersion = $playerPoints->version;
            $affected = PlayerPoints::where('id', $playerPoints->id)
                ->where('version', $currentVersion)
                ->where('available_points', '>=', $points)
                ->update([
                    'available_points' => Db::raw('available_points - ' . (int)$points),
                    'used_points' => Db::raw('used_points + ' . (int)$points),
                    'version' => $currentVersion + 1,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            if ($affected === 0) {
                throw new Exception(admin_trans('player_points.message.deduct_failed'));
            }

            // 创建记录
            PlayerPointsRecord::create([
                'player_id' => $playerId,
                'department_id' => $playerPoints->department_id,
                'type' => $type,
                'source' => $source,
                'points' => -$points,
                'points_before' => $pointsBefore,
                'points_after' => $pointsBefore - $points,
                'remark' => $remark,
                'admin_id' => $adminInfo['admin_id'] ?? null,
                'admin_name' => $adminInfo['admin_name'] ?? null,
                'admin_ip' => $adminInfo['admin_ip'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Db::commit();

            // 同步 Redis 缓存
            self::syncPointsCache($playerId);

            return true;

        } catch (Exception $e) {
            Db::rollBack();
            Log::error('[积分] 扣除积分失败', [
                'player_id' => $playerId,
                'points' => $points,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 冻结积分
     *
     * @param int $playerId
     * @param int $points
     * @param string $remark
     * @return bool
     * @throws Exception
     */
    public static function freezePoints(
        int $playerId,
        int $points,
        string $remark,
        array $adminInfo = [],
        ?int $type = null,
        ?string $source = null
    ): bool {
        $type = $type ?? PlayerPointsRecord::TYPE_POINTS_FREEZE;
        $source = $source ?? PlayerPointsRecord::SOURCE_POINTS;

        if ($points <= 0) {
            throw new Exception(admin_trans('player_points.message.invalid_points_amount'));
        }

        Db::beginTransaction();

        try {
            $playerPoints = PlayerPoints::where('player_id', $playerId)->first();

            if (!$playerPoints) {
                throw new Exception(admin_trans('player_points.message.points_not_found'));
            }

            if ($playerPoints->available_points < $points) {
                throw new Exception(admin_trans('player_points.message.insufficient_points'));
            }

            $availableBefore = $playerPoints->available_points;

            // 使用乐观锁更新
            $currentVersion = $playerPoints->version;
            $affected = PlayerPoints::where('id', $playerPoints->id)
                ->where('version', $currentVersion)
                ->where('available_points', '>=', $points)
                ->update([
                    'available_points' => Db::raw('available_points - ' . (int)$points),
                    'frozen_points' => Db::raw('frozen_points + ' . (int)$points),
                    'version' => $currentVersion + 1,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            if ($affected === 0) {
                throw new Exception(admin_trans('player_points.message.freeze_failed'));
            }

            // 创建记录
            PlayerPointsRecord::create([
                'player_id' => $playerId,
                'department_id' => $playerPoints->department_id,
                'type' => $type,
                'source' => $source,
                'points' => -$points,
                'points_before' => $availableBefore,
                'points_after' => $availableBefore - $points,
                'remark' => admin_trans('player_points.action.freeze_points') . ' - ' . $remark,
                'admin_id' => $adminInfo['admin_id'] ?? null,
                'admin_name' => $adminInfo['admin_name'] ?? null,
                'admin_ip' => $adminInfo['admin_ip'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Db::commit();

            // 同步 Redis 缓存
            self::syncPointsCache($playerId);

            return true;

        } catch (Exception $e) {
            Db::rollBack();
            Log::error('[积分] 冻结积分失败', [
                'player_id' => $playerId,
                'points' => $points,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 解冻积分
     *
     * @param int $playerId
     * @param int $points
     * @param string $remark
     * @return bool
     * @throws Exception
     */
    public static function unfreezePoints(
        int $playerId,
        int $points,
        string $remark,
        array $adminInfo = [],
        ?int $type = null,
        ?string $source = null
    ): bool {
        $type = $type ?? PlayerPointsRecord::TYPE_POINTS_UNFREEZE;
        $source = $source ?? PlayerPointsRecord::SOURCE_POINTS;

        if ($points <= 0) {
            throw new Exception(admin_trans('player_points.message.invalid_points_amount'));
        }

        Db::beginTransaction();

        try {
            $playerPoints = PlayerPoints::where('player_id', $playerId)->first();

            if (!$playerPoints) {
                throw new Exception(admin_trans('player_points.message.points_not_found'));
            }

            if ($playerPoints->frozen_points < $points) {
                throw new Exception(admin_trans('player_points.message.insufficient_frozen_points'));
            }

            $availableBefore = $playerPoints->available_points;

            // 使用乐观锁更新（解冻到可用）
            $currentVersion = $playerPoints->version;
            $affected = PlayerPoints::where('id', $playerPoints->id)
                ->where('version', $currentVersion)
                ->where('frozen_points', '>=', $points)
                ->update([
                    'frozen_points' => Db::raw('frozen_points - ' . (int)$points),
                    'available_points' => Db::raw('available_points + ' . (int)$points),
                    'version' => $currentVersion + 1,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            if ($affected === 0) {
                throw new Exception(admin_trans('player_points.message.unfreeze_failed'));
            }

            // 创建记录
            PlayerPointsRecord::create([
                'player_id' => $playerId,
                'department_id' => $playerPoints->department_id,
                'type' => $type,
                'source' => $source,
                'points' => $points,
                'points_before' => $availableBefore,
                'points_after' => $availableBefore + $points,
                'remark' => admin_trans('player_points.action.unfreeze_points') . ' - ' . $remark,
                'admin_id' => $adminInfo['admin_id'] ?? null,
                'admin_name' => $adminInfo['admin_name'] ?? null,
                'admin_ip' => $adminInfo['admin_ip'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Db::commit();

            // 同步 Redis 缓存
            self::syncPointsCache($playerId);

            return true;

        } catch (Exception $e) {
            Db::rollBack();
            Log::error('[积分] 解冻积分失败', [
                'player_id' => $playerId,
                'points' => $points,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 查询积分记录
     *
     * @param int $playerId
     * @param int $page
     * @param int $size
     * @param int|null $type
     * @param array $permissionFilter 权限过滤条件 ['department_id' => xxx, 'agent_admin_id' => xxx, 'store_admin_id' => xxx]
     * @return array
     */
    public static function getRecords(
        int $playerId,
        int $page = 1,
        int $size = 20,
        ?int $type = null,
        array $permissionFilter = []
    ): array {
        $query = PlayerPointsRecord::where('player_id', $playerId);

        if ($type !== null) {
            $query->where('type', $type);
        }

        // 应用数据权限过滤（如果提供）
        if (!empty($permissionFilter)) {
            if (isset($permissionFilter['department_id'])) {
                $query->where('department_id', $permissionFilter['department_id']);
            }
            // 如果需要按代理或门店过滤，需要JOIN player表
            if (isset($permissionFilter['agent_admin_id']) || isset($permissionFilter['store_admin_id'])) {
                $query->join('player', 'player_points_record.player_id', '=', 'player.id');

                if (isset($permissionFilter['agent_admin_id'])) {
                    $query->where('player.agent_admin_id', $permissionFilter['agent_admin_id']);
                }

                if (isset($permissionFilter['store_admin_id'])) {
                    $query->where('player.store_admin_id', $permissionFilter['store_admin_id']);
                }

                // 避免字段重复，指定返回字段
                $query->select('player_points_record.*');
            }
        }

        $total = $query->count();
        $records = $query->orderBy('player_points_record.created_at', 'desc')
            ->forPage($page, $size)
            ->get();

        return [
            'total' => $total,
            'list' => $records,
        ];
    }

    /**
     * 同步积分到 Redis 缓存（与 gk_api PlayerPointsService 保持一致）
     *
     * @param int $playerId
     */
    private static function syncPointsCache(int $playerId): void
    {
        try {
            $playerPoints = PlayerPoints::where('player_id', $playerId)->first();
            if (!$playerPoints) {
                return;
            }

            $redis = Redis::connection()->client();
            $key = 'gk_api:player_points:' . $playerId;
            $redis->hMSet($key, [
                'available_points' => (int)$playerPoints->available_points,
                'frozen_points' => (int)$playerPoints->frozen_points,
                'total_points' => (int)$playerPoints->total_points,
                'last_update' => time(),
            ]);
            $redis->expire($key, 86400 * 365);
        } catch (\Throwable $e) {
            Log::warning('[积分] Redis缓存同步失败', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
