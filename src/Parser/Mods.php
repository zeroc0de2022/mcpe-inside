<?php
/**
 * Description of Mods
 * @author zeroc0de <98693638+zeroc0de2022@users.noreply.github.com>
 */

namespace Api\Parser;

use Api\Common\Database;
use Api\Common\GoogleTranslateForFree;
use Api\Common\Helper;

class Mods {
    // Настройки перевода
    private array $language;
    private string $source = 'ru';
    private int $attempts = 5;
    private static int $id;
    /**
     * Конструктур 
     */
    public function __construct() {
        $stmt = Database::getConnection()->query("SELECT * FROM `language` WHERE `active`='1'");
        while($row = $stmt->fetchAll()){
            $this->language[$row['code']] = $row['language_id'];
        }
    }

    /**
     *  Загрузка мода
     * @param array $content
     * @throws \Exception
     */
    public function setItem(array $content): void
    {
        self::$id = $content['id'];
        $stmt = Database::getConnection()->prepare("SELECT * FROM items WHERE item_id= :item_id");
        $stmt->execute([':item_id' => $content['id']]);
        if(!$stmt->rowCount()){
            # Загрузка Файлов и распределение в таблицу
            # Загрузка скринов
            if(isset($content['screens']) && is_array($content['screens'])){
                $content['screens'] = json_encode(
                    $this->uploadFilesArray(
                        $content['screens'], $content['id'], $content['type'], 'screens', 's', 'image'
                    )
                );
            }
            # Загрузка рецептов
            if(isset($content['recipes']) && is_array($content['recipes'])){
                $content['recipes'] = json_encode(
                    $this->uploadFilesArray(
                        $content['recipes'], $content['id'], $content['type'], 'recipes', 'r', 'image'
                    )
                );
            }
            # Добавление основной информации по модам
            $stmt = Database::getConnection()->prepare("INSERT INTO `items` (`item_id`, `author`, `screens`, `recipes`, `date`, `views`, `likes`, `versions`, `source_link`, `source_name`, `type`, `key`, `downloads`) VALUES (:item_id, :author, :screens, :recipes, :date, :views, :likes, :versions, :source_link, :source_name, :type, :key, :downloads)");
            $stmt->execute([
                ':item_id' => $content['id'],
                ':author' => $content['author'],
                ':screens' => $content['screens'],
                ':recipes' => $content['recipes'],
                ':date' => $content['post_time'],
                ':views' => $content['views'],
                ':likes' => $content['likes'],
                ':versions' => $content['versions'],
                ':source_link' => $content['source_link'],
                ':source_name' => $content['source_name'],
                ':type' => $content['type'],
                ':key' => $content['key'],
                ':downloads' => $content['downloads']
            ]);

            # Добавлении основной информации с переводом (остальные языки)
            foreach($this->language as $lang_key => $language_id) {
                $title = ($lang_key != 'ru') 
                    ? GoogleTranslateForFree::translate($this->source, $lang_key, $content['title'], $this->attempts)
                    : $content['title'];
                $description = ($lang_key != 'ru') 
                    ? GoogleTranslateForFree::translate($this->source, $lang_key, $content['description'], $this->attempts)
                    : $content['description'];
                //
                $stmt = Database::getConnection()->prepare("INSERT INTO `items_description` (`item_id`, `language_id`, `title`, `description`) VALUES (:item_id, :language_id, :title, :description)");
                $stmt->execute([
                    ':item_id' => $content['id'],
                    ':language_id' => $language_id,
                    ':title' => $title,
                    ':description' => $description
                ]);
            }
            
            # Добавление категории       
            # Добавлении категорий с переводом     
            $categories = $this->addCategory($content['category']);
            # Привязка мода к категориям
            $this->item_to_category($content['id'], $categories, $content['type']);
            
            # Загрузка аддона
            if(isset($content['files']) && is_array($content['files'])){
                $countFiles = count($content['files']);
                if($countFiles > 0){
                    $files = [];
                    for($i = 0; $i < count($content['files']); $i++ ){
                        $files[] = $content['files'][$i]['link'];
                    }
                    if($content['type'] == 'skins'){
                        $downloads = $this->uploadSkins($files, $content['id'], $content['type']);
                    }
                    else {
                        $downloads = $this->uploadFilesArray($files, $content['id'], $content['type'], 'files', 'r', 'file');
                    }
                    for($i = 0; $i < $countFiles; $i++ ){
                        $filesNameOrigin = $content['files'][$i]['name'];
                        $stmt = Database::getConnection()->prepare("INSERT INTO `files` (`file_id`, `item_id`, `url`, `downloads`, `sort_order`) VALUES (:file_id, :item_id, :url, :downloads, :sort_order)");
                        $stmt->execute([
                                ':item_id' => $content['id'],
                                ':url' => $downloads[$i],
                                ':downloads' => $content['files'][$i]['downloads'],
                                ':sort_order' => $i
                        ]);
                        $file_id = Database::getConnection()->lastInsertId();
                        # С переводом на остальные языки
                        foreach($this->language as $lang_key => $language_id) {
                            if(strlen($filesNameOrigin) > 3 && $lang_key != 'ru'){
                                $content['files'][$i]['name'] = GoogleTranslateForFree::translate($this->source, $lang_key, $filesNameOrigin, $this->attempts);
                            }
                            $stmt = Database::getConnection()->prepare("INSERT INTO `files_description` (`file_id`, `language_id`, `name`, `sort_order`) VALUES (:file_id, :language_id, :name, :sort_order)");
                            $stmt->execute([
                                    ':file_id' => $file_id,
                                    ':language_id' => $language_id,
                                    ':name' => $content['files'][$i]['name'],
                                    ':sort_order' => $i
                            ]);
                        }
                    }
                }
            }
        }
    }


