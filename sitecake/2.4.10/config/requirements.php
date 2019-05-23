<?php
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}
// Check for php version
$phpVersion = preg_split("/[:.]/", phpversion());
if (($phpVersion[0] * 10 + $phpVersion[1]) < 56) {
    trigger_error("PHP version $phpVersion[0].$phpVersion[1].$phpVersion[2] is found on your web hosting.
		PHP version 5.6 (or greater) is required.", E_USER_ERROR);
}
// Check if GD is present
if (!extension_loaded('gd')) {
    trigger_error("GD lib (PHP extension) is required for Sitecake to run.", E_USER_ERROR);
}
// Check if mbstring is present
if (!extension_loaded('mbstring')) {
    trigger_error("mbstring lib (PHP extension) is required for Sitecake to run.", E_USER_ERROR);
} elseif (!function_exists('mb_eregi')) {
    trigger_error("mbstring lib (PHP extension) has to have `mbregex` enabled for Sitecake to run.", E_USER_ERROR);
}
// Check if document directory is writable and readable
$rootSiteDir = realpath(dirname(__FILE__) . '/../../../');
if (!is_writable($rootSiteDir) || !is_readable($rootSiteDir)) {
    trigger_error("Root site directory have to be readable and writable.", E_USER_ERROR);
}

// Echo OK message if requirements.php file is accessed directly
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    echo "Basic server configuration needed for Sitecake to run is OK";
}
