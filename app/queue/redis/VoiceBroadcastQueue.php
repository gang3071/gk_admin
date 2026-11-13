<?php

namespace app\queue\redis;

use addons\webman\service\GoogleTtsHttpService;
use support\Log;
use Webman\Push\Api;
use Webman\RedisQueue\Consumer;

/**
 * 语音播报队列消费者
 *
 * 由 gk_work 在触发广播（彩金中奖/钢珠高分）后推入此队列
 * 负责：生成 Gemini TTS 语音文件 → 推送到后台管理 WebSocket 频道
 *
 * 队列名：voice-broadcast
 * 推入方：gk_work/app/functions.php sendSocketMessage()
 */
class VoiceBroadcastQueue implements Consumer
{
    public $queue = 'voice-broadcast';

    public $connection = 'default';

    public function consume($data): void
    {
        try {
            $departmentId = (int)($data['department_id'] ?? 0);
            $storeAdminId = (int)($data['store_admin_id'] ?? 0);
            $voiceText    = trim($data['voice_text'] ?? '');
            $title        = $data['title'] ?? '';
            $msgType      = $data['msg_type'] ?? '';

            if (!$departmentId || !$voiceText) {
                Log::warning('[VoiceBroadcast] 数据不完整，跳过', $data);
                return;
            }

            Log::info('[VoiceBroadcast] 开始生成播报语音', [
                'department_id' => $departmentId,
                'msg_type'      => $msgType,
                'voice_text'    => $voiceText,
            ]);

            $result = GoogleTtsHttpService::generateBroadcastVoice($voiceText);

            if (!$result['success']) {
                Log::warning('[VoiceBroadcast] 语音生成失败，跳过播报', [
                    'department_id' => $departmentId,
                    'error'         => $result['error'] ?? '',
                ]);
                return;
            }

            // 前端使用：new Audio('data:audio/wav;base64,' + voice_base64).play()
            // store_admin_id 用于前端判断是否本店（匹配则额外弹通知）
            $this->sendToAdminChannel($departmentId, [
                'msg_type'        => 'voice_broadcast',
                'title'           => $title,
                'content'         => $voiceText,
                'voice_base64'    => $result['base64'],
                'voice_mime'      => $result['mime'],
                'department_id'   => $departmentId,
                'store_admin_id'  => $storeAdminId,
                'source_msg_type' => $msgType,
                'timestamp'       => time(),
            ]);

            Log::info('[VoiceBroadcast] 播报语音已推送', [
                'department_id' => $departmentId,
                'msg_type'      => $msgType,
                'audio_size_kb' => round(strlen($result['base64'] ?? '') * 3 / 4 / 1024, 1),
            ]);

        } catch (\Throwable $e) {
            Log::error('[VoiceBroadcast] 处理异常', [
                'data'  => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function sendToAdminChannel(int $departmentId, array $content): void
    {
        try {
            $api = new Api(
                env('PUSH_API_URL', 'http://10.140.0.6:3232'),
                env('PUSH_APP_KEY', '20f94408fc4c52845f162e92a253c7a3'),
                env('PUSH_APP_SECRET', '3151f8648a6ccd9d4515386f34127e28')
            );

            // 对应前端：private-admin_group-${type}-${department_id}，type=store
            $channel = 'private-admin_group-store-' . $departmentId;

            $api->trigger($channel, 'message', [
                'from_uid' => 'voice_system',
                'content'  => json_encode($content, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Exception $e) {
            Log::error('[VoiceBroadcast] 推送门店 WebSocket 失败', [
                'department_id' => $departmentId,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
