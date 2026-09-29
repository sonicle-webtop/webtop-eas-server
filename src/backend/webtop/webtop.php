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
			//require_once WT_EAS_ROOT.'/backend/wtimap/wtimap.php';
			$backends['i'] = [
				'name' => 'BackendWTIMAP'
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
			//require_once WT_EAS_ROOT.'/backend/wtcontacts/wtcontacts.php';
			$backends['v'] = [
				'name' => 'BackendWTContacts'
			];
			$folderackends[SYNC_FOLDER_TYPE_CONTACT] = 'v';
			$folderackends[SYNC_FOLDER_TYPE_USER_CONTACT] = 'v';
		}
		
		if (Config::get()->getCalendarEnabled()) {
			//require_once WT_EAS_ROOT.'/backend/wtcalendar/wtcalendar.php';
			$backends['c'] = [
				'name' => 'BackendWTCalendar'
			];
			$folderackends[SYNC_FOLDER_TYPE_APPOINTMENT] = 'c';
			$folderackends[SYNC_FOLDER_TYPE_USER_APPOINTMENT] = 'c';
		}
		
		if (Config::get()->getTasksEnabled()) {
			//require_once WT_EAS_ROOT.'/backend/wttasks/wttasks.php';
			$backends['t'] = [
				'name' => 'BackendWTTasks'
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
		// Do NOT call constructor: this allows to skip call to default BackendCombinedConfig::GetBackendCombinedConfig (see parent::__construct() for more details)
		$this->config = self::GetBackendCombinedConfig();
		
		// These lines are basically those of original constructor...
		$backend_values = array_unique(array_values($this->config['folderbackend']));
        foreach ($backend_values as $i) {
			ZLog::Write(LOGLEVEL_DEBUG, sprintf("Including backend %s", $this->config['backends'][$i]['name']));
            //ZPush::IncludeBackend($this->config['backends'][$i]['name']); // Use our IncludeBackend (see below)!
			self::IncludeBackend($this->config['backends'][$i]['name']);
            $this->backends[$i] = new $this->config['backends'][$i]['name']();
		}
		ZLog::Write(LOGLEVEL_DEBUG, sprintf("Combined %d backends loaded.", count($this->backends)));
	}
	
	public function Logon($username, $domain, $password) {
		if (Config::get()->getWTStripSubdomainFromLoginUsername()) {
			$email_domain = WT\Util::getDomainPartFromEmail($username);
			if ($email_domain != false) {
				$logon_username = Utils::GetLocalPartFromEmail($username) . '@' . WT\Util::stripSubdomain($email_domain);
				if ($logon_username != $username) {
					ZLog::Write(LOGLEVEL_DEBUG, sprintf("Sub-domain stripped from Logon username '%s' -> '%s'", $username, $logon_username));
					return parent::Logon($logon_username, $domain, $password);
				}
			}
		}
		return parent::Logon($username, $domain, $password);
	}
	
	public function SendMail($sm) {
        ZLog::Write(LOGLEVEL_DEBUG, "BackendWebTop->SendMail()");
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
	
	public function GetUserDetails($username) {
		ZLog::Write(LOGLEVEL_DEBUG, "BackendWebTop->GetUserDetails()");
        // Find a backend that can provide the information
		foreach ($this->backends as $backend) {
			// Do NOT get info from IMAP backends, they NOT have user informations!
            if (!($backend instanceof BackendIMAP) && method_exists($backend, "GetUserDetails")) {
                return $backend->GetUserDetails($username);
            }
        }
        return parent::GetUserDetails($username);
	}
	
	/**
	 * Loads a backend file.
	 * This is just a clone of ZPush::IncludeBackend methods that uses custom base-path instead of REAL_BASE_PATH.
	 * @param string $backendname
	 * @return boolean
	 * @throws FatalNotImplementedException
	 */
	static public function IncludeBackend($backendname) {
        if ($backendname == false) return false;

        $backendname = strtolower($backendname);
        if (substr($backendname, 0, 7) !== 'backend')
            throw new FatalNotImplementedException(sprintf("Backend '%s' is not allowed", $backendname));

        $rbn = substr($backendname, 7);

        $subdirbackend = WT_EAS_ROOT . '/' . "backend/" . $rbn . "/" . $rbn . ".php";
        $stdbackend = WT_EAS_ROOT . '/' . "backend/" . $rbn . ".php";

        if (is_file($subdirbackend))
            $toLoad = $subdirbackend;
        else if (is_file($stdbackend))
            $toLoad = $stdbackend;
        else
            return false;

        ZLog::Write(LOGLEVEL_DEBUG, sprintf("Including backend file: '%s'", $toLoad));
        return include_once($toLoad);
    }
}
