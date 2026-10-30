<?php

$v = \WT\Util::readVersion();
// The Z-Push version is the one of the installed package, as Z-Push itself
// reports it (defining it here also avoids Z-Push's git lookups in version.php)
$zpVersionFile = __DIR__.'/vendor/z-push/z-push-dev/src/version';
$zpVersion = is_readable($zpVersionFile) ? trim((string)file_get_contents($zpVersionFile)) : '';
define('ZPUSH_VERSION', ($zpVersion !== '') ? $zpVersion : substr($v, 0, strrpos($v, '.')));
define('WEBTOP-EAS-SERVER_NAME', 'webtop-eas-server');
define('WEBTOP-EAS-SERVER_VERSION', $v);
