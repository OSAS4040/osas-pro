<#
.SYNOPSIS
  Mirrors repo frontend into deployment/official_release_package/frontend (sources + config + public).

.DESCRIPTION
  Replaces deployment frontend/src entirely so the official release package matches the working tree.
  Run after UI/auth changes before packaging or copying the deployment folder elsewhere.

.EXAMPLE
  pwsh -NoProfile -File scripts/sync-official-release-frontend.ps1
#>
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$fe = Join-Path $root "frontend"
$dstRoot = Join-Path $root "deployment\official_release_package\frontend"
$dstSrc = Join-Path $dstRoot "src"

Write-Host "== Sync official_release frontend from repo frontend ==" -ForegroundColor Cyan
if (-not (Test-Path $fe)) { throw "Missing: $fe" }
New-Item -ItemType Directory -Path $dstRoot -Force | Out-Null

Remove-Item -Path $dstSrc -Recurse -Force -ErrorAction SilentlyContinue
Copy-Item -Path (Join-Path $fe "src") -Destination $dstSrc -Recurse -Force

$files = @(
  "package.json", "package-lock.json", "vite.config.ts", "vitest.config.ts", "index.html",
  "tailwind.config.js", "postcss.config.js", "tsconfig.json", "tsconfig.app.json", "tsconfig.node.json",
  "eslint.config.js", "components.json", "env.d.ts"
)
foreach ($f in $files) {
  $s = Join-Path $fe $f
  if (Test-Path $s) {
    Copy-Item $s (Join-Path $dstRoot $f) -Force
    Write-Host "  ok $f"
  }
}

$pubSrc = Join-Path $fe "public"
$pubDst = Join-Path $dstRoot "public"
if (Test-Path $pubSrc) {
  Remove-Item -Path $pubDst -Recurse -Force -ErrorAction SilentlyContinue
  Copy-Item -Path $pubSrc -Destination $pubDst -Recurse -Force
  Write-Host "  ok public/"
}

Write-Host "Done: $dstRoot" -ForegroundColor Green
