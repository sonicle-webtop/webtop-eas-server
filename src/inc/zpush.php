<?php

$BASE = dirname(__DIR__);
$ZPUSH_SRC = \WT\EAS\Config::get()->getZPushSrc();

$_SERVER['SCRIPT_FILENAME'] = WT_EAS_ROOT.$ZPUSH_SRC.'/'.$ZPUSH_TARGET_SCRIPT;
chdir(WT_EAS_ROOT.$ZPUSH_SRC);
define('ZPUSH_CONFIG', WT_EAS_ROOT.'/inc/zpush.config.php');
include WT_EAS_ROOT.$ZPUSH_SRC.'/'.$ZPUSH_TARGET_SCRIPT;
