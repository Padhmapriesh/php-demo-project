#!/bin/bash
set -e
sleep 5
curl -f http://localhost/health.php || exit 1
echo "Service is up and healthy."
