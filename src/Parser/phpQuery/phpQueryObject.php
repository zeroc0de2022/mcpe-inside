<?php
/*
Date: 14.01.2024
*/
declare(strict_types = 1);

namespace Api\Parser\phpQuery;



use Api\Parser\phpQuery;
use ArrayAccess;
use Closure;
use Countable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
use Exception;
use Iterator;
use function Api\Parser\pq;

/**
 * Class representing phpQuery objects.
 *
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 * @package phpQuery
 * @method phpQueryObject clone () clone ()
 * @method phpQueryObject empty() empty()
 * @property Int $length
 */
class phpQueryObject implements Iterator, Countable, ArrayAccess
{


    public function toReference(&$var): phpQueryObject
    {
        return $var = $this;
    }
    private int $length;

    public function documentFragment($state = null): phpQueryObject|bool
    {
        if($state) {
            phpQuery::$documents[$this->getDocumentID()]['documentFragment'] = $state;
            return $this;
        }
        return $this->documentFragment;
    }

    public string $documentID;
    public DOMDocument|DOMNode|null $document = null;
    public string $charset;
    public DOMDocumentWrapper $documentWrapper;
    public DOMXPath $xpath;
    public array $elements = [];
    protected array $elementsBackup = [];
    protected phpQueryObject $previous;
    protected mixed $root = null;
    public bool $documentFragment = true;
    protected array $elementsInterator = [];
    protected bool $valid = false;
    protected int $current;
    /**
     * @var mixed
     */
    private mixed $_loadSelector;

    /**
     * Create new phpQuery object.
     * @throws Exception
     */
    public function __construct($documentID)
    {
//		if ($documentID instanceof self)
//			var_dump($documentID->getDocumentID());
        $id = $documentID instanceof self ? $documentID->getDocumentID() : $documentID;
//		var_dump($id);
        if(!isset(phpQuery::$documents[$id])) {
//			var_dump(phpQuery::$documents);
            throw new Exception("Document with ID '$id' isn't loaded. Use phpQuery::newDocument(\$html) or phpQuery::newDocumentFile(\$file) first.");
        }
        $this->documentID = $id;
        $this->documentWrapper =& phpQuery::$documents[$id];
        $this->document =& $this->documentWrapper->document;
        $this->xpath =& $this->documentWrapper->xpath;
        $this->charset =& $this->documentWrapper->charset;
        $this->documentFragment =& $this->documentWrapper->isDocumentFragment;
        // TODO check $this->DOM->documentElement;
//		$this->root = $this->document->documentElement;
        $this->root =& $this->documentWrapper->root;
//		$this->toRoot();
        $this->elements = array($this->root);
    }
    public function __get($attr)
    {
        return match ($attr) {
            'length' => $this->size(),
            default => $this->$attr,
        };
    }
    protected function isRoot($node): bool
    {
//		return $node instanceof DOMDOCUMENT || $node->tagName == 'html';
        return $node instanceof DOMDOCUMENT
            || ($node instanceof DOMELEMENT && $node->tagName == 'html')
            || $this->root->isSameNode($node);
    }
    protected function stackIsRoot(): bool
    {
        return $this->size() == 1 && $this->isRoot($this->elements[0]);
    }
    public function toRoot(): phpQueryObject
    {
        $this->elements = array($this->root);
        return $this;
//		return $this->newInstance(array($this->root));
    }

