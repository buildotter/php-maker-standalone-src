#!/usr/bin/env bash

set -eu

script_dir="$(dirname "$0")"
readonly script_dir

cd "$script_dir"
composer install --no-dev --prefer-dist
php ./tools/phpunit.phar --testdox
