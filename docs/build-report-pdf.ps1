# يولّد System_Comprehensive_Report.pdf من System_Comprehensive_Report.md (HTML ذاتي + Gotenberg).
$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

Write-Host "Markdown -> body HTML (marked)..."
npx --yes marked@12.0.0 --gfm -i System_Comprehensive_Report.md -o System_Comprehensive_Report.body.html

Write-Host "Inline CSS + ERD PNGs -> System_Comprehensive_Report.html..."
node build-report-html.mjs

$port = 3399
$name = "gt-report-pdf"
docker rm -f $name 2>$null | Out-Null
Write-Host "Starting Gotenberg on port $port..."
docker run -d --rm -p "${port}:3000" --name $name gotenberg/gotenberg:8 | Out-Null
Start-Sleep -Seconds 8

try {
  $url = "http://127.0.0.1:$port/forms/chromium/convert/html"
  curl.exe -f -S -X POST $url -F "files=@System_Comprehensive_Report.html;filename=index.html" -o System_Comprehensive_Report.pdf
  $len = (Get-Item System_Comprehensive_Report.pdf).Length
  Write-Host "Wrote System_Comprehensive_Report.pdf ($len bytes)."
}
finally {
  docker rm -f $name 2>$null | Out-Null
}
