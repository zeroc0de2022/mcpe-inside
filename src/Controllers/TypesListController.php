<?php
/**
 * Description of TypesListController
 * @author zeroc0de <98693638+zeroc0de2022@users.noreply.github.com>
 * Date 2021.08.06
 */

namespace Api\Controllers;


use Api\Common\Common;
use Api\Common\Helper;
use Api\Models\TypesList;

class TypesListController {

    public function actionIndex($params):bool
    {
        if(isset($params['lang'])){
            Common::language($params['lang']);
            $types = [];
            if(isset($params['types']) && strlen($params['types']) > 0){
                $types['types'] = explode(',', $params['types']);
                if(isset($params['subtypes']) && is_array($params['subtypes'])){
                    $params['subtypes'] = array_diff($params['subtypes'], ['', null]);
                    if(count($params['subtypes']) > 0){
                        $types['subtypes'] = [];
                        for($i = 0; $i < count($params['subtypes']); $i++ ){
                            $subtypes = explode(',', $params['subtypes'][$i]);
                            $types['subtypes'] = array_merge($types['subtypes'], $subtypes);
                            $types['subtypes'] = array_values(array_unique($types['subtypes']));
                        }
                    }
                }
            }
            TypesList::$types = $types;
            TypesList::categories();
            Helper::printPre(json_encode(
                TypesList::$return,
                JSON_UNESCAPED_UNICODE));
        }
        else {
            Helper::printPre(json_encode([
                "error" => true,
                "message" => "Обязательно наличие параметра языка"],
                JSON_UNESCAPED_UNICODE));
        }
        return true;
    }
    
    
    
    
    
    
    
    
}
