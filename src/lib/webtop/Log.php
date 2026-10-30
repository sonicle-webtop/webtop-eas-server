<?php

namespace WT;

use Monolog\Handler\StreamHandler;

/**
 * Logging setup and level threshold, shared by all the loggers returned
 * by LoggerFactory. Monolog only writes the records to the file.
 *
 * Inspired by: https://gist.github.com/laverboy/fd0a32e9e4e9fbbf9584
 */
class Log {

	// Our levels: from DEBUG up they have the same values of Monolog (RFC 5424) ones.
	// Monolog has no TRACE: it is below DEBUG and written as a marked DEBUG record.
	const TRACE = 50;
	const DEBUG = 100;
	const INFO = 200;
	const WARN = 300;
	const ERROR = 400;
	// Above every level: nothing gets logged
	const OFF = 1000;

	// Context key marking a DEBUG record as TRACE (see LogLineFormatter)
	const TRACE_MARKER = 'wt.trace';

	private static $levelsByName = [
		'TRACE' => self::TRACE,
		'DEBUG' => self::DEBUG,
		'INFO' => self::INFO,
		'WARN' => self::WARN,
		'ERROR' => self::ERROR,
		'OFF' => self::OFF
	];

	protected static $level = self::OFF;
	protected static $instance;

	private function __construct() {}

	/**
	 * Initializes logger instance.
	 *
	 * @param string $rootName Channel name printed in every line.
	 * @param string $level Our logging level. One of: ERROR, WARN, INFO, DEBUG, TRACE, OFF
	 * @param string $file Log file path; nothing is written if empty.
	 */
	public static function init($rootName, $level, $file = NULL) {
		if (!self::$instance) {
			$lev = self::toLevel($level, self::ERROR);
			$logger = new \Monolog\Logger($rootName);
			if (!empty($file) && $lev !== self::OFF) {
				// TRACE records travel as DEBUG ones: the handler must let them through
				$handler = new StreamHandler($file, max($lev, self::DEBUG));
				// Same date format of Monolog 1, used until now
				$handler->setFormatter(new LogLineFormatter(null, 'Y-m-d H:i:s', true));
				$logger->pushHandler($handler);
			}

			self::$level = $lev;
			self::$instance = $logger;
		}
	}

	/**
	 * Returns the level value corresponding to the passed name.
	 *
	 * @param string $level Our logging level. One of: ERROR, WARN, INFO, DEBUG, TRACE, OFF
	 * @param int $defaultLevel The default level to return in case of missing match.
	 * @return int
	 */
	private static function toLevel($level, $defaultLevel) {
		if (is_string($level) && isset(self::$levelsByName[$level])) {
			return self::$levelsByName[$level];
		}
		return $defaultLevel;
	}

	/**
	 * Returns the root logger instance.
	 *
	 * @return \Monolog\Logger
	 */
	public static function getLogger() {
		if (!self::$instance) throw new \Exception(sprintf("Logger instance not yet initialized. Please call Log::init() before this."));
		return self::$instance;
	}

	/**
	 * Is the logger instance enabled for the DEBUG level?
	 *
	 * @return Boolean
	 */
	public static function isDebugEnabled() {
		return self::isLevelEnabled(self::DEBUG);
	}

	/**
	 * Is the logger instance enabled for the passed level?
	 *
	 * @param int $level One of our level constants.
	 * @return Boolean
	 */
	public static function isLevelEnabled($level) {
		return $level >= self::$level;
	}

	/**
	 * Writes an already formatted message, if the level is enabled.
	 *
	 * @param int $level One of our level constants (OFF excluded).
	 * @param string $message
	 */
	public static function write($level, $message) {
		if (!self::$instance || !self::isLevelEnabled($level)) return;
		if ($level === self::TRACE) {
			self::$instance->addRecord(self::DEBUG, $message, [self::TRACE_MARKER => true]);
		} else {
			self::$instance->addRecord($level, $message);
		}
	}
}
