<?php

use lf4php\LoggerFactory;
use WT\EAS\Config;
use WT\EAS\ZPUtil;

class BackendTasks extends AbstractWebTopBackendDiff {

	protected function getTasksApiConfig() {
		$config = Config::get();
		$obj = new \WT\Client\Tasks\Configuration();
		$obj->setUserAgent(constant('WEBTOP-EAS-SERVER_NAME').'/'.constant('WEBTOP-EAS-SERVER_VERSION').'/php');
		$obj->setUsername($this->currentUsername);
		$obj->setPassword($this->currentPassword);
		$obj->setHost($config->getWTApiBaseURL().$config->getTasksApiUrlPath());
		return $obj;
	}
	
	protected function getUserTZName() {
		return is_null($this->currentUserInfo) ? null : $this->currentUserInfo->getTimezoneId();
	}

	protected function getLogger() {
		return LoggerFactory::getLogger(__CLASS__);
	}
	
	protected function doGetApiFolders() {
		$logger = $this->getLogger();
		
		try {
			$api = new \WT\Client\Tasks\Api\EasFoldersApi(null, $this->getTasksApiConfig());
			$logger->debug('[REST] --> getFolders()');
			$items = $api->getFolders();
			$map = [];
			for ($i = 0; $i < count($items); $i++) {
				if ($logger->isDebugEnabled()) $logger->debug('[REST] ... [{}]'.PHP_EOL.'{}', [$i, $items[$i]]);
				$map[$items[$i]->getId()] = $items[$i];
			}
			return $map;

		} catch (\WT\Client\Tasks\ApiException $ex) {
			$logger->error($ex);
			return null;
		}
	}
	
	protected function doGetApiFolderMessages($folderId, $isoCutoffDate) {
		$logger = $this->getLogger();
		
		try {
			$api = new \WT\Client\Tasks\Api\EasMessagesApi(null, $this->getTasksApiConfig());
			$logger->debug('[REST] --> getMessagesStats({})', [$folderId]);
			$items = $api->getMessagesStats($folderId, $isoCutoffDate);
			$map = [];
			$logger->debug('Returned {} items', [count($items)]);
			for ($i = 0; $i < count($items); $i++) {
				if ($logger->isDebugEnabled()) $logger->debug('[REST] ... [{}]'.PHP_EOL.'{}', [$i, $items[$i]]);
				$map[$items[$i]->getId()] = $items[$i];
			}
			return $map;

		} catch (\WT\Client\Tasks\ApiException $ex) {
			$logger->error($ex);
			return null;
		}
	}
	
	public function GetMessage($folderid, $id, $contentparameters) {
		$logger = $this->getLogger();
		$logger->debug('{}({}, {})', [__METHOD__, $folderid, $id, $contentparameters]);
		$foId = $this->decodeFolderId($folderid);
		$truncsize = Utils::GetTruncSize($contentparameters->GetTruncation());
		//$mimesupport = $contentparameters->GetMimeSupport();
		//$bodypreference = $contentparameters->GetBodyPreference(); /* fmbiete's contribution r1528, ZP-320 */
		
		try {
			$api = new \WT\Client\Tasks\Api\EasMessagesApi(null, $this->getTasksApiConfig());
			$logger->debug('[REST] --> getMessage({}, {})', [$foId, $id]);
			$item = $api->getMessage($foId, $id);
			if ($logger->isDebugEnabled()) $logger->debug('[REST] ...'.PHP_EOL.'{}', [$item]);
			
			return is_null($item) ? false : $this->toZPSyncTask($item, $truncsize);

		} catch (\WT\Client\Tasks\ApiException $ex) {
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
			
			$body = $this->toApiSyncTaskUpdate($message);
			$api = new \WT\Client\Tasks\Api\EasMessagesApi(null, $this->getTasksApiConfig());
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

		} catch (\WT\Client\Tasks\ApiException $ex) {
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
			
			$api = new \WT\Client\Tasks\Api\EasMessagesApi(null, $this->getTasksApiConfig());
			$logger->debug('[REST] --> deleteMessage({}, {})', [$foId, $id]);
			$api->deleteMessage($foId, $id);
			$this->updateApiSyncFolderMessage($foId, $id, null); // Update cached stat info!
			
			return true;

		} catch (\WT\Client\Tasks\ApiException $ex) {
			$logger->error($ex);
			if ($ex->getCode() === 404) {
				throw new StatusException(sprintf("DeleteMessage(): message %s not found", $id), SYNC_STATUS_OBJECTNOTFOUND);
			} else {
				return false;
			}
		}
	}
	
	protected function toZPStatFolder($item) { // Due to inheritance, we need to omit param type!
	//protected function toZPStatFolder(\WT\Client\Tasks\Model\SyncFolder $item) {
		return $this->createZPStatFolder(
			$this->encodeFolderId(strval($item->getId())),
			'0',
			$item->getDisplayName()
		);
	}
	
