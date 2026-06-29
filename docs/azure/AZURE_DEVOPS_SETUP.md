# Azure DevOps setup — posbsc/bsc-dms

Your project: [dev.azure.com/posbsc/bsc-dms](https://dev.azure.com/posbsc/bsc-dms)  
Git repo: [bsc-dms](https://dev.azure.com/posbsc/bsc-dms/_git/bsc-dms)

Azure DevOps handles **build and deploy pipelines**. You still need an **Azure subscription** to create App Service + MySQL (see [BEGINNER_GUIDE.md](./BEGINNER_GUIDE.md)).

---

## Step 1 — Push code to Azure DevOps

If your code is still only on GitHub, add DevOps as a remote and push:

```powershell
cd "C:\Users\My Computer\Desktop\efes"
git remote add devops https://dev.azure.com/posbsc/bsc-dms/_git/bsc-dms
git push devops main
```

When prompted, sign in with **roland.esakia@bsc.ge**.

If you already imported the repo in DevOps, make sure `azure-pipelines.yml` is on the **main** branch.

---

## Step 2 — Get an Azure subscription (if not done)

Your login shows **no subscription** yet. Ask BSC IT for **Contributor** on an Azure subscription, then verify:

```powershell
az login -u roland.esakia@bsc.ge
az account list -o table
```

Provision hosting **once** (from your PC):

```powershell
cd infra\azure
.\deploy-all.ps1 -DeployAppZip
```

This creates App Service **`bsc-dms`** → https://bsc-dms.azurewebsites.net

---

## Step 3 — Service connection in DevOps

1. Open [Project settings](https://dev.azure.com/posbsc/bsc-dms/_settings/adminservices) → **Service connections**
2. **New service connection** → **Azure Resource Manager**
3. **Service principal (automatic)** → select your **subscription** and resource group `rg-efes-prod-weu`
4. Name it exactly: **`BSC-Azure-Connection`** (or note your name for Step 4)

Grant access to all pipelines when asked.

---

## Step 4 — Create the pipeline

1. [Pipelines](https://dev.azure.com/posbsc/bsc-dms/_build) → **New pipeline**
2. **Azure Repos Git** → select **bsc-dms**
3. **Existing Azure Pipelines YAML file** → branch `main` → `/azure-pipelines.yml`
4. **Run**

Before first run, set the pipeline variable:

| Name | Value |
|------|--------|
| `azureServiceConnection` | `BSC-Azure-Connection` (name from Step 3) |

Pipeline → **Edit** → **Variables** → **New variable** → save.

---

## Step 5 — First login after deploy

Credentials are written locally when you run `configure-app.ps1` → see **`DEPLOY_HANDOFF.local.md`** on your machine.

- App: https://bsc-dms.azurewebsites.net  
- Admin: https://bsc-dms.azurewebsites.net/admin  

---

## GitHub vs Azure DevOps

| | GitHub Actions | Azure DevOps |
|---|---|---|
| Config file | `.github/workflows/azure-deploy.yml` | `azure-pipelines.yml` |
| Repo | github.com/roltex/bsc-dms | dev.azure.com/posbsc/bsc-dms |
| Use both? | Optional — pick one for deploy |

You only need **one** CI/CD path; DevOps is a good fit if BSC standardizes on [posbsc](https://dev.azure.com/posbsc).

---

## Troubleshooting

**Pipeline fails: azureSubscription not found**  
→ Set variable `azureServiceConnection` to your service connection name.

**Deploy succeeds but site 503**  
→ App Service → Configuration: check `APP_KEY`, restart app. Run `configure-app.ps1` if not done.

**No subscription in service connection wizard**  
→ IT must assign Azure subscription access first (tenant BSC LLC).
