<?php

/*
 * Z-Push's IMAP backend loads "backend/imap/config.php" through the include
 * path, and bootstrap.php puts the zpush-overrides folder first: so it gets
 * WebTop's IMAP settings, already loaded by BackendWTIMAP, instead of Z-Push's
 * defaults. Defining every constant a second time is a warning on PHP 8 (it
 * was a notice on PHP 7), one per constant at each request.
 * The two constants defined only by Z-Push's file are optional: its code
 * checks them with defined() (IMAP_SEARCH_CHARSET, IMAP_FROM_LDAP_SERVER_URI).
 */
require_once WT_EAS_ROOT . '/backend/wtimap/config.php';
