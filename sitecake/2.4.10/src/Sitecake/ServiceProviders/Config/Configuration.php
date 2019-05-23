<?php

namespace Sitecake\ServiceProviders\Config;

use Sitecake\Exception\ConfigSourceNotFoundException;
use Sitecake\ServiceProviders\File\Exception\ConfigLockedException;
use Sitecake\ServiceProviders\File\Exception\InvalidConfigSourceException;
use Sitecake\Util\Hash;

class Configuration
{
    const DEFAULT_CONFIGURATION_KEY = 'default';

    /**
     * @var ConfigLoaderRegistry
     */
    protected $registry;

    protected $data = [];

    protected $locked = [];

    protected $mutators = [];

    public function __construct(ConfigLoaderRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param mixed $source
     * @param string $key
     * @param bool   $merge
     *
     * @return array Loaded configuration or false if requested key is locked
     *
     * @throws InvalidConfigSourceException
     * @throws ConfigSourceNotFoundException
     * @throws ConfigLockedException
     */
    public function load($source, $key = self::DEFAULT_CONFIGURATION_KEY, $merge = true)
    {
        $loaders = $this->registry->loaded();

        if (!in_array($key, $this->locked)) {
            $loaded = false;
            foreach ($loaders as $loader) {
                if (($config = $this->registry->get($loader)->load($source)) !== false) {
                    if ($merge && isset($this->data[$key])) {
                        $this->data[$key] = array_merge($this->data[$key], $config);
                        $loaded = true;
                    } else {
                        if (!isset($this->data[$key])) {
                            $this->data[$key] = $config;
                        }
                        $loaded = true;
                    }
                    break;
                }
            }

            if (!$loaded) {
                throw new InvalidConfigSourceException([
                    'source' => $source
                ]);
            }

            return $this->data[$key];
        }

        throw new ConfigLockedException(['key' => $key]);
    }

    /**
     * Loads and returns loaded configuration and locks passed key
     *
     * @param mixed $source
     * @param string $key
     * @param bool   $merge
     *
     * @return array
     */
    public function consume($source, $key = self::DEFAULT_CONFIGURATION_KEY, $merge = true)
    {
        $config = $this->load($source, $key, $merge);
        unset($this->data[$key]);
        return $config;
    }

    /**
     * Locks passed configuration key
     *
     * @param string $key
     */
    protected function lock($key)
    {
        $this->locked[] = $key;
    }

    /**
     * Registers passed mutator
     *
     * @param callable $mutator
     */
    public function registerMutator($mutator)
    {
        if (!is_callable($mutator) && !method_exists($mutator, '__invoke')) {
            throw new \LogicException('Mutator has to be a Closure or invokable object.');
        }
        $this->mutators[] = $mutator;
    }

    /**
     * Wrapper method for ConfigLoaderRegistry::registerLoader
     *
     * @param string $name
     * @param array $config
     *
     * @return ConfigLoaderInterface
     * @throws \Exception
     */
    public function registerLoader($name, array $config = [])
    {
        return $this->registry->registerLoader($name, $config);
    }

    /**
     * Runs passed value(s) through registered mutators and returns mutated value.
     * If array passed values will be processed recursively
     *
     * @param mixed $value
     *
     * @return mixed
     */
    protected function mutate($value)
    {
        if (!empty($this->mutators)) {
            foreach ($this->mutators as $mutator) {
                if (is_array($value)) {
                    foreach ($value as &$item) {
                        $item = $this->mutate($item);
                    }
                } else {
                    $value = $mutator($value);
                }
            }
        }

        return $value;
    }

    /**
     * Used to read information stored in $this->>data. It's not
     * possible to store `null` values in $this->>data.
     *
     * Usage:
     * ```
     * $configure->read('Name'); will return all values for $configure->data[Name]
     * $configure->read('Name.key'); will return only the value of $configure->data[Name][key]
     * ```
     *
     * @param string|null $var Variable to obtain. Use '.' to access array elements.
     * @param bool $mutate Indicates whether searched data should be mutated before returning or not
     *
     * @return mixed Value stored in configure, or null.
     */
    public function read($var = null, $mutate = false)
    {
        if ($var === null) {
            return $mutate ? $this->mutate($this->data) : $this->data;
        }

        $data = Hash::get($this->data, $var);

        if ($data) {
            return $mutate ? $this->mutate($data) : $data;
        }

        return null;
    }

    /**
     * Returns true if given variable is set in $this->>data.
     *
     * @param string $var Variable name to check for
     * @return bool True if variable is there
     */
    public function check($var)
    {
        if (empty($var)) {
            return false;
        }

        return $this->read($var, false) !== null;
    }
}
