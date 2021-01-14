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

/**
 * Think 系统函数库
 */

use think\App;
use think\Loader;
use think\Request;
use think\Response;
use think\Log;
use think\Cache;

if (!function_exists('C')) {
    /**
     * 获取和设置配置参数 支持批量定义
     * @param string|array $name 配置变量
     * @param mixed $value 配置值
     * @param mixed $default 默认值
     * @return mixed
     */
    function C($name = null, $value = null, $default = null)
    {
        static $_config = array();
        // 无参数时获取所有
        if (empty($name)) {
            return $_config;
        }
        // 优先执行设置获取或赋值
        if (is_string($name)) {
            if (!strpos($name, '.')) {
                $name = strtoupper($name);
                if (is_null($value)) {
                    return isset($_config[$name]) ? $_config[$name] : $default;
                }

                $_config[$name] = $value;
                return null;
            }
            // 二维数组设置和获取支持
            $name = explode('.', $name);
            $name[0] = strtoupper($name[0]);
            if (is_null($value)) {
                return isset($_config[$name[0]][$name[1]]) ? $_config[$name[0]][$name[1]] : $default;
            }

            $_config[$name[0]][$name[1]] = $value;
            return null;
        }
        // 批量设置
        if (is_array($name)) {
            $_config = array_merge($_config, array_change_key_case($name, CASE_UPPER));
            return null;
        }
        return null; // 避免非法参数
    }
}

if (!function_exists('config')) {
    /**
     * @param $key
     * @param null $default
     * @return mixed
     */
    function config($key = null, $default = null)
    {
        return C($key, null, $default);
    }
}

if (!function_exists('load_config')) {
    /**
     * 加载配置文件 支持格式转换 仅支持一级配置
     * @param string $file 配置文件名
     * @param string $parse 配置解析方法 有些格式需要用户自己解析
     * @return array|bool|mixed
     * @throws \think\Exception
     */
    function load_config($file, $parse = CONF_PARSE)
    {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        switch ($ext) {
            case 'php':
                return include $file;
            case 'ini':
                return parse_ini_file($file);
            case 'yaml':
                return yaml_parse_file($file);
            case 'xml':
                return (array)simplexml_load_file($file);
            case 'json':
                return json_decode(file_get_contents($file), true);
            default:
                if (function_exists($parse)) {
                    return $parse($file);
                } else {
                    E(L('_NOT_SUPPORT_') . ':' . $ext);
                }
        }
    }
}


if (!function_exists('json')) {
    /**
     * 获取\think\response\Json对象实例
     * @param mixed $data 返回的数据
     * @param integer $code 状态码
     * @param array $header 头部
     * @param array $options 参数
     * @return Response
     */
    function json($data = [], $code = 200, $header = [], $options = [])
    {
        return Response::create($data, 'json', $code, $header, $options);
    }
}


if (!function_exists('download')) {
    /**
     * 获取\think\response\Download对象实例
     * @param string $filename 要下载的文件
     * @param string $name 显示文件名
     * @param bool $content 是否为内容
     * @param integer $expire 有效期（秒）
     * @return \think\response\Download
     */
    function download($filename, $name = '', $content = false, $expire = 180)
    {
        return Response::create($filename, 'download')->name($name)->isContent($content)->expire($expire);
    }
}

if (!function_exists('success_response')) {
    /**
     * 成功返回数据
     * @param string $message
     * @param array $data
     * @param int $status
     * @return array
     */
    function success_response($message = '', $data = [], $status = 0)
    {
        return ["code" => $status, "msg" => $message, "data" => $data];
    }
}

if (!function_exists('StrackE')) {
    /**
     * 抛出异常处理
     * @param $msg
     * @param int $code
     * @throws Exception
     */
    function StrackE($msg, $code = -400000)
    {
        throw new \Exception($msg, $code);
        //throw_strack_exception($msg, $code);
    }
}

if (!function_exists('throw_strack_exception')) {
    /**
     * 抛出Strack异常
     * @param $msg
     * @param int $code
     * @param array $data
     * @param string $type
     */
    function throw_strack_exception($msg, $code = -400000, $data = [], $type = 'json')
    {
        $errorData = [
            "code" => $code,
            "msg" => $msg,
            "data" => $data
        ];
        $response = Response::create($errorData, "json");
        throw new think\exception\HttpResponseException($response);
    }
}

