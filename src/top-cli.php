<?php

require_once 'version.php';
require_once 'vendor/autoload.php';

define('WT_EAS_ROOT', __DIR__);
\WT\EAS\Config::load('config.json');

//$ZPUSH_SRC = \WT\EAS\Config::get()->getZPushSrc();
$ZPUSH_TARGET_SCRIPT = 'z-push-top.php';
if (php_sapi_name() !== 'cli') {
	die('<h1>'.$ZPUSH_TARGET_SCRIPT.' must NOT be called as web-page --> exiting !!!</h1>');
}
include WT_EAS_ROOT.'/inc/zpush.php';

//$_SERVER['SCRIPT_FILENAME'] = __DIR__.$ZPUSH_SRC.'/'.$ZPUSH_SCRIPT;
//chdir(__DIR__.$ZPUSH_SRC);
//define('ZPUSH_CONFIG', __DIR__.'/inc/config.php');
//include(__DIR__.$ZPUSH_SRC.'/'.$ZPUSH_SCRIPT);
