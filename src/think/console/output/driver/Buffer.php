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

namespace think\console\output\driver;

use think\console\Output;

class Buffer
{
    /**
     * @var string
     */
    private string $buffer = '';

    public function __construct(Output $output)
    {
        // do nothing
    }

    public function fetch(): string
    {
        $content = $this->buffer;
        $this->buffer = '';
        return $content;
    }

    public function write($messages, $newline = false, $options = Output::OUTPUT_NORMAL)
    {
        $messages = (array)$messages;

        foreach ($messages as $message) {
            $this->buffer .= $message;
        }
        if ($newline) {
            $this->buffer .= "\n";
        }
    }

    public function renderException(\Exception $e)
    {
        // do nothing
    }

}
