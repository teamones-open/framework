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

namespace think\exception;

class ThrowableError extends \ErrorException
{
    /**
     * ThrowableError constructor.
     * @param \Throwable $e
     */
    public function __construct(\Throwable $e)
    {

        if ($e instanceof \ParseError) {
            $message = 'Parse error: ' . $e->getMessage();
            $severity = E_PARSE;
        } elseif ($e instanceof \TypeError) {
            $message = 'Type error: ' . $e->getMessage();
            $severity = E_RECOVERABLE_ERROR;
        } else {
            $message = 'Fatal error: ' . $e->getMessage();
            $severity = E_ERROR;
        }

        parent::__construct(
            $message,
            $e->getCode(),
            $severity,
            $e->getFile(),
            $e->getLine()
        );

        $this->setTrace($e->getTrace());
    }

    /**
     * @param $trace
     */
    protected function setTrace($trace)
    {
        $traceReflector = new \ReflectionProperty('Exception', 'trace');
        $traceReflector->setAccessible(true);
        $traceReflector->setValue($this, $trace);
    }
}
