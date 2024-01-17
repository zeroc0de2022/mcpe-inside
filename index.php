<?php
/**
 * Description of index
 * @author zeroc0de <98693638+zeroc0de2022@users.noreply.github.com>
 * Date 2021.08.06
 */

use Api\Router\Router;

// FRONT CONTROLLER
// 1. Общие настройки
define('ROOT', dirname(__FILE__));
// 2. Подключение файлов системы
require_once(ROOT."/vendor/autoload.php");
// 3. Вызов Router
$router = new Router();
$router->run();