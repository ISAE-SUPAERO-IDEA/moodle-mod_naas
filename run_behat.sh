#!/bin/bash
docker exec moodle-docker-webserver-1 php admin/tool/behat/cli/init.php
docker exec moodle-docker-webserver-1 php admin/tool/behat/cli/run.php --tags="@mod_naas"
