<?php

namespace addons\webman\service;

use addons\webman\model\PlayerPlatformCash;
use support\Log;
use support\Redis;

/**
 * 钱包服务 - Redis 缓存版本
 *
 * 提供高性能的钱包余额查询和更新功能
 * - 使用 Redis 缓存减少数据库查询
 * - 自动降级到数据库（Redis 故障时）
 * - 通过模型事件自动同步缓存
 */
class WalletService
{
    /**
     * 缓存键前缀（与 Lua 原子脚本统一）
     * 修改说明：统一使用 wallet:balance:{player_id} 格式
     * 与 gk_work RedisLuaScripts 保持一致，避免缓存不一致
     */
    private const CACHE_PREFIX = 'wallet:balance:';

    /**
     * 缓存过期时间（秒）
     * 1小时，足够长以提高命中率，余额通过模型事件自动同步保证一致性
     */
    private const CACHE_TTL = 3600;

    /**
     * 获取玩家余额（带 Redis 缓存）
     *
     * @param int $playerId 玩家ID
     * @param int $platformId 平台ID，默认1（实体机平台）
     * @param bool $forceRefresh 是否强制刷新缓存
     * @return float 余额
     */
    public static function getBalance(int $playerId, int $platformId = 1, bool $forceRefresh = false): float
    {
        // 🚨 紧急开关：缓存被禁用时直接查询数据库
        if (!self::isCacheEnabled()) {
            return self::getBalanceFromDB($playerId, $platformId);
        }

        $cacheKey = self::getCacheKey($playerId, $platformId);

        try {
            // 如果不是强制刷新，先尝试从缓存读取
            if (!$forceRefresh) {
                $cached = Redis::get($cacheKey);
                if ($cached !== null && $cached !== false) {
                    return (float)$cached;
                }
            }

            // 缓存未命中或强制刷新，从数据库读取
            $balance = self::getBalanceFromDB($playerId, $platformId);

            // 更新缓存
            Redis::setex($cacheKey, self::CACHE_TTL, $balance);

            return $balance;
        } catch (\Throwable $e) {
            // Redis 异常时自动降级到数据库
            Log::warning('WalletService: Redis failed, fallback to DB', [
                'player_id' => $playerId,
                'platform_id' => $platformId,
                'error' => $e->getMessage(),
            ]);

            return self::getBalanceFromDB($playerId, $platformId);
        }
    }

    /**
     * 🚨 紧急开关：禁用 Redis 缓存
     * 在 .env 中设置 WALLET_CACHE_ENABLED=false 可立即禁用缓存
     * 用于紧急情况下快速回滚到纯数据库查询
     */
    private static function isCacheEnabled(): bool
    {
        return env('WALLET_CACHE_ENABLED', true);
    }

    /**
     * 从数据库获取余额
     *
     * @param int $playerId
     * @param int $platformId
     * @return float
     */
    private static function getBalanceFromDB(int $playerId, int $platformId): float
    {
        $wallet = PlayerPlatformCash::query()
            ->where('player_id', $playerId)
            ->where('platform_id', $platformId)
            ->first();

        return $wallet ? (float)$wallet->money : 0.0;
    }

    /**
     * 生成缓存键（包含版本号）
     *
     * @param int $playerId
     * @param int $platformId
     * @return string
     */
    /**
     * 获取缓存键（与 Lua 原子脚本统一格式）
     *
     * @param int $playerId 玩家ID
     * @param int $platformId 平台ID（保留参数兼容性，实际不使用）
     * @return string Redis 缓存键
     */
    private static function getCacheKey(int $playerId, int $platformId): string
    {
        // 统一使用 wallet:balance:{player_id} 格式
        // 与 gk_work RedisLuaScripts::atomicBet/atomicSettle 保持一致
        return self::CACHE_PREFIX . $playerId;
    }

