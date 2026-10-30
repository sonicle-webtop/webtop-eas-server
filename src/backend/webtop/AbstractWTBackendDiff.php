<?php

use WT\EAS\Config;
use WT\EAS\ZPUtil;

abstract class AbstractWTBackendDiff extends BackendDiff {
	const PERMREF_DEVICES_SYNC = 'com.sonicle.webtop.core/DEVICES_SYNC/ACCESS';
	// Minimum seconds between two WebTop checks of the changes sink
	const SINK_MIN_CHECK_INTERVAL = 5;
	protected $deviceFamily;
	//protected $currentDomain;
	protected $currentUsername;
	protected $currentPassword;
	protected $currentUserInfo;
	protected $foldersCache;
	protected $folderMessagesCache;
	protected $legacyFolderIdsCache;
	protected $useLegacyFolderIds;
	protected $forceLegacyFolderTypes;
	private $sinkFolders;
	private $sinkStates;
	private $sinkLastCheck = 0;

	public function __construct() {
		$this->foldersCache = [];
		$this->folderMessagesCache = [];
		$this->legacyFolderIdsCache = [];
		$this->useLegacyFolderIds = Config::get()->getUseLegacyFolderIds();
		$this->forceLegacyFolderTypes = Config::get()->getForceLegacyFolderTypes();
		$this->sinkFolders = [];
		$this->sinkStates = [];
	}
	
	public function GetUserDetails($username) {
		$logger = $this->getLogger();
		$logger->debug('{}({})', [__METHOD__, $username]);
		
		if (isset($this->currentUserInfo)) {
			return array('emailaddress' => $this->currentUserInfo->getEmailAddress(), 'fullname' => $this->currentUserInfo->getDisplayName());
		} else {
			return array('emailaddress' => $username, 'fullname' => $username);
		}
	}
	
	protected function getWTApiConfig($username = null, $password = null) {
		$easConfig = Config::get();
		$config = new \WT\Client\Core\Configuration();
		$config->setUserAgent(constant('WEBTOP-EAS-SERVER_NAME').'/'.constant('WEBTOP-EAS-SERVER_VERSION').'/php');
		$config->setUsername(!is_null($username) ? $username : $this->currentUsername);
		$config->setPassword(!is_null($password) ? $password : $this->currentPassword);
		$config->setHost($easConfig->getWTApiBaseURL().$easConfig->getWTApiUrlPath());
		return $config;
	}
	
	protected function encodeFolderId($id) {
		$logger = $this->getLogger();
		$logger->trace('{}({})', [__METHOD__, $id]);
		
		if ($this->useLegacyFolderIds === true) {
			$folder = $this->getApiSyncFolder($id);
			if (!is_null($folder)) {
				$entry = $this->buildLegacyFolderIdCacheEntry($folder);
				if (!is_null($entry)) {
					if ($logger->isTraceEnabled()) $logger->trace('{} -> {}', [$id, $entry['key']]);
					return $entry['key'];
				}
			}
			throw new FatalException(sprintf("AbstractWebTopBackendDiff(): Unable to encode folder '%s'", $id), 0, null, LOGLEVEL_FATAL);
			
		} else {
			return $id;
		}
	}
	
