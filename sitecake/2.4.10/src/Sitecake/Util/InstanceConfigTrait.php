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

use Sitecake\Exception\Exception;

/**
 * A trait for reading and writing instance config
 *
 * Implementing objects are expected to declare a `$_defaultConfig` property.
 */
trait InstanceConfigTrait
{

    /**
     * Runtime config
     *
     * @var array
     */
    protected $config = [];

    /**
     * Whether the config property has already been configured with defaults
     *
     * @var bool
     */
    protected $configInitialized = false;

    /**
     * Sets the config.
     *
     * ### Usage
     *
     * Setting a specific value:
     *
     * ```
     * $this->setConfig('key', $value);
     * ```
     *
     * Setting a nested value:
     *
     * ```
     * $this->setConfig('some.nested.key', $value);
     * ```
     *
     * Updating multiple config settings at the same time:
     *
     * ```
     * $this->setConfig(['one' => 'value', 'another' => 'value']);
     * ```
     *
     * @param string|array $key The key to set, or a complete array of configs.
     * @param mixed|null $value The value to set.
     * @param bool $merge Whether to recursively merge or overwrite existing config, defaults to true.
     * @return $this
     * @throws \Sitecake\Exception\Exception; When trying to set a key that is invalid.
     */
    public function setConfig($key, $value = null, $merge = true)
    {
        if (!$this->configInitialized) {
            $this->config = $this->defaultConfig;
            $this->configInitialized = true;
        }

        $this->_configWrite($key, $value, $merge);

        return $this;
    }

    /**
     * Returns the config.
     *
     * ### Usage
     *
     * Reading the whole config:
     *
     * ```
     * $this->getConfig();
     * ```
     *
     * Reading a specific value:
     *
     * ```
     * $this->getConfig('key');
     * ```
     *
     * Reading a nested value:
     *
     * ```
     * $this->getConfig('some.nested.key');
     * ```
     *
     * Reading with default value:
     *
     * ```
     * $this->getConfig('some-key', 'default-value');
     * ```
     *
     * @param string|null $key The key to read or null for the whole config.
     * @param mixed $default The return value when the key does not exist.
     * @return mixed Config value being read.
     */
    public function getConfig($key = null, $default = null)
    {
        if (!$this->configInitialized) {
            $this->config = $this->defaultConfig;
            $this->configInitialized = true;
        }

        $return = $this->_configRead($key);

        return $return === null ? $default : $return;
    }

    /**
     * Reads a config key.
     *
     * @param string|null $key Key to read.
     * @return mixed
     */
    protected function _configRead($key)
    {
        if ($key === null) {
            return $this->config;
        }

        if (strpos($key, '.') === false) {
            return isset($this->config[$key]) ? $this->config[$key] : null;
        }

        $return = $this->config;

        foreach (explode('.', $key) as $k) {
            if (!is_array($return) || !isset($return[$k])) {
                $return = null;
                break;
            }

            $return = $return[$k];
        }

        return $return;
    }

    /**
     * Writes a config key.
     *
     * @param string|array $key Key to write to.
     * @param mixed $value Value to write.
     * @param bool|string $merge True to merge recursively, 'shallow' for simple merge,
     *   false to overwrite, defaults to false.
     * @return void
     * @throws \Sitecake\Exception\Exception if attempting to clobber existing config
     */
    protected function _configWrite($key, $value, $merge = false)
    {
        if (is_string($key) && $value === null) {
            $this->_configDelete($key);

            return;
        }

        if ($merge) {
            $update = is_array($key) ? $key : [$key => $value];
            if ($merge === 'shallow') {
                $this->config = array_merge($this->config, Hash::expand($update));
            } else {
                $this->config = Hash::merge($this->config, Hash::expand($update));
            }

            return;
        }

        if (is_array($key)) {
            foreach ($key as $k => $val) {
                $this->_configWrite($k, $val);
            }

            return;
        }

        if (strpos($key, '.') === false) {
            $this->config[$key] = $value;

            return;
        }

        $update =& $this->config;
        $stack = explode('.', $key);

        foreach ($stack as $k) {
            if (!is_array($update)) {
                throw new Exception(sprintf('Cannot set %s value', $key));
            }

            if (!isset($update[$k])) {
                $update[$k] = [];
            }

            $update =& $update[$k];
        }

        $update = $value;
    }

    /**
     * Deletes a single config key.
     *
     * @param string $key Key to delete.
     * @return void
     * @throws \Sitecake\Exception\Exception if attempting to clobber existing config
     */
    protected function _configDelete($key)
    {
        if (strpos($key, '.') === false) {
            unset($this->config[$key]);

            return;
        }

        $update =& $this->config;
        $stack = explode('.', $key);
        $length = count($stack);

        foreach ($stack as $i => $k) {
            if (!is_array($update)) {
                throw new Exception(sprintf('Cannot unset %s value', $key));
            }

            if (!isset($update[$k])) {
                break;
            }

            if ($i === $length - 1) {
                unset($update[$k]);
                break;
            }

            $update =& $update[$k];
        }
    }
}
