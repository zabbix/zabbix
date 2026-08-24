
# Veeam Backup Enterprise Manager by HTTP

## Overview

It works without any external scripts and uses the script item.

***NOTE:*** This template uses the Veeam Backup Enterprise Manager REST API.

Veeam Backup Enterprise Manager must be installed and licensed.

Supported editions include:
* VUL Foundation
* VUL Advanced
* VUL Premium
* Enterprise
* Enterprise Plus

Community Edition and Backup Starter do not provide Enterprise Manager functionality and are not supported by this template.

***NOTE:*** Veeam Backup & Replication v13 also provides a native REST API on port 9419, separate from the Enterprise Manager REST API used by this template.

A different Veeam Backup and Replication by HTTP template is required for monitoring the native VBR REST API.

> See [Veeam Data Platform Feature Comparison](https://www.veeam.com/licensing-pricing.html) for more details.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Veeam Backup and Replication, version 13.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create a user to monitor the service, or use an existing read-only account.
  Similarly to the user authentication in the Veeam Backup Enterprise Manager Web UI,
  the client authentication in the REST API dictates which operations a client is allowed to perform when working with the REST API.
  That is, if the client is authenticated using an account that does not have enough permissions to perform some actions, it will not be able to execute them.
  You can also obtain the collected jobs if you are logged in under an account having only `Portal Administrator` role.
> See [Veeam Help Center](https://helpcenter.veeam.com/docs/vbr/em_rest/http_authentication.html?ver=13) for more details.
2. Link the template to a host.
3. Configure the following macros: `{$VEEAM.MANAGER.API.URL}`, `{$VEEAM.MANAGER.USER}`, `{$VEEAM.MANAGER.PASSWORD}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VEEAM.MANAGER.API.URL}|<p>Veeam Backup Enterprise Manager API endpoint is a URL in the format: `<scheme>://<host>:<port>`.</p>|`https://localhost:9398`|
|{$VEEAM.MANAGER.HTTP.PROXY}|<p>Sets the HTTP proxy to `http_proxy` value. If this parameter is empty, then no proxy is used.</p>||
|{$VEEAM.MANAGER.PASSWORD}|<p>The `password` of the Veeam Backup Enterprise Manager account.</p>||
|{$VEEAM.MANAGER.USER}|<p>The `user name` of the Veeam Backup Enterprise Manager account.</p>||
|{$VEEAM.MANAGER.DATA.FREQUENCY}|<p>The update interval for the get metrics item.</p>|`1m`|
|{$VEEAM.MANAGER.DATA.TIMEOUT}|<p>A response timeout for API.</p>|`15s`|
|{$BACKUP.TYPE.MATCHES}|<p>Filter of discoverable backups by type.</p>|`.*`|
|{$BACKUP.TYPE.NOT_MATCHES}|<p>Filter to exclude discoverable backups by type.</p>|`CHANGE_IF_NEEDED`|
|{$BACKUP.NAME.MATCHES}|<p>Filter of discoverable backups by name.</p>|`.*`|
|{$BACKUP.NAME.NOT_MATCHES}|<p>Filter to exclude discoverable backups by name.</p>|`CHANGE_IF_NEEDED`|
|{$VEEAM.MANAGER.JOB.MAX.WARN}|<p>The maximum score of warning jobs (for a trigger expression).</p>|`10`|
|{$VEEAM.MANAGER.JOB.MAX.FAIL}|<p>The maximum score of failed jobs (for a trigger expression).</p>|`5`|
|{$VEEAM.REPOSITORY.NAME.MATCHES}|<p>Filter of discoverable repositories by name.</p>|`.*`|
|{$VEEAM.REPOSITORY.NAME.NOT_MATCHES}|<p>Filter to exclude discoverable repositories by name.</p>|`CHANGE_IF_NEEDED`|
|{$VEEAM.MANAGER.REPO.PFREE.WARN}|<p>The minimum free space percentage for a warning trigger expression (percent).</p>|`20`|
|{$VEEAM.MANAGER.REPO.PFREE.CRIT}|<p>The minimum free space percentage for a critical trigger expression (percent).</p>|`10`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get metrics|<p>The result of API requests is returned as JSON.</p>|Script|veeam.manager.get.metrics|
|Get errors|<p>The errors from API requests.</p>|Dependent item|veeam.manager.get.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jobs: Running|<p>Number of running jobs.</p>|Dependent item|veeam.manager.running.jobs<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.JobStatistics.runningJobs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jobs: Scheduled|<p>Number of scheduled jobs.</p>|Dependent item|veeam.manager.scheduled.jobs<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.JobStatistics.scheduledJobs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jobs: Scheduled backup|<p>Number of scheduled backup jobs.</p>|Dependent item|veeam.manager.scheduled.backup.jobs<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.JobStatistics.scheduledBackupJobs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jobs: Scheduled replica|<p>Number of scheduled replica jobs.</p>|Dependent item|veeam.manager.scheduled.replica.jobs<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.JobStatistics.scheduledReplicaJobs`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jobs: Total|<p>Number of total job runs.</p>|Dependent item|veeam.manager.total.jobs<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.JobStatistics.totalJobRuns`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jobs: Warnings|<p>Number of warning job runs.</p>|Dependent item|veeam.manager.warning.jobs<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.JobStatistics.warningsJobRuns`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jobs: Failed|<p>Number of failed job runs.</p>|Dependent item|veeam.manager.failed.jobs<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.JobStatistics.failedJobRuns`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Overview: Backup servers|<p>Number of backup servers.</p>|Dependent item|veeam.manager.overview.backup.servers<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Overview.backupServers`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Overview: Proxy servers|<p>Number of proxy servers.</p>|Dependent item|veeam.manager.overview.proxy.servers<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Overview.proxyServers`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Overview: Repository servers|<p>Number of repository servers.</p>|Dependent item|veeam.manager.overview.repository.servers<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Overview.repositoryServers`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VM States: Successful|<p>Number of successful VM latest states.</p>|Dependent item|veeam.manager.overview.successful.vm.states<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Overview.successfulVmLastestStates`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VM States: Warning|<p>Number of warning VM latest states.</p>|Dependent item|veeam.manager.overview.warning.vm.states<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Overview.warningVmLastestStates`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VM States: Failed|<p>Number of failed VM latest states.</p>|Dependent item|veeam.manager.overview.failed.vm.states<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Overview.failedVmLastestStates`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VM: Protected|<p>Number of protected VMs.</p>|Dependent item|veeam.manager.vms.protected<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VmsOverview.protectedVms`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VM: Backed up|<p>Number of backed up VMs.</p>|Dependent item|veeam.manager.vms.backedup<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VmsOverview.backedUpVms`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VM: Replicated|<p>Number of replicated VMs.</p>|Dependent item|veeam.manager.vms.replicated<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VmsOverview.replicatedVms`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VM: Restore points|<p>Number of restore points.</p>|Dependent item|veeam.manager.vms.restore.points<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VmsOverview.restorePoints`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VM: Full backup points size|<p>Full backup points total size.</p>|Dependent item|veeam.manager.vms.full.backup.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VmsOverview.fullBackupPointsSize`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VM: Incremental backup points size|<p>Incremental backup points total size.</p>|Dependent item|veeam.manager.vms.incremental.backup.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VmsOverview.incrementalBackupPointsSize`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VM: Replica restore points size|<p>Replica restore points total size.</p>|Dependent item|veeam.manager.vms.replica.restore.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VmsOverview.replicaRestorePointsSize`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VM: Source size|<p>Total size of source VMs.</p>|Dependent item|veeam.manager.vms.source.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VmsOverview.sourceVmsSize`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VM: Success backup percent|<p>Percent of successful backups.</p>|Dependent item|veeam.manager.vms.success.backup.percent<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.VmsOverview.successBackupPercents`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Veeam Backup Enterprise Manager: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/Veeam Backup Enterprise Manager by HTTP/veeam.manager.get.errors))>0`|Average||
|Veeam Backup Enterprise Manager: Warning job runs are too high||`min(/Veeam Backup Enterprise Manager by HTTP/veeam.manager.warning.jobs, 5m)>{$VEEAM.MANAGER.JOB.MAX.WARN}`|Warning|**Manual close**: Yes|
|Veeam Backup Enterprise Manager: Failed job runs are too high||`last(/Veeam Backup Enterprise Manager by HTTP/veeam.manager.failed.jobs)>{$VEEAM.MANAGER.JOB.MAX.FAIL}`|Average|**Manual close**: Yes|

### LLD rule Backup files discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Backup files discovery|<p>Discovery of all backup files created on, or imported to the backup servers that are connected to Veeam Backup Enterprise Manager.</p>|Dependent item|veeam.backup.files.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.backupFiles`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Backup files discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Backup file [{#NAME}]: Size|<p>Gets the backup size with the name `[{#NAME}]`.</p>|Dependent item|veeam.backup.file.size[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.backupFiles[?(@.name == "{#NAME}")].backupSize.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Backup file [{#NAME}]: Data size|<p>Gets the data size with the name `[{#NAME}]`.</p>|Dependent item|veeam.backup.data.size[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.backupFiles[?(@.name == "{#NAME}")].dataSize.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Backup file [{#NAME}]: Compression ratio|<p>Gets the data compression ratio with the name `[{#NAME}]`.</p>|Dependent item|veeam.backup.compress.ratio[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.backupFiles[?(@.name == "{#NAME}")].compressRatio.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Backup file [{#NAME}]: Deduplication ratio|<p>Gets the data deduplication ratio with the name `[{#NAME}]`.</p>|Dependent item|veeam.backup.deduplication.ratio[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Repositories discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Repositories discovery|<p>Discovery of all backup repositories connected to Veeam Backup Enterprise Manager.</p>|Dependent item|veeam.repository.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Repositories.periods`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Repositories discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Repository [{#NAME}]: Capacity|<p>Total capacity of the backup repository `[{#NAME}]`.</p>|Dependent item|veeam.repository.capacity[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Repository [{#NAME}]: Free space|<p>Free space on the backup repository `[{#NAME}]`.</p>|Dependent item|veeam.repository.free.space[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Repository [{#NAME}]: Backup size|<p>Backup data size on the backup repository `[{#NAME}]`.</p>|Dependent item|veeam.repository.backup.size[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Repositories discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Veeam Backup Enterprise Manager: Repository [{#NAME}]: Free space is critically low|<p>Free space on the repository `{#NAME}` is critically low.</p>|`last(/Veeam Backup Enterprise Manager by HTTP/veeam.repository.capacity[{#NAME}])>0 and min(/Veeam Backup Enterprise Manager by HTTP/veeam.repository.free.space[{#NAME}],15m)/last(/Veeam Backup Enterprise Manager by HTTP/veeam.repository.capacity[{#NAME}])*100<{$VEEAM.MANAGER.REPO.PFREE.CRIT}`|Average||
|Veeam Backup Enterprise Manager: Repository [{#NAME}]: Free space is low|<p>Free space on the repository `{#NAME}` is low.</p>|`last(/Veeam Backup Enterprise Manager by HTTP/veeam.repository.capacity[{#NAME}])>0 and min(/Veeam Backup Enterprise Manager by HTTP/veeam.repository.free.space[{#NAME}],15m)/last(/Veeam Backup Enterprise Manager by HTTP/veeam.repository.capacity[{#NAME}])*100<{$VEEAM.MANAGER.REPO.PFREE.WARN}`|Warning|**Depends on**:<br><ul><li>Veeam Backup Enterprise Manager: Repository [{#NAME}]: Free space is critically low</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