    /**
     * Добавление структуры категорий
     * @throws \Exception
     */
    private function addCategory(array $categoryList): array
    {
        # Main Category
        $categories = [];
        $main_category = array_shift($categoryList);
        $categories[] = $parent_id = $this->category($main_category, 0);
        
        # Secondary category
        if(is_array($categoryList)){
            $categoryList = array_values(array_unique($categoryList));
            for($i = 0;$i < count($categoryList);$i++ ){
                $categories[] = $this->category($categoryList[$i], $parent_id);
            }
        }
        return $categories;
    }

    /**
     * Add category with translate
     * Return category_id
     * @throws \Exception
     */
    private function category(string $category_name, int $parent_id){
        $stmt = Database::getConnection()->prepare("SELECT * FROM category, category_description WHERE category_description.name=:category_name AND category.parent_id='$parent_id' AND category.category_id=category_description.category_id");
        $stmt->execute([
                ':category_name' => $category_name
        ]);

        if(!$stmt->rowCount()){
            Database::getConnection()->query("INSERT INTO `category` (`category_id`, `parent_id`) VALUES (NULL, '$parent_id')");
            $category_id = Database::getConnection()->lastInsertId();
            $translateBool = preg_match('#[А-яёЁ]+#iu', $category_name );
            $category_type = (preg_match('#[0-9.]+#i', $category_name )) ? "version" : "thematic";
            $catQuery = ["('$category_id', '$category_name', '1', '$category_type')"];
            $transA = $category_name;
            foreach($this->language as $lang_key => $language_id) {
                if($translateBool){
                    $transA = ($lang_key != 'ru') 
                        ? GoogleTranslateForFree::translate($this->source, $lang_key, $category_name.", minecraft", $this->attempts)
                        : $transA ;
                    $transA = trim(str_replace(array(', minecraft', ', Minecraft', 'Minecraft'), '', $transA));
                    [$transA] = explode(',', $transA);
                }
                $catQuery[] = "('$category_id', '$transA', '$language_id', '$category_type')";
            }
            $catQ = implode(', ', $catQuery);
            Database::getConnection()->query("INSERT INTO `category_description` (`category_id`, `name`, `language_id`, `type`) VALUES $catQ");

        }
        else {
            $category = $stmt->fetchAll();
            $category_id = $category['category_id'];
        }
        return $category_id;
    }
    
    
    
    
    
    
    #####################################
    ###### ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ  #####
    #####################################
     /**
     * Очистка бд от ломаных записей
      * @param int $item_id
     */
    public static function removeItems(int $item_id = 0): void
    {
        $item_id = ($item_id !== 0) ? $item_id : self::$id;

        Database::getConnection()->query("DELETE FROM `items_description` WHERE `item_id`='$item_id'");
        Database::getConnection()->query("DELETE FROM `item_to_category` WHERE `item_id`='$item_id'");
        Database::getConnection()->query("DELETE FROM `items` WHERE `item_id`='$item_id'");
        Database::getConnection()->query("UPDATE `parser_data` SET `status`='0' WHERE `id`='$item_id'");
            
        $files = Database::getConnection()->query("SELECT * FROM `files` WHERE `item_id`='$item_id'");
        if($files->rowCount()){
            $filesA = $files->fetchAll();
            $file_id = $filesA['file_id'];
            Database::getConnection()->query("DELETE FROM `files_description` WHERE `file_id`='$file_id'");
            Database::getConnection()->query("DELETE FROM `files` WHERE `item_id`='$item_id'");
        }
    }
    

