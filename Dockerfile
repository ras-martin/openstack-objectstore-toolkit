FROM --platform=$TARGETPLATFORM python:slim

ARG TARGETPLATFORM
ARG BUILDPLATFORM

ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_HOME /tmp/composer
ENV COMPOSER_CACHE_DIR /tmp/composer/cache

RUN apt-get update \
	&& apt-get install -y gcc \
		php7.4-cli \
		php7.4-curl \
		composer \
		git \
		libffi-dev \
		libssl-dev \
		python-dev \
		rustc \
	&& /usr/local/bin/python -m pip install --upgrade pip \
	&& pip install --no-cache-dir python-openstackclient \
	&& mkdir -p /openstack /data

WORKDIR /openstack

COPY entrypoint.sh /entrypoint.sh
COPY composer.json composer.json
COPY src src

RUN composer install \
	&& apt-get purge -y gcc rustc \
	&& apt-get autoremove -y \
	&& apt-get clean \
	&& rm -rf /tmp/composer*

ENTRYPOINT [ "/entrypoint.sh" ]
