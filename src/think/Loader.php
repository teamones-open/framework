<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2017 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think;

use think\exception\ClassNotFoundException;

class Loader
{
    protected static $_instance = [];

    // 类名映射
    protected static $_map = [];

    // 命名空间别名
    protected static $namespaceAlias = [];

    // PSR-4
    private static $prefixLengthsPsr4 = [];
    private static $prefixDirsPsr4 = [];
    private static $fallbackDirsPsr4 = [];

    // PSR-0
    private static $prefixesPsr0 = [];
    private static $fallbackDirsPsr0 = [];

    /**
     * 需要加载的文件
     * @var array
     */
    private static $files = [];

    private static $configCacheFile = "";
    private static $configCache = [];
    private static $configCacheRefresh = false;

    /**
     * 查找文件
     * @param $class
     * @return bool
     */
    private static function findFile($class)
    {
        if (!empty(self::$_map[$class])) {
            // 类库映射
            return self::$_map[$class];
        }

        // 查找 PSR-4
        $logicalPathPsr4 = strtr($class, '\\', DS) . EXT;

        $first = $class[0];
        if (isset(self::$prefixLengthsPsr4[$first])) {
            foreach (self::$prefixLengthsPsr4[$first] as $prefix => $length) {
                if (0 === strpos($class, $prefix)) {
                    foreach (self::$prefixDirsPsr4[$prefix] as $dir) {
                        if (is_file($file = $dir . DS . substr($logicalPathPsr4, $length))) {
                            return $file;
                        }
                    }
                }
            }
        }

        // 查找 PSR-4 fallback dirs
        foreach (self::$fallbackDirsPsr4 as $dir) {
            if (is_file($file = $dir . DS . $logicalPathPsr4)) {
                return $file;
            }
        }

        // 查找 PSR-0
        if (false !== $pos = strrpos($class, '\\')) {
            // namespaced class name
            $logicalPathPsr0 = substr($logicalPathPsr4, 0, $pos + 1)
                . strtr(substr($logicalPathPsr4, $pos + 1), '_', DS);
        } else {
            // PEAR-like class name
            $logicalPathPsr0 = strtr($class, '_', DS) . EXT;
        }

        if (isset(self::$prefixesPsr0[$first])) {
            foreach (self::$prefixesPsr0[$first] as $prefix => $dirs) {
                if (0 === strpos($class, $prefix)) {
                    foreach ($dirs as $dir) {
                        if (is_file($file = $dir . DS . $logicalPathPsr0)) {
                            return $file;
                        }
                    }
                }
            }
        }

        // 查找 PSR-0 fallback dirs
        foreach (self::$fallbackDirsPsr0 as $dir) {
            if (is_file($file = $dir . DS . $logicalPathPsr0)) {
                return $file;
            }
        }

        return self::$_map[$class] = false;
    }

    /**
     * 注册classmap
     * @param $class
     * @param string $map
     */
    public static function addClassMap($class, $map = '')
    {
        if (is_array($class)) {
            self::$_map = array_merge(self::$_map, $class);
        } else {
            self::$_map[$class] = $map;
        }
    }

    /**
     * 注册命名空间
     * @param $namespace
     * @param string $path
     */
    public static function addNamespace($namespace, $path = '')
    {
        if (is_array($namespace)) {
            foreach ($namespace as $prefix => $paths) {
                self::addPsr4($prefix . '\\', rtrim($paths, DS), true);
            }
        } else {
            self::addPsr4($namespace . '\\', rtrim($path, DS), true);
        }
    }

