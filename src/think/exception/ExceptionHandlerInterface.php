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

use Throwable;
use think\Request;
use think\Response;

interface ExceptionHandlerInterface
{
    /**
     * @param Throwable $e
     * @return mixed
     */
    public function report(Throwable $e);

    /**
     * @param Request $request
     * @param Throwable $e
     * @return Response
     */
    public function render(Request $request, Throwable $e) : Response;
}
