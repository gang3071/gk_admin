<?php

namespace addons\webman\service;

use WebmanTech\LaravelHttpClient\Facades\Http;
use Exception;
use support\Log;

/**
 * 媒体服务器 API 客户端
 * 调用 gk_work 的媒体服务器接口
 */
class MediaServerApiClient
{
    private string $baseUrl;
    private int $timeout;
    private Log $log;

    public function __construct()
    {
        // 使用 config/app.php 中的配置（会自动复用 GAME_PLATFORM_PROXY_HOST）
        $this->baseUrl = sprintf(
            'http://%s:%d/api/admin/media-server',
            config('app.media_proxy.host'),
            config('app.media_proxy.port')
        );
        $this->timeout = 10;
        $this->log = Log::channel('media_recording');
    }

    /**
     * 删除机台流
     */
    public function deleteMachineStream(string $streamName, string $domain = '', string $mediaApp = ''): bool
    {
        try {
            $response = Http::timeout($this->timeout)->post($this->baseUrl . '/delete-machine-stream', [
                'stream_name' => $streamName,
                'domain' => $domain,
                'media_app' => $mediaApp,
            ]);

            $result = $response->json();

            if (!$result['success']) {
                $this->log->error('[媒体API客户端] deleteMachineStream 失败', [
                    'stream_name' => $streamName,
                    'error' => $result['message'] ?? 'Unknown error',
                ]);
                return false;
            }

            return $result['data'] ?? true;

        } catch (\Exception $e) {
            $this->log->error('[媒体API客户端] deleteMachineStream 异常', [
                'stream_name' => $streamName,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 创建机台流
     */
    public function createMachineStream(string $name, string $streamUrl, int $type, array $pushList = [], string $domain = '', string $mediaApp = ''): mixed
    {
        try {
            $response = Http::timeout($this->timeout)->post($this->baseUrl . '/create-machine-stream', [
                'name' => $name,
                'stream_url' => $streamUrl,
                'type' => $type,
                'push_list' => $pushList,
                'domain' => $domain,
                'media_app' => $mediaApp,
            ]);

            $result = $response->json();

            if (!$result['success']) {
                $this->log->error('[媒体API客户端] createMachineStream 失败', [
                    'name' => $name,
                    'error' => $result['message'] ?? 'Unknown error',
                ]);
                throw new Exception($result['message'] ?? admin_trans('message.media.media_request_error'));
            }

            return $result['data'];

        } catch (\Exception $e) {
            $this->log->error('[媒体API客户端] createMachineStream 异常', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 添加 RTMP 节点
     */
    public function rtmpEndpoint(string $rtmpUrl, string $endpointServiceId, string $streamName, string $domain = '', string $mediaApp = ''): bool
    {
        try {
            $response = Http::timeout($this->timeout)->post($this->baseUrl . '/rtmp-endpoint', [
                'rtmp_url' => $rtmpUrl,
                'endpoint_service_id' => $endpointServiceId,
                'stream_name' => $streamName,
                'domain' => $domain,
                'media_app' => $mediaApp,
            ]);

            $result = $response->json();

            if (!$result['success']) {
                throw new Exception($result['message'] ?? admin_trans('message.media.media_stream_end_point_error'));
            }

            return $result['data'] ?? true;

        } catch (\Exception $e) {
            $this->log->error('[媒体API客户端] rtmpEndpoint 异常', [
                'stream_name' => $streamName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 删除 RTMP 节点
     */
    public function deleteRtmpEndpoint(string $endpointServiceId, string $streamName, string $domain = '', string $mediaApp = ''): bool
    {
        try {
            $response = Http::timeout($this->timeout)->post($this->baseUrl . '/delete-rtmp-endpoint', [
                'endpoint_service_id' => $endpointServiceId,
                'stream_name' => $streamName,
                'domain' => $domain,
                'media_app' => $mediaApp,
            ]);

            $result = $response->json();

            if (!$result['success']) {
                throw new Exception($result['message'] ?? admin_trans('message.media.delete_media_stream_end_point_error'));
            }

            return $result['data'] ?? true;

        } catch (\Exception $e) {
            $this->log->error('[媒体API客户端] deleteRtmpEndpoint 异常', [
                'stream_name' => $streamName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 获取观看人数
     */
    public function getViewers(string $streamName, string $domain = '', string $mediaApp = ''): mixed
    {
        try {
            $response = Http::timeout($this->timeout)->post($this->baseUrl . '/get-viewers', [
                'stream_name' => $streamName,
                'domain' => $domain,
                'media_app' => $mediaApp,
            ]);

            $result = $response->json();

            if (!$result['success']) {
                return false;
            }

            return $result['data'];

        } catch (\Exception $e) {
            $this->log->error('[媒体API客户端] getViewers 异常', [
                'stream_name' => $streamName,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 获取流信息
     */
    public function getBroadcasts(string $streamName, string $domain = '', string $mediaApp = ''): mixed
    {
        try {
            $response = Http::timeout($this->timeout)->post($this->baseUrl . '/get-broadcasts', [
                'stream_name' => $streamName,
                'domain' => $domain,
                'media_app' => $mediaApp,
            ]);

            $result = $response->json();

            if (!$result['success']) {
                throw new Exception($result['message'] ?? '获取流信息失败');
            }

            return $result['data'];

        } catch (\Exception $e) {
            $this->log->error('[媒体API客户端] getBroadcasts 异常', [
                'stream_name' => $streamName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
