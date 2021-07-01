<?php
declare(strict_types=1);

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// | Reviser: weijer <weiwei163@foxmail.com>
// +----------------------------------------------------------------------

namespace think;

use think\exception\ErrorCode;
use Workerman\Protocols\Http;

class Request extends \Workerman\Protocols\Http\Request
{

    /**
     * @var string 请求方法
     */
    protected string $method;

    /**
     * @var string 域名（含协议和端口）
     */
    protected string $domain;

    /**
     * @var string URL地址
     */
    protected string $url;

    /**
     * @var string 基础URL
     */
    protected string $baseUrl;

    /**
     * @var ?string
     */
    public ?string $app = null;

    /**
     * @var ?string
     */
    protected ?string $module = null;

    /**
     * @var ?string
     */
    public ?string $controller = null;

    /**
     * @var ?string
     */
    public ?string $action = null;

    /**
     * @var array 当前调度信息
     */
    protected array $dispatch = [];

    /**
     * @var array 请求参数
     */
    protected array $param = [];
    protected array $get = [];
    protected array $post = [];
    protected array $route = [];
    protected array $put = [];

    /**
     * @var string pathinfo（不含后缀）
     */
    protected string $path;

    /**
     * @var array 当前路由信息
     */
    protected array $routeInfo = [];


    // 输入参数
    protected string $content = '';
    protected string $input = '';


    // 全局过滤规则
    protected ?string $filter;

    // Hook扩展方法
    protected static array $hook = [];

    // 请求批次号
    public string $batchNumber = '';

    // 当前模块的 code
    public string $moduleCode = '';

    // 当前请求绑定的project_id
    public int $projectId = 0;

    // 当前请求绑定的tenant_id
    public int $tenantId = 0;

    // 当前请求绑定的user_uuid
    public string $userUUID = '';

    // 当前请求绑定的user_id
    public int $userId = 0;

    // 当前请求绑定的 union_user_id
    public int $unionUserId = 0;

    // 当前请求绑定的x-userinfo
    public string $xuserinfo = '';


    /**
     * 构造函数
     * Request constructor.
     * @param $buffer
     */
    public function __construct($buffer)
    {

        parent::__construct($buffer);

        // 全局过滤规则
        if (is_null($this->filter)) {
            $this->filter = C('DEFAULT_FILTER');
        }
    }

    /**
     * @param $method
     * @param $args
     * @return mixed
     * @throws \Exception
     */
    public function __call($method, $args)
    {
        if (array_key_exists($method, self::$hook)) {
            array_unshift($args, $this);
            return call_user_func_array(self::$hook[$method], $args);
        } else {
            throw new \Exception('method not exists:' . __CLASS__ . '->' . $method, ErrorCode::ERROR_404);
        }
    }

    /**
     * Hook 方法注入
     * @param $method
     * @param null $callback
     */
    public static function hook($method, $callback = null)
    {
        if (is_array($method)) {
            self::$hook = array_merge(self::$hook, $method);
        } else {
            self::$hook[$method] = $callback;
        }
    }

    /**
     * 获取用户信息缓存
     * @param string $unionId
     * @param array $XUserInfoBase
     * @return false|mixed
     */
    public function getUserInfoCache($unionId = '', $XUserInfoBase = []): mixed
    {
        if (!empty($unionId) && !empty($XUserInfoBase)) {
            $cacheKey = 'user_info_cache_' . C('belong_system') . '_' . $unionId;
            $userCache = Cache::get($cacheKey);
            $keys = ['phone', 'email', 'name', 'union_id', 'avatar'];
            if (!empty($userCache)) {
                foreach ($keys as $key) {
                    if (!empty($XUserInfoBase[$key]) && $XUserInfoBase[$key] === $userCache[$key]) {
                        return $userCache;
                    }
                }
            }
        }
        return false;
    }

    /**
     * 设置用户信息缓存
     * @param $unionId
     * @param $userData
     */
    public function setUserInfoCache($unionId, $userData)
    {
        $cacheKey = 'user_info_cache_' . C('belong_system') . '_' . $unionId;
        Cache::set($cacheKey, $userData);
    }

    /**
     * 设置操作批次号
     * @param string $batchNumber
     * @return string
     */
    public function getBatchNumber($batchNumber = ''): string
    {
        if (!empty($batchNumber)) {
            $this->batchNumber = $batchNumber;
        }
        return $this->batchNumber;
    }

