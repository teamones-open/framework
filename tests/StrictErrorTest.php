<?php
namespace FrameworkTests;

class StrictErrorTest
{
    public static function run(): void
    {
        TestRunner::group('strict E_ALL');
        TestRunner::test('registration includes deprecations and raises them', static function () {
            $command=escapeshellarg(PHP_BINARY).' -d error_reporting=-1 '.escapeshellarg(__DIR__.'/fixtures/strict-error.php').' '.escapeshellarg(FRAMEWORK_ROOT);
            exec($command.' 2>&1',$lines,$status);
            TestRunner::assertSame(0,$status,implode("\n",$lines));
            $result=json_decode(implode("\n",$lines),true);
            TestRunner::assertSame(E_ALL,$result['mask']??null);
            TestRunner::assertTrue($result['raised']??false);
        });
        TestRunner::test('integer filter accepts default options without null diagnostics', static function () {
            $validator=new \think\Validate();
            $validator->rule(['id'=>'integer']);
            TestRunner::assertTrue($validator->check(['id'=>42]));
            TestRunner::assertFalse($validator->check(['id'=>'invalid']));
        });
    }
}
