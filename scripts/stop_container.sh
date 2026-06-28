#!/bin/bash
set -e
if [ "$(docker ps -aq -f name=php-mysql-app)" ]; then
    docker stop php-mysql-app || true
    docker rm php-mysql-app || true
fi