    /**
     * 获取当前操作模块Code
     * @param string $moduleCode
     * @return string
     */
    public function getModuleCode($moduleCode = ''): string
    {
        if (!empty($moduleCode)) {
            $this->moduleCode = $moduleCode;
        }
        return $this->moduleCode;
    }

    /**
     * 获取当前操作的项目ID
     * @param int $projectId
     * @return int
     */
    public function getProjectId($projectId = 0): int
    {
        if (!empty($projectId)) {
            $this->projectId = $projectId;
        }
        return $this->projectId;
    }

    /**
     * 获取当前操作的租户ID
     * @param int $tenantId
     * @return int
     */
    public function getTenantId($tenantId = 0): int
    {
        if (!empty($tenantId)) {
            $this->tenantId = $tenantId;
        }
        return $this->tenantId;
    }

    /**
     * 获取当前系统操作的用户UUID
     * @param string $userUUID
     * @return string
     */
    public function getUserUUID($userUUID = ''): string
    {
        if (!empty($userUUID)) {
            $this->userUUID = $userUUID;
        }
        return $this->userUUID;
    }


    /**
     * 获取当前系统操作的用户ID
     * @param int $userId
     * @return int
     */
    public function getUserId($userId = 0): int
    {
        if (!empty($userId)) {
            $this->userId = $userId;
        }
        return $this->userId;
    }

    /**
     * 获取当前系统操作的全局用户ID
     * @param int $unionUserId
     * @return int
     */
    public function getUnionUserId($unionUserId = 0): int
    {
        if (!empty($unionUserId)) {
            $this->unionUserId = $unionUserId;
        }
        return $this->unionUserId;
    }


    /**
     * 获取当前系统操作的用户UUID
     * @param string $xUserInfo
     * @return string
     */
    public function getXUserInfo($xUserInfo = ''): string
    {
        if (!empty($xUserInfo)) {
            $this->xuserinfo = $xUserInfo;
        }
        return $this->xuserinfo;
    }

    /**
     * 设置或获取当前包含协议的域名
     * @param null $domain
     * @return $this|string
     */
    public function domain($domain = null): string|static
    {
        if (!is_null($domain)) {
            $this->domain = $domain;
            return $this;
        } elseif (!$this->domain) {
            $this->domain = $this->scheme() . '://' . $this->host(true);
        }
        return $this->domain;
    }

    /**
     * 设置或获取当前完整URL 包括QUERY_STRING
     * @param null $url
     * @return $this|string
     */
    public function url($url = null): string|static
    {
        if (!is_null($url) && true !== $url) {
            $this->url = $url;
            return $this;
        } elseif (!$this->url) {
            $this->url = '//' . $this->uri();
        }
        return true === $url ? $this->domain() . $this->url : $this->url;
    }

    /**
     * 设置或获取当前URL 不含QUERY_STRING
     * @param null $url
     * @return $this|string
     */
    public function baseUrl($url = null): string|static
    {
        if (!is_null($url) && true !== $url) {
            $this->baseUrl = $url;
            return $this;
        } elseif (!$this->baseUrl) {
            $str = $this->url();
            $this->baseUrl = strpos($str, '?') ? strstr($str, '?', true) : $str;
        }
        return true === $url ? $this->domain() . $this->baseUrl : $this->baseUrl;
    }

    /**
     * 当前URL的访问后缀
     * @access public
     * @return string
     */
    public function ext(): string
    {
        return pathinfo($this->path(), PATHINFO_EXTENSION);
    }

    /**
     * 获取当前请求URL的pathinfo信息(不含URL后缀)
     * @access public
     * @return string
     */
    public function path(): string
    {
        $path = parent::path();
        $this->path = substr_replace($path, "", 0, 1);
        return $this->path;
    }


    /**
     * 是否为GET请求
     * @access public
     * @return bool
     */
    public function isGet(): bool
    {
        return $this->method() == 'GET';
    }

    /**
     * 是否为POST请求
     * @access public
     * @return bool
     */
    public function isPost(): bool
    {
        return $this->method() == 'POST';
    }

    /**
     * 是否为PUT请求
     * @access public
     * @return bool
     */
    public function isPut(): bool
    {
        return $this->method() == 'PUT';
    }

