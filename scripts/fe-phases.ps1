<#
.SYNOPSIS
  Run frontend Vitest phase chain 0–6 (same as `make fe-phases` / CI job frontend-phase-gates).

.DESCRIPTION
  From repo root: cd frontend && npm run test:phases:fe
  Requires Node/npm and frontend dependencies (npm ci in frontend once).

.EXAMPLE
  pwsh -NoProfile -ExecutionPolicy Bypass -File scripts/fe-phases.ps1
#>
#Requires -Version 5.1
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location (Join-Path $root "frontend")
npm run test:phases:fe
