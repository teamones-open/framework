<?php
// +----------------------------------------------------------------------
// | TOPThink [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://topthink.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
namespace think\storage\driver;

use think\exception\ErrorCode;
use think\Storage;

// 本地文件写入存储类
class File extends Storage
{

    private $contents = [];

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
     * @return bool
     */
    public function read($filename, $type = '')
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
    public function put($filename, $content, $type = '')
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
    public function append($filename, $content, $type = '')
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
    public function load($filename, $vars = null)
    {
        if (!is_null($vars)) {
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
    public function has($filename, $type = '')
    {
        return is_file($filename);
    }

    /**
     * 文件删除
     * @access public
     * @param string $filename 文件名
     * @param string $type
     * @return boolean
     */
    public function unlink($filename, $type = '')
    {
        unset($this->contents[$filename]);
        return is_file($filename) ? unlink($filename) : false;
    }

    /**
     * 读取文件信息
     * @access public
     * @param string $filename 文件名
     * @param string $name 信息名 mtime或者content
     * @param string $type
     * @return boolean
     */
    public function get($filename, $name, $type = '')
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
