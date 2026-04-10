param(
  [string]$CheckBaseUrl = "http://localhost",
  [int]$LogErrorThreshold = 15,
  [string]$RedisPassword = "redis_password",
  [int]$FailOnFailedJobs = 0
)

$ErrorActionPreference = "Continue"
$fail = $false

Write-Host ("Quick Monitoring Gate (PowerShell) - {0}" -f (Get-Date -Format s))
Write-Host "------------------------------------------------------------"

Write-Host "Checking app logs for ERROR/CRITICAL (last 5m)..."
$logsRaw = & docker compose logs app --since 5m 2>$null
$logErrors = 0
if ($logsRaw) {
  $logErrors = ($logsRaw | Select-String -Pattern "error|critical" -CaseSensitive:$false).Count
}
if ($logErrors -gt $LogErrorThreshold) {
  Write-Host ("FAIL: log noise high ({0} > {1})" -f $logErrors, $LogErrorThreshold)
  $fail = $true
} else {
  Write-Host ("PASS: logs within threshold ({0}/{1})" -f $logErrors, $LogErrorThreshold)
}

Write-Host "Checking failed jobs..."
$failedJobsOut = & docker compose exec -T app php artisan queue:failed 2>$null
$failedCount = 0
if ($failedJobsOut) {
  $failedCount = ($failedJobsOut | Select-String -Pattern "^\s+\d{4}-\d{2}-\d{2}").Count
}
if ($failedCount -gt 0) {
  if ($FailOnFailedJobs -eq 1) {
    Write-Host ("FAIL: failed jobs detected ({0}) and strict mode enabled" -f $failedCount)
    $fail = $true
  } else {
    Write-Host ("WARN: failed jobs detected ({0}), strict mode disabled" -f $failedCount)
  }
} else {
  Write-Host "PASS: no failed jobs"
}

function Get-QueueLen {
  param([string]$QueueName)
  $lenRaw = & docker compose exec -T redis redis-cli -a $RedisPassword --no-auth-warning LLEN ("queues:{0}" -f $QueueName) 2>$null
  $lenText = ""
  if ($lenRaw) {
    $lenText = (($lenRaw | Select-Object -First 1).ToString()).Trim()
  }
  if ($lenText -match "^\d+$") { return [int]$lenText }
  return -1
}

Write-Host "Checking Redis queue depths..."
$qh = Get-QueueLen -QueueName "high_priority"
$qd = Get-QueueLen -QueueName "default"
$ql = Get-QueueLen -QueueName "low_priority"
Write-Host ("high_priority={0} (limit 50), default={1} (limit 100), low_priority={2} (limit 100)" -f $qh, $qd, $ql)
if ($qh -lt 0 -or $qd -lt 0 -or $ql -lt 0) {
  Write-Host "WARN: could not parse one or more queue lengths"
} elseif ($qh -gt 50 -or $qd -gt 100 -or $ql -gt 100) {
  Write-Host "FAIL: queue backlog above threshold"
  $fail = $true
} else {
  Write-Host "PASS: queue depths within threshold"
}

Write-Host "Checking compose Restarting state..."
$psOut = & docker compose ps 2>$null
$restartingCount = 0
if ($psOut) {
  $restartingCount = ($psOut | Select-String -Pattern "Restarting" -CaseSensitive:$false).Count
}
if ($restartingCount -gt 0) {
  Write-Host ("FAIL: containers restarting ({0})" -f $restartingCount)
  $fail = $true
} else {
  Write-Host "PASS: no restarting containers"
}

$healthUrl = "{0}/api/v1/health" -f $CheckBaseUrl
Write-Host ("Checking health endpoint: {0}" -f $healthUrl)
$httpCode = & curl.exe -s -o NUL -w "%{http_code}" $healthUrl
if ($httpCode -ne "200") {
  Write-Host ("FAIL: health status {0}" -f $httpCode)
  $fail = $true
} else {
  Write-Host "PASS: health status 200"
}

$timeTotal = & curl.exe -s -o NUL -w "%{time_total}" $healthUrl
Write-Host ("health time_total={0}s" -f $timeTotal)
$timeVal = 999.0
[void][double]::TryParse($timeTotal, [ref]$timeVal)
if ($timeVal -gt 2.0) {
  Write-Host "FAIL: health latency > 2s"
  $fail = $true
} else {
  Write-Host "PASS: health latency within 2s limit"
}

$versionUrl = "{0}/api/v1/system/version" -f $CheckBaseUrl
$versionCode = & curl.exe -s -o NUL -w "%{http_code}" $versionUrl
if ($versionCode -eq "200") {
  Write-Host "PASS: system/version available (200)"
} else {
  Write-Host ("WARN: system/version returned {0} (non-blocking)" -f $versionCode)
}

Write-Host "------------------------------------------------------------"
if ($fail) {
  Write-Host "STATUS: FAIL"
  exit 1
}

Write-Host "STATUS: PASS"
exit 0
