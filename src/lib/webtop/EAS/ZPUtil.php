<?php

namespace WT\EAS;

use lf4php\LoggerFactory;
use Html2Text\Html2Text;

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
		$ret = \Mail_RFC822::parseAddressList($ia, 'local', null, null, 1);
		if (($ret === false) || !is_array($ret) || (count($ret) < 1)) {
			return false;
		} else {
			return [
				'address' => $ret[0]->mailbox.'@'.$ret[0]->host,
				'personal' => $ret[0]->personal
			];
		}
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
		return base64_encode(\TimezoneUtil::GetSyncBlobFromTZ($tzObj));
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
	 * @param array $tzObj The timezone-info object
	 * @param String $myTZName A timezone name/ID to be evaluated as first
	 * @return String
	 */
	public static function guessTZNameFromTZObj($tzObj, $myTZName = null) {
		// Get a list of all timezones
		$tzIds = \DateTimeZone::listIdentifiers();
		// Adds default/system timezone (at beginning)
		array_unshift($tzIds, date_default_timezone_get());
		// Adds my timezone (at beginning), if present
		if (!is_null($myTZName)) array_unshift($tzIds, $myTZName);
		
		foreach ($tzIds as $tzId) {
			$tzObj2 = \TimezoneUtil::GetFullTZFromTZName($tzId);
			if ($tzObj['bias'] !== $tzObj2['bias']) continue;
			if ($tzObj['dstbias'] !== $tzObj2['dstbias']) continue;
			if ($tzObj['dststartyear'] !== $tzObj2['dststartyear']) continue;
			if ($tzObj['dststartmonth'] !== $tzObj2['dststartmonth']) continue;
			if ($tzObj['dststartday'] !== $tzObj2['dststartday']) continue;
			if ($tzObj['dststartweek'] !== $tzObj2['dststartweek']) continue;
			if ($tzObj['dststarthour'] !== $tzObj2['dststarthour']) continue;
			if ($tzObj['dststartminute'] !== $tzObj2['dststartminute']) continue;
			if ($tzObj['dstendyear'] !== $tzObj2['dstendyear']) continue;
			if ($tzObj['dstendmonth'] !== $tzObj2['dstendmonth']) continue;
			if ($tzObj['dstendday'] !== $tzObj2['dstendday']) continue;
			if ($tzObj['dstendweek'] !== $tzObj2['dstendweek']) continue;
			if ($tzObj['dstendhour'] !== $tzObj2['dstendhour']) continue;
			if ($tzObj['dstendminute'] !== $tzObj2['dstendminute']) continue;
			return $tzId;
		}
		return date_default_timezone_get();
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
	
	
	
	
	
	
	
	
	
	
	
	public static function messageBodyToNote(\SyncBaseBody $body = null) {
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
						if ($recurrence->type == "2") {
							if (strlen($day) > 2) {
								$recurrence->weekofmonth = intval($day);
								$day = substr($day, -2);
							} else {
								$recurrence->weekofmonth = 1;
							}
							$recurrence->type = "3";
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

				default:
					\ZLog::Write(LOGLEVEL_DEBUG, sprintf("ZPUtil->rruleToMessageRecurrence(): '%s' is not yet supported.", $rule[0]));
			}
		}
		return $recurrence;
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
			$week = '';
			if (isset($rec->weekofmonth)) {
				$week = $rec->weekofmonth;
			}
			$days = array();
			if (($rec->dayofweek & 1) == 1) {
				if (empty($week)) {
					$days[] = "SU";
				} else {
					$days[] = $week . "SU";
				}
			}
			if (($rec->dayofweek & 2) == 2) {
				if (empty($week)) {
					$days[] = "MO";
				} else {
					$days[] = $week . "MO";
				}
			}
			if (($rec->dayofweek & 4) == 4) {
				if (empty($week)) {
					$days[] = "TU";
				} else {
					$days[] = $week . "TU";
				}
			}
			if (($rec->dayofweek & 8) == 8) {
				if (empty($week)) {
					$days[] = "WE";
				} else {
					$days[] = $week . "WE";
				}
			}
			if (($rec->dayofweek & 16) == 16) {
				if (empty($week)) {
					$days[] = "TH";
				} else {
					$days[] = $week . "TH";
				}
			}
			if (($rec->dayofweek & 32) == 32) {
				if (empty($week)) {
					$days[] = "FR";
				} else {
					$days[] = $week . "FR";
				}
			}
			if (($rec->dayofweek & 64) == 64) {
				if (empty($week)) {
					$days[] = "SA";
				} else {
					$days[] = $week . "SA";
				}
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
