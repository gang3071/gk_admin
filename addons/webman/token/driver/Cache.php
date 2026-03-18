<?php

namespace addons\webman\token\driver;


use ExAdmin\ui\token\TokenDriver;
use Support\Cache as C;

class Cache extends TokenDriver
{

    /**
     * 存储token
     * @param string $token token
     * @param int $expire 过期时长
     * @return bool
     */
    public function set($token, $expire)
    {
        return C::set(md5($token), $token, $expire);
    }

    /**
     * token是否可用
     * @param string $token
     * @return bool
     */
    public function has($token)
    {
        return C::has(md5($token));
    }

    /**
     * 删除token
     * @param string $token token
     * @return bool
     */
    public function delete($token)
    {
        return C::delete(md5($token));
    }

    /**
     * 存储最后token
     * @param int $id 用户id
     * @param string $token
     * @param int $expire
     * @return bool
     */
    public function setLastToken($id, $token, $expire)
    {
        return C::set('last_auth_token_' . $id, $token, $expire);
    }

    /**
     * 获取最后token
     * @param int $id 用户id
     * @return mixed
     */
    public function getLastToken($id)
    {
        return C::get('last_auth_token_' . $id);
    }

    /**
     * 获取主键
     * @return int
     */
    public function getPk()
    {
        return $this->model->getKeyName();
    }

    /**
     * 获取当前用户
     * @return mixed
     */
    public function user($id)
    {
        return $this->model->find($id);
    }
}