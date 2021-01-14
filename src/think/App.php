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

use think\Exception\ExceptionHandlerInterface;
use think\Exception\ExceptionHandler;
use think\exception\ClassNotFoundException;
use Workerman\Worker;
use Workerman\Timer;
use Workerman\Connection\TcpConnection;
use think\exception\HttpResponseException;
use think\exception\HttpException;
use Psr\Container\ContainerInterface;
use Monolog\Logger;

class App
{
    /**
     * @var bool 是否初始化过
     */
    protected static $init = false;

    /**
     * @var string 当前模块路径
     */
    public static $modulePath;

    /**
     * @var string 应用类库命名空间
     */
    public static $namespace = 'App';

    /**
     * @var bool 应用类库后缀
     */
    public static $suffix = false;

    /**
     * @var bool 应用路由检测
     */
    protected static $routeCheck;

    /**
     * @var bool 严格路由检测
     */
    protected static $routeMust;

    /**
     * @var bool
     */
    protected static $_supportStaticFiles = true;

    /**
     * @var bool
     */
    protected static $_supportPHPFiles = false;

    /**
     * @var array
     */
    protected static $_callbacks = [];

    /**
     * @var Worker
     */
    protected static $_worker = null;

    /**
     * @var ContainerInterface
     */
    protected static $_container = null;

    /**
     * @var Logger
     */
    protected static $_logger = null;

    /**
     * @var string
     */
    protected static $_publicPath = '';

    /**
     * @var string
     */
    protected static $_configPath = '';

    /**
     * @var TcpConnection
     */
    protected static $_connection = null;

    /**
     * @var Request
     */
    protected static $_request = null;

    /**
     * @var int
     */
    protected static $_maxRequestCount = 1000000;

    /**
     * @var int
     */
    protected static $_gracefulStopTimer = null;


    /**
     * App constructor.
     * @param Worker $worker
     * @param $container
     * @param $logger
     * @param $app_path
     * @param $public_path
     */
    public function __construct(Worker $worker, $container, $logger, $app_path, $public_path)
    {
        static::$_worker = $worker;
        static::$_container = $container;
        static::$_logger = $logger;
        static::$_publicPath = $public_path;
        static::loadController($app_path . C("DEFAULT_MODULE"));

        $max_requst_count = (int)C('SERVER.max_request');
        if ($max_requst_count > 0) {
            static::$_maxRequestCount = $max_requst_count;
        }
        static::$_supportStaticFiles = true;
        static::$_supportPHPFiles = false;
    }


    /**
     * 应用程序初始化
     * @throws Exception
     */
    public static function init()
    {
        // 日志目录转换为绝对路径 默认情况下存储到公共模块下面
        C('LOG_PATH', realpath(LOG_PATH) . '/Common/');

        // 定义当前请求的系统常量
        if (!defined('NOW_TIME')) {
            define('NOW_TIME', $_SERVER['REQUEST_TIME']);
        }

        // 加载动态应用公共文件和配置
        load_ext_file(COMMON_PATH);

        return;
    }


    /**
     * 初始化应用，并返回配置信息
     * @return mixed
     * @throws Exception
     */
    public static function initCommon()
    {
        if (empty(self::$init)) {
            self::init();

            self::$init = true;
        }

        return C();
    }

    /**
     * 执行模块
     * @access public
     * @param array $result 模块/控制器/操作
     * @param array $config 配置参数
     * @param bool $convert 是否自动转换控制器和操作名
     * @return mixed
     * @throws \ReflectionException
     */
    public static function module($result, $config, $convert = null)
    {
        if (is_string($result)) {
            $result = explode('/', $result);
        }

        if ($config['MULTI_MODULE']) {
            // 多模块部署
            $module = strip_tags($result[0] ?: $config['DEFAULT_MODULE']);
            $bind = Route::getBind('module');
            $available = false;

            if ($bind) {
                // 绑定模块
                list($bindModule) = explode('/', $bind);

                if (empty($result[0])) {
                    $module = $bindModule;
                    $available = true;
                } elseif ($module == $bindModule) {
                    $available = true;
                }
            } elseif (!in_array($module, $config['MODULE_DENY_LIST']) && is_dir(APP_PATH . $module)) {
                $available = true;
            }

            // 模块初始化
            if ($module && $available) {

                // 初始化模块
                static::$_request->module($module);
            } else {
                throw new HttpException(404, 'module not exists:' . $module);
            }
        } else {
            // 单一模块部署
            $module = strip_tags($result[0] ?: $config['DEFAULT_MODULE']);
            static::$_request->module($module);
        }

        // 当前模块路径
        App::$modulePath = APP_PATH . ($module ? $module . DS : '');

        // 是否自动转换控制器和操作名
        $convert = is_bool($convert) ? $convert : $config['URL_CONVERT'];

        // 获取控制器名
        $controller = strip_tags($result[1] ?: $config['DEFAULT_CONTROLLER']);

        if (!preg_match('/^[A-Za-z](\w|\.)*$/', $controller)) {
            throw new HttpException(404, 'controller not exists:' . $controller);
        }

        $controller = $convert ? strtolower($controller) : $controller;

        // 获取操作名
        $actionName = strip_tags($result[2] ?: $config['DEFAULT_ACTION']);

        // 获取当前操作名
        $action = $actionName . $config['ACTION_SUFFIX'];

        return [$module, $controller, $action];
    }

