<?php

namespace WT\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use WT\EAS\ZPUtil;
use WT\Tests\Support\EasTestCase;

/**
 * Conversions between WebTop values and ActiveSync ones. Recurrence values
 * follow MS-ASCAL (Type, DayOfWeek, WeekOfMonth: 5 means the last week); the
 * nth weekday is written as WebTop does (BYSETPOS=-1;BYDAY=MO), since its
 * recurrence editor recognizes only that form.
 */
class ZPUtilTest extends EasTestCase {

	public static function internetAddresses() {
		return [
			'address only' => ['mario@example.com', 'mario@example.com', null],
			'name and address' => ['Mario Rossi <mario@example.com>', 'mario@example.com', 'Mario Rossi'],
			'quoted name' => ['"Rossi, Mario" <mario@example.com>', 'mario@example.com', 'Rossi, Mario'],
			'accents' => ['Nicolò Pàstore <nicolo@example.com>', 'nicolo@example.com', 'Nicolò Pàstore'],
			'apostrophe' => ["Gianni D'Angelo <gianni@example.com>", 'gianni@example.com', "Gianni D'Angelo"],
			'dot' => ['M. Rossi <mario@example.com>', 'mario@example.com', 'M. Rossi'],
			// WebTop writes attendees without a name this way
			'address as name' => ['luigi@example.com <luigi@example.com>', 'luigi@example.com', 'luigi@example.com'],
		];
	}

	#[DataProvider('internetAddresses')]
	public function testParseInternetAddress($ia, $address, $personal) {
		$parsed = ZPUtil::parseInternetAddress($ia);

		$this->assertSame($address, $parsed['address']);
		$this->assertEquals($personal, $parsed['personal']);
	}

	public function testISODates() {
		$this->assertSame('2000-02-29', gmdate('Y-m-d', ZPUtil::parseISODate('20000229')));
		$this->assertSame('20000229', ZPUtil::toISODate(gmmktime(0, 0, 0, 2, 29, 2000)));
		$this->assertSame('2026-07-15 08:00:00', gmdate('Y-m-d H:i:s', ZPUtil::parseISODateTime('20260715T080000Z')));
		$this->assertSame('20260715T080000Z', ZPUtil::toISODateTime(gmmktime(8, 0, 0, 7, 15, 2026)));
		$this->assertNull(ZPUtil::parseISODate(null));
		$this->assertNull(ZPUtil::toISODateTime(null));
	}

	public static function timezones() {
		return [['Europe/Rome'], ['America/New_York'], ['Asia/Tokyo'], ['Australia/Sydney'], ['Europe/London'], ['Europe/Kyiv'], ['UTC']];
	}

	#[DataProvider('timezones')]
	public function testTimezoneBlobRoundTrip($tzName) {
		$blob = ZPUtil::tzNameToTZBlob($tzName);

		$this->assertSame(172, strlen(base64_decode($blob)), 'MS-ASDTYPE TimeZone is 172 bytes');
		// The phone's own zone wins among the equivalent ones
		$this->assertSame($tzName, ZPUtil::tzBlobToTZName($blob, $tzName));
		// Without it, an equivalent zone: same offsets in winter and summer
		$this->assertSameOffsets($tzName, ZPUtil::tzBlobToTZName($blob));
	}

	public function testBlobOfAnotherZoneKeepsItsOffsets() {
		// A phone in London, the user's WebTop zone is Rome: zones Z-Push does
		// not know (eg. America/Ciudad_Juarez, UTC-7) must not match London
		$blob = ZPUtil::tzNameToTZBlob('Europe/London');

		$this->assertSameOffsets('Europe/London', ZPUtil::tzBlobToTZName($blob, 'Europe/Rome'));
	}

	public function testUtcBlob() {
		// What a phone sends for UTC: no bias, no daylight saving
		$blob = self::blob(0, '', [0, 0, 0, 0], 0, [0, 0, 0, 0]);

		$this->assertSameOffsets('UTC', ZPUtil::tzBlobToTZName($blob, 'Europe/Rome'));
	}

	public function testZoneNameWrittenInTheBlobIsPreferred() {
		// We write the zone ID in the blob: when the phone sends it back, it wins over equivalent zones
		$blob = ZPUtil::tzNameToTZBlob('Europe/London');

		$this->assertSame('Europe/London', ZPUtil::tzBlobToTZName($blob, 'Europe/Rome'));
	}

