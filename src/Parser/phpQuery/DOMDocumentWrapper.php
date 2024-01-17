<?php
/*
Date: 14.01.2024
*/
declare(strict_types = 1);

namespace Api\Parser\phpQuery;

use Api\Parser\phpQuery;
use DOMDocument;
use DOMNODE;
use DOMNODELIST;
use DOMXPath;
use Exception;

class DOMDocumentWrapper
{


    public int $uuid = 0;

    public array $eventsNodes = [];

    public array $dataNodes = [];

    public array $eventsGlobal = [];

    public array $frames = [];

    public DOMNODE|DOMDocument|null $document = null;
    public mixed $id;
    public string $contentType;
    public DOMXPath $xpath;
    public array $data;
    public array $events = [];
    public mixed $root = null;
    public bool $isDocumentFragment;
    public bool $isXML = false;
    public bool $isXHTML = false;
    public bool $isHTML = false;
    public string $charset;

    /**
     * @throws \Exception
     */
    public function __construct($markup = null, string $contentType = null, $newDocumentID = null)
    {
        if(isset($markup)) {
            $this->load($markup, $contentType);
        }
        $this->id = $newDocumentID ?? md5(microtime());
    }

    /**
     * @throws \Exception
     */
    public function load($markup, string $contentType = null): bool
    {
        $loaded = false;
        $this->contentType = $contentType ? strtolower($contentType) : 'text/html';
        if($markup instanceof DOMDOCUMENT) {
            $this->document = $markup;
            $this->root = $this->document;
            $this->charset = $this->document->encoding;
            // TODO isDocumentFragment
        } else {
            $loaded = $this->loadMarkup($markup);
        }
        if($loaded) {
            $this->document->preserveWhiteSpace = true;
            $this->xpath = new DOMXPath($this->document);
            $this->afterMarkupLoad();
            return true;
        }
        return false;
    }

    protected function afterMarkupLoad(): void
    {
        if($this->isXHTML) {
            $this->xpath->registerNamespace("html", "http://www.w3.org/1999/xhtml");
        }
    }

    /**
     * @throws \Exception
     */
    protected function loadMarkup($markup): bool
    {
        $loaded = false;
        if($this->contentType) {
            self::debug("Load markup for content type ".$this->contentType);
            // content determined by contentType
            [$contentType, $charset] = $this->contentTypeToArray($this->contentType);
            switch($contentType) {
                case 'text/html':
                    {
                        phpQuery::debug("Loading HTML, content type ".$this->contentType);
                        $loaded = $this->loadMarkupHTML($markup, $charset);
                    }
                    break;
                case 'text/xml':
                case 'application/xhtml+xml':
                    {
                        phpQuery::debug("Loading XML, content type ".$this->contentType);
                        $loaded = $this->loadMarkupXML($markup, $charset);
                    }
                    break;
                default:
                {
                    // for feeds or anything that sometimes doesn't use text/xml
                    if(str_contains('xml', $this->contentType)) {
                        phpQuery::debug("Loading XML, content type ".$this->contentType);
                        $loaded = $this->loadMarkupXML($markup, $charset);
                    } else {
                        phpQuery::debug("Could not determine document type from content type ".$this->contentType);
                    }
                }

            }
        } else {
            // content type autodetection
            if($this->isXML($markup)) {
                phpQuery::debug("Loading XML, isXML() == true");
                $loaded = $this->loadMarkupXML($markup);
                if(!$loaded && $this->isXHTML) {
                    phpQuery::debug('Loading as XML failed, trying to load as HTML, isXHTML == true');
                    $loaded = $this->loadMarkupHTML($markup);
                }
            } else {
                phpQuery::debug("Loading HTML, isXML() == false");
                $loaded = $this->loadMarkupHTML($markup);
            }
        }
        return $loaded;
    }

    protected function loadMarkupReset(): void
    {
        $this->isXML = $this->isXHTML = $this->isHTML = false;
    }

