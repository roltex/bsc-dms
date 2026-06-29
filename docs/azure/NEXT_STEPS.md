# Your next 3 steps to go live on Azure

Code is on GitHub: https://github.com/roltex/bsc-dms

Azure CLI is installed. **You must sign in once** (browser step — we cannot do this for you).

## Step 1 — Sign in to Azure (2 minutes)

Open **PowerShell** in this project folder and run:

```powershell
az login
```

When the browser opens, sign in with your Azure subscription account.

Then run the full deploy (creates servers + database, ~25 min):

```powershell
.\infra\azure\deploy-all.ps1 -DeployAppZip
```

This saves passwords to **`DEPLOY_HANDOFF.local.md`** on your Desktop project folder (not uploaded to GitHub).

## Step 2 — Connect GitHub to Azure (5 minutes)

```powershell
.\infra\azure\setup-github-oidc.ps1
```

Copy the secrets it prints into GitHub:

https://github.com/roltex/bsc-dms/settings/secrets/actions

Also create environment **production**:

https://github.com/roltex/bsc-dms/settings/environments

Add variables shown by the script under **Variables** tab.

## Step 3 — Verify

```powershell
.\scripts\smoke-test.ps1 -BaseUrl https://app-efes-dms-prod.azurewebsites.net
```

Open in browser:

- App: https://app-efes-dms-prod.azurewebsites.net
- Admin: https://app-efes-dms-prod.azurewebsites.net/admin
- Login: see `DEPLOY_HANDOFF.local.md`

---

**Full beginner guide:** [BEGINNER_GUIDE.md](./BEGINNER_GUIDE.md)

**Azure Portal:** https://portal.azure.com → search `rg-efes-prod-weu`
