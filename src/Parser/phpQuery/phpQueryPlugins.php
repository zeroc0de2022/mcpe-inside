<?php
/*
Date: 14.01.2024
*/
declare(strict_types = 1);


namespace Api\Parser\phpQuery;


use Api\Parser\phpQuery;
use Exception;

class phpQueryPlugins
{
    /**
     * @throws \Exception
     */
    public function __call($method, $args)
    {
        if(isset(phpQuery::$extendStaticMethods[$method])) {
            $return = call_user_func_array(
                phpQuery::$extendStaticMethods[$method],
                $args
            );
            return $return ?? $this;
        }
        else if(isset(phpQuery::$pluginsStaticMethods[$method])) {
            $class = phpQuery::$pluginsStaticMethods[$method];
            $realClass = "phpQueryPlugin_$class";
            $return = call_user_func_array(
                array($realClass, $method),
                $args
            );
            return $return ?? $this;
        }
        throw new Exception("Method '$method' doesnt exist");
    }
}