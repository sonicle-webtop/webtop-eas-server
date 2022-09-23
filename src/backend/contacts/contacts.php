<?php

use lf4php\LoggerFactory;
use WT\EAS\Config;
use WT\EAS\ZPUtil;

class BackendContacts extends AbstractWebTopBackendDiff {
	
	static public $MESSAGE_MAPPINGS = [
		'title' => 'title',
		'firstname' => 'firstName',
		'lastname' => 'lastName',
		'nickname' => 'nickname',
		'mobilephonenumber' => 'mobile',
		'pagernumber' => 'pager1',
		'radiophonenumber' => 'pager2',
		'email1address' => 'email1',
		'email2address' => 'email2',
		'email3address' => 'email3',
		'imaddress' => 'im1',
		'imaddress2' => 'im2',
		'imaddress3' => 'im3',
		'businessstreet' => 'workAddress',
		'businesspostalcode' => 'workPostalCode',
		'businesscity' => 'workCity',
		'businessstate' => 'workState',
		'businesscountry' => 'workCountry',
		'businessphonenumber' => 'workTelephone1',
		'business2phonenumber' => 'workTelephone2',
		'businessfaxnumber' => 'workFax',
		'homestreet' => 'homeAddress',
		'homepostalcode' => 'homePostalCode',
		'homecity' => 'homeCity',
		'homestate' => 'homeState',
		'homecountry' => 'homeCountry',
		'homephonenumber' => 'homeTelephone1',
		'home2phonenumber' => 'homeTelephone2',
		'homefaxnumber' => 'homeFax',
		'otherstreet' => 'otherAddress',
		'otherpostalcode' => 'otherPostalCode',
		'othercity' => 'otherCity',
		'otherstate' => 'otherState',
		'othercountry' => 'otherCountry',
		'customerid' => 'companyId',
		'companyname' => 'companyName',
		'jobtitle' => 'function',
		'department' => 'department',
		'assistantname' => 'assistant',
		'assistnamephonenumber' => 'assistantTelephone',
		'managername' => 'manager',
		'spouse' => 'partner',
		'birthday' => 'birthday',
		'anniversary' => 'anniversary',
		'webpage' => 'url',
		'body'	=> 'notes',
		'picture' => 'picture',
	];

	protected function getContactsApiConfig() {
		$config = Config::get();
		$obj = new \WT\Client\Contacts\Configuration();
		$obj->setUserAgent(constant('WEBTOP-EAS-SERVER_NAME').'/'.constant('WEBTOP-EAS-SERVER_VERSION').'/php');
		$obj->setUsername($this->currentUsername);
		$obj->setPassword($this->currentPassword);
		$obj->setHost($config->getWTApiBaseURL().$config->getContactsApiUrlPath());
		return $obj;
	}
	
	protected function getLogger() {
		return LoggerFactory::getLogger(__CLASS__);
	}
	
	/*
	protected function getApiSyncFolderMessages($folderId, $isoCutoffDate) {
		$logger = $this->getLogger();
		$logger->debug('{} IGNORING CUOFFDATEEEEEEEEEEEEEEEE', [__METHOD__]);
		return parent::getApiSyncFolderMessages($folderId, false);
	}
	*/
	
	protected function doGetApiFolders() {
		$logger = $this->getLogger();
		
		try {
			$api = new \WT\Client\Contacts\Api\EasFoldersApi(null, $this->getContactsApiConfig());
			$logger->debug('[REST] --> getFolders()');
			$items = $api->getFolders();
			$map = [];
			for ($i = 0; $i < count($items); $i++) {
				if ($logger->isDebugEnabled()) $logger->debug('[REST] ... [{}]'.PHP_EOL.'{}', [$i, $items[$i]]);
				$map[$items[$i]->getId()] = $items[$i];
			}
			return $map;

		} catch (\WT\Client\Contacts\ApiException $ex) {
			$logger->error($ex);
			return null;
		}
	}
	
	protected function doGetApiFolderMessages($folderId, $isoCutoffDate) {
		$logger = $this->getLogger();
		
		try {
			$api = new \WT\Client\Contacts\Api\EasMessagesApi(null, $this->getContactsApiConfig());
			$logger->debug('[REST] --> getMessagesStats({})', [$folderId]);
			$items = $api->getMessagesStats($folderId);
			$map = [];
			$logger->debug('Returned {} items', [count($items)]);
			for ($i = 0; $i < count($items); $i++) {
				if ($logger->isDebugEnabled()) $logger->debug('[REST] ... [{}]'.PHP_EOL.'{}', [$i, $items[$i]]);
				$map[$items[$i]->getId()] = $items[$i];
			}
			return $map;

		} catch (\WT\Client\Contacts\ApiException $ex) {
			$logger->error($ex);
			return null;
		}
	}
	
