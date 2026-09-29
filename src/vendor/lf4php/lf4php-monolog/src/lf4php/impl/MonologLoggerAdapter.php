<?php
declare(strict_types=1);

namespace lf4php\impl;

use Exception;
use lf4php\helpers\MessageFormatter;
use lf4php\LocationLogger;
use lf4php\MDC;
use Monolog\Logger as MonologLogger;

/**
 * @author Janos Szurovecz <szjani@szjani.hu>
 */
class MonologLoggerAdapter extends LocationLogger
{
    const MONOLOG_EXTRA = 'extra';

    /**
     * @var MonologLogger
     */
    private $monologLogger;

    /**
     * @param \Monolog\Logger $monologLogger
     */
    public function __construct(MonologLogger $monologLogger)
    {
        $this->monologLogger = $monologLogger;
        $this->monologLogger->pushProcessor(array(__CLASS__, 'monologMDCProcessor'));
        $this->setLocationPrefix('');
    }

    public static function monologMDCProcessor($record)
    {
        foreach (MDC::getCopyOfContextMap() as $key => $value) {
            $record[self::MONOLOG_EXTRA][$key] = $value;
        }
        return $record;
    }

    /**
     * @return MonologLogger
     */
    public function getMonologLogger()
    {
        return $this->monologLogger;
    }

    public function getName() : string
    {
        return $this->monologLogger->getName();
    }

    protected function getFormattedLocation() : string
    {
        return $this->getLocationPrefix() . $this->getShortLocation(self::DEFAULT_BACKTRACE_LEVEL + 1) . $this->getLocationSuffix();
    }

    protected function getExceptionString(Exception $e = null) : string
    {
        if ($e === null) {
            return '';
        }
        return PHP_EOL . $e->__toString();
    }

    public function debug($format, $params = array(), Exception $e = null)
    {
        if ($this->isDebugEnabled()) {
            $this->monologLogger->debug($this->getFormattedLocation() . MessageFormatter::format($format, $params) . $this->getExceptionString($e));
        }
    }

    public function error($format, $params = array(), Exception $e = null)
    {
        if ($this->isErrorEnabled()) {
            $this->monologLogger->error($this->getFormattedLocation() . MessageFormatter::format($format, $params) . $this->getExceptionString($e));
        }
    }

    public function info($format, $params = array(), Exception $e = null)
    {
        if ($this->isInfoEnabled()) {
            $this->monologLogger->info($this->getFormattedLocation() . MessageFormatter::format($format, $params) . $this->getExceptionString($e));
        }
    }

    public function trace($format, $params = array(), Exception $e = null)
    {
        if ($this->isTraceEnabled()) {
            $this->monologLogger->debug($this->getFormattedLocation() . MessageFormatter::format($format, $params) . $this->getExceptionString($e));
        }
    }

    public function warn($format, $params = array(), Exception $e = null)
    {
        if ($this->isWarnEnabled()) {
            $this->monologLogger->warning($this->getFormattedLocation() . MessageFormatter::format($format, $params) . $this->getExceptionString($e));
        }
    }

    public function isDebugEnabled() : bool
    {
        return $this->monologLogger->isHandling(MonologLogger::DEBUG);
    }

    public function isErrorEnabled() : bool
    {
        return $this->monologLogger->isHandling(MonologLogger::ERROR);
    }

    public function isInfoEnabled() : bool
    {
        return $this->monologLogger->isHandling(MonologLogger::INFO);
    }

    public function isTraceEnabled() : bool
    {
        return $this->monologLogger->isHandling(MonologLogger::DEBUG);
    }

    public function isWarnEnabled() : bool
    {
        return $this->monologLogger->isHandling(MonologLogger::WARNING);
    }
}
