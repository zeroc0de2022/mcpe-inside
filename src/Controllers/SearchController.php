<?php
/**
 * Description of SearchController
 * @author zeroc0de <98693638+zeroc0de2022@users.noreply.github.com>
 * Date 2021.08.06
 */

namespace Api\Controllers;

use Api\Common\Common;
use Api\Common\Helper;
use Api\Models\Search;

class SearchController {
    
    public function actionIndex($params): bool
    {
        if(isset($params['lang'])){
            $query = $params;
            Common::language($query['lang']);
            # Проверяем наличие обязательных параметров
            if(isset($params['category'], $params['sort'], $params['search-phrase'], $params['title'], $params['description'])){
                # Проверяем включением в поиск одного из обязательных параметров поиска title - description
                if($params['title'] != "on" && $params['description'] != "on"){
                    Helper::printPre(json_encode([
                        "error" => true,
                        "message" => "Необходимо указать обязательные параметры запроса"],
                        JSON_UNESCAPED_UNICODE));
                }
                else {
                    $params['version'] = $params['version'] ?? false;
                    $params['page'] = $params['page'] ?? 1;
                    $params['category'] = explode(',', $params['category']);
                    $params['order'] = (isset($params['order']) && $params['order'] == "desc") ? "DESC" : "ASC";
                    Search::searching($params['category'], $params['search-phrase'], $params['title'], $params['description'], $params['version'], $params['sort'], $params['page'], $params['order']);
                }
            }
            else {
                Helper::printPre(json_encode([
                    "error" => true,
                    "message" => "Отсутствуют обязательные параметры запроса"],
                    JSON_UNESCAPED_UNICODE));
            }
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