    protected function documentCreate($charset, $version = '1.0'): void
    {
        if(!$version)
            $version = '1.0';
        $this->document = new DOMDocument($version, $charset);
        $this->charset = $this->document->encoding;
//		$this->document->encoding = $charset;
        $this->document->formatOutput = true;
        $this->document->preserveWhiteSpace = true;
    }

    protected function loadMarkupHTML($markup, $requestedCharset = null): bool
    {
        if(phpQuery::$debug) {
            phpQuery::debug('Full markup load (HTML): '.substr($markup, 0, 250));
        }
        $this->loadMarkupReset();
        $this->isHTML = true;
        if(!isset($this->isDocumentFragment)) {
            $this->isDocumentFragment = self::isDocumentFragmentHTML($markup);
        }
        $charset = null;
        $documentCharset = $this->charsetFromHTML($markup);
        $addDocumentCharset = false;
        if($documentCharset) {
            $charset = $documentCharset;
            $markup = $this->charsetFixHTML($markup);
        } else if($requestedCharset) {
            $charset = $requestedCharset;
            $requestedCharset = strtoupper($requestedCharset);
        }
        if(!$charset) {
            $charset = phpQuery::$defaultCharset;
        }
        // HTTP 1.1 says that the default charset is ISO-8859-1
        // @see http://www.w3.org/International/O-HTTP-charset
        if(!$documentCharset) {
            $documentCharset = 'ISO-8859-1';
            $addDocumentCharset = true;
        }
        // Should be careful here, still need 'magic encoding detection' since lots of pages have other 'default encoding'
        // Worse, some pages can have mixed encodings... we'll try not to worry about that

        $documentCharset = strtoupper($documentCharset);
        phpQuery::debug("DOC: $documentCharset REQ: $requestedCharset");
        if($requestedCharset && $documentCharset && $requestedCharset !== $documentCharset) {
            phpQuery::debug("CHARSET CONVERT");
            // Document Encoding Conversion
            // http://code.google.com/p/phpquery/issues/detail?id=86
            if(function_exists('mb_detect_encoding')) {
                $possibleCharsets = array($documentCharset, $requestedCharset, 'AUTO');
                $docEncoding = mb_detect_encoding($markup, implode(', ', $possibleCharsets));
                if(!$docEncoding) {
                    $docEncoding = $documentCharset; // ok trust the document
                }
                phpQuery::debug("DETECTED '$docEncoding'");
                // Detected does not match what document says...
                //if ($docEncoding !== $documentCharset) {
                // Tricky..
                //}
                if($docEncoding !== $requestedCharset) {
                    phpQuery::debug("CONVERT $docEncoding => $requestedCharset");
                    $markup = mb_convert_encoding($markup, $requestedCharset, $docEncoding);
                    $markup = $this->charsetAppendToHTML($markup, $requestedCharset);
                    $charset = $requestedCharset;
                }
            } else {
                phpQuery::debug("TODO: charset conversion without mbstring...");
            }
        }
        if($this->isDocumentFragment) {
            phpQuery::debug("Full markup load (HTML), DocumentFragment detected, using charset '$charset'");
            $return = $this->documentFragmentLoadMarkup($this, $charset, $markup);
        } else {
            if($addDocumentCharset) {
                phpQuery::debug("Full markup load (HTML), appending charset: '$charset'");
                $markup = $this->charsetAppendToHTML($markup, $charset);
            }
            phpQuery::debug("Full markup load (HTML), documentCreate('$charset')");
            $this->documentCreate($charset);
            libxml_use_internal_errors(true);
            $return = @$this->document->loadHTML($markup);
            libxml_clear_errors();
            if($return)
                $this->root = $this->document;
        }
        if($return && !$this->contentType)
            $this->contentType = 'text/html';
        return $return;
    }

