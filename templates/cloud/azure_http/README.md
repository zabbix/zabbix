
# Azure by HTTP

## Overview

- This template is designed to monitor Microsoft Azure by HTTP.
- It works without any external scripts and uses the script item.
- Currently, the template supports the discovery of virtual machines (VMs), VM scale sets, Cosmos DB for MongoDB, storage accounts, Microsoft SQL, MySQL, and PostgreSQL servers.

## Included Monitoring Templates

- *Azure Virtual Machine by HTTP*
- *Azure VM Scale Set by HTTP*
- *Azure MySQL Flexible Server by HTTP*
- *Azure MySQL Single Server by HTTP*
- *Azure PostgreSQL Flexible Server by HTTP*
- *Azure PostgreSQL Single Server by HTTP*
- *Azure Microsoft SQL Serverless Database by HTTP*
- *Azure Microsoft SQL DTU Database by HTTP*
- *Azure Microsoft SQL Database by HTTP*
- *Azure SQL Managed Instance by HTTP*
- *Azure Cost Management by HTTP*

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Microsoft Azure

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

> See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure the macros: `{$AZURE.APP.ID}`, `{$AZURE.PASSWORD}`, `{$AZURE.TENANT.ID}`, and `{$AZURE.SUBSCRIPTION.ID}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$AZURE.APP.ID}|<p>The App ID of Microsoft Azure.</p>||
|{$AZURE.PASSWORD}|<p>Microsoft Azure password.</p>||
|{$AZURE.DATA.TIMEOUT}|<p>API response timeout.</p>|`15s`|
|{$AZURE.TENANT.ID}|<p>Microsoft Azure tenant ID.</p>||
|{$AZURE.SUBSCRIPTION.ID}|<p>Microsoft Azure subscription ID.</p>||
|{$AZURE.VM.NAME.MATCHES}|<p>This macro is used in virtual machines discovery rule.</p>|`.*`|
|{$AZURE.VM.NAME.NOT.MATCHES}|<p>This macro is used in virtual machines discovery rule.</p>|`CHANGE_IF_NEEDED`|
|{$AZURE.VM.LOCATION.MATCHES}|<p>This macro is used in virtual machines discovery rule.</p>|`.*`|
|{$AZURE.VM.LOCATION.NOT.MATCHES}|<p>This macro is used in virtual machines discovery rule.</p>|`CHANGE_IF_NEEDED`|
|{$AZURE.SCALESET.NAME.MATCHES}|<p>This macro is used in virtual machine scale sets discovery rule.</p>|`.*`|
|{$AZURE.SCALESET.NAME.NOT.MATCHES}|<p>This macro is used in virtual machine scale sets discovery rule.</p>|`CHANGE_IF_NEEDED`|
|{$AZURE.SCALESET.LOCATION.MATCHES}|<p>This macro is used in virtual machine scale sets discovery rule.</p>|`.*`|
|{$AZURE.SCALESET.LOCATION.NOT.MATCHES}|<p>This macro is used in virtual machine scale sets discovery rule.</p>|`CHANGE_IF_NEEDED`|
|{$AZURE.SQL.INST.NAME.MATCHES}|<p>This macro is used in Azure SQL Managed Instance discovery rule.</p>|`.*`|
|{$AZURE.SQL.INST.NAME.NOT.MATCHES}|<p>This macro is used in Azure SQL Managed Instance discovery rule.</p>|`CHANGE_IF_NEEDED`|
|{$AZURE.SQL.INST.LOCATION.MATCHES}|<p>This macro is used in Azure SQL Managed Instance discovery rule.</p>|`.*`|
|{$AZURE.SQL.INST.LOCATION.NOT.MATCHES}|<p>This macro is used in Azure SQL Managed Instance discovery rule.</p>|`CHANGE_IF_NEEDED`|
|{$AZURE.STORAGE.ACC.NAME.MATCHES}|<p>This macro is used in storage accounts discovery rule.</p>|`.*`|
|{$AZURE.STORAGE.ACC.NAME.NOT.MATCHES}|<p>This macro is used in storage accounts discovery rule.</p>|`CHANGE_IF_NEEDED`|
|{$AZURE.STORAGE.ACC.LOCATION.MATCHES}|<p>This macro is used in storage accounts discovery rule.</p>|`.*`|
|{$AZURE.STORAGE.ACC.LOCATION.NOT.MATCHES}|<p>This macro is used in storage accounts discovery rule.</p>|`CHANGE_IF_NEEDED`|
|{$AZURE.STORAGE.ACC.AVAILABILITY}|<p>The warning threshold of the storage account availability.</p>|`70`|
|{$AZURE.STORAGE.ACC.BLOB.AVAILABILITY}|<p>The warning threshold of the storage account blob services availability.</p>|`70`|
|{$AZURE.STORAGE.ACC.TABLE.AVAILABILITY}|<p>The warning threshold of the storage account table services availability.</p>|`70`|
|{$AZURE.RESOURCE.GROUP.MATCHES}|<p>This macro is used in discovery rules.</p>|`.*`|
|{$AZURE.RESOURCE.GROUP.NOT.MATCHES}|<p>This macro is used in discovery rules.</p>|`CHANGE_IF_NEEDED`|
|{$AZURE.MYSQL.DB.NAME.MATCHES}|<p>This macro is used in MySQL servers discovery rule.</p>|`.*`|
|{$AZURE.MYSQL.DB.NAME.NOT.MATCHES}|<p>This macro is used in MySQL servers discovery rule.</p>|`CHANGE_IF_NEEDED`|
|{$AZURE.MYSQL.DB.LOCATION.MATCHES}|<p>This macro is used in MySQL servers discovery rule.</p>|`.*`|
|{$AZURE.MYSQL.DB.LOCATION.NOT.MATCHES}|<p>This macro is used in MySQL servers discovery rule.</p>|`CHANGE_IF_NEEDED`|
|{$AZURE.PGSQL.DB.NAME.MATCHES}|<p>This macro is used in PostgreSQL servers discovery rule.</p>|`.*`|
|{$AZURE.PGSQL.DB.NAME.NOT.MATCHES}|<p>This macro is used in PostgreSQL servers discovery rule.</p>|`CHANGE_IF_NEEDED`|
|{$AZURE.PGSQL.DB.LOCATION.MATCHES}|<p>This macro is used in PostgreSQL servers discovery rule.</p>|`.*`|
|{$AZURE.PGSQL.DB.LOCATION.NOT.MATCHES}|<p>This macro is used in PostgreSQL servers discovery rule.</p>|`CHANGE_IF_NEEDED`|
|{$AZURE.MSSQL.DB.NAME.MATCHES}|<p>This macro is used in Microsoft SQL databases discovery rule.</p>|`.*`|
|{$AZURE.MSSQL.DB.NAME.NOT.MATCHES}|<p>This macro is used in Microsoft SQL databases discovery rule.</p>|`CHANGE_IF_NEEDED`|
|{$AZURE.MSSQL.DB.LOCATION.MATCHES}|<p>This macro is used in Microsoft SQL databases discovery rule.</p>|`.*`|
|{$AZURE.MSSQL.DB.LOCATION.NOT.MATCHES}|<p>This macro is used in Microsoft SQL databases discovery rule.</p>|`CHANGE_IF_NEEDED`|
|{$AZURE.MSSQL.DB.SIZE.NOT.MATCHES}|<p>This macro is used in Microsoft SQL databases discovery rule.</p>|`^System$`|
|{$AZURE.COSMOS.MONGO.DB.NAME.MATCHES}|<p>This macro is used in Microsoft Cosmos DB account discovery rule.</p>|`.*`|
|{$AZURE.COSMOS.MONGO.DB.NAME.NOT.MATCHES}|<p>This macro is used in Microsoft Cosmos DB account discovery rule.</p>|`CHANGE_IF_NEEDED`|
|{$AZURE.COSMOS.MONGO.DB.LOCATION.MATCHES}|<p>This macro is used in Microsoft Cosmos DB account discovery rule.</p>|`.*`|
|{$AZURE.COSMOS.MONGO.DB.LOCATION.NOT.MATCHES}|<p>This macro is used in Microsoft Cosmos DB account discovery rule.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get resources|<p>The result of API requests is expressed in the JSON.</p>|Script|azure.get.resources|
|Get errors|<p>A list of errors from API requests.</p>|Dependent item|azure.get.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.errors`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get storage accounts|<p>The result of API requests is expressed in the JSON.</p>|Script|azure.get.storage.acc|
|Get storage accounts errors|<p>The errors from API requests.</p>|Dependent item|azure.get.storage.acc.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Azure: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/Azure by HTTP/azure.get.errors))>0`|Average||
|Azure: There are errors in storages requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/Azure by HTTP/azure.get.storage.acc.errors))>0`|Average|**Depends on**:<br><ul><li>Azure: There are errors in requests to API</li></ul>|

