<?php

namespace WT\EAS;

use WT\LoggerFactory;
use Html2Text\Html2Text;
use WT\Util;
use DataURI;

class ZPUtil {
	
	const FAMILY_ANDROID = "android";
	const FAMILY_OUTLOOK = "outlook";
	const FAMILY_IOS = "ios";
	const FAMILY_UNKNOWN = "unknown";
	
	//DEBUG, INFO, NOTICE, WARNING, ERROR
	private static $logLevelToZP = [
		'OFF' => 'OFF',
		'ERROR' => 'FATAL',
		'WARN' => 'WARN', // ERROR+WARN
		'INFO' => 'INFO',
		'DEBUG' => 'DEBUG',
		'TRACE' => 'WBXML',
	];
	
	public static function getDeviceFamily() {
		$deviceType = \Request::GetDeviceType();
		if (stripos($deviceType, "android") > -1) {
			return self::FAMILY_ANDROID;
		} else if (stripos($deviceType, "outlook") > -1) {
			return self::FAMILY_OUTLOOK;
		} else if (stripos($deviceType, "iphone") > -1) {
			return self::FAMILY_IOS;
		} else if (stripos($deviceType, "ipad") > -1) {
			return self::FAMILY_IOS;
		} else {
			return self::FAMILY_UNKNOWN;
		}
	}
	
	public static function toLogLevel($level) {
		if (array_key_exists($level, self::$logLevelToZP)) {
			return constant('LOGLEVEL_'.self::$logLevelToZP[$level]);
		} else {
			return LOGLEVEL_ERROR;
		}
		/*
		if (is_string($level) && defined('LOGLEVEL_'.strtoupper($level))) {
			return constant('LOGLEVEL_'.strtoupper($level));
		}
		return ($fallback == true) ? LOGLEVEL_INFO : null;
		*/
	}
	
	public static function toInternetAddress($address, $personal = null) {
		$tokens = explode('@', $address);
		if (count($tokens) !== 2) {
			throw new Exception('Address needs to be in form: address@domain.tld');
		}
		return imap_rfc822_write_address($tokens[0], $tokens[1], (empty($personal) || ($address === $personal)) ? null : $personal);
	}
	
	public static function parseInternetAddress($ia) {
		// Z-Push 2.7 dropped its Mail_RFC822 fix that quoted the display name:
		// unquoted names with accents, apostrophes, dots, commas or an email
		// address (eg. "a@b.c <a@b.c>", as WebTop writes attendees without a
		// name) no longer parse. Quote the name here, as Z-Push 2.4 did.
		if (is_string($ia) && preg_match('/^\s*([^"<\s][^<]*?)\s*(<[^>]*>)\s*$/', $ia, $matches)) {
			$ia = '"'.addcslashes($matches[1], '"\\').'" '.$matches[2];
		}
		$parser = new \Mail_RFC822();
		$ret = $parser->parseAddressList($ia, 'local', null, null, 1);
		if (($ret === false) || !is_array($ret) || (count($ret) < 1)) {
			return false;
		} else {
			return [
				'address' => $ret[0]->mailbox.'@'.$ret[0]->host,
				'personal' => self::unquotePersonal($ret[0]->personal)
			];
		}
	}

	/**
	 * Mail_RFC822 returns a quoted display name as is: remove the quotes and
	 * the escaping, so that the phone gets the plain name.
	 */
	private static function unquotePersonal($personal) {
		if (is_string($personal) && (strlen($personal) >= 2) && ($personal[0] === '"') && (substr($personal, -1) === '"')) {
			return preg_replace('/\\\\(.)/s', '$1', substr($personal, 1, -1));
		}
		return $personal;
	}
	
	public static function parseISODate($sdate, $tzName = 'UTC') {
		return empty($sdate) ? null : \TimezoneUtil::MakeUTCDate($sdate, $tzName);
	}
	
	public static function parseISODateTime($sdatetime) {
		return empty($sdatetime) ? null : \TimezoneUtil::MakeUTCDate($sdatetime, 'UTC');
	}
	
	/**
	 * Returns a date string formatted in ISO format.
	 * @param int $date Unix timestamp of date/time
	 * @return string Formatted string
	 */
	public static function toISODate($date) {
		return empty($date) ? null : gmdate("Ymd", $date);
	}
	
