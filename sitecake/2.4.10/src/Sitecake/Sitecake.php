<?php

namespace Sitecake;

use Cake\Event\EventManager;
use Silex\Application;
use Sitecake\Api\BootableInterface;
use Sitecake\Api\Exception\InvalidPluginDefinitionException;
use Sitecake\Api\Exception\MissingPluginException;
use Sitecake\Api\PluginInterface;
use Sitecake\Api\Sitecake as SitecakeApi;
use Sitecake\Content\DOM\ElementFactory;
use Sitecake\Content\MenuManager;
use Sitecake\Exception\InternalException;
use Sitecake\PageManager\PageManager;
use Sitecake\PageManager\TemplateManager;
use Sitecake\Plugin\PluginRegistry;
use Sitecake\Resources\Handler\ImageHandler;
use Sitecake\Resources\Handler\UploadHandler;
use Sitecake\Resources\ResourceManager;
use Sitecake\ServiceProviders\SessionServiceProvider;
use Sitecake\Services\ContentService;
use Sitecake\Services\ImageService;
use Sitecake\Services\PagesService;
use Sitecake\Services\ServiceRegistry;
use Sitecake\Services\SessionService;
use Sitecake\Services\UploadService;
use Sitecake\Util\Hash;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Class Sitecake
 *
 * @package Sitecake
 */
class Sitecake
{
    /**
     * Sitecake temporary files root dir name
     */
    const SITECAKE_TEMP_DIR_NAME = 'sitecake-temp';

    /**
     * @var Sitecake
     */
    protected static $instance;

    /**
     * @var Application
     */
    protected $app;

    /**
     * Absolute path to sitecake config dir
     *
     * @var string
     */
    protected $configPath;

    /**
     * Indicates whether configuration is initialized
     *
     * @var bool
     */
    protected $configInitialized = false;

    /**
     * Global configuration.
     *
     * @var array
     */
    protected static $config = [];

    /**
     * Hold all needed paths for sitecake to function
     *
     * @var array
     */
    protected static $paths = [];

    /**
     * Stores whether request handling is initialized or not
     *
     * @var bool
     */
    protected $requestHandlingInitialized = false;

    /**
     * Absolute path to plugin directory
     *
     * @var string
     */
    protected $pluginPath;

    /**
     * Array of loaded plugins
     *
     * @var array
     */
    protected $plugins = [];

    /**
     * Sitecake constructor.
     * Sitecake can't be instantiated (Singleton) so this method is protected
     *
     * @param Application $app
     * @param string      $configPath
     */
    protected function __construct(Application $app, $configPath)
    {
        $this->setApp($app);
        $this->configPath = $configPath;
    }

    /**
     * No cloning because of singleton
     */
    protected function __clone()
    {
    }

    /**
     * Initializes Silex app and creates singleton instance
     *
     * @param Application $app Silex application
     * @param string      $configPath
     *
     * @return Sitecake
     */
    public static function createInstance($app, $configPath)
    {
        if (self::$instance) {
            throw new \LogicException('Sitecake is already instantiated.');
        }

        // Create singleton instance
        self::$instance = new static($app, $configPath);

        return self::$instance;
    }

    protected function boot()
    {
        // Load default configuration and paths
        $this->loadConfigurationAndPaths();
        // Set paths and URLs to site root and sitecake resources
        $this->buildPaths();
        // Set default timezone
        date_default_timezone_set(self::getConfig('timezone'));
        // Set encoding
        mb_internal_encoding(self::getConfig('encoding'));
        // Update filesystem service provider adapter config property
        $this->app['filesystem.adapter.config'] = self::$paths['SITE_ROOT'];
        // Ensures sitecake working directory basic structure
        $this->ensureDirStructure();
        // Update cache directory path for cache service provider
        $this->app['local_cache.dir'] = $this->getCachePath();
        // Update log file location for monolog service provider (logger)
        $this->app['monolog.logfile'] = $this->app['filesystem']->fullPath($this->getLogsPath() . '/sitecake.log');
        // Update default session save path for session service provider
        $this->app['session.default_save_path'] = $this->app['filesystem']->fullPath($this->getTmpPath());
        // Expose public API
        $this->initialize();
        // Load plugins and initialize them
        $this->setPluginPath(self::$paths['SITECAKE_DIR']);
        $this->loadPlugins();
    }