### LLD rule Storage accounts discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage accounts discovery|<p>The list of all storage accounts available under the subscription.</p>|Dependent item|azure.storage.acc.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Storage accounts discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage account [{#NAME}]: Get data|<p>The HTTP API endpoint that returns storage metrics with the name `[{#NAME}]`.</p>|Script|azure.get.storage.acc[{#NAME}]|
|Storage account [{#NAME}]: Used Capacity|<p>The amount of storage used by the storage account with the name `[{#NAME}]`, expressed in bytes.</p><p>For standard storage accounts, it's the sum of capacity used by blob, table, file, and queue.</p><p>For premium storage accounts and blob storage accounts, it is the same as BlobCapacity or FileCapacity.</p>|Dependent item|azure.storage.used.capacity[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.storageAccount.UsedCapacity.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Transactions|<p>The number of requests made to the storage service or a specified API operation.</p><p>This number includes successful and failed requests and also requests that produced errors.</p><p>Use `ResponseType` dimension for the number of different types of responses.</p>|Dependent item|azure.storage.transactions[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.storageAccount.Transactions.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Ingress|<p>The amount of ingress data, expressed in bytes. This number includes ingress from an external client into Azure Storage and also ingress within Azure.</p>|Dependent item|azure.storage.ingress[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.storageAccount.Ingress.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Egress|<p>The amount of egress data. This number includes egress to external client from Azure Storage and also egress within Azure.</p><p>As a result, this number does not reflect billable egress.</p>|Dependent item|azure.storage.engress[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.storageAccount.Egress.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Success Server Latency|<p>The average time used to process a successful request by Azure Storage.</p><p>This value does not include the network latency specified in `SuccessE2ELatency`.</p>|Dependent item|azure.storage.success.server.latency[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.storageAccount.SuccessServerLatency.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Success E2E Latency|<p>The average end-to-end latency of successful requests made to a storage service or the specified API operation, expressed in milliseconds.</p><p>This value includes the required processing time within Azure Storage to read the request, send the response, and receive acknowledgment of the response.</p>|Dependent item|azure.storage.success.e2e.latency[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.storageAccount.SuccessE2ELatency.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Availability|<p>The percentage of availability for the storage service or a specified API operation.</p><p>Availability is calculated by taking the `TotalBillableRequests` value and dividing it by the number of applicable requests, including those that produced unexpected errors.</p><p>All unexpected errors result in reduced availability for the storage service or the specified API operation.</p>|Dependent item|azure.storage.availability[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.storageAccount.Availability.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Blob Capacity|<p>The amount of storage used by the blob service of the storage account with the name `[{#NAME}]`, expressed in bytes.</p>|Dependent item|azure.storage.blob.capacity[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blobServices.BlobCapacity.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Blob Count|<p>The number of blob objects stored in the storage account with the name `[{#NAME}]`.</p>|Dependent item|azure.storage.blob.count[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blobServices.BlobCount.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Blob Container Count|<p>The number of containers in the storage account with the name `[{#NAME}]`.</p>|Dependent item|azure.storage.blob.container.count[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blobServices.ContainerCount.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Blob Index Capacity|<p>The amount of storage with the name `[{#NAME}]` used by the Azure Data Lake Storage Gen2 hierarchical index.</p>|Dependent item|azure.storage.blob.index.capacity[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blobServices.IndexCapacity.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Blob Transactions|<p>The number of requests made to the storage service or a specified API operation.</p><p>This number includes successful and failed requests and also requests that produced errors.</p><p>Use `ResponseType` dimension for the number of different types of responses.</p>|Dependent item|azure.storage.blob.transactions[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blobServices.Transactions.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Blob Ingress|<p>The amount of ingress data, expressed in bytes. This number includes ingress from an external client into Azure Storage and also ingress within Azure.</p>|Dependent item|azure.storage.blob.ingress[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blobServices.Ingress.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Blob Egress|<p>The amount of egress data. This number includes egress to external client from Azure Storage and also egress within Azure.</p><p>As a result, this number does not reflect billable egress.</p>|Dependent item|azure.storage.blob.engress[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blobServices.Egress.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Blob Success Server Latency|<p>The average time used to process a successful request by Azure Storage.</p><p>This value does not include the network latency specified in `SuccessE2ELatency`.</p>|Dependent item|azure.storage.blob.success.server.latency[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blobServices.SuccessServerLatency.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Blob Success E2E Latency|<p>The average end-to-end latency of successful requests made to a storage service or the specified API operation, expressed in milliseconds.</p><p>This value includes the required processing time within Azure Storage to read the request, send the response, and receive acknowledgment of the response.</p>|Dependent item|azure.storage.blob.success.e2e.latency[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blobServices.SuccessE2ELatency.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Blob Availability|<p>The percentage of availability for the storage service or a specified API operation.</p><p>Availability is calculated by taking the `TotalBillableRequests` value and dividing it by the number of applicable requests, including those that produced unexpected errors.</p><p>All unexpected errors result in reduced availability for the storage service or the specified API operation.</p>|Dependent item|azure.storage.blob.availability[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.blobServices.Availability.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Table Capacity|<p>The amount of storage used by the table service of the storage account with the name `[{#NAME}]`, expressed in bytes.</p>|Dependent item|azure.storage.table.capacity[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tableServices.TableCapacity.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Table Count|<p>The number of tables in the storage account with the name `[{#NAME}]`.</p>|Dependent item|azure.storage.table.count[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tableServices.TableCount.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Table Entity Count|<p>The number of table entities in the storage account with the name `[{#NAME}]`.</p>|Dependent item|azure.storage.table.entity.count[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tableServices.TableEntityCount.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Table Transactions|<p>The number of requests made to the storage service or a specified API operation.</p><p>This number includes successful and failed requests and also requests that produced errors.</p><p>Use `ResponseType` dimension for the number of different types of responses.</p>|Dependent item|azure.storage.table.transactions[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tableServices.Transactions.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Table Ingress|<p>The amount of ingress data, expressed in bytes. This number includes ingress from an external client into Azure Storage and also ingress within Azure.</p>|Dependent item|azure.storage.table.ingress[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tableServices.Ingress.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Table Egress|<p>The amount of egress data. This number includes egress to external client from Azure Storage and also egress within Azure.</p><p>As a result, this number does not reflect billable egress.</p>|Dependent item|azure.storage.table.engress[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tableServices.Egress.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Table Success Server Latency|<p>The average time used to process a successful request by Azure Storage.</p><p>This value does not include the network latency specified in `SuccessE2ELatency`.</p>|Dependent item|azure.storage.table.success.server.latency[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tableServices.SuccessServerLatency.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Table Success E2E Latency|<p>The average end-to-end latency of successful requests made to a storage service or the specified API operation, expressed in milliseconds.</p><p>This value includes the required processing time within Azure Storage to read the request, send the response, and receive acknowledgment of the response.</p>|Dependent item|azure.storage.table.success.e2e.latency[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tableServices.SuccessE2ELatency.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Table Availability|<p>The percentage of availability for the storage service or a specified API operation.</p><p>Availability is calculated by taking the `TotalBillableRequests` value and dividing it by the number of applicable requests, including those that produced unexpected errors.</p><p>All unexpected errors result in reduced availability for the storage service or the specified API operation.</p>|Dependent item|azure.storage.table.availability[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tableServices.Availability.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: File Capacity|<p>The amount of file storage used by the storage account with the name `[{#NAME}]`, expressed in bytes.</p>|Dependent item|azure.storage.file.capacity[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fileServices.FileCapacity.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: File Count|<p>The number of files in the storage account with the name `[{#NAME}]`.</p>|Dependent item|azure.storage.file.count[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fileServices.FileCount.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: File Share Count|<p>The number of file shares in the storage account.</p>|Dependent item|azure.storage.file.share.count[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fileServices.FileShareCount.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: File Share Snapshot Count|<p>The number of snapshots present on the share in storage account's Files Service.</p>|Dependent item|azure.storage.file.shares.snapshot.count[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fileServices.FileShareSnapshotCount.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: File Share Snapshot Size|<p>The amount of storage used by the snapshots in storage account's File service, in bytes.</p>|Dependent item|azure.storage.file.share.snapshot.size[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fileServices.FileShareSnapshotSize.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: File Share Capacity Quota|<p>The upper limit on the amount of storage that can be used by Azure Files Service, in bytes.</p>|Dependent item|azure.storage.file.share.capacity.quota[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fileServices.FileShareCapacityQuota.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: File Transactions|<p>The number of requests made to the storage service or a specified API operation.</p><p>This number includes successful and failed requests and also requests that produced errors.</p><p>Use `ResponseType` dimension for the number of different types of responses.</p>|Dependent item|azure.storage.file.transactions[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fileServices.Transactions.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: File Ingress|<p>The amount of ingress data, expressed in bytes. This number includes ingress from an external client into Azure Storage and also ingress within Azure.</p>|Dependent item|azure.storage.file.ingress[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fileServices.Ingress.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: File Egress|<p>The amount of egress data. This number includes egress to external client from Azure Storage and also egress within Azure.</p><p>As a result, this number does not reflect billable egress.</p>|Dependent item|azure.storage.file.engress[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fileServices.Egress.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: File Success Server Latency|<p>The average time used to process a successful request by Azure Storage.</p><p>This value does not include the network latency specified in `SuccessE2ELatency`.</p>|Dependent item|azure.storage.file.success.server.latency[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fileServices.SuccessServerLatency.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: File Success E2E Latency|<p>The average end-to-end latency of successful requests made to a storage service or the specified API operation, expressed in milliseconds.</p><p>This value includes the required processing time within Azure Storage to read the request, send the response, and receive acknowledgment of the response.</p>|Dependent item|azure.storage.file.success.e2e.latency[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fileServices.file.SuccessE2ELatency.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Queue Capacity|<p>The amount of queue storage used by the storage account with the name `[{#NAME}]`, expressed in bytes.</p>|Dependent item|azure.storage.queue.capacity[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.queueServices.QueueCapacity.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Queue Count|<p>The number of queues in the storage account with the name `[{#NAME}]`.</p>|Dependent item|azure.storage.queue.count[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.queueServices.QueueCount.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Queue Message Count|<p>The number of unexpired queue messages in the storage account with the name `[{#NAME}]`.</p>|Dependent item|azure.storage.queue.message.count[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.queueServices.QueueMessageCount.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Queue Transactions|<p>The number of requests made to the storage service or a specified API operation.</p><p>This number includes successful and failed requests and also requests that produced errors.</p><p>Use `ResponseType` dimension for the number of different types of responses.</p>|Dependent item|azure.storage.queue.transactions[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.queueServices.Transactions.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Queue Ingress|<p>The amount of ingress data, expressed in bytes. This number includes ingress from an external client into Azure Storage and also ingress within Azure.</p>|Dependent item|azure.storage.queue.ingress[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.queueServices.Ingress.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Queue Egress|<p>The amount of egress data. This number includes egress to external client from Azure Storage and also egress within Azure.</p><p>As a result, this number does not reflect billable egress.</p>|Dependent item|azure.storage.queue.engress[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.queueServices.Egress.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Queue Success Server Latency|<p>The average time used to process a successful request by Azure Storage.</p><p>This value does not include the network latency specified in `SuccessE2ELatency`.</p>|Dependent item|azure.storage.queue.success.server.latency[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.queueServices.SuccessServerLatency.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Storage account [{#NAME}]: Queue Success E2E Latency|<p>The average end-to-end latency of successful requests made to a storage service or the specified API operation, expressed in milliseconds.</p><p>This value includes the required processing time within Azure Storage to read the request, send the response, and receive acknowledgment of the response.</p>|Dependent item|azure.storage.queue.success.e2e.latency[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.queueServices.queue.SuccessE2ELatency.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Trigger prototypes for Storage accounts discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Azure: Storage account [{#NAME}]: Availability is low||`(min(/Azure by HTTP/azure.storage.availability[{#NAME}],#3))<{$AZURE.STORAGE.ACC.AVAILABILITY:"{#NAME}"}`|Warning||
|Azure: Storage account [{#NAME}]: Blob Availability is low||`(min(/Azure by HTTP/azure.storage.blob.availability[{#NAME}],#3))<{$AZURE.STORAGE.ACC.BLOB.AVAILABILITY:"{#NAME}"}`|Warning||
|Azure: Storage account [{#NAME}]: Table Availability is low||`(min(/Azure by HTTP/azure.storage.table.availability[{#NAME}],#3))<{$AZURE.STORAGE.ACC.TABLE.AVAILABILITY:"{#NAME}"}`|Warning||

### LLD rule Virtual machines discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Virtual machines discovery|<p>The list of virtual machines provided by the subscription.</p>|Dependent item|azure.vm.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.resources.value`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### LLD rule Virtual machine scale set discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Virtual machine scale set discovery|<p>The list of virtual machine scale sets provided by the subscription.</p>|Dependent item|azure.scaleset.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.resources.value`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### LLD rule Azure SQL managed instance discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Azure SQL managed instance discovery|<p>The list of Azure SQL managed instances provided by the subscription.</p>|Dependent item|azure.sql_inst.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.resources.value`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### LLD rule MySQL servers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|MySQL servers discovery|<p>The list of MySQL servers provided by the subscription.</p>|Dependent item|azure.mysql.servers.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.resources.value`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### LLD rule PostgreSQL servers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PostgreSQL servers discovery|<p>The list of PostgreSQL servers provided by the subscription.</p>|Dependent item|azure.pgsql.servers.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.resources.value`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### LLD rule Microsoft SQL databases discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Microsoft SQL databases discovery|<p>The list of Microsoft SQL databases provided by the subscription.</p>|Dependent item|azure.mssql.databases.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.resources.value`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### LLD rule Cosmos DB account discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cosmos DB account discovery|<p>The list of Cosmos databases provided by the subscription.</p>|Dependent item|azure.cosmos.mongo.db.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.resources.value`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

# Azure VM Scale Set by HTTP

## Overview

This template is designed to monitor Microsoft Azure virtual machine scale sets by HTTP.
It works without any external scripts and uses the script item.

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Microsoft Azure virtual machine scale sets

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

> See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure the macros: `{$AZURE.APP.ID}`, `{$AZURE.PASSWORD}`, `{$AZURE.TENANT.ID}`, `{$AZURE.SUBSCRIPTION.ID}`, and `{$AZURE.RESOURCE.ID}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$AZURE.APP.ID}|<p>The App ID of Microsoft Azure.</p>||
|{$AZURE.PASSWORD}|<p>Microsoft Azure password.</p>||
|{$AZURE.DATA.TIMEOUT}|<p>API response timeout.</p>|`15s`|
|{$AZURE.TENANT.ID}|<p>Microsoft Azure tenant ID.</p>||
|{$AZURE.SUBSCRIPTION.ID}|<p>Microsoft Azure subscription ID.</p>||
|{$AZURE.RESOURCE.ID}|<p>Microsoft Azure virtual machine ID.</p>||
|{$AZURE.SCALESET.CPU.UTIL.CRIT}|<p>The critical threshold of CPU utilization, expressed in %.</p>|`90`|
|{$AZURE.SCALESET.VM.COUNT.CRIT}|<p>The critical amount of virtual machines in the scale set.</p>|`100`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>Gathers data of the virtual machine scale set.</p>|Script|azure.scaleset.data.get|
|Get errors|<p>A list of errors from API requests.</p>|Dependent item|azure.scaleset.data.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Availability state|<p>The availability status of the resource.</p><p>0 - Available - no events detected that affect the health of the resource.</p><p>1 - Degraded  - your resource detected a loss in performance, although it's still available for use.</p><p>2 - Unavailable - the service detected an ongoing platform or non-platform event that affects the health of the resource.</p><p>3 - Unknown - Resource Health hasn't received information about the resource for more than 10 minutes.</p>|Dependent item|azure.scaleset.availability.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health.availabilityState`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Replace: `Available -> 0`</p></li><li><p>Replace: `Degraded -> 1`</p></li><li><p>Replace: `Unavailable -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>In range: `0 -> 3`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Availability status detailed|<p>The summary description of availability status.</p>|Dependent item|azure.scaleset.availability.details<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health.summary`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Virtual machine count|<p>Current amount of virtual machines in the scale set.</p>|Dependent item|azure.scaleset.vm.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.capacity`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Available memory|<p>Amount of physical memory, in bytes, immediately available for allocation to a process or for system use in the virtual machine.</p>|Dependent item|azure.scaleset.vm.memory<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.AvailableMemoryBytes.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU credits consumed|<p>Total number of credits consumed by the virtual machine. Only available on B-series burstable VMs.</p>|Dependent item|azure.scaleset.cpu.credits.consumed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.CPUCreditsConsumed.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU credits remaining|<p>Total number of credits available to burst. Only available on B-series burstable VMs.</p>|Dependent item|azure.scaleset.cpu.credits.remaining<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.CPUCreditsRemaining.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU utilization|<p>The percentage of allocated compute units that are currently in use by the virtual machine(s).</p>|Dependent item|azure.scaleset.cpu.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.PercentageCPU.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Data disk bandwidth consumed|<p>Percentage of data disk bandwidth consumed per minute.</p>|Dependent item|azure.scaleset.data.disk.bandwidth.consumed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskBandwidthConsumedPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Data disk IOPS consumed|<p>Percentage of data disk I/Os consumed per minute.</p>|Dependent item|azure.scaleset.data.disk.iops.consumed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskIOPSConsumedPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Data disk read rate|<p>Bytes/sec read from a single disk during the monitoring period.</p>|Dependent item|azure.scaleset.data.disk.read.bps<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskReadBytessec.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Data disk IOPS read|<p>Read IOPS from a single disk during the monitoring period.</p>|Dependent item|azure.scaleset.data.disk.read.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskReadOperationsSec.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Data disk used burst BPS credits|<p>Percentage of data disk burst bandwidth credits used so far.</p>|Dependent item|azure.scaleset.data.disk.bandwidth.burst.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskUsedBurstBPSCreditsPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Data disk used burst IO credits|<p>Percentage of data disk burst I/O credits used so far.</p>|Dependent item|azure.scaleset.data.disk.iops.burst.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskUsedBurstIOCreditsPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Data disk write rate|<p>Bytes/sec written to a single disk during the monitoring period.</p>|Dependent item|azure.scaleset.data.disk.write.bps<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskWriteBytessec.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Data disk IOPS write|<p>Write IOPS from a single disk during the monitoring period.</p>|Dependent item|azure.scaleset.data.disk.write.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskWriteOperationsSec.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Data disk queue depth|<p>Data disk queue depth (or queue length).</p>|Dependent item|azure.scaleset.data.disk.queue.depth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskQueueDepth.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Data disk target bandwidth|<p>Baseline byte-per-second throughput the data disk can achieve without bursting.</p>|Dependent item|azure.scaleset.data.disk.bandwidth.target<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskTargetBandwidth.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Data disk target IOPS|<p>Baseline IOPS the data disk can achieve without bursting.</p>|Dependent item|azure.scaleset.data.disk.iops.target<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskTargetIOPS.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Data disk max burst bandwidth|<p>Maximum byte-per-second throughput the data disk can achieve with bursting.</p>|Dependent item|azure.scaleset.data.disk.bandwidth.burst.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskMaxBurstBandwidth.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Data disk max burst IOPS|<p>Maximum IOPS the data disk can achieve with bursting.</p>|Dependent item|azure.scaleset.data.disk.iops.burst.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskMaxBurstIOPS.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk read|<p>Bytes read from the disk during the monitoring period.</p>|Dependent item|azure.scaleset.disk.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DiskReadBytes.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk IOPS read|<p>Disk read IOPS.</p>|Dependent item|azure.scaleset.disk.read.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DiskReadOperationsSec.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk write|<p>Bytes written to the disk during the monitoring period.</p>|Dependent item|azure.scaleset.disk.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DiskWriteBytes.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk IOPS write|<p>Write IOPS from a single disk during the monitoring period.</p>|Dependent item|azure.scaleset.disk.write.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DiskWriteOperationsSec.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Inbound flows|<p>Inbound Flows are the number of current flows in the inbound direction (traffic going into the VMs).</p>|Dependent item|azure.scaleset.flows.inbound<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.InboundFlows.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Outbound flows|<p>Outbound Flows are the number of current flows in the outbound direction (traffic going out of the VMs).</p>|Dependent item|azure.scaleset.flows.outbound<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OutboundFlows.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Network in total|<p>The number of bytes received on all network interfaces by the virtual machine(s) (incoming traffic).</p>|Dependent item|azure.scaleset.network.in.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.NetworkInTotal.total`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network out total|<p>The number of bytes out on all network interfaces by the virtual machine(s) (outgoing traffic).</p>|Dependent item|azure.scaleset.network.out.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.NetworkOutTotal.total`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Inbound flow maximum creation rate|<p>The maximum creation rate of inbound flows (traffic going into the VM).</p>|Dependent item|azure.scaleset.flows.inbound.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.InboundFlowsMaximumCreationRate.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Outbound flow maximum creation rate|<p>The maximum creation rate of outbound flows (traffic going out of the VM).</p>|Dependent item|azure.scaleset.flows.outbound.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OutboundFlowsMaximumCreationRate.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|OS disk read rate|<p>Bytes/sec read from a single disk during the monitoring period - for an OS disk.</p>|Dependent item|azure.scaleset.os.disk.read.bps<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskReadBytessec.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|OS disk write rate|<p>Bytes/sec written to a single disk during the monitoring period - for an OS disk.</p>|Dependent item|azure.scaleset.os.disk.write.bps<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskWriteBytessec.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|OS disk IOPS read|<p>Read IOPS from a single disk during the monitoring period - for an OS disk.</p>|Dependent item|azure.scaleset.os.disk.read.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskReadOperationsSec.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|OS disk IOPS write|<p>Write IOPS from a single disk during the monitoring period - for an OS disk.</p>|Dependent item|azure.scaleset.os.disk.write.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskWriteOperationsSec.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|OS disk queue depth|<p>OS Disk queue depth (or queue length).</p>|Dependent item|azure.scaleset.os.disk.queue.depth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskQueueDepth.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|OS disk bandwidth consumed|<p>Percentage of operating system disk bandwidth consumed per minute.</p>|Dependent item|azure.scaleset.os.disk.bandwidth.consumed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskBandwidthConsumedPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|OS disk IOPS consumed|<p>Percentage of operating system disk I/Os consumed per minute.</p>|Dependent item|azure.scaleset.os.disk.iops.consumed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskIOPSConsumedPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|OS disk target bandwidth|<p>Baseline byte-per-second throughput the OS Disk can achieve without bursting.</p>|Dependent item|azure.scaleset.os.disk.bandwidth.target<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskTargetBandwidth.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|OS disk target IOPS|<p>Baseline IOPS the OS disk can achieve without bursting.</p>|Dependent item|azure.scaleset.os.disk.iops.target<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskTargetIOPS.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|OS disk max burst bandwidth|<p>Maximum byte-per-second throughput the OS Disk can achieve with bursting.</p>|Dependent item|azure.scaleset.os.disk.bandwidth.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskMaxBurstBandwidth.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|OS disk max burst IOPS|<p>Maximum IOPS the OS Disk can achieve with bursting.</p>|Dependent item|azure.scaleset.os.disk.iops.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskMaxBurstIOPS.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|OS disk used burst BPS credits|<p>Percentage of OS Disk burst bandwidth credits used so far.</p>|Dependent item|azure.scaleset.os.disk.bandwidth.burst.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskUsedBurstBPSCreditsPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|OS disk used burst IO credits|<p>Percentage of OS Disk burst I/O credits used so far.</p>|Dependent item|azure.scaleset.os.disk.iops.burst.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskUsedBurstIOCreditsPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Premium data disk cache read hit in %|<p>Percentage of premium data disk cache read hit.</p>|Dependent item|azure.scaleset.premium.data.disk.cache.read.hit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.PremiumDataDiskCacheReadHit.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Premium data disk cache read miss in %|<p>Percentage of premium data disk cache read miss.</p>|Dependent item|azure.scaleset.premium.data.disk.cache.read.miss<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.PremiumDataDiskCacheReadMiss.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Premium OS disk cache read hit in %|<p>Percentage of premium OS disk cache read hit.</p>|Dependent item|azure.scaleset.premium.os.disk.cache.read.hit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.PremiumOSDiskCacheReadHit.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Premium OS disk cache read miss in %|<p>Percentage of premium OS disk cache read miss.</p>|Dependent item|azure.scaleset.premium.os.disk.cache.read.miss<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.PremiumOSDiskCacheReadMiss.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VM cached bandwidth consumed|<p>Percentage of cached disk bandwidth consumed by the VM.</p>|Dependent item|azure.scaleset.vm.cached.bandwidth.consumed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.VMCachedBandwidthConsumedPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VM cached IOPS consumed|<p>Percentage of cached disk IOPS consumed by the VM.</p>|Dependent item|azure.scaleset.vm.cached.iops.consumed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.VMCachedIOPSConsumedPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VM uncached bandwidth consumed|<p>Percentage of uncached disk bandwidth consumed by the VM.</p>|Dependent item|azure.scaleset.vm.uncached.bandwidth.consumed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.VMUncachedBandwidthConsumedPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VM uncached IOPS consumed|<p>Percentage of uncached disk IOPS consumed by the VM.</p>|Dependent item|azure.scaleset.vm.uncached.iops.consumed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.VMUncachedIOPSConsumedPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VM availability metric|<p>Measure of availability of the virtual machines over time.</p>|Dependent item|azure.scaleset.availability<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.VmAvailabilityMetric.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Azure VM Scale: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/Azure VM Scale Set by HTTP/azure.scaleset.data.errors))>0`|Average||
|Azure VM Scale: Virtual machine scale set is unavailable|<p>The resource state is unavailable.</p>|`last(/Azure VM Scale Set by HTTP/azure.scaleset.availability.state)=2`|High||
|Azure VM Scale: Virtual machine scale set is degraded|<p>The resource is in a degraded state.</p>|`last(/Azure VM Scale Set by HTTP/azure.scaleset.availability.state)=1`|Average||
|Azure VM Scale: Virtual machine scale set is in unknown state|<p>The resource state is unknown.</p>|`last(/Azure VM Scale Set by HTTP/azure.scaleset.availability.state)=3`|Warning||
|Azure VM Scale: High amount of VMs in the scale set|<p>High amount of VMs in the scale set.</p>|`min(/Azure VM Scale Set by HTTP/azure.scaleset.vm.count,5m)>{$AZURE.SCALESET.VM.COUNT.CRIT}`|High||
|Azure VM Scale: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Azure VM Scale Set by HTTP/azure.scaleset.cpu.utilization,5m)>{$AZURE.SCALESET.CPU.UTIL.CRIT}`|High||

# Azure Virtual Machine by HTTP

## Overview

This template is designed to monitor Microsoft Azure virtual machines (VMs) by HTTP.
It works without any external scripts and uses the script item.

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Microsoft Azure virtual machines

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

> See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure the macros: `{$AZURE.APP.ID}`, `{$AZURE.PASSWORD}`, `{$AZURE.TENANT.ID}`, `{$AZURE.SUBSCRIPTION.ID}`, and `{$AZURE.RESOURCE.ID}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$AZURE.APP.ID}|<p>The App ID of Microsoft Azure.</p>||
|{$AZURE.PASSWORD}|<p>Microsoft Azure password.</p>||
|{$AZURE.DATA.TIMEOUT}|<p>API response timeout.</p>|`15s`|
|{$AZURE.TENANT.ID}|<p>Microsoft Azure tenant ID.</p>||
|{$AZURE.SUBSCRIPTION.ID}|<p>Microsoft Azure subscription ID.</p>||
|{$AZURE.RESOURCE.ID}|<p>Microsoft Azure virtual machine ID.</p>||
|{$AZURE.VM.CPU.UTIL.CRIT}|<p>The critical threshold of CPU utilization, expressed in %.</p>|`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>The result of API requests is expressed in the JSON.</p>|Script|azure.vm.data.get|
|Get errors|<p>A list of errors from API requests.</p>|Dependent item|azure.vm.data.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.errors`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Availability state|<p>The availability status of the resource.</p><p>0 - Available - no events detected that affect the health of the resource.</p><p>1 - Degraded  - your resource detected a loss in performance, although it's still available for use.</p><p>2 - Unavailable - the service detected an ongoing platform or non-platform event that affects the health of the resource.</p><p>3 - Unknown - Resource Health hasn't received information about the resource for more than 10 minutes.</p>|Dependent item|azure.vm.availability.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health.availabilityState`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Replace: `Available -> 0`</p></li><li><p>Replace: `Degraded -> 1`</p></li><li><p>Replace: `Unavailable -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>In range: `0 -> 3`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Availability status detailed|<p>The summary description of availability status.</p>|Dependent item|azure.vm.availability.details<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health.summary`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU utilization|<p>Percentage of allocated compute units that are currently in use by virtual machine.</p>|Dependent item|azure.vm.cpu.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.PercentageCPU.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Disk read|<p>Bytes read from the disk during the monitoring period.</p>|Dependent item|azure.vm.disk.read.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DiskReadBytes.total`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Disk write|<p>Bytes written to the disk during the monitoring period.</p>|Dependent item|azure.vm.disk.write.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DiskWriteBytes.total`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Disk IOPS read|<p>The count of read operations from the disk per second.</p>|Dependent item|azure.vm.disk.read.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DiskReadOperationsSec.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Disk IOPS write|<p>The count of write operations to the disk per second.</p>|Dependent item|azure.vm.disk.write.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DiskWriteOperationsSec.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU credits remaining|<p>Total number of credits available to burst. Available only on B-series burstable VMs.</p>|Dependent item|azure.vm.cpu.credits.remaining<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.CPUCreditsRemaining.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU credits consumed|<p>Total number of credits consumed by the virtual machine. Only available on B-series burstable VMs.</p>|Dependent item|azure.vm.cpu.credits.consumed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.CPUCreditsConsumed.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data disk read rate|<p>Bytes per second read from a single disk during the monitoring period.</p>|Dependent item|azure.vm.data.disk.read.bps<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskReadBytessec.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data disk write rate|<p>Bytes per second written to a single disk during the monitoring period.</p>|Dependent item|azure.vm.data.disk.write.bps<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskWriteBytessec.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data disk IOPS read|<p>Read IOPS from a single disk during the monitoring period.</p>|Dependent item|azure.vm.data.disk.read.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskReadOperationsSec.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data disk IOPS write|<p>Write IOPS from a single disk during the monitoring period.</p>|Dependent item|azure.vm.data.disk.write.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskWriteOperationsSec.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data disk queue depth|<p>The number of outstanding IO requests that are waiting to be performed on a disk.</p>|Dependent item|azure.vm.data.disk.queue.depth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskQueueDepth.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data disk bandwidth consumed|<p>Percentage of the data disk bandwidth consumed per minute.</p>|Dependent item|azure.vm.data.disk.bandwidth.consumed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskBandwidthConsumedPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data disk IOPS consumed|<p>Percentage of the data disk input/output (I/O) consumed per minute.</p>|Dependent item|azure.vm.data.disk.iops.consumed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskIOPSConsumedPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data disk target bandwidth|<p>Baseline byte-per-second throughput that the data disk can achieve without bursting.</p>|Dependent item|azure.vm.data.disk.bandwidth.target<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskTargetBandwidth.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Data disk target IOPS|<p>Baseline IOPS that the data disk can achieve without bursting.</p>|Dependent item|azure.vm.data.disk.iops.target<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskTargetIOPS.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Data disk max burst bandwidth|<p>Maximum byte-per-second throughput that the data disk can achieve with bursting.</p>|Dependent item|azure.vm.data.disk.bandwidth.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskMaxBurstBandwidth.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Data disk max burst IOPS|<p>Maximum IOPS that the data disk can achieve with bursting.</p>|Dependent item|azure.vm.data.disk.iops.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskMaxBurstIOPS.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Data disk used burst BPS credits|<p>Percentage of the data disk burst bandwidth credits used so far.</p>|Dependent item|azure.vm.data.disk.bandwidth.burst.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskUsedBurstBPSCreditsPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data disk used burst IO credits|<p>Percentage of the data disk burst I/O credits used so far.</p>|Dependent item|azure.vm.data.disk.iops.burst.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskUsedBurstIOCreditsPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|OS disk read rate|<p>Bytes/sec read from a single disk during the monitoring period - for an OS disk.</p>|Dependent item|azure.vm.os.disk.read.bps<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskReadBytessec.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|OS disk write rate|<p>Bytes/sec written to a single disk during the monitoring period - for an OS disk.</p>|Dependent item|azure.vm.os.disk.write.bps<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskWriteBytessec.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|OS disk IOPS read|<p>Read IOPS from a single disk during the monitoring period - for an OS disk.</p>|Dependent item|azure.vm.os.disk.read.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskReadOperationsSec.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|OS disk IOPS write|<p>Write IOPS from a single disk during the monitoring period - for an OS disk.</p>|Dependent item|azure.vm.os.disk.write.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskWriteOperationsSec.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|OS disk queue depth|<p>The OS disk queue depth (or queue length).</p>|Dependent item|azure.vm.os.disk.queue.depth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskQueueDepth.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|OS disk bandwidth consumed|<p>Percentage of the operating system disk bandwidth consumed per minute.</p>|Dependent item|azure.vm.os.disk.bandwidth.consumed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskBandwidthConsumedPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|OS disk IOPS consumed|<p>Percentage of the operating system disk I/Os consumed per minute.</p>|Dependent item|azure.vm.os.disk.iops.consumed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskIOPSConsumedPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|OS disk target bandwidth|<p>Baseline byte-per-second throughput that the OS disk can achieve without bursting.</p>|Dependent item|azure.vm.os.disk.bandwidth.target<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskTargetBandwidth.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|OS disk target IOPS|<p>Baseline IOPS that the OS disk can achieve without bursting.</p>|Dependent item|azure.vm.os.disk.iops.target<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskTargetIOPS.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|OS disk max burst bandwidth|<p>Maximum byte-per-second throughput that the OS disk can achieve with bursting.</p>|Dependent item|azure.vm.os.disk.bandwidth.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskMaxBurstBandwidth.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|OS disk max burst IOPS|<p>Maximum IOPS that the OS disk can achieve with bursting.</p>|Dependent item|azure.vm.os.disk.iops.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskMaxBurstIOPS.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|OS disk used burst BPS credits|<p>Percentage of the OS disk burst bandwidth credits used so far.</p>|Dependent item|azure.vm.os.disk.bandwidth.burst.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskUsedBurstBPSCreditsPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|OS disk used burst IO credits|<p>Percentage of the OS disk burst I/O credits used so far.</p>|Dependent item|azure.vm.os.disk.iops.burst.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskUsedBurstIOCreditsPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Inbound flows|<p>The number of current flows in the inbound direction (the traffic going into the VM).</p>|Dependent item|azure.vm.flows.inbound<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.InboundFlows.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Outbound flows|<p>The number of current flows in the outbound direction (the traffic going out of the VM).</p>|Dependent item|azure.vm.flows.outbound<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OutboundFlows.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Inbound flows max creation rate|<p>Maximum creation rate of the inbound flows (the traffic going into the VM).</p>|Dependent item|azure.vm.flows.inbound.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.InboundFlowsMaximumCreationRate.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Outbound flows max creation rate|<p>Maximum creation rate of the outbound flows (the traffic going out of the VM).</p>|Dependent item|azure.vm.flows.outbound.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OutboundFlowsMaximumCreationRate.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Premium data disk cache read hit in %|<p>Percentage of premium data disk cache read hit.</p>|Dependent item|azure.vm.premium.data.disk.cache.read.hit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.PremiumDataDiskCacheReadHit.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Premium data disk cache read miss in %|<p>Percentage of premium data disk cache read miss.</p>|Dependent item|azure.vm.premium.data.disk.cache.read.miss<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.PremiumDataDiskCacheReadMiss.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Premium OS disk cache read hit in %|<p>Percentage of premium OS disk cache read hit.</p>|Dependent item|azure.vm.premium.os.disk.cache.read.hit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.PremiumOSDiskCacheReadHit.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Premium OS disk cache read miss in %|<p>Percentage of premium OS disk cache read miss.</p>|Dependent item|azure.vm.premium.os.disk.cache.read.miss<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.PremiumOSDiskCacheReadMiss.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|VM cached bandwidth consumed|<p>Percentage of the cached disk bandwidth consumed by the VM.</p>|Dependent item|azure.vm.cached.bandwidth.consumed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.VMCachedBandwidthConsumedPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|VM cached IOPS consumed|<p>Percentage of the cached disk IOPS consumed by the VM.</p>|Dependent item|azure.vm.cached.iops.consumed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.VMCachedIOPSConsumedPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|VM uncached bandwidth consumed|<p>Percentage of the uncached disk bandwidth consumed by the VM.</p>|Dependent item|azure.vm.uncached.bandwidth.consumed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.VMUncachedBandwidthConsumedPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|VM uncached IOPS consumed|<p>Percentage of the uncached disk IOPS consumed by the VM.</p>|Dependent item|azure.vm.uncached.iops.consumed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.VMUncachedIOPSConsumedPercentage.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network in total|<p>The number of bytes received by the VM via all network interfaces (incoming traffic).</p>|Dependent item|azure.vm.network.in.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.NetworkInTotal.total`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Network out total|<p>The number of bytes sent by the VM via all network interfaces (outgoing traffic).</p>|Dependent item|azure.vm.network.out.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.NetworkOutTotal.total`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Available memory|<p>Amount of physical memory, in bytes, immediately available for the allocation to a process or for a system use in the virtual machine.</p>|Dependent item|azure.vm.memory.available<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.AvailableMemoryBytes.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data disk latency|<p>Average time to complete each IO during the monitoring period for Data Disk.</p>|Dependent item|azure.vm.disk.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DataDiskLatency.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|OS disk latency|<p>Average time to complete each IO during the monitoring period for OS Disk.</p>|Dependent item|azure.vm.os.disk.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.OSDiskLatency.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Temp disk latency|<p>Average time to complete each IO during the monitoring period for temp disk.</p>|Dependent item|azure.vm.temp.disk.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.TempDiskLatency.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Temp disk read rate|<p>Bytes/Sec read from a single disk during the monitoring period for temp disk.</p>|Dependent item|azure.vm.temp.disk.read.bps<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.TempDiskReadBytessec.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Temp disk write rate|<p>Bytes/Sec written to a single disk during the monitoring period for temp disk.</p>|Dependent item|azure.vm.temp.disk.write.bps<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.TempDiskWriteBytessec.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Temp disk IOPS read|<p>Read IOPS from a single disk during the monitoring period for temp disk.</p>|Dependent item|azure.vm.temp.disk.read.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.TempDiskReadOperationsSec.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Temp disk IOPS write|<p>Bytes/Sec written to a single disk during the monitoring period for temp disk.</p>|Dependent item|azure.vm.temp.disk.write.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.TempDiskWriteOperationsSec.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Temp disk queue depth|<p>Temp Disk queue depth (or queue length).</p>|Dependent item|azure.vm.temp.disk.queue.depth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.TempDiskQueueDepth.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|VM availability metric|<p>Measure of availability of virtual machine over time.</p>|Dependent item|azure.vm.availability<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.VmAvailabilityMetric.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Azure VM: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/Azure Virtual Machine by HTTP/azure.vm.data.errors))>0`|Average||
|Azure VM: Virtual machine is unavailable|<p>The resource state is unavailable.</p>|`last(/Azure Virtual Machine by HTTP/azure.vm.availability.state)=2`|High||
|Azure VM: Virtual machine is degraded|<p>The resource is in a degraded state.</p>|`last(/Azure Virtual Machine by HTTP/azure.vm.availability.state)=1`|Average||
|Azure VM: Virtual machine is in unknown state|<p>The resource state is unknown.</p>|`last(/Azure Virtual Machine by HTTP/azure.vm.availability.state)=3`|Warning||
|Azure VM: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Azure Virtual Machine by HTTP/azure.vm.cpu.utilization,5m)>{$AZURE.VM.CPU.UTIL.CRIT}`|High||

# Azure MySQL Flexible Server by HTTP

## Overview

This template is designed to monitor Microsoft Azure MySQL flexible servers by HTTP.
It works without any external scripts and uses the script item.

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Microsoft Azure MySQL flexible servers

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

> See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure the macros: `{$AZURE.APP.ID}`, `{$AZURE.PASSWORD}`, `{$AZURE.TENANT.ID}`, `{$AZURE.SUBSCRIPTION.ID}`, and `{$AZURE.RESOURCE.ID}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$AZURE.APP.ID}|<p>The App ID of Microsoft Azure.</p>||
|{$AZURE.PASSWORD}|<p>Microsoft Azure password.</p>||
|{$AZURE.DATA.TIMEOUT}|<p>API response timeout.</p>|`15s`|
|{$AZURE.TENANT.ID}|<p>Microsoft Azure tenant ID.</p>||
|{$AZURE.SUBSCRIPTION.ID}|<p>Microsoft Azure subscription ID.</p>||
|{$AZURE.RESOURCE.ID}|<p>Microsoft Azure MySQL server ID.</p>||
|{$AZURE.DB.CPU.UTIL.CRIT}|<p>The critical threshold of CPU utilization, expressed in %.</p>|`90`|
|{$AZURE.DB.STORAGE.PUSED.WARN}|<p>The warning threshold of the storage utilization, expressed in %.</p>|`80`|
|{$AZURE.DB.STORAGE.PUSED.CRIT}|<p>The critical threshold of the storage utilization, expressed in %.</p>|`90`|
|{$AZURE.DB.ABORTED.CONN.MAX.WARN}|<p>The number of failed attempts to connect to the MySQL server for a trigger expression.</p>|`25`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>The result of API requests is expressed in the JSON.</p>|Script|azure.db.mysql.data.get|
|Get errors|<p>A list of errors from API requests.</p>|Dependent item|azure.db.mysql.data.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.errors`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Availability state|<p>The availability status of the resource.</p>|Dependent item|azure.db.mysql.availability.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health.availabilityState`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Replace: `Available -> 0`</p></li><li><p>Replace: `Degraded -> 1`</p></li><li><p>Replace: `Unavailable -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>In range: `0 -> 3`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Availability status detailed|<p>The summary description of the availability status.</p>|Dependent item|azure.db.mysql.availability.details<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health.summary`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Percentage CPU|<p>The CPU percent of a host.</p>|Dependent item|azure.db.mysql.cpu.percentage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cpu_percent.maximum`</p></li></ul>|
|Memory utilization|<p>The memory percent of a host.</p>|Dependent item|azure.db.mysql.memory.percentage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.memory_percent.maximum`</p></li></ul>|
|Network out|<p>Network egress of a host, expressed in bytes.</p>|Dependent item|azure.db.mysql.network.egress<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.network_bytes_egress.total`</p></li><li><p>Custom multiplier: `0.0088`</p></li></ul>|
|Network in|<p>Network ingress of a host, expressed in bytes.</p>|Dependent item|azure.db.mysql.network.ingress<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.network_bytes_ingress.total`</p></li><li><p>Custom multiplier: `0.0088`</p></li></ul>|
|Connections active|<p>The count of active connections.</p>|Dependent item|azure.db.mysql.connections.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.active_connections.maximum`</p></li></ul>|
|Connections total|<p>The count of total connections.</p>|Dependent item|azure.db.mysql.connections.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.total_connections.total`</p></li></ul>|
|Connections aborted|<p>The count of aborted connections.</p>|Dependent item|azure.db.mysql.connections.aborted<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.aborted_connections.total`</p></li></ul>|
|Queries|<p>The count of queries.</p>|Dependent item|azure.db.mysql.queries<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.Queries.total`</p></li></ul>|
|IO consumption percent|<p>The consumption percent of I/O.</p>|Dependent item|azure.db.mysql.io.consumption.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.io_consumption_percent.maximum`</p></li></ul>|
|Storage percent|<p>The storage utilization, expressed in %.</p>|Dependent item|azure.db.mysql.storage.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.storage_percent.maximum`</p></li></ul>|
|Storage used|<p>Used storage space, expressed in bytes.</p>|Dependent item|azure.db.mysql.storage.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.storage_used.maximum`</p></li></ul>|
|Storage limit|<p>The storage limit, expressed in bytes.</p>|Dependent item|azure.db.mysql.storage.limit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.storage_limit.maximum`</p></li></ul>|
|Backup storage used|<p>Used backup storage, expressed in bytes.</p>|Dependent item|azure.db.mysql.storage.backup.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.backup_storage_used.maximum`</p></li></ul>|
|Replication lag|<p>The replication lag, expressed in seconds.</p>|Dependent item|azure.db.mysql.replication.lag<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.replication_lag.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU credits remaining|<p>The remaining CPU credits.</p>|Dependent item|azure.db.mysql.cpu.credits.remaining<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cpu_credits_remaining.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU credits consumed|<p>The consumed CPU credits.</p>|Dependent item|azure.db.mysql.cpu.credits.consumed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cpu_credits_consumed.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Azure MySQL Flexible: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/Azure MySQL Flexible Server by HTTP/azure.db.mysql.data.errors))>0`|Average||
|Azure MySQL Flexible: MySQL server is unavailable|<p>The resource state is unavailable.</p>|`last(/Azure MySQL Flexible Server by HTTP/azure.db.mysql.availability.state)=2`|High||
|Azure MySQL Flexible: MySQL server is degraded|<p>The resource is in a degraded state.</p>|`last(/Azure MySQL Flexible Server by HTTP/azure.db.mysql.availability.state)=1`|Average||
|Azure MySQL Flexible: MySQL server is in unknown state|<p>The resource state is unknown.</p>|`last(/Azure MySQL Flexible Server by HTTP/azure.db.mysql.availability.state)=3`|Warning||
|Azure MySQL Flexible: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Azure MySQL Flexible Server by HTTP/azure.db.mysql.cpu.percentage,5m)>{$AZURE.DB.CPU.UTIL.CRIT}`|High||
|Azure MySQL Flexible: Server has aborted connections|<p>The number of failed attempts to connect to the MySQL server is more than `{$AZURE.DB.ABORTED.CONN.MAX.WARN}`.</p>|`min(/Azure MySQL Flexible Server by HTTP/azure.db.mysql.connections.aborted,5m)>{$AZURE.DB.ABORTED.CONN.MAX.WARN}`|Average||
|Azure MySQL Flexible: Storage space is critically low|<p>Critical utilization of the storage space.</p>|`last(/Azure MySQL Flexible Server by HTTP/azure.db.mysql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.CRIT}`|Average||
|Azure MySQL Flexible: Storage space is low|<p>High utilization of the storage space.</p>|`last(/Azure MySQL Flexible Server by HTTP/azure.db.mysql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.WARN}`|Warning||

# Azure MySQL Single Server by HTTP

## Overview

This template is designed to monitor Microsoft Azure MySQL single servers by HTTP.
It works without any external scripts and uses the script item.

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Microsoft Azure MySQL single servers

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

> See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure the macros: `{$AZURE.APP.ID}`, `{$AZURE.PASSWORD}`, `{$AZURE.TENANT.ID}`, `{$AZURE.SUBSCRIPTION.ID}`, and `{$AZURE.RESOURCE.ID}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$AZURE.APP.ID}|<p>The App ID of Microsoft Azure.</p>||
|{$AZURE.PASSWORD}|<p>Microsoft Azure password.</p>||
|{$AZURE.DATA.TIMEOUT}|<p>API response timeout.</p>|`15s`|
|{$AZURE.TENANT.ID}|<p>Microsoft Azure tenant ID.</p>||
|{$AZURE.SUBSCRIPTION.ID}|<p>Microsoft Azure subscription ID.</p>||
|{$AZURE.RESOURCE.ID}|<p>Microsoft Azure MySQL server ID.</p>||
|{$AZURE.DB.CPU.UTIL.CRIT}|<p>The critical threshold of CPU utilization, expressed in %.</p>|`90`|
|{$AZURE.DB.MEMORY.UTIL.CRIT}|<p>The critical threshold of memory utilization, expressed in %.</p>|`90`|
|{$AZURE.DB.STORAGE.PUSED.WARN}|<p>The warning threshold of storage utilization, expressed in %.</p>|`80`|
|{$AZURE.DB.STORAGE.PUSED.CRIT}|<p>The critical threshold of storage utilization, expressed in %.</p>|`90`|
|{$AZURE.DB.FAILED.CONN.MAX.WARN}|<p>The number of failed attempts to connect to the MySQL server for trigger expression.</p>|`25`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>The result of API requests is expressed in the JSON.</p>|Script|azure.db.mysql.data.get|
|Get errors|<p>A list of errors from API requests.</p>|Dependent item|azure.db.mysql.data.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.errors`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Availability state|<p>The availability status of the resource.</p>|Dependent item|azure.db.mysql.availability.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health.availabilityState`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Replace: `Available -> 0`</p></li><li><p>Replace: `Degraded -> 1`</p></li><li><p>Replace: `Unavailable -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>In range: `0 -> 3`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Availability status detailed|<p>The summary description of the availability status.</p>|Dependent item|azure.db.mysql.availability.details<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health.summary`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Percentage CPU|<p>The CPU percent of a host.</p>|Dependent item|azure.db.mysql.cpu.percentage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cpu_percent.average`</p></li></ul>|
|Memory utilization|<p>The memory percent of a host.</p>|Dependent item|azure.db.mysql.memory.percentage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.memory_percent.average`</p></li></ul>|
|Network out|<p>The network outbound traffic across the active connections.</p>|Dependent item|azure.db.mysql.network.egress<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.network_bytes_egress.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0088`</p></li></ul>|
|Network in|<p>The network inbound traffic across the active connections.</p>|Dependent item|azure.db.mysql.network.ingress<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.network_bytes_ingress.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.0088`</p></li></ul>|
|Connections active|<p>The count of active connections.</p>|Dependent item|azure.db.mysql.connections.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.active_connections.average`</p></li></ul>|
|Connections failed|<p>The count of failed connections.</p>|Dependent item|azure.db.mysql.connections.failed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.connections_failed.total`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|IO consumption percent|<p>The consumption percent of I/O.</p>|Dependent item|azure.db.mysql.io.consumption.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.io_consumption_percent.average`</p></li></ul>|
|Storage percent|<p>The storage utilization, expressed in %.</p>|Dependent item|azure.db.mysql.storage.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.storage_percent.average`</p></li></ul>|
|Storage used|<p>Used storage space, expressed in bytes.</p>|Dependent item|azure.db.mysql.storage.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.storage_used.average`</p></li></ul>|
|Storage limit|<p>The storage limit, expressed in bytes.</p>|Dependent item|azure.db.mysql.storage.limit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.storage_limit.maximum`</p></li></ul>|
|Backup storage used|<p>Used backup storage, expressed in bytes.</p>|Dependent item|azure.db.mysql.storage.backup.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.backup_storage_used.average`</p></li></ul>|
|Replication lag|<p>The replication lag, expressed in seconds.</p>|Dependent item|azure.db.mysql.replication.lag<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.seconds_behind_master.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Server log storage percent|<p>The storage utilization by server log, expressed in %.</p>|Dependent item|azure.db.mysql.storage.server.log.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.serverlog_storage_percent.average`</p></li></ul>|
|Server log storage used|<p>The storage space used by server log, expressed in bytes.</p>|Dependent item|azure.db.mysql.storage.server.log.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.serverlog_storage_usage.average`</p></li></ul>|
|Server log storage limit|<p>The storage limit of server log, expressed in bytes.</p>|Dependent item|azure.db.mysql.storage.server.log.limit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.serverlog_storage_limit.maximum`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Azure MySQL Single: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/Azure MySQL Single Server by HTTP/azure.db.mysql.data.errors))>0`|Average||
|Azure MySQL Single: MySQL server is unavailable|<p>The resource state is unavailable.</p>|`last(/Azure MySQL Single Server by HTTP/azure.db.mysql.availability.state)=2`|High||
|Azure MySQL Single: MySQL server is degraded|<p>The resource is in a degraded state.</p>|`last(/Azure MySQL Single Server by HTTP/azure.db.mysql.availability.state)=1`|Average||
|Azure MySQL Single: MySQL server is in unknown state|<p>The resource state is unknown.</p>|`last(/Azure MySQL Single Server by HTTP/azure.db.mysql.availability.state)=3`|Warning||
|Azure MySQL Single: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Azure MySQL Single Server by HTTP/azure.db.mysql.cpu.percentage,5m)>{$AZURE.DB.CPU.UTIL.CRIT}`|High||
|Azure MySQL Single: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Azure MySQL Single Server by HTTP/azure.db.mysql.memory.percentage,5m)>{$AZURE.DB.MEMORY.UTIL.CRIT}`|Average||
|Azure MySQL Single: Server has failed connections|<p>The number of failed attempts to connect to the MySQL server is more than `{$AZURE.DB.FAILED.CONN.MAX.WARN}`.</p>|`min(/Azure MySQL Single Server by HTTP/azure.db.mysql.connections.failed,5m)>{$AZURE.DB.FAILED.CONN.MAX.WARN}`|Average||
|Azure MySQL Single: Storage space is critically low|<p>Critical utilization of the storage space.</p>|`last(/Azure MySQL Single Server by HTTP/azure.db.mysql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.CRIT}`|Average||
|Azure MySQL Single: Storage space is low|<p>High utilization of the storage space.</p>|`last(/Azure MySQL Single Server by HTTP/azure.db.mysql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.WARN}`|Warning||

# Azure PostgreSQL Flexible Server by HTTP

## Overview

This template is designed to monitor Microsoft Azure PostgreSQL flexible servers by HTTP.
It works without any external scripts and uses the script item.

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Microsoft Azure PostgreSQL flexible servers

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

> See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure the macros: `{$AZURE.APP.ID}`, `{$AZURE.PASSWORD}`, `{$AZURE.TENANT.ID}`, `{$AZURE.SUBSCRIPTION.ID}`, and `{$AZURE.RESOURCE.ID}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$AZURE.APP.ID}|<p>The App ID of Microsoft Azure.</p>||
|{$AZURE.PASSWORD}|<p>Microsoft Azure password.</p>||
|{$AZURE.DATA.TIMEOUT}|<p>API response timeout.</p>|`15s`|
|{$AZURE.TENANT.ID}|<p>Microsoft Azure tenant ID.</p>||
|{$AZURE.SUBSCRIPTION.ID}|<p>Microsoft Azure subscription ID.</p>||
|{$AZURE.RESOURCE.ID}|<p>Microsoft Azure PostgreSQL server ID.</p>||
|{$AZURE.DB.CPU.UTIL.CRIT}|<p>The critical threshold of CPU utilization, expressed in %.</p>|`90`|
|{$AZURE.DB.MEMORY.UTIL.CRIT}|<p>The critical threshold of memory utilization, expressed in %.</p>|`90`|
|{$AZURE.DB.STORAGE.PUSED.WARN}|<p>The warning threshold of storage utilization, expressed in %.</p>|`80`|
|{$AZURE.DB.STORAGE.PUSED.CRIT}|<p>The critical threshold of storage utilization, expressed in %.</p>|`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>The result of API requests is expressed in the JSON.</p>|Script|azure.db.pgsql.data.get|
|Get errors|<p>A list of errors from API requests.</p>|Dependent item|azure.db.pgsql.data.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.errors`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Availability state|<p>The availability status of the resource.</p>|Dependent item|azure.db.pgsql.availability.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health.availabilityState`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Replace: `Available -> 0`</p></li><li><p>Replace: `Degraded -> 1`</p></li><li><p>Replace: `Unavailable -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>In range: `0 -> 3`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Availability status detailed|<p>The summary description of the availability status.</p>|Dependent item|azure.db.pgsql.availability.details<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health.summary`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Percentage CPU|<p>The CPU percent of a host.</p>|Dependent item|azure.db.pgsql.cpu.percentage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cpu_percent.average`</p></li></ul>|
|Memory utilization|<p>The memory percent of a host.</p>|Dependent item|azure.db.pgsql.memory.percentage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.memory_percent.average`</p></li></ul>|
|Network out|<p>The network outbound traffic across the active connections.</p>|Dependent item|azure.db.pgsql.network.egress<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.network_bytes_egress.total`</p></li><li><p>Custom multiplier: `0.1333`</p></li></ul>|
|Network in|<p>The network inbound traffic across the active connections.</p>|Dependent item|azure.db.pgsql.network.ingress<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.network_bytes_ingress.total`</p></li><li><p>Custom multiplier: `0.1333`</p></li></ul>|
|Connections active|<p>The count of active connections.</p>|Dependent item|azure.db.pgsql.connections.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.active_connections.average`</p></li></ul>|
|Connections succeeded|<p>The count of succeeded connections.</p>|Dependent item|azure.db.pgsql.connections.succeeded<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.connections_succeeded.total`</p></li></ul>|
|Connections failed|<p>The count of failed connections.</p>|Dependent item|azure.db.pgsql.connections.failed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.connections_failed.total`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Storage percent|<p>The storage utilization, expressed in %.</p>|Dependent item|azure.db.pgsql.storage.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.storage_percent.average`</p></li></ul>|
|Storage used|<p>Used storage space, expressed in bytes.</p>|Dependent item|azure.db.pgsql.storage.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.storage_used.average`</p></li></ul>|
|Storage free|<p>Free storage space, expressed in bytes.</p>|Dependent item|azure.db.pgsql.storage.free<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.storage_free.average`</p></li></ul>|
|Backup storage used|<p>Used backup storage, expressed in bytes.</p>|Dependent item|azure.db.pgsql.storage.backup.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.backup_storage_used.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU credits remaining|<p>The total number of credits available to burst.</p>|Dependent item|azure.db.pgsql.cpu.credits.remaining<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cpu_credits_remaining.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU credits consumed|<p>The total number of credits consumed by the database server.</p>|Dependent item|azure.db.pgsql.cpu.credits.consumed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cpu_credits_consumed.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data disk queue depth|<p>The number of outstanding I/O operations to the data disk.</p>|Dependent item|azure.db.pgsql.disk.queue.depth<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.disk_queue_depth.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data disk IOPS|<p>I/O operations per second.</p>|Dependent item|azure.db.pgsql.iops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.iops.average`</p></li></ul>|
|Data disk read IOPS|<p>The number of the data disk I/O read operations per second.</p>|Dependent item|azure.db.pgsql.iops.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.read_iops.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data disk write IOPS|<p>The number of the data disk I/O write operations per second.</p>|Dependent item|azure.db.pgsql.iops.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.write_iops.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data disk read Bps|<p>Bytes read per second from the data disk during the monitoring period.</p>|Dependent item|azure.db.pgsql.disk.bps.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.read_throughput.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data disk write Bps|<p>Bytes written per second to the data disk during the monitoring period.</p>|Dependent item|azure.db.pgsql.disk.bps.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.write_throughput.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Transaction log storage used|<p>The storage space used by transaction log, expressed in bytes.</p>|Dependent item|azure.db.pgsql.storage.txlogs.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.txlogs_storage_used.average`</p></li></ul>|
|Maximum used transaction IDs|<p>The maximum number of used transaction IDs.</p>|Dependent item|azure.db.pgsql.txid.used.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.maximum_used_transactionIDs.average`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Azure PostgreSQL Flexible: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/Azure PostgreSQL Flexible Server by HTTP/azure.db.pgsql.data.errors))>0`|Average||
|Azure PostgreSQL Flexible: PostgreSQL server is unavailable|<p>The resource state is unavailable.</p>|`last(/Azure PostgreSQL Flexible Server by HTTP/azure.db.pgsql.availability.state)=2`|High||
|Azure PostgreSQL Flexible: PostgreSQL server is degraded|<p>The resource is in a degraded state.</p>|`last(/Azure PostgreSQL Flexible Server by HTTP/azure.db.pgsql.availability.state)=1`|Average||
|Azure PostgreSQL Flexible: PostgreSQL server is in unknown state|<p>The resource state is unknown.</p>|`last(/Azure PostgreSQL Flexible Server by HTTP/azure.db.pgsql.availability.state)=3`|Warning||
|Azure PostgreSQL Flexible: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Azure PostgreSQL Flexible Server by HTTP/azure.db.pgsql.cpu.percentage,5m)>{$AZURE.DB.CPU.UTIL.CRIT}`|High||
|Azure PostgreSQL Flexible: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Azure PostgreSQL Flexible Server by HTTP/azure.db.pgsql.memory.percentage,5m)>{$AZURE.DB.MEMORY.UTIL.CRIT}`|Average||
|Azure PostgreSQL Flexible: Storage space is critically low|<p>Critical utilization of the storage space.</p>|`last(/Azure PostgreSQL Flexible Server by HTTP/azure.db.pgsql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.CRIT}`|Average||
|Azure PostgreSQL Flexible: Storage space is low|<p>High utilization of the storage space.</p>|`last(/Azure PostgreSQL Flexible Server by HTTP/azure.db.pgsql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.WARN}`|Warning||

# Azure PostgreSQL Single Server by HTTP

## Overview

This template is designed to monitor Microsoft Azure PostgreSQL servers by HTTP.
It works without any external scripts and uses the script item.

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Microsoft Azure PostgreSQL servers

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

> See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure the macros: `{$AZURE.APP.ID}`, `{$AZURE.PASSWORD}`, `{$AZURE.TENANT.ID}`, `{$AZURE.SUBSCRIPTION.ID}`, and `{$AZURE.RESOURCE.ID}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$AZURE.APP.ID}|<p>The App ID of Microsoft Azure.</p>||
|{$AZURE.PASSWORD}|<p>Microsoft Azure password.</p>||
|{$AZURE.DATA.TIMEOUT}|<p>API response timeout.</p>|`15s`|
|{$AZURE.TENANT.ID}|<p>Microsoft Azure tenant ID.</p>||
|{$AZURE.SUBSCRIPTION.ID}|<p>Microsoft Azure subscription ID.</p>||
|{$AZURE.RESOURCE.ID}|<p>Microsoft Azure PostgreSQL server ID.</p>||
|{$AZURE.DB.CPU.UTIL.CRIT}|<p>The critical threshold of CPU utilization, expressed in %.</p>|`90`|
|{$AZURE.DB.MEMORY.UTIL.CRIT}|<p>The critical threshold of memory utilization, expressed in %.</p>|`90`|
|{$AZURE.DB.STORAGE.PUSED.WARN}|<p>The warning threshold of storage utilization, expressed in %.</p>|`80`|
|{$AZURE.DB.STORAGE.PUSED.CRIT}|<p>The critical threshold of storage utilization, expressed in %.</p>|`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>The result of API requests is expressed in the JSON.</p>|Script|azure.db.pgsql.data.get|
|Get errors|<p>A list of errors from API requests.</p>|Dependent item|azure.db.pgsql.data.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.errors`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Availability state|<p>The availability status of the resource.</p>|Dependent item|azure.db.pgsql.availability.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health.availabilityState`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Replace: `Available -> 0`</p></li><li><p>Replace: `Degraded -> 1`</p></li><li><p>Replace: `Unavailable -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>In range: `0 -> 3`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Availability status detailed|<p>The summary description of the availability status.</p>|Dependent item|azure.db.pgsql.availability.details<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health.summary`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Percentage CPU|<p>The CPU percent of a host.</p>|Dependent item|azure.db.pgsql.cpu.percentage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cpu_percent.average`</p></li></ul>|
|Memory utilization|<p>The memory percent of a host.</p>|Dependent item|azure.db.pgsql.memory.percentage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.memory_percent.average`</p></li></ul>|
|Network out|<p>The network outbound traffic across the active connections.</p>|Dependent item|azure.db.pgsql.network.egress<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.network_bytes_egress.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.1333`</p></li></ul>|
|Network in|<p>The network inbound traffic across the active connections.</p>|Dependent item|azure.db.pgsql.network.ingress<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.network_bytes_ingress.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.1333`</p></li></ul>|
|Connections active|<p>The count of active connections.</p>|Dependent item|azure.db.pgsql.connections.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.active_connections.average`</p></li></ul>|
|Connections failed|<p>The count of failed connections.</p>|Dependent item|azure.db.pgsql.connections.failed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.connections_failed.total`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|IO consumption percent|<p>The consumption percent of I/O.</p>|Dependent item|azure.db.pgsql.io.consumption.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.io_consumption_percent.average`</p></li></ul>|
|Storage percent|<p>The storage utilization, expressed in %.</p>|Dependent item|azure.db.pgsql.storage.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.storage_percent.average`</p></li></ul>|
|Storage used|<p>Used storage space, expressed in bytes.</p>|Dependent item|azure.db.pgsql.storage.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.storage_used.average`</p></li></ul>|
|Storage limit|<p>The storage limit, expressed in bytes.</p>|Dependent item|azure.db.pgsql.storage.limit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.storage_limit.maximum`</p></li></ul>|
|Backup storage used|<p>Used backup storage, expressed in bytes.</p>|Dependent item|azure.db.pgsql.storage.backup.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.backup_storage_used.average`</p></li></ul>|
|Replication lag|<p>The replication lag, expressed in seconds.</p>|Dependent item|azure.db.pgsql.replica.log.delay<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.pg_replica_log_delay_in_seconds.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Max lag across replicas in bytes|<p>Lag for the most lagging replica, expressed in bytes.</p>|Dependent item|azure.db.pgsql.replica.log.delay.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.pg_replica_log_delay_in_bytes.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Server log storage percent|<p>The storage utilization by server log, expressed in %.</p>|Dependent item|azure.db.pgsql.storage.server.log.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.serverlog_storage_percent.average`</p></li></ul>|
|Server log storage used|<p>The storage space used by server log, expressed in bytes.</p>|Dependent item|azure.db.pgsql.storage.server.log.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.serverlog_storage_usage.average`</p></li></ul>|
|Server log storage limit|<p>The storage limit of server log, expressed in bytes.</p>|Dependent item|azure.db.pgsql.storage.server.log.limit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.serverlog_storage_limit.maximum`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Azure PostgreSQL Single: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/Azure PostgreSQL Single Server by HTTP/azure.db.pgsql.data.errors))>0`|Average||
|Azure PostgreSQL Single: PostgreSQL server is unavailable|<p>The resource state is unavailable.</p>|`last(/Azure PostgreSQL Single Server by HTTP/azure.db.pgsql.availability.state)=2`|High||
|Azure PostgreSQL Single: PostgreSQL server is degraded|<p>The resource is in a degraded state.</p>|`last(/Azure PostgreSQL Single Server by HTTP/azure.db.pgsql.availability.state)=1`|Average||
|Azure PostgreSQL Single: PostgreSQL server is in unknown state|<p>The resource state is unknown.</p>|`last(/Azure PostgreSQL Single Server by HTTP/azure.db.pgsql.availability.state)=3`|Warning||
|Azure PostgreSQL Single: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Azure PostgreSQL Single Server by HTTP/azure.db.pgsql.cpu.percentage,5m)>{$AZURE.DB.CPU.UTIL.CRIT}`|High||
|Azure PostgreSQL Single: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Azure PostgreSQL Single Server by HTTP/azure.db.pgsql.memory.percentage,5m)>{$AZURE.DB.MEMORY.UTIL.CRIT}`|Average||
|Azure PostgreSQL Single: Storage space is critically low|<p>Critical utilization of the storage space.</p>|`last(/Azure PostgreSQL Single Server by HTTP/azure.db.pgsql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.CRIT}`|Average||
|Azure PostgreSQL Single: Storage space is low|<p>High utilization of the storage space.</p>|`last(/Azure PostgreSQL Single Server by HTTP/azure.db.pgsql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.WARN}`|Warning||