if (!function_exists('G')) {
    /**
     * 记录和统计时间（微秒）和内存使用情况
     * 使用方法:
     * <code>
     * G('begin'); // 记录开始标记位
     * // ... 区间运行代码
     * G('end'); // 记录结束标签位
     * echo G('begin','end',6); // 统计区间运行时间 精确到小数后6位
     * echo G('begin','end','m'); // 统计区间内存使用情况
     * 如果end标记位没有定义，则会自动以当前作为标记位
     * 其中统计内存使用需要 MEMORY_LIMIT_ON 常量为true才有效
     * </code>
     * @param string $start 开始标签
     * @param string $end 结束标签
     * @param integer|string $dec 小数位或者m
     * @return mixed
     */
    function G($start, $end = '', $dec = 4)
    {
        static $_info = array();
        static $_mem = array();
        if (is_float($end)) {
            // 记录时间
            $_info[$start] = $end;
        } elseif (!empty($end)) {
            // 统计时间和内存使用
            if (!isset($_info[$end])) {
                $_info[$end] = microtime(true);
            }

            if (MEMORY_LIMIT_ON && 'm' == $dec) {
                if (!isset($_mem[$end])) {
                    $_mem[$end] = memory_get_usage();
                }

                return number_format(($_mem[$end] - $_mem[$start]) / 1024);
            } else {
                return number_format(($_info[$end] - $_info[$start]), $dec);
            }

        } else {
            // 记录时间和内存使用
            $_info[$start] = microtime(true);
            if (MEMORY_LIMIT_ON) {
                $_mem[$start] = memory_get_usage();
            }

        }
        return null;
    }
}

if (!function_exists('L')) {
    /**
     * 获取和设置语言定义(不区分大小写)
     * @param string|array $name 语言变量
     * @param mixed $value 语言值或者变量
     * @return mixed
     */
    function L($name = null, $value = null)
    {
        static $_lang = array();
        // 空参数返回所有定义
        if (empty($name)) {
            return $_lang;
        }

        // 判断语言获取(或设置)
        // 若不存在,直接返回全大写$name
        if (is_string($name)) {
            $name = strtoupper($name);
            if (is_null($value)) {
                return isset($_lang[$name]) ? $_lang[$name] : $name;
            } elseif (is_array($value)) {
                // 支持变量
                $replace = array_keys($value);
                foreach ($replace as &$v) {
                    $v = '{$' . $v . '}';
                }
                return str_replace($replace, $value, isset($_lang[$name]) ? $_lang[$name] : null);
            }
            $_lang[$name] = $value; // 语言定义
            return null;
        }
        // 批量定义
        if (is_array($name)) {
            $_lang = array_merge($_lang, array_change_key_case($name, CASE_UPPER));
        }

        return null;
    }
}

if (!function_exists('trace')) {
    /**
     * 添加和获取页面Trace记录
     * @param string $log
     * @param string $level
     * @return array
     */
    function trace($log = '[think]', $level = think\Log::INFO)
    {
        if ('[think]' === $log) {
            return Log::getLog();
        } else {
            Log::record($log, $level);
        }
    }
}

if (!function_exists('compile')) {
    /**
     * 编译文件
     * @param string $filename 文件名
     * @return string
     */
    function compile($filename)
    {
        $content = php_strip_whitespace($filename);
        $content = trim(substr($content, 5));
        // 替换预编译指令
        $content = preg_replace('/\/\/\[RUNTIME\](.*?)\/\/\[\/RUNTIME\]/s', '', $content);
        if (0 === strpos($content, 'namespace')) {
            $content = preg_replace('/namespace\s(.*?);/', 'namespace \\1{', $content, 1);
        } else {
            $content = 'namespace {' . $content;
        }
        if ('?>' == substr($content, -2)) {
            $content = substr($content, 0, -2);
        }

        return $content . '}';
    }
}

if (!function_exists('array_map_recursive')) {
    function array_map_recursive($filter, $data)
    {
        $result = array();
        foreach ($data as $key => $val) {
            $result[$key] = is_array($val)
                ? array_map_recursive($filter, $val)
                : call_user_func($filter, $val);
        }
        return $result;
    }
}

if (!function_exists('N')) {
    /**
     * 设置和获取统计数据
     * 使用方法:
     * <code>
     * N('db',1); // 记录数据库操作次数
     * N('read',1); // 记录读取次数
     * echo N('db'); // 获取当前页面数据库的所有操作次数
     * echo N('read'); // 获取当前页面读取次数
     * </code>
     * @param string $key 标识位置
     * @param integer $step 步进值
     * @param boolean $save 是否保存结果
     * @return mixed
     */
    function N($key, $step = 0, $save = false)
    {
        static $_num = array();
        if (!isset($_num[$key])) {
            $_num[$key] = (false !== $save) ? S('N_' . $key) : 0;
        }
        if (empty($step)) {
            return $_num[$key];
        } else {
            $_num[$key] = $_num[$key] + (int)$step;
        }
        if (false !== $save) {
            // 保存结果
            S('N_' . $key, $_num[$key], $save);
        }
        return null;
    }
}

