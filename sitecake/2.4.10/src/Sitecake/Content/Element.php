<?php

namespace Sitecake\Content;

use Sitecake\Sitecake;
use Sitecake\Util\InstanceConfigTrait;

/**
 * Class Element
 *
 * DomElement properties and methods
 *
 * @property bool             $schemaTypeInfo
 * @property string           $tagName
 *
 * @method string getAttribute (string $name)
 * @method \DOMAttr getAttributeNode (string $name)
 * @method \DOMAttr getAttributeNodeNS (string $namespaceURI, string $localName)
 * @method string getAttributeNS (string $namespaceURI, string $localName)
 * @method \DOMNodeList getElementsByTagName (string $name)
 * @method \DOMNodeList getElementsByTagNameNS (string $namespaceURI, string $localName)
 * @method bool hasAttribute (string $name)
 * @method bool hasAttributeNS (string $namespaceURI, string $localName)
 * @method bool removeAttribute (string $name)
 * @method bool removeAttributeNode (\DOMAttr $oldnode)
 * @method bool removeAttributeNS (string $namespaceURI, string $localName)
 * @method \DOMAttr setAttribute (string $name, string $value)
 * @method \DOMAttr setAttributeNode (\DOMAttr $attr)
 * @method \DOMAttr setAttributeNodeNS (\DOMAttr $attr)
 * @method void setAttributeNS (string $namespaceURI, string $qualifiedName, string $value)
 * @method void setIdAttribute (string $name, bool $isId)
 * @method void setIdAttributeNode (\DOMAttr $attr, bool $isId)
 * @method void setIdAttributeNS (string $namespaceURI, string $localName, bool $isId)
 *
 * DOMNode properties and methods
 *
 * @property string           $nodeName
 * @property string           $nodeValue
 * @property int              $nodeType
 * @property \DOMNode         $parentNode
 * @property \DOMNodeList     $childNodes
 * @property \DOMNode         $firstChild
 * @property \DOMNode         $lastChild
 * @property \DOMNode         $previousSibling
 * @property \DOMNode         $nextSibling
 * @property \DOMNamedNodeMap $attributes
 * @property \DOMDocument     $ownerDocument
 * @property string           $namespaceURI
 * @property string           $prefix
 * @property string           $localName
 * @property string           $baseURI
 * @property string           $textContent
 *
 * @method \DOMNode appendChild (\DOMNode $newnode)
 * @method string C14N (bool $exclusive, bool $with_comments, array $xpath = null, array $ns_prefixes = null)
 * @method int C14NFile (string $uri, bool $exclusive, array $with_comments, array $xpath = null, array $ns_prefixes = null)
 * @method \DOMNode cloneNode ($deep = null)
 * @method int getLineNo ()
 * @method string getNodePath ()
 * @method bool hasAttributes ()
 * @method bool hasChildNodes ()
 * @method bool isDefaultNamespace (string $namespaceURI)
 * @method bool isSameNode (\DOMNode $node)
 * @method bool isSupported (string $feature, string $version)
 * @method string lookupNamespaceUri (string $prefix)
 * @method string lookupPrefix (string $namespaceURI)
 *
 * @package Sitecake\Content
 */
class Element
{
    use InstanceConfigTrait {
        setConfig as protected _setConfig;
    }

    /**
     * Default configuration used by element
     *
     * @var array
     */
    protected $defaultConfig = [];

    /**
     * @var \DOMElement
     */
    protected $domElement;

    /**
     * Element identifier
     *
     * @var string
     */
    protected $identifier;

    /**
     * Internal identifier used to distinguish elements with same identifier
     *
     * @var string
     */
    protected $uid;

    /**
     * Indicates whether element inner content is changed
     *
     * @var bool
     */
    protected $contentModified = false;

    /**
     * Indicates whether elements attributes are changed
     *
     * @var bool
     */
    protected $attributesModified = false;

    /**
     * Methods that modifies HTML content of element when called
     *
     * @var array
     */
    private static $contentModifiers = [
        'append',
        'appendChild'
    ];