    public function getDocumentIDRef(&$documentID): phpQueryObject
    {
        $documentID = $this->getDocumentID();
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function getDocument(): phpQueryObject
    {
        return phpQuery::getDocument($this->getDocumentID());
    }

    public function getDOMDocument(): DOMNode|DOMDocument|null
    {
        return $this->document;
    }

    public function getDocumentID()
    {
        return $this->documentID;
    }

    /**
     * @throws \Exception
     */
    public function unloadDocument(): void
    {
        phpQuery::unloadDocuments($this->getDocumentID());
    }

    public function isHTML(): bool
    {
        return $this->documentWrapper->isHTML;
    }

    public function isXHTML(): bool
    {
        return $this->documentWrapper->isXHTML;
    }

    public function isXML(): bool
    {
        return $this->documentWrapper->isXML;
    }

    /**
     * @throws \Exception
     */
    public function serialize(): string
    {
        return phpQuery::param($this->serializeArray());
    }

    /**
     * @throws \Exception
     */
    public function serializeArray($submit = null): array
    {
        $source = $this->filter('form, input, select, textarea')
            ->find('input, select, textarea')
            ->andSelf()
            ->not('form');
        $return = [];
//		$source->dumpDie();
        foreach($source as $input) {
            $input = phpQuery::pq($input);
            if($input->is('[disabled]'))
                continue;
            if(!$input->is('[name]'))
                continue;
            if($input->is('[type=checkbox]') && !$input->is('[checked]'))
                continue;
            // jquery diff
            if($submit && $input->is('[type=submit]')) {
                if($submit instanceof DOMELEMENT && !$input->elements[0]->isSameNode($submit))
                    continue;
                else if(is_string($submit) && $input->attr('name') != $submit)
                    continue;
            }
            $return[] = array(
                'name' => $input->attr('name'),
                'value' => $input->val(),
            );
        }
        return $return;
    }

    protected function debug($in): void
    {
        if(!phpQuery::$debug)
            return;
        print('<pre>');
        print_r($in);
        // file debug
//		file_put_contents(dirname(__FILE__).'/phpQuery.log', print_r($in, true)."\n", FILE_APPEND);
        // quite handy debug trace
//		if ( is_array($in))
//			print_r(array_slice(debug_backtrace(), 3));
        print("</pre>\n");
    }

    protected function isRegexp($pattern): bool
    {
        return in_array(
            $pattern[mb_strlen($pattern) - 1],
            array('^', '*', '$')
        );
    }

    protected function isChar($char): bool|int
    {
        return extension_loaded('mbstring') && phpQuery::$mbstringSupport
            ? mb_eregi('\w', $char)
            : preg_match('@\w@', $char);
    }

    protected function parseSelector($query): array
    {
        // clean spaces
        // TODO include this inside parsing ?
        $query = trim(
            preg_replace('@\s+@', ' ',
                preg_replace('@\s*([>+~])\s*@', '\\1', $query)
            )
        );
        $queries = array(array());
        if(!$query)
            return $queries;
        $return =& $queries[0];
        $specialChars = array('>', ' ');
        $strlen = mb_strlen($query);
        $classChars = array('.', '-');
        $pseudoChars = array('-');
        $tagChars = array('*', '|', '-');
        // split multibyte string
        $_query = [];
        for($i = 0; $i < $strlen; $i++)
            $_query[] = mb_substr($query, $i, 1);
        $query = $_query;
        $i = 0;
        while($i < $strlen) {
            $c = $query[$i];
            $tmp = '';
            // TAG
            if($this->isChar($c) || in_array($c, $tagChars)) {
                while(isset($query[$i]) && ($this->isChar($query[$i]) || in_array($query[$i], $tagChars))) {
                    $tmp .= $query[$i];
                    $i++;
                }
                $return[] = $tmp;
                // IDs
            } else if($c == '#') {
                $i++;
                while(isset($query[$i]) && ($this->isChar($query[$i]) || $query[$i] == '-')) {
                    $tmp .= $query[$i];
                    $i++;
                }
                $return[] = '#'.$tmp;
                // SPECIAL CHARS
            } else if(in_array($c, $specialChars)) {
                $return[] = $c;
                $i++;
                // MAPPED SPECIAL MULTICHARS
//			} else if ( $c.$query[$i+1] == '//') {
//				$return[] = ' ';
//				$i = $i+2;
                // MAPPED SPECIAL CHARS
            } else if($c == ',') {
                $queries[] = [];
                $return =& $queries[count($queries) - 1];
                $i++;
                while(isset($query[$i]) && $query[$i] == ' ')
                    $i++;
                // CLASSES
            } else if($c == '.') {
                while(isset($query[$i]) && ($this->isChar($query[$i]) || in_array($query[$i], $classChars))) {
                    $tmp .= $query[$i];
                    $i++;
                }
                $return[] = $tmp;
                // ~ General Sibling Selector
            } else if($c == '~') {
                $spaceAllowed = true;
                $tmp .= $query[$i++];
                while(isset($query[$i])
                    && ($this->isChar($query[$i])
                        || in_array($query[$i], $classChars)
                        || $query[$i] == '*'
                        || ($query[$i] == ' ' && $spaceAllowed)
                    )) {
                    if($query[$i] != ' ')
                        $spaceAllowed = false;
                    $tmp .= $query[$i];
                    $i++;
                }
                $return[] = $tmp;
                // + Adjacent sibling selectors
            } else if($c == '+') {
                $spaceAllowed = true;
                $tmp .= $query[$i++];
                while(isset($query[$i])
                    && ($this->isChar($query[$i])
                        || in_array($query[$i], $classChars)
                        || $query[$i] == '*'
                        || ($spaceAllowed && $query[$i] == ' ')
                    )) {
                    if($query[$i] != ' ')
                        $spaceAllowed = false;
                    $tmp .= $query[$i];
                    $i++;
                }
                $return[] = $tmp;
                // ATTRS
            }
            else if($c == '[') {
                $stack = 1;
                $tmp .= $c;
                while(isset($query[++$i])) {
                    $tmp .= $query[$i];
                    if($query[$i] == '[') {
                        $stack++;
                    } else if($query[$i] == ']') {
                        $stack--;
                        if(!$stack)
                            break;
                    }
                }
                $return[] = $tmp;
                $i++;
                // PSEUDO CLASSES
            } else if($c == ':') {
                $stack = 1;
                $tmp .= $query[$i++];
                while(isset($query[$i]) && ($this->isChar($query[$i]) || in_array($query[$i], $pseudoChars))) {
                    $tmp .= $query[$i];
                    $i++;
                }
                // with arguments ?
                if(isset($query[$i]) && $query[$i] == '(') {
                    $tmp .= $query[$i];
                    while(isset($query[++$i])) {
                        $tmp .= $query[$i];
                        if($query[$i] == '(') {
                            $stack++;
                        } else if($query[$i] == ')') {
                            $stack--;
                            if(!$stack)
                                break;
                        }
                    }
                    $return[] = $tmp;
                    $i++;
                } else {
                    $return[] = $tmp;
                }
            } else {
                $i++;
            }
        }
        foreach($queries as $k => $q) {
            if(isset($q[0])) {
                if(isset($q[0][0]) && $q[0][0] == ':')
                    array_unshift($queries[$k], '*');
                if($q[0] != '>')
                    array_unshift($queries[$k], ' ');
            }
        }
        return $queries;
    }

    public function get($index = null, $callback1 = null, $callback2 = null, $callback3 = null)
    {
        $return = isset($index)
            ? ($this->elements[$index] ?? null)
            : $this->elements;
        // pass thou callbacks
        $args = func_get_args();
        $args = array_slice($args, 1);
        foreach($args as $callback) {
            if(is_array($return))
                foreach($return as $k => $v)
                    $return[$k] = phpQuery::callbackRun($callback, array($v));
            else
                $return = phpQuery::callbackRun($callback, array($return));
        }
        return $return;
    }

    /**
     * @throws \Exception
     */
    public function getString($index = null, $callback1 = null, $callback2 = null, $callback3 = null)
    {
        if($index)
            $return = $this->eq($index)->text();
        else {
            $return = [];
            for($i = 0; $i < $this->size(); $i++) {
                $return[] = $this->eq($i)->text();
            }
        }
        // pass thou callbacks
        $args = func_get_args();
        $args = array_slice($args, 1);
        foreach($args as $callback) {
            $return = phpQuery::callbackRun($callback, array($return));
        }
        return $return;
    }

    /**
     * @throws \Exception
     */
    public function getStrings($index = null, $callback1 = null, $callback2 = null, $callback3 = null)
    {
        $args = [];
        if($index) {
            $return = $this->eq($index)->text();
        } else {
            $return = [];
            for($i = 0; $i < $this->size(); $i++) {
                $return[] = $this->eq($i)->text();
            }
            // pass thou callbacks
            $args = func_get_args();
            $args = array_slice($args, 1);
        }
        foreach($args as $callback) {
            if(is_array($return))
                foreach($return as $k => $v)
                    $return[$k] = phpQuery::callbackRun($callback, array($v));
            else
                $return = phpQuery::callbackRun($callback, array($return));
        }
        return $return;
    }

    /**
     * @throws \Exception
     */
    public function newInstance($newStack = null)
    {
        $class = get_class($this);
        // support inheritance by passing old object to overloaded constructor
        $new = $class != 'phpQuery'
            ? new $class($this, $this->getDocumentID())
            : new phpQueryObject($this->getDocumentID());
        $new->previous = $this;
        if(is_null($newStack)) {
            $new->elements = $this->elements;
            if($this->elementsBackup)
                $this->elements = $this->elementsBackup;
        } else if(is_string($newStack)) {
            $new->elements = phpQuery::pq($newStack, $this->getDocumentID())->stack();
        } else {
            $new->elements = $newStack;
        }
        return $new;
    }

    protected function matchClasses($class, $node): bool
    {
        // multi-class
        if(mb_strpos($class, '.', 1)) {
            $classes = explode('.', substr($class, 1));
            $classesCount = count($classes);
            $nodeClasses = explode(' ', $node->getAttribute('class'));
            $nodeClassesCount = count($nodeClasses);
            if($classesCount > $nodeClassesCount)
                return false;
            $diff = count(
                array_diff(
                    $classes,
                    $nodeClasses
                )
            );
            if(!$diff)
                return true;
            // single-class
        } else {
            return in_array(
            // strip leading dot from class name
                substr($class, 1),
                // get classes for element as array
                explode(' ', $node->getAttribute('class'))
            );
        }
        return false;
    }

    /**
     * @throws \Exception
     */
    protected function runQuery($XQuery, $selector = null, $compare = null): void
    {
        if($compare && !method_exists($this, $compare))
            throw new Exception("Method '$compare' doesn't exist");
        $stack = [];
        if(!$this->elements)
            $this->debug('Stack empty, skipping...');
//		var_dump($this->elements[0]->nodeType);
        // element, document
        foreach($this->stack(array(1, 9, 13)) as $k => $stackNode) {
            unset($k);
            $detachAfter = false;
            // to work on detached nodes we need temporary place them somewhere
            $testNode = $stackNode;
            while($testNode) {
                if(!$testNode->parentNode && !$this->isRoot($testNode)) {
                    $this->root->appendChild($testNode);
                    $detachAfter = $testNode;
                    break;
                }
                $testNode = $testNode->parentNode ?? null;
            }
            // XXX tmp ?
            $xpath = $this->getNodeXpath($stackNode);
            // FIXME pseudoclasses-only query, support XML
            $query = $XQuery == '//' && $xpath == '/html[1]'
                ? '//*'
                : $xpath.$XQuery;
            $this->debug("XPATH: $query");
            // run query, get elements
            $nodes = $this->xpath->query($query);
            $this->debug("QUERY FETCHED");
            if(!$nodes->length)
                $this->debug('Nothing found');
            $debug = [];
            foreach($nodes as $node) {
                $matched = false;
                if($compare) {
                    if(phpQuery::$debug) {
                        $this->debug("Found: ".$this->whois($node).", comparing with $compare()");
                    }
                    // TODO ??? use phpQuery::callbackRun()
                    if(call_user_func_array(array($this, $compare), array($selector, $node))) {
                        $matched = true;
                    }
                } else {
                    $matched = true;
                }
                if($matched) {
                    if(phpQuery::$debug) {
                        $debug[] = $this->whois($node);
                    }
                    $stack[] = $node;
                }
            }
            if(phpQuery::$debug) {
                $this->debug("Matched ".count($debug).": ".implode(', ', $debug));
            }
            if($detachAfter)
                $this->root->removeChild($detachAfter);
        }
        $this->elements = $stack;
    }

    /**
     * @throws \Exception
     */
    public function find($selectors, $context = null, $noHistory = false): phpQueryObject
    {
        if(!$noHistory)
            // backup last stack /for end()/
            $this->elementsBackup = $this->elements;
        // allow to define context
        // TODO combine code below with phpQuery::pq() context guessing code
        //   as generic function
        if($context) {
            if(!is_array($context) && $context instanceof DOMELEMENT)
                $this->elements = array($context);
            else if(is_array($context)) {
                $this->elements = [];
                foreach($context as $c)
                    if($c instanceof DOMELEMENT)
                        $this->elements[] = $c;
            } else if($context instanceof self)
                $this->elements = $context->elements;
        }
        $queries = $this->parseSelector($selectors);
        $this->debug(array('FIND', $selectors, $queries));
        $XQuery = '';
        // remember stack state because of multi-queries
        $oldStack = $this->elements;
        // here we will be keeping found elements
        $stack = [];
        foreach($queries as $selector) {
            $this->elements = $oldStack;
            $delimiterBefore = false;
            foreach($selector as $s) {
                // TAG
                $pattern = ['@^[\w|\||-]+$@'];

                $isTag = extension_loaded('mbstring') && phpQuery::$mbstringSupport
                    ? mb_ereg_match('^[\w|\||-]+$', $s) || $s == '*'
                    : preg_match(implode('', $pattern), $s) || $s == '*';
                if($isTag) {
                    if($this->isXML()) {
                        // namespace support
                        if(mb_strpos($s, '|') !== false) {
                            [$ns, $tag] = explode('|', $s);
                            $XQuery .= "$ns:$tag";
                        } else if($s == '*') {
                            $XQuery .= "*";
                        } else {
                            $XQuery .= "*[local-name()='$s']";
                        }
                    } else {
                        $XQuery .= $s;
                    }
                    // ID
                } else if($s[0] == '#') {
                    if($delimiterBefore)
                        $XQuery .= '*';
                    $XQuery .= "[@id='".substr($s, 1)."']";
                    // ATTRIBUTES
                } else if($s[0] == '[') {
                    if($delimiterBefore)
                        $XQuery .= '*';
                    // strip side brackets
                    $attr = trim($s, '][');
                    $execute = false;
                    // attr with specifed value
                    if(mb_strpos($s, '=')) {
                        [$attr, $value] = explode('=', $attr);
                        $value = trim($value, "'\"");
                        if($this->isRegexp($attr)) {
                            // cut regexp character
                            $attr = substr($attr, 0, -1);
                            $execute = true;
                            $XQuery .= "[@$attr]";
                        } else {
                            $XQuery .= "[@$attr='$value']";
                        }
                        // attr without specified value
                    } else {
                        $XQuery .= "[@$attr]";
                    }
                    if($execute) {
                        $this->runQuery($XQuery, $s, 'is');
                        $XQuery = '';
                        if(!$this->length())
                            break;
                    }
                    // CLASSES
                } else if($s[0] == '.') {
                    // TODO use return $this->find("./self::*[contains(concat(\" \",@class,\" \"), \" $class \")]");
                    // thx wizDom ;)
                    if($delimiterBefore)
                        $XQuery .= '*';
                    $XQuery .= '[@class]';
                    $this->runQuery($XQuery, $s, 'matchClasses');
                    $XQuery = '';
                    if(!$this->length())
                        break;
                    // ~ General Sibling Selector
                } else if($s[0] == '~') {
                    $this->runQuery($XQuery);
                    $XQuery = '';
                    $this->elements = $this
                        ->siblings(
                            substr($s, 1)
                        )->elements;
                    if(!$this->length())
                        break;
                    // + Adjacent sibling selectors
                } else if($s[0] == '+') {
                    // TODO /following-sibling::
                    $this->runQuery($XQuery);
                    $XQuery = '';
                    $subSelector = substr($s, 1);
                    $subElements = $this->elements;
                    $this->elements = [];
                    foreach($subElements as $node) {
                        // search first DOMElement sibling
                        $test = $node->nextSibling;
                        while($test && !($test instanceof DOMELEMENT))
                            $test = $test->nextSibling;
                        if($test && $this->is($subSelector, $test))
                            $this->elements[] = $test;
                    }
                    if(!$this->length())
                        break;
                    // PSEUDO CLASSES
                } else if($s[0] == ':') {
                    // TODO optimization for :first :last
                    if($XQuery) {
                        $this->runQuery($XQuery);
                        $XQuery = '';
                    }
                    if(!$this->length())
                        break;
                    $this->pseudoClasses($s);
                    if(!$this->length())
                        break;
                    // DIRECT DESCENDANDS
                } else if($s == '>') {
                    $XQuery .= '/';
                    $delimiterBefore = 2;
                    // ALL DESCENDANDS
                } else if($s == ' ') {
                    $XQuery .= '//';
                    $delimiterBefore = 2;
                    // ERRORS
                } else {
                    phpQuery::debug("Unrecognized token '$s'");
                }
                $delimiterBefore = $delimiterBefore === 2;
            }
            // run query if any
            if($XQuery && $XQuery != '//') {
                $this->runQuery($XQuery);
                $XQuery = '';
            }
            foreach($this->elements as $node)
                if(!$this->elementsContainsNode($node, $stack))
                    $stack[] = $node;
        }
        $this->elements = $stack;
        return $this->newInstance();
    }

    /**
     * @throws \Exception
     */
    protected function pseudoClasses($class): void
    {
        $args = 0;
        // TODO clean args parsing ?
        $class = ltrim($class, ':');
        $haveArgs = mb_strpos($class, '(');
        if($haveArgs !== false) {
            $args = substr($class, $haveArgs + 1, -1);
            $class = substr($class, 0, $haveArgs);
        }

        switch($class) {
            case 'even':
            case 'odd':
                $stack = [];
                foreach($this->elements as $i => $node) {
                    if($class == 'even' && ($i % 2) == 0)
                        $stack[] = $node;
                    else if($class == 'odd' && $i % 2)
                        $stack[] = $node;
                }
                $this->elements = $stack;
                break;
            case 'eq':
                $k = intval($args);
                $this->elements = isset($this->elements[$k])
                    ? array($this->elements[$k])
                    : [];
                break;
            case 'gt':
                $this->elements = array_slice($this->elements, $args + 1);
                break;
            case 'lt':
                $this->elements = array_slice($this->elements, 0, $args + 1);
                break;
            case 'first':
                if(isset($this->elements[0]))
                    $this->elements = array($this->elements[0]);
                break;
            case 'last':
                if($this->elements)
                    $this->elements = array($this->elements[count($this->elements) - 1]);
                break;
            case 'contains':
                $text = trim($args, "\"'");
                $stack = [];
                foreach($this->elements as $node) {
                    if(mb_stripos($node->textContent, $text) === false)
                        continue;
                    $stack[] = $node;
                }
                $this->elements = $stack;
                break;
            case 'not':
                $selector = self::unQuote($args);
                $this->elements = $this->not($selector)->stack();
                break;
            case 'slice':
                // TODO jQuery difference ?
                $args = explode(',',
                    str_replace(', ', ',', trim($args, "\"'"))
                );
                $start = $args[0];
                $end = $args[1] ?? null;
                if($end > 0)
                    $end = $end - $start;
                $this->elements = array_slice($this->elements, (int)$start, $end);
                break;
            case 'has':
                $selector = trim($args, "\"'");
                $stack = [];
                foreach($this->stack(1) as $el) {
                    if($this->find($selector, $el, true)->count())
                        $stack[] = $el;
                }
                $this->elements = $stack;
                break;
            case 'submit':
            case 'reset':
                $this->elements = phpQuery::merge(
                    $this->map(array($this, 'is'),
                        "input[type=$class]", new CallbackParam()
                    ),
                    $this->map(array($this, 'is'),
                        "button[type=$class]", new CallbackParam()
                    )
                );
                break;
            case 'input':
                $this->elements = $this->map(
                    array($this, 'is'),
                    'input', new CallbackParam()
                )->elements;
                break;
            case 'password':
            case 'checkbox':
            case 'radio':
            case 'hidden':
            case 'image':
            case 'file':
                $this->elements = $this->map(
                    array($this, 'is'),
                    "input[type=$class]", new CallbackParam()
                )->elements;
                break;
            case 'parent':
                $this->elements = $this->map(
                    function($node) {
                        return $node instanceof DOMELEMENT && $node->childNodes->length ? $node : null;
                    }
                )->elements;
                break;
            case 'empty':
                $this->elements = $this->map(
                    function($node) {
                        return $node instanceof DOMELEMENT && $node->childNodes->length ? null : $node;
                    }
                )->elements;
                break;
            case 'disabled':
            case 'selected':
            case 'checked':
                $this->elements = $this->map(
                    array($this, 'is'),
                    "[$class]", new CallbackParam()
                )->elements;
                break;
            case 'enabled':
                $this->elements = $this->map(
                    function($node) {
                        return pq($node)->not(":disabled") ? $node : null;
                    }
                )->elements;
                break;
            case 'header':
                $this->elements = $this->map(
                    function($node) {
                        $isHeader = isset($node->tagName)
                            && in_array($node->tagName, array("h1", "h2", "h3", "h4", "h5", "h6", "h7"));
                        return $isHeader ? $node : null;
                    }
                )->elements;
                break;
            case 'only-child':
                $this->elements = $this->map(
                    function($node) {
                        return pq($node)->siblings()->size() == 0 ? $node : null;
                    }
                )->elements;
                break;
            case 'first-child':
                $this->elements = $this->map(
                    function($node) {
                        return pq($node)->prevAll()->size() == 0 ? $node : null;
                    }
                )->elements;
                break;
            case 'last-child':
                $this->elements = $this->map(
                    function($node) {
                        return pq($node)->nextAll()->size() == 0 ? $node : null;
                    }
                )->elements;
                break;
            case 'nth-child':
                $param = trim($args, "\"'");
                if(!$param)
                    break;
                // nth-child(n+b) to nth-child(1n+b)
                if($param[0] == 'n')
                    $param = '1'.$param;
                // :nth-child(index/even/odd/equation)
                if($param == 'even' || $param == 'odd')
                    $mapped = $this->map(
                        function($node, $param) {
                            $index = pq($node)->prevAll()->size() + 1;
                            if($param == "even" && ($index % 2) == 0)
                                return $node;
                            else if($param == "odd" && $index % 2 == 1)
                                return $node;
                            else
                                return null;
                        },
                        new CallbackParam(), $param
                    );
                else if(mb_strlen($param) > 1 && $param[1] == 'n')
                    // an+b
                    $mapped = $this->map(
                        function($node, $param) {
                            $prevs = pq($node)->prevAll()->size();
                            $index = 1 + $prevs;
                            $b = mb_strlen($param) > 3 ? $param[3] : 0;
                            $a = $param[0];
                            if($b && $param[2] == "-")
                                $b = -$b;
                            if($a > 0) {
                                return ($index - $b) % $a == 0 ? $node : null;
                                //return $a*floor($index/$a)+$b-1 == $prevs  ? $node  : null;
                            } else if($a == 0)
                                return $index == $b ? $node : null;
                            else
                                // negative value
                                return $index <= $b ? $node : null;
                        },
                        new CallbackParam(), $param
                    );
                else
                    // index
                    $mapped = $this->map(
                        function($node, $index) {
                            $prevs = pq($node)->prevAll()->size();
                            if($prevs && $prevs == $index - 1)
                                return $node;
                            else if(!$prevs && $index == 1)
                                return $node;
                            else
                                return null;
                        },
                        new CallbackParam(), $param
                    );
                $this->elements = $mapped->elements;
                break;
            default:
                $this->debug("Unknown pseudoclass '$class', skipping...");
        }
    }

    /**
     * @throws \Exception
     */
    public function is($selector, $nodes = null): bool|array|null
    {
        phpQuery::debug(array("Is:", $selector));
        if(!$selector) {
            return false;
        }
        $oldStack = $this->elements;
        if($nodes && is_array($nodes)) {
            $this->elements = $nodes;
        } else if($nodes) {
            $this->elements = array($nodes);
        }

        $this->filter($selector, true);
        $stack = $this->elements;
        $this->elements = $oldStack;
        if($nodes) {
            return $stack;
        }
        return (bool)count($stack);
    }

    /**
     * @throws \Exception
     */
    public function filterCallback($callback, $_skipHistory = false)
    {
        if(!$_skipHistory) {
            $this->elementsBackup = $this->elements;
            $this->debug("Filtering by callback");
        }
        $newStack = [];
        foreach($this->elements as $index => $node) {
            $result = phpQuery::callbackRun($callback, array($index, $node));
            if($result)
                $newStack[] = $node;
        }
        $this->elements = $newStack;
        return $_skipHistory
            ? $this
            : $this->newInstance();
    }

    /**
     * @throws \Exception
     */
    public function filter($selectors, $_skipHistory = false)
    {

        $isInstance = function($selector, $const) {
            return $selector instanceof $const;
        };
        //if ($selectors instanceof Callback OR $selectors instanceof Closure)
        if($isInstance($selectors, Callback::class) or $isInstance($selectors, Closure::class)) {
            return $this->filterCallback($selectors, $_skipHistory);
        }
        if(!$_skipHistory)
            $this->elementsBackup = $this->elements;
        $notSimpleSelector = array(' ', '>', '~', '+', '/');
        if(!is_array($selectors))
            $selectors = $this->parseSelector($selectors);
        if(!$_skipHistory)
            $this->debug(array("Filtering:", $selectors));
        $finalStack = [];
        foreach($selectors as $selector) {
            $stack = [];
            if(!$selector)
                break;
            // avoid first space or /
            if(in_array($selector[0], $notSimpleSelector))
                $selector = array_slice($selector, 1);
            // PER NODE selector chunks
            foreach($this->stack() as $node) {
                $break = false;
                foreach($selector as $s) {
                    if(!($node instanceof DOMELEMENT)) {
                        // all besides DOMElement
                        if($s[0] == '[') {
                            $attr = trim($s, '[]');
                            if(mb_strpos($attr, '=')) {
                                list($attr, $val) = explode('=', $attr);
                                if($attr == 'nodeType' && $node->nodeType != $val)
                                    $break = true;
                            }
                        } else {
                            $break = true;
                        }

                    } else {
                        // DOMElement only
                        // ID
                        if($s[0] == '#') {
                            if($node->getAttribute('id') != substr($s, 1)) {
                                $break = true;
                            }
                            // CLASSES
                        } else if($s[0] == '.') {
                            if(!$this->matchClasses($s, $node)) {
                                $break = true;
                            }
                            // ATTRS
                        } else if($s[0] == '[') {
                            // strip side brackets
                            $attr = trim($s, '[]');
                            if(mb_strpos($attr, '=')) {
                                list($attr, $val) = explode('=', $attr);
                                $val = self::unQuote($val);
                                if($attr == 'nodeType') {
                                    if($val != $node->nodeType)
                                        $break = true;
                                } else if($this->isRegexp($attr)) {
                                    $val = extension_loaded('mbstring') && phpQuery::$mbstringSupport
                                        ? quotemeta(trim($val, '"\''))
                                        : preg_quote(trim($val, '"\''), '@');
                                    // switch last character
                                    // quotemeta used insted of preg_quote
                                    // http://code.google.com/p/phpquery/issues/detail?id=76
                                    $pattern = match (substr($attr, -1)) {
                                        '^' => '^'.$val,
                                        '*' => '.*'.$val.'.*',
                                        '$' => '.*'.$val.'$',
                                        default => $val
                                    };
                                    // cut last character
                                    $attr = substr($attr, 0, -1);
                                    $isMatch = extension_loaded('mbstring') && phpQuery::$mbstringSupport
                                        ? mb_ereg_match($pattern, $node->getAttribute($attr))
                                        : preg_match("@$pattern@", $node->getAttribute($attr));
                                    if(!$isMatch)
                                        $break = true;
                                } else if($node->getAttribute($attr) != $val)
                                    $break = true;
                            } else if(!$node->hasAttribute($attr))
                                $break = true;
                            // PSEUDO CLASSES
                        } else if($s[0] == ':') {
                            continue;
                        } else if(trim($s)) {
                            if($s != '*') {
                                // TODO namespaces
                                if(isset($node->tagName)) {
                                    if($node->tagName != $s)
                                        $break = true;
                                } else if($s == 'html' && !$this->isRoot($node))
                                    $break = true;
                            }
                            // AVOID NON-SIMPLE SELECTORS
                        } else if(in_array($s, $notSimpleSelector)) {
                            $break = true;
                            $this->debug(array('Skipping non simple selector', $selector));
                        }
                    }
                    if($break)
                        break;
                }
                // if element passed all chunks of selector - add it to new stack
                if(!$break)
                    $stack[] = $node;
            }
            $tmpStack = $this->elements;
            $this->elements = $stack;
            // PER ALL NODES selector chunks
            foreach($selector as $s)
                // PSEUDO CLASSES
                if($s[0] == ':')
                    $this->pseudoClasses($s);
            foreach($this->elements as $node)
                // XXX it should be merged without duplicates
                // but jQuery doesnt do that
                $finalStack[] = $node;
            $this->elements = $tmpStack;
        }
        $this->elements = $finalStack;
        if($_skipHistory) {
            return $this;
        } else {
            $this->debug("Stack length after filter(): ".count($finalStack));
            return $this->newInstance();
        }
    }

    protected static function unQuote($value): string
    {
        return $value[0] == '\'' || $value[0] == '"'
            ? substr($value, 1, -1)
            : $value;
    }

    /**
     * @throws Exception
     */
    public function load($url, $data = null, $callback = null): phpQueryObject
    {
        if($data && !is_array($data)) {
            $callback = $data;
            $data = null;
        }
        if(mb_strpos($url, ' ') !== false) {
            $matches = null;
            if(extension_loaded('mbstring') && phpQuery::$mbstringSupport) {
                mb_ereg('^([^ ]+) (.*)$', $url, $matches);
            } else {
                $pattern = ['([^', ' ]+)', ' (.', '*)'];
                preg_match('^'.implode('', $pattern).'$', $url, $matches);
            }

            $url = $matches[1];
            $selector = $matches[2];
            $this->_loadSelector = $selector;
        }
        $ajax = array(
            'url' => $url,
            'type' => $data ? 'POST' : 'GET',
            'data' => $data,
            'complete' => $callback,
            'success' => array($this, '__loadSuccess')
        );
        phpQuery::ajax($ajax);
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function __loadSuccess($html): void
    {
        if($this->_loadSelector) {
            $html = phpQuery::newDocument($html)->find($this->_loadSelector);
            unset($this->_loadSelector);
        }
        foreach($this->stack(1) as $node) {
            phpQuery::pq($node, $this->getDocumentID())
                ->markup($html);
        }
    }

    public function css(): phpQueryObject
    {
        // TODO
        return $this;
    }

    public function show(): phpQueryObject
    {
        // TODO
        return $this;
    }

    public function hide(): phpQueryObject
    {
        // TODO
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function trigger($type, $data = []): phpQueryObject
    {
        foreach($this->elements as $node)
            phpQueryEvents::trigger($this->getDocumentID(), $type, $data, $node);
        return $this;
    }

    public function triggerHandler($type, $data = [])
    {
        // TODO;
    }

    /**
     * @throws \Exception
     */
    public function bind($type, $data, $callback = null): phpQueryObject
    {
        if(!isset($callback)) {
            $callback = $data;
            $data = null;
        }
        foreach($this->elements as $node)
            phpQueryEvents::add($this->getDocumentID(), $node, $type, $data, $callback);
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function unbind($type = null, $callback = null): phpQueryObject
    {
        foreach($this->elements as $node)
            phpQueryEvents::remove($this->getDocumentID(), $node, $type, $callback);
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function change($callback = null): phpQueryObject
    {
        return ($callback)
            ? $this->bind('change', $callback)
            : $this->trigger('change');
    }

    /**
     * @throws \Exception
     */
    public function submit($callback = null): phpQueryObject
    {
        if($callback)
            return $this->bind('submit', $callback);
        return $this->trigger('submit');
    }

    /**
     * @throws \Exception
     */
    public function click($callback = null): phpQueryObject
    {
        if($callback)
            return $this->bind('click', $callback);
        return $this->trigger('click');
    }

    public function wrapAllOld($wrapper): phpQueryObject
    {
        $wrapper = pq($wrapper)->_clone();
        if(!$wrapper->length() || !$this->length())
            return $this;
        $wrapper->insertBefore($this->elements[0]);
        $deepest = $wrapper->elements[0];
        while($deepest->firstChild instanceof DOMELEMENT)
            $deepest = $deepest->firstChild;
        pq($deepest)->append($this);
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function wrapAll($wrapper)
    {
        if(!$this->length())
            return $this;
        return phpQuery::pq($wrapper, $this->getDocumentID())
            ->clone()
            ->insertBefore($this->get(0))
            ->map(array($this, '___wrapAllCallback'))
            ->append($this);
    }

    public function ___wrapAllCallback($node): DOMELEMENT
    {
        $deepest = $node;
        while($deepest->firstChild instanceof DOMELEMENT)
            $deepest = $deepest->firstChild;
        return $deepest;
    }

    /**
     * @throws \Exception
     */
    public function wrapAllPHP($codeBefore, $codeAfter)
    {
        return $this
            ->slice(0, 1)
            ->beforePHP($codeBefore)
            ->end()
            ->slice(-1)
            ->afterPHP($codeAfter)
            ->end();
    }

    /**
     * @throws Exception
     */
    public function wrap($wrapper): phpQueryObject
    {
        foreach($this->stack() as $node)
            phpQuery::pq($node, $this->getDocumentID())->wrapAll($wrapper);
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function wrapPHP($codeBefore, $codeAfter): phpQueryObject
    {
        foreach($this->stack() as $node)
            phpQuery::pq($node, $this->getDocumentID())->wrapAllPHP($codeBefore, $codeAfter);
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function wrapInner($wrapper): phpQueryObject
    {
        foreach($this->stack() as $node)
            phpQuery::pq($node, $this->getDocumentID())->contents()->wrapAll($wrapper);
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function wrapInnerPHP($codeBefore, $codeAfter): phpQueryObject
    {
        foreach($this->stack(1) as $node)
            phpQuery::pq($node, $this->getDocumentID())->contents()
                ->wrapAllPHP($codeBefore, $codeAfter);
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function contents()
    {
        $stack = [];
        foreach($this->stack(1) as $el) {
            // FIXME (fixed) http://code.google.com/p/phpquery/issues/detail?id=56
//			if (! isset($el->childNodes))
//				continue;
            foreach($el->childNodes as $node) {
                $stack[] = $node;
            }
        }
        return $this->newInstance($stack);
    }

    public function contentsUnwrap(): phpQueryObject
    {
        foreach($this->stack(1) as $node) {
            if(!$node->parentNode)
                continue;
            $childNodes = [];
            // any modification in DOM tree breaks childNodes iteration, so cache them first
            foreach($node->childNodes as $chNode)
                $childNodes[] = $chNode;
            foreach($childNodes as $chNode)
//				$node->parentNode->appendChild($chNode);
                $node->parentNode->insertBefore($chNode, $node);
            $node->parentNode->removeChild($node);
        }
        return $this;
    }

    public function switchWith($markup): phpQueryObject
    {
        $markup = pq($markup, $this->getDocumentID());
        $content = null;
        foreach($this->stack(1) as $node) {
            pq($node)
                ->contents()->toReference($content)->end()
                ->replaceWith($markup->clone()->append($content));
        }
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function eq($num)
    {
        $oldStack = $this->elements;
        $this->elementsBackup = $this->elements;
        $this->elements = [];
        if(isset($oldStack[$num]))
            $this->elements[] = $oldStack[$num];
        return $this->newInstance();
    }

    public function size(): int
    {
        return count($this->elements);
    }

    public function length(): int
    {
        return $this->size();
    }

    public function count(): int
    {
        return $this->size();
    }

    public function end(): phpQueryObject
    {
//		$this->elements = array_pop( $this->history );
//		return $this;
//		$this->previous->DOM = $this->DOM;
//		$this->previous->XPath = $this->XPath;
        return $this->previous ?? $this;
    }

    /**
     * @throws \Exception
     */
    public function _clone()
    {
        $newStack = [];
        //pr(array('copy... ', $this->whois()));
        //$this->dumpHistory('copy');
        $this->elementsBackup = $this->elements;
        foreach($this->elements as $node) {
            $newStack[] = $node->cloneNode(true);
        }
        $this->elements = $newStack;
        return $this->newInstance();
    }

    /**
     * @throws \Exception
     */
    public function replaceWithPHP($code): phpQueryObject
    {
        return $this->replaceWith(phpQuery::php($code));
    }

    /**
     * @throws \Exception
     */
    public function replaceWith($content): phpQueryObject
    {
        return $this->after($content)->remove();
    }

    /**
     * @throws \Exception
     */
    public function replaceAll($selector): phpQueryObject
    {
        foreach(phpQuery::pq($selector, $this->getDocumentID()) as $node)
            phpQuery::pq($node, $this->getDocumentID())
                ->after($this->_clone())
                ->remove();
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function remove($selector = null): phpQueryObject
    {
        $loop = $selector
            ? $this->filter($selector)->elements
            : $this->elements;
        foreach($loop as $node) {
            if(!$node->parentNode)
                continue;
            if(isset($node->tagName))
                $this->debug("Removing ".$node->tagName);
            $node->parentNode->removeChild($node);
            // Mutation event
            $event = new DOMEvent(array(
                'target' => $node,
                'type' => 'DOMNodeRemoved'
            ));
            phpQueryEvents::trigger($this->getDocumentID(),
                $event->type, array($event), $node
            );
        }
        return $this;
    }

    /**
     * @throws \Exception
     */
    protected function markupEvents($newMarkup, $oldMarkup, $node): void
    {
        if($node->tagName == 'textarea' && $newMarkup != $oldMarkup) {
            $event = new DOMEvent(array(
                'target' => $node,
                'type' => 'change'
            ));
            phpQueryEvents::trigger($this->getDocumentID(),
                $event->type, array($event), $node
            );
        }
    }

    public function markup($markup = null, $callback1 = null, $callback2 = null, $callback3 = null)
    {
        $args = func_get_args();
        if($this->documentWrapper->isXML)
            return call_user_func_array(array($this, 'xml'), $args);
        else
            return call_user_func_array(array($this, 'html'), $args);
    }

    public function markupOuter($callback1 = null, $callback2 = null, $callback3 = null)
    {
        $args = func_get_args();
        if($this->documentWrapper->isXML)
            return call_user_func_array(array($this, 'xmlOuter'), $args);
        else
            return call_user_func_array(array($this, 'htmlOuter'), $args);
    }

    /**
     * @throws \Exception
     */
    public function html($html = null, $callback1 = null, $callback2 = null, $callback3 = null)
    {
        if(isset($html)) {
            // INSERT
            $oldHtml = '';
            $nodes = $this->documentWrapper->import($html);
            $this->empty();
            foreach($this->stack(1) as $alreadyAdded => $node) {
                // for now, limit events for textarea
                if(($this->isXHTML() || $this->isHTML()) && $node->tagName == 'textarea')
                    $oldHtml = pq($node, $this->getDocumentID())->markup();
                foreach($nodes as $newNode) {
                    $node->appendChild($alreadyAdded
                        ? $newNode->cloneNode(true)
                        : $newNode);
                }
                // for now, limit events for textarea
                if(($this->isXHTML() || $this->isHTML()) && $node->tagName == 'textarea')
                    $this->markupEvents($html, $oldHtml, $node);
            }
            return $this;
        } else {
            // FETCH
            $return = $this->documentWrapper->markup($this->elements, true);
            $args = func_get_args();
            foreach(array_slice($args, 1) as $callback) {
                $return = phpQuery::callbackRun($callback, array($return));
            }
            return $return;
        }
    }

    public function xml($xml = null, $callback1 = null, $callback2 = null, $callback3 = null)
    {
        $args = func_get_args();
        return call_user_func_array(array($this, 'html'), $args);
    }

    /**
     * @throws \Exception
     */
    public function htmlOuter($callback1 = null, $callback2 = null, $callback3 = null)
    {
        $markup = $this->documentWrapper->markup($this->elements);
        // pass thou callbacks
        $args = func_get_args();
        foreach($args as $callback) {
            $markup = phpQuery::callbackRun($callback, array($markup));
        }
        return $markup;
    }

    public function xmlOuter($callback1 = null, $callback2 = null, $callback3 = null)
    {
        $args = func_get_args();
        return call_user_func_array(array($this, 'htmlOuter'), $args);
    }

    public function __toString()
    {
        return $this->markupOuter();
    }

    public function php($code = null)
    {
        return $this->markupPHP($code);
    }

    public function markupPHP($code = null)
    {
        return isset($code)
            ? $this->markup(phpQuery::php($code))
            : phpQuery::markupToPHP($this->markup());
    }

    public function markupOuterPHP(): array|string|null
    {
        return phpQuery::markupToPHP($this->markupOuter());
    }

    /**
     * @throws \Exception
     */
    public function children($selector = null)
    {
        $stack = [];
        foreach($this->stack(1) as $node) {
//			foreach($node->getElementsByTagName('*') as $newNode) {
            foreach($node->childNodes as $newNode) {
                if($newNode->nodeType != 1)
                    continue;
                if($selector && !$this->is($selector, $newNode))
                    continue;
                if($this->elementsContainsNode($newNode, $stack))
                    continue;
                $stack[] = $newNode;
            }
        }
        $this->elementsBackup = $this->elements;
        $this->elements = $stack;
        return $this->newInstance();
    }

    /**
     * @throws \Exception
     */
    public function ancestors($selector = null)
    {
        return $this->children($selector);
    }

    /**
     * @throws \Exception
     */
    public function append($content): phpQueryObject
    {
        return $this->insert($content, __FUNCTION__);
    }

    /**
     * @throws \Exception
     */
    public function appendPHP($content): phpQueryObject
    {
        return $this->insert("<php><!-- $content --></php>", 'append');
    }

    /**
     * @throws \Exception
     */
    public function appendTo($seletor): phpQueryObject
    {
        return $this->insert($seletor, __FUNCTION__);
    }

    /**
     * @throws \Exception
     */
    public function prepend($content): phpQueryObject
    {
        return $this->insert($content, __FUNCTION__);
    }

    /**
     * @throws \Exception
     */
    public function prependPHP($content): phpQueryObject
    {
        return $this->insert("<php><!-- $content --></php>", 'prepend');
    }

    /**
     * @throws \Exception
     */
    public function prependTo($seletor): phpQueryObject
    {
        return $this->insert($seletor, __FUNCTION__);
    }

    /**
     * @throws \Exception
     */
    public function before($content): phpQueryObject
    {
        return $this->insert($content, __FUNCTION__);
    }

    /**
     * @throws \Exception
     */
    public function beforePHP($content): phpQueryObject
    {
        return $this->insert("<php><!-- $content --></php>", 'before');
    }

    /**
     * @throws \Exception
     */
    public function insertBefore($seletor): phpQueryObject
    {
        return $this->insert($seletor, __FUNCTION__);
    }

    /**
     * @throws \Exception
     */
    public function after($content): phpQueryObject
    {
        return $this->insert($content, __FUNCTION__);
    }

    /**
     * @throws \Exception
     */
    public function afterPHP($content): phpQueryObject
    {
        return $this->insert("<php><!-- $content --></php>", 'after');
    }

    /**
     * @throws \Exception
     */
    public function insertAfter($seletor): phpQueryObject
    {
        return $this->insert($seletor, __FUNCTION__);
    }

    /**
     * @throws \Exception
     */
    public function insert($target, $type): phpQueryObject
    {
        $this->debug("Inserting data with '$type'");
        $firstChild = $nextSibling = '';
        $to = false;
        switch($type) {
            case 'appendTo':
            case 'prependTo':
            case 'insertBefore':
            case 'insertAfter':
                $to = true;
        }
        $insertFrom = $insertTo = [];
        switch(gettype($target)) {
            case 'string':
                if($to) {
                    // INSERT TO
                    $insertFrom = $this->elements;
                    if(phpQuery::isMarkup($target)) {
                        // $target is new markup, import it
                        $insertTo = $this->documentWrapper->import($target);
                        // insert into selected element
                    } else {
                        // $tagret is a selector
                        $thisStack = $this->elements;
                        $this->toRoot();
                        $insertTo = $this->find($target)->elements;
                        $this->elements = $thisStack;
                    }
                } else {
                    // INSERT FROM
                    $insertTo = $this->elements;
                    $insertFrom = $this->documentWrapper->import($target);
                }
                break;
            case 'object':
                {
                    // phpQuery
                    if($target instanceof self) {
                        if($to) {
                            $insertTo = $target->elements;
                            if($this->documentFragment && $this->stackIsRoot())
                                // get all body children
//							$loop = $this->find('body > *')->elements;
                                // TODO test it, test it hard...
//							$loop = $this->newInstance($this->root)->find('> *')->elements;
                                $loop = $this->root->childNodes;
                            else
                                $loop = $this->elements;
                            // import nodes if needed
                            $insertFrom = $this->getDocumentID() == $target->getDocumentID()
                                ? $loop
                                : $target->documentWrapper->import($loop);
                        } else {
                            $insertTo = $this->elements;
                            if($target->documentFragment && $target->stackIsRoot())
                                // get all body children
//							$loop = $target->find('body > *')->elements;
                                $loop = $target->root->childNodes;
                            else
                                $loop = $target->elements;
                            // import nodes if needed
                            $insertFrom = $this->getDocumentID() == $target->getDocumentID()
                                ? $loop
                                : $this->documentWrapper->import($loop);
                        }
                        // DOMNODE
                    } elseif($target instanceof DOMNODE) {
                        // import node if needed
//					if ( $target->ownerDocument != $this->DOM )
//						$target = $this->DOM->importNode($target, true);
                        if($to) {
                            $insertTo = array($target);
                            if($this->documentFragment && $this->stackIsRoot())
                                // get all body children
                                $loop = $this->root->childNodes;
//							$loop = $this->find('body > *')->elements;
                            else
                                $loop = $this->elements;
                            foreach($loop as $fromNode)
                                // import nodes if needed
                                $insertFrom[] = !$fromNode->ownerDocument->isSameNode($target->ownerDocument)
                                    ? $target->ownerDocument->importNode($fromNode, true)
                                    : $fromNode;
                        } else {
                            // import node if needed
                            if(!$target->ownerDocument->isSameNode($this->document))
                                $target = $this->document->importNode($target, true);
                            $insertTo = $this->elements;
                            $insertFrom[] = $target;
                        }
                    }
                }
                break;
        }
        phpQuery::debug("From ".count($insertFrom)."; To ".count($insertTo)." nodes");
        foreach($insertTo as $insertNumber => $toNode) {
            // we need static relative elements in some cases
            switch($type) {
                case 'prependTo':
                case 'prepend':
                    $firstChild = $toNode->firstChild;
                    break;
                case 'insertAfter':
                case 'after':
                    $nextSibling = $toNode->nextSibling;
                    break;
            }
            foreach($insertFrom as $fromNode) {
                // clone if inserted already before
                $insert = $insertNumber
                    ? $fromNode->cloneNode(true)
                    : $fromNode;
                switch($type) {
                    case 'appendTo':
                    case 'append':
//						$toNode->insertBefore(
//							$fromNode,
//							$toNode->lastChild->nextSibling
//						);
                        $toNode->appendChild($insert);
                        break;
                    case 'prependTo':
                    case 'prepend':
                        $toNode->insertBefore(
                            $insert,
                            $firstChild
                        );
                        break;
                    case 'insertBefore':
                    case 'before':
                        if(!$toNode->parentNode)
                            throw new Exception("No parentNode, can't do $type()");
                        else
                            $toNode->parentNode->insertBefore(
                                $insert,
                                $toNode
                            );
                        break;
                    case 'insertAfter':
                    case 'after':
                        if(!$toNode->parentNode)
                            throw new Exception("No parentNode, can't do $type()");
                        else
                            $toNode->parentNode->insertBefore(
                                $insert,
                                $nextSibling
                            );
                        break;
                }
                // Mutation event
                $event = new DOMEvent(array(
                    'target' => $insert,
                    'type' => 'DOMNodeInserted'
                ));
                phpQueryEvents::trigger($this->getDocumentID(),
                    $event->type, array($event), $insert
                );
            }
        }
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function index($subject): int
    {
        $index = -1;
        $subject = $subject instanceof phpQueryObject
            ? $subject->elements[0]
            : $subject;
        foreach($this->newInstance() as $k => $node) {
            if($node->isSameNode($subject))
                $index = $k;
        }
        return $index;
    }

    /**
     * @throws \Exception
     */
    public function slice($start, $end = null)
    {
//		$last = count($this->elements)-1;
//		$end = $end
//			? min($end, $last)
//			: $last;
//		if ($start < 0)
//			$start = $last+$start;
//		if ($start > $last)
//			return [];
        if($end > 0)
            $end = $end - $start;
        return $this->newInstance(
            array_slice($this->elements, $start, $end)
        );
    }

    /**
     * @throws \Exception
     */
    public function reverse()
    {
        $this->elementsBackup = $this->elements;
        $this->elements = array_reverse($this->elements);
        return $this->newInstance();
    }

    /**
     * @throws \Exception
     */
    public function text($text = null, $callback1 = null, $callback2 = null, $callback3 = null)
    {
        if(isset($text))
            return $this->html(htmlspecialchars($text));
        $args = func_get_args();
        $args = array_slice($args, 1);
        $return = '';
        foreach($this->elements as $node) {
            $text = $node->textContent;
            if(count($this->elements) > 1 && $text)
                $text .= "\n";
            foreach($args as $callback) {
                $text = phpQuery::callbackRun($callback, array($text));
            }
            $return .= $text;
        }
        return $return;
    }

    /**
     * @throws Exception
     */
    public function plugin($class, $file = null): phpQueryObject
    {
        phpQuery::plugin($class, $file);
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function __call($method, $args)
    {
        $aliasMethods = array('clone', 'empty');
        if(isset(phpQuery::$extendMethods[$method])) {
            array_unshift($args, $this);
            return phpQuery::callbackRun(
                phpQuery::$extendMethods[$method], $args
            );
        } else if(isset(phpQuery::$pluginsMethods[$method])) {
            array_unshift($args, $this);
            $class = phpQuery::$pluginsMethods[$method];
            $realClass = "phpQueryObjectPlugin_$class";
            $return = call_user_func_array(
                array($realClass, $method),
                $args
            );
            // XXX deprecate ?
            return is_null($return)
                ? $this
                : $return;
        } else if(in_array($method, $aliasMethods)) {
            return call_user_func_array(array($this, '_'.$method), $args);
        } else
            throw new Exception("Method '$method' doesnt exist");
    }

    /**
     * @throws \Exception
     */
    public function _next($selector = null)
    {
        return $this->newInstance($this->getElementSiblings('nextSibling', $selector, true));
    }

    /**
     * @throws \Exception
     */
    public function _prev($selector = null)
    {
        return $this->prev($selector);
    }

    /**
     * @throws \Exception
     */
    public function prev($selector = null)
    {
        return $this->newInstance(
            $this->getElementSiblings('previousSibling', $selector, true)
        );
    }

    /**
     * @throws \Exception
     */
    public function prevAll($selector = null)
    {
        return $this->newInstance(
            $this->getElementSiblings('previousSibling', $selector)
        );
    }

    /**
     * @throws \Exception
     */
    public function nextAll($selector = null)
    {
        return $this->newInstance(
            $this->getElementSiblings('nextSibling', $selector)
        );
    }

    /**
     * @throws \Exception
     */
    protected function getElementSiblings($direction, $selector = null, $limitToOne = false): array
    {
        $stack = [];
        foreach($this->stack() as $node) {
            $test = $node;
            while(isset($test->{$direction}) && $test->{$direction}) {
                $test = $test->{$direction};
                if(!$test instanceof DOMELEMENT)
                    continue;
                $stack[] = $test;
                if($limitToOne)
                    break;
            }
        }
        if($selector) {
            $stackOld = $this->elements;
            $this->elements = $stack;
            $stack = $this->filter($selector, true)->stack();
            $this->elements = $stackOld;
        }
        return $stack;
    }

    /**
     * @throws \Exception
     */
    public function siblings($selector = null)
    {
        $stack = [];
        $siblings = array_merge(
            $this->getElementSiblings('previousSibling', $selector),
            $this->getElementSiblings('nextSibling', $selector)
        );
        foreach($siblings as $node) {
            if(!$this->elementsContainsNode($node, $stack))
                $stack[] = $node;
        }
        return $this->newInstance($stack);
    }

    /**
     * @throws \Exception
     */
    public function not($selector = null)
    {
        if(is_string($selector))
            phpQuery::debug(array('not', $selector));
        else
            phpQuery::debug('not');
        $stack = [];
        if($selector instanceof self || $selector instanceof DOMNODE) {
            foreach($this->stack() as $node) {
                if($selector instanceof self) {
                    $matchFound = false;
                    foreach($selector->stack() as $notNode) {
                        if($notNode->isSameNode($node))
                            $matchFound = true;
                    }
                    if(!$matchFound)
                        $stack[] = $node;
                }
                else if($selector instanceof DOMNODE) {
                    if(!$selector->isSameNode($node))
                        $stack[] = $node;
                }
                else {
                    if(!$this->is($selector))
                        $stack[] = $node;
                }
            }
        }
        else {
            $orgStack = $this->stack();
            $matched = $this->filter($selector, true)->stack();
            foreach($orgStack as $node)
                if(!$this->elementsContainsNode($node, $matched))
                    $stack[] = $node;
        }
        return $this->newInstance($stack);
    }

    /**
     * @throws \Exception
     */
    public function add($selector = null)
    {
        if(!$selector)
            return $this;
        $this->elementsBackup = $this->elements;
        $found = phpQuery::pq($selector, $this->getDocumentID());
        $this->merge($found->elements);
        return $this->newInstance();
    }

    protected function merge(): void
    {
        foreach(func_get_args() as $nodes) {
            foreach($nodes as $newNode) {
                if(!$this->elementsContainsNode($newNode)) {
                    $this->elements[] = $newNode;
                }
            }
        }
    }

    protected function elementsContainsNode($nodeToCheck, $elementsStack = null): bool
    {
        $loop = !is_null($elementsStack)
            ? $elementsStack
            : $this->elements;
        foreach($loop as $node) {
            if($node->isSameNode($nodeToCheck))
                return true;
        }
        return false;
    }

    /**
     * @throws \Exception
     */
    public function parent($selector = null)
    {
        $stack = [];
        foreach($this->elements as $node)
            if($node->parentNode && !$this->elementsContainsNode($node->parentNode, $stack))
                $stack[] = $node->parentNode;
        $this->elementsBackup = $this->elements;
        $this->elements = $stack;
        if($selector)
            $this->filter($selector, true);
        return $this->newInstance();
    }

    /**
     * @throws \Exception
     */
    public function parents($selector = null)
    {
        $stack = [];
        if(!$this->elements)
            $this->debug('parents() - stack empty');
        foreach($this->elements as $node) {
            $test = $node;
            while($test->parentNode) {
                $test = $test->parentNode;
                if($this->isRoot($test))
                    break;
                if(!$this->elementsContainsNode($test, $stack)) {
                    $stack[] = $test;
                }
            }
        }
        $this->elementsBackup = $this->elements;
        $this->elements = $stack;
        if($selector)
            $this->filter($selector, true);
        return $this->newInstance();
    }

    public function stack($nodeTypes = null): array
    {
        if(!isset($nodeTypes))
            return $this->elements;
        if(!is_array($nodeTypes))
            $nodeTypes = array($nodeTypes);
        $return = [];
        foreach($this->elements as $node) {
            if(in_array($node->nodeType, $nodeTypes))
                $return[] = $node;
        }
        return $return;
    }

    /**
     * @throws \Exception
     */
    protected function attrEvents($attr, $oldAttr, $oldValue, $node): void
    {
        // skip events for XML documents
        if(!$this->isXHTML() && !$this->isHTML())
            return;
        $event = null;
        // identify
        $isInputValue = $node->tagName == 'input'
            && (
                in_array($node->getAttribute('type'),
                    array('text', 'password', 'hidden'))
                || !$node->getAttribute('type')
            );
        $isRadio = $node->tagName == 'input'
            && $node->getAttribute('type') == 'radio';
        $isCheckbox = $node->tagName == 'input'
            && $node->getAttribute('type') == 'checkbox';
        $isOption = $node->tagName == 'option';
        if($isInputValue && $attr == 'value' && $oldValue != $node->getAttribute($attr)) {
            $event = new DOMEvent(array(
                'target' => $node,
                'type' => 'change'
            ));
        } else if(($isRadio || $isCheckbox) && $attr == 'checked' && (
                // check
                (!$oldAttr && $node->hasAttribute($attr))
                // un-check
                || (!$node->hasAttribute($attr) && $oldAttr)
            )) {
            $event = new DOMEvent(array(
                'target' => $node,
                'type' => 'change'
            ));
        } else if($isOption && $node->parentNode && $attr == 'selected' && (
                // select
                (!$oldAttr && $node->hasAttribute($attr))
                // un-select
                || (!$node->hasAttribute($attr) && $oldAttr)
            )) {
            $event = new DOMEvent(array(
                'target' => $node->parentNode,
                'type' => 'change'
            ));
        }
        if($event) {
            phpQueryEvents::trigger($this->getDocumentID(),
                $event->type, array($event), $node
            );
        }
    }

    /**
     * @throws \Exception
     */
    public function attr($attr = null, $value = null): phpQueryObject|array|string|null
    {
        foreach($this->stack(1) as $node) {
            if(!is_null($value)) {
                $loop = $attr == '*'
                    ? $this->getNodeAttrs($node)
                    : array($attr);
                foreach($loop as $a) {
                    $oldValue = $node->getAttribute($a);
                    $oldAttr = $node->hasAttribute($a);
                    // TODO raises an error when charset other than UTF-8
                    // while document's charset is also not UTF-8
                    @$node->setAttribute($a, $value);
                    $this->attrEvents($a, $oldAttr, $oldValue, $node);
                }
            } else if($attr == '*') {
                // jQuery difference
                $return = [];
                foreach($node->attributes as $n => $v)
                    $return[$n] = $v->value;
                return $return;
            } else
                return $node->getAttribute($attr);
        }
        return ($value) ? $this : '';
    }

    protected function getNodeAttrs($node): array
    {
        $return = [];
        foreach($node->attributes as $n => $o)
            $return[] = $n;
        return $return;
    }

    public function attrPHP($attr, $code): phpQueryObject|array|string|null
    {
        if(!is_null($code)) {
            $value = '<'.'?php '.$code.' ?'.'>';
            // TODO tempolary solution
            // http://code.google.com/p/phpquery/issues/detail?id=17
//			if (function_exists('mb_detect_encoding') && mb_detect_encoding($value) == 'ASCII')
//				$value	= mb_convert_encoding($value, 'UTF-8', 'HTML-ENTITIES');
        }
        foreach($this->stack(1) as $node) {
            if(!is_null($code)) {
//				$attrNode = $this->DOM->createAttribute($attr);
                $node->setAttribute($attr, $value);
//				$attrNode->value = $value;
//				$node->appendChild($attrNode);
            } else if($attr == '*') {
                // jQuery diff
                $return = [];
                foreach($node->attributes as $n => $v)
                    $return[$n] = $v->value;
                return $return;
            } else
                return $node->getAttribute($attr);
        }
        return $this;
    }

    /**
     *  Removes an attribute from each matched element.
     * @param string $attr An attribute to remove, it can be a space-separated list of attributes.
     * @return phpQueryObject
     *@throws \Exception
     */
    public function removeAttr(string $attr): phpQueryObject
    {
        foreach($this->stack(1) as $node) {
            $loop = $attr == '*'
                ? $this->getNodeAttrs($node)
                : array($attr);
            foreach($loop as $a) {
                $oldValue = $node->getAttribute($a);
                $node->removeAttribute($a);
                $this->attrEvents($a, $oldValue, null, $node);
            }
        }
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function val($val = null)
    {
        if(!isset($val)) {
            if($this->eq(0)->is('select')) {
                $selected = $this->eq(0)->find('option[selected=selected]');
                if($selected->is('[value]'))
                    return $selected->attr('value');
                else
                    return $selected->text();
            } else if($this->eq(0)->is('textarea'))
                return $this->eq(0)->markup();
            else
                return $this->eq(0)->attr('value');
        } else {
            $_val = null;
            foreach($this->stack(1) as $node) {
                $node = pq($node, $this->getDocumentID());
                if(is_array($val) && in_array($node->attr('type'), array('checkbox', 'radio'))) {
                    $isChecked = in_array($node->attr('value'), $val)
                        || in_array($node->attr('name'), $val);
                    if($isChecked)
                        $node->attr('checked', 'checked');
                    else
                        $node->removeAttr('checked');
                } else if($node->get(0)->tagName == 'select') {
                    if(!isset($_val)) {
                        $_val = [];
                        if(!is_array($val))
                            $_val = array((string)$val);
                        else
                            foreach($val as $v)
                                $_val[] = $v;
                    }
                    foreach($node['option']->stack(1) as $option) {
                        $option = pq($option, $this->getDocumentID());
                        // XXX: workaround for string comparsion, see issue #96
                        // http://code.google.com/p/phpquery/issues/detail?id=96
                        $selected = is_null($option->attr('value'))
                            ? in_array($option->markup(), $_val)
                            : in_array($option->attr('value'), $_val);
//						$optionValue = $option->attr('value');
//						$optionText = $option->text();
//						$optionTextLenght = mb_strlen($optionText);
//						foreach($_val as $v)
//							if ($optionValue == $v)
//								$selected = true;
//							else if ($optionText == $v && $optionTextLenght == mb_strlen($v))
//								$selected = true;
                        if($selected)
                            $option->attr('selected', 'selected');
                        else
                            $option->removeAttr('selected');
                    }
                } else if($node->get(0)->tagName == 'textarea')
                    $node->markup($val);
                else
                    $node->attr('value', $val);
            }
        }
        return $this;
    }

    public function andSelf(): phpQueryObject
    {
        $this->elements = array_merge($this->elements, (isset($this->previous)) ? $this->previous->elements : []);
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function addClass($className): phpQueryObject
    {
        if(!$className)
            return $this;
        foreach($this->stack(1) as $node) {
            if(!$this->is(".$className", $node))
                $node->setAttribute(
                    'class',
                    trim($node->getAttribute('class').' '.$className)
                );
        }
        return $this;
    }

    public function addClassPHP($className): phpQueryObject
    {
        foreach($this->stack(1) as $node) {
            $classes = $node->getAttribute('class');
            $newValue = $classes
                ? $classes.' <'.'?php '.$className.' ?'.'>'
                : '<'.'?php '.$className.' ?'.'>';
            $node->setAttribute('class', $newValue);
        }
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function hasClass($className): bool
    {
        foreach($this->stack(1) as $node) {
            if($this->is(".$className", $node))
                return true;
        }
        return false;
    }

    public function removeClass($className): phpQueryObject
    {
        foreach($this->stack(1) as $node) {
            $classes = explode(' ', $node->getAttribute('class'));
            if(in_array($className, $classes)) {
                $classes = array_diff($classes, array($className));
                if($classes)
                    $node->setAttribute('class', implode(' ', $classes));
                else
                    $node->removeAttribute('class');
            }
        }
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function toggleClass($className): phpQueryObject
    {
        foreach($this->stack(1) as $node) {
            if($this->is($node, '.'.$className))
                $this->removeClass($className);
            else
                $this->addClass($className);
        }
        return $this;
    }
    /** @noinspection PhpUnused */
    public function _empty(): phpQueryObject
    {
        foreach($this->stack(1) as $node) {
            // thx to 'dave at dgx dot cz'
            $node->nodeValue = '';
        }
        return $this;
    }
    /** @noinspection PhpUnused */
    public function each($callback, $param1 = null, $param2 = null, $param3 = null): phpQueryObject
    {
        $paramStructure = null;
        if(func_num_args() > 1) {
            $paramStructure = func_get_args();
            $paramStructure = array_slice($paramStructure, 1);
        }
        foreach($this->elements as $v)
            phpQuery::callbackRun($callback, array($v), $paramStructure);
        return $this;
    }

    /** @noinspection PhpUnused */
    public function callback($callback, $param1 = null, $param2 = null, $param3 = null): phpQueryObject
    {
        $params = func_get_args();
        $params[0] = $this;
        phpQuery::callbackRun($callback, $params);
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function map($callback, $param1 = null, $param2 = null, $param3 = null)
    {
//		$stack = [];
////		foreach($this->newInstance() as $node) {
//		foreach($this->newInstance() as $node) {
//			$result = call_user_func($callback, $node);
//			if ($result)
//				$stack[] = $result;
//		}
        $params = func_get_args();
        array_unshift($params, $this->elements);
        return $this->newInstance(
            call_user_func_array(array('phpQuery', 'map'), $params)
//			phpQuery::map($this->elements, $callback)
        );
    }

    /**
     * @throws \Exception
     * @noinspection PhpUnused
     */
    public function data($key, $value = null)
    {
        if(!isset($value)) {
            // TODO? implement specific jQuery behavior od returning parent values
            // is child which we look up doesn't exist
            return phpQuery::data($this->get(0), $key, $value, $this->getDocumentID());
        } else {
            foreach($this as $node)
                phpQuery::data($node, $key, $value, $this->getDocumentID());
            return $this;
        }
    }

    /**
     * @throws \Exception
     * @noinspection PhpUnused
     */
    public function removeData($key): phpQueryObject
    {
        foreach($this as $node)
            phpQuery::removeData($node, $key, $this->getDocumentID());
        return $this;
    }
    // INTERFACE IMPLEMENTATIONS

    // ITERATOR INTERFACE
    public function rewind(): void
    {
        $this->debug('iterating foreach');
//		phpQuery::selectDocument($this->getDocumentID());
        $this->elementsBackup = $this->elements;
        $this->elementsInterator = $this->elements;
        $this->valid = isset($this->elements[0]);
// 		$this->elements = $this->valid
// 			? array($this->elements[0])
// 			: [];
        $this->current = 0;
    }

    public function current(): mixed
    {
        return $this->elementsInterator[$this->current];
    }

    public function key(): int
    {
        return $this->current;
    }

    /**
     * @throws \Exception
     */
    public function next($cssSelector = null):void
    {
//		if ($cssSelector || $this->valid)
//			return $this->_next($cssSelector);
        $this->valid = isset($this->elementsInterator[$this->current + 1]);
        if(!$this->valid && $this->elementsInterator) {
            $this->elementsInterator = [];
        } else if($this->valid) {
            $this->current++;
        } else {
            $this->_next($cssSelector);
        }
    }

    public function valid(): bool
    {
        return $this->valid;
    }

    /**
     * @throws \Exception
     */
    public function offsetExists($offset): bool
    {
        return $this->find($offset)->size() > 0;
    }

    /**
     * @throws \Exception
     */
    public function offsetGet($offset): phpQueryObject
    {
        return $this->find($offset);
    }

    /**
     * @throws \Exception
     */
    public function offsetSet($offset, $value): void
    {
//		$this->find($offset)->replaceWith($value);
        $this->find($offset)->html($value);
    }

    /**
     * @throws \Exception
     */
    public function offsetUnset($offset): void
    {
        // empty
        throw new Exception("Can't do unset, use array interface only for calling queries and replacing HTML.");
    }

    protected function getNodeXpath($oneNode = null)
    {
        $return = [];
        $loop = $oneNode ? array($oneNode) : $this->elements;
        foreach($loop as $node) {
            if($node instanceof DOMDOCUMENT) {
                $return[] = '';
                continue;
            }
            $xpath = [];
            while(!($node instanceof DOMDOCUMENT)) {
                $i = 1;
                $sibling = $node;
                while($sibling->previousSibling) {
                    $sibling = $sibling->previousSibling;
                    $isElement = $sibling instanceof DOMELEMENT;
                    if($isElement && $sibling->tagName == $node->tagName)
                        $i++;
                }
                $xpath[] = $this->isXML()
                    ? "*[local-name()='".$node->tagName."'][$i]"
                    : $node->tagName."[$i]";
                $node = $node->parentNode;
            }
            $xpath = join('/', array_reverse($xpath));
            $return[] = '/'.$xpath;
        }
        return $oneNode ? $return[0] : $return;
    }

    // HELPERS
    public function whois($oneNode = null)
    {
        $return = [];
        $loop = $oneNode
            ? array($oneNode)
            : $this->elements;
        foreach($loop as $node) {
            if(isset($node->tagName)) {
                $tag = in_array($node->tagName, array('php', 'js'))
                    ? strtoupper($node->tagName)
                    : $node->tagName;
                $return[] = $tag
                    .($node->getAttribute('id')
                        ? '#'.$node->getAttribute('id') : '')
                    .($node->getAttribute('class')
                        ? '.'.join('.', explode(' ', $node->getAttribute('class'))) : '')
                    .($node->getAttribute('name')
                        ? '[name="'.$node->getAttribute('name').'"]' : '')
                    .($node->getAttribute('value') && !str_contains($node->getAttribute('value'), '<'.'?php')
                        ? '[value="'.substr(str_replace("\n", '', $node->getAttribute('value')), 0, 15).'"]' : '')
                    .($node->getAttribute('value') && str_contains($node->getAttribute('value'), '<'.'?php')
                        ? '[value=PHP]' : '')
                    .($node->getAttribute('selected')
                        ? '[selected]' : '')
                    .($node->getAttribute('checked')
                        ? '[checked]' : '');
            } else if($node instanceof DOMTEXT) {
                if(trim($node->textContent))
                    $return[] = 'Text:'.substr(str_replace("\n", ' ', $node->textContent), 0, 15);
            }
        }
        return $oneNode && isset($return[0])
            ? $return[0]
            : $return;
    }

    /**
     * @throws \Exception
     * @noinspection PhpUnused
     */
    public function dump(): phpQueryObject
    {
        print 'DUMP #'.(phpQuery::$dumpCount++).' ';
        phpQuery::$debug = false;
        var_dump($this->htmlOuter());
        return $this;
    }

    /* @noinspection PhpUnused */
    public function dumpWhois(): phpQueryObject
    {
        print 'DUMP #'.(phpQuery::$dumpCount++).' ';
        var_dump('whois', $this->whois());
        return $this;
    }

    /* @noinspection PhpUnused */
    public function dumpLength(): phpQueryObject
    {
        print 'DUMP #'.(phpQuery::$dumpCount++).' ';
        var_dump('length', $this->length());
        return $this;
    }


    /**
     *  Dumps HTML of document or HTML of elements stack.
     * @param string $html
     * @param string $title
     * @return $this
     * @noinspection PhpUnused
     */
    public function dumpTree(string $html = '', string $title = ''): phpQueryObject
    {
        $output = $title
            ? 'DUMP #'.(phpQuery::$dumpCount++)." \n" : '';
        foreach($this->stack() as $node)
            $output .= $this->__dumpTree($node);
        print $html
            ? nl2br(str_replace(' ', '&nbsp;', $output))
            : $output;
        return $this;
    }

    private function __dumpTree($node, $intend = 0): string
    {
        $whois = $this->whois($node);
        $return = '';
        if($whois)
            $return .= str_repeat(' - ', $intend).$whois."\n";
        if(isset($node->childNodes))
            foreach($node->childNodes as $chNode)
                $return .= $this->__dumpTree($chNode, $intend + 1);
        return $return;
    }

    /**
     * @throws \Exception
     * @noinspection PhpUnused
     */
    public function dumpDie(): void
    {
        print __FILE__.':'.__LINE__;
        var_dump($this->htmlOuter());
    }
}