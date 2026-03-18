<?php


namespace addons\webman\middleware;

use addons\webman\service\ClientIpAuthenticator;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class IpAuthMiddleware implements MiddlewareInterface
{
    protected ClientIpAuthenticator $authenticator;
    
    public function __construct()
    {
        $this->authenticator = new ClientIpAuthenticator($this->getConfig());
    }
    
    /**
     * 中间件处理
     */
    public function process(Request $request, callable $handler): Response
    {
        // 获取真实IP
        $realIp = $this->getRealIp($request);
        // IP认证
        $authResult = $this->authenticator->authenticate($realIp);
        
        if (!$authResult['success']) {
            return response('fail', 400);
        }
        
        return $handler($request);
    }
    
    /**
     * 获取客户端真实IP
     */
    protected function getRealIp(Request $request): string
    {
        $remoteIp = $request->getRemoteIp();
        
        // 检查常见的IP头部
        $ipHeaders = [
            'x-forwarded-for',
            'x-real-ip',
            'cf-connecting-ip',
            'true-client-ip',
            'ali-cdn-real-ip',
        ];
        
        foreach ($ipHeaders as $header) {
            $ip = $request->header($header);
            if (!empty($ip)) {
                $ip = current(explode(',', $ip));
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $remoteIp;
    }
    
    /**
     * 获取配置
     */
    protected function getConfig(): array
    {
        return [
            'enable_whitelist' => env('IP_WHITELIST_ENABLE', false),
        ];
    }
}