	protected function toZPSyncFolder($item) { // Due to inheritance, we need to omit param type!
	//protected function toZPSyncFolder(\WT\Client\Tasks\Model\SyncFolder $item) {
		$obj = new SyncFolder();
		$obj->serverid = $this->encodeFolderId(strval($item->getId()));
		$obj->parentid = '0';
		//$obj->parentid = $item->getOwnerUsername();
		$obj->displayname = $item->getDisplayName();
		$obj->type = SYNC_FOLDER_TYPE_USER_TASK;
		return $obj;
	}
	
	protected function toZPStatMessage(\WT\Client\Tasks\Model\SyncTaskStat $item) {
		$obj = [
			'id' => strval($item->getId()),
			'mod' => $item->getEtag(),
			'flags' => 1
		];
		return $obj;
	}
	
	protected function toZPSyncTask(\WT\Client\Tasks\Model\SyncTask $item, $bodyMaxSize = -1) {
		$obj = new SyncTask();
		
		$obj->subject = $item->getSubject();
		$obj->utcstartdate = ZPUtil::parseISODateTime($item->getStart());
		$obj->utcduedate = ZPUtil::parseISODateTime($item->getDue());
		$obj->complete = $this->toZPSyncTaskComplete($item->getStatus());
		if ($obj->complete) {
			$date = null;
			if (!empty($item->getComplOn())) $date = ZPUtil::parseISODateTime($item->getComplOn());
			if (is_null($date)) $date = ZPUtil::parseISODateTime($item->getDue());
			$obj->datecompleted = $date;
		}
		$obj->importance = $this->toZPSyncTaskImportance($item->getImpo());
		$obj->sensitivity = $this->toZPSyncTaskSensitivity($item->getPrvt());
		if (!empty($item->getNotes())) {
			if (Request::GetProtocolVersion() >= 12.0) {
				$obj->asbody = ZPUtil::noteToMessageBody($item->getNotes(), $bodyMaxSize);
			} else {
				$ret = ZPUtil::noteToMessageBodyLT12($item->getNotes(), $bodyMaxSize);
				if (!is_null($ret)) {
					$obj->body = $ret['body'];
					//$obj->bodytruncated = $ret['bodytruncated']; // Not supported here!
					//$obj->bodysize = $ret['bodysize']; // Not supported here!
				}
			}
		}
		
		return $obj;
	}
	
	protected function toApiSyncTaskUpdate(SyncTask $sync) {
		$obj = new \WT\Client\Tasks\Model\SyncTaskUpdate();
		$logger = $this->getLogger();
		
		if (isset($sync->subject)) {
			$obj->setSubject($sync->subject);
		}
		if (isset($sync->startdate)) {
			$obj->setStart(ZPUtil::toISODateTime($sync->startdate));
		}
		if (isset($sync->duedate)) {
			$obj->setDue(ZPUtil::toISODateTime($sync->duedate));
		}
		if (isset($sync->complete) && ($sync->complete === '1')) {
			if (isset($sync->datecompleted)) {
				$obj->setComplOn(ZPUtil::toISODateTime($sync->datecompleted));
			}
		}
		$obj->setImpo($this->toApiSyncTaskImportance($sync->importance));
		$obj->setPrvt($this->toApiSyncTaskPrivate($sync->sensitivity));
		/* TODO: evaluate to add or not RTF support!!!
		if (isset($data->rtf)) {
            $rtfparser = new rtf();
            $rtfparser->loadrtf(base64_decode($data->rtf));
            $rtfparser->output("ascii");
            $rtfparser->parse();
            $vevent->AddProperty("DESCRIPTION", $rtfparser->out);
        } 
		*/
		if (isset($sync->body) || isset($sync->asbody->data)) {
			if (Request::GetProtocolVersion() >= 12.0) {
				$obj->setNotes(ZPUtil::messageBodyToNote($sync->asbody));
			} else {
				$obj->setNotes(ZPUtil::messageBodyToNoteLT12($sync->body, null, null));
			}
		}
		
		return $obj;
	}
	
	protected function toZPSyncTaskComplete($status) {
		return ($status === 'CO') ? '1' : '0';
	}
	
	protected function toZPSyncTaskImportance($impo) {
		// AS Sensitivity values:
		//  0 -> Low
		//  1 -> Normal
		//  2 -> High
		if ($impo >= 1 && $impo < 5) {
			return '2';
		} else if ($impo > 5 && $impo <= 9) {
			return '0';
		} else {
			return '1';
		}
	}
	
	protected function toApiSyncTaskImportance($asImpo) {
		// API importance values:
		//  1..4 -> High
		//  5    -> Normal/Medium
		//  6..9 -> Low
		if (isset($asImpo)) {
			if (intval($asImpo) === 0) {
				return 9;
			} else if (intval($asImpo) === 2) {
				return 1;
			}
		}
		return 5;
	}
	
	protected function toZPSyncTaskSensitivity($prvt) {
		// AS Sensitivity values
		//  0 -> Normal
		//  1 -> Personal
		//  2 -> Private
		//  3 -> Confident
		return ($prvt === true) ? '2' : '0';
	}
	
	protected function toApiSyncTaskPrivate($asSensitivity) {
		return (isset($asSensitivity) && ($asSensitivity === '2')) ? true : false;
	}
}