    /**
     * 是否为DELTE请求
     * @access public
     * @return bool
     */
    public function isDelete(): bool
    {
        return $this->method() == 'DELETE';
    }

    /**
     * 是否为HEAD请求
     * @access public
     * @return bool
     */
    public function isHead(): bool
    {
        return $this->method() == 'HEAD';
    }

    /**
     * 是否为PATCH请求
     * @access public
     * @return bool
     */
    public function isPatch(): bool
    {
        return $this->method() == 'PATCH';
    }

    /**
     * 是否为OPTIONS请求
     * @access public
     * @return bool
     */
    public function isOptions(): bool
    {
        return $this->method() == 'OPTIONS';
    }


    /**
     * 获取当前请求的参数
     * @param string|false $name
     * @param null $default
     * @param string $filter
     * @return array|mixed
     */
    public function param($name = '', $default = null, $filter = '')
    {
        $this->param = [];

        $method = $this->method();

        // 自动获取请求变量
        switch ($method) {
            case 'POST':
                $vars = $this->post();
                break;
            case 'PUT':
            case 'DELETE':
            case 'PATCH':
                $vars = $this->put();
                break;
            default:
                $vars = [];
        }

        // 当前请求参数和URL地址中的参数合并
        $this->param = array_merge($this->get(), $vars);

        if (true === $name) {
            // 获取包含文件上传信息的数组
            $file = parent::file();
            $data = is_array($file) ? array_merge($this->param, $file) : $this->param;
            return $this->input($data, '', $default, $filter);
        }

        return $this->input($this->param, $name, $default, $filter);
    }


    /**
     * 设置获取GET参数
     * @access public
     * @param string|array $name 变量名
     * @param mixed $default 默认值
     * @param string|array $filter 过滤方法
     * @return mixed
     */
    public function get($name = '', $default = null, $filter = '')
    {
        $getContent = parent::get($name === "" ? null : $name, $default);

        if (empty($name) && $getContent === null) {
            $this->get = [];
        } else {
            $this->get = $getContent;
        }

        if (is_array($name)) {
            $this->param = [];
            return $this->get = array_merge($this->get, $name);
        }

        return $this->input($this->get, $name, $default, $filter);
    }

    /**
     * 设置获取路由参数
     * @access public
     * @param string|array $name 变量名
     * @param mixed $default 默认值
     * @param string|array $filter 过滤方法
     * @return mixed
     */
    public function route($name = '', $default = null, $filter = '')
    {
        if (is_array($name)) {
            $this->param = [];
            return $this->route = array_merge($this->route, $name);
        }
        return $this->input($this->route, $name, $default, $filter);
    }

    /**
     * 设置获取POST参数
     * @access public
     * @param string $name 变量名
     * @param mixed $default 默认值
     * @param string|array $filter 过滤方法
     * @return mixed
     */
    public function post($name = '', $default = null, $filter = '')
    {
        $postContent = parent::post();

        if (empty($postContent) && false !== strpos($this->contentType(), 'application/json')) {
            $content = $this->rawBody();
            $this->post = (array)json_decode($content, true);
        } else {
            $this->post = $postContent;
        }

        if (is_array($name)) {
            $this->param = [];
            return $this->post = array_merge($this->post, $name);
        }
        return $this->input($this->post, $name, $default, $filter);
    }

