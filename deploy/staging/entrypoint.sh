#!/bin/sh
set -eu
/usr/local/bin/ffp-export-runner >/dev/null 2>&1 &
exec /usr/local/bin/docker-entrypoint.sh "$@"
