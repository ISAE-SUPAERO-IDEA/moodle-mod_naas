#!/usr/bin/env bash
# Run mod_naas PHPUnit inside moodle-docker (service webserver, Moodle at /var/www/html).
#
# Usage (from anywhere):
#   ./run-phpunit-docker.sh
#   ./run-phpunit-docker.sh --filter root_scripts_test
#
# Override paths:
#   MOODLE_DOCKER=/path/to/moodle-docker ./run-phpunit-docker.sh
#   MOODLE_DOCKER_WWWROOT=/path/to/moodle ./run-phpunit-docker.sh

set -euo pipefail

moddir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
moodleroot="$(cd "${moddir}/../.." && pwd)"

if [[ -n "${MOODLE_DOCKER:-}" ]]; then
    dockerdir="${MOODLE_DOCKER}"
else
    dockerdir="$(cd "${moodleroot}/../moodle-docker" 2>/dev/null && pwd || true)"
fi

compose="${dockerdir}/bin/moodle-docker-compose"
if [[ ! -x "${compose}" ]]; then
    echo "moodle-docker not found (expected bin/moodle-docker-compose)." >&2
    echo "Set MOODLE_DOCKER to your moodle-docker checkout, e.g.:" >&2
    echo "  export MOODLE_DOCKER=/path/to/moodle-docker" >&2
    exit 1
fi

export MOODLE_DOCKER_WWWROOT="${MOODLE_DOCKER_WWWROOT:-${moodleroot}}"
export MOODLE_DOCKER_DB="${MOODLE_DOCKER_DB:-pgsql}"

cd "${dockerdir}"

exec "${compose}" exec -T -w /var/www/html webserver \
    php -d pcov.enabled=1 -d pcov.directory=/var/www/html \
    vendor/bin/phpunit -c mod/naas/phpunit.xml "$@"
