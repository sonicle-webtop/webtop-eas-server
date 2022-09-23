<?php

use lf4php\LoggerFactory;

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
		
		switch ($loglevel) {
			case LOGLEVEL_FATAL:
			case LOGLEVEL_ERROR:
				if ($logger->isErrorEnabled()) {
					$logger->error($this->BuildLogString($loglevel, $message));
				}
				break;
			case LOGLEVEL_WARN:
				if ($logger->isWarnEnabled()) {
					$logger->warn($this->BuildLogString($loglevel, $message));
				}
				break;
			case LOGLEVEL_INFO:
				if ($logger->isInfoEnabled()) {
					$logger->info($this->BuildLogString($loglevel, $message));
				}
				break;
			case LOGLEVEL_DEBUG:
				if ($logger->isDebugEnabled()) {
					$logger->debug($this->BuildLogString($loglevel, $message));
				}
				break;
			case LOGLEVEL_WBXML:
			case LOGLEVEL_DEVICEID:
			case LOGLEVEL_WBXMLSTACK:
				if ($logger->isTraceEnabled()) {
					$logger->trace($this->BuildLogString($loglevel, $message));
				}
				break;
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
