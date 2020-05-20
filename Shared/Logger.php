<?php

namespace Shared;

require_once __DIR__.'/../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('logger');

$date = date('d-m-Y');
$logs_directory = __DIR__."/../Logs/$date";

if(!file_exists($logs_directory)){
    mkdir($logs_directory);
}

$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

$logger->pushHandler(new StreamHandler($logs_directory.'/debug.log', Logger::DEBUG));
$logger->pushHandler(new StreamHandler($logs_directory.'/info.log', Logger::INFO));
$logger->pushHandler(new StreamHandler($logs_directory.'/error.log', Logger::ERROR));
$logger->pushHandler(new StreamHandler($logs_directory.'/critical.log', Logger::CRITICAL));
$logger->pushHandler(new StreamHandler($logs_directory.'/alert.log', Logger::ALERT));
$logger->pushHandler(new StreamHandler($logs_directory.'/warning.log', Logger::WARNING));
$logger->pushHandler(new StreamHandler($logs_directory.'/notice.log', Logger::NOTICE));

?>
