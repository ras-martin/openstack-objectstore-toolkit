FROM python:slim

RUN apt-get update \
	&& apt-get install -y gcc \
		php7.3-cli \
		composer \
		git \
	&& pip install --no-cache-dir python-openstackclient \
	&& mkdir -p /openstack /data

WORKDIR /openstack

COPY entrypoint.sh /entrypoint.sh
COPY composer.json composer.json
COPY housekeeping.php housekeeping.php

ENTRYPOINT [ "/entrypoint.sh" ]
