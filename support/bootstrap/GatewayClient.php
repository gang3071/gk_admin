<?php

namespace support\bootstrap;

use GatewayWorker\Lib\Gateway;
use Webman\Bootstrap;

/**
 * GatewayWorker 客户端配置
 */
class GatewayClient implements Bootstrap
{
    /**
     * @param \Workerman\Worker|null $worker
     */
    public static function start($worker)
    {
        // 从环境变量读取 Register 服务地址
        $registerAddress = getenv('GATEWAY_REGISTER_ADDRESS') ?: '127.0.0.1:1236';

        // 支持多个地址，用逗号分隔
        if (strpos($registerAddress, ',') !== false) {
            Gateway::$registerAddress = array_map('trim', explode(',', $registerAddress));
        } else {
            Gateway::$registerAddress = $registerAddress;
        }
    }
}
