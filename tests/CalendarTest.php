<?php

namespace WT\Tests;

use WT\EAS\ZPUtil;
use WT\Tests\Support\EasTestCase;

/**
 * Events between the phone (SyncAppointment) and WebTop. Values coming from
 * the phone are strings, as Z-Push decodes them from WBXML.
 */
class CalendarTest extends EasTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->webtop->addFolder('calendar', '10', 'Calendar', ['deflt' => true]);
		$this->webtop->addFolder('calendar', '20', '[User Two] Shared', ['elAcl' => 'r']);
		$this->webtop->addItem('calendar', '10', '1', [
			'start' => '20260715T080000Z',
			'end' => '20260715T090000Z',
			'tz' => 'Europe/Rome',
			'allDay' => false,
			'title' => 'Review',
			'location' => 'Room 1',
			'prvt' => true,
			'busy' => true,
			'reminder' => 15,
			'description' => 'Agenda',
			'recRule' => 'FREQ=WEEKLY;BYDAY=WE;COUNT=4',
			'exDates' => ['20260722'],
			'attendees' => [
				['address' => '"Rossi, Mario" <mario@example.com>', 'type' => 'IND', 'role' => 'OPT', 'status' => 'AC'],
				['address' => 'luigi@example.com <luigi@example.com>', 'type' => 'IND', 'role' => 'REQ', 'status' => 'NA']
			]
		]);
	}

	public function testGetEvent() {
		$event = $this->logon(\BackendWTCalendar::class)->GetMessage('10', '1', self::contentParameters());

		$this->assertSame('2026-07-15 08:00:00', gmdate('Y-m-d H:i:s', $event->starttime));
		$this->assertSame('2026-07-15 09:00:00', gmdate('Y-m-d H:i:s', $event->endtime));
		$this->assertSame('Europe/Rome', ZPUtil::tzBlobToTZName($event->timezone, 'Europe/Rome'));
		$this->assertEquals(0, $event->alldayevent);
		$this->assertSame('Review', $event->subject);
		$this->assertSame('Room 1', $event->location);
		$this->assertEquals(2, $event->sensitivity, 'private');
		$this->assertEquals(2, $event->busystatus, 'busy');
		$this->assertSame(15, $event->reminder);
		$this->assertSame('Agenda', self::readBody($event->asbody));
		// dtstamp is the time of the last change, from the etag
		$etag = $this->webtop->item('calendar', '10', '1')['etag'];
		$this->assertSame(substr($etag, 0, 14), gmdate('YmdHis', $event->dtstamp));
	}

	public function testGetRecurringEventWithDeletedOccurrence() {
		$event = $this->logon(\BackendWTCalendar::class)->GetMessage('10', '1', self::contentParameters());

		$this->assertEquals(1, $event->recurrence->type, 'weekly');
		$this->assertEquals(8, $event->recurrence->dayofweek, 'wednesday');
		$this->assertEquals(4, $event->recurrence->occurrences);
		$this->assertCount(1, $event->exceptions);
		$this->assertEquals(1, $event->exceptions[0]->deleted);
		$this->assertSame('2026-07-22 08:00:00', gmdate('Y-m-d H:i:s', $event->exceptions[0]->exceptionstarttime));
	}

	public function testGetEventAttendees() {
		$event = $this->logon(\BackendWTCalendar::class)->GetMessage('10', '1', self::contentParameters());

		$this->assertEquals(1, $event->meetingstatus);
		$this->assertCount(2, $event->attendees);
		$this->assertSame(['Rossi, Mario', 'mario@example.com', 2, 3],
			[$event->attendees[0]->name, $event->attendees[0]->email, $event->attendees[0]->attendeetype, $event->attendees[0]->attendeestatus]);
		$this->assertSame(['luigi@example.com', 'luigi@example.com', 1, 0],
			[$event->attendees[1]->name, $event->attendees[1]->email, $event->attendees[1]->attendeetype, $event->attendees[1]->attendeestatus]);
	}

	public function testMessageListUsesFixedWindow() {
		// Whatever the phone's filter, WebTop gets calendar.syncBackMonths (1): the
		// list must be the same at SyncKey=0 (no filter yet) and afterwards
		$backend = $this->logon(\BackendWTCalendar::class);
		$backend->GetMessageList('10', strtotime('-2 weeks'));

		$request = $this->webtop->requestsTo('#/folders/10/messages-stats$#')[0];
		$cutoff = \DateTime::createFromFormat('Ymd\THis\Z', $request['query']['cutoffDate'][0], new \DateTimeZone('UTC'));
		$this->assertEqualsWithDelta(strtotime('-1 months'), $cutoff->getTimestamp(), 60);
	}

	public function testCreateEvent() {
		$event = self::newEvent('Party');
		$event->location = 'Home';
		$event->asbody = self::plainBody('Bring a cake');

		$stat = $this->logon(\BackendWTCalendar::class)->ChangeMessage('10', '', $event, self::contentParameters());

		$stored = $this->webtop->item('calendar', '10', $stat['id'])['data'];
		$this->assertSame('Europe/Rome', $stored['tz']);
		$this->assertSame('20260801T180000Z', $stored['start']);
		$this->assertSame('20260801T200000Z', $stored['end']);
		$this->assertFalse($stored['allDay']);
		$this->assertSame('Party', $stored['title']);
		$this->assertSame('Home', $stored['location']);
		$this->assertFalse($stored['prvt']);
		$this->assertTrue($stored['busy']);
		$this->assertEquals(15, $stored['reminder']);
		$this->assertSame('Bring a cake', $stored['description']);
	}

	public function testCreateAllDayEvent() {
		$event = self::newEvent('Holiday');
		$event->alldayevent = '1';

		$stat = $this->logon(\BackendWTCalendar::class)->ChangeMessage('10', '', $event, self::contentParameters());

		$this->assertTrue($this->webtop->item('calendar', '10', $stat['id'])['data']['allDay']);
	}

	public function testCreateRecurringEventWithDeletedOccurrence() {
		$event = self::newEvent('Gym');
		$event->recurrence = new \SyncRecurrence();
		$event->recurrence->type = '1';
		$event->recurrence->dayofweek = '64';
		$event->recurrence->occurrences = '10';
		$exception = new \SyncAppointmentException();
		$exception->deleted = '1';
		$exception->exceptionstarttime = gmmktime(18, 0, 0, 8, 8, 2026);
		$event->exceptions = [$exception];

		$stat = $this->logon(\BackendWTCalendar::class)->ChangeMessage('10', '', $event, self::contentParameters());

		$stored = $this->webtop->item('calendar', '10', $stat['id'])['data'];
		$this->assertSame('FREQ=WEEKLY;COUNT=10;BYDAY=SA', $stored['recRule']);
		$this->assertSame(['20260808'], $stored['exDates']);
	}

	public static function attendeeTypesAndStatuses() {
		// ActiveSync type and status => WebTop type, role, status
		return [
			'required, unknown' => ['1', '0', ['IND', 'REQ', 'NA']],
			'optional, accepted' => ['2', '3', ['IND', 'OPT', 'AC']],
			'resource, tentative' => ['3', '2', ['RES', 'REQ', 'TE']],
			'required, declined' => ['1', '4', ['IND', 'REQ', 'DE']],
			'required, not responded' => ['1', '5', ['IND', 'REQ', 'NA']],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('attendeeTypesAndStatuses')]
	public function testCreateEventWithAttendeeTypeAndStatus($type, $status, array $expected) {
		// As the phone sends them (no address, to stay off ext-imap)
		$attendee = new \SyncAttendee();
		$attendee->name = 'Anna';
		$attendee->attendeetype = $type;
		$attendee->attendeestatus = $status;
		$event = self::newEvent('Meeting');
		$event->attendees = [$attendee];

		$stat = $this->logon(\BackendWTCalendar::class)->ChangeMessage('10', '', $event, self::contentParameters());

		$stored = $this->webtop->item('calendar', '10', $stat['id'])['data']['attendees'][0];
		$this->assertSame($expected, [$stored['type'], $stored['role'], $stored['status']]);
	}

	public function testModifiedOccurrenceAsksThePhoneToResync() {
		// The occurrence moved on the phone becomes an event of its own in WebTop:
		// the phone must fetch the series again
		$event = self::newEvent('Review');
		$event->recurrence = new \SyncRecurrence();
		$event->recurrence->type = '1';
		$event->recurrence->dayofweek = '8';
		$moved = new \SyncAppointmentException();
		$moved->deleted = '0';
		$moved->exceptionstarttime = gmmktime(8, 0, 0, 7, 22, 2026);
		$moved->starttime = gmmktime(10, 0, 0, 7, 22, 2026);
		$moved->endtime = gmmktime(11, 0, 0, 7, 22, 2026);
		$event->exceptions = [$moved];

		try {
			$this->logon(\BackendWTCalendar::class)->ChangeMessage('10', '1', $event, self::contentParameters());
			$this->fail('StatusException expected');
		} catch (\StatusException $ex) {
			$this->assertSame(SYNC_STATUS_CONFLICTCLIENTSERVEROBJECT, $ex->getCode());
		}
		$sent = $this->lastJsonSent('PUT', '#/folders/10/messages/1$#');
		$this->assertCount(1, $sent['exceptions']);
		$this->assertSame('20260722T100000Z', $sent['exceptions'][0]['start']);
		$this->assertSame('Review', $sent['exceptions'][0]['title'], 'Taken from the series');
	}

	public function testReadOnlyCalendarRefusesChanges() {
		$backend = $this->logon(\BackendWTCalendar::class);

		$this->assertFalse($backend->ChangeMessage('20', '', self::newEvent('Party'), self::contentParameters()));
		$this->assertSame([], $this->webtop->requestsTo('#/folders/20/messages#', 'POST'));
	}

	public function testDeleteEvent() {
		$this->assertTrue($this->logon(\BackendWTCalendar::class)->DeleteMessage('10', '1', self::contentParameters()));
		$this->assertNull($this->webtop->item('calendar', '10', '1'));
	}

	public function testDeleteOfEventAlreadyDeletedInWebTop() {
		try {
			$this->logon(\BackendWTCalendar::class)->DeleteMessage('10', '99', self::contentParameters());
			$this->fail('StatusException expected');
		} catch (\StatusException $ex) {
			$this->assertSame(SYNC_STATUS_OBJECTNOTFOUND, $ex->getCode());
		}
	}

	/**
	 * An event as the phone sends it: 20:00-22:00 in Rome on 1 August 2026.
	 */
	private static function newEvent($subject) {
		$event = new \SyncAppointment();
		$event->timezone = ZPUtil::tzNameToTZBlob('Europe/Rome');
		$event->starttime = gmmktime(18, 0, 0, 8, 1, 2026);
		$event->endtime = gmmktime(20, 0, 0, 8, 1, 2026);
		$event->subject = $subject;
		$event->alldayevent = '0';
		$event->sensitivity = '0';
		$event->busystatus = '2';
		$event->reminder = '15';
		return $event;
	}
}