	/**
	 * Returns a date/time string formatted in ISO format.
	 * @param int $date Unix timestamp of date/time
	 * @return string Formatted string
	 */
	public static function toISODateTime($date) {
		return empty($date) ? null : gmdate("Ymd\THis\Z", $date);
	}
	
	/**
	 * Return a DateTime object corresponding to the passed timestamp and timezone.
	 * @param int $date Unix timestamp of date/time
	 * @param string $tzName The timezone name
	 * @return DateTime DateTime object
	 */
	public static function toDateTime($date, $tzName) {
		//https://www.phpzag.com/convert-unix-timestamp-to-readable-date-time-in-php/
		$dt = new \DateTime("@$date");
		$tz = self::getTimezone($tzName);
		return new \DateTime($dt->format('Y-m-d H:i:s'), $tz);
	}
	
	/**
	 * Returns a Timezone object corresponding to the passed name, 
	 * or the default timezone if provided name cannot be recognized.
	 * @param String $tzName The timezone name
	 * @return DateTimeZone Timezone object
	 */
	public static function getTimezone($tzName) {
		$tz = null;
		if ($tzName) {
			$tz = timezone_open($tzName);
		}
		if (!$tz) {
			//If there is no timezone set, we use the default timezone
			$tz = timezone_open(date_default_timezone_get());
		}
		return $tz;
	}
	
	/**
	 * Converts a timezone name into a packed timezone sync blob.
	 * 
	 * @param String $tzName The timezone name
	 * @return String
	 */
	public static function tzNameToTZBlob($tzName) {
		$tzId = \TimezoneUtil::GetPhpSupportedTimezone($tzName);
		$tzObj = \TimezoneUtil::GetFullTZFromTZName($tzId);
		
		// GetFullTZFromTZName() already called encodeTZName() internally, storing
		// a platform-dependent binary string in tzname/tznamedst. We bypass
		// GetSyncBlobFromTZ() entirely and re-pack the blob ourselves using
		// correct UTF-16LE encoding for the name fields.
		$tzName = self::encodeTZNameFixed($tzId);
		
		// Pack the MSTZ blob manually instead of delegating to TimezoneUtil::GetSyncBlobFromTZ(),
		// which internally calls the private encodeTZName() method. That method uses
		// iconv('UTF-8', 'UTF-16', ...) without explicit endianness, producing platform-dependent
		// output: UTF-16BE on illumos/Solaris, UTF-16LE on Linux. It also strips the last byte
		// of the encoded string via substr(..., 2, -1), breaking null termination.
		// The MS-ASDTYPE MSTZ blob requires 64-byte UTF-16LE name fields with a null terminator.
		// We use mb_convert_encoding() with explicit 'UTF-16LE' which is deterministic on all platforms.
		$packed = pack(
			'la64vvvvvvvv' . 'la64vvvvvvvv' . 'l',
			$tzObj['bias'],
			$tzName,
			0,
			$tzObj['dstendmonth'], $tzObj['dstendday'], $tzObj['dstendweek'],
			$tzObj['dstendhour'], $tzObj['dstendminute'], $tzObj['dstendsecond'],
			$tzObj['dstendmillis'],
			$tzObj['stdbias'],
			$tzName,
			0,
			$tzObj['dststartmonth'], $tzObj['dststartday'], $tzObj['dststartweek'],
			$tzObj['dststarthour'], $tzObj['dststartminute'], $tzObj['dststartsecond'],
			$tzObj['dststartmillis'],
			$tzObj['dstbias']
		);
		
		return base64_encode($packed);
	}
	
	/**
	 * Encodes a timezone display name into a 64-byte UTF-16LE field,
	 * truncated to 31 characters (62 bytes) to ensure a null terminator fits.
	 * 
	 * @param string $name UTF-8 timezone name
	 * @return string 64-byte binary string (UTF-16LE, null-padded)
	 */
	private static function encodeTZNameFixed($name) {
		// Truncate to 31 chars max to leave room for the UTF-16LE null terminator (2 bytes).
		// The MSTZ blob name field is 64 bytes = 32 UTF-16LE chars.
		$name = mb_substr($name, 0, 31, 'UTF-8');
		
		// mb_convert_encoding with explicit 'UTF-16LE' is platform-independent,
		// unlike iconv('UTF-8', 'UTF-16', ...) which produces platform-dependent
		// endianness and a BOM — on illumos/Solaris iconv outputs UTF-16BE,
		// on Linux it typically outputs UTF-16LE. Both break the MS-ASDTYPE spec.
		$encoded = mb_convert_encoding($name, 'UTF-16LE', 'UTF-8');
		
		// Pad to exactly 64 bytes; str_pad fills with null bytes which serve
		// as the required UTF-16LE null terminator.
		return str_pad(substr($encoded, 0, 62), 64, "\x00");
	}
	
