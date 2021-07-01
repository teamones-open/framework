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

namespace think\cache\driver;

use think\cache\Driver;
use think\RedisHandler;

class Redis extends Driver
{
    /**
     * 构造函数
     * @param array $options 缓存参数
     * @access public
     */
    public function __construct($options = [])
    {
        $config = config('redis', $options);

        // 初始化redis配置
        $this->options = [
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => $config['port'] ?? 6739,
            'password' => $config['password'] ?? '',
            'select' => $config['database'] ?? 0,
            'timeout' => $config['timeout'] ?? 300,
            'expire' => $config['expire'] ?? 0,
            'persistent' => $config['persistent'] ?? true,
            'prefix' => $config['prefix'] ?? ''
        ];

        // 初始化对象
        RedisHandler::instance();
    }

    /**
     * 判断缓存
     * @access public
     * @param string $name 缓存变量名
     * @return bool
     */
    public function has(string $name): bool
    {
        return (bool)RedisHandler::get($this->getCacheKey($name));
    }

    /**
     * 读取缓存
     * @access public
     * @param string $name 缓存变量名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $name, $default = false)
    {
        $value = RedisHandler::get($this->getCacheKey($name));

        if (is_null($value)) {
            return $default;
        }

        $jsonData = json_decode($value, true);
        // 检测是否为JSON数据 true 返回JSON解析数组, false返回源数据 byron sampson<xiaobo.sun@qq.com>
        return (null === $jsonData) ? $value : $jsonData;
    }

    /**
     * 写入缓存
     * @access public
     * @param string $name 缓存变量名
     * @param mixed $value 存储数据
     * @param integer $expire 有效时间（秒）
     * @return boolean
     */
    public function set(string $name, $value, $expire = null): bool
    {
        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }
        if ($this->tag && !$this->has($name)) {
            $first = true;
        }
        $key = $this->getCacheKey($name);
        //对数组/对象数据进行缓存处理，保证数据完整性  byron sampson<xiaobo.sun@qq.com>
        $value = (is_object($value) || is_array($value)) ? json_encode($value) : $value;
        if (is_int($expire) && $expire) {
            $result = RedisHandler::setEx($key, $expire, $value);
        } else {
            $result = RedisHandler::set($key, $value);
        }

        isset($first) && $this->setTagItem($key);
        return $result;
    }

    /**
     * 自增缓存（针对数值缓存）
     * @access public
     * @param string $name 缓存变量名
     * @param int $step 步长
     * @return int
     */
    public function inc(string $name, $step = 1): int
    {
        $key = $this->getCacheKey($name);
        return RedisHandler::incrBy($key, $step);
    }

    /**
     * 自减缓存（针对数值缓存）
     * @access public
     * @param string $name 缓存变量名
     * @param int $step 步长
     * @return int
     */
    public function dec(string $name, $step = 1): int
    {
        $key = $this->getCacheKey($name);
        return RedisHandler::decrBy($key, $step);
    }

    /**
     * 删除缓存
     * @access public
     * @param string $name 缓存变量名
     * @return int
     */
    public function rm(string $name): int
    {
        return RedisHandler::del($this->getCacheKey($name));
    }

    /**
     * 清除缓存
     * @access public
     * @param string $tag 标签名
     * @return boolean
     */
    public function clear($tag = null): bool
    {
        if ($tag) {
            // 指定标签清除
            $keys = $this->getTagItem($tag);
            foreach ($keys as $key) {
                RedisHandler::del($key);
            }
            $this->rm('tag_' . md5($tag));
            return true;
        }
        return RedisHandler::flushdb();
    }


    /**
     * 追加（数组）缓存数据
     * @access public
     * @param string $name 缓存标识
     * @param mixed $value 数据
     * @return void
     */
    public function push(string $name, $value): void
    {
        RedisHandler::sAdd($name, $value);
    }
}