    /**
     * @throws Exception
     */
    protected function loadMarkupXML($markup, $requestedCharset = null): bool
    {
        if(phpQuery::$debug)
            phpQuery::debug('Full markup load (XML): '.substr($markup, 0, 250));
        $this->loadMarkupReset();
        $this->isXML = true;
        // check agains XHTML in contentType or markup
        $isContentTypeXHTML = $this->isXHTML();
        $isMarkupXHTML = $this->isXHTML($markup);
        if($isContentTypeXHTML || $isMarkupXHTML) {
            self::debug('Full markup load (XML), XHTML detected');
            $this->isXHTML = true;
        }
        // determine document fragment
        if(!isset($this->isDocumentFragment))
            $this->isDocumentFragment = $this->isXHTML
                ? self::isDocumentFragmentXHTML($markup)
                : self::isDocumentFragmentXML($markup);
        // this charset will be used
        $charset = null;
        // charset from XML declaration @var string
        $documentCharset = $this->charsetFromXML($markup);
        if(!$documentCharset) {
            if($this->isXHTML) {
                // this is XHTML, try to get charset from content-type meta header
                $documentCharset = $this->charsetFromHTML($markup);
                if($documentCharset) {
                    phpQuery::debug("Full markup load (XML), appending XHTML charset '$documentCharset'");
                    /** @var Object $this */
                    $this->charsetAppendToXML($markup, $documentCharset);
                    $charset = $documentCharset;
                }
            }
            if(!$documentCharset) {
                // if still no document charset...
                $charset = $requestedCharset;
            }
        } else if($requestedCharset) {
            $charset = $requestedCharset;
        }
        if(!$charset) {
            $charset = phpQuery::$defaultCharset;
        }
        //if ($requestedCharset && $documentCharset && $requestedCharset != $documentCharset) {
        // TODO place for charset conversion
//			$charset = $requestedCharset;
        //}
        if($this->isDocumentFragment) {
            phpQuery::debug("Full markup load (XML), DocumentFragment detected, using charset '$charset'");
            $return = $this->documentFragmentLoadMarkup($this, $charset, $markup);
        } else {
            // FIXME ???
            if($isContentTypeXHTML && !$isMarkupXHTML)
                if(!$documentCharset) {
                    phpQuery::debug("Full markup load (XML), appending charset '$charset'");
                    $markup = $this->charsetAppendToXML($markup, $charset);
                }
            // see http://pl2.php.net/manual/en/book.dom.php#78929
            // LIBXML_DTDLOAD (>= PHP 5.1)
            // does XML ctalogues works with LIBXML_NONET
            //		$this->document->resolveExternals = true;
            // TODO test LIBXML_COMPACT for performance improvement
            // create document
            $this->documentCreate($charset);
            if(phpversion() < 5.1) {
                $this->document->resolveExternals = true;
                $return = @$this->document->loadXML($markup);
            } else {
                $libxmlStatic = LIBXML_DTDLOAD | LIBXML_DTDATTR | LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR;
                $return = $this->document->loadXML($markup, $libxmlStatic);
// 				if (! $return)
// 					$return = $this->document->loadHTML($markup);
            }
            if($return)
                $this->root = $this->document;
        }
        if($return) {
            if(!$this->contentType) {
                $this->contentType = ($this->isXHTML) ? 'application/xhtml+xml' : 'text/xml';
            }
            return $return;
        } else {
            throw new Exception("Error loading XML markup");
        }
    }

    protected function isXHTML($markup = null): bool
    {
        if(!isset($markup)) {
            return str_contains($this->contentType, 'xhtml');
        }
        // XXX ok ?
        return str_contains($markup, "<!DOCTYPE html");
//		return stripos($doctype, 'xhtml') !== false;
//		$doctype = isset($dom->doctype) && is_object($dom->doctype)
//			? $dom->doctype->publicId
//			: self::$defaultDoctype;
    }