    /**
     * Sets global configuration
     * Configuration is result of merging default configuration and local configuration if provided
     */
    protected function loadConfigurationAndPaths()
    {
        if (!$this->configInitialized) {
            // Get default configuration
            $defaults = $this->loadDefaults();

            // Load default paths
            $this->loadPaths();

            // Get local configuration in present
            $local = $this->loadLocalConfiguration();

            $this->app['config'] = self::$config = Hash::expand(array_merge($defaults, $local));

            $this->configInitialized = true;
        }
    }

    /**
     * Loads default configuration and paths
     *
     * @return array
     */
    protected function loadDefaults()
    {
        // Consume current default configuration
        return $this->app['configuration']->consume(
            $this->configPath . DIRECTORY_SEPARATOR . 'default.php'
        );
    }

    /**
     * Checks if local config file exist and if so, loads it and returns.
     *
     * @return array
     */
    protected function loadLocalConfiguration()
    {
        // Merge local configuration is present
        if (file_exists(self::$paths['SITECAKE_DIR'] . DIRECTORY_SEPARATOR . 'config.php')) {
            return $this->app['configuration']->consume(
                self::$paths['SITECAKE_DIR'] . DIRECTORY_SEPARATOR . 'config.php'
            );
        }

        return [];
    }

    /**
     * Loads default paths
     */
    protected function loadPaths()
    {
        self::$paths = $this->app['configuration']->consume(
            $this->configPath . DIRECTORY_SEPARATOR . 'paths.php'
        );
    }

    /**
     * Sets path to site root and URLs to sitecake resources
     *
     * @return void
     */
    protected function buildPaths()
    {
        // Overwrite default site root if set in configuration
        if (($siteRoot = self::getConfig('SITE_ROOT')) !== null) {
            if (self::$paths['SITE_ROOT'] !== $siteRoot) {
                self::$paths['SITE_ROOT'] = $siteRoot;
            }
        }
        // Overwrite default sitecake client base url if set in configuration
        if (($clientBaseUrl = self::getConfig('SITECAKE_CLIENT_BASE_URL')) !== null) {
            self::$paths['SITECAKE_CLIENT_BASE_URL'] = $clientBaseUrl;
        }

        /*
         * Set resource URLs
         *
         * ~ EDITOR_LOGIN_URL - URL relative to sitecake.php that Sitecake editor is using to load the login module
         * ~ EDITOR_EDIT_URL - URL relative to sitecake.php that Sitecake editor is using to load the editor module
         * ~ PAGEMANAGER_JS_URL - URL relative to sitecake.php that Sitecake is using to load the pagemanager module
         * ~ PAGEMANAGER_VENDORS_URL - URL relative to sitecake.php that Sitecake is using to load the pagemanager
         *                             vendor file
         * ~ PAGEMANAGER_CSS_URL - URL relative to sitecake.php that Sitecake is using to load CSS
         *                         for pagemanager module
         */
        self::$paths['EDITOR_LOGIN_URL'] =
            self::$paths['SITECAKE_CLIENT_BASE_URL'] . '/publicmanager/publicmanager.nocache.js';

        self::$paths['EDITOR_EDIT_URL'] =
            self::$paths['SITECAKE_CLIENT_BASE_URL'] . '/contentmanager/contentmanager.nocache.js';

        self::$paths['PAGEMANAGER_JS_URL'] =
            self::$paths['SITECAKE_CLIENT_BASE_URL'] . '/pagemanager/js/pagemanager.' . SITECAKE_VERSION . '.js';

        self::$paths['PAGEMANAGER_VENDORS_URL'] =
            self::$paths['SITECAKE_CLIENT_BASE_URL'] . '/pagemanager/js/vendor.' . SITECAKE_VERSION . '.js';

        self::$paths['PAGEMANAGER_CSS_URL'] =
            self::$paths['SITECAKE_CLIENT_BASE_URL'] . '/pagemanager/css/pagemanager.' . SITECAKE_VERSION . '.css';
    }