    /**
     * URL路由检测（根据PATH_INFO)
     * @param Request $request
     * @param array $config
     * @return array|bool|false
     * @throws \Exception
     */
    public static function routeCheck(Request $request, array $config)
    {
        $path = $request->path();

        $depr = $config['URL_PATHINFO_DEPR'];
        $result = false;

        // 路由检测
        $check = !is_null(self::$routeCheck) ? self::$routeCheck : $config['URL_ROUTE_ON'];
        if ($check) {
            // 路由检测（根据路由定义返回不同的URL调度）
            $result = Route::check($request, $path, $depr, $config['URL_DOMAIN_DEPLOY']);
            $must = !is_null(self::$routeMust) ? self::$routeMust : $config['URL_ROUTE_MUST'];

            if ($must && false === $result) {
                // 路由无效
                StrackE('Invalid route config.', -404);
            }
        }

        // 路由无效 解析模块/控制器/操作/参数... 支持控制器自动搜索
        if (false === $result) {
            $result = Route::parseUrl($path, $depr, $config['CONTROLLER_AUTO_SEARCH']);
        }

        return $result;
    }


    /**
     * 执行应用程序
     * @param $dispatch
     * @param $config
     * @return mixed
     * @throws \ReflectionException
     */
    public static function analysisModule($dispatch, $config)
    {
        if ($dispatch['type'] === 'module') {
            // 模块/控制器/操作
            return self::module(
                $dispatch['module'],
                $config,
                isset($dispatch['convert']) ? $dispatch['convert'] : null
            );
        }

        throw new \InvalidArgumentException('dispatch type not support');
    }


    /**
     * 绑定参数
     * @param $reflect
     * @param array $vars
     * @return array
     * @throws \ReflectionException
     */
    private static function bindParams($reflect, $vars = [])
    {
        if (empty($vars)) {
            // 自动获取请求变量
            if (C('URL_PARAMS_BIND_TYPE')) {
                $vars = \request()->route();
            } else {
                $vars = \request()->param();
            }
        }
        $args = [];
        // 判断数组类型 数字数组时按顺序绑定参数
        reset($vars);
        $type = key($vars) === 0 ? 1 : 0;
        if ($reflect->getNumberOfParameters() > 0) {
            $params = $reflect->getParameters();
            foreach ($params as $param) {
                $name = $param->getName();
                $class = $param->getClass();
                if ($class) {
                    $className = $class->getName();
                    $bind = \request()->$name;
                    if ($bind instanceof $className) {
                        $args[] = $bind;
                    } else {
                        if (method_exists($className, 'invoke')) {
                            $method = new \ReflectionMethod($className, 'invoke');
                            if ($method->isPublic() && $method->isStatic()) {
                                $args[] = $className::invoke(\request());
                                continue;
                            }
                        }
                        $args[] = method_exists($className, 'instance') ? $className::instance() : new $className;
                    }
                } elseif (1 == $type && !empty($vars)) {
                    $args[] = array_shift($vars);
                } elseif (0 == $type && isset($vars[$name])) {
                    $args[] = $vars[$name];
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    throw new \InvalidArgumentException('method param miss:' . $name);
                }
            }
        }
        return $args;
    }