    protected function isXML($markup): bool
    {
//		return str_contains($markup, '<?xml')str_contains && stripos($markup, 'xhtml') === false;
        return str_contains(substr($markup, 0, 100), '<'.'?xml');
    }

    protected function contentTypeToArray($contentType): array
    {
        $matches = explode(';', trim(strtolower($contentType)));
        if(isset($matches[1])) {
            $matches[1] = explode('=', $matches[1]);
            // strip 'charset='
            $matches[1] = isset($matches[1][1]) && trim($matches[1][1])
                ? $matches[1][1]
                : $matches[1][0];
        } else
            $matches[1] = null;
        return $matches;
    }

    protected function contentTypeFromHTML($markup): array
    {
        $matches = [];
        // find meta tag
        preg_match('@<meta[^>]+http-equiv\\s*=\\s*(["|\'])Content-Type\\1([^>]+?)>@i',
            $markup, $matches);
        if(!isset($matches[0]))
            return array(null, null);
        // get attr 'content'
        preg_match('@content\\s*=\\s*(["|\'])(.+?)\\1@', $matches[0], $matches);
        if(!isset($matches[0]))
            return array(null, null);
        return $this->contentTypeToArray($matches[2]);
    }

    protected function charsetFromHTML($markup)
    {
        $contentType = $this->contentTypeFromHTML($markup);
        return $contentType[1];
    }

    protected function charsetFromXML($markup): string|bool
    {
        $matches = [];
        // find declaration
        preg_match('@<'.'?xml[^>]+encoding\\s*=\\s*(["|\'])(.*?)\\1@i',
            $markup, $matches
        );
        return isset($matches[2]) ? strtolower($matches[2])
            : false;
    }

    protected function charsetFixHTML($markup): string
    {
        $matches = [];
        // find meta tag
        preg_match('@\s*<meta[^>]+http-equiv\\s*=\\s*(["|\'])Content-Type\\1([^>]+?)>@i',
            $markup, $matches, PREG_OFFSET_CAPTURE
        );
        if(!isset($matches[0]))
            return '';
        $metaContentType = $matches[0][0];
        $markup = substr($markup, 0, $matches[0][1])
            .substr($markup, $matches[0][1] + strlen($metaContentType));
        $headStart = stripos($markup, /** @lang text */ '<head>');
        return substr($markup, 0, $headStart + 6).$metaContentType
            .substr($markup, $headStart + 6);
    }

    protected function charsetAppendToHTML(string $html, string $charset, bool $xhtml = false): array|string|null
    {
        // remove existing meta[type=content-type]
        $html = preg_replace('@\s*<meta[^>]+http-equiv\\s*=\\s*(["|\'])Content-Type\\1([^>]+?)>@i', '', $html);
        $meta = '<meta http-equiv="Content-Type" content="text/html;charset='.$charset.'" '
            .($xhtml ? '/' : '').'>';
        if(!str_contains($html, '<head')) {
            if(!str_contains($html, '<html')) {
                return $meta.$html;
            } else {
                return preg_replace(
                    '@<html(.*?)(?(?<!\?)>)@s',
                    /** @lang text */ "<html\\1><head>$meta</head>",
                    $html
                );
            }
        } else {
            return preg_replace(
                '@<head(.*?)(?(?<!\?)>)@s',
                '<head\\1>'.$meta,
                $html
            );
        }
    }

    protected function charsetAppendToXML($markup, $charset): string
    {
        $declaration = '<'.'?xml version="1.0" encoding="'.$charset.'"?'.'>';
        return $declaration.$markup;
    }

    public static function isDocumentFragmentHTML($markup): bool
    {
        return stripos($markup, '<html') === false && stripos($markup, '<!doctype') === false;
    }

    public static function isDocumentFragmentXML($markup): bool
    {
        return stripos($markup, '<'.'?xml') === false;
    }

    public static function isDocumentFragmentXHTML($markup): bool
    {
        return self::isDocumentFragmentHTML($markup);
    }

