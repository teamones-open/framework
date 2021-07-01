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

use think\exception\ErrorCode;

class Response
{

    /**
     * 原始数据
     * @var mixed
     */
    protected $data;


    /**
     * 当前contentType
     * @var string
     */
    protected string $contentType = 'text/html';

    /**
     * 字符集
     * @var string
     */
    protected string $charset = 'utf-8';

    /**
     * 状态码
     * @var integer
     */
    protected int $code = 200;

    /**
     * 是否允许请求缓存
     * @var bool
     */
    protected bool $allowCache = true;

    /**
     * 输出参数
     * @var array
     */
    protected array $options = [];

    /**
     * header参数
     * @var array
     */
    protected array $header = [];

    /**
     * 输出内容
     * @var string
     */
    protected string $content = '';


    /**
     * 架构函数
     * @access public
     * @param mixed $data 输出数据
     * @param int $code
     * @param array $header
     * @param array $options 输出参数
     */
    public function __construct($data = '', $code = 200, array $header = [], $options = [])
    {
        $this->data($data);

        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }

        $this->contentType($this->contentType, $this->charset);

        $this->code = $code;
        $this->header = array_merge($this->header, $header);
    }

    /**
     * 创建Response对象
     * @access public
     * @param mixed $data 输出数据
     * @param string $type 输出类型
     * @param int $code
     * @param array $header
     * @param array $options 输出参数
     * @return Response
     */
    public static function create($data = '', $type = '', $code = 200, array $header = [], $options = []): Response
    {
        $class = false !== strpos($type, '\\') ? $type : '\\think\\response\\' . ucfirst(strtolower($type));

        if (class_exists($class)) {
            $response = new $class($data, $code, $header, $options);
        } else {
            $response = new static($data, $code, $header, $options);
        }

        return $response;
    }


    /**
     * 发送数据到客户端
     * @access public
     * @return void
     * @throws \InvalidArgumentException
     */
    public function send()
    {
        // 处理输出数据
        $data = $this->getContent();

        if (!headers_sent() && !empty($this->header)) {
            // 发送状态码
            http_response_code($this->code);
            // 发送头部信息
            foreach ($this->header as $name => $val) {
                header($name . (!is_null($val) ? ':' . $val : ''));
            }
        }

        $this->sendData($data);

        if (function_exists('fastcgi_finish_request')) {
            // 提高页面响应
            fastcgi_finish_request();
        }
    }

    /**
     * 渲染输出数据
     * @return \Workerman\Protocols\Http\Response
     */
    public function renderWorkermanData(): \Workerman\Protocols\Http\Response
    {
        // 处理输出数据
        $data = $this->getContent();
        return new \Workerman\Protocols\Http\Response($this->code, $this->header, $data);
    }


    /**
     * 处理数据
     * @access protected
     * @param mixed $data 要处理的数据
     * @return mixed
     */
    protected function output($data)
    {
        return $data;
    }

    /**
     * 输出数据
     * @access protected
     * @param string $data 要处理的数据
     * @return void
     */
    protected function sendData(string $data)
    {
        echo $data;
    }

    /**
     * 输出的参数
     * @access public
     * @param mixed $options 输出参数
     * @return $this
     */
    public function options($options = []): Response
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * 输出数据设置
     * @access public
     * @param mixed $data 输出数据
     * @return $this
     */
    public function data($data): Response
    {
        $this->data = $data;

        return $this;
    }

    /**
     * 是否允许请求缓存
     * @access public
     * @param bool $cache 允许请求缓存
     * @return $this
     */
    public function allowCache($cache): Response
    {
        $this->allowCache = $cache;

        return $this;
    }

    /**
     * 设置响应头
     * @access public
     * @param string|array $name 参数名
     * @param string $value 参数值
     * @return $this
     */
    public function header($name, $value = null): Response
    {
        if (is_array($name)) {
            $this->header = array_merge($this->header, $name);
        } else {
            $this->header[$name] = $value;
        }

        return $this;
    }

    /**
     * 设置页面输出内容
     * @access public
     * @param mixed $content
     * @return $this
     */
    public function content($content): Response
    {
        if (null !== $content && !is_string($content) && !is_numeric($content) && !is_callable([
                $content,
                '__toString',
            ])
        ) {
            throw new \InvalidArgumentException(sprintf('variable type error： %s', gettype($content)), ErrorCode::VARIABLE_TYPE_ERROR);
        }

        $this->content = (string)$content;

        return $this;
    }

    /**
     * 发送HTTP状态
     * @access public
     * @param int $code 状态码
     * @return $this
     */
    public function code(int $code): Response
    {
        $this->code = $code;

        return $this;
    }

    /**
     * LastModified
     * @access public
     * @param string $time
     * @return $this
     */
    public function lastModified(string $time): Response
    {
        $this->header['Last-Modified'] = $time;

        return $this;
    }

    /**
     * Expires
     * @access public
     * @param string $time
     * @return $this
     */
    public function expires(string $time): Response
    {
        $this->header['Expires'] = $time;

        return $this;
    }

    /**
     * ETag
     * @access public
     * @param string $eTag
     * @return $this
     */
    public function eTag(string $eTag): Response
    {
        $this->header['ETag'] = $eTag;

        return $this;
    }

    /**
     * 页面缓存控制
     * @access public
     * @param string $cache 缓存设置
     * @return $this
     */
    public function cacheControl(string $cache): Response
    {
        $this->header['Cache-control'] = $cache;

        return $this;
    }

    /**
     * 设置页面不做任何缓存
     * @access public
     * @return $this
     */
    public function noCache(): Response
    {
        $this->header['Cache-Control'] = 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0';
        $this->header['Pragma'] = 'no-cache';

        return $this;
    }

    /**
     * 页面输出类型
     * @access public
     * @param string $contentType 输出类型
     * @param string $charset 输出编码
     * @return $this
     */
    public function contentType(string $contentType, string $charset = 'utf-8'): Response
    {
        $this->header['Content-Type'] = $contentType . '; charset=' . $charset;

        return $this;
    }

    /**
     * 获取头部信息
     * @access public
     * @param string $name 头部名称
     * @return mixed
     */
    public function getHeader(string $name = '')
    {
        if (!empty($name)) {
            return $this->header[$name] ?? null;
        }

        return $this->header;
    }

    /**
     * 获取原始数据
     * @access public
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * 获取输出数据
     * @return string
     */
    public function getContent(): string
    {

        $content = $this->output($this->data);

        if (null !== $content && !is_string($content) && !is_numeric($content) && !is_callable([
                $content,
                '__toString',
            ])
        ) {
            throw new \InvalidArgumentException(sprintf('variable type error： %s', gettype($content)), ErrorCode::VARIABLE_TYPE_ERROR);
        }

        $this->content = (string)$content;

        return $this->content;
    }

    /**
     * 获取状态码
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }
}
