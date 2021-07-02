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

namespace think\exception;

class StrackException extends \RuntimeException
{
    private int $errorCode;
    private array $headers;
    private array $responseData;
    private string $responseType;

    /**
     * StrackException constructor.
     * StrackException constructor.
     * @param int $errorCode
     * @param null $message
     * @param array $data
     * @param string $type
     * @param \Exception|null $previous
     * @param array $headers
     */
    public function __construct($errorCode = 404, $message = null, $data = [], $type = 'json', \Exception $previous = null, array $headers = [])
    {
        $this->errorCode = $errorCode;
        $this->headers = $headers;
        $this->responseType = $type;

        $this->responseData = [
            "status" => $errorCode,
            "message" => $message,
            "data" => $data
        ];

        parent::__construct($message, $errorCode, $previous);
    }

    /**
     * @return string
     */
    public function getResponseType(): string
    {
        return $this->responseType;
    }

    /**
     * @return int
     */
    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return array
     */
    public function getResponseData(): array
    {
        return $this->responseData;
    }
}
