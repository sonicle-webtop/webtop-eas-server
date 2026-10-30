<?php

namespace WT;

/**
 * Hands out named loggers, one instance per name.
 * Typical usage: LoggerFactory::getLogger(__CLASS__)
 */
class LoggerFactory {

	private static $loggers = [];

	private function __construct() {}

	/**
	 * Returns the logger registered under the passed name, creating it if needed.
	 *
	 * @param string $name Logger name, usually the fully-qualified class name.
	 * @return Logger
	 */
	public static function getLogger($name) {
		if (!isset(self::$loggers[$name])) {
			self::$loggers[$name] = new Logger($name);
		}
		return self::$loggers[$name];
	}
}
