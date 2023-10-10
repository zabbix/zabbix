
# AIX by Zabbix agent

## Overview

It is an official AIX template. It requires Zabbix agent 7.0 or newer.


## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- AIX 6.1

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Install Zabbix agent on the AIX OS according to Zabbix documentation.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AGENT.TIMEOUT}|<p>The timeout after which agent is considered unavailable. It works only for the agents reachable from Zabbix server/proxy (in passive mode).</p>|`3m`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|AIX: Number of running processes|<p>The number of processes in a running state.</p>|Zabbix agent|proc.num[,,run]|
|AIX: Number of processes|<p>The total number of processes in any state.</p>|Zabbix agent|proc.num[]|
|AIX: Interrupts per second||Zabbix agent|system.cpu.intr<p>**Preprocessing**</p><ul><li>Change per second: </li></ul>|
|AIX: Processor load (1 min average per core)|<p>The processor load is calculated as the system CPU load divided by the number of CPU cores.</p>|Zabbix agent|system.cpu.load[percpu,avg1]|
|AIX: Processor load (5 min average per core)|<p>The processor load is calculated as the system CPU load divided by the number of CPU cores.</p>|Zabbix agent|system.cpu.load[percpu,avg5]|
|AIX: Processor load (15 min average per core)|<p>The processor load is calculated as the system CPU load divided by the number of CPU cores.</p>|Zabbix agent|system.cpu.load[percpu,avg15]|
|AIX: Context switches per second||Zabbix agent|system.cpu.switches<p>**Preprocessing**</p><ul><li>Change per second: </li></ul>|
|AIX: Host name|<p>A host name of the system.</p>|Zabbix agent|system.hostname<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|AIX: Host local time||Zabbix agent|system.localtime|
|AIX: CPU available physical processors in the shared pool||Zabbix agent|system.stat[cpu,app]|
|AIX: CPU entitled capacity consumed||Zabbix agent|system.stat[cpu,ec]|
|AIX: CPU idle time||Zabbix agent|system.stat[cpu,id]|
|AIX: CPU logical processor utilization||Zabbix agent|system.stat[cpu,lbusy]|
|AIX: CPU number of physical processors consumed||Zabbix agent|system.stat[cpu,pc]|
|AIX: CPU system time||Zabbix agent|system.stat[cpu,sy]|
|AIX: CPU user time||Zabbix agent|system.stat[cpu,us]|
|AIX: CPU iowait time||Zabbix agent|system.stat[cpu,wa]|
|AIX: Amount of data transferred||Zabbix agent|system.stat[disk,bps]|
|AIX: Number of transfers||Zabbix agent|system.stat[disk,tps]|
|AIX: Processor units is entitled to receive||Zabbix agent|system.stat[ent]|
|AIX: Kernel thread context switches||Zabbix agent|system.stat[faults,cs]|
|AIX: Device interrupts||Zabbix agent|system.stat[faults,in]|
|AIX: System calls||Zabbix agent|system.stat[faults,sy]|
|AIX: Length of the swap queue||Zabbix agent|system.stat[kthr,b]|
|AIX: Length of the run queue||Zabbix agent|system.stat[kthr,r]|
|AIX: Active virtual pages||Zabbix agent|system.stat[memory,avm]|
|AIX: Free real memory||Zabbix agent|system.stat[memory,fre]|
|AIX: File page-ins per second||Zabbix agent|system.stat[page,fi]|
|AIX: File page-outs per second||Zabbix agent|system.stat[page,fo]|
|AIX: Pages freed (page replacement)||Zabbix agent|system.stat[page,fr]|
|AIX: Pages paged in from paging space||Zabbix agent|system.stat[page,pi]|
|AIX: Pages paged out to paging space||Zabbix agent|system.stat[page,po]|
|AIX: Pages scanned by page-replacement algorithm||Zabbix agent|system.stat[page,sr]|
|AIX: System information|<p>The information as normally returned by the 'uname -a'.</p>|Zabbix agent|system.uname<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|AIX: System uptime||Zabbix agent|system.uptime|
|AIX: Number of logged in users|<p>The number of users who are currently logged in.</p>|Zabbix agent|system.users.num|
|AIX: Checksum of /etc/passwd||Zabbix agent|vfs.file.cksum[/etc/passwd,sha256]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|AIX: Available memory|<p>The available memory is defined as free+cached+buffers memory.</p>|Zabbix agent|vm.memory.size[available]|
|AIX: Total memory||Zabbix agent|vm.memory.size[total]|
|AIX: Version of Zabbix agent running||Zabbix agent|agent.version<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|AIX: Host name of Zabbix agent running||Zabbix agent|agent.hostname<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|AIX: Zabbix agent ping|<p>The agent always returns 1 for this item. It could be used in combination with nodata() for the availability check.</p>|Zabbix agent|agent.ping|
|AIX: Zabbix agent availability|<p>Monitoring the availability status of the agent.</p>|Zabbix internal|zabbix[host,agent,available]|
|AIX: Get filesystems|<p>The `vfs.fs.get` key acquires raw information set about the file systems. Later to be extracted by preprocessing in dependent items.</p>|Zabbix agent|vfs.fs.get|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|AIX: Too many processes running||`avg(/AIX by Zabbix agent/proc.num[,,run],5m)>30`|Warning||
|AIX: Too many processes||`avg(/AIX by Zabbix agent/proc.num[],5m)>300`|Warning||
|AIX: Processor load is too high||`avg(/AIX by Zabbix agent/system.cpu.load[percpu,avg1],5m)>5`|Warning||
|AIX: Hostname was changed||`last(/AIX by Zabbix agent/system.hostname,#1)<>last(/AIX by Zabbix agent/system.hostname,#2)`|Info||
|AIX: Disk I/O is overloaded|<p>The OS spends significant time waiting for the I/O (input/output) operations. It could be an indicator of performance issues with the storage system.</p>|`avg(/AIX by Zabbix agent/system.stat[cpu,wa],5m)>20`|Warning||
|AIX: Host information was changed||`last(/AIX by Zabbix agent/system.uname,#1)<>last(/AIX by Zabbix agent/system.uname,#2)`|Info||
|AIX: Server has just been restarted||`change(/AIX by Zabbix agent/system.uptime)<0`|Info||
|AIX: /etc/passwd has been changed||`last(/AIX by Zabbix agent/vfs.file.cksum[/etc/passwd,sha256],#1)<>last(/AIX by Zabbix agent/vfs.file.cksum[/etc/passwd,sha256],#2)`|Warning||
|AIX: Lack of available memory on server||`last(/AIX by Zabbix agent/vm.memory.size[available])<20M`|Average||
|AIX: Zabbix agent is not available|<p>For passive checks only the availability of the agent(s) and a host is used with {$AGENT.TIMEOUT} as the time threshold.</p>|`max(/AIX by Zabbix agent/zabbix[host,agent,available],{$AGENT.TIMEOUT})=0`|Average|**Manual close**: Yes|

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>The discovery of network interfaces as defined in the global regular expression "Network interfaces for discovery".</p>|Zabbix agent|net.if.discovery|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}: Incoming network traffic||Zabbix agent|net.if.in[{#IFNAME}]<p>**Preprocessing**</p><ul><li>Change per second: </li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}: Outgoing network traffic||Zabbix agent|net.if.out[{#IFNAME}]<p>**Preprocessing**</p><ul><li>Change per second: </li><li><p>Custom multiplier: `8`</p></li></ul>|

### LLD rule Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mounted filesystem discovery|<p>The discovery of different types of file systems as defined in the global regular expression "File systems for discovery".</p>|Dependent item|vfs.fs.dependent.discovery|

### Item prototypes for Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#FSNAME}: Get filesystem data||Dependent item|vfs.fs.dependent[{#FSNAME},data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.fsname=='{#FSNAME}')].first()`</p></li></ul>|
|{#FSNAME}: Filesystem is read-only|<p>The filesystem is mounted as read-only. It is available only for Zabbix agents 6.4 and higher.</p>|Dependent item|vfs.fs.dependent[{#FSNAME},readonly]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.options`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Regular expression: `(?:^|,)ro\b 1`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|{#FSNAME}: Free inodes, %||Dependent item|vfs.fs.dependent.inode[{#FSNAME},pfree]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.inodes.pfree`</p></li></ul>|
|{#FSNAME}: Free disk space||Dependent item|vfs.fs.dependent.size[{#FSNAME},free]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.free`</p></li></ul>|
|{#FSNAME}: Free disk space, %||Dependent item|vfs.fs.dependent.size[{#FSNAME},pfree]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.pfree`</p></li></ul>|
|{#FSNAME}: Total disk space||Dependent item|vfs.fs.dependent.size[{#FSNAME},total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.total`</p></li></ul>|
|{#FSNAME}: Used disk space||Dependent item|vfs.fs.dependent.size[{#FSNAME},used]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.used`</p></li></ul>|

### Trigger prototypes for Mounted filesystem discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#FSNAME}: Filesystem has become read-only|<p>The filesystem has become read-only. A possible reason is an I/O error. It is available only for Zabbix agents 6.4 and higher.</p>|`last(/AIX by Zabbix agent/vfs.fs.dependent[{#FSNAME},readonly],#2)=0 and last(/AIX by Zabbix agent/vfs.fs.dependent[{#FSNAME},readonly])=1`|Average|**Manual close**: Yes|
|{#FSNAME}: Free inodes is less than 20%||`last(/AIX by Zabbix agent/vfs.fs.dependent.inode[{#FSNAME},pfree])<20`|Warning||
|{#FSNAME}: Free disk space is less than 20%||`last(/AIX by Zabbix agent/vfs.fs.dependent.size[{#FSNAME},pfree])<20`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

