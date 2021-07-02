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

use think\response;

class HttpResponseException extends \RuntimeException
{
    /**
     * @var Response
     */
    protected response $response;

    public function __construct(Response $response)
    {
        parent::__construct();
        $this->response = $response;
    }

    /**
     * @return response
     */
    public function getResponse(): response
    {
        return $this->response;
    }

}
