<?php
namespace FrameworkTests;

final class RequestInputTest
{
    private static function request(): \think\Request
    {
        $request = new \think\Request("POST / HTTP/1.1\r\nHost: localhost\r\nContent-Length: 0\r\n\r\n");
        $request->filter('htmlspecialchars');
        return $request;
    }

    public static function run(): void
    {
        TestRunner::group('Request input type safety');
        TestRunner::test('named scalar reads retain filtering and do not write array markers', static function () {
            $request = self::request();
            TestRunner::assertSame('&lt;title&gt;', $request->input(['title'=>'<title>'], 'title'));
            TestRunner::assertSame(42, $request->input(['id'=>'42'], 'id/d'));
            TestRunner::assertSame(false, $request->input(['flag'=>false], 'flag/b', null, null));
            TestRunner::assertSame('fallback', $request->input(['data'=>[]], 'data.missing', 'fallback'));
        });
        TestRunner::test('null fields are preserved without PHP 8.5 sanitizer deprecations', static function () {
            $request = self::request();
            TestRunner::assertNull($request->input(['name'=>null], 'name'));
            $data = $request->input(['data'=>['name'=>null]]);
            TestRunner::assertTrue(array_key_exists('name', $data['data']));
            TestRunner::assertNull($data['data']['name']);
        });
        foreach ([null, '', 'bad', false, true, 0, 1.5, new \stdClass()] as $i=>$value) {
            TestRunner::test('malformed param wrapper is an explicit JSON parameter error #'.$i, static function () use ($value) {
                try {
                    self::request()->input(['param'=>$value]);
                    TestRunner::assertTrue(false, 'Malformed param wrapper was accepted');
                } catch (\think\exception\HttpResponseException $e) {
                    TestRunner::assertSame(400, $e->getResponse()->getCode());
                    TestRunner::assertSame(\think\exception\ErrorCode::PARAMETER_FORMAT_ERROR, $e->getResponse()->getData()['code']);
                    TestRunner::assertSame([], $e->getResponse()->getData()['data']);
                }
            });
        }
        TestRunner::test('valid filter containers retain operator mapping and scalar filtering', static function () {
            $data = self::request()->input(['param'=>['filter'=>['id'=>['-eq',42]], 'page'=>[1,20]]]);
            TestRunner::assertSame(['EQ','42'], $data['param']['filter']['id']);
            TestRunner::assertSame(['1','20'], $data['param']['page']);
            TestRunner::assertTrue($data['parser_filter']);
            TestRunner::assertTrue(is_array(self::request()->input(['param'=>[]])['param']));
        });
        TestRunner::test('a client supplied filter marker cannot bypass the param type check', static function () {
            try {
                self::request()->input(['parser_filter'=>true, 'param'=>'bad']);
                TestRunner::assertTrue(false, 'Client marker bypassed the type check');
            } catch (\think\exception\HttpResponseException $e) {
                TestRunner::assertSame(400, $e->getResponse()->getCode());
                TestRunner::assertSame(\think\exception\ErrorCode::PARAMETER_FORMAT_ERROR, $e->getResponse()->getData()['code']);
            }
        });
        TestRunner::test('filter operator mapping ignores non-string leaves without coercion', static function () {
            $request = self::request();
            foreach ([null, false, 0, 1.5, [], new \stdClass()] as $value) {
                $before = $value;
                $request->parserFilterCondition($value, 'value');
                TestRunner::assertSame($before, $value);
            }
            $value = '-in';
            $request->parserFilterCondition($value, 0);
            TestRunner::assertSame('IN', $value);
        });
        TestRunner::test('raw mode and direct scalar filter calls preserve their original values', static function () {
            $request = self::request();
            TestRunner::assertSame(['param'=>null], $request->input(['param'=>null], false));
            TestRunner::assertSame('scalar', $request->parserFilter('scalar'));
            TestRunner::assertSame(E_ALL, error_reporting());
        });
    }
}
