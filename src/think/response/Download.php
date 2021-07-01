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

namespace think\response;

use think\exception\ErrorCode;
use think\Response;

class Download extends Response
{
    protected int $expire = 360;
    protected string $name = '';
    protected string $mimeType = '';
    protected bool $isContent = false;

    /**
     * 处理数据
     * @access protected
     * @param mixed $data 要处理的数据
     * @return false|mixed|string
     * @throws \Exception
     */
    protected function output($data)
    {
        if (!$this->isContent && !is_file($data)) {
            throw new \Exception('file not exists:' . $data, ErrorCode::FILE_NOT_EXISTS);
        }

        ob_end_clean();

        if (!empty($this->name)) {
            $name = $this->name;
        } else {
            $name = !$this->isContent ? pathinfo($data, PATHINFO_BASENAME) : '';
        }

        if ($this->isContent) {
            $mimeType = $this->mimeType;
            $size = strlen($data);
        } else {
            $mimeType = $this->getMimeType($data);
            $size = filesize($data);
        }

        $this->header['Pragma'] = 'public';
        $this->header['Content-Type'] = $mimeType ?: 'application/octet-stream';
        $this->header['Cache-control'] = 'max-age=' . $this->expire;
        $this->header['Content-Disposition'] = 'attachment; filename="' . $name . '"';
        $this->header['Content-Length'] = $size;
        $this->header['Content-Transfer-Encoding'] = 'binary';
        $this->header['Expires'] = gmdate("D, d M Y H:i:s", time() + $this->expire) . ' GMT';

        $this->lastModified(gmdate('D, d M Y H:i:s', time()) . ' GMT');

        return $this->isContent ? $data : file_get_contents($data);
    }

    /**
     * 设置是否为内容 必须配合mimeType方法使用
     * @access public
     * @param bool $content
     * @return $this
     */
    public function isContent(bool $content = true): Download
    {
        $this->isContent = $content;
        return $this;
    }

    /**
     * 设置有效期
     * @access public
     * @param int $expire 有效期
     * @return $this
     */
    public function expire(int $expire): Download
    {
        $this->expire = $expire;
        return $this;
    }

    /**
     * 设置文件类型
     * @param string $mimeType
     * @return $this
     */
    public function mimeType(string $mimeType): Download
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    /**
     * 获取文件类型信息
     * @access public
     * @param string $filename 文件名
     * @return string
     */
    protected function getMimeType(string $filename): string
    {
        if (!empty($this->mimeType)) {
            return $this->mimeType;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        return finfo_file($finfo, $filename);
    }

    /**
     * 设置下载文件的显示名称
     * @access public
     * @param string $filename 文件名
     * @param bool $extension 后缀自动识别
     * @return $this
     */
    public function name(string $filename, $extension = true): Download
    {
        $this->name = $filename;

        if ($extension && !strpos($filename, '.')) {
            $this->name .= '.' . pathinfo($this->data, PATHINFO_EXTENSION);
        }

        return $this;
    }
}
