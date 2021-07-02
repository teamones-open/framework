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

use think\Exception;

/**
 * Database相关异常处理类
 */
class DbException extends Exception
{
    /**
     * DbException constructor.
     * @param string $message
     * @param array $config
     * @param string $sql
     * @param int $code
     */
    public function __construct($message, array $config, $sql, $code = -10500)
    {
        parent::__construct($message, $code);

        $this->message = $message;
        $this->code = $code;

        $this->setData('Database Status', [
            'Error Code' => $code,
            'Error Message' => $message,
            'Error SQL' => $sql,
        ]);

        unset($config['username'], $config['password']);

        $this->setData('Database Config', $config);
    }

}
