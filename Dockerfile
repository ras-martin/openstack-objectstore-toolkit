FROM --platform=$BUILDPLATFORM php:8-cli-alpine as build

ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_HOME /tmp/composer
ENV COMPOSER_CACHE_DIR /tmp/composer/cache

RUN apk update \
	&& apk add composer git \
	&& mkdir -p /openstack /data

WORKDIR /openstack

COPY composer.json composer.json
COPY src src

RUN composer install \
	&& apk cache clear \
	&& rm -rf /tmp/composer*


FROM --platform=$TARGETPLATFORM python:slim

RUN apt-get update \
	&& apt-get install -y gcc \
		php7.4-cli \
		php7.4-curl \
		libffi-dev \
		libssl-dev \
		python-dev \
		rustc \
	&& /usr/local/bin/python -m pip install --upgrade pip \
	&& pip install --no-cache-dir python-openstackclient \
	&& apt-get purge -y gcc rustc \
	&& apt-get autoremove -y \
	&& apt-get clean \
	&& mkdir -p /openstack /data

WORKDIR /openstack

COPY entrypoint.sh /entrypoint.sh
COPY --from=build /openstack /openstack

ENTRYPOINT [ "/entrypoint.sh" ]
