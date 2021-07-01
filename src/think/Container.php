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

use Psr\Container\ContainerInterface;
use think\exception\ClassNotFoundException;
use think\exception\ErrorCode;

/**
 * Class Container
 * @package think
 */
class Container implements ContainerInterface
{

    /**
     * @var array
     */
    protected array $_instances = [];

    /**
     * @param string $name
     * @return object
     */
    public function get(string $name = ''): object
    {
        if (!isset($this->_instances[$name])) {
            if (!class_exists($name)) {
                throw new ClassNotFoundException("Class '$name' not found", ErrorCode::CLASS_NOT_FOUND);
            }
            $this->_instances[$name] = new $name();
        }
        return $this->_instances[$name];
    }

    /**
     * @param string $name
     * @return false|object
     */
    public function getObjByName(string $name)
    {
        if (!empty($this->_instances[$name])) {
            return $this->_instances[$name];
        }
        return false;
    }

    /**
     * @param string $name
     * @param object $obj
     * @return mixed
     */
    public function set(string $name, object $obj)
    {
        if (!isset($this->_instances[$name])) {
            $this->_instances[$name] = $obj;
        }
        return $this->_instances[$name];
    }

    /**
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->_instances);
    }

    /**
     * @param $name
     * @param array $constructor
     * @return mixed
     */
    public function make($name, array $constructor = [])
    {
        if (!class_exists($name)) {
            throw new ClassNotFoundException("Class '$name' not found", ErrorCode::CLASS_NOT_FOUND);
        }
        return new $name(... array_values($constructor));
    }

}
