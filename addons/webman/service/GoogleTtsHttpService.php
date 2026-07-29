<?php

namespace addons\webman\service;

use Google\Cloud\Storage\StorageClient;
use support\Log;
use WebmanTech\LaravelHttpClient\Facades\Http;

/**
 * Google Cloud Text-to-Speech 服务（HTTP REST API 版本）
 *
 * 优点：
 * - 不依赖 Google SDK
 * - 兼容 PHP 8.0+
 * - 无依赖冲突
 */
class GoogleTtsHttpService
{
    /**
     * Google TTS REST API 端点
     */
    private const API_ENDPOINT = 'https://texttospeech.googleapis.com/v1/text:synthesize';

    /**
     * Gemini TTS API 端点（预览版）
     */
    private const GEMINI_TTS_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-tts-preview:generateContent';

    /**
     * 语音文件存储目录（相对于 public）
     */
    private const VOICE_DIR = 'voice/device';

    /**
     * 台湾中文语音参数
     */
    private const TW_LANGUAGE_CODE = 'cmn-TW';
    private const TW_VOICE_NAME = 'cmn-TW-Wavenet-A';

    /**
     * Gemini TTS 语音参数
     */
    private const GEMINI_VOICE = 'Kore'; // 可选：Kore, Puck, Charon, Achernar 等

