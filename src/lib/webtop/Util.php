<?php

namespace WT;

class Util {
	
	/*
	 * Clear all levels of output buffering
	 * 
	 * @return void
	 */
	public static function obEnd() {
		while (ob_get_level()) {
			ob_end_clean();
		}
	}
	
	public static function stripLeadingDirSeparator($path, $separator = DIRECTORY_SEPARATOR) {
		if ($path === $separator) {
			return $path;
		}
		return is_null($path) ? $path : ltrim($path, $separator);
		//return ltrim($path, $separator);
	}
	
	public static function stripTrailingDirSeparator($path, $separator = DIRECTORY_SEPARATOR) {
		if ($path === $separator) {
			return $path;
		}
		return is_null($path) ? $path : rtrim($path, $separator);
		//return rtrim($path, $separator);
	}
	
	/*
	public static function parseRfc822($rfc822) {
		if (function_exists('imap_rfc822_parse_adrlist')) {
			$arr = imap_rfc822_parse_adrlist($rfc822, '');
			if (!is_array($arr) || count($arr) < 1) return null;
			
			$firstAdr = array_shift($arr);
			$address = null;
			if (!empty($firstAdr->mailbox) && ($firstAdr->mailbox !== 'INVALID_ADDRESS') && !empty($firstAdr->host)) {
				$address = $firstAdr->mailbox.'@'.$firstAdr->host;
			}
			return [
				'address' => $address,
				'personal' => $firstAdr->personal
			];
		}
		
		//$logger->debug('{} Warning : php-imap not available', [__METHOD__]);
		return null;		
	}
	*/
	
	/**
	 * Guess MIME type of a picture by picture file signature.
	 * 
	 * @param string $data Image data (at least the first 4 bytes)
	 * @return string MIME type
	 */
	public static function guessImageMediaType($data) {
		if (substr($data, 0, 4) == '\x47\x49\x46\x38') {
			return('image/gif');
		} else if (substr($data, 0, 4) == '\xFF\xD8\xFF\xE0') {
			return('image/jpg');
		} else if (substr($data, 0, 4) == '\x89\x50\x4E\x47') {
			return('image/png');
		} else {
			return('image/unknown');
		}
	}
	
	/*
	public static function image($data, $dataIsBase64 = false, $mediaType = '') {
		$mtype = $mediaType;
		if (empty($mtype)) {
			$raw = ($dataIsBase64 === true) ? base64_decode($data) : $data;
			$mtype = function_exists('mime_content_type') ? mime_content_type($raw) : 'image/unknown';
		}
		$data64 = ($dataIsBase64 === true) ? $data : base64_encode($data);
		return new DataUri($mtype, $data64, DataUri::ENCODING_BASE64);
	}
	*/
	
	/*
	public static function requireFromDir($directory, $file) {
		$cwd = getcwd();
		\ZLog::Write(LOGLEVEL_WARN, sprintf("cwd: '%s'", $cwd));
		chdir($directory);
		\ZLog::Write(LOGLEVEL_WARN, sprintf("chdir: '%s'", $directory));
		require_once ''.$file;
		\ZLog::Write(LOGLEVEL_WARN, sprintf("require_once: '%s'", $file));
		chdir($cwd);
		\ZLog::Write(LOGLEVEL_WARN, sprintf("chdir: '%s'", $cwd));
	}
	*/
}
