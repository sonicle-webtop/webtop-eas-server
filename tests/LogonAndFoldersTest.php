<?php

namespace WT\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use WT\Tests\Support\EasTestCase;

/**
 * What the three WebTop backends share: logon, folder list and types,
 * message list, changes sink (push).
 */
class LogonAndFoldersTest extends EasTestCase {

	public static function services() {
		return [
			'contacts' => [\BackendWTContacts::class, 'contacts', SYNC_FOLDER_TYPE_CONTACT, SYNC_FOLDER_TYPE_USER_CONTACT],
			'calendar' => [\BackendWTCalendar::class, 'calendar', SYNC_FOLDER_TYPE_APPOINTMENT, SYNC_FOLDER_TYPE_USER_APPOINTMENT],
			'tasks' => [\BackendWTTasks::class, 'tasks', SYNC_FOLDER_TYPE_TASK, SYNC_FOLDER_TYPE_USER_TASK],
		];
	}

	public static function backends() {
		return array_map(function($args) { return array_slice($args, 0, 2); }, self::services());
	}

	public function testLogonWithWrongPasswordFails() {
		$this->assertFalse((new \BackendWTContacts())->Logon(self::USER, '', 'wrong'));
	}

	public function testLogonWithoutDevicesSyncPermissionFails() {
		$this->webtop->addUser('u2@wt.test', 'secret', 'User Two', null, 'Europe/Rome', false);

		$this->assertFalse((new \BackendWTContacts())->Logon('u2@wt.test', '', 'secret'));
		$principal = $this->webtop->requestsTo('#/principals/u2@wt.test$#')[0];
		$this->assertSame(['com.sonicle.webtop.core/DEVICES_SYNC/ACCESS'], $principal['query']['permRefs']);
	}

	public function testLogonWhileWebTopIsInMaintenance() {
		// Not a wrong password: the phone must not ask for new credentials
		$this->webtop->failWith('GET', '#/principals/#', 503);

		$this->expectException(\ZPushException::class);
		(new \BackendWTContacts())->Logon(self::USER, '', self::PASSWORD);
	}

	public function testLogonWhenWebTopFails() {
		$this->webtop->failWith('GET', '#/principals/#', 500);

		$this->expectException(\FatalException::class);
		(new \BackendWTContacts())->Logon(self::USER, '', self::PASSWORD);
	}

	public function testUserDetails() {
		$details = $this->logon(\BackendWTContacts::class)->GetUserDetails(self::USER);

		$this->assertSame(['emailaddress' => 'u1@example.com', 'fullname' => 'User One'], $details);
	}

	#[DataProvider('backends')]
	public function testFolderList($backendClass, $service) {
		$this->webtop->addFolder($service, '10', 'Mine', ['deflt' => true]);
		$this->webtop->addFolder($service, '20', '[User Two] Shared', ['ownerId' => 'wt.test!u2', 'elAcl' => 'r']);

		$folders = $this->logon($backendClass)->GetFolderList();

		$this->assertSame([
			['id' => '10', 'parent' => '0', 'mod' => 'Mine'],
			['id' => '20', 'parent' => '0', 'mod' => '[User Two] Shared']
		], $folders);
	}

	#[DataProvider('services')]
	public function testFolderTypes($backendClass, $service, $defaultType, $userType) {
		$this->webtop->addFolder($service, '10', 'Mine', ['deflt' => true]);
		$this->webtop->addFolder($service, '11', 'Default but read-only', ['deflt' => true, 'elAcl' => 'r']);
		$this->webtop->addFolder($service, '12', 'Other', ['deflt' => false]);
		$backend = $this->logon($backendClass);

		// The phone writes new items into the default folder: only a writable one can be it
		$this->assertEquals($defaultType, $backend->GetFolder('10')->type);
		$this->assertEquals($userType, $backend->GetFolder('11')->type);
		$this->assertEquals($userType, $backend->GetFolder('12')->type);
	}

	#[DataProvider('services')]
	public function testLegacyFolderTypes($backendClass, $service, $defaultType, $userType) {
		$this->webtop->addFolder($service, '10', 'Mine', ['deflt' => true]);
		$backend = $this->logon($backendClass);
		self::setProperty($backend, 'forceLegacyFolderTypes', true);

		// As Z-Push 2.4 did: phones paired then keep their folders
		$this->assertEquals($userType, $backend->GetFolder('10')->type);
	}

	#[DataProvider('backends')]
	public function testMissingFolder($backendClass, $service) {
		$this->assertFalse($this->logon($backendClass)->GetFolder('99'));
	}

	#[DataProvider('backends')]
	public function testMessageList($backendClass, $service) {
		$this->webtop->addFolder($service, '10', 'Mine');
		$this->webtop->addItem($service, '10', '1', []);
		$this->webtop->addItem($service, '10', '2', []);

		$messages = $this->logon($backendClass)->GetMessageList('10', 0);

		$this->assertSame(['1', '2'], array_column($messages, 'id'));
		$this->assertSame([$this->webtop->item($service, '10', '1')['etag'], $this->webtop->item($service, '10', '2')['etag']], array_column($messages, 'mod'));
	}

	#[DataProvider('backends')]
	public function testChangesSinkWithoutChanges($backendClass, $service) {
		$this->webtop->addFolder($service, '10', 'Mine');
		$backend = $this->logon($backendClass);

		$this->assertTrue($backend->ChangesSinkInitialize('10'));
		$this->assertSame([], $backend->ChangesSink(1));
	}

	#[DataProvider('backends')]
	public function testChangesSinkNotifiesChangedFolder($backendClass, $service) {
		$this->webtop->addFolder($service, '10', 'Mine');
		$this->webtop->addFolder($service, '20', 'Other');
		$backend = $this->logon($backendClass);
		$backend->ChangesSinkInitialize('10');
		$backend->ChangesSinkInitialize('20');

		$this->webtop->addItem($service, '20', '1', []);

		// The id may come back as an integer: BackendCombined prefixes it with the backend id anyway
		$this->assertEquals(['20'], $backend->ChangesSink(1));
	}

	#[DataProvider('backends')]
	public function testChangesSinkKeepsWaitingWhenWebTopFails($backendClass, $service) {
		$this->webtop->addFolder($service, '10', 'Mine');
		$backend = $this->logon($backendClass);
		$backend->ChangesSinkInitialize('10');

		// eg. WebTop restarting: no error to the phone, and no wake-up either
		$this->webtop->failWith('GET', '#/eas/folders$#', 503);
		$this->assertSame([], $backend->ChangesSink(1));
	}

	#[DataProvider('backends')]
	public function testChangesSinkDoesNotAskWebTopTooOften($backendClass, $service) {
		$this->webtop->addFolder($service, '10', 'Mine');
		$backend = $this->logon($backendClass);
		$backend->ChangesSinkInitialize('10');
		$backend->ChangesSink(1);
		$checks = count($this->webtop->requestsTo('#/eas/folders$#'));

		// Called again right away, as Z-Push does near the end of the heartbeat
		$backend->ChangesSink(1);

		$this->assertCount($checks, $this->webtop->requestsTo('#/eas/folders$#'));
	}
}