    /**
     * 更新缓存（由模型事件自动调用）
     *
     * @param int $playerId
     * @param int $platformId
     * @param float $balance
     * @return bool
     */
    public static function updateCache(int $playerId, int $platformId, float $balance): bool
    {
        try {
            $cacheKey = self::getCacheKey($playerId, $platformId);
            Redis::setex($cacheKey, self::CACHE_TTL, $balance);
            return true;
        } catch (\Throwable $e) {
            Log::warning('WalletService: Failed to update cache', [
                'player_id' => $playerId,
                'platform_id' => $platformId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 清除缓存
     *
     * @param int $playerId
     * @param int $platformId
     * @return bool
     */
    public static function clearCache(int $playerId, int $platformId = 1): bool
    {
        try {
            $cacheKey = self::getCacheKey($playerId, $platformId);
            Redis::del($cacheKey);
            return true;
        } catch (\Throwable $e) {
            Log::warning('WalletService: Failed to clear cache', [
                'player_id' => $playerId,
                'platform_id' => $platformId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 批量清除缓存
     *
     * @param array $playerIds 玩家ID数组
     * @param int $platformId 平台ID
     * @return int 成功清除的数量
     */
    public static function clearBatchCache(array $playerIds, int $platformId = 1): int
    {
        if (empty($playerIds)) {
            return 0;
        }

        try {
            $cacheKeys = [];
            foreach ($playerIds as $playerId) {
                $cacheKeys[] = self::getCacheKey($playerId, $platformId);
            }

            // 批量删除
            $deletedCount = Redis::del(...$cacheKeys);

            Log::info('WalletService: Batch cache cleared', [
                'count' => count($playerIds),
                'deleted' => $deletedCount,
                'platform_id' => $platformId,
            ]);

            return $deletedCount;

        } catch (\Throwable $e) {
            Log::warning('WalletService: Failed to clear batch cache', [
                'player_ids' => $playerIds,
                'platform_id' => $platformId,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * 批量获取余额
     *
     * @param array $playerIds 玩家ID数组
     * @param int $platformId 平台ID
     * @return array [player_id => balance]
     */
    public static function getBatchBalance(array $playerIds, int $platformId = 1): array
    {
        if (empty($playerIds)) {
            return [];
        }

        // 重建索引确保数组键是连续的 0, 1, 2...
        $playerIds = array_values($playerIds);

        $result = [];
        $missedIds = [];

        try {
            // 批量从 Redis 获取
            $cacheKeys = [];
            foreach ($playerIds as $playerId) {
                $cacheKeys[$playerId] = self::getCacheKey($playerId, $platformId);
            }

            $cached = Redis::mget(array_values($cacheKeys));

            foreach ($playerIds as $index => $playerId) {
                if (isset($cached[$index]) && $cached[$index] !== false && $cached[$index] !== null) {
                    $result[$playerId] = (float)$cached[$index];
                } else {
                    $missedIds[] = $playerId;
                }
            }
        } catch (\Throwable $e) {
            // Redis 失败，全部从数据库查询
            Log::warning('WalletService: Batch Redis failed, fallback to DB', [
                'error' => $e->getMessage(),
            ]);
            $missedIds = $playerIds;
        }

        // 从数据库补充未命中的数据
        if (!empty($missedIds)) {
            $wallets = PlayerPlatformCash::query()
                ->whereIn('player_id', $missedIds)
                ->where('platform_id', $platformId)
                ->get();

            foreach ($wallets as $wallet) {
                $balance = (float)$wallet->money;
                $result[$wallet->player_id] = $balance;

                // 回填缓存
                try {
                    $cacheKey = self::getCacheKey($wallet->player_id, $platformId);
                    Redis::setex($cacheKey, self::CACHE_TTL, $balance);
                } catch (\Throwable $e) {
                    // 忽略缓存回填失败
                }
            }

            // 补充不存在的玩家（余额为0）
            foreach ($missedIds as $playerId) {
                if (!isset($result[$playerId])) {
                    $result[$playerId] = 0.0;
                    // 缓存不存在的玩家（避免缓存穿透）
                    try {
                        $cacheKey = self::getCacheKey($playerId, $platformId);
                        Redis::setex($cacheKey, self::CACHE_TTL, 0.0);
                    } catch (\Throwable $e) {
                        // 忽略缓存回填失败
                    }
                }
            }
        }

        return $result;
    }

    /**
     * 扣款（Redis Lua 原子操作）
     *
     * 高并发场景下，Redis 是余额的唯一实时标准
     *
     * @param int $playerId
     * @param float $amount
     * @param int $platformId
     * @return float 新余额
     * @throws \Exception
     */
    public static function deduct(int $playerId, float $amount, int $platformId = 1): float
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        $result = self::atomicDecrement($playerId, $amount);

        if (isset($result['ok']) && $result['ok'] === 0) {
            throw new \Exception($result['error'] ?? 'Deduct failed');
        }

        return (float)($result['balance'] ?? 0);
    }

    /**
     * 加款（Redis Lua 原子操作）
     *
     * 高并发场景下，Redis 是余额的唯一实时标准
     *
     * @param int $playerId
     * @param float $amount
     * @param int $platformId
     * @return float 新余额
     * @throws \Exception
     */
    public static function add(int $playerId, float $amount, int $platformId = 1): float
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        return self::atomicIncrement($playerId, $amount);
    }

    /**
     * 缓存预热（批量加载玩家余额到缓存）
     *
     * @param array $playerIds 玩家ID数组
     * @param int $platformId 平台ID
     * @return array ['success' => int, 'failed' => int]
     */
    public static function warmupCache(array $playerIds, int $platformId = 1): array
    {
        if (empty($playerIds)) {
            return ['success' => 0, 'failed' => 0];
        }

        $successCount = 0;
        $failedCount = 0;

        try {
            // 从数据库批量查询
            $wallets = PlayerPlatformCash::query()
                ->whereIn('player_id', $playerIds)
                ->where('platform_id', $platformId)
                ->get(['player_id', 'money']);

            $foundPlayerIds = [];

            // 批量写入缓存
            foreach ($wallets as $wallet) {
                $balance = (float)$wallet->money;
                $foundPlayerIds[] = $wallet->player_id;

                try {
                    $cacheKey = self::getCacheKey($wallet->player_id, $platformId);
                    Redis::setex($cacheKey, self::CACHE_TTL, $balance);
                    $successCount++;
                } catch (\Throwable $e) {
                    $failedCount++;
                }
            }

            // 为不存在的玩家缓存 0 余额
            $notFoundPlayerIds = array_diff($playerIds, $foundPlayerIds);
            foreach ($notFoundPlayerIds as $playerId) {
                try {
                    $cacheKey = self::getCacheKey($playerId, $platformId);
                    Redis::setex($cacheKey, self::CACHE_TTL, 0.0);
                    $successCount++;
                } catch (\Throwable $e) {
                    $failedCount++;
                }
            }

            Log::info('WalletService: Cache warmup completed', [
                'requested' => count($playerIds),
                'success' => $successCount,
                'failed' => $failedCount,
                'platform_id' => $platformId,
            ]);

        } catch (\Throwable $e) {
            Log::warning('WalletService: Cache warmup failed', [
                'player_ids' => $playerIds,
                'platform_id' => $platformId,
                'error' => $e->getMessage(),
            ]);
            $failedCount = count($playerIds) - $successCount;
        }

        return ['success' => $successCount, 'failed' => $failedCount];
    }

    // ========================================
    // Lua 脚本原子操作（管理员充值、活动奖励等场景）
    // ========================================

    /**
     * Lua 脚本：原子性增加余额
     *
     * 用于：管理员充值、活动奖励、推荐人奖励等场景
     *
     * KEYS[1] = wallet:balance:{player_id}
     * ARGV[1] = 增加金额
     * ARGV[2] = 缓存TTL（默认3600秒）
     *
     * 返回：新余额
     */
    private const LUA_ATOMIC_INCREMENT = <<<'LUA'
local key = KEYS[1]
local amount = tonumber(ARGV[1])
local ttl = tonumber(ARGV[2]) or 3600

-- 读取当前余额
local currentBalance = tonumber(redis.call('GET', key)) or 0

-- 计算新余额
local newBalance = currentBalance + amount

-- 原子性写入
redis.call('SETEX', key, ttl, newBalance)

return newBalance
LUA;

    /**
     * Lua 脚本：原子性减少余额（带余额检查）
     *
     * 用于：管理员扣款、系统调整等场景
     *
     * KEYS[1] = wallet:balance:{player_id}
     * ARGV[1] = 减少金额
     * ARGV[2] = 缓存TTL（默认3600秒）
     *
     * 返回：
     * - 成功：{ok: 1, balance: 新余额, old_balance: 旧余额}
     * - 余额不足：{ok: 0, error: "insufficient_balance", balance: 当前余额}
     */
    private const LUA_ATOMIC_DECREMENT = <<<'LUA'
local key = KEYS[1]
local amount = tonumber(ARGV[1])
local ttl = tonumber(ARGV[2]) or 3600

-- 读取当前余额
local currentBalance = tonumber(redis.call('GET', key)) or 0

-- 余额检查
if currentBalance < amount then
    return cjson.encode({ok = 0, error = 'insufficient_balance', balance = currentBalance})
end

-- 计算新余额
local newBalance = currentBalance - amount

-- 原子性写入
redis.call('SETEX', key, ttl, newBalance)

return cjson.encode({ok = 1, balance = newBalance, old_balance = currentBalance})
LUA;

    /**
     * 原子性增加余额（管理员充值、活动奖励等场景）
     *
     * 用于：管理员人工充值、活动奖励审核、推荐人奖励等
     *
     * @param int $playerId 玩家ID
     * @param float $amount 增加金额
     * @param int $ttl 缓存TTL（秒），默认3600
     * @return float 新余额
     * @throws \RuntimeException Redis 执行失败时抛出
     */
    public static function atomicIncrement(int $playerId, float $amount, int $ttl = 3600): float
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException("增加金额必须大于0，当前值：{$amount}");
        }

        $cacheKey = self::getCacheKey($playerId, 1);

        try {
            $newBalance = Redis::eval(
                self::LUA_ATOMIC_INCREMENT,
                1,  // 1 个 KEYS 参数
                $cacheKey,  // KEYS[1]
                $amount,    // ARGV[1]
                $ttl        // ARGV[2]
            );

            if ($newBalance === false || $newBalance === null) {
                throw new \RuntimeException(
                    sprintf('[WalletService::atomicIncrement] Redis Lua 脚本执行失败。玩家ID: %d, 金额: %s',
                        $playerId, $amount)
                );
            }

            // ✅ 异步同步数据库（Redis 是实时标准，数据库用于持久化）
            self::asyncUpdateDB($playerId, (float)$newBalance);

            return (float)$newBalance;
        } catch (\Throwable $e) {
            Log::error('WalletService: atomicIncrement failed', [
                'player_id' => $playerId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 原子性减少余额（管理员扣款等场景，带余额检查）
     *
     * @param int $playerId 玩家ID
     * @param float $amount 减少金额
     * @param int $ttl 缓存TTL（秒），默认3600
     * @return array {ok: 1, balance: 新余额, old_balance: 旧余额} 或 {ok: 0, error: "insufficient_balance", balance: 当前余额}
     * @throws \RuntimeException Redis 执行失败时抛出
     */
    public static function atomicDecrement(int $playerId, float $amount, int $ttl = 3600): array
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException("减少金额必须大于0，当前值：{$amount}");
        }

        $cacheKey = self::getCacheKey($playerId, 1);

        try {
            $result = Redis::eval(
                self::LUA_ATOMIC_DECREMENT,
                1,  // 1 个 KEYS 参数
                $cacheKey,  // KEYS[1]
                $amount,    // ARGV[1]
                $ttl        // ARGV[2]
            );

            if ($result === false || $result === null) {
                throw new \RuntimeException(
                    sprintf('[WalletService::atomicDecrement] Redis Lua 脚本执行失败。玩家ID: %d, 金额: %s',
                        $playerId, $amount)
                );
            }

            // 解码 JSON
            $decoded = json_decode($result, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException(
                    sprintf('[WalletService::atomicDecrement] Redis Lua 脚本返回值解码失败: %s. 原始返回: %s',
                        json_last_error_msg(),
                        is_string($result) ? substr($result, 0, 200) : var_export($result, true))
                );
            }

            // ✅ 异步同步数据库（仅在扣款成功时）
            if (isset($decoded['ok']) && $decoded['ok'] === 1) {
                self::asyncUpdateDB($playerId, (float)$decoded['balance']);
            }

            return $decoded ?? [];
        } catch (\Throwable $e) {
            Log::error('WalletService: atomicDecrement failed', [
                'player_id' => $playerId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 异步同步数据库（非阻塞方式）
     *
     * Redis 是实时权威数据源，数据库仅用于持久化
     * 采用 fire-and-forget 模式，不等待数据库写入完成
     *
     * @param int $playerId 玩家ID
     * @param float $newBalance 新余额
     * @return void
     */
    private static function asyncUpdateDB(int $playerId, float $newBalance): void
    {
        try {
            \support\Db::table('player_platform_cash')
                ->where('player_id', $playerId)
                ->update(['money' => $newBalance]);
        } catch (\Throwable $e) {
            // 数据库同步失败不影响 Redis（Redis 是唯一实时标准）
            Log::error('WalletService: asyncUpdateDB failed', [
                'player_id' => $playerId,
                'balance' => $newBalance,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 获取 Lua 增加余额脚本（供外部直接使用）
     *
     * @return string Lua 脚本
     */
    public static function getLuaIncrementScript(): string
    {
        return self::LUA_ATOMIC_INCREMENT;
    }

    /**
     * 获取 Lua 减少余额脚本（供外部直接使用）
     *
     * @return string Lua 脚本
     */
    public static function getLuaDecrementScript(): string
    {
        return self::LUA_ATOMIC_DECREMENT;
    }
}
