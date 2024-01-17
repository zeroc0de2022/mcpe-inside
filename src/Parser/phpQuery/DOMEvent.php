<?php
/*
Date: 14.01.2024
*/
declare(strict_types = 1);

namespace Api\Parser\phpQuery;

use DOMNode;

/**
 * DOMEvent class.
 *
 * Based on
 * @link http://developer.mozilla.org/En/DOM:event
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 * @package phpQuery
 * @todo implement ArrayAccess ?
 */
class DOMEvent
{
    public bool $bubbles = true;
    public DOMNode $currentTarget;
    public DOMEvent $relatedTarget;
    public DOMEvent $target;
    public int|null $timeStamp = null;
    public string|null $type = null;
    public bool $runDefault = true;
    public mixed $data;

    public function __construct($data)
    {
        foreach($data as $k => $v) {
            $this->$k = $v;
        }
        if(!$this->timeStamp) {
            $this->timeStamp = time();
        }
    }

    /**
     * Prevents the default action of the event.
     * @return void
     */
    public function preventDefault(): void
    {
        $this->runDefault = false;
    }

    /**
     * Stops the propagation of events further along in the DOM tree.
     * @return void
     */
    public function stopPropagation(): void
    {
        $this->bubbles = false;
    }
}