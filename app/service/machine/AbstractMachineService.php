<?php

declare(strict_types=1);

namespace app\service\machine;

use addons\webman\model\Machine;
use Exception;
use Psr\Log\LoggerInterface;
use support\Cache;
use support\Log;

/**
 * 机台服务抽象基类
 *
 * 职责分离：
 * - Redis：存储和读取机台实时状态数据
 * - gk_work：负责指令发送和机台通信
 * - AbstractMachineService：提供统一的数据访问接口
 *
 * 优化特性：
 * - 内存缓存：减少 Redis 访问频率
 * - 批量读取：一次性加载所有字段
 * - 自动重试：Redis 操作失败自动重试
 * - 日志记录：完整的错误日志
 */
abstract class AbstractMachineService implements BaseMachine
{
    /** 缓存前缀 */
    protected const CACHE_PREFIX = 'machine_tcp_action_cache_';
    protected const MACHINE_DATA_PREFIX = 'machine_tcp_data_cache_';

    /** 机台对象 */
    protected Machine $machine;

    /** 缓存 Key */
    protected string $cacheKey;
    protected string $cacheDataKey;

    /** 缓存数据 Key 数组 */
    protected array $cacheDataKeyArr = [];

    /** 语言 */
    protected string $lang;

    /** 机台信息字段列表 */
    protected array $machineInfo = [];

    /** 缓存数据（用于批量获取） */
    protected array $cacheData = [];

    /** 日志实例 */
    protected LoggerInterface $log;

    /** 操作超时时间（微秒） */
    protected int $expirationTime = 5000000;

    /** 内存缓存的完整数据（避免每次 __get 都查询 Redis） */
    protected ?array $cachedAllData = null;

    /** 缓存失效时间（秒） */
    protected int $cacheAllDataTTL = 1;

    /** 上次缓存时间 */
    protected ?float $lastCacheTime = null;

    /**
     * 构造函数
     *
     * @param Machine $machine 机台对象
     * @param string $lang 语言代码
     */
    public function __construct(Machine $machine, string $lang = 'zh_CN')
    {
        $this->machine = $machine;
        $this->lang = $lang;
        $this->cacheKey = self::CACHE_PREFIX . $this->machine->id;
        $this->cacheDataKey = self::MACHINE_DATA_PREFIX . $this->machine->id;

        // 子类初始化缓存 Key 和机台信息字段
        $this->initializeCacheKeys();
        $this->initializeMachineInfo();

        // 初始化日志
        $this->log = $this->initializeLogger();
    }

    /**
     * 初始化缓存 Key 数组
     * 子类必须实现此方法
     */
    abstract protected function initializeCacheKeys(): void;

    /**
     * 初始化机台信息字段列表
     * 子类必须实现此方法
     */
    abstract protected function initializeMachineInfo(): void;

    /**
     * 初始化日志实例
     * 子类可以覆盖此方法使用不同的日志通道
     *
     * @return LoggerInterface
     */
    protected function initializeLogger(): LoggerInterface
    {
        return Log::channel('default');
    }

    /**
     * 魔术方法 - 获取机台属性
     * 从 Redis 缓存读取机台实时状态（带内存缓存优化）
     *
     * @param string $name 属性名
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        $key = $this->cacheDataKey . '_' . $name;

        if (!in_array($key, $this->cacheDataKeyArr)) {
            return null;
        }

        // 尝试从内存缓存获取
        $allData = $this->getAllData();
        if (isset($allData[$key])) {
            return $allData[$key];
        }

        // 内存缓存未命中，直接从 Redis 读取（带重试）
        return $this->getFromRedis($name);
    }

    /**
     * 魔术方法 - 设置机台属性
     * 将机台状态写入 Redis 缓存
     *
     * @param string $name 属性名
     * @param mixed $value 属性值
     */
    public function __set(string $name, mixed $value): void
    {
        $key = $this->cacheDataKey . '_' . $name;

        if (!in_array($key, $this->cacheDataKeyArr)) {
            return;
        }

        // 保存到 Redis
        $saveResult = $this->saveToRedis($name, $value);

        // 关键字段保存失败时记录额外日志
        if (!$saveResult) {
            $this->handleCriticalFieldSaveFailure($name, $value);
        }

        // 使内存缓存失效
        $this->invalidateCache();

        // 子类特定推送逻辑（模板方法）
        $this->handleFieldUpdatePush($name, $value);
    }