	public function testBlobWithWindowsNameAndEuropeanRules() {
		// As Windows phones send it: Windows name, daylight saving from the last Sunday of
		// March at 2:00 to the last Sunday of October at 3:00
		$blob = self::blob(-60, 'W. Europe Standard Time', [10, 0, 5, 3], -60, [3, 0, 5, 2]);

		$this->assertSameOffsets('Europe/Rome', ZPUtil::tzBlobToTZName($blob, 'America/New_York'));
	}

	public function testBlobOfZoneThatDroppedDaylightSaving() {
		// Mexico City has no daylight saving time since 2022, while Z-Push's table still has it
		$blob = self::blob(360, '', [0, 0, 0, 0], 0, [0, 0, 0, 0]);

		$this->assertSameOffsets('America/Mexico_City', ZPUtil::tzBlobToTZName($blob, 'Europe/Rome'));
	}

	/**
	 * Packs a MS-ASDTYPE TimeZone blob; changes are [month, day of week, week of month, hour].
	 */
	private static function blob($bias, $name, array $toStd, $dstBias, array $toDst) {
		$name = str_pad(mb_convert_encoding($name, 'UTF-16LE', 'UTF-8'), 64, "\0");
		return base64_encode(pack('la64vvvvvvvvla64vvvvvvvvl',
			$bias, $name, 0, $toStd[0], $toStd[1], $toStd[2], $toStd[3], 0, 0, 0,
			0, $name, 0, $toDst[0], $toDst[1], $toDst[2], $toDst[3], 0, 0, 0,
			$dstBias));
	}

	/**
	 * The guessed zone is equivalent to the expected one: same offsets in winter and summer.
	 */
	private function assertSameOffsets($expectedTzName, $tzName) {
		$expected = new \DateTimeZone($expectedTzName);
		$actual = new \DateTimeZone($tzName);
		foreach (['2026-01-15 12:00:00', '2026-07-15 12:00:00'] as $date) {
			$utc = new \DateTime($date, new \DateTimeZone('UTC'));
			$this->assertSame($expected->getOffset($utc), $actual->getOffset($utc), "$tzName as $expectedTzName, offset on $date");
		}
	}

