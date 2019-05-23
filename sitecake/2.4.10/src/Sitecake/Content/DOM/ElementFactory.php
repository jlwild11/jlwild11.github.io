<?php

namespace Sitecake\Content\DOM;

use Sitecake\Content\DOM\Element\PageElement;
use Sitecake\Content\Exception\InvalidElementTypeException;
use Sitecake\Content\Exception\UnregisteredElementTypeException;

class ElementFactory
{
    /**
     * Collection of registered elements
     *
     * @var array
     */
    public static $registry = [];

    /**
     * Registers passed element
     *
     * @param string $class Class name
     * @param array $config Configuration to pass to element constructor
     *
     * @return void
     */
    public static function registerElementType($class, $config = [])
    {
        if (!is_subclass_of($class, Element::class)) {
            throw new InvalidElementTypeException($class);
        }
        $class::setConfig($config);
        $type = call_user_func([$class, 'type']);
        self::$registry[$type] = $class;
    }

    /**
     * Creates new element instance of a passed type
     *
     * @param string $type
     * @param string $html
     *
     * @return Element
     */
    public static function createElement($type, $html)
    {
        if (!isset(self::$registry[$type])) {
            return new PageElement($type, $html);
        }

        $class = self::$registry[$type];
        return new $class($html);
    }

    public static function getClass($type)
    {
        if (!isset(self::$registry[$type])) {
            return PageElement::class;
        }

        return self::$registry[$type];
    }
}
