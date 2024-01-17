<?php 
/**
 * Author: zeroc0de <98693638+zeroc0de2022@users.noreply.github.com>
 */
namespace Api\Parser;

use Api\Common\Database;
use Api\Common\GoogleTranslateForFree;
use Api\Common\Helper;
use PDO;

#########################
### Настройки парсера ###
#########################
/**
 * Парсинг категорий с пагинацией по кругу,
 * - Добавление отсутствующих записей со статусом 0
 * Парсинг записей со статусом 0
 * - добавление записей в бд, постановка статус
 */
class Parser extends Helper {
      

    
    #########################
    ### Внутренние данные ###
    ######################### 
    public array $settings = [];
    private PDO $database;
    private string $target = "https://mcpe-inside.ru";
    private array $content;
    private array $modData;
    private mixed $boxBody;
    private mixed $shortBoxBody;
    private mixed $htmlQuery;

    /**
     * Конструктур 
     */
    public function __construct() {
        $this->database = Database::getConnection();
        $stmt =$this->database->query("SELECT * FROM `p_settings`");
        while($row = $stmt->fetchAll()){
            $this->settings[$row['set_name']] = $row['set_value'];
        }
    }
    
    ##################################
    ###     Parser One             ###
    ###     Main page Parsing      ###
    ##################################
    /**
     * Автоматический сбор ссылок с главной страницы
     * До 03.2016
     * @throws \Exception
     */
    public function mainPageParse(): void
    {
        // Запрашиваем страницу каталога
        $message = "";
        $link = $this->target . "/page/" . $this->settings['page'] . "/";
        $html = Helper::p_request(['url' => $link])['body'];
        $this->htmlQuery = phpQuery::newDocument($html);
        $status = (preg_match('#class="next disabled"#i', $html)) ? 'next_circle' : 'next_page';
        $addBool = false;
        $addQuery = [];
        foreach ($this->htmlQuery->find("div.posts-grid__item") as $item) {
            $item = pq($item);
            # Если дата записи меньше 2020 года, пропускаем
            preg_match('#/([0-9]{4}-[0-9]{2})/#i', $item, $matchDate);
            $strtotime = strtotime($matchDate[1]);
            $message = "дата ".date("Y-m-d", $strtotime);  
            if($strtotime < 1577826000){                
                $status = 'next_circle';
                $message = "дата ".date("Y-m-d", $strtotime);                
                break;
            }
            $innerLink = $item->find('h2.box__title > a')->attr("href");
            $this->content['id'] = $item->find('div.info > div.post__rating')->attr("data-id");
            $this->content['link'] = $this->target.$innerLink;
            $this->content['title'] = $item->find('h2.box__title > a')->text();
            $this->content['post_versions'] = $item->find('h2.box__title > span')->text();
            $this->content['description'] = $item->find('div.post__text')->text();
            list($category_name) = explode('/', trim($innerLink, '/'));
            $category = ucfirst(str_replace('-', '_', $category_name));
            $result =$this->database->query("SELECT * FROM `parser_data` WHERE `link`='{$this->content['link']}'");
            if(!$result->rowCount()){
                $addBool = true;
                $data = json_encode($this->content, JSON_UNESCAPED_UNICODE);
                $addQuery[] = "('{$this->content['link']}', '$category', '$data')";
            }
            else {
                $message = "повтор: {$this->content['link']}";
                $status = 'next_circle';
                break;
            }
        }
        if($addBool){
            $query = implode(', ', $addQuery);
           $this->database->query("INSERT INTO `parser_data` (`link`, `category`, `data`) VALUES $query");
            Database::db_error(__LINE__);
        }
        // Задаем настройки для следующего парсинга
        $this->updateSettings($status);
        Helper::printPre("$status - page={$this->settings['page']} - $message", true, true);
        
    }
    
