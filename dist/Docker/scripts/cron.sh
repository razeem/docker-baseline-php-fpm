#!/bin/bash

# Load container environment variables
set -a
source /etc/container_env.sh
set +a

echo "[CRON START] $(date)" >> /proc/1/fd/1

# Drush or other task
/app/vendor/bin/drush cron >> /proc/1/fd/1 2>&1

echo "[CRON END] $(date)" >> /proc/1/fd/1

