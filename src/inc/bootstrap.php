<?php

/***********************************************
 * File      :   bootstrap.php
 * Project   :   webtop-eas-server
 * Descr     :   ZPush boostrap script
 *               Mainly gets the sources location from settings, it moves current
 *               directory to specified sub-folder (using $BOOTSTRAP_TARGET_FOLDER)
 *               or falling back to sources directory itself if missing, and then
 *               includes the specified PHP script (using $BOOTSTRAP_TARGET_SCRIPT)
 *               that exists in that folder.
 */

if (empty($BOOTSTRAP_TARGET_SCRIPT)) {
	die('bootstrap.php: missing TARGET_SCRIPT');
}

$BASE = dirname(__DIR__);
$ZPUSH_SRC = \WT\EAS\Config::get()->getZPushSrc();
set_include_path(get_include_path() . PATH_SEPARATOR . WT_EAS_ROOT . $ZPUSH_SRC);

$BOOTSTRAP_TARGET_FOLDER = dirname($BOOTSTRAP_TARGET_SCRIPT) == '.' ? '' : dirname($BOOTSTRAP_TARGET_SCRIPT);
$BOOTSTRAP_TARGET_SCRIPT = basename($BOOTSTRAP_TARGET_SCRIPT);

$_SERVER['SCRIPT_FILENAME'] = WT_EAS_ROOT . $ZPUSH_SRC . $BOOTSTRAP_TARGET_FOLDER . '/' . $BOOTSTRAP_TARGET_SCRIPT;
define('ZPUSH_CONFIG', WT_EAS_ROOT . '/inc/zpush.config.php');
chdir(WT_EAS_ROOT . $ZPUSH_SRC . $BOOTSTRAP_TARGET_FOLDER);
include WT_EAS_ROOT . $ZPUSH_SRC . $BOOTSTRAP_TARGET_FOLDER . '/' . $BOOTSTRAP_TARGET_SCRIPT;