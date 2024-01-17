<?php
/**
 * Description of Update
 * @author zeroc0de <98693638+zeroc0de2022@users.noreply.github.com>
 * Date 2021.08.11
 */

namespace Api\Models;

use Api\Common\Common;
use Api\Common\Database;
use PDOException;

class Update {
    
    public static function updating($action, $id, $params): bool
    {

        $newNum = 0;
        $stmt = Database::getConnection()->prepare("SELECT * FROM `$params[0]` WHERE `$params[1]`=:param LIMIT 1");
        $stmt->execute([
            ':param', $params[1]
        ]);
        if($stmt->rowCount()){
            $row = $stmt->fetch();
            switch($action){
                case "likes": {
                    $newNum = ++$row['likes'];
                    $set = "likes";
                } break;
                case "views": {
                    $newNum = ++$row['views'];
                    $set = "views";

                } break;
                case "downloads": {
                    $newNum = ++$row['downloads'];
                    $set = "downloads";
                } break;
                case "dislike": {
                    $newNum = --$row['likes'];
                    $set = "likes";
                } break;
            }
            if(isset($set)){
                $stmt = Database::getConnection()->prepare("UPDATE $params[0] SET $set=:newNum WHERE $params[1]=:id");
                try{
                    $stmt->execute([
                        ':newNum' => $newNum,
                        ':id' => $id
                    ]);
                }
                catch(PDOException $exception){
                    Common::$return['error'] = true;
                    Common::$return['message'] = __LINE__.": ".$exception->getMessage();
                    return false;
                }
                if($stmt->rowCount()){
                    Common::$return['error'] = false;
                    Common::$return['message'] = __LINE__.": Запись обновлена";
                }
                else {
                    Common::$return['error'] = true;
                    Common::$return['message'] = __LINE__.": Запись не обновлена";
                }
                Database::db_error(__LINE__);
                return true;
            }
        }
        Common::$return['error'] = true;
        Common::$return['message'] = __LINE__.": По запросу ничего не найдено";
        return false;
    }
}