	public function GetMessage($folderid, $id, $contentparameters) {
		$logger = $this->getLogger();
		$logger->debug('{}({}, {})', [__METHOD__, $folderid, $id, $contentparameters]);
		$foId = $this->decodeFolderId($folderid);
		$truncsize = Utils::GetTruncSize($contentparameters->GetTruncation());
		
		try {
			$api = new \WT\Client\Contacts\Api\EasMessagesApi(null, $this->getContactsApiConfig());
			$logger->debug('[REST] --> getMessage({}, {})', [$foId, $id]);
			$item = $api->getMessage($foId, $id);
			if ($logger->isDebugEnabled()) $logger->debug('[REST] ...'.PHP_EOL.'{}', [$item]);
			
			return is_null($item) ? false : $this->toZPSyncContact($item, $truncsize);

		} catch (\WT\Client\Contacts\ApiException $ex) {
			$logger->error($ex);
			return false;
		}
	}
	
	public function StatMessage($folderid, $id) {
		$logger = $this->getLogger();
		$logger->debug('{}({}, {})', [__METHOD__, $folderid, $id]);
		$foId = $this->decodeFolderId($folderid);
		
		$stat = $this->getApiSyncFolderMessage($foId, $id);
		return is_null($stat) ? false : $this->toZPStatMessage($stat);
	}

	public function ChangeMessage($folderid, $id, $message, $contentParameters) {
		$logger = $this->getLogger();
		$logger->debug('{}({}, {})', [__METHOD__, $folderid, $id]);
		$foId = $this->decodeFolderId($folderid);
		
		try {
			if (!$this->checkFolderElementsPermission($foId, empty($id) ? 'c' : 'u')) {
				$logger->debug('Folder {} is not writable', [$foId]);
				return false;
			}
			
			$body = $this->toApiSyncContactUpdate($message);
			$api = new \WT\Client\Contacts\Api\EasMessagesApi(null, $this->getContactsApiConfig());
			if (empty($id)) {
				$logger->debug('[REST] --> addMessage({})', [$foId]);
				$stat = $api->addMessage($foId, $body);
				
			} else {
				$logger->debug('[REST] --> updateMessage({}, {})', [$foId, $id]);
				$stat = $api->updateMessage($foId, $id, $body);
			}
			if ($logger->isDebugEnabled()) $logger->debug('[REST] ...'.PHP_EOL.'{}', [$stat]);
			$this->updateApiSyncFolderMessage($foId, $id, $stat); // Update cached stat info!
			
			return $this->toZPStatMessage($stat);

		} catch (\WT\Client\Contacts\ApiException $ex) {
			$logger->error($ex);
			if ($ex->getCode() === 404) {
				throw new StatusException(sprintf("ChangeMessage(): message %s not found", $id), SYNC_STATUS_OBJECTNOTFOUND);
			} else {
				throw new StatusException(sprintf("ChangeMessage(): unable to save message %s", $id), SYNC_STATUS_CLIENTSERVERCONVERSATIONERROR);
			}
		}
	}

	public function MoveMessage($folderid, $id, $newfolderid, $contentParameters) {
		$logger = $this->getLogger();
		$logger->debug("{} not supported/implemented", [__METHOD__]);
		return false;
	}
	
	public function DeleteMessage($folderid, $id, $contentParameters) {
		$logger = $this->getLogger();
		$logger->debug('{}({}, {})', [__METHOD__, $folderid, $id]);
		$foId = $this->decodeFolderId($folderid);
		
		try {
			if (!$this->checkFolderElementsPermission($foId, 'd')) {
				$logger->debug('Folder {} is not writable', [$foId]);
				return false;
			}
			
			$api = new \WT\Client\Contacts\Api\EasMessagesApi(null, $this->getContactsApiConfig());
			$logger->debug('[REST] --> deleteMessage({}, {})', [$foId, $id]);
			$api->deleteMessage($foId, $id);
			$this->updateApiSyncFolderMessage($foId, $id, null); // Update cached stat info!
			
			return true;

		} catch (\WT\Client\Contacts\ApiException $ex) {
			$logger->error($ex);
			if ($ex->getCode() === 404) {
				throw new StatusException(sprintf("DeleteMessage(): message %s not found", $id), SYNC_STATUS_OBJECTNOTFOUND);
			} else {
				return false;
			}
		}
	}
	
	protected function toZPStatFolder($item) { // Due to inheritance, we need to omit param type!
	//protected function toZPStatFolder(\WT\Client\Contacts\Model\SyncFolder $item) {
		return $this->createZPStatFolder(
			$this->encodeFolderId(strval($item->getId())),
			'0',
			$item->getDisplayName()
		);
	}
	
	protected function toZPSyncFolder($item) { // Due to inheritance, we need to omit param type!
	//protected function toZPSyncFolder(\WT\Client\Contacts\Model\SyncFolder $item) {
		$obj = new SyncFolder();
		$obj->serverid = $this->encodeFolderId(strval($item->getId()));
		$obj->parentid = '0';
		//$obj->parentid = $item->getOwnerUsername();
		$obj->displayname = $item->getDisplayName();
		$obj->type = SYNC_FOLDER_TYPE_USER_CONTACT;
		return $obj;
	}
	