if (!function_exists('parse_name')) {
    /**
     * 字符串命名风格转换
     * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
     * @param string $name 字符串
     * @param integer $type 转换类型
     * @return string
     */
    function parse_name($name, $type = 0)
    {
        if ($type) {
            return ucfirst(preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $name));
        } else {
            return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
        }
    }
}

if (!function_exists('require_cache')) {
    /**
     * 优化的require_once
     * @param string $filename 文件地址
     * @return boolean
     */
    function require_cache($filename)
    {
        static $_importFiles = array();
        if (!isset($_importFiles[$filename])) {
            if (file_exists_case($filename)) {
                require $filename;
                $_importFiles[$filename] = true;
            } else {
                $_importFiles[$filename] = false;
            }
        }
        return $_importFiles[$filename];
    }
}

if (!function_exists('file_exists_case')) {
    /**
     * 区分大小写的文件存在判断
     * @param string $filename 文件地址
     * @return boolean
     */
    function file_exists_case($filename)
    {
        if (is_file($filename)) {
            if (IS_WIN && APP_DEBUG) {
                if (basename(realpath($filename)) != basename($filename)) {
                    return false;
                }

            }
            return true;
        }
        return false;
    }
}

if (!function_exists('model')) {

    /**
     * 实例化Model
     * @param string $name Model名称
     * @param string $layer 业务层名称
     * @param bool $appendSuffix 是否添加类名后缀
     * @return \think\Model
     */
    function model($name = '', $layer = 'model', $appendSuffix = false)
    {
        return Loader::model($name, $layer, $appendSuffix);
    }
}

if (!function_exists('M')) {
    /**
     * 实例化一个没有模型文件的Model
     * @param string $name Model名称 支持指定基础模型 例如 MongoModel:User
     * @param string $tablePrefix 表前缀
     * @param mixed $connection 数据库连接信息
     * @return think\Model
     */
    function M($name = '', $tablePrefix = '', $connection = '')
    {
        static $_model = array();
        if (strpos($name, ':')) {
            list($class, $name) = explode(':', $name);
        } else {
            $class = 'think\\Model';
        }
        $guid = (is_array($connection) ? implode('', $connection) : $connection) . $tablePrefix . $name . '_' . $class;
        if (!isset($_model[$guid])) {
            $_model[$guid] = new $class($name, $tablePrefix, $connection);
        }

        return $_model[$guid];
    }
}

if (!function_exists('controller')) {
    /**
     * 实例化控制器 格式：[模块/]控制器
     * @param string $name 资源地址
     * @param string $layer 控制层名称
     * @param bool $appendSuffix 是否添加类名后缀
     * @return \think\Controller
     */
    function controller($name, $layer = 'controller', $appendSuffix = false)
    {
        return Loader::controller($name, $layer, $appendSuffix);
    }
}

if (!function_exists('action')) {
    /**
     * 调用模块的操作方法 参数格式 [模块/控制器/]操作
     * @param string $url 调用地址
     * @param string|array $vars 调用参数 支持字符串和数组
     * @param string $layer 要调用的控制层名称
     * @param bool $appendSuffix 是否添加类名后缀
     * @return mixed
     */
    function action($url, $vars = [], $layer = 'controller', $appendSuffix = false)
    {
        return Loader::action($url, $vars, $layer, $appendSuffix);
    }
}

if (!function_exists('R')) {
    /**
     * 远程调用控制器的操作方法 URL 参数格式 [资源://][模块/]控制器/操作
     * @param string $url 调用地址
     * @param string|array $vars 调用参数 支持字符串和数组
     * @param string $layer 要调用的控制层名称
     * @return mixed
     */
    function R($url, $vars = array(), $layer = '')
    {
        $info = pathinfo($url);
        $action = $info['basename'];
        $module = $info['dirname'];
        $class = A($module, $layer);
        if ($class) {
            if (is_string($vars)) {
                parse_str($vars, $vars);
            }
            return call_user_func_array(array(&$class, $action . C('ACTION_SUFFIX')), $vars);
        } else {
            return false;
        }
    }
}

if (!function_exists('tag')) {
    /**
     * 处理标签扩展
     * @param string $tag 标签名称
     * @param mixed $params 传入参数
     * @return void
     */
    function tag($tag, &$params = null)
    {
        \think\Hook::listen($tag, $params);
    }
}

if (!function_exists('B')) {
    /**
     * 执行某个行为
     * @param string $name 行为名称
     * @param string $tag 标签名称（行为类无需传入）
     * @param Mixed $params 传入的参数
     * @return void
     */
    function B($name, $tag = '', &$params = null)
    {
        if ('' == $tag) {
            $name .= 'Behavior';
        }
        return \think\Hook::exec($name, $tag, $params);
    }
}

