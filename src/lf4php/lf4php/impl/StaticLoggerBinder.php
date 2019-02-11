<?php

namespace lf4php\impl;

final class StaticLoggerBinder {
	public static $SINGLETON;
	private $loggerFactory;
	
	public static function init() {
		\ZLog::Write(LOGLEVEL_DEBUG, 'StaticLoggerBinder::init()');
		self::$SINGLETON = new StaticLoggerBinder();
		self::$SINGLETON->loggerFactory = new ZPLoggerFactory();
	}
	
	public function getLoggerFactory() {
		return $this->loggerFactory;
	}
}
StaticLoggerBinder::init();
