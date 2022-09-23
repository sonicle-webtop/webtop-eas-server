<?php

error_reporting(E_ALL & ~E_NOTICE & ~E_USER_NOTICE);
// Tweaking the php error_reporting is not sufficient, z-push will internally
// z-push will internally reset the configuration to E_ALL.
// We need to set also this customized const...
define('LOG_ERROR_MASK', ~(E_NOTICE|E_USER_NOTICE|E_STRICT));

require_once 'vendor/autoload.php';

define('WT_EAS_ROOT', __DIR__);
\WT\EAS\Config::load(WT_EAS_ROOT . DIRECTORY_SEPARATOR . 'config.json');
$config = \WT\EAS\Config::get();
\WT\Log::init('eas-server', $config->getLogLevel(), $config->getLogFile());

$ZPUSH_TARGET_SCRIPT = 'index.php';
include WT_EAS_ROOT.'/inc/zpush.php';
