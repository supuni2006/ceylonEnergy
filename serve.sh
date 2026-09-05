#!/usr/bin/env bash
cd "$(dirname "$0")"
php -c local-php.ini -S localhost:8000
