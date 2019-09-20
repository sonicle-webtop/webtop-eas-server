<?php

namespace lf4php\impl;

use Exception;
use lf4php\LocationLogger;
use lf4php\helpers\MessageFormatter;

class ZPLoggerAdapter extends LocationLogger {
	private $name;
	
	public function __construct($name) {
		$this->name = $name;
		$this->setLocationPrefix('');
	}
	
	public function getName() {
		return $this->name;
	}
	
	public function hasSpecialLogUsers() {
		global $specialLogUsers; // This variable comes from the configuration file (config.php)
		return !empty($specialLogUsers);
	}
	
	public function isDebugEnabled() {
		return constant('LOGLEVEL') >= LOGLEVEL_DEBUG || (constant('LOGUSERLEVEL') >= LOGLEVEL_DEBUG && $this->hasSpecialLogUsers());
	}

	public function isErrorEnabled() {
		return constant('LOGLEVEL') >= LOGLEVEL_FATAL || (constant('LOGUSERLEVEL') >= LOGLEVEL_FATAL && $this->hasSpecialLogUsers());
	}

	public function isInfoEnabled() {
		return constant('LOGLEVEL') >= LOGLEVEL_INFO || (constant('LOGUSERLEVEL') >= LOGLEVEL_INFO && $this->hasSpecialLogUsers());
	}

	public function isTraceEnabled() {
		return constant('LOGLEVEL') >= LOGLEVEL_WBXML || (constant('LOGUSERLEVEL') >= LOGLEVEL_WBXML && $this->hasSpecialLogUsers());
	}

	public function isWarnEnabled() {
		return constant('LOGLEVEL') >= LOGLEVEL_WARN || (constant('LOGUSERLEVEL') >= LOGLEVEL_WARN && $this->hasSpecialLogUsers());
	}
	
	public function debug($format, $params = array(), \Exception $e = null) {
		if ($this->isDebugEnabled()) {
			\ZLog::Write(LOGLEVEL_DEBUG, $this->getFormattedLocation() . MessageFormatter::format($format, $params) . $this->getExceptionString($e));
		}
	}

	public function error($format, $params = array(), \Exception $e = null) {
		if ($this->isErrorEnabled()) {
			\ZLog::Write(LOGLEVEL_ERROR, $this->getFormattedLocation() . MessageFormatter::format($format, $params) . $this->getExceptionString($e));
		}
	}

	public function info($format, $params = array(), \Exception $e = null) {
		if ($this->isInfoEnabled()) {
			\ZLog::Write(LOGLEVEL_INFO, $this->getFormattedLocation() . MessageFormatter::format($format, $params) . $this->getExceptionString($e));
		}
	}

	public function trace($format, $params = array(), \Exception $e = null) {
		if ($this->isTraceEnabled()) {
			//\ZLog::Write(LOGLEVEL_WBXML, $this->getFormattedLocation() . MessageFormatter::format($format, $params) . $this->getExceptionString($e));
			\ZLog::Write(LOGLEVEL_DEBUG, $this->getFormattedLocation() . MessageFormatter::format($format, $params) . $this->getExceptionString($e));
		}
	}

	public function warn($format, $params = array(), \Exception $e = null) {
		if ($this->isWarnEnabled()) {
			\ZLog::Write(LOGLEVEL_WARN, $this->getFormattedLocation() . MessageFormatter::format($format, $params) . $this->getExceptionString($e));
		}
	}
	
	protected function getFormattedLocation() {
		return $this->getLocationPrefix() . $this->getShortLocation(self::DEFAULT_BACKTRACE_LEVEL + 1) . $this->getLocationSuffix();
	}
	
	protected function getExceptionString(Exception $e = null) {
		if ($e === null) {
			return '';
		}
		return PHP_EOL . $e->__toString();
	}
}
