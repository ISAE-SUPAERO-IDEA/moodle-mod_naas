#!/usr/bin/env bash
# Run the mod_naas Behat features inside moodle-docker.
# Override the container with MOODLE_CONTAINER=<name> ./run_behat.sh
set -euo pipefail
CONTAINER="${MOODLE_CONTAINER:-moodle-docker-webserver-1}"
docker exec -w /var/www/html "$CONTAINER" php admin/tool/behat/cli/init.php
docker exec -w /var/www/html "$CONTAINER" php admin/tool/behat/cli/run.php --tags="@mod_naas"