    /**
     */
    public function importAttr($value)
    {
    }

    /**
     * @throws Exception
     */
    public function import($source, $sourceCharset = null): array
    {
        // TODO charset conversions
        $return = [];
        if($source instanceof DOMNODE)
            $source = array($source);
        if(is_array($source) || $source instanceof DOMNODELIST) {
            // dom nodes
            self::debug('Importing nodes to document');
            foreach($source as $node)
                $return[] = $this->document->importNode($node, true);
        } else {
            // string markup
            $fake = $this->documentFragmentCreate($source, $sourceCharset);
            if($fake === false)
                throw new Exception("Error loading documentFragment markup");
            else
                return $this->import($fake->root->childNodes);
        }
        return $return;
    }

    /**
     * @throws \Exception
     */
    protected function documentFragmentCreate($source, $charset = null): bool|DOMDocumentWrapper
    {
        $fake = new DOMDocumentWrapper();
        $fake->contentType = $this->contentType;
        $fake->isXML = $this->isXML;
        $fake->isHTML = $this->isHTML;
        $fake->isXHTML = $this->isXHTML;
        $fake->root = $fake->document;
        if(!$charset)
            $charset = $this->charset;
//	$fake->documentCreate($this->charset);
        if($source instanceof DOMNODE)
            $source = array($source);
        if(is_array($source) || $source instanceof DOMNODELIST) {
            // dom nodes
            // load fake document
            if(!$this->documentFragmentLoadMarkup($fake, $charset))
                return false;
            $nodes = $fake->import($source);
            foreach($nodes as $node)
                $fake->root->appendChild($node);
        } else {
            // string markup
            $this->documentFragmentLoadMarkup($fake, $charset, $source);
        }
        return $fake;
    }

    private function documentFragmentLoadMarkup($fragment, string $charset, string $markup = ''): bool
    {
        // tempolary turn off
        $fragment->isDocumentFragment = false;
        if($fragment->isXML) {
            if($fragment->isXHTML) {
                $fake = ["<fa", "ke xmlns=\"http://www.w3.org/1999/xhtml\">", $markup, '</fa', 'ke>'];
                // add FAKE element to set default namespace
                $fragment->loadMarkupXML('<?xml version="1.0" encoding="'.$charset.'"?>'
                    .'<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" '
                    .'"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'
                    .implode('', $fake));
                $fragment->root = $fragment->document->firstChild->nextSibling;
            } else {
                $fragment->loadMarkupXML('<?xml version="1.0" encoding="'.$charset.'"?><fake>'.$markup.'</fake>');
                $fragment->root = $fragment->document->firstChild;
            }
        } else {
            $markup2 = /** @lang text */
                phpQuery::$defaultDoctype.'<html><head><meta http-equiv="Content-Type" content="text/html;charset='
                .$charset.'"></head>';
            $noBody = !str_contains($markup, '<body');
            if($noBody)
                $markup2 .= '<body>';
            $markup2 .= $markup;
            if($noBody)
                $markup2 .= '</body>';
            $markup2 .= '</html>';
            $fragment->loadMarkupHTML($markup2);
            $fragment->root = $fragment->document->firstChild->nextSibling->firstChild->nextSibling;
        }
        if(!$fragment->root)
            return false;
        $fragment->isDocumentFragment = true;
        return true;
    }

    protected function documentFragmentToMarkup($fragment): string
    {
        phpQuery::debug('documentFragmentToMarkup');
        $tmp = $fragment->isDocumentFragment;
        $fragment->isDocumentFragment = false;
        $markup = $fragment->markup();
        if($fragment->isXML) {
            $markup = substr($markup, 0, strrpos($markup, '</fake>'));
            if($fragment->isXHTML) {
                $markup = substr($markup, strpos($markup, '<fake') + 43);
            } else {
                $markup = substr($markup, strpos($markup, '<fake>') + 6);
            }
        } else {
            $markup = substr($markup, strpos($markup, '<body>') + 6);
            $markup = substr($markup, 0, strrpos($markup, '</body>'));
        }
        $fragment->isDocumentFragment = $tmp;
        if(phpQuery::$debug)
            phpQuery::debug('documentFragmentToMarkup: '.substr($markup, 0, 150));
        return $markup;
    }

