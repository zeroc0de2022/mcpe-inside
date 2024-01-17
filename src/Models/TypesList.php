<?php
/**
 * Description of TypesList
 * @author zeroc0de <98693638+zeroc0de2022@users.noreply.github.com>
 * Date 2021.08.06
 */

namespace Api\Models;

use Api\Common\Common;
use Api\Common\Database;

class TypesList {
    public static array $return = [];
    public static array $tree = [];
    public static array $types = [];
    
    /**
     * Все основные категории с подкатегориями
     */
    public static function all_categories(): bool
    {
        $language_id = Common::$language_id;
        $status = false;
        $stmt = Database::getConnection()->query("SELECT * FROM `category`, `category_description` WHERE `category`.`category_id`=`category_description`.`category_id` AND `category_description`.`language_id`='$language_id' AND `category_description`.`type`='thematic'");
        if(!$stmt->rowCount()){
            self::$return['error'] = true;
            self::$return['message'] = __LINE__.": Категории в системе не найдены";
        }
        else {
            while($row = $stmt->fetchAll()){
                self::$tree[$row['parent_id']][$row['category_id']] = [
                    'name' => $row['name'],
                    'post' => $row['post'],
                    'type' => $row['type']
                    ];
            }
            $status = true;
        }
        return $status;
    }
    
    
    
    public static function categories(): void
    {
        $status = self::all_categories();
        if($status){
            self::select_categories();
        }
    }
    
    
    /**
     * Сбор субкатегорий
     */
    public static function subcategories($category_post)
    {
        self::categories();
        return (self::$return[$category_post]['subcategories']) ?? [];
    }
    
    /**
     * Выбор указанных категорий и подкатегорий из общего массива категорий
     */
    private static function select_categories(): void
    {
        foreach(self::$tree[0] as $key => $value){
            if(isset(self::$types['types']) && is_array(self::$types['types']) && count(self::$types['types']) > 0){
                if(!in_array($value['post'], self::$types['types'])){
                    continue;
                }
            }
            self::$return[$value['post']] = [
                'id' => $key,
                'name' => $value['name'],
                'subcategories' => []
            ];
            foreach(self::$tree[$key] as $subKey => $subValue){                
                if(isset(self::$types['subtypes']) && is_array(self::$types['subtypes']) && count(self::$types['subtypes']) > 0){
                    if(!in_array($subValue['post'], self::$types['subtypes'])){
                        continue;
                    }
                }
                self::$return[$value['post']]['subcategories'][] = [
                    'id' => $subKey,
                    'name' => $subValue['name'],
                    'post' => $subValue['post']
                ];
            }
        }
    }
    
    
}
