<?php

namespace WT;

class Log {
	//protected static $instance;
	
	/*
	 * Adds a log record at the DEBUG level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function debug($message, $args = array()) {
			\ZLog::Write(LOGLEVEL_DEBUG, $message.self::printContext($context));
	}
	
	/*
	 * Adds a log record at the INFO level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function info($message, array $context = []) {
		\ZLog::Write(LOGLEVEL_INFO, $message.self::printContext($context));
	}
	
	/*
	 * Adds a log record at the NOTICE level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function notice($message, array $context = []) {
		\ZLog::Write(LOGLEVEL_INFO, $message.self::printContext($context));
	}
	
	/*
	 * Adds a log record at the WARNING level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function warn($message, array $context = []) {
		\ZLog::Write(LOGLEVEL_WARN, $message.self::printContext($context));
	}
	
	/*
	 * Adds a log record at the ERROR level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function error($message, array $context = []) {
		\ZLog::Write(LOGLEVEL_ERROR, $message.self::printContext($context));
	}
	
	/*
	 * Adds a log record at the CRITICAL level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function critical($message, array $context = []) {
		\ZLog::Write(LOGLEVEL_FATAL, $message.self::printContext($context));
	}
	
	/*
	 * Adds a log record at the ALERT level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function alert($message, array $context = []) {
		\ZLog::Write(LOGLEVEL_FATAL, $message.self::printContext($context));
	}
	
	/*
	 * Adds a log record at the EMERGENCY level.
	 * 
	 * @param string $message The log message
	 * @param array  $context The log context
	 */
	public static function emergency($message, array $context = []) {
		\ZLog::Write(LOGLEVEL_FATAL, $message.self::printContext($context));
	}
	
	/**
	 * Is the logger instance enabled for the DEBUG level?
	 * 
	 * @return Boolean
	 */
	public static function isDebugEnabled() {
		return true;
		//return self::isLevelEnabled(\Monolog\Logger::DEBUG);
	}
	
	/**
	 * Is the logger instance enabled for the passed level?
	 * 
	 * @param int $level Level number (monolog)
	 * @return Boolean
	 */
	public static function isLevelEnabled($level) {
		return true;
		//return $level >= self::$level;
	}
	
	private static function printContext($context) {
		return empty($context) ? '' : ' '.print_r($context, true);
		//return self::print_r_reverse($context);
	}
	
	/**
	 * Matt: core
	 * Trixor: object handling
	 * lech: Windows suppport
	 * simivar: null support
	 * @see http://php.net/manual/en/function.print-r.php
	 */
	private static function print_r_reverse($input) {
		$lines = preg_split('#\r?\n#', trim($input));
		if (trim($lines[0]) != 'Array' && trim($lines[0] != 'stdClass Object')) {
			// bottomed out to something that isn't an array or object
			if ($input === '') {
				return null;
			}

			return $input;
		} else {
			// this is an array or object, lets parse it
			$match = array();
			if (preg_match("/(\s{5,})\(/", $lines[1], $match)) {
				// this is a tested array/recursive call to this function
				// take a set of spaces off the beginning
				$spaces = $match[1];
				$spaces_length = strlen($spaces);
				$lines_total = count($lines);
				for ($i = 0; $i < $lines_total; $i++) {
					if (substr($lines[$i], 0, $spaces_length) == $spaces) {
						$lines[$i] = substr($lines[$i], $spaces_length);
					}
				}
			}
			$is_object = trim($lines[0]) == 'stdClass Object';
			array_shift($lines); // Array
			array_shift($lines); // (
			array_pop($lines); // )
			$input = implode("\n", $lines);
			$matches = array();
			// make sure we only match stuff with 4 preceding spaces (stuff for this array and not a nested one)
			preg_match_all("/^\s{4}\[(.+?)\] \=\> /m", $input, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
			$pos = array();
			$previous_key = '';
			$in_length = strlen($input);
			// store the following in $pos:
			// array with key = key of the parsed array's item
			// value = array(start position in $in, $end position in $in)
			foreach ($matches as $match) {
				$key = $match[1][0];
				$start = $match[0][1] + strlen($match[0][0]);
				$pos[$key] = array($start, $in_length);
				if ($previous_key != '') {
					$pos[$previous_key][1] = $match[0][1] - 1;
				}
				$previous_key = $key;
			}
			$ret = array();
			foreach ($pos as $key => $where) {
				// recursively see if the parsed out value is an array too
				$ret[$key] = print_r_reverse(substr($input, $where[0], $where[1] - $where[0]));
			}

			return $is_object ? (object) $ret : $ret;
		}
	}
	
	private static function normalizeException($e) {
        // TODO 2.0 only check for Throwable
        if (!$e instanceof \Exception && !$e instanceof \Throwable) {
            throw new \InvalidArgumentException('Exception/Throwable expected, got '.gettype($e).' / '.get_class($e));
        }

        $previousText = '';
        if ($previous = $e->getPrevious()) {
            do {
                $previousText .= ', '.get_class($previous).'(code: '.$previous->getCode().'): '.$previous->getMessage().' at '.$previous->getFile().':'.$previous->getLine();
            } while ($previous = $previous->getPrevious());
        }

        $str = '[object] ('.get_class($e).'(code: '.$e->getCode().'): '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine().$previousText.')';
        if ($this->includeStacktraces) {
            $str .= "\n[stacktrace]\n".$e->getTraceAsString()."\n";
        }

        return $str;
    }
}