	protected function decodeFolderId($id) {
		$logger = $this->getLogger();
		$logger->trace('{}({})', [__METHOD__, $id]);
		
		if ($this->useLegacyFolderIds === true) {
			$logger = $this->getLogger();
			if (empty($this->legacyFolderIdsCache)) {
				$logger->debug('Building folder decoding cache');
				$folders = $this->getApiSyncFolders();
				if (!is_null($folders)) {
					foreach ($folders as $folder) {
						$entry = $this->buildLegacyFolderIdCacheEntry($folder);
						if (!is_null($entry)) {
							$key = $entry['key'];
							if (array_key_exists($key, $this->legacyFolderIdsCache)) {
								$logger->warn('Duplicated decoding key for "{}", already associated to "{}"', [$key, $entry['value']]);
							} else {
								$this->legacyFolderIdsCache[$key] = $entry['value'];
							}
						}
					}
				}
			}
			
			if (array_key_exists($id, $this->legacyFolderIdsCache)) {
				if ($logger->isTraceEnabled()) $logger->trace('{} -> {}', [$id, $this->legacyFolderIdsCache[$id]]);
				return $this->legacyFolderIdsCache[$id];
			} else {
				throw new FatalException(sprintf("AbstractWebTopBackendDiff(): Unable to decode folder '%s'", $id), 0, null, LOGLEVEL_FATAL);
			}
			
		} else {
			return $id;
		}
	}
	
	protected function buildLegacyFolderIdCacheEntry($item) {
		//$share->ownerDisplayName . " / " . $share->calendarName . " [" . $share->calendarId . "]";
		$key = $item->getDisplayName();
		if ($item->getOwnerId() !== $this->currentUserInfo->getProfileId()) {
			preg_match('/^\[(.+)\] (.+)$/', $item->getDisplayName(), $matches);
			if (empty($matches)) return null;
			$key = $matches[1] . ' / ' . $matches[2] . ' [' . strval($item->getId()) . ']';
		}
		
		return [
			'key' => $key,
			'value' => strval($item->getId())
		];
	}

	public function GetSupportedASVersion() {
		//return ZPush::ASV_14;
		return ZPush::ASV_141;
	}
	
	/**
	 * Authenticates the user
	 *
	 * @param string        $username
	 * @param string        $domain
	 * @param string        $password
	 *
	 * @access public
	 * @return boolean
	 * @throws FatalException   e.g. some required libraries are unavailable
	 */
	public function Logon($username, $domain, $password) {
		$logger = $this->getLogger();
		
		$this->deviceFamily = ZPUtil::getDeviceFamily();
		try {
			$api = new \WT\Client\Core\Api\PrincipalsApi(null, $this->getWTApiConfig($username, $password));
			$item = $api->getPrincipalInfo($username, [self::PERMREF_DEVICES_SYNC]);
			//$this->currentDomain = $domain;
			$this->currentUsername = $username;
			$this->currentPassword = $password;
			$this->currentUserInfo = $item;
			
			if ($item->getEvalPermRefs()[0] === true) {
				return true;
			} else {
				$logger->info('Required permission not satisfied [{}, {}]', [$username, self::PERMREF_DEVICES_SYNC]);
				return false;
			}
			
		} catch (\WT\Client\Core\ApiException $ex) {
			// NB: return false ONLY when the user is not found or credentials are incorrect!
			// This behaviour should avoid the prompt for new/updated credentials
			// on the phone. So, the default action is throwing an exception.
			if (($ex->getCode() === 401) || ($ex->getCode() === 404)) {
				return false;
			} else if ($ex->getCode() === 503) {
				throw new ZPushException("Service unavailable at the moment: backend is maybe in maintenance!");
			} else {
				throw new FatalException(sprintf("AbstractWebTopBackendDiff(): %s", $ex->getMessage()), 0, null, LOGLEVEL_FATAL);
			}
		}
	}
	
	/**
	 * Logs off
	 * non critical operations closing the session should be done here
	 *
	 * @access public
	 * @return boolean
	 */
	public function Logoff() {
		unset($this->deviceFamily);
		//unset($this->currentDomain);
		unset($this->currentUser);
		unset($this->currentPassword);
		unset($this->currentUserInfo);
		return true;
	}

	/**
	 * Sends an e-mail
	 * This messages needs to be saved into the 'sent items' folder
	 *
	 * Basically two things can be done
	 *      1) Send the message to an SMTP server as-is
	 *      2) Parse the message, and send it some other way
	 *
	 * @param SyncSendMail        $sm         SyncSendMail object
	 *
	 * @access public
	 * @return boolean
	 * @throws StatusException
	 */
	public function SendMail($sm) {
		// Not implemented!
		return false;
	}

