<?php
/**
 * 测试引导：加载框架源码与必要常量
 * 不依赖项目 vendor，直接以源码方式加载被测类
 */

namespace FrameworkTests;

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    if ((error_reporting() & $severity) || in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
    return false;
});

// 框架源码根目录
// 优先使用环境变量指定的框架源码路径（便于在容器内用 vendor 中已安装版本验证）
$frameworkRoot = getenv('FRAMEWORK_SRC_ROOT');
if (!$frameworkRoot) {
    $frameworkRoot = dirname(__DIR__);
}
define('FRAMEWORK_ROOT', rtrim($frameworkRoot, '/\\'));

// 定义框架运行期依赖的常量，缺一不可
defined('DS') || define('DS', DIRECTORY_SEPARATOR);
defined('EXT') || define('EXT', '.php');
defined('IS_CLI') || define('IS_CLI', true);
defined('IS_WIN') || define('IS_WIN', false);
defined('APP_DEBUG') || define('APP_DEBUG', false);
defined('MAGIC_QUOTES_GPC') || define('MAGIC_QUOTES_GPC', false);
defined('THINK_VERSION') || define('THINK_VERSION', '3.2');
defined('LOG_PATH') || define('LOG_PATH', sys_get_temp_dir() . DS . 'fwtest_log' . DS);
defined('RUNTIME_PATH') || define('RUNTIME_PATH', sys_get_temp_dir() . DS . 'fwtest_runtime' . DS);
defined('CACHE_PATH') || define('CACHE_PATH', RUNTIME_PATH . 'cache' . DS);
defined('DATA_PATH') || define('DATA_PATH', RUNTIME_PATH . 'data' . DS);
defined('TEMP_PATH') || define('TEMP_PATH', RUNTIME_PATH . 'temp' . DS);
defined('CONF_PATH') || define('CONF_PATH', FRAMEWORK_ROOT . DS . 'src' . DS . 'config' . DS);
defined('MODULE_PATH') || define('MODULE_PATH', sys_get_temp_dir() . DS . 'fwtest_module' . DS);
defined('LIB_PATH') || define('LIB_PATH', FRAMEWORK_ROOT . DS . 'src' . DS);
defined('APP_PATH') || define('APP_PATH', sys_get_temp_dir() . DS . 'fwtest_app' . DS);
defined('BIND_MODULE') || define('BIND_MODULE', '');
defined('NOW_TIME') || define('NOW_TIME', time());
defined('START_TIME') || define('START_TIME', microtime(true));
defined('MEMORY_LIMIT_ON') || define('MEMORY_LIMIT_ON', function_exists('memory_get_usage'));

// 加载框架全局帮助函数（C、L、G、F 等均在此定义，处于全局命名空间）
$helperFile = FRAMEWORK_ROOT . DS . 'helper.php';
if (is_file($helperFile)) {
    require_once $helperFile;
}

// 全局 C() 必须存在，否则 Log、App 等类无法读取配置
if (!\function_exists('C')) {
    fwrite(STDERR, "致命错误：未找到全局 C() 函数，helper.php 加载失败\n");
    exit(2);
}

// 初始化测试用配置
\C([
    'LOG_LEVEL' => 'EMERG,ALERT,CRIT,ERR,WARN,NOTIC,INFO,DEBUG,SQL',
    'LOG_TYPE' => 'File',
]);

// Load declared dependencies, including Workerman's real HTTP request parser.
$dependencyAutoload = getenv('FRAMEWORK_TEST_VENDOR_AUTOLOAD') ?: FRAMEWORK_ROOT . '/vendor/autoload.php';
if (is_file($dependencyAutoload)) {
    require_once $dependencyAutoload;
}

// 框架类自动加载（仅加载 src/think 下被测类）
\spl_autoload_register(function ($class) {
    $prefix = 'think\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = FRAMEWORK_ROOT . DS . 'src' . DS . 'think' . DS . str_replace('\\', DS, $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
}, true, true);

// 加载测试框架
require_once __DIR__ . '/TestRunner.php';