    /**
     * Обновление текущих настроек автоматического парсинга
     */
    private function updateSettings(string $status): void
    {
        switch($status){
            case "next_page" : {
                $this->settings['page']++;
               $this->database->query("UPDATE `p_settings` SET `set_value`='" . $this->settings['page'] . "' WHERE `set_name`='page'");
            }break;
            case "next_circle" : {
               $this->database->query("UPDATE `p_settings` SET `set_value`='1' WHERE `set_name`='page'");
            }break;
        }
    }
    
    
    ##################################
    ###     Parser Two             ###
    ### Skin parsing from category ###
    ##################################    
    /**
     * Автоматически парсинг категории скинов
     * @throws \Exception
     */
    public function SkinPageParse(): void
    {
        $next_circle = false;
        $num = 0;
        for($i = 1;$i < 50;$i++ ){
            if($next_circle){
                die();
            }
            $link = $this->target."/skins/page/$i/";
            $html = Helper::p_request(['url' => $link])['body'];
            $html = str_replace('class="box post skin"', 'class="box_post_skin"', $html);
            $this->htmlQuery = phpQuery::newDocument($html);
            if(preg_match('#class="next disabled"#i', $html)){
                $next_circle = true;
            }
            foreach ($this->htmlQuery->find("div.box_post_skin") as $item) {
                $item = pq($item);
                $innerLink = $item->find('h2.box__title > a')->attr("href");
                $this->content[$num]['id'] = $item->find('div.post__rating')->attr("data-id");
                $this->content[$num]['link'] = $this->target.$innerLink;
                $this->content[$num]['title'] = $item->find('h2.box__title > a')->text();
                //continue;
                list($category_name) = explode('/', trim($innerLink, '/'));
                $category = ucfirst(str_replace('-', '_', $category_name));
                $result = $this->database->query("SELECT * FROM `parser_data` WHERE `link`='" . $this->content[$num]['link']. "'");
                if(!$result->rowCount()){
                    $data = json_encode($this->content[$num], JSON_UNESCAPED_UNICODE);
                   $stmt = $this->database->prepare("INSERT INTO `parser_data` (`link`, `category`, `data`) VALUES (:link, :category, :data)");
                   $stmt->execute([
                        ':link' => $this->content[$num]['link'],
                        ':category' => $category,
                        ':data' => $data
                   ]);
                    if($this->database->errorInfo()){
                        printf(__LINE__.": Сообщение ошибки: %s<br>\n",  $this->database->errorInfo());
                        die();
                    }
                }
                else {
                    $next_circle = true;
                }
            }
        }
    }
    
    
    
    #######################################
    ###     Parser Three                ###
    ### Items parsing from parser DB    ###
    #######################################
    /**
     * Router
     */    
    public function Router(): void
    {
        /*
         * Выбор мода со статусом 0 из БД
         * Определения текущей функции для обработки мода
         * Запуск функции 
         */
        $modQuery =$this->database->query("SELECT * FROM `parser_data` WHERE `status`='0' LIMIT 1");
        if($modQuery->rowCount()){
            $this->modData = $modQuery->fetchAll();
            $funcName = "parse".$this->modData['category'];
            if(method_exists($this, $funcName)){
                $this->$funcName();
            }
            else {
                Helper::printPre("Метод $funcName не существует", true, true);
            }
        }
        else {
            /*  Mods::removeItems(48); */
            $modQuery =$this->database->query("SELECT * FROM `items` WHERE `date`='0'");
            if($modQuery->rowCount()){
                while($row = $modQuery->fetchAll()){
                    Mods::removeItems($row['item_id']);
                }
                die();
            }
            Helper::printPre("Новых записей нет", true, true);
        }
    }

    /**
     * Парсинг страницы с модом
     * @throws \Exception
     */
    private function parseMods(): void
    {
        $this->parseItem();
    }

    /**
     * Парсинг страницы с текстурой
     * @throws \Exception
     */
    private function parseTexture_packs(): void
    {
        $this->parseItem();
    }

    /**
     * Парсинг страницы с картой
     * @throws \Exception
     */
    private function parseMaps(): void
    {
        $this->parseItem();
    }

