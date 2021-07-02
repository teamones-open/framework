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

class ClassNotFoundException extends \RuntimeException
{
    protected string $class;

    /**
     * ClassNotFoundException constructor.
     * @param $message
     * @param string $class
     */
    public function __construct($message, $class = '')
    {
        parent::__construct($message);
        $this->message = $message;
        $this->class = $class;
    }

    /**
     * 获取类名
     * @access public
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }
}
