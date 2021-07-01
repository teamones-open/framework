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

use think\console\Output as ConsoleOutput;
use think\exception\ErrorException;
use think\exception\Handle;
use think\exception\ThrowableError;

class Error
{
    /**
     * 注册异常处理
     * @return void
     */
    public static function register()
    {
        error_reporting(E_ALL);
        set_error_handler([__CLASS__, 'appError']);
        set_exception_handler([__CLASS__, 'appException']);
        register_shutdown_function([__CLASS__, 'appShutdown']);
    }

    /**
     * Exeption Handlerc
     * @param $e
     * @throws \ReflectionException
     */
    public static function appException($e)
    {
        if (!$e instanceof \Exception) {
            $e = new ThrowableError($e);
        }
        self::getExceptionHandler()->report($e);
        if (IS_CLI) {
            self::getExceptionHandler()->renderForConsole(new ConsoleOutput, $e);
        } else {
            self::getExceptionHandler()->render($e)->send();
        }
    }

    /**
     * Error Handler
     * @param integer $errno 错误编号
     * @param integer $errstr 详细错误信息
     * @param string $errfile 出错的文件
     * @param integer $errline 出错行号
     * @param array $errcontext
     * @throws ErrorException
     */
    public static function appError(int $errno, int $errstr, $errfile = '', $errline = 0, $errcontext = [])
    {
        $exception = new ErrorException($errno, $errstr, $errfile, $errline, $errcontext);
        if (error_reporting() & $errno) {
            // 将错误信息托管至 think\exception\ErrorException
            throw $exception;
        } else {
            self::getExceptionHandler()->report($exception);
        }
    }

    /**
     * Shutdown Handler
     * @throws \ReflectionException
     */
    public static function appShutdown()
    {
        if (!is_null($error = error_get_last()) && self::isFatal($error['type'])) {
            // 将错误信息托管至think\ErrorException
            $exception = new ErrorException($error['type'], $error['message'], $error['file'], $error['line']);
            self::appException($exception);
        }

        // 写入日志
        Log::save();
    }

    /**
     * 确定错误类型是否致命
     *
     * @param int $type
     * @return bool
     */
    protected static function isFatal(int $type): bool
    {
        return in_array($type, [E_ERROR, E_NOTICE, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE]);
    }

    /**
     * Get an instance of the exception handler.
     *
     * @return Handle
     */
    public static function getExceptionHandler(): Handle
    {
        static $handle;
        if (!$handle) {
            // 异常处理handle
            $class = C('EXCEPTION_HANDLE');
            if ($class && class_exists($class) && is_subclass_of($class, "\\think\\exception\\Handle")) {
                $handle = new $class;
            } else {
                $handle = new Handle;
            }
        }

        return $handle;
    }
}
