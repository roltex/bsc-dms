# Azure Deployment Checklist

Fill in this checklist before running `infra/azure/deploy.sh`. Store secrets in Azure Key Vault — never commit them to git.

## 1. Azure subscription

| Item | Your value |
|---|---|
| Subscription ID | |
| Tenant ID | |
| Resource group name | e.g. `rg-efes-prod-weu` |
| Region | e.g. `westeurope` |
| Environment | `production` or `staging` |

## 2. Domain and TLS

| Item | Your value |
|---|---|
| Production domain | e.g. `dms.yourcompany.com` |
| DNS provider access | yes / no |
| Scheduler timezone | e.g. `Asia/Tbilisi` (deadline job runs at 08:00 local) |

## 3. Email provider (required for notifications)

Choose one: Azure Communication Services / SendGrid / SMTP

| Item | Your value |
|---|---|
| Provider | |
| MAIL_MAILER | `smtp` or `sendgrid` |
| MAIL_HOST | |
| MAIL_PORT | |
| MAIL_USERNAME | |
| MAIL_PASSWORD | Key Vault secret |
| MAIL_FROM_ADDRESS | |
| MAIL_FROM_NAME | EFES DMS |

## 4. GitHub CI/CD

| Item | Your value |
|---|---|
| Repository URL | |
| Deploy branch | `main` |
| GitHub secret `AZURE_CLIENT_ID` | Service principal app ID |
| GitHub secret `AZURE_TENANT_ID` | |
| GitHub secret `AZURE_SUBSCRIPTION_ID` | |
| GitHub variable `AZURE_WEBAPP_NAME` | e.g. `app-efes-dms-prod` |
| GitHub variable `AZURE_RESOURCE_GROUP` | e.g. `rg-efes-prod-weu` |

## 5. Integrations (day one)

| Integration | Needed? | Notes |
|---|---|---|
| Google Docs OAuth | yes / no | Update redirect URI to `https://<domain>/api/settings/google/callback` |
| OpenAI | yes / no | Set in Admin → System Settings after deploy |
| Adata API | yes / no | Set in Admin → System Settings |
| NCALayer signing | yes / no | Client-side; configure in Admin |

## 6. Sizing approval

| Resource | Recommended |
|---|---|
| App Service Plan | **B2** (Linux, PHP 8.2) |
| MySQL Flexible Server | **Burstable B1ms** (staging) / **General Purpose** (prod) |
| Azure Files share | 100 GB quota |

## 7. Fresh deploy bootstrap

| Item | Your value |
|---|---|
| Initial admin email | |
| Initial admin password | Key Vault secret |
| Initial admin name | |
| Run demo seeders? | `no` for production (use `ProductionBootstrapSeeder` only) |

## 8. Staging environment

| Item | Your value |
|---|---|
| Separate staging stack? | yes / no |
| Staging domain | e.g. `staging-dms.yourcompany.com` |

---

After completing this checklist:

1. Copy `backend/.env.azure.example` → fill values → store in Key Vault
2. Run `infra/azure/deploy.sh` (or `deploy.ps1` on Windows)
3. Configure GitHub Actions secrets/variables
4. Push to `main` to trigger deploy
5. Run `scripts/smoke-test.ps1 -BaseUrl https://<domain>`
