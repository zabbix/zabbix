
# Azure by HTTP

## Overview

This template is designed to monitor Microsoft Azure by HTTP.
It works without any external scripts and uses the script item.
Currently the template supports the discovery of Virtual Machines (VMs), Storage accounts, Microsoft SQL, MySQL, and PostgreSQL servers.

## Requirements

For Zabbix version: 6.4 and higher.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

> See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure the macros: `{$AZURE.APP.ID}`, `{$AZURE.PASSWORD}`, `{$AZURE.TENANT.ID}`, and `{$AZURE.SUBSCRIPTION.ID}`.

## Configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.APP.ID} |<p>The App ID of Microsoft Azure.</p> |`` |
|{$AZURE.DATA.TIMEOUT} |<p>A response timeout for an API.</p> |`15s` |
|{$AZURE.MSSQL.DB.LOCATION.MATCHES} |<p>This macro is used in Microsoft SQL databases discovery rule.</p> |`.*` |
|{$AZURE.MSSQL.DB.LOCATION.NOT.MATCHES} |<p>This macro is used in Microsoft SQL databases discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$AZURE.MSSQL.DB.NAME.MATCHES} |<p>This macro is used in Microsoft SQL databases discovery rule.</p> |`.*` |
|{$AZURE.MSSQL.DB.NAME.NOT.MATCHES} |<p>This macro is used in Microsoft SQL databases discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$AZURE.MSSQL.DB.SIZE.NOT.MATCHES} |<p>This macro is used in Microsoft SQL databases discovery rule.</p> |`^System$` |
|{$AZURE.MYSQL.DB.LOCATION.MATCHES} |<p>This macro is used in MySQL servers discovery rule.</p> |`.*` |
|{$AZURE.MYSQL.DB.LOCATION.NOT.MATCHES} |<p>This macro is used in MySQL servers discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$AZURE.MYSQL.DB.NAME.MATCHES} |<p>This macro is used in MySQL servers discovery rule.</p> |`.*` |
|{$AZURE.MYSQL.DB.NAME.NOT.MATCHES} |<p>This macro is used in MySQL servers discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$AZURE.PASSWORD} |<p>Microsoft Azure password.</p> |`` |
|{$AZURE.PGSQL.DB.LOCATION.MATCHES} |<p>This macro is used in PostgreSQL servers discovery rule.</p> |`.*` |
|{$AZURE.PGSQL.DB.LOCATION.NOT.MATCHES} |<p>This macro is used in PostgreSQL servers discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$AZURE.PGSQL.DB.NAME.MATCHES} |<p>This macro is used in PostgreSQL servers discovery rule.</p> |`.*` |
|{$AZURE.PGSQL.DB.NAME.NOT.MATCHES} |<p>This macro is used in PostgreSQL servers discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$AZURE.RESOURCE_GROUP.MATCHES} |<p>This macro is used in discovery rules.</p> |`.*` |
|{$AZURE.RESOURCE_GROUP.NOT.MATCHES} |<p>This macro is used in discovery rules.</p> |`CHANGE_IF_NEEDED` |
|{$AZURE.STORAGE.ACC.AVAILABILITY} |<p>The warning threshold of the storage account availability.</p> |`70` |
|{$AZURE.STORAGE.ACC.BLOB.AVAILABILITY} |<p>The warning threshold of the storage account blob services availability.</p> |`70` |
|{$AZURE.STORAGE.ACC.LOCATION.MATCHES} |<p>This macro is used in storage accounts discovery rule.</p> |`.*` |
|{$AZURE.STORAGE.ACC.LOCATION.NOT.MATCHES} |<p>This macro is used in storage accounts discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$AZURE.STORAGE.ACC.NAME.MATCHES} |<p>This macro is used in storage accounts discovery rule.</p> |`.*` |
|{$AZURE.STORAGE.ACC.NAME.NOT.MATCHES} |<p>This macro is used in storage accounts discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$AZURE.STORAGE.ACC.TABLE.AVAILABILITY} |<p>The warning threshold of the storage account table services availability.</p> |`70` |
|{$AZURE.SUBSCRIPTION.ID} |<p>Microsoft Azure subscription ID.</p> |`` |
|{$AZURE.TENANT.ID} |<p>Microsoft Azure tenant ID.</p> |`` |
|{$AZURE.VM.LOCATION.MATCHES} |<p>This macro is used in virtual machines discovery rule.</p> |`.*` |
|{$AZURE.VM.LOCATION.NOT.MATCHES} |<p>This macro is used in virtual machines discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$AZURE.VM.NAME.MATCHES} |<p>This macro is used in virtual machines discovery rule.</p> |`.*` |
|{$AZURE.VM.NAME.NOT.MATCHES} |<p>This macro is used in virtual machines discovery rule.</p> |`CHANGE_IF_NEEDED` |

### Template links

There are no template links in this template.

### Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Microsoft SQL databases discovery |<p>The list of the Microsoft SQL databases is provided by the subscription.</p> |DEPENDENT |azure.mssql.databases.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.resources.value`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p><p>**Filter**:</p>AND <p>- {#TYPE} MATCHES_REGEX `^Microsoft.Sql/servers/databases`</p><p>- {#NAME} MATCHES_REGEX `{$AZURE.MSSQL.DB.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$AZURE.MSSQL.DB.NAME.NOT.MATCHES}`</p><p>- {#LOCATION} MATCHES_REGEX `{$AZURE.MSSQL.DB.LOCATION.MATCHES}`</p><p>- {#LOCATION} NOT_MATCHES_REGEX `{$AZURE.MSSQL.DB.LOCATION.NOT.MATCHES}`</p><p>- {#GROUP} MATCHES_REGEX `{$AZURE.RESOURCE_GROUP.MATCHES}`</p><p>- {#GROUP} NOT_MATCHES_REGEX `{$AZURE.RESOURCE_GROUP.NOT.MATCHES}`</p><p>- {#SIZE} NOT_MATCHES_REGEX `{$AZURE.MSSQL.DB.SIZE.NOT.MATCHES}`</p><p>**Overrides:**</p><p>Serverless<br> - {#VERSION} MATCHES_REGEX `^.*serverless$`<br>  - HOST_PROTOTYPE REGEXP ``</p><p>Server<br> - {#VERSION} MATCHES_REGEX `^((?!serverless).)*$`<br>  - HOST_PROTOTYPE REGEXP ``</p> |
|MySQL servers discovery |<p>The list of the MySQL servers is provided by the subscription.</p> |DEPENDENT |azure.mysql.servers.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.resources.value`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p><p>**Filter**:</p>AND <p>- {#TYPE} MATCHES_REGEX `^Microsoft.DBforMySQL`</p><p>- {#NAME} MATCHES_REGEX `{$AZURE.MYSQL.DB.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$AZURE.MYSQL.DB.NAME.NOT.MATCHES}`</p><p>- {#LOCATION} MATCHES_REGEX `{$AZURE.MYSQL.DB.LOCATION.MATCHES}`</p><p>- {#LOCATION} NOT_MATCHES_REGEX `{$AZURE.MYSQL.DB.LOCATION.NOT.MATCHES}`</p><p>- {#GROUP} MATCHES_REGEX `{$AZURE.RESOURCE_GROUP.MATCHES}`</p><p>- {#GROUP} NOT_MATCHES_REGEX `{$AZURE.RESOURCE_GROUP.NOT.MATCHES}`</p><p>**Overrides:**</p><p>Flexible server<br> - {#TYPE} MATCHES_REGEX `Microsoft.DBforMySQL/flexibleServers`<br>  - HOST_PROTOTYPE REGEXP ``</p><p>Single server<br> - {#TYPE} MATCHES_REGEX `Microsoft.DBforMySQL/servers`<br>  - HOST_PROTOTYPE REGEXP ``</p> |
|PostgreSQL servers discovery |<p>The list of the PostgreSQL servers is provided by the subscription.</p> |DEPENDENT |azure.pgsql.servers.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.resources.value`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p><p>**Filter**:</p>AND <p>- {#TYPE} MATCHES_REGEX `^Microsoft.DBforPostgreSQL`</p><p>- {#NAME} MATCHES_REGEX `{$AZURE.PGSQL.DB.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$AZURE.PGSQL.DB.NAME.NOT.MATCHES}`</p><p>- {#LOCATION} MATCHES_REGEX `{$AZURE.PGSQL.DB.LOCATION.MATCHES}`</p><p>- {#LOCATION} NOT_MATCHES_REGEX `{$AZURE.PGSQL.DB.LOCATION.NOT.MATCHES}`</p><p>- {#GROUP} MATCHES_REGEX `{$AZURE.RESOURCE_GROUP.MATCHES}`</p><p>- {#GROUP} NOT_MATCHES_REGEX `{$AZURE.RESOURCE_GROUP.NOT.MATCHES}`</p><p>**Overrides:**</p><p>Flexible server<br> - {#TYPE} MATCHES_REGEX `Microsoft.DBforPostgreSQL/flexibleServers`<br>  - HOST_PROTOTYPE REGEXP ``</p><p>Single server<br> - {#TYPE} MATCHES_REGEX `Microsoft.DBforPostgreSQL/servers`<br>  - HOST_PROTOTYPE REGEXP ``</p> |
|Storage accounts discovery |<p>The list of all storage accounts available under the subscription.</p> |DEPENDENT |azure.starage.acc.discovery<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p><p>**Filter**:</p>AND <p>- {#NAME} MATCHES_REGEX `{$AZURE.STORAGE.ACC.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$AZURE.STORAGE.ACC.NAME.NOT.MATCHES}`</p><p>- {#LOCATION} MATCHES_REGEX `{$AZURE.STORAGE.ACC.LOCATION.MATCHES}`</p><p>- {#LOCATION} NOT_MATCHES_REGEX `{$AZURE.STORAGE.ACC.LOCATION.NOT.MATCHES}`</p><p>- {#GROUP} MATCHES_REGEX `{$AZURE.RESOURCE_GROUP.MATCHES}`</p><p>- {#GROUP} NOT_MATCHES_REGEX `{$AZURE.RESOURCE_GROUP.NOT.MATCHES}`</p> |
|Virtual machines discovery |<p>The list of the virtual machines is provided by the subscription.</p> |DEPENDENT |azure.vm.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.resources.value`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p><p>**Filter**:</p>AND <p>- {#TYPE} MATCHES_REGEX `^Microsoft.Compute/virtualMachines$`</p><p>- {#NAME} MATCHES_REGEX `{$AZURE.VM.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$AZURE.VM.NAME.NOT.MATCHES}`</p><p>- {#LOCATION} MATCHES_REGEX `{$AZURE.VM.LOCATION.MATCHES}`</p><p>- {#LOCATION} NOT_MATCHES_REGEX `{$AZURE.VM.LOCATION.NOT.MATCHES}`</p><p>- {#GROUP} MATCHES_REGEX `{$AZURE.RESOURCE_GROUP.MATCHES}`</p><p>- {#GROUP} NOT_MATCHES_REGEX `{$AZURE.RESOURCE_GROUP.NOT.MATCHES}`</p> |

### Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Azure |Azure: Get resources |<p>The result of API requests is expressed in the JSON.</p> |SCRIPT |azure.get.resources<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Azure |Azure: Get errors |<p>A list of errors from API requests.</p> |DEPENDENT |azure.get.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.errors`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure: Get storage accounts |<p>The result of API requests is expressed in the JSON.</p> |SCRIPT |azure.get.storage.acc<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Azure |Azure: Get storage accounts errors |<p>The errors from API requests.</p> |DEPENDENT |azure.get.storage.acc.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.error`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Get data |<p>The HTTP API endpoint that returns storage metrics with the name `[{#NAME}]`.</p> |SCRIPT |azure.get.storage.acc[{#NAME}]<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Azure |Azure: Storage account [{#NAME}]: Used Capacity |<p>The amount of storage used by the storage account with the name `[{#NAME}]`, expressed in bytes.</p><p>For standard storage accounts, it's the sum of capacity used by blob, table, file, and queue. </p><p>For premium storage accounts and Blob storage accounts, it is the same as BlobCapacity or FileCapacity.</p> |DEPENDENT |azure.storage.used.capacity[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.storageAccount.UsedCapacity.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Transactions |<p>The number of requests made to the storage service or a specified API operation.</p><p>This number includes successful and failed requests and also requests, which produced errors.</p><p>Use `ResponseType` dimension for the number of different type of responses.</p> |DEPENDENT |azure.storage.transactions[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.storageAccount.Transactions.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Ingress |<p>The amount of ingress data expressed in bytes. This number includes ingress from an external client into Azure Storage and also ingress within Azure.</p> |DEPENDENT |azure.storage.ingress[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.storageAccount.Ingress.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Egress |<p>The amount of egress data. This number includes egress to external client from Azure Storage and also egress within Azure.</p><p>As a result, this number does not reflect billable egress.</p> |DEPENDENT |azure.storage.engress[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.storageAccount.Egress.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Success Server Latency |<p>The average time used to process a successful request by Azure Storage.</p><p>This value does not include the network latency specified in `SuccessE2ELatency`.</p> |DEPENDENT |azure.storage.success.server.latency[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.storageAccount.SuccessServerLatency.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Success E2E Latency |<p>The average end-to-end latency of successful requests made to a storage service or the specified API operation expressed in milliseconds.</p><p>This value includes the required processing time within Azure Storage to read the request, send the response, and receive acknowledgment of the response.</p> |DEPENDENT |azure.storage.success.e2e.latency[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.storageAccount.SuccessE2ELatency.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Availability |<p>The percentage of availability for the storage service or a specified API operation.</p><p>Availability is calculated by taking the `TotalBillableRequests` value and dividing it by the number of applicable requests, including those that produced unexpected errors.</p><p>All unexpected errors result in reduced availability for the storage service or the specified API operation.</p> |DEPENDENT |azure.storage.availability[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.storageAccount.Availability.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Blob Capacity |<p>The amount of storage used by the blob service of the storage account with the name `[{#NAME}]`, expressed in bytes.</p> |DEPENDENT |azure.storage.blob.capacity[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.blobServices.BlobCapacity.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Blob Count |<p>The number of blob objects stored in the storage account with the name `[{#NAME}]`.</p> |DEPENDENT |azure.storage.blob.count[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.blobServices.BlobCount.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Blob Container Count |<p>The number of containers in the storage account with the name `[{#NAME}]`.</p> |DEPENDENT |azure.storage.blob.container.count[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.blobServices.ContainerCount.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Blob Index Capacity |<p>The amount of storage with the name `[{#NAME}]` used by the Azure Data Lake Storage Gen2 hierarchical index.</p> |DEPENDENT |azure.storage.blob.index.capacity[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.blobServices.IndexCapacity.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Blob Transactions |<p>The number of requests made to the storage service or a specified API operation.</p><p>This number includes successful and failed requests and also requests, which produced errors.</p><p>Use `ResponseType` dimension for the number of different type of responses.</p> |DEPENDENT |azure.storage.blob.transactions[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.blobServices.Transactions.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Blob Ingress |<p>The amount of ingress data expressed in bytes. This number includes ingress from an external client into Azure Storage and also ingress within Azure.</p> |DEPENDENT |azure.storage.blob.ingress[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.blobServices.Ingress.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Blob Egress |<p>The amount of egress data. This number includes egress to external client from Azure Storage and also egress within Azure.</p><p>As a result, this number does not reflect billable egress.</p> |DEPENDENT |azure.storage.blob.engress[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.blobServices.Egress.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Blob Success Server Latency |<p>The average time used to process a successful request by Azure Storage.</p><p>This value does not include the network latency specified in `SuccessE2ELatency`.</p> |DEPENDENT |azure.storage.blob.success.server.latency[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.blobServices.SuccessServerLatency.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Blob Success E2E Latency |<p>The average end-to-end latency of successful requests made to a storage service or the specified API operation expressed in milliseconds.</p><p>This value includes the required processing time within Azure Storage to read the request, send the response, and receive acknowledgment of the response.</p> |DEPENDENT |azure.storage.blob.success.e2e.latency[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.blobServices.SuccessE2ELatency.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Blob Availability |<p>The percentage of availability for the storage service or a specified API operation.</p><p>Availability is calculated by taking the `TotalBillableRequests` value and dividing it by the number of applicable requests, including those that produced unexpected errors.</p><p>All unexpected errors result in reduced availability for the storage service or the specified API operation.</p> |DEPENDENT |azure.storage.blob.availability[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.blobServices.Availability.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Table Capacity |<p>The amount of storage used by the table service of the storage account with the name `[{#NAME}]`, expressed in bytes.</p> |DEPENDENT |azure.storage.table.capacity[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.tableServices.TableCapacity.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Table Count |<p>The number of tables in the storage account with the name `[{#NAME}]`.</p> |DEPENDENT |azure.storage.table.count[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.tableServices.TableCount.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Table Entity Count |<p>The number of table entities in the storage account with the name `[{#NAME}]`.</p> |DEPENDENT |azure.storage.table.entity.count[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.tableServices.TableEntityCount.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Table Transactions |<p>The number of requests made to the storage service or a specified API operation.</p><p>This number includes successful and failed requests and also requests, which produced errors.</p><p>Use `ResponseType` dimension for the number of different type of responses.</p> |DEPENDENT |azure.storage.table.transactions[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.tableServices.Transactions.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Table Ingress |<p>The amount of ingress data expressed in bytes. This number includes ingress from an external client into Azure Storage and also ingress within Azure.</p> |DEPENDENT |azure.storage.table.ingress[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.tableServices.Ingress.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Table Egress |<p>The amount of egress data. This number includes egress to external client from Azure Storage and also egress within Azure.</p><p>As a result, this number does not reflect billable egress.</p> |DEPENDENT |azure.storage.table.engress[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.tableServices.Egress.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Table Success Server Latency |<p>The average time used to process a successful request by Azure Storage.</p><p>This value does not include the network latency specified in `SuccessE2ELatency`.</p> |DEPENDENT |azure.storage.table.success.server.latency[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.tableServices.SuccessServerLatency.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Table Success E2E Latency |<p>The average end-to-end latency of successful requests made to a storage service or the specified API operation expressed in milliseconds.</p><p>This value includes the required processing time within Azure Storage to read the request, send the response, and receive acknowledgment of the response.</p> |DEPENDENT |azure.storage.table.success.e2e.latency[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.tableServices.SuccessE2ELatency.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Table Availability |<p>The percentage of availability for the storage service or a specified API operation.</p><p>Availability is calculated by taking the `TotalBillableRequests` value and dividing it by the number of applicable requests, including those that produced unexpected errors.</p><p>All unexpected errors result in reduced availability for the storage service or the specified API operation.</p> |DEPENDENT |azure.storage.table.availability[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.tableServices.Availability.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: File Capacity |<p>The amount of File storage used by the storage account with the name `[{#NAME}]`, expressed in bytes.</p> |DEPENDENT |azure.storage.file.capacity[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.fileServices.FileCapacity.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: File Count |<p>The number of files in the storage account with the name `[{#NAME}]`.</p> |DEPENDENT |azure.storage.file.count[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.fileServices.FileCount.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: File Share Count |<p>The number of file shares in the storage account.</p> |DEPENDENT |azure.storage.file.share.count[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.fileServices.FileShareCount.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: File Share Snapshot Count |<p>The number of snapshots present on the share in storage account's Files Service.</p> |DEPENDENT |azure.storage.file.shares.snapshot.count[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.fileServices.FileShareSnapshotCount.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: File Share Snapshot Size |<p>The amount of storage used by the snapshots in storage account's File service in bytes.</p> |DEPENDENT |azure.storage.file.share.snapshot.size[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.fileServices.FileShareSnapshotSize.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: File Share Capacity Quota |<p>The upper limit on the amount of storage that can be used by Azure Files Service in bytes.</p> |DEPENDENT |azure.storage.file.share.capacity.quota[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.fileServices.FileShareCapacityQuota.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: File Transactions |<p>The number of requests made to the storage service or a specified API operation.</p><p>This number includes successful and failed requests and also requests, which produced errors.</p><p>Use `ResponseType` dimension for the number of different type of responses.</p> |DEPENDENT |azure.storage.file.transactions[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.fileServices.Transactions.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: File Ingress |<p>The amount of ingress data expressed in bytes. This number includes ingress from an external client into Azure Storage and also ingress within Azure.</p> |DEPENDENT |azure.storage.file.ingress[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.fileServices.Ingress.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: File Egress |<p>The amount of egress data. This number includes egress to external client from Azure Storage and also egress within Azure.</p><p>As a result, this number does not reflect billable egress.</p> |DEPENDENT |azure.storage.file.engress[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.fileServices.Egress.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: File Success Server Latency |<p>The average time used to process a successful request by Azure Storage.</p><p>This value does not include the network latency specified in `SuccessE2ELatency`.</p> |DEPENDENT |azure.storage.file.success.server.latency[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.fileServices.SuccessServerLatency.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: File Success E2E Latency |<p>The average end-to-end latency of successful requests made to a storage service or the specified API operation expressed in milliseconds.</p><p>This value includes the required processing time within Azure Storage to read the request, send the response, and receive acknowledgment of the response.</p> |DEPENDENT |azure.storage.file.success.e2e.latency[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.fileServices.file.SuccessE2ELatency.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Queue Capacity |<p>The amount of Queue storage used by the storage account with the name `[{#NAME}]`, expressed in bytes.</p> |DEPENDENT |azure.storage.queue.capacity[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.queueServices.QueueCapacity.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Queue Count |<p>The number of queues in the storage account with the name `[{#NAME}]`.</p> |DEPENDENT |azure.storage.queue.count[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.queueServices.QueueCount.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Queue Message Count |<p>The number of unexpired queue messages in the storage account with the name `[{#NAME}]`.</p> |DEPENDENT |azure.storage.queue.message.count[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.queueServices.QueueMessageCount.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Queue Transactions |<p>The number of requests made to the storage service or a specified API operation.</p><p>This number includes successful and failed requests and also requests, which produced errors.</p><p>Use `ResponseType` dimension for the number of different type of responses.</p> |DEPENDENT |azure.storage.queue.transactions[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.queueServices.Transactions.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Queue Ingress |<p>The amount of ingress data expressed in bytes. This number includes ingress from an external client into Azure Storage and also ingress within Azure.</p> |DEPENDENT |azure.storage.queue.ingress[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.queueServices.Ingress.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Queue Egress |<p>The amount of egress data. This number includes egress to external client from Azure Storage and also egress within Azure.</p><p>As a result, this number does not reflect billable egress.</p> |DEPENDENT |azure.storage.queue.engress[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.queueServices.Egress.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Queue Success Server Latency |<p>The average time used to process a successful request by Azure Storage.</p><p>This value does not include the network latency specified in `SuccessE2ELatency`.</p> |DEPENDENT |azure.storage.queue.success.server.latency[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.queueServices.SuccessServerLatency.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Azure |Azure: Storage account [{#NAME}]: Queue Success E2E Latency |<p>The average end-to-end latency of successful requests made to a storage service or the specified API operation expressed in milliseconds.</p><p>This value includes the required processing time within Azure Storage to read the request, send the response, and receive acknowledgment of the response.</p> |DEPENDENT |azure.storage.queue.success.e2e.latency[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.queueServices.queue.SuccessE2ELatency.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Azure: There are errors in requests to API |<p>Zabbix has received errors in response to API requests.</p> |`length(last(/Azure by HTTP/azure.get.errors))>0` |AVERAGE | |
|Azure: There are errors in storages requests to API |<p>Zabbix has received errors in response to API requests.</p> |`length(last(/Azure by HTTP/azure.get.storage.acc.errors))>0` |AVERAGE |<p>**Depends on**:</p><p>- Azure: There are errors in requests to API</p> |
|Azure: Storage account [{#NAME}]: Availability is low |<p>-</p> |`(min(/Azure by HTTP/azure.storage.availability[{#NAME}],#3))<{$AZURE.STORAGE.ACC.AVAILABILITY:"{#NAME}"}` |WARNING | |
|Azure: Storage account [{#NAME}]: Blob Availability is low |<p>-</p> |`(min(/Azure by HTTP/azure.storage.blob.availability[{#NAME}],#3))<{$AZURE.STORAGE.ACC.BLOB.AVAILABILITY:"{#NAME}"}` |WARNING | |
|Azure: Storage account [{#NAME}]: Table Availability is low |<p>-</p> |`(min(/Azure by HTTP/azure.storage.table.availability[{#NAME}],#3))<{$AZURE.STORAGE.ACC.TABLE.AVAILABILITY:"{#NAME}"}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

# Azure Virtual Machine by HTTP

## Overview

This template is designed to monitor Microsoft Azure Virtual Machines (VMs) by HTTP.
It works without any external scripts and uses the script item.

## Requirements

For Zabbix version: 6.4 and higher.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

> See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure the macros: `{$AZURE.APP.ID}`, `{$AZURE.PASSWORD}`, `{$AZURE.TENANT.ID}`, `{$AZURE.SUBSCRIPTION.ID}`, and `{$AZURE.RESOURCE.ID}`.

## Configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.APP.ID} |<p>The App ID of Microsoft Azure.</p> |`` |
|{$AZURE.DATA.TIMEOUT} |<p>A response timeout for an API.</p> |`60s` |
|{$AZURE.PASSWORD} |<p>Microsoft Azure password.</p> |`` |
|{$AZURE.RESOURCE.ID} |<p>Microsoft Azure Virtual Machine ID.</p> |`` |
|{$AZURE.SUBSCRIPTION.ID} |<p>Microsoft Azure subscription ID.</p> |`` |
|{$AZURE.TENANT.ID} |<p>Microsoft Azure tenant ID.</p> |`` |
|{$AZURE.VM.CPU.UTIL.CRIT} |<p>The critical threshold of CPU utilization expressed in %.</p> |`90` |

### Template links

There are no template links in this template.

### Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Azure |Azure: Get data |<p>The result of API requests is expressed in the JSON.</p> |SCRIPT |azure.vm.data.get<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Azure |Azure: Get errors |<p>A list of errors from API requests.</p> |DEPENDENT |azure.vm.data.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.errors`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure: Availability state |<p>The availability status of the resource.</p> |DEPENDENT |azure.vm.availability.state<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.availabilityState`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- STR_REPLACE: `Available 0`</p><p>- STR_REPLACE: `Degraded 1`</p><p>- STR_REPLACE: `Unavailable 2`</p><p>- STR_REPLACE: `Unknown 3`</p><p>- IN_RANGE: `0 3 `</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure: Availability status detailed |<p>The summary description of availability status.</p> |DEPENDENT |azure.vm.availability.details<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.summary`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure: Percentage CPU |<p>The percentage of allocated computing units that are currently in use by VMs.</p> |DEPENDENT |azure.vm.cpu.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.PercentageCPU.average`</p> |
|Azure |Azure: Disk read rate |<p>Bytes read from the disk during the monitoring period (1 minute).</p> |DEPENDENT |azure.vm.disk.read.bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DiskReadBytes.total`</p><p>- MULTIPLIER: `0.0167`</p> |
|Azure |Azure: Disk write rate |<p>Bytes written to the disk during the monitoring period (1 minute).</p> |DEPENDENT |azure.vm.disk.write.bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DiskWriteBytes.total`</p><p>- MULTIPLIER: `0.0167`</p> |
|Azure |Azure: Disk read Operations/Sec |<p>The count of read operations from the disk per second.</p> |DEPENDENT |azure.vm.disk.read.ops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DiskReadOperationsSec.average`</p> |
|Azure |Azure: Disk write Operations/Sec |<p>The count of write operations to the disk per second.</p> |DEPENDENT |azure.vm.disk.write.ops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DiskWriteOperationsSec.average`</p> |
|Azure |Azure: CPU credits remaining |<p>The total number of credits available to burst. Available only on B-series burstable VMs.</p> |DEPENDENT |azure.vm.cpu.credits.remaining<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.CPUCreditsRemaining.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: CPU credits consumed |<p>The total number of credits consumed by the Virtual Machine. Only available on B-series burstable VMs.</p> |DEPENDENT |azure.vm.cpu.credits.consumed<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.CPUCreditsConsumed.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk read rate |<p>Bytes per second read from a single disk during the monitoring period.</p> |DEPENDENT |azure.vm.data.disk.read.bps<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskReadBytessec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk write rate |<p>Bytes per second written to a single disk during the monitoring period.</p> |DEPENDENT |azure.vm.data.disk.write.bps<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskWriteBytessec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk read operations/sec |<p>The read IOPS from a single disk during the monitoring period.</p> |DEPENDENT |azure.vm.data.disk.read.ops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskReadOperationsSec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk write operations/sec |<p>The write IOPS from a single disk during the monitoring period.</p> |DEPENDENT |azure.vm.data.disk.write.ops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskWriteOperationsSec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk queue depth |<p>The number of outstanding IO requests that are waiting to be performed on a disk.</p> |DEPENDENT |azure.vm.data.disk.queue.depth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskQueueDepth.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk bandwidth consumed percentage |<p>The percentage of the data disk's bandwidth consumed per minute.</p> |DEPENDENT |azure.vm.data.disk.bandwidth.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskBandwidthConsumedPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk IOPS consumed percentage |<p>The percentage of the data disk input/output (I/O) consumed per minute.</p> |DEPENDENT |azure.vm.data.disk.iops.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskIOPSConsumedPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk target bandwidth |<p>Baseline bytes per second throughput Data Disk can achieve without bursting.</p> |DEPENDENT |azure.vm.data.disk.target.bandwidth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskTargetBandwidth.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk target IOPS |<p>The baseline IOPS that the data disk can achieve without bursting.</p> |DEPENDENT |azure.vm.data.disk.target.iops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskTargetIOPS.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk max burst bandwidth |<p>The maximum bytes per second throughput that the data disk can achieve with bursting.</p> |DEPENDENT |azure.vm.data.disk.max.bandwidth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskMaxBurstBandwidth.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk max burst IOPS |<p>The maximum IOPS that the data disk can achieve with bursting.</p> |DEPENDENT |azure.vm.data.disk.max.iops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskMaxBurstIOPS.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk used burst BPS credits percentage |<p>The percentage of the data disk burst bandwidth credits used so far.</p> |DEPENDENT |azure.vm.data.disk.used.burst.bandwidth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskUsedBurstBPSCreditsPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Data disk used burst IO credits percentage |<p>The percentage of the data disk burst I/O credits used so far.</p> |DEPENDENT |azure.vm.data.disk.used.burst.iops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.DataDiskUsedBurstIOCreditsPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk read rate |<p>Bytes per second read from a single disk during the monitoring period for OS disk.</p> |DEPENDENT |azure.vm.os.disk.read.bps<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskReadBytessec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk write rate |<p>Bytes per second written to a single disk during the monitoring period for OS disk.</p> |DEPENDENT |azure.vm.os.disk.write.bps<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskWriteBytessec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk read operations/sec |<p>The read IOPS from a single disk during the monitoring period for OS disk.</p> |DEPENDENT |azure.vm.os.disk.read.ops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskReadOperationsSec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk write operations/sec |<p>The write IOPS from a single disk during the monitoring period for OS disk.</p> |DEPENDENT |azure.vm.os.disk.write.ops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskWriteOperationsSec.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk queue depth |<p>The OS disk queue depth (or queue length).</p> |DEPENDENT |azure.vm.os.disk.queue.depth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskQueueDepth.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk bandwidth consumed percentage |<p>The percentage of the operating system's disk bandwidth consumed per minute.</p> |DEPENDENT |azure.vm.os.disk.bandwidth.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskBandwidthConsumedPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk IOPS consumed percentage |<p>The percentage of the operating system's disk I/Os consumed per minute.</p> |DEPENDENT |azure.vm.os.disk.iops.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskIOPSConsumedPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk target bandwidth |<p>Baseline bytes per second throughput OS Disk can achieve without bursting.</p> |DEPENDENT |azure.vm.os.disk.target.bandwidth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskTargetBandwidth.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk target IOPS |<p>Baseline IOPS that the OS disk can achieve without bursting.</p> |DEPENDENT |azure.vm.os.disk.target.iops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskTargetIOPS.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk max burst bandwidth |<p>Maximum bytes per second throughput OS Disk can achieve with bursting.</p> |DEPENDENT |azure.vm.os.disk.max.bandwidth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskMaxBurstBandwidth.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk max burst IOPS |<p>Maximum IOPS OS Disk can achieve with bursting.</p> |DEPENDENT |azure.vm.os.disk.max.iops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskMaxBurstIOPS.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk used burst BPS credits percentage |<p>The percentage of the OS Disk burst bandwidth credits used so far.</p> |DEPENDENT |azure.vm.os.disk.used.burst.bandwidth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskUsedBurstBPSCreditsPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: OS disk used burst IO credits percentage |<p>The percentage of the OS disk burst I/O credits used so far.</p> |DEPENDENT |azure.vm.os.disk.used.burst.iops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OSDiskUsedBurstIOCreditsPercentage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Inbound flows |<p>Inbound Flows are a number of the current flows in the inbound direction (the traffic going into the VM).</p> |DEPENDENT |azure.vm.flows.inbound<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.InboundFlows.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Outbound flows |<p>Outbound Flows are a number of the current flows in the outbound direction (the traffic going out of the VM).</p> |DEPENDENT |azure.vm.flows.outbound<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OutboundFlows.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Inbound flows max creation rate |<p>The maximum creation rate of the inbound flows (the traffic going into the VM).</p> |DEPENDENT |azure.vm.flows.inbound.max<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.InboundFlowsMaximumCreationRate.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Outbound flows max creation rate |<p>The maximum creation rate of the outbound flows (the traffic going out of the VM).</p> |DEPENDENT |azure.vm.flows.outbound.max<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.OutboundFlowsMaximumCreationRate.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Premium data disk cache read hit |<p>Premium data disk cache read hit.</p> |DEPENDENT |azure.vm.premium.data.disk.cache.read.hit<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.PremiumDataDiskCacheReadHit.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Premium data disk cache read miss |<p>Premium data disk cache read miss.</p> |DEPENDENT |azure.vm.premium.data.disk.cache.read.miss<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.PremiumDataDiskCacheReadMiss.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Premium OS disk cache read hit |<p>Premium OS disk cache read hit.</p> |DEPENDENT |azure.vm.premium.os.disk.cache.read.hit<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.PremiumOSDiskCacheReadHit.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: Premium OS disk cache read miss |<p>Premium OS disk cache read miss.</p> |DEPENDENT |azure.vm.premium.os.disk.cache.read.miss<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.PremiumOSDiskCacheReadMiss.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure: VM cached bandwidth consumed percentage |<p>The percentage of the cached disk bandwidth consumed by the VM.</p> |DEPENDENT |azure.vm.cached.bandwidth.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.VMCachedBandwidthConsumedPercentage.average`</p> |
|Azure |Azure: VM cached IOPS consumed percentage |<p>The percentage of the cached disk IOPS consumed by the VM.</p> |DEPENDENT |azure.vm.cached.iops.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.VMCachedIOPSConsumedPercentage.average`</p> |
|Azure |Azure: VM uncached bandwidth consumed percentage |<p>The percentage of the uncached disk bandwidth consumed by the VM.</p> |DEPENDENT |azure.vm.uncached.bandwidth.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.VMUncachedBandwidthConsumedPercentage.average`</p> |
|Azure |Azure: VM uncached IOPS consumed percentage |<p>The percentage of the uncached disk IOPS consumed by the VM.</p> |DEPENDENT |azure.vm.uncached.iops.consumed.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.VMUncachedIOPSConsumedPercentage.average`</p> |
|Azure |Azure: Network in total |<p>The number of bytes received on all network interfaces by the VMs (incoming traffic).</p> |DEPENDENT |azure.vm.network.in.total<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.NetworkInTotal.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.1333`</p> |
|Azure |Azure: Network out total |<p>The number of bytes out on all network interfaces by the VMs (outgoing traffic).</p> |DEPENDENT |azure.vm.network.out.total<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.NetworkOutTotal.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.1333`</p> |
|Azure |Azure: Available memory |<p>The amount of physical memory (in bytes) immediately available for the allocation to a process or for a system use in the VM.</p> |DEPENDENT |azure.vm.memory.available<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.AvailableMemoryBytes.average`</p> |

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Azure: There are errors in requests to API |<p>Zabbix has received errors in response to API requests.</p> |`length(last(/Azure Virtual Machine by HTTP/azure.vm.data.errors))>0` |AVERAGE | |
|Azure: Virtual machine is unavailable |<p>The resource state is unavailable.</p> |`last(/Azure Virtual Machine by HTTP/azure.vm.availability.state)=2` |HIGH | |
|Azure: Virtual machine is degraded |<p>The resource is in degraded state.</p> |`last(/Azure Virtual Machine by HTTP/azure.vm.availability.state)=1` |AVERAGE | |
|Azure: Virtual machine is in unknown state |<p>The resource state is unknown.</p> |`last(/Azure Virtual Machine by HTTP/azure.vm.availability.state)=3` |WARNING | |
|Azure: High CPU utilization |<p>The CPU utilization is too high. The system might be slow to respond.</p> |`min(/Azure Virtual Machine by HTTP/azure.vm.cpu.percentage,5m)>{$AZURE.VM.CPU.UTIL.CRIT}` |HIGH | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

# Azure MySQL Flexible Server by HTTP

## Overview

This template is designed to monitor Microsoft Azure MySQL flexible servers by HTTP.
It works without any external scripts and uses the script item.

## Requirements

For Zabbix version: 6.4 and higher.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

> See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure the macros: `{$AZURE.APP.ID}`, `{$AZURE.PASSWORD}`, `{$AZURE.TENANT.ID}`, `{$AZURE.SUBSCRIPTION.ID}`, and `{$AZURE.RESOURCE.ID}`.

## Configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.APP.ID} |<p>The App ID of Microsoft Azure.</p> |`` |
|{$AZURE.DATA.TIMEOUT} |<p>A response timeout for an API.</p> |`60s` |
|{$AZURE.DB.ABORTED_CONN.MAX.WARN} |<p>The number of failed attempts to connect to the MySQL server for a trigger expression.</p> |`25` |
|{$AZURE.DB.CPU.UTIL.CRIT} |<p>The critical threshold of CPU utilization expressed in %.</p> |`90` |
|{$AZURE.DB.STORAGE.PUSED.CRIT} |<p>The critical threshold of the storage utilization expressed in %.</p> |`90` |
|{$AZURE.DB.STORAGE.PUSED.WARN} |<p>The warning threshold of the storage utilization expressed in %.</p> |`80` |
|{$AZURE.PASSWORD} |<p>Microsoft Azure password.</p> |`` |
|{$AZURE.RESOURCE.ID} |<p>Microsoft Azure MySQL server ID.</p> |`` |
|{$AZURE.SUBSCRIPTION.ID} |<p>Microsoft Azure subscription ID.</p> |`` |
|{$AZURE.TENANT.ID} |<p>Microsoft Azure tenant ID.</p> |`` |

### Template links

There are no template links in this template.

### Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Azure |Azure MySQL: Get data |<p>The result of API requests is expressed in the JSON.</p> |SCRIPT |azure.db.mysql.data.get<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Azure |Azure MySQL: Get errors |<p>A list of errors from API requests.</p> |DEPENDENT |azure.db.mysql.data.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.errors`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure MySQL: Availability state |<p>The availability status of the resource.</p> |DEPENDENT |azure.db.mysql.availability.state<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.availabilityState`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- STR_REPLACE: `Available 0`</p><p>- STR_REPLACE: `Degraded 1`</p><p>- STR_REPLACE: `Unavailable 2`</p><p>- STR_REPLACE: `Unknown 3`</p><p>- IN_RANGE: `0 3 `</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure MySQL: Availability status detailed |<p>The summary description of the availability status.</p> |DEPENDENT |azure.db.mysql.availability.details<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.summary`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure MySQL: Percentage CPU |<p>The CPU percent of a host.</p> |DEPENDENT |azure.db.mysql.cpu.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_percent.maximum`</p> |
|Azure |Azure MySQL: Memory utilization |<p>The memory percent of a host.</p> |DEPENDENT |azure.db.mysql.memory.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.memory_percent.maximum`</p> |
|Azure |Azure MySQL: Network out |<p>Network egress of a host expressed in bytes.</p> |DEPENDENT |azure.db.mysql.network.egress<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.network_bytes_egress.total`</p><p>- MULTIPLIER: `0.0088`</p> |
|Azure |Azure MySQL: Network in |<p>Network ingress of a host expressed in bytes.</p> |DEPENDENT |azure.db.mysql.network.ingress<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.network_bytes_ingress.total`</p><p>- MULTIPLIER: `0.0088`</p> |
|Azure |Azure MySQL: Connections active |<p>The count of active connections.</p> |DEPENDENT |azure.db.mysql.connections.active<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.active_connections.maximum`</p> |
|Azure |Azure MySQL: Connections total |<p>The count of total connections.</p> |DEPENDENT |azure.db.mysql.connections.total<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.total_connections.total`</p> |
|Azure |Azure MySQL: Connections aborted |<p>The count of aborted connections.</p> |DEPENDENT |azure.db.mysql.connections.aborted<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.aborted_connections.total`</p> |
|Azure |Azure MySQL: Queries |<p>The count of queries.</p> |DEPENDENT |azure.db.mysql.queries<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.Queries.total`</p> |
|Azure |Azure MySQL: IO consumption percent |<p>The consumption percent of I/O.</p> |DEPENDENT |azure.db.mysql.io.consumption.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.io_consumption_percent.maximum`</p> |
|Azure |Azure MySQL: Storage percent |<p>The storage utilization expressed in %.</p> |DEPENDENT |azure.db.mysql.storage.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_percent.maximum`</p> |
|Azure |Azure MySQL: Storage used |<p>Used storage space expressed in bytes.</p> |DEPENDENT |azure.db.mysql.storage.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_used.maximum`</p> |
|Azure |Azure MySQL: Storage limit |<p>The storage limit expressed in bytes.</p> |DEPENDENT |azure.db.mysql.storage.limit<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_limit.maximum`</p> |
|Azure |Azure MySQL: Backup storage used |<p>Used backup storage expressed in bytes.</p> |DEPENDENT |azure.db.mysql.storage.backup.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.backup_storage_used.maximum`</p> |
|Azure |Azure MySQL: Replication lag |<p>The replication lag expressed in seconds.</p> |DEPENDENT |azure.db.mysql.replication.lag<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.replication_lag.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure MySQL: CPU credits remaining |<p>The remaining CPU credits.</p> |DEPENDENT |azure.db.mysql.cpu.credits.remaining<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_credits_remaining.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure MySQL: CPU credits consumed |<p>The consumed CPU credits.</p> |DEPENDENT |azure.db.mysql.cpu.credits.consumed<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_credits_consumed.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Azure MySQL: There are errors in requests to API |<p>Zabbix has received errors in response to API requests.</p> |`length(last(/Azure MySQL Flexible Server by HTTP/azure.db.mysql.data.errors))>0` |AVERAGE | |
|Azure MySQL: MySQL server is unavailable |<p>The resource state is unavailable.</p> |`last(/Azure MySQL Flexible Server by HTTP/azure.db.mysql.availability.state)=2` |HIGH | |
|Azure MySQL: MySQL server is degraded |<p>The resource is in degraded state.</p> |`last(/Azure MySQL Flexible Server by HTTP/azure.db.mysql.availability.state)=1` |AVERAGE | |
|Azure MySQL: MySQL server is in unknown state |<p>The resource state is unknown.</p> |`last(/Azure MySQL Flexible Server by HTTP/azure.db.mysql.availability.state)=3` |WARNING | |
|Azure MySQL: High CPU utilization |<p>The CPU utilization is too high. The system might be slow to respond.</p> |`min(/Azure MySQL Flexible Server by HTTP/azure.db.mysql.cpu.percentage,5m)>{$AZURE.DB.CPU.UTIL.CRIT}` |HIGH | |
|Azure MySQL: Server has aborted connections |<p>The number of failed attempts to connect to the MySQL server is more than `{$AZURE.DB.ABORTED_CONN.MAX.WARN}`.</p> |`min(/Azure MySQL Flexible Server by HTTP/azure.db.mysql.connections.aborted,5m)>{$AZURE.DB.ABORTED_CONN.MAX.WARN}` |AVERAGE | |
|Azure MySQL: Storage space is critically low |<p>Critical utilization of the storage space.</p> |`last(/Azure MySQL Flexible Server by HTTP/azure.db.mysql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.CRIT}` |AVERAGE | |
|Azure MySQL: Storage space is low |<p>High utilization of the storage space.</p> |`last(/Azure MySQL Flexible Server by HTTP/azure.db.mysql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.WARN}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

# Azure MySQL Single Server by HTTP

## Overview

This template is designed to monitor Microsoft Azure MySQL single servers by HTTP.
It works without any external scripts and uses the script item.

## Requirements

For Zabbix version: 6.4 and higher.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

> See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure the macros: `{$AZURE.APP.ID}`, `{$AZURE.PASSWORD}`, `{$AZURE.TENANT.ID}`, `{$AZURE.SUBSCRIPTION.ID}`, and `{$AZURE.RESOURCE.ID}`.

## Configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.APP.ID} |<p>The App ID of Microsoft Azure.</p> |`` |
|{$AZURE.DATA.TIMEOUT} |<p>A response timeout for an API.</p> |`60s` |
|{$AZURE.DB.CPU.UTIL.CRIT} |<p>The critical threshold of CPU utilization expressed in %.</p> |`90` |
|{$AZURE.DB.FAILED_CONN.MAX.WARN} |<p>The number of failed attempts to connect to the MySQL server for trigger expression.</p> |`25` |
|{$AZURE.DB.MEMORY.UTIL.CRIT} |<p>The critical threshold of memory utilization expressed in %.</p> |`90` |
|{$AZURE.DB.STORAGE.PUSED.CRIT} |<p>The critical threshold of storage utilization expressed in %.</p> |`90` |
|{$AZURE.DB.STORAGE.PUSED.WARN} |<p>The warning threshold of storage utilization expressed in %.</p> |`80` |
|{$AZURE.PASSWORD} |<p>Microsoft Azure password.</p> |`` |
|{$AZURE.RESOURCE.ID} |<p>Microsoft Azure MySQL server ID.</p> |`` |
|{$AZURE.SUBSCRIPTION.ID} |<p>Microsoft Azure subscription ID.</p> |`` |
|{$AZURE.TENANT.ID} |<p>Microsoft Azure tenant ID.</p> |`` |

### Template links

There are no template links in this template.

### Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Azure |Azure MySQL: Get data |<p>The result of API requests is expressed in the JSON.</p> |SCRIPT |azure.db.mysql.data.get<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Azure |Azure MySQL: Get errors |<p>A list of errors from API requests.</p> |DEPENDENT |azure.db.mysql.data.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.errors`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure MySQL: Availability state |<p>The availability status of the resource.</p> |DEPENDENT |azure.db.mysql.availability.state<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.availabilityState`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- STR_REPLACE: `Available 0`</p><p>- STR_REPLACE: `Degraded 1`</p><p>- STR_REPLACE: `Unavailable 2`</p><p>- STR_REPLACE: `Unknown 3`</p><p>- IN_RANGE: `0 3 `</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure MySQL: Availability status detailed |<p>The summary description of the availability status.</p> |DEPENDENT |azure.db.mysql.availability.details<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.summary`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure MySQL: Percentage CPU |<p>The CPU percent of a host.</p> |DEPENDENT |azure.db.mysql.cpu.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_percent.average`</p> |
|Azure |Azure MySQL: Memory utilization |<p>The memory percent of a host.</p> |DEPENDENT |azure.db.mysql.memory.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.memory_percent.average`</p> |
|Azure |Azure MySQL: Network out |<p>The network outbound traffic across the active connections.</p> |DEPENDENT |azure.db.mysql.network.egress<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.network_bytes_egress.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.0088`</p> |
|Azure |Azure MySQL: Network in |<p>The network inbound traffic across the active connections.</p> |DEPENDENT |azure.db.mysql.network.ingress<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.network_bytes_ingress.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.0088`</p> |
|Azure |Azure MySQL: Connections active |<p>The count of active connections.</p> |DEPENDENT |azure.db.mysql.connections.active<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.active_connections.average`</p> |
|Azure |Azure MySQL: Connections failed |<p>The count of failed connections.</p> |DEPENDENT |azure.db.mysql.connections.failed<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.connections_failed.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure MySQL: IO consumption percent |<p>The consumption percent of I/O.</p> |DEPENDENT |azure.db.mysql.io.consumption.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.io_consumption_percent.average`</p> |
|Azure |Azure MySQL: Storage percent |<p>The storage utilization expressed in %.</p> |DEPENDENT |azure.db.mysql.storage.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_percent.average`</p> |
|Azure |Azure MySQL: Storage used |<p>Used storage space expressed in bytes.</p> |DEPENDENT |azure.db.mysql.storage.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_used.average`</p> |
|Azure |Azure MySQL: Storage limit |<p>The storage limit expressed in bytes.</p> |DEPENDENT |azure.db.mysql.storage.limit<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_limit.maximum`</p> |
|Azure |Azure MySQL: Backup storage used |<p>Used backup storage expressed in bytes.</p> |DEPENDENT |azure.db.mysql.storage.backup.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.backup_storage_used.average`</p> |
|Azure |Azure MySQL: Replication lag |<p>The replication lag expressed in seconds.</p> |DEPENDENT |azure.db.mysql.replication.lag<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.seconds_behind_master.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure MySQL: Server log storage percent |<p>The storage utilization by a server log expressed in %.</p> |DEPENDENT |azure.db.mysql.storage.server.log.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.serverlog_storage_percent.average`</p> |
|Azure |Azure MySQL: Server log storage used |<p>The storage space used by a server log expressed in bytes.</p> |DEPENDENT |azure.db.mysql.storage.server.log.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.serverlog_storage_usage.average`</p> |
|Azure |Azure MySQL: Server log storage limit |<p>The storage limit of a server log expressed in bytes.</p> |DEPENDENT |azure.db.mysql.storage.server.log.limit<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.serverlog_storage_limit.maximum`</p> |

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Azure MySQL: There are errors in requests to API |<p>Zabbix has received errors in response to API requests.</p> |`length(last(/Azure MySQL Single Server by HTTP/azure.db.mysql.data.errors))>0` |AVERAGE | |
|Azure MySQL: MySQL server is unavailable |<p>The resource state is unavailable.</p> |`last(/Azure MySQL Single Server by HTTP/azure.db.mysql.availability.state)=2` |HIGH | |
|Azure MySQL: MySQL server is degraded |<p>The resource is in degraded state.</p> |`last(/Azure MySQL Single Server by HTTP/azure.db.mysql.availability.state)=1` |AVERAGE | |
|Azure MySQL: MySQL server is in unknown state |<p>The resource state is unknown.</p> |`last(/Azure MySQL Single Server by HTTP/azure.db.mysql.availability.state)=3` |WARNING | |
|Azure MySQL: High CPU utilization |<p>The CPU utilization is too high. The system might be slow to respond.</p> |`min(/Azure MySQL Single Server by HTTP/azure.db.mysql.cpu.percentage,5m)>{$AZURE.DB.CPU.UTIL.CRIT}` |HIGH | |
|Azure MySQL: High memory utilization |<p>The system is running out of free memory.</p> |`min(/Azure MySQL Single Server by HTTP/azure.db.mysql.memory.percentage,5m)>{$AZURE.DB.MEMORY.UTIL.CRIT}` |AVERAGE | |
|Azure MySQL: Server has failed connections |<p>The number of failed attempts to connect to the MySQL server is more than `{$AZURE.DB.FAILED_CONN.MAX.WARN}`.</p> |`min(/Azure MySQL Single Server by HTTP/azure.db.mysql.connections.failed,5m)>{$AZURE.DB.FAILED_CONN.MAX.WARN}` |AVERAGE | |
|Azure MySQL: Storage space is critically low |<p>Critical utilization of the storage space.</p> |`last(/Azure MySQL Single Server by HTTP/azure.db.mysql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.CRIT}` |AVERAGE | |
|Azure MySQL: Storage space is low |<p>High utilization of the storage space.</p> |`last(/Azure MySQL Single Server by HTTP/azure.db.mysql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.WARN}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

# Azure PostgreSQL Flexible Server by HTTP

## Overview

This template is designed to monitor Microsoft Azure PostgreSQL flexible servers by HTTP.
It works without any external scripts and uses the script item.

## Requirements

For Zabbix version: 6.4 and higher.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

> See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure the macros: `{$AZURE.APP.ID}`, `{$AZURE.PASSWORD}`, `{$AZURE.TENANT.ID}`, `{$AZURE.SUBSCRIPTION.ID}`, and `{$AZURE.RESOURCE.ID}`.

## Configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.APP.ID} |<p>The App ID of Microsoft Azure.</p> |`` |
|{$AZURE.DATA.TIMEOUT} |<p>A response timeout for an API.</p> |`60s` |
|{$AZURE.DB.CPU.UTIL.CRIT} |<p>The critical threshold of CPU utilization expressed in %.</p> |`90` |
|{$AZURE.DB.MEMORY.UTIL.CRIT} |<p>The critical threshold of memory utilization expressed in %.</p> |`90` |
|{$AZURE.DB.STORAGE.PUSED.CRIT} |<p>The critical threshold of storage utilization expressed in %.</p> |`90` |
|{$AZURE.DB.STORAGE.PUSED.WARN} |<p>The warning threshold of storage utilization expressed in %.</p> |`80` |
|{$AZURE.PASSWORD} |<p>Microsoft Azure password.</p> |`` |
|{$AZURE.RESOURCE.ID} |<p>Microsoft Azure PostgreSQL server ID.</p> |`` |
|{$AZURE.SUBSCRIPTION.ID} |<p>Microsoft Azure subscription ID.</p> |`` |
|{$AZURE.TENANT.ID} |<p>Microsoft Azure tenant ID.</p> |`` |

### Template links

There are no template links in this template.

### Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Azure |Azure PostgreSQL: Get data |<p>The result of API requests is expressed in the JSON.</p> |SCRIPT |azure.db.pgsql.data.get<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Azure |Azure PostgreSQL: Get errors |<p>A list of errors from API requests.</p> |DEPENDENT |azure.db.pgsql.data.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.errors`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure PostgreSQL: Availability state |<p>The availability status of the resource.</p> |DEPENDENT |azure.db.pgsql.availability.state<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.availabilityState`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- STR_REPLACE: `Available 0`</p><p>- STR_REPLACE: `Degraded 1`</p><p>- STR_REPLACE: `Unavailable 2`</p><p>- STR_REPLACE: `Unknown 3`</p><p>- IN_RANGE: `0 3 `</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure PostgreSQL: Availability status detailed |<p>The summary description of the availability status.</p> |DEPENDENT |azure.db.pgsql.availability.details<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.summary`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure PostgreSQL: Percentage CPU |<p>The CPU percent of a host.</p> |DEPENDENT |azure.db.pgsql.cpu.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_percent.average`</p> |
|Azure |Azure PostgreSQL: Memory utilization |<p>The memory percent of a host.</p> |DEPENDENT |azure.db.pgsql.memory.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.memory_percent.average`</p> |
|Azure |Azure PostgreSQL: Network out |<p>The network outbound traffic across the active connections.</p> |DEPENDENT |azure.db.pgsql.network.egress<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.network_bytes_egress.total`</p><p>- MULTIPLIER: `0.1333`</p> |
|Azure |Azure PostgreSQL: Network in |<p>The network inbound traffic across the active connections.</p> |DEPENDENT |azure.db.pgsql.network.ingress<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.network_bytes_ingress.total`</p><p>- MULTIPLIER: `0.1333`</p> |
|Azure |Azure PostgreSQL: Connections active |<p>The count of active connections.</p> |DEPENDENT |azure.db.pgsql.connections.active<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.active_connections.average`</p> |
|Azure |Azure PostgreSQL: Connections succeeded |<p>The count of succeeded connections.</p> |DEPENDENT |azure.db.pgsql.connections.succeeded<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.connections_succeeded.total`</p> |
|Azure |Azure PostgreSQL: Connections failed |<p>The count of failed connections.</p> |DEPENDENT |azure.db.pgsql.connections.failed<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.connections_failed.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: Storage percent |<p>The storage utilization expressed in %.</p> |DEPENDENT |azure.db.pgsql.storage.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_percent.average`</p> |
|Azure |Azure PostgreSQL: Storage used |<p>Used storage space expressed in bytes.</p> |DEPENDENT |azure.db.pgsql.storage.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_used.average`</p> |
|Azure |Azure PostgreSQL: Storage free |<p>Free storage space expressed in bytes.</p> |DEPENDENT |azure.db.pgsql.storage.free<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_free.average`</p> |
|Azure |Azure PostgreSQL: Backup storage used |<p>Used backup storage expressed in bytes.</p> |DEPENDENT |azure.db.pgsql.storage.backup.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.backup_storage_used.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: CPU credits remaining |<p>The total number of credits available to burst.</p> |DEPENDENT |azure.db.pgsql.cpu.credits.remaining<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_credits_remaining.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: CPU credits consumed |<p>The total number of credits consumed by the database server.</p> |DEPENDENT |azure.db.pgsql.cpu.credits.consumed<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_credits_consumed.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: Data disk queue depth |<p>The number of outstanding I/O operations to the data disk.</p> |DEPENDENT |azure.db.pgsql.disk.queue.depth<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.disk_queue_depth.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: Data disk IOPS |<p>I/O operations per second.</p> |DEPENDENT |azure.db.pgsql.iops<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.iops.average`</p> |
|Azure |Azure PostgreSQL: Data disk read IOPS |<p>The number of the data disk I/O read operations per second.</p> |DEPENDENT |azure.db.pgsql.iops.read<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.read_iops.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: Data disk write IOPS |<p>The number of the data disk I/O write operations per second.</p> |DEPENDENT |azure.db.pgsql.iops.write<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.write_iops.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: Data disk read Bps |<p>Bytes read per second from the data disk during the monitoring period.</p> |DEPENDENT |azure.db.pgsql.disk.bps.read<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.read_throughput.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: Data disk write Bps |<p>Bytes written per second to the data disk during the monitoring period.</p> |DEPENDENT |azure.db.pgsql.disk.bps.write<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.write_throughput.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: Transaction log storage used |<p>The storage space used by a transaction log expressed in bytes.</p> |DEPENDENT |azure.db.pgsql.storage.txlogs.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.txlogs_storage_used.average`</p> |
|Azure |Azure PostgreSQL: Maximum used transaction IDs |<p>The maximum number of used transaction IDs.</p> |DEPENDENT |azure.db.pgsql.txid.used.max<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.maximum_used_transactionIDs.average`</p> |

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Azure PostgreSQL: There are errors in requests to API |<p>Zabbix has received errors in response to API requests.</p> |`length(last(/Azure PostgreSQL Flexible Server by HTTP/azure.db.pgsql.data.errors))>0` |AVERAGE | |
|Azure PostgreSQL: PostgreSQL server is unavailable |<p>The resource state is unavailable.</p> |`last(/Azure PostgreSQL Flexible Server by HTTP/azure.db.pgsql.availability.state)=2` |HIGH | |
|Azure PostgreSQL: PostgreSQL server is degraded |<p>The resource is in degraded state.</p> |`last(/Azure PostgreSQL Flexible Server by HTTP/azure.db.pgsql.availability.state)=1` |AVERAGE | |
|Azure PostgreSQL: PostgreSQL server is in unknown state |<p>The resource state is unknown.</p> |`last(/Azure PostgreSQL Flexible Server by HTTP/azure.db.pgsql.availability.state)=3` |WARNING | |
|Azure PostgreSQL: High CPU utilization |<p>The CPU utilization is too high. The system might be slow to respond.</p> |`min(/Azure PostgreSQL Flexible Server by HTTP/azure.db.pgsql.cpu.percentage,5m)>{$AZURE.DB.CPU.UTIL.CRIT}` |HIGH | |
|Azure PostgreSQL: High memory utilization |<p>The system is running out of free memory.</p> |`min(/Azure PostgreSQL Flexible Server by HTTP/azure.db.pgsql.memory.percentage,5m)>{$AZURE.DB.MEMORY.UTIL.CRIT}` |AVERAGE | |
|Azure PostgreSQL: Storage space is critically low |<p>Critical utilization of the storage space.</p> |`last(/Azure PostgreSQL Flexible Server by HTTP/azure.db.pgsql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.CRIT}` |AVERAGE | |
|Azure PostgreSQL: Storage space is low |<p>High utilization of the storage space.</p> |`last(/Azure PostgreSQL Flexible Server by HTTP/azure.db.pgsql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.WARN}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

# Azure PostgreSQL Single Server by HTTP

## Overview

This template is designed to monitor Microsoft Azure PostgreSQL servers by HTTP.
It works without any external scripts and uses the script item.

## Requirements

For Zabbix version: 6.4 and higher.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

> See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure the macros: `{$AZURE.APP.ID}`, `{$AZURE.PASSWORD}`, `{$AZURE.TENANT.ID}`, `{$AZURE.SUBSCRIPTION.ID}`, and `{$AZURE.RESOURCE.ID}`.

## Configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.APP.ID} |<p>The App ID of Microsoft Azure.</p> |`` |
|{$AZURE.DATA.TIMEOUT} |<p>A response timeout for an API.</p> |`60s` |
|{$AZURE.DB.CPU.UTIL.CRIT} |<p>The critical threshold of CPU utilization expressed in %.</p> |`90` |
|{$AZURE.DB.MEMORY.UTIL.CRIT} |<p>The critical threshold of memory utilization expressed in %.</p> |`90` |
|{$AZURE.DB.STORAGE.PUSED.CRIT} |<p>The critical threshold of storage utilization expressed in %.</p> |`90` |
|{$AZURE.DB.STORAGE.PUSED.WARN} |<p>The warning threshold of storage utilization expressed in %.</p> |`80` |
|{$AZURE.PASSWORD} |<p>Microsoft Azure password.</p> |`` |
|{$AZURE.RESOURCE.ID} |<p>Microsoft Azure PostgreSQL server ID.</p> |`` |
|{$AZURE.SUBSCRIPTION.ID} |<p>Microsoft Azure subscription ID.</p> |`` |
|{$AZURE.TENANT.ID} |<p>Microsoft Azure tenant ID.</p> |`` |

### Template links

There are no template links in this template.

### Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Azure |Azure PostgreSQL: Get data |<p>The result of API requests is expressed in the JSON.</p> |SCRIPT |azure.db.pgsql.data.get<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Azure |Azure PostgreSQL: Get errors |<p>A list of errors from API requests.</p> |DEPENDENT |azure.db.pgsql.data.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.errors`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure PostgreSQL: Availability state |<p>The availability status of the resource.</p> |DEPENDENT |azure.db.pgsql.availability.state<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.availabilityState`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- STR_REPLACE: `Available 0`</p><p>- STR_REPLACE: `Degraded 1`</p><p>- STR_REPLACE: `Unavailable 2`</p><p>- STR_REPLACE: `Unknown 3`</p><p>- IN_RANGE: `0 3 `</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure PostgreSQL: Availability status detailed |<p>The summary description of the availability status.</p> |DEPENDENT |azure.db.pgsql.availability.details<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.summary`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure PostgreSQL: Percentage CPU |<p>The CPU percent of a host.</p> |DEPENDENT |azure.db.pgsql.cpu.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_percent.average`</p> |
|Azure |Azure PostgreSQL: Memory utilization |<p>The memory percent of a host.</p> |DEPENDENT |azure.db.pgsql.memory.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.memory_percent.average`</p> |
|Azure |Azure PostgreSQL: Network out |<p>The network outbound traffic across the active connections.</p> |DEPENDENT |azure.db.pgsql.network.egress<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.network_bytes_egress.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.1333`</p> |
|Azure |Azure PostgreSQL: Network in |<p>The network inbound traffic across the active connections.</p> |DEPENDENT |azure.db.pgsql.network.ingress<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.network_bytes_ingress.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `0.1333`</p> |
|Azure |Azure PostgreSQL: Connections active |<p>The count of active connections.</p> |DEPENDENT |azure.db.pgsql.connections.active<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.active_connections.average`</p> |
|Azure |Azure PostgreSQL: Connections failed |<p>The count of failed connections.</p> |DEPENDENT |azure.db.pgsql.connections.failed<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.connections_failed.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: IO consumption percent |<p>The consumption percent of I/O.</p> |DEPENDENT |azure.db.pgsql.io.consumption.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.io_consumption_percent.average`</p> |
|Azure |Azure PostgreSQL: Storage percent |<p>The storage utilization expressed in %.</p> |DEPENDENT |azure.db.pgsql.storage.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_percent.average`</p> |
|Azure |Azure PostgreSQL: Storage used |<p>Used storage space expressed in bytes.</p> |DEPENDENT |azure.db.pgsql.storage.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_used.average`</p> |
|Azure |Azure PostgreSQL: Storage limit |<p>The storage limit expressed in bytes.</p> |DEPENDENT |azure.db.pgsql.storage.limit<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_limit.maximum`</p> |
|Azure |Azure PostgreSQL: Backup storage used |<p>Used backup storage expressed in bytes.</p> |DEPENDENT |azure.db.pgsql.storage.backup.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.backup_storage_used.average`</p> |
|Azure |Azure PostgreSQL: Replication lag |<p>The replication lag expressed in seconds.</p> |DEPENDENT |azure.db.pgsql.replica.log.delay<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.pg_replica_log_delay_in_seconds.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: Max lag across replicas in bytes |<p>Lag expressed in bytes for the most lagging replica.</p> |DEPENDENT |azure.db.pgsql.replica.log.delay.bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.pg_replica_log_delay_in_bytes.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure PostgreSQL: Server log storage percent |<p>The storage utilization by a server log expressed in %.</p> |DEPENDENT |azure.db.pgsql.storage.server.log.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.serverlog_storage_percent.average`</p> |
|Azure |Azure PostgreSQL: Server log storage used |<p>The storage space used by a server log expressed in bytes.</p> |DEPENDENT |azure.db.pgsql.storage.server.log.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.serverlog_storage_usage.average`</p> |
|Azure |Azure PostgreSQL: Server log storage limit |<p>The storage limit of a server log expressed in bytes.</p> |DEPENDENT |azure.db.pgsql.storage.server.log.limit<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.serverlog_storage_limit.maximum`</p> |

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Azure PostgreSQL: There are errors in requests to API |<p>Zabbix has received errors in response to API requests.</p> |`length(last(/Azure PostgreSQL Single Server by HTTP/azure.db.pgsql.data.errors))>0` |AVERAGE | |
|Azure PostgreSQL: PostgreSQL server is unavailable |<p>The resource state is unavailable.</p> |`last(/Azure PostgreSQL Single Server by HTTP/azure.db.pgsql.availability.state)=2` |HIGH | |
|Azure PostgreSQL: PostgreSQL server is degraded |<p>The resource is in degraded state.</p> |`last(/Azure PostgreSQL Single Server by HTTP/azure.db.pgsql.availability.state)=1` |AVERAGE | |
|Azure PostgreSQL: PostgreSQL server is in unknown state |<p>The resource state is unknown.</p> |`last(/Azure PostgreSQL Single Server by HTTP/azure.db.pgsql.availability.state)=3` |WARNING | |
|Azure PostgreSQL: High CPU utilization |<p>The CPU utilization is too high. The system might be slow to respond.</p> |`min(/Azure PostgreSQL Single Server by HTTP/azure.db.pgsql.cpu.percentage,5m)>{$AZURE.DB.CPU.UTIL.CRIT}` |HIGH | |
|Azure PostgreSQL: High memory utilization |<p>The system is running out of free memory.</p> |`min(/Azure PostgreSQL Single Server by HTTP/azure.db.pgsql.memory.percentage,5m)>{$AZURE.DB.MEMORY.UTIL.CRIT}` |AVERAGE | |
|Azure PostgreSQL: Storage space is critically low |<p>Critical utilization of the storage space.</p> |`last(/Azure PostgreSQL Single Server by HTTP/azure.db.pgsql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.CRIT}` |AVERAGE | |
|Azure PostgreSQL: Storage space is low |<p>High utilization of the storage space.</p> |`last(/Azure PostgreSQL Single Server by HTTP/azure.db.pgsql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.WARN}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

# Azure Microsoft SQL Serverless Database by HTTP

## Overview

This template is designed to monitor Microsoft SQL serverless databases by HTTP.
It works without any external scripts and uses the script item.

## Requirements

For Zabbix version: 6.4 and higher.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

> See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure the macros: `{$AZURE.APP.ID}`, `{$AZURE.PASSWORD}`, `{$AZURE.TENANT.ID}`, `{$AZURE.SUBSCRIPTION.ID}`, and `{$AZURE.RESOURCE.ID}`.

## Configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.APP.ID} |<p>The App ID of Microsoft Azure.</p> |`` |
|{$AZURE.DATA.TIMEOUT} |<p>A response timeout for an API.</p> |`60s` |
|{$AZURE.DB.CPU.UTIL.CRIT} |<p>The critical threshold of CPU utilization expressed in %.</p> |`90` |
|{$AZURE.DB.MEMORY.UTIL.CRIT} |<p>The critical threshold of memory utilization expressed in %.</p> |`90` |
|{$AZURE.DB.STORAGE.PUSED.CRIT} |<p>The critical threshold of storage utilization expressed in %.</p> |`90` |
|{$AZURE.DB.STORAGE.PUSED.WARN} |<p>The warning threshold of storage utilization expressed in %.</p> |`80` |
|{$AZURE.PASSWORD} |<p>Microsoft Azure password.</p> |`` |
|{$AZURE.RESOURCE.ID} |<p>Microsoft Azure Microsoft SQL database ID.</p> |`` |
|{$AZURE.SUBSCRIPTION.ID} |<p>Microsoft Azure subscription ID.</p> |`` |
|{$AZURE.TENANT.ID} |<p>Microsoft Azure tenant ID.</p> |`` |

### Template links

There are no template links in this template.

### Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Azure |Azure Microsoft SQL: Get data |<p>The result of API requests is expressed in the JSON.</p> |SCRIPT |azure.db.mssql.data.get<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Azure |Azure Microsoft SQL: Get errors |<p>A list of errors from API requests.</p> |DEPENDENT |azure.db.mssql.data.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.errors`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure Microsoft SQL: Availability state |<p>The availability status of the resource.</p> |DEPENDENT |azure.db.mssql.availability.state<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.availabilityState`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- STR_REPLACE: `Available 0`</p><p>- STR_REPLACE: `Degraded 1`</p><p>- STR_REPLACE: `Unavailable 2`</p><p>- STR_REPLACE: `Unknown 3`</p><p>- IN_RANGE: `0 3 `</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure Microsoft SQL: Availability status detailed |<p>The summary description of the availability status.</p> |DEPENDENT |azure.db.mssql.availability.details<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.summary`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure Microsoft SQL: Percentage CPU |<p>The CPU percent of a host.</p> |DEPENDENT |azure.db.mssql.cpu.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_percent.average`</p> |
|Azure |Azure Microsoft SQL: Data IO percentage |<p>The physical data read percentage.</p> |DEPENDENT |azure.db.mssql.data.read.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.physical_data_read_percent.average`</p> |
|Azure |Azure Microsoft SQL: Log IO percentage |<p>The percentage of I/O log. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.log.write.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.log_write_percent.average`</p> |
|Azure |Azure Microsoft SQL: Data space used |<p>Data space used. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.storage.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure Microsoft SQL: Connections successful |<p>The count of successful connections.</p> |DEPENDENT |azure.db.mssql.connections.successful<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.connection_successful.total`</p> |
|Azure |Azure Microsoft SQL: Connections failed: System errors |<p>The count of failed connections with system errors.</p> |DEPENDENT |azure.db.mssql.connections.failed.system<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.connection_failed.total`</p> |
|Azure |Azure Microsoft SQL: Connections blocked by firewall |<p>The count of connections blocked by a firewall.</p> |DEPENDENT |azure.db.mssql.firewall.blocked<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.blocked_by_firewall.total`</p> |
|Azure |Azure Microsoft SQL: Deadlocks |<p>The count of deadlocks. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.deadlocks<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.deadlock.total`</p> |
|Azure |Azure Microsoft SQL: Data space used percent |<p>The percentage of used data space. Not applicable to the data warehouses or hyperscale databases.</p> |DEPENDENT |azure.db.mssql.storage.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_percent.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure Microsoft SQL: In-Memory OLTP storage percent |<p>In-Memory OLTP storage percent. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.storage.xtp.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.xtp_storage_percent.average`</p> |
|Azure |Azure Microsoft SQL: Workers percentage |<p>The percentage of workers. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.workers.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.workers_percent.average`</p> |
|Azure |Azure Microsoft SQL: Sessions percentage |<p>The percentage of sessions. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.sessions.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.sessions_percent.average`</p> |
|Azure |Azure Microsoft SQL: CPU limit |<p>The CPU limit. Applies to the vCore-based databases.</p> |DEPENDENT |azure.db.mssql.cpu.limit<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_limit.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure Microsoft SQL: CPU used |<p>The CPU used. Applies to the vCore-based databases.</p> |DEPENDENT |azure.db.mssql.cpu.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_used.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure Microsoft SQL: SQL Server process core percent |<p>The CPU usage as a percentage of the SQL DB process. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.server.cpu.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.sqlserver_process_core_percent.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure Microsoft SQL: SQL Server process memory percent |<p>Memory usage as a percentage of the SQL DB process. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.server.memory.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.sqlserver_process_memory_percent.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure Microsoft SQL: Tempdb data file size |<p>Space used in `tempdb` data files expressed in bytes. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.tempdb.data.size<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.tempdb_data_size.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `1024`</p> |
|Azure |Azure Microsoft SQL: Tempdb log file size |<p>Space used in `tempdb` transaction log files expressed in bytes. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.tempdb.log.size<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.tempdb_log_size.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `1024`</p> |
|Azure |Azure Microsoft SQL: Tempdb log used percent |<p>The percentage of space used in `tempdb` transaction log files. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.tempdb.log.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.tempdb_log_used_percent.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure Microsoft SQL: App CPU billed |<p>App CPU billed. Applies to serverless databases.</p> |DEPENDENT |azure.db.mssql.app.cpu.billed<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.app_cpu_billed.total`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure Microsoft SQL: App CPU percentage |<p>App CPU percentage. Applies to serverless databases.</p> |DEPENDENT |azure.db.mssql.app.cpu.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.app_cpu_percent.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure Microsoft SQL: App memory percentage |<p>App memory percentage. Applies to serverless databases.</p> |DEPENDENT |azure.db.mssql.app.memory.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.app_memory_percent.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure Microsoft SQL: Data space allocated |<p>The allocated data storage. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.storage.allocated<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.allocated_data_storage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Azure Microsoft SQL: There are errors in requests to API |<p>Zabbix has received errors in response to API requests.</p> |`length(last(/Azure Microsoft SQL Serverless Database by HTTP/azure.db.mssql.data.errors))>0` |AVERAGE | |
|Azure Microsoft SQL: Microsoft SQL database is unavailable |<p>The resource state is unavailable.</p> |`last(/Azure Microsoft SQL Serverless Database by HTTP/azure.db.mssql.availability.state)=2` |HIGH | |
|Azure Microsoft SQL: Microsoft SQL database is degraded |<p>The resource is in degraded state.</p> |`last(/Azure Microsoft SQL Serverless Database by HTTP/azure.db.mssql.availability.state)=1` |AVERAGE | |
|Azure Microsoft SQL: Microsoft SQL database is in unknown state |<p>The resource state is unknown.</p> |`last(/Azure Microsoft SQL Serverless Database by HTTP/azure.db.mssql.availability.state)=3` |WARNING | |
|Azure Microsoft SQL: High CPU utilization |<p>The CPU utilization is too high. The system might be slow to respond.</p> |`min(/Azure Microsoft SQL Serverless Database by HTTP/azure.db.mssql.cpu.percentage,5m)>{$AZURE.DB.CPU.UTIL.CRIT}` |HIGH | |
|Azure Microsoft SQL: Storage space is critically low |<p>Critical utilization of the storage space.</p> |`last(/Azure Microsoft SQL Serverless Database by HTTP/azure.db.mssql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.CRIT}` |AVERAGE | |
|Azure Microsoft SQL: Storage space is low |<p>High utilization of the storage space.</p> |`last(/Azure Microsoft SQL Serverless Database by HTTP/azure.db.mssql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.WARN}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

# Azure Microsoft SQL Database by HTTP

## Overview

This template is designed to monitor Microsoft SQL databases by HTTP.
It works without any external scripts and uses the script item.

## Requirements

For Zabbix version: 6.4 and higher.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

> See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure the macros: `{$AZURE.APP.ID}`, `{$AZURE.PASSWORD}`, `{$AZURE.TENANT.ID}`, `{$AZURE.SUBSCRIPTION.ID}`, and `{$AZURE.RESOURCE.ID}`.

## Configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.APP.ID} |<p>The App ID of Microsoft Azure.</p> |`` |
|{$AZURE.DATA.TIMEOUT} |<p>A response timeout for an API.</p> |`60s` |
|{$AZURE.DB.CPU.UTIL.CRIT} |<p>The critical threshold of CPU utilization expressed in %.</p> |`90` |
|{$AZURE.DB.MEMORY.UTIL.CRIT} |<p>The critical threshold of memory utilization expressed in %.</p> |`90` |
|{$AZURE.DB.STORAGE.PUSED.CRIT} |<p>The critical threshold of storage utilization expressed in %.</p> |`90` |
|{$AZURE.DB.STORAGE.PUSED.WARN} |<p>The warning threshold of storage utilization expressed in %.</p> |`80` |
|{$AZURE.PASSWORD} |<p>Microsoft Azure password.</p> |`` |
|{$AZURE.RESOURCE.ID} |<p>Microsoft Azure Microsoft SQL database ID.</p> |`` |
|{$AZURE.SUBSCRIPTION.ID} |<p>Microsoft Azure subscription ID.</p> |`` |
|{$AZURE.TENANT.ID} |<p>Microsoft Azure tenant ID.</p> |`` |

### Template links

There are no template links in this template.

### Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Azure |Azure Microsoft SQL: Get data |<p>The result of API requests is expressed in the JSON.</p> |SCRIPT |azure.db.mssql.data.get<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Azure |Azure Microsoft SQL: Get errors |<p>A list of errors from API requests.</p> |DEPENDENT |azure.db.mssql.data.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.errors`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure Microsoft SQL: Availability state |<p>The availability status of the resource.</p> |DEPENDENT |azure.db.mssql.availability.state<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.availabilityState`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- STR_REPLACE: `Available 0`</p><p>- STR_REPLACE: `Degraded 1`</p><p>- STR_REPLACE: `Unavailable 2`</p><p>- STR_REPLACE: `Unknown 3`</p><p>- IN_RANGE: `0 3 `</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 3`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure Microsoft SQL: Availability status detailed |<p>The summary description of the availability status.</p> |DEPENDENT |azure.db.mssql.availability.details<p>**Preprocessing**:</p><p>- JSONPATH: `$.health.summary`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Azure |Azure Microsoft SQL: Percentage CPU |<p>The CPU percent of a host.</p> |DEPENDENT |azure.db.mssql.cpu.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_percent.average`</p> |
|Azure |Azure Microsoft SQL: Data IO percentage |<p>The percentage of physical data read.</p> |DEPENDENT |azure.db.mssql.data.read.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.physical_data_read_percent.average`</p> |
|Azure |Azure Microsoft SQL: Log IO percentage |<p>The percentage of I/O log. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.log.write.percentage<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.log_write_percent.average`</p> |
|Azure |Azure Microsoft SQL: Data space used |<p>Data space used. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.storage.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure Microsoft SQL: Connections successful |<p>The count of successful connections.</p> |DEPENDENT |azure.db.mssql.connections.successful<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.connection_successful.total`</p> |
|Azure |Azure Microsoft SQL: Connections failed: System errors |<p>The count of failed connections with system errors.</p> |DEPENDENT |azure.db.mssql.connections.failed.system<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.connection_failed.total`</p> |
|Azure |Azure Microsoft SQL: Connections blocked by firewall |<p>The count of connections blocked by a firewall.</p> |DEPENDENT |azure.db.mssql.firewall.blocked<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.blocked_by_firewall.total`</p> |
|Azure |Azure Microsoft SQL: Deadlocks |<p>The count of deadlocks. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.deadlocks<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.deadlock.total`</p> |
|Azure |Azure Microsoft SQL: Data space used percent |<p>Data space used percent. Not applicable to the data warehouses or hyperscale databases.</p> |DEPENDENT |azure.db.mssql.storage.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.storage_percent.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure Microsoft SQL: In-Memory OLTP storage percent |<p>In-Memory OLTP storage percent. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.storage.xtp.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.xtp_storage_percent.average`</p> |
|Azure |Azure Microsoft SQL: Workers percentage |<p>The percantage of workers. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.workers.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.workers_percent.average`</p> |
|Azure |Azure Microsoft SQL: Sessions percentage |<p>The percentage of sessions. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.sessions.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.sessions_percent.average`</p> |
|Azure |Azure Microsoft SQL: Sessions count |<p>The number of active sessions. Not applicable to Synapse DW Analytics.</p> |DEPENDENT |azure.db.mssql.sessions.count<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.sessions_count.average`</p> |
|Azure |Azure Microsoft SQL: CPU limit |<p>The CPU limit. Applies to the vCore-based databases.</p> |DEPENDENT |azure.db.mssql.cpu.limit<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_limit.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure Microsoft SQL: CPU used |<p>The CPU used. Applies to the vCore-based databases.</p> |DEPENDENT |azure.db.mssql.cpu.used<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cpu_used.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure Microsoft SQL: SQL Server process core percent |<p>The CPU usage as a percentage of the SQL DB process. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.server.cpu.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.sqlserver_process_core_percent.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure Microsoft SQL: SQL Server process memory percent |<p>Memory usage as a percentage of the SQL DB process. Not applicable to data warehouses.</p> |DEPENDENT |azure.db.mssql.server.memory.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.sqlserver_process_memory_percent.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure Microsoft SQL: Tempdb data file size |<p>The space used in `tempdb` data files expressed in bytes. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.tempdb.data.size<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.tempdb_data_size.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `1024`</p> |
|Azure |Azure Microsoft SQL: Tempdb log file size |<p>The space used in `tempdb` transaction log file in bytes. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.tempdb.log.size<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.tempdb_log_size.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- MULTIPLIER: `1024`</p> |
|Azure |Azure Microsoft SQL: Tempdb log used percent |<p>The percentage of space used in `tempdb` transaction log file. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.tempdb.log.percent<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.tempdb_log_used_percent.maximum`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure Microsoft SQL: Data space allocated |<p>The allocated data storage. Not applicable to the data warehouses.</p> |DEPENDENT |azure.db.mssql.storage.allocated<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.allocated_data_storage.average`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Azure |Azure Microsoft SQL: Full backup storage size |<p>Cumulative full backup storage size. Applies to the vCore-based databases. Not applicable to the Hyperscale databases.</p> |DEPENDENT |azure.db.mssql.storage.backup.size<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.full_backup_size_bytes.maximum`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Azure |Azure Microsoft SQL: Differential backup storage size |<p>Cumulative differential backup storage size. Applies to the vCore-based databases. Not applicable to the Hyperscale databases.</p> |DEPENDENT |azure.db.mssql.storage.backup.diff.size<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.diff_backup_size_bytes.maximum`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Azure |Azure Microsoft SQL: Log backup storage size |<p>Cumulative log backup storage size. Applies to the vCore-based and Hyperscale databases.</p> |DEPENDENT |azure.db.mssql.storage.backup.log.size<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.log_backup_size_bytes.maximum`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Azure Microsoft SQL: There are errors in requests to API |<p>Zabbix has received errors in response to API requests.</p> |`length(last(/Azure Microsoft SQL Database by HTTP/azure.db.mssql.data.errors))>0` |AVERAGE | |
|Azure Microsoft SQL: Microsoft SQL database is unavailable |<p>The resource state is unavailable.</p> |`last(/Azure Microsoft SQL Database by HTTP/azure.db.mssql.availability.state)=2` |HIGH | |
|Azure Microsoft SQL: Microsoft SQL database is degraded |<p>The resource is in degraded state.</p> |`last(/Azure Microsoft SQL Database by HTTP/azure.db.mssql.availability.state)=1` |AVERAGE | |
|Azure Microsoft SQL: Microsoft SQL database is in unknown state |<p>The resource state is unknown.</p> |`last(/Azure Microsoft SQL Database by HTTP/azure.db.mssql.availability.state)=3` |WARNING | |
|Azure Microsoft SQL: High CPU utilization |<p>The CPU utilization is too high. The system might be slow to respond.</p> |`min(/Azure Microsoft SQL Database by HTTP/azure.db.mssql.cpu.percentage,5m)>{$AZURE.DB.CPU.UTIL.CRIT}` |HIGH | |
|Azure Microsoft SQL: Storage space is critically low |<p>Critical utilization of the storage space.</p> |`last(/Azure Microsoft SQL Database by HTTP/azure.db.mssql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.CRIT}` |AVERAGE | |
|Azure Microsoft SQL: Storage space is low |<p>High utilization of the storage space.</p> |`last(/Azure Microsoft SQL Database by HTTP/azure.db.mssql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.WARN}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

