<?php
declare(strict_types=1);

namespace lf4php\impl;

use lf4php\CachedClassLoggerFactory;
use Monolog\Logger as MonologLogger;

/**
 * @author Janos Szurovecz <szjani@szjani.hu>
 */
class MonologLoggerFactory extends CachedClassLoggerFactory
{
    const ROOT_LOGGER_NAME = 'ROOT';

    public function __construct()
    {
        parent::__construct(new MonologLoggerAdapter(new MonologLogger(self::ROOT_LOGGER_NAME)));
    }

    /**
     * @param MonologLogger $monologLogger
     */
    public function registerMonologLogger(MonologLogger $monologLogger) : void
    {
        $this->registerLogger($monologLogger->getName(), new MonologLoggerAdapter($monologLogger));
    }

    public function setRootMonologLogger(MonologLogger $monologLogger) : void
    {
        $this->setRootLogger(new MonologLoggerAdapter($monologLogger));
    }
}