    /**
     * Парсинг страницы с шейдером
     * @throws \Exception
     */
    private function parseShaders(): void
    {
        $this->parseItem();
    }

    /**
     * Парсинг страницы с сидом
     * @throws \Exception
     */
    private function parseSeeds(): void
    {
        $this->parseItem();
    }

    /**
     * Парсинг страницы со скином
     * @throws \Exception
     */
    private function parseSkins(): void
    {
        $this->parseItem();
    }



    /**
     * Парсинг общей информации
     * @throws \Exception
     */
    private function parsePageContent(): void
    {
        // Парсинг автора
        $this->parseAuthor();
        // Парсинг описания
        $this->parseDescription();
        // Парсинг скриншотов и рецептов
        $this->parseScreensAndRecipes();
        // Парсинг общей информации
        $this->parseInfo();
        $this->parseDirsAndVersions();
        $this->parseDownloads();
    }

    /**
     *  Инициализация массива $content
     * @return void
     */
    private function initializeContent(): void
    {
        $this->content = json_decode($this->modData['data'], true);
        $this->content['mod_id'] = $this->content['id'];
        $this->content['id'] = $this->modData['id'];
        $this->content['versions'] = $this->content['screens'] = $this->content['recipes'] =
        $this->content['category'] = $this->content['files'] = [];
        $this->content['author'] = $this->content['key'] = "";
        $this->content['downloads'] = 0;
        $this->content['type'] = strtolower($this->modData['category']);
    }

    /**
     * Удаление записи из БД, если страница не найдена
     * @param $html
     * @return void
     */
    private function deletePageIfNotFound($html): void
    {
        if (preg_match('#page_404#i', $html)) {
            $this->database->query("DELETE FROM `parser_data` WHERE `id`='" . $this->modData['id'] . "'");
            Helper::printPre("Страница отсутствует!", true, true);
        }
    }

    /**
     * Замена ссылок на абсолютные
     * @param $html
     * @return void
     */
    private function replaceLinksInHtml(&$html): void
    {
        $replacements = [
            '/(src=["\'])\//' => '$1' . $this->target . '/',
            '/(href=["\'])\//' => '$1' . $this->target . '/',
        ];
        $html = preg_replace(array_keys($replacements), $replacements, $html);
    }

    /**
     * Парсинг Автора
     * @return void
     */
    private function parseAuthor(): void
    {
        if (preg_match('#Автор#iu', $this->boxBody)) {
            $this->content['author'] = trim($this->extractContent('Автор:', '<', $this->boxBody, 'string'));
            if (strlen($this->content['author']) < 1) {
                $this->content['author'] = trim(strip_tags($this->extractContent('Автор:', '</a>', $this->boxBody, 'string')));
            }
        }
    }

    /**
     * Парсинг описания
     * @return void
     */
    private function parseDescription(): void
    {
        if ($this->content['type'] == 'skins') {
            $this->content['description'] = $this->extractContent('<h2>Описание</h2>', '<h2>', $this->boxBody, 'string');
        }
        else {
            $desc__body = str_replace(['<br />', '<br/>', '<br>'], ' ', $this->boxBody);
            $description = trim($this->extractContent('class="box__body">', '<', $desc__body, 'string'));
            $description = preg_replace('#[\r\n]+#i', '', trim(substr($description, 0, strpos($description, 'Автор'))));
            $this->content['description'] = (strlen($description) > 5) ? $description : $this->content['description'];
        }
    }

    /**
     * Парсинг Галереи
     * @param $galleria
     * @return void
     */
    private function parseGallery($galleria): void
    {
        for ($num = 0; $num < count($galleria); $num++) {
            $value = ($num == 0) ? "screens" : "recipes";
            $attr = (preg_match('data-big="', $galleria[$num])) ? 'data-big="' : 'href="';
            $this->content[$value] = $this->extractContent($attr, '"', $galleria[$num], 'array');
        }
    }

