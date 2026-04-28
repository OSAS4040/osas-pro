#Requires -Version 5.1
<#
.SYNOPSIS
  نفس منطق: make execution-order-local — سياسة env ثم تذكير المراحل 1–5.

.DESCRIPTION
  للمطورين على Windows بدون GNU Make.

.EXAMPLE
  pwsh -NoProfile -ExecutionPolicy Bypass -File scripts/execution-order-local.ps1

.EXAMPLE
  powershell -NoProfile -ExecutionPolicy Bypass -File scripts/execution-order-local.ps1
#>
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

node scripts/check-policy-env-example.mjs
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

node scripts/execution-order-local-hint.mjs
