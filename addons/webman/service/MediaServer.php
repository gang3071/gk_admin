<?php

namespace addons\webman\service;


use addons\webman\model\GameType;
use addons\webman\model\MachineMedia;
use addons\webman\model\MachineMediaPush;
use addons\webman\model\MachineRecording;
use addons\webman\model\MachineTencentPlay;
use Exception;
use support\Db;
use support\Log;
use TencentCloud\Common\Credential;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Live\V20180801\LiveClient;
use TencentCloud\Live\V20180801\Models\DescribeLiveStreamStateRequest;
use TencentCloud\Live\V20180801\Models\DescribeStreamPlayInfoListRequest;
use TencentCloud\Live\V20180801\Models\ForbidLiveStreamRequest;
use TencentCloud\Live\V20180801\Models\ResumeLiveStreamRequest;
use WebmanTech\LaravelHttpClient\Facades\Http;

class MediaServer
{

    public $method = 'POST';
    public $log;
    private $domain = '';
    private $mediaApp = '';
    private $stream_url = 'rtsp://admin:ez88888888@stream_url/cam/realmonitor?channel=1&subtype=0';
    private $fish_stream_url = 'rtsp://{ip}/live/0';

    /**
     */
    public function __construct($domain = '', $mediaApp = '')
    {
        $this->domain = $domain;
        $this->mediaApp = $mediaApp;
        $this->log = Log::channel('media_recording');
    }

    /**
     * 获取媒体代理配置
     *
     * @return array{host: string, port: int}
     */
    private function getProxyConfig(): array
    {
        return config('app.media_proxy', [
            'host' => '127.0.0.1',
            'port' => 8788,
        ]);
    }

