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
 * 控制器基类 抽象类
 */
class Controller
{
    /**
     * 控制器参数
     * @var config
     * @access protected
     */
    protected $config = array();

    /**
     * 架构函数 取得模板对象实例
     * @access public
     */
    public function __construct()
    {
        Hook::listen('action_begin', $this->config);

        //控制器初始化
        if (method_exists($this, '_initialize')) {
            $this->_initialize();
        }
    }


    /**
     * 魔术方法 有不存在的操作的时候执行
     * @param $method
     * @param $args
     * @throws \Exception
     */
    public function __call($method, $args)
    {
        if (0 === strcasecmp($method, \request()->action() . C('ACTION_SUFFIX'))) {
            if (method_exists($this, '_empty')) {
                // 如果定义了_empty操作 则调用
                $this->_empty($method, $args);
            } else {
                StrackE(L('_ERROR_ACTION_') . ':' . \request()->action());
            }
        } else {
            StrackE(__CLASS__ . ':' . $method . L('_METHOD_NOT_EXIST_'));
            return;
        }
    }

    /**
     * 析构方法
     * @access public
     */
    public function __destruct()
    {
        // 执行后续操作
        Hook::listen('action_end');
    }
}