    /**
     * 添加Ps0空间
     * @param $prefix
     * @param $paths
     * @param bool $prepend
     */
    private static function addPsr0($prefix, $paths, $prepend = false)
    {
        if (!$prefix) {
            if ($prepend) {
                self::$fallbackDirsPsr0 = array_merge(
                    (array)$paths,
                    self::$fallbackDirsPsr0
                );
            } else {
                self::$fallbackDirsPsr0 = array_merge(
                    self::$fallbackDirsPsr0,
                    (array)$paths
                );
            }

            return;
        }

        $first = $prefix[0];
        if (!isset(self::$prefixesPsr0[$first][$prefix])) {
            self::$prefixesPsr0[$first][$prefix] = (array)$paths;

            return;
        }
        if ($prepend) {
            self::$prefixesPsr0[$first][$prefix] = array_merge(
                (array)$paths,
                self::$prefixesPsr0[$first][$prefix]
            );
        } else {
            self::$prefixesPsr0[$first][$prefix] = array_merge(
                self::$prefixesPsr0[$first][$prefix],
                (array)$paths
            );
        }
    }

    /**
     * 添加Psr4空间
     * @param $prefix
     * @param $paths
     * @param bool $prepend
     */
    private static function addPsr4($prefix, $paths, $prepend = false)
    {
        if (!$prefix) {
            // Register directories for the root namespace.
            if ($prepend) {
                self::$fallbackDirsPsr4 = array_merge(
                    (array)$paths,
                    self::$fallbackDirsPsr4
                );
            } else {
                self::$fallbackDirsPsr4 = array_merge(
                    self::$fallbackDirsPsr4,
                    (array)$paths
                );
            }
        } elseif (!isset(self::$prefixDirsPsr4[$prefix])) {
            // Register directories for a new namespace.
            $length = strlen($prefix);
            if ('\\' !== $prefix[$length - 1]) {
                throw new \InvalidArgumentException("A non-empty PSR-4 prefix must end with a namespace separator.");
            }
            self::$prefixLengthsPsr4[$prefix[0]][$prefix] = $length;
            self::$prefixDirsPsr4[$prefix] = (array)$paths;
        } elseif ($prepend) {
            // Prepend directories for an already registered namespace.
            self::$prefixDirsPsr4[$prefix] = array_merge(
                (array)$paths,
                self::$prefixDirsPsr4[$prefix]
            );
        } else {
            // Append directories for an already registered namespace.
            self::$prefixDirsPsr4[$prefix] = array_merge(
                self::$prefixDirsPsr4[$prefix],
                (array)$paths
            );
        }
    }

    /**
     * 读取应用模式文件
     * @throws Exception
     */
    private static function readModeFile()
    {
        // 读取应用模式
        $mode = include_once is_file(CONF_PATH . 'core.php') ? CONF_PATH . 'core.php' : CONF_PATH . APP_MODE . '.php';

        // 读取应用模式缓存配置
        // 加载应用模式配置文件
        foreach ($mode['config'] as $key => $file) {
            is_numeric($key) ? C(load_config($file)) : C($key, load_config($file));
        }

        // 读取当前应用模式对应的配置文件
        if ('common' != APP_MODE && is_file(CONF_PATH . 'config_' . APP_MODE . CONF_EXT)) {
            C(load_config(CONF_PATH . 'config_' . APP_MODE . CONF_EXT));
        }

        // 调试模式加载系统默认的配置文件
        C(include CONF_PATH . 'debug.php');

        // 读取应用调试配置文件
        if (is_file(CONF_PATH . 'debug' . CONF_EXT)) {
            C(include CONF_PATH . 'debug' . CONF_EXT);
        }

        // 读取当前应用状态对应的配置文件
        if (APP_STATUS && is_file(CONF_PATH . APP_STATUS . CONF_EXT)) {
            C(include CONF_PATH . APP_STATUS . CONF_EXT);
        }

        // 加载动态应用公共文件和配置
        load_ext_file(COMMON_PATH);

        self::$configCache['mode'] = C();

        // 加载模式行为定义
        if (isset($mode['tags'])) {
            Hook::import(is_array($mode['tags']) ? $mode['tags'] : include $mode['tags']);
        }

        // 加载应用行为定义
        if (isset($mode['app_tags']) && !empty($mode['app_tags'])) { // 允许应用增加开发模式配置定义
            Hook::import(include $mode['app_tags']);
        }
    }

    /**
     * 加载语言包文件
     */
    private static function loadConfigFile()
    {
        // 加载框架底层语言包
        L(include LIB_PATH . 'lang/' . strtolower(C('DEFAULT_LANG')) . '.php');

        // 设置系统时区
        date_default_timezone_set(C('DEFAULT_TIMEZONE'));
    }

