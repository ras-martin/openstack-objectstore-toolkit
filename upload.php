<?php

require_once 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('upload');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

define('OPENSTACK', '/usr/local/bin/openstack');

