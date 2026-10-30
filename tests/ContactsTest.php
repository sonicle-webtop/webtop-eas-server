<?php

namespace WT\Tests;

use WT\Tests\Support\EasTestCase;

/**
 * Contacts between the phone (SyncContact) and WebTop.
 */
class ContactsTest extends EasTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->webtop->addFolder('contacts', '10', 'Contacts', ['deflt' => true]);
		$this->webtop->addFolder('contacts', '20', '[User Two] Shared', ['elAcl' => 'r']);
		$this->webtop->addItem('contacts', '10', '1', [
			'firstName' => 'Mario',
			'lastName' => 'Rossi',
			'email1' => 'mario@example.com',
			'mobile' => '+39 333 1234567',
			'companyName' => 'ACME',
			'workCity' => 'Milano',
			'birthday' => '20000229',
			'notes' => 'Met at the fair'
		]);
	}

	public function testGetContact() {
		$contact = $this->logon(\BackendWTContacts::class)->GetMessage('10', '1', self::contentParameters());

		$this->assertSame('Mario', $contact->firstname);
		$this->assertSame('Rossi', $contact->lastname);
		$this->assertSame('mario@example.com', $contact->email1address);
		$this->assertSame('+39 333 1234567', $contact->mobilephonenumber);
		$this->assertSame('ACME', $contact->companyname);
		$this->assertSame('Milano', $contact->businesscity);
		$this->assertSame('2000-02-29', gmdate('Y-m-d', $contact->birthday));
		$this->assertSame('Met at the fair', self::readBody($contact->asbody));
	}

	public function testGetContactWithProtocol25() {
		self::setRequest('iPhone', '2.5');
		$contact = $this->logon(\BackendWTContacts::class)->GetMessage('10', '1', self::contentParameters());

		$this->assertSame('Met at the fair', $contact->body);
		$this->assertNull($contact->asbody);
	}

	public function testGetMissingContact() {
		$this->assertFalse($this->logon(\BackendWTContacts::class)->GetMessage('10', '99', self::contentParameters()));
	}

	public function testCreateContact() {
		$contact = new \SyncContact();
		$contact->firstname = 'Anna';
		$contact->lastname = 'Bianchi';
		$contact->mobilephonenumber = '+39 347 7654321';
		$contact->birthday = gmmktime(0, 0, 0, 3, 8, 1990);
		$contact->asbody = self::plainBody('Neighbour');

		$stat = $this->logon(\BackendWTContacts::class)->ChangeMessage('10', '', $contact, self::contentParameters());

		$stored = $this->webtop->item('contacts', '10', $stat['id']);
		$this->assertSame($stored['etag'], $stat['mod']);
		$this->assertSame('Anna', $stored['data']['firstName']);
		$this->assertSame('Bianchi', $stored['data']['lastName']);
		$this->assertSame('+39 347 7654321', $stored['data']['mobile']);
		$this->assertSame('19900308', $stored['data']['birthday']);
		$this->assertSame('Neighbour', $stored['data']['notes']);
	}

	public function testEmailWithDisplayNameIsStoredAsAddress() {
		// iOS sends the address with the name the contact had
		$contact = new \SyncContact();
		$contact->lastname = 'Verdi';
		$contact->email1address = '"Luca Verdi" <luca@example.com>';

		$stat = $this->logon(\BackendWTContacts::class)->ChangeMessage('10', '', $contact, self::contentParameters());

		$this->assertSame('luca@example.com', $this->webtop->item('contacts', '10', $stat['id'])['data']['email1']);
	}

	public function testUpdateContact() {
		$contact = new \SyncContact();
		$contact->firstname = 'Mario';
		$contact->lastname = 'Rossi Jr';
		$oldEtag = $this->webtop->item('contacts', '10', '1')['etag'];

		$stat = $this->logon(\BackendWTContacts::class)->ChangeMessage('10', '1', $contact, self::contentParameters());

		$this->assertSame('Rossi Jr', $this->webtop->item('contacts', '10', '1')['data']['lastName']);
		$this->assertNotSame($oldEtag, $stat['mod']);
	}

	public function testReadOnlyFolderRefusesChanges() {
		$contact = new \SyncContact();
		$contact->lastname = 'Verdi';
		$backend = $this->logon(\BackendWTContacts::class);

		$this->assertFalse($backend->ChangeMessage('20', '', $contact, self::contentParameters()));
		$this->assertFalse($backend->DeleteMessage('20', '1', self::contentParameters()));
		$this->assertSame([], $this->webtop->requestsTo('#/folders/20/messages#', 'POST'));
		$this->assertSame([], $this->webtop->requestsTo('#/folders/20/messages#', 'DELETE'));
	}

	public function testUpdateOfContactDeletedInWebTop() {
		$contact = new \SyncContact();
		$contact->lastname = 'Gone';

		try {
			$this->logon(\BackendWTContacts::class)->ChangeMessage('10', '99', $contact, self::contentParameters());
			$this->fail('StatusException expected');
		} catch (\StatusException $ex) {
			$this->assertSame(SYNC_STATUS_OBJECTNOTFOUND, $ex->getCode());
		}
	}

	public function testUpdateRefusedByWebTop() {
		$this->webtop->failWith('PUT', '#/messages/1$#', 500);
		$contact = new \SyncContact();
		$contact->lastname = 'Rossi';

		try {
			$this->logon(\BackendWTContacts::class)->ChangeMessage('10', '1', $contact, self::contentParameters());
			$this->fail('StatusException expected');
		} catch (\StatusException $ex) {
			$this->assertSame(SYNC_STATUS_CLIENTSERVERCONVERSATIONERROR, $ex->getCode());
		}
	}

	public function testDeleteContact() {
		$backend = $this->logon(\BackendWTContacts::class);

		$this->assertTrue($backend->DeleteMessage('10', '1', self::contentParameters()));
		$this->assertNull($this->webtop->item('contacts', '10', '1'));
		$this->assertFalse($backend->StatMessage('10', '1'));
	}

	public function testDeleteOfContactAlreadyDeletedInWebTop() {
		try {
			$this->logon(\BackendWTContacts::class)->DeleteMessage('10', '99', self::contentParameters());
			$this->fail('StatusException expected');
		} catch (\StatusException $ex) {
			$this->assertSame(SYNC_STATUS_OBJECTNOTFOUND, $ex->getCode());
		}
	}
}