	public static function zonesZPushGetsRight() {
		// Blobs written before taking the rules from PHP: phones paired then must see no change
		return [
			'Europe/Rome' => ['Europe/Rome', 'xP///0UAdQByAG8AcABlAC8AUgBvAG0AZQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAoAAAAFAAMAAAAAAAAAAAAAAEUAdQByAG8AcABlAC8AUgBvAG0AZQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAFAAIAAAAAAAAAxP///w=='],
			'Europe/Paris' => ['Europe/Paris', 'xP///0UAdQByAG8AcABlAC8AUABhAHIAaQBzAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAoAAAAFAAMAAAAAAAAAAAAAAEUAdQByAG8AcABlAC8AUABhAHIAaQBzAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAFAAIAAAAAAAAAxP///w=='],
			'Europe/Berlin' => ['Europe/Berlin', 'xP///0UAdQByAG8AcABlAC8AQgBlAHIAbABpAG4AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAoAAAAFAAMAAAAAAAAAAAAAAEUAdQByAG8AcABlAC8AQgBlAHIAbABpAG4AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAFAAIAAAAAAAAAxP///w=='],
			'Europe/London' => ['Europe/London', 'AAAAAEUAdQByAG8AcABlAC8ATABvAG4AZABvAG4AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAoAAAAFAAIAAAAAAAAAAAAAAEUAdQByAG8AcABlAC8ATABvAG4AZABvAG4AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAFAAEAAAAAAAAAxP///w=='],
			'Europe/Dublin' => ['Europe/Dublin', 'AAAAAEUAdQByAG8AcABlAC8ARAB1AGIAbABpAG4AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAoAAAAFAAIAAAAAAAAAAAAAAEUAdQByAG8AcABlAC8ARAB1AGIAbABpAG4AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAFAAEAAAAAAAAAxP///w=='],
			'Europe/Lisbon' => ['Europe/Lisbon', 'AAAAAEUAdQByAG8AcABlAC8ATABpAHMAYgBvAG4AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAoAAAAFAAIAAAAAAAAAAAAAAEUAdQByAG8AcABlAC8ATABpAHMAYgBvAG4AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAFAAEAAAAAAAAAxP///w=='],
			'Europe/Madrid' => ['Europe/Madrid', 'xP///0UAdQByAG8AcABlAC8ATQBhAGQAcgBpAGQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAoAAAAFAAMAAAAAAAAAAAAAAEUAdQByAG8AcABlAC8ATQBhAGQAcgBpAGQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAFAAIAAAAAAAAAxP///w=='],
			'Europe/Athens' => ['Europe/Athens', 'iP///0UAdQByAG8AcABlAC8AQQB0AGgAZQBuAHMAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAoAAAAFAAQAAAAAAAAAAAAAAEUAdQByAG8AcABlAC8AQQB0AGgAZQBuAHMAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAFAAMAAAAAAAAAxP///w=='],
			'Europe/Helsinki' => ['Europe/Helsinki', 'iP///0UAdQByAG8AcABlAC8ASABlAGwAcwBpAG4AawBpAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAoAAAAFAAQAAAAAAAAAAAAAAEUAdQByAG8AcABlAC8ASABlAGwAcwBpAG4AawBpAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAFAAMAAAAAAAAAxP///w=='],
			'Europe/Zurich' => ['Europe/Zurich', 'xP///0UAdQByAG8AcABlAC8AWgB1AHIAaQBjAGgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAoAAAAFAAMAAAAAAAAAAAAAAEUAdQByAG8AcABlAC8AWgB1AHIAaQBjAGgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAFAAIAAAAAAAAAxP///w=='],
			'America/New_York' => ['America/New_York', 'LAEAAEEAbQBlAHIAaQBjAGEALwBOAGUAdwBfAFkAbwByAGsAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAsAAAABAAIAAAAAAAAAAAAAAEEAbQBlAHIAaQBjAGEALwBOAGUAdwBfAFkAbwByAGsAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAACAAIAAAAAAAAAxP///w=='],
			'America/Los_Angeles' => ['America/Los_Angeles', '4AEAAEEAbQBlAHIAaQBjAGEALwBMAG8AcwBfAEEAbgBnAGUAbABlAHMAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAsAAAABAAIAAAAAAAAAAAAAAEEAbQBlAHIAaQBjAGEALwBMAG8AcwBfAEEAbgBnAGUAbABlAHMAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAACAAIAAAAAAAAAxP///w=='],
			'Australia/Sydney' => ['Australia/Sydney', 'qP3//0EAdQBzAHQAcgBhAGwAaQBhAC8AUwB5AGQAbgBlAHkAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAQAAAABAAMAAAAAAAAAAAAAAEEAdQBzAHQAcgBhAGwAaQBhAC8AUwB5AGQAbgBlAHkAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAoAAAABAAIAAAAAAAAAxP///w=='],
			'Asia/Tokyo' => ['Asia/Tokyo', '5P3//0EAcwBpAGEALwBUAG8AawB5AG8AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAEEAcwBpAGEALwBUAG8AawB5AG8AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAxP///w=='],
		];
	}
	
	#[DataProvider('zonesZPushGetsRight')]
	public function testBlobOfZoneZPushGetsRightIsUnchanged($tzName, $blob) {
		$this->assertSame($blob, ZPUtil::tzNameToTZBlob($tzName));
	}
	
	public function testBlobDescribesEveryZone() {
		// Read back with the zone itself as first choice, the blob gives it back only if it
		// has exactly its offsets and daylight saving changes of this year
		$year = (int)gmdate('Y');
		$wrong = [];
		foreach (\DateTimeZone::listIdentifiers() as $tzName) {
			$changes = count((new \DateTimeZone($tzName))->getTransitions(gmmktime(0, 0, 0, 1, 1, $year), gmmktime(0, 0, 0, 1, 1, $year + 1) - 1)) - 1;
			if ($changes > 2) continue; // eg. Morocco during Ramadan: not expressible
			if (ZPUtil::tzBlobToTZName(ZPUtil::tzNameToTZBlob($tzName), $tzName) !== $tzName) $wrong[] = $tzName;
		}
		$this->assertSame([], $wrong);
	}
	
	public static function zonesZPushGotWrong() {
		return [
			// Unknown to Z-Push (it knows Europe/Kiev): it had the rules of London
			'Kyiv' => ['Europe/Kyiv', [7200, 10800]],
			// No more daylight saving time, still there in Z-Push's table
			'Moscow' => ['Europe/Moscow', [10800, 10800]],
			'Istanbul' => ['Europe/Istanbul', [10800, 10800]],
			'Mexico City' => ['America/Mexico_City', [-21600, -21600]],
			// Z-Push encoded UTC with the rules of London
			'UTC' => ['UTC', [0, 0]],
		];
	}
	
