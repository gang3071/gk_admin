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
         * 使用 Gemini TTS（推荐）
         *
         * true = 使用 Gemini 3.1 Flash TTS（音质更好，支持风格指令）
         * false = 使用传统 Wavenet TTS（音质一般）
         *
         * 优势对比：
         * - Gemini TTS: 自然语音，支持风格指令，适合客服场景
         * - Wavenet TTS: 传统 TTS，参数控制有限，音质较机械
         */
        'use_gemini' => env('GOOGLE_TTS_USE_GEMINI', true),

        /**
         * Gemini TTS 配置（新版，推荐）
         */
        'gemini_voice' => env('GOOGLE_TTS_GEMINI_VOICE', 'Achernar'), // 可选：Achernar, Betelgeuse
        'gemini_language' => env('GOOGLE_TTS_GEMINI_LANGUAGE', 'zh-TW'), // zh-TW(繁体) 或 zh-CN(简体)

        /**
         * Gemini TTS 风格指令（自然语言描述）
         *
         * 预设风格模板：
         *
         * 1. 专业客服（默认，推荐）
         *    'Read aloud in a clear, professional customer service voice, warm and attentive, as if a waitress is politely announcing a customer request.'
         *
         * 2. 紧急提醒
         *    'Read aloud in an urgent, alerting tone, emphasizing the device name clearly and loudly, like an important notification.'
         *
         * 3. 温柔提醒
         *    'Read aloud in a gentle, soft female voice, calm and soothing, like a friendly reminder.'
         *
         * 4. 中性播报
         *    'Read aloud in a neutral, clear announcer voice, straightforward and easy to understand.'
         */
        'gemini_style' => env(
            'GOOGLE_TTS_GEMINI_STYLE',
            'Read aloud in a clear, professional customer service voice, warm and attentive, as if a waitress is politely announcing a customer request.'
        ),

        /**
         * 传统 Wavenet TTS 配置（备用）
         *
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
