
# Veeam Backup Enterprise Manager by HTTP

## Overview

This template is designed to monitor Veeam Backup Enterprise Manager.
The Veeam Backup Enterprise Manager REST API enables the communication with Zabbix to query the information about Veeam Backup Enterprise Manager objects.
It works without any external scripts and uses the script item. 

## Requirements

For Zabbix version: 6.2 and higher.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create a user to monitor the service, or use an existing read-only account.
   You can also obtain the collected jobs if you are logged in under an account having only `Portal Administrator` role.
> See [Veeam Help Center](https://helpcenter.veeam.com/docs/backup/em_rest/http_authentication.html?ver=110) for more details.
2. Link the template to a host.
3. Configure the following macros: `{$VEEAM.MANAGER.API.URL}`, `{$VEEAM.MANAGER.USER}`, `{$VEEAM.MANAGER.PASSWORD}`.

## Configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$BACKUP.NAME.MATCHES} |<p>This macro is used in backup discovery rule.</p> |`.*` |
|{$BACKUP.NAME.NOT_MATCHES} |<p>This macro is used in backup discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$BACKUP.TYPE.MATCHES} |<p>This macro is used in backup discovery rule.</p> |`.*` |
|{$BACKUP.TYPE.NOT_MATCHES} |<p>This macro is used in backup discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$VEEAM.MANAGER.API.URL} |<p>Veeam Backup Enterprise Manager API endpoint is a URL in the format: `<scheme>://<host>:<port>`.</p> |`https://localhost:9398` |
|{$VEEAM.MANAGER.DATA.TIMEOUT} |<p>A response timeout for API.</p> |`10` |
|{$VEEAM.MANAGER.HTTP.PROXY} |<p>Sets the HTTP proxy to `http_proxy` value. If this parameter is empty, then no proxy is used.</p> |`` |
|{$VEEAM.MANAGER.JOB.MAX.FAIL} |<p>The maximum score of failed jobs (for a trigger expression).</p> |`5` |
|{$VEEAM.MANAGER.JOB.MAX.WARN} |<p>The maximum score of warning jobs (for a trigger expression).</p> |`10` |
|{$VEEAM.MANAGER.PASSWORD} |<p>The `password` of the Veeam Backup Enterprise Manager account.</p> |`` |
|{$VEEAM.MANAGER.USER} |<p>The `user name` of the Veeam Backup Enterprise Manager account .</p> |`` |

### Template links

There are no template links in this template.

### Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Backup Files discovery |<p>Discovery of all backup files created on, or imported to the backup servers that are connected to Veeam Backup Enterprise Manager.</p> |DEPENDENT |veeam.backup.files.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.backupFiles.Refs`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p><p>**Filter**:</p>AND <p>- {#TYPE} MATCHES_REGEX `{$BACKUP.TYPE.MATCHES}`</p><p>- {#TYPE} NOT_MATCHES_REGEX `{$BACKUP.TYPE.NOT_MATCHES}`</p><p>- {#NAME} MATCHES_REGEX `{$BACKUP.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$BACKUP.NAME.NOT_MATCHES}`</p> |

### Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Veeam |Veeam Manager: Get metrics |<p>The result of API requests is expressed in the JSON.</p> |SCRIPT |veeam.manager.get.metrics<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Veeam |Veeam Manager: Get errors |<p>The errors from API requests.</p> |DEPENDENT |veeam.manager.get.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.error`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Veeam |Veeam Manager: Running Jobs |<p>Informs about the running jobs.</p> |DEPENDENT |veeam.manager.running.jobs<p>**Preprocessing**:</p><p>- JSONPATH: `$.JobStatistics.RunningJobs`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Veeam |Veeam Manager: Scheduled Jobs |<p>Informs about the scheduled jobs.</p> |DEPENDENT |veeam.manager.scheduled.jobs<p>**Preprocessing**:</p><p>- JSONPATH: `$.JobStatistics.ScheduledJobs`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Veeam |Veeam Manager: Scheduled Backup Jobs |<p>Informs about the scheduled backup jobs.</p> |DEPENDENT |veeam.manager.scheduled.backup.jobs<p>**Preprocessing**:</p><p>- JSONPATH: `$.JobStatistics.ScheduledBackupJobs`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Veeam |Veeam Manager: Scheduled Replica Jobs |<p>Informs about the scheduled replica jobs.</p> |DEPENDENT |veeam.manager.scheduled.replica.jobs<p>**Preprocessing**:</p><p>- JSONPATH: `$.JobStatistics.ScheduledReplicaJobs`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Veeam |Veeam Manager: Total Job Runs |<p>Informs about the total job runs.</p> |DEPENDENT |veeam.manager.scheduled.total.jobs<p>**Preprocessing**:</p><p>- JSONPATH: `$.JobStatistics.TotalJobRuns`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Veeam |Veeam Manager: Warnings Job Runs |<p>Informs about the warning job runs.</p> |DEPENDENT |veeam.manager.warning.jobs<p>**Preprocessing**:</p><p>- JSONPATH: `$.JobStatistics.WarningsJobRuns`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Veeam |Veeam Manager: Failed Job Runs |<p>Informs about the failed job runs.</p> |DEPENDENT |veeam.manager.failed.jobs<p>**Preprocessing**:</p><p>- JSONPATH: `$.JobStatistics.FailedJobRuns`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Veeam |Veeam Manager: Backup Size [{#NAME}] |<p>Gets the backup size with the name `[{#NAME}]`.</p> |DEPENDENT |veeam.backup.file.size[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['{#NAME}'].BackupFile.BackupSize`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Veeam |Veeam Manager: Data Size [{#NAME}] |<p>Gets the data size with the name `[{#NAME}]`.</p> |DEPENDENT |veeam.backup.data.size[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['{#NAME}'].BackupFile.DataSize`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Veeam |Veeam Manager: Compression ratio [{#NAME}] |<p>Gets the data compression ratio with the name `[{#NAME}]`.</p> |DEPENDENT |veeam.backup.compress.ratio[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['{#NAME}'].BackupFile.CompressRatio`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Veeam |Veeam Manager: Deduplication Ratio [{#NAME}] |<p>Gets the data deduplication ratio with the name `[{#NAME}]`.</p> |DEPENDENT |veeam.backup.deduplication.ratio[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['{#NAME}'].BackupFile.DeduplicationRatio`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Veeam Manager: There are errors in requests to API |<p>Zabbix has received errors in response to API requests.</p> |`length(last(/Veeam Backup Enterprise Manager by HTTP/veeam.manager.get.errors))>0` |AVERAGE | |
|Veeam Manager: Warning job runs is too high |<p>-</p> |`last(/Veeam Backup Enterprise Manager by HTTP/veeam.manager.warning.jobs)>{$VEEAM.MANAGER.JOB.MAX.WARN}` |WARNING |<p>Manual close: YES</p> |
|Veeam Manager: Failed job runs is too high |<p>-</p> |`last(/Veeam Backup Enterprise Manager by HTTP/veeam.manager.failed.jobs)>{$VEEAM.MANAGER.JOB.MAX.FAIL}` |AVERAGE |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

