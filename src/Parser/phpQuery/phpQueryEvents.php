<?php
/*
Date: 14.01.2024
*/
declare(strict_types = 1);


namespace Api\Parser\phpQuery;



use Api\Parser\phpQuery;
use Exception;

abstract class phpQueryEvents
{
    /**
     * @throws \Exception
     */
    public static function trigger($document, $type, $data = [], $node = null): void
    {
        // trigger: function(type, data, elem, donative, extra) {
        $documentID = phpQuery::getDocumentID($document);
        $namespace = null;
        if(str_contains($type, '.'))
            [$name, $namespace] = explode('.', $type);
        else
            $name = $type;
        if(!$node) {
            if(self::issetGlobal($documentID, $type)) {
                $pq = phpQuery::getDocument($documentID);
                // TODO check add($pq->document)
                $pq->find('*')->add($pq->document)
                    ->trigger($type, $data);
            }
        } else {
            if(isset($data[0]) && $data[0] instanceof DOMEvent) {
                $event = $data[0];
                $event->relatedTarget = $event->target;
                $event->target = $node;
                $data = array_slice($data, 1);
            } else {
                $event = new DOMEvent(array(
                    'type' => $type,
                    'target' => $node,
                    'timeStamp' => time(),
                ));
            }
            $i = 0;
            while($node) {
                // TODO whois
                phpQuery::debug("Triggering ".($i ? "bubbled " : '')."event '$type' on "
                    ."node \n");//.phpQueryObject::whois($node)."\n");
                $event->currentTarget = $node;
                $eventNode = self::getNode($documentID, $node);
                if(isset($eventNode->eventHandlers)) {
                    foreach($eventNode->eventHandlers as $eventType => $handlers) {
                        $eventNamespace = null;
                        if(str_contains($type, '.'))
                            [$eventName, $eventNamespace] = explode('.', $eventType);
                        else
                            $eventName = $eventType;
                        if($name != $eventName)
                            continue;
                        if($namespace && $eventNamespace && $namespace != $eventNamespace)
                            continue;
                        foreach($handlers as $handler) {
                            phpQuery::debug("Calling event handler\n");
                            $event->data = $handler['data'] ?? null;
                            $params = array_merge(array($event), $data);
                            $return = phpQuery::callbackRun($handler['callback'], $params);
                            if($return === false) {
                                $event->bubbles = false;
                            }
                        }
                    }
                }
                // to bubble or not to bubble...
                if(!$event->bubbles)
                    break;
                $node = $node->parentNode;
                $i++;
            }
        }
    }

    /**
     * @throws \Exception
     */
    public static function add($document, $node, $type, $data, $callback = null): void
    {
        phpQuery::debug("Binding '$type' event");
        $documentID = phpQuery::getDocumentID($document);

        $eventNode = self::getNode($documentID, $node);
        if(!$eventNode)
            $eventNode = self::setNode($documentID, $node);
        if(!isset($eventNode->eventHandlers[$type])) {
            $eventNode->eventHandlers[$type] = [];
        }

        $eventNode->eventHandlers[$type][] = array(
            'callback' => $callback,
            'data' => $data,
        );
    }

    /**
     * @throws \Exception
     */
    public static function remove($document, $node, $type = null, $callback = null): void
    {
        $documentID = phpQuery::getDocumentID($document);
        $eventNode = self::getNode($documentID, $node);
        if(!is_object($eventNode)) {
            throw new Exception("Event node for node not found");
        }
        if(isset($eventNode->eventHandlers[$type])) {
            if($callback) {
                foreach($eventNode->eventHandlers[$type] as $k => $handler)
                    if($handler['callback'] == $callback)
                        unset($eventNode->eventHandlers[$type][$k]);
            } else {
                unset($eventNode->eventHandlers[$type]);
            }
        }
    }

    /**
     * @throws \Exception
     */
    protected static function getNode($documentID, $node): string
    {
        foreach(phpQuery::$documents[$documentID]->eventsNodes as $eventNode) {
            if($node->isSameNode($eventNode)) {
                return $eventNode;
            }
        }
        throw new Exception("Event node for node not found");
    }

    protected static function setNode($documentID, $node)
    {
        phpQuery::$documents[$documentID]->eventsNodes[] = $node;
        return phpQuery::$documents[$documentID]->eventsNodes[count(phpQuery::$documents[$documentID]->eventsNodes) - 1];
    }

    protected static function issetGlobal($documentID, $type): bool
    {
        return isset(phpQuery::$documents[$documentID]) && in_array($type, phpQuery::$documents[$documentID]->eventsGlobal);
    }
}