	/**
	 * Converts a packed timezone sync blob into a timezone name.
	 * 
	 * @param String $tzBlob Packed blob of timezone data
	 * @return String
	 */
	public static function tzBlobToTZName($tzBlob, $myTZName = null) {
		$tzObj = self::tzBlobToTZObj($tzBlob);
		return self::guessTZNameFromTZObj($tzObj, $myTZName);
	}
	
	/**
	 * Decodes timezone-info from a sync blob.
	 * 
	 * @param String $tzBlob Packed blob of timezone data
	 * @return array
	 */
	public static function tzBlobToTZObj($tzBlob) {
		$tzObj = unpack("lbias/a64tzname/vdstendyear/vdstendmonth/vdstendday/vdstendweek/vdstendhour/vdstendminute/vdstendsecond/vdstendmillis/" .
                    "lstdbias/a64tznamedst/vdststartyear/vdststartmonth/vdststartday/vdststartweek/vdststarthour/vdststartminute/vdststartsecond/vdststartmillis/" .
                    "ldstbias", base64_decode($tzBlob));
		
        // Make the structure compatible with class.recurrence.php
        $tzObj["timezone"] = $tzObj["bias"];
        $tzObj["timezonedst"] = $tzObj["dstbias"];
		
        return $tzObj;
	}
	
	/**
	 * Tries to guess the timezone name from a timezone-info.
	 *
	 * The timezone-info is compared with PHP's own timezone data (offsets and
	 * daylight saving changes of the current year), not with Z-Push's table:
	 * there the zones Z-Push does not know get the rules of London (so eg.
	 * America/Ciudad_Juarez, UTC-7, matched a London phone) and the rules of
	 * some zones are outdated (eg. Mexico and Brazil no longer have daylight
	 * saving time).
	 *
	 * @param array $tzObj The timezone-info object
	 * @param String $myTZName A timezone name/ID to be evaluated as first
	 * @return String
	 */
	public static function guessTZNameFromTZObj($tzObj, $myTZName = null) {
		$year = (int)gmdate('Y');
		$expected = self::tzObjYearProfile($tzObj, $year);

		$tzIds = \DateTimeZone::listIdentifiers();
		// Evaluated first, in this order: my timezone, the one written in the
		// timezone-info (we write the ID there), the default one and UTC
		$preferred = [$myTZName, self::decodeTZName($tzObj['tzname']), date_default_timezone_get(), 'UTC'];
		foreach (array_unique(array_merge(array_filter($preferred), $tzIds)) as $tzId) {
			try {
				$tz = new \DateTimeZone($tzId);
			} catch (\Exception $ex) {
				continue; // Not a valid ID (eg. a Windows name written in the timezone-info)
			}
			if (self::phpYearProfile($tz, $year) === $expected) return $tzId;
		}
		return date_default_timezone_get();
	}

	/**
	 * Returns what a timezone does in the passed year, from the timezone-info:
	 * [lower offset, higher offset, start of the higher one, start of the lower
	 * one], in seconds and UTC timestamps; the starts are null without daylight
	 * saving time.
	 */
	private static function tzObjYearProfile($tzObj, $year) {
		$std = -($tzObj['bias'] + $tzObj['stdbias']) * 60;
		if (empty($tzObj['dststartmonth']) || empty($tzObj['dstendmonth'])) {
			return [$std, $std, null, null];
		}
		$dst = -($tzObj['bias'] + $tzObj['dstbias']) * 60;
		// Each change is expressed in the local time in force before it
		$toDst = self::systemTimeToTimestamp($tzObj, 'dststart', $year, $std);
		$toStd = self::systemTimeToTimestamp($tzObj, 'dstend', $year, $dst);
		return ($dst > $std) ? [$std, $dst, $toDst, $toStd] : [$dst, $std, $toStd, $toDst];
	}

