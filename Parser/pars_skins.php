<?php 
/**
 * Автоматический парсинг скинов, 
 * Запуск 1 раз в сутки 
 */
//$start = microtime(true);
use Api\Parser\Parser;

const PARSER_ROOT = __DIR__;
define('ROOT', dirname(__DIR__)); 

include_once ROOT."/autoload.php";



$parser = new Parser();
try {
    $parser->SkinPageParse();
}
catch(Exception $exception) {
    echo $exception->getMessage();
}