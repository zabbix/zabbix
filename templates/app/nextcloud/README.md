
# Nextcloud by HTTP

## Overview

This template is designed for monitoring Nextcloud by HTTP via Zabbix, and it works without any external scripts.
Nextcloud is a suite of client-server software for creating and using file hosting services.
For more information, see the [`official documentation`](https://docs.nextcloud.com/server/latest/developer_manual/client_apis/OCS/ocs-api-overview.html#)


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Nextcloud ver. 27.0.1

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Set macros `{$NEXTCLOUD.USER.NAME}`, `{$NEXTCLOUD.USER.PASSWORD}`, `{$NEXTCLOUD.ADDRESS}`.
The user must be included in the Administrators group.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$NEXTCLOUD.SCHEMA}|<p>HTTP or HTTPS protocol of Nextcloud.</p>|`https`|
|{$NEXTCLOUD.USER.NAME}|<p>Nextcloud username.</p>|`root`|
|{$NEXTCLOUD.USER.PASSWORD}|<p>Nextcloud user password.</p>||
|{$NEXTCLOUD.ADDRESS}|<p>IP or DNS name of Nextcloud server.</p>|`127.0.0.1`|
|{$NEXTCLOUD.LLD.FILTER.USER.MATCHES}|<p>Filter of discoverable users by name.</p>|`.*`|
|{$NEXTCLOUD.LLD.FILTER.USER.NOT_MATCHES}|<p>Filter to exclude discovered users by name.</p>|`CHANGE_IF_NEEDED`|
|{$NEXTCLOUD.USER.QUOTA.PUSED.MAX}|<p>Storage utilization threshold.</p>|`90`|
|{$NEXTCLOUD.USER.MAX.INACTIVE}|<p>How many days a user can be inactive.</p>|`30`|
|{$NEXTCLOUD.CPU.LOAD.MAX}|<p>CPU load threshold (the number of processes in the system run queue).</p>|`95`|
|{$NEXTCLOUD.MEM.PUSED.MAX}|<p>Memory utilization threshold.</p>|`90`|
|{$NEXTCLOUD.SWAP.PUSED.MAX}|<p>Swap utilization threshold.</p>|`90`|
|{$NEXTCLOUD.PHP.MEM.PUSED.MAX}|<p>PHP memory utilization threshold.</p>|`90`|
|{$NEXTCLOUD.STORAGE.FREE.MIN}|<p>Free space threshold.</p>|`1G`|
|{$NEXTCLOUD.PROXY}|<p>Proxy HTTP(S) address.</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get server information|<p>This item provides useful server information, such as CPU load, RAM usage, disk usage, number of users, etc.</p><p>https://github.com/nextcloud/serverinfo</p>|HTTP agent|nextcloud.serverinfo.get_data<p>**Preprocessing**</p><ul><li>XML to JSON</li><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `<ocs><meta><status>failure</status><statuscode>999</statuscode><message/></meta><data><message>Unknown error</message></data></ocs>`</p></li></ul>|
|Server information status|<p>Server information API status</p>|Dependent item|nextcloud.serverinfo.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.meta.message`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Version|<p>Nextcloud service version.</p>|Dependent item|nextcloud.serverinfo.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.nextcloud.system.version`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Free space|<p>The amount of free disk space.</p>|Dependent item|nextcloud.serverinfo.freespace<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.nextcloud.system.freespace`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU load, avg 1m|<p>The average system load (the number of processes in the system run queue), last 1 minute.</p>|Dependent item|nextcloud.serverinfo.cpu.avg.1m<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.nextcloud.system.cpuload.element[0]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU load, avg 5m|<p>The average system load (the number of processes in the system run queue), last 5 minutes.</p>|Dependent item|nextcloud.serverinfo.cpu.avg.5m<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.nextcloud.system.cpuload.element[1]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU load, avg 15m|<p>The average system load (the number of processes in the system run queue), last 15 minutes.</p>|Dependent item|nextcloud.serverinfo.cpu.avg.15m<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.nextcloud.system.cpuload.element[2]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Memory total|<p>The size of the RAM.</p>|Dependent item|nextcloud.serverinfo.mem.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.nextcloud.system.mem_total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Memory free|<p>The amount of free RAM.</p>|Dependent item|nextcloud.serverinfo.mem.free<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.nextcloud.system.mem_free`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Memory used, in %|<p>RAM usage, in percent.</p>|Dependent item|nextcloud.serverinfo.mem.pused<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Swap total|<p>The size of the swap memory.</p>|Dependent item|nextcloud.serverinfo.swap.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.nextcloud.system.swap_total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Swap free|<p>The amount of free swap.</p>|Dependent item|nextcloud.serverinfo.swap.free<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.nextcloud.system.swap_free`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1024`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Swap used, in %|<p>Swap usage, in percent.</p>|Dependent item|nextcloud.serverinfo.swap.pused<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Apps installed|<p>The number of installed applications.</p>|Dependent item|nextcloud.serverinfo.apps.installed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.nextcloud.system.apps.num_installed`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Apps update available|<p>The number of applications for which an update is available.</p>|Dependent item|nextcloud.serverinfo.apps.update<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.nextcloud.system.apps.num_updates_available`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Web server|<p>Web server description.</p>|Dependent item|nextcloud.serverinfo.apps.webserver<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.server.webserver`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|PHP version|<p>PHP version</p>|Dependent item|nextcloud.serverinfo.php.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.server.php.version`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|PHP memory limit|<p>By default, the PHP memory limit is generally set to 128 MB, but it can be customized based on the application's specific needs. The php.ini file is usually the standard location to set the PHP memory limit.</p>|Dependent item|nextcloud.serverinfo.php.memory.limit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.server.php.memory_limit`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|PHP memory used|<p>PHP memory used</p>|Dependent item|nextcloud.serverinfo.php.memory.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.server.php.opcache.memory_usage.used_memory`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|PHP memory free|<p>PHP free memory size.</p>|Dependent item|nextcloud.serverinfo.php.memory.free<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.server.php.opcache.memory_usage.free_memory`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|PHP memory wasted|<p>Memory allocated to the service but not in use.</p>|Dependent item|nextcloud.serverinfo.php.memory.wasted<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.server.php.opcache.memory_usage.wasted_memory`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|PHP memory wasted, in %|<p>Memory allocated to the service but not in use, in percent.</p>|Dependent item|nextcloud.serverinfo.php.memory.wasted_percentage<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|PHP memory used, in %|<p>PHP memory used percentage</p>|Dependent item|nextcloud.serverinfo.php.memory.pused<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|PHP maximum execution time|<p>By default, the maximum execution time for PHP scripts is set to 30 seconds. If a script runs for longer than 30 seconds, PHP stops the script and reports an error. You can control the amount of time PHP allows scripts to run by changing the 'max_execution_time' directive in your php.ini file.</p>|Dependent item|nextcloud.serverinfo.php.max_execution_time<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.server.php.max_execution_time`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|PHP maximum upload file size|<p>By default, the maximum upload file size for PHP scripts is set to 128 megabytes. However, you may want to change this limit. For example, you can set a lower limit to prevent users from uploading large files to your site. To do this, change the 'upload_max_filesize' and 'post_max_size' directives.</p>|Dependent item|nextcloud.serverinfo.php.upload_max_filesize<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.server.php.upload_max_filesize`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Database type|<p>Database type.</p>|Dependent item|nextcloud.serverinfo.db.type<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.server.database.type`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Database version|<p>Database description.</p>|Dependent item|nextcloud.serverinfo.db.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.server.database.version`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Database size|<p>Size of database.</p>|Dependent item|nextcloud.serverinfo.db.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.server.database.size`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Active users, last 5 minutes|<p>The number of active users in the last 5 minutes.</p>|Dependent item|nextcloud.serverinfo.active_users.last5m<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.activeUsers.last5minutes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Active users, last 1 hour|<p>The number of active users in the last 1 hour.</p>|Dependent item|nextcloud.serverinfo.active_users.last1h<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.activeUsers.last1hour`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Active users, last 24 hours|<p>The number of active users in the last day.</p>|Dependent item|nextcloud.serverinfo.active_users.last24hours<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.activeUsers.last24hours`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Nextcloud: Server information unavailable|<p>Failed to get server information.</p>|`last(/Nextcloud by HTTP/nextcloud.serverinfo.status)<>"OK"`|High||
|Nextcloud: Version has changed|<p>Nextcloud version has changed. Acknowledge to close the problem manually.</p>|`change(/Nextcloud by HTTP/nextcloud.serverinfo.version)=1 and length(last(/Nextcloud by HTTP/nextcloud.serverinfo.version))>0`|Info|**Manual close**: Yes|
|Nextcloud: Disk space is low|<p>Condition should be the following:<br>- the disk free space is less than `{$NEXTCLOUD.STORAGE.FREE.MIN}`;</p>|`last(/Nextcloud by HTTP/nextcloud.serverinfo.freespace)<{$NEXTCLOUD.STORAGE.FREE.MIN}`|Average|**Manual close**: Yes|
|Nextcloud: CPU load is too high|<p>High CPU load.</p>|`min(/Nextcloud by HTTP/nextcloud.serverinfo.cpu.avg.1m,5m) > {$NEXTCLOUD.CPU.LOAD.MAX}`|Average||
|Nextcloud: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Nextcloud by HTTP/nextcloud.serverinfo.mem.pused,5m) > {$NEXTCLOUD.MEM.PUSED.MAX}`|Average||
|Nextcloud: High swap utilization|<p>The system is running out of free swap.</p>|`min(/Nextcloud by HTTP/nextcloud.serverinfo.swap.pused,5m) > {$NEXTCLOUD.SWAP.PUSED.MAX}`|Average||
|Nextcloud: Number of installed apps has been changed|<p>Applications have been installed or removed.</p>|`change(/Nextcloud by HTTP/nextcloud.serverinfo.apps.installed)<>0`|Info|**Manual close**: Yes|
|Nextcloud: Application updates are available|<p>Updates are available for some of the installed applications.</p>|`last(/Nextcloud by HTTP/nextcloud.serverinfo.apps.update)<>0`|Warning|**Manual close**: Yes|
|Nextcloud: PHP version has changed|<p>The PHP version has changed. Acknowledge to close the problem manually.</p>|`change(/Nextcloud by HTTP/nextcloud.serverinfo.php.version)=1 and length(last(/Nextcloud by HTTP/nextcloud.serverinfo.php.version))>0`|Info|**Manual close**: Yes|
|Nextcloud: High PHP memory utilization|<p>The PHP is running out of free memory.</p>|`min(/Nextcloud by HTTP/nextcloud.serverinfo.php.memory.pused,5m) > {$NEXTCLOUD.PHP.MEM.PUSED.MAX}`|Average||
|Nextcloud: Database version has changed|<p>The Database version has changed. Acknowledge to close the problem manually.</p>|`change(/Nextcloud by HTTP/nextcloud.serverinfo.db.version)=1 and length(last(/Nextcloud by HTTP/nextcloud.serverinfo.db.version))>0`|Info|**Manual close**: Yes|

### LLD rule Nextcloud: User discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Nextcloud: User discovery|<p>User discovery.</p>|HTTP agent|nextcloud.user.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.users`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Nextcloud: User discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|User "{#NEXTCLOUD.USER}": Get data|<p>Get common information about user</p>|HTTP agent|nextcloud.user.get_data[{#NEXTCLOUD.USER}]<p>**Preprocessing**</p><ul><li>XML to JSON</li><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `<ocs><meta><status>failure</status><statuscode>999</statuscode><message/></meta><data><message>Unknown error</message></data></ocs>`</p></li></ul>|
|User "{#NEXTCLOUD.USER}": Status|<p>User account status.</p>|Dependent item|nextcloud.user.enabled[{#NEXTCLOUD.USER}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.enabled`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|User "{#NEXTCLOUD.USER}": Storage location|<p>The location of the user's store.</p>|Dependent item|nextcloud.user.storageLocation[{#NEXTCLOUD.USER}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.storageLocation`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|User "{#NEXTCLOUD.USER}": Last login|<p>The time the user has last logged in.</p>|Dependent item|nextcloud.user.lastLogin[{#NEXTCLOUD.USER}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.lastLogin`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|User "{#NEXTCLOUD.USER}": Last login, days ago|<p>The number of days since the user has last logged in.</p>|Dependent item|nextcloud.user.inactive[{#NEXTCLOUD.USER}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|User "{#NEXTCLOUD.USER}": Quota free space|<p>The size of the free available space in the user's storage.</p>|Dependent item|nextcloud.user.quota.free[{#NEXTCLOUD.USER}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.quota.free`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|User "{#NEXTCLOUD.USER}": Quota used space|<p>The size of the used available space in the user storage.</p>|Dependent item|nextcloud.user.quota.used[{#NEXTCLOUD.USER}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.quota.used`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|User "{#NEXTCLOUD.USER}": Quota total space|<p>The size of space available in the user's storage.</p>|Dependent item|nextcloud.user.quota.total[{#NEXTCLOUD.USER}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.quota.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|User "{#NEXTCLOUD.USER}": Quota used space, in %|<p>Usage of the allocated storage space, in percent.</p>|Dependent item|nextcloud.user.quota.pused[{#NEXTCLOUD.USER}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.quota.relative`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|User "{#NEXTCLOUD.USER}": Quota|<p>The size of space available in the user's storage.</p>|Dependent item|nextcloud.user.quota[{#NEXTCLOUD.USER}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.quota.quota`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Replace: `none -> -99`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|User "{#NEXTCLOUD.USER}": Display name|<p>User visible name.</p>|Dependent item|nextcloud.user.displayname[{#NEXTCLOUD.USER}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.displayname`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|User "{#NEXTCLOUD.USER}": Language|<p>User language.</p>|Dependent item|nextcloud.user.language[{#NEXTCLOUD.USER}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ocs.data.language`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Nextcloud: User discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Nextcloud: User "{#NEXTCLOUD.USER}" status changed|<p>User account status has changed.</p>|`change(/Nextcloud by HTTP/nextcloud.user.enabled[{#NEXTCLOUD.USER}]) = 1`|Info||
|Nextcloud: User "{#NEXTCLOUD.USER}": inactive|<p>The user has not logged in for more than {$NEXTCLOUD.USER.MAX.INACTIVE:"{#NEXTCLOUD.USER}"} days.</p>|`last(/Nextcloud by HTTP/nextcloud.user.inactive[{#NEXTCLOUD.USER}]) > {$NEXTCLOUD.USER.MAX.INACTIVE:"{#NEXTCLOUD.USER}"}`|Info||
|Nextcloud: User "{#NEXTCLOUD.USER}": High quota utilization|<p>More than {$NEXTCLOUD.USER.QUOTA.PUSED.MAX:"{#NEXTCLOUD.USER}"} percent of the allocated storage space has been used.</p>|`min(/Nextcloud by HTTP/nextcloud.user.quota.pused[{#NEXTCLOUD.USER}],5m) > {$NEXTCLOUD.USER.QUOTA.PUSED.MAX:"{#NEXTCLOUD.USER}"}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

