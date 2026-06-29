# Azure Production Deployment

**New to Azure?** Start with [BEGINNER_GUIDE.md](./BEGINNER_GUIDE.md).

## Quick start

1. Complete [DEPLOYMENT_CHECKLIST.md](./DEPLOYMENT_CHECKLIST.md)
2. Edit [main.parameters.json](../../infra/azure/main.parameters.json) (MySQL password, region, SKU)
3. Provision infrastructure:
   ```bash
   cd infra/azure
   ./deploy.sh
   ```
4. Configure [GitHub Actions OIDC](./GITHUB_ACTIONS_SETUP.md)
5. Set App Service configuration from [backend/.env.azure.example](../../backend/.env.azure.example)
6. Push to `main` — GitHub Actions builds and deploys
7. Run smoke tests: `scripts/smoke-test.ps1 -BaseUrl https://<your-app>.azurewebsites.net`

## Architecture

- **App Service** (Linux, PHP 8.2) — Laravel API + React SPA
- **Azure Database for MySQL** — primary database
- **Azure Files** — persistent document storage (mounted to `storage/app/private`)
- **Key Vault** — secrets (wire via App Service Key Vault references)
- **Application Insights** — monitoring
- **GitHub Actions** — CI/CD

## Files

| Path | Purpose |
|---|---|
| `infra/azure/main.bicep` | Infrastructure as Code |
| `infra/azure/deploy.sh` | Provision script (bash) |
| `backend/infra/azure/startup.sh` | App Service startup (migrate, queue, scheduler) |
| `backend/.env.azure.example` | Production env template |
| `scripts/build-deploy-package.sh` | Build deploy.zip |
| `scripts/smoke-test.ps1` | Post-deploy verification |
| `.github/workflows/azure-deploy.yml` | CI/CD pipeline |
| `INTEGRATIONS_DNS.md` | Domain, OAuth, email DNS |
| `GITHUB_ACTIONS_SETUP.md` | OIDC service principal setup |

## Manual deploy (without GitHub Actions)

```bash
cd frontend && npm ci && npm run build && cd ..
cd backend && composer install --no-dev --optimize-autoloader && cd ..
bash scripts/build-deploy-package.sh
az webapp deploy --resource-group rg-efes-prod-weu --name app-efes-dms-prod --src-path deploy.zip --type zip
```
