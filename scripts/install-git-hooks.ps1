# يضبط خطافات Git على مجلد .githooks (نفس منطق: make install-git-hooks)
# الاستخدام: pwsh -NoProfile -File scripts/install-git-hooks.ps1

$ErrorActionPreference = 'Stop'
Set-Location (Resolve-Path (Join-Path $PSScriptRoot '..'))
git config core.hooksPath .githooks
Write-Host 'OK: git core.hooksPath=.githooks (pre-commit يشغّل فحص policy عند تعديل backend/.env*.example أو frontend/env*.example)'