    /**
     * 生成设备呼叫服务语音文件
     *
     * @param string $deviceName 设备名称
     * @param int $deviceId 设备ID
     * @return array ['success' => bool, 'url' => string|null, 'path' => string|null, 'error' => string|null]
     */
    public static function generateDeviceCallServiceVoice(string $deviceName, int $deviceId): array
    {
        try {
            // 1. 验证设备名称
            if (empty($deviceName)) {
                throw new \Exception('设备名称不能为空');
            }

            // 2. 构建语音文本
            $text = $deviceName . '呼叫服务';

            // 3. 生成语音文件
            return self::synthesizeSpeech($text, $deviceId);

        } catch (\Exception $e) {
            Log::error('Google TTS 生成语音失败', [
                'device_id' => $deviceId,
                'device_name' => $deviceName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'url' => null,
                'path' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 合成语音（HTTP REST API）
     *
     * @param string $text 要合成的文本
     * @param int $deviceId 设备ID
     * @return array
     * @throws \Exception
     */
    private static function synthesizeSpeech(string $text, int $deviceId): array
    {
        // 1. 获取 API Key
        $apiKey = self::getApiKey();

        // 2. 检查是否启用 Gemini TTS（优先使用，音质更好）
        $useGemini = config('google.tts.use_gemini', true);

        if ($useGemini) {
            return self::synthesizeSpeechWithGemini($text, $deviceId, $apiKey);
        } else {
            return self::synthesizeSpeechWithWavenet($text, $deviceId, $apiKey);
        }
    }

    /**
     * 使用 Gemini TTS 合成语音（推荐）
     *
     * @param string $text 要合成的文本
     * @param int $deviceId 设备ID
     * @param string $apiKey API Key
     * @return array
     * @throws \Exception
     */
    private static function synthesizeSpeechWithGemini(string $text, int $deviceId, string $apiKey): array
    {
        // 1. 获取风格指令（可配置）
        $styleInstructions = config(
            'google.tts.gemini_style',
            'Read aloud in a clear, professional customer service voice, warm and attentive, as if a waitress is politely announcing a customer request.'
        );

        // 2. 获取语音配置
        $voiceName = config('google.tts.gemini_voice', self::GEMINI_VOICE);

        // 3. 构建 Gemini TTS 请求体（正确格式）
        $requestBody = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $styleInstructions . ' ' . $text]  // 风格指令前置
                    ]
                ]
            ],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => [
                    'voiceConfig' => [
                        'prebuiltVoiceConfig' => [
                            'voiceName' => $voiceName
                        ]
                    ]
                ]
            ]
        ];

        // 4. 调用 Gemini TTS API
        $response = Http::timeout(30)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $apiKey
            ])
            ->post(self::GEMINI_TTS_ENDPOINT, $requestBody);

        if (!$response->successful()) {
            $error = $response->json('error.message', 'Unknown error');
            throw new \Exception("Gemini TTS API 错误: {$error}");
        }

        // 5. 获取音频内容（Base64 编码）
        $result = $response->json();
        $audioContentBase64 = $result['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? null;

        if (empty($audioContentBase64)) {
            throw new \Exception('Gemini TTS API 返回空音频内容');
        }

        // 6. 解码音频内容
        $audioContent = base64_decode($audioContentBase64);

        // 7. 保存音频文件
        return self::saveVoiceFile($audioContent, $deviceId);
    }

    /**
     * 使用传统 Wavenet TTS 合成语音（备用方案）
     *
     * @param string $text 要合成的文本
     * @param int $deviceId 设备ID
     * @param string $apiKey API Key
     * @return array
     * @throws \Exception
     */
    private static function synthesizeSpeechWithWavenet(string $text, int $deviceId, string $apiKey): array
    {
        // 构建请求体
        $requestBody = [
            'input' => [
                'text' => $text
            ],
            'voice' => [
                'languageCode' => self::TW_LANGUAGE_CODE,
                'name' => self::TW_VOICE_NAME,
                'ssmlGender' => 'FEMALE'
            ],
            'audioConfig' => [
                'audioEncoding' => 'MP3',
                'speakingRate' => (float)config('google.tts.speaking_rate', 1.0),
                'pitch' => (float)config('google.tts.pitch', 0.0),
                'volumeGainDb' => (float)config('google.tts.volume_gain_db', 0.0),
            ]
        ];

        // 调用 Google TTS API
        $response = Http::timeout(30)
            ->post(self::API_ENDPOINT . '?key=' . $apiKey, $requestBody);

        if (!$response->successful()) {
            $error = $response->json('error.message', 'Unknown error');
            throw new \Exception("Google TTS API 错误: {$error}");
        }

        // 获取音频内容（Base64 编码）
        $audioContentBase64 = $response->json('audioContent');
        if (empty($audioContentBase64)) {
            throw new \Exception('Google TTS API 返回空音频内容');
        }

        // 解码音频内容
        $audioContent = base64_decode($audioContentBase64);

        // 保存音频文件
        return self::saveVoiceFile($audioContent, $deviceId);
    }

    /**
     * 获取 API Key
     *
     * @return string
     * @throws \Exception
     */
    private static function getApiKey(): string
    {
        // 方式 1：直接从环境变量读取 API Key
        $apiKey = env('GOOGLE_TTS_API_KEY');
        if (!empty($apiKey)) {
            return $apiKey;
        }

        // 方式 2：从服务账号凭证文件中提取（需要额外步骤生成 API Key）
        $credentialsPath = config('google.credentials', env('GOOGLE_APPLICATION_CREDENTIALS'));

        if (empty($credentialsPath) || !file_exists($credentialsPath)) {
            throw new \Exception('Google Cloud 凭证文件不存在，请配置 GOOGLE_TTS_API_KEY 环境变量');
        }

        // 读取凭证文件获取 project_id，然后需要用户手动创建 API Key
        $credentials = json_decode(file_get_contents($credentialsPath), true);
        $projectId = $credentials['project_id'] ?? '';

        throw new \Exception(
            "请在 Google Cloud Console 创建 API Key：\n" .
            "1. 访问 https://console.cloud.google.com/apis/credentials?project={$projectId}\n" .
            "2. 点击 'CREATE CREDENTIALS' → 'API key'\n" .
            "3. 复制 API Key 并添加到 .env：GOOGLE_TTS_API_KEY=你的API_KEY\n" .
            "4. 限制 API Key 仅用于 Cloud Text-to-Speech API"
        );
    }

    /**
     * 保存语音文件到 Google Cloud Storage
     *
     * @param string $audioContent 音频二进制内容
     * @param int $deviceId 设备ID
     * @return array
     * @throws \Exception
     */
    private static function saveVoiceFile(string $audioContent, int $deviceId): array
    {
        // 1. 生成文件名和路径
        $timestamp = time();
        $filename = "device_{$deviceId}_{$timestamp}.mp3";
        $ossPath = self::VOICE_DIR . '/' . $filename;

        // 2. 上传到 Google Cloud Storage
        try {
            $storageClient = self::getStorageClient();
            $bucket = $storageClient->bucket(config('plugin.rockys.ex-admin-webman.filesystems.disks.google_oss.bucket'));

            // 直接从内存上传（不写入本地文件）
            $object = $bucket->upload($audioContent, [
                'name' => $ossPath,
                'metadata' => [
                    'contentType' => 'audio/mpeg',
                    'cacheControl' => 'public, max-age=31536000', // 缓存1年
                ],
                'predefinedAcl' => 'publicRead', // 设置公开访问
            ]);

            // 3. 获取公开访问 URL
            $url = "https://storage.googleapis.com/{$bucket->name()}/{$ossPath}";

            // 4. 删除该设备的旧语音文件
            self::cleanOldVoiceFiles($deviceId, $filename, $bucket);

            Log::info('Google TTS 生成语音成功并上传到 OSS', [
                'device_id' => $deviceId,
                'oss_path' => $ossPath,
                'url' => $url,
                'file_size' => strlen($audioContent),
            ]);

        } catch (\Exception $e) {
            throw new \Exception('上传到 Google Cloud Storage 失败: ' . $e->getMessage());
        }

        return [
            'success' => true,
            'url' => $url,
            'path' => $ossPath,
            'error' => null,
        ];
    }

    /**
     * 获取 Google Cloud Storage 客户端
     *
     * @return \Google\Cloud\Storage\StorageClient
     * @throws \Exception
     */
    private static function getStorageClient()
    {
        $keyFile = config('plugin.rockys.ex-admin-webman.filesystems.disks.google_oss.key_file');
        $projectId = config('plugin.rockys.ex-admin-webman.filesystems.disks.google_oss.project_id');

        if (empty($keyFile) || !file_exists($keyFile)) {
            throw new \Exception('Google Cloud Storage 密钥文件不存在: ' . $keyFile);
        }

        return new \Google\Cloud\Storage\StorageClient([
            'keyFilePath' => $keyFile,
            'projectId' => $projectId,
        ]);
    }

    /**
     * 清理旧的语音文件（从 Google Cloud Storage 删除）
     *
     * @param int $deviceId 设备ID
     * @param string $currentFilename 当前文件名（保留）
     * @param \Google\Cloud\Storage\Bucket $bucket OSS Bucket
     * @return void
     */
    private static function cleanOldVoiceFiles(int $deviceId, string $currentFilename, $bucket): void
    {
        try {
            $prefix = self::VOICE_DIR . "/device_{$deviceId}_";
            $objects = $bucket->objects(['prefix' => $prefix]);

            foreach ($objects as $object) {
                $objectName = $object->name();
                $basename = basename($objectName);

                // 跳过当前文件
                if ($basename === $currentFilename) {
                    continue;
                }

                // 删除旧文件
                $object->delete();
                Log::info('从 OSS 删除旧语音文件', ['oss_path' => $objectName]);
            }
        } catch (\Exception $e) {
            Log::warning('清理 OSS 旧语音文件失败', [
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 删除设备的所有语音文件（从 Google Cloud Storage）
     *
     * @param int $deviceId 设备ID
     * @return bool
     */
    public static function deleteDeviceVoiceFiles(int $deviceId): bool
    {
        try {
            $storageClient = self::getStorageClient();
            $bucket = $storageClient->bucket(config('plugin.rockys.ex-admin-webman.filesystems.disks.google_oss.bucket'));

            $prefix = self::VOICE_DIR . "/device_{$deviceId}_";
            $objects = $bucket->objects(['prefix' => $prefix]);

            $deletedCount = 0;
            foreach ($objects as $object) {
                $object->delete();
                $deletedCount++;
            }

            Log::info('从 OSS 删除设备所有语音文件', [
                'device_id' => $deviceId,
                'deleted_count' => $deletedCount,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('从 OSS 删除设备语音文件失败', [
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 批量重新生成语音文件
     */
    public static function batchRegenerateVoices(array $devices): array
    {
        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($devices as $device) {
            $deviceId = $device['id'] ?? 0;
            $deviceName = $device['device_name'] ?? '';

            if (empty($deviceId) || empty($deviceName)) {
                $failedCount++;
                $errors[] = "设备ID或名称为空: ID={$deviceId}";
                continue;
            }

            $result = self::generateDeviceCallServiceVoice($deviceName, $deviceId);

            if ($result['success']) {
                $successCount++;
            } else {
                $failedCount++;
                $errors[] = "设备ID={$deviceId}: {$result['error']}";
            }

            // 防止API请求过快
            usleep(100000);
        }

        return [
            'success' => $successCount,
            'failed' => $failedCount,
            'errors' => $errors,
        ];
    }
}
