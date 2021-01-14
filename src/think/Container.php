<?php

namespace think;

use Psr\Container\ContainerInterface;
use think\exception\ClassNotFoundException;

/**
 * Class Container
 * @package think
 */
class Container implements ContainerInterface
{

    /**
     * @var array
     */
    protected $_instances = [];

    /**
     * @param string $name
     * @return mixed
     * @throws NotFoundException
     */
    public function get($name)
    {
        if (!isset($this->_instances[$name])) {
            if (!class_exists($name)) {
                throw new ClassNotFoundException("Class '$name' not found");
            }
            $this->_instances[$name] = new $name();
        }
        return $this->_instances[$name];
    }

    /**
     * @param $name
     * @return mixed
     */
    public function getObjByName($name)
    {
        if (!empty($this->_instances[$name])) {
            return $this->_instances[$name];
        }
        return false;
    }

    /**
     * @param $name
     * @param $obj
     * @return mixed
     */
    public function set($name, $obj)
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
    public function has($name)
    {
        return \array_key_exists($name, $this->_instances);
    }

    /**
     * @param $name
     * @param array $constructor
     * @return mixed
     * @throws NotFoundException
     */
    public function make($name, array $constructor = [])
    {
        if (!class_exists($name)) {
            throw new ClassNotFoundException("Class '$name' not found");
        }
        return new $name(... array_values($constructor));
    }

}