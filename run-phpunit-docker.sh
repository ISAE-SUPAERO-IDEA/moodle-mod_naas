#!/usr/bin/env bash
# Run mod_naas PHPUnit inside moodle-docker (service webserver, Moodle at /var/www/html).
#
# Usage (from anywhere):
#   ./run-phpunit-docker.sh
#   ./run-phpunit-docker.sh --filter root_scripts_test
#   ./run-phpunit-docker.sh --coverage-html mod/naas/coverage/html
#
# mod/naas/phpunit.xml is not committed: Moodle generates it (every tests/**/*_test.php,
# coverage scope from tests/coverage.php) when the PHPUnit site is initialised:
#   ./bin/moodle-docker-compose exec -T -w /var/www/html webserver php admin/tool/phpunit/cli/init.php
# Re-run init.php after adding or removing test files.
#
# The external service tests (tests/external) target Moodle 4.1, the CI branch: from 4.2 on,
# lib/externallib.php demands process isolation (require_phpunit_isolation()).
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

if [[ ! -f "${moddir}/phpunit.xml" ]]; then
    echo "mod/naas/phpunit.xml not found: Moodle generates it, it is not committed." >&2
    echo "Initialise the PHPUnit site once from your moodle-docker checkout:" >&2
    echo "  ./bin/moodle-docker-compose exec -T -w /var/www/html webserver php admin/tool/phpunit/cli/init.php" >&2
    exit 1
fi

export MOODLE_DOCKER_WWWROOT="${MOODLE_DOCKER_WWWROOT:-${moodleroot}}"
export MOODLE_DOCKER_DB="${MOODLE_DOCKER_DB:-pgsql}"

cd "${dockerdir}"

exec "${compose}" exec -T -w /var/www/html webserver \
    php -d pcov.enabled=1 -d pcov.directory=/var/www/html \
    vendor/bin/phpunit -c mod/naas/phpunit.xml "$@"
