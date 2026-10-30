<?php

use WT\LoggerFactory;

Class ZPCore extends Log {
	
	public function __construct() {
		parent::__construct();
	}
	
	private function getLogger() {
		return LoggerFactory::getLogger(__CLASS__);
	}
	
	/**
     * Writes a log message to the general log.
     *
     * @param int $loglevel
     * @param string $message
     *
     * @access protected
     * @return void
     */
	protected function Write($loglevel, $message) {
		$logger = $this->getLogger();
		if (($loglevel & LOGLEVEL_FATAL) == LOGLEVEL_FATAL) {
			$logger->error($this->BuildLogString($loglevel, $message));
		} else if (($loglevel & LOGLEVEL_ERROR) == LOGLEVEL_ERROR) {
			$logger->error($this->BuildLogString($loglevel, $message));
		} else if (($loglevel & LOGLEVEL_WARN) == LOGLEVEL_WARN) {
			$logger->warn($this->BuildLogString($loglevel, $message));
		} else if (($loglevel & LOGLEVEL_INFO) == LOGLEVEL_INFO) {
			$logger->info($this->BuildLogString($loglevel, $message));
		} else if (($loglevel & LOGLEVEL_DEBUG) == LOGLEVEL_DEBUG) {
			$logger->debug($this->BuildLogString($loglevel, $message));
		} else if (($loglevel & LOGLEVEL_WBXML) == LOGLEVEL_WBXML) {
			$logger->trace($this->BuildLogString($loglevel, $message));
		} else if (($loglevel & LOGLEVEL_DEVICEID) == LOGLEVEL_DEVICEID) {
			$logger->trace($this->BuildLogString($loglevel, $message));
		} else if (($loglevel & LOGLEVEL_WBXMLSTACK) == LOGLEVEL_WBXMLSTACK) {
			$logger->trace($this->BuildLogString($loglevel, $message));
		}
	}
	
	/**
     * This function is used as an event for log implementer.
     * It happens when the a call to the Log function is finished.
     *
     * @access public
     * @return void
     */
	public function WriteForUser($loglevel, $message) {
		$this->Write(LOGLEVEL_DEBUG, $message); // Always pass the logleveldebug so it uses syslog level LOG_DEBUG
	}
	
	/**
     * Build the log string for Monolog.
     *
     * @param int       $loglevel
     * @param string    $message
     * @param boolean   $includeUserDevice  puts username and device in the string, default: true
     *
     * @access public
     * @return string
     */
	private function BuildLogString($loglevel, $message, $includeUserDevice = true) {
        $log = '['. str_pad($this->GetPid(),5," ",STR_PAD_LEFT) .']';

        if ($includeUserDevice) {
            // when the users differ, we need to log both
            if (strcasecmp($this->GetAuthUser(), $this->GetUser()) == 0) {
                $log .= ' ['. $this->GetUser() .']';
            }
            else {
                $log .= ' ['. $this->GetAuthUser() . Request::IMPERSONATE_DELIM . $this->GetUser() .']';
            }
        }
        if ($includeUserDevice && $loglevel >= LOGLEVEL_DEVICEID) {
            $log .= ' ['. $this->GetDevid() .']';
        }
        $log .= ' ' . $message;
        return $log;
    }
}
