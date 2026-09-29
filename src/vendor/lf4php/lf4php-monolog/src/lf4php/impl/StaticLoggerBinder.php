<?php
declare(strict_types=1);

namespace lf4php\impl;

/**
 * StaticLoggerBinder for monolog.
 *
 * @package lf4php\impl
 * @author Janos Szurovecz <szjani@szjani.hu>
 */
final class StaticLoggerBinder
{
    /**
     * @var StaticLoggerBinder
     */
    public static $SINGLETON;

    private $loggerFactory;

    public static function init() : void
    {
        self::$SINGLETON = new StaticLoggerBinder();
        self::$SINGLETON->loggerFactory = new MonologLoggerFactory();
    }

    /**
     * @return MonologLoggerFactory
     */
    public function getLoggerFactory() : MonologLoggerFactory
    {
        return $this->loggerFactory;
    }
}
StaticLoggerBinder::init();
