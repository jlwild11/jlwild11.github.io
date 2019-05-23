<?php

namespace Sitecake\Resources;

use Cake\Event\EventListenerInterface;

interface ResourceHandlerInterface extends EventListenerInterface
{
    /**
     * Returns whether handler support passed resource
     *
     * @param string $path Resource path
     *
     * @return bool
     */
    public function supports($path);

    /**
     * Returns handler type which is also used as alias when registering handler
     *
     * @return string
     */
    public static function type();

    /**
     * Returns regular expressions to match paths that handler supports
     *
     * @return string
     */
    public function getPathMatcher();

    /**
     * Returns draft for resource under passed path.
     * If second parameter is passed it is considered as source.
     *
     * @param string                                 $path
     * @param string|resource|ResourceInterface|null $resource
     *
     * @return mixed
     */
    public function createDraft($path, $resource = null);

    /**
     * Prepared resource under passed resource path for publishing
     *
     * @param string $draftPath
     *
     * @return bool
     */
    public function prepareForPublish($draftPath);

    /**
     * Returns URL that can be used inside HTML based on passed path.
     *
     * @param string $path
     *
     * @return string
     */
    public function pathToUrl($path);

    /**
     * Normalize passed path
     *
     * @return string
     */
    public function normalizePath($path);

    /**
     * Returns list of required paths needed by handler to be created.
     * If no paths are needed should return null.
     * Method received base draft path that can be used to build required path.
     *
     * @param string $draftPath
     *
     * @return string|array|null
     */
    public function requiredPaths($draftPath);
}
