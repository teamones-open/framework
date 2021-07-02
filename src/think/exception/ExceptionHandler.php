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

use Psr\Log\LoggerInterface;
use think\Request;
use think\Response;
use Throwable;

/**
 * Class Handler
 * @package support\exception
 */
class ExceptionHandler implements ExceptionHandlerInterface
{
    /**
     * @var ?LoggerInterface
     */
    protected ?LoggerInterface $_logger = null;

    /**
     * @var bool
     */
    protected bool $_debug = false;

    /**
     * @var array
     */
    public array $dontReport = [];

    /**
     * ExceptionHandler constructor.
     * @param $logger
     * @param $debug
     */
    public function __construct($logger, $debug)
    {
        $this->_logger = $logger;
        $this->_debug = (bool)$debug;
    }

    /**
     * @param Throwable $e
     * @return mixed|void
     */
    public function report(Throwable $e)
    {
        if ($this->shouldntReport($e)) {
            return;
        }

        $this->_logger->error($e->getMessage(), ['exception' => (string)$e]);
    }

    /**
     * @param Request $request
     * @param Throwable $exception
     * @return Response
     */
    public function render(Request $request, Throwable $e): Response
    {
        if (\method_exists($e, 'render')) {
            return $e->render();
        }
        $code = $e->getCode();
        if ($request->expectsJson()) {
            $json = ['code' => $code ? $code : 500, 'msg' => $e->getMessage()];
            $this->_debug && $json['traces'] = (string)$e;
        } else {
            $error = $this->_debug ? nl2br((string)$e) : 'Server internal error';
            $json = ['code' => $code ? $code : 500, 'msg' => $error];
        }

        return new Response(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 200, ['Content-Type' => 'application/json']);

    }

    /**
     * @param Throwable $e
     * @return bool
     */
    protected function shouldntReport(Throwable $e): bool
    {
        foreach ($this->dontReport as $type) {
            if ($e instanceof $type) {
                return true;
            }
        }
        return false;
    }
}
