param(
  [string]$BaseUrl = "http://127.0.0.1:5173"
)

$ErrorActionPreference = "Stop"

function Assert-Contains {
  param(
    [string]$Content,
    [string]$Needle,
    [string]$Label
  )

  if ($Content -notmatch [regex]::Escape($Needle)) {
    throw "Missing '$Needle' in $Label"
  }
}

Write-Host "Verifying customer portal hotfix on $BaseUrl ..." -ForegroundColor Cyan

$woUrl = "$BaseUrl/src/views/customer/CustomerWorkOrdersView.vue"
$reportsUrl = "$BaseUrl/src/views/customer/CustomerReportsView.vue"
$orgUnitsUrl = "$BaseUrl/src/views/customer/CustomerOrgUnitsView.vue"
$layoutUrl = "$BaseUrl/src/layouts/CustomerLayout.vue"
$routerUrl = "$BaseUrl/src/router/index.ts"

function Fetch-Content {
  param([string]$Url)
  $tmp = [System.IO.Path]::GetTempFileName()
  try {
    & curl.exe --silent --show-error --location --max-time 15 "$Url" -o "$tmp" | Out-Null
    return Get-Content -Path $tmp -Raw
  } finally {
    Remove-Item -Path $tmp -Force -ErrorAction SilentlyContinue
  }
}

$wo = Fetch-Content -Url $woUrl
$reports = Fetch-Content -Url $reportsUrl
$orgUnits = Fetch-Content -Url $orgUnitsUrl
$layout = Fetch-Content -Url $layoutUrl
$router = Fetch-Content -Url $routerUrl

# Work orders search fields
Assert-Contains -Content $wo -Needle "vehicleSearch" -Label "CustomerWorkOrdersView"
Assert-Contains -Content $wo -Needle "serviceSearch" -Label "CustomerWorkOrdersView"

# Profile route and shortcuts
Assert-Contains -Content $layout -Needle "/customer/profile" -Label "CustomerLayout"
Assert-Contains -Content $router -Needle "customer.profile" -Label "router/index.ts"

# Customer reports phase-1/2 endpoints and filters
Assert-Contains -Content $reports -Needle "/customer-portal/reports/work-order-items-by-service" -Label "CustomerReportsView"
Assert-Contains -Content $reports -Needle "/customer-portal/reports/work-order-items-by-product" -Label "CustomerReportsView"
Assert-Contains -Content $reports -Needle "/customer-portal/reports/work-orders-completed" -Label "CustomerReportsView"
Assert-Contains -Content $reports -Needle "/customer-portal/reports/filter-options" -Label "CustomerReportsView"
Assert-Contains -Content $reports -Needle "filters.orgUnitId" -Label "CustomerReportsView"

# Customer org units tree endpoint
Assert-Contains -Content $orgUnits -Needle "/customer-portal/org-units/tree" -Label "CustomerOrgUnitsView"

Write-Host "OK: deployed frontend contains latest customer portal changes." -ForegroundColor Green
