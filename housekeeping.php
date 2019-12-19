<?php

require_once 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('housekeeping');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

define('OPENSTACK', '/usr/local/bin/openstack');
define('OPENSTACK_CONTAINER_LIST', OPENSTACK . ' container list -f csv --quote all --long  2>&1');
define('OPENSTACK_OBJECT_LIST', OPENSTACK . ' object list %1$s -f csv --quote all --long  2>&1');
define('OPENSTACK_OBJECT_DELETE', OPENSTACK . ' object delete %1$s %2$s 2>&1');

define('CONTAINER', getenv('CONTAINER'));
define('TIMESTAMP_FORMAT', getenv('TIMESTAMP_FORMAT') ?: 'Ymd');
define('RETENTION_DAYS', getenv('RETENTION_DAYS') ?: 1);
define('DELETE_OLDER', getenv('DELETE_OLDER') ?: 0);
define('DRY_RUN', (bool)getenv('DRY_RUN'));

if (CONTAINER === false) {
    $logger->error('No container defined.');
    exit(1);
}

if (!is_numeric(RETENTION_DAYS) || RETENTION_DAYS < 1) {
    $logger->error('RETENTION_DAYS is not a number or is smaller than 1');
    exit(1);
}

if (!is_numeric(DELETE_OLDER) || DELETE_OLDER < 0) {
    $logger->error('DELETE_OLDER is not a number or is smaller than 0');
    exit(1);
}

$logger->info(sprintf('Container: %1$s', CONTAINER));
$logger->info(sprintf('Retention: %1$d days', RETENTION_DAYS));
$logger->info(sprintf('Delete older: %1$d days', DELETE_OLDER));
$logger->info(sprintf('Timestamp Format: %1$s', TIMESTAMP_FORMAT));
$logger->info(sprintf('Dry run: %1$s', DRY_RUN ? 'on' : 'off'));

/**
 * Check container exists
 */
$cmd = OPENSTACK_CONTAINER_LIST;
$output = [];
$returnStatus = null;

$logger->debug($cmd);
exec($cmd . '', $output, $returnStatus);

$logger->debug($returnStatus);
$logger->debug(var_export($output, true));

if ($returnStatus > 0) {
    $logger->error(sprintf('Return status %1$d is not equals zero.', $returnStatus));
    $logger->error(sprintf('Message: %1$s', array_pop($output)));
    exit(1);
}

$containerFound = false;
$firstOutputLine = true;
foreach ($output as $container) {
    $containerData = explode(',', $container);
    array_walk($containerData, function (&$value) {
        $value = trim($value, '"');
    });

    if ($firstOutputLine === true) {
        if ($containerData[0] !== 'Name') {
            $logger->error(sprintf('Expected first column to be \'Name\', but got \'%1$s\'', $objectData[0]));
            exit(1);
        }
        $firstOutputLine = false;
        continue;
    }

    if ($containerData[0] === CONTAINER) {
        $containerFound = true;
        break;
    }
}

if ($containerFound === false) {
    $logger->error(sprintf('No container with name \'%1$s\' found.', CONTAINER));
    exit(1);
}


/**
 * List objects in container
 */
$cmd = sprintf(OPENSTACK_OBJECT_LIST, CONTAINER);
$output = [];
$returnStatus = null;

$logger->debug($cmd);
exec($cmd . '', $output, $returnStatus);

$logger->debug($returnStatus);
$logger->debug(var_export($output, true));

if ($returnStatus > 0) {
    $logger->error(sprintf('Return status %1$d is not equals zero.', $returnStatus));
    $logger->error(sprintf('Message: %1$s', array_pop($output)));
    exit(1);
}

/**
 * Prepare for deletetion
 */
$selectedObjectsForDeletion = [];

$now = new \DateTime();
$now->modify(sprintf('-%1$d day', RETENTION_DAYS));

$day = 0;
do {
    if ($day > 0) {
        $now->modify('-1 day');
    }

    $logger->debug(sprintf('Will select objects where filename contains %1$s', $now->format(TIMESTAMP_FORMAT)));

    $firstOutputLine = true;
    foreach ($output as $object) {
        $objectData = explode(',', $object);
        array_walk($objectData, function (&$value) {
            $value = trim($value, '"');
        });

        if ($firstOutputLine === true) {
            if ($objectData[0] !== 'Name') {
                $logger->error(sprintf('Expected first column to be \'Name\', but got \'%1$s\'', $objectData[0]));
                exit(1);
            }
            $firstOutputLine = false;
            continue;
        }

        if (strpos($objectData[0], $now->format(TIMESTAMP_FORMAT)) !== false) {
            $selectedObjectsForDeletion[] = $objectData[0];
            $logger->debug(sprintf('%1$s selected for deletion', $objectData[0]));
        }    
    }

    $day++;
} while (DELETE_OLDER > 0 && $day <= DELETE_OLDER);

/**
 * Execute deletion
 */
if (DRY_RUN === false) {
    if (count($selectedObjectsForDeletion) > 0) {
        foreach ($selectedObjectsForDeletion as $objectForDeletion) {
            $logger->debug(sprintf('Will try to delete object \'%1$s\'', $objectForDeletion));

            $cmd = sprintf(OPENSTACK_OBJECT_DELETE, CONTAINER, $objectForDeletion);
            $output = [];
            $returnStatus = null;

            $logger->debug($cmd);
            exec($cmd . '', $output, $returnStatus);

            $logger->debug($returnStatus);
            $logger->debug(var_export($output, true));

            if ($returnStatus > 0) {
                $logger->error(sprintf('Return status %1$d is not equals zero.', $returnStatus));
                $logger->error(sprintf('Message: %1$s', array_pop($output)));
                exit(1);
            }

            $logger->info(sprintf('Object \'%1$s\' deleted', $objectForDeletion));
        }

        $logger->info(sprintf('Deleted %1$d objects. Bye, bye!', count($selectedObjectsForDeletion)));
    } else {
        $logger->info('Nothing to delete. Bye, bye!');
    }
} else {
    $logger->info(sprintf('Dry run mode. Selected %1$d objects for deletion. Deletion will not be executed. Bye, bye!', count($selectedObjectsForDeletion)));
}
