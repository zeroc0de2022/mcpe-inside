<?php
/**
 * Description of Common
 * @author zeroc0de <98693638+zeroc0de2022@users.noreply.github.com>
 * Date 2021.08.08
 */

namespace Api\Common;



class Common {
    const SHOW_RESULTS_BY_DEFAULT = 10;
    public static array $return = [];
    public static int $language_id;
    
    
    /**
     * Определяем текущий язык
     * @param string $language_post
     * @return void
     */
    public static function language(string $language_post): void
    {
        $database = Database::getConnection();
        # определяем текущий язык
        $stmt = $database->prepare("SELECT * FROM language WHERE code=:code");
        $stmt->execute(['code' => $language_post]);
        if(!$stmt->rowCount()){
            Helper::printPre(json_encode([
                'error' => true,
                'message' => "Такого языка в системе нет"
                ],JSON_UNESCAPED_UNICODE), true);
        }
        Common::$language_id = $stmt->fetch()['language_id'];
    }
    
    /**
     * Проверяем соответствие ключа
     * @return bool
     */
    public static function apiAuth(): bool
    {
        $database = Database::getConnection();
        if (isset($_GET['apiKey'])) {
            $userApiKey = $_GET['apiKey'];
            $stmt = $database->prepare("SELECT * FROM `api` WHERE `apiKey` = :apiKey LIMIT 1");
            $stmt->execute([':apiKey' => $userApiKey]);
            if ($stmt->rowCount()) {
                $apiKey = $stmt->fetch()['apikey'];
                if ($apiKey == $userApiKey) {
                    return true;
                }
            }
        }
        $reply = [
            'error' => true,
            'message' => 'Укажите правильный ключ API',
        ];
        Helper::printPre(json_encode($reply, JSON_UNESCAPED_UNICODE), true);
        return false;
    }


}
