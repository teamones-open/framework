<?php
declare(strict_types=1);
namespace FrameworkTests;

final class QueryCompatibilityTest
{
    private static function parse(string $method, mixed $input, bool $legacy = false): string
    {
        $class = $legacy ? LegacyQueryParsing::class : \think\db\driver\Mysql::class;
        $driver = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        return (new \ReflectionMethod($class, $method))->invoke($driver, $input);
    }

    public static function run(): void
    {
        TestRunner::group('Legacy WHERE/ORDER compatibility / PHP 8.5 E_ALL');
        TestRunner::test('JSON expression fields generate historical SQL', static function (): void {
            foreach ([
                ["task.json->>'$.task_status'" => ['IN', [1, 2]]],
                ['message.content->>"$.message_key"' => 'message_review'],
                ["JSON_UNQUOTE(JSON_EXTRACT(task.json, '$.task_work_order_id'))" => 155676],
                ["JSON_UNQUOTE(JSON_EXTRACT(entity.json, CONCAT('$.',module.code,'_design')))" => '470110'],
                ['`task`.`id`' => 1],
            ] as $where) {
                TestRunner::assertSame(self::parse('parseWhere', $where, true), self::parse('parseWhere', $where));
            }
        });
        TestRunner::test('ordinary, compound and nested filters retain historical SQL', static function (): void {
            foreach ([['id' => 0], ['name' => "O'Reilly"], ['name|code' => ['like', '%sample%']],
                ['id&parent_id' => 1], ['_complex' => ["task.json->>'$.task_status'" => 2], 'tenant_id' => 3],
                ['_string' => 'id > 0', 'project_id' => 259],
                ['id' => 1, 'parent_id' => 2, '_logic' => 'OR']] as $where) {
                TestRunner::assertSame(self::parse('parseWhere', $where, true), self::parse('parseWhere', $where));
            }
        });
        TestRunner::test('string sorting matches old parsing, including historical function handling', static function (): void {
            foreach (['', 'id', 'id DESC', 'task.id ASC, code DESC', '`task`.`id` DESC',
                "task.json->>'$.task_status' DESC", 'LENGTH(code) ASC, code ASC',
                "FIELD(id,3,2,1) DESC", 'LENGTH(code)', '[RAND]'] as $order) {
                TestRunner::assertSame(self::parse('parseOrder', $order, true), self::parse('parseOrder', $order));
            }
        });
        TestRunner::test('array sorting matches old parsing', static function (): void {
            foreach ([[], ['id' => 'desc'], ['task.id' => 'asc'], ['id', 'code DESC'],
                ['LENGTH(code) ASC', 'code ASC'], ["FIELD(id,3,2,1) DESC"],
                ["JSON_UNQUOTE(JSON_EXTRACT(json,'$.rank')) DESC"]] as $order) {
                TestRunner::assertSame(self::parse('parseOrder', $order, true), self::parse('parseOrder', $order));
            }
        });
        TestRunner::test('new helper APIs remain present and chainable', static function (): void {
            $driver = (new \ReflectionClass(\think\db\driver\Mysql::class))->newInstanceWithoutConstructor();
            $model = (new \ReflectionClass(\think\Model::class))->newInstanceWithoutConstructor();
            (new \ReflectionProperty(\think\Model::class, 'db'))->setValue($model, $driver);
            TestRunner::assertSame($model, $model->whereExp("JSON_UNQUOTE(JSON_EXTRACT(json, '$.kind')) = '%s'", ["O'Reilly"]));
            $options = (new \ReflectionProperty(\think\Model::class, 'options'))->getValue($model);
            TestRunner::assertTrue(str_contains($options['where']['_string'], "O\\'Reilly"));
            TestRunner::assertSame($model, $model->whereJson('task.json', '$.status', 'IN', [1, 2]));
            TestRunner::assertSame($model, $model->orderRaw('LENGTH(code) DESC'));
            foreach (['parseOrderItem', 'parseOrderField'] as $name) {
                TestRunner::assertTrue(method_exists($driver, $name));
            }
            $raw = (new \ReflectionMethod($driver, 'parseOrder'))->invoke($driver, 'LENGTH(code) DESC', true);
            TestRunner::assertSame(' ORDER BY LENGTH(code) DESC', $raw);
        });
    }
}
