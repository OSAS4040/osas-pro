<#
.SYNOPSIS
  Run Vitest phases 0–6 then Playwright phase 7 bundle (same as `make fe-phases-with-e2e`).

.DESCRIPTION
  Requires Playwright browser install in frontend: npm run test:e2e:install

.EXAMPLE
  pwsh -NoProfile -ExecutionPolicy Bypass -File scripts/fe-phases-with-e2e.ps1
#>
#Requires -Version 5.1
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location (Join-Path $root "frontend")
npm run test:phases:fe:with-e2e
