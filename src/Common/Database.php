<?php
/**
 * Author: zeroc0de <98693638+zeroc0de2022@users.noreply.github.com>
 * Date: 2021.08.06
 */

namespace Api\Common;

use PDO;


class Database
{
    private static bool $connection = false;
    private static PDO $database;
    
    public static function getConnection(): PDO
    {
        if(!Database::$connection){
            $db_params = require_once ROOT.'/config/db_params.php';
            $dsn = "mysql:host={$db_params['hostname']};dbname={$db_params['dbname']}";
            Database::$database = new PDO(
                $dsn,
                $db_params['username'],
                $db_params['password'],
                [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            Database::$connection = true;
        }
        return Database::$database;
    }
    
    public static function db_error($line): void
    {
        if(Database::$database->errorInfo()){
            printf($line.": Сообщение ошибки: %s<br>\n",  self::$database->errorInfo());
            die();
        }
    }





}