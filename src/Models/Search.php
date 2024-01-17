<?php
/**
 * Description of Search
 * @author zeroc0de <98693638+zeroc0de2022@users.noreply.github.com>
 * Date 2021.08.06
 */

namespace Api\Models;


class Search {
    
    public static function searching($category, $search, $title = "on", $description = "on", $version = false, $sort = "date", $page = 1, $orderBy = "ASC"): void
    {
        Posts::includeDescTitleSearch($title, $description, urldecode($search));
        Posts::post($category, $sort, $version, $page, $orderBy);
    }
    
     
    
    
}
