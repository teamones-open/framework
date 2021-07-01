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

class Middleware
{
    /**
     * @var array
     */
    protected static array $_instances = [];

    /**
     * @param $allMiddlewares
     */
    public static function load($allMiddlewares)
    {
        foreach ($allMiddlewares as $appName => $middlewares) {
            foreach ($middlewares as $className) {
                if (\method_exists($className, 'process')) {
                    static::$_instances[$appName][] = [App::container()->get($className), 'process'];
                } else {
                    // @todo Log
                    echo "middleware $className::process not exsits\n";
                }
            }
        }
    }

    /**
     * @param string $appName
     * @param bool $withGlobalMiddleware
     * @return array
     */
    public static function getMiddleware(string $appName, $withGlobalMiddleware = true): array
    {
        $globalMiddleware = $withGlobalMiddleware && isset(static::$_instances['']) ? static::$_instances[''] : [];
        if ($appName === '') {
            return \array_reverse($globalMiddleware);
        }
        $appMiddleware = static::$_instances[$appName] ?? [];
        return \array_reverse(\array_merge($globalMiddleware, $appMiddleware));
    }

    /**
     * @param string $appName
     * @return array
     */
    public static function getRouteMiddleware(string $appName): array
    {
        if ($appName === '') {
            return [];
        }
        return static::$_instances[$appName] ?? [];
    }

    /**
     * @param string $appName
     * @return bool
     */
    public static function hasMiddleware(string $appName): bool
    {
        return isset(static::$_instances[$appName]);
    }
}