	#[DataProvider('zonesZPushGotWrong')]
	public function testBlobOfZoneZPushGotWrong($tzName, array $offsets) {
		$tzObj = ZPUtil::tzBlobToTZObj(ZPUtil::tzNameToTZBlob($tzName));
		
		// Standard offset from the bias, daylight saving one from its bias
		$this->assertSame($offsets, [-$tzObj['bias'] * 60, -($tzObj['bias'] + $tzObj['dstbias']) * 60]);
	}
	
	public function testTimezoneBlobNameIsUtf16LittleEndian() {
		$tzObj = ZPUtil::tzBlobToTZObj(ZPUtil::tzNameToTZBlob('Europe/Rome'));

		$this->assertSame('Europe/Rome', rtrim(mb_convert_encoding($tzObj['tzname'], 'UTF-8', 'UTF-16LE'), "\0"));
		$this->assertSame(-60, $tzObj['bias']);
	}

	public static function rrulesToRecurrence() {
		return [
			'daily' => ['FREQ=DAILY;INTERVAL=2;COUNT=5', ['type' => 0, 'interval' => 2, 'occurrences' => 5]],
			'weekly' => ['FREQ=WEEKLY;BYDAY=MO,WE,FR', ['type' => 1, 'dayofweek' => 2 + 8 + 32]],
			'monthly on day' => ['FREQ=MONTHLY;BYMONTHDAY=15', ['type' => 2, 'dayofmonth' => 15]],
			'monthly on nth day' => ['FREQ=MONTHLY;BYDAY=2TU', ['type' => 3, 'weekofmonth' => 2, 'dayofweek' => 4]],
			'monthly on last day' => ['FREQ=MONTHLY;BYDAY=-1FR', ['type' => 3, 'weekofmonth' => 5, 'dayofweek' => 32]],
			// As WebTop writes them
			'monthly on last day by position' => ['FREQ=MONTHLY;BYSETPOS=-1;BYDAY=MO', ['type' => 3, 'weekofmonth' => 5, 'dayofweek' => 2]],
			'monthly on last weekday' => ['FREQ=MONTHLY;BYSETPOS=-1;BYDAY=MO,TU,WE,TH,FR', ['type' => 3, 'weekofmonth' => 5, 'dayofweek' => 62]],
			'yearly on nth day by position' => ['FREQ=YEARLY;BYSETPOS=2;BYDAY=SU;BYMONTH=5', ['type' => 6, 'monthofyear' => 5, 'weekofmonth' => 2, 'dayofweek' => 1]],
			'yearly on date' => ['FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=8', ['type' => 5, 'monthofyear' => 3, 'dayofmonth' => 8]],
			'yearly on nth day' => ['FREQ=YEARLY;BYMONTH=11;BYDAY=4TH', ['type' => 6, 'monthofyear' => 11, 'weekofmonth' => 4, 'dayofweek' => 16]],
			'yearly on last day' => ['FREQ=YEARLY;BYMONTH=5;BYDAY=-1MO', ['type' => 6, 'monthofyear' => 5, 'weekofmonth' => 5, 'dayofweek' => 2]],
			'parts in any order' => ['BYDAY=-1MO;BYMONTH=5;FREQ=YEARLY', ['type' => 6, 'monthofyear' => 5, 'weekofmonth' => 5, 'dayofweek' => 2]],
		];
	}

	#[DataProvider('rrulesToRecurrence')]
	public function testRRuleToRecurrence($rrule, array $expected) {
		$recurrence = ZPUtil::rruleToMessageRecurrence($rrule);

		foreach ($expected as $field => $value) {
			$this->assertEquals($value, $recurrence->$field, $field);
		}
	}

	public function testRRuleUntil() {
		$recurrence = ZPUtil::rruleToMessageRecurrence('FREQ=DAILY;UNTIL=20260731T080000Z');

		$this->assertSame('2026-07-31 08:00:00', gmdate('Y-m-d H:i:s', $recurrence->until));
	}

