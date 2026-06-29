# Azure Beginner Guide — EFES DMS

This guide assumes you have never used Azure before. Follow the phases in order.

## What is Azure?

Microsoft Azure is a cloud platform where you rent servers, databases, and storage instead of running them on your own computer or Hostinger. You pay monthly for what you use.

For EFES DMS we use four main services:

| Service | Plain English |
|---|---|
| **App Service** | Your website server (runs PHP + React) |
| **MySQL Flexible Server** | Your database (users, tasks, documents metadata) |
| **Azure Files** | Folder in the cloud for uploaded document files |
| **Application Insights** | Error and performance monitoring |

Your app URL (first launch): **https://bsc-dms.azurewebsites.net**

---

## Monthly cost estimate

| Resource | Tier | Approx. cost |
|---|---|---|
| App Service Plan | B2 Linux | $25–55 USD |
| MySQL | Burstable B1ms | $12–25 USD |
| Storage + Files | 100 GB | $5–15 USD |
| Application Insights | Basic | $0–10 USD |

**Total: roughly $45–90 USD/month.** Set a **budget alert** in Azure Portal → Cost Management → Budgets (recommended: $100/month).

To reduce cost later you can scale down the App Service plan when traffic is low.

---

## Glossary

| Term | Meaning |
|---|---|
| **Subscription** | Your billing account. Everything you create belongs to one subscription. |
| **Resource group** | A folder for related resources. Deleting the group deletes everything inside. |
| **Region** | Physical datacenter location (e.g. West Europe). Pick one close to your users. |
| **App Service Plan** | The CPU/RAM size behind your web app. |
| **Web App** | The actual site URL (*.azurewebsites.net). |
| **Configuration** | Environment variables — same idea as `.env` in Laravel. |
| **Deployment Center** | Shows deploy history from GitHub. |
| **Log stream** | Live server logs for debugging. |
| **Key Vault** | Secure storage for passwords and API keys. |

---

## Phase A — Install tools (one time)

### 1. Azure CLI

Windows (PowerShell as Administrator):

```powershell
winget install -e --id Microsoft.AzureCLI
```

Close and reopen PowerShell, then verify:

```powershell
az --version
```

### 2. Sign in to Azure

```powershell
az login
```

A browser opens. Sign in with the Microsoft account linked to your Azure subscription.

Verify and copy your Subscription ID:

```powershell
az account show --query "{name:name, id:id}" -o table
```

Save the **id** — you need it for GitHub Actions.

### 3. Git (if not installed)

```powershell
winget install -e --id Git.Git
```

---

## Phase B — Create cloud resources

From the project folder:

```powershell
cd infra\azure
.\deploy.ps1
```

This takes **20–30 minutes**. It creates:

- Resource group: `rg-efes-prod-weu`
- Web app: `bsc-dms`
- MySQL database: `efes_dms`
- File storage for documents
- Key Vault and Application Insights

Watch progress in [Azure Portal](https://portal.azure.com) → **Resource groups** → `rg-efes-prod-weu`.

---

## Phase C — Azure Portal tour (after deploy)

1. Open [https://portal.azure.com](https://portal.azure.com)
2. Search **bsc-dms** → click the Web App
3. Important blades:
   - **Overview** — Default URL, Restart button
   - **Configuration** — All environment variables (`APP_KEY`, database, admin bootstrap)
   - **Deployment Center** — GitHub deploy history
   - **Log stream** — Live PHP/Laravel logs
   - **Diagnose and solve problems** — Health checks

### Restart the app

Overview → **Restart** (after changing Configuration).

### View logs

Monitoring → **Log stream** (enable Application Logging if prompted).

---

## Phase D — GitHub automatic deploy

Code lives at: [github.com/roltex/bsc-dms](https://github.com/roltex/bsc-dms)

Every push to `main` runs GitHub Actions → builds frontend → deploys to Azure.

### Required GitHub secrets

Repository → **Settings** → **Secrets and variables** → **Actions**:

| Secret | Where to get it |
|---|---|
| `AZURE_CLIENT_ID` | Created by OIDC setup script (see GITHUB_ACTIONS_SETUP.md) |
| `AZURE_TENANT_ID` | `az account show --query tenantId -o tsv` |
| `AZURE_SUBSCRIPTION_ID` | `az account show --query id -o tsv` |

### Required GitHub variables

**Settings** → **Secrets and variables** → **Actions** → **Variables**:

| Variable | Value |
|---|---|
| `AZURE_WEBAPP_NAME` | `bsc-dms` |
| `AZURE_RESOURCE_GROUP` | `rg-efes-prod-weu` |
| `AZURE_WEBAPP_HOST` | `bsc-dms.azurewebsites.net` |

Create a **production** environment: Settings → Environments → New → name `production`.

---

## Phase E — First login

1. Open **https://bsc-dms.azurewebsites.net**
2. Log in with the admin email and password from your deploy handoff (see `DEPLOY_HANDOFF.local.md` on your machine — not in git)
3. Admin panel: **https://bsc-dms.azurewebsites.net/admin**
4. Configure workflows, templates, and settings in the admin UI (fresh database — no data from Hostinger)

### Health check

```powershell
.\scripts\smoke-test.ps1 -BaseUrl https://bsc-dms.azurewebsites.net
```

Or open in browser: `/api/health` — should show `"status":"ok"`.

---

## Phase F — Add later (optional)

| Feature | Guide |
|---|---|
| Custom domain + HTTPS | [INTEGRATIONS_DNS.md](./INTEGRATIONS_DNS.md) |
| Email notifications | Add SMTP/SendGrid in App Service Configuration |
| Google Docs editing | Admin → Settings + Google Cloud OAuth redirect URI |
| OpenAI / Adata | Admin → System Settings |

---

## Troubleshooting

### Site shows 503 or "Application Error"

1. Portal → App Service → **Log stream**
2. Check Configuration has `APP_KEY` set
3. Restart the app

### Database connection failed

1. Portal → MySQL server → **Networking** — ensure Azure services allowed
2. Verify `DB_HOST`, `DB_PASSWORD` in App Service Configuration

### GitHub Actions deploy failed

1. GitHub → **Actions** tab → click failed run → read error
2. Verify all secrets and variables are set
3. Verify `production` environment exists

### Documents disappear after restart

Azure Files mount may be missing. Check startup logs in **Log stream** for `[startup] Linked ... storage/app/private`.

---

## Useful commands

```powershell
# Show current subscription
az account show -o table

# List resources in your group
az resource list -g rg-efes-prod-weu -o table

# Restart web app
az webapp restart -g rg-efes-prod-weu -n bsc-dms

# Stream logs
az webapp log tail -g rg-efes-prod-weu -n bsc-dms

# Manual deploy (without GitHub)
cd frontend; npm ci; npm run build; cd ..
cd backend; composer install --no-dev --optimize-autoloader; cd ..
bash scripts/build-deploy-package.sh
az webapp deploy -g rg-efes-prod-weu -n bsc-dms --src-path deploy.zip --type zip
```

---

## Related docs

- [DEPLOYMENT_CHECKLIST.md](./DEPLOYMENT_CHECKLIST.md) — values to fill before deploy
- [GITHUB_ACTIONS_SETUP.md](./GITHUB_ACTIONS_SETUP.md) — OIDC service principal
- [INTEGRATIONS_DNS.md](./INTEGRATIONS_DNS.md) — domain, email, OAuth
- [README.md](./README.md) — technical quick start
