# POS capacity ladder: runs k6 profile `capacity_pos` at several arrival rates (default 3,5,7/sec).
# Safety: refuses non-local BASE_URL unless -AllowNonLocalhost (explicit opt-in).
# Prerequisites: Docker, stack reachable from k6 container (e.g. host.docker.internal), seeds + credentials.

param(
  [string]$K6BaseUrl = "http://host.docker.internal/api",
  [int[]]$Rates = @(3, 5, 7),
  [string]$K6EmailA = "simulation.owner@demo.local",
  [string]$K6PasswordA = "SimulationDemo123!",
  [string]$K6EmailB = "owner@demo.sa",
  [string]$K6PasswordB = "password",
  [string]$K6PosDistribution = "single",
  [string]$K6PosIncludeRaw = "true",
  [int]$SteadyMin = 5,
  [switch]$SkipPreflight,
  [switch]$AllowNonLocalhost
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

function Test-UrlAllowedForCapacity([string]$url) {
  if ($AllowNonLocalhost) {
    return $true
  }
  return $url -match '(?i)(localhost|127\.0\.0\.1|host\.docker\.internal)(/|:|$)'
}

if (-not (Test-UrlAllowedForCapacity $K6BaseUrl)) {
  throw @"
Refusing K6_BASE_URL='$K6BaseUrl' for capacity discovery (non-local).
Use -AllowNonLocalhost only if you intentionally target a remote staging URL and accept load risk.
"@
}

if (-not $SkipPreflight) {
  Write-Host "k6 preflight (auth + seeds)..."
  docker run --rm `
    -v "${root}/load-testing:/work" `
    -w /work/k6 `
    --add-host=host.docker.internal:host-gateway `
    -e "K6_BASE_URL=$K6BaseUrl" `
    -e "K6_EMAIL_A=$K6EmailA" `
    -e "K6_PASSWORD_A=$K6PasswordA" `
    -e "K6_EMAIL_B=$K6EmailB" `
    -e "K6_PASSWORD_B=$K6PasswordB" `
    grafana/k6:latest run preflight.js
  if ($LASTEXITCODE -ne 0) {
    throw "k6 preflight failed (exit $LASTEXITCODE). Fix seeds/credentials before capacity runs."
  }
}

$ts = Get-Date -Format "yyyyMMdd-HHmmss"
$outDir = Join-Path $root "load-testing/reports/capacity-discovery-$ts"
New-Item -ItemType Directory -Path $outDir -Force | Out-Null

$meta = [ordered]@{
  created_utc = (Get-Date).ToUniversalTime().ToString("o")
  k6_base_url = $K6BaseUrl
  rates       = $Rates
  steady_min  = $SteadyMin
  k6_pos_distribution = $K6PosDistribution
}
($meta | ConvertTo-Json -Depth 5) | Set-Content -Path (Join-Path $outDir "run-meta.json") -Encoding UTF8

$rows = New-Object System.Collections.Generic.List[object]

foreach ($rate in $Rates) {
  Write-Host ""
  Write-Host "=== capacity_pos @ ${rate}/s (steady ${SteadyMin}m) ===" -ForegroundColor Cyan

  docker run --rm `
    -v "${root}/load-testing:/work" `
    -w /work/k6 `
    --add-host=host.docker.internal:host-gateway `
    -e "K6_BASE_URL=$K6BaseUrl" `
    -e "K6_PROFILE=capacity_pos" `
    -e "K6_CAPACITY_POS_RATE=$rate" `
    -e "K6_CAPACITY_POS_STEADY_MIN=$SteadyMin" `
    -e "K6_EMAIL_A=$K6EmailA" `
    -e "K6_PASSWORD_A=$K6PasswordA" `
    -e "K6_EMAIL_B=$K6EmailB" `
    -e "K6_PASSWORD_B=$K6PasswordB" `
    -e "K6_POS_DISTRIBUTION=$K6PosDistribution" `
    -e "K6_POS_INCLUDE_RAW=$K6PosIncludeRaw" `
    grafana/k6:latest run suite.js

  $code = $LASTEXITCODE
  $tag = "rate-${rate}ps"

  $latestMd = Join-Path $root "load-testing/reports/latest.md"
  $latestJson = Join-Path $root "load-testing/reports/latest-summary.json"
  if (Test-Path $latestMd) {
    Copy-Item $latestMd (Join-Path $outDir "$tag.md") -Force
  }
  if (Test-Path $latestJson) {
    Copy-Item $latestJson (Join-Path $outDir "$tag-summary.json") -Force
  }

  $pos2xx = $null
  $p99Pos = $null
  $p99Http = $null
  $s5 = $null
  $dropped = $null
  try {
    $j = Get-Content $latestJson -Raw -ErrorAction Stop | ConvertFrom-Json
    if ($j.metrics.pos_sale_2xx.values.rate -ne $null) {
      $pos2xx = [double]$j.metrics.pos_sale_2xx.values.rate
    }
    if ($j.metrics.scen_pos_post_http_ms.values.'p(99)' -ne $null) {
      $p99Pos = [double]$j.metrics.scen_pos_post_http_ms.values.'p(99)'
    }
    if ($j.metrics.http_req_duration.values.'p(99)' -ne $null) {
      $p99Http = [double]$j.metrics.http_req_duration.values.'p(99)'
    }
    if ($j.metrics.server_errors_5xx.values.rate -ne $null) {
      $s5 = [double]$j.metrics.server_errors_5xx.values.rate
    }
    if ($j.metrics.dropped_iterations.values.count -ne $null) {
      $dropped = [int64]$j.metrics.dropped_iterations.values.count
    }
  }
  catch {
    Write-Warning "Could not parse latest-summary.json: $($_.Exception.Message)"
  }

  $rows.Add([pscustomobject]@{
      pos_rate_per_s = $rate
      k6_exit_code   = $code
      pos_sale_2xx_rate = $pos2xx
      scen_pos_post_p99_ms = $p99Pos
      http_req_duration_p99_ms = $p99Http
      server_errors_5xx_rate = $s5
      dropped_iterations = $dropped
    })
}

$csvPath = Join-Path $outDir "capacity-ladder.csv"
$rows | Export-Csv -Path $csvPath -NoTypeInformation -Encoding UTF8

$readme = @"
# POS capacity discovery run

- **Output directory:** ``$outDir``
- **Rates:** $($Rates -join ', ') req/s (POS arrival target)
- **Steady plateau:** ${SteadyMin} minutes per rate (see ``run-meta.json``)

## Files

- ``capacity-ladder.csv`` — one row per rate: pos_sale_2xx, p99 POS POST, 5xx, dropped_iterations, k6 exit code
- ``rate-*-summary.json`` — full k6 summary for each step
- ``rate-*.md`` — human-readable report per step

## Next steps

1. Compare ``scen_pos_post_http_ms`` p99 across rates; note where latency or 5xx degrades.
2. Treat **comfortable capacity** below the first step where p99 or 2xx violates your product SLO (e.g. verification gate), not only the hard failure point.
3. Re-run after infra or code changes to see if the ceiling moved.

**Do not** point this script at production without explicit governance.
"@

Set-Content -Path (Join-Path $outDir "README.md") -Value $readme -Encoding UTF8

Write-Host ""
Write-Host "Done. Artifacts: $outDir" -ForegroundColor Green
Write-Host "See capacity-ladder.csv for a quick cross-rate comparison."
