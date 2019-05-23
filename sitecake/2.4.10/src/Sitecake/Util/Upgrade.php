<?php
namespace Sitecake\Util;

use PclZip;
use ZipArchive as ZipArchive;

class Upgrade
{
    public static function perform()
    {
        $latest = Upgrade::latestRemote();
        $current = Upgrade::latestLocal();

        return ($latest > $current) ?
            Upgrade::upgradeTo(Upgrade::toVersion($latest)) :
            ['status' => 0, 'upgrade' => 0];
    }

    public static function latestRemote()
    {
        $resp = client::get('http://sitecake.com/dl/upgrade/latest.txt');
        if ($resp->isSuccess()) {
            return Upgrade::version($resp->getBody());
        } else {
            return -1;
        }
    }

    public static function version($str)
    {
        if (preg_match('/([0-9]+)\.([0-9]+)\.([0-9]+)/', trim($str), $matches) >
            0
        ) {
            return $matches[1] * 1000000 + $matches[2] * 1000 + $matches[3];
        } else {
            return -1;
        }
    }

    public static function latestLocal()
    {
        $versions = io::glob(SC_ROOT . '/' . 'sitecake' . '/' .
                             '*.*.*', GLOB_ONLYDIR);

        return array_reduce($versions, function ($latest, $item) {
            $curr = Upgrade::version($item);

            return ($curr > $latest) ? $curr : $latest;
        }, -1);
    }

    public static function upgradeTo($ver)
    {
        $file = Upgrade::download($ver);
        if (is_array($file)) {
            $res = $file;
        } else {
            $res = Upgrade::extract($ver, $file);
            io::unlink($file);
        }
        if ($res['status'] == 0) {
            Upgrade::switchTo($ver);
        }

        return $res;
    }

    public static function download($ver)
    {
        $url = 'http://sitecake.com/dl/upgrade/sitecake-' .
               $ver . '-upgrade.zip';
        $resp = client::get($url);
        if ($resp->isSuccess()) {
            $file = TEMP_DIR . '/' . 'sitecake-' . $ver . '-upgrade.zip';
            io::file_put_contents($file, $resp->getBody());

            return $file;
        } else {
            return [
                'status' => -1,
                'errorMessage' => 'Unable to download upgrade from ' . $url
            ];
        }
    }

    public static function extract($ver, $file)
    {
        $dir = SC_ROOT . '/' . 'sitecake';
        if (class_exists('ZipArchive')) {
            $res = Upgrade::extractZipArchive($file, $dir);
        } else {
            $res = PclZip::extract($file, $dir);
        }

        return $res ?
            ['status' => 0, 'upgrade' => 1, 'latest' => $ver] :
            [
                'status' => -1,
                'errorMessage' => 'Unable to extract the upgrade archive'
            ];
    }

    public static function extractZipArchive($zipfile, $dest)
    {
        $z = new ZipArchive();
        if ($z->open($zipfile) === true) {
            return $z->extractTo($dest);
        } else {
            return false;
        }
    }

    public static function switchTo($ver)
    {
        io::file_put_contents(
            SC_ROOT . '/' . 'sitecake.php',
            "<?php include 'sitecake/$ver/server/admin.php';");
    }

    public static function toVersion($num)
    {
        $major = floor($num / 1000000);
        $minor = floor(($num - $major * 1000000) / 1000);
        $rev = $num - $major * 1000000 - $minor * 1000;

        return "$major.$minor.$rev";
    }
}