    /**
     * Creates initial directory structure Sitecake needs to run, if not already created
     * Default structure is:
     *      + SITE_ROOT
     *          + sitecake-temp/
     *              + <random-working-dir[/r[0-9a-f]{13}/]>/
     *                  + tmp/
     *                  +    cache/
     *                  + logs/
     * If SITE_ROOT is overwritten with custom path, sitecake will create 'tmp/cache' and 'logs' dirs inside
     * 'sitecake' dir
     *
     * @return void
     */
    protected function ensureDirStructure()
    {
        // check/create sitecake-temp
        try {
            if (!$this->app['filesystem']->ensureDir(self::SITECAKE_TEMP_DIR_NAME)) {
                throw new \LogicException(
                    'Could not ensure that the directory /' . self::SITECAKE_TEMP_DIR_NAME . ' is present and writable.'
                );
            }
        } catch (\RuntimeException $e) {
            throw new \LogicException(
                'Could not ensure that the directory /' . self::SITECAKE_TEMP_DIR_NAME . ' is present and writable.'
            );
        }
        // check/create sitecake-temp/<random-working-dir>
        try {
            $workingDir = $this->app['filesystem']->randomDir(self::SITECAKE_TEMP_DIR_NAME);
            if ($workingDir === false) {
                throw new \LogicException(
                    'Could not ensure that the work directory in /' .
                    self::SITECAKE_TEMP_DIR_NAME . ' is present and writable.'
                );
            }
            self::$paths['SITECAKE_WORKING_DIR'] = $workingDir;
        } catch (\RuntimeException $e) {
            throw new \LogicException(
                'Could not ensure that the work directory in /' .
                self::SITECAKE_TEMP_DIR_NAME . ' is present and writable.'
            );
        }
        // check/create sitecake-temp/<random-working-dir>/tmp
        try {
            $tmpPath = $this->app['filesystem']->ensureDir(self::$paths['SITECAKE_WORKING_DIR'] . '/tmp');
            if ($tmpPath === false) {
                throw new \LogicException('Could not ensure that the directory '
                    . self::$paths['SITECAKE_WORKING_DIR'] . '/tmp is present and writable.');
            }
            self::$paths['TMP'] = $tmpPath;
        } catch (\RuntimeException $e) {
            throw new \LogicException('Could not ensure that the directory '
                . self::$paths['SITECAKE_WORKING_DIR'] . '/tmp is present and writable.');
        }
        // check/create sitecake-temp/<random-working-dir>/tmp/cache
        try {
            $cachePath = $this->app['filesystem']->ensureDir(self::$paths['TMP'] . '/cache');
            if ($cachePath === false) {
                throw new \LogicException('Could not ensure that the directory '
                    . self::$paths['TMP'] . '/cache is present and writable.');
            }
            self::$paths['CACHE'] = $cachePath;
        } catch (\RuntimeException $e) {
            throw new \LogicException('Could not ensure that the directory '
                . self::$paths['TMP'] . '/cache is present and writable.');
        }
        // check/create sitecake-temp/<random-working-dir>/logs
        try {
            $logsPath = $this->app['filesystem']->ensureDir(self::$paths['SITECAKE_WORKING_DIR'] . '/logs');
            if ($logsPath === false) {
                throw new \LogicException('Could not ensure that the directory '
                    . self::$paths['SITECAKE_WORKING_DIR'] . '/logs is present and writable.');
            }
            self::$paths['LOGS'] = $logsPath;
        } catch (\RuntimeException $e) {
            throw new \LogicException('Could not ensure that the directory '
                . self::$paths['SITECAKE_WORKING_DIR'] . '/logs is present and writable.');
        }
    }

