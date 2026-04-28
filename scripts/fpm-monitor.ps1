param(
    [int] $IntervalSeconds = 15,
    [int] $Iterations = 70,
    [string] $OutputFile = "fpm-monitor-out.txt"
)

$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot/..

for ($i = 0; $i -lt $Iterations; $i++) {
    $ts = Get-Date -Format "o"
    Add-Content -Path $OutputFile -Value "===== $ts (tick $($i+1)/$Iterations) ====="
    docker compose exec -T nginx sh -lc "wget -q -O - 'http://127.0.0.1/fpm-status?full' || true" 2>&1 | Add-Content -Path $OutputFile
    docker compose exec -T app sh -lc "test -f /tmp/php-fpm-slow.log && tail -n 40 /tmp/php-fpm-slow.log || true" 2>&1 | Add-Content -Path $OutputFile
    docker compose logs app --since 1m 2>&1 | Select-String -Pattern "pm.max_children|child exited|request terminated|slowlog" | ForEach-Object { $_.ToString() } | Add-Content -Path $OutputFile
    Start-Sleep -Seconds $IntervalSeconds
}
