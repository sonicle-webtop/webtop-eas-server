<?php

namespace WT\EAS;

use WT\AbstractConfig;

class Config extends AbstractConfig {
	
	private static $instance;
	
	public static function load($file) {
		self::$instance = new Config($file);
	}
	
	public static function get() {
		if (!self::$instance) {
			throw new Exception(sprintf("Instance not available yet. Please call Config::load() first."));
		}
		return self::$instance;
	}
	
	private $defaults = [
		'timezone' => 'Europe/Rome',
		'log.level' => 'ERROR',
		'log.name' => 'server.log',
		'log.error.name' => 'server_error.log',
		'state.useLegacyFolderIds' => false,
		'webtop.apiUrlPath' => '/api/com.sonicle.webtop.core/v1',
		'mail.enabled' => true,
		'mail.imapServer' => 'localhost',
		'mail.imapPort' => 143,
		'calendar.enabled' => true,
		'calendar.apiUrlPath' => '/api/com.sonicle.webtop.calendar/v2',
		'contacts.enabled' => true,
		'contacts.apiUrlPath' => '/api/com.sonicle.webtop.contacts/v2',
		'tasks.enabled' => true,
		'tasks.apiUrlPath' => '/api/com.sonicle.webtop.tasks/v2'
	];
	
	protected function __construct($file) {
		parent::__construct($file);
		
		$timezone = $this->getTimezone();
		if (empty($timezone)) throw new Exception("Missing 'timezone' configuration.");
		if (!date_default_timezone_set($timezone)) {
			throw new Exception(sprintf("The specified timezone '%s' is not valid. Please check supported timezones at http://www.php.net/manual/en/timezones.php", $timezone));
		}
	}
	
	public function getZPushSrc() {
		return '/vendor/z-push/z-push/src';
	}
	
	public function getTimezone() {
		return $this->getValue('timezone', $this->defaults);
	}
	
	public function getLogLevel() {
		return $this->getValue('log.level', $this->defaults);
	}

	public function getLogDir() {
		return \WT\Util::stripTrailingDirSeparator($this->getValue('log.dir'));
	}
	
	public function getLogName() {
		return $this->getValue('log.name', $this->defaults);
	}
	
	public function getLogFile() {
		$v = $this->getValue('log.file', $this->defaults);
		if (!empty($v)) {
			return $v;
		} else {
			return $this->getLogDir().'/'.$this->getLogName();
		}
	}
	
	public function getErrorLogName() {
		return $this->getValue('log.error.name', $this->defaults);
	}
	
	public function getLogSpecialUsers() {
		$value = $this->getValue('log.specialUsers');
		return is_array($value) ? $value : array();
	}
	
	public function getStateDir() {
		return \WT\Util::stripTrailingDirSeparator($this->getValue('state.dir'));
	}
	
	public function getUseLegacyFolderIds() {
		return $this->getValue('state.useLegacyFolderIds', $this->defaults);
	}
	
	public function getWTApiBaseURL() {
		return \WT\Util::stripTrailingDirSeparator($this->getValue('webtop.apiBaseUrl', $this->defaults), '/');
	}
	
	public function getWTApiUrlPath() {
		return '/'.\WT\Util::stripLeadingDirSeparator($this->getValue('webtop.apiUrlPath', $this->defaults), '/');
	}
	
	public function getMailEnabled() {
		return $this->getValue('mail.enabled', $this->defaults);
	}
	
	public function getMailImapServer() {
		return $this->getValue('mail.imapServer', $this->defaults);
	}
	
	public function getMailImapPort() {
		return $this->getValue('mail.imapPort', $this->defaults);
	}
	
	public function getCalendarEnabled() {
		return $this->getValue('calendar.enabled', $this->defaults);
	}
	
	public function getCalendarApiUrlPath() {
		return '/'.\WT\Util::stripDirSeparator($this->getValue('calendar.apiUrlPath', $this->defaults), '/');
	}
	
	public function getContactsEnabled() {
		return $this->getValue('contacts.enabled', $this->defaults);
	}
	
	public function getContactsApiUrlPath() {
		return '/'.\WT\Util::stripDirSeparator($this->getValue('contacts.apiUrlPath', $this->defaults), '/');
	}
	
	public function getTasksEnabled() {
		return $this->getValue('tasks.enabled', $this->defaults);
	}
	
	public function getTasksApiUrlPath() {
		return '/'.\WT\Util::stripDirSeparator($this->getValue('tasks.apiUrlPath', $this->defaults), '/');
	}
}
