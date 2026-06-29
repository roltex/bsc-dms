#!/usr/bin/env bash
# Provision Azure resources for EFES DMS.
# Prerequisites: az CLI logged in (az login), jq optional.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"

RESOURCE_GROUP="${RESOURCE_GROUP:-rg-efes-prod-weu}"
LOCATION="${LOCATION:-westeurope}"
DEPLOYMENT_NAME="${DEPLOYMENT_NAME:-efes-dms-$(date +%Y%m%d%H%M%S)}"

echo "==> Creating resource group: $RESOURCE_GROUP ($LOCATION)"
az group create --name "$RESOURCE_GROUP" --location "$LOCATION" --output none

echo "==> Deploying Bicep template..."
DEPLOYMENT_OUTPUT=$(az deployment group create \
  --resource-group "$RESOURCE_GROUP" \
  --name "$DEPLOYMENT_NAME" \
  --template-file "$SCRIPT_DIR/main.bicep" \
  --parameters "@$SCRIPT_DIR/main.parameters.json" \
  --parameters location="$LOCATION" \
  --query properties.outputs \
  --output json)

WEBAPP_NAME=$(echo "$DEPLOYMENT_OUTPUT" | jq -r '.webAppName.value')
WEBAPP_HOST=$(echo "$DEPLOYMENT_OUTPUT" | jq -r '.webAppDefaultHostName.value')
MYSQL_FQDN=$(echo "$DEPLOYMENT_OUTPUT" | jq -r '.mysqlServerFqdn.value')
KEYVAULT=$(echo "$DEPLOYMENT_OUTPUT" | jq -r '.keyVaultName.value')

echo ""
echo "=== Deployment complete ==="
echo "Web App:     $WEBAPP_NAME"
echo "URL:         https://$WEBAPP_HOST"
echo "MySQL:       $MYSQL_FQDN"
echo "Key Vault:   $KEYVAULT"
echo ""
echo "Next steps:"
echo "  1. Generate APP_KEY: php artisan key:generate --show"
echo "  2. Store secrets in Key Vault"
echo "  3. Configure GitHub Actions variables (AZURE_WEBAPP_NAME=$WEBAPP_NAME, AZURE_RESOURCE_GROUP=$RESOURCE_GROUP)"
echo "  4. Deploy app: push to main or run GitHub Actions workflow"
echo "  5. Complete docs/azure/DEPLOYMENT_CHECKLIST.md"
