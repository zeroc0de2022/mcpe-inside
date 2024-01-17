<?php
/*
Date: 14.01.2024
*/
declare(strict_types = 1);

namespace Api\Parser\phpQuery;



interface ICallbackNamed
{
    function hasName();

    function getName();
}