	public static function recurrencesToRRule() {
		// Values as Z-Push decodes them from WBXML: strings
		return [
			'daily' => [['type' => '0', 'interval' => '2', 'occurrences' => '5'], 'FREQ=DAILY;COUNT=5;INTERVAL=2'],
			'weekly' => [['type' => '1', 'dayofweek' => '42'], 'FREQ=WEEKLY;BYDAY=MO,WE,FR'],
			'monthly on day' => [['type' => '2', 'dayofmonth' => '15'], 'FREQ=MONTHLY;BYMONTHDAY=15'],
			'monthly on nth day' => [['type' => '3', 'weekofmonth' => '2', 'dayofweek' => '4'], 'FREQ=MONTHLY;BYSETPOS=2;BYDAY=TU'],
			'monthly on last day' => [['type' => '3', 'weekofmonth' => '5', 'dayofweek' => '32'], 'FREQ=MONTHLY;BYSETPOS=-1;BYDAY=FR'],
			'monthly on last weekday' => [['type' => '3', 'weekofmonth' => '5', 'dayofweek' => '62'], 'FREQ=MONTHLY;BYSETPOS=-1;BYDAY=MO,TU,WE,TH,FR'],
			'yearly on date' => [['type' => '5', 'monthofyear' => '3', 'dayofmonth' => '8'], 'FREQ=YEARLY;BYMONTHDAY=8;BYMONTH=3'],
			'yearly on nth day' => [['type' => '6', 'monthofyear' => '11', 'weekofmonth' => '4', 'dayofweek' => '16'], 'FREQ=YEARLY;BYSETPOS=4;BYDAY=TH;BYMONTH=11'],
			'yearly on last day' => [['type' => '6', 'monthofyear' => '5', 'weekofmonth' => '5', 'dayofweek' => '2'], 'FREQ=YEARLY;BYSETPOS=-1;BYDAY=MO;BYMONTH=5'],
		];
	}

	#[DataProvider('recurrencesToRRule')]
	public function testRecurrenceToRRule(array $fields, $rrule) {
		$recurrence = new \SyncRecurrence();
		foreach ($fields as $field => $value) {
			$recurrence->$field = $value;
		}

		$this->assertSame($rrule, ZPUtil::messageRecurrenceToRRule($recurrence, null, null, ZPUtil::FAMILY_IOS));
	}

	public function testAndroidUntilGetsTheEventTime() {
		// Android sends the last day at midnight: the occurrence of that day must be included
		$recurrence = new \SyncRecurrence();
		$recurrence->type = '0';
		$recurrence->until = gmmktime(0, 0, 0, 7, 31, 2026);
		$start = gmmktime(8, 0, 0, 7, 15, 2026); // 10:00 in Rome

		$this->assertSame('FREQ=DAILY;UNTIL=20260731T080000Z',
			ZPUtil::messageRecurrenceToRRule($recurrence, $start, 'Europe/Rome', ZPUtil::FAMILY_ANDROID));
	}

	public function testNoteToBodyAndBack() {
		$body = ZPUtil::noteToMessageBody('Bring the slides');

		$this->assertEquals(SYNC_BODYPREFERENCE_PLAIN, $body->type);
		$this->assertSame('Bring the slides', ZPUtil::messageBodyToNote($body));
	}

	public function testNoteTruncation() {
		$body = ZPUtil::noteToMessageBody('Città è più bella', 6);

		$this->assertEquals(1, $body->truncated);
		$this->assertSame('Città', stream_get_contents($body->data), 'Cut on a character boundary');
	}

	public function testHtmlBodyBecomesText() {
		$body = new \SyncBaseBody();
		$body->type = SYNC_BODYPREFERENCE_HTML;
		$body->data = \StringStreamWrapper::Open('<p>Hello <b>World</b></p>');

		$this->assertSame('Hello WORLD', trim(ZPUtil::messageBodyToNote($body)));
	}

	#[\PHPUnit\Framework\Attributes\RequiresPhpExtension('fileinfo')]
	public function testPictureRoundTrip() {
		$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

		$dataUri = ZPUtil::pictureToDataUri(base64_encode($png));
		$this->assertSame('image/png', $dataUri->getMimeType());

		$picture = ZPUtil::dataUriToPicture(\DataURI\Parser::parse(\DataURI\Dumper::dump($dataUri)));
		$this->assertSame(base64_encode($png), $picture['data']);
	}

	public static function deviceTypes() {
		return [
			['iPhone', ZPUtil::FAMILY_IOS],
			['iPad', ZPUtil::FAMILY_IOS],
			['Android', ZPUtil::FAMILY_ANDROID],
			['SAMSUNGSMG991B', ZPUtil::FAMILY_UNKNOWN],
			['WindowsOutlook15', ZPUtil::FAMILY_OUTLOOK],
		];
	}

	#[DataProvider('deviceTypes')]
	public function testDeviceFamily($deviceType, $family) {
		self::setRequest($deviceType, '14.1');

		$this->assertSame($family, ZPUtil::getDeviceFamily());
	}
}
