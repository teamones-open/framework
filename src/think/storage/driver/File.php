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

namespace think\storage\driver;

use think\exception\ErrorCode;
use think\Storage;

// 本地文件写入存储类
class File extends Storage
{

    private array $contents = [];

    /**
     * 架构函数
     * @access public
     */
    public function __construct()
    {
    }

    /**
     * 文件内容读取
     * @access public
     * @param string $filename 文件名
     * @param string $type
     * @return false|mixed
     */
    public function read(string $filename, string $type = '')
    {
        return $this->get($filename, 'content', $type);
    }

    /**
     * 文件写入
     * @access public
     * @param string $filename 文件名
     * @param string $content 文件内容
     * @param string $type
     * @return bool
     * @throws \Exception
     */
    public function put(string $filename, string $content, string $type = ''): bool
    {
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if (false === file_put_contents($filename, $content)) {
            StrackE(L('_STORAGE_WRITE_ERROR_') . ':' . $filename, ErrorCode::STORAGE_WRITE_ERROR);
        } else {
            $this->contents[$filename] = $content;
            return true;
        }
    }

    /**
     * 文件追加写入
     * @access public
     * @param string $filename 文件名
     * @param string $content 追加的文件内容
     * @param string $type
     * @return bool
     * @throws \Exception
     */
    public function append(string $filename, string $content, string $type = ''): bool
    {
        if (is_file($filename)) {
            $content = $this->read($filename, $type) . $content;
        }
        return $this->put($filename, $content, $type);
    }

    /**
     * 加载文件
     * @access public
     * @param string $filename 文件名
     * @param array $vars 传入变量
     * @return void
     */
    public function load(string $filename, $vars = []): void
    {
        if (!empty($vars) && is_array($vars)) {
            extract($vars, EXTR_OVERWRITE);
        }
        include $filename;
    }

    /**
     * 文件是否存在
     * @access public
     * @param string $filename 文件名
     * @param string $type
     * @return bool
     */
    public function has(string $filename, string $type = ''): bool
    {
        return is_file($filename);
    }

    /**
     * 文件删除
     * @access public
     * @param string $filename 文件名
     * @param string $type
     * @return bool
     */
    public function unlink(string $filename, $type = ''): bool
    {
        unset($this->contents[$filename]);
        return is_file($filename) && unlink($filename);
    }

    /**
     * 读取文件信息
     * @access public
     * @param string $filename 文件名
     * @param string $name 信息名 mtime或者content
     * @param string $type
     * @return false|mixed
     */
    public function get(string $filename, string $name, $type = '')
    {
        if (!isset($this->contents[$filename])) {
            if (!is_file($filename)) {
                return false;
            }

            $this->contents[$filename] = file_get_contents($filename);
        }
        $content = $this->contents[$filename];
        $info = [
            'mtime' => filemtime($filename),
            'content' => $content,
        ];
        return $info[$name];
    }
}
