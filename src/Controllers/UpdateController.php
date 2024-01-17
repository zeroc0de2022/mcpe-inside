<?php
/**
 * Description of Update
 * @author zeroc0de <98693638+zeroc0de2022@users.noreply.github.com>
 * Date 2021.08.11
 */

namespace Api\Controllers;
use Api\Common\Common;
use Api\Common\Helper;
use Api\Models\Update;

class UpdateController {
    public function actionIndex($params):bool
    {
        Common::$return['error'] = true;
        if(isset($params['action'])){
            switch($params['action']){
                case "likes": 
                case "dislikes": 
                case "views": {
                    if(isset($params['item_id']) && is_numeric($params['item_id'])){
                        Update::updating($params['action'], $params['item_id'], ['items', 'item_id']);
                        Common::$return['error'] = false;
                    }
                    else {
                        Common::$return['message'] = __LINE__.": Некорректный параметр item_id";
                    }
                }break;
                case "downloads": {
                    if(isset($params['file_id']) && is_numeric($params['file_id'])){
                        Update::updating($params['action'], $params['file_id'], ['files', 'file_id']);
                    }
                    else {
                        Common::$return['message'] = __LINE__.": Некорректный параметр file_id";
                    }
                }break;
                default:{
                    Common::$return['message'] = __LINE__.": Некорректный параметр action";
                } break;
            }
        }
        else {
            Common::$return['message'] = __LINE__.": Отсутствует параметр action";
        }
        Helper::printPre(json_encode(Common::$return, JSON_UNESCAPED_UNICODE));
        return true;
    }
}