    /**
     * 调用反射执行类的实例化 支持依赖注入
     * @access public
     * @param string $class 类名
     * @param array $vars 变量
     * @return object
     * @throws \ReflectionException
     */
    public static function invokeClass($class, $vars = [])
    {
        $reflect = new \ReflectionClass($class);

        $constructor = $reflect->getConstructor();
        if ($constructor) {
            $args = self::bindParams($constructor, $vars);
        } else {
            $args = [];
        }

        return $reflect->newInstanceArgs($args);
    }


    /**
     * 调用反射执行类的方法 支持参数绑定
     * @access public
     * @param string|array $method 方法
     * @param array $vars 变量
     * @return mixed
     * @throws \ReflectionException
     */
    public static function invokeMethod($method, $vars = [])
    {
        if (is_array($method)) {
            $class = is_object($method[0]) ? $method[0] : self::invokeClass($method[0]);
            $reflect = new \ReflectionMethod($class, $method[1]);
        } else {
            // 静态方法
            $reflect = new \ReflectionMethod($method);
        }
        $args = self::bindParams($reflect, $vars);

        APP_DEBUG && Log::record('[ RUN ] ' . $reflect->class . '->' . $reflect->name . '[ ' . $reflect->getFileName() . ' ]', Log::INFO);
        return $reflect->invokeArgs(isset($class) ? $class : null, $args);
    }

    /**
     * @param TcpConnection $connection
     * @param \think\Request $request
     * @return null
     */
    public function onMessage(TcpConnection $connection, $request)
    {
        static $request_count = 0;

        if (++$request_count > static::$_maxRequestCount) {
            static::tryToGracefulExit();
        }

        // 应用初始化标签
        Hook::listen('app_init');

        // 赋值
        static::$_request = $request;
        static::$_connection = $connection;
        $path = $request->path();
        $key = $request->method() . $path;

        // 请求开始HOOK
        Hook::listen("request", $request);

        // 设置参数过滤规则
        $request->filter(C('DEFAULT_FILTER'));

        // 获取配置
        $config = C();

        // 返回header参数
        $header = [];

        try {

            // 应用开始标签
            Hook::listen('app_begin');

            // 处理跨域
            $checkCorsResult = Cors::check($request);

            // 进行 URL 路由检测
            if ($checkCorsResult instanceof Response) {
                // Options请求直接返回
                $data = $checkCorsResult;
            } else {
                $header = $checkCorsResult;

                if (isset(static::$_callbacks[$key]) && !empty(static::$_callbacks[$key])) {
                    // 直接读取缓存对象
                    list($callback, $request->app, $request->controller, $request->action) = static::$_callbacks[$key];

                    // 执行路由方法
                    $data = $callback($request);
                } else {
                    // 检测路由
                    $dispatch = self::routeCheck($request, $config);

                    // 执行路由方法
                    $data = static::exec($key, $request, $dispatch, $config);
                }

                // 对象超过 1024 回收
                if (\count(static::$_callbacks) > 1024) {
                    static::clearCache();
                }
            }
        } catch (HttpResponseException $exception) {
            $data = $exception->getResponse();
        } catch (\Throwable $e) {
            $data = Response::create(["code" => $e->getCode(), "msg" => $e->getMessage()], "json");;
        }


        // 输出数据到客户端
        if ($data instanceof Response) {
            $response = $data->header($header);
        } elseif (!is_null($data)) {
            // 默认自动识别响应输出类型
            $type = $request->isAjax() ?
                $config['DEFAULT_AJAX_RETURN'] :
                $config['DEFAULT_RETURN_TYPE'];

            $response = Response::create($data, $type)->header($header);
        } else {
            $response = Response::create()->header($header);
        }

        // 应用结束标签
        Hook::listen('app_end');

        static::send($connection, $response->renderWorkermanData(), $request);

        return null;
    }

    protected static function exceptionResponse(\Throwable $e, $request)
    {
        try {
            /** @var ExceptionHandlerInterface $exception_handler */
            $exception_handler = static::$_container->make(ExceptionHandler::class, [
                'logger' => static::$_logger,
                'debug' => APP_DEBUG
            ]);
            $exception_handler->report($e);
            $response = $exception_handler->render($request, $e);
            return $response;
        } catch (\Throwable $e) {
            return APP_DEBUG ? (string)$e : $e->getMessage();
        }
    }

