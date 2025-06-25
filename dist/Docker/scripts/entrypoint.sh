#!/bin/bash

# Export each env variable properly so the file is source-able
printenv | awk -F= '{ print "export " $1 "=\"" substr($0, index($0,$2)) "\"" }' > /etc/container_env.sh

# Fix permissions if needed
chmod 0644 /etc/container_env.sh

# Start supervisord
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