    /**
     * Initialize sitecake managers and handlers
     */
    protected function initialize()
    {
        // Register Session provider
        $this->app->register(new SessionServiceProvider());

        // Initialize metadata manager
        $this->app['sc.cache'] = function ($app) {
            return new Cache($app['local_cache']);
        };

        $this->app['sc.resource_manager'] = function ($app) {
            return new ResourceManager($app['filesystem'], $app['sc.cache']);
        };
        $this->app->extend('sc.resource_manager', function (ResourceManager $resourceManager) {
            $resourceManager->registerHandler(ImageHandler::class, [
                'validExtensions' => self::getConfig('image.valid_extensions'),
                'imageDirName' => self::getConfig('image.directory_name')
            ]);
            $resourceManager->registerHandler(UploadHandler::class, [
                'forbiddenExtensions' => self::getConfig('upload.forbidden_extensions'),
                'uploadsDirName' => self::getConfig('upload.directory_name')
            ]);

            $resourceManager->ignore(self::SITECAKE_TEMP_DIR_NAME . '/');

            return $resourceManager;
        });

        // Initialize backup manager
        $this->app['sc.backup_manager'] = function ($app) {
            return new BackupManager($app['filesystem'], $app['sc.resource_manager']);
        };

        // Initialize site manager
        $this->app['sc.site'] = function ($app) {
            return new Site(
                $app['filesystem'],
                $app['sc.resource_manager'],
                $app['sc.backup_manager']
            );
        };

        // Initialize menu manager
        $this->app['sc.template_manager'] = function ($app) {
            return new TemplateManager($app['sc.resource_manager']);
        };

        // Initialize page manager
        $this->app['sc.page_manager'] = function ($app) {
            return new PageManager(
                $app['sc.resource_manager'],
                $app['sc.template_manager'],
                $app['sc.cache'],
                $app['filesystem']
            );
        };

        // Initialize menu manager
        $this->app['sc.menu_manager'] = function ($app) {
            return new MenuManager($app['sc.site'], $app['sc.page_manager'], $app['sc.resource_manager']);
        };

        // Set file lock handler
        $this->app['sc.flock'] = function ($app) {
            return new FileLock($app['filesystem'], $this->getTmpPath());
        };

        // Set session manager
        $this->app['sc.session_manager'] = function ($app) {
            return new SessionManager($app['session'], $app['sc.flock']);
        };

        // Set API provider
        $this->app['sc.api'] = function ($app) {
            return new SitecakeApi($app);
        };

        $this->app['sc.plugin_registry'] = function ($app) {
            return new PluginRegistry($app['PLUGINS_DIR'], $app['class_loader'], $app['configuration'], $app['sc.api']);
        };

        // Set Service Registry
        $this->app['sc.service_registry'] = function ($app) {
            return new ServiceRegistry($app['sc.site']);
        };
        $this->app->extend('sc.service_registry', function (ServiceRegistry $serviceRegistry, $app) {
            $serviceRegistry->register(ContentService::class, [
                'pageManager' => $app['sc.page_manager']
            ]);
            $serviceRegistry->register(ImageService::class, [
                'resourceManager' => $app['sc.resource_manager'],
                'metadata' => $app['sc.cache'],
                'imageExtensions' => self::getConfig('image.valid_extensions'),
                'imgDirName' => self::getConfig('image.directory_name'),
                'srcSetWidths' => self::getConfig('image.srcset_widths'),
                'srcSetQuality' => self::getConfig('image.srcset_qualities'),
                'srcSetWidthMaxDiff' => self::getConfig('image.srcset_width_maxdiff')
            ]);
            $serviceRegistry->register(PagesService::class, [
                'pageManager' => $app['sc.page_manager'],
                'menuManager' => $app['sc.menu_manager'],
                'resourceManager' => $app['sc.resource_manager'],
                'metadata' => $app['sc.cache']
            ]);
            $serviceRegistry->register(SessionService::class, [
                'CREDENTIALS_PATH' => self::$paths['CREDENTIALS_PATH'],
                'sessionManager' => $app['sc.session_manager'],
                'pageManager' => $app['sc.page_manager'],
                'resourceManager' => $app['sc.resource_manager']
            ]);
            $serviceRegistry->register(UploadService::class, [
                'resourceManager' => $app['sc.resource_manager'],
                'metadata' => $app['sc.cache'],
                'forbiddenExtensions' => self::getConfig('upload.forbidden_extensions'),
                'uploadDirName' => self::getConfig('upload.directory_name')
            ]);

            return $serviceRegistry;
        });

        // Set Action Dispatcher
        $this->app['sc.action_dispatcher'] = function ($app) {
            $dispatcher = new ActionDispatcher(
                $app['sc.session_manager'],
                $app['sc.service_registry'],
                $app['exception_handler']
            );
            $dispatcher->setDefaultAction('_pages', 'render');
            $dispatcher->setLoginAction('_session', 'login');

            return $dispatcher;
        };
    }