    /**
     * @param $http_post_boundary
     */
    protected function parseUploadFiles($http_post_boundary)
    {
        $http_body = $this->rawBody();
        $http_body = \substr($http_body, 0, \strlen($http_body) - (\strlen($http_post_boundary) + 4));
        $boundary_data_array = \explode($http_post_boundary . "\r\n", $http_body);
        if ($boundary_data_array[0] === '') {
            unset($boundary_data_array[0]);
        }
        $key = -1;
        $files = [];
        foreach ($boundary_data_array as $boundary_data_buffer) {
            list($boundary_header_buffer, $boundary_value) = \explode("\r\n\r\n", $boundary_data_buffer, 2);
            // Remove \r\n from the end of buffer.
            $boundary_value = \substr($boundary_value, 0, -2);
            $key++;
            foreach (\explode("\r\n", $boundary_header_buffer) as $item) {
                list($header_key, $header_value) = \explode(": ", $item);
                $header_key = \strtolower($header_key);
                switch ($header_key) {
                    case "content-disposition":
                        // Is file data.
                        if (\preg_match('/name="(.*?)"; filename="(.*?)"/i', $header_value, $match)) {
                            $error = 0;
                            $tmp_file = '';
                            $size = \strlen($boundary_value);
                            $tmp_upload_dir = HTTP::uploadTmpDir();
                            if (!$tmp_upload_dir) {
                                $error = UPLOAD_ERR_NO_TMP_DIR;
                            } else {
                                $tmp_file = \tempnam($tmp_upload_dir, 'workerman.upload.');
                                if ($tmp_file === false || false == \file_put_contents($tmp_file, $boundary_value)) {
                                    $error = UPLOAD_ERR_CANT_WRITE;
                                }
                            }
                            // Parse upload files.
                            $files[$key] = [
                                'key' => $match[1],
                                'name' => $match[2],
                                'tmp_name' => $tmp_file,
                                'size' => $size,
                                'error' => $error
                            ];
                            break;
                        } // Is post field.
                        else {
                            // Parse $_POST.
                            if (\preg_match('/name="(.*?)"$/', $header_value, $match)) {
                                // 处理多层数组
                                $keys = explode(' ', str_replace('[', ' ', str_replace(']', '', $match[1])));
                                if (count($keys) > 1) {
                                    $this->fillArray($this->_data['post'], $keys, $boundary_value);
                                } else {
                                    $this->_data['post'][$match[1]] = $boundary_value;
                                }
                            }
                        }
                        break;
                    case "content-type":
                        // add file_type
                        $files[$key]['type'] = \trim($header_value);
                        break;
                }
            }
        }

        foreach ($files as $file) {
            $key = $file['key'];
            unset($file['key']);

            $this->_data['files'][$key] = $file;
        }
    }

    /**
     * 递归填充数组
     * @param $container
     * @param $keys
     * @param $val
     */
    public function fillArray(&$container, $keys, $val)
    {
        if (!is_array($keys) || count($keys) == 0) {
            return;
        }
        if (count($keys) == 1) {
            $firstKey = array_shift($keys);
            // 如果是'' 那么直接追加数组
            if ($firstKey === '') {
                $container[] = $val;
            } else {
                $container[$firstKey] = $val;
            }
            return;
        }
        $firstKey = array_shift($keys);
        if (!is_array($container)) {
            $container = [];
        }
        // 如果是'' 那么直接追加数组
        if ($firstKey === '') {
            $container[] = $val;
        } else {
            if (!array_key_exists($firstKey, $container)) {
                $container[$firstKey] = null;
            }
        }
        $this->fillArray($container[$firstKey], $keys, $val);
    }

    /**
     * 设置获取PUT参数
     * @access public
     * @param string|array $name 变量名
     * @param mixed $default 默认值
     * @param string|array $filter 过滤方法
     * @return array
     */
    public function put($name = '', $default = null, $filter = ''): array
    {
        $content = $this->rawBody();
        if (false !== strpos($this->contentType(), 'application/json')) {
            $this->put = (array)json_decode($content, true);
        } else {
            parse_str($content, $this->put);
        }

        if (is_array($name)) {
            $this->param = [];
            return $this->put = is_null($this->put) ? $name : array_merge($this->put, $name);
        }

        return $this->input($this->put, $name, $default, $filter);
    }

    /**
     * 设置获取DELETE参数
     * @access public
     * @param string|array $name 变量名
     * @param mixed $default 默认值
     * @param string|array $filter 过滤方法
     * @return mixed
     */
    public function delete($name = '', $default = null, $filter = '')
    {
        return $this->put($name, $default, $filter);
    }

    /**
     * 设置获取PATCH参数
     * @access public
     * @param string|array $name 变量名
     * @param mixed $default 默认值
     * @param string|array $filter 过滤方法
     * @return mixed
     */
    public function patch($name = '', $default = null, $filter = '')
    {
        return $this->put($name, $default, $filter);
    }

    /**
     * 获取上传的文件信息
     * @param null $name
     * @return null| array | File
     */
    public function file($name = null)
    {
        $files = parent::file($name);
        if (null === $files) {
            return $name === null ? [] : null;
        }
        if ($name !== null) {
            $file = new File($files['tmp_name'], $files['name'], $files['type'], $files['error']);
            $file->setUploadInfo($files);
            return $file;
        }
        $uploadFiles = [];
        foreach ($files as $name => $file) {
            $uploadFiles[$name] = new File($file['tmp_name'], $file['name'], $file['type'], $file['error']);
            $uploadFiles[$name]->setUploadInfo($file);
        }
        return $uploadFiles;
    }

