<?php
$config = [];

/**
 * SYSTEM CONFIGURATION (You probably shouldn't change this if you are not sure what you are doing :))
 *
 * Debug mode.
 * This should be set to false on production server
 */
$config['debug'] = false;

/**
 * Entry point file name
 * By default entry point for Sitecake CMS is sitecake.php file in site root directory.
 * If you need to change filename of this file also change it  here in configuration
 */
$config['entry_point_file_name'] = 'sitecake.php';

/**
 * Application encoding.
 */
$config['encoding'] = 'UTF-8';

/**
 * Server timezone defaults to UTC. You can change it to another timezone of your
 * choice but using UTC makes time calculations / conversions easier.
 * Check http://php.net/manual/en/timezones.php for list of valid timezone strings.
 */
$config['timezone'] = 'UTC';



/**
 * SESSION CONFIGURATION SECTION
 *
 * Session handler.
 * By default 'files' session handler is used. This value can also be set to 'memcache', 'memcached' or 'redis'
 * if your environment support these options. If neither of values above is working, set this value to null and let
 * Sitecake to try to figure things out
 */
$config['session.save_handler'] = 'files';

/**
 * Options for selected session handler.
 * For native session handlers valid storage options that can be set are :
 *      cache_limiter, "nocache" (use "0" to prevent headers from being sent entirely).
 *      cookie_domain, ""
 *      cookie_httponly, ""
 *      cookie_lifetime, "0"
 *      cookie_path, "/"
 *      cookie_secure, ""
 *      entropy_file, ""
 *      entropy_length, "0"
 *      gc_divisor, "100"
 *      gc_maxlifetime, "1440"
 *      gc_probability, "1"
 *      hash_bits_per_character, "4"
 *      hash_function, "0"
 *      name, "PHPSESSID"
 *      referer_check, ""
 *      serialize_handler, "php"
 *      use_cookies, "1"
 *      use_only_cookies, "1"
 *      use_trans_sid, "0"
 *      upload_progress.enabled, "1"
 *      upload_progress.cleanup, "1"
 *      upload_progress.prefix, "upload_progress_"
 *      upload_progress.name, "PHP_SESSION_UPLOAD_PROGRESS"
 *      upload_progress.freq, "1%"
 *      upload_progress.min-freq, "1"
 *      url_rewriter.tags, "a=href,area=href,frame=src,form=,fieldset="
 *      save_path, ""
 * For 'memcache' and 'memcached' handlers additional valid options are:
 *      prefix, "sc"
 *      expiretime, 86400 (24h)
 *      servers, [['127.0.0.1', 11211]]
 * For 'redis' handler additional valid options are:
 *      prefix, "sc"
 *      expiretime, 86400 (24h)
 *      server, ['127.0.0.1', 6379]
 */
$config['session.options'] = [];

/**
 * Example configuration for memcache session storage
 */
//$config['session.save_handler'] = 'memcache';
//$config['session.options'] = [
//  'servers' => [['127.0.0.1', 11211]]
//];

/**
 * Example configuration for redis session storage
 */
//$config['session.save_handler'] = 'redis';
//$config['session.options'] = [
//  'server' => ['127.0.0.1', 6379]
//];



/**
 * FILESYSTEM CONFIGURATION SECTION
 *
 * If the PHP process on the server has permission to write to the website
 * root files (e.g. to delete/update html files, to create and delete folders)
 * use 'local' filesystem adapter.
 */
$config['filesystem.adapter'] = 'local';

/**
 * If the PHP process on the server doesn't have permission to write to the website
 * root files then use the 'ftp' adapter and provide necessary FTP access properties.
 * FTP protocol will be used to manage the website root files.
 */
//$config['filesystem.adapter'] = 'ftp';
// optional ftp adapter config settings
//$config['filesystem.adapter.config'] = [
//    'root' => '/path/to/root',
//    'passive' => true,
//    'ssl' => true,
//    'timeout' => 30,
//    'host' => 'ftp.example.com',
//    'username' => 'username',
//    'password' => 'password',
//    'port' => 21
//];



/**
 * LOG CONFIGURATION SECTION
 *
 * File size that certain log file can reach before it is archived and new log file is created
 */
$config['log.size'] = '2MB';

/**
 * The number of the recent log archives to be kept
 */
$config['log.archive_size'] = 5;