	/**
	 * Returns the UTC timestamp of a change of the timezone-info (SYSTEMTIME
	 * fields with the passed prefix) in the passed year. Without a year, the
	 * change is on the nth day of week of the month (week 5 being the last).
	 */
	private static function systemTimeToTimestamp($tzObj, $prefix, $year, $offset) {
		$month = $tzObj[$prefix.'month'];
		if (!empty($tzObj[$prefix.'year'])) {
			$day = $tzObj[$prefix.'week']; // Absolute date: wDay is the day of month
		} else {
			$days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
			$nth = ($tzObj[$prefix.'week'] >= 5) ? 'last' : ['first', 'second', 'third', 'fourth'][$tzObj[$prefix.'week'] - 1];
			$ref = new \DateTime(sprintf('%04d-%02d-01', $year, $month), new \DateTimeZone('UTC'));
			$day = (int)$ref->modify($nth.' '.$days[$tzObj[$prefix.'day']].' of this month')->format('j');
		}
		return gmmktime($tzObj[$prefix.'hour'], $tzObj[$prefix.'minute'], $tzObj[$prefix.'second'], $month, $day, $year) - $offset;
	}

	/**
	 * Returns what a PHP timezone does in the passed year, as tzObjYearProfile().
	 * The higher offset is taken as daylight saving time instead of the isdst
	 * flag, which is reversed in some zones (eg. Europe/Dublin).
	 */
	private static function phpYearProfile(\DateTimeZone $tz, $year) {
		$from = gmmktime(0, 0, 0, 1, 1, $year);
		$transitions = $tz->getTransitions($from, gmmktime(0, 0, 0, 1, 1, $year + 1) - 1);
		$offset = $transitions[0]['offset']; // The one in force when the year begins
		$changes = array_slice($transitions, 1);
		if (empty($changes)) return [$offset, $offset, null, null];
		if (count($changes) !== 2) return null; // eg. Morocco during Ramadan: not expressible as a timezone-info
		$offsets = [$changes[0]['offset'], $changes[1]['offset']];
		$higher = ($offsets[0] > $offsets[1]) ? 0 : 1;
		return [$offsets[1 - $higher], $offsets[$higher], $changes[$higher]['ts'], $changes[1 - $higher]['ts']];
	}

	/**
	 * Decodes a timezone name of a timezone-info (UTF-16LE, null padded).
	 */
	private static function decodeTZName($tzName) {
		$name = rtrim(mb_convert_encoding((string)$tzName, 'UTF-8', 'UTF-16LE'), "\0");
		return ($name === '') ? null : $name;
	}
	
	
	
	
	
	
	
	
	
	
	
	
	
	/**
	 * Decodes timezone-info from a sync blob.
	 * 
	 * @param String $tzBlob Packed blob of timezone data
	 * @return array
	 */
	/*
	public static function unpackTimezoneInfo($tzBlob) {
		$tzObj = unpack("lbias/a64tzname/vdstendyear/vdstendmonth/vdstendday/vdstendweek/vdstendhour/vdstendminute/vdstendsecond/vdstendmillis/".
				"lstdbias/a64tznamedst/vdststartyear/vdststartmonth/vdststartday/vdststartweek/vdststarthour/vdststartminute/vdststartsecond/vdststartmillis/".
				"ldstbias", base64_decode($tzBlob));
		// Make the structure compatible with class.recurrence.php
		$tz["timezone"] = $tz["bias"];
		$tz["timezonedst"] = $tz["dstbias"];
		return $tzObj;
	}
	*/
	
	
	
	
	
	
	
	
	
	
	
	
	/**
	 * Encodes a string to a SyncBlob. 
	 * 
	 * @param string $blob SyncBlob decoded string
	 * @return string Encoded SyncBlob string
	 */
	/*public static function encodeSyncBlob($blob) {
		return base64_encode($blob);
	}*/
	
	/**
	 * Decodes a SyncBlob to a string. 
	 * 
	 * @param string $blob SyncBlob encoded string
	 * @return string Decoded SyncBlob string
	 */
	/*public static function decodeSyncBlob($blob) {
		return base64_decode($blob);
	}
	*/
	
