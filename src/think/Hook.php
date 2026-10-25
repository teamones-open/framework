<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2013 http://topthink.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
namespace think;

/**
 * ThinkPHP系统钩子实现
 */
class Hook
{

    public static $tags = [];

    /**
     * 动态添加插件到某个标签
     * @param string $tag 标签名称
     * @param mixed $name 插件名称
     * @return void
     */
    public static function add($tag, $name)
    {
        if (!isset(self::$tags[$tag])) {
            self::$tags[$tag] = [];
        }
        if (is_array($name)) {
            self::$tags[$tag] = array_merge(self::$tags[$tag], $name);
        } else {
            self::$tags[$tag][] = $name;
        }
    }

    /**
     * 批量导入插件
     * @param array $data 插件信息
     * @param boolean $recursive 是否递归合并
     * @return void
     */
    public static function import($data, $recursive = true)
    {
        if (!$recursive) {
            // 覆盖导入
            self::$tags = array_merge(self::$tags, $data);
        } else {
            // 合并导入
            foreach ($data as $tag => $val) {
                if (!isset(self::$tags[$tag])) {
                    self::$tags[$tag] = [];
                }

                if (!empty($val['_overlay'])) {
                    // 可以针对某个标签指定覆盖模式
                    unset($val['_overlay']);
                    self::$tags[$tag] = $val;
                } else {
                    // 合并模式
                    self::$tags[$tag] = array_merge(self::$tags[$tag], $val);
                }
            }
        }
    }

    /**
     * 获取插件信息
     * @param string $tag 插件位置 留空获取全部
     * @return array
     */
    public static function get($tag = '')
    {
        if (empty($tag)) {
            // 获取全部的插件信息
            return self::$tags;
        } else {
            return self::$tags[$tag];
        }
    }

    /**
     *
     * 监听标签的插件
     * @param string $tag 标签名称
     * @param null $params 传入参数
     */
    public static function listen($tag, &$params = null)
    {
        if (isset(self::$tags[$tag])) {
            if (APP_DEBUG) {
                G($tag . 'Start');
                trace('[ ' . $tag . ' ] --START--');
            }
            foreach (self::$tags[$tag] as $name) {
                APP_DEBUG && G($name . '_start');
                $result = self::exec($name, $tag, $params);
                if (APP_DEBUG) {
                    G($name . '_end');
                    trace('Run ' . $name . ' [ RunTime:' . G($name . '_start', $name . '_end', 6) . 's ]');
                }
                if (false === $result) {
                    // 如果返回false 则中断插件执行
                    return;
                }
            }
            if (APP_DEBUG) {
                // 记录行为的执行日志
                trace('[ ' . $tag . ' ] --END-- [ RunTime:' . G($tag . 'Start', $tag . 'End', 6) . 's ]');
            }
        }
        return;
    }

    /**
     * 无状态插件实例池，避免同一请求内重复实例化
     * @var array
     */
    protected static $instances = [];

    /**
     * 无状态判定结果缓存
     * @var array
     */
    protected static $statelessMap = [];

    /**
     * 执行某个插件
     * @param string $name 插件名称
     * @param string $tag 方法名（标签名）
     * @param Mixed $params 传入的参数
     * @return mixed
     */
    public static function exec($name, $tag, &$params = null)
    {
        if ('Behavior' == substr($name, -8)) {
            // 行为扩展必须用run入口方法
            $tag = 'run';
        }
        $addon = self::instance($name);
        return $addon->$tag($params);
    }

    /**
     * 获取插件实例
     * 仅对"无状态"插件复用实例；有状态插件保持每次新建，避免跨请求串数据
     * @param string $name 插件类名
     * @return object
     */
    protected static function instance($name)
    {
        if (isset(self::$instances[$name])) {
            return self::$instances[$name];
        }

        // 保持原有语义：类不存在或构造失败时异常照常抛出
        $object = new $name();

        if (self::isStateless($name)) {
            self::$instances[$name] = $object;
        }

        return $object;
    }

    /**
     * 判断插件是否为无状态类
     * 认定条件：无任何非静态属性，且构造函数无必填参数
     * @param string $name 插件类名
     * @return bool
     */
    protected static function isStateless($name)
    {
        if (isset(self::$statelessMap[$name])) {
            return self::$statelessMap[$name];
        }

        try {
            $reflect = new \ReflectionClass($name);

            // 存在非静态属性说明可能携带请求级状态，不可复用
            foreach ($reflect->getProperties() as $property) {
                if (!$property->isStatic()) {
                    return self::$statelessMap[$name] = false;
                }
            }

            // 构造函数需要入参时无法安全复用同一实例
            $constructor = $reflect->getConstructor();
            if ($constructor && $constructor->getNumberOfRequiredParameters() > 0) {
                return self::$statelessMap[$name] = false;
            }

            return self::$statelessMap[$name] = true;
        } catch (\ReflectionException $e) {
            return self::$statelessMap[$name] = false;
        }
    }

    /**
     * 清空实例池与判定缓存
     * 供进程重启、配置热更新或测试隔离时调用
     * @return void
     */
    public static function clearInstances()
    {
        self::$instances = [];
        self::$statelessMap = [];
    }
}
