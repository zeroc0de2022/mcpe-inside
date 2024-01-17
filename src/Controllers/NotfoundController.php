<?php
/**
 * Description of NotfoundController
 * @author zeroc0de <98693638+zeroc0de2022@users.noreply.github.com>
 * Date 2021.08.06
 */

namespace Api\Controllers;

use Api\Common\Helper;

class NotfoundController {

    /**
     * NotFound action page
     */
    public static function actionNotfound(): true
    {
        Helper::printPre(json_encode([
                    'error' => true, 
                    'message' => 'Запрошенная страница отсутствует'],
                JSON_UNESCAPED_UNICODE));
        return true;
    }
}
