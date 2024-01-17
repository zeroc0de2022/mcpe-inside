<?php 
header('Content-Type: text/html; charset=utf-8');
set_time_limit(0);
/*
ini_set('error_reporting', 2047);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

ini_set('error_reporting', 214748364);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

ini_set('memory_limit', '2048M');
ini_set('ignore_repeated_errors', TRUE); // always use TRUE
ini_set('log_errors', TRUE); // Error/Exception file logging engine.
ini_set('error_log', __DIR__ . '/php-errors.log');
//date_default_timezone_set("Europe/Moscow");
*/
// Автозагрузка классов
spl_autoload_register(function ($class_name) {
    $array_paths = array(
        '/Parser/Classes/phpQuery/',
        '/Parser/Classes/',
        '/Router/',
        '/Controllers/',
        '/Models/',
        '/Common/'
    );
    foreach ($array_paths as $path){
        $path = ROOT.$path.$class_name.".php";
        if(is_file($path)){
            include_once $path;
        }
    }
});