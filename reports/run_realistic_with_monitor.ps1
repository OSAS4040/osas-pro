$ErrorActionPreference = "Stop"

$root = "C:\Users\nawaf\.verdent\verdent-projects\new-project-3"
$benchOut = Join-Path $root "reports\realistic_mix_10000_200_run.json"
$monOut = Join-Path $root "reports\realistic_mix_10000_200_resources.jsonl"

$env:BENCH_EMAIL = "admin@osas.sa"
$env:BENCH_PASSWORD = "12345678"
$env:BENCH_TOTAL = "10000"
$env:BENCH_CONCURRENCY = "200"
$env:BENCH_DURATION_SEC = "3600"
$env:BENCH_OUT = $benchOut

if (Test-Path $monOut) {
  Remove-Item $monOut -Force
}

$proc = Start-Process -FilePath "node" `
  -ArgumentList (Join-Path $root "reports\realistic_mix_10000_200.js") `
  -PassThru -WindowStyle Hidden

try {
  while ($true) {
    $proc.Refresh()
    if ($proc.HasExited) { break }
    $ts = (Get-Date).ToString("o")
    $statsRaw = docker stats --no-stream --format "{{.Name}}|{{.CPUPerc}}|{{.MemUsage}}" saas_app saas_postgres saas_redis
    $stats = @{}
    foreach ($line in $statsRaw) {
      $parts = $line -split "\|"
      if ($parts.Length -ge 3) {
        $stats[$parts[0]] = @{ cpu = $parts[1]; mem = $parts[2] }
      }
    }

    $pgConn = docker exec saas_postgres psql -U saas_user -d saas_db -t -A -c "select count(*) from pg_stat_activity where datname='saas_db';"
    $failedCount = docker exec saas_postgres psql -U saas_user -d saas_db -t -A -c "select count(*) from failed_jobs;"
    $queueDefault = docker exec saas_redis sh -lc "REDISCLI_AUTH=redis_password redis-cli --raw LLEN queues:default"
    $queueHigh = docker exec saas_redis sh -lc "REDISCLI_AUTH=redis_password redis-cli --raw LLEN queues:high"
    $queueLow = docker exec saas_redis sh -lc "REDISCLI_AUTH=redis_password redis-cli --raw LLEN queues:low"

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
    }
    ($obj | ConvertTo-Json -Compress) | Add-Content -Path $monOut -Encoding UTF8

    Start-Sleep -Seconds 20
  }
}
finally {
  $proc.Refresh()
}

if ($proc.ExitCode -ne 0) {
  throw "Realistic benchmark failed with exit code $($proc.ExitCode)"
}

Write-Host "Benchmark completed."
Write-Host "Benchmark file: $benchOut"
Write-Host "Resource file: $monOut"