    /**
     * 初始化文件存储方式
     */
    private static function initStorage()
    {
        Storage::connect(STORAGE_TYPE);
    }

    /**
     * 检查应用目录结构 如果不存在则自动创建
     */
    private static function checkAppDir()
    {
        if (C('CHECK_APP_DIR')) {
            if (!is_dir(APP_PATH . BIND_MODULE) || !is_dir(LOG_PATH)) {
                // 检测应用目录结构
                Build::checkDir(BIND_MODULE);
            }
        }
    }

    /**
     * 加载常量
     */
    public static function loadConstant()
    {
        // 记录开始运行时间
        $GLOBALS['_beginTime'] = microtime(true);

        // 记录内存初始使用
        defined('MEMORY_LIMIT_ON') or define('MEMORY_LIMIT_ON', function_exists('memory_get_usage'));
        if (MEMORY_LIMIT_ON) {
            $GLOBALS['_startUseMems'] = memory_get_usage();
        }

        // 系统常量定义
        defined('DS') or define('DS', DIRECTORY_SEPARATOR);   // 分隔符常量
        defined('EXT') or define('EXT', '.php'); // 类文件后缀
        defined('ROOT_PATH') or define('ROOT_PATH', dirname(realpath(APP_PATH)) . DS);
        defined('RUNTIME_PATH') or define('RUNTIME_PATH', ROOT_PATH . 'runtime/');   // 定义缓存目录
        defined('APP_STATUS') or define('APP_STATUS', ''); // 应用状态 加载对应的配置文件
        defined('APP_MODE') or define('APP_MODE', 'common'); // 应用模式 默认为普通模式
        defined('STORAGE_TYPE') or define('STORAGE_TYPE', 'File'); // 存储类型 默认为File
        defined('COMMON_PATH') or define('COMMON_PATH', APP_PATH . 'common' . DS); // 应用公共目录
        defined('EXTEND_PATH') or define('EXTEND_PATH', ROOT_PATH . 'extend' . DS);
        defined('COMMON_VENDOR_PATH') or define('COMMON_VENDOR_PATH', ROOT_PATH . 'vendor' . DS);
        defined('LANG_PATH') or define('LANG_PATH', COMMON_PATH . 'lang' . DS); // 应用语言目录
        defined('HTML_PATH') or define('HTML_PATH', APP_PATH . 'html' . DS); // 应用静态目录
        defined('LOG_PATH') or define('LOG_PATH', RUNTIME_PATH . 'logs' . DS); // 应用日志目录
        defined('TEMP_PATH') or define('TEMP_PATH', RUNTIME_PATH . 'temp' . DS); // 应用缓存目录
        defined('DATA_PATH') or define('DATA_PATH', RUNTIME_PATH . 'data' . DS); // 应用数据目录
        defined('CACHE_PATH') or define('CACHE_PATH', RUNTIME_PATH . 'cache' . DS); // 应用模板缓存目录

        // 环境常量
        defined('THINK_PATH') or define('THINK_PATH', __DIR__ . '/../../');
        defined('LIB_PATH') or define('LIB_PATH', realpath(THINK_PATH . 'src') . DS); // 系统核心类库目录
        defined('CONF_PATH') or define('CONF_PATH', LIB_PATH . 'config' . DS); // 应用配置目录
        defined('CORE_PATH') or define('CORE_PATH', LIB_PATH . 'think' . DS); // Think类库目录
        defined('BEHAVIOR_PATH') or define('BEHAVIOR_PATH', LIB_PATH . 'behavior' . DS); // 行为类库目录
        defined('CONF_EXT') or define('CONF_EXT', '.php'); // 配置文件后缀
        defined('CONF_PARSE') or define('CONF_PARSE', ''); // 配置文件解析方法
        defined('ADDON_PATH') or define('ADDON_PATH', APP_PATH . 'addon');
        defined('ENV_PREFIX') or define('ENV_PREFIX', 'PHP_'); // 环境变量的配置前缀
        defined('MAGIC_QUOTES_GPC') or define('MAGIC_QUOTES_GPC', false);

        define('IS_CGI', (0 === strpos(PHP_SAPI, 'cgi') || false !== strpos(PHP_SAPI, 'fcgi')) ? 1 : 0);
        define('IS_WIN', strstr(PHP_OS, 'WIN') ? 1 : 0);
        define('IS_CLI', PHP_SAPI == 'cli' ? 1 : 0);

        // 模板文件根地址
        if (!IS_CLI) {
            // 当前文件名
            if (!defined('_PHP_FILE_')) {
                if (IS_CGI) {
                    //CGI/FASTCGI模式下
                    $_temp = explode('.php', $_SERVER['PHP_SELF']);
                    define('_PHP_FILE_', rtrim(str_replace($_SERVER['HTTP_HOST'], '', $_temp[0] . '.php'), '/'));
                } else {
                    define('_PHP_FILE_', rtrim($_SERVER['SCRIPT_NAME'], '/'));
                }
            }
            if (!defined('__ROOT__')) {
                $_root = rtrim(dirname(_PHP_FILE_), '/');
                define('__ROOT__', (('/' == $_root || '\\' == $_root) ? '' : $_root));
            }
        } else {
            define('__ROOT__', '');
            define('_PHP_FILE_', '');
        }
    }

