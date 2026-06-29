# Integrations and DNS Setup

## DNS records

After Azure App Service is created, bind the custom domain in the Azure portal (App Service → Custom domains).

| Type | Name | Value |
|---|---|---|
| CNAME | `dms` (or `@` via ALIAS/ANAME if supported) | `<app-name>.azurewebsites.net` |
| TXT | `asuid.dms` | Verification ID from Azure portal |

Enable **App Service Managed Certificate** (free TLS) after domain verification.

Set App Service → Configuration:

```
APP_URL=https://dms.yourcompany.com
SANCTUM_STATEFUL_DOMAINS=dms.yourcompany.com
SESSION_DOMAIN=dms.yourcompany.com
SESSION_SECURE_COOKIE=true
```

## Google Docs OAuth

In [Google Cloud Console](https://console.cloud.google.com/) → APIs & Services → Credentials:

1. Create OAuth 2.0 Client ID (Web application)
2. Authorized redirect URIs:
   - `https://dms.yourcompany.com/api/settings/google/callback`
3. Save Client ID and Secret in Admin → System Settings (or Key Vault)

## Email DNS (SendGrid example)

If using SendGrid:

| Type | Host | Value |
|---|---|---|
| CNAME | `em####` | `u####.wl.sendgrid.net` |
| CNAME | `s1._domainkey` | `s1.domainkey.u####.wl.sendgrid.net` |
| CNAME | `s2._domainkey` | `s2.domainkey.u####.wl.sendgrid.net` |
| TXT | `@` | `v=spf1 include:sendgrid.net ~all` |

For Azure Communication Services Email, follow the domain verification steps in the ACS portal.

## Application Insights

The Bicep template creates Application Insights automatically. After deploy, verify:

- App Service → Application Insights → Connected
- Live Metrics shows requests after first traffic

## Health check

Configure App Service → Health check:

- Path: `/api/health`
- Expected: HTTP 200 with `"status":"ok"`

Laravel also exposes `/up` (framework default).

## MySQL firewall

The deploy script allows Azure services. For local admin access, add your IP in Azure Portal → MySQL Flexible Server → Networking.

## Key Vault secret references

In App Service → Configuration, reference Key Vault secrets:

```
@Microsoft.KeyVault(SecretUri=https://<vault>.vault.azure.net/secrets/APP-KEY/)
@Microsoft.KeyVault(SecretUri=https://<vault>.vault.azure.net/secrets/DB-PASSWORD/)
```

Enable **System assigned managed identity** on the App Service and grant it **Key Vault Secrets User** on the vault.
