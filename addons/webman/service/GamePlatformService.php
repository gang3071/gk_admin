<?php

namespace addons\webman\service;

use addons\webman\model\GamePlatform;
use addons\webman\model\Player;
use Exception;

/**
 * 游戏平台服务类
 * 整合所有调用 gk_work 游戏平台 API 的操作
 */
class GamePlatformService
{
    /**
     * gk_work 服务器地址
     */
    private string $workerHost;

    /**
     * gk_work 服务器端口
     */
    private int $workerPort;

    /**
     * 当前语言
     */
    private string $lang;

    /**
     * 当前玩家
     */
    private ?Player $player;

    /**
     * 构造函数
     *
     * @param string|null $lang 语言代码，默认从当前环境获取
     * @param Player|null $player 玩家对象，默认使用管理员玩家
     */
    public function __construct(?string $lang = null, ?Player $player = null)
    {
        $this->workerHost = env('GAME_PLATFORM_PROXY_HOST', '10.140.0.10');
        $this->workerPort = (int)env('GAME_PLATFORM_PROXY_PORT', '8788');

        // 获取语言
        if ($lang) {
            $this->lang = $lang;
        } else {
            $locale = locale();
            $this->lang = str_replace('_', '-', $locale);
        }

        // 获取玩家
        if ($player) {
            $this->player = $player;
        } else {
            $this->player = Player::query()->where('is_admin', 1)->first();
        }
    }

    /**
     * 进入游戏大厅
     *
     * @param int|GamePlatform $gamePlatform 游戏平台ID或对象
     * @return string 游戏大厅URL
     * @throws Exception
     */
    public function enterLobby($gamePlatform): string
    {
        // 如果传入的是ID，查询平台对象
        if (is_int($gamePlatform)) {
            $platform = GamePlatform::query()
                ->where('id', $gamePlatform)
                ->select(['id', 'code', 'name'])
                ->first();

            if (!$platform) {
                throw new Exception(admin_trans('game_platform.not_fount'));
            }
            $gamePlatform = $platform;
        }

        $data = $this->callApi('/api/admin/lobby-login', [
            'game_platform_id' => $gamePlatform->id,
        ]);

        return $data['url'] ?? $data['lobby_url'] ?? '';
    }

    /**
     * 调用 gk_work 游戏平台 API
     *
     * @param string $endpoint API 端点
     * @param array $data 请求数据
     * @param int $timeout 超时时间（秒）
     * @return array 响应数据
     * @throws Exception
     */
    private function callApi(string $endpoint, array $data = [], int $timeout = 10): array
    {
        if (!$this->player) {
            throw new Exception(admin_trans('game_platform.player_not_fount'));
        }

        $proxyUrl = "http://{$this->workerHost}:{$this->workerPort}{$endpoint}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $proxyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Player-Id: ' . $this->player->id,
            'Accept: application/json',
            'Content-Type: application/json',
            'Accept-Language: ' . $this->lang,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // 检查 curl 错误
        if ($curlError) {
            throw new Exception(admin_trans('message.system_busy') . ': ' . $curlError);
        }

        // 检查 HTTP 状态码
        if ($httpCode !== 200) {
            $errorMsg = admin_trans('message.system_busy') . ' (HTTP ' . $httpCode . ')';

            // 尝试解析响应中的错误信息
            if ($response) {
                $errorData = json_decode($response, true);
                if (!empty($errorData['msg'])) {
                    $errorMsg = $errorData['msg'];
                }
            }

            throw new Exception($errorMsg);
        }

        $result = json_decode($response, true);
        if (empty($result)) {
            throw new Exception(admin_trans('message.system_busy'));
        }

        if (isset($result['code']) && $result['code'] != 200) {
            throw new Exception($result['msg'] ?? admin_trans('game_platform.action_error'));
        }

        return $result['data'] ?? [];
    }

    /**
     * 获取游戏列表
     * 调用 gk_work API 获取并自动保存到数据库
     *
     * @param int|GamePlatform $gamePlatform 游戏平台ID或对象
     * @return array 响应数据
     * @throws Exception
     */
    public function getGameList($gamePlatform): array
    {
        // 如果传入的是ID，查询平台对象
        if (is_int($gamePlatform)) {
            $platform = GamePlatform::query()
                ->where('id', $gamePlatform)
                ->select(['id', 'code', 'name'])
                ->first();

            if (!$platform) {
                throw new Exception(admin_trans('game_platform.not_fount'));
            }
            $gamePlatform = $platform;
        }

        // 获取游戏列表超时时间设为30秒（可能数据量较大）
        return $this->callApi('/api/admin/get-game-list', [
            'game_platform_id' => $gamePlatform->id,
        ], 30);
    }

    /**
     * 进入指定游戏
     *
     * @param int|GamePlatform $gamePlatform 游戏平台ID或对象
     * @param string $gameCode 游戏代码
     * @return string 游戏URL
     * @throws Exception
     */
    public function enterGame($gamePlatform, string $gameCode): string
    {
        // 如果传入的是ID，查询平台对象
        if (is_int($gamePlatform)) {
            $platform = GamePlatform::query()
                ->where('id', $gamePlatform)
                ->select(['id', 'code', 'name'])
                ->first();

            if (!$platform) {
                throw new Exception(admin_trans('game_platform.not_fount'));
            }
            $gamePlatform = $platform;
        }

        $data = $this->callApi('/api/admin/enter-game', [
            'game_platform_id' => $gamePlatform->id,
            'game_code' => $gameCode,
        ]);

        return $data['url'] ?? '';
    }

    /**
     * 设置语言
     *
     * @param string $lang 语言代码
     * @return self
     */
    public function setLang(string $lang): self
    {
        $this->lang = $lang;
        return $this;
    }

    /**
     * 设置玩家
     *
     * @param Player $player 玩家对象
     * @return self
     */
    public function setPlayer(Player $player): self
    {
        $this->player = $player;
        return $this;
    }

    /**
     * 设置 gk_work 服务器地址
     *
     * @param string $host 主机地址
     * @param int $port 端口
     * @return self
     */
    public function setWorkerServer(string $host, int $port): self
    {
        $this->workerHost = $host;
        $this->workerPort = $port;
        return $this;
    }
}