    /**
     * 获取所有缓存数据（带内存缓存）
     *
     * @return array
     */
    protected function getAllData(): array
    {
        $now = microtime(true);

        // 内存缓存有效，直接返回
        if ($this->cachedAllData !== null
            && $this->lastCacheTime !== null
            && ($now - $this->lastCacheTime) < $this->cacheAllDataTTL
        ) {
            return $this->cachedAllData;
        }

        // 缓存失效，重新获取
        try {
            $values = Cache::getMultiple($this->cacheDataKeyArr, 0);
            $this->cachedAllData = is_array($values) ? $values : [];
            $this->lastCacheTime = $now;

            return $this->cachedAllData;
        } catch (Exception $e) {
            $this->log->error('批量获取机台缓存数据失败', [
                'machine_id' => $this->machine->id,
                'machine_code' => $this->machine->code,
                'keys_count' => count($this->cacheDataKeyArr),
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 从 Redis 获取单个字段（带重试）
     *
     * @param string $name 字段名
     * @return mixed
     */
    protected function getFromRedis(string $name): mixed
    {
        $key = $this->cacheDataKey . '_' . $name;

        try {
            return Cache::get($key, 0);
        } catch (Exception $e) {
            // 失败后重试1次
            try {
                $value = Cache::get($key, 0);
                $this->log->warning('Redis缓存读取失败后重试成功', [
                    'machine_id' => $this->machine->id,
                    'field' => $name,
                    'error' => $e->getMessage()
                ]);
                return $value;
            } catch (Exception $e2) {
                // 重试仍失败，记录错误并返回默认值
                $this->log->error('Redis缓存读取失败（重试1次后仍失败）', [
                    'machine_id' => $this->machine->id,
                    'machine_code' => $this->machine->code,
                    'field' => $name,
                    'key' => $key,
                    'error' => $e2->getMessage()
                ]);
                return 0;
            }
        }
    }

    /**
     * 保存数据到 Redis（带重试逻辑）
     *
     * @param string $name 字段名
     * @param mixed $value 字段值
     * @return bool 是否保存成功
     */
    protected function saveToRedis(string $name, mixed $value): bool
    {
        $key = $this->cacheDataKey . '_' . $name;
        $maxRetries = 1;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $result = Cache::set($key, $value);

                if ($result) {
                    if ($attempt > 0) {
                        $this->log->warning('Redis缓存保存重试成功', [
                            'machine_id' => $this->machine->id,
                            'field' => $name,
                            'attempt' => $attempt
                        ]);
                    }
                    return true;
                }

                // 第一次失败立即重试
                if ($attempt < $maxRetries) {
                    continue;
                }

            } catch (Exception $e) {
                if ($attempt < $maxRetries) {
                    $this->log->warning('Redis缓存保存异常，准备重试', [
                        'machine_id' => $this->machine->id,
                        'field' => $name,
                        'attempt' => $attempt,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }

                $this->log->error('Redis缓存保存异常（重试后仍失败）', [
                    'machine_id' => $this->machine->id,
                    'machine_code' => $this->machine->code,
                    'field' => $name,
                    'value' => $value,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        }

        return false;
    }

    /**
     * 处理关键字段保存失败
     *
     * @param string $name 字段名
     * @param mixed $value 字段值
     */
    protected function handleCriticalFieldSaveFailure(string $name, mixed $value): void
    {
        // 关键字段列表（影响业务逻辑的字段）
        $criticalFields = [
            'gaming_user_id',
            'reward_status',
            'point',
            'auto',
            'gaming'
        ];

        if (in_array($name, $criticalFields)) {
            $this->log->critical('关键字段保存失败', [
                'machine_id' => $this->machine->id,
                'machine_code' => $this->machine->code,
                'field' => $name,
                'value' => $value
            ]);
        }
    }

    /**
     * 使内存缓存失效
     */
    protected function invalidateCache(): void
    {
        $this->cachedAllData = null;
        $this->lastCacheTime = null;
    }

    /**
     * 处理字段更新后的推送逻辑（模板方法，子类可覆盖）
     *
     * @param string $name 字段名
     * @param mixed $value 字段值
     * @return void
     */
    protected function handleFieldUpdatePush(string $name, mixed $value): void
    {
        // 默认不做任何事，子类按需实现
    }

    /**
     * 发送机台指令（统一调用 gk_work）
     *
     * 注意：gk_work 负责指令发送和机台通信
     * 本方法只是封装 HTTP 调用，不直接操作 Gateway
     *
     * @param string $cmd 指令代码
     * @param int $data 数据
     * @param string $source 操作来源 (player/admin)
     * @param int $source_id 来源ID
     * @return bool
     * @throws Exception
     */
    public function sendCmd(
        string $cmd,
        int $data = 0,
        string $source = 'admin',
        int $source_id = 0
    ): bool {
        try {
            // ✅ 统一通过 MachineApiService 调用 gk_work
            $adminId = $source === 'admin' ? $source_id : 0;

            $result = \app\service\MachineApiService::sendCmd(
                $this->machine->id,
                $cmd,
                $data,
                $adminId,
                $this->lang
            );

            // 记录操作日志
            $operatorType = $source === 'admin' ? '【管理员操作】' : '【玩家操作】';
            $this->log->info($operatorType . '机台指令', [
                'machine_id' => $this->machine->id,
                'machine_code' => $this->machine->code,
                'cmd' => $cmd,
                'data' => $data,
                'source' => $source,
                'source_id' => $source_id,
                'result' => $result
            ]);

            return true;

        } catch (Exception $e) {
            // 记录错误日志
            $this->log->error('机台指令发送失败', [
                'machine_id' => $this->machine->id,
                'machine_code' => $this->machine->code,
                'cmd' => $cmd,
                'data' => $data,
                'source' => $source,
                'source_id' => $source_id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 获取机台信息（用于推送）
     *
     * @return array
     */
    public function getMachineInfo(): array
    {
        $info = [];

        foreach ($this->machineInfo as $field) {
            $info[$field] = $this->$field ?? 0;
        }

        return array_merge([
            'id' => $this->machine->id,
            'code' => $this->machine->code,
            'name' => $this->machine->name,
        ], $info);
    }
}
