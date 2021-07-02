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

use think\App;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Db;

class Schema extends Command
{
    /** @var  Output */
    protected Output $output;

    protected function configure()
    {
        $this->setName('optimize:schema')
            ->addOption('db', null, Option::VALUE_REQUIRED, 'db name .')
            ->addOption('table', null, Option::VALUE_REQUIRED, 'table name .')
            ->addOption('module', null, Option::VALUE_REQUIRED, 'module name .')
            ->setDescription('Build database schema cache.');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     * @throws \ReflectionException
     */
    protected function execute(Input $input, Output $output)
    {
        if (!is_dir(RUNTIME_PATH . 'schema')) {
            @mkdir(RUNTIME_PATH . 'schema', 0755, true);
        }
        if ($input->hasOption('module')) {
            $module = $input->getOption('module');
            // 读取模型
            $list = scandir(APP_PATH . $module . DS . 'model');
            $app  = App::$namespace;
            foreach ($list as $file) {
                if ('.' == $file || '..' == $file) {
                    continue;
                }
                $class = '\\' . $app . '\\' . $module . '\\model\\' . pathinfo($file, PATHINFO_FILENAME);
                $this->buildModelSchema($class);
            }
            $output->writeln('<info>Succeed!</info>');
            return;
        } elseif ($input->hasOption('table')) {
            $table = $input->getOption('table');
            if (!strpos($table, '.')) {
                $dbName = Db::getConfig('database');
            }
            $tables[] = $table;
        } elseif ($input->hasOption('db')) {
            $dbName = $input->getOption('db');
            $tables = Db::getTables($dbName);
        } elseif (!C('MULTI_MODULE')) {
            $app  = App::$namespace;
            $list = scandir(APP_PATH . 'model');
            foreach ($list as $file) {
                if ('.' == $file || '..' == $file) {
                    continue;
                }
                $class = '\\' . $app . '\\model\\' . pathinfo($file, PATHINFO_FILENAME);
                $this->buildModelSchema($class);
            }
            $output->writeln('<info>Succeed!</info>');
            return;
        } else {
            $tables = Db::getTables();
        }

        $db = isset($dbName) ? $dbName . '.' : '';
        $this->buildDataBaseSchema($tables, $db);

        $output->writeln('<info>Succeed!</info>');
    }

    /**
     * @param $class
     * @throws \ReflectionException
     */
    protected function buildModelSchema($class)
    {
        $reflect = new \ReflectionClass($class);
        if (!$reflect->isAbstract() && $reflect->isSubclassOf('\think\Model')) {
            $table   = $class::getTableName();
            $dbName  = $class::getConfig('database');
            $content = '<?php ' . PHP_EOL . 'return ';
            $info    = $class::connect()->getFields($table);
            $content .= var_export($info, true) . ';';
            file_put_contents(RUNTIME_PATH . 'schema' . DS . $dbName . '.' . $table . EXT, $content);
        }
    }

    /**
     * @param $tables
     * @param $db
     */
    protected function buildDataBaseSchema($tables, $db)
    {
        if ('' == $db) {
            $dbName = Db::getConfig('database') . '.';
        } else {
            $dbName = $db;
        }
        foreach ($tables as $table) {
            $content = '<?php ' . PHP_EOL . 'return ';
            $info    = Db::getFields($db . $table);
            $content .= var_export($info, true) . ';';
            file_put_contents(RUNTIME_PATH . 'schema' . DS . $dbName . $table . EXT, $content);
        }
    }
}