    protected static $forbiddenContentModifiers = [
        'insertBefore',
        'removeChild',
        'replaceChild',
        'normalize'
    ];

    protected static $attributeModifiers = [
        'setDataAttribute',
        'removeAttribute',
        'removeAttributeNode',
        'removeAttributeNS',
        'setAttribute',
        'setAttributeNode',
        'setAttributeNodeNS',
        'setAttributeNS',
        'setIdAttribute',
        'setIdAttributeNode',
        'setIdAttributeNS',
    ];

    /**
     * Element constructor.
     *
     * @param null|string $html   Element HTML source
     * @param array       $config Element configuration
     */
    public function __construct($html, $config = [])
    {
        $this->_setConfig($config);
        $this->domElement = $this->createDOM($html)->item(0);
        //echo print_r($this->outerHtml(), true) . "\n\n";
        //$this->identifier = $this->findDOMIdentifier();
        $this->uid = uniqid($this->identifier);
    }

    public function isModified()
    {
        return $this->attributesModified || $this->contentModified;
    }

    public function outerHtml()
    {
        return $this->domElement->ownerDocument->saveHtml($this->domElement);
    }

    /**
     * Sets/gets inner HTML of an element.
     *
     * @param string|null $content
     *
     * @return string|null
     */
    public function html($content = null)
    {
        if ($content === null) {
            $innerHTML = '';
            $children = $this->domElement->childNodes;

            foreach ($children as $child) {
                $innerHTML .= $this->domElement->ownerDocument->saveHTML($child);
            }

            return $innerHTML;
        }

        // First, empty the element
        for ($i = $this->domElement->childNodes->length - 1; $i >= 0; $i--) {
            $this->domElement->removeChild($this->domElement->childNodes->item($i));
        }
        if (!empty($content)) {
            if ($content === strip_tags($content)) {
                $this->nodeValue = $content;
            } else {
                $this->append($content);
            }
        }

        $this->contentModified = true;
    }

    /**
     * Gets/sets inner text of element
     *
     * @param string|null $value
     *
     * @return string|null
     */
    public function text($value = null)
    {
        if ($value === null) {
            return $this->domElement->nodeValue;
        }

        $this->domElement->textContent = $value;

        $this->contentModified = true;
    }

    /**
     * Returns array of element attributes where keys are attribute names and values are attribute values
     *
     * @return array
     */
    public function getAttributes()
    {
        $return = [];
        if ($this->domElement->hasAttributes()) {
            $attributes = $this->domElement->attributes;
            foreach ($attributes as $attribute) {
                $return[$attribute->nodeName] = $attribute->nodeValue;
            }
        }

        return $return;
    }

    /**
     * Returns whether element has passed data attribute
     *
     * @param string $attribute Attribute name to check
     *
     * @return bool
     */
    public function hasDataAttribute($attribute)
    {
        return $this->domElement->hasAttribute('data-' . $attribute);
    }

    /**
     * Returns the value of the data attribute, or an empty string if no attribute with the given name is found.
     *
     * @param string $attribute Attribute name
     *
     * @return string
     */
    public function getDataAttribute($attribute)
    {
        return $this->getAttribute('data-' . $attribute);
    }

    /**
     * Sets attribute on a element
     *
     * @param string $attribute
     * @param string $value
     *
     * @return \DOMAttr
     */
    public function setDataAttribute($attribute, $value)
    {
        $attr = $this->setAttribute('data-' . $attribute, $value);

        return $attr;
    }

    /**
     * Returns whether element has passed class name
     *
     * @param string $className
     *
     * @return bool
     */
    public function hasClass($className)
    {
        $classNames = explode(' ', $this->getAttribute('class'));

        return in_array($className, $classNames);
    }

    /**
     * Adds passed class name to the element and returns updated class attribute
     *
     * @param string $className
     *
     * @return string
     */
    public function addClass($className)
    {
        $classNames = explode(' ', $this->getAttribute('class'));
        if (!in_array($className, $classNames)) {
            $classNames[] = $className;
        }

        $classStr = implode(' ', $classNames);

        $this->setAttribute('class', $classStr);

        return $classStr;
    }

