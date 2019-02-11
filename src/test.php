<?php

require_once 'version.php';
require_once 'vendor/autoload.php';

define('WT_EAS_ROOT', __DIR__);
\WT\EAS\Config::load('config.json');

$tzBlob = "xP///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAoAAAAFAAMAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAFAAIAAAAAAAAAxP///w==";
echo $tzBlob;
$tzObj = \WT\EAS\ZPUtil::decodeTzBlob($tzBlob);
echo print_r($tzObj);
echo "\n";
$guessed = \WT\EAS\ZPUtil::guessTimezoneIDFromObj($tzObj, 'Europe/Rome');
echo "guessed: $guessed";


