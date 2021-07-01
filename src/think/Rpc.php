<?php

declare(strict_types=1);

// +----------------------------------------------------------------------
// | The teamones framework runs on the workerman high performance framework
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// | Reviser: weijer <weiwei163@foxmail.com>
// +----------------------------------------------------------------------

namespace think;

class Rpc
{
    /**
     * @var Request|null
     */
    protected static ?Request $_request = null;

    /**
     * @return Request
     */
    public static function request(): Request
    {
        return static::$_request;
    }

    /**
     * 初始化RPC request
     * @return Request
     * @throws \Exception
     */
    public static function instanceRequest(): Request
    {
        if (empty(static::$_request)) {
            // 赋值
            static::$_request = new Request('');
        }
        return static::$_request;
    }
}
