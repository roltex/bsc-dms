#Requires -Version 5.1
param(
    [string]$BaseUrl = "https://bsc-dms.azurewebsites.net"
)

$BaseUrl = $BaseUrl.TrimEnd('/')
$pass = 0
$fail = 0

function Test-Endpoint {
    param(
        [string]$Name,
        [string]$Url,
        [int]$Expected = 200,
        [ValidateSet('GET', 'POST')]
        [string]$Method = 'GET'
    )
    try {
        if ($Method -eq 'POST') {
            $response = Invoke-WebRequest -Uri $Url -Method POST -UseBasicParsing -TimeoutSec 30 -SkipHttpErrorCheck `
                -Headers @{ Accept = 'application/json'; 'Content-Type' = 'application/json' }
        } else {
            $response = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 30 -SkipHttpErrorCheck
        }
        if ($response.StatusCode -eq $Expected) {
            Write-Host "PASS  $Name ($($response.StatusCode))"
            $script:pass++
        } else {
            Write-Host "FAIL  $Name (expected $Expected, got $($response.StatusCode))"
            $script:fail++
        }
        return $response
    } catch {
        Write-Host "FAIL  $Name ($($_.Exception.Message))"
        $script:fail++
        return $null
    }
}

Write-Host "Smoke tests against $BaseUrl"
Write-Host "---"

$health = Test-Endpoint -Name "Health API" -Url "$BaseUrl/api/health"
Test-Endpoint -Name "Laravel up" -Url "$BaseUrl/up" | Out-Null
Test-Endpoint -Name "SPA index" -Url "$BaseUrl/" | Out-Null
Test-Endpoint -Name "Login API (422)" -Url "$BaseUrl/api/login" -Expected 422 -Method POST | Out-Null

Write-Host "---"
Write-Host "Passed: $pass  Failed: $fail"

if ($health) {
    Write-Host "Health response:"
    Write-Host $health.Content
}

if ($fail -gt 0) { exit 1 }
Write-Host "All smoke tests passed."