	/**
	 * Returns the waste basket
	 *
	 * The waste basked is used when deleting items; if this function returns a valid folder ID,
	 * then all deletes are handled as moves and are sent to the backend as a move.
	 * If it returns FALSE, then deletes are handled as real deletes
	 *
	 * @access public
	 * @return string
	 */
	public function GetWasteBasket() {
		// Not implemented!
		return false;
	}
	
	/**
	 * Returns the content of the named attachment as stream. The passed attachment identifier is
	 * the exact string that is returned in the 'AttName' property of an SyncAttachment.
	 * Any information necessary to locate the attachment must be encoded in that 'attname' property.
	 * Data is written directly - 'print $data;'
	 *
	 * @param string        $attname
	 *
	 * @access public
	 * @return SyncItemOperationsAttachment
	 * @throws StatusException
	 */
	public function GetAttachmentData($attname) {
		// Not implemented!
		return false;
	}
	
	public function HasChangesSink() {
		return true;
	}
	
	public function ChangesSinkInitialize($folderid) {
		$logger = $this->getLogger();
		$logger->debug('{}({})', [__METHOD__, $folderid]);

		// $folderid is the id Z-Push knows (encoded, with legacy folder ids): keep
		// it for the notifications, and the WebTop id for checking the changes
		try {
			$foId = $this->decodeFolderId($folderid);
		} catch (FatalException $ex) {
			// A legacy id of a folder renamed or no longer shared: Z-Push will
			// then ask the phone to sync its folder list again
			$logger->debug('Unknown folder: {}', [$ex->getMessage()]);
			return false;
		}
		// Remember the folder state now: any change made from here on wakes the phone
		$folder = $this->getApiSyncFolder($foId);
		$this->sinkFolders[$folderid] = $foId;
		$this->sinkStates[$folderid] = is_null($folder) ? null : $folder->getEtag();
		if ($logger->isTraceEnabled()) $logger->trace('Initializing changes sink... [{}, {}]', [$foId, $this->sinkStates[$folderid]]);
		return true;
	}

	public function ChangesSink($timeout = 30) {
		$logger = $this->getLogger();
		if ($logger->isTraceEnabled()) $logger->trace('{}({})', [__METHOD__, $timeout]);

		$notifications = [];
		if (empty($this->sinkFolders)) return $notifications;

		// Z-Push 2.7 BackendCombined calls this with a 1 second timeout every
		// PING_INTERVAL and waits by itself: then a single check is done
		$stopat = time() + $timeout - 1;
		while (true) {
			$notifications = $this->checkSinkFoldersChanges();
			if (!empty($notifications) || (time() >= $stopat)) break;
			$sleepTime = min($stopat - time(), 30);
			if ($logger->isTraceEnabled()) $logger->trace('({}) no changes, going to sleep ({})', [$timeout, $sleepTime]);
			sleep($sleepTime);
		}

		if (!empty($notifications)) {
			$logger->debug('({}) returning notifications: [{}]', [$timeout, implode(' ', $notifications)]);
		} else if ($logger->isTraceEnabled()) {
			$logger->trace('({}) no notifications', [$timeout]);
		}
		return $notifications;
	}

