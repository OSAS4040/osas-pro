$ErrorActionPreference = "Stop"

$root = "C:\Users\nawaf\.verdent\verdent-projects\new-project-3"
$soakOut = Join-Path $root "reports\soak_30m_after_view_fix.json"
$monOut = Join-Path $root "reports\soak_30m_resources_after_view_fix.jsonl"

$env:BENCH_EMAIL = "admin@osas.sa"
$env:BENCH_PASSWORD = "12345678"
$env:SOAK_CONCURRENCY = "120"
$env:SOAK_DURATION_SEC = "1800"
$env:SOAK_OUT = $soakOut

if (Test-Path $monOut) {
  Remove-Item $monOut -Force
}

$proc = Start-Process -FilePath "node" `
  -ArgumentList (Join-Path $root "reports\soak_mixed.js") `
  -PassThru -WindowStyle Hidden

try {
  while (-not $proc.HasExited) {
    $ts = (Get-Date).ToString("o")

    $statsRaw = docker stats --no-stream --format "{{.Name}}|{{.CPUPerc}}|{{.MemUsage}}" saas_app saas_postgres saas_redis
    $stats = @{}
    foreach ($line in $statsRaw) {
      $parts = $line -split "\|"
      if ($parts.Length -ge 3) {
        $stats[$parts[0]] = @{
          cpu = $parts[1]
          mem = $parts[2]
        }
      }
    }

    $pgConn = docker exec saas_postgres psql -U saas_user -d saas_db -t -A -c "select count(*) from pg_stat_activity where datname='saas_db';"
    $failedCount = docker exec saas_postgres psql -U saas_user -d saas_db -t -A -c "select count(*) from failed_jobs;"
    $redisLatency = docker exec saas_redis redis-cli --latency-history -i 1 | Select-Object -First 1
    $queueDefault = docker exec saas_redis redis-cli LLEN queues:default
    $queueHigh = docker exec saas_redis redis-cli LLEN queues:high
    $queueLow = docker exec saas_redis redis-cli LLEN queues:low

    $obj = [ordered]@{
      at = $ts
      saas_app = $stats["saas_app"]
      saas_postgres = $stats["saas_postgres"]
      saas_redis = $stats["saas_redis"]
      postgres_connections = ($pgConn | Out-String).Trim()
      queue_depth_redis_default = ($queueDefault | Out-String).Trim()
      queue_depth_redis_high = ($queueHigh | Out-String).Trim()
      queue_depth_redis_low = ($queueLow | Out-String).Trim()
      failed_jobs = ($failedCount | Out-String).Trim()
      redis_latency_probe = ($redisLatency | Out-String).Trim()
    }

    ($obj | ConvertTo-Json -Compress) | Add-Content -Path $monOut -Encoding UTF8
    Start-Sleep -Seconds 15
    $proc.Refresh()
  }
}
finally {
  $proc.Refresh()
}

if ($proc.ExitCode -ne 0) {
  throw "Soak process failed with exit code $($proc.ExitCode)"
}

Write-Host "Soak completed."
Write-Host "Soak file: $soakOut"
Write-Host "Resource file: $monOut"

