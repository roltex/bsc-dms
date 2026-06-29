# Post-provision: configure App Service environment variables
# Run after infra/azure/deploy.ps1 succeeds
# Requires: az login, APP_KEY from php artisan key:generate --show

param(
    [string]$ResourceGroup = "rg-efes-prod-weu",
    [string]$WebAppName = "app-efes-dms-prod",
    [string]$AppUrl = "https://app-efes-dms-prod.azurewebsites.net",
    [string]$AdminEmail = "admin@bsc.ge",
    [string]$AdminPassword = "",
    [string]$AdminName = "System Administrator",
    [string]$AppKey = ""
)

$ErrorActionPreference = "Stop"

if (-not (Get-Command az -ErrorAction SilentlyContinue)) {
    Write-Error "Azure CLI not found. Install: winget install -e --id Microsoft.AzureCLI"
}

if (-not $AppKey) {
    Write-Host "Generating APP_KEY..."
    Push-Location "$PSScriptRoot\..\..\backend"
    $AppKey = (php artisan key:generate --show 2>$null)
    Pop-Location
    if (-not $AppKey) {
        Write-Error "Could not generate APP_KEY. Run: cd backend && php artisan key:generate --show"
    }
}

if (-not $AdminPassword) {
    $AdminPassword = -join ((48..57) + (65..90) + (97..122) | Get-Random -Count 24 | ForEach-Object { [char]$_ })
    Write-Host "Generated admin password (save this): $AdminPassword"
}

$hostName = ([Uri]$AppUrl).Host

$settings = @(
    "APP_NAME=EFES DMS"
    "APP_ENV=production"
    "APP_KEY=$AppKey"
    "APP_DEBUG=false"
    "APP_URL=$AppUrl"
    "APP_TIMEZONE=Asia/Tbilisi"
    "LOG_LEVEL=error"
    "SESSION_DRIVER=database"
    "SESSION_ENCRYPT=true"
    "SESSION_SECURE_COOKIE=true"
    "SESSION_DOMAIN=$hostName"
    "SANCTUM_STATEFUL_DOMAINS=$hostName"
    "QUEUE_CONNECTION=database"
    "CACHE_STORE=database"
    "FILESYSTEM_DISK=local"
    "TRUSTED_PROXIES=*"
    "MAIL_MAILER=log"
    "PRODUCTION_ADMIN_NAME=$AdminName"
    "PRODUCTION_ADMIN_EMAIL=$AdminEmail"
    "PRODUCTION_ADMIN_PASSWORD=$AdminPassword"
)

Write-Host "Applying App Service configuration to $WebAppName..."
az webapp config appsettings set `
    --resource-group $ResourceGroup `
    --name $WebAppName `
    --settings $settings `
    --output none

Write-Host "Restarting web app..."
az webapp restart --resource-group $ResourceGroup --name $WebAppName

$handoff = @"
# EFES DMS — Azure Deploy Handoff
Generated: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')

## URLs
- App: $AppUrl
- Admin: $AppUrl/admin
- Health: $AppUrl/api/health

## Login
- Email: $AdminEmail
- Password: $AdminPassword

## Azure Portal
- Resource group: $ResourceGroup
- Web app: $WebAppName
- Portal: https://portal.azure.com/#@/resource/subscriptions/$(az account show --query id -o tsv)/resourceGroups/$ResourceGroup

## APP_KEY (store securely)
$AppKey

IMPORTANT: Change the admin password after first login.
"@

$handoffPath = Join-Path $PSScriptRoot "..\..\DEPLOY_HANDOFF.local.md"
Set-Content -Path $handoffPath -Value $handoff -Encoding UTF8
Write-Host ""
Write-Host "Handoff saved to: $handoffPath"
Write-Host "Admin login: $AdminEmail / $AdminPassword"