	/**
	 * Compares the watched folders with their current state in WebTop.
	 *
	 * @return array Ids (as known by Z-Push) of the folders that changed.
	 */
	private function checkSinkFoldersChanges() {
		$logger = $this->getLogger();

		$notifications = [];
		// When less than a second is left before the end of the heartbeat, Z-Push
		// calls the sink back-to-back (BackendCombined does not wait with a 1
		// second timeout): do not ask WebTop again so soon
		if ((time() - $this->sinkLastCheck) < self::SINK_MIN_CHECK_INTERVAL) {
			if ($logger->isTraceEnabled()) $logger->trace('Last check {}s ago, skipping', [time() - $this->sinkLastCheck]);
			return $notifications;
		}
		$this->sinkLastCheck = time();
		try {
			// Fresh states: the folders cache was filled when the request started
			$folders = $this->doGetApiFolders(true);
		} catch (\Exception $ex) {
			// WebTop unreachable or answering errors (eg. while restarting):
			// do not wake the phone, keep waiting
			$logger->warn('Unable to check folders for changes: {}', [$ex->getMessage()]);
			return $notifications;
		}
		if (is_null($folders)) return $notifications;

		$this->foldersCache = $folders;
		foreach ($this->sinkFolders as $folderid => $foId) {
			$newState = array_key_exists($foId, $folders) ? $folders[$foId]->getEtag() : null;
			if ($logger->isTraceEnabled()) $logger->trace('Checking changes... [{}, {} =? {}]', [$foId, $this->sinkStates[$folderid], $newState]);
			if ($this->sinkStates[$folderid] !== $newState) {
				if ($logger->isTraceEnabled()) $logger->trace('Changes for folder [{}]: {} -> {}', [$foId, $this->sinkStates[$folderid], $newState]);
				$notifications[] = $folderid;
				$this->sinkStates[$folderid] = $newState;
				// The message list cached before the change would hide it
				unset($this->folderMessagesCache[$foId]);
			}
		}
		return $notifications;
	}
	
	public function GetFolderList() {
		$logger = $this->getLogger();
		$logger->debug('{}()', [__METHOD__]);
		
		$folders = $this->getApiSyncFolders();
		if (is_null($folders)) return false;
		
		$arr = [];
		foreach ($folders as $folder) {
			$arr[] = $this->toZPStatFolder($folder);
		}
		return $arr;
	}
	
	public function GetFolder($id) {
		$logger = $this->getLogger();
		$logger->debug('{}({})', [__METHOD__, $id]);
		$foId = $this->decodeFolderId($id);
		
		$folder = $this->getApiSyncFolder($foId);
		return is_null($folder) ? false : $this->toZPSyncFolder($folder);	
	}
	
	public function StatFolder($id) {
		$logger = $this->getLogger();
		$logger->debug('{}({})', [__METHOD__, $id]);
		$foId = $this->decodeFolderId($id);
		
		$sf = $this->GetFolder($id);
		if ($sf === false) return false;
		
		return $this->createZPStatFolder($id, $sf->parentid, $sf->displayname);
	}
	
	public function ChangeFolder($folderid, $oldid, $displayname, $type) {
		$logger = $this->getLogger();
		$logger->debug("{} not supported/implemented", [__METHOD__]);
		return false;
	}
	
	public function DeleteFolder($id, $parentid) {
		$logger = $this->getLogger();
		$logger->debug("{} not supported/implemented", [__METHOD__]);
		return false;
	}
	
	public function SetReadFlag($folderid, $id, $flags, $contentParameters) {
		$logger = $this->getLogger();
		$logger->debug("{} not supported/implemented", [__METHOD__]);
		return false;
	}
	
	// Resolves the effective cutoff date used to fetch the folder message list.
	// The base implementation honors the cutoff provided by Z-Push (correct for
	// collections without a date window, e.g. contacts). Subclasses whose state must
	// stay consistent between the SyncKey=0 setup (no FilterType yet) and the later
	// FilterType-based syncs can override this to apply a fixed cutoff.
	protected function resolveSyncCutoffDate($cutoffdate) {
		return $cutoffdate;
	}
	
	public function GetMessageList($folderid, $cutoffdate) {
		$logger = $this->getLogger();
		$logger->debug('{}({})', [__METHOD__, $folderid]);
		$foId = $this->decodeFolderId($folderid);
		
		$effectiveCutoff = $this->resolveSyncCutoffDate($cutoffdate);
		$folderMessages = $this->getApiSyncFolderMessages($foId, $effectiveCutoff);
		if (is_null($folderMessages)) return false;
		
		$logger->debug('Returned {} messages', [count($folderMessages)]);
		$arr = [];
		foreach ($folderMessages as $id => $stat) {
			$arr[] = $this->toZPStatMessage($stat);
		}
		return $arr;
	}
	
