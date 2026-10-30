<?php

// Server code and its dependencies, plus the test helpers
$src = dirname(__DIR__).'/src';
$loader = require $src.'/vendor/autoload.php';
$loader->addPsr4('WT\\Tests\\', __DIR__);
// Z-Push classes and constants, as its index.php loads them
require $src.'/vendor/z-push/z-push-dev/src/vendor/autoload.php';

define('WT_EAS_ROOT', $src);
\WT\EAS\Config::load(__DIR__.'/config.json');
// Set EAS_TEST_LOG_LEVEL (eg. TRACE) to read what the backends log in the system temp directory
\WT\Log::init('eas-server', getenv('EAS_TEST_LOG_LEVEL') ?: 'OFF', sys_get_temp_dir().DIRECTORY_SEPARATOR.'webtop-eas-tests.log');

// Include path and Z-Push configuration, as inc/bootstrap.php sets them (logging is off: see config.json)
set_include_path(WT_EAS_ROOT.'/inc/zpush-overrides'.PATH_SEPARATOR.get_include_path()
	.PATH_SEPARATOR.WT_EAS_ROOT.\WT\EAS\Config::get()->getZPushSrc());
$_SERVER['SCRIPT_FILENAME'] = $src.'/vendor/z-push/z-push-dev/src/index.php';
// Syslog facilities do not exist in PHP for Windows: same value as on Linux
if (!defined('LOG_LOCAL0')) define('LOG_LOCAL0', 128);
// PHPUnit includes this file from a method: the variables of the configuration must be global, as Z-Push reads them
global $specialLogUsers, $additionalFolders;
include $src.'/inc/zpush.config.php';
// Defined by ZPush::CheckConfig() at every request: our log backend (ZPCore)
define('LOGBACKEND_CLASS', LOGBACKEND);

// Our backends, loaded as BackendWebTop loads them in production
foreach (['BackendWTContacts', 'BackendWTCalendar', 'BackendWTTasks'] as $backend) {
	BackendWebTop::IncludeBackend($backend);
}