    /**
     * 获取变量支持过滤和默认值
     * @param array $data 数据源
     * @param string|false $name 字段名
     * @param mixed $default 默认值
     * @param string|array $filter 过滤函数
     * @return mixed
     */
    public function input($data = [], $name = '', $default = null, $filter = '')
    {
        if (false === $name) {
            // 获取原始数据
            return $data;
        }

        $name = (string)$name;
        if ('' != $name) {
            // 解析name
            if (strpos($name, '/')) {
                list($name, $type) = explode('/', $name);
            } else {
                $type = 's';
            }
            // 按.拆分成多维数组进行判断
            foreach (explode('.', $name) as $val) {
                if (isset($data[$val])) {
                    $data = $data[$val];
                } else {
                    // 无输入数据，返回默认值
                    return $default;
                }
            }
            if (is_object($data)) {
                return $data;
            }
        }

        // 解析过滤器
        if (!array_key_exists('parser_filter', $data)) {
            $filter = $this->getFilter($filter, $default);
            if (is_array($data)) {
                array_walk_recursive($data, [$this, 'filterValue'], $filter);
                $data = $this->parserFilter($data);
            } else {
                $this->filterValue($data, $name, $filter);
            }
            $data['parser_filter'] = true;
        }

        if (isset($type) && $data !== $default) {
            // 强制类型转换
            $this->typeCast($data, $type);
        }

        return $data;
    }

    /**
     * 处理过滤条件中的方法名
     * @param $data
     * @return mixed
     */
    public function parserFilter($data)
    {
        if ((array_key_exists("param", $data) && array_key_exists("filter", $data['param'])) || array_key_exists("filter", $data)) {
            array_walk_recursive($data, [$this, 'parserFilterCondition']);
        }
        return $data;
    }

    /**
     * 替换过滤条件中的方法名
     * @param $val
     */
    public function parserFilterCondition(&$val, $key)
    {
        $map = [
            '-or' => 'OR', // 或者
            '-and' => 'AND', // 且
            '-eq' => 'EQ', // 等于
            '-neq' => 'NEQ', // 不等于
            '-gt' => 'GT', // 大于
            '-egt' => 'EGT', // 大于等于
            '-lt' => 'LT', // 小于
            '-elt' => 'ELT', // 小于等于
            '-lk' => 'LIKE', // 模糊查询（像）
            '-not-lk' => 'NOTLIKE', // 模糊查询（不像）
            '-bw' => 'BETWEEN', // 在之间
            '-not-bw' => 'NOT BETWEEN', // 不在之间
            '-in' => 'IN', // 在里面
            '-not-in' => 'NOT IN' // 不在里面
        ];

        if (array_key_exists($val, $map)) {
            $val = $map[$val];
        }
    }

    /**
     * 设置或获取当前的过滤规则
     * @param mixed $filter 过滤规则
     * @return mixed
     */
    public function filter($filter = null)
    {
        if (is_null($filter)) {
            return $this->filter;
        } else {
            $this->filter = $filter;
        }
    }

    protected function getFilter($filter, $default)
    {
        if (is_null($filter)) {
            $filter = [];
        } else {
            $filter = $filter ?: $this->filter;
            if (is_string($filter) && false === strpos($filter, '/')) {
                $filter = explode(',', $filter);
            } else {
                $filter = (array)$filter;
            }
        }

        $filter[] = $default;
        return $filter;
    }

    /**
     * 递归过滤给定的值
     * @param mixed $value 键值
     * @param mixed $key 键名
     * @param array $filters 过滤方法+默认值
     * @return mixed
     */
    private function filterValue(&$value, $key, $filters)
    {
        $default = array_pop($filters);
        foreach ($filters as $filter) {
            if (is_callable($filter)) {
                // 调用函数或者方法过滤
                $value = call_user_func($filter, $value);
            } elseif (is_scalar($value)) {
                if (false !== strpos($filter, '/')) {
                    // 正则过滤
                    if (!preg_match($filter, $value)) {
                        // 匹配不成功返回默认值
                        $value = $default;
                        break;
                    }
                } elseif (!empty($filter)) {
                    // filter函数不存在时, 则使用filter_var进行过滤
                    // filter为非整形值时, 调用filter_id取得过滤id
                    $value = filter_var($value, is_int($filter) ? $filter : filter_id($filter));
                    if (false === $value) {
                        $value = $default;
                        break;
                    }
                }
            }
        }
        return $this->filterExp($value);
    }

