<?php

namespace WT;

/**
 * Named logger, the same API previously provided by lf4php: messages with
 * positional {} placeholders filled from $params, plus a real TRACE level.
 * Whether a level is enabled is decided by Log, Monolog only writes the records.
 *
 * Each message is prefixed by the shortened logger name, as lf4php did:
 * 'WT\DAV\CalDAV\Backend' becomes 'W\D\C\Backend - '.
 */
class Logger {

	private $name;
	private $prefix;

	public function __construct($name) {
		$this->name = $name;
		$this->prefix = self::shortenName($name) . ' - ';
	}

	public function getName() {
		return $this->name;
	}

	public function trace($message, array $params = [], ?\Throwable $ex = null) {
		$this->log(Log::TRACE, $message, $params, $ex);
	}

	public function debug($message, array $params = [], ?\Throwable $ex = null) {
		$this->log(Log::DEBUG, $message, $params, $ex);
	}

	public function info($message, array $params = [], ?\Throwable $ex = null) {
		$this->log(Log::INFO, $message, $params, $ex);
	}

	public function warn($message, array $params = [], ?\Throwable $ex = null) {
		$this->log(Log::WARN, $message, $params, $ex);
	}

	public function error($message, array $params = [], ?\Throwable $ex = null) {
		$this->log(Log::ERROR, $message, $params, $ex);
	}

	public function isTraceEnabled() {
		return Log::isLevelEnabled(Log::TRACE);
	}

	public function isDebugEnabled() {
		return Log::isLevelEnabled(Log::DEBUG);
	}

	public function isInfoEnabled() {
		return Log::isLevelEnabled(Log::INFO);
	}

	public function isWarnEnabled() {
		return Log::isLevelEnabled(Log::WARN);
	}

	public function isErrorEnabled() {
		return Log::isLevelEnabled(Log::ERROR);
	}

	/**
	 * @param int $level One of Log level constants.
	 * @param mixed $message The message (with optional {} placeholders), or any
	 *		value to be logged: exceptions and Stringable objects are converted.
	 * @param array $params Values that replace, in order, the {} placeholders.
	 * @param \Throwable $ex Optional exception whose string is appended to the message.
	 */
	protected function log($level, $message, array $params, ?\Throwable $ex) {
		if (!Log::isLevelEnabled($level)) return;
		$text = $this->prefix . self::format(self::stringify($message), $params);
		if ($ex !== null) {
			$text .= PHP_EOL . $ex;
		}
		Log::write($level, $text);
	}

	private static function format($message, array $params) {
		if (empty($params) || strpos($message, '{}') === false) return $message;
		return preg_replace_callback('/\{\}/', function() use (&$params) {
			return empty($params) ? '' : self::stringify(array_shift($params));
		}, $message);
	}

	private static function stringify($value) {
		if (is_string($value)) {
			return $value;
		} else if (is_null($value)) {
			return 'null';
		} else if (is_bool($value)) {
			return $value ? 'true' : 'false';
		} else if (is_scalar($value) || $value instanceof \Stringable) {
			return (string)$value;
		} else if (is_array($value)) {
			$json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
			return $json === false ? 'Array' : $json;
		} else if (is_object($value)) {
			return get_class($value) . '@' . spl_object_id($value);
		} else {
			return gettype($value);
		}
	}

	private static function shortenName($name) {
		$parts = explode('\\', $name);
		$last = array_pop($parts);
		$short = [];
		foreach ($parts as $part) {
			$short[] = substr($part, 0, 1);
		}
		$short[] = $last;
		return implode('\\', $short);
	}
}
