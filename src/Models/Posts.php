<?php
/**
 * Description of Posts
 * @author zeroc0de <98693638+zeroc0de2022@users.noreply.github.com>
 * Date 2021.08.06
 */

namespace Api\Models;

use Api\Common\Common;
use Api\Common\Database;
use Api\Common\Helper;

class Posts {
    
    public static array $return = [];
    public static array $queryA = [];
    public static array $prepare = [];

    public static function post($category, $sort, $version = false, $page = 1, $orderBy = "DESC"): void
    {
        $versionQuery = "";
        $language_id = Common::$language_id;
        $sortQuery = (in_array($sort,['title', 'item_id', 'author', 'date', 'views', 'likes', 'versions', 'downloads']))
            ? $sort
            : "date";
        if($version){
            $versionQuery = " AND `items`.`versions` LIKE '%$version%' ";
            Posts::$prepare[":versions"] = $version;
        }
        
        $page  = intval($page);
        $offset = ($page - 1) * Common::SHOW_RESULTS_BY_DEFAULT;

        $categories =  [];
        for($i = 0; $i < count($category); $i++ ){
            $categories[] = "category_description.post = :".$category[$i];
            Posts::$prepare[":".$category[$i]] = $category[$i];
        }
        $category_post = implode(' OR ', $categories);
        
        self::$queryA['primary'] = "SELECT * FROM `category_description`, `item_to_category`, `items`, `items_description`";
        self::$queryA['primary'] .= " WHERE ($category_post)"; // в категориях
        self::$queryA['primary'] .= " AND category_description.language_id = '$language_id'"; //язык категории
        self::$queryA['primary'] .= " AND items_description.language_id` = '$language_id'"; // Язык описания
        self::$queryA['primary'] .= " AND item_to_category.item_id = items.item_id" ;// связь категории с модом
        self::$queryA['primary'] .= " AND items.item_id = items_description.item_id"; // связь мода с описанием
        self::$queryA['primary'] .= " AND item_to_category.category_id = category_description.category_id";// связь категории с описанием
        self::$queryA['primary'] .= " $versionQuery";// Версии;
        self::$queryA['final'] .= " ORDER BY items.$sortQuery $orderBy";
        self::$queryA['final'] .= " LIMIT ".Common::SHOW_RESULTS_BY_DEFAULT;
        self::$queryA['final'] .= " OFFSET $offset";
        self::$queryA['additional'] = self::$queryA['additional'] ?? "";
        
        
        $query = self::$queryA['primary'].self::$queryA['additional'].self::$queryA['final'];
        //Helper::printPre($query, true);
        $stmt = Database::getConnection()->prepare($query);
        $stmt->execute(self::$prepare);
        
        
        if(!$stmt->RowCount()){
            Common::$return['error'] = true;
            Common::$return['message'] = __LINE__.": По запросу ничего не найдено";
        }
        else {
            while($row = $stmt->fetchAll()){
                switch($row['post']){
                    case "seeds":{
                        self::seeds($row);
                    }break;
                    case "skins":{
                        self::skins($row);
                    }break;
                    default: {
                        self::mods($row);
                    }
                }
            }
        }
        Helper::printPre(json_encode(Common::$return, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Поиск по названию и описанию
     * @param string $search
     * @param string $title
     * @param string $description
     * @return void
     */
    public static function includeDescTitleSearch(string $title, string $description, string $search): void
    {
        $titleDescriptionQuery = [];
        if($title == "on") {
            $titleDescriptionQuery[] = " items_description.title LIKE '%:title_search%' ";
            Posts::$prepare[":title_search"] = $search;
        }
        if($description == "on"){
            $titleDescriptionQuery[] = " items_description.description LIKE '%$search%' ";
            Posts::$prepare[":description_search"] = $search;
        }
        $TD = implode(" OR ", $titleDescriptionQuery);
        Posts::$queryA['additional'] = " AND ($TD)"; // Поиск в названии и в описании
    }

    /**
     * Сбор скинов
     * @param array $row
     * @return void
     */
    private static function skins(array $row): void
    {
        Common::$return[$row['post']][] = [
            'id' => $row['item_id'],
            'title' => $row['title'],
            'files' => self::files($row['item_id']),
            'date' => date("d-m-Y", $row['date']),
            'views' => $row['views'],
            'likes' => $row['likes'],
            'downloads' => $row['downloads'],
            'description' => $row['description']
        ];
    }

    /**
     * Сбор сидов
     * @param array $row
     * @return void
     */
    private static function seeds(array $row): void
    {
        Common::$return[$row['post']][] = [
            'id' => $row['item_id'],
            'title' => $row['title'],
            'versions' => self::sort_versions(json_decode($row['versions'])),
            'key' => $row['key'],
            'screens' => self::images(json_decode($row['screens'], true)),
            'author' => $row['author'],
            'date' => date("d-m-Y", $row['date']),
            'views' => $row['views'],
            'likes' => $row['likes'],
            'downloads' => $row['downloads'],
            'description' => $row['description']
        ];
    }
    
    /**
     * Сбор модов
     * @param array $row
     * @return void
     */
    private static function mods(array $row): void
    {
        Common::$return[$row['post']][] = [
            'id' => $row['item_id'],
            'title' => $row['title'],
            'categories' => self::subcategory($row['item_id']),
            'versions' => self::sort_versions(json_decode($row['versions'])),
            'files' => self::files($row['item_id']),
            'screens' => self::images(json_decode($row['screens'], true)),
            'recipes' => self::images(json_decode($row['recipes'], true)),
            'author' => $row['author'],
            'date' => date("d-m-Y", $row['date']),
            'views' => $row['views'],
            'likes' => $row['likes'],
            'downloads' => $row['downloads'],
            'description' => $row['description']
        ];
    }
    
    
    
    /**
     * Субкатегории записи
     */
    public static function subcategory($item_id): array
    {
        $language_id = Common::$language_id;
        $category = [];
        $cres = Database::getConnection()->query("SELECT * FROM `category_description`, `item_to_category`"
            ." WHERE `item_to_category`.`item_id`='$item_id'"
            ." AND `category_description`.`category_id`=`item_to_category`.`category_id`"
            ." AND `category_description`.`language_id`='$language_id'"
            ." AND `category_description`.`type`='thematic'");
        if($cres->RowCount()){
            while ($category_row = $cres->fetchAll()) {
                $category[] = [
                    'category_id' => $category_row['category_id'], 
                    'name' => $category_row['name'], 
                    'post' => $category_row['post']];
            }
        }
        return $category;
    }
    
    
    /**
     *  Сбор версий
     */
    public static function sort_versions($versions): array
    {
        $version = [];
        if(is_array($versions)){
            for($i = 0; $i < count($versions); $i++ ){
                $version[] = ['code' => $versions[$i]];
            }
        }
        return $version;
    }
    
    /**
     * Сбор файлов
     */
    public static function files($item_id): array
    {
        $files = [];
        $language_id = Common::$language_id;
        $smtm = Database::getConnection()->query("SELECT * FROM `files`, `files_description`"
            ." WHERE `files`.`item_id`='$item_id'"
            ." AND `files`.`file_id`=`files_description`.`file_id`"
            ." AND `files_description`.`language_id`='$language_id'");
        if($smtm->RowCount()){
            while ($file_row = $smtm->fetchAll()) {
                $files[] = [
                    'file_id' => $file_row['file_id'], 
                    'url' => basename($file_row['url']), 
                    'full_url' => $file_row['url'], 
                    'desc' => $file_row['name'], 
                    'downloads' => $file_row['downloads']];
            }
        }
        return $files;
    }
    
    /**
     * Сбор изображений
     */
    public static function images($images = []): array
    {
        $img = [];
        if(is_array($images)){
            for($i = 0; $i < count($images) ; $i++ ){
                $img[] = [
                    'url' => basename($images[$i]), 
                    'full_url' => $images[$i], 
                ];
            }
        }
        return $img;
    }
    
    
    
    
    
    
    
    
    
    
}
