#!/bin/sh
set -e
cd /app
# يُبقي bind mount على المصدر، بينما node_modules في volume منفصل؛ هذا يحدّث التبعيات عند تغيّر package.json
# بدون audit/fund على كل تشغيل — يقلّل الضجيج والوقت في السجلات
npm install --no-audit --no-fund
exec "$@"