    /**
     * Removes passed class name if exists and returns updated class attribute
     *
     * @param string $className
     *
     * @return string
     */
    public function removeClass($className)
    {
        $classNames = explode(' ', $this->getAttribute('class'));
        if (($index = array_search($className, $classNames)) === false) {
            return $className;
        }

        array_splice($classNames, $index, 1);

        $classStr = implode(' ', $classNames);

        $this->setAttribute('class', $classStr);

        return $classStr;
    }

    /**
     * Returns whether attributes are modified on element
     *
     * @param bool $modified
     *
     * @return bool
     */
    public function attributesModified($modified = null)
    {
        if ($modified === null) {
            return $this->attributesModified;
        }

        return $this->attributesModified = (bool)$modified;
    }

    public function __get($name)
    {
        if (property_exists($this->domElement, $name)) {
            return $this->domElement->{$name};
        }

        return null;
    }

    public function __call($name, array $arguments)
    {
        /*
         * DOMNode::insertBefore method can't be called on element because there is no way to track positions
         * from Element class. Method is implemented in ContentManager class
         */
        if (method_exists($this->domElement, $name) && !in_array($name, self::$forbiddenContentModifiers)) {
            $result = call_user_func_array([$this->domElement, $name], $arguments);
            if (in_array($name, self::$contentModifiers)) {
                $this->contentModified = true;
            } elseif (in_array($name, self::$attributeModifiers)) {
                $this->attributesModified = true;
            }

            return $result;
        }

        throw new \RuntimeException('Call to undefined method on Element instance');
    }

    /**
     * Appends passed HTML code to passed DOM node
     *
     * @param string|\DOMElement $content
     *
     * @return \DOMNode|\DOMNodeList|\DOMElement
     */
    protected function append($content)
    {
        if ($content instanceof \DOMElement) {
            $content = $this->domElement->ownerDocument->importNode($content, true);

            $this->contentModified = true;

            return $this->domElement->appendChild($content);
        }
        if ($content instanceof Element) {
            $content = $content->outerHtml();
        }

        $elements = $this->createDOM($content);

        foreach ($elements as $node) {
            $node = $this->domElement->ownerDocument->importNode($node, true);
            $this->domElement->appendChild($node);
        }

        if ($elements->length === 1) {
            return $elements->item(0);
        }

        $this->contentModified = true;

        return $elements;
    }

    /**
     * Returns whether elements content is modified in any way
     *
     * @param bool $modified
     *
     * @return bool
     */
    public function contentModified($modified = null)
    {
        if ($modified === null) {
            return $this->contentModified;
        }

        return $this->contentModified = (bool)$modified;
    }

    /**
     * Returns length of elements HTML
     *
     * @return int
     */
    public function length()
    {
        return mb_strlen($this->outerHtml());
    }

    /**
     * Returns DOMElement created of passed HTML code
     *
     * @param string $html HTML code
     *
     * @return \DOMNodeList
     */
    protected function createDOM($html)
    {
        $doc = new \DOMDocument();
        // Suppress HTML5 errors
        libxml_use_internal_errors(true);
        $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', Sitecake::getConfig('encoding')));
        libxml_use_internal_errors(false);

        $baseElements = ['head', 'body'];
        $headElements = ['title', 'base', 'link', 'meta', 'script', 'style'];

        // Strip comments in case that passed html starts with a comment
        if (($withoutComments = preg_replace('/<!--[^>]*-->/', '', $html)) === null) {
            $withoutComments = $html;
        }

        // Base elements should be accessed directly
        if (preg_match('/^<(' . implode('|', $baseElements) . ')(\s|>)/', $withoutComments, $matches)) {
            return $doc->documentElement->getElementsByTagName($matches[1]);
        // Head elements should be accessed through head
        } elseif (preg_match('/^<(' . implode('|', $headElements) . ')(\s|>)/', $withoutComments, $matches)) {
            return $doc->documentElement->getElementsByTagName('head')->item(0)->childNodes;
        // All other elements will be appended to body
        } else {
            return $doc->documentElement->getElementsByTagName('body')->item(0)->childNodes;
        }
    }
}