if (!function_exists('strip_whitespace')) {
    /**
     * 去除代码中的空白和注释
     * @param string $content 代码内容
     * @return string
     */
    function strip_whitespace($content)
    {
        $stripStr = '';
        //分析php源码
        $tokens = token_get_all($content);
        $last_space = false;
        for ($i = 0, $j = count($tokens); $i < $j; $i++) {
            if (is_string($tokens[$i])) {
                $last_space = false;
                $stripStr .= $tokens[$i];
            } else {
                switch ($tokens[$i][0]) {
                    //过滤各种PHP注释
                    case T_COMMENT:
                    case T_DOC_COMMENT:
                        break;
                    //过滤空格
                    case T_WHITESPACE:
                        if (!$last_space) {
                            $stripStr .= ' ';
                            $last_space = true;
                        }
                        break;
                    case T_START_HEREDOC:
                        $stripStr .= "<<<THINK\n";
                        break;
                    case T_END_HEREDOC:
                        $stripStr .= "THINK;\n";
                        for ($k = $i + 1; $k < $j; $k++) {
                            if (is_string($tokens[$k]) && ';' == $tokens[$k]) {
                                $i = $k;
                                break;
                            } else if (T_CLOSE_TAG == $tokens[$k][0]) {
                                break;
                            }
                        }
                        break;
                    default:
                        $last_space = false;
                        $stripStr .= $tokens[$i][1];
                }
            }
        }
        return $stripStr;
    }
}

if (!function_exists('dump')) {
    /**
     * 浏览器友好的变量输出
     * @param mixed $var 变量
     * @param boolean $echo 是否输出 默认为True 如果为false 则返回输出字符串
     * @param string $label 标签 默认为空
     * @param boolean $strict 是否严谨 默认为true
     * @return void|string
     */
    function dump($var, $echo = true, $label = null, $strict = true)
    {
        $label = (null === $label) ? '' : rtrim($label) . ' ';
        if (!$strict) {
            if (ini_get('html_errors')) {
                $output = print_r($var, true);
                $output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
            } else {
                $output = $label . print_r($var, true);
            }
        } else {
            ob_start();
            var_dump($var);
            $output = ob_get_clean();
            if (!extension_loaded('xdebug')) {
                $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);
                $output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
            }
        }
        if ($echo) {
            echo($output);
            return null;
        } else {
            return $output;
        }
    }
}

if (!function_exists('layout')) {
    /**
     * 设置当前页面的布局
     * @param string|false $layout 布局名称 为false的时候表示关闭布局
     * @return void
     */
    function layout($layout)
    {
        if (false !== $layout) {
            // 开启布局
            C('LAYOUT_ON', true);
            if (is_string($layout)) {
                // 设置新的布局模板
                C('LAYOUT_NAME', $layout);
            }
        } else {
            // 临时关闭布局
            C('LAYOUT_ON', false);
        }
    }
}

