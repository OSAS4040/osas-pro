# OSAS Pro — Final Readiness Report

**Decision: PENDING**

This file is overwritten when you run the automated gate:

- **Windows:** `powershell -ExecutionPolicy Bypass -File scripts/osas-pro-production-readiness-gate.ps1`
- **Unix / Make:** `make production-readiness-gate` or `bash scripts/osas-pro-production-readiness-gate.sh`

Optional flags (shell): `--skip-clean`, `--skip-k6`, `--skip-playwright`. PowerShell: `-SkipClean`, `-SkipK6`, `-SkipPlaywright`.

See `load-testing/env.example` for k6 credentials and `K6_BASE_URL` when exercising the API under load.
