<?php

use WT\EAS\Config;

class BackendWebTop extends BackendCombined {
	
	public static function GetBackendCombinedConfig() {
		$backends = [];
		$folderackends = [];
		$rootcreatefolderbackend = null;
		
		// NB: backend IDs (i, v, c, t) should not be changes otherwise devices 
		// paired in the past may encounter synchronization problems/loops.
		// This substantially avoids backward states compatibility issues.
		
		if (Config::get()->getMailEnabled()) {
			// Add the path for Andrew's Web Libraries to include_path
			// because it is required for the emails with ics attachments
			// @see https://jira.z-hub.io/browse/ZP-1149
			set_include_path(get_include_path().PATH_SEPARATOR . WT_EAS_ROOT.'/php-awl');
			
			require_once WT_EAS_ROOT.'/backend/imap/config.php';
			
			$backends['i'] = [
				'name' => 'BackendIMAP'
			];
			$folderackends[SYNC_FOLDER_TYPE_INBOX] = 'i';
			$folderackends[SYNC_FOLDER_TYPE_DRAFTS] = 'i';
			$folderackends[SYNC_FOLDER_TYPE_WASTEBASKET] = 'i';
			$folderackends[SYNC_FOLDER_TYPE_SENTMAIL] = 'i';
			$folderackends[SYNC_FOLDER_TYPE_OUTBOX] = 'i';
			$folderackends[SYNC_FOLDER_TYPE_OTHER] = 'i';
			$folderackends[SYNC_FOLDER_TYPE_USER_MAIL] = 'i';
			$rootcreatefolderbackend = 'i';
		}
		
		if (Config::get()->getContactsEnabled()) {
			require_once WT_EAS_ROOT.'/backend/contacts/contacts.php';
			$backends['v'] = [
				'name' => 'BackendContacts'
			];
			$folderackends[SYNC_FOLDER_TYPE_CONTACT] = 'v';
			$folderackends[SYNC_FOLDER_TYPE_USER_CONTACT] = 'v';
		}
		
		if (Config::get()->getCalendarEnabled()) {
			require_once WT_EAS_ROOT.'/backend/calendar/calendar.php';
			$backends['c'] = [
				'name' => 'BackendCalendar'
			];
			$folderackends[SYNC_FOLDER_TYPE_APPOINTMENT] = 'c';
			$folderackends[SYNC_FOLDER_TYPE_USER_APPOINTMENT] = 'c';
		}
		
		if (Config::get()->getTasksEnabled()) {
			require_once WT_EAS_ROOT.'/backend/tasks/tasks.php';
			$backends['t'] = [
				'name' => 'BackendTasks'
			];
			$folderackends[SYNC_FOLDER_TYPE_TASK] = 't';
			$folderackends[SYNC_FOLDER_TYPE_USER_TASK] = 't';
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
