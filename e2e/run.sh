#!/usr/bin/env bash

set -eu

script_dir="$(dirname "$0")"
readonly script_dir

cd "$script_dir"
php ../tools/phpunit.phar --testdox
