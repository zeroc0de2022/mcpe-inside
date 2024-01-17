<?php
/*
Date: 14.01.2024
*/
declare(strict_types = 1);


namespace Api\Parser\phpQuery;


class CallbackParameterToReference extends Callback
{

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct(&$reference)
    {
        $this->callback =& $reference;
    }
}