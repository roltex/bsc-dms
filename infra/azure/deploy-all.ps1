#Requires -Version 5.1
<#
.SYNOPSIS
  Full Azure deploy: infrastructure + app config + optional manual app deploy.
  Run from project root after: winget install Microsoft.AzureCLI && az login
#>
param(
    [switch]$SkipInfra,
    [switch]$SkipConfigure,
    [switch]$DeployAppZip
)

$ErrorActionPreference = "Stop"
$Root = Resolve-Path "$PSScriptRoot\..\.."

Write-Host "=== EFES DMS Azure Deploy ===" -ForegroundColor Cyan

if (-not $SkipInfra) {
    & "$PSScriptRoot\deploy.ps1"
}

if (-not $SkipConfigure) {
    & "$PSScriptRoot\configure-app.ps1"
}

if ($DeployAppZip) {
    Write-Host "Building and deploying application zip..."
    Push-Location $Root
    Push-Location frontend
    npm ci
    npm run build
    Pop-Location
    Push-Location backend
    composer install --no-dev --optimize-autoloader --no-interaction
    Pop-Location
    bash scripts/build-deploy-package.sh
    az webapp deploy `
        --resource-group rg-efes-prod-weu `
        --name app-efes-dms-prod `
        --src-path deploy.zip `
        --type zip
    Pop-Location
}

Write-Host ""
Write-Host "=== Done ===" -ForegroundColor Green
Write-Host "1. Run: .\infra\azure\setup-github-oidc.ps1"
Write-Host "2. Push code to GitHub (or use -DeployAppZip above)"
Write-Host "3. Run: .\scripts\smoke-test.ps1 -BaseUrl https://app-efes-dms-prod.azurewebsites.net"
Write-Host "4. Read: DEPLOY_HANDOFF.local.md"
