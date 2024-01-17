<?php
/*
Date: 14.01.2024
*/
declare(strict_types = 1);



namespace Api\Parser\phpQuery;





class CallbackReturnReference extends Callback implements ICallbackNamed
{
    protected mixed $reference;

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct(&$reference)
    {
        $this->reference =& $reference;
        $this->callback = array($this, 'callback');
    }

    public function callback()
    {
        return $this->reference;
    }

    public function getName(): string
    {
        return 'Callback: '.$this->name;
    }

    public function hasName(): bool
    {
        return isset($this->name) && $this->name;
    }
}