#!/usr/bin/env bash

CMD=$1
shift

if [ -f "/openstack/openrc.sh" ]; then
    source /openstack/openrc.sh
else
    echo WARNING: No openrc.sh found. Please provide the required environment variables.
fi

case ${CMD} in
housekeeping*)
  /usr/bin/php7.3 /openstack/housekeeping.php
  ;;
upload*)
  /usr/bin/php7.3 /openstack/upload.php
  ;;
*)
  exec "$@"
  ;;
esac