	/**
	 * Generates AS timezone packed string.
	 * 
	 * @param string $tzName Timezone ID
	 * @return string Endoded timezone SyncBlob string
	 * @throws Exception
	 */
	/*public static function getSyncTimezoneString($tzName) {
		$tzObj = \TimezoneUtil::GetFullTZFromTZName($tzName);
		$blob = \TimezoneUtil::GetSyncBlobFromTZ($tzObj);
		return self::encodeSyncBlob($blob);
	}
	*/
	
	/*
	public static function timezoneIDToBlob($tzName) {
		$tz = \TimezoneUtil::GetPhpSupportedTimezone($tzName);
		$tzObj = \TimezoneUtil::GetFullTZFromTZName($tz);
		$blob = \TimezoneUtil::GetSyncBlobFromTZ($tzObj);
		return self::encodeSyncBlob($blob);
	}
	*/
	
	/**
	 * Tries to guess the timezone ID from a SyncBlob.
	 * 
	 * @param string $tzBlob Endoded timezone SyncBlob string
	 * @return string
	 */
	/*public static function timezoneBlobToID($tzBlob) {
		$logger = LoggerFactory::getLogger(__CLASS__);
		
		$logger->debug("tzBlob: {}", [self::decodeSyncBlob($tzBlob)]);
		
		// Get a list of all timezones
		$tzIds = \DateTimeZone::listIdentifiers();
		// Try the default timezone first
		array_unshift($tzIds, date_default_timezone_get());
		foreach ($tzIds as $tzId) {
			$logger->debug("analyzing {}", [$tzId]);
			$str = self::getSyncTimezoneString($tzId);
			if ($str === $tzBlob) {
				$logger->debug("{} YES", [$tzId]);
				return $tzId;
			} else {
				$logger->debug("{} NO", [$tzId]);
			}
		}
		return date_default_timezone_get();
	}
	*/
	
	/**
     * Unpack timezone info from Sync. (copied from backend/ics.php)
     *
     * @param string    $data
     * @return array
     */
    /*static public function getTZFromSyncBlob($data) {
        $tz = unpack("lbias/a64tzname/vdstendyear/vdstendmonth/vdstendday/vdstendweek/vdstendhour/vdstendminute/vdstendsecond/vdstendmillis/" .
                    "lstdbias/a64tznamedst/vdststartyear/vdststartmonth/vdststartday/vdststartweek/vdststarthour/vdststartminute/vdststartsecond/vdststartmillis/" .
                    "ldstbias", $data);

        // Make the structure compatible with class.recurrence.php
        $tz["timezone"] = $tz["bias"];
        $tz["timezonedst"] = $tz["dstbias"];

        return $tz;
    }*/
	
	
	
	
	
	
	
	
	
	
	
	public static function messageBodyToNote(?\SyncBaseBody $body = null) {
		if (isset($body) && isset($body->data)) {
			$ret = stream_get_contents($body->data);
			if ($ret !== false) {
				switch ($body->type) {
					case '3': // RTF
						return $ret;
					case '2': // HTML
						return (new Html2Text($ret))->getText();
					case '1': // Plain
						return $ret;
				}
			}
		}
		return null;
	}
	
	public static function messageBodyToNoteLT12($body, $bodytruncated, $bodysize) {
		if (!empty($body)) {
			return $body;
		}
		return null;
	}
	
	public static function noteToMessageBody($note, $truncsize=-1) {
		$body = null;
		if (!empty($note)) {
			$body = new \SyncBaseBody();
			$body->type = SYNC_BODYPREFERENCE_PLAIN;
			$data = $note;
			if ($truncsize > 0 && $truncsize < strlen($data)) {
				$body->truncated = 1;
				$data = \Utils::Utf8_truncate($data, $truncsize);
			} else {
				$body->truncated = 0;
			}
			$body->data = \StringStreamWrapper::Open($data);
			$body->estimatedDataSize = strlen($data);
		}
		return $body;
	}
	
	public static function noteToMessageBodyLT12($note, $truncsize=-1) {
		$ret = null;
		if (!empty($note)) {
			$truncated = 0;
			$body = $note;
			if ($truncsize > 0 && $truncsize < strlen($body)) {
				$truncated = 1;
				$body = \Utils::Utf8_truncate($body, $truncsize);
			}
			$ret = [
				'body' => $body,
				'bodytruncated' => $truncated,
				'bodysize' => strlen($body)
			];
		}
		return $ret;
	}
	
