param(
  [Parameter(Mandatory = $true)][string]$GateHost,
  [Parameter(Mandatory = $true)][string]$Runner,
  [string]$CheckBaseUrl = "http://localhost",
  [string]$RedisPassword = "redis_password"
)

$ErrorActionPreference = "Stop"
$result = "PASS"
$notes = "stable"
$logRef = "N/A"

function Step([string]$name, [scriptblock]$action) {
  Write-Host ""
  Write-Host ("=== {0} ===" -f $name)
  & $action
}

try {
  Step "docker compose up -d" {
    docker compose up -d
  }

  Step "migrate --force" {
    docker compose exec -T app php artisan migrate --force
  }

  Step "integrity:verify" {
    docker compose exec -T app php artisan integrity:verify
  }

  Step "strict monitoring gate" {
    powershell -ExecutionPolicy Bypass -File scripts/check.ps1 -FailOnFailedJobs 1 -CheckBaseUrl $CheckBaseUrl -RedisPassword $RedisPassword
  }

  Step "pre-production tests" {
    docker compose exec -T app php artisan test --group=pre-production
  }
}
catch {
  $result = "FAIL"
  $notes = ($_.Exception.Message -replace "`r?`n", " ").Trim()
  $logRef = "failure.log"
  Write-Host ""
  Write-Host "Gate failed. Collecting failure logs -> failure.log"
  docker compose logs > failure.log
}

$today = Get-Date -Format "yyyy-MM-dd"

Write-Host ""
Write-Host "================ FINAL RESULT ================"
if ($result -eq "PASS") {
  Write-Host "date: $today"
  Write-Host "result: PASS"
  Write-Host "host: $GateHost"
  Write-Host "runner: $Runner"
  Write-Host "notes: $notes"
  Write-Host "log: N/A"
}
else {
  Write-Host "date: $today"
  Write-Host "result: FAIL"
  Write-Host "host: $GateHost"
  Write-Host "runner: $Runner"
  Write-Host "notes: $notes"
  Write-Host "log: $logRef"
}

if ($result -eq "FAIL") {
  exit 1
}

exit 0