	protected function toZPStatMessage(\WT\Client\Contacts\Model\SyncContactStat $item) {
		$obj = [
			'id' => strval($item->getId()),
			'mod' => $item->getEtag(),
			'flags' => 1
		];
		return $obj;
	}
	
	protected function toZPSyncContact(\WT\Client\Contacts\Model\SyncContact $item, $bodyMaxSize = -1) {
		$logger = $this->getLogger();
		$obj = new SyncContact();
		
		foreach (self::$MESSAGE_MAPPINGS as $zpName => $wtName) {
			switch ($wtName) {
				case 'notes':
					if (!empty($item->offsetGet($wtName))) {
						if (Request::GetProtocolVersion() >= 12.0) {
							$obj->asbody = ZPUtil::noteToMessageBody($item->offsetGet($wtName), $bodyMaxSize);
						} else {
							$ret = ZPUtil::noteToMessageBodyLT12($item->offsetGet($wtName), $bodyMaxSize);
							if (!is_null($ret)) {
								$obj->body = $ret['body'];
								$obj->bodytruncated = $ret['bodytruncated'];
								$obj->bodysize = $ret['bodysize'];
							}
						}
					}
					break;
				case 'picture':
					if (!empty($item->offsetGet($wtName))) {
						if ($logger->isDebugEnabled()) $logger->debug('Unpacking picture Data URI...');
						$pic = ZPUtil::dataUriToPicture(DataURI\Parser::parse($item->offsetGet($wtName)));
						if (!is_null($pic)) {
							if ($logger->isDebugEnabled()) $logger->debug('Unpacked: {} - {} bytes', [$pic['mediaType'], strlen($pic['data'])]);
							//https://github.com/b1gMail/zpush-b1gmail/blob/zpush-22/b1gmail.php#L1597
							//if (strlen($pic['data']) <= 49152) { // Do not attach too big images to avoid Z-Push dropping the entire contact
								$obj->$zpName = $pic['data'];
							//}
						}
					}
					break;
				case 'birthday':
				case 'anniversary':
					$v = $item->offsetGet($wtName);
					if (!empty($v)) $obj->$zpName = ZPUtil::parseISODate($v);
					//if (!empty($v)) $obj->$zpName = $this->stringToMessageDate($v);
					break;
				
				default:
					$v = $item->offsetGet($wtName);
					if (!empty($v)) $obj->$zpName = $v;
			}
		}
		
		return $obj;
	}
	
	protected function toApiSyncContactUpdate(SyncContact $item) {
		$logger = $this->getLogger();
		$obj = new \WT\Client\Contacts\Model\SyncContactUpdate();
		
		foreach (self::$MESSAGE_MAPPINGS as $zpName => $wtName) {
			switch ($wtName) {
				case 'notes':
					if (Request::GetProtocolVersion() >= 12.0) {
						$obj->offsetSet($wtName, ZPUtil::messageBodyToNote($item->asbody));
					} else {
						$obj->offsetSet($wtName, ZPUtil::messageBodyToNoteLT12($item->body, $item->bodytruncated, $item->bodysize));
					}
					break;
				case 'picture':
					$pic = null;
					if (!empty($item->$zpName)) {
						if ($logger->isDebugEnabled()) $logger->debug('Packing picture into Data URI...');
						//https://github.com/b1gMail/zpush-b1gmail/blob/zpush-22/b1gmail.php#L1998
						$dataUri = ZPUtil::pictureToDataUri($item->$zpName);
						if (!is_null($dataUri)) {
							if ($logger->isDebugEnabled()) $logger->debug('Packed: {} - {} bytes', [$dataUri->getMimeType(), strlen($dataUri->getData())]);
							$pic = DataURI\Dumper::dump($dataUri);
						}
					}
					$obj->offsetSet($wtName, $pic);
					break;
				case 'birthday':
				case 'anniversary':
					$obj->offsetSet($wtName, ZPUtil::toISODate($item->$zpName));
					//$obj->offsetSet($wtName, $this->messageDateToString($item->$zpName));
					break;
				
				case 'email1':
				case 'email2':
				case 'email3':
					$ia = ZPUtil::parseInternetAddress($item->$zpName);
					if ($ia !== false) {
						$obj->offsetSet($wtName, $ia['address']);
					} else {
						$obj->offsetSet($wtName, $item->$zpName);
					}
				
				default:
					$obj->offsetSet($wtName, $item->$zpName);
			}
		}
		
		return $obj;
	}
	
	private function messageDateToString($date) {
		return !empty($date) ? date('Ymd', $date) : null;
	}
	
	private function stringToMessageDate($sdate) {
		if (empty($sdate)) return null;
		$tz = date_default_timezone_get();
		date_default_timezone_set('UTC');
		$date = strtotime($sdate);
		date_default_timezone_set($tz);
		return $date;
	}
}
