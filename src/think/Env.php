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

class Env
{
    /**
     * 获取环境变量值
     * @param string $name 环境变量名（支持二级 .号分割）
     * @param ?string $default 默认值
     * @return array|mixed|string|null
     */
    public static function get(string $name, $default = null)
    {
        $result = getenv(ENV_PREFIX . strtoupper(str_replace('.', '_', $name)));
        if (false !== $result) {
            return $result;
        } else {
            return $default;
        }
    }
}
