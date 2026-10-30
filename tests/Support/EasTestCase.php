<?php

namespace WT\Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\TestCase;

/**
 * Calls our backends as Z-Push does during a request, against a FakeWebTop.
 *
 * As in production, every test gets new backend instances: nothing cached
 * by them survives from one test (request) to the next.
 */
abstract class EasTestCase extends TestCase {

	const USER = 'u1@wt.test';
	const PASSWORD = 'secret';

	/** @var FakeWebTop */
	protected $webtop;

	protected function setUp(): void {
		$this->webtop = new FakeWebTop();
		$this->webtop->addUser(self::USER, self::PASSWORD, 'User One', 'u1@example.com');
		\AbstractWTBackendDiff::setHttpClient(new Client(['handler' => HandlerStack::create($this->webtop)]));
		self::setRequest('iPhone', '14.1');
	}

	protected function tearDown(): void {
		\AbstractWTBackendDiff::setHttpClient(null);
	}

	/**
	 * Sets what Z-Push would have read from the request of the phone.
	 */
	protected static function setRequest($deviceType, $protocolVersion) {
		$request = new \ReflectionClass(\Request::class);
		$request->setStaticPropertyValue('devtype', $deviceType);
		$request->setStaticPropertyValue('asProtocolVersion', $protocolVersion);
	}

	/**
	 * Returns a new backend of the passed class, logged in as the test user.
	 */
	protected function logon($backendClass) {
		$backend = new $backendClass();
		$this->assertTrue($backend->Logon(self::USER, '', self::PASSWORD), 'Logon failed');
		return $backend;
	}

	protected static function setProperty($object, $name, $value) {
		$property = new \ReflectionProperty($object, $name);
		$property->setValue($object, $value);
	}

	protected static function contentParameters() {
		$cp = new \ContentParameters();
		$cp->SetTruncation(SYNC_TRUNCATION_ALL);
		return $cp;
	}

	protected static function plainBody($text) {
		$body = new \SyncBaseBody();
		$body->type = SYNC_BODYPREFERENCE_PLAIN;
		$body->data = \StringStreamWrapper::Open($text);
		return $body;
	}

	protected static function readBody(?\SyncBaseBody $body) {
		return is_null($body) ? null : stream_get_contents($body->data);
	}

	/**
	 * Body of the last request matching method and path regex, decoded from JSON.
	 */
	protected function lastJsonSent($method, $pathRegex) {
		$requests = $this->webtop->requestsTo($pathRegex, $method);
		$this->assertNotEmpty($requests, "No $method request to $pathRegex");
		return json_decode(end($requests)['body'], true);
	}
}
