<?php

namespace think;

class Rpc
{
    /**
     * @var Request
     */
    protected static $_request = null;

    /**
     * @return Request
     */
    public static function request()
    {
        return static::$_request;
    }

    /**
     * 初始化RPC request
     * @return Request
     * @throws \Exception
     */
    public static function instanceRequest()
    {
        if (empty(static::$_request)) {
            // 赋值
            static::$_request = new Request('');
        }
        return static::$_request;
    }
}