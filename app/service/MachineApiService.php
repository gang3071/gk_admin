<?php

namespace app\service;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
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
            $response = self::createClient($adminId)
                ->post('/api/admin/machine/send-cmd', [
                    'machine_id' => $machineId,
                    'cmd' => $cmd,
                    'data' => $data,
                    'lang' => $lang,
                ]);

            return self::handleResponse($response, '发送机台指令');

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
     * @return PendingRequest
     */
    private static function createClient(int $adminId = 0): PendingRequest
    {
        self::init();

        return Http::baseUrl(self::$baseUrl)
            ->timeout(30)
            ->withHeaders([
                'X-Admin-Id' => $adminId,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]);
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
     * @param Response $response HTTP响应
     * @param string $action 操作名称
     * @return array
     * @throws Exception
     */
    private static function handleResponse(Response $response, string $action): array
    {
        if (!$response->successful()) {
            throw new Exception("{$action}失败: HTTP {$response->status()}");
        }

        $data = $response->json();

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
     * @return array
     * @throws Exception
     */
    public static function getMachineStatus(int $machineId, string $lang = 'zh_CN'): array
    {
        try {
            $response = self::createClient()
                ->post('/api/admin/machine/status', [
                    'machine_id' => $machineId,
                    'lang' => $lang,
                ]);

            return self::handleResponse($response, '获取机台状态');

        } catch (Exception $e) {
            Log::error('MachineApiService::getMachineStatus failed', [
                'machine_id' => $machineId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 检查机台是否在线
     *
     * @param int $machineId 机台ID
     * @return array
     * @throws Exception
     */
    public static function checkOnline(int $machineId): array
    {
        try {
            $response = self::createClient()
                ->post('/api/admin/machine/check-online', [
                    'machine_id' => $machineId,
                ]);

            return self::handleResponse($response, '检查机台在线');

        } catch (Exception $e) {
            Log::error('MachineApiService::checkOnline failed', [
                'machine_id' => $machineId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 批量检查机台在线状态
     *
     * @param array $machineIds 机台ID列表
     * @return array
     * @throws Exception
     */
    public static function batchCheckOnline(array $machineIds): array
    {
        try {
            $response = self::createClient()
                ->post('/api/admin/machine/batch-check-online', [
                    'machine_ids' => $machineIds,
                ]);

            return self::handleResponse($response, '批量检查机台在线');

        } catch (Exception $e) {
            Log::error('MachineApiService::batchCheckOnline failed', [
                'machine_ids' => $machineIds,
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
     * @return array
     * @throws Exception
     */
    public static function getDescription(int $machineId, string $fun = '', int $data = 0, string $lang = 'zh_CN'): array
    {
        try {
            $response = self::createClient()
                ->post('/api/admin/machine/get-description', [
                    'machine_id' => $machineId,
                    'fun' => $fun,
                    'data' => $data,
                    'lang' => $lang,
                ]);

            return self::handleResponse($response, '获取机台操作描述');

        } catch (Exception $e) {
            Log::error('MachineApiService::getDescription failed', [
                'machine_id' => $machineId,
                'fun' => $fun,
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
            $response = self::createClient()
                ->get('/api/admin/machine/gateway-info');

            return self::handleResponse($response, '获取Gateway信息');

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
     * @return array
     * @throws Exception
     */
    public static function getAllOnlineStatus(int $departmentId = 0, ?string $type = null): array
    {
        try {
            $response = self::createClient()
                ->post('/api/admin/machine/all-online-status', [
                    'department_id' => $departmentId,
                    'type' => $type,
                ]);

            return self::handleResponse($response, '获取所有机台在线状态');

        } catch (Exception $e) {
            Log::error('MachineApiService::getAllOnlineStatus failed', [
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
            $response = self::createClient()
                ->get('/api/admin/machine/online-statistics');

            return self::handleResponse($response, '获取机台在线统计');

        } catch (Exception $e) {
            Log::error('MachineApiService::getOnlineStatistics failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
