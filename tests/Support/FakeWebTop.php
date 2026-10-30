<?php

namespace WT\Tests\Support;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * WebTop EAS REST API in memory, as a Guzzle handler: users and, for each
 * service (contacts, calendar, tasks), folders with their items. Every change
 * gives the item and its folder a new etag, as WebTop does (the folder etag is
 * what the changes sink watches).
 *
 * Tests set the initial state with the add* methods, call the backends and then
 * check the state (what WebTop would have stored) and $requests (what was asked).
 */
class FakeWebTop {

	const CORE_PATH = '/webtop/api/com.sonicle.webtop.core/v1';
	const SERVICE_PATHS = [
		'contacts' => '/webtop/api/com.sonicle.webtop.contacts/v2/eas/folders',
		'calendar' => '/webtop/api/com.sonicle.webtop.calendar/v2/eas/folders',
		'tasks' => '/webtop/api/com.sonicle.webtop.tasks/v2/eas/folders'
	];
	// Etags are timestamps (yyyyMMddHHmmssSSS): one second more at every change
	const ETAG_BASE = 1767225600; // 2026-01-01T00:00:00Z

	public $users = [];
	// service => folder id => folder (see addFolder); items are keyed by id
	public $folders = ['contacts' => [], 'calendar' => [], 'tasks' => []];
	// Every request received: ['method', 'path', 'query', 'body']
	public $requests = [];
	private $failures = [];
	private $changes = 0;
	private $nextId = 1000;

	public function addUser($username, $password, $displayName = null, $email = null, $timezone = 'Europe/Rome', $canSync = true) {
		$this->users[$username] = ['password' => $password, 'displayName' => $displayName, 'email' => $email,
			'timezone' => $timezone, 'canSync' => $canSync];
	}

	/**
	 * @param array $options deflt (bool), foAcl, elAcl, ownerId
	 */
	public function addFolder($service, $id, $displayName, array $options = []) {
		$this->folders[$service][$id] = array_merge([
			'id' => $id,
			'displayName' => $displayName,
			'deflt' => false,
			'foAcl' => 'lrud',
			'elAcl' => 'crud',
			'ownerId' => 'wt.test!u1',
			'etag' => $this->nextEtag(),
			'items' => []
		], $options);
	}

	public function addItem($service, $folderId, $id, array $data) {
		$this->putItem($this->folders[$service][$folderId], $id, $data);
	}

	public function item($service, $folderId, $id) {
		return $this->folders[$service][$folderId]['items'][$id] ?? null;
	}

	/**
	 * Next requests matching method and path regex get the passed HTTP status,
	 * with WebTop's error body, as long as the failure stays registered.
	 */
	public function failWith($method, $pathRegex, $status) {
		$this->failures[] = [$method, $pathRegex, $status];
	}

	public function clearFailures() {
		$this->failures = [];
	}

	/**
	 * Requests received whose path matches the regex (and the method, if passed).
	 */
	public function requestsTo($pathRegex, $method = null) {
		return array_values(array_filter($this->requests, function($r) use ($pathRegex, $method) {
			return preg_match($pathRegex, $r['path']) && (is_null($method) || $r['method'] === $method);
		}));
	}

	public function __invoke(RequestInterface $request, array $options) {
		$method = $request->getMethod();
		$path = rawurldecode($request->getUri()->getPath());
		$query = self::parseQuery($request->getUri()->getQuery());
		$body = (string)$request->getBody();
		$this->requests[] = ['method' => $method, 'path' => $path, 'query' => $query, 'body' => $body];

		return new FulfilledPromise($this->handle($request, $method, $path, $query, $body));
	}

	private function handle(RequestInterface $request, $method, $path, $query, $body) {
		$user = $this->authenticate($request);
		if (is_null($user)) return self::error(401, 'Unauthorized');

		foreach ($this->failures as $f) {
			if ($f[0] === $method && preg_match($f[1], $path)) return self::error($f[2], 'Injected failure');
		}

		if (preg_match('#^'.self::CORE_PATH.'/principals/([^/]+)$#', $path, $m) && $method === 'GET') {
			if ($m[1] !== $user) return self::error(403, 'Forbidden');
			$u = $this->users[$user];
			return self::json(200, [
				'profileId' => str_replace('@', '!', $user),
				'profileUsername' => $user,
				'displayName' => $u['displayName'],
				'emailAddress' => $u['email'],
				'timezoneId' => $u['timezone'],
				'languageTag' => 'it_IT',
				'evalPermRefs' => array_map(function() use ($u) { return $u['canSync']; }, $query['permRefs'] ?? [])
			]);
		}
		foreach (self::SERVICE_PATHS as $service => $servicePath) {
			if (strpos($path, $servicePath) === 0) {
				return $this->handleService($service, substr($path, strlen($servicePath)), $method, $body);
			}
		}
		return self::error(404, 'No such endpoint');
	}