# Azure Microsoft SQL Serverless Database by HTTP

## Overview

This template is designed to monitor Microsoft SQL serverless databases by HTTP.
It works without any external scripts and uses the script item.

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Microsoft Azure SQL serverless databases

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

> See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure the macros: `{$AZURE.APP.ID}`, `{$AZURE.PASSWORD}`, `{$AZURE.TENANT.ID}`, `{$AZURE.SUBSCRIPTION.ID}`, and `{$AZURE.RESOURCE.ID}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$AZURE.APP.ID}|<p>The App ID of Microsoft Azure.</p>||
|{$AZURE.PASSWORD}|<p>Microsoft Azure password.</p>||
|{$AZURE.DATA.TIMEOUT}|<p>API response timeout.</p>|`15s`|
|{$AZURE.TENANT.ID}|<p>Microsoft Azure tenant ID.</p>||
|{$AZURE.SUBSCRIPTION.ID}|<p>Microsoft Azure subscription ID.</p>||
|{$AZURE.RESOURCE.ID}|<p>Microsoft Azure Microsoft SQL database ID.</p>||
|{$AZURE.DB.CPU.UTIL.CRIT}|<p>The critical threshold of CPU utilization, expressed in %.</p>|`90`|
|{$AZURE.DB.MEMORY.UTIL.CRIT}|<p>The critical threshold of memory utilization, expressed in %.</p>|`90`|
|{$AZURE.DB.STORAGE.PUSED.WARN}|<p>The warning threshold of storage utilization, expressed in %.</p>|`80`|
|{$AZURE.DB.STORAGE.PUSED.CRIT}|<p>The critical threshold of storage utilization, expressed in %.</p>|`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>The result of API requests is expressed in the JSON.</p>|Script|azure.db.mssql.data.get|
|Get errors|<p>A list of errors from API requests.</p>|Dependent item|azure.db.mssql.data.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.errors`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Availability state|<p>The availability status of the resource.</p>|Dependent item|azure.db.mssql.availability.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health.availabilityState`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Replace: `Available -> 0`</p></li><li><p>Replace: `Degraded -> 1`</p></li><li><p>Replace: `Unavailable -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>In range: `0 -> 3`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Availability status detailed|<p>The summary description of the availability status.</p>|Dependent item|azure.db.mssql.availability.details<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health.summary`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Percentage CPU|<p>The CPU percent of a host.</p>|Dependent item|azure.db.mssql.cpu.percentage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cpu_percent.average`</p></li></ul>|
|Data IO percentage|<p>The physical data read percentage.</p>|Dependent item|azure.db.mssql.data.read.percentage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.physical_data_read_percent.average`</p></li></ul>|
|Log IO percentage|<p>The percentage of I/O log. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.log.write.percentage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.log_write_percent.average`</p></li></ul>|
|Data space used|<p>Data space used. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.storage.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.storage.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Connections successful|<p>The count of successful connections.</p>|Dependent item|azure.db.mssql.connections.successful<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.connection_successful.total`</p></li></ul>|
|Connections failed: System errors|<p>The count of failed connections with system errors.</p>|Dependent item|azure.db.mssql.connections.failed.system<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.connection_failed.total`</p></li></ul>|
|Connections blocked by firewall|<p>The count of connections blocked by firewall.</p>|Dependent item|azure.db.mssql.firewall.blocked<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.blocked_by_firewall.total`</p></li></ul>|
|Deadlocks|<p>The count of deadlocks. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.deadlocks<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.deadlock.total`</p></li></ul>|
|Data space used percent|<p>The percentage of used data space. Not applicable to the data warehouses or Hyperscale databases.</p>|Dependent item|azure.db.mssql.storage.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.storage_percent.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|In-Memory OLTP storage percent|<p>In-Memory OLTP storage percent. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.storage.xtp.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.xtp_storage_percent.average`</p></li></ul>|
|Workers percentage|<p>The percentage of workers. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.workers.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.workers_percent.average`</p></li></ul>|
|Sessions percentage|<p>The percentage of sessions. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.sessions.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.sessions_percent.average`</p></li></ul>|
|CPU limit|<p>The CPU limit. Applies to the vCore-based databases.</p>|Dependent item|azure.db.mssql.cpu.limit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cpu_limit.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU used|<p>The CPU used. Applies to the vCore-based databases.</p>|Dependent item|azure.db.mssql.cpu.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cpu_used.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|SQL Server process core percent|<p>The CPU usage as a percentage of the SQL DB process. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.server.cpu.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.sqlserver_process_core_percent.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|SQL Server process memory percent|<p>Memory usage as a percentage of the SQL DB process. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.server.memory.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.sqlserver_process_memory_percent.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Tempdb data file size|<p>Space used in `tempdb` data files, expressed in bytes. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.tempdb.data.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.tempdb_data_size.maximum`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Tempdb log file size|<p>Space used in `tempdb` transaction log files, expressed in bytes. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.tempdb.log.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.tempdb_log_size.maximum`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Tempdb log used percent|<p>The percentage of space used in `tempdb` transaction log files. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.tempdb.log.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.tempdb_log_used_percent.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|App CPU billed|<p>App CPU billed. Applies to serverless databases.</p>|Dependent item|azure.db.mssql.app.cpu.billed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.app_cpu_billed.total`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|App CPU percentage|<p>App CPU percentage. Applies to serverless databases.</p>|Dependent item|azure.db.mssql.app.cpu.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.app_cpu_percent.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|App memory percentage|<p>App memory percentage. Applies to serverless databases.</p>|Dependent item|azure.db.mssql.app.memory.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.app_memory_percent.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data space allocated|<p>The allocated data storage. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.storage.allocated<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.allocated_data_storage.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Azure MSSQL Serverless: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/Azure Microsoft SQL Serverless Database by HTTP/azure.db.mssql.data.errors))>0`|Average||
|Azure MSSQL Serverless: Microsoft SQL database is unavailable|<p>The resource state is unavailable.</p>|`last(/Azure Microsoft SQL Serverless Database by HTTP/azure.db.mssql.availability.state)=2`|High||
|Azure MSSQL Serverless: Microsoft SQL database is degraded|<p>The resource is in a degraded state.</p>|`last(/Azure Microsoft SQL Serverless Database by HTTP/azure.db.mssql.availability.state)=1`|Average||
|Azure MSSQL Serverless: Microsoft SQL database is in unknown state|<p>The resource state is unknown.</p>|`last(/Azure Microsoft SQL Serverless Database by HTTP/azure.db.mssql.availability.state)=3`|Warning||
|Azure MSSQL Serverless: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Azure Microsoft SQL Serverless Database by HTTP/azure.db.mssql.cpu.percentage,5m)>{$AZURE.DB.CPU.UTIL.CRIT}`|High||
|Azure MSSQL Serverless: Storage space is critically low|<p>Critical utilization of the storage space.</p>|`last(/Azure Microsoft SQL Serverless Database by HTTP/azure.db.mssql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.CRIT}`|Average||
|Azure MSSQL Serverless: Storage space is low|<p>High utilization of the storage space.</p>|`last(/Azure Microsoft SQL Serverless Database by HTTP/azure.db.mssql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.WARN}`|Warning||

# Azure Microsoft SQL DTU Database by HTTP

## Overview

This template is designed to monitor Microsoft SQL DTU-based databases via HTTP.
It works without any external scripts and uses the script item.

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Microsoft Azure SQL DTU-based databases

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

> See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure the macros: `{$AZURE.APP.ID}`, `{$AZURE.PASSWORD}`, `{$AZURE.TENANT.ID}`, `{$AZURE.SUBSCRIPTION.ID}`, and `{$AZURE.RESOURCE.ID}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$AZURE.APP.ID}|<p>The App ID of Microsoft Azure.</p>||
|{$AZURE.PASSWORD}|<p>Microsoft Azure password.</p>||
|{$AZURE.DATA.TIMEOUT}|<p>API response timeout.</p>|`15s`|
|{$AZURE.TENANT.ID}|<p>Microsoft Azure tenant ID.</p>||
|{$AZURE.SUBSCRIPTION.ID}|<p>Microsoft Azure subscription ID.</p>||
|{$AZURE.RESOURCE.ID}|<p>Microsoft Azure SQL DTU-based database ID.</p>||
|{$AZURE.DB.DTU.UTIL.CRIT}|<p>The critical threshold of DTU utilization, expressed in %.</p>|`90`|
|{$AZURE.DB.CPU.UTIL.CRIT}|<p>The critical threshold of CPU utilization, expressed in %.</p>|`90`|
|{$AZURE.DB.MEMORY.UTIL.CRIT}|<p>The critical threshold of memory utilization, expressed in %.</p>|`90`|
|{$AZURE.DB.STORAGE.PUSED.WARN}|<p>The warning threshold of storage utilization, expressed in %.</p>|`80`|
|{$AZURE.DB.STORAGE.PUSED.CRIT}|<p>The critical threshold of storage utilization, expressed in %.</p>|`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>The result of API requests is expressed in JSON format.</p>|Script|azure.db.mssql.data.get|
|Get errors|<p>A list of errors from API requests.</p>|Dependent item|azure.db.mssql.data.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.errors`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Availability state|<p>The availability status of the resource.</p>|Dependent item|azure.db.mssql.availability.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health.availabilityState`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Replace: `Available -> 0`</p></li><li><p>Replace: `Degraded -> 1`</p></li><li><p>Replace: `Unavailable -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>In range: `0 -> 3`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Availability status detailed|<p>A detailed summary of the availability status.</p>|Dependent item|azure.db.mssql.availability.details<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health.summary`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU percentage|<p>The average percentage of CPU usage on a host.</p>|Dependent item|azure.db.mssql.cpu.percentage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cpu_percent.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DTU percentage|<p>The average percentage of DTU consumption for a DTU-based database.</p>|Dependent item|azure.db.mssql.dtu.percentage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.dtu_consumption_percent.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data IO percentage|<p>The average percentage of physical data read.</p>|Dependent item|azure.db.mssql.data.read.percentage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.physical_data_read_percent.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Log IO percentage|<p>The percentage of I/O used for log writes. Not applicable to data warehouses.</p>|Dependent item|azure.db.mssql.log.write.percentage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.log_write_percent.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data space used|<p>Data space used. Not applicable to data warehouses.</p>|Dependent item|azure.db.mssql.storage.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.storage.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Connections successful|<p>The number of successful connections.</p>|Dependent item|azure.db.mssql.connections.successful<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.connection_successful.total`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Connections failed: System errors|<p>The number of failed connections with system errors.</p>|Dependent item|azure.db.mssql.connections.failed.system<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.connection_failed.total`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Connections blocked by firewall|<p>The number of connections blocked by the firewall.</p>|Dependent item|azure.db.mssql.firewall.blocked<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.blocked_by_firewall.total`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Deadlocks|<p>The number of deadlocks. Not applicable to data warehouses.</p>|Dependent item|azure.db.mssql.deadlocks<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.deadlock.total`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data space used percent|<p>Data space used in percent. Not applicable to data warehouses or Hyperscale databases.</p>|Dependent item|azure.db.mssql.storage.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.storage_percent.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|In-Memory OLTP storage percent|<p>In-Memory OLTP storage percent. Not applicable to data warehouses.</p>|Dependent item|azure.db.mssql.storage.xtp.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.xtp_storage_percent.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Workers percentage|<p>The percentage of workers. Not applicable to data warehouses.</p>|Dependent item|azure.db.mssql.workers.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.workers_percent.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Sessions percentage|<p>The percentage of sessions. Not applicable to data warehouses.</p>|Dependent item|azure.db.mssql.sessions.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.sessions_percent.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Sessions count|<p>The number of active sessions. Not applicable to Synapse DW Analytics.</p>|Dependent item|azure.db.mssql.sessions.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.sessions_count.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DTU limit|<p>The DTU limit. Applicable to DTU-based databases.</p>|Dependent item|azure.db.mssql.dtu.limit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.dtu_limit.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|DTU used|<p>The DTU used. Applicable to DTU-based databases.</p>|Dependent item|azure.db.mssql.dtu.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.dtu_used.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|SQL instance CPU percent|<p>CPU usage from all user and system workloads. Not applicable to data warehouses.</p>|Dependent item|azure.db.mssql.server.cpu.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.sql_instance_cpu_percent.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|SQL instance memory percent|<p>The percentage of memory used by the database engine instance. Not applicable to data warehouses.</p>|Dependent item|azure.db.mssql.server.memory.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.sql_instance_cpu_percent.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Tempdb data file size|<p>The space used in `tempdb` data files, expressed in bytes. Not applicable to data warehouses.</p>|Dependent item|azure.db.mssql.tempdb.data.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.tempdb_data_size.maximum`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Tempdb log file size|<p>The space used in the `tempdb` transaction log file, expressed in bytes. Not applicable to data warehouses.</p>|Dependent item|azure.db.mssql.tempdb.log.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.tempdb_log_size.maximum`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Tempdb log used percent|<p>The percentage of space used in the `tempdb` transaction log file. Not applicable to data warehouses.</p>|Dependent item|azure.db.mssql.tempdb.log.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.tempdb_log_used_percent.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data space allocated|<p>The allocated data storage. Not applicable to data warehouses.</p>|Dependent item|azure.db.mssql.storage.allocated<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.allocated_data_storage.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Log backup storage size|<p>The cumulative log backup storage size. Applies to vCore-based and Hyperscale databases.</p>|Dependent item|azure.db.mssql.storage.backup.log.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.log_backup_size_bytes.maximum`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Azure MSSQL DTU: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/Azure Microsoft SQL DTU Database by HTTP/azure.db.mssql.data.errors))>0`|Average||
|Azure MSSQL DTU: Microsoft SQL database is unavailable|<p>The resource state is unavailable.</p>|`last(/Azure Microsoft SQL DTU Database by HTTP/azure.db.mssql.availability.state)=2`|High||
|Azure MSSQL DTU: Microsoft SQL database is degraded|<p>The resource is in a degraded state.</p>|`last(/Azure Microsoft SQL DTU Database by HTTP/azure.db.mssql.availability.state)=1`|Average||
|Azure MSSQL DTU: Microsoft SQL database is in unknown state|<p>The resource state is unknown.</p>|`last(/Azure Microsoft SQL DTU Database by HTTP/azure.db.mssql.availability.state)=3`|Warning||
|Azure MSSQL DTU: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Azure Microsoft SQL DTU Database by HTTP/azure.db.mssql.cpu.percentage,5m)>{$AZURE.DB.CPU.UTIL.CRIT}`|High||
|Azure MSSQL DTU: High DTU utilization|<p>The DTU utilization is too high. The system might be slow to respond.</p>|`min(/Azure Microsoft SQL DTU Database by HTTP/azure.db.mssql.dtu.percentage,5m)>{$AZURE.DB.DTU.UTIL.CRIT}`|High||
|Azure MSSQL DTU: Storage space is critically low|<p>Critical utilization of the storage space.</p>|`last(/Azure Microsoft SQL DTU Database by HTTP/azure.db.mssql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.CRIT}`|Average||
|Azure MSSQL DTU: Storage space is low|<p>High utilization of the storage space.</p>|`last(/Azure Microsoft SQL DTU Database by HTTP/azure.db.mssql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.WARN}`|Warning|**Depends on**:<br><ul><li>Azure MSSQL DTU: Storage space is critically low</li></ul>|

