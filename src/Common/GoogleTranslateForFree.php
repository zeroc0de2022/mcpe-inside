<?php

namespace Api\Common;

use Api\Parser\Mods;
use Exception;


/**
 * GoogleTranslateForFree.php.
 *
 * Class for free use Google Translator. With attempts connecting on failure and array support.
 *
 * @category Translation
 *
 * @author Yuri Darwin
 * @author Yuri Darwin <gkhelloworld@gmail.com>
 * @copyright 2019 Yuri Darwin
 * @license https://opensource.org/licenses/MIT
 *
 * @version 1.0.0
 */

/**
 * Main class GoogleTranslateForFree.
 */
class GoogleTranslateForFree{
    public static int $useProxy = 0;
    public static string $proxy;

    /**
     * @param string $source
     * @param string $target
     * @param array|string $text
     * @param int $attempts
     *
     * @return string|array With the translation of the text in the target language
     * @throws \Exception
     */
    public static function translate(string $source, string $target, array|string $text, int $attempts = 5): array|string
    {
        return is_array($text)
            ? GoogleTranslateForFree::requestTranslationArray($source, $target, $text, $attempts)
            : GoogleTranslateForFree::requestTranslation($source, $target, $text, $attempts);
    }

    /**
     * @param string $source
     * @param string $target
     * @param array $text
     * @param int $attempts
     *
     * @return array
     * @throws \Exception
     */
    protected static function requestTranslationArray(string $source, string $target, array $text, int $attempts = 5): array
    {
        $arr = [];
        foreach($text as $value) {
            usleep(500000); // timeout 0.5 sec
            $arr[] = GoogleTranslateForFree::requestTranslation($source, $target, $value, $attempts);
        }
        return $arr;
    }

    /**
     * @param string $source
     * @param string $target
     * @param string $text
     * @param int $attempts
     *
     * @return string
     * @throws \Exception
     */
    protected static function requestTranslation(string $source, string $target, string $text, int $attempts): string
    {
        $url = 'https://translate.google.com/translate_a/single?client=at&dt=t&dt=ld&dt=qca&dt=rm&dt=bd&dj=1&hl=uk-RU&ie=UTF-8&oe=UTF-8&inputm=2&otf=2&iid=1dd3b944-fa62-4b55-b330-74909a99969e';

        $fields = [
            'sl' => urlencode($source),
            'tl' => urlencode($target),
            'q' => urlencode($text),
        ];

        if(strlen($fields['q']) >= 5000) {
            throw new Exception('Maximum number of characters exceeded: 5000');
        }

        $fields_string = GoogleTranslateForFree::fieldsString($fields);
        $content = GoogleTranslateForFree::curlRequest($url, $fields, $fields_string, 0, $attempts);

        return GoogleTranslateForFree::getSentencesFromJSON($content);
    }

    /**
     * Dump of the JSON's response in an array.
     *
     * @param string $json
     * @return string
     */
    protected static function getSentencesFromJSON(string $json): string
    {
        $arr = json_decode($json, true);
        $sentences = '';
        if(isset($arr['sentences'])) {
            foreach($arr['sentences'] as $s) {
                $sentences .= $s['trans'] ?? '';
            }
        }
        return $sentences;
    }

    /**
     * Curl Request attempts connecting on failure.
     *
     * @param string $url
     * @param array $fields
     * @param string $fields_string
     * @param int $i
     * @param int $attempts
     *
     * @return string
     */
    protected static function curlRequest(string $url, array $fields, string $fields_string, int $i, int $attempts): string
    {

        if(GoogleTranslateForFree::$useProxy == 1) {
            $result = Database::getConnection()->query("SELECT * FROM `proxy` WHERE `status`='0' ORDER BY RAND() LIMIT 1");
            if(!$result->RowCount()) {
                Helper::printPre("Прокси отсутствуют", true);
            }
            GoogleTranslateForFree::$proxy = $result->fetch();
        }

        $i++;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AndroidTranslate/5.3.0.RC02.130475354-53000263 5.1 phone TRANSLATE_OPM5_TEST_1');

        if(GoogleTranslateForFree::$useProxy == 1) {
            $type = 'SOCKS5';
            $proxy = explode(':', self::$proxy['proxy']);

            if(isset($proxy[2], $proxy[3])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy[2].":".$proxy[3]);
            }

            curl_setopt_array($ch, [
                CURLOPT_PROXY => $proxy[0].":".$proxy[1],
                CURLOPT_PROXYTYPE => constant("CURLPROXY_".strtoupper($type)) ?? CURLPROXY_HTTP,
            ]);
        }

        $result = curl_exec($ch);

        if($result === false) {
            $return = ['status' => false, 'body' => "Ошибка curl: ".curl_error($ch).(isset($proxy[0]) ? ": Прокси: ".($proxy[0]) : "")];
            Mods::removeItems();
            Helper::printPre($return, true);
        }
        else if(strlen($result) < 1) {
            $return = ['status' => false, 'body' => "Пустая страница"];
            Mods::removeItems();
            Helper::printPre($return, true);
        }

        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if(false === $result || 200 !== $httpcode) {
            if($i >= $attempts) {
                return ''; // Could not connect and get data
            }
            else {
                usleep(1500000); // timeout 1.5 sec
                return GoogleTranslateForFree::curlRequest($url, $fields, $fields_string, $i, $attempts);
            }
        }
        else {
            if(GoogleTranslateForFree::$useProxy == 1) {
                Database::getConnection()->query("UPDATE `proxy` SET `uptime`='".time()."' WHERE `id`='".self::$proxy['id']."'");
            }

            return $result;
        }
    }
    /**
     * Make string with post data fields.
     *
     * @param array $fields     *
     * @return string
     */
    protected static function fieldsString(array $fields): string
    {
        $fields_string = '';
        foreach ($fields as $key => $value) {
            $fields_string .= $key.'='.$value.'&';
        }

        return rtrim($fields_string, '&');
    }


}