    /**
     * Runs Silex application
     *
     * @return void
     */
    public function run()
    {
        $this->boot();
        $this->app->run();
    }

    /**
     * Initialize request handling for passed silex application
     *
     * @return bool
     */
    public function initializeRequestHandling()
    {
        if (!$this->requestHandlingInitialized) {
            $controller = function (Application $app, Request $request) {
                return $this->handleRequest($app, $request);
            };
            $this->app->match('/', $controller)->method("GET");
            $this->app->match('/', $controller)->method("POST");

            return $this->requestHandlingInitialized = true;
        }

        return false;
    }

    /**
     * Invokes action dispatcher and handles possible thrown exceptions
     *
     * @param Application $app
     * @param Request     $request
     *
     * @return Response
     */
    protected function handleRequest(Application $app, Request $request)
    {
        try {
            return $this->app['sc.action_dispatcher']->dispatch($request);
        } catch (InternalException $e) {
            $this->app['logger']->error((string)$e);

            return new Response($this->app['exception_handler']->handleException($e, true), 500);
        } catch (\Exception $e) {
            $this->app['logger']->error((string)$e);
            $code = $e->getCode();

            // TODO : Check if there is more codes used in app
            $possibleCodes = [400, 401, 403, 404, 405, 500];

            // TODO : Need to send real http status when new client is implemented
            if (empty($code) || !in_array($code, $possibleCodes)) {
                $code = 500;
            }

            return new Response($this->app['exception_handler']->handleException($e, true), $code);
        }
    }

