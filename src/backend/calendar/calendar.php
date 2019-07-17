<?php

use lf4php\LoggerFactory;
use WT\EAS\Config;
use WT\EAS\ZPUtil;

class BackendCalendar extends AbstractWebTopBackendDiff {
	
	protected function getCalendarApiConfig() {
		$config = Config::get();
		$obj = new \WT\Client\Calendar\Configuration();
		$obj->setUserAgent(constant('WEBTOP-EAS-SERVER_NAME').'/'.constant('WEBTOP-EAS-SERVER_VERSION').'/php');
		$obj->setUsername($this->currentUsername);
		$obj->setPassword($this->currentPassword);
		$obj->setHost($config->getWTApiBaseURL().$config->getCalendarApiUrlPath());
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
			$api = new \WT\Client\Calendar\Api\EasFoldersApi(null, $this->getCalendarApiConfig());
			$logger->debug('[REST] --> getFolders()');
			$items = $api->getFolders();
			$map = [];
			for ($i = 0; $i < count($items); $i++) {
				if ($logger->isDebugEnabled()) $logger->debug('[REST] ... [{}]'.PHP_EOL.'{}', [$i, $items[$i]]);
				$map[$items[$i]->getId()] = $items[$i];
			}
			return $map;

		} catch (\WT\Client\Calendar\ApiException $ex) {
			$logger->error($ex);
			return null;
		}
	}
	
	protected function doGetApiFolderMessages($folderId, $isoCutoffDate) {
		$logger = $this->getLogger();
		
		try {
			$api = new \WT\Client\Calendar\Api\EasMessagesApi(null, $this->getCalendarApiConfig());
			$logger->debug('[REST] --> getMessagesStats({})', [$folderId]);
			$items = $api->getMessagesStats($folderId, $isoCutoffDate);
			$map = [];
			$logger->debug('Returned {} items', [count($items)]);
			for ($i = 0; $i < count($items); $i++) {
				if ($logger->isDebugEnabled()) $logger->debug('[REST] ... [{}]'.PHP_EOL.'{}', [$i, $items[$i]]);
				$map[$items[$i]->getId()] = $items[$i];
			}
			return $map;

		} catch (\WT\Client\Calendar\ApiException $ex) {
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
			$api = new \WT\Client\Calendar\Api\EasMessagesApi(null, $this->getCalendarApiConfig());
			$logger->debug('[REST] --> getMessage({}, {})', [$foId, $id]);
			$item = $api->getMessage($foId, $id);
			if ($logger->isDebugEnabled()) $logger->debug('[REST] ...'.PHP_EOL.'{}', [$item]);
			
			return is_null($item) ? false : $this->toZPSyncAppointment($item, $truncsize);

		} catch (\WT\Client\Calendar\ApiException $ex) {
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
			
			$api = new \WT\Client\Calendar\Api\EasMessagesApi(null, $this->getCalendarApiConfig());
			if (empty($id)) {
				$body = $this->toApiSyncEventUpdate($this->toApiSyncEventData($message)); // New messages do not have event (broken) exceptions
				$logger->debug('[REST] --> addMessage({})', [$foId]);
				$stat = $api->addMessage($foId, $body);
				if ($logger->isDebugEnabled()) $logger->debug('[REST] ...'.PHP_EOL.'{}', [$stat]);
				$this->updateApiSyncFolderMessage($foId, $id, $stat); // Update cached stat info!
				
				return $this->toZPStatMessage($stat);
				
			} else {
				$exceptions = $this->toApiSyncEventDataExceptions($message);
				$body = $this->toApiSyncEventUpdate($this->toApiSyncEventData($message), $exceptions);
				$logger->debug('[REST] --> updateMessage({}, {})', [$foId, $id]);
				$stats = $api->updateMessage($foId, $id, $body);
				for ($i = 0; $i < count($stats); $i++) {
					if ($logger->isDebugEnabled()) $logger->debug('[REST] ... [{}]'.PHP_EOL.'{}', [$i, $stats[$i]]);
					$this->updateApiSyncFolderMessage($foId, $stats[$i]->getId(), $stats[$i]); // Update cached stat info!
				}
				
				if (count($exceptions) > 0) {
					$logger->debug("Exception created... resync event!!!");
					//SYNC_STATUS_CONFLICTCLIENTSERVEROBJECT
					//SYNC_STATUS_SYNCCANNOTBECOMPLETED
					throw new StatusException("Exception created... resync event!!!", SYNC_STATUS_CONFLICTCLIENTSERVEROBJECT);
				}
				
				return $this->toZPStatMessage($stats[0]);
			}

		} catch (\WT\Client\Calendar\ApiException $ex) {
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
			
			$api = new \WT\Client\Calendar\Api\EasMessagesApi(null, $this->getCalendarApiConfig());
			$logger->debug('[REST] --> deleteMessage({}, {})', [$foId, $id]);
			$api->deleteMessage($foId, $id);
			$this->updateApiSyncFolderMessage($foId, $id, null); // Update cached stat info!
			
			return true;

		} catch (\WT\Client\Calendar\ApiException $ex) {
			$logger->error($ex);
			if ($ex->getCode() === 404) {
				throw new StatusException(sprintf("DeleteMessage(): message %s not found", $id), SYNC_STATUS_OBJECTNOTFOUND);
			} else {
				return false;
			}
		}
	}
	
	protected function toZPStatFolder($item) { // Due to inheritance, we need to omit param type!
	//protected function toZPStatFolder(\WT\Client\Calendar\Model\SyncFolder $item) {
		return $this->createZPStatFolder(
			$this->encodeFolderId(strval($item->getId())),
			'0',
			$item->getDisplayName()
		);
	}
	
	protected function toZPSyncFolder($item) { // Due to inheritance, we need to omit param type!
	//protected function toZPSyncFolder(\WT\Client\Calendar\Model\SyncFolder $item) {
		$obj = new SyncFolder();
		$obj->serverid = $this->encodeFolderId(strval($item->getId()));
		$obj->parentid = '0';
		//$obj->parentid = $item->getOwnerUsername();
		$obj->displayname = $item->getDisplayName();
		$obj->type = SYNC_FOLDER_TYPE_USER_APPOINTMENT;
		return $obj;
	}
	
	protected function toZPStatMessage(\WT\Client\Calendar\Model\SyncEventStat $item) {
		$obj = [
			'id' => strval($item->getId()),
			'mod' => $item->getEtag(),
			'flags' => 1
		];
		return $obj;
	}
	
	protected function toZPSyncAppointment(\WT\Client\Calendar\Model\SyncEvent $item, $bodyMaxSize = -1) {
		$logger = $this->getLogger();
		$obj = new SyncAppointment();
		
		$obj->uid = $item->getId();
		$obj->timezone = ZPUtil::tzNameToTZBlob($item->getTz());
		$obj->dtstamp = $this->etagToDate($item->getEtag());
		$obj->starttime = ZPUtil::parseISODateTime($item->getStart());
		$obj->endtime = ZPUtil::parseISODateTime($item->getEnd());
		$obj->alldayevent = $this->toZPSyncAppointmentAllDay($item->getAllDay());
		$obj->subject = $item->getTitle();
		$obj->location = $item->getLocation();
		
		/*
		 * Do not set organizer because Android's allow allow modification 
		 * only if your account email is equal to the one written into the 
		 * event's organizer field.
		 * 
		$iaOrg = ZPUtil::parseInternetAddress($item->getOrganizer());
		if ($iaOrg !== false) {
			$obj->organizername = $iaOrg['personal'];
			$obj->organizeremail = $iaOrg['address'];
		}
		*/
		
		$obj->sensitivity = $this->toZPSyncAppointmentSensitivity($item->getPrvt());
		$obj->busystatus = $this->toZPSyncAppointmentBusyStatus($item->getBusy());
		if (!empty($item->getReminder())) {
			$obj->reminder = $item->getReminder();
		}
		
		if (!empty($item->getRecRule())) {
			$obj->recurrence = ZPUtil::rruleToMessageRecurrence($item->getRecRule());
			$dates = $item->getExDates();
			if (!empty($dates)) {
				$obj->exceptions = [];
				for ($i = 0; $i < count($dates); $i++) {
					$obj->exceptions[] = $this->toZPSyncAppointmentException($dates[$i], ($item->getAllDay() === true) ? null : $item->getStart());
				}
			}
		}
		
		$obj->meetingstatus = (count($item->getAttendees()) < 1) ? 0 : 1;
		
		$atts = $item->getAttendees();
		if (!empty($atts)) {
			$obj->attendees = [];
			for ($i = 0; $i < count($atts); $i++) {
				$obj->attendees[] = $this->toZPSyncAttendee($atts[$i]);
			}
		}
		
		if (!empty($item->getDescription())) {
			if (Request::GetProtocolVersion() >= 12.0) {
				$obj->asbody = ZPUtil::noteToMessageBody($item->getDescription(), $bodyMaxSize);
			} else {
				$ret = ZPUtil::noteToMessageBodyLT12($item->getDescription(), $bodyMaxSize);
				if (!is_null($ret)) {
					$obj->body = $ret['body'];
					$obj->bodytruncated = $ret['bodytruncated'];
					//$obj->bodysize = $ret['bodysize']; // Not supported here!
				}
			}
		}
		
		/* TODO
		public $deleted;
		public $exceptionstarttime;
		public $categories;

		// AS 14.0 props
		public $disallownewtimeprop;
		public $responsetype;
		public $responserequested;

		// AS 14.1 props
		public $onlineMeetingConfLink;
		public $onlineMeetingExternalLink;
		*/
		
		return $obj;
	}
	
	protected function toZPSyncAppointmentException($exDate, $start = null) {
		$obj = new SyncAppointmentException();
		
		$obj->deleted = 1;
		if (is_null($start)) {
			$obj->exceptionstarttime = ZPUtil::parseISODate($exDate);
		} else {
			$obj->exceptionstarttime = ZPUtil::parseISODateTime($exDate . substr($start, -8));
		}
		
		return $obj;
	}
	
	protected function toZPSyncAttendee(\WT\Client\Calendar\Model\SyncEventDataAttendee $item) {
		$obj = new SyncAttendee();
		
		$ia = ZPUtil::parseInternetAddress($item->getAddress());
		if ($ia !== false) {
			$obj->name = $ia['personal'];
			$obj->email = $ia['address'];
		}
		$obj->attendeetype = $this->toZPSyncAttendeeType($item->getType(), $item->getRole());
		$obj->attendeestatus = $this->toZPSyncAttendeeStatus($item->getStatus());
		
		return $obj;
	}
	
	protected function toApiSyncEventUpdate(\WT\Client\Calendar\Model\SyncEventData $data, $exceptions = null) {
		$obj = new \WT\Client\Calendar\Model\SyncEventUpdate();
		
		$obj->setData($data);
		$obj->setExceptions($exceptions);
		
		return $obj;
	}
	
	protected function toApiSyncEventData(SyncAppointment $sync) {
		$logger = $this->getLogger();
		
		$obj = new \WT\Client\Calendar\Model\SyncEventData();
		
		if (isset($sync->timezone)) {
			$obj->setTz(ZPUtil::tzBlobToTZName($sync->timezone, $this->getUserTZName()));
		}
		if (isset($sync->starttime)) {
			$obj->setStart(ZPUtil::toISODateTime($sync->starttime));
		}
		if (isset($sync->endtime)) {
			$obj->setEnd(ZPUtil::toISODateTime($sync->endtime));
		}
		$obj->setAllDay($this->toApiSyncEventDataAllDay($sync->alldayevent));
		if (isset($sync->subject)) {
			$obj->setTitle($sync->subject);
		}
		if (isset($sync->location)) {
			$obj->setLocation($sync->location);
		}
		if (isset($sync->organizeremail)) {
			$obj->setOrganizer(ZPUtil::toInternetAddress($sync->organizeremail, $sync->organizername));
		}
		$obj->setPrvt($this->toApiSyncEventDataPrivate($sync->sensitivity));
		$obj->setBusy($this->toApiSyncEventDataBusy($sync->busystatus));
		if (isset($sync->reminder)) {
			$obj->setReminder($sync->reminder);
		}
		if (isset($sync->recurrence)) {
			$obj->setRecRule(ZPUtil::messageRecurrenceToRRule($sync->recurrence, $sync->starttime, $obj->getTz(), $this->deviceFamily));
			if (isset($sync->exceptions) && is_array($sync->exceptions)) {
				$exDates = [];
				foreach ($sync->exceptions as $exception) { // Adds every exception as excluded date
					$exDate = ZPUtil::toISODate($exception->exceptionstarttime);
					$logger->debug("Adding exception as excluded date [{}, {}]", [$exDate, (!$exception->deleted ? "broken" : "deleted")]);
					$exDates[] = $exDate;
				}
				$obj->setExDates($exDates);
			}
		}
		if (isset($sync->attendees) && is_array($sync->attendees)) {
			$atts = [];
			foreach ($sync->attendees as $att) {
				$atts[] = $this->toApiSyncEventDataAttendee($att);
			}
			$logger->debug("Added {} attendees", [count($atts)]);
			$obj->setAttendees($atts);
		}
		
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
				$obj->setDescription(ZPUtil::messageBodyToNote($sync->asbody));
			} else {
				$obj->setDescription(ZPUtil::messageBodyToNoteLT12($sync->body, $sync->bodytruncated, null));
			}
		}
		
		return $obj;
	}
	
	private function cloneSyncAppointmentDefaults(SyncAppointment $tgt, SyncAppointment $base) {
		
		if (!isset($tgt->timezone)) {
			$tgt->timezone = $base->timezone;
		}
		if (!isset($tgt->subject)) {
			$tgt->subject = $base->subject;
		}
		if (!isset($tgt->organizeremail)) {
			$tgt->organizeremail = $base->organizeremail;
		}
		if (!isset($tgt->organizername)) {
			$tgt->organizername = $base->organizername;
		}
		if (!isset($tgt->location)) {
			$tgt->location = $base->location;
		}
		if (!isset($tgt->sensitivity)) {
			$tgt->sensitivity = $base->sensitivity;
		}
		if (!isset($tgt->busystatus)) {
			$tgt->busystatus = $base->busystatus;
		}
		if (!isset($tgt->alldayevent)) {
			$tgt->alldayevent = $base->alldayevent;
		}
		if (!isset($tgt->reminder)) {
			$tgt->reminder = $base->reminder;
		}
		if (!isset($tgt->body)) {
			$tgt->body = $base->body;
		}
		
		return $tgt;
	}
	
	protected function toApiSyncEventDataExceptions(SyncAppointment $sync) {
		$exEvents = null;
		if (isset($sync->recurrence)) {
			if (isset($sync->exceptions) && is_array($sync->exceptions)) {
				foreach ($sync->exceptions as $exception) {
					if (!$exception->deleted) {
						if (is_null($exEvents)) $exEvents = [];
						$exEvents[] = $this->toApiSyncEventData($this->cloneSyncAppointmentDefaults($exception, $sync));
					}
				}
			}
		}
		return $exEvents;
	}
	
	protected function toApiSyncEventDataAttendee(SyncAttendee $sync) {
		$logger = $this->getLogger();
		$obj = new \WT\Client\Calendar\Model\SyncEventDataAttendee();
		
		if (isset($sync->email)) {
			$logger->debug("email: [{}]", [$sync->email]);
			$logger->debug("name: [{}]", [$sync->name]);
			$obj->setAddress(ZPUtil::toInternetAddress($sync->email, $sync->name));
		}
		if (isset($sync->attendeetype)) {
			$logger->debug("attendeetype: [{}]", [$sync->attendeetype]);
			$ret = $this->toApiSyncEventDataAttendeeType($sync->attendeetype);
			$obj->setType($ret['type']);
			$obj->setRole($ret['role']);
		}
		if (isset($sync->attendeestatus)) {
			$logger->debug("attendeestatus: [{}]", [$sync->attendeestatus]);
			$obj->setStatus($this->toApiSyncEventDataAttendeeStatus($sync->attendeestatus));
		}
		
		return $obj;
	}
	
	protected function toZPSyncAppointmentSensitivity($prvt) {
		// AS Sensitivity values
		// 0 = Normal
		// 1 = Personal
		// 2 = Private
		// 3 = Confident
		return ($prvt === true) ? '2' : '0';
	}
	
	protected function toApiSyncEventDataPrivate($sensitivity) {
		return (isset($sensitivity) && ($sensitivity === '2')) ? true : false;
	}
	
	protected function toZPSyncAppointmentBusyStatus($busy) {
		// AS Busystatus values
		// 0 = Free
		// 1 = Tentative
		// 2 = Busy
		// 3 = Out of office
		// 4 = Working Elsewhere
		return ($busy === true) ? '2' : '0';
	}
	
	protected function toApiSyncEventDataBusy($busystatus) {
		return (isset($busystatus) && ($busystatus === '2')) ? true : false;
	}
	
	protected function toZPSyncAppointmentAllDay($ad) {
		return ($ad === true) ? 1 : 0;
	}
	
	protected function toApiSyncEventDataAllDay($alldayevent) {
		// NB: keep equality check (==), do not test if they are identical (===)
		return (isset($alldayevent) && ($alldayevent == 1)) ? true : false;
	}
	
	protected function toZPSyncAttendeeType($type, $role) {
		// AS Type values
		// 1 = Required
		// 2 = Optional
		// 3 = Resource
		if ($type === 'RES') {
			return 3;
		} else {
			if ($role === 'OPT') {
				return 2;
			} else {
				return 1;
			}
		}
	}
	
	protected function toApiSyncEventDataAttendeeType($asType) {
		// Api Type values
		// IND = INDIVIDUAL
		// RES = RESOURCE
		// --------------
		// Api Role values
		// CHA = CHAIR
		// OPT = OPTIONAL
		// REQ = REQUIRED
		if ($asType === 3) {
			return [
				'type' => 'RES',
				'role' => 'REQ'
			];
		} else {
			if ($asType === 2) {
				return [
					'type' => 'IND',
					'role' => 'OPT'
				];
			} else {
				return [
					'type' => 'IND',
					'role' => 'REQ'
				];
			}	
		}
	}
	
	protected function toZPSyncAttendeeStatus($status) {
		// AS Status values
		// 0 = Response unknown
		// 2 = Tentative
		// 3 = Accept
		// 4 = Decline
		// 5 = Not responded
		if ($status === 'DE') {
			return 4;
		} else if ($status === 'TE') {
			return 2;
		} else if ($status === 'AC') {
			return 3;
		} else {
			return 0;
		}
	}
	
	protected function toApiSyncEventDataAttendeeStatus($asStatus) {
		// Api Status values
		// NA = NEEDS_ACTION
		// DE = DECLINED
		// TE = TENTATIVE
		// AC = ACCEPTED
		if ($asStatus === 2) {
			return 'TE';
		} else if ($asStatus === 3) {
			return 'AC';
		} else if ($asStatus === 4) {
			return 'DE';
		} else {
			return 'NA';
		}
	}
	
	protected function etagToDate($etag) {
		// yyyyMMddHHmmssSSS -> yyyyMMddTHHmmssZ
		$sdate = substr($etag, 0, 8).'T'.substr($etag, 8, 6).'Z';
		return TimezoneUtil::MakeUTCDate($sdate, 'UTC');
	}
}