	public static function dataUriToPicture($dataUri) {
		$ret = null;
		if (!is_null($dataUri) && strpos($dataUri->getMimeType(), 'image/') === 0) {
			$ret = [
				// Encode base64 again: getData returns the raw value!
				'data' => base64_encode($dataUri->getData()),
				//'data' => ($dataIsBase64 === true) ? $dataUri->getData() : base64_encode($dataUri->getData()),
				'mimeType' => $dataUri->getMimeType()
			];
		}
		return $ret;
	}
	
	public static function pictureToDataUri($picData, $mediaType = '') {
		$ret = null;
		if (!empty($picData)) {
			$mtype = $mediaType;
			if (empty($mtype)) {
				$raw = base64_decode($picData);
				$mtype = Util::guessMediaType($raw);
			}
			if (is_null($mtype) || empty($mtype)) {
				$mtype = 'image/unknown';
			}
			$ret = new DataURI\Data($raw, $mtype);
			$ret->setBinaryData(true);
		}
		return $ret;
	}
	
	public static function rruleToMessageRecurrence($rrulestr, $task = false) {
		$recurrence = new \SyncRecurrence();
		if ($task === true) {
			$recurrence = new \SyncTaskRecurrence();
		}
		$rrules = explode(";", $rrulestr);
		foreach ($rrules as $rrule) {
			$rule = explode("=", $rrule);
			switch ($rule[0]) {
				case "FREQ":
					switch ($rule[1]) {
						case "DAILY":
							$recurrence->type = "0";
							break;
						case "WEEKLY":
							$recurrence->type = "1";
							break;
						case "MONTHLY":
							$recurrence->type = "2";
							break;
						case "YEARLY":
							$recurrence->type = "5";
					}
					break;

				case "UNTIL":
					// Seems that iphones show the until date received as until+1.
					// This is true only for events created server-side, if the
					// appointment is defined on the device the until date is
					// displayed correctly.
					// The code below try to solve this situation by subtracting 
					// a day to the end date, but it produces the side-effect 
					// that during visualization on device the event is one day 
					// less long. So, keep commented for now!
					/*
					$until = \TimezoneUtil::MakeUTCDate($rule[1]);
					if (is_null($deviceFamily)) $deviceFamily = self::getDeviceFamily();
					if (self::FAMILY_IOS === $deviceFamily) {
						$until = strtotime('-1 days', $until);
					}
					$recurrence->until = $until;
					*/
					$recurrence->until = \TimezoneUtil::MakeUTCDate($rule[1]);
					break;

				case "COUNT":
					$recurrence->occurrences = $rule[1];
					break;

				case "INTERVAL":
					$recurrence->interval = $rule[1];
					break;

				case "BYDAY":
					$dval = 0;
					$days = explode(",", $rule[1]);
					foreach ($days as $day) {
						if (strlen($day) > 2) {
							// nth weekday (eg. 2TU, -1FR): ActiveSync week of month goes from 1 to 5, 5 being the last
							$recurrence->weekofmonth = self::toWeekOfMonth(substr($day, 0, -2));
							$day = substr($day, -2);
						}
						switch ($day) {
							//   1 = Sunday
							//   2 = Monday
							//   4 = Tuesday
							//   8 = Wednesday
							//  16 = Thursday
							//  32 = Friday
							//  62 = Weekdays  // not in spec: daily weekday recurrence
							//  64 = Saturday
							case "SU":
								$dval += 1;
								break;
							case "MO":
								$dval += 2;
								break;
							case "TU":
								$dval += 4;
								break;
							case "WE":
								$dval += 8;
								break;
							case "TH":
								$dval += 16;
								break;
							case "FR":
								$dval += 32;
								break;
							case "SA":
								$dval += 64;
								break;
						}
					}
					$recurrence->dayofweek = $dval;
					break;

				//Only 1 BYMONTHDAY is supported, so BYMONTHDAY=2,3 will only include 2
				case "BYMONTHDAY":
					$days = explode(",", $rule[1]);
					$recurrence->dayofmonth = $days[0];
					break;

				case "BYMONTH":
					$recurrence->monthofyear = $rule[1];
					break;

				// The same nth weekday written as BYDAY=FR;BYSETPOS=-1 (only one position is supported)
				case "BYSETPOS":
					$positions = explode(",", $rule[1]);
					$recurrence->weekofmonth = self::toWeekOfMonth($positions[0]);
					break;

				default:
					\ZLog::Write(LOGLEVEL_DEBUG, sprintf("ZPUtil->rruleToMessageRecurrence(): '%s' is not yet supported.", $rule[0]));
			}
		}
		// Monthly and yearly on weekdays are types of their own (on the nth weekday)
		if (isset($recurrence->dayofweek)) {
			if ($recurrence->type == "2") {
				$recurrence->type = "3";
			} else if ($recurrence->type == "5") {
				$recurrence->type = "6";
			}
			if ((($recurrence->type == "3") || ($recurrence->type == "6")) && !isset($recurrence->weekofmonth)) {
				$recurrence->weekofmonth = 1;
			}
		}
		return $recurrence;
	}

