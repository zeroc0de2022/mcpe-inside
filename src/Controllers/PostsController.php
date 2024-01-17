<?php
/**
 * Description of PostsController
 * @author zeroc0de <98693638+zeroc0de2022@users.noreply.github.com>
 * Date 2021.08.06
 */
namespace Api\Controllers;

use Api\Common\Common;
use Api\Models\Posts;

class PostsController {

    public function actionIndex($params): bool
    {
        if(isset($params['lang'])){
            $query = $params;
            Common::language($query['lang']);
            if(isset($params['category'], $params['sort'])){
                $params['version'] = $params['version'] ?? false;
                $params['order'] = (isset($params['order']) && $params['order'] == "asc") ? "ASC" : "DESC";
                $params['page'] = $params['page'] ?? 1;
                $params['category'] = explode(',', $params['category']);
                Posts::post($params['category'], $params['sort'], $params['version'], $params['page'], $params['order']);
            }
        }
        return true;
    }
}