    /**
     * ADD ITEM TO CATEGORY
     * @param int $item_id
     * @param array $categories
     * @param string $type
     */
    private function item_to_category(int $item_id, array $categories, string $type): void
    {
        for($i = 0;$i < count($categories);$i++ ){
            $stmt = Database::getConnection()->prepare("SELECT * FROM `item_to_category` WHERE `category_id`=:category_id AND `item_id`=:item_id AND `type`=:type");
            $stmt->execute([
                    ':category_id' => $categories[$i],
                    ':item_id' => $item_id,
                    ':type' => $type
            ]);
            if(!$stmt->rowCount()){
                $stmt = Database::getConnection()->prepare("INSERT INTO `item_to_category` (category_id, item_id, type, sort_order) VALUES (:category_id, :item_id, :type, $i)");
                $stmt->execute( [
                    ':category_id' => $categories[$i],
                    ':item_id' => $item_id,
                    ':type' => $type
                ]);
            }
        }
    }

    /**
     * Перевод в рекурсивном цикле, основной язык Ru
     * @param array $array
     * @param string $lang
     * @return array
     * @throws \Exception
     */
    /* @noinspection PhpUnusedPrivateMethodInspection */
    private function translate(array $array, string $lang ): array
    {
        foreach($array as $key => $value) {
            if(is_array($value)){
                $array[$key] = $this->translate($value, $lang );
            }
            else {
                if(preg_match('#[А-яёЁ]+#iu', $value)){
                    $array[$key] = GoogleTranslateForFree::translate($this->source, $lang, $value, $this->attempts);
                }
            }
        }
        return $array;
    }
    
    
    /**
     * 
     * @param array $links = links array
     * @param  $id_num = id_num
     * @param  $category = mods - maps - shaders
     * @param  $img_type = screens - recipes - files
     * @param  $file_pref = s - r
     * @param  $filetype = file - image
     * @return array
     */
    private function uploadFilesArray(array $links, string $id_num, string $category, string $img_type, string $file_pref, string $filetype): array
    {
        $files = [];
        for($i = 0;$i < count($links) ;$i++ ){
            if(($filetype == 'file')){
                $links[$i] = $this->getHeaderFileLocation($links[$i]);
                $filename = basename($links[$i]);
            }
            else {
                $filename = $file_pref.$i.".jpg" ;
            }
            $toFile = "main_catalog/$category/$id_num/$img_type/$filename";
            $upload = Helper::uploadFile($links[$i], ROOT."/".$toFile);
            if($upload['status']){
                $files[] = $toFile;
            }
        }
        return $files;
    }

    /**
     * Upload Skin files
     * @param array $links
     * @param string $id_num
     * @param string $category
     * @return array
     */
    private function uploadSkins(array $links, string $id_num, string $category): array
    {
        $files = [];
        for($i = 0;$i < count($links);$i++ ){
            $toFile = "main_catalog/$category/$id_num/skin.png";
            $upload = Helper::uploadFile($links[$i], ROOT."/".$toFile);
            if($upload['status']){
                $files[] = $toFile;
            }
        }
        return $files;
    }
    
    /**
     * Return end location file link
     * @param string $fileLink
     * @return string
     */
    private function getHeaderFileLocation(string $fileLink): string
    {
        $headers = get_headers($fileLink);
        $link = '';
        if(preg_match("/(200 OK)/", implode(', ', $headers))) {            
            for($i = 0; $i < count($headers); $i++ ){
                if(preg_match('#Location#i', $headers[$i])){
                    $link = trim(str_replace('Location:', '', $headers[$i]));
                    break;
                }
            }
        }
        return $link;
    }
}