# Azure Microsoft SQL Database by HTTP

## Overview

This template is designed to monitor Microsoft SQL databases by HTTP.
It works without any external scripts and uses the script item.

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Microsoft Azure SQL databases

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

> See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure the macros: `{$AZURE.APP.ID}`, `{$AZURE.PASSWORD}`, `{$AZURE.TENANT.ID}`, `{$AZURE.SUBSCRIPTION.ID}`, and `{$AZURE.RESOURCE.ID}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$AZURE.APP.ID}|<p>The App ID of Microsoft Azure.</p>||
|{$AZURE.PASSWORD}|<p>Microsoft Azure password.</p>||
|{$AZURE.DATA.TIMEOUT}|<p>API response timeout.</p>|`15s`|
|{$AZURE.TENANT.ID}|<p>Microsoft Azure tenant ID.</p>||
|{$AZURE.SUBSCRIPTION.ID}|<p>Microsoft Azure subscription ID.</p>||
|{$AZURE.RESOURCE.ID}|<p>Microsoft Azure Microsoft SQL database ID.</p>||
|{$AZURE.DB.CPU.UTIL.CRIT}|<p>The critical threshold of CPU utilization, expressed in %.</p>|`90`|
|{$AZURE.DB.MEMORY.UTIL.CRIT}|<p>The critical threshold of memory utilization, expressed in %.</p>|`90`|
|{$AZURE.DB.STORAGE.PUSED.WARN}|<p>The warning threshold of storage utilization, expressed in %.</p>|`80`|
|{$AZURE.DB.STORAGE.PUSED.CRIT}|<p>The critical threshold of storage utilization, expressed in %.</p>|`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>The result of API requests is expressed in the JSON.</p>|Script|azure.db.mssql.data.get|
|Get errors|<p>A list of errors from API requests.</p>|Dependent item|azure.db.mssql.data.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.errors`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Availability state|<p>The availability status of the resource.</p>|Dependent item|azure.db.mssql.availability.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health.availabilityState`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Replace: `Available -> 0`</p></li><li><p>Replace: `Degraded -> 1`</p></li><li><p>Replace: `Unavailable -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>In range: `0 -> 3`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Availability status detailed|<p>The summary description of the availability status.</p>|Dependent item|azure.db.mssql.availability.details<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health.summary`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Percentage CPU|<p>The CPU percent of a host.</p>|Dependent item|azure.db.mssql.cpu.percentage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cpu_percent.average`</p></li></ul>|
|Data IO percentage|<p>The percentage of physical data read.</p>|Dependent item|azure.db.mssql.data.read.percentage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.physical_data_read_percent.average`</p></li></ul>|
|Log IO percentage|<p>The percentage of I/O log. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.log.write.percentage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.log_write_percent.average`</p></li></ul>|
|Data space used|<p>Data space used. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.storage.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.storage.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Connections successful|<p>The count of successful connections.</p>|Dependent item|azure.db.mssql.connections.successful<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.connection_successful.total`</p></li></ul>|
|Connections failed: System errors|<p>The count of failed connections with system errors.</p>|Dependent item|azure.db.mssql.connections.failed.system<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.connection_failed.total`</p></li></ul>|
|Connections blocked by firewall|<p>The count of connections blocked by firewall.</p>|Dependent item|azure.db.mssql.firewall.blocked<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.blocked_by_firewall.total`</p></li></ul>|
|Deadlocks|<p>The count of deadlocks. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.deadlocks<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.deadlock.total`</p></li></ul>|
|Data space used percent|<p>Data space used percent. Not applicable to the data warehouses or Hyperscale databases.</p>|Dependent item|azure.db.mssql.storage.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.storage_percent.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|In-Memory OLTP storage percent|<p>In-Memory OLTP storage percent. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.storage.xtp.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.xtp_storage_percent.average`</p></li></ul>|
|Workers percentage|<p>The percentage of workers. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.workers.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.workers_percent.average`</p></li></ul>|
|Sessions percentage|<p>The percentage of sessions. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.sessions.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.sessions_percent.average`</p></li></ul>|
|Sessions count|<p>The number of active sessions. Not applicable to Synapse DW Analytics.</p>|Dependent item|azure.db.mssql.sessions.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.sessions_count.average`</p></li></ul>|
|CPU limit|<p>The CPU limit. Applies to the vCore-based databases.</p>|Dependent item|azure.db.mssql.cpu.limit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cpu_limit.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|CPU used|<p>The CPU used. Applies to the vCore-based databases.</p>|Dependent item|azure.db.mssql.cpu.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cpu_used.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|SQL Server process core percent|<p>The CPU usage as a percentage of the SQL DB process. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.server.cpu.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.sqlserver_process_core_percent.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|SQL Server process memory percent|<p>Memory usage as a percentage of the SQL DB process. Not applicable to data warehouses.</p>|Dependent item|azure.db.mssql.server.memory.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.sqlserver_process_memory_percent.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Tempdb data file size|<p>The space used in `tempdb` data files, expressed in bytes. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.tempdb.data.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.tempdb_data_size.maximum`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Tempdb log file size|<p>The space used in `tempdb` transaction log file, expressed in bytes. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.tempdb.log.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.tempdb_log_size.maximum`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|Tempdb log used percent|<p>The percentage of space used in `tempdb` transaction log file. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.tempdb.log.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.tempdb_log_used_percent.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data space allocated|<p>The allocated data storage. Not applicable to the data warehouses.</p>|Dependent item|azure.db.mssql.storage.allocated<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.allocated_data_storage.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Full backup storage size|<p>Cumulative full backup storage size. Applies to the vCore-based databases. Not applicable to the Hyperscale databases.</p>|Dependent item|azure.db.mssql.storage.backup.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.full_backup_size_bytes.maximum`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Differential backup storage size|<p>Cumulative differential backup storage size. Applies to the vCore-based databases. Not applicable to the Hyperscale databases.</p>|Dependent item|azure.db.mssql.storage.backup.diff.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.diff_backup_size_bytes.maximum`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Log backup storage size|<p>Cumulative log backup storage size. Applies to the vCore-based and Hyperscale databases.</p>|Dependent item|azure.db.mssql.storage.backup.log.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.log_backup_size_bytes.maximum`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Azure MSSQL: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/Azure Microsoft SQL Database by HTTP/azure.db.mssql.data.errors))>0`|Average||
|Azure MSSQL: Microsoft SQL database is unavailable|<p>The resource state is unavailable.</p>|`last(/Azure Microsoft SQL Database by HTTP/azure.db.mssql.availability.state)=2`|High||
|Azure MSSQL: Microsoft SQL database is degraded|<p>The resource is in a degraded state.</p>|`last(/Azure Microsoft SQL Database by HTTP/azure.db.mssql.availability.state)=1`|Average||
|Azure MSSQL: Microsoft SQL database is in unknown state|<p>The resource state is unknown.</p>|`last(/Azure Microsoft SQL Database by HTTP/azure.db.mssql.availability.state)=3`|Warning||
|Azure MSSQL: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Azure Microsoft SQL Database by HTTP/azure.db.mssql.cpu.percentage,5m)>{$AZURE.DB.CPU.UTIL.CRIT}`|High||
|Azure MSSQL: Storage space is critically low|<p>Critical utilization of the storage space.</p>|`last(/Azure Microsoft SQL Database by HTTP/azure.db.mssql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.CRIT}`|Average||
|Azure MSSQL: Storage space is low|<p>High utilization of the storage space.</p>|`last(/Azure Microsoft SQL Database by HTTP/azure.db.mssql.storage.percent)>{$AZURE.DB.STORAGE.PUSED.WARN}`|Warning||

