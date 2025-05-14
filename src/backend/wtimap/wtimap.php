<?php

// overridden config file
require_once(dirname(__FILE__) . '/config.php');

use WT\EAS\Config;

class BackendWTIMAP extends BackendIMAP {
	
	public function __construct() {
		global $imap_smtp_params;
		
		if (Config::get()->getMailSmtpServerEnabled()) {
			ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendWTIMAP(): customizing imap_smtp_params..."));
			$imap_smtp_params['host'] = Config::get()->getMailSmtpServer();
			$imap_smtp_params['port'] = Config::get()->getMailSmtpPort();
			$imap_smtp_params['auth'] = Config::get()->getMailSmtpAuth();
			$imap_smtp_params['username'] = Config::get()->getMailSmtpUsername();
			$imap_smtp_params['password'] = Config::get()->getMailSmtpPassword();
			if (Config::get()->getMailSmtpNoSSLCertificateCheck()) {
				$imap_smtp_params['verify_peer'] = false;
				$imap_smtp_params['verify_peer_name'] = false;
				$imap_smtp_params['allow_self_signed'] = true;
			}
		}
		
		parent::__construct();
	}
}
