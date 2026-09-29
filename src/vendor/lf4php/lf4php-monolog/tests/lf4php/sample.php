<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use lf4php\impl\StaticLoggerBinder;
use lf4php\LoggerFactory;
use lf4php\MDC;

// initialize Monolog
$handler = new Monolog\Handler\StreamHandler('php://output');
$monolog1 = new \Monolog\Logger('root');
$monolog2 = new \Monolog\Logger('Test');
$monolog1->pushHandler($handler);
$monolog2->pushHandler($handler);

// configure lf4php
$factory = StaticLoggerBinder::$SINGLETON->getLoggerFactory();
$factory->setRootMonologLogger($monolog1);
$factory->registerMonologLogger($monolog2);

// logging
$logger = LoggerFactory::getLogger('default');
MDC::put('IP', '127.0.0.1');
$logger->error('Hello {}!', array('World'), new Exception());
$logger->info(Test::getException());

class Test
{
    public static function getException()
    {
        LoggerFactory::getLogger(__CLASS__)->debug(__METHOD__);
        MDC::clear();
        return new Exception('ouch');
    }
}
