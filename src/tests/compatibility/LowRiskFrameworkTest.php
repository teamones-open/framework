<?php
/**
 * Isolated compatibility regression runner (PHP >= 8.1).
 * Usage: php LowRiskFrameworkTest.php <framework-root> <service-vendor> [result.json]
 * Does not bootstrap applications or connect to external databases/queues.
 */
declare(strict_types=1);

$framework = rtrim($argv[1] ?? dirname(__DIR__, 3), '/');
$vendor = rtrim($argv[2] ?? '', '/');
spl_autoload_register(function ($class) use ($framework, $vendor) {
    foreach (['think\\' => $framework . '/src/think/', 'Workerman\\' => $vendor . '/workerman/workerman/'] as $prefix => $dir) {
        if (strncmp($class, $prefix, strlen($prefix)) === 0) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) { require_once $file; }
            return;
        }
    }
});
function C($key = null, $value = null, $default = null) {
    static $data = [];
    if ($value !== null) { $data[$key] = $value; return; }
    return $data[$key] ?? $default;
}
function trace(...$args) {}
function G(...$args) {}
function L($key) { return $key; }
function StrackE($message) { throw new RuntimeException($message); }

class MemoryLink {
    public $begins = 0;
    public $commits = 0;
    public $rollbacks = 0;
    public $beginError;
    public $beginResult = true;
    public $finishError;
    public $finishResult = true;
    public function beginTransaction() {
        ++$this->begins;
        if ($this->beginError) { throw $this->beginError; }
        return $this->beginResult;
    }
    public function commit() {
        ++$this->commits;
        if ($this->finishError) { throw $this->finishError; }
        return $this->finishResult;
    }
    public function rollback() {
        ++$this->rollbacks;
        if ($this->finishError) { throw $this->finishError; }
        return $this->finishResult;
    }
}
class SafeDriver extends think\db\driver\Mysql {
    public $initError;
    public $errorCalls = 0;
    public function __destruct() {}
    public function link($link, $depth = 0) { $this->_linkID = $link; $this->transTimes = $depth; }
    public function depth() { return $this->transTimes; }
    public function error() { ++$this->errorCalls; return 'fake finish error'; }
    protected function initConnect($master = true) {
        if ($this->initError) { throw $this->initError; }
    }
}
class ReconnectDriver extends SafeDriver {
    public $closes = 0;
    public function close() {
        ++$this->closes;
        $this->_linkID->beginError = null;
        return parent::close();
    }
}
class SharedModel extends think\Model {
    public function __construct($driver) { $this->db = $driver; }
}
function requestFor($path = '/same', $host = 'review.invalid', $method = 'GET', $headers = '') {
    return new think\Request("$method $path HTTP/1.1\r\nHost: $host\r\n$headers\r\n");
}
$assertions = 0;
$results = [];
function same($expected, $actual) {
    global $assertions;
    ++$assertions;
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
function testCase($name, $fn) {
    global $results;
    try { $fn(); $results[] = ['test' => $name, 'status' => 'PASS']; }
    catch (Throwable $e) { $results[] = ['test' => $name, 'status' => 'FAIL', 'error' => get_class($e) . ': ' . $e->getMessage()]; }
}

testCase('idle rollback and commit are idempotent', function () {
    $d = new SafeDriver; $link = new MemoryLink; $d->link($link);
    same(true, $d->rollback()); same(0, $d->depth());
    same(true, $d->commit()); same(0, $d->depth());
    same(true, $d->rollback()); same(0, $d->depth());
    same(0, $link->commits); same(0, $link->rollbacks);
    same(null, $d->startTrans()); same(1, $d->depth()); same(1, $link->begins);
});
testCase('legacy negative depth is recovered on cleanup', function () {
    foreach (['commit', 'rollback'] as $method) {
        $d = new SafeDriver; $d->link(new MemoryLink, -2);
        same(true, $d->$method()); same(0, $d->depth());
    }
});
testCase('successful transaction retains legacy return values', function () {
    $d = new SafeDriver; $link = new MemoryLink; $d->link($link);
    same(null, $d->startTrans()); same(1, $d->depth());
    same(true, $d->commit()); same(0, $d->depth()); same(1, $link->commits);
    same(null, $d->startTrans()); same(true, $d->rollback()); same(1, $link->rollbacks);
});
testCase('nested transactions keep counter-only legacy semantics', function () {
    $d = new SafeDriver; $link = new MemoryLink; $d->link($link);
    $d->startTrans(); $d->startTrans(); same(2, $d->depth()); same(1, $link->begins);
    same(true, $d->rollback()); same(1, $d->depth()); same(0, $link->rollbacks);
    same(true, $d->commit()); same(0, $d->depth()); same(1, $link->commits);
});
testCase('finish false keeps existing false return and zero depth', function () {
    foreach (['commit', 'rollback'] as $method) {
        $d = new SafeDriver; $link = new MemoryLink; $d->link($link, 1); $link->finishResult = false;
        same(false, $d->$method()); same(0, $d->depth()); same(1, $d->errorCalls);
    }
});
testCase('finish exception remains visible and retains existing active depth', function () {
    foreach (['commit', 'rollback'] as $method) {
        $d = new SafeDriver; $link = new MemoryLink; $d->link($link, 1);
        $error = new RuntimeException('finish failed'); $link->finishError = $error;
        try { $d->$method(); throw new LogicException('Expected original exception'); }
        catch (Throwable $caught) { same($error, $caught); }
        same(1, $d->depth());
    }
});
testCase('shared driver may be started and committed by different models', function () {
    $d = new SafeDriver; $link = new MemoryLink; $d->link($link);
    $a = new SharedModel($d); $b = new SharedModel($d);
    same(null, $a->startTrans()); same(1, $d->depth());
    same(true, $b->commit()); same(0, $d->depth()); same(1, $link->commits);
    $a->startTrans(); same(true, $b->rollback()); same(1, $link->rollbacks);
});
testCase('missing connection restores depth and false result', function () {
    $d = new SafeDriver; $d->link(null);
    same(false, $d->startTrans()); same(0, $d->depth());
    $d->link(null, 1); same(false, $d->startTrans()); same(1, $d->depth());
});
testCase('connection initialization exception restores depth', function () {
    foreach ([new RuntimeException('connect failed'), new TypeError('bad connection setup')] as $error) {
        $d = new SafeDriver; $d->link(null); $d->initError = $error;
        try { $d->startTrans(); throw new LogicException('Expected original exception'); }
        catch (Throwable $caught) { same($error, $caught); }
        same(0, $d->depth());
    }
});
testCase('begin exception restores depth and can be retried by caller', function () {
    $d = new SafeDriver; $link = new MemoryLink; $d->link($link);
    $error = new RuntimeException('begin failed'); $link->beginError = $error;
    try { $d->startTrans(); throw new LogicException('Expected original exception'); }
    catch (Throwable $caught) { same($error, $caught); }
    same(0, $d->depth()); $link->beginError = null;
    same(null, $d->startTrans()); same(1, $d->depth()); same(2, $link->begins);
});
testCase('begin TypeError restores depth', function () {
    $d = new SafeDriver; $link = new MemoryLink; $d->link($link);
    $error = new TypeError('begin type error'); $link->beginError = $error;
    try { $d->startTrans(); throw new LogicException('Expected original error'); }
    catch (Throwable $caught) { same($error, $caught); }
    same(0, $d->depth());
});
testCase('begin returning false restores depth and allows next transaction', function () {
    $d = new SafeDriver; $link = new MemoryLink; $d->link($link); $link->beginResult = false;
    same(false, $d->startTrans()); same(0, $d->depth());
    $link->beginResult = true; same(null, $d->startTrans()); same(1, $d->depth()); same(2, $link->begins);
});
testCase('existing begin disconnect retry branch is preserved', function () {
    $d = new ReconnectDriver; $link = new MemoryLink; $d->link($link);
    $link->beginError = new RuntimeException('server has gone away');
    same(null, $d->startTrans()); same(1, $d->closes); same(2, $link->begins); same(1, $d->depth());
});
testCase('nested initialization failure restores existing outer depth', function () {
    $d = new SafeDriver; $d->link(new MemoryLink, 1);
    $error = new RuntimeException('nested setup failed'); $d->initError = $error;
    try { $d->startTrans(); throw new LogicException('Expected exception'); }
    catch (Throwable $caught) { same($error, $caught); }
    same(1, $d->depth());
});
testCase('route keys vary with path host and HTTP method', function () {
    $method = new ReflectionMethod(think\Route::class, 'getCheckCacheKey');
    $method->setAccessible(true);
    same('review.invalid|GET|one', $method->invoke(null, requestFor('/one')));
    same('review.invalid|GET|two', $method->invoke(null, requestFor('/two')));
    same('other.invalid|GET|one', $method->invoke(null, requestFor('/one', 'other.invalid')));
    same('review.invalid|POST|one', $method->invoke(null, requestFor('/one', 'review.invalid', 'POST')));
});
testCase('custom route key is calculated per request', function () {
    $calls = 0;
    C('ROUTE_CHECK_CACHE_KEY', function ($request) use (&$calls) { ++$calls; return 'custom:' . $request->path(); });
    $method = new ReflectionMethod(think\Route::class, 'getCheckCacheKey'); $method->setAccessible(true);
    same('custom:one', $method->invoke(null, requestFor('/one')));
    same('custom:two', $method->invoke(null, requestFor('/two'))); same(2, $calls);
    C('ROUTE_CHECK_CACHE_KEY', false);
});
testCase('Accept detection keeps existing matching policy without deprecations', function () {
    $before = error_reporting(E_ALL);
    set_error_handler(function ($level, $message) { throw new RuntimeException($message); });
    try {
        same(false, requestFor()->acceptJson()); same(false, requestFor()->expectsJson());
        same(false, requestFor('/same', 'review.invalid', 'GET', "Accept: \r\n")->acceptJson());
        same(true, requestFor('/same', 'review.invalid', 'GET', "Accept: application/json\r\n")->acceptJson());
        same(true, requestFor('/same', 'review.invalid', 'GET', "Accept: application/problem+json\r\n")->acceptJson());
        same(false, requestFor('/same', 'review.invalid', 'GET', "Accept: text/html\r\n")->acceptJson());
        same(false, requestFor('/same', 'review.invalid', 'GET', "Accept: application/JSON\r\n")->acceptJson());
        same(true, requestFor('/same', 'review.invalid', 'GET', "X-Requested-With: XMLHttpRequest\r\n")->expectsJson());
    } finally { restore_error_handler(); error_reporting($before); }
});
testCase('SQLite memory transaction preserves cross-model atomicity', function () {
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) { throw new RuntimeException('pdo_sqlite required'); }
    $pdo = new PDO('sqlite::memory:'); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE sample (id INTEGER PRIMARY KEY, value TEXT)');
    $d = new SafeDriver; $d->link($pdo); $a = new SharedModel($d); $b = new SharedModel($d);
    $a->startTrans(); $pdo->exec("INSERT INTO sample VALUES (1, 'committed')");
    same(true, $b->commit()); same(1, (int)$pdo->query('SELECT COUNT(*) FROM sample')->fetchColumn());
    $a->startTrans(); $pdo->exec("INSERT INTO sample VALUES (2, 'rolled-back')");
    same(true, $b->rollback()); same(1, (int)$pdo->query('SELECT COUNT(*) FROM sample')->fetchColumn());
    same(true, $b->rollback()); $a->startTrans(); same(true, $pdo->inTransaction()); $b->rollback();
});
$failed = count(array_filter($results, function ($r) { return $r['status'] === 'FAIL'; }));
$report = ['php' => PHP_VERSION, 'vendor' => $vendor, 'tests' => count($results), 'assertions' => $assertions,
    'failures' => $failed, 'results' => $results];
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
if (isset($argv[3])) { file_put_contents($argv[3], $json); }
echo $json;
exit($failed ? 1 : 0);
