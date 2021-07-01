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

use think\storage\driver\File;

// 分布式文件存储类
class Storage
{

    /**
     * 操作句柄
     * @var File | null
     * @access protected
     */
    protected static ?File $handler = null;

    /**
     * 连接分布式文件系统
     */
    public static function connect()
    {
        self::$handler = new File();
    }

    /**
     * @param $method
     * @param $args
     * @return false|mixed
     */
    public static function __callStatic($method, $args)
    {
        //调用缓存驱动的方法
        if (method_exists(self::$handler, $method)) {
            return call_user_func_array([self::$handler, $method], $args);
        }
        return false;
    }
}
