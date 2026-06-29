# GitHub Actions — Azure OIDC Setup

Configure federated credentials so GitHub Actions can deploy without storing long-lived passwords.

## 1. Create App Registration / Service Principal

```bash
SUBSCRIPTION_ID="<your-subscription-id>"
RESOURCE_GROUP="rg-efes-prod-weu"
APP_NAME="github-efes-dms-deploy"

az ad sp create-for-rbac \
  --name "$APP_NAME" \
  --role contributor \
  --scopes "/subscriptions/$SUBSCRIPTION_ID/resourceGroups/$RESOURCE_GROUP" \
  --json-auth
```

Note the `clientId`, `tenantId`, and `subscriptionId` from the output.

## 2. Configure federated credential (OIDC)

Replace `ORG`, `REPO`, and branch as needed:

```bash
APP_ID="<clientId from step 1>"

az ad app federated-credential create \
  --id "$APP_ID" \
  --parameters '{
    "name": "github-main",
    "issuer": "https://token.actions.githubusercontent.com",
    "subject": "repo:ORG/REPO:ref:refs/heads/main",
    "audiences": ["api://AzureADTokenExchange"]
  }'
```

For `environment: production` in the workflow, use:

```json
"subject": "repo:ORG/REPO:environment:production"
```

## 3. GitHub repository secrets

| Secret | Value |
|---|---|
| `AZURE_CLIENT_ID` | Service principal client ID |
| `AZURE_TENANT_ID` | Azure AD tenant ID |
| `AZURE_SUBSCRIPTION_ID` | Subscription ID |

## 4. GitHub repository variables

| Variable | Example |
|---|---|
| `AZURE_WEBAPP_NAME` | `bsc-dms` |
| `AZURE_RESOURCE_GROUP` | `rg-efes-prod-weu` |
| `AZURE_WEBAPP_HOST` | `bsc-dms.azurewebsites.net` (optional) |

## 5. GitHub environment

Create a **production** environment in GitHub → Settings → Environments (matches workflow `environment: production`).

## 6. Verify

Push to `main` or run **Deploy to Azure App Service** manually from Actions tab.
