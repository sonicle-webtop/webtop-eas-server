<?php

error_reporting(E_ALL & ~E_NOTICE);
// Tweaking the php error_reporting is not sufficient, z-push will internally
// z-push will internally reset the configuration to E_ALL.
// We need to set also this customized const...
define('LOG_ERROR_MASK', ~(E_NOTICE|E_STRICT));

require_once 'version.php';
require_once 'vendor/autoload.php';

define('WT_EAS_ROOT', __DIR__);
\WT\EAS\Config::load('config.json');

$ZPUSH_TARGET_SCRIPT = 'index.php';
include WT_EAS_ROOT.'/inc/zpush.php';
