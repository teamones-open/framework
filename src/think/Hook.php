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

/**
 * ThinkPHP系统钩子实现
 */
class Hook
{

    public static array $tags = [];

    /**
     * 动态添加插件到某个标签
     * @param string $tag 标签名称
     * @param mixed $name 插件名称
     * @return void
     */
    public static function add(string $tag, $name): void
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
    public static function import(array $data, $recursive = true): void
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
    public static function get($tag = ''): array
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
    public static function listen(string $tag, &$params = null): void
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
    }

    /**
     * 执行某个插件
     * @param string $name 插件名称
     * @param string $tag 方法名（标签名）
     * @param Mixed $params 传入的参数
     * @return mixed
     */
    public static function exec(string $name, $tag, &$params = null)
    {
        if ('Behavior' == substr($name, -8)) {
            // 行为扩展必须用run入口方法
            $tag = 'run';
        }
        $addon = new $name();
        return $addon->$tag($params);
    }
}