    protected function setApp(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Sets plugin path based on passed source dir path
     *
     * @param string $baseSourceDir
     *
     * @return void
     */
    protected function setPluginPath($baseSourceDir)
    {
        $this->pluginPath = $baseSourceDir . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR;
    }

    /**
     * Returns general Plugin path or path to specific plugin if plugin name passed.
     *
     * @param string $plugin Optional. If passed returned path will be for specific passed plugin
     *
     * @return string the draft dir path
     * @throws MissingPluginException If plugin for which path is requested is not loaded
     */
    protected function getPluginsPath($plugin = null)
    {
        if ($plugin === null) {
            return $this->pluginPath;
        }

        if (!isset($this->plugins[$plugin])) {
            throw new MissingPluginException(['plugin' => $plugin]);
        }

        return $this->pluginPath . strtolower($plugin);
    }

    /**
     * Loads plugins from sitecake/plugins directory
     */
    protected function loadPlugins()
    {
        $dir = new \DirectoryIterator($this->getPluginsPath());
        foreach ($dir as $fileInfo) {
            if (!$fileInfo->isDot() && $fileInfo->isDir()) {
                // Get base name which will be plugin alias
                $name = $fileInfo->getBasename();
                // Check for user defined configuration
                $config = self::getConfig($name);
                // Check for bootstrap file
                $bootstrapFile = $fileInfo->getRealPath() . DIRECTORY_SEPARATOR . 'bootstrap.php';
                if (file_exists($bootstrapFile)) {
                    $pluginLoader = \Closure::bind(function ($bootstrapFile, $sitecake, $config) {
                        return include $bootstrapFile;
                    }, null);
                    $pluginInstance = $pluginLoader($bootstrapFile, $this->app['sc.api'], $config);
                    if (!($pluginInstance instanceof PluginInterface)) {
                        throw new InvalidPluginDefinitionException(['plugin' => $name]);
                    }
                    $this->app['sc.plugin_registry']->add($name, $pluginInstance);
                } else {
                    $class = $this->app['sc.plugin_registry']->resolveClassName($name);
                    if (!class_exists($class)) {
                        throw new MissingPluginException([
                            'plugin' => $class
                        ]);
                    }
                    if (!is_subclass_of($class, PluginInterface::class)) {
                        throw new InvalidPluginDefinitionException(['plugin' => $name]);
                    }
                    $pluginInstance = $this->app['sc.plugin_registry']->load($class, $config);
                }

                if ($pluginInstance instanceof BootableInterface) {
                    $pluginInstance->boot();
                }
            }
        }
    }

    //<editor-fold desc="Static API">

    /**
     * Returns global configuration or whole configuration array.
     * If configuration not found default value will be returned
     *
     * @param string|null $key
     * @param mixed       $default
     *
     * @return array|mixed|null
     */
    public static function getConfig($key = null, $default = null)
    {
        if ($key === null) {
            return self::$config;
        }

        if (strpos($key, '.') === false) {
            return isset(self::$config[$key]) ? self::$config[$key] : null;
        }

        $return = self::$config;

        foreach (explode('.', $key) as $k) {
            if (!is_array($return) || !isset($return[$k])) {
                $return = null;
                break;
            }

            $return = $return[$k];
        }

        return $return === null ? $default : $return;
    }

    /**
     * Returns whether Sitecake is in debug mode or not
     *
     * @return bool
     */
    public static function isDebugMode()
    {
        return self::$instance->app['debug'];
    }

    /**
     * Returns resource manager instance
     *
     * @return mixed
     */
    public static function resourceManager()
    {
        return self::$instance->app['sc.resource_manager'];
    }

    /**
     * Returns event dispatcher instance
     *
     * @return EventManager
     */
    public static function eventBus()
    {
        return self::$instance->app['event_bus'];
    }

    public static function cache()
    {
        return self::$instance->app['sc.cache'];
    }

    public function getFilesystem()
    {
        return $this->app['filesystem'];
    }
    //</editor-fold>

    /**
     * Returns previous version of Sitecake if installed.
     *
     * Method is needed when creating local configuration file if doesn't exist
     */
    protected function previousVersion()
    {
        $versionDirs = $this->app['filesystem']->listPatternPaths('sitecake', '/^[0-9]\.[0-9]\.[0-9]$/');
        if (!empty($versionDirs) && count($versionDirs) > 1) {
            sort($versionDirs);
            // Pop current version
            array_pop($versionDirs);

            // Return previous version
            return array_pop($versionDirs);
        }

        return '';
    }

    //<editor-fold desc="API accessors">

    /**
     * Returns API instance
     *
     * @return \Sitecake\Api\Sitecake
     */
    public function getApi()
    {
        return $this->app['sc.api'];
    }

    /**
     * Returns specific path or  if no argument passed
     *
     * @param null|string $key
     *
     * @return string|array
     */
    public static function getPath($key = null)
    {
        if ($key !== null && array_key_exists($key, self::$paths)) {
            return self::$paths[$key];
        }

        return null;
    }

    /**
     * return all sitecake paths
     *
     * @return array
     */
    public static function getPaths()
    {
        return self::$paths;
    }

    /**
     * Returns the path of the temporary directory.
     *
     * @return string the tmp dir path
     */
    public function getTmpPath()
    {
        return self::$paths['TMP'];
    }

    /**
     * Returns the path of the logs directory.
     *
     * @return string the logs dir path
     */
    public static function getLogsPath()
    {
        return self::$paths['LOGS'];
    }

    /**
     * Returns the path of the logs directory.
     *
     * @return string the logs dir path
     */
    public static function getCachePath()
    {
        return self::$paths['CACHE'];
    }

    public function getResourceManager()
    {
        return $this->app['sc.resource_manager'];
    }

    public function getLogger()
    {
        return $this->app['sc.logger'];
    }

    /**
     * Accessor for service registry
     *
     * @return ServiceRegistry
     */
    public function services()
    {
        return $this->app['sc.service_registry'];
    }

    /**
     * Accessor for session manager
     *
     * @return SessionManager
     */
    public function getSessionManager()
    {
        return $this->app['sc.session_manager'];
    }

    /**
     * Accessor for session
     *
     * @return Session
     */
    public function getSession()
    {
        return $this->app['sc.session'];
    }

    /**
     * Accessor for session manager
     *
     * @return SessionManager
     */
    public function getFileLocker()
    {
        return $this->app['sc.flock'];
    }

    /**
     * Accessor for renderer
     *
     * @return ElementFactory
     */
    public function getElementFactory()
    {
        return $this->app['sc.element_factory'];
    }

    //</editor-fold>
}
