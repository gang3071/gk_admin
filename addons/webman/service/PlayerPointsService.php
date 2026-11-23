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
        // ✅ 优先读 Redis：打码积分实时累加在 Redis（gk_api），MySQL 每分钟才同步一次，
        // 只读 MySQL 会拿到滞后余额（推广积分扣回曾因此被静默跳过）
        try {
            $redis = Redis::connection()->client();
            $data = $redis->hGetAll('gk_api:player_points:' . $playerId);

            if (!empty($data) && isset($data['available_points'])) {
                return [
                    'available_points' => round(floatval($data['available_points'] ?? 0), 4),
                    'frozen_points' => round(floatval($data['frozen_points'] ?? 0), 4),
                    'total_points' => round(floatval($data['total_points'] ?? 0), 4),
                    'used_points' => round(floatval($data['used_points'] ?? 0), 4),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('[积分] Redis余额读取失败，回退MySQL', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
        }

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
     * @param float $points 积分数量（保留4位小数）
     * @param string $remark 备注
     * @param array $adminInfo 操作人员信息 ['admin_id', 'admin_name', 'admin_ip']
     * @param string|null $batchId 幂等批次ID（player_points_record.batch_id 唯一索引保障，重复调用跳过）
     * @return array
     * @throws Exception
     */
    public static function addPoints(
        int $playerId,
        float $points,
        string $remark,
        array $adminInfo,
        ?int $type = null,
        ?string $source = null,
        bool $updateTotal = true,
        ?string $batchId = null
    ): array {
        $type = $type ?? PlayerPointsRecord::TYPE_POINTS_ADD;
        $source = $source ?? PlayerPointsRecord::SOURCE_POINTS;

        if ($points <= 0) {
            throw new Exception(admin_trans('player_points.message.invalid_points_amount'));
        }

        // 保留4位小数
        $points = round($points, 4);

        // 幂等：同一 batch_id 只入账一次（补算任务重跑防重发）
        if ($batchId !== null && PlayerPointsRecord::where('batch_id', $batchId)->exists()) {
            Log::info('[积分] batch_id已存在，跳过重复入账', [
                'player_id' => $playerId,
                'batch_id' => $batchId,
            ]);
            $current = self::getPlayerPoints($playerId);
            return [
                'points_added' => 0,
                'total_points' => $current['total_points'],
                'available_points' => $current['available_points'],
            ];
        }

        // 先把 Redis 未同步的打码积分落账，保证变动前后数值准确
        self::flushPlayerToMySQL($playerId);

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

            // 使用乐观锁更新（积分保留4位小数，须格式化为定点数拼进 SQL）
            $pointsStr = number_format($points, 4, '.', '');
            $currentVersion = $playerPoints->version;
            $updateData = [
                'available_points' => Db::raw('available_points + ' . $pointsStr),
                'version' => $currentVersion + 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($updateTotal) {
                $updateData['total_points'] = Db::raw('total_points + ' . $pointsStr);
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
                'batch_id' => $batchId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Db::commit();

            // 同步 Redis 缓存（按增量，避免覆盖未同步的打码积分）
            self::syncPointsCache($playerId, [
                'available_points' => $points,
                'total_points' => $updateTotal ? $points : 0,
            ]);

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
     * @param float $points 积分数量（保留4位小数）
     * @param string $remark
     * @param array $adminInfo
     * @param string|null $batchId 幂等批次ID（player_points_record.batch_id 唯一索引保障，重复调用跳过）
     * @return bool
     * @throws Exception
     */
    public static function deductPoints(
        int $playerId,
        float $points,
        string $remark,
        array $adminInfo,
        ?int $type = null,
        ?string $source = null,
        ?string $batchId = null
    ): bool {
        $type = $type ?? PlayerPointsRecord::TYPE_POINTS_DEDUCT;
        $source = $source ?? PlayerPointsRecord::SOURCE_POINTS;

        if ($points <= 0) {
            throw new Exception(admin_trans('player_points.message.invalid_points_amount'));
        }

        // 保留4位小数
        $points = round($points, 4);

        // 幂等：同一 batch_id 只扣一次（补算任务重跑防重复扣）
        if ($batchId !== null && PlayerPointsRecord::where('batch_id', $batchId)->exists()) {
            Log::info('[积分] batch_id已存在，跳过重复扣除', [
                'player_id' => $playerId,
                'batch_id' => $batchId,
            ]);
            return true;
        }

        // 先把 Redis 未同步的打码积分落账，否则余额不足检查会读到滞后值
        self::flushPlayerToMySQL($playerId);

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


            // 使用乐观锁更新（积分保留4位小数，须格式化为定点数拼进 SQL）
            $pointsStr = number_format($points, 4, '.', '');
            $currentVersion = $playerPoints->version;
            $affected = PlayerPoints::where('id', $playerPoints->id)
                ->where('version', $currentVersion)
                ->where('available_points', '>=', $points)
                ->update([
                    'available_points' => Db::raw('available_points - ' . $pointsStr),
                    'used_points' => Db::raw('used_points + ' . $pointsStr),
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
                'batch_id' => $batchId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            Db::commit();

            // 同步 Redis 缓存（按增量，避免覆盖未同步的打码积分）
            self::syncPointsCache($playerId, [
                'available_points' => -$points,
                'used_points' => $points,
            ]);

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

        // 先把 Redis 未同步的打码积分落账，否则余额不足检查会读到滞后值
        self::flushPlayerToMySQL($playerId);

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

            // 同步 Redis 缓存（按增量，避免覆盖未同步的打码积分）
            self::syncPointsCache($playerId, [
                'available_points' => -$points,
                'frozen_points' => $points,
            ]);

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

        // 先把 Redis 未同步的打码积分落账，保证变动前后数值准确
        self::flushPlayerToMySQL($playerId);

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

            // 同步 Redis 缓存（按增量，避免覆盖未同步的打码积分）
            self::syncPointsCache($playerId, [
                'frozen_points' => -$points,
                'available_points' => $points,
            ]);

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
     * @param int $playerId 玩家ID；传 0 表示查询全部玩家
     * @param int $page
     * @param int $size
     * @param int|null $type
     * @param array $permissionFilter 权限过滤条件 ['department_id' => xxx, 'agent_admin_id' => xxx, 'store_admin_id' => xxx]
     * @param bool $withPlayer 是否载入玩家信息（查询全部玩家时需要）
     * @return array
     */
    public static function getRecords(
        int $playerId = 0,
        int $page = 1,
        int $size = 20,
        ?int $type = null,
        array $permissionFilter = [],
        bool $withPlayer = false,
        ?string $uuid = null
    ): array {
        $query = PlayerPointsRecord::query();

        // playerId > 0：查询单一玩家；playerId = 0：查询全部玩家
        if ($playerId > 0) {
            $query->where('player_id', $playerId);
        }

        if ($type !== null) {
            // 限定表名：JOIN player 后 player 表也有 type 列，裸列名会报
            // "Column 'type' in where clause is ambiguous"，导致按类型搜索查不到任何数据
            $query->where('player_points_record.type', $type);
        }

        if (!empty($uuid)) {
            $query->whereHas('player', function ($q) use ($uuid) {
                $q->where('uuid', $uuid);
            });
        }

        // 查询全部玩家时，预载玩家信息，避免逐笔查询
        if ($withPlayer) {
            $query->with(['player' => function ($q) {
                $q->select('id', 'name', 'uuid', 'account');
            }]);
        }

        // 应用数据权限过滤（如果提供）
        if (!empty($permissionFilter)) {
            if (isset($permissionFilter['department_id'])) {
                // 限定表名，避免与 join 的 player 表字段冲突（department_id 两边都有）
                $query->where('player_points_record.department_id', $permissionFilter['department_id']);
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
     * 将玩家 Redis 积分快照落账到 MySQL（增扣前调用，对齐未同步的打码积分）
     *
     * 打码积分实时累加在 Redis、每分钟由 gk_api 同步 MySQL。增扣积分前先调用本方法，
     * 否则余额不足检查会读到滞后值（推广积分扣回曾因此被静默跳过）。
     * 语义与 gk_api syncDirtyPlayersToMySQL 单玩家版一致：MySQL := Redis。
     */
    public static function flushPlayerToMySQL(int $playerId): void
    {
        try {
            $redis = Redis::connection()->client();
            $data = $redis->hGetAll('gk_api:player_points:' . $playerId);

            if (empty($data) || !isset($data['available_points'])) {
                return; // Redis 无快照，MySQL 即为准
            }

            $player = Player::find($playerId);
            if (!$player) {
                return;
            }

            $playerPoints = PlayerPoints::getOrCreate($playerId, $player->department_id ?? 0);

            for ($i = 0; $i < 3; $i++) {
                $playerPoints->refresh();
                $currentVersion = $playerPoints->version;

                $affected = PlayerPoints::where('id', $playerPoints->id)
                    ->where('version', $currentVersion)
                    ->update([
                        'available_points' => round(floatval($data['available_points'] ?? 0), 4),
                        'frozen_points' => round(floatval($data['frozen_points'] ?? 0), 4),
                        'total_points' => round(floatval($data['total_points'] ?? 0), 4),
                        'used_points' => round(floatval($data['used_points'] ?? 0), 4),
                        'version' => $currentVersion + 1,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                if ($affected > 0) {
                    return;
                }
            }

            Log::warning('[积分] Redis快照落账MySQL持续冲突，跳过（后续增扣自带乐观锁兜底）', [
                'player_id' => $playerId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[积分] Redis快照落账MySQL失败', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 同步积分到 Redis 缓存（与 gk_api PlayerPointsService 保持一致）
     *
     * ⚠️ 必须按增量更新：Redis 里可能有尚未同步到 MySQL 的打码积分累加，
     * 整体覆盖（hMSet MySQL 绝对值）会把这部分积分抹掉。
     * 仅在 Redis 无快照时才用 MySQL 绝对值初始化。
     *
     * @param int $playerId
     * @param array $deltas 字段增量 ['available_points' => +5, 'total_points' => +5, ...]
     */
    private static function syncPointsCache(int $playerId, array $deltas = []): void
    {
        try {
            $playerPoints = PlayerPoints::where('player_id', $playerId)->first();
            if (!$playerPoints) {
                return;
            }

            $redis = Redis::connection()->client();
            $key = 'gk_api:player_points:' . $playerId;

            if ($redis->exists($key)) {
                foreach ($deltas as $field => $delta) {
                    if ($delta != 0) {
                        $redis->hIncrByFloat($key, $field, $delta);
                    }
                }
                $redis->hSet($key, 'last_update', time());
            } else {
                // ⚠️ 不可转 int：积分为 decimal(14,4)，截断后 Redis 与 MySQL 产生小数漂移，
                //    脏数据同步（MySQL := Redis）会把小数部分永久抹掉
                $redis->hMSet($key, [
                    'available_points' => number_format($playerPoints->available_points, 4, '.', ''),
                    'frozen_points' => number_format($playerPoints->frozen_points, 4, '.', ''),
                    'total_points' => number_format($playerPoints->total_points, 4, '.', ''),
                    'used_points' => number_format($playerPoints->used_points, 4, '.', ''),
                    'last_update' => time(),
                ]);
            }
            $redis->expire($key, 86400 * 365);
        } catch (\Throwable $e) {
            Log::warning('[积分] Redis缓存同步失败', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
