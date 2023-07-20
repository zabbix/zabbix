
# Veeam Backup Enterprise Manager by HTTP

## Overview

It works without any external scripts and uses the script item. 

***NOTE:*** Veeam Backup Enterprise Manager REST API may not be available for some editions, the template will only work with the following editions of Veeam Backup and Replication:

1. Veeam Universal License (VUL) editions:
* Foundation
* Advanced
* Premium

2. Veeam Socket License editions:
* Enterprise Socket
* Enterprise Plus Socket 

> See [Veeam Data Platform Feature Comparison](https://www.veeam.com/licensing-pricing.html) for more details.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Veeam Backup and Replication, version 11.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create a user to monitor the service, or use an existing read-only account.
  Similarly to the user authentication in the Veeam Backup Enterprise Manager Web UI, 
  the client authentication in the REST API dictates which operations a client is allowed to perform when working with the REST API.
  That is, if the client is authenticated using an account that does not have enough permissions to perform some actions, it will not be able to execute them.
  You can also obtain the collected jobs if you are logged in under an account having only `Portal Administrator` role.
> See [Veeam Help Center](https://helpcenter.veeam.com/docs/backup/em_rest/http_authentication.html?ver=110) for more details.
2. Link the template to a host.
3. Configure the following macros: `{$VEEAM.MANAGER.API.URL}`, `{$VEEAM.MANAGER.USER}`, `{$VEEAM.MANAGER.PASSWORD}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VEEAM.MANAGER.API.URL}|<p>Veeam Backup Enterprise Manager API endpoint is a URL in the format: `<scheme>://<host>:<port>`.</p>|`https://localhost:9398`|
|{$VEEAM.MANAGER.HTTP.PROXY}|<p>Sets the HTTP proxy to `http_proxy` value. If this parameter is empty, then no proxy is used.</p>||
|{$VEEAM.MANAGER.PASSWORD}|<p>The `password` of the Veeam Backup Enterprise Manager account.</p>||
|{$VEEAM.MANAGER.USER}|<p>The `user name` of the Veeam Backup Enterprise Manager account .</p>||
|{$VEEAM.MANAGER.DATA.TIMEOUT}|<p>A response timeout for API.</p>|`10`|
|{$BACKUP.TYPE.MATCHES}|<p>This macro is used in backup discovery rule.</p>|`.*`|
|{$BACKUP.TYPE.NOT_MATCHES}|<p>This macro is used in backup discovery rule.</p>|`CHANGE_IF_NEEDED`|
|{$BACKUP.NAME.MATCHES}|<p>This macro is used in backup discovery rule.</p>|`.*`|
|{$BACKUP.NAME.NOT_MATCHES}|<p>This macro is used in backup discovery rule.</p>|`CHANGE_IF_NEEDED`|
|{$VEEAM.MANAGER.JOB.MAX.WARN}|<p>The maximum score of warning jobs (for a trigger expression).</p>|`10`|
|{$VEEAM.MANAGER.JOB.MAX.FAIL}|<p>The maximum score of failed jobs (for a trigger expression).</p>|`5`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Veeam Manager: Get metrics|<p>The result of API requests is expressed in the JSON.</p>|Script|veeam.manager.get.metrics|
|Veeam Manager: Get errors|<p>The errors from API requests.</p>|Dependent item|veeam.manager.get.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Veeam Manager: Running Jobs|<p>Informs about the running jobs.</p>|Dependent item|veeam.manager.running.jobs<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.JobStatistics.RunningJobs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Veeam Manager: Scheduled Jobs|<p>Informs about the scheduled jobs.</p>|Dependent item|veeam.manager.scheduled.jobs<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.JobStatistics.ScheduledJobs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Veeam Manager: Scheduled Backup Jobs|<p>Informs about the scheduled backup jobs.</p>|Dependent item|veeam.manager.scheduled.backup.jobs<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.JobStatistics.ScheduledBackupJobs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Veeam Manager: Scheduled Replica Jobs|<p>Informs about the scheduled replica jobs.</p>|Dependent item|veeam.manager.scheduled.replica.jobs<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.JobStatistics.ScheduledReplicaJobs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Veeam Manager: Total Job Runs|<p>Informs about the total job runs.</p>|Dependent item|veeam.manager.scheduled.total.jobs<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.JobStatistics.TotalJobRuns`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Veeam Manager: Warnings Job Runs|<p>Informs about the warning job runs.</p>|Dependent item|veeam.manager.warning.jobs<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.JobStatistics.WarningsJobRuns`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Veeam Manager: Failed Job Runs|<p>Informs about the failed job runs.</p>|Dependent item|veeam.manager.failed.jobs<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.JobStatistics.FailedJobRuns`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Veeam Manager: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/Veeam Backup Enterprise Manager by HTTP/veeam.manager.get.errors))>0`|Average||
|Veeam Manager: Warning job runs is too high||`last(/Veeam Backup Enterprise Manager by HTTP/veeam.manager.warning.jobs)>{$VEEAM.MANAGER.JOB.MAX.WARN}`|Warning|**Manual close**: Yes|
|Veeam Manager: Failed job runs is too high||`last(/Veeam Backup Enterprise Manager by HTTP/veeam.manager.failed.jobs)>{$VEEAM.MANAGER.JOB.MAX.FAIL}`|Average|**Manual close**: Yes|

### LLD rule Backup Files discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Backup Files discovery|<p>Discovery of all backup files created on, or imported to the backup servers that are connected to Veeam Backup Enterprise Manager.</p>|Dependent item|veeam.backup.files.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.backupFiles.Refs`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Backup Files discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Veeam Manager: Backup Size [{#NAME}]|<p>Gets the backup size with the name `[{#NAME}]`.</p>|Dependent item|veeam.backup.file.size[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['{#NAME}'].BackupFile.BackupSize`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Veeam Manager: Data Size [{#NAME}]|<p>Gets the data size with the name `[{#NAME}]`.</p>|Dependent item|veeam.backup.data.size[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['{#NAME}'].BackupFile.DataSize`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Veeam Manager: Compression ratio [{#NAME}]|<p>Gets the data compression ratio with the name `[{#NAME}]`.</p>|Dependent item|veeam.backup.compress.ratio[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['{#NAME}'].BackupFile.CompressRatio`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Veeam Manager: Deduplication Ratio [{#NAME}]|<p>Gets the data deduplication ratio with the name `[{#NAME}]`.</p>|Dependent item|veeam.backup.deduplication.ratio[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['{#NAME}'].BackupFile.DeduplicationRatio`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

