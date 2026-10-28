<?php
namespace FrameworkTests;

use think\log\driver\File;

class FileLogConcurrencyTest
{
    private static function root(): string
    {
        $root = sys_get_temp_dir() . '/framework-log-test-' . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        return $root;
    }

    private static function remove(string $root): void
    {
        if (dirname($root) !== rtrim(sys_get_temp_dir(), '/') || !str_starts_with(basename($root), 'framework-log-test-')) {
            throw new \RuntimeException('Unexpected test cleanup path');
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($root);
    }

    private static function contents(string $root): string
    {
        $content = '';
        foreach (glob($root . '/logs/*.log') as $file) {
            $content .= file_get_contents($file);
        }
        return $content;
    }

    public static function run(): void
    {
        TestRunner::group('File logger rotation and concurrency');
        TestRunner::test('single write preserves original message format and custom destination', function () {
            $root = self::root();
            try {
                (new File(['log_time_format' => 'Y']))->write('unique-message', $root . '/logs/custom.log');
                TestRunner::assertSame('[' . date('Y') . "] \r\nunique-message\r\n", file_get_contents($root . '/logs/custom.log'));
            } finally {
                self::remove($root);
            }
        });
        TestRunner::test('repeated rotations in one second retain every record', function () {
            $root = self::root();
            try {
                $logger = new File(['log_path' => $root . '/logs/', 'log_file_size' => 1]);
                for ($i = 0; $i < 20; $i++) {
                    $logger->write('[[rotation:' . $i . ']]');
                }
                $content = self::contents($root);
                for ($i = 0; $i < 20; $i++) {
                    TestRunner::assertSame(1, substr_count($content, '[[rotation:' . $i . ']]'), 'missing or duplicated rotation ' . $i);
                }
            } finally {
                self::remove($root);
            }
        });
        TestRunner::test('six workers create directories rotate and write without warnings or lost records', function () {
            if (!function_exists('pcntl_fork')) {
                throw new \RuntimeException('pcntl is required for the real concurrency test');
            }
            $root = self::root();
            $children = [];
            try {
                for ($worker = 0; $worker < 6; $worker++) {
                    $pid = pcntl_fork();
                    if ($pid === -1) {
                        throw new \RuntimeException('Cannot fork test worker');
                    }
                    if ($pid === 0) {
                        try {
                            file_put_contents($root . '/ready-' . $worker, '1');
                            $deadline = microtime(true) + 10;
                            while (!is_file($root . '/go')) {
                                if (microtime(true) > $deadline) {
                                    throw new \RuntimeException('Barrier timed out');
                                }
                                usleep(1000);
                            }
                            $logger = new File(['log_path' => $root . '/logs/', 'log_file_size' => 128]);
                            for ($i = 0; $i < 30; $i++) {
                                $logger->write('[[worker:' . $worker . ':' . $i . ']] ' . str_repeat('x', 60));
                            }
                            exit(0);
                        } catch (\Throwable $e) {
                            file_put_contents($root . '/error-' . $worker, $e->getMessage());
                            exit(1);
                        }
                    }
                    $children[] = $pid;
                }
                $deadline = microtime(true) + 10;
                while (count(glob($root . '/ready-*')) !== 6) {
                    if (microtime(true) > $deadline) {
                        throw new \RuntimeException('Workers did not become ready');
                    }
                    usleep(1000);
                }
                file_put_contents($root . '/go', '1');
                $failed = [];
                foreach ($children as $pid) {
                    pcntl_waitpid($pid, $status);
                    if (!pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
                        $failed[] = $pid;
                    }
                }
                $children = [];
                $errors = array_map('file_get_contents', glob($root . '/error-*'));
                TestRunner::assertSame([], $failed, implode('; ', $errors));
                $content = self::contents($root);
                for ($worker = 0; $worker < 6; $worker++) {
                    for ($i = 0; $i < 30; $i++) {
                        TestRunner::assertSame(1, substr_count($content, '[[worker:' . $worker . ':' . $i . ']]'), 'missing or duplicated concurrent record');
                    }
                }
            } finally {
                if ($children) {
                    file_put_contents($root . '/go', '1');
                    foreach ($children as $pid) {
                        pcntl_waitpid($pid, $status);
                    }
                }
                self::remove($root);
            }
        });
    }
}
