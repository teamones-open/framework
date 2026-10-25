<?php
namespace think {
    // Logging is irrelevant to this isolated error-handler contract.
    class Log { public static function save() {} }
}
namespace {
    $root=$argv[1];
    spl_autoload_register(static function($class)use($root){if(str_starts_with($class,'think\\'))require $root.'/src/'.str_replace('\\','/',$class).'.php';});
    error_reporting(0);
    \think\Error::register();
    $mask=error_reporting();$raised=false;
    try {trigger_error('expected deprecation probe',E_USER_DEPRECATED);}
    catch(\think\exception\ErrorException $e){$raised=$e->getSeverity()===E_USER_DEPRECATED;}
    echo json_encode(compact('mask','raised')),"\n";
    exit($mask===E_ALL&&$raised?0:1);
}
