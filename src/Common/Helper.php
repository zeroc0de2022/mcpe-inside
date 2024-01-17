<?php 
/**
* @author zeroc0de <98693638+zeroc0de2022@users.noreply.github.com>
*/

namespace Api\Common;


class Helper{


    /**
     * Проверяет существование удаленного файла и сохраняет его на сервере
     * @param string $fromFile
     * @param string $toFile
     * @return array
     */
    public static function uploadFile(string $fromFile, string $toFile): array
    {
        $toDir = dirname($toFile);
        if (!is_dir($toDir) && !mkdir($toDir, 0777, true)) {
            $return = ["status" => false, "message" => "Не удалось создать директорию."];
        } elseif (file_exists($toFile)) {
            $return = ["status" => true, "message" => "Удаленный файл уже существует."];
        } elseif (strlen($fromFile) <= 5) {
            $return = ["status" => false, "message" => "Название файла слишком короткое."];
        } elseif (!self::remoteFileExists($fromFile)) {
            $return = ["status" => false, "message" => "Удаленный файл не существует."];
        } elseif (!self::saveRemoteFile($fromFile, $toFile)) {
            $return = ["status" => false, "message" => "Не удалось сохранить файл."];
        } else {
            $return = ["status" => true, "message" => "Файл успешно загружен"];
        }

        return $return;
    }

    /**
     * Проверяет существование удаленного файла
     * @param string $url
     * @return bool
     */
    private static function remoteFileExists(string $url): bool
    {
        $headers = get_headers($url);
        return str_contains($headers[0], "200 OK");
    }

    /**
     *  Сохраняет удаленный файл на сервере
     * @param string $fromFile
     * @param string $toFile
     * @return bool
     */
    private static function saveRemoteFile(string $fromFile, string $toFile): bool
    {
        $imgContent = file_get_contents($fromFile);
        return $imgContent !== false && file_put_contents($toFile, $imgContent) !== false;
    }






    /**
     * @param $array
     * @param bool $die
     * @param bool $pre
     * @return void
     */
    public static function printPre($array, bool $die = false, bool $pre = false): void
    {
        if($pre){
            echo "<pre>";
        }
        print_r($array);
        if($pre){            
            echo "</pre>";
        }
        if($die) {
          die();  
        }
    }


    /**
     *  Создает переменные из значений массива с начальными значениями true и запускает методы
     *
     * @param array $settings
     * $settings = [
     *      'url' => 'https://example.com',// обязательный параметр
     *      'useragent' => 'Mozilla/5.0',// юзерагент
     *      'header' => -1, // 0 - без заголовков, 1 - с заголовками, -1 - без изменений
     *      'follow' => 0, // 0 - без редиректов, 1 - с редиректами, -1 - без изменений
     *      'TIMEOUT_MS' => 30000, // таймаут в миллисекундах
     *       'cookieFile' => 'cookie.txt',// файл с куками
     *       'cookieStr' => 'session_id=123456789',// строка с куками
     *       'headers' => ['Content-type: text/plain; charset=utf-8', 'Content-length: 100'], // массив с заголовками
     *       'post' => 'param1=value1&param2=value2', // строка с POST параметрами
     *       'iconv' => ['from' => 'windows-1251', 'to' => 'utf-8'], // перекодировка
     *       'proxy' => ['ip' => 2.97.82.255:8080:login:password', 'type' => 'http'], // прокси
     * ];
     * @return array ['status' => true, 'body' => 'html код страницы', 'headers' => 'заголовки ответа', 'error' => 'текст ошибки', 'info' => 'информация о запросе', 'errno' => 'код ошибки']
     */
    public static function p_request(array $settings): array
    {
        $ch = curl_init();
        // Общие параметры cURL
        $options = [
            CURLOPT_URL => $settings['url'],
            CURLOPT_USERAGENT => $settings['useragent'] ?? 'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.94 YaBrowser/17.11.0.2358 Yowser/2.5 Safari/537.36',
            CURLOPT_HEADER => $settings['header'] ?? 0,
            CURLOPT_FOLLOWLOCATION => $settings['follow'] ?? 0,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => $settings['TIMEOUT_MS'] ?? 30000,
            CURLOPT_TIMEOUT_MS => $settings['TIMEOUT_MS'] ?? 30000,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FAILONERROR =>  true// Обработка ошибок
        ];
        // Параметры cookie файлом
        if (isset($settings['cookieFile'])) {
            $options[CURLOPT_COOKIEJAR] = $settings['cookieFile'];
            $options[CURLOPT_COOKIEFILE] = $settings['cookieFile'];
        }
        // Параметры cookie строкой
        if (isset($settings['cookieStr'])) {
            $options[CURLOPT_COOKIE] = $settings['cookieStr'];
        }
        // Параметры заголовков
        if (isset($settings['headers'])) {
            $options[CURLOPT_HTTPHEADER] = $settings['headers'];
        }
        // Параметры POST
        if (isset($settings['post'])) {
            $options[CURLOPT_POSTFIELDS] = $settings['post'];
            $options[CURLOPT_POST] = true;
        }
        // Параметры прокси
        if (isset($settings['proxy'])) {
            $proxy = explode(':', $settings['proxy']['ip']);
            $options[CURLOPT_PROXY] = $proxy[0] . ":" . $proxy[1];
            $options[CURLOPT_PROXYTYPE] = constant("CURLPROXY_".strtoupper($settings['proxy']['type'])) ?? CURLPROXY_HTTP;
            if (isset($proxy[2], $proxy[3])) {
                $options[CURLOPT_PROXYUSERPWD] = $proxy[2] . ":" . $proxy[3];
            }
        }

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);

        $return = [
            'status' => false,
            'body' => ''];
        // Обработка ошибок
        $errno = curl_errno($ch);
        if ($errno || $response === false) {
            $proxyError = isset($proxy[0]) ? " Прокси: ".$proxy[0] : "";
            $return['errno'] = $errno;
            $return['error'] = curl_strerror($errno);
            $return['info'] = curl_getinfo($ch);
            $return['error_message'] = "Ошибка cURL: ".curl_error($ch).".".$proxyError;
            // Произошла ошибка
            return $return;
        }
        // Завершение сеанса cURL
        curl_close($ch);
        // Проверка на пустую страницу
        if (strlen($response) < 1) {
            $return['error_message'] = "Пустая страница";
            return $return;
        }
        // Обработка кодировки
        if (isset($settings['iconv'])) {
            $response = iconv($settings['iconv']['from'], $settings['iconv']['to'], $response);
        }
        // Формирование результата
        $return['status'] = true;
        [$headers] = explode("\r\n\r\n", $response);
        $data = str_replace($headers, '', $response);
        $return['headers'] = $headers;
        $return['body'] = trim($data);
        return $return;
    }

    //парс со страницы
    public static function extractContent(string $start_, string $end_, string $result, string $type, $eq = null)
    {
        $start = preg_quote($start_);
        $end = preg_quote($end_);
        $events = [];
        $pattern = '~'.$start.'(.*?)'.$end.'~s';
        if (!$end) {
            $pattern = '~'.$start.'(.*?)~s';
        }
        elseif (!$start) {
            $pattern = '~(.*?)'.$end.'~s';
        }
        $preg_match_func = ($type === "array") ? "preg_match_all" : "preg_match";
        $preg_match_func($pattern, $result, $events);
        if (!empty($events[1])) {
            $event = (is_numeric($eq)) ? $events[1][$eq] : $events[1];
            return ($type === "array") ? $event : $events[1];
        }
        return ($type === "array") ? [] : "";
    }


    




}