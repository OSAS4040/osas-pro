param(
  [string]$BaseApiUrl = "http://127.0.0.1:8000/api/v1",
  [Parameter(Mandatory = $true)][string]$StaffToken,
  [Parameter(Mandatory = $true)][int]$CustomerId,
  [Parameter(Mandatory = $true)][int]$VehicleId,
  [int]$Requests = 30,
  [int]$PauseMs = 100,
  [switch]$StopOnFirstFailure
)

$ErrorActionPreference = "Stop"

function New-AuthHeader {
  param([string]$Token)
  return @{
    "Accept" = "application/json"
    "Authorization" = "Bearer $Token"
    "Content-Type" = "application/json"
  }
}

function New-WorkOrderPayload {
  param([int]$CustomerId, [int]$VehicleId, [int]$Index)
  return @{
    customer_id = $CustomerId
    vehicle_id = $VehicleId
    priority = "normal"
    customer_complaint = "Load experiment #$Index"
    items = @(
      @{
        item_type = "labor"
        name = "LoadLine-$Index"
        quantity = 1
        unit_price = 12
        tax_rate = 15
      }
    )
  }
}

function ExtractSuffix {
  param([string]$OrderNumber)
  if ($OrderNumber -match "(\d{6})$") { return [int]$matches[1] }
  return -1
}

if ($Requests -lt 1) { throw "Requests must be >= 1" }

$headers = New-AuthHeader -Token $StaffToken
$endpoint = "$BaseApiUrl/work-orders"
$results = [System.Collections.Generic.List[object]]::new()

Write-Host "Running load experiments on $endpoint" -ForegroundColor Cyan
Write-Host "Requests: $Requests | PauseMs: $PauseMs" -ForegroundColor Cyan

$globalWatch = [System.Diagnostics.Stopwatch]::StartNew()

for ($i = 1; $i -le $Requests; $i++) {
  $payload = New-WorkOrderPayload -CustomerId $CustomerId -VehicleId $VehicleId -Index $i
  $json = $payload | ConvertTo-Json -Depth 8

  $sw = [System.Diagnostics.Stopwatch]::StartNew()
  $ok = $true
  $statusCode = 201
  $orderNumber = ""
  $errorMessage = ""

  try {
    $res = Invoke-RestMethod -Method POST -Uri $endpoint -Headers $headers -Body $json -TimeoutSec 45
    $orderNumber = [string]$res.data.order_number
  } catch {
    $ok = $false
    $statusCode = if ($_.Exception.Response -and $_.Exception.Response.StatusCode) { [int]$_.Exception.Response.StatusCode } else { 0 }
    $errorMessage = $_.Exception.Message
  } finally {
    $sw.Stop()
  }

  $results.Add([pscustomobject]@{
    idx = $i
    ok = $ok
    status = $statusCode
    elapsed_ms = [math]::Round($sw.Elapsed.TotalMilliseconds, 2)
    order_number = $orderNumber
    suffix = if ($orderNumber) { ExtractSuffix -OrderNumber $orderNumber } else { -1 }
    error = $errorMessage
  })

  if (-not $ok -and $StopOnFirstFailure.IsPresent) {
    Write-Host "Stopped on first failure at request #$i" -ForegroundColor Red
    break
  }

  if ($PauseMs -gt 0) { Start-Sleep -Milliseconds $PauseMs }
}

$globalWatch.Stop()

$total = $results.Count
$successRows = @($results | Where-Object { $_.ok })
$failedRows = @($results | Where-Object { -not $_.ok })
$success = $successRows.Count
$failed = $failedRows.Count
$successRate = if ($total -gt 0) { [math]::Round(($success / $total) * 100, 2) } else { 0 }

$latencies = @($results | ForEach-Object { $_.elapsed_ms } | Sort-Object)
$avgMs = if ($latencies.Count -gt 0) { [math]::Round((($latencies | Measure-Object -Average).Average), 2) } else { 0 }
$p95Idx = if ($latencies.Count -gt 0) { [math]::Max([math]::Ceiling($latencies.Count * 0.95) - 1, 0) } else { 0 }
$p95Ms = if ($latencies.Count -gt 0) { $latencies[$p95Idx] } else { 0 }
$maxMs = if ($latencies.Count -gt 0) { $latencies[-1] } else { 0 }

$sequenceBreaks = 0
$prev = -1
foreach ($row in $successRows) {
  if ($row.suffix -lt 0) { continue }
  if ($prev -gt 0 -and $row.suffix -ne ($prev + 1)) { $sequenceBreaks++ }
  $prev = $row.suffix
}

Write-Host ""
Write-Host "=== Load Summary ===" -ForegroundColor Yellow
Write-Host ("Total: {0} | Success: {1} | Failed: {2} | SuccessRate: {3}%" -f $total, $success, $failed, $successRate)
Write-Host ("TotalElapsedMs: {0}" -f [math]::Round($globalWatch.Elapsed.TotalMilliseconds, 2))
Write-Host ("LatencyMs avg={0} p95={1} max={2}" -f $avgMs, $p95Ms, $maxMs)
Write-Host ("SequenceBreaks: {0}" -f $sequenceBreaks)

if ($failed -gt 0) {
  Write-Host ""
  Write-Host "=== Failures (first 10) ===" -ForegroundColor Red
  $failedRows | Select-Object -First 10 idx, status, elapsed_ms, error | Format-Table -AutoSize
}

Write-Host ""
Write-Host "=== Sample Success Rows (first 10) ===" -ForegroundColor Green
$successRows | Select-Object -First 10 idx, elapsed_ms, order_number, suffix | Format-Table -AutoSize

if ($failed -gt 0 -or $sequenceBreaks -gt 0) {
  exit 2
}

exit 0
