#!/usr/bin/env bash

set -eux

BUILDOTTER_COMPOSER_EXEC="${BUILDOTTER_COMPOSER_EXEC:-composer}"

"${BUILDOTTER_COMPOSER_EXEC}" require php:"^8.1|^8.4"
"${BUILDOTTER_COMPOSER_EXEC}" config platform-check false
php vendor/bin/rector process -c downgrade.php
"${BUILDOTTER_COMPOSER_EXEC}" dump
