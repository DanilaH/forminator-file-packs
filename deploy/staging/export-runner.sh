#!/bin/sh
# Only exporter hooks in this owned staging container. Never run other sites' queues.
while :; do
    # The destructive test harness owns synthetic fixtures and process-failure timing.
    if [ -f /var/www/html/wp-config.php ] && [ ! -f /opt/ffp/artifacts/fixture.json ]; then
        gosu www-data wp --path=/var/www/html cron event run ffp_export_step --due-now --quiet || :
        gosu www-data wp --path=/var/www/html cron event run ffp_export_gc --due-now --quiet || :
    fi
    sleep 10
done