	private function handleService($service, $subPath, $method, $body) {
		if ($subPath === '' && $method === 'GET') {
			return self::json(200, array_values(array_map(function($f) {
				$json = $f;
				unset($json['items']);
				return $json;
			}, $this->folders[$service])));
		}
		if (!preg_match('#^/([^/]+)/(messages-stats|messages)(?:/([^/]+))?$#', $subPath, $m)) return self::error(404, 'No such endpoint');
		if (!isset($this->folders[$service][$m[1]])) return self::error(404, 'Folder not found');
		$folder =& $this->folders[$service][$m[1]];
		$id = $m[3] ?? null;

		if ($m[2] === 'messages-stats' && $method === 'GET') {
			return self::json(200, array_values(array_map([$this, 'statJson'], $folder['items'])));
		}
		if ($m[2] === 'messages' && is_null($id) && $method === 'POST') {
			$id = (string)$this->nextId++;
			$this->putItem($folder, $id, self::itemData($service, json_decode($body, true)));
			return self::json(200, $this->statJson($folder['items'][$id]));
		}
		if ($m[2] === 'messages' && !is_null($id)) {
			if (!isset($folder['items'][$id])) return self::error(404, 'Item not found');
			if ($method === 'GET') {
				$item = $folder['items'][$id];
				return self::json(200, array_merge($item['data'], ['id' => $id, 'etag' => $item['etag']]));
			}
			if ($method === 'PUT') {
				$this->putItem($folder, $id, self::itemData($service, json_decode($body, true)));
				$stat = $this->statJson($folder['items'][$id]);
				// Calendar answers the stats of the event and of the exceptions it created
				return self::json(200, ($service === 'calendar') ? [$stat] : $stat);
			}
			if ($method === 'DELETE') {
				unset($folder['items'][$id]);
				$folder['etag'] = $this->nextEtag();
				return new Response(204);
			}
		}
		return self::error(405, 'Method not allowed');
	}

	/**
	 * The stored data of a written item: calendar sends the event under "data".
	 */
	private static function itemData($service, array $json) {
		return ($service === 'calendar') ? ($json['data'] ?? []) : $json;
	}

	private function putItem(array &$folder, $id, array $data) {
		$folder['items'][$id] = ['id' => $id, 'etag' => $this->nextEtag(), 'data' => $data];
		$folder['etag'] = $folder['items'][$id]['etag'];
	}

	private function nextEtag() {
		return gmdate('YmdHis', self::ETAG_BASE + $this->changes++).'000';
	}

	private function statJson(array $item) {
		return ['id' => $item['id'], 'etag' => $item['etag']];
	}

	private function authenticate(RequestInterface $request) {
		$header = $request->getHeaderLine('Authorization');
		if (stripos($header, 'Basic ') !== 0) return null;
		$parts = explode(':', base64_decode(substr($header, 6)), 2);
		if (count($parts) !== 2 || !isset($this->users[$parts[0]])) return null;
		return $this->users[$parts[0]]['password'] === $parts[1] ? $parts[0] : null;
	}

	/**
	 * Query string as name => list of values: repeated names (a=1&a=2) are kept.
	 */
	private static function parseQuery($query) {
		$result = [];
		if ($query === '') return $result;
		foreach (explode('&', $query) as $pair) {
			$kv = explode('=', $pair, 2);
			$result[urldecode($kv[0])][] = urldecode($kv[1] ?? '');
		}
		return $result;
	}

	private static function json($status, $data) {
		return new Response($status, ['Content-Type' => 'application/json'], json_encode($data));
	}

	private static function error($status, $message) {
		return self::json($status, ['code' => $status, 'description' => $message]);
	}
}