    /**
     * 过滤表单中的表达式
     * @param string $value
     * @return void
     */
    public function filterExp(&$value)
    {
        // 过滤查询特殊字符
        if (is_string($value) && preg_match('/^(EXP|NEQ|GT|EGT|LT|ELT|OR|XOR|LIKE|NOTLIKE|NOT LIKE|NOT BETWEEN|NOTBETWEEN|BETWEEN|NOT EXISTS|NOTEXISTS|EXISTS|NOT NULL|NOTNULL|NULL|BETWEEN TIME|NOT BETWEEN TIME|NOTBETWEEN TIME|NOTIN|NOT IN|IN)$/i', $value)) {
            $value .= ' ';
        }
        // TODO 其他安全过滤
    }

    /**
     * 强制类型转换
     * @param string $data
     * @param string $type
     * @return mixed
     */
    private function typeCast(&$data, $type)
    {
        switch (strtolower($type)) {
            // 数组
            case 'a':
                $data = (array)$data;
                break;
            // 数字
            case 'd':
                $data = (int)$data;
                break;
            // 浮点
            case 'f':
                $data = (float)$data;
                break;
            // 布尔
            case 'b':
                $data = (boolean)$data;
                break;
            // 字符串
            case 's':
            default:
                if (is_scalar($data)) {
                    $data = (string)$data;
                } else {
                    throw new \InvalidArgumentException('variable type error：' . gettype($data), ErrorCode::VARIABLE_TYPE_ERROR);
                }
        }
    }

    /**
     * 是否存在某个请求参数
     * @access public
     * @param string $name 变量名
     * @param string $type 变量类型
     * @param bool $checkEmpty 是否检测空值
     * @return mixed
     */
    public function has($name, $type = 'param', $checkEmpty = false)
    {
        if (empty($this->$type)) {
            $param = $this->$type();
        } else {
            $param = $this->$type;
        }
        // 按.拆分成多维数组进行判断
        foreach (explode('.', $name) as $val) {
            if (isset($param[$val])) {
                $param = $param[$val];
            } else {
                return false;
            }
        }
        return ($checkEmpty && '' === $param) ? false : true;
    }


    /**
     * @param array $keys
     * @return array
     */
    public function only(array $keys)
    {
        $all = $this->all();
        $result = [];
        foreach ($keys as $key) {
            if (isset($all[$key])) {
                $result[$key] = $all[$key];
            }
        }
        return $result;
    }

    /**
     * @param array $keys
     * @return mixed|null
     */
    public function except(array $keys)
    {
        $all = $this->all();
        foreach ($keys as $key) {
            unset($all[$key]);
        }
        return $all;
    }

    /**
     * 当前是否ssl
     * @access public
     * @return bool
     */
    public function isSsl()
    {
        return (bool)$this->header('X_FORWARDED_PROTO');
    }


    /**
     * @return bool
     */
    public function isAjax()
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * @return bool
     */
    public function isPjax()
    {
        return (bool)$this->header('X-PJAX');
    }

    /**
     * 获取客户端IP地址
     * @param string $type
     * @return string
     */
    public function ip()
    {
        return $this->getRealIp();
    }

    /**
     * @return string
     */
    public function getRemoteIp()
    {
        return App::connection()->getRemoteIp();
    }

    /**
     * @return int
     */
    public function getRemotePort()
    {
        return App::connection()->getRemotePort();
    }

    /**
     * @return string
     */
    public function getLocalIp()
    {
        return App::connection()->getLocalIp();
    }

    /**
     * @return int
     */
    public function getLocalPort()
    {
        return App::connection()->getLocalPort();
    }


    /**
     * 检测是否使用手机访问
     * @return bool
     */
    public function isMobile()
    {
        return (bool)($this->header('X_WAP_PROFILE') || $this->header('PROFILE'));
    }


    /**
     * 当前URL地址中的scheme参数
     * @access public
     * @return string
     */
    public function scheme()
    {
        return $this->isSsl() ? 'https' : 'http';
    }

