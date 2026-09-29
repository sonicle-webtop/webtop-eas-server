<?php

namespace WT;

use Monolog\Logger;

/**
 * Inspired by: https://gist.github.com/laverboy/fd0a32e9e4e9fbbf9584
 */
class Log {
	
	protected static $level;
	protected static $instance;
	
	private function __construct() {}
	
	/**
	 * Initializes logger instance.
	 */
	public static function init($rootName, $level, $file = NULL) {
		if (!self::$instance) {
			// Initialize Monolog
			$lev = self::toMonologLevel($level, \Monolog\Logger::ERROR);
			$logger = new Logger($rootName);
			if (!empty($file)) {
				$handler = new \Monolog\Handler\StreamHandler($file, $lev);
				$handler->setFormatter(new \Monolog\Formatter\LineFormatter(null, null, true));
				$logger->pushHandler($handler);
			}
			
			self::$level = $lev;
			self::$instance = $logger;
			
			// Configure lf4php
			$factory = \lf4php\impl\StaticLoggerBinder::$SINGLETON->getLoggerFactory();
			$factory->setRootMonologLogger(self::$instance);
		}
	}
	
	/**
	 * Returns the corresponding level value in Monolog "language".
	 * @param string $level Our loggin level. One of: ERROR, WARN, INFO, DEBUG, TRACE, OFF
	 * @param int $defaultMonologLevel The default level to return in case of missing match.
	 * @return int
	 */
	private static function toMonologLevel($level, $defaultMonologLevel) {
		if (is_string($level)) {
			$mlevel = $level;
			if ('TRACE' == $level) {
				$mlevel = 'DEBUG';
			}
			$ret = Logger::toMonologLevel($mlevel);
			if (is_int($ret)) {
				return $ret;
			}
		}
		return $defaultMonologLevel;
	}
	
	/**
	 * Returns the root logger instance.
	 * 
	 * @return \Monolog\Logger
	 */
	public static function getLogger() {
		if (!self::$instance) throw new Exception(sprintf("Logger instance not yet initialized. Please call Log::init() before this."));
		return self::$instance;
	}
	
	/**
	 * Is the logger instance enabled for the DEBUG level?
	 * 
	 * @return Boolean
	 */
	public static function isDebugEnabled() {
		return self::isLevelEnabled(\Monolog\Logger::DEBUG);
	}
	
	/**
	 * Is the logger instance enabled for the passed level?
	 * 
	 * @param int $level Level number (monolog)
	 * @return Boolean
	 */
	public static function isLevelEnabled($level) {
		return $level >= self::$level;
	}
}