    /**
     * @throws \Exception
     */
    public function markup($nodes = null, $innerMarkup = false): false|array|string
    {
        if(isset($nodes) && count($nodes) == 1 && $nodes[0] instanceof DOMDOCUMENT)
            $nodes = null;
        if(isset($nodes)) {
            $markup = '';
            if(!is_array($nodes) && !($nodes instanceof DOMNODELIST))
                $nodes = array($nodes);
            if($this->isDocumentFragment && !$innerMarkup)
                foreach($nodes as $i => $node)
                    if($node->isSameNode($this->root)) {
                        //	var_dump($node);
                        $nodes = array_slice($nodes, 0, $i)
                            + phpQuery::DOMNodeListToArray($node->childNodes)
                            + array_slice($nodes, $i + 1);
                    }
            if($this->isXML && !$innerMarkup) {
                self::debug("Getting outerXML with charset ".$this->charset);
                // we need outerXML, so we can benefit from
                // $node param support in saveXML()
                foreach($nodes as $node)
                    $markup .= $this->document->saveXML($node);
            } else {
                $loop = [];
                if($innerMarkup)
                    foreach($nodes as $node) {
                        if($node->childNodes)
                            foreach($node->childNodes as $child)
                                $loop[] = $child;
                        else
                            $loop[] = $node;
                    }
                else
                    $loop = $nodes;
                self::debug("Getting markup, moving selected nodes (".count($loop).") to new DocumentFragment");
                $fake = $this->documentFragmentCreate($loop);
                $markup = $this->documentFragmentToMarkup($fake);
            }
            if($this->isXHTML) {
                self::debug("Fixing XHTML");
                $markup = self::markupFixXHTML($markup);
            }
            self::debug("Markup: ".substr($markup, 0, 250));
        } else {
            if($this->isDocumentFragment) {
                // documentFragment, html only...
                self::debug("Getting markup, DocumentFragment detected");
//				return $this->markup(
////					$this->document->getElementsByTagName('body')->item(0)
//					$this->document->root, true
//				);
                $markup = $this->documentFragmentToMarkup($this);
                // no need for markupFixXHTML, as it's done thought markup($nodes) method
            } else {
                self::debug("Getting markup (".($this->isXML ? 'XML' : 'HTML')."), final with charset ".$this->charset);
                $markup = $this->isXML ? $this->document->saveXML() : $this->document->saveHTML();
                if($this->isXHTML) {
                    self::debug("Fixing XHTML");
                    $markup = self::markupFixXHTML($markup);
                }
                self::debug("Markup: ".substr($markup, 0, 250));
            }
        }
        return $markup;
    }

    /**
     * @param $markup
     * @return array|string
     */
    protected static function markupFixXHTML($markup): array|string
    {
        $markup = self::expandEmptyTag('script', $markup);
        $markup = self::expandEmptyTag('select', $markup);
        return self::expandEmptyTag('textarea', $markup);
    }

    public static function debug($text): void
    {
        phpQuery::debug($text);
    }

    public static function expandEmptyTag($tag, $xml): array|string
    {
        $indice = 0;
        while($indice < strlen($xml)) {
            $pos = strpos($xml, "<$tag ", $indice);
            if($pos) {
                $posCierre = strpos($xml, ">", $pos);
                if($xml[$posCierre - 1] == "/") {
                    $xml = substr_replace($xml, "></$tag>", $posCierre - 1, 2);
                }
                $indice = $posCierre;
            } else {
                break;
            }
        }
        return $xml;
    }
}