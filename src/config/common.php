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

/**
 * ThinkPHP 普通模式定义
 */
return [
    // 配置文件
    'config' => [
        CONF_PATH . 'convention.php', // 系统惯例配置
        COMMON_PATH . 'config/config' . CONF_EXT, // 应用公共配置
    ],

    // 应用tags配置
    'app_tags' => COMMON_PATH . 'config/tags.php',

    // 公共函数
    'function' => [
        THINK_PATH . 'helper.php',
        APP_PATH . 'helper.php',
    ],

    // 别名定义
    'alias' => [
        'think\Log' => CORE_PATH . 'Log' . EXT,
        'think\log\driver\File' => CORE_PATH . 'log/driver/file' . EXT,
        'think\Exception' => CORE_PATH . 'Exception' . EXT,
        'think\Model' => CORE_PATH . 'Model' . EXT,
        'think\Db' => CORE_PATH . 'Db' . EXT,
        'think\Cache' => CORE_PATH . 'Cache' . EXT,
        'think\cache\driver\File' => CORE_PATH . 'cache/driver/file' . EXT,
        'think\Storage' => CORE_PATH . 'Storage' . EXT,
    ],

    // 函数和类文件
    'core' => [
        CORE_PATH . 'Hook' . EXT,
        CORE_PATH . 'App' . EXT,
        CORE_PATH . 'Log' . EXT,
        CORE_PATH . 'Route' . EXT,
        CORE_PATH . 'Controller' . EXT,
        CORE_PATH . 'Request' . EXT,
        CORE_PATH . 'Response' . EXT,
    ],

    // 行为扩展定义
    'tags' => [
        'app_init' => [],
        'app_end' => [
            'behavior\WriteLogBehavior'
        ]
    ],
];
