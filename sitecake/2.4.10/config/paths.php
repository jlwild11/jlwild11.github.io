<?php
$paths = [];

/*
 * An absolute filesystem path to sitecake dir.
 * It is used to instantiate the filesystem abstraction.
 */
$paths['SITECAKE_DIR'] = realpath(__DIR__ . '/../../');

/* An absolute pah to plugins directory */
$paths['PLUGINS_DIR'] = $paths['SITECAKE_DIR'] . DIRECTORY_SEPARATOR . 'plugins';

/* An absolute path to sitecake directory where current version files are stored */
$paths['SITECAKE_CORE'] = $paths['SITECAKE_DIR'] . DIRECTORY_SEPARATOR . SITECAKE_VERSION;

/*
 * An absolute filesystem path to the site root directory (where sitecake entry point file is located).
 * It is used only to instantiate the filesystem abstraction. From this point on, all
 * paths are relative (to the SITE_ROOT) and all paths can be used as relative URLs as well.
 */
$paths['SITE_ROOT'] = realpath(__DIR__ . '/../../../');

/* URL relative to sitecake.php that Sitecake editor is using as the entry point to the CMS service API */
$paths['SERVICE_URL'] = 'sitecake/' . SITECAKE_VERSION . '/src/app.php';

/* Base URL to location where sitecake client source is stored relative to site root URL */
$paths['SITECAKE_CLIENT_BASE_URL'] = 'sitecake/' . SITECAKE_VERSION . '/client';

/* URL relative to sitecake.php that Sitecake editor is using to load the editor configuration */
$paths['EDITOR_CONFIG_URL'] = 'sitecake/editor.cnf';

/* A relative path to sitecake credential file */
$paths['CREDENTIALS_PATH'] = $paths['SITECAKE_DIR'] . DIRECTORY_SEPARATOR . 'credentials.php';

return $paths;
