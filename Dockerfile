FROM python:slim

ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_HOME /tmp/composer
ENV COMPOSER_CACHE_DIR /tmp/composer/cache

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

RUN composer install \
	&& apt-get purge -y gcc \
	&& apt-get autoremove -y \
	&& apt-get clean \
	&& rm -rf /tmp/composer*

ENTRYPOINT [ "/entrypoint.sh" ]
