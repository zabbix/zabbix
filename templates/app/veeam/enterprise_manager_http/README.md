
# Veeam Backup Enterprise Manager by HTTP

## Overview

This template is designed to monitor Veeam Backup & Replication used Veeam Backup Enterprise Manager REST API.
Veeam Backup Enterprise Manager REST API lets communicate with Zabbix to query information about Veeam Backup Enterprise Manager objects.      
It works without any external scripts and uses the script item. 

## Requirements

For Zabbix version: 6.0 and higher.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create a user to monitor the service or use an existing read-only account.
You can obtain a collection of jobs if you are logged in under an account with the `Portal Administrator` role only.
> See [VEEAM HELP CENTER](https://helpcenter.veeam.com/docs/backup/em_rest/http_authentication.html?ver=110) for more details.
2. Link the template to a host.
3. Configure macros {$VEEAM.MANAGER.API.URL}, {$VEEAM.MANAGER.USER}, {$VEEAM.MANAGER.PASSWORD}.

## Configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$BACKUP.NAME.MATCHES} |<p>This macro is used in backup discovery rule.</p> |`.*` |
|{$BACKUP.NAME.NOT_MATCHES} |<p>This macro is used in backup discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$BACKUP.TYPE.MATCHES} |<p>This macro is used in backup discovery rule.</p> |`.*` |
|{$BACKUP.TYPE.NOT_MATCHES} |<p>This macro is used in backup discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$VEEAM.MANAGER.API.URL} |<p>Veeam Backup Enterprise Manager API endpoint URL in the format <scheme>://<host>:<port>.</p> |`https://localhost:9398/api` |
|{$VEEAM.MANAGER.DATA.TIMEOUT} |<p>A response timeout for API.</p> |`10` |
|{$VEEAM.MANAGER.HTTP.PROXY} |<p>Sets HTTP proxy to "http_proxy" value. If this parameter is empty then no proxy is used.</p> |`` |
|{$VEEAM.MANAGER.PASSWORD} |<p>Password of the account to be used for authentication..</p> |`` |
|{$VEEAM.MANAGER.USER} |<p>Username of the account to be used for authentication..</p> |`` |

### Template links

There are no template links in this template.

### Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Backup Files discovery |<p>Discovery of all backup files created on or imported to backup servers connected to Veeam Backup Enterprise Manager.</p> |DEPENDENT |veeam.backup.files.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.backupFiles.Refs`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p><p>**Filter**:</p>AND <p>- {#TYPE} MATCHES_REGEX `{$BACKUP.TYPE.MATCHES}`</p><p>- {#TYPE} NOT_MATCHES_REGEX `{$BACKUP.TYPE.NOT_MATCHES}`</p><p>- {#NAME} MATCHES_REGEX `{$BACKUP.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$BACKUP.NAME.NOT_MATCHES}`</p> |

### Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Veeam |Veeam Manager: Get metrics |<p>The result of API requests is expressed in the JSON.</p> |SCRIPT |veeam.manager.get.metrics<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Veeam |Veeam: Get errors |<p>The errors from API requests.</p> |DEPENDENT |veeam.manager.get.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.error`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Veeam |Running Jobs |<p>The informing about of the running jobs.</p> |DEPENDENT |veeam.manager.running.jobs<p>**Preprocessing**:</p><p>- JSONPATH: `$.JobStatistics.RunningJobs`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Veeam |Scheduled Jobs |<p>The informing about of the scheduled jobs.</p> |DEPENDENT |veeam.manager.scheduled.jobs<p>**Preprocessing**:</p><p>- JSONPATH: `$.reports_summary.JobStatistics.ScheduledJobs`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Veeam |Scheduled Backup Jobs |<p>The informing about of the scheduled backup jobs.</p> |DEPENDENT |veeam.manager.scheduled.backup.jobs<p>**Preprocessing**:</p><p>- JSONPATH: `$.JobStatistics.ScheduledBackupJobs`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Veeam |Scheduled Replica Jobs |<p>The informing about of the scheduled replica jobs.</p> |DEPENDENT |veeam.manager.scheduled.replica.jobs<p>**Preprocessing**:</p><p>- JSONPATH: `$.JobStatistics.ScheduledReplicaJobs`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Veeam |Total Job Runs |<p>The informing about of the total job runs.</p> |DEPENDENT |veeam.manager.scheduled.total.jobs<p>**Preprocessing**:</p><p>- JSONPATH: `$.JobStatistics.TotalJobRuns`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Veeam |Warnings Job Runs |<p>The informing about of the warning job runs.</p> |DEPENDENT |veeam.manager.warning.jobs<p>**Preprocessing**:</p><p>- JSONPATH: `$.JobStatistics.WarningsJobRuns`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Veeam |Failed Job Runs |<p>The informing about of the failed job runs.</p> |DEPENDENT |veeam.manager.failed.jobs<p>**Preprocessing**:</p><p>- JSONPATH: `$.JobStatistics.FailedJobRuns`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Veeam |Veeam backup: Backup Size [{#NAME}] |<p>Get backup size [{#NAME}].</p> |DEPENDENT |veeam.backup.file.size[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['{#NAME}'].BackupFile.BackupSize`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Veeam |Veeam backup: Data Size [{#NAME}] |<p>Get data size [{#NAME}].</p> |DEPENDENT |veeam.backup.data.size[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['{#NAME}'].BackupFile.DataSize`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Veeam |Veeam backup: Compression ratio [{#NAME}] |<p>Get data compression ratio [{#NAME}].</p> |DEPENDENT |veeam.backup.compress.ratio[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['{#NAME}'].BackupFile.CompressRatio`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Veeam |Veeam backup: Deduplication Ratio [{#NAME}] |<p>Get data deduplication ratio [{#NAME}].</p> |DEPENDENT |veeam.backup.deduplication.ratio[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['{#NAME}'].BackupFile.DeduplicationRatio`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Veeam: There are errors in requests to API |<p>Zabbix has received errors in response to API requests.</p> |`length(last(/Veeam Backup Enterprise Manager by HTTP/veeam.manager.get.errors))>0` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

