<?php

require_once 'common.php';

$logger = initLogger('upload');

define('OPENSTACK_OBJECT_CREATE', OPENSTACK . ' object create --name %1$s %2$s %3$s 2>&1');

define('LOCAL_DATA_PATH', '/data');

define('CONTAINER', getenv('CONTAINER'));
define('MD5_CHECK', (bool)getenv('MD5_CHECK'));

if (CONTAINER === false) {
    $logger->error('No container defined.');
    exit(1);
}

$logger->info(sprintf('Container: %1$s', CONTAINER));
$logger->info(sprintf('MD5 Check: %1$s', MD5_CHECK ? 'on' : 'off'));

/**
 * Check container exists
 */
if (!containerExists(CONTAINER, $logger)) {
    exit(1);
}

/**
 * Prepare local data path
 */
$localFiles = array_diff(scandir(LOCAL_DATA_PATH), ['.', '..']);

foreach ($localFiles as $localFile) {
    if (fetchObjectInfo($localFile, CONTAINER, $logger) !== false) {
        $logger->error(sprintf('File %1$s already exists in container %2$s.', $localFile, CONTAINER));
        exit(1);
    }
}

/**
 * Upload
 */
foreach ($localFiles as $localFile) {
    $cmd = sprintf(OPENSTACK_OBJECT_CREATE, $localFile, CONTAINER, LOCAL_DATA_PATH . DIRECTORY_SEPARATOR . $localFile);
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

    if (MD5_CHECK === true) {
        $logger->info(sprintf('Checking MD5 Sum for %1$s', $localFile));

        $objectInfo = fetchObjectInfo($localFile, CONTAINER, $logger);
        if ($objectInfo === false) {
            $logger->error(sprintf('Object info for uploaded file %1$s in container %2$s could not be fetched.', $localFile, CONTAINER));
            $logger->error(sprintf('Skipping MD5 check for %1$s', $localFile));
            continue;
        }

        if (!isset($objectInfo['etag'])) {
            $logger->error('Expected data field \'etag\', but it did not exist.');
            $logger->error(sprintf('Skipping MD5 check for %1$s', $localFile));
            continue;
        }

        $cmd = sprintf('/usr/bin/md5sum %1$s', LOCAL_DATA_PATH . DIRECTORY_SEPARATOR . $localFile);
        $output = [];
        exec($cmd, $output);
        $md5 = substr($output[0], 0, 32);

        $logger->info(sprintf('Calculated MD5 Sum: %1$s', $md5));
        $logger->info(sprintf('Fetched MD5 Sum: %1$s', $objectInfo['etag']));

        if ($md5 === $objectInfo['etag']) {
            $logger->info('MD5 ok.');
        } else {
            $logger->error('MD5 mismatch');
        }
    }
}