# Azure Cosmos DB for MongoDB by HTTP

## Overview

This template is designed for the effortless deployment of Azure Cosmos DB for MongoDB monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Microsoft Azure Cosmos DB

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

> See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure the macros: `{$AZURE.APP.ID}`, `{$AZURE.PASSWORD}`, `{$AZURE.TENANT.ID}`, `{$AZURE.SUBSCRIPTION.ID}`, and `{$AZURE.RESOURCE.ID}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$AZURE.APP.ID}|<p>The App ID of Microsoft Azure.</p>||
|{$AZURE.PASSWORD}|<p>Microsoft Azure password.</p>||
|{$AZURE.DATA.TIMEOUT}|<p>API response timeout.</p>|`15s`|
|{$AZURE.TENANT.ID}|<p>Microsoft Azure tenant ID.</p>||
|{$AZURE.SUBSCRIPTION.ID}|<p>Microsoft Azure subscription ID.</p>||
|{$AZURE.RESOURCE.ID}|<p>Microsoft Azure Cosmos DB ID.</p>||
|{$AZURE.DB.COSMOS.MONGO.AVAILABILITY}|<p>The warning threshold of the Cosmos DB for MongoDB service availability.</p>|`70`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>The result of API requests is expressed in the JSON.</p>|Script|azure.cosmosdb.data.get|
|Get errors|<p>A list of errors from API requests.</p>|Dependent item|azure.cosmosdb.data.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Total requests|<p>Number of requests per minute.</p>|Dependent item|azure.cosmosdb.total.requests<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.TotalRequests.count`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Total request units|<p>The request units consumed per minute.</p>|Dependent item|azure.cosmosdb.total.request.units<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.TotalRequestUnits.total`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Metadata requests|<p>The count of metadata requests.</p><p>Cosmos DB maintains system metadata collection for each account, which allows you to enumerate collections, databases, etc., and their configurations, free of charge.</p>|Dependent item|azure.cosmosdb.metadata.requests<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.MetadataRequests.count`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Mongo requests|<p>The number of Mongo requests made.</p>|Dependent item|azure.cosmosdb.mongo.requests<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.MongoRequests.count`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Mongo request charge|<p>The Mongo request units consumed.</p>|Dependent item|azure.cosmosdb.mongo.requests.charge<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.MongoRequestCharge.total`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Server side latency|<p>The server side latency.</p>|Dependent item|azure.cosmosdb.server.side.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.ServerSideLatency.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Server side latency, gateway|<p>The server side latency in gateway connection mode.</p>|Dependent item|azure.cosmosdb.server.side.latency.gateway<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.ServerSideLatencyGateway.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Server side latency, direct|<p>The server side latency in direct connection mode.</p>|Dependent item|azure.cosmosdb.server.side.latency.direct<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.ServerSideLatencyDirect.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Replication latency, P99|<p>The P99 replication latency across source and target regions for geo-enabled account.</p>|Dependent item|azure.cosmosdb.replication.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.ReplicationLatency.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Service availability|<p>The account requests availability at one hour granularity.</p>|Dependent item|azure.cosmosdb.service.availability<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.availability.ServiceAvailability.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Data usage|<p>The total data usage.</p>|Dependent item|azure.cosmosdb.data.usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.DataUsage.total`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Index usage|<p>The total index usage.</p>|Dependent item|azure.cosmosdb.index.usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.IndexUsage.total`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Document quota|<p>The total storage quota.</p>|Dependent item|azure.cosmosdb.document.quota<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.DocumentQuota.total`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Document count|<p>The total document count.</p>|Dependent item|azure.cosmosdb.document.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.DocumentCount.total`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Normalized RU consumption|<p>The max RU consumption percentage per minute.</p>|Dependent item|azure.cosmosdb.normalized.ru.consumption<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.NormalizedRUConsumption.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Physical partition throughput|<p>The physical partition throughput.</p>|Dependent item|azure.cosmosdb.physical.partition.throughput<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.PhysicalPartitionThroughputInfo.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Autoscale max throughput|<p>The autoscale max throughput.</p>|Dependent item|azure.cosmosdb.autoscale.max.throughput<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.AutoscaleMaxThroughput.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Provisioned throughput|<p>The provisioned throughput.</p>|Dependent item|azure.cosmosdb.provisioned.throughput<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.ProvisionedThroughput.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Physical partition size|<p>The physical partition size in bytes.</p>|Dependent item|azure.cosmosdb.physical.partition.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.requests.PhysicalPartitionSizeInfo.maximum`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Azure Cosmos DB: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/Azure Cosmos DB for MongoDB by HTTP/azure.cosmosdb.data.errors))>0`|Average||
|Azure Cosmos DB: Cosmos DB for MongoDB account: Availability is low||`(min(/Azure Cosmos DB for MongoDB by HTTP/azure.cosmosdb.service.availability,#3))<{$AZURE.DB.COSMOS.MONGO.AVAILABILITY}`|Warning||

