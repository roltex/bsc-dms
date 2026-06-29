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
$BscTenantId = "4290f8e5-a249-4a90-a17c-942ba30aaa42"

function Invoke-Az {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$AzArgs)
    $prev = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    $output = & az @AzArgs 2>&1
    $exit = $LASTEXITCODE
    $ErrorActionPreference = $prev
    if ($exit -ne 0) {
        $text = ($output | ForEach-Object { $_.ToString() }) -join [Environment]::NewLine
        throw $text.Trim()
    }
    return $output
}

function Test-AzManagementLogin {
    $prev = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    $null = & az account get-access-token --resource https://management.core.windows.net/ --output none 2>&1
    $ok = $LASTEXITCODE -eq 0
    $ErrorActionPreference = $prev
    return $ok
}

function Start-AzBrowserLogin {
    Write-Host "Opening browser for Azure MFA sign-in (no password prompt)..."
    $prev = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    & az logout 2>$null | Out-Null
    $ErrorActionPreference = $prev
    Invoke-Az login --tenant $BscTenantId
}

function Ensure-AzManagementLogin {
    if (Test-AzManagementLogin) { return }
    Start-AzBrowserLogin
    if (-not (Test-AzManagementLogin)) {
        Write-Error "Browser login did not grant management access. Try Azure Cloud Shell (portal.azure.com > Cloud Shell)."
    }
}

function Invoke-AzManagement {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$AzArgs)
    try {
        return Invoke-Az @AzArgs
    } catch {
        $msg = $_.Exception.Message
        if ($msg -notmatch 'authenticate|MFA|AADSTS|InteractionRequired|invalid_grant|claims-challenge|WARNING: Run the command below') {
            throw
        }
        Write-Host "Resource change requires MFA step-up. Retrying after browser login..."
        Start-AzBrowserLogin
        if (-not (Test-AzManagementLogin)) {
            Write-Error "Browser login did not grant management access. Try Azure Cloud Shell (portal.azure.com > Cloud Shell)."
        }
        return Invoke-Az @AzArgs
    }
}

if (-not (Get-Command az -ErrorAction SilentlyContinue)) {
    Write-Error "Azure CLI not found. Run: winget install -e --id Microsoft.AzureCLI"
}

Ensure-AzManagementLogin

if (-not $MysqlPassword) {
    $MysqlPassword = -join ((48..57) + (65..90) + (97..122) | Get-Random -Count 32 | ForEach-Object { [char]$_ })
}

$DeploymentName = "efes-dms-$(Get-Date -Format 'yyyyMMddHHmmss')"
$AppUrl = "https://bsc-dms.azurewebsites.net"

Write-Host "==> Creating resource group: $ResourceGroup in $Location"
Invoke-AzManagement group create --name $ResourceGroup --location $Location --output none

Write-Host "==> Deploying Bicep template - this takes about 20 to 30 minutes"
$parametersArg = '@' + $ParametersFile
$deployJson = Invoke-AzManagement deployment group create `
    --resource-group $ResourceGroup `
    --name $DeploymentName `
    --template-file "$PSScriptRoot\main.bicep" `
    --parameters $parametersArg `
    --parameters location=$Location `
    --parameters mysqlAdminPassword=$MysqlPassword `
    --parameters appUrl=$AppUrl `
    --query properties.outputs `
    --output json

$outputs = ($deployJson | Out-String).Trim() | ConvertFrom-Json

$secretsPath = Join-Path $PSScriptRoot "..\..\DEPLOY_HANDOFF.local.md"
$generatedAt = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
$webAppName = $outputs.webAppName.value
$mysqlHost = $outputs.mysqlServerFqdn.value
$keyVault = $outputs.keyVaultName.value
$defaultHost = $outputs.webAppDefaultHostName.value

$secretsLines = @(
    "# EFES DMS - Infrastructure handoff"
    "Generated: $generatedAt"
    ""
    "## URLs"
    "App: $AppUrl"
    "Admin: $AppUrl/admin"
    ""
    "## Azure resources"
    "Resource group: $ResourceGroup"
    "Web app: $webAppName"
    "MySQL host: $mysqlHost"
    "Key Vault: $keyVault"
    ""
    "## MySQL credentials - store securely, NOT in git"
    "Admin user: efesadmin"
    "Admin password: $MysqlPassword"
    "Database: efes_dms"
    ""
    "## Next step"
    "Run: .\infra\azure\configure-app.ps1"
    "Then: .\infra\azure\setup-github-oidc.ps1"
)

Set-Content -Path $secretsPath -Value ($secretsLines -join [Environment]::NewLine) -Encoding UTF8

Write-Host ""
Write-Host "=== Deployment complete ==="
Write-Host "Web App:  $webAppName"
Write-Host "URL:      https://$defaultHost"
Write-Host "MySQL:    $mysqlHost"
Write-Host "Secrets saved to: $secretsPath"
Write-Host ""
Write-Host "Next: .\infra\azure\configure-app.ps1"