    /**
     * 通过 gk_work 代理发送媒体服务器请求
     *
     * @param string $method HTTP 方法 (GET|POST|PUT|DELETE)
     * @param string $url 完整的媒体服务器 API URL
     * @param array $body 请求体（可选）
     * @param array $headers 额外头部（可选）
     * @param int $timeout 超时时间（秒）
     * @return mixed 响应对象或数据
     * @throws Exception
     */
    private function proxyRequest(string $method, string $url, array $body = [], array $headers = [], int $timeout = 10): mixed
    {
        try {
            // 获取代理配置
            $proxyConfig = $this->getProxyConfig();
            $proxyUrl = sprintf('http://%s:%d/api/admin/media-proxy', $proxyConfig['host'], $proxyConfig['port']);

            // 构建代理请求参数
            $proxyPayload = [
                'method' => strtoupper($method),
                'url' => $url,
                'timeout' => $timeout,
            ];

            if (!empty($headers)) {
                $proxyPayload['headers'] = $headers;
            }

            if (!empty($body)) {
                $proxyPayload['body'] = $body;
            }

            // 发送代理请求（代理超时时间 = 实际超时 + 5秒缓冲）
            $response = Http::timeout($timeout + 5)->post($proxyUrl, $proxyPayload);

            if (!$response->successful()) {
                throw new Exception('Media proxy request failed with status: ' . $response->status());
            }

            $result = $response->json();

            // 检查代理响应格式
            if (!isset($result['success']) || !$result['success']) {
                $errorMsg = $result['message'] ?? 'Unknown media proxy error';
                throw new Exception($errorMsg);
            }

            // 返回媒体服务器的实际响应
            return $result['data'];

        } catch (\Exception $e) {
            $this->log->error('[媒体代理] 请求失败', [
                'method' => $method,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * 添加rtmp节点
     * @param $rtmpUrl
     * @param $endpointServiceId
     * @param $streamName
     * @param int $attempts
     * @return true
     * @throws Exception
     */
    public function rtmpEndpoint($rtmpUrl, $endpointServiceId, $streamName, int $attempts = 0): bool
    {
        $maxRetries = 4;
        try {
            $url = $this->domain . '/' . $this->mediaApp . '/rest/v2/broadcasts/' . $streamName . '/rtmp-endpoint';
            $body = [
                'type' => 'generic',
                'rtmpUrl' => $rtmpUrl,
                'endpointServiceId' => $endpointServiceId,
            ];

            // 通过 gk_work 代理调用
            $response = $this->proxyRequest('POST', $url, $body, [], 5);

        } catch (\Exception) {
            throw new Exception(admin_trans('message.media.media_request_error'));
        }

        $this->log->info('rtmpEndpoint', [
            'response' => $response,
            'url' => $url,
            'body' => $body,
        ]);

        if (!empty($response) && isset($response['success'])) {
            if (empty($response['success'])) {
                $attempts++;
                if ($attempts >= $maxRetries) {
                    throw new Exception(admin_trans('message.media.media_stream_end_point_error'));
                }
                $this->rtmpEndpoint($rtmpUrl, $endpointServiceId, $streamName, $attempts);
            }
        } else {
            throw new Exception(admin_trans('message.media.media_request_error'));
        }

        return true;
    }
    
    /**
     * 删除rtmp节点
     * @param $endpointServiceId
     * @param $streamName
     * @param int $attempts
     * @return true
     * @throws Exception
     */
    public function deleteRtmpEndpoint($endpointServiceId, $streamName, int $attempts = 0): bool
    {
        $maxRetries = 4;
        try {
            $url = $this->domain . '/' . $this->mediaApp . '/rest/v2/broadcasts/' . $streamName . '/rtmp-endpoint?endpointServiceId=' . $endpointServiceId;

            // 通过 gk_work 代理调用
            $response = $this->proxyRequest('DELETE', $url, [], [], 5);

        } catch (\Exception) {
            throw new Exception(admin_trans('message.media.media_request_error'));
        }

        $this->log->info('deleteRtmpEndpoint', [
            'response' => $response,
            'endpointServiceId' => $endpointServiceId,
            'streamName' => $streamName,
        ]);

        if (!empty($response) && isset($response['success'])) {
            if (empty($response['success']) && !$response['success']) {
                $attempts++;
                if ($attempts >= $maxRetries) {
                    throw new Exception(admin_trans('message.media.delete_media_stream_end_point_error'));
                }
                $this->deleteRtmpEndpoint($endpointServiceId, $streamName, $attempts);
            }
        } else {
            throw new Exception(admin_trans('message.media.media_request_error'));
        }

        return true;
    }
    
    /**
     * 获取流信息
     * @param $streamName
     * @return false|mixed
     */
    public function getViewers($streamName): mixed
    {
        try {
            $url = $this->domain . '/' . $this->mediaApp . '/rest/v2/broadcasts/' . $streamName;

            // 通过 gk_work 代理调用
            $response = $this->proxyRequest('GET', $url, [], ['Content-Type' => 'application/json'], 5);

        } catch (\Exception) {
            return false;
        }

        if (!empty($response)) {
            if (empty($response['success'])) {
                if (isset($response['webRTCViewerCount'])) {
                    return $response['webRTCViewerCount'];
                }
            }
        }

        return false;
    }
    
    /**
     * 获取播放流
     * @param MachineRecording $machineRecording
     * @return string
     * @throws \Exception
     */
    public function getRecording(MachineRecording $machineRecording): string
    {
        if (!empty($machineRecording->vod_name)) {
            return 'http://' . $machineRecording->media->pull_ip . '/' . $machineRecording->media->media_app . '/streams/' . $machineRecording->vod_name;
        }

        $url = $machineRecording->media->push_ip . '/' . $machineRecording->media->media_app . '/rest/v2/vods/' . $machineRecording->data_id;

        // 通过 gk_work 代理调用
        $response = $this->proxyRequest('GET', $url, [], ['Content-Type' => 'application/json'], 5);

        if (!empty($response)) {
            $this->log->info('getRecording', [$response]);
            if (empty($response['vodName'])) {
                throw new \Exception(trans('vod_file_not_found', [], 'message'));
            }
            $machineRecording->org_data = json_encode($response);
            $machineRecording->vod_name = $response['vodName'];
            $machineRecording->save();
        } else {
            throw new \Exception(trans('vod_file_not_found', [], 'message'));
        }
        
        return 'http://' . $machineRecording->media->pull_ip . '/' . $machineRecording->media->media_app . '/streams/' . $machineRecording->vod_name;
    }
    
    /**
     * 删除视频
     * @param MachineRecording $machineRecording
     * @return true
     * @throws \Exception
     */
    public function deleteRecording(MachineRecording $machineRecording): bool
    {
        try {
            $url = $machineRecording->media->push_ip . '/' . $machineRecording->media->media_app . '/rest/v2/vods/' . $machineRecording->data_id;

            // 通过 gk_work 代理调用
            $response = $this->proxyRequest('DELETE', $url, [], ['Content-Type' => 'application/json'], 5);

        } catch (\Exception) {
            throw new Exception(admin_trans('message.media.media_request_error'));
        }

        $this->log->info('deleteRecording', [$response]);
        if (!empty($response)) {
            $machineRecording->delete();
        } else {
            throw new \Exception(trans('vod_file_not_found', [], 'message'));
        }

        return true;
    }
    
    /**
     * 开始录制
     * @param MachineMedia $media
     * @param int $type
     * @param int $departmentId
     * @param int $recordId
     * @param int $logId
     * @return bool
     * @throws \Exception
     */
    public function startRecording(
        MachineMedia $media,
        int $type = MachineRecording::TYPE_TEST,
        int $departmentId = 1,
        int $recordId = 0,
        int $logId = 0
    ): bool
    {
        if (MachineRecording::query()->where('machine_id', $media->machine_id)->where('status',
            MachineRecording::STATUS_STARTING)->exists()) {
            $this->stopRecording($media);
        }
        $machineRecording = new MachineRecording();
        $machineRecording->type = $type;
        $machineRecording->machine_id = $media->machine_id;
        $machineRecording->machine_code = $media->machine->code;
        $machineRecording->machine_name = $media->machine->name;
        $machineRecording->media_id = $media->id;
        $machineRecording->department_id = $departmentId;
        $machineRecording->player_game_record_id = $recordId;
        $machineRecording->player_game_log_id = $logId;
        $machineRecording->start_time = date('Y-m-d H:i:s');
        try {
            $url = $media->push_ip . '/' . $media->media_app . '/rest/v2/broadcasts/' . $media->stream_name . '/recording/true?recordType=mp4&name=' . $media->stream_name . uniqid();

            // 通过 gk_work 代理调用
            $response = $this->proxyRequest('PUT', $url, [], ['Content-Type' => 'application/json'], 5);

        } catch (\Exception) {
            throw new Exception(admin_trans('message.media.media_request_error'));
        }

        if (!empty($response)) {
            $this->log->info('startRecording', [$response]);
            if (empty($response['success'])) {
                $machineRecording->status = MachineRecording::STATUS_FAIL;
                $machineRecording->save();
                throw new \Exception(trans('stop_media_recording_fail', [], 'message'));
            }
            $machineRecording->data_id = $response['dataId'];
        } else {
            $machineRecording->status = MachineRecording::STATUS_FAIL;
            $machineRecording->save();
            throw new \Exception(trans('media_sever_fail', [], 'message'));
        }
        $machineRecording->status = MachineRecording::STATUS_STARTING;
        $machineRecording->save();
        
        return true;
    }
    
    /**
     * 停止录制
     * @param MachineMedia $media
     * @return bool
     * @throws \Exception
     */
    public function stopRecording(MachineMedia $media): bool
    {
        try {
            $url = $media->push_ip . '/' . $media->media_app . '/rest/v2/broadcasts/' . $media->stream_name . '/recording/false?recordType=mp4';

            // 通过 gk_work 代理调用
            $response = $this->proxyRequest('PUT', $url, [], ['Content-Type' => 'application/json'], 5);

        } catch (\Exception) {
            throw new Exception(admin_trans('message.media.media_request_error'));
        }

        if (!empty($response)) {
            $this->log->info('stopRecording', [$response]);
            /** @var MachineRecording $startRecording */
            $startRecording = MachineRecording::query()
                ->where('media_id', $media->id)
                ->where('data_id', $response['dataId'])
                ->where('status', MachineRecording::STATUS_STARTING)
                ->first();
            if (empty($response['success'])) {
                if (!empty($startRecording)) {
                    $startRecording->status = MachineRecording::STATUS_FAIL;
                    $startRecording->save();
                }
            } else {
                if (!empty($startRecording)) {
                    $startRecording->status = MachineRecording::STATUS_COMPLETE;
                    $startRecording->end_time = date('Y-m-d H:i:s');
                    $startRecording->save();
                }
            }
            return true;
        }
        
        return false;
    }
    
    /**
     * 获取腾讯流观看人数
     * @param MachineMediaPush $machineMediaPush
     * @return int
     * @throws \Exception
     */
    public function getTencentViewers(MachineMediaPush $machineMediaPush): int
    {
        try {
            $cred = new Credential($machineMediaPush->machineTencentPlay->api_appid,
                $machineMediaPush->machineTencentPlay->api_key);
            $httpProfile = new HttpProfile();
            $httpProfile->setEndpoint("live.tencentcloudapi.com");
            $clientProfile = new ClientProfile();
            $clientProfile->setHttpProfile($httpProfile);
            $client = new LiveClient($cred, "", $clientProfile);
            $req = new DescribeStreamPlayInfoListRequest();
            $params = [
                'StreamName' => $machineMediaPush->machine_code . '_' . $machineMediaPush->endpoint_service_id,
                'StartTime' => date('Y-m-d H:i:s', strtotime('-2 minute')),
                'EndTime' => date('Y-m-d H:i:s', strtotime('-1 minute')),
                'ServiceName' => 'LEB',
            ];
            $req->fromJsonString(json_encode($params));
            $resp = $client->DescribeStreamPlayInfoList($req)->DataInfoList;
            $lastItem = end($resp);
        } catch (TencentCloudSDKException $e) {
            $this->log->error('getTencentViewers', [$e->getMessage()]);
            throw new \Exception($e->getMessage());
        }
        return $lastItem->Online;
    }
    
    /**
     * 恢复流推流
     * @param $machineCode
     * @return int
     * @throws \Exception
     */
    public function resumeLiveStream($machineCode): int
    {
        /** @var MachineTencentPlay $machineTencentPlay */
        $machineTencentPlay = MachineTencentPlay::query()->first();
        try {
            $cred = new Credential($machineTencentPlay->api_appid, $machineTencentPlay->api_key);
            $httpProfile = new HttpProfile();
            $httpProfile->setEndpoint("live.tencentcloudapi.com");
            $clientProfile = new ClientProfile();
            $clientProfile->setHttpProfile($httpProfile);
            $client = new LiveClient($cred, "", $clientProfile);
            $req = new ResumeLiveStreamRequest();
            $params = [
                'AppName' => 'live',
                'DomainName' => $machineTencentPlay->push_domain,
                'StreamName' => $machineCode
            ];
            $req->fromJsonString(json_encode($params));
            $client->ResumeLiveStream($req);
        } catch (TencentCloudSDKException $e) {
            throw new \Exception($e->getMessage());
        }
        
        return true;
    }
    
    /**
     * 禁用流推流
     * @param $machineCode
     * @return int
     * @throws \Exception
     */
    public function forbidLiveStream($machineCode): int
    {
        /** @var MachineTencentPlay $machineTencentPlay */
        $machineTencentPlay = MachineTencentPlay::query()->first();
        try {
            $cred = new Credential($machineTencentPlay->api_appid, $machineTencentPlay->api_key);
            $httpProfile = new HttpProfile();
            $httpProfile->setEndpoint("live.tencentcloudapi.com");
            $clientProfile = new ClientProfile();
            $clientProfile->setHttpProfile($httpProfile);
            $client = new LiveClient($cred, "", $clientProfile);
            $req = new ForbidLiveStreamRequest();
            $params = [
                'AppName' => 'live',
                'DomainName' => $machineTencentPlay->push_domain,
                'StreamName' => $machineCode
            ];
            $req->fromJsonString(json_encode($params));
            $client->ForbidLiveStream($req);
        } catch (TencentCloudSDKException $e) {
            throw new \Exception($e->getMessage());
        }
        
        return true;
    }
    
    /**
     * 获取腾讯流观看5分钟人数
     * @param $apiAppid
     * @param $apiKey
     * @param $streamName
     * @return int
     */
    public function getTencentViewers2($apiAppid, $apiKey, $streamName): int
    {
        try {
            $cred = new Credential($apiAppid, $apiKey);
            $httpProfile = new HttpProfile();
            $httpProfile->setEndpoint("live.tencentcloudapi.com");
            $clientProfile = new ClientProfile();
            $clientProfile->setHttpProfile($httpProfile);
            $client = new LiveClient($cred, "", $clientProfile);
            $req = new DescribeStreamPlayInfoListRequest();
            $params = [
                'StreamName' => $streamName,
                'StartTime' => date('Y-m-d H:i:s', strtotime('-3 minute')),
                'EndTime' => date('Y-m-d H:i:s', strtotime('-1 minute')),
                'ServiceName' => 'LEB',
            ];
            $req->fromJsonString(json_encode($params));
            $resp = $client->DescribeStreamPlayInfoList($req)->DataInfoList;
        } catch (TencentCloudSDKException $e) {
            $this->log->error('getTencentViewers5', [$e->getMessage()]);
            return false;
        }
        $hasViewer = false;
        if (count($resp) == 1) {
            return true;
        }
        foreach ($resp as $item) {
            if ($item->Online > 0) {
                $hasViewer = true;
                break;
            }
        }
        
        return $hasViewer;
    }
    
    /**
     * 重设视讯流
     * @param MachineMediaPush $machineMediaPush
     * @return false|string
     */
    public function rebuildMedia(MachineMediaPush $machineMediaPush): bool|string
    {
        Db::beginTransaction();
        try {
            $pushList = [];
            $insertData = [];
            /** @var MachineTencentPlay $machineTencentPlay */
            $machineTencentPlay = MachineTencentPlay::query()->where('id',
                $machineMediaPush->machine_tencent_play_id)->first();
            $pushData = getPushUrl($machineMediaPush->machine->code, $machineTencentPlay->push_domain,
                $machineTencentPlay->push_key);
            $pushList[] = [
                'type' => 'generic',
                'rtmpUrl' => $pushData['rtmp_url'],
                'endpointServiceId' => $pushData['endpoint_service_id'],
            ];
            $insertData[] = [
                'machine_id' => $machineMediaPush->machine_id,
                'media_id' => $machineMediaPush->media->id,
                'endpoint_service_id' => $pushData['endpoint_service_id'],
                'expiration_date' => $pushData['expiration_date'],
                'machine_code' => $machineMediaPush->media->machine->code,
                'rtmp_url' => $pushData['rtmp_url'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'machine_tencent_play_id' => $machineTencentPlay->id,
            ];
            $result = $this->resetMachineStream($machineMediaPush->media->machine->type,
                $machineMediaPush->media->stream_name, $machineMediaPush->media->machine->code,
                $machineMediaPush->media->media_ip, '', '', 'WebRTCAppEE', '', $pushList);
            if ($result && $result['success']) {
                $machineMediaPush->media->stream_name = $result['dataId'];
            } else {
                $machineMediaPush->media->stream_name = -1;
            }
            
            $machineMediaPush->media->push();
            MachineMediaPush::query()->where('id', $machineMediaPush->id)->delete();
            if (!empty($insertData)) {
                MachineMediaPush::query()->insert($insertData);
            }
            Db::commit();
        } catch (Exception) {
            Db::rollback();
            return false;
        }
        
        return $machineMediaPush->media->machine->code . $pushData['endpoint_service_id'];
    }
    
    /**
     * @param int $type 机台类型
     * @param string $streamName 串流码
     * @param string $code 机台编号
     * @param string $mediaIp 视频ip
     * @param string $oldPushIp 推流ip
     * @param string $newPushIp 新推流ip
     * @param string $oldMediaApp 媒体服务app
     * @param string $newMediaApp 新媒体服务app
     * @param array $pushList
     * @return mixed
     * @throws Exception
     */
    public function resetMachineStream(
        int $type,
        string $streamName = '',
        string $code = '',
        string $mediaIp = '',
        string $oldPushIp = '',
        string $newPushIp = '',
        string $oldMediaApp = 'WebRTCAppEE',
        string $newMediaApp = '',
        array $pushList = []
    ): mixed {
        if (!empty($streamName)) {
            $this->deleteMachineStream($streamName);
        }
        if (empty($code)) {
            throw new Exception(admin_trans('message.media.media_name_must'));
        }
        if (empty($mediaIp)) {
            throw new Exception(admin_trans('message.media.media_url_must'));
        }
        if (!empty($newPushIp) && $oldPushIp != $newPushIp) {
            $this->domain = $newPushIp;
        }
        if (!empty($newMediaApp) && $oldMediaApp != $newMediaApp) {
            $this->mediaApp = $newMediaApp;
        }
        return $this->createMachineStream($code, $mediaIp, $type, $pushList);
    }
    
    /**
     * 删除流
     * @param $streamName
     * @param string $domain
     * @param string $oldMediaApp
     * @return bool
     */
    public function deleteMachineStream($streamName, string $domain = '', string $oldMediaApp = ''): bool
    {
        $domain = !empty($domain) ? $domain : $this->domain;
        $mediaApp = !empty($oldMediaApp) ? $oldMediaApp : $this->mediaApp;
        try {
            $url = $domain . '/' . $mediaApp . '/rest/v2/broadcasts/' . $streamName;

            // 通过 gk_work 代理调用
            $response = $this->proxyRequest('DELETE', $url, [], ['Content-Type' => 'application/json'], 5);

        } catch (\Exception) {
            return false;
        }

        $this->log->info('deleteMachineStream', [
            'response' => $response,
            'url' => $url,
        ]);

        if (!empty($response)) {
            if (empty($response['success'])) {
                return false;
            }
        } else {
            return false;
        }
        return true;
    }
    
    /**
     * 创建视频流
     * @param string $name 名称
     * @param string $stream_url 流ip+端口
     * @param int $type 机台类型
     * @param array $pushList
     * @return mixed
     * @throws Exception
     */
    public function createMachineStream(string $name, string $stream_url, int $type, array $pushList = []): mixed
    {
        if (strpos($stream_url, 'rtsp') !== false) {
            throw new Exception(admin_trans('message.media.media_stream_url_error'));
        }
        
        $stream_url = $type == GameType::TYPE_FISH ? str_replace('{ip}', $stream_url,
            $this->fish_stream_url) : str_replace('stream_url', $stream_url, $this->stream_url);
        try {
            $url = $this->domain . '/' . $this->mediaApp . '/rest/v2/broadcasts/create?autoStart=true';
            $body = [
                'hlsViewerCount' => 0,
                'mp4Enabled' => 0,
                'name' => $name,
                'playListItemList' => [],
                'rtmpViewerCount' => 0,
                'streamUrl' => $stream_url,
                'type' => 'streamSource',
                'webRTCViewerCount' => 0,
                'endPointList' => $pushList
            ];

            // 通过 gk_work 代理调用
            $response = $this->proxyRequest('POST', $url, $body, ['Content-Type' => 'application/json'], 5);

        } catch (\Exception $e) {
            $this->log->info('createMachineStream', [$e->getMessage()]);
            throw new Exception(admin_trans('message.media.media_request_error'));
        }

        $this->log->info('createMachineStream', [$response]);

        // 代理返回的已经是解析后的数组数据
        if (!empty($response)) {
            if (empty($response['success'])) {
                throw new Exception(admin_trans('message.media.media_stream_pull_error'));
            }
        } else {
            throw new Exception(admin_trans('message.media.media_request_error'));
        }

        return $response;
    }
    
    /**
     * 恢复流推流
     * @param $machineCode
     * @return string
     * @throws \Exception
     */
    public function describeLiveStreamState($machineCode): string
    {
        /** @var MachineTencentPlay $machineTencentPlay */
        $machineTencentPlay = MachineTencentPlay::query()->first();
        try {
            $cred = new Credential($machineTencentPlay->api_appid, $machineTencentPlay->api_key);
            $httpProfile = new HttpProfile();
            $httpProfile->setEndpoint("live.tencentcloudapi.com");
            $clientProfile = new ClientProfile();
            $clientProfile->setHttpProfile($httpProfile);
            $client = new LiveClient($cred, "", $clientProfile);
            $req = new DescribeLiveStreamStateRequest();
            $params = [
                'AppName' => 'live',
                'DomainName' => $machineTencentPlay->push_domain,
                'StreamName' => $machineCode
            ];
            $req->fromJsonString(json_encode($params));
            $req = $client->DescribeLiveStreamState($req);
        } catch (TencentCloudSDKException $e) {
            throw new \Exception($e->getMessage());
        }
        
        return $req->StreamState;
    }
    
    /**
     * 获取腾讯流观看5分钟人数
     * @param MachineMedia $media
     * @return array
     */
    public function getTencentViewersTest(MachineMedia $media): array
    {
        /** @var MachineMediaPush $machineMediaPush */
        $machineMediaPush = $media->machineMediaPush->first();
        try {
            $cred = new Credential($machineMediaPush->machineTencentPlay->api_appid,
                $machineMediaPush->machineTencentPlay->api_key);
            $httpProfile = new HttpProfile();
            $httpProfile->setEndpoint("live.tencentcloudapi.com");
            $clientProfile = new ClientProfile();
            $clientProfile->setHttpProfile($httpProfile);
            $client = new LiveClient($cred, "", $clientProfile);
            $req = new DescribeStreamPlayInfoListRequest();
            $params = [
                'StreamName' => $machineMediaPush->machine_code . '_' . $machineMediaPush->endpoint_service_id,
                'StartTime' => date('Y-m-d H:i:s', strtotime('-6 minute')),
                'EndTime' => date('Y-m-d H:i:s', strtotime('-1 minute')),
                'ServiceName' => 'LEB',
            ];
            $req->fromJsonString(json_encode($params));
            $resp = $client->DescribeStreamPlayInfoList($req)->DataInfoList;
            $this->log->info('getTencentViewers5', [$resp, $machineMediaPush->machine_code, $params]);
        } catch (TencentCloudSDKException $e) {
            $this->log->error('getTencentViewers5', [$e->getMessage()]);
            return [];
        }
        
        return $resp;
    }
    
    /**
     * 获取流信息
     * @param $streamName
     * @return mixed
     * @throws Exception
     */
    public function getBroadcasts($streamName): mixed
    {
        try {
            $url = $this->domain . '/' . $this->mediaApp . '/rest/v2/broadcasts/' . $streamName;

            // 通过 gk_work 代理调用
            $response = $this->proxyRequest('GET', $url, [], [], 5);

        } catch (\Exception) {
            throw new Exception(admin_trans('common.video_host_request_failed'));
        }

        if (empty($response)) {
            throw new Exception(admin_trans('common.get_stream_info_failed'));
        }

        return $response;
    }
}