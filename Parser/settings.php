<?php
/*
Author: zeroc0de <98693638+zeroc0de2022@users.noreply.github.com>
*/
declare(strict_types = 1);

use Api\Common\GoogleTranslateForFree;
use Api\Parser\Parser;


// 1 - использовать прокси, 0 - не использовать прокси
GoogleTranslateForFree::$useProxy = 0;

// адрес файла с прокси
Parser::$proxyFile = 'Parser/proxy.txt';
