<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
namespace think;

use think\cache\Driver;
use think\exception\ErrorCode;
use think\exception\InvalidArgumentException;

/**
 * 缓存管理类
 */
class Cache
{

    protected static $instance = [];
    public static $readTimes = 0;
    public static $writeTimes = 0;

    /**
     * 操作句柄
     * @var Driver
     * @access protected
     */
    protected static $handler;

    /**
     * 连接缓存
     * @access public
     * @param array $options 配置数组
     * @param bool|string $name 缓存连接标识 true 强制重新连接
     * @return Driver
     */
    public static function connect(array $options = [], $name = false)
    {
        $type = !empty($options['type']) ? $options['type'] : 'File';
        if (false === $name) {
            $name = md5(serialize($options));
        }

        if (true === $name || !isset(self::$instance[$name])) {
            $class = false !== strpos($type, '\\') ? $type : '\\think\\cache\\driver\\' . ucwords($type);

            // 记录初始化信息
            APP_DEBUG && Log::record('[ CACHE ] INIT ' . $type, 'info');

            if (true === $name) {
                return new $class($options);
            } else {
                self::$instance[$name] = new $class($options);
            }
        }
        self::$handler = self::$instance[$name];
        return self::$handler;
    }

    /**
     * 自动初始化缓存
     * @access public
     * @param array $options 配置数组
     * @return void
     */
    public static function init(array $options = [])
    {
        if (is_null(self::$handler)) {
            // 自动初始化缓存
            if (!empty($options)) {
                self::connect($options);
            } elseif ('complex' == C('cache.type')) {
                self::connect(C('cache.default'));
            } else {
                self::connect(C('cache'));
            }
        }
    }

    /**
     * 销毁实例化对象
     */
    public static function destroy(array $options = [])
    {
        self::$handler = null;
        $type = !empty($options['type']) ? $options['type'] : 'File';
        if (strtolower($type) === 'redis') {
            RedisHandler::destroy();
        }
    }

    /**
     * 切换缓存类型 需要配置 cache.type 为 complex
     * @access public
     * @param string $name 缓存标识
     * @return Driver
     */
    public static function store($name = '')
    {
        if ('' !== $name && 'complex' == C('cache.type')) {
            self::connect(C('cache.' . $name), strtolower($name));
        }
        return self::$handler;
    }

    /**
     * 判断缓存是否存在
     * @access public
     * @param string $name 缓存变量名
     * @return bool
     */
    public static function has($name)
    {
        self::init();
        self::$readTimes++;
        return self::$handler->has($name);
    }

    /**
     * 读取缓存
     * @access public
     * @param string $name 缓存标识
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get($name, $default = false)
    {
        self::init();
        self::$readTimes++;
        return self::$handler->get($name, $default);
    }

    /**
     * 写入缓存
     * @access public
     * @param string $name 缓存标识
     * @param mixed $value 存储数据
     * @param int|null $expire 有效时间 0为永久
     * @return boolean
     */
    public static function set($name, $value, $expire = null)
    {
        self::init();
        self::$writeTimes++;
        return self::$handler->set($name, $value, $expire);
    }

    /**
     * 自增缓存（针对数值缓存）
     * @access public
     * @param string $name 缓存变量名
     * @param int $step 步长
     * @return false|int
     */
    public static function inc($name, $step = 1)
    {
        self::init();
        self::$writeTimes++;
        return self::$handler->inc($name, $step);
    }

    /**
     * 自减缓存（针对数值缓存）
     * @access public
     * @param string $name 缓存变量名
     * @param int $step 步长
     * @return false|int
     */
    public static function dec($name, $step = 1)
    {
        self::init();
        self::$writeTimes++;
        return self::$handler->dec($name, $step);
    }

    /**
     * 删除缓存
     * @access public
     * @param string $name 缓存标识
     * @return boolean
     */
    public static function rm($name)
    {
        self::init();
        self::$writeTimes++;
        return self::$handler->rm($name);
    }

    /**
     * 清除缓存
     * @access public
     * @param string $tag 标签名
     * @return boolean
     */
    public static function clear($tag = null)
    {
        self::init();
        self::$writeTimes++;
        return self::$handler->clear($tag);
    }

    /**
     * 读取缓存并删除
     * @access public
     * @param string $name 缓存变量名
     * @return mixed
     */
    public static function pull($name)
    {
        self::init();
        self::$readTimes++;
        self::$writeTimes++;
        return self::$handler->pull($name);
    }

    /**
     * 追加（数组）缓存
     * @access public
     * @param string $name 缓存变量名
     * @param mixed $value 存储数据
     * @return void
     */
    public static function push(string $name, $value): void
    {
        if (!empty($value)) {
            return;
        }
        $item = self::get($name, []);

        if (!is_array($item)) {
            throw new InvalidArgumentException('only array cache can be push', ErrorCode::CACHE_PUSH_FORMAT_ONLY_ARRAY);
        }

        $item[] = $value;

        if (count($item) > 1000) {
            array_shift($item);
        }

        $item = array_unique($item);

        self::set($name, $item);
    }

    /**
     * 如果不存在则写入缓存
     * @access public
     * @param string $name 缓存变量名
     * @param mixed $value 存储数据
     * @param int $expire 有效时间 0为永久
     * @return mixed
     */
    public static function remember($name, $value, $expire = null)
    {
        self::init();
        self::$readTimes++;
        return self::$handler->remember($name, $value, $expire);
    }

    /**
     * 缓存标签
     * @access public
     * @param string $name 标签名
     * @param string|array $keys 缓存标识
     * @param bool $overlay 是否覆盖
     * @return Driver
     */
    public static function tag($name, $keys = null, $overlay = false)
    {
        self::init();
        return self::$handler->tag($name, $keys, $overlay);
    }
}
