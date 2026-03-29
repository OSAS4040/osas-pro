param()

$uri = 'http://localhost/api/v1/auth/login'
$body = '{"email":"owner@demo.sa","password":"Password123!"}'

try {
    $resp = Invoke-RestMethod -Uri $uri -Method POST -Body $body -ContentType 'application/json' -TimeoutSec 10
    Write-Host "Login OK - token starts: $($resp.token.Substring(0,20))..."
} catch {
    Write-Host "Error: $_"
    Write-Host $_.Exception.Response
}
