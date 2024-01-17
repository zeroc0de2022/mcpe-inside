<?php
/*
Date: 14.01.2024
*/
declare(strict_types = 1);

namespace Api\Parser\phpQuery;




class CallbackReturnValue extends Callback implements ICallbackNamed
{

    protected CallbackReturnValue $value;
    protected mixed $name;

    public function __construct($value, $name = null)
    {
        $this->value =& $value;
        $this->name = $name;
        $this->callback = array($this, 'callback');
    }

    public function callback(): CallbackReturnValue
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->getName();
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