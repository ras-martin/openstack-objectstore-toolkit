<?php

require_once 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

define('OPENSTACK', '/usr/local/bin/openstack');
define('OPENSTACK_CONTAINER_LIST', OPENSTACK . ' container list -f csv --quote all --long 2>&1');
define('OPENSTACK_OBJECT_SHOW', OPENSTACK . ' object show -f json %1$s %2$s 2>&1');

define('LOG_LEVEL', getenv('LOG_LEVEL') ?: 200);

/**
 * @return Logger
 */
function initLogger($name) {
    $logLevel = (int)LOG_LEVEL;
    try {
        Logger::getLevelName($logLevel);
    } catch (\InvalidArgumentException $e) {
        $logLevel = Logger::INFO;
    }
    
    $logger = new Logger($name);
    $logger->pushHandler(new StreamHandler('php://stdout', $logLevel));
    return $logger;
}

function containerExists($container, Logger $logger) {
    $cmd = OPENSTACK_CONTAINER_LIST;
    $output = [];
    $returnStatus = null;

    $logger->debug($cmd);
    exec($cmd, $output, $returnStatus);

    $logger->debug($returnStatus);
    $logger->debug(var_export($output, true));

    if ($returnStatus > 0) {
        $logger->error(sprintf('Return status %1$d is not equals zero.', $returnStatus));
        $logger->error(sprintf('Message: %1$s', array_pop($output)));
        return false;
    }

    $containerFound = false;
    $firstOutputLine = true;
    foreach ($output as $containerItem) {
        $containerData = explode(',', $containerItem);
        array_walk($containerData, function (&$value) {
            $value = trim($value, '"');
        });

        if ($firstOutputLine === true) {
            if ($containerData[0] !== 'Name') {
                $logger->error(sprintf('Expected first column to be \'Name\', but got \'%1$s\'', $containerData[0]));
                return false;
            }
            $firstOutputLine = false;
            continue;
        }

        if ($containerData[0] === $container) {
            $containerFound = true;
            break;
        }
    }

    if ($containerFound === false) {
        $logger->error(sprintf('No container with name \'%1$s\' found.', $container));
        return false;
    }

    return true;
}

function fetchObjectInfo($object, $container, Logger $logger) {
    $cmd = sprintf(OPENSTACK_OBJECT_SHOW, $container, $object);
    $output = [];
    $returnStatus = null;

    $logger->debug($cmd);
    exec($cmd, $output, $returnStatus);

    $logger->debug($returnStatus);
    $logger->debug(var_export($output, true));

    if ($returnStatus > 0) {
        $logger->error(sprintf('Return status %1$d is not equals zero.', $returnStatus));
        $logger->error(sprintf('Message: %1$s', array_pop($output)));
        return false;
    }

    return json_decode(implode('', $output), true);
}
