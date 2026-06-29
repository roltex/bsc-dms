@description('Azure region, e.g. westeurope')
param location string = resourceGroup().location

@description('Environment suffix: prod or staging')
@allowed(['prod', 'staging'])
param environment string = 'prod'

@description('Base name prefix for all resources')
param baseName string = 'efes-dms'

@description('MySQL administrator login')
param mysqlAdminLogin string = 'efesadmin'

@secure()
param mysqlAdminPassword string

@description('App Service plan SKU, e.g. B2')
param appServicePlanSku string = 'B2'

@description('MySQL SKU name, e.g. Standard_B1ms')
param mysqlSkuName string = 'Standard_B1ms'

@description('MySQL storage size in GB')
param mysqlStorageSizeGB int = 32

@description('Application URL for CORS/Sanctum (no trailing slash)')
param appUrl string = 'https://changeme.azurewebsites.net'

var uniqueSuffix = uniqueString(resourceGroup().id)
var appName = 'app-${baseName}-${environment}'
var planName = 'plan-${baseName}-${environment}'
var mysqlServerName = 'mysql-${baseName}-${environment}-${uniqueSuffix}'
var storageAccountName = take(replace('st${baseName}${environment}${uniqueSuffix}', '-', ''), 24)
var fileShareName = 'laravel-storage'
var keyVaultName = take('kv-${baseName}-${environment}-${uniqueSuffix}', 24)
var appInsightsName = 'ai-${baseName}-${environment}'
var mysqlDatabaseName = 'efes_dms'
var mysqlAppUser = 'efes_app'

resource appInsights 'Microsoft.Insights/components@2020-02-02' = {
  name: appInsightsName
  location: location
  kind: 'web'
  properties: {
    Application_Type: 'web'
    Request_Source: 'rest'
  }
}

resource storageAccount 'Microsoft.Storage/storageAccounts@2023-01-01' = {
  name: storageAccountName
  location: location
  sku: {
    name: 'Standard_LRS'
  }
  kind: 'StorageV2'
  properties: {
    minimumTlsVersion: 'TLS1_2'
    supportsHttpsTrafficOnly: true
  }
}

resource fileService 'Microsoft.Storage/storageAccounts/fileServices@2023-01-01' = {
  parent: storageAccount
  name: 'default'
}

resource fileShare 'Microsoft.Storage/storageAccounts/fileServices/shares@2023-01-01' = {
  parent: fileService
  name: fileShareName
  properties: {
    shareQuota: 100
  }
}

resource keyVault 'Microsoft.KeyVault/vaults@2023-07-01' = {
  name: keyVaultName
  location: location
  properties: {
    sku: {
      family: 'A'
      name: 'standard'
    }
    tenantId: subscription().tenantId
    enableRbacAuthorization: true
    enabledForTemplateDeployment: true
  }
}

resource mysqlServer 'Microsoft.DBforMySQL/flexibleServers@2023-12-30' = {
  name: mysqlServerName
  location: location
  sku: {
    name: mysqlSkuName
    tier: 'Burstable'
  }
  properties: {
    administratorLogin: mysqlAdminLogin
    administratorLoginPassword: mysqlAdminPassword
    version: '8.0.21'
    storage: {
      storageSizeGB: mysqlStorageSizeGB
      autoGrow: 'Enabled'
    }
    backup: {
      backupRetentionDays: 7
      geoRedundantBackup: 'Disabled'
    }
    highAvailability: {
      mode: 'Disabled'
    }
  }
}

resource mysqlFirewallAzure 'Microsoft.DBforMySQL/flexibleServers/firewallRules@2023-12-30' = {
  parent: mysqlServer
  name: 'AllowAzureServices'
  properties: {
    startIpAddress: '0.0.0.0'
    endIpAddress: '0.0.0.0'
  }
}

resource mysqlDatabase 'Microsoft.DBforMySQL/flexibleServers/databases@2023-12-30' = {
  parent: mysqlServer
  name: mysqlDatabaseName
  properties: {
    charset: 'utf8mb4'
    collation: 'utf8mb4_unicode_ci'
  }
}

