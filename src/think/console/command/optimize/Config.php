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

namespace think\console\command\optimize;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

class Config extends Command
{
    /** @var  Output */
    protected Output $output;

    protected function configure()
    {
        $this->setName('optimize:config')
            ->addArgument('module', Argument::OPTIONAL, 'Build module config cache .')
            ->setDescription('Build config and common file cache.');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     * @throws \think\Exception
     */
    protected function execute(Input $input, Output $output)
    {
        if ($input->hasArgument('module')) {
            $module = $input->getArgument('module') . DS;
        } else {
            $module = '';
        }

        $content = '<?php ' . PHP_EOL . $this->buildCacheContent($module);

        if (!is_dir(RUNTIME_PATH . $module)) {
            @mkdir(RUNTIME_PATH . $module, 0755, true);
        }

        file_put_contents(RUNTIME_PATH . $module . 'init' . EXT, $content);

        $output->writeln('<info>Succeed!</info>');
    }

    /**
     * @param $module
     * @return string
     * @throws \think\Exception
     */
    protected function buildCacheContent($module): string
    {
        $content = '';
        $path    = realpath(APP_PATH . $module) . DS;

        if ($module) {
            // 加载模块配置
            $config = load_config(CONF_PATH . $module . 'config' . CONF_EXT);

            // 读取数据库配置文件
            $filename = CONF_PATH . $module . 'database' . CONF_EXT;
            load_config($filename, 'database');

            // 加载应用状态配置
            if ($config['app_status']) {
                load_config(CONF_PATH . $module . $config['app_status'] . CONF_EXT);
            }

            // 读取扩展配置文件
            if (is_dir(CONF_PATH . $module . 'extra')) {
                $dir   = CONF_PATH . $module . 'extra';
                $files = scandir($dir);
                foreach ($files as $file) {
                    if (strpos($file, CONF_EXT)) {
                        $filename = $dir . DS . $file;
                        load_config($filename, pathinfo($file, PATHINFO_FILENAME));
                    }
                }
            }
        }

        // 加载行为扩展文件
        if (is_file(CONF_PATH . $module . 'tags' . EXT)) {
            $content .= 'Hook::import(' . (var_export(include CONF_PATH . $module . 'tags' . EXT, true)) . ');' . PHP_EOL;
        }

        // 加载公共文件
        if (is_file($path . 'common' . EXT)) {
            $content .= substr(php_strip_whitespace($path . 'common' . EXT), 5) . PHP_EOL;
        }

        $content .= 'C(' . var_export(C(), true) . ');';
        return $content;
    }
}