	protected abstract function getLogger();
	// $throw: pass WebTop REST errors on, instead of logging them (used by the changes sink)
	protected abstract function doGetApiFolders($throw = false);
	protected abstract function doGetApiFolderMessages($folderId, $isoCutoffDate);
	protected abstract function toZPStatFolder($folder);
	protected abstract function toZPSyncFolder($folder);
	
	protected function getApiSyncFolders() {
		$logger = $this->getLogger();
		
		if (empty($this->foldersCache)) {
			$logger->debug('Building folders cache');
			$map = $this->doGetApiFolders();
			if (!is_null($map)) {
				foreach ($map as $id => $fold) {
					$this->foldersCache[$id] = $fold;
				}
			}
		}
		return $this->foldersCache;
	}
	
	protected function getApiSyncFolder($id) {
		$folders = $this->getApiSyncFolders();
		if (!is_null($folders) && array_key_exists($id, $folders)) {
			return $folders[$id];
		} else {
			return null;
		}
	}
	
	protected function getApiSyncFolderMessages($folderId, $cutoffDate) {
		$logger = $this->getLogger();
		
		$isoCutoff = (isset($cutoffDate) && !is_null($cutoffDate)) ? gmdate("Ymd\THis\Z", $cutoffDate) : null;
		$cacheKey = $folderId;
		if (!array_key_exists($cacheKey, $this->folderMessagesCache)) {
			$map = $this->doGetApiFolderMessages($folderId, $isoCutoff);
			if (!is_null($map)) {
				$logger->debug('Building folder-messages cache for [{}, {}] as "{}"', [$folderId, $isoCutoff, $cacheKey]);
				$this->folderMessagesCache[$cacheKey] = $map;
			} else {
				return null;
			}
		} else {
			$logger->debug('Reading folder-messages cache at "{}"', [$cacheKey]);
		}
		return $this->folderMessagesCache[$cacheKey];
	}
	
	protected function getApiSyncFolderMessage($folderId, $id) {
		$logger = $this->getLogger();
		
		$folderMessages = $this->getApiSyncFolderMessages($folderId, null);
		if (is_null($folderMessages)) return null;
		
		if (array_key_exists($id, $folderMessages)) {
			return $folderMessages[$id];
		} else {
			$logger->trace('Message not found in cache [{}, {}]', [$folderId, $id]);
			return null;
		}
	}
	
	protected function updateApiSyncFolderMessage($folderId, $id, $stat = null) {
		$logger = $this->getLogger();
		
		if (array_key_exists($folderId, $this->folderMessagesCache)) {
			if (is_null($stat)) {
				if (array_key_exists($id, $this->folderMessagesCache[$folderId])) {
					$logger->trace('Deleting message from cache [{}, {}]', [$folderId, $id]);
					unset($this->folderMessagesCache[$folderId][$id]);
				}
			} else {
				$logger->trace('Inserting/Updating message in cache [{}, {}]', [$folderId, $id]);
				$this->folderMessagesCache[$folderId][$id] = $stat;
			}	
		}
	}
	
	protected function createZPStatFolder($id, $parentId, $mod) {
		$obj = [
			'id' => $id,
			'parent' => $parentId,
			'mod' => $mod
		];
		return $obj;
	}
	
	protected function checkFolderElementsPermission($folderId, $perm) {
		$folder = $this->getApiSyncFolder($folderId);
		return is_null($folder) ? false : $this->checkPermission($folder->getElAcl(), $perm);
	}
	
	protected function checkPermission($acl, $perm) {
		return strpos($acl, $perm) !== false;
	}
}
