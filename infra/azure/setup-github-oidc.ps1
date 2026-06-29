# Create GitHub Actions OIDC federated credential for Azure deploy
# Requires: az login, gh CLI (optional for auto-setting secrets)

param(
    [string]$ResourceGroup = "rg-efes-prod-weu",
    [string]$GitHubOrg = "roltex",
    [string]$GitHubRepo = "bsc-dms",
    [string]$AppName = "github-efes-dms-deploy"
)

$ErrorActionPreference = "Stop"

if (-not (Get-Command az -ErrorAction SilentlyContinue)) {
    Write-Error "Azure CLI not found."
}

$subscriptionId = az account show --query id -o tsv
$scope = "/subscriptions/$subscriptionId/resourceGroups/$ResourceGroup"

Write-Host "Creating service principal with Contributor on $ResourceGroup..."
$spJson = az ad sp create-for-rbac --name $AppName --role contributor --scopes $scope --json-auth | ConvertFrom-Json

$clientId = $spJson.clientId
$tenantId = $spJson.tenantId

Write-Host "Client ID: $clientId"
Write-Host "Tenant ID: $tenantId"

# Get app object id for federated credential
$appId = az ad app list --filter "appId eq '$clientId'" --query "[0].id" -o tsv

$credName = "github-main-production"
$subject = "repo:${GitHubOrg}/${GitHubRepo}:environment:production"

Write-Host "Adding federated credential for: $subject"
az ad app federated-credential create --id $appId --parameters "{
  `"name`": `"$credName`",
  `"issuer`": `"https://token.actions.githubusercontent.com`",
  `"subject`": `"$subject`",
  `"audiences`": [`"api://AzureADTokenExchange`"]
}" 2>$null

if ($LASTEXITCODE -ne 0) {
    Write-Host "Federated credential may already exist — continuing."
}

Write-Host ""
Write-Host "=== Add these to GitHub: $GitHubOrg/$GitHubRepo ==="
Write-Host ""
Write-Host "Secrets (Settings -> Secrets and variables -> Actions):"
Write-Host "  AZURE_CLIENT_ID       = $clientId"
Write-Host "  AZURE_TENANT_ID       = $tenantId"
Write-Host "  AZURE_SUBSCRIPTION_ID = $subscriptionId"
Write-Host ""
Write-Host "Variables:"
Write-Host "  AZURE_WEBAPP_NAME     = bsc-dms"
Write-Host "  AZURE_RESOURCE_GROUP  = $ResourceGroup"
Write-Host "  AZURE_WEBAPP_HOST     = bsc-dms.azurewebsites.net"
Write-Host ""
Write-Host "Environment: create 'production' under Settings -> Environments"
Write-Host ""

if (Get-Command gh -ErrorAction SilentlyContinue) {
    $confirm = Read-Host "Set GitHub secrets automatically with gh CLI? (y/n)"
    if ($confirm -eq 'y') {
        gh secret set AZURE_CLIENT_ID --body $clientId -R "$GitHubOrg/$GitHubRepo"
        gh secret set AZURE_TENANT_ID --body $tenantId -R "$GitHubOrg/$GitHubRepo"
        gh secret set AZURE_SUBSCRIPTION_ID --body $subscriptionId -R "$GitHubOrg/$GitHubRepo"
        gh variable set AZURE_WEBAPP_NAME --body "bsc-dms" -R "$GitHubOrg/$GitHubRepo"
        gh variable set AZURE_RESOURCE_GROUP --body $ResourceGroup -R "$GitHubOrg/$GitHubRepo"
        gh variable set AZURE_WEBAPP_HOST --body "bsc-dms.azurewebsites.net" -R "$GitHubOrg/$GitHubRepo"
        Write-Host "GitHub secrets and variables set."
    }
}