    /**
     * @param $app
     * @param $call
     * @param null $args
     * @param bool $withGlobalMiddleware
     * @param RouteObject $route
     * @return \Closure|mixed
     */
    protected static function getCallback($app, $call, $args = null, $withGlobalMiddleware = true, $route = null)
    {
        $args = $args === null ? null : \array_values($args);
        $middleware = Middleware::getMiddleware($app, $withGlobalMiddleware);
        if ($middleware) {
            $callback = array_reduce($middleware, function ($carry, $pipe) {
                return function ($request) use ($carry, $pipe) {
                    return $pipe($request, $carry);
                };
            }, function ($request) use ($call, $args) {

                try {
                    if ($args === null) {
                        $response = $call($request);
                    } else {
                        $response = $call($request, ...$args);
                    }
                } catch (\Throwable $e) {
                    return static::exceptionResponse($e, $request);
                }
                if (\is_scalar($response) || null === $response) {
                    $response = new Response($response, 200);
                }
                return $response;
            });
        } else {
            if ($args === null) {
                $callback = $call;
            } else {
                $callback = function ($request) use ($call, $args) {
                    return $call($request, ...$args);
                };
            }
        }
        return $callback;
    }

    /**
     * @return ContainerInterface
     */
    public static function container()
    {
        return static::$_container;
    }

    /**
     * @return Request
     */
    public static function request()
    {
        return static::$_request;
    }

    /**
     * @return TcpConnection
     */
    public static function connection()
    {
        return static::$_connection;
    }

    /**
     * @return Worker
     */
    public static function worker()
    {
        return static::$_worker;
    }

    /**
     * @param $connection
     * @param $path
     * @param $key
     * @param Request $request
     * @param $dispatch
     * @param $config
     * @return bool
     * @throws \ReflectionException
     */
    protected static function exec($key, Request $request, $dispatch, $config)
    {
        list($app, $controller, $action) = self::analysisModule($dispatch, $config);

        // 设置当前请求的控制器、操作
        $request->controller(Loader::parseName($controller, 1))->action($action);

        try {
            $controllerObj = Loader::controller(
                $controller,
                $config['URL_CONTROLLER_LAYER'],
                $config['CONTROLLER_SUFFIX'],
                $config['EMPTY_CONTROLLER']
            );
        } catch (ClassNotFoundException $e) {
            throw new HttpException(-404, 'controller not exists:' . $e->getClass());
        }

        if (\is_callable([$controllerObj, $action])) {
            $callback = static::getCallback($app, [$controllerObj, $action]);
        } else {
            throw new HttpException(-404, 'action not exists:' . $action);
        }


        static::$_callbacks[$key] = [$callback, $app, $controller, $action];
        list($callback, $request->app, $request->controller, $request->action) = static::$_callbacks[$key];
        return $callback($request);
    }

    /**
     * @param TcpConnection $connection
     * @param $response
     * @param Request $request
     */
    protected static function send(TcpConnection $connection, $response, Request $request)
    {
        $keep_alive = $request->header('connection');
        if (($keep_alive === null && $request->protocolVersion() === '1.1')
            || $keep_alive === 'keep-alive' || $keep_alive === 'Keep-Alive'
        ) {
            $connection->send($response);
            return;
        }
        $connection->close($response);
    }

    /**
     * @return void
     */
    public static function loadController($path)
    {
        if (\strpos($path, 'phar://') === false) {
            foreach (\glob($path . '/controller/*.php') as $file) {
                require_once $file;
            }
            foreach (\glob($path . '/*/controller/*.php') as $file) {
                require_once $file;
            }
        } else {
            $dir_iterator = new \RecursiveDirectoryIterator($path);
            $iterator = new \RecursiveIteratorIterator($dir_iterator);
            foreach ($iterator as $file) {
                if (is_dir($file)) {
                    continue;
                }
                $fileinfo = new \SplFileInfo($file);
                $ext = $fileinfo->getExtension();
                if (\strpos($file, '/controller/') !== false && $ext === 'php') {
                    require_once $file;
                }
            }
        }
    }

    /**
     * Clear cache.
     */
    public static function clearCache()
    {
        static::$_callbacks = [];
    }

    /**
     * @return void
     */
    protected static function tryToGracefulExit()
    {
        if (static::$_gracefulStopTimer === null) {
            static::$_gracefulStopTimer = Timer::add(rand(1, 10), function () {
                if (\count(static::$_worker->connections) === 0) {
                    Worker::stopAll();
                }
            });
        }
    }
}
