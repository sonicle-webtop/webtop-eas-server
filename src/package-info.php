<?php

$v = \WT\Util::readVersion();
define('ZPUSH_VERSION', substr($v, 0, strrpos($v, '.')));
define('WEBTOP-EAS-SERVER_NAME', 'webtop-eas-server');
define('WEBTOP-EAS-SERVER_VERSION', $v);
