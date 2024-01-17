<?php 
/**
 * Автоматический парсинг с главной страницы, запуск 1 раз в 12 часов.
 */
//$start = microtime(true);
use Api\Parser\Parser;

const PARSER_ROOT = __DIR__;
define('ROOT', dirname(__DIR__)); 

include_once ROOT."/autoload.php";

$parser = new Parser();
try {
    $parser->mainPageParse();
}
catch(Exception $exception)  {
    print_r($exception->getMessage());
}

//$memory = memory_get_usage();
//echo "<br>".$parser->FileSizeConvert($memory);
//echo '<br>Время выполнения скрипта: '.round(microtime(true) - $start, 4).' сек.';
