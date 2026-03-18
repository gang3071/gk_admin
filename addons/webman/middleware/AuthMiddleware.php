<?php

namespace addons\webman\middleware;

use addons\webman\Admin;
use addons\webman\model\AdminDepartment;
use addons\webman\model\AdminUser;
use addons\webman\model\Channel;
use ExAdmin\ui\support\Token;
use ExAdmin\ui\token\AuthException;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;


class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        list($class, $function) = Admin::getDispatch();
        if ($class != 'system' && $class != 'login') {
            try {
                Token::auth();
                /** @var AdminUser $user */
                $user = Admin::user();
                if ($user->type == AdminDepartment::TYPE_CHANNEL) {
                    if (!empty($user->department_id)) {
                        /** @var Channel $channel */
                        $channel = Channel::where('department_id', $user->department_id)->first();
                        if ($channel->status == 0 || $channel->department->status == 0) {
                            throw new AuthException('渠道已禁用', 40006);
                        }
                        if (!empty($channel->deleted_at) || !empty($channel->department->deleted_at)) {
                            throw new AuthException('渠道已删除', 40007);
                        }
                    } else {
                        throw new AuthException('账号异常', 40008);
                    }
                }
                if ($user->status == 0) {
                    throw new AuthException('账号已禁用', 40009);
                }
            } catch (AuthException $exception) {
                return response(
                    json_encode(['message' => $exception->getMessage(), 'code' => $exception->getCode()]),
                    401,
                    ['Content-Type' => 'application/json']);
            }
        }
        return $handler($request);
    }
}