if (!function_exists('U')) {
    /**
     * URL组装 支持不同URL模式
     * @param string $url URL表达式，格式：'[模块/控制器/操作#锚点@域名]?参数1=值1&参数2=值2...'
     * @param string|array $vars 传入的参数，支持数组和字符串
     * @param string|boolean $suffix 伪静态后缀，默认为true表示获取配置值
     * @param boolean $domain 是否显示域名
     * @return string
     */
    function U(Request $request, $url = '', $vars = '', $suffix = true, $domain = false)
    {
        // 解析URL
        $info = parse_url($url);
        $url = !empty($info['path']) ? $info['path'] : $request->action();
        if (isset($info['fragment'])) {
            // 解析锚点
            $anchor = $info['fragment'];
            if (false !== strpos($anchor, '?')) {
                // 解析参数
                list($anchor, $info['query']) = explode('?', $anchor, 2);
            }
            if (false !== strpos($anchor, '@')) {
                // 解析域名
                list($anchor, $host) = explode('@', $anchor, 2);
            }
        } elseif (false !== strpos($url, '@')) {
            // 解析域名
            list($url, $host) = explode('@', $info['path'], 2);
        }
        // 解析子域名
        if (isset($host)) {
            $domain = $host . (strpos($host, '.') ? '' : strstr($_SERVER['HTTP_HOST'], '.'));
        } elseif (true === $domain) {
            $domain = $_SERVER['HTTP_HOST'];
            if (C('APP_SUB_DOMAIN_DEPLOY')) {
                // 开启子域名部署
                $domain = 'localhost' == $domain ? 'localhost' : 'www' . strstr($_SERVER['HTTP_HOST'], '.');
                // '子域名'=>array('模块[/控制器]');
                foreach (C('APP_SUB_DOMAIN_RULES') as $key => $rule) {
                    $rule = is_array($rule) ? $rule[0] : $rule;
                    if (false === strpos($key, '*') && 0 === strpos($url, $rule)) {
                        $domain = $key . strstr($domain, '.'); // 生成对应子域名
                        $url = substr_replace($url, '', 0, strlen($rule));
                        break;
                    }
                }
            }
        }

        // 解析参数
        if (is_string($vars)) {
            // aaa=1&bbb=2 转换成数组
            parse_str($vars, $vars);
        } elseif (!is_array($vars)) {
            $vars = array();
        }
        if (isset($info['query'])) {
            // 解析地址里面参数 合并到vars
            parse_str($info['query'], $params);
            $vars = array_merge($params, $vars);
        }

        // URL组装
        $depr = C('URL_PATHINFO_DEPR');
        $urlCase = C('URL_CASE_INSENSITIVE');
        if ($url) {
            if (0 === strpos($url, '/')) {
                // 定义路由
                $route = true;
                $url = substr($url, 1);
                if ('/' != $depr) {
                    $url = str_replace('/', $depr, $url);
                }
            } else {
                if ('/' != $depr) {
                    // 安全替换
                    $url = str_replace('/', $depr, $url);
                }
                // 解析模块、控制器和操作
                $url = trim($url, $depr);
                $path = explode($depr, $url);
                $var = array();
                $varModule = C('VAR_MODULE');
                $varController = C('VAR_CONTROLLER');
                $varAction = C('VAR_ACTION');
                $var[$varAction] = !empty($path) ? array_pop($path) : $request->action();
                $var[$varController] = !empty($path) ? array_pop($path) : $request->controller();
                if ($maps = C('URL_ACTION_MAP')) {
                    if (isset($maps[strtolower($var[$varController])])) {
                        $maps = $maps[strtolower($var[$varController])];
                        if ($action = array_search(strtolower($var[$varAction]), $maps)) {
                            $var[$varAction] = $action;
                        }
                    }
                }
                if ($maps = C('URL_CONTROLLER_MAP')) {
                    if ($controller = array_search(strtolower($var[$varController]), $maps)) {
                        $var[$varController] = $controller;
                    }
                }
                if ($urlCase) {
                    $var[$varController] = parse_name($var[$varController]);
                }
                $module = '';

                if (!empty($path)) {
                    $var[$varModule] = implode($depr, $path);
                } else {
                    if (C('MULTI_MODULE')) {
                        if ($request->module() != C('DEFAULT_MODULE') || !C('MODULE_ALLOW_LIST')) {
                            $var[$varModule] = $request->module();
                        }
                    }
                }
                if ($maps = C('URL_MODULE_MAP')) {
                    if ($_module = array_search(strtolower($var[$varModule]), $maps)) {
                        $var[$varModule] = $_module;
                    }
                }
                if (isset($var[$varModule])) {
                    $module = $var[$varModule];
                    unset($var[$varModule]);
                }

            }
        }

        if (C('URL_MODEL') == 0) {
            // 普通模式URL转换
            $url = __APP__ . '?' . C('VAR_MODULE') . "={$module}&" . http_build_query(array_reverse($var));
            if ($urlCase) {
                $url = strtolower($url);
            }
            if (!empty($vars)) {
                $vars = http_build_query($vars);
                $url .= '&' . $vars;
            }
        } else {
            // PATHINFO模式或者兼容URL模式
            if (isset($route)) {
                $url = __APP__ . '/' . rtrim($url, $depr);
            } else {
                $module = (defined('BIND_MODULE') && BIND_MODULE == $module) ? '' : $module;
                $url = __APP__ . '/' . ($module ? $module . C() : 'URL_PATHINFO_DEPR') . implode($depr, array_reverse($var));
            }
            if ($urlCase) {
                $url = strtolower($url);
            }
            if (!empty($vars)) {
                // 添加参数
                foreach ($vars as $var => $val) {
                    if ('' !== trim($val)) {
                        $url .= $depr . $var . $depr . urlencode($val);
                    }

                }
            }
            if ($suffix) {
                $suffix = true === $suffix ? C('URL_HTML_SUFFIX') : $suffix;
                if ($pos = strpos($suffix, '|')) {
                    $suffix = substr($suffix, 0, $pos);
                }
                if ($suffix && '/' != substr($url, -1)) {
                    $url .= '.' . ltrim($suffix, '.');
                }
            }
        }
        if (isset($anchor)) {
            $url .= '#' . $anchor;
        }
        if ($domain) {
            $url = (is_ssl() ? 'https://' : 'http://') . $domain . $url;
        }
        return $url;
    }
}

if (!function_exists('W')) {
    /**
     * 渲染输出Widget
     * @param string $name Widget名称
     * @param array $data 传入的参数
     * @return void
     */
    function W($name, $data = array())
    {
        return R($name, $data, 'Widget');
    }
}

