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

/**
 * 日志处理类
 */
class Log
{

    // 日志级别 从上到下，由低到高
    const EMERG = 'EMERG'; // 严重错误: 导致系统崩溃无法使用
    const ALERT = 'ALERT'; // 警戒性错误: 必须被立即修改的错误
    const CRIT = 'CRIT'; // 临界值错误: 超过临界值的错误，例如一天24小时，而输入的是25小时这样
    const ERR = 'ERR'; // 一般错误: 一般性错误
    const WARN = 'WARN'; // 警告性错误: 需要发出警告的错误
    const NOTICE = 'NOTIC'; // 通知: 程序可以运行但是还不够完美的错误
    const INFO = 'INFO'; // 信息: 程序输出信息
    const DEBUG = 'DEBUG'; // 调试: 调试信息
    const SQL = 'SQL'; // SQL：SQL语句 注意只在调试模式开启时有效

    // 日志信息
    protected static $log = [];

    // 日志存储
    protected static $storage = null;

    // 日志初始化
    public static function init($config = [])
    {
        $type = isset($config['type']) ? $config['type'] : 'File';
        $class = strpos($type, '\\') ? $type : 'think\\log\\driver\\' . ucwords(strtolower($type));
        unset($config['type']);
        if (IS_CLI) {
            $config['log_path'] .= 'cli' . DS;
        }
        self::$storage = new $class($config);
    }

    /**
     * 记录日志 并且会过滤未经设置的级别
     * @static
     * @access public
     * @param string $message 日志信息
     * @param string $level 日志级别
     * @param boolean $record 是否强制记录
     * @return void
     */
    public static function record($message, $level = self::ERR, $record = false)
    {
        if ($record || in_array(strtoupper($level), explode(",", C('LOG_LEVEL')))) {
            self::$log[] = "{$level}: {$message}\r\n";

            if(count(self::$log) > 100){
                // 防止日志累计太多，内存溢出
                self::save();
                self::$log = [];
            }
        }
    }

    /**
     * 日志保存
     * @static
     * @access public
     * @param string $type 日志记录方式
     * @param string $destination 写入目标
     * @return void
     */
    public static function save($type = '', $destination = '')
    {
        if (empty(self::$log)) {
            return;
        }

        if (empty($destination)) {
            if (IS_CLI) {
                $destination = LOG_PATH . 'cli' . DS . date('y_m_d') . '.log';
            } else {
                $destination = LOG_PATH . date('y_m_d') . '.log';
            }
        }

        $message = implode('', self::$log);

        $curlLogHandler = \think\log\driver\Curl::channel();
        if (isset($curlLogHandler) && C('DEBUG_LOG_DRIVE') === 'curl') {
            // 走 curl 记录日志
            \think\log\driver\Curl::error($message);
        } else {
            if (!self::$storage) {
                $type = $type ?: C('LOG_TYPE');
                $class = 'think\\log\\driver\\' . ucwords($type);
                self::$storage = new $class();
            }
            self::$storage->write($message, $destination);
        }

        // 保存后清空日志缓存
        self::$log = [];
    }

    /**
     * 获取日志信息
     * @param string $type 信息类型
     * @return array
     */
    public static function getLog($type = '')
    {
        return $type ? self::$log[$type] : self::$log;
    }
}
