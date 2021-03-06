# Openstack Object Store Toolkit

This Docker-Image provides upload and housekeeping functionality for the Openstack Object Store. Primary it was made for [OVH Public Cloud - Object Store and Cloud Archive](https://www.ovh.de/public-cloud/), which is used as a backup archive store.

[![Docker Build](https://img.shields.io/docker/cloud/build/rasmartin/openstack-objectstore-toolkit)](https://hub.docker.com/r/rasmartin/openstack-objectstore-toolkit)
[![Docker Automated](https://img.shields.io/docker/cloud/automated/rasmartin/openstack-objectstore-toolkit)](https://hub.docker.com/r/rasmartin/openstack-objectstore-toolkit)
[![Docker Hub amd64](https://img.shields.io/badge/docker%20hub-amd64-blue)](https://hub.docker.com/r/rasmartin/openstack-objectstore-toolkit/tags)
[![Docker Hub amd](https://img.shields.io/badge/docker%20hub-arm-blue)](https://hub.docker.com/r/rasmartin/openstack-objectstore-toolkit/tags)

[![GitHub](https://img.shields.io/github/last-commit/ras-martin/openstack-objectstore-toolkit/master)](https://github.com/ras-martin/openstack-objectstore-toolkit)

## Available Tags on Docker Hub
* `latest`, `latest-amd64` for `amd64` (`x86_64`) architecture
* `latest-arm` for `arm` (`arm32v7`, `armhf`) architecture (runs on Raspberry Pi)

## General

The Docker-Image is based on Python and uses the [`python-openstackclient`](https://github.com/openstack/python-openstackclient) and [PHP](https://www.php.net/) to provide the described functionality.

The code is located in `/openstack`. For uploading files must be available in `/data`.

### Openstack CLI configuration

The required Openstack CLI configuration can be provided by environment variables or by an `openrc.sh` file mounted to `/openstack`. The file must be available during Docker container startup. A warning will be printed to STDOUT if there is no `openrc.sh`.

Notice: You can download your `openrc.sh` directly from your OVH controlpanel and modify it so that `OS_PASSWORD` is set directly and not requested from STDIN.

If you wanna use environment variables for configuration, please provide the following environment variables when starting the Docker container:

* `OS_AUTH_URL`
* `OS_IDENTITY_API_VERSION`
* `OS_USER_DOMAIN_NAME`
* `OS_PROJECT_DOMAIN_NAME`
* `OS_TENANT_ID`
* `OS_TENANT_NAME`
* `OS_USERNAME`
* `OS_PASSWORD`
* `OS_REGION_NAME`

### Naming of files

This helper assumes that your files, locally for uploading and also stored in object store, contains a timestamp (date) somewhere in the filename, for example `20191219-mybackup.7z` or `mybackup-20191219.tar`. Directories are not supported.

## Upload

Upload all files (no subdirectories) of a directory to an given object store container. Mount the directory you wanna upload to `/data`.

Before the upload is done, the object store container will be checked if a given file already exists. After upload a MD5-check can be executed.

### Run upload

``docker run --rm -it -e CONTAINER=<my-container> -e MD5_CHECK=1 -v <path-to-openrc>/openrc.sh:/openstack/openrc.sh -v <path-to-archive-dir>:/data rasmartin/openstack-objectstore-toolkit:latest-<architecture> upload``

### Environment variables

* `CONTAINER` (required): the name of the object container to operate on.
* `MD5_CHECK` (optional, default `1`): executes a MD5 check after file upload.
* `LOG_LEVEL` (optional, default `200`): default value means log level info. Possible values are described [here](https://github.com/Seldaek/monolog/blob/master/doc/01-usage.md#log-levels)

## Housekeeping

Housekeeping looks for old backups in object store and deletes them from there.

### Run housekeeping

``docker run --rm -it -e CONTAINER=<my-container> -e RETENTION_DAYS=7 -e DRY_RUN=1 -v <path-to-openrc>/openrc.sh:/openstack/openrc.sh rasmartin/openstack-objectstore-toolkit:latest-<architecture> housekeeping``

### Environment variables

Which backups are selected for deletion can be controled by the following environment variables.

* `CONTAINER` (required): the name of the object container to operate on.
* `RETENTION_DAYS` (optional, default `1`): number of days the backups should be stored in the object store.
* `TIMESTAMP_FORMAT` (optional, default `Ymd`): format of the timestamp in filenames. References to [PHP date()](https://www.php.net/manual/en/function.date.php).
* `DELETE_OLDER` (optional, default `0`): number of days that the helper should back in the past and delete backups, for example if `housekeeping` is not executed daily.
* `DRY_RUN` (optional, default `0`): if enabled, the files for deletion will be selected, but no deletion will be executed.
* `LOG_LEVEL` (optional, default `200`): Possible values are described [here](https://github.com/Seldaek/monolog/blob/master/doc/01-usage.md#log-levels)
