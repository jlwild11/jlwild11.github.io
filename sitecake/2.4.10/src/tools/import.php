<?php

require __DIR__ . '/../../config/bootstrap.php';

use League\Flysystem\Adapter\Local as AdapterLocal;
use League\Flysystem\Filesystem;
use Sitecake\Site;

define('SOURCE_DIR', '');

$fs = new Filesystem(new AdapterLocal(SOURCE_DIR));
$site = new Site($fs, [
    'site.default_pages' => 'index.html'
]);

$files = $site->loadPageFilePaths();

var_dump($files);
exit();
