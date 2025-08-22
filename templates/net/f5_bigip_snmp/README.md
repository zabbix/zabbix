
# F5 Big-IP by SNMP

## Overview

This template is designed for the effortless deployment of F5 Big-IP monitoring by Zabbix via SNMP and doesn't require any external scripts.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- F5 Big-IP

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SNMP.TIMEOUT}|<p>The time interval for SNMP agent availability trigger expression.</p>|`5m`|
|{$BIGIP.LLD.FILTER.PART.NAME.MATCHES}|<p>Filter of discoverable mount point names.</p>|`.*`|
|{$BIGIP.LLD.FILTER.PART.NAME.NOT_MATCHES}|<p>Filter to exclude discovered by mount point names.</p>|`CHANGE_IF_NEEDED`|
|{$BIGIP.LLD.OVERRIDE.PART.FILTER_LOW_SPACE_TRIGGER}|<p>Partitions that low free space trigger should ignore.</p>|`^(?:/usr\|/opt/\.sdm/(?:usr\|lib\|lib64))$`|
|{$BIGIP.CERT.MIN}|<p>Minimum number of days before certificate expiration.</p>|`7`|
|{$BIGIP.CPU.UTIL.WARN.MAX}|<p>The warning threshold of the CPU utilization expressed in %.</p>|`85`|
|{$BIGIP.CPU.UTIL.WARN.MIN}|<p>The recovery threshold of the CPU utilization expressed in %.</p>|`65`|
|{$BIGIP.MEMORY.UTIL.WARN.MAX}|<p>The warning threshold of the memory utilization in %.</p>|`85`|
|{$BIGIP.MEMORY.UTIL.WARN.MIN}|<p>The recovery threshold of the memory utilization in %.</p>|`65`|
|{$BIGIP.SWAP.UTIL.WARN.MAX}|<p>The warning threshold of the swap utilization in %.</p>|`85`|
|{$BIGIP.SWAP.UTIL.WARN.MIN}|<p>The recovery threshold of the swap utilization in %.</p>|`65`|
|{$BIGIP.FS.FREE.WARN.MAX}|<p>The recovery threshold of the file system utilization in %.</p>|`20`|
|{$BIGIP.FS.FREE.WARN.MIN}|<p>The warning threshold of the file system utilization in %.</p>|`10`|
|{$BIGIP.TEMP.HIGH}|<p>The critical threshold of the temperature in °C</p>|`50`|
|{$BIGIP.TEMP.WARN}|<p>The warning threshold of the temperature in °C</p>|`45`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SNMP agent availability||Zabbix internal|zabbix[host,snmp,available]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Chassis serial number|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>Serial number</p>|SNMP agent|bigip.serialnumber<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Hardware model name|<p>MIB: RFC1213-MIB</p><p>A textual description of the entity.  This value</p><p>should include the full name and version</p><p>identification of the system's hardware type,</p><p>software operating-system, and networking</p><p>software.  It is mandatory that this only contain</p><p>printable ASCII characters.</p>|SNMP agent|bigip.model<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Contact|<p>MIB: RFC1213-MIB</p><p>The textual identification of the contact person</p><p>for this managed node, together with information</p><p>on how to contact this person.</p>|SNMP agent|bigip.contact<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Host name|<p>MIB: RFC1213-MIB</p><p>An administratively-assigned name for this</p><p>managed node.  By convention, this is the node's</p><p>fully-qualified domain name.</p>|SNMP agent|bigip.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Location|<p>MIB: RFC1213-MIB</p><p>The physical location of this node (e.g.,</p><p>`telephone closet, 3rd floor').</p>|SNMP agent|bigip.location<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Uptime|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The system up time in 1/100 seconds since boot.</p>|SNMP agent|bigip.uptime<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Product name|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The product name.</p>|SNMP agent|bigip.product.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Product version|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The product version.</p>|SNMP agent|bigip.product.version<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Product build|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The product build number.</p>|SNMP agent|bigip.product.build<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Product edition|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The product edition.</p>|SNMP agent|bigip.product.edition<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Product build date|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The product build date.</p>|SNMP agent|bigip.product.date<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Open TCP connections|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The number of current open TCP connections.</p>|SNMP agent|bigip.tcp.open|
|Open UDP connections|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The number of current open UDP connections.</p>|SNMP agent|bigip.udp.open|
|TCP connections, CLOSE-WAIT/LAST-ACK|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The number of current TCP connections in CLOSE-WAIT/LAST-ACK.</p>|SNMP agent|bigip.tcp.close_wait|
|TCP connections, FIN-WAIT-1/CLOSING|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The number of current TCP connections in FIN-WAIT-1/CLOSING.</p>|SNMP agent|bigip.tcp.fin1_wait|
|TCP connections, FIN-WAIT-2|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The number of current TCP connections in FIN-WAIT-2.</p>|SNMP agent|bigip.tcp.fin2_wait|
|TCP connections, TIME-WAIT|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The number of current TCP connections in TIME-WAIT.</p>|SNMP agent|bigip.tcp.time_wait|
|Failover status|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The failover status ID on the system.</p><p>unknown - the failover status of the device is unknown;</p><p>offline - the device is offline;</p><p>forcedOffline - the device is forced offline;</p><p>standby - the device is standby;</p><p>active - the device is active.</p>|SNMP agent|bigip.failover|
|Sync Status|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The sync status ID on the system.</p><p>unknown - the device is disconnected from the device group;</p><p>syncing - the device is joining the device group or has requested changes from device group or inconsistent with the group;</p><p>needManualSync - changes have been made on the device not syncd to the device group;</p><p>inSync - the device is consistent with the device group;</p><p>syncFailed - the device is inconsistent with the device group, requires user intervention;</p><p>syncDisconnected - the device is not connected to any peers;</p><p>standalone - the device is in a standalone configuration;</p><p>awaitingInitialSync - the device is waiting for initial sync;</p><p>incompatibleVersion - the device's version is incompatible with rest of the devices in the device group;</p><p>partialSync - some but not all devices successfully received the last sync.</p>|SNMP agent|bigip.syncstatus|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|F5 Big-IP: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/F5 Big-IP by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning||
|F5 Big-IP: Chassis has been replaced|<p>Chassis serial number has changed. Acknowledge to close the problem manually.</p>|`last(/F5 Big-IP by SNMP/bigip.serialnumber,#1)<>last(/F5 Big-IP by SNMP/bigip.serialnumber,#2) and length(last(/F5 Big-IP by SNMP/bigip.serialnumber))>0`|Info|**Manual close**: Yes|
|F5 Big-IP: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/F5 Big-IP by SNMP/bigip.uptime)<10m`|Info|**Manual close**: Yes|
|F5 Big-IP: Cluster not in sync||`count(/F5 Big-IP by SNMP/bigip.failover,10m,"ne","3")>8 and count(/F5 Big-IP by SNMP/bigip.failover,10m,"ne","4")>6`|Warning|**Manual close**: Yes|
|F5 Big-IP: The device is inconsistent with the device group|<p>The device is inconsistent with the device group, requires user intervention</p>|`last(/F5 Big-IP by SNMP/bigip.syncstatus)=4`|Warning|**Manual close**: Yes|
|F5 Big-IP: Changes have been made on the device not sync|<p>Changes have been made on the device not sync to the device group, requires user intervention</p>|`last(/F5 Big-IP by SNMP/bigip.syncstatus)=2`|Warning|**Manual close**: Yes|

### LLD rule File system discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|File system discovery|<p>A table containing entries of system disk usage information.</p>|SNMP agent|bigip.disktable.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for File system discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mount point [{#PART.NAME}]: Block size|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The number of bytes in the specified partition.</p>|SNMP agent|bigip.disktable.blocksize[{#PART.NAME}]|
|Mount point [{#PART.NAME}]: Total blocks|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The number of total blocks in the specified partition.</p>|SNMP agent|bigip.disktable.totalblocks[{#PART.NAME}]|
|Mount point [{#PART.NAME}]: Free blocks|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The number of free blocks in the specified partition.</p>|SNMP agent|bigip.disktable.freeblocks[{#PART.NAME}]|
|Mount point [{#PART.NAME}]: Total nodes|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The number of total file nodes in the specified partition.</p>|SNMP agent|bigip.disktable.totalnodes[{#PART.NAME}]|
|Mount point [{#PART.NAME}]: Free nodes|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The number of free file nodes in the specified partition.</p>|SNMP agent|bigip.disktable.freenodes[{#PART.NAME}]|

### Trigger prototypes for File system discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|F5 Big-IP: Low free space in file system [{#PART.NAME}]|<p>The system is running out of free space.</p>|`last(/F5 Big-IP by SNMP/bigip.disktable.freeblocks[{#PART.NAME}])/last(/F5 Big-IP by SNMP/bigip.disktable.totalblocks[{#PART.NAME}])*100<{$BIGIP.FS.FREE.WARN.MIN:"{#PART.NAME}"}`|Warning|**Manual close**: Yes|

### LLD rule Memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Memory discovery|<p>Containing system statistics information of the memory usage</p>|SNMP agent|bigip.memory.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Host [{#HOST.ID}]: Total memory|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The total host memory in bytes for the specified host.</p>|SNMP agent|bigip.memory.total[{#HOST.ID}]|
|Host [{#HOST.ID}]: Used memory|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The host memory in bytes currently in use for the specified host.</p>|SNMP agent|bigip.memory.used[{#HOST.ID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Host [{#HOST.ID}]: Total other non-TMM memory|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The total other non-TMM memory in bytes for the specified host.</p>|SNMP agent|bigip.memory.total.other[{#HOST.ID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Host [{#HOST.ID}]: Used other non-TMM memory|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The other non-TMM memory in bytes currently in use for the specified host.</p>|SNMP agent|bigip.memory.used.other[{#HOST.ID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Host [{#HOST.ID}]: Total swap|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The total swap in bytes for the specified host.</p>|SNMP agent|bigip.memory.total.swap[{#HOST.ID}]|
|Host [{#HOST.ID}]: Used swap|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The swap in bytes currently in use for the specified host.</p>|SNMP agent|bigip.memory.used.swap[{#HOST.ID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Memory discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|F5 Big-IP: High memory utilization in host [{#HOST.ID}]|<p>The system is running out of free memory.</p>|`last(/F5 Big-IP by SNMP/bigip.memory.used[{#HOST.ID}])/last(/F5 Big-IP by SNMP/bigip.memory.total[{#HOST.ID}])*100>{$BIGIP.MEMORY.UTIL.WARN.MAX}`|Warning|**Manual close**: Yes|
|F5 Big-IP: High swap utilization in host [{#HOST.ID}]|<p>The system is running out of free swap memory.</p>|`last(/F5 Big-IP by SNMP/bigip.memory.used.swap[{#HOST.ID}])/last(/F5 Big-IP by SNMP/bigip.memory.total.swap[{#HOST.ID}])*100>{$BIGIP.SWAP.UTIL.WARN.MAX}`|Warning|**Manual close**: Yes|

### LLD rule CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU discovery|<p>A table containing entries of system CPU usage information for a system.</p>|SNMP agent|bigip.cpu.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Host [{#HOST.ID}] CPU{#CPU.ID}: User, avg 5s|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time spent by the specified processor in user context for the associated host in the last five seconds.</p>|SNMP agent|bigip.cpu.user.5s[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: Nice, avg 5s|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time spent by the specified processor running niced processes for the associated host in the last five seconds.</p>|SNMP agent|bigip.cpu.nice.5s[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: System, avg 5s|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time spent by the specified processor servicing system calls for the associated host in the last five seconds.</p>|SNMP agent|bigip.cpu.system.5s[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: Idle, avg 5s|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time spent by the specified processor doing nothing for the associated host in the last five seconds.</p>|SNMP agent|bigip.cpu.idle.5s[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: IRQ, avg 5s|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time spent by the specified processor servicing hardware interrupts for the associated host in the last five seconds.</p>|SNMP agent|bigip.cpu.irq.5s[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: Soft IRQ, avg 5s|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time spent by the specified processor servicing soft interrupts for the associated host in the last five seconds.</p>|SNMP agent|bigip.cpu.spftirq.5s[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: IO wait, avg 5s|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time spent by the specified processor waiting for external I/O to complete for the associated host in the last five seconds.</p>|SNMP agent|bigip.cpu.iowait.5s[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: Usage ratio, avg 5s|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>This is  average usage ratio of CPU for the associated host in the last five seconds. It is calculated by</p><p>(sum of deltas for user, niced, system)/(sum of deltas of user, niced, system, idle, irq, softirq, and iowait),</p><p>where each delta is the difference for each stat over the last 5-second interval;</p><p>user:sysMultiHostCpuUser5s;</p><p>niced:sysMultiHostCpuNiced5s;</p><p>stolen:sysMultiHostCpuStolen5s;</p><p>system:sysMultiHostCpuSystem5s;</p><p>idle:sysMultiHostCpuIdle5s;</p><p>irq:sysMultiHostCpuIrq5s;</p><p>iowait:sysMultiHostCpuIowait5s</p>|SNMP agent|bigip.cpu.usageratio.5s[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: User, avg 1m|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time spent by the specified processor in user context for the associated host in the last one minute.</p>|SNMP agent|bigip.cpu.user.1m[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: Nice, avg 1m|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time spent by the specified processor running niced processes for the associated host in the last one minute.</p>|SNMP agent|bigip.cpu.nice.1m[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: System, avg 1m|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time spent by the specified processor servicing system calls for the associated host in the last one minute.</p>|SNMP agent|bigip.cpu.system.1m[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: Idle, avg 1m|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time spent by the specified processor doing nothing for the associated host in the last one minute.</p>|SNMP agent|bigip.cpu.idle.1m[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: IRQ, avg 1m|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time spent by the specified processor servicing hardware interrupts for the associated host in the last one minute.</p>|SNMP agent|bigip.cpu.irq.1m[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: Soft IRQ, avg 1m|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time spent by the specified processor servicing soft interrupts for the associated host in the last one minute.</p>|SNMP agent|bigip.cpu.spftirq.1m[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: IO wait, avg 1m|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time spent by the specified processor waiting for external I/O to complete for the associated host in the last one minute.</p>|SNMP agent|bigip.cpu.iowait.1m[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: Usage ratio, avg 1m|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>This is  average usage ratio of CPU for the associated host in the last one minute. It is calculated by</p><p>(sum of deltas for user, niced, system)/(sum of deltas of user, niced, system, idle, irq, softirq, and iowait),</p><p>where each delta is the difference for each stat over the last 5-second interval;</p><p>user:sysMultiHostCpuUser1m;</p><p>niced:sysMultiHostCpuNiced1m;</p><p>stolen:sysMultiHostCpuStolen1m;</p><p>system:sysMultiHostCpuSystem1m;</p><p>idle:sysMultiHostCpuIdle1m;</p><p>irq:sysMultiHostCpuIrq1m;</p><p>iowait:sysMultiHostCpuIowait1m</p>|SNMP agent|bigip.cpu.usageratio.1m[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: User, avg 5m|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time spent by the specified processor in user context for the associated host in the last five minutes.</p>|SNMP agent|bigip.cpu.user.5m[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: Nice, avg 5m|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time spent by the specified processor running niced processes for the associated host in the last five minutes.</p>|SNMP agent|bigip.cpu.nice.5m[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: System, avg 5m|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time spent by the specified processor servicing system calls for the associated host in the last five minutes.</p>|SNMP agent|bigip.cpu.system.5m[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: Idle, avg 5m|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time spent by the specified processor doing nothing for the associated host in the last five minutes.</p>|SNMP agent|bigip.cpu.idle.5m[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: IRQ, avg 5m|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time spent by the specified processor servicing hardware interrupts for the associated host in the last five minutes.</p>|SNMP agent|bigip.cpu.irq.5m[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: Soft IRQ, avg 5m|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time spent by the specified processor servicing soft interrupts for the associated host in the last five minutes.</p>|SNMP agent|bigip.cpu.spftirq.5m[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: IO wait, avg 5m|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time spent by the specified processor waiting for external I/O to complete for the associated host in the last five minutes.</p>|SNMP agent|bigip.cpu.iowait.5m[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: Usage ratio, avg 5m|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>This is  average usage ratio of CPU for the associated host in the last five minutes. It is calculated by</p><p>(sum of deltas for user, niced, system)/(sum of deltas of user, niced, system, idle, irq, softirq, and iowait),</p><p>where each delta is the difference for each stat over the last 5-second interval;</p><p>user:sysMultiHostCpuUser5m;</p><p>niced:sysMultiHostCpuNiced5m;</p><p>stolen:sysMultiHostCpuStolen5m;</p><p>system:sysMultiHostCpuSystem5m;</p><p>idle:sysMultiHostCpuIdle5m;</p><p>irq:sysMultiHostCpuIrq5m;</p><p>iowait:sysMultiHostCpuIowait5m</p>|SNMP agent|bigip.cpu.usageratio.5m[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: Stolen, avg 1s)|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time 'stolen' from the specified processor for the associated host in the last five seconds.</p>|SNMP agent|bigip.cpu.stolen.1s[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: Stolen, avg 1m|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time 'stolen' from the specified processor for the associated host in the last one minute.</p>|SNMP agent|bigip.cpu.stolen.1m[{#HOST.ID},{#CPU.ID}]|
|Host [{#HOST.ID}] CPU{#CPU.ID}: Stolen, avg 5m|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The average time 'stolen' from the specified processor for the associated host in the last five minutes.</p>|SNMP agent|bigip.cpu.stolen.5m[{#HOST.ID},{#CPU.ID}]|

### Trigger prototypes for CPU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|F5 Big-IP: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`last(/F5 Big-IP by SNMP/bigip.cpu.usageratio.5m[{#HOST.ID},{#CPU.ID}])>{$BIGIP.CPU.UTIL.WARN.MAX}`|Warning|**Manual close**: Yes|

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>A table containing statistic information of the interfaces on the device.</p>|SNMP agent|bigip.net.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface [{#IF.NAME}]: Incoming packet, rate|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The rate of packets received on this interface.</p>|SNMP agent|bigip.net.in.pkts.rate[{#IF.NAME}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface [{#IF.NAME}]: Incoming traffic, rate|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The rate of bytes received on this interface.</p>|SNMP agent|bigip.net.in.bytes.rate[{#IF.NAME}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface [{#IF.NAME}]: Outgoing packet, rate|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The rate of packets transmitted out of the specified interface.</p>|SNMP agent|bigip.net.out.pkts.rate[{#IF.NAME}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface [{#IF.NAME}]: Outgoing traffic, rate|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The rate of bytes transmitted out of the specified interface.</p>|SNMP agent|bigip.net.out.bytes.rate[{#IF.NAME}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface [{#IF.NAME}]: Incoming multicast packet, rate|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The rate of multicast packets received on this interface.</p>|SNMP agent|bigip.net.in.multicast.rate[{#IF.NAME}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface [{#IF.NAME}]: Outgoing multicast packet, rate|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The rate of multicast packets transmitted out of the specified interface.</p>|SNMP agent|bigip.net.out.multicast.rate[{#IF.NAME}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface [{#IF.NAME}]: Incoming packet error|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The number of received packets that are either undersized,</p><p>oversized, or have FCS errors by the specified interface.</p>|SNMP agent|bigip.net.in.error[{#IF.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface [{#IF.NAME}]: Outgoing packet error|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The number of excessive collisions, incremented for each</p><p>frame that experienced 16 collisions during transmission and</p><p>was aborted on the specified interface.</p>|SNMP agent|bigip.net.out.error[{#IF.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface [{#IF.NAME}]: Incoming packet drops|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The number of packets dropped on ingress for various reasons on the specified interface.</p>|SNMP agent|bigip.net.in.drops[{#IF.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface [{#IF.NAME}]: Outgoing packet drops|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The number of packets aged out or with excessive transmission</p><p>delays due to multiple deferrals on the specified interface.</p>|SNMP agent|bigip.net.out.drops[{#IF.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface [{#IF.NAME}]: Collisions|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The number of collisions on the specified interface, incremented by the</p><p>number of collisions experienced during transmissions of a frame</p>|SNMP agent|bigip.net.collisions[{#IF.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface [{#IF.NAME}]: Incoming QnQ packet, rate|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The rate of double tagged packets received on the specified interface.</p>|SNMP agent|bigip.net.in.qq.rate[{#IF.NAME}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface [{#IF.NAME}]: Outgoing QnQ packet, rate|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The rate of double tagged packets transmitted out of the specified interface.</p>|SNMP agent|bigip.net.out.qq.rate[{#IF.NAME}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface [{#IF.NAME}]: Pause state|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The pause state of the specified interface.</p><p>none - no pause;</p><p>txrx - pause all data flow;</p><p>tx - pause outgoing data flow;</p><p>rx - pause incoming data flow.</p>|SNMP agent|bigip.net.pause[{#IF.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|F5 Big-IP: There are errors on the network interface||`last(/F5 Big-IP by SNMP/bigip.net.in.error[{#IF.NAME}])>last(/F5 Big-IP by SNMP/bigip.net.in.error[{#IF.NAME}],#2) or last(/F5 Big-IP by SNMP/bigip.net.out.error[{#IF.NAME}])>last(/F5 Big-IP by SNMP/bigip.net.out.error[{#IF.NAME}],#2)`|Average|**Manual close**: Yes|

### LLD rule Chassis fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Chassis fan discovery|<p>A table containing information of chassis fan status of the system</p>|SNMP agent|bigip.chassis.fan.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Chassis fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN [{#FAN.INDEX}]: Status|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The status of the indexed chassis fan on the system.,</p><p>This is only supported for the platform where</p><p>the sensor data is available.</p><p>Possible values: 0 - bad, 1 - good, 2 - notpresent.</p>|SNMP agent|bigip.chassis.fan.status[{#FAN.INDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|FAN [{#FAN.INDEX}]: Speed|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The actual speed of the indexed chassis fan on the system.</p><p>This is only supported for the platform where the actual</p><p>fan speed data is available.</p><p>'0' means fan speed is unavailable while the associated chassis status is good.</p>|SNMP agent|bigip.chassis.fan.speed[{#FAN.INDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Chassis fan discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|F5 Big-IP: Fan[{#FAN.INDEX}] is in critical state|<p>Please check the fan unit</p>|`last(/F5 Big-IP by SNMP/bigip.chassis.fan.status[{#FAN.INDEX}])=0`|Average|**Manual close**: Yes|
|F5 Big-IP: Fan[{#FAN.INDEX}] is not present|<p>Please check the fan unit</p>|`last(/F5 Big-IP by SNMP/bigip.chassis.fan.status[{#FAN.INDEX}])=2`|Info|**Manual close**: Yes|

### LLD rule Chassis power supply discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Chassis power supply discovery|<p>A table containing information of chassis power supply status of the system.</p>|SNMP agent|bigip.chassis.power.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Chassis power supply discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Power supply [{#POWER.INDEX}]: Status|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The status of the indexed power supply on the system.,</p><p>This is only supported for the platform where</p><p>the sensor data is available.</p><p>Possible values: 0 - bad, 1 - good, 2 - notpresent.</p>|SNMP agent|bigip.chassis.power.status[{#POWER.INDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Chassis power supply discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|F5 Big-IP: Power supply [{#POWER.INDEX}] is in critical state|<p>Please check the power supply unit</p>|`last(/F5 Big-IP by SNMP/bigip.chassis.power.status[{#POWER.INDEX}])=0`|High|**Manual close**: Yes|
|F5 Big-IP: Power supply [{#POWER.INDEX}] is not present|<p>Please check the power supply unit</p>|`last(/F5 Big-IP by SNMP/bigip.chassis.power.status[{#POWER.INDEX}])=2`|Info|**Manual close**: Yes|

### LLD rule Chassis temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Chassis temperature discovery|<p>A table containing information of chassis temperature of the system</p>|SNMP agent|bigip.chassis.temp.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Chassis temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Sensor [{#TEMP.INDEX}]: Temperature|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The chassis temperature (in Celsius) of the indexed sensor on the system.,</p><p>This is only supported for the platform where</p><p>the sensor data is available.</p>|SNMP agent|bigip.chassis.temp.value[{#TEMP.INDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Chassis temperature discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|F5 Big-IP: Chassis temperature||`last(/F5 Big-IP by SNMP/bigip.chassis.temp.value[{#TEMP.INDEX}])>{$BIGIP.TEMP.HIGH}`|High||
|F5 Big-IP: Chassis temperature||`last(/F5 Big-IP by SNMP/bigip.chassis.temp.value[{#TEMP.INDEX}])>{$BIGIP.TEMP.WARN}`|Warning|**Depends on**:<br><ul><li>F5 Big-IP: Chassis temperature</li></ul>|

### LLD rule Blade temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Blade temperature discovery|<p>Containing information of blade temperature of the system</p>|SNMP agent|bigip.blade.temp.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Blade temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Sensor [{#SLOT.INDEX}:{#TEMP.INDEX}]: Temperature|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>Location: {#TEMP.LOCATION}</p><p>The blade temperature (in Celsius) of the indexed sensor on the system.,</p><p>This is only supported for the platform where</p><p>the sensor data is available.</p>|SNMP agent|bigip.blade.temp.value[{#SLOT.INDEX},{#TEMP.INDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Blade voltage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Blade voltage discovery|<p>A table containing information of blade voltage of the system.</p>|SNMP agent|bigip.blade.voltage.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Blade voltage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Voltage [{#VOLT.INDEX}]: Value|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The blade voltage (in V) of the indexed sensor on the system.,</p><p>This is only supported for the platform where</p><p>the sensor data is available.</p>|SNMP agent|bigip.blade.voltage.value[{#VOLT.INDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Voltage [{#VOLT.INDEX}]: Slot|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The chassis slot number, if applicable.</p>|SNMP agent|bigip.blade.voltage.slot[{#VOLT.INDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule CPU sensor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU sensor discovery|<p>A table containing information of CPU sensor status on the system.</p>|SNMP agent|bigip.cpu.sensor.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for CPU sensor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Sensor [{#CPU.SENSOR.SLOT}:{#CPU.SENSOR.INDEX}]: Temperature|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>CPU: {#CPU.SENSOR.NAME}</p><p>The temperature of the indexed CPU on the system.</p><p>This is only supported for the platform where</p><p>the sensor data is available.</p>|SNMP agent|bigip.cpu.sensor.temperature[{#CPU.SENSOR.SLOT},{#CPU.SENSOR.INDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Sensor [{#CPU.SENSOR.SLOT}:{#CPU.SENSOR.INDEX}]: FAN speed|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>CPU: {#CPU.SENSOR.NAME}</p><p>The fan speed (in RPM) of the indexed CPU on the system.,</p><p>This is only supported for the platform where</p><p>the sensor data is available.</p>|SNMP agent|bigip.cpu.sensor.fan[{#CPU.SENSOR.SLOT},{#CPU.SENSOR.INDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Sensor [{#CPU.SENSOR.SLOT}:{#CPU.SENSOR.INDEX}]: Name|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>Identifier for the CPU.</p>|SNMP agent|bigip.cpu.sensor.name[{#CPU.SENSOR.SLOT},{#CPU.SENSOR.INDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Module discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Module discovery|<p>Resource allocation information about modules on the system</p>|SNMP agent|bigip.module.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Module discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Module [{#MODULE.NAME}]: Provision level|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The provisioning level indicates how the systems resources</p><p>are distributed amongst the modules</p><p>Possible values: 1 - none, 2 - minimum, 3 - nominal, 4 - dedicated, 5 - custom.</p>|SNMP agent|bigip.module.provision.level[{#MODULE.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Module [{#MODULE.NAME}]: Memory ratio|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The ratio of available memory to allocate. Only valid if level is 'custom'</p>|SNMP agent|bigip.module.memory.ratio[{#MODULE.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Module [{#MODULE.NAME}]: CPU ratio|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The ratio of CPU to allocate to this module. Only valid if level is 'custom'</p>|SNMP agent|bigip.module.cpu.ratio[{#MODULE.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Module [{#MODULE.NAME}]: Disk ratio|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The ratio of available disk space to allocate to this module. Only valid if level is 'custom'</p>|SNMP agent|bigip.module.disk.ratio[{#MODULE.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Certificate discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Certificate discovery|<p>A table containing certificate configuration.</p>|SNMP agent|bigip.cert.discovery|

### Item prototypes for Certificate discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Certificate [{#CERT.NAME}]: Expiration date|<p>MIB: F5-BIGIP-SYSTEM-MIB</p><p>The expiration date of the certificate in unix time.</p>|SNMP agent|bigip.cert.expiration.date[{#CERT.NAME}]|

### Trigger prototypes for Certificate discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|F5 Big-IP: Certificate expires ({#CERT.NAME})|<p>Please check certificate</p>|`last(/F5 Big-IP by SNMP/bigip.cert.expiration.date[{#CERT.NAME}]) - 86400 * {$BIGIP.CERT.MIN} < now()`|Warning|**Manual close**: Yes|

### LLD rule Virtual server discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Virtual server discovery|<p>A table containing information of virtual servers.</p>|SNMP agent|bigip.virtual_server.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Virtual server discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Virtual server [{#VSERVER.NAME}]: Incoming packet, rate|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The rate of packets received by the specified virtual server from client-side.</p>|SNMP agent|bigip.vserver.net.in.pkts.rate[{#VSERVER.NAME}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Virtual server [{#VSERVER.NAME}]: Incoming traffic, rate|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The rate of bytes received by the specified virtual server from client-side.</p>|SNMP agent|bigip.vserver.net.in.bytes.rate[{#VSERVER.NAME}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Virtual server [{#VSERVER.NAME}]: Outgoing packet, rate|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The rate of packets sent to client-side from the specified virtual server.</p>|SNMP agent|bigip.vserver.net.out.pkts.rate[{#VSERVER.NAME}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Virtual server [{#VSERVER.NAME}]: Outgoing traffic, rate|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The rate of bytes sent to client-side from the specified virtual server.</p>|SNMP agent|bigip.vserver.net.out.bytes.rate[{#VSERVER.NAME}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Virtual server [{#VSERVER.NAME}]: Current connections|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The current connections from client-side to the specified virtual server.</p>|SNMP agent|bigip.vserver.net.conn[{#VSERVER.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Virtual server [{#VSERVER.NAME}]: Usage ratio, avg 5s|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The percentage of time Virtual Server was busy over the last 5 seconds.</p>|SNMP agent|bigip.vserver.usage.5s[{#VSERVER.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Virtual server [{#VSERVER.NAME}]: Usage ratio, avg 1m|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The percentage of time Virtual Server was busy over the last 1 minute.</p>|SNMP agent|bigip.vserver.usage.1m[{#VSERVER.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Virtual server [{#VSERVER.NAME}]: Usage ratio, avg 5m|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The percentage of time Virtual Server was busy over the last 5 minutes.</p>|SNMP agent|bigip.vserver.usage.5m[{#VSERVER.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Virtual server [{#VSERVER.NAME}]: Connections hit a rate limit|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The last recorded value for the number of connections to the virtual server when connections hit a rate limit;</p><p>this calculation is only maintained if rate limiting is configured for the service.</p>|SNMP agent|bigip.vserver.overlimit[{#VSERVER.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Virtual server [{#VSERVER.NAME}]: Duration of exceeding rate limit|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>Duration of time in seconds the specified virtual server has exceeded the configured connection rate limit.</p>|SNMP agent|bigip.vserver.overtime[{#VSERVER.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Node discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node discovery|<p>A table containing statistic information of node addresses.</p>|SNMP agent|bigip.node.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Node discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NODE.NAME}]: Incoming packet, rate|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The rate of packets received by the specified node address from server-side.</p>|SNMP agent|bigip.node.net.in.pkts.rate[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: Incoming traffic, rate|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The rate of bytes received by the specified node address from server-side.</p>|SNMP agent|bigip.node.net.in.bytes.rate[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: Outgoing packet, rate|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The rate of packets sent to server-side from the specified node address.</p>|SNMP agent|bigip.node.net.out.pkts.rate[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: Outgoing traffic, rate|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The rate of bytes sent to server-side from the specified node address.</p>|SNMP agent|bigip.node.net.out.bytes.rate[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: Current connections|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The current connections from server-side to the specified node address.</p>|SNMP agent|bigip.node.net.conn[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: Current sessions|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The number of current sessions going through the specified node address.</p>|SNMP agent|bigip.node.net.sessions[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: Connections hit a rate limit|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The last recorded value for the number of connections to the node address when connections hit a rate limit;</p><p>this calculation is only maintained if rate limiting is configured for the node.</p>|SNMP agent|bigip.node.overlimit[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: Duration of exceeding rate limit|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>Duration of time in seconds the specified node address has exceeded the</p><p>configured connection rate limit.</p>|SNMP agent|bigip.node.overtime[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Pool discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Pool discovery|<p>A table containing statistic information of pools.</p>|SNMP agent|bigip.pool.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Pool discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Pool [{#POOL.NAME}]: Incoming packet, rate|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The rate of packets received by the specified pool from server-side.</p>|SNMP agent|bigip.pool.net.in.pkts.rate[{#POOL.NAME}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Pool [{#POOL.NAME}]: Incoming traffic, rate|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The rate of bytes received by the specified pool from server-side.</p>|SNMP agent|bigip.pool.net.in.bytes.rate[{#POOL.NAME}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Pool [{#POOL.NAME}]: Outgoing packet, rate|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The rate of packets sent to server-side from the specified pool.</p>|SNMP agent|bigip.pool.net.out.pkts.rate[{#POOL.NAME}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Pool [{#POOL.NAME}]: Outgoing traffic, rate|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The rate of bytes sent to server-side from the specified pool.</p>|SNMP agent|bigip.pool.net.out.bytes.rate[{#POOL.NAME}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Pool [{#POOL.NAME}]: Current connections|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The current connections from server-side to the specified pool.</p>|SNMP agent|bigip.pool.net.conn[{#POOL.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Pool [{#POOL.NAME}]: Current sessions|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The number of current sessions going through the specified pool.</p>|SNMP agent|bigip.pool.net.sessions[{#POOL.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Pool [{#POOL.NAME}]: Queue|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>Number of connections currently in queue, sum.</p>|SNMP agent|bigip.pool.queue[{#POOL.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Pool [{#POOL.NAME}]: Age of the oldest queue entry|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>Age of the oldest queue entry, max.</p>|SNMP agent|bigip.pool.queue.age[{#POOL.NAME}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Pool [{#POOL.NAME}]: Status available|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>none(0),</p><p>green(1),</p><p>tyellow(2),</p><p>tred(3),</p><p>tblue(4),</p><p>tgrey(5)</p>|SNMP agent|bigip.pool.available[{#POOL.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Pool [{#POOL.NAME}]: Status enabled|<p>MIB: F5-BIGIP-LOCAL-MIB</p><p>The activity status of the specified pool, as specified by the user.</p><p>none(0),</p><p>enabled(1),</p><p>disabled(2),</p><p>disabledbyparent(3)</p>|SNMP agent|bigip.pool.enabled[{#POOL.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Pool discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|F5 Big-IP: Pool {#POOL.NAME} is not available in some capacity: {ITEM.VALUE1}||`count(/F5 Big-IP by SNMP/bigip.pool.available[{#POOL.NAME}],120m,"ne","1")>20`|Average|**Depends on**:<br><ul><li>F5 Big-IP: Pool {#POOL.NAME} is not enabled in some capacity: {ITEM.VALUE1}</li></ul>|
|F5 Big-IP: Pool {#POOL.NAME} is not enabled in some capacity: {ITEM.VALUE1}||`count(/F5 Big-IP by SNMP/bigip.pool.enabled[{#POOL.NAME}],120m,"ne","1")>4`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