    /**
     * Парсинг скриншотов и рецептов
     * @return void
     */
    private function parseScreensAndRecipes(): void
    {
        $galleria = $this->extractContent('<div class="galleria">', '</div>', $this->boxBody, 'array');
        if (is_array($galleria)) {
            $this->parseGallery($galleria);
        }
        else {
            $this->parseScreensAndRecipesForNonSkinMods();
        }
    }

    /**
     * Парсинг скриншотов и рецептов для модов, кроме скинов
     * @return void
     */

    private function parseScreensAndRecipesForNonSkinMods(): void
    {
        if (preg_match('#<h2>Рецепты</h2>#i', $this->shortBoxBody)) {
            [$screens, $recipes] = explode('<h2>Рецепты</h2>', $this->shortBoxBody);
            $this->content["screens"] = $this->extractContent('src="', '"', $screens, 'array');
            $this->content["recipes"] = $this->extractContent('src="', '"', $recipes, 'array');
        }
        else {
            $this->content["screens"] = $this->extractContent('src="', '"', $this->shortBoxBody, 'array');
        }
    }

    /**
     * Извлечение контента из html
     * @throws \Exception
     */
    private function parseInfo(): void
    {
        $dateStrToTime = trim($this->boxBody->find('div.post__time')->text());
        $this->content['post_time'] = $this->dateStrToTime($dateStrToTime);
        $this->content['views'] = preg_replace('#[^0-9]+#i', '', trim($this->boxBody->find('div.post__views')->attr("title")));
        $this->content['likes'] = trim($this->boxBody->find('div.post__rating')->text());

        if ($this->content['type'] == 'seeds') {
            $this->content['key'] = $this->boxBody->find('td.dl__info')->text();
            $this->content['description'] .= $this->boxBody->find('div.spoiler')->html();
        }
        else {
            $this->parseDownloadsTable();
        }
    }

    /**
     * Парсинг директории и версий
     * @return void
     */
    private function parseDirsAndVersions (): void
    {
        $cat_versions = [];
        // post категории
        foreach($this->boxBody->find('div.post__category > a') as $sub) {
            $sub = pq($sub);
            $category = trim($sub->text());
            if(preg_match('#[0-9.]+#i', $category)){
                $cat_versions[] = $category;
            }
            $this->content['category'][] = $category;
        }
        // Категории мода
        foreach($this->boxBody->find('div.post__category > div.post__tags > span > a') as $tags) {
            $tags = pq($tags);
            $category = trim($tags->text());
            if(preg_match('#[0-9.]+#i', $category)){
                $cat_versions[] = $category;
            }
            $this->content['category'][] = $category;
        }
        // Версии мода
        foreach($this->boxBody->find('div.builds-wrapper > div.builds > div') as $builds) {
            $builds = pq($builds);
            $this->content['versions'][] = trim($builds->text());
        }
        preg_match_all('#([0-9.]+)#i', $this->content['post_versions'], $matchVersion);
        $post_versions = $matchVersion[0] ?? [];

        $versions = array_values(array_unique(array_merge($this->content['versions'], $cat_versions, $post_versions)));
        sort($versions);
        $this->content['versions'] = json_encode($versions, JSON_UNESCAPED_UNICODE );
    }

    /**
     * Парсинг загрузок
     * @return void
     */
    private function parseDownloads(): void
    {
        $this->content['source_link'] = $this->boxBody->find('noindex > a')->attr("href");
        $this->content['source_name'] = trim($this->boxBody->find('noindex > a')->text());
    }

