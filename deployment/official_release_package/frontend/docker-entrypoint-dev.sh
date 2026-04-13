#!/bin/sh
set -e
cd /app
# يُبقي bind mount على المصدر، بينما node_modules في volume منفصل؛ هذا يحدّث التبعيات عند تغيّر package.json
npm install
exec "$@"
