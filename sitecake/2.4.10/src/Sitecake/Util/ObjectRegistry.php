<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

namespace Sitecake\Util;

use ArrayIterator;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventListenerInterface;
use Countable;
use IteratorAggregate;
use RuntimeException;

/**
 * Acts as a registry/factory for objects.
 *
 * Provides registry & factory functionality for object types.
 *
 * Each subclass needs to implement the various abstract methods to complete
 * the template method load().
 *
 * The ObjectRegistry is EventManager aware, but each extending class will need to use
 * \Cake\Event\EventDispatcherTrait to attach and detach on set and bind
 */
abstract class ObjectRegistry implements Countable, IteratorAggregate
{
    /**
     * Map of loaded objects.
     *
     * @var object[]
     */
    protected $loaded = [];

    /**
     * Loads/constructs an object instance.
     *
     * Will return the instance in the registry if it already exists.
     * If a subclass provides event support, you can use `$config['enabled'] = false`
     * to exclude constructed objects from being registered for events.
     *
     * Using Cake\Controller\Controller::$components as an example. You can alias
     * an object by setting the 'className' key, i.e.,
     *
     * ```
     * public $components = [
     *   'Email' => [
     *     'className' => '\App\Controller\Component\AliasedEmailComponent'
     *   ];
     * ];
     * ```
     *
     * All calls to the `Email` component would use `AliasedEmail` instead.
     *
     * @param string $objectName The name/class of the object to load.
     * @param array  $config     Additional settings to use when loading the object.
     *
     * @return mixed
     * @throws \Exception
     */
    protected function load($objectName, $config = [])
    {
        if (is_array($config) && isset($config['className'])) {
            $name = $objectName;
            $objectName = $config['className'];
        } else {
            $name = $objectName;
        }

        $loaded = isset($this->loaded[$name]);
        if ($loaded && !empty($config)) {
            $this->checkDuplicate($name, $config);
        }
        if ($loaded) {
            return $this->loaded[$name];
        }

        $className = $this->resolveClassName($objectName);
        if (!$className || (is_string($className) && !class_exists($className))) {
            $this->throwMissingClassError($objectName);
        }
        $instance = $this->create($className, $name, $config);
        if ($this instanceof EventDispatcherInterface && $instance instanceof EventListenerInterface) {
            $this->getEventManager()->on($instance);
        }
        $this->loaded[$name] = $instance;

        return $instance;
    }

    /**
     * Check for duplicate object loading.
     *
     * If a duplicate is being loaded and has different configuration, that is
     * bad and an exception will be raised.
     *
     * An exception is raised, as replacing the object will not update any
     * references other objects may have. Additionally, simply updating the runtime
     * configuration is not a good option as we may be missing important constructor
     * logic dependent on the configuration.
     *
     * @param string $name   The name of the alias in the registry.
     * @param array  $config The config data for the new instance.
     *
     * @return void
     * @throws \RuntimeException When a duplicate is found.
     */
    protected function checkDuplicate($name, $config)
    {
        $existing = $this->loaded[$name];
        $msg = sprintf('The "%s" alias has already been loaded', $name);
        $hasConfig = method_exists($existing, 'getConfig');
        if (!$hasConfig) {
            throw new RuntimeException($msg);
        }
        if (empty($config)) {
            return;
        }
        $existingConfig = $existing->getConfig();
        unset($config['enabled'], $existingConfig['enabled']);

        $fail = false;
        foreach ($config as $key => $value) {
            if (!array_key_exists($key, $existingConfig)) {
                $fail = true;
                break;
            }
            if (isset($existingConfig[$key]) && $existingConfig[$key] !== $value) {
                $fail = true;
                break;
            }
        }
        if ($fail) {
            $msg .= ' with the following config: ';
            $msg .= var_export($existingConfig, true);
            $msg .= ' which differs from ' . var_export($config, true);
            throw new RuntimeException($msg);
        }
    }

    /**
     * Should resolve the classname for a given object type.
     *
     * @param string $class The class to resolve.
     *
     * @return string|bool The resolved name or false for failure.
     */
    abstract protected function resolveClassName($class);

    /**
     * Throw an exception when the requested object name is missing.
     *
     * @param string $class The class that is missing.
     *
     * @return void
     * @throws \Exception
     */
    abstract protected function throwMissingClassError($class);

    /**
     * Create an instance of a given classname.
     *
     * This method should construct and do any other initialization logic
     * required.
     *
     * @param string $class  The class to build.
     * @param string $alias  The alias of the object.
     * @param array  $config The Configuration settings for construction
     *
     * @return mixed
     */
    abstract protected function create($class, $alias, $config);


    /**
     * Gets alias for passed class name.
     * Method converts full namespaced camelize class name to underscored class name
     *
     * eg. $this->>getAlias('UploadService', 'Service') => upload
     *     $this->>getAlias('ImageUploadService', 'Service') => image_upload
     *     $this->>getAlias('ImageUploadService') => image_upload_service
     *
     * @param string $class
     * @param string $type
     *
     * @return string
     */
    protected function getAlias($class, $type = '')
    {
        $nameParts = explode('\\', $class);
        $name = array_pop($nameParts);

        if ($type !== '' && Text::endsWith($type, $name)) {
            $name = str_replace($type, '', $name);
        }

        return Text::underscore($name);
    }


    /**
     * Get the list of loaded objects.
     *
     * @return array List of object names.
     */
    protected function loaded()
    {
        return array_keys($this->loaded);
    }

    /**
     * Check whether or not a given object is loaded.
     *
     * @param string $name The object name to check for.
     *
     * @return bool True is object is loaded else false.
     */
    protected function has($name)
    {
        return isset($this->loaded[$name]);
    }

    /**
     * Get loaded object instance.
     *
     * @param string $name Name of object.
     *
     * @return object|null Object instance if loaded else null.
     */
    public function get($name)
    {
        if (isset($this->loaded[$name])) {
            return $this->loaded[$name];
        }

        return null;
    }

    /**
     * Set an object directly into the registry by name.
     *
     * If this collection implements events, the passed object will
     * be attached into the event manager
     *
     * @param string $objectName The name of the object to set in the registry.
     * @param object $object instance to store in the registry
     * @return void
     * @throws \Exception
     */
    protected function set($objectName, $object)
    {
        $this->unload($objectName);
        if ($this instanceof EventDispatcherInterface && $object instanceof EventListenerInterface) {
            $this->getEventManager()->on($object);
        }
        $this->loaded[$objectName] = $object;
    }

    /**
     * Clear loaded instances in the registry.
     *
     * If the registry subclass has an event manager, the objects will be detached from events as well.
     *
     * @return $this
     * @throws \Exception
     */
    protected function reset()
    {
        foreach (array_keys($this->loaded) as $name) {
            $this->unload($name);
        }

        return $this;
    }

    /**
     * Remove an object from the registry.
     *
     * If this registry has an event manager, the object will be detached from any events as well.
     *
     * @param string $objectName The name of the object to remove from the registry.
     *
     * @return $this
     * @throws \Exception
     */
    protected function unload($objectName)
    {
        if (empty($this->loaded[$objectName])) {
            $this->throwMissingClassError($objectName);
        }

        $object = $this->loaded[$objectName];
        if ($this instanceof EventDispatcherInterface && $object instanceof EventListenerInterface) {
            $this->getEventManager()->off($object);
        }
        unset($this->loaded[$objectName]);

        return $this;
    }

    /**
     * Returns an array iterator.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->loaded);
    }

    /**
     * Returns the number of loaded objects.
     *
     * @return int
     */
    public function count()
    {
        return count($this->loaded);
    }
}