if (!function_exists('is_ssl')) {
    /**
     * 判断是否SSL协议
     * @return boolean
     */
    function is_ssl()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return true;
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        } elseif (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
            return true;
        }
    }
}

if (!function_exists('redirect')) {
    /**
     * URL重定向
     * @param string $url 重定向的URL地址
     * @param integer $time 重定向的等待时间（秒）
     * @param string $msg 重定向前的提示信息
     * @return void
     */
    function redirect($url, $time = 0, $msg = '', $params = [], $code = 302)
    {
        $url = str_replace(array("\n", "\r"), '', $url);

        if (is_integer($params)) {
            $code = $params;
            $params = [];
        }

        if ($time > 0) {
            header("refresh:{$time};url={$url}");
        }

        return Response::create($url, 'redirect', $code)
            ->header("refresh", $time)
            ->params($params);
    }
}

if (!function_exists('S')) {
    /**
     * 缓存管理
     * @param mixed $name 缓存名称，如果为数组表示进行缓存设置
     * @param mixed $value 缓存值
     * @param mixed $options 缓存参数
     * @param string $tag 缓存标签
     * @return mixed
     */
    function S($name, $value = '', $options = null, $tag = null)
    {
        if (is_array($options)) {
            // 缓存操作的同时初始化
            Cache::connect($options);
        } elseif (is_array($name)) {
            // 缓存初始化
            return Cache::connect($name);
        }
        if (is_null($name)) {
            return Cache::clear($value);
        } elseif ('' === $value) {
            // 获取缓存
            return 0 === strpos($name, '?') ? Cache::has(substr($name, 1)) : Cache::get($name);
        } elseif (is_null($value)) {
            // 删除缓存
            return Cache::rm($name);
        } elseif (0 === strpos($name, '?') && '' !== $value) {
            $expire = is_numeric($options) ? $options : null;
            return Cache::remember(substr($name, 1), $value, $expire);
        } else {
            // 缓存数据
            if (is_array($options)) {
                $expire = isset($options['expire']) ? $options['expire'] : null; //修复查询缓存无法设置过期时间
            } else {
                $expire = is_numeric($options) ? $options : null; //默认快捷缓存设置过期时间
            }
            if (is_null($tag)) {
                return Cache::set($name, $value, $expire);
            } else {
                return Cache::tag($tag)->set($name, $value, $expire);
            }
        }
    }
}

if (!function_exists('F')) {
    /**
     * 快速文件数据读取和保存 针对简单类型数据 字符串、数组
     * @param string $name 缓存名称
     * @param mixed $value 缓存值
     * @param string $path 缓存路径
     * @return mixed
     */
    function F($name, $value = '', $path = DATA_PATH)
    {
        static $_cache = array();
        $filename = $path . $name . '.php';
        if ('' !== $value) {
            if (is_null($value)) {
                // 删除缓存
                if (false !== strpos($name, '*')) {
                    return false; // TODO
                } else {
                    unset($_cache[$name]);
                    return think\Storage::unlink($filename, 'F');
                }
            } else {
                think\Storage::put($filename, serialize($value), 'F');
                // 缓存数据
                $_cache[$name] = $value;
                return null;
            }
        }
        // 获取缓存数据
        if (isset($_cache[$name])) {
            return $_cache[$name];
        }

        if (think\Storage::has($filename, 'F')) {
            $value = unserialize(think\Storage::read($filename, 'F'));
            $_cache[$name] = $value;
        } else {
            $value = false;
        }
        return $value;
    }
}

if (!function_exists('to_guid_string')) {
    /**
     * 根据PHP各种类型变量生成唯一标识号
     * @param mixed $mix 变量
     * @return string
     */
    function to_guid_string($mix)
    {
        if (is_object($mix)) {
            return spl_object_hash($mix);
        } elseif (is_resource($mix)) {
            $mix = get_resource_type($mix) . strval($mix);
        } else {
            $mix = serialize($mix);
        }
        return md5($mix);
    }
}

if (!function_exists('xml_encode')) {
    /**
     * XML编码
     * @param mixed $data 数据
     * @param string $root 根节点名
     * @param string $item 数字索引的子节点名
     * @param string $attr 根节点属性
     * @param string $id 数字索引子节点key转换的属性名
     * @param string $encoding 数据编码
     * @return string
     */
    function xml_encode($data, $root = 'think', $item = 'item', $attr = '', $id = 'id', $encoding = 'utf-8')
    {
        if (is_array($attr)) {
            $_attr = array();
            foreach ($attr as $key => $value) {
                $_attr[] = "{$key}=\"{$value}\"";
            }
            $attr = implode(' ', $_attr);
        }
        $attr = trim($attr);
        $attr = empty($attr) ? '' : " {$attr}";
        $xml = "<?xml version=\"1.0\" encoding=\"{$encoding}\"?>";
        $xml .= "<{$root}{$attr}>";
        $xml .= data_to_xml($data, $item, $id);
        $xml .= "</{$root}>";
        return $xml;
    }
}