resource appServicePlan 'Microsoft.Web/serverfarms@2023-01-01' = {
  name: planName
  location: location
  sku: {
    name: appServicePlanSku
    tier: startsWith(appServicePlanSku, 'B') ? 'Basic' : 'Standard'
  }
  kind: 'linux'
  properties: {
    reserved: true
  }
}

resource webApp 'Microsoft.Web/sites@2023-01-01' = {
  name: appName
  location: location
  kind: 'app,linux'
  identity: {
    type: 'SystemAssigned'
  }
  properties: {
    serverFarmId: appServicePlan.id
    httpsOnly: true
    siteConfig: {
      linuxFxVersion: 'PHP|8.2'
      alwaysOn: true
      ftpsState: 'Disabled'
      minTlsVersion: '1.2'
      http20Enabled: true
      appCommandLine: 'bash /home/site/wwwroot/infra/azure/startup.sh'
      healthCheckPath: '/api/health'
      appSettings: [
        {
          name: 'WEBSITE_DOCUMENT_ROOT'
          value: '/home/site/wwwroot/public'
        }
        {
          name: 'WEBSITES_ENABLE_APP_SERVICE_STORAGE'
          value: 'true'
        }
        {
          name: 'SCM_DO_BUILD_DURING_DEPLOYMENT'
          value: 'false'
        }
        {
          name: 'APP_ENV'
          value: 'production'
        }
        {
          name: 'APP_DEBUG'
          value: 'false'
        }
        {
          name: 'APP_URL'
          value: appUrl
        }
        {
          name: 'DB_CONNECTION'
          value: 'mysql'
        }
        {
          name: 'DB_HOST'
          value: mysqlServer.properties.fullyQualifiedDomainName
        }
        {
          name: 'DB_PORT'
          value: '3306'
        }
        {
          name: 'DB_DATABASE'
          value: mysqlDatabaseName
        }
        {
          name: 'DB_USERNAME'
          value: mysqlAdminLogin
        }
        {
          name: 'DB_PASSWORD'
          value: mysqlAdminPassword
        }
        {
          name: 'SESSION_DRIVER'
          value: 'database'
        }
        {
          name: 'SESSION_SECURE_COOKIE'
          value: 'true'
        }
        {
          name: 'SESSION_ENCRYPT'
          value: 'true'
        }
        {
          name: 'QUEUE_CONNECTION'
          value: 'database'
        }
        {
          name: 'CACHE_STORE'
          value: 'database'
        }
        {
          name: 'FILESYSTEM_DISK'
          value: 'local'
        }
        {
          name: 'LOG_LEVEL'
          value: 'error'
        }
        {
          name: 'TRUSTED_PROXIES'
          value: '*'
        }
        {
          name: 'APPLICATIONINSIGHTS_CONNECTION_STRING'
          value: appInsights.properties.ConnectionString
        }
        {
          name: 'APPINSIGHTS_INSTRUMENTATIONKEY'
          value: appInsights.properties.InstrumentationKey
        }
        {
          name: 'AZURE_FILES_MOUNT_PATH'
          value: '/home/laravel-storage'
        }
      ]
      azureStorageAccounts: {
        LaravelStorage: {
          type: 'AzureFiles'
          accountName: storageAccount.name
          shareName: fileShareName
          accessKey: storageAccount.listKeys().keys[0].value
          mountPath: '/home/laravel-storage'
        }
      }
    }
  }
}

output webAppName string = webApp.name
output webAppDefaultHostName string = webApp.properties.defaultHostName
output mysqlServerFqdn string = mysqlServer.properties.fullyQualifiedDomainName
output keyVaultName string = keyVault.name
output storageAccountName string = storageAccount.name
output appInsightsConnectionString string = appInsights.properties.ConnectionString
output appInsightsInstrumentationKey string = appInsights.properties.InstrumentationKey
