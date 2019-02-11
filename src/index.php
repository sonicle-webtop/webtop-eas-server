<?php

require_once 'version.php';
require_once 'vendor/autoload.php';

define('WT_EAS_ROOT', __DIR__);
\WT\EAS\Config::load('config.json');

$ZPUSH_TARGET_SCRIPT = 'index.php';
include WT_EAS_ROOT.'/inc/zpush.php';
