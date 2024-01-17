<?php 
/**
 * Автоматический парсинг модов, запуск 1 раз в минуту.
 */
//$start = microtime(true);
use Api\Parser\Parser;

const PARSER_ROOT = __DIR__;
define('ROOT', dirname(__DIR__)); 

include_once ROOT."/autoload.php";

$parser = new Parser();
$parser->Router();
//$parser->addProxy();
        
        
