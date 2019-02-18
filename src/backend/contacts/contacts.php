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
		//'picture' => 'picture',
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
		$truncsize = Utils::GetTruncSize($contentparameters->GetTruncation());
		
		try {
			$api = new \WT\Client\Contacts\Api\EasMessagesApi(null, $this->getContactsApiConfig());
			$logger->debug('[REST] --> getMessage({}, {})', [$folderid, $id]);
			$item = $api->getMessage($folderid, $id);
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
		
		$stat = $this->getApiSyncFolderMessage($folderid, $id);
		return is_null($stat) ? false : $this->toZPStatMessage($stat);
	}

	public function ChangeMessage($folderid, $id, $message, $contentParameters) {
		$logger = $this->getLogger();
		$logger->debug('{}({}, {})', [__METHOD__, $folderid, $id]);
		
		try {
			if (!$this->checkFolderElementsPermission($folderid, empty($id) ? 'c' : 'u')) {
				$logger->debug('Folder {} is not writable', [$folderid]);
				return false;
			}
			
			$body = $this->toApiSyncContactUpdate($message);
			$api = new \WT\Client\Contacts\Api\EasMessagesApi(null, $this->getContactsApiConfig());
			if (empty($id)) {
				$logger->debug('[REST] --> addMessage({})', [$folderid]);
				$stat = $api->addMessage($folderid, $body);
				
			} else {
				$logger->debug('[REST] --> updateMessage({}, {})', [$folderid, $id]);
				$stat = $api->updateMessage($folderid, $id, $body);
			}
			if ($logger->isDebugEnabled()) $logger->debug('[REST] ...'.PHP_EOL.'{}', [$stat]);
			$this->updateApiSyncFolderMessage($folderid, $id, $stat); // Update cached stat info!
			
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
		
		try {
			if (!$this->checkFolderElementsPermission($folderid, 'd')) {
				$logger->debug('Folder {} is not writable', [$folderid]);
				return false;
			}
			
			$api = new \WT\Client\Contacts\Api\EasMessagesApi(null, $this->getContactsApiConfig());
			$logger->debug('[REST] --> deleteMessage({}, {})', [$folderid, $id]);
			$api->deleteMessage($folderid, $id);
			$this->updateApiSyncFolderMessage($folderid, $id, null); // Update cached stat info!
			
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
			strval($item->getId()),
			'0',
			$item->getDisplayName()
		);
	}
	
	protected function toZPSyncFolder($item) { // Due to inheritance, we need to omit param type!
	//protected function toZPSyncFolder(\WT\Client\Contacts\Model\SyncFolder $item) {
		$obj = new SyncFolder();
		$obj->serverid = strval($item->getId());
		$obj->parentid = '0';
		//$obj->parentid = $item->getOwnerUsername();
		$obj->displayname = $item->getDisplayName();
		$obj->type = SYNC_FOLDER_TYPE_CONTACT;
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
				/*	
				//TODO: add support to pictures
				case 'picture':
					//https://github.com/b1gMail/zpush-b1gmail/blob/zpush-22/b1gmail.php
					if (!empty($item->offsetGet($wtName))) {
						$dataUri = null;
						//if (DataUri::tryParse($item->offsetGet($wtName), $dataUri) === true) {
						//	$dataUri
						//}
					}
					break;
				*/
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
				/*
				//TODO: add support to pictures
				case 'picture':
					if (!empty($item->$zpName)) {
						$dataUri = DataUri::image($item->$zpName, true);
						$obj->offsetSet($wtName, $dataUri->toString());
					}
					break;
				*/
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
