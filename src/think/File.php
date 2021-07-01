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

class File extends \SplFileInfo
{
    /**
     * 错误信息
     * @var string
     */
    private string $error = '';

    // 当前完整文件名
    protected string $filename;

    // 上传文件名
    protected string $saveName;

    // 文件上传命名规则
    protected string $rule = 'date';

    // 文件上传验证规则
    protected array $validate = [];

    // 单元测试
    protected bool $isTest;

    // 上传文件信息
    protected array $info;

    // 文件hash信息
    protected array $hash = [];

    /**
     * @var string
     */
    protected string $_uploadMimeType = '';

    /**
     * @var int
     */
    protected int $_uploadErrorCode = 0;


    /**
     * UploadFile constructor.
     * UploadFile constructor.
     * @param $fileName
     * @param $uploadName
     * @param $uploadMimeType
     * @param $uploadErrorCode
     */
    public function __construct($fileName, $uploadName = "", $uploadMimeType = "", $uploadErrorCode = 0)
    {
        $this->saveName = $uploadName;
        $this->_uploadMimeType = $uploadMimeType;
        $this->_uploadErrorCode = $uploadErrorCode;
        parent::__construct($fileName);
        $this->filename = $this->getRealPath() ?: $this->getPathname();
    }

    /**
     * 是否测试
     * @param bool $test 是否测试
     * @return $this
     */
    public function isTest($test = false): File
    {
        $this->isTest = $test;
        return $this;
    }

    /**
     * 设置上传信息
     * @param array $info 上传文件信息
     * @return $this
     */
    public function setUploadInfo(array $info): File
    {
        $this->info = $info;
        return $this;
    }

    /**
     * 获取上传文件的信息
     * @param string $name
     * @return array|string
     */
    public function getInfo($name = '')
    {
        return $this->info[$name] ?? $this->info;
    }

    /**
     * 获取上传文件的文件名
     * @return string
     */
    public function getSaveName(): string
    {
        return $this->saveName;
    }

    /**
     * 设置上传文件的保存文件名
     * @param string $saveName
     * @return $this
     */
    public function setSaveName(string $saveName): File
    {
        $this->saveName = $saveName;
        return $this;
    }

    /**
     * 获取文件的哈希散列值
     * @param string $type
     * @return mixed
     */
    public function hash($type = 'sha1')
    {
        if (!isset($this->hash[$type])) {
            $this->hash[$type] = hash_file($type, $this->filename);
        }
        return $this->hash[$type];
    }

    /**
     * 检查目录是否可写
     * @param string $path 目录
     * @return boolean
     */
    protected function checkPath(string $path): bool
    {
        if (is_dir($path)) {
            return true;
        }

        if (mkdir($path, 0755, true)) {
            return true;
        } else {
            $this->error = "目录 {$path} 创建失败！";
            return false;
        }
    }

    /**
     * 获取文件类型信息
     * @return false|string
     */
    public function getMime()
    {
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        return finfo_file($fileInfo, $this->filename);
    }

    /**
     * 设置文件的命名规则
     * @param string $rule 文件命名规则
     * @return $this
     */
    public function rule(string $rule): File
    {
        $this->rule = $rule;
        return $this;
    }

    /**
     * 设置上传文件的验证规则
     * @param array $rule 验证规则
     * @return $this
     */
    public function validate($rule = []): File
    {
        $this->validate = $rule;
        return $this;
    }

    /**
     * 检测是否合法的上传文件
     * @return bool
     */
    public function isValid(): bool
    {
        if ($this->isTest) {
            return is_file($this->filename);
        }
        return is_uploaded_file($this->filename);
    }

    /**
     * 检测上传文件
     * @param array $rule 验证规则
     * @return bool
     */
    public function check($rule = []): bool
    {
        $rule = $rule ?: $this->validate;

        /* 检查文件大小 */
        if (isset($rule['size']) && !$this->checkSize($rule['size'])) {
            $this->error = '上传文件大小不符！';
            return false;
        }

        /* 检查文件Mime类型 */
        if (isset($rule['type']) && !$this->checkMime($rule['type'])) {
            $this->error = '上传文件MIME类型不允许！';
            return false;
        }

        /* 检查文件后缀 */
        if (isset($rule['ext']) && !$this->checkExt($rule['ext'])) {
            $this->error = '上传文件后缀不允许';
            return false;
        }

        /* 检查图像文件 */
        if (!$this->checkImg()) {
            $this->error = '非法图像文件！';
            return false;
        }

        return true;
    }

    /**
     * @return array|string|string[]
     */
    public function getExtension()
    {
        return pathinfo($this->saveName, PATHINFO_EXTENSION);
    }

    /**
     * 检测上传文件后缀
     * @param array|string $ext 允许后缀
     * @return bool
     */
    public function checkExt($ext): bool
    {
        if (is_string($ext)) {
            $ext = explode(',', $ext);
        }
        $extension = strtolower(pathinfo($this->getInfo('name'), PATHINFO_EXTENSION));
        if (!in_array($extension, $ext)) {
            return false;
        }
        return true;
    }

    /**
     * 检测图像文件
     * @return bool
     */
    public function checkImg(): bool
    {
        $extension = strtolower(pathinfo($this->getInfo('name'), PATHINFO_EXTENSION));
        /* 对图像文件进行严格检测 */
        if (in_array($extension, ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'swf']) && !in_array($this->getImageType($this->filename), [1, 2, 3, 4, 6])) {
            return false;
        }
        return true;
    }

    /**
     * 判断图像类型
     * @param $image
     * @return false|int|mixed
     */
    protected function getImageType($image)
    {
        if (function_exists('exif_imagetype')) {
            return exif_imagetype($image);
        } else {
            $info = getimagesize($image);
            return $info[2];
        }
    }

    /**
     * 检测上传文件大小
     * @param integer $size 最大大小
     * @return bool
     */
    public function checkSize($size): bool
    {
        if ($this->getSize() > $size) {
            return false;
        }
        return true;
    }

    /**
     * @return string
     */
    public function getMineType(): string
    {
        return $this->_uploadMimeType;
    }

    /**
     * 检测上传文件类型
     * @param array|string $mime 允许类型
     * @return bool
     */
    public function checkMime($mime): bool
    {
        if (is_string($mime)) {
            $mime = explode(',', $mime);
        }
        if (!in_array(strtolower($this->getMime()), $mime)) {
            return false;
        }
        return true;
    }

    /**
     * @param $destination
     * @return File
     */
    public function move($destination): File
    {
        set_error_handler(function ($type, $msg) use (&$error) {
            $error = $msg;
        });
        $path = pathinfo($destination, PATHINFO_DIRNAME);
        if (!is_dir($path) && !mkdir($path, 0777, true)) {
            restore_error_handler();
            throw new \RuntimeException(sprintf('Unable to create the "%s" directory (%s)', $path, strip_tags($error)), ErrorCode::UNABLE_TO_CREATE_DIRECTORY);
        }
        if (!rename($this->getPathname(), $destination)) {
            restore_error_handler();
            throw new \RuntimeException(sprintf('Could not move the file "%s" to "%s" (%s)', $this->getPathname(), $destination, strip_tags($error)), ErrorCode::COULD_NOT_MOVE_FILE);
        }
        restore_error_handler();
        @chmod($destination, 0666 & ~umask());
        return new self($destination);
    }

    /**
     * 获取错误信息
     * @return string
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     * 获取上传错误码
     * @return int
     */
    public function getUploadErrorCode(): int
    {
        return $this->_uploadErrorCode;
    }
}