if (!function_exists('data_to_xml')) {
    /**
     * 数据XML编码
     * @param mixed $data 数据
     * @param string $item 数字索引时的节点名称
     * @param string $id 数字索引key转换为的属性名
     * @return string
     */
    function data_to_xml($data, $item = 'item', $id = 'id')
    {
        $xml = $attr = '';
        foreach ($data as $key => $val) {
            if (is_numeric($key)) {
                $id && $attr = " {$id}=\"{$key}\"";
                $key = $item;
            }
            $xml .= "<{$key}{$attr}>";
            $xml .= (is_array($val) || is_object($val)) ? data_to_xml($val, $item, $id) : $val;
            $xml .= "</{$key}>";
        }
        return $xml;
    }
}

if (!function_exists('load_ext_file')) {
    /**
     * 加载动态扩展文件
     * @throws \think\Exception
     * @var string $path 文件路径
     */
    function load_ext_file($path)
    {
        // 加载自定义外部文件
        if ($files = C('LOAD_EXT_FILE')) {
            $files = explode(',', $files);
            foreach ($files as $file) {
                $file = $path . 'Common/' . $file . '.php';
                if (is_file($file)) {
                    include $file;
                }
            }
        }
        // 加载自定义的动态配置文件
        if ($configs = C('LOAD_EXT_CONFIG')) {
            if (is_string($configs)) {
                $configs = explode(',', $configs);
            }

            foreach ($configs as $key => $config) {
                $file = is_file($config) ? $config : $path . 'config/' . $config . CONF_EXT;
                if (is_file($file)) {
                    is_numeric($key) ? C(load_Config($file)) : C($key, load_Config($file));
                }
            }
        }
    }
}

if (!function_exists('get_client_ip')) {
    /**
     * 获取客户端IP地址
     * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
     * @param boolean $adv 是否进行高级模式获取（有可能被伪装）
     * @return mixed
     */
    function get_client_ip($type = 0, $adv = false)
    {
        $type = $type ? 1 : 0;
        static $ip = null;
        if (null !== $ip) {
            return $ip[$type];
        }

        if ($adv) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $pos = array_search('unknown', $arr);
                if (false !== $pos) {
                    unset($arr[$pos]);
                }

                $ip = trim($arr[0]);
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        // IP地址合法验证
        $long = sprintf("%u", ip2long($ip));
        $ip = $long ? array($ip, $long) : array('0.0.0.0', 0);
        return $ip[$type];
    }
}

if (!function_exists('send_http_status')) {
    /**
     * 发送HTTP状态
     * @param integer $code 状态码
     * @return void
     */
    function send_http_status($code)
    {
        static $_status = array(
            // Informational 1xx
            100 => 'Continue',
            101 => 'Switching Protocols',
            // Success 2xx
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            // Redirection 3xx
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Moved Temporarily ', // 1.1
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            // 306 is deprecated but reserved
            307 => 'Temporary Redirect',
            // Client Error 4xx
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            // Server Error 5xx
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            509 => 'Bandwidth Limit Exceeded',
        );
        if (isset($_status[$code])) {
            header('HTTP/1.1 ' . $code . ' ' . $_status[$code]);
            // 确保FastCGI模式下正常
            header('Status:' . $code . ' ' . $_status[$code]);
        }
    }
}

if (!function_exists('think_filter')) {
    function think_filter(&$value)
    {
        // 过滤查询特殊字符
        if (preg_match('/^(EXP|NEQ|GT|EGT|LT|ELT|OR|XOR|LIKE|NOTLIKE|NOT BETWEEN|NOTBETWEEN|BETWEEN|NOTIN|NOT IN|IN|BIND)$/i', $value)) {
            $value .= ' ';
        }
    }
}

if (!function_exists('in_array_case')) {
    /**
     * 不区分大小写的in_array实现
     * @param $value
     * @param $array
     * @return bool
     */
    function in_array_case($value, $array)
    {
        return in_array(strtolower($value), array_map('strtolower', $array));
    }
}

