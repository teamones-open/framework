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

namespace think\console\command\make;

use think\console\command\Make;
use think\console\input\Option;

class Controller extends Make
{

    protected string $type = "Controller";

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
    protected function getStub(): string
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
    protected function getClassName($name): string
    {
        return parent::getClassName($name) . (C('controller_suffix') ? ucfirst(C('url_controller_layer')) : '');
    }

    /**
     * @param string $appNamespace
     * @param string $module
     * @return string
     */
    protected function getNamespace(string $appNamespace, string $module): string
    {
        return parent::getNamespace($appNamespace, $module) . '\controller';
    }

}