	/**
	 * Converts an RRULE ordinal (1..5, or -1 for the last) into an ActiveSync week of month (1..5, 5 being the last).
	 */
	private static function toWeekOfMonth($ordinal) {
		$week = intval($ordinal);
		return (($week < 0) || ($week > 5)) ? 5 : $week;
	}
	
	public static function messageRecurrenceToRRule($rec, $eventStart, $eventTimezoneName, $deviceFamily = null) {
		$rrule = array();
		if (isset($rec->type)) {
			$freq = "";
			switch ($rec->type) {
				case "0":
					$freq = "DAILY";
					break;
				case "1":
					$freq = "WEEKLY";
					break;
				case "2":
				case "3":
					$freq = "MONTHLY";
					break;
				case "5":
				case "6":
					$freq = "YEARLY";
					break;
			}
			$rrule[] = "FREQ=" . $freq;
		}
		if (isset($rec->until)) {
			// Seems that android treat until-date chosen from the interface
			// as until+1 so it automatically pass the until date subtracted 
			// by 1 day. Also the time seems strange, it's set always at 00:00.
			// The code below adjust it by adding a day more, but note that 
			// the android device will display the event with a length of a day 
			// less until a sync will be performed on that event
			// We keep commmented only the day modification, the time will be
			// adjusted according to the start time.
			$until = $rec->until;
			if (is_null($deviceFamily)) $deviceFamily = self::getDeviceFamily();
			if (self::FAMILY_ANDROID === $deviceFamily) {
				if (isset($eventStart) && isset($eventTimezoneName)) {
					$eventTz = self::getTimezone($eventTimezoneName);
					$startDt = self::toDateTime($eventStart, "UTC");
					$startDt->setTimezone($eventTz);
					//$sdate = self::toISODate(strtotime('+1 days', $rec->until));
					$sdate = self::toISODate($rec->until);
					$stime = $startDt->format('His');
					$until = \TimezoneUtil::MakeUTCDate($sdate.'T'.$stime, $eventTimezoneName);
				}
			}
			$rrule[] = "UNTIL=" . self::toISODateTime($until);
			//$rrule[] = "UNTIL=" . gmdate("Ymd\THis\Z", $rec->until);
		}
		if (isset($rec->occurrences)) {
			$rrule[] = "COUNT=" . $rec->occurrences;
		}
		if (isset($rec->interval)) {
			$rrule[] = "INTERVAL=" . $rec->interval;
		}
		if (isset($rec->dayofweek)) {
			if (isset($rec->weekofmonth)) {
				// nth of the days, written as WebTop does (BYSETPOS=-1;BYDAY=MO): with more days (eg. 62, any
				// weekday) it is the nth of them all, as in ActiveSync. Week 5 is the last one: -1 in RRULE
				$rrule[] = "BYSETPOS=" . ((intval($rec->weekofmonth) === 5) ? '-1' : $rec->weekofmonth);
			}
			$days = array();
			foreach (["SU" => 1, "MO" => 2, "TU" => 4, "WE" => 8, "TH" => 16, "FR" => 32, "SA" => 64] as $day => $bit) {
				if (($rec->dayofweek & $bit) == $bit) $days[] = $day;
			}
			$rrule[] = "BYDAY=" . implode(",", $days);
		}
		if (isset($rec->dayofmonth)) {
			$rrule[] = "BYMONTHDAY=" . $rec->dayofmonth;
		}
		if (isset($rec->monthofyear)) {
			$rrule[] = "BYMONTH=" . $rec->monthofyear;
		}
		return implode(";", $rrule);
	}
}