    /**
     * 加载ENV环境变量
     */
    public static function loadEnv()
    {
        $env = [];
        if (!empty(self::$configCache['env'])) {
            $env = self::$configCache['env'];
        } else {
            if (is_file(ROOT_PATH . '.env')) {
                $env = parse_ini_file(ROOT_PATH . '.env', true);
                self::$configCache['env'] = $env;
                self::$configCacheRefresh = true;
            }
        }

        // 加载环境变量配置文件
        if (!empty($env)) {
            foreach ($env as $key => $val) {
                $name = ENV_PREFIX . strtoupper($key);
                if (is_array($val)) {
                    foreach ($val as $k => $v) {
                        $item = $name . '_' . strtoupper($k);
                        putenv("$item=$v");
                    }
                } else {
                    putenv("$name=$val");
                }
            }
        }
    }


    /**
     * 注册自动加载机制
     * @param string $autoload
     * @throws Exception
     */
    public static function register($autoload = '')
    {
        // 加载变量
        self::loadConstant();

        // 初始化文件存储方式
        self::initStorage();

        // 读取ENV环境配置
        self::loadEnv();

        // 读取应用模式配置数据
        self::readModeFile();

        // 读取配置文件
        self::loadConfigFile();
    }

    /**
     * 字符串命名风格转换
     * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
     * @param string $name 字符串
     * @param integer $type 转换类型
     * @param bool $ucfirst 首字母是否大写（驼峰规则）
     * @return string
     */
    public static function parseName($name, $type = 0, $ucfirst = true)
    {
        if ($type) {
            $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $name);
            return $ucfirst ? ucfirst($name) : lcfirst($name);
        } else {
            return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
        }
    }

    /**
     * 解析应用类的类名
     * @param string $module 模块名
     * @param string $layer 层名 controller model ...
     * @param string $name 类名
     * @param bool $appendSuffix
     * @return string
     */
    public static function parseClass($module, $layer, $name, $appendSuffix = false)
    {
        $array = explode('\\', str_replace(['/', '.'], '\\', $name));

        $class = self::parseName(array_pop($array), 1);
        $class = $class . (App::$suffix || $appendSuffix ? ucfirst($layer) : '');
        $path = $array ? implode('\\', $array) . '\\' : '';

        return ($module ? $module . '\\' : '') . $layer . '\\' . $path . $class;
    }

    /**
     * 解析模块和类名
     * @access protected
     * @param string $name 资源地址
     * @param string $layer 验证层名称
     * @param bool $appendSuffix 是否添加类名后缀
     * @return array
     */
    protected static function getModuleAndClass($name, $layer, $appendSuffix)
    {
        $request = \request();
        $requestModule = null;
        if (isset($request)) {
            $requestModule = \request()->module();
        }

        if (false !== strpos($name, '\\')) {
            $module = !is_null($requestModule) ? $requestModule : C('DEFAULT_MODULE');
            $class = $name;
        } else {
            if (strpos($name, '/')) {
                list($module, $name) = explode('/', $name, 2);
            } else {
                $module = !is_null($requestModule) ? $requestModule : C('DEFAULT_MODULE');
            }

            $class = self::parseClass($module, $layer, $name, $appendSuffix);
        }

        return [$module, $class];
    }


    /**
     * 实例化（分层）模型
     * @access public
     * @param string $name Model名称
     * @param string $layer 业务层名称
     * @param bool $appendSuffix 是否添加类名后缀
     * @param string $common 公共模块名
     * @return object
     * @throws ClassNotFoundException
     */
    public static function model($name = '', $layer = 'model', $appendSuffix = false, $common = 'common')
    {
        $uid = $name . $layer;

        if (isset(self::$_instance[$uid])) {
            return self::$_instance[$uid];
        }

        list($module, $class) = self::getModuleAndClass($name, $layer, $appendSuffix);

        if (class_exists($class)) {
            $model = new $class();
        } else {
            $class = str_replace('\\' . $module . '\\', '\\' . $common . '\\', $class);

            if (class_exists($class)) {
                $model = new $class();
            } else {
                throw new ClassNotFoundException('class not exists:' . $class, $class);
            }
        }

        return self::$_instance[$uid] = $model;
    }

    /**
     * 实例化（分层）控制器 格式：[模块名/]控制器名
     * @param string $name 资源地址
     * @param string $layer 控制层名称
     * @param bool $appendSuffix 是否添加类名后缀
     * @param string $empty 空控制器名称
     * @return Object|false
     * @throws ClassNotFoundException
     */
    public static function controller($name, $layer = 'controller', $appendSuffix = false, $empty = '')
    {
        list($module, $class) = self::getModuleAndClass($name, $layer, $appendSuffix);

        if (class_exists($class)) {
            $controllerObj = APP::container()->getObjByName($class);
            if ($controllerObj === false) {
                $controllerObj = App::invokeClass($class);
                APP::container()->set($class, $controllerObj);
            }

            return $controllerObj;
        }

        throw new ClassNotFoundException('class not exists:' . $class, $class);
    }


    /**
     * 远程调用模块的操作方法 参数格式 [模块/控制器/]操作
     * @param string $url 调用地址
     * @param string|array $vars 调用参数 支持字符串和数组
     * @param string $layer 要调用的控制层名称
     * @param bool $appendSuffix 是否添加类名后缀
     * @return mixed
     */
    public static function action($url, $vars = [], $layer = 'controller', $appendSuffix = false)
    {
        $info = pathinfo($url);
        $action = $info['basename'];
        $module = '.' != $info['dirname'] ? $info['dirname'] : \request()->controller();
        $class = self::controller($module, $layer, $appendSuffix);
        if ($class) {
            if (is_scalar($vars)) {
                if (strpos($vars, '=')) {
                    parse_str($vars, $vars);
                } else {
                    $vars = [$vars];
                }
            }
            return App::invokeMethod([$class, $action . C('ACTION_SUFFIX')], $vars);
        }

        return false;
    }


    /**
     * 取得对象实例 支持调用类的静态方法
     * @param string $class 对象类名
     * @param string $method 类的静态方法名
     * @return object
     */
    public static function instance($class, $method = '')
    {
        $identify = $class . $method;
        if (!isset(self::$_instance[$identify])) {
            if (class_exists($class)) {
                $o = new $class();
                if (!empty($method) && method_exists($o, $method)) {
                    self::$_instance[$identify] = call_user_func(array(&$o, $method));
                } else {
                    self::$_instance[$identify] = $o;
                }

            } else {
                throw new ClassNotFoundException('class not exists:' . $class, $class);
            }

        }
        return self::$_instance[$identify];
    }

    /**
     * 初始化类的实例
     * @return void
     */
    public static function clearInstance()
    {
        self::$_instance = [];
    }
}