/**
 * Uncomment to define specific path to log file. Otherwise default path will be used.
 * Should be relative path to sitecake.php file
 */
//$config['log.path'] = 'path/to/your/log/file';



/**
 * ERROR CONFIGURATION SECTION
 *
 * Error reporting level
 */
$config['error.level'] = E_ALL & ~E_DEPRECATED & ~E_STRICT;



/**
 * SITE CONFIGURATION SECTION
 *
 * The number of the recent site versions to be kept in backup
 */
$config['site.number_of_backups'] = 2;

/**
 * Default home page names
 */
$config['site.default_pages'] = ['index.html', 'index.htm', 'index.php', 'index.shtml', 'index.php5'];

/**
 * 'images' directory name
 */
$config['site.images_directory_name'] = 'images';


/**
 * IMAGE MANIPULATION CONFIGURATION SECTION
 *
 * List of image widths in pixels that would be used for generating
 * images for srcset attribute.
 * @see http://w3c.github.io/html/semantics-embedded-content.html#element-attrdef-img-srcset
 */
$config['image.srcset_widths'] = [1280, 960, 640, 320];

/**
 * List of qualities (0-100) to be used when generating images for srcset attribute.
 * Also can be set as int (0-100) so same quality will be used for all generated images.
 * If list of qualities set, keys should be one of set widths from 'image.srcset_widths' config var
 * and quality should be value wanted for that width. See example below:
 *   'image.srcset_widths' =>  [1200, 900, 600, 300]
 *   'image.srcset_qualities' => [
 *      '1200' => 10, // Quality of 10 will bw used when generating 1200w image
 *      '900' => 20, // Quality of 20 will bw used when generating 900w image
 *      '600' => 30, // Quality of 30 will bw used when generating 600w image
 *      '300' => 40 // Quality of 40 will bw used when generating 300w image
 *  ]
 * This quality will be used only when working with JPEG images
 *
 * @see http://php.net/manual/en/function.imagejpeg.php
 */
$config['image.srcset_qualities'] = 75;

/**
 * 'images' directory name
 */
$config['image.directory_name'] = 'images';

/**
 * Max relative diff (in percents) between two image widths in pixels
 * so they could be considered similar
 */
$config['image.srcset_width_maxdiff'] = 20;

/**
 * Valid extensions for uploaded images
 */
$config['image.valid_extensions'] = ['jpg', 'jpeg', 'png', 'gif'];


/**
 * UPLOADS CONFIGURATION SECTION
 *
 * Files with these extensions will be rejected on upload
 */
$config['upload.forbidden_extensions'] = ['php', 'php5', 'php4', 'php3', 'phtml', 'phpt'];

/**
 * 'files' directory name
 */
$config['upload.directory_name'] = 'files';

/**
 * CONTENT CONFIGURATION SECTION
 *
 * Indentation to use when updating content containers
 */
$config['content.indent'] = '    ';


/**
 * PAGES CONFIGURATION SECTION
 *
 * If this is set to TRUE, in case when page is modified through editor and content is not published, but same page is
 * also uploaded manually, SiteCake will take uploaded page's content as valid one.
 * If this is set to FALSE, manual changes will be disregarded.
 */
$config['pages.prioritize_manual_changes'] = true;

/**
 * Indicates weather generated pages should be linked relatively to document root or site root
 * If this option is set to TRUE, document relative paths will be used for navigation links href value.
 * Otherwise site relative paths would be used.
 */
$config['pages.use_document_relative_paths'] = false;

/**
 * Indicates weather default page name should be used when building navigation url or not
 * e.g. if this is set to true, sitecake will use /about/index.html instead of /about in urls by default
 */
$config['pages.use_default_page_name_in_url'] = false;

/**
 * The main navigation item template
 */
$config['menus.item_template'] = '<li><a class="${active}" href="${url}" title="${titleText}">${title}</a></li>';

/**
 * An alternative nav configuration example, without using <ul> tag as the container and <li> tags for menu items
 */
//$config['menus.item_template'] = '<li><a accesskey="${order}" href="${url}">${title}</a> <em>(${order})</em></li>';

/**
 * CSS class to be used for active menu link. If set to false, no class will be used
 * In a dynamic site where menu is defined in include file this will probably not be applicable
 */
$config['menus.active_class'] = 'active';

return $config;
