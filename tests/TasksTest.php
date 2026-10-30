<?php

namespace WT\Tests;

use WT\Tests\Support\EasTestCase;

/**
 * Tasks between the phone (SyncTask) and WebTop. WebTop importance goes from
 * 1 (highest) to 9 (lowest), ActiveSync one is 0 (low), 1 (normal), 2 (high).
 */
class TasksTest extends EasTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->webtop->addFolder('tasks', '10', 'Tasks', ['deflt' => true]);
		$this->webtop->addItem('tasks', '10', '1', [
			'subject' => 'Call back',
			'start' => '20260714T070000Z',
			'due' => '20260720T080000Z',
			'status' => 'CO',
			'complOn' => '20260719T100000Z',
			'impo' => 1,
			'prvt' => true,
			'notes' => 'Ask for the quote'
		]);
		$this->webtop->addItem('tasks', '10', '2', [
			'subject' => 'Renew domain',
			'due' => '20260901T080000Z',
			'status' => 'NA',
			'impo' => 5,
			'prvt' => false
		]);
	}

	public function testGetCompletedTask() {
		$task = $this->logon(\BackendWTTasks::class)->GetMessage('10', '1', self::contentParameters());

		$this->assertSame('Call back', $task->subject);
		$this->assertSame('2026-07-14 07:00:00', gmdate('Y-m-d H:i:s', $task->utcstartdate));
		$this->assertSame('2026-07-20 08:00:00', gmdate('Y-m-d H:i:s', $task->utcduedate));
		$this->assertSame('1', $task->complete);
		$this->assertSame('2026-07-19 10:00:00', gmdate('Y-m-d H:i:s', $task->datecompleted));
		$this->assertSame('2', $task->importance, 'high');
		$this->assertSame('2', $task->sensitivity, 'private');
		$this->assertSame('Ask for the quote', self::readBody($task->asbody));
	}

	public function testGetOpenTask() {
		$task = $this->logon(\BackendWTTasks::class)->GetMessage('10', '2', self::contentParameters());

		$this->assertSame('0', $task->complete);
		$this->assertNull($task->datecompleted);
		$this->assertSame('1', $task->importance, 'normal');
		$this->assertSame('0', $task->sensitivity);
	}

	public function testCompleteTaskOnPhone() {
		$task = new \SyncTask();
		$task->subject = 'Renew domain';
		$task->utcduedate = gmmktime(8, 0, 0, 9, 1, 2026);
		$task->complete = '1';
		$task->datecompleted = gmmktime(9, 30, 0, 8, 28, 2026);
		$task->importance = '0';
		$task->sensitivity = '2';

		$this->logon(\BackendWTTasks::class)->ChangeMessage('10', '2', $task, self::contentParameters());

		$stored = $this->webtop->item('tasks', '10', '2')['data'];
		$this->assertSame('20260828T093000Z', $stored['complOn']);
		$this->assertSame('20260901T080000Z', $stored['due']);
		$this->assertSame(9, $stored['impo'], 'low');
		$this->assertTrue($stored['prvt']);
	}

	public function testCreateHighImportanceTask() {
		$task = new \SyncTask();
		$task->subject = 'Pay invoice';
		$task->complete = '0';
		$task->importance = '2';
		$task->asbody = self::plainBody('Invoice 42');

		$stat = $this->logon(\BackendWTTasks::class)->ChangeMessage('10', '', $task, self::contentParameters());

		$stored = $this->webtop->item('tasks', '10', $stat['id'])['data'];
		$this->assertSame('Pay invoice', $stored['subject']);
		$this->assertSame(1, $stored['impo'], 'high');
		$this->assertSame('Invoice 42', $stored['notes']);
		$this->assertArrayNotHasKey('complOn', $stored);
	}
}
