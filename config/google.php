<?php

/**
 * Google Cloud 服务配置
 */
return [
    /**
     * Google Cloud 凭证文件路径
     *
     * 获取方式：
     * 1. 访问 https://console.cloud.google.com
     * 2. 选择项目 -> IAM & Admin -> Service Accounts
     * 3. 创建服务账号 -> 创建密钥（JSON格式）
     * 4. 下载后保存到此路径
     */
    'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS', base_path() . '/config/google-cloud-credentials.json'),

    /**
     * Text-to-Speech API 配置
     */
    'tts' => [
        /**
         * 语音语言代码
         * cmn-TW: 台湾国语
         * cmn-CN: 大陆国语
         * yue-HK: 粤语（香港）
         */
        'language_code' => env('GOOGLE_TTS_LANGUAGE', 'cmn-TW'),

        /**
         * 语音名称
         *
         * 台湾女声选项：
         * - cmn-TW-Wavenet-A (女声，高质量)
         * - cmn-TW-Wavenet-B (男声，高质量)
         * - cmn-TW-Wavenet-C (男声，高质量)
         * - cmn-TW-Standard-A (女声，标准质量)
         * - cmn-TW-Standard-B (男声，标准质量)
         * - cmn-TW-Standard-C (男声，标准质量)
         */
        'voice_name' => env('GOOGLE_TTS_VOICE', 'cmn-TW-Wavenet-A'),

        /**
         * 语速（0.25 - 4.0，1.0 为正常速度）
         */
        'speaking_rate' => env('GOOGLE_TTS_SPEAKING_RATE', 1.0),

        /**
         * 音调（-20.0 - 20.0，0.0 为正常音调）
         */
        'pitch' => env('GOOGLE_TTS_PITCH', 0.0),

        /**
         * 音量增益（-96.0 - 16.0 dB）
         */
        'volume_gain_db' => env('GOOGLE_TTS_VOLUME_GAIN', 0.0),

        /**
         * 音频格式
         * MP3: 最常用，兼容性好
         * LINEAR16: 无损格式，文件较大
         * OGG_OPUS: 小文件，Chrome 支持
         */
        'audio_encoding' => env('GOOGLE_TTS_AUDIO_ENCODING', 'MP3'),
    ],
];
