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

use Psr\Cache\InvalidArgumentException as Psr6CacheInvalidArgumentInterface;
use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentInterface;

/**
 * 非法数据异常
 */
class InvalidArgumentException extends \InvalidArgumentException implements Psr6CacheInvalidArgumentInterface, SimpleCacheInvalidArgumentInterface
{
}