    /**
     * 当前请求URL地址中的port参数
     * @access public
     * @return integer
     */
    public function port()
    {
        return $this->header('host');
    }


    /**
     * @return mixed|null
     */
    public function all()
    {
        return $this->post() + $this->get();
    }

    /**
     * 当前请求 REMOTE_PORT
     * @access public
     * @return integer
     */
    public function remotePort()
    {
        return $this->getRemotePort();
    }


    /**
     * 当前请求 HTTP_CONTENT_TYPE
     * @access public
     * @return string
     */
    public function contentType()
    {
        $contentType = $this->header('content-type', '');
        if ($contentType) {
            if (strpos($contentType, ';')) {
                list($type) = explode(';', $contentType);
            } else {
                $type = $contentType;
            }
            return trim($type);
        }
        return '';
    }

    /**
     * 获取当前请求的路由信息
     * @access public
     * @param array $route 路由名称
     * @return array
     */
    public function routeInfo($route = [])
    {
        if (!empty($route)) {
            $this->routeInfo = $route;
        } else {
            return $this->routeInfo;
        }
    }

    /**
     * 设置或者获取当前请求的调度信息
     * @access public
     * @param array $dispatch 调度信息
     * @return array
     */
    public function dispatch($dispatch = null)
    {
        if (!is_null($dispatch)) {
            $this->dispatch = $dispatch;
        }
        return $this->dispatch;
    }

    /**
     * 设置或者获取当前的模块名
     * @param null $module
     * @return $this|string
     */
    public function module($module = null): string|static
    {
        if (!is_null($module)) {
            $this->module = $module;
            return $this;
        } else {
            return $this->module ?: '';
        }
    }


    /**
     * 设置或者获取当前的控制器名
     * @param null $controller
     * @return $this|string
     */
    public function controller($controller = null): string|static
    {
        if (!is_null($controller)) {
            $this->controller = $controller;
            return $this;
        } else {
            return $this->controller ?: '';
        }
    }


    /**
     * 设置或者获取当前的操作名
     * @param null $action
     * @return $this|string
     */
    public function action($action = null): string|static
    {
        if (!is_null($action) && !is_bool($action)) {
            $this->action = $action;
            return $this;
        } else {
            $name = $this->action ?: '';
            return true === $action ? $name : strtolower($name);
        }
    }

    /**
     * 设置或者获取当前请求的content
     * @access public
     * @return string
     */
    public function getContent(): string
    {
        if (!empty($this->content)) {
            $this->content = $this->input;
        }
        return $this->content;
    }

    /**
     * 获取当前请求的php://input
     * @access public
     * @return string
     */
    public function getInput(): string
    {
        return $this->input;
    }

    /**
     * @param bool $safe_mode
     * @return string
     */
    public function getRealIp($safe_mode = true)
    {
        $remote_ip = $this->getRemoteIp();
        if ($safe_mode && !static::isIntranetIp($remote_ip)) {
            return $remote_ip;
        }
        return $this->header('client-ip', $this->header('x-forwarded-for',
            $this->header('x-real-ip', $this->header('x-client-ip',
                $this->header('via', $remote_ip)))));
    }


    /**
     * @return string
     */
    public function fullUrl()
    {
        return '//' . $this->host() . $this->uri();
    }


    /**
     * @return bool
     */
    public function expectsJson()
    {
        return ($this->isAjax() && !$this->isPjax()) || $this->acceptJson();
    }

    /**
     * @return bool
     */
    public function acceptJson()
    {
        return false !== strpos($this->header('accept'), 'json');
    }

    /**
     * @param string $ip
     * @return bool
     */
    public static function isIntranetIp($ip = ''): bool
    {
        $reservedIps = [
            '167772160' => 184549375,  /*    10.0.0.0 -  10.255.255.255 */
            '3232235520' => 3232301055, /* 192.168.0.0 - 192.168.255.255 */
            '2130706432' => 2147483647, /*   127.0.0.0 - 127.255.255.255 */
            '2886729728' => 2887778303, /*  172.16.0.0 -  172.31.255.255 */
        ];

        $ipLong = ip2long($ip);

        foreach ($reservedIps as $ipStart => $ipEnd) {
            if (($ipLong >= $ipStart) && ($ipLong <= $ipEnd)) {
                return true;
            }
        }
        return false;
    }
}
