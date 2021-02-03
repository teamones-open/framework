<?php

namespace think\log\driver;

use Monolog\Logger;

/**
 * Class Redis
 * @package support
 *
 * @method static void log($level, $message, array $context = [])
 * @method static void debug($message, array $context = [])
 * @method static void info($message, array $context = [])
 * @method static void notice($message, array $context = [])
 * @method static void warning($message, array $context = [])
 * @method static void error($message, array $context = [])
 * @method static void critical($message, array $context = [])
 * @method static void alert($message, array $context = [])
 * @method static void emergency($message, array $context = [])
 */
class Curl
{
    /**
     * @var array
     */
    protected static $_instance = [];

    /**
     * @param string $name
     * @return Logger;
     */
    public static function channel($name = 'default')
    {
        $configs = config('monolog', []);
        if (empty($configs)) {
            return null;
        }

        if (empty(static::$_instance[$name])) {
            foreach ($configs as $channel => $config) {
                $logger = static::$_instance[$channel] = new Logger($channel);
                foreach ($config['handlers'] as $handler_config) {
                    $handler = new $handler_config['class'](... \array_values($handler_config['constructor']));
                    $logger->pushHandler($handler);
                }
            }
        };

        return static::$_instance[$name];
    }


    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        return static::channel('default')->{$name}(... $arguments);
    }
}