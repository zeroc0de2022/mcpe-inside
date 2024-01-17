<?php
/*
Date: 14.01.2024
*/
declare(strict_types = 1);

namespace Api\Parser\phpQuery;

class CallbackBody extends Callback
{
    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct($paramList, $code, $param1 = null, $param2 = null, $param3 = null)
    {
        $params = func_get_args();
        $params = array_slice($params, 2);
        $this->callback = function() use ($paramList, $code) {
            eval($code);
        };
        $this->params = $params;
    }
}