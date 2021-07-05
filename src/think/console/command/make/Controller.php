<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 刘志淳 <chun@engineer.com>
// +----------------------------------------------------------------------

namespace think\console\command\make;

use think\console\command\Make;
use think\console\input\Option;

class Controller extends Make
{

    protected $type = "Controller";

    protected function configure()
    {
        parent::configure();
        $this->setName('make:controller')
            ->addOption('plain', null, Option::VALUE_NONE, 'Generate an empty controller class.')
            ->setDescription('Create a new resource controller class');
    }

    /**
     * @return string
     */
    protected function getStub()
    {
        if ($this->input->getOption('plain')) {
            return __DIR__ . '/stubs/controller.plain.stub';
        }
        return __DIR__ . '/stubs/controller.stub';
    }

    /**
     * @param $name
     * @return string
     */
    protected function getClassName($name)
    {
        return parent::getClassName($name) . (C('CONTROLLER_SUFFIX') ? ucfirst(C('URL_CONTROLLER_LAYER')) : '');
    }

    /**
     * @param $appNamespace
     * @param $module
     * @return string
     */
    protected function getNamespace($appNamespace, $module)
    {
        return parent::getNamespace($appNamespace, $module) . '\Controller';
    }

}
