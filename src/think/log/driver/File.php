<?php
// +----------------------------------------------------------------------
// | TOPThink [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011 http://topthink.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\log\driver;

class File
{

    protected $config = [
        'log_time_format' => ' c ',
        'log_file_size' => 2097152,
        'log_path' => '',
    ];

    // 实例化并传入参数
    public function __construct($config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 日志写入接口
     * @access public
     * @param string $log 日志信息
     * @param string $destination 写入目标
     * @return void
     */
    public function write($log, $destination = '')
    {
        $now = date($this->config['log_time_format']);
        if (empty($destination)) {
            $destination = $this->config['log_path'] . date('y_m_d') . '.log';
        }
        $logDir = dirname($destination);
        // A stable directory lock also covers first-use mkdir and survives rotation.
        $lockPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'teamones-log-' . sha1($logDir) . '.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            throw new \RuntimeException('Cannot open log directory lock: ' . $logDir);
        }
        $locked = false;
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new \RuntimeException('Cannot lock log directory: ' . $logDir);
            }
            $locked = true;
            if (!is_dir($logDir) && !mkdir($logDir, 0755, true)) {
                throw new \RuntimeException('Cannot create log directory: ' . $logDir);
            }
            clearstatcache(true, $destination);
            if (is_file($destination) && floor($this->config['log_file_size']) <= filesize($destination)) {
                // Several workers can exceed the size limit within the same second.
                $backup = $logDir . DIRECTORY_SEPARATOR . time() . '-' . bin2hex(random_bytes(6)) . '-' . basename($destination);
                if (!rename($destination, $backup)) {
                    throw new \RuntimeException('Cannot rotate log file: ' . $destination);
                }
            }
            if (!error_log("[{$now}] " . "\r\n{$log}\r\n", 3, $destination)) {
                throw new \RuntimeException('Cannot write log file: ' . $destination);
            }
        } finally {
            if ($locked) {
                flock($lock, LOCK_UN);
            }
            fclose($lock);
        }
    }
}
