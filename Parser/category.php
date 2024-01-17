<?php 
/**
 * Автоматический парсинг с главной страницы, запуск 1 раз в 12 часов.
 */
//$start = microtime(true);
use Api\Common\Helper;
use Api\Parser\Parser;
use Api\Common\GoogleTranslateForFree;

const PARSER_ROOT = __DIR__;
define('ROOT', dirname(__DIR__)); 

include_once ROOT."/autoload.php";

try {
    $parser = new Parser();
    $parser->mainPageParse();
}
catch(Exception $exception)  {
    Helper::printPre($exception->getMessage());
}

//$memory = memory_get_usage();
//echo "<br>".$parser->FileSizeConvert($memory);
//echo '<br>Время выполнения скрипта: '.round(microtime(true) - $start, 4).' сек.';