    /**
     * Парсинг таблицы загрузок
     * @return void
     */
    private function parseDownloadsTable(): void
    {
        $num = 0;
        foreach ($this->htmlQuery->find("tr.dl__row") as $dl__row) {
            $dl__row = pq($dl__row);
            $this->content['files'][$num]['name'] = $dl__row->find('td.dl__info > a > span.dl__name')->text();
            $fileLink = $dl__row->find('td.dl__info > a')->attr("href");
            $this->content['files'][$num]['link'] = preg_match('#' . preg_quote($this->target) . '#i', $fileLink) ? $fileLink : $this->target . $fileLink;
            $downloads = preg_replace('#[^0-9]+#i', '', $dl__row->find('td.dl__info > a > span.dl__link')->attr("title"));
            $this->content['files'][$num]['downloads'] = is_numeric($downloads) ? $downloads : 0;
            $this->content['downloads'] += $this->content['files'][$num]['downloads'];
            //$this->content['files']['list'][$num]['size'] = $dl__row->find('td.dl__size')->text();
            //$this->content['files']['list'][$num]['date'] = $dl__row->find('td.dl__date')->text();
            $num++;
        }
    }

    /**
     * Обновление записи в БД
     */
    private function updateDatabase($content): void
    {
        $stmt = $this->database->prepare("UPDATE `parser_data` SET status='1', data=:data WHERE id=:id");
        $stmt->execute([
            ':data' => $content,
            ':id' => $this->modData['id']
        ]);
    }

    /**
     * Вывод контента на экран, если есть параметр print=1
     */
    private function printContentIfNeeded(): void
    {
        if (isset($_GET['print']) && $_GET['print'] == 1) {
            Helper::printPre($this->content, true, true);
        }
    }

    /**
     * Общая функция Парсинга страницы с модом
     * @throws \Exception
     */
    public function parseItem(): void
    {
        $this->initializeContent();
        // Запрашиваем страницу каталога
        $body = Helper::p_request(['url' => $this->modData['link']]);
        $html = $body['body'];
        // Проверяем наличие страницы
        $this->deletePageIfNotFound($html);
        // Замена ссылок на абсолютные
        $this->replaceLinksInHtml($html);
        // Парсинг страницы
        $this->htmlQuery = phpQuery::newDocument($html);
        $this->boxBody = pq($this->htmlQuery->find("div.box__body"));
        $this->shortBoxBody = $this->extractContent('class="box__body">', '<div', $this->boxBody, 'string');
        // Парсинг общей информации
        $this->parsePageContent();

        $Mods = new Mods();
        $Mods->setItem($this->content);
        $content = json_encode($this->content, JSON_UNESCAPED_UNICODE);

        $this->updateDatabase($content);
        $this->printContentIfNeeded();
    }

    /**
     * Перевод строковой даты в timestamp
     * @param string $str
     * @return int
     * @throws \Exception
     */
    private function dateStrToTime(string $str):int
    {
        $translations = [
            'Вчера в' => 'Yesterday',
            'Сегодня в' => 'Today',
            'января' => 'january',
            'февраля' => 'february',
            'марта' => 'march',
            'апреля' => 'april',
            'мая' => 'may',
            'июня' => 'june',
            'июля' => 'july',
            'августа' => 'august',
            'сентября' => 'september',
            'октября' => 'october',
            'ноября' => 'november',
            'декабря' => 'december',
        ];

        // Проверка, есть ли в строке текст для перевода
        if (preg_match('#('.implode('|', array_keys($translations)).')#iu', $str)) {
            $str = str_replace(array_keys($translations), $translations, $str);
        }
        else {
            $translation = GoogleTranslateForFree::translate("ru", "en", $str);
            // Проверка, успешно ли выполнен перевод и не пуст ли результат
            if ($translation && $translation !== $str) {
                $str = $translation;
            }
        }
        return (int)strtotime($str);
    }

    /**
     * Добавление прокси в БД
     * @throws \Exception
     * @return void
     * @noinspection PhpUnused
     */
    public function addProxy(): void
    {
        $proxyFile = file(PARSER_ROOT."/proxy.txt");
        for($i = 0; $i < count($proxyFile); $i++ ){
            $bindProxy = [
                ':proxy' => trim($proxyFile[$i])
            ];
            $stmt = $this->database->prepare("INSERT IGNORE INTO proxy(proxy, uptime) VALUES (:proxy, 0)");
            $stmt->execute( $bindProxy );
        }
    }

}

