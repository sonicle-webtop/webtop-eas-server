<?php

use WT\Util;
use WT\EAS\Config;

class BackendWebTop extends BackendCombined {
	
	public static function GetBackendCombinedConfig() {
		$backends = [];
		$folderackends = [];
		$rootcreatefolderbackend = null;
		
		if (Config::get()->getMailEnabled()) {
			//Util::requireFromDir(WT_EAS_ROOT, 'backend/imap/config.php');
			require_once WT_EAS_ROOT.'/backend/imap/config.php';
			
			$backends['im'] = [
				'name' => 'BackendIMAP'
			];
			$folderackends[SYNC_FOLDER_TYPE_INBOX] = 'im';
			$folderackends[SYNC_FOLDER_TYPE_DRAFTS] = 'im';
			$folderackends[SYNC_FOLDER_TYPE_WASTEBASKET] = 'im';
			$folderackends[SYNC_FOLDER_TYPE_SENTMAIL] = 'im';
			$folderackends[SYNC_FOLDER_TYPE_OUTBOX] = 'im';
			$folderackends[SYNC_FOLDER_TYPE_OTHER] = 'im';
			$folderackends[SYNC_FOLDER_TYPE_USER_MAIL] = 'im';
			$rootcreatefolderbackend = 'im';
		}
		
		if (Config::get()->getCalendarEnabled()) {
			require_once WT_EAS_ROOT.'/backend/calendar/calendar.php';
			$backends['ca'] = [
				'name' => 'BackendCalendar'
			];
			$folderackends[SYNC_FOLDER_TYPE_APPOINTMENT] = 'ca';
			$folderackends[SYNC_FOLDER_TYPE_USER_APPOINTMENT] = 'ca';
		}
		
		if (Config::get()->getContactsEnabled()) {
			require_once WT_EAS_ROOT.'/backend/contacts/contacts.php';
			$backends['co'] = [
				'name' => 'BackendContacts'
			];
			$folderackends[SYNC_FOLDER_TYPE_CONTACT] = 'co';
			$folderackends[SYNC_FOLDER_TYPE_USER_CONTACT] = 'co';
		}
		
		if (Config::get()->getTasksEnabled()) {
			require_once WT_EAS_ROOT.'/backend/tasks/tasks.php';
			$backends['ta'] = [
				'name' => 'BackendTasks'
			];
			$folderackends[SYNC_FOLDER_TYPE_TASK] = 'ta';
			$folderackends[SYNC_FOLDER_TYPE_USER_TASK] = 'ta';
		}
		
		//use a function for it because php does not allow
		//assigning variables to the class members (expecting T_STRING)
		return [
			//the order in which the backends are loaded.
			//login only succeeds if all backend return true on login
			//sending mail: the mail is sent with first backend that is able to send the mail
			'backends' => $backends,
			'delimiter' => '/',
			//force one type of folder to one backend
			//it must match one of the above defined backends
			'folderbackend' => $folderackends,
			'rootcreatefolderbackend' => $rootcreatefolderbackend
		];
		
		/*
		return array(
			//the order in which the backends are loaded.
			//login only succeeds if all backend return true on login
			//sending mail: the mail is sent with first backend that is able to send the mail
			'backends' => array(
				'i' => array(
					'name' => 'BackendIMAP',
				),
				//'z' => array(
				//	'name' => 'BackendKopano',
				//),
				
			),
			'delimiter' => '/',
			//force one type of folder to one backend
			//it must match one of the above defined backends
			'folderbackend' => array(
				SYNC_FOLDER_TYPE_INBOX => 'i',
				SYNC_FOLDER_TYPE_DRAFTS => 'i',
				SYNC_FOLDER_TYPE_WASTEBASKET => 'i',
				SYNC_FOLDER_TYPE_SENTMAIL => 'i',
				SYNC_FOLDER_TYPE_OUTBOX => 'i',
				//SYNC_FOLDER_TYPE_TASK => 'z',
				//SYNC_FOLDER_TYPE_APPOINTMENT => 'z',
				//SYNC_FOLDER_TYPE_CONTACT => 'z',
				//SYNC_FOLDER_TYPE_NOTE => 'z',
				//SYNC_FOLDER_TYPE_JOURNAL => 'z',
				SYNC_FOLDER_TYPE_OTHER => 'i',
				SYNC_FOLDER_TYPE_USER_MAIL => 'i',
				//SYNC_FOLDER_TYPE_USER_APPOINTMENT => 'z',
				//SYNC_FOLDER_TYPE_USER_CONTACT => 'z',
				//SYNC_FOLDER_TYPE_USER_TASK => 'z',
				//SYNC_FOLDER_TYPE_USER_JOURNAL => 'z',
				//SYNC_FOLDER_TYPE_USER_NOTE => 'z',
				//SYNC_FOLDER_TYPE_UNKNOWN => 'z',
			),
			//creating a new folder in the root folder should create a folder in one backend
			'rootcreatefolderbackend' => 'i',
		);
		*/
	}
	
	public function __construct() {
		//parent::__construct(); // Skip parent constructor call!
		$this->config = self::GetBackendCombinedConfig();
		
		$backend_values = array_unique(array_values($this->config['folderbackend']));
        foreach ($backend_values as $i) {
			ZLog::Write(LOGLEVEL_DEBUG, sprintf("Including backend %s", $this->config['backends'][$i]['name']));
            ZPush::IncludeBackend($this->config['backends'][$i]['name']);
            $this->backends[$i] = new $this->config['backends'][$i]['name']();
        }
        ZLog::Write(LOGLEVEL_DEBUG, sprintf("Combined %d backends loaded.", count($this->backends)));
	}
	
	public function SendMail($sm) {
        ZLog::Write(LOGLEVEL_DEBUG, "Combined->SendMail()");
        // Convert source folderid
		$backendId = null;
        if (isset($sm->source->folderid)) {
			ZLog::Write(LOGLEVEL_DEBUG, sprintf("Combined->SendMail() FOLDERID: %s", $sm->source->folderid));
            $sm->source->folderid = $this->GetBackendFolder($sm->source->folderid);
			$backendId = $this->GetBackendId($sm->source->folderid);
        } else {ZLog::Write(LOGLEVEL_DEBUG, "Combined->SendMail() SOURCE MISSING");}
		foreach ($this->backends as $i => $b){
			if ($this->backends[$i]->SendMail($sm) == true) {
				ZLog::Write(LOGLEVEL_DEBUG, sprintf("Combined->SendMail() Backend %s has sent email for %s", $i, is_null($backendId) ? 'NULL' : $backendId));
				return true;
			}
		}
        return false;
    }
}
