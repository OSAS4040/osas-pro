#!/usr/bin/env bash
# يولّد System_Comprehensive_Report.pdf من System_Comprehensive_Report.md (HTML ذاتي + Gotenberg).
set -euo pipefail
cd "$(dirname "$0")"

echo "Markdown -> body HTML (marked)..."
npx --yes marked@12.0.0 --gfm -i System_Comprehensive_Report.md -o System_Comprehensive_Report.body.html

echo "Inline CSS + ERD PNGs -> System_Comprehensive_Report.html..."
node build-report-html.mjs

PORT=3399
NAME=gt-report-pdf
docker rm -f "$NAME" 2>/dev/null || true
echo "Starting Gotenberg on port $PORT..."
docker run -d --rm -p "${PORT}:3000" --name "$NAME" gotenberg/gotenberg:8
sleep 8
trap 'docker rm -f "$NAME" 2>/dev/null || true' EXIT

curl -f -S -X POST "http://127.0.0.1:${PORT}/forms/chromium/convert/html" \
  -F "files=@System_Comprehensive_Report.html;filename=index.html" \
  -o System_Comprehensive_Report.pdf

ls -la System_Comprehensive_Report.pdf
