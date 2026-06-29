#Requires -Version 5.1
<#
.SYNOPSIS
  Provision Azure resources for EFES DMS using Bicep.
#>
param(
    [string]$ResourceGroup = "rg-efes-prod-weu",
    [string]$Location = "westeurope",
    [string]$ParametersFile = "$PSScriptRoot\main.parameters.json",
    [string]$MysqlPassword = ""
)

$ErrorActionPreference = "Stop"

if (-not (Get-Command az -ErrorAction SilentlyContinue)) {
    Write-Error "Azure CLI not found. Run: winget install -e --id Microsoft.AzureCLI"
}

$account = az account show 2>$null
if (-not $account) {
    Write-Host "Not logged in. Starting device-code login — open the URL shown and enter the code."
    az login --use-device-code
}

if (-not $MysqlPassword) {
    $MysqlPassword = -join ((48..57) + (65..90) + (97..122) | Get-Random -Count 32 | ForEach-Object { [char]$_ })
}

$DeploymentName = "efes-dms-$(Get-Date -Format 'yyyyMMddHHmmss')"
$AppUrl = "https://app-efes-dms-prod.azurewebsites.net"

Write-Host "==> Creating resource group: $ResourceGroup ($Location)"
az group create --name $ResourceGroup --location $Location --output none

Write-Host "==> Deploying Bicep template (20-30 min)..."
$outputs = az deployment group create `
    --resource-group $ResourceGroup `
    --name $DeploymentName `
    --template-file "$PSScriptRoot\main.bicep" `
    --parameters "@$ParametersFile" `
    --parameters location=$Location `
    --parameters mysqlAdminPassword=$MysqlPassword `
    --parameters appUrl=$AppUrl `
    --query properties.outputs `
    --output json | ConvertFrom-Json

$secretsPath = Join-Path $PSScriptRoot "..\..\DEPLOY_HANDOFF.local.md"
$secrets = @"
# EFES DMS — Infrastructure handoff
Generated: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')

## URLs
- App: $AppUrl
- Admin: $AppUrl/admin

## Azure resources
- Resource group: $ResourceGroup
- Web app: $($outputs.webAppName.value)
- MySQL host: $($outputs.mysqlServerFqdn.value)
- Key Vault: $($outputs.keyVaultName.value)

## MySQL credentials (store securely — NOT in git)
- Admin user: efesadmin
- Admin password: $MysqlPassword
- Database: efes_dms

## Next step
Run: .\infra\azure\configure-app.ps1
Then: .\infra\azure\setup-github-oidc.ps1
"@

Set-Content -Path $secretsPath -Value $secrets -Encoding UTF8

Write-Host ""
Write-Host "=== Deployment complete ==="
Write-Host "Web App:  $($outputs.webAppName.value)"
Write-Host "URL:      https://$($outputs.webAppDefaultHostName.value)"
Write-Host "MySQL:    $($outputs.mysqlServerFqdn.value)"
Write-Host "Secrets saved to: $secretsPath"
Write-Host ""
Write-Host "Next: .\infra\azure\configure-app.ps1"