if (!function_exists('check_tel_number')) {
    /**
     * 验证电话号码方法
     * @param $number
     * @param string $type
     * @return bool
     */
    function check_tel_number($number, $type = 'phone')
    {
        $regxArr = array(
            'phone' => '/^(\+?86-?)?(19|18|17|16|15|14|13)[0-9]{9}$/',
            'telephone' => '/^(010|02\d{1}|0[3-9]\d{2})-\d{7,9}(-\d+)?$/',
            '400' => '/^400(-\d{3,4}){2}$/',
        );
        if ($type && isset($regxArr[$type])) {
            return preg_match($regxArr[$type], $number) ? true : false;
        }
        foreach ($regxArr as $regx) {
            if (preg_match($regx, $number)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('str_replace_once')) {
    /**
     * 替换一次
     * @param $needle
     * @param $replace
     * @param $haystack
     * @return mixed
     */
    function str_replace_once($needle, $replace, $haystack)
    {
        // Looks for the first occurence of $needle in $haystack
        // and replaces it with $replace.
        $pos = strpos($haystack, $needle);
        if ($pos === false) {
            return $haystack;
        }

        return substr_replace($haystack, $replace, $pos, strlen($needle));

    }
}

if (!function_exists('un_camelize')) {
    /**
     * 驼峰命名转下划线命名
     * 思路:
     * 小写和大写紧挨一起的地方,加上分隔符,然后全部转小写
     * @param $camelCaps
     * @param string $separator
     * @return string
     */
    function un_camelize($camelCaps, $separator = '_')
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', "$1" . $separator . "$2", $camelCaps));
    }
}


if (!function_exists('is_many_dimension_array')) {
    /**
     * 是否是多维数组
     * @param $array
     * @return bool
     */
    function is_many_dimension_array($array)
    {
        if (count($array) == count($array, 1)) {
            return false;
        } else {
            return true;
        }
    }
}

if (!function_exists('array_depth')) {
    /**
     * 计算数组深度
     * @param $array
     * @return int
     */
    function array_depth($array)
    {
        $maxDepth = 1;
        foreach ($array as $value) {
            if (is_array($value)) {
                $depth = array_depth($value) + 1;
                if ($depth > $maxDepth) {
                    $maxDepth = $depth;
                }
            }
        }
        return $maxDepth;
    }
}

if (!function_exists('get_module_model_name')) {
    /**
     * @param $moduleData
     * @return string
     */
    function get_module_model_name($moduleData)
    {
        if ($moduleData['type'] === 'entity') {
            $class = '\\common\\model\\EntityModel';
        } else {
            $class = '\\common\\model\\' . camelize($moduleData['code']) . 'Model';
        }

        return $class;
    }
}


if (!function_exists('get_module_table_name')) {
    /**
     * @param $moduleData
     * @return string
     */
    function get_module_table_name($moduleData)
    {
        if ($moduleData['type'] === 'entity') {
            return 'entity';
        } else {
            return $moduleData['code'];
        }
    }
}

if (!function_exists('view')) {
    /**
     * 渲染模板输出
     * @param string $template 模板文件
     * @param array $vars 模板变量
     * @param array $replace 模板替换
     * @param integer $code 状态码
     * @return \think\response\View
     */
    function view($template = '', $vars = [], $replace = [], $code = 200)
    {
        return Response::create($template, 'view', $code)->replace($replace)->assign($vars);
    }
}


if (!function_exists('halt')) {
    /**
     * 调试变量并且中断输出
     */
    function halt()
    {
        throw new HttpResponseException(new Response);
    }
}

if (!function_exists('string_random')) {
    /**
     * 随机字符串加数字
     * @param $length
     * @return string
     * @throws Exception
     */
    function string_random($length)
    {
        $int = $length / 2;
        $bytes = random_bytes($int);
        $string = bin2hex($bytes);
        return $string;
    }
}


if (!function_exists('cpu_count')) {
    /**
     * @return int
     */
    function cpu_count()
    {
        if (strtolower(PHP_OS) === 'darwin') {
            $count = shell_exec('sysctl -n machdep.cpu.core_count');
        } else {
            $count = shell_exec('nproc');
        }
        $count = (int)$count > 0 ? (int)$count : 4;
        return $count;
    }
}

if (!function_exists('runtime_path')) {
    /**
     * @return string
     */
    function runtime_path()
    {
        return RUNTIME_PATH;
    }
}


if (!function_exists('base_path')) {
    /**
     * @return string
     */
    function base_path()
    {
        return ROOT_PATH;
    }
}

if (!function_exists('app_path')) {
    /**
     * @return string
     */
    function app_path()
    {
        return APP_PATH;
    }
}

if (!function_exists('config_path')) {
    /**
     * @return string
     */
    function config_path()
    {
        return COMMON_PATH . 'config' . DS;
    }
}

if (!function_exists('public_path')) {
    /**
     * @return string
     */
    function public_path()
    {
        return ROOT_PATH . 'public';
    }
}

if (!function_exists('create_uuid')) {
    /**
     * 生成uuid
     * @return mixed
     * @throws Exception
     */
    function create_uuid()
    {
        if (function_exists("uuid_create")) {
            return uuid_create();
        } else {
            return Webpatser\Uuid\Uuid::generate()->string;
        }
    }
}

if (!function_exists('request')) {
    /**
     * @return Request
     */
    function request()
    {
        return App::request();
    }
}