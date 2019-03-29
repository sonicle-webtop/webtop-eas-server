<?php

use WT\EAS\Config;

abstract class AbstractWebTopBackendDiff extends BackendDiff {
	const PERMREF_DEVICES_SYNC = 'com.sonicle.webtop.core/DEVICES_SYNC/ACCESS';
	//protected $currentDomain;
	protected $currentUsername;
	protected $currentPassword;
	protected $currentUserInfo;
	protected $foldersCache;
	protected $folderMessagesCache;
	private $sinkFolders;
	private $sinkStates;
	
	public function __construct() {
		$this->foldersCache = [];
		$this->folderMessagesCache = [];
		$this->sinkFolders = [];
		$this->sinkStates = [];
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
				$logger->trace('Required permission not satisfied [{}, {}]', [$username, self::PERMREF_DEVICES_SYNC]);
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
				$logger->error($ex);
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
		
		$this->sinkFolders[] = $folderid;
		return true;
	}
	
	public function ChangesSink($timeout = 30) {
		$logger = $this->getLogger();
		if ($logger->isDebugEnabled()) $logger->debug('{}({})', [__METHOD__, $timeout]);
		
		$notifications = [];
		$stopat = time() + $timeout -1;
		while ($stopat > time() && empty($notifications)) {
			$folders = $this->getApiSyncFolders();
			if (!is_null($folders)) {
				foreach ($this->sinkFolders as $folderId) {
					$newState = array_key_exists($folderId, $folders) ? $folders[$folderId]->getEtag() : null;
					if (!isset($this->sinkStates[$folderId])) {
						if ($logger->isTraceEnabled()) $logger->trace('Initializing changes sink... [{}, {}]', [$folderId, $newState]);
						$this->sinkStates[$folderId] = $newState;
					}
					if ($logger->isTraceEnabled()) $logger->trace('Checking changes... [{}, {} =? {}]', [$folderId, $this->sinkStates[$folderId], $newState]);
					if ($this->sinkStates[$folderId] !== $newState) {
						if ($logger->isTraceEnabled()) $logger->trace('Changes for folder [{}]: {} -> {}', [$folderId, $this->sinkStates[$folderId], $newState]);
						$notifications[] = $folderId;
						$this->sinkStates[$folderId] = $newState;
					}
				}
			}
			
			if (empty($notifications)) {
				$sleepTime = min($timeout, 30);
				if ($logger->isDebugEnabled()) $logger->debug('({}) no changes, going to sleep ({})', [$timeout, $sleepTime]);
				sleep($sleepTime);
			}
		}
		
		if ($logger->isDebugEnabled()) $logger->debug('({}) returning notifications: [{}]', [$timeout, implode(' ', $notifications)]);
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
		
		$folder = $this->getApiSyncFolder($id);
		return is_null($folder) ? false : $this->toZPSyncFolder($folder);	
	}
	
	public function StatFolder($id) {
		$logger = $this->getLogger();
		$logger->debug('{}({})', [__METHOD__, $id]);
		
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
	
	public function GetMessageList($folderid, $cutoffdate) {
		$logger = $this->getLogger();
		$logger->debug('{}({})', [__METHOD__, $folderid]);
		
		$folderMessages = $this->getApiSyncFolderMessages($folderid, $cutoffdate);
		if (is_null($folderMessages)) return false;
		
		$logger->debug('Returned {} messages', [count($folderMessages)]);
		$arr = [];
		foreach ($folderMessages as $id => $stat) {
			$arr[] = $this->toZPStatMessage($stat);
		}
		return $arr;
	}
	
	protected abstract function getLogger();
	protected abstract function doGetApiFolders();
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
		
		if (!array_key_exists($folderId, $this->folderMessagesCache)) {
			$isoCutoff = (isset($cutoffDate) && !is_null($cutoffDate)) ? gmdate("Ymd\THis\Z", $cutoffDate) : null;
			$logger->debug('Building cache for folder [{}, {}]', [$folderId, $isoCutoff]);
			$map = $this->doGetApiFolderMessages($folderId, $isoCutoff);
			if (!is_null($map)) {
				$this->folderMessagesCache[$folderId] = $map;
			} else {
				return null;
			}
		}
		return $this->folderMessagesCache[$folderId];
	}
	
	protected function getApiSyncFolderMessage($folderId, $id) {
		$logger = $this->getLogger();
		
		$folderMessages = $this->getApiSyncFolderMessages($folderId, null);
		if (is_null($folderMessages)) return null;
		
		if (array_key_exists($id, $folderMessages)) {
			return $folderMessages[$id];
		} else {
			$logger->debug('Message not found in cache [{}, {}]', [$folderId, $id]);
			return null;
		}
	}
	
	protected function updateApiSyncFolderMessage($folderId, $id, $stat = null) {
		$logger = $this->getLogger();
		
		if (array_key_exists($folderId, $this->folderMessagesCache)) {
			if (is_null($stat)) {
				if (array_key_exists($id, $this->folderMessagesCache[$folderId])) {
					$logger->debug('Deleting message from cache [{}, {}]', [$folderId, $id]);
					unset($this->folderMessagesCache[$folderId][$id]);
				}
			} else {
				$logger->debug('Inserting/Updating message in cache [{}, {}]', [$folderId, $id]);
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
