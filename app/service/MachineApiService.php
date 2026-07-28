<?php

namespace app\service;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use support\Log;

/**
 * 机台 API 服务
 * 封装对 gk_work 机台操作 API 的调用
 */
class MachineApiService
{
    /**
     * gk_work API 基础 URL
     * @var string
     */
    private static $baseUrl;

    /**
     * 发送机台指令
     *
     * @param int $machineId 机台ID
     * @param string $cmd 指令
     * @param int $data 数据
     * @param int $adminId 管理员ID
     * @param string $lang 语言
     * @return array
     * @throws Exception
     */
    public static function sendCmd(int $machineId, string $cmd, int $data = 0, int $adminId = 0, string $lang = 'zh_CN'): array
    {
        try {
            $client = self::createClient($adminId);
            $response = $client->post('/api/admin/machine/send-cmd', [
                'json' => [
                    'machine_id' => $machineId,
                    'cmd' => $cmd,
                    'data' => $data,
                    'lang' => $lang,
                ]
            ]);

            return self::handleResponse($response, '发送机台指令');

        } catch (GuzzleException $e) {
            Log::error('MachineApiService::sendCmd failed', [
                'machine_id' => $machineId,
                'cmd' => $cmd,
                'error' => $e->getMessage()
            ]);
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            Log::error('MachineApiService::sendCmd failed', [
                'machine_id' => $machineId,
                'cmd' => $cmd,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 创建 HTTP 客户端
     * @param int $adminId 管理员ID
     * @return Client
     */
    private static function createClient(int $adminId = 0): Client
    {
        self::init();

        // 如果未传递adminId，尝试从session获取
        if ($adminId === 0) {
            $adminId = self::getAdminIdFromSession();
        }

        return new Client([
            'base_uri' => self::$baseUrl,
            'connect_timeout' => 1,  // 连接超时1秒
            'timeout' => 2,  // 本地开发环境减少超时时间，避免页面卡死
            'headers' => [
                'X-Admin-Id' => $adminId,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * 从session获取管理员ID
     * @return int
     */
    private static function getAdminIdFromSession(): int
    {
        try {
            // 尝试从session获取管理员ID
            $session = request()->session();
            $adminId = $session->get('admin.id', 0);

            if ($adminId > 0) {
                return $adminId;
            }

            // 如果session中没有，记录警告
            Log::warning('[MachineApiService] 调用缺少adminId，无法追踪操作来源', [
                'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
            ]);

            return 0;

        } catch (\Exception $e) {
            Log::warning('[MachineApiService] 从session获取adminId失败', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * 获取当前管理员用户名
     * @return string
     */
    private static function getAdminUsername(): string
    {
        try {
            // 优先使用 Admin facade（ExAdmin环境）
            if (class_exists('\addons\webman\Admin')) {
                $admin = \addons\webman\Admin::user();
                if ($admin && isset($admin->username)) {
                    return $admin->username;
                }
            }

            // Fallback: 从session获取
            $session = request()->session();
            $username = $session->get('admin.username', '');

            return $username ?: '';

        } catch (\Exception $e) {
            Log::warning('[MachineApiService] 获取admin username失败', [
                'error' => $e->getMessage()
            ]);
            return '';
        }
    }

    /**
     * 初始化配置
     */
    private static function init(): void
    {
        if (self::$baseUrl === null) {
            self::$baseUrl = getenv('GK_WORK_API_URL') ?: 'http://127.0.0.1:8788';
        }
    }

    /**
     * 处理响应
     *
     * @param \Psr\Http\Message\ResponseInterface $response HTTP响应
     * @param string $action 操作名称
     * @return array
     * @throws Exception
     */
    private static function handleResponse($response, string $action): array
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new Exception("{$action}失败: HTTP {$statusCode}");
        }

        $body = (string)$response->getBody();
        $data = json_decode($body, true);

        if (!isset($data['code'])) {
            throw new Exception("{$action}失败: 响应格式错误");
        }

        if ($data['code'] != 200) {
            $msg = $data['msg'] ?? '未知错误';
            throw new Exception("{$action}失败: {$msg}");
        }

        return $data['data'] ?? [];
    }

    /**
     * 获取机台状态
     *
     * @param int $machineId 机台ID
     * @param string $lang 语言
     * @param int $adminId 管理员ID（可选，用于日志追踪）
     * @return array
     * @throws Exception
     */
    public static function getMachineStatus(int $machineId, string $lang = 'zh_CN', int $adminId = 0): array
    {
        try {
            $client = self::createClient($adminId);
            $response = $client->post('/api/admin/machine/status', [
                'json' => [
                    'machine_id' => $machineId,
                    'lang' => $lang,
                ]
            ]);

            return self::handleResponse($response, '获取机台状态');

        } catch (GuzzleException $e) {
            Log::error('MachineApiService::getMachineStatus failed', [
                'machine_id' => $machineId,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            Log::error('MachineApiService::getMachineStatus failed', [
                'machine_id' => $machineId,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 检查机台是否在线
     *
     * @param int $machineId 机台ID
     * @param int $adminId 管理员ID（可选，用于日志追踪）
     * @return array
     * @throws Exception
     */
    public static function checkOnline(int $machineId, int $adminId = 0): array
    {
        try {
            $client = self::createClient($adminId);
            $response = $client->post('/api/admin/machine/check-online', [
                'json' => [
                    'machine_id' => $machineId,
                ]
            ]);

            return self::handleResponse($response, '检查机台在线');

        } catch (GuzzleException $e) {
            Log::error('MachineApiService::checkOnline failed', [
                'machine_id' => $machineId,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            Log::error('MachineApiService::checkOnline failed', [
                'machine_id' => $machineId,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 批量检查机台在线状态
     *
     * @param array $machineIds 机台ID列表
     * @param int $adminId 管理员ID（可选，用于日志追踪）
     * @return array
     * @throws Exception
     */
    public static function batchCheckOnline(array $machineIds, int $adminId = 0): array
    {
        try {
            $client = self::createClient($adminId);
            $response = $client->post('/api/admin/machine/batch-check-online', [
                'json' => [
                    'machine_ids' => $machineIds,
                ]
            ]);

            return self::handleResponse($response, '批量检查机台在线');

        } catch (GuzzleException $e) {
            Log::error('MachineApiService::batchCheckOnline failed', [
                'machine_ids' => $machineIds,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            Log::error('MachineApiService::batchCheckOnline failed', [
                'machine_ids' => $machineIds,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 获取机台操作描述
     *
     * @param int $machineId 机台ID
     * @param string $fun 功能
     * @param int $data 数据
     * @param string $lang 语言
     * @param int $adminId 管理员ID（可选，用于日志追踪）
     * @return array
     * @throws Exception
     */
    public static function getDescription(int $machineId, string $fun = '', int $data = 0, string $lang = 'zh_CN', int $adminId = 0): array
    {
        try {
            $client = self::createClient($adminId);
            $response = $client->post('/api/admin/machine/get-description', [
                'json' => [
                    'machine_id' => $machineId,
                    'fun' => $fun,
                    'data' => $data,
                    'lang' => $lang,
                ]
            ]);

            return self::handleResponse($response, '获取机台操作描述');

        } catch (GuzzleException $e) {
            Log::error('MachineApiService::getDescription failed', [
                'machine_id' => $machineId,
                'fun' => $fun,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            Log::error('MachineApiService::getDescription failed', [
                'machine_id' => $machineId,
                'fun' => $fun,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 测试连接
     *
     * @return bool
     */
    public static function testConnection(): bool
    {
        try {
            self::getGatewayInfo();
            return true;
        } catch (Exception $e) {
            Log::error('MachineApiService::testConnection failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取 Gateway 信息（调试用）
     *
     * @return array
     * @throws Exception
     */
    public static function getGatewayInfo(): array
    {
        try {
            $client = self::createClient();
            $response = $client->get('/api/admin/machine/gateway-info');

            return self::handleResponse($response, '获取Gateway信息');

        } catch (GuzzleException $e) {
            Log::error('MachineApiService::getGatewayInfo failed', [
                'error' => $e->getMessage()
            ]);
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            Log::error('MachineApiService::getGatewayInfo failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 获取所有机台的实时在线状态
     *
     * @param int $departmentId 部门ID
     * @param string|null $type 机台类型 (slot/steel_ball/fish)
     * @param int $adminId 管理员ID（可选，用于日志追踪）
     * @return array
     * @throws Exception
     */
    public static function getAllOnlineStatus(int $departmentId = 0, ?string $type = null, int $adminId = 0): array
    {
        try {
            $client = self::createClient($adminId);
            $response = $client->post('/api/admin/machine/all-online-status', [
                'json' => [
                    'department_id' => $departmentId,
                    'type' => $type,
                ]
            ]);

            return self::handleResponse($response, '获取所有机台在线状态');

        } catch (GuzzleException $e) {
            Log::error('MachineApiService::getAllOnlineStatus failed', [
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            Log::error('MachineApiService::getAllOnlineStatus failed', [
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 获取机台在线统计
     *
     * @return array
     * @throws Exception
     */
    public static function getOnlineStatistics(): array
    {
        try {
            $client = self::createClient();
            $response = $client->get('/api/admin/machine/online-statistics');

            return self::handleResponse($response, '获取机台在线统计');

        } catch (GuzzleException $e) {
            Log::error('MachineApiService::getOnlineStatistics failed', [
                'error' => $e->getMessage()
            ]);
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            Log::error('MachineApiService::getOnlineStatistics failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 批量获取机台状态
     *
     * @param array $machineIds 机台ID列表
     * @param string $lang 语言
     * @param int $adminId 管理员ID（可选，用于日志追踪）
     * @return array
     * @throws Exception
     */
    public static function batchGetMachineStatus(array $machineIds, string $lang = 'zh_CN', int $adminId = 0): array
    {
        try {
            $client = self::createClient($adminId);
            $response = $client->post('/api/admin/machine/batch-status', [
                'json' => [
                    'machine_ids' => $machineIds,
                    'lang' => $lang,
                ]
            ]);

            return self::handleResponse($response, '批量获取机台状态');

        } catch (GuzzleException $e) {
            Log::error('MachineApiService::batchGetMachineStatus failed', [
                'machine_ids' => $machineIds,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            Log::error('MachineApiService::batchGetMachineStatus failed', [
                'machine_ids' => $machineIds,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 更新机台状态
     *
     * @param int $machineId 机台ID
     * @param string $field 字段名
     * @param mixed $value 字段值
     * @param string $lang 语言
     * @param int $adminId 管理员ID（可选，用于日志追踪）
     * @return array
     * @throws Exception
     */
    public static function updateMachineState(int $machineId, string $field, $value, string $lang = 'zh_CN', int $adminId = 0): array
    {
        try {
            $client = self::createClient($adminId);
            $response = $client->post('/api/admin/machine/update-state', [
                'json' => [
                    'machine_id' => $machineId,
                    'field' => $field,
                    'value' => $value,
                    'lang' => $lang,
                ]
            ]);

            return self::handleResponse($response, '更新机台状态');

        } catch (GuzzleException $e) {
            Log::error('MachineApiService::updateMachineState failed', [
                'machine_id' => $machineId,
                'field' => $field,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            Log::error('MachineApiService::updateMachineState failed', [
                'machine_id' => $machineId,
                'field' => $field,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 踢出玩家（洗分）
     *
     * @param int $machineId 机台ID
     * @param int $playerId 玩家ID
     * @param string $path 路径 (leave/down)
     * @param int $adminId 管理员ID
     * @param string $lang 语言
     * @return array
     * @throws Exception
     */
    public static function kickPlayer(int $machineId, int $playerId, string $path = 'leave', int $adminId = 0, string $lang = 'zh_CN'): array
    {
        try {
            $client = self::createClient($adminId);
            $response = $client->post('/api/admin/machine/kick-player', [
                'json' => [
                    'machine_id' => $machineId,
                    'player_id' => $playerId,
                    'path' => $path,
                    'lang' => $lang,
                    'admin_username' => self::getAdminUsername(),
                ]
            ]);

            return self::handleResponse($response, '踢出玩家');

        } catch (GuzzleException $e) {
            Log::error('MachineApiService::kickPlayer failed', [
                'machine_id' => $machineId,
                'player_id' => $playerId,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            Log::error('MachineApiService::kickPlayer failed', [
                'machine_id' => $machineId,
                'player_id' => $playerId,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 强制踢出玩家（不返还分数）
     *
     * @param int $machineId 机台ID
     * @param int $playerId 玩家ID
     * @param int $adminId 管理员ID
     * @param string $lang 语言
     * @return array
     * @throws Exception
     */
    public static function forceKickPlayer(int $machineId, int $playerId, int $adminId = 0, string $lang = 'zh_CN'): array
    {
        try {
            $client = self::createClient($adminId);
            $response = $client->post('/api/admin/machine/force-kick-player', [
                'json' => [
                    'machine_id' => $machineId,
                    'player_id' => $playerId,
                    'lang' => $lang,
                    'admin_username' => self::getAdminUsername(),
                ]
            ]);

            return self::handleResponse($response, '强制踢出玩家');

        } catch (GuzzleException $e) {
            Log::error('MachineApiService::forceKickPlayer failed', [
                'machine_id' => $machineId,
                'player_id' => $playerId,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            Log::error('MachineApiService::forceKickPlayer failed', [
                'machine_id' => $machineId,
                'player_id' => $playerId,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 自定义开分
     *
     * @param int $machineId 机台ID
     * @param int $playerId 玩家ID
     * @param int $openScore 开分数值
     * @param int $adminId 管理员ID
     * @param string $lang 语言
     * @return array
     * @throws Exception
     */
    public static function customOpenScore(int $machineId, int $playerId, int $openScore, int $adminId = 0, string $lang = 'zh_CN'): array
    {
        try {
            $client = self::createClient($adminId);
            $response = $client->post('/api/admin/machine/custom-open-score', [
                'json' => [
                    'machine_id' => $machineId,
                    'player_id' => $playerId,
                    'open_score' => $openScore,
                    'lang' => $lang,
                    'admin_username' => self::getAdminUsername(),
                ]
            ]);

            return self::handleResponse($response, '自定义开分');

        } catch (GuzzleException $e) {
            Log::error('MachineApiService::customOpenScore failed', [
                'machine_id' => $machineId,
                'player_id' => $playerId,
                'open_score' => $openScore,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            Log::error('MachineApiService::customOpenScore failed', [
                'machine_id' => $machineId,
                'player_id' => $playerId,
                'open_score' => $openScore,
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
