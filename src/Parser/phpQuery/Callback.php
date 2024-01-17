<?php
/*
Date: 14.01.2024
*/
declare(strict_types = 1);

namespace Api\Parser\phpQuery;

class Callback implements ICallbackNamed
{
    public mixed $callback = null;
    public array $params;
    protected mixed $name;

    public function __construct($callback, $param1 = null, $param2 = null, $param3 = null)
    {
        $params = func_get_args();
        $params = array_slice($params, 1);
        if(!($callback instanceof Callback)) {
            $this->callback = $callback;
            $this->params = $params;
        }
    }

    public function getName(): string
    {
        return 'Callback: '.$this->name;
    }

    public function hasName(): bool
    {
        return isset($this->name) && $this->name;
    }

    public function setName($name): Callback
    {
        $this->name = $name;
        return $this;
    }
    // TODO test me
//	public function addParams() {
//		$params = func_get_args();
//		return new Callback($this->callback, $this->params+$params);
//	}
}