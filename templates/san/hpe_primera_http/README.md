
# HPE Primera by HTTP

## Overview

The template to monitor HPE Primera by HTTP.
It works without any external scripts and uses the script item.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- HPE Primera 4.2.1.6

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Create a user on the storage with a browse role and enable it for all domains, for example "zabbix".
2. The WSAPI server does not start automatically.
   Log in to the CLI as Super, Service, or any role granted the wsapi_set right.
   Start the WSAPI server by command: `startwsapi`.
   To check WSAPI state use command: `showwsapi`.
3. Link template to the host.
4. Set the hostname or IP address of the host in the {$HPE.PRIMERA.API.HOST} macro and configure the username and password in the {$HPE.PRIMERA.API.USERNAME} and {$HPE.PRIMERA.API.PASSWORD} macros.
5. Change the {$HPE.PRIMERA.API.SCHEME} and {$HPE.PRIMERA.API.PORT} macros if needed.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$HPE.PRIMERA.API.PASSWORD}|<p>Specify password for WSAPI.</p>||
|{$HPE.PRIMERA.API.USERNAME}|<p>Specify user name for WSAPI.</p>|`zabbix`|
|{$HPE.PRIMERA.LLD.FILTER.TASK.NAME.MATCHES}|<p>Filter of discoverable tasks by name.</p>|`CHANGE_IF_NEEDED`|
|{$HPE.PRIMERA.LLD.FILTER.TASK.NAME.NOT_MATCHES}|<p>Filter to exclude discovered tasks by name.</p>|`.*`|
|{$HPE.PRIMERA.LLD.FILTER.TASK.TYPE.MATCHES}|<p>Filter of discoverable tasks by type.</p>|`.*`|
|{$HPE.PRIMERA.LLD.FILTER.TASK.TYPE.NOT_MATCHES}|<p>Filter to exclude discovered tasks by type.</p>|`CHANGE_IF_NEEDED`|
|{$HPE.PRIMERA.DATA.TIMEOUT}|<p>Response timeout for WSAPI.</p>|`15s`|
|{$HPE.PRIMERA.API.SCHEME}|<p>The WSAPI scheme (http/https).</p>|`https`|
|{$HPE.PRIMERA.API.HOST}|<p>The hostname or IP address of the API host.</p>||
|{$HPE.PRIMERA.API.PORT}|<p>The WSAPI port.</p>|`443`|
|{$HPE.PRIMERA.VOLUME.NAME.MATCHES}|<p>This macro is used in filters of volume discovery rule.</p>|`.*`|
|{$HPE.PRIMERA.VOLUME.NAME.NOT_MATCHES}|<p>This macro is used in filters of volume discovery rule.</p>|`^(admin\|.srdata\|.mgmtdata)$`|
|{$HPE.PRIMERA.CPG.NAME.MATCHES}|<p>This macro is used in filters of CPGs discovery rule.</p>|`.*`|
|{$HPE.PRIMERA.CPG.NAME.NOT_MATCHES}|<p>This macro is used in filters of CPGs discovery rule.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>The JSON with result of WSAPI requests.</p>|Script|hpe.primera.get.data|
|Get errors|<p>A list of errors from WSAPI requests.</p>|Dependent item|hpe.primera.get.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.errors`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get disks data|<p>Disks data.</p>|Dependent item|hpe.primera.get.disks<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.disks`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get CPGs data|<p>Common provisioning groups data.</p>|Dependent item|hpe.primera.get.cpgs<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpgs`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get hosts data|<p>Hosts data.</p>|Dependent item|hpe.primera.get.hosts<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hosts`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get ports data|<p>Ports data.</p>|Dependent item|hpe.primera.get.ports<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ports`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get system data|<p>System data.</p>|Dependent item|hpe.primera.get.system<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.system`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get tasks data|<p>Tasks data.</p>|Dependent item|hpe.primera.get.tasks<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tasks`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get volumes data|<p>Volumes data.</p>|Dependent item|hpe.primera.get.volumes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.volumes`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Capacity allocated|<p>Allocated capacity in the system.</p>|Dependent item|hpe.primera.system.capacity.allocated<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.allocatedCapacityMiB`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Chunklet size|<p>Chunklet size.</p>|Dependent item|hpe.primera.system.chunklet.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.chunkletSizeMiB`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|System contact|<p>Contact of the system.</p>|Dependent item|hpe.primera.system.contact<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.contact`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Capacity failed|<p>Failed capacity in the system.</p>|Dependent item|hpe.primera.system.capacity.failed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.failedCapacityMiB`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Capacity free|<p>Free capacity in the system.</p>|Dependent item|hpe.primera.system.capacity.free<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.freeCapacityMiB`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|System location|<p>Location of the system.</p>|Dependent item|hpe.primera.system.location<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.location`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Model|<p>System model.</p>|Dependent item|hpe.primera.system.model<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.model`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|System name|<p>System name.</p>|Dependent item|hpe.primera.system.name<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.name`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Serial number|<p>System serial number.</p>|Dependent item|hpe.primera.system.serial_number<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serialNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Software version number|<p>Storage system software version number.</p>|Dependent item|hpe.primera.system.sw_version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.systemVersion`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Capacity total|<p>Total capacity in the system.</p>|Dependent item|hpe.primera.system.capacity.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalCapacityMiB`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Nodes total|<p>Total number of nodes in the system.</p>|Dependent item|hpe.primera.system.nodes.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalNodes`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Nodes online|<p>Number of online nodes in the system.</p>|Dependent item|hpe.primera.system.nodes.online<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.onlineNodes.length()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disks total|<p>Number of physical disks.</p>|Dependent item|hpe.primera.disks.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service ping|<p>Checks if the service is running and accepting TCP connections.</p>|Simple check|net.tcp.service["{$HPE.PRIMERA.API.SCHEME}","{$HPE.PRIMERA.API.HOST}","{$HPE.PRIMERA.API.PORT}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Primera: There are errors in requests to WSAPI|<p>Zabbix has received errors in requests to WSAPI.</p>|`length(last(/HPE Primera by HTTP/hpe.primera.get.errors))>0`|Average|**Depends on**:<br><ul><li>HPE Primera: Service is unavailable</li></ul>|
|HPE Primera: Service is unavailable||`max(/HPE Primera by HTTP/net.tcp.service["{$HPE.PRIMERA.API.SCHEME}","{$HPE.PRIMERA.API.HOST}","{$HPE.PRIMERA.API.PORT}"],5m)=0`|High|**Manual close**: Yes|

### LLD rule Common provisioning groups discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Common provisioning groups discovery|<p>List of CPGs resources.</p>|Dependent item|hpe.primera.cpg.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Common provisioning groups discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPG [{#NAME}]: Get CPG data|<p>CPG {#NAME} data</p>|Dependent item|hpe.primera.cpg["{#ID}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.id == "{#ID}")].first()`</p></li></ul>|
|CPG [{#NAME}]: Degraded state|<p>Detailed state of the CPG:</p><p></p><p>LDS_NOT_STARTED (1) - LDs not started.</p><p>NOT_STARTED (2) - VV not started.</p><p>NEEDS_CHECK (3) - check for consistency.</p><p>NEEDS_MAINT_CHECK (4) - maintenance check is required.</p><p>INTERNAL_CONSISTENCY_ERROR (5) - internal consistency error.</p><p>SNAPDATA_INVALID (6) - invalid snapshot data.</p><p>PRESERVED (7) - unavailable LD sets due to missing chunklets. Preserved remaining VV data.</p><p>STALE (8) - parts of the VV contain old data because of a copy-on-write operation.</p><p>COPY_FAILED (9) - a promote or copy operation to this volume failed.</p><p>DEGRADED_AVAIL (10) - degraded due to availability.</p><p>DEGRADED_PERF (11) - degraded due to performance.</p><p>PROMOTING (12) - volume is the current target of a promote operation.</p><p>COPY_TARGET (13) - volume is the current target of a physical copy operation.</p><p>RESYNC_TARGET (14) - volume is the current target of a resynchronized copy operation.</p><p>TUNING (15) - volume tuning is in progress.</p><p>CLOSING (16) - volume is closing.</p><p>REMOVING (17) - removing the volume.</p><p>REMOVING_RETRY (18) - retrying a volume removal operation.</p><p>CREATING (19) - creating a volume.</p><p>COPY_SOURCE (20) - copy source.</p><p>IMPORTING (21) - importing a volume.</p><p>CONVERTING (22) - converting a volume.</p><p>INVALID (23) - invalid.</p><p>EXCLUSIVE (24) - local storage system has exclusive access to the volume.</p><p>CONSISTENT (25) - volume is being imported consistently along with other volumes in the VV set.</p><p>STANDBY (26) - volume in standby mode.</p><p>SD_META_INCONSISTENT (27) - SD Meta Inconsistent.</p><p>SD_NEEDS_FIX (28) - SD needs fix.</p><p>SD_META_FIXING (29) - SD meta fix.</p><p>UNKNOWN (999) - unknown state.</p><p>NOT_SUPPORTED_BY_WSAPI (1000) - state not supported by WSAPI.</p>|Dependent item|hpe.primera.cpg.state["{#ID}",degraded]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.degradedStates`</p></li></ul>|
|CPG [{#NAME}]: Failed state|<p>Detailed state of the CPG:</p><p></p><p>LDS_NOT_STARTED (1) - LDs not started.</p><p>NOT_STARTED (2) - VV not started.</p><p>NEEDS_CHECK (3) - check for consistency.</p><p>NEEDS_MAINT_CHECK (4) - maintenance check is required.</p><p>INTERNAL_CONSISTENCY_ERROR (5) - internal consistency error.</p><p>SNAPDATA_INVALID (6) - invalid snapshot data.</p><p>PRESERVED (7) - unavailable LD sets due to missing chunklets. Preserved remaining VV data.</p><p>STALE (8) - parts of the VV contain old data because of a copy-on-write operation.</p><p>COPY_FAILED (9) - a promote or copy operation to this volume failed.</p><p>DEGRADED_AVAIL (10) - degraded due to availability.</p><p>DEGRADED_PERF (11) - degraded due to performance.</p><p>PROMOTING (12) - volume is the current target of a promote operation.</p><p>COPY_TARGET (13) - volume is the current target of a physical copy operation.</p><p>RESYNC_TARGET (14) - volume is the current target of a resynchronized copy operation.</p><p>TUNING (15) - volume tuning is in progress.</p><p>CLOSING (16) - volume is closing.</p><p>REMOVING (17) - removing the volume.</p><p>REMOVING_RETRY (18) - retrying a volume removal operation.</p><p>CREATING (19) - creating a volume.</p><p>COPY_SOURCE (20) - copy source.</p><p>IMPORTING (21) - importing a volume.</p><p>CONVERTING (22) - converting a volume.</p><p>INVALID (23) - invalid.</p><p>EXCLUSIVE (24) - local storage system has exclusive access to the volume.</p><p>CONSISTENT (25) - volume is being imported consistently along with other volumes in the VV set.</p><p>STANDBY (26) - volume in standby mode.</p><p>SD_META_INCONSISTENT (27) - SD Meta Inconsistent.</p><p>SD_NEEDS_FIX (28) - SD needs fix.</p><p>SD_META_FIXING (29) - SD meta fix.</p><p>UNKNOWN (999) - unknown state.</p><p>NOT_SUPPORTED_BY_WSAPI (1000) - state not supported by WSAPI.</p>|Dependent item|hpe.primera.cpg.state["{#ID}",failed]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.failedStates`</p></li><li><p>JavaScript: `return JSON.stringify(JSON.parse(value));`</p></li></ul>|
|CPG [{#NAME}]: CPG space: Free|<p>Free CPG space.</p>|Dependent item|hpe.primera.cpg.space["{#ID}",free]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.freeSpaceMiB`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|CPG [{#NAME}]: Number of FPVVs|<p>Number of FPVVs (Fully Provisioned Virtual Volumes) allocated in the CPG.</p>|Dependent item|hpe.primera.cpg.fpvv["{#ID}",count]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.numFPVVs`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPG [{#NAME}]: Number of TPVVs|<p>Number of TPVVs (Thinly Provisioned Virtual Volumes) allocated in the CPG.</p>|Dependent item|hpe.primera.cpg.tpvv["{#ID}",count]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.numTPVVs`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPG [{#NAME}]: Number of TDVVs|<p>Number of TDVVs (Thinly Deduplicated Virtual Volume) created in the CPG.</p>|Dependent item|hpe.primera.cpg.tdvv["{#ID}",count]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.numTDVVs`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPG [{#NAME}]: Raw space: Free|<p>Raw free space.</p>|Dependent item|hpe.primera.cpg.space.raw["{#ID}",free]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rawFreeSpaceMiB`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|CPG [{#NAME}]: Raw space: Shared|<p>Raw shared space.</p>|Dependent item|hpe.primera.cpg.space.raw["{#ID}",shared]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rawSharedSpaceMiB`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|CPG [{#NAME}]: Raw space: Total|<p>Raw total space.</p>|Dependent item|hpe.primera.cpg.space.raw["{#ID}",total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rawTotalSpaceMiB`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|CPG [{#NAME}]: CPG space: Shared|<p>Shared CPG space.</p>|Dependent item|hpe.primera.cpg.space["{#ID}",shared]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sharedSpaceMiB`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|CPG [{#NAME}]: State|<p>Overall state of the CPG:</p><p></p><p>NORMAL (1) - normal operation;</p><p>DEGRADED (2) - degraded state;</p><p>FAILED (3) - abnormal operation;</p><p>UNKNOWN (99) - unknown state.</p>|Dependent item|hpe.primera.cpg.state["{#ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li></ul>|
|CPG [{#NAME}]: Logical disk space: Snapshot administration: Total (raw)|<p>Total physical (raw) logical disk space in snapshot administration.</p>|Dependent item|hpe.primera.cpg.space.sa["{#ID}",raw_total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SAUsage.rawTotalMiB`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|CPG [{#NAME}]: Logical disk space: Snapshot data: Total (raw)|<p>Total physical (raw) logical disk space in snapshot data space.</p>|Dependent item|hpe.primera.cpg.space.sd["{#ID}",raw_total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SDUsage.rawTotalMiB`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|CPG [{#NAME}]: Logical disk space: User space: Total (raw)|<p>Total physical (raw) logical disk space in user data space.</p>|Dependent item|hpe.primera.cpg.space.usr["{#ID}",raw_total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.UsrUsage.rawTotalMiB`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|CPG [{#NAME}]: Logical disk space: Snapshot administration: Total|<p>Total logical disk space in snapshot administration.</p>|Dependent item|hpe.primera.cpg.space.sa["{#ID}",total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SAUsage.totalMiB`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|CPG [{#NAME}]: Logical disk space: Snapshot data: Total|<p>Total logical disk space in snapshot data space.</p>|Dependent item|hpe.primera.cpg.space.sd["{#ID}",total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SDUsage.totalMiB`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|CPG [{#NAME}]: Logical disk space: User space: Total|<p>Total logical disk space in user data space.</p>|Dependent item|hpe.primera.cpg.space.usr["{#ID}",total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.UsrUsage.totalMiB`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|CPG [{#NAME}]: CPG space: Total|<p>Total CPG space.</p>|Dependent item|hpe.primera.cpg.space["{#ID}",total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalSpaceMiB`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|CPG [{#NAME}]: Logical disk space: Snapshot administration: Used (raw)|<p>Amount of physical (raw) logical disk used in snapshot administration.</p>|Dependent item|hpe.primera.cpg.space.sa["{#ID}",raw_used]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SAUsage.rawUsedMiB`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|CPG [{#NAME}]: Logical disk space: Snapshot data: Used (raw)|<p>Amount of physical (raw) logical disk used in snapshot data space.</p>|Dependent item|hpe.primera.cpg.space.sd["{#ID}",raw_used]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SDUsage.rawUsedMiB`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|CPG [{#NAME}]: Logical disk space: User space: Used (raw)|<p>Amount of physical (raw) logical disk used in user data space.</p>|Dependent item|hpe.primera.cpg.space.usr["{#ID}",raw_used]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.UsrUsage.rawUsedMiB`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|CPG [{#NAME}]: Logical disk space: Snapshot administration: Used|<p>Amount of logical disk used in snapshot administration.</p>|Dependent item|hpe.primera.cpg.space.sa["{#ID}",used]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SAUsage.usedMiB`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|CPG [{#NAME}]: Logical disk space: Snapshot data: Used|<p>Amount of logical disk used in snapshot data space.</p>|Dependent item|hpe.primera.cpg.space.sd["{#ID}",used]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SDUsage.usedMiB`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|CPG [{#NAME}]: Logical disk space: User space: Used|<p>Amount of logical disk used in user data space.</p>|Dependent item|hpe.primera.cpg.space.usr["{#ID}",used]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.UsrUsage.usedMiB`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|

### Trigger prototypes for Common provisioning groups discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Primera: CPG [{#NAME}]: Degraded|<p>CPG [{#NAME}] is in degraded state.</p>|`last(/HPE Primera by HTTP/hpe.primera.cpg.state["{#ID}"])=2`|Average||
|HPE Primera: CPG [{#NAME}]: Failed|<p>CPG [{#NAME}] is in failed state.</p>|`last(/HPE Primera by HTTP/hpe.primera.cpg.state["{#ID}"])=3`|High||

### LLD rule Disks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disks discovery|<p>List of physical disk resources.</p>|Dependent item|hpe.primera.disks.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Disks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk [{#POSITION}]: Get disk data|<p>Disk [{#POSITION}] data</p>|Dependent item|hpe.primera.disk["{#ID}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.id == "{#ID}")].first()`</p></li></ul>|
|Disk [{#POSITION}]: Firmware version|<p>Physical disk firmware version.</p>|Dependent item|hpe.primera.disk["{#ID}",fw_version]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fwVersion`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Disk [{#POSITION}]: Free size|<p>Physical disk free size.</p>|Dependent item|hpe.primera.disk["{#ID}",free_size]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.freeSizeMiB`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Disk [{#POSITION}]: Manufacturer|<p>Physical disk manufacturer.</p>|Dependent item|hpe.primera.disk["{#ID}",manufacturer]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.manufacturer`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Disk [{#POSITION}]: Model|<p>Manufacturer's device ID for disk.</p>|Dependent item|hpe.primera.disk["{#ID}",model]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.model`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Disk [{#POSITION}]: Path A0 degraded|<p>Indicates if this is a degraded path for the disk.</p>|Dependent item|hpe.primera.disk["{#ID}",loop_a0_degraded]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.loopA0.degraded`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li>Boolean to decimal</li></ul>|
|Disk [{#POSITION}]: Path A1 degraded|<p>Indicates if this is a degraded path for the disk.</p>|Dependent item|hpe.primera.disk["{#ID}",loop_a1_degraded]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.loopA1.degraded`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li>Boolean to decimal</li></ul>|
|Disk [{#POSITION}]: Path B0 degraded|<p>Indicates if this is a degraded path for the disk.</p>|Dependent item|hpe.primera.disk["{#ID}",loop_b0_degraded]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.loopB0.degraded`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li>Boolean to decimal</li></ul>|
|Disk [{#POSITION}]: Path B1 degraded|<p>Indicates if this is a degraded path for the disk.</p>|Dependent item|hpe.primera.disk["{#ID}",loop_b1_degraded]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.loopB1.degraded`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li>Boolean to decimal</li></ul>|
|Disk [{#POSITION}]: RPM|<p>RPM of the physical disk.</p>|Dependent item|hpe.primera.disk["{#ID}",rpm]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.RPM`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Disk [{#POSITION}]: Serial number|<p>Disk drive serial number.</p>|Dependent item|hpe.primera.disk["{#ID}",serial_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serialNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Disk [{#POSITION}]: State|<p>State of the physical disk:</p><p></p><p>Normal (1) - physical disk is in Normal state;</p><p>Degraded (2) - physical disk is not operating normally;</p><p>New (3) - physical disk is new, needs to be admitted;</p><p>Failed (4) - physical disk has failed;</p><p>Unknown (99) - physical disk state is unknown.</p>|Dependent item|hpe.primera.disk["{#ID}",state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p><p>⛔️Custom on fail: Set value to: `99`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk [{#POSITION}]: Total size|<p>Physical disk total size.</p>|Dependent item|hpe.primera.disk["{#ID}",total_size]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalSizeMiB`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|

### Trigger prototypes for Disks discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Primera: Disk [{#POSITION}]: Path A0 degraded|<p>Disk [{#POSITION}] path A0 in degraded state.</p>|`last(/HPE Primera by HTTP/hpe.primera.disk["{#ID}",loop_a0_degraded])=1`|Average||
|HPE Primera: Disk [{#POSITION}]: Path A1 degraded|<p>Disk [{#POSITION}] path A1 in degraded state.</p>|`last(/HPE Primera by HTTP/hpe.primera.disk["{#ID}",loop_a1_degraded])=1`|Average||
|HPE Primera: Disk [{#POSITION}]: Path B0 degraded|<p>Disk [{#POSITION}] path B0 in degraded state.</p>|`last(/HPE Primera by HTTP/hpe.primera.disk["{#ID}",loop_b0_degraded])=1`|Average||
|HPE Primera: Disk [{#POSITION}]: Path B1 degraded|<p>Disk [{#POSITION}] path B1 in degraded state.</p>|`last(/HPE Primera by HTTP/hpe.primera.disk["{#ID}",loop_b1_degraded])=1`|Average||
|HPE Primera: Disk [{#POSITION}]: Degraded|<p>Disk [{#POSITION}] in degraded state.</p>|`last(/HPE Primera by HTTP/hpe.primera.disk["{#ID}",state])=2`|Average||
|HPE Primera: Disk [{#POSITION}]: Failed|<p>Disk [{#POSITION}] in failed state.</p>|`last(/HPE Primera by HTTP/hpe.primera.disk["{#ID}",state])=3`|High||
|HPE Primera: Disk [{#POSITION}]: Unknown issue|<p>Disk [{#POSITION}] in unknown state.</p>|`last(/HPE Primera by HTTP/hpe.primera.disk["{#ID}",state])=99`|Info||

### LLD rule Hosts discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Hosts discovery|<p>List of host properties.</p>|Dependent item|hpe.primera.hosts.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Hosts discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Host [{#NAME}]: Get host data|<p>Host [{#NAME}] data</p>|Dependent item|hpe.primera.host["{#ID}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.id == "{#ID}")].first()`</p></li></ul>|
|Host [{#NAME}]: Comment|<p>Additional information for the host.</p>|Dependent item|hpe.primera.host["{#ID}",comment]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.descriptors.comment`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Host [{#NAME}]: Contact|<p>The host's owner and contact.</p>|Dependent item|hpe.primera.host["{#ID}",contact]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.descriptors.contact`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Host [{#NAME}]: IP address|<p>The host's IP address.</p>|Dependent item|hpe.primera.host["{#ID}",ipaddress]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.descriptors.IPAddr`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Host [{#NAME}]: Location|<p>The host's location.</p>|Dependent item|hpe.primera.host["{#ID}",location]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.descriptors.location`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Host [{#NAME}]: Model|<p>The host's model.</p>|Dependent item|hpe.primera.host["{#ID}",model]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.descriptors.model`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Host [{#NAME}]: OS|<p>The operating system running on the host.</p>|Dependent item|hpe.primera.host["{#ID}",os]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.descriptors.os`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### LLD rule Ports discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Ports discovery|<p>List of ports.</p>|Dependent item|hpe.primera.ports.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Ports discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Port [{#NODE}:{#SLOT}:{#CARD.PORT}]: Get port data|<p>Port [{#NODE}:{#SLOT}:{#CARD.PORT}] data</p>|Dependent item|hpe.primera.port["{#NODE}:{#SLOT}:{#CARD.PORT}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Port [{#NODE}:{#SLOT}:{#CARD.PORT}]: Failover state|<p>The state of the failover operation, shown for the two ports indicated in the N:S:P and Partner columns. The value can be one of the following:</p><p></p><p>none (1) - no failover in operation;</p><p>failover_pending (2) - in the process of failing over to partner;</p><p>failed_over (3) - failed over to partner;</p><p>active (4) - the partner port is failed over to this port;</p><p>active_down (5) - the partner port is failed over to this port, but this port is down;</p><p>active_failed (6) - the partner port is failed over to this port, but this port is down;</p><p>failback_pending (7) - in the process of failing back from partner.</p>|Dependent item|hpe.primera.port["{#NODE}:{#SLOT}:{#CARD.PORT}",failover_state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.failoverState`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Port [{#NODE}:{#SLOT}:{#CARD.PORT}]: Link state|<p>Port link state:</p><p></p><p>CONFIG_WAIT (1) - configuration wait;</p><p>ALPA_WAIT (2) - ALPA wait;</p><p>LOGIN_WAIT (3) - login wait;</p><p>READY (4) - link is ready;</p><p>LOSS_SYNC (5) - link is loss sync;</p><p>ERROR_STATE (6) - in error state;</p><p>XXX (7) - xxx;</p><p>NONPARTICIPATE (8) - link did not participate;</p><p>COREDUMP (9) - taking coredump;</p><p>OFFLINE (10) - link is offline;</p><p>FWDEAD (11) - firmware is dead;</p><p>IDLE_FOR_RESET (12) - link is idle for reset;</p><p>DHCP_IN_PROGRESS (13) - DHCP is in progress;</p><p>PENDING_RESET (14) - link reset is pending;</p><p>NEW (15) - link in new. This value is applicable for only virtual ports;</p><p>DISABLED (16) - link in disabled. This value is applicable for only virtual ports;</p><p>DOWN (17) - link in down. This value is applicable for only virtual ports;</p><p>FAILED (18) - link in failed. This value is applicable for only virtual ports;</p><p>PURGING (19) - link in purging. This value is applicable for only virtual ports.</p>|Dependent item|hpe.primera.port["{#NODE}:{#SLOT}:{#CARD.PORT}",link_state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.linkState`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Port [{#NODE}:{#SLOT}:{#CARD.PORT}]: Type|<p>Port connection type:</p><p></p><p>HOST (1) - FC port connected to hosts or fabric;</p><p>DISK (2) - FC port connected to disks;</p><p>FREE (3) - port is not connected to hosts or disks;</p><p>IPORT (4) - port is in iport mode;</p><p>RCFC (5) - FC port used for remote copy;</p><p>PEER (6) - FC port used for data migration;</p><p>RCIP (7) - IP (Ethernet) port used for remote copy;</p><p>ISCSI (8) - iSCSI (Ethernet) port connected to hosts;</p><p>CNA (9) - CNA port, which can be FCoE or iSCSI;</p><p>FS (10) - Ethernet File Persona ports.</p>|Dependent item|hpe.primera.port["{#NODE}:{#SLOT}:{#CARD.PORT}",type]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.type`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Port [{#NODE}:{#SLOT}:{#CARD.PORT}]: Hardware type|<p>Hardware type:</p><p></p><p>FC (1) - Fibre channel HBA;</p><p>ETH (2) - Ethernet NIC;</p><p>iSCSI (3) - iSCSI HBA;</p><p>CNA (4) - Converged network adapter;</p><p>SAS (5) - SAS HBA;</p><p>COMBO (6) - Combo card;</p><p>NVME (7) - NVMe drive;</p><p>UNKNOWN (99) - unknown hardware type.</p>|Dependent item|hpe.primera.port["{#NODE}:{#SLOT}:{#CARD.PORT}",hw_type]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hardwareType`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Ports discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Primera: Port [{#NODE}:{#SLOT}:{#CARD.PORT}]: Failover state is {ITEM.VALUE1}|<p>Port [{#NODE}:{#SLOT}:{#CARD.PORT}] has failover error.</p>|`last(/HPE Primera by HTTP/hpe.primera.port["{#NODE}:{#SLOT}:{#CARD.PORT}",failover_state])<>1 and last(/HPE Primera by HTTP/hpe.primera.port["{#NODE}:{#SLOT}:{#CARD.PORT}",failover_state])<>4`|Average||
|HPE Primera: Port [{#NODE}:{#SLOT}:{#CARD.PORT}]: Link state is {ITEM.VALUE1}|<p>Port [{#NODE}:{#SLOT}:{#CARD.PORT}] not in ready state.</p>|`last(/HPE Primera by HTTP/hpe.primera.port["{#NODE}:{#SLOT}:{#CARD.PORT}",link_state])<>4 and last(/HPE Primera by HTTP/hpe.primera.port["{#NODE}:{#SLOT}:{#CARD.PORT}",link_state])<>1 and last(/HPE Primera by HTTP/hpe.primera.port["{#NODE}:{#SLOT}:{#CARD.PORT}",link_state])<>3 and last(/HPE Primera by HTTP/hpe.primera.port["{#NODE}:{#SLOT}:{#CARD.PORT}",link_state])<>13 and last(/HPE Primera by HTTP/hpe.primera.port["{#NODE}:{#SLOT}:{#CARD.PORT}",link_state])<>15 and last(/HPE Primera by HTTP/hpe.primera.port["{#NODE}:{#SLOT}:{#CARD.PORT}",link_state])<>16`|High||
|HPE Primera: Port [{#NODE}:{#SLOT}:{#CARD.PORT}]: Link state is {ITEM.VALUE1}|<p>Port [{#NODE}:{#SLOT}:{#CARD.PORT}] not in ready state.</p>|`last(/HPE Primera by HTTP/hpe.primera.port["{#NODE}:{#SLOT}:{#CARD.PORT}",link_state])=1 or last(/HPE Primera by HTTP/hpe.primera.port["{#NODE}:{#SLOT}:{#CARD.PORT}",link_state])=3 or last(/HPE Primera by HTTP/hpe.primera.port["{#NODE}:{#SLOT}:{#CARD.PORT}",link_state])=13 or last(/HPE Primera by HTTP/hpe.primera.port["{#NODE}:{#SLOT}:{#CARD.PORT}",link_state])=15 or last(/HPE Primera by HTTP/hpe.primera.port["{#NODE}:{#SLOT}:{#CARD.PORT}",link_state])=16`|Average||

### LLD rule Tasks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Tasks discovery|<p>List of tasks started within last 24 hours.</p>|Dependent item|hpe.primera.tasks.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Tasks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Task [{#NAME}]: Get task data|<p>Task [{#NAME}] data</p>|Dependent item|hpe.primera.task["{#ID}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.id == "{#ID}")].first()`</p></li></ul>|
|Task [{#NAME}]: Finish time|<p>Task finish time.</p>|Dependent item|hpe.primera.task["{#ID}",finish_time]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.finishTime`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li><li><p>Does not match regular expression: `^-$`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Task [{#NAME}]: Start time|<p>Task start time.</p>|Dependent item|hpe.primera.task["{#ID}",start_time]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.startTime`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Task [{#NAME}]: Status|<p>Task status:</p><p></p><p>DONE (1) - task is finished;</p><p>ACTIVE (2) - task is in progress;</p><p>CANCELLED (3) - task is canceled;</p><p>FAILED (4) - task failed.</p>|Dependent item|hpe.primera.task["{#ID}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Task [{#NAME}]: Type|<p>Task type:</p><p></p><p>VV_COPY (1) - track the physical copy operations;</p><p>PHYS_COPY_RESYNC (2) - track physical copy resynchronization operations;</p><p>MOVE_REGIONS (3) - track region move operations;</p><p>PROMOTE_SV (4) - track virtual-copy promotions;</p><p>REMOTE_COPY_SYNC (5) - track remote copy group synchronizations;</p><p>REMOTE_COPY_REVERSE (6) - track the reversal of a remote copy group;</p><p>REMOTE_COPY_FAILOVER (7) - track the change-over of a secondary volume group to a primaryvolume group;REMOTE_COPY_RECOVER (8) - track synchronization start after a failover operation from originalsecondary cluster to original primary cluster;</p><p>REMOTE_COPY_RESTORE (9) - tracks the restoration process for groups that have already been recovered;</p><p>COMPACT_CPG (10) - track space consolidation in CPGs;</p><p>COMPACT_IDS (11) - track space consolidation in logical disks;</p><p>SNAPSHOT_ACCOUNTING (12) - track progress of snapshot space usage accounting;</p><p>CHECK_VV (13) - track the progress of the check-volume operation;</p><p>SCHEDULED_TASK (14) - track tasks that have been executed by the system scheduler;</p><p>SYSTEM_TASK (15) - track tasks that are periodically run by the storage system;</p><p>BACKGROUND_TASK (16) - track commands started using the starttask command;</p><p>IMPORT_VV (17) - track tasks that migrate data to the local storage system;</p><p>ONLINE_COPY (18) - track physical copy of the volume while online (createvvcopy-online command);</p><p>CONVERT_VV (19) - track tasks that convert a volume from an FPVV to a TPVV, and the reverse;</p><p>BACKGROUND_COMMAND (20) - track background command tasks;</p><p>CLX_SYNC (21) - track CLX synchronization tasks;</p><p>CLX_RECOVERY (22) - track CLX recovery tasks;</p><p>TUNE_SD (23) - tune copy space;</p><p>TUNE_VV (24) - tune virtual volume;</p><p>TUNE_VV_ROLLBACK (25) - tune virtual volume rollback;</p><p>TUNE_VV_RESTART (26) - tune virtual volume restart;</p><p>SYSTEM_TUNING (27) - system tuning;</p><p>NODE_RESCUE (28) - node rescue;</p><p>REPAIR_SYNC (29) - remote copy repair sync;</p><p>REMOTE_COPY_SWOVER (30) - remote copy switchover;</p><p>DEFRAGMENTATION (31) - defragmentation;</p><p>ENCRYPTION_CHANGE (32) - encryption change;</p><p>REMOTE_COPY_FAILSAFE (33) - remote copy failsafe;</p><p>TUNE_TPVV (34) - tune thin virtual volume;</p><p>REMOTE_COPY_CHG_MODE (35) - remote copy change mode;</p><p>ONLINE_PROMOTE (37) - online promote snap;</p><p>RELOCATE_PD (38) - relocate PD;</p><p>PERIODIC_CSS (39) - remote copy periodic CSS;</p><p>TUNEVV_LARGE (40) - tune large virtual volume;</p><p>SD_META_FIXER (41) - compression SD meta fixer;</p><p>DEDUP_DRYRUN (42) - preview dedup ratio;</p><p>COMPR_DRYRUN (43) - compression estimation;</p><p>DEDUP_COMPR_DRYRUN (44) - compression and dedup estimation;</p><p>UNKNOWN (99) - unknown task type.</p>|Dependent item|hpe.primera.task["{#ID}",type]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.type`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Tasks discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Primera: Task [{#NAME}]: Cancelled|<p>Task [{#NAME}] is cancelled.</p>|`last(/HPE Primera by HTTP/hpe.primera.task["{#ID}",status])=3`|Info||
|HPE Primera: Task [{#NAME}]: Failed|<p>Task [{#NAME}] is failed.</p>|`last(/HPE Primera by HTTP/hpe.primera.task["{#ID}",status])=4`|Average||

### LLD rule Volumes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Volumes discovery|<p>List of storage volume resources.</p>|Dependent item|hpe.primera.volumes.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Volumes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Volume [{#NAME}]: Get volume data|<p>Volume [{#NAME}] data</p>|Dependent item|hpe.primera.volume["{#ID}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.id == "{#ID}")].first()`</p></li></ul>|
|Volume [{#NAME}]: Administrative space: Free|<p>Free administrative space.</p>|Dependent item|hpe.primera.volume.space.admin["{#ID}",free]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.adminSpace.freeMiB`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Volume [{#NAME}]: Administrative space: Raw reserved|<p>Raw reserved administrative space.</p>|Dependent item|hpe.primera.volume.space.admin["{#ID}",raw_reserved]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.adminSpace.rawReservedMiB`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Volume [{#NAME}]: Administrative space: Reserved|<p>Reserved administrative space.</p>|Dependent item|hpe.primera.volume.space.admin["{#ID}",reserved]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.adminSpace.reservedMiB`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Volume [{#NAME}]: Administrative space: Used|<p>Used administrative space.</p>|Dependent item|hpe.primera.volume.space.admin["{#ID}",used]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.adminSpace.usedMiB`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Volume [{#NAME}]: Compaction ratio|<p>The compaction ratio indicates the overall amount of storage space saved with thin technology.</p>|Dependent item|hpe.primera.volume.capacity.efficiency["{#ID}",compaction]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.capacityEfficiency.compaction`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Volume [{#NAME}]: Compression state|<p>Volume compression state:</p><p></p><p>YES (1) - compression is enabled on the volume;</p><p>NO (2) - compression is disabled on the volume;</p><p>OFF (3) - compression is turned off;</p><p>NA (4) - compression is not available on the volume.</p>|Dependent item|hpe.primera.volume.state["{#ID}",compression]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.compressionState`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Volume [{#NAME}]: Deduplication state|<p>Volume deduplication state:</p><p></p><p>YES (1) - enables deduplication on the volume;</p><p>NO (2) - disables deduplication on the volume;</p><p>NA (3) - deduplication is not available;</p><p>OFF (4) - deduplication is turned off.</p>|Dependent item|hpe.primera.volume.state["{#ID}",deduplication]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.deduplicationState`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Volume [{#NAME}]: Degraded state|<p>Volume detailed state:</p><p></p><p>LDS_NOT_STARTED (1) - LDs not started.</p><p>NOT_STARTED (2) - VV not started.</p><p>NEEDS_CHECK (3) - check for consistency.</p><p>NEEDS_MAINT_CHECK (4) - maintenance check is required.</p><p>INTERNAL_CONSISTENCY_ERROR (5) - internal consistency error.</p><p>SNAPDATA_INVALID (6) - invalid snapshot data.</p><p>PRESERVED (7) - unavailable LD sets due to missing chunklets. Preserved remaining VV data.</p><p>STALE (8) - parts of the VV contain old data because of a copy-on-write operation.</p><p>COPY_FAILED (9) - a promote or copy operation to this volume failed.</p><p>DEGRADED_AVAIL (10) - degraded due to availability.</p><p>DEGRADED_PERF (11) - degraded due to performance.</p><p>PROMOTING (12) - volume is the current target of a promote operation.</p><p>COPY_TARGET (13) - volume is the current target of a physical copy operation.</p><p>RESYNC_TARGET (14) - volume is the current target of a resynchronized copy operation.</p><p>TUNING (15) - volume tuning is in progress.</p><p>CLOSING (16) - volume is closing.</p><p>REMOVING (17) - removing the volume.</p><p>REMOVING_RETRY (18) - retrying a volume removal operation.</p><p>CREATING (19) - creating a volume.</p><p>COPY_SOURCE (20) - copy source.</p><p>IMPORTING (21) - importing a volume.</p><p>CONVERTING (22) - converting a volume.</p><p>INVALID (23) - invalid.</p><p>EXCLUSIVE (24) - local storage system has exclusive access to the volume.</p><p>CONSISTENT (25) - volume is being imported consistently along with other volumes in the VV set.</p><p>STANDBY (26) - volume in standby mode.</p><p>SD_META_INCONSISTENT (27) - SD Meta Inconsistent.</p><p>SD_NEEDS_FIX (28) - SD needs fix.</p><p>SD_META_FIXING (29) - SD meta fix.</p><p>UNKNOWN (999) - unknown state.</p><p>NOT_SUPPORTED_BY_WSAPI (1000) - state not supported by WSAPI.</p>|Dependent item|hpe.primera.volume.state["{#ID}",degraded]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.degradedStates`</p></li></ul>|
|Volume [{#NAME}]: Failed state|<p>Volume detailed state:</p><p></p><p>LDS_NOT_STARTED (1) - LDs not started.</p><p>NOT_STARTED (2) - VV not started.</p><p>NEEDS_CHECK (3) - check for consistency.</p><p>NEEDS_MAINT_CHECK (4) - maintenance check is required.</p><p>INTERNAL_CONSISTENCY_ERROR (5) - internal consistency error.</p><p>SNAPDATA_INVALID (6) - invalid snapshot data.</p><p>PRESERVED (7) - unavailable LD sets due to missing chunklets. Preserved remaining VV data.</p><p>STALE (8) - parts of the VV contain old data because of a copy-on-write operation.</p><p>COPY_FAILED (9) - a promote or copy operation to this volume failed.</p><p>DEGRADED_AVAIL (10) - degraded due to availability.</p><p>DEGRADED_PERF (11) - degraded due to performance.</p><p>PROMOTING (12) - volume is the current target of a promote operation.</p><p>COPY_TARGET (13) - volume is the current target of a physical copy operation.</p><p>RESYNC_TARGET (14) - volume is the current target of a resynchronized copy operation.</p><p>TUNING (15) - volume tuning is in progress.</p><p>CLOSING (16) - volume is closing.</p><p>REMOVING (17) - removing the volume.</p><p>REMOVING_RETRY (18) - retrying a volume removal operation.</p><p>CREATING (19) - creating a volume.</p><p>COPY_SOURCE (20) - copy source.</p><p>IMPORTING (21) - importing a volume.</p><p>CONVERTING (22) - converting a volume.</p><p>INVALID (23) - invalid.</p><p>EXCLUSIVE (24) - local storage system has exclusive access to the volume.</p><p>CONSISTENT (25) - volume is being imported consistently along with other volumes in the VV set.</p><p>STANDBY (26) - volume in standby mode.</p><p>SD_META_INCONSISTENT (27) - SD Meta Inconsistent.</p><p>SD_NEEDS_FIX (28) - SD needs fix.</p><p>SD_META_FIXING (29) - SD meta fix.</p><p>UNKNOWN (999) - unknown state.</p><p>NOT_SUPPORTED_BY_WSAPI (1000) - state not supported by WSAPI.</p>|Dependent item|hpe.primera.volume.state["{#ID}",failed]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.failedStates`</p></li><li><p>JavaScript: `return JSON.stringify(JSON.parse(value));`</p></li></ul>|
|Volume [{#NAME}]: Overprovisioning ratio|<p>Overprovisioning capacity efficiency ratio.</p>|Dependent item|hpe.primera.volume.capacity.efficiency["{#ID}",overprovisioning]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.capacityEfficiency.overProvisioning`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Volume [{#NAME}]: Remote copy status|<p>Remote copy status of the volume:</p><p></p><p>NONE (1) - volume is not associated with remote copy;</p><p>PRIMARY (2) - volume is the primary copy;</p><p>SECONDARY (3) - volume is the secondary copy;</p><p>SNAP (4) - volume is the remote copy snapshot;</p><p>SYNC (5) - volume is a remote copy snapshot being used for synchronization;</p><p>DELETE (6) - volume is a remote copy snapshot that is marked for deletion;</p><p>UNKNOWN (99) - remote copy status is unknown for this volume.</p>|Dependent item|hpe.primera.volume.status["{#ID}",rcopy]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rcopyStatus`</p></li></ul>|
|Volume [{#NAME}]: Snapshot space: Free|<p>Free snapshot space.</p>|Dependent item|hpe.primera.volume.space.snapshot["{#ID}",free]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.snapshotSpace.freeMiB`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Volume [{#NAME}]: Snapshot space: Raw reserved|<p>Raw reserved snapshot space.</p>|Dependent item|hpe.primera.volume.space.snapshot["{#ID}",raw_reserved]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.snapshotSpace.rawReservedMiB`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Volume [{#NAME}]: Snapshot space: Reserved|<p>Reserved snapshot space.</p>|Dependent item|hpe.primera.volume.space.snapshot["{#ID}",reserved]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.snapshotSpace.reservedMiB`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Volume [{#NAME}]: Snapshot space: Used|<p>Used snapshot space.</p>|Dependent item|hpe.primera.volume.space.snapshot["{#ID}",used]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.snapshotSpace.usedMiB`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Volume [{#NAME}]: State|<p>State of the volume:</p><p></p><p>NORMAL (1) - normal operation;</p><p>DEGRADED (2) - degraded state;</p><p>FAILED (3) - abnormal operation;</p><p>UNKNOWN (99) - unknown state.</p>|Dependent item|hpe.primera.volume.state["{#ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li></ul>|
|Volume [{#NAME}]: Storage space saved using compression|<p>Indicates the amount of storage space saved using compression.</p>|Dependent item|hpe.primera.volume.capacity.efficiency["{#ID}",compression]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.capacityEfficiency.compression`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Volume [{#NAME}]: Storage space saved using deduplication|<p>Indicates the amount of storage space saved using deduplication.</p>|Dependent item|hpe.primera.volume.capacity.efficiency["{#ID}",deduplication]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.capacityEfficiency.deduplication`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Volume [{#NAME}]: Storage space saved using deduplication and compression|<p>Indicates the amount of storage space saved using deduplication and compression together.</p>|Dependent item|hpe.primera.volume.capacity.efficiency["{#ID}",reduction]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.capacityEfficiency.dataReduction`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Volume [{#NAME}]: Total reserved space|<p>Total reserved space.</p>|Dependent item|hpe.primera.volume.space.total["{#ID}",reserved]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalReservedMiB`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Volume [{#NAME}]: Total space|<p>Virtual size of volume.</p>|Dependent item|hpe.primera.volume.space.total["{#ID}",size]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sizeMiB`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Volume [{#NAME}]: Total used space|<p>Total used space. Sum of used user space and used snapshot space.</p>|Dependent item|hpe.primera.volume.space.total["{#ID}",used]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalUsedMiB`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Volume [{#NAME}]: User space: Free|<p>Free user space.</p>|Dependent item|hpe.primera.volume.space.user["{#ID}",free]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.userSpace.freeMiB`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Volume [{#NAME}]: User space: Raw reserved|<p>Raw reserved user space.</p>|Dependent item|hpe.primera.volume.space.user["{#ID}",raw_reserved]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.userSpace.rawReservedMiB`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Volume [{#NAME}]: User space: Reserved|<p>Reserved user space.</p>|Dependent item|hpe.primera.volume.space.user["{#ID}",reserved]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.userSpace.reservedMiB`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Volume [{#NAME}]: User space: Used|<p>Used user space.</p>|Dependent item|hpe.primera.volume.space.user["{#ID}",used]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.userSpace.usedMiB`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|

### Trigger prototypes for Volumes discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Primera: Volume [{#NAME}]: Degraded|<p>Volume [{#NAME}] is in degraded state.</p>|`last(/HPE Primera by HTTP/hpe.primera.volume.state["{#ID}"])=2`|Average||
|HPE Primera: Volume [{#NAME}]: Failed|<p>Volume [{#NAME}] is in failed state.</p>|`last(/HPE Primera by HTTP/hpe.primera.volume.state["{#ID}"])=3`|High||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

