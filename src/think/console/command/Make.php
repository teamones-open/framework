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

namespace think\console\command;

use think\console\Command;
use think\Console\Input;
use think\Console\input\Argument;
use think\Console\Output;

abstract class Make extends Command
{

    protected string $type;

    abstract protected function getStub(): string;

    protected function configure()
    {
        $this->addArgument('name', Argument::REQUIRED, "The name of the class");
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return false
     */
    protected function execute(Input $input, Output $output): bool
    {

        $name = trim($input->getArgument('name'));

        $classname = $this->getClassName($name);

        $pathname = $this->getPathName($classname);

        if (is_file($pathname)) {
            $output->writeln('<error>' . $this->type . ' already exists!</error>');
            return false;
        }

        if (!is_dir(dirname($pathname))) {
            mkdir(strtolower(dirname($pathname)), 0755, true);
        }

        file_put_contents($pathname, $this->buildClass($classname));

        $output->writeln('<info>' . $this->type . ' created successfully.</info>');
        return true;

    }

    protected function buildClass($name)
    {
        $stub = file_get_contents($this->getStub());

        $namespace = trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');

        $class = str_replace($namespace . '\\', '', $name);

        return str_replace(['{%className%}', '{%namespace%}', '{%app_namespace%}'], [
            $class,
            $namespace,
            C('APP_NAMESPACE')
        ], $stub);

    }

    /**
     * @param string $name
     * @return string
     */
    protected function getPathName(string $name): string
    {
        $name = str_replace(C('APP_NAMESPACE') . '\\', '', $name);

        return APP_PATH . str_replace('\\', '/', $name) . '.php';
    }

    /**
     * @param string $name
     * @return string
     */
    protected function getClassName(string $name): string
    {
        $appNamespace = C('APP_NAMESPACE');

        if (strpos($name, $appNamespace . '\\') === 0) {
            return $name;
        }

        if (C('MULTI_MODULE')) {
            if (strpos($name, '/')) {
                list($module, $name) = explode('/', $name, 2);
            } else {
                $module = 'common';
            }
        } else {
            $module = null;
        }

        if (strpos($name, '/') !== false) {
            $name = str_replace('/', '\\', $name);
        }

        return $this->getNamespace($appNamespace, $module) . '\\' . $name;
    }

    /**
     * @param string $appNamespace
     * @param string $module
     * @return string
     */
    protected function getNamespace(string $appNamespace, string $module): string
    {
        return $module ? ($appNamespace . '\\' . $module) : $appNamespace;
    }

}