# Azure Cost Management by HTTP

## Overview

This template is designed to monitor Microsoft Cost Management by HTTP.
It works without any external scripts and uses the script item.

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Microsoft Azure

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

> See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure the macros: `{$AZURE.APP.ID}`, `{$AZURE.PASSWORD}`, `{$AZURE.TENANT.ID}`, `{$AZURE.SUBSCRIPTION.ID}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$AZURE.APP.ID}|<p>The App ID of Microsoft Azure.</p>||
|{$AZURE.PASSWORD}|<p>Microsoft Azure password.</p>||
|{$AZURE.DATA.TIMEOUT}|<p>API response timeout.</p>|`60s`|
|{$AZURE.TENANT.ID}|<p>Microsoft Azure tenant ID.</p>||
|{$AZURE.SUBSCRIPTION.ID}|<p>Microsoft Azure subscription ID.</p>||
|{$AZURE.BILLING.MONTH}|<p>Months to get historical data from Azure Cost Management API, no more than 11 (plus current month). The time period for pulling the data cannot exceed 1 year.</p>|`11`|
|{$AZURE.LLD.FILTER.SERVICE.MATCHES}|<p>Filter of discoverable services by name.</p>|`.*`|
|{$AZURE.LLD.FILTER.SERVICE.NOT_MATCHES}|<p>Filter to exclude discovered services by name.</p>|`CHANGE_IF_NEEDED`|
|{$AZURE.LLD.FILTER.RESOURCE.LOCATION.MATCHES}|<p>Filter of discoverable locations by name.</p>|`.*`|
|{$AZURE.LLD.FILTER.RESOURCE.LOCATION.NOT_MATCHES}|<p>Filter to exclude discovered locations by name.</p>|`CHANGE_IF_NEEDED`|
|{$AZURE.LLD.FILTER.RESOURCE.GROUP.MATCHES}|<p>Filter of discoverable resource groups by name.</p>|`.*`|
|{$AZURE.LLD.FILTER.RESOURCE.GROUP.NOT_MATCHES}|<p>Filter to exclude discovered resource groups by name.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get monthly costs|<p>The result of API requests is expressed in the JSON.</p>|Script|azure.get.monthly.costs|
|Get daily costs|<p>The result of API requests is expressed in the JSON.</p>|Script|azure.get.daily.costs|
|Azure Cost: Get monthly costs errors|<p>A list of errors from API requests.</p>|Dependent item|azure.get.monthly.costs.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Azure Cost: Get daily costs errors|<p>A list of errors from API requests.</p>|Dependent item|azure.get.daily.costs.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Azure Cost: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/Azure Cost Management by HTTP/azure.get.monthly.costs.errors))>0`|Average||
|Azure Cost: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/Azure Cost Management by HTTP/azure.get.daily.costs.errors))>0`|Average||

### LLD rule Azure daily costs by services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Azure daily costs by services discovery|<p>Discovery of daily costs by services.</p>|Dependent item|azure.daily.services.costs.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Item prototypes for Azure daily costs by services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Service ["{#AZURE.SERVICE.NAME}"]: Meter ["{#AZURE.BILLING.METER}"]: Subcategory ["{#AZURE.BILLING.METER.SUBCATEGORY}"] daily cost|<p>The daily cost by service {#AZURE.SERVICE.NAME}, meter {#AZURE.BILLING.METER}, subcategory {#AZURE.BILLING.METER.SUBCATEGORY}.</p>|Dependent item|azure.daily.cost["{#AZURE.SERVICE.NAME}", "{#AZURE.BILLING.METER}", "{#AZURE.BILLING.METER.SUBCATEGORY}","{#AZURE.RESOURCE.GROUP}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule Azure monthly costs by services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Azure monthly costs by services discovery|<p>Discovery of monthly costs by services.</p>|Dependent item|azure.monthly.services.costs.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serviceCost.data`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Item prototypes for Azure monthly costs by services discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Service ["{#AZURE.SERVICE.NAME}"]: Month ["{#AZURE.BILLING.MONTH}"] cost|<p>The monthly cost by service {#AZURE.SERVICE.NAME}.</p>|Dependent item|azure.monthly.service.cost["{#AZURE.SERVICE.NAME}", "{#AZURE.BILLING.MONTH}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule Azure monthly costs by location discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Azure monthly costs by location discovery|<p>Discovery of monthly costs by location.</p>|Dependent item|azure.monthly.location.costs.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.resourceLocationCost.data`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Item prototypes for Azure monthly costs by location discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Location: ["{#AZURE.RESOURCE.LOCATION}"]: Month ["{#AZURE.BILLING.MONTH}"] cost|<p>The monthly cost by location {#AZURE.RESOURCE.LOCATION}.</p>|Dependent item|azure.monthly.location.cost["{#AZURE.RESOURCE.LOCATION}", "{#AZURE.BILLING.MONTH}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule Azure monthly costs by resource group discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Azure monthly costs by resource group discovery|<p>Discovery of monthly costs by resource group.</p>|Dependent item|azure.monthly.resource.group.costs.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.resourceGroupCost.data`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Item prototypes for Azure monthly costs by resource group discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Resource group: ["{#AZURE.RESOURCE.GROUP}"]: Month ["{#AZURE.BILLING.MONTH}"] cost|<p>The monthly cost by resource group {#AZURE.RESOURCE.GROUP}.</p>|Dependent item|azure.monthly.resource.group.cost["{#AZURE.RESOURCE.GROUP}", "{#AZURE.BILLING.MONTH}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule Azure monthly costs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Azure monthly costs discovery|<p>Discovery of monthly costs.</p>|Dependent item|azure.monthly.costs.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.monthCost.data`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Item prototypes for Azure monthly costs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Month ["{#AZURE.BILLING.MONTH}"] cost|<p>The monthly cost.</p>|Dependent item|azure.monthly.cost["{#AZURE.BILLING.MONTH}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

# Azure SQL Managed Instance by HTTP

## Overview

This template is designed to monitor Microsoft Azure SQL Managed Instance by HTTP.
It works without any external scripts and uses the script item.

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Azure SQL Managed Instance

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Create an Azure service principal via the Azure command-line interface (Azure CLI) for your subscription.

      `az ad sp create-for-rbac --name zabbix --role reader --scope /subscriptions/<subscription_id>`

> See [Azure documentation](https://docs.microsoft.com/en-us/cli/azure/create-an-azure-service-principal-azure-cli) for more details.

2. Link the template to a host.
3. Configure the macros: `{$AZURE.APP.ID}`, `{$AZURE.PASSWORD}`, `{$AZURE.TENANT.ID}`, `{$AZURE.SUBSCRIPTION.ID}`, and `{$AZURE.RESOURCE.ID}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AZURE.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||
|{$AZURE.APP.ID}|<p>The App ID of Microsoft Azure.</p>||
|{$AZURE.PASSWORD}|<p>Microsoft Azure password.</p>||
|{$AZURE.DATA.TIMEOUT}|<p>API response timeout.</p>|`15s`|
|{$AZURE.TENANT.ID}|<p>Microsoft Azure tenant ID.</p>||
|{$AZURE.SUBSCRIPTION.ID}|<p>Microsoft Azure subscription ID.</p>||
|{$AZURE.RESOURCE.ID}|<p>Microsoft Azure SQL managed instance ID.</p>||
|{$AZURE.SQL.INST.SPACE.CRIT}|<p>Storage space critical threshold, expressed in %.</p>|`90`|
|{$AZURE.SQL.INST.SPACE.WARN}|<p>Storage space warning threshold, expressed in %.</p>|`80`|
|{$AZURE.SQL.INST.CPU.WARN}|<p>CPU utilization warning threshold, expressed in %.</p>|`80`|
|{$AZURE.SQL.INST.CPU.CRIT}|<p>CPU utilization critical threshold, expressed in %.</p>|`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>Gathers data of the Azure SQL managed instance.</p>|Script|azure.sql_inst.data.get|
|Get errors|<p>A list of errors from API requests.</p>|Dependent item|azure.sql_inst.data.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Availability state|<p>The availability status of the resource.</p><p>0 - Available - no events detected that affect the health of the resource.</p><p>1 - Degraded  - your resource detected a loss in performance, although it's still available for use.</p><p>2 - Unavailable - the service detected an ongoing platform or non-platform event that affects the health of the resource.</p><p>3 - Unknown - Resource Health hasn't received information about the resource for more than 10 minutes.</p>|Dependent item|azure.sql_inst.availability.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health.availabilityState`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Replace: `Available -> 0`</p></li><li><p>Replace: `Degraded -> 1`</p></li><li><p>Replace: `Unavailable -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>In range: `0 -> 3`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Availability status detailed|<p>The summary description of the availability status.</p>|Dependent item|azure.sql_inst.availability.details<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health.summary`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Average CPU utilization|<p>Average CPU utilization of the instance.</p>|Dependent item|azure.sql_inst.cpu<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.avg_cpu_percent.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|IO bytes read|<p>Bytes read by the managed instance.</p>|Dependent item|azure.sql_inst.bytes.read<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.io_bytes_read.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|IO bytes write|<p>Bytes written by the managed instance.</p>|Dependent item|azure.sql_inst.bytes.write<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.io_bytes_written.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|IO request count|<p>IO request count by the managed instance.</p>|Dependent item|azure.sql_inst.requests<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.io_requests.average`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Storage space reserved|<p>Storage space reserved by the managed instance.</p>|Dependent item|azure.sql_inst.storage.reserved<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.reserved_storage_mb.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Storage space used|<p>Storage space used by the managed instance.</p>|Dependent item|azure.sql_inst.storage.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.storage_space_used_mb.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Storage space utilization|<p>Managed instance storage space utilization, in percent.</p>|Calculated|azure.sql_inst.storage.utilization|
|Virtual core count|<p>Virtual core count available to the managed instance.</p>|Dependent item|azure.sql_inst.core.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.virtual_core_count.average`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Instance state|<p>State of the managed instance.</p>|Dependent item|azure.sql_inst.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.instance.properties.state`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Instance collation|<p>Collation of the managed instance.</p>|Dependent item|azure.sql_inst.collation<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.instance.properties.collation`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Instance provisioning state|<p>Provisioning state of the managed instance.</p>|Dependent item|azure.sql_inst.provision<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.instance.properties.provisioningState`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Azure SQL instance: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/Azure SQL Managed Instance by HTTP/azure.sql_inst.data.errors))>0`|Average||
|Azure SQL instance: Azure SQL managed instance is unavailable|<p>The resource state is unavailable.</p>|`last(/Azure SQL Managed Instance by HTTP/azure.sql_inst.availability.state)=2`|High||
|Azure SQL instance: Azure SQL managed instance is degraded|<p>The resource is in a degraded state.</p>|`last(/Azure SQL Managed Instance by HTTP/azure.sql_inst.availability.state)=1`|Average||
|Azure SQL instance: Azure SQL managed instance is in unknown state|<p>The resource state is unknown.</p>|`last(/Azure SQL Managed Instance by HTTP/azure.sql_inst.availability.state)=3`|Warning||
|Azure SQL instance: Critically high CPU utilization|<p>CPU utilization is critically high.</p>|`min(/Azure SQL Managed Instance by HTTP/azure.sql_inst.cpu, 10m)>={$AZURE.SQL.INST.CPU.CRIT}`|Average|**Depends on**:<br><ul><li>Azure SQL instance: High CPU utilization</li></ul>|
|Azure SQL instance: High CPU utilization|<p>CPU utilization is too high.</p>|`min(/Azure SQL Managed Instance by HTTP/azure.sql_inst.cpu, 10m)>={$AZURE.SQL.INST.CPU.WARN}`|Warning||
|Azure SQL instance: Storage free space is critically low|<p>The free storage space has been less than `{$AZURE.SQL.INST.SPACE.CRIT}`% for 5m.</p>|`min(/Azure SQL Managed Instance by HTTP/azure.sql_inst.storage.utilization,5m)>{$AZURE.SQL.INST.SPACE.CRIT}`|Average|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Azure SQL instance: Storage free space is low</li></ul>|
|Azure SQL instance: Storage free space is low|<p>The free storage space has been less than `{$AZURE.SQL.INST.SPACE.WARN}`% for 5m.</p>|`min(/Azure SQL Managed Instance by HTTP/azure.sql_inst.storage.utilization,5m)>{$AZURE.SQL.INST.SPACE.WARN}`|Warning|**Manual close**: Yes|
|Azure SQL instance: Instance state has changed|<p>Azure SQL managed instance state has changed.</p>|`change(/Azure SQL Managed Instance by HTTP/azure.sql_inst.state)=1`|Warning||
|Azure SQL instance: Instance collation has changed|<p>Azure SQL managed instance collation has changed.</p>|`change(/Azure SQL Managed Instance by HTTP/azure.sql_inst.collation)=1`|Average||
|Azure SQL instance: Instance provisioning state has changed|<p>Azure SQL managed instance provisioning state has changed.</p>|`change(/Azure SQL Managed Instance by HTTP/azure.sql_inst.provision)<>0`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

