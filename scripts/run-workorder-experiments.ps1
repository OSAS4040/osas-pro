param(
  [string]$BaseApiUrl = "http://127.0.0.1:8000/api/v1",
  [Parameter(Mandatory = $true)][string]$StaffTokenA,
  [string]$StaffTokenB = "",
  [string]$CustomerToken = "",
  [Parameter(Mandatory = $true)][int]$CustomerId,
  [Parameter(Mandatory = $true)][int]$VehicleId,
  [int]$OrgUnitIdForReport = 0
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

function Invoke-Api {
  param(
    [string]$Method,
    [string]$Url,
    [string]$Token,
    [object]$Body = $null
  )

  $headers = New-AuthHeader -Token $Token
  if ($null -eq $Body) {
    return Invoke-RestMethod -Method $Method -Uri $Url -Headers $headers -TimeoutSec 40
  }
  $json = $Body | ConvertTo-Json -Depth 8
  return Invoke-RestMethod -Method $Method -Uri $Url -Headers $headers -Body $json -TimeoutSec 40
}

function New-WorkOrderPayload {
  param([int]$CustomerId, [int]$VehicleId, [string]$LineName)
  return @{
    customer_id = $CustomerId
    vehicle_id = $VehicleId
    priority = "normal"
    customer_complaint = "QA Experiment"
    items = @(
      @{
        item_type = "labor"
        name = $LineName
        quantity = 1
        unit_price = 10
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

Write-Host "Running work-order experiments against $BaseApiUrl" -ForegroundColor Cyan

$results = [System.Collections.Generic.List[object]]::new()

# Scenario 1: Same staff user should get incrementing suffix.
$payloadA1 = New-WorkOrderPayload -CustomerId $CustomerId -VehicleId $VehicleId -LineName "Experiment A1"
$payloadA2 = New-WorkOrderPayload -CustomerId $CustomerId -VehicleId $VehicleId -LineName "Experiment A2"

$rA1 = Invoke-Api -Method "POST" -Url "$BaseApiUrl/work-orders" -Token $StaffTokenA -Body $payloadA1
$rA2 = Invoke-Api -Method "POST" -Url "$BaseApiUrl/work-orders" -Token $StaffTokenA -Body $payloadA2

$orderA1 = [string]$rA1.data.order_number
$orderA2 = [string]$rA2.data.order_number
$suffixA1 = ExtractSuffix -OrderNumber $orderA1
$suffixA2 = ExtractSuffix -OrderNumber $orderA2
$isIncremented = ($suffixA1 -ge 0 -and $suffixA2 -eq ($suffixA1 + 1))

$results.Add([pscustomobject]@{
  scenario = "same_user_sequence_increment"
  passed = $isIncremented
  order_1 = $orderA1
  order_2 = $orderA2
})

# Scenario 2: Optional second staff token (different org unit) for scoped sequencing check.
if ($StaffTokenB -and $StaffTokenB.Trim() -ne "") {
  $payloadB = New-WorkOrderPayload -CustomerId $CustomerId -VehicleId $VehicleId -LineName "Experiment B1"
  $rB = Invoke-Api -Method "POST" -Url "$BaseApiUrl/work-orders" -Token $StaffTokenB -Body $payloadB
  $orderB = [string]$rB.data.order_number

  # We consider it a pass if number differs from A2, and contains WO- pattern.
  $scopedLooksValid = ($orderB -ne $orderA2) -and $orderB.StartsWith("WO-")
  $results.Add([pscustomobject]@{
    scenario = "cross_org_scope_numbering"
    passed = $scopedLooksValid
    order_staff_a = $orderA2
    order_staff_b = $orderB
  })
}

# Scenario 3: Optional customer-portal report checks.
if ($CustomerToken -and $CustomerToken.Trim() -ne "") {
  $from = (Get-Date).AddDays(-7).ToString("yyyy-MM-dd")
  $to = (Get-Date).ToString("yyyy-MM-dd")

  $treeRes = Invoke-Api -Method "GET" -Url "$BaseApiUrl/customer-portal/org-units/tree" -Token $CustomerToken
  $results.Add([pscustomobject]@{
    scenario = "customer_org_tree_available"
    passed = ($null -ne $treeRes.data)
    root_count = @($treeRes.data).Count
  })

  $reportUrl = "$BaseApiUrl/customer-portal/reports/work-orders-completed?from=$from&to=$to"
  if ($OrgUnitIdForReport -gt 0) {
    $reportUrl = "$reportUrl&org_unit_id=$OrgUnitIdForReport"
  }
  $reportRes = Invoke-Api -Method "GET" -Url $reportUrl -Token $CustomerToken
  $results.Add([pscustomobject]@{
    scenario = "customer_completed_work_orders_report"
    passed = ($null -ne $reportRes.data.rows)
    rows_count = @($reportRes.data.rows).Count
    total = $reportRes.meta.total
  })
}

$failed = @($results | Where-Object { -not $_.passed })

Write-Host ""
Write-Host "=== Experiment Results ===" -ForegroundColor Yellow
$results | Format-Table -AutoSize

if ($failed.Count -gt 0) {
  Write-Host ""
  Write-Host "Some scenarios failed. Review results above." -ForegroundColor Red
  exit 2
}

Write-Host ""
Write-Host "All experimental scenarios passed." -ForegroundColor Green
