<?php
/**
 * Description of VersionsController
 * @author zeroc0de <98693638+zeroc0de2022@users.noreply.github.com>
 * Date 2021.08.06
 */

namespace Api\Controllers;


use Api\Common\Helper;
use Api\Models\Versions;

class VersionsController {
    
    
    public function actionIndex($params): bool
    {
        if(isset($params['dots'])){
            if(is_numeric($params['dots'])){
                $versions = new Versions();
                $versions->all_versions($params['dots']);                
            }
            else {
                Helper::printPre(json_encode([
                    "error" => true,
                    "message" => "Параметр dots должен быть цифрой"],
                    JSON_UNESCAPED_UNICODE));
            }
        }
        else {
             Helper::printPre(json_encode([
                "error" => true,
                "message" => "Обязательно наличие параметра dots"],
                JSON_UNESCAPED_UNICODE));
        }
        return true;
    }
    
}
