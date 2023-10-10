
# FreeBSD by Zabbix agent

## Overview

Official FreeBSD template. Requires agent of Zabbix 7.0 and newer.


## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- FreeBSD

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Install Zabbix agent on FreeBSD according to Zabbix documentation.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AGENT.TIMEOUT}|<p>The timeout after which the agent is considered unavailable. It works only for the agents reachable from Zabbix server/proxy (in passive mode).</p>|`3m`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FreeBSD: Maximum number of opened files|<p>It could be increased by using the sysctl utility or modifying the file /etc/sysctl.conf.</p>|Zabbix agent|kernel.maxfiles|
|FreeBSD: Maximum number of processes|<p>It could be increased by using the sysctl utility or modifying the file /etc/sysctl.conf.</p>|Zabbix agent|kernel.maxproc|
|FreeBSD: Number of running processes|<p>The number of processes in a running state.</p>|Zabbix agent|proc.num[,,run]|
|FreeBSD: Number of processes|<p>The total number of processes in any state.</p>|Zabbix agent|proc.num[]|
|FreeBSD: Host boot time||Zabbix agent|system.boottime|
|FreeBSD: Interrupts per second||Zabbix agent|system.cpu.intr<p>**Preprocessing**</p><ul><li>Change per second: </li></ul>|
|FreeBSD: Processor load (1 min average per core)|<p>The processor load is calculated as the system CPU load divided by the number of CPU cores.</p>|Zabbix agent|system.cpu.load[percpu,avg1]|
|FreeBSD: Processor load (5 min average per core)|<p>The processor load is calculated as the system CPU load divided by the number of CPU cores.</p>|Zabbix agent|system.cpu.load[percpu,avg5]|
|FreeBSD: Processor load (15 min average per core)|<p>The processor load is calculated as the system CPU load divided by the number of CPU cores.</p>|Zabbix agent|system.cpu.load[percpu,avg15]|
|FreeBSD: Context switches per second||Zabbix agent|system.cpu.switches<p>**Preprocessing**</p><ul><li>Change per second: </li></ul>|
|FreeBSD: CPU idle time|<p>The time the CPU has spent doing nothing.</p>|Zabbix agent|system.cpu.util[,idle]|
|FreeBSD: CPU interrupt time|<p>The amount of time the CPU has been servicing hardware interrupts.</p>|Zabbix agent|system.cpu.util[,interrupt]|
|FreeBSD: CPU nice time|<p>The time the CPU has spent running users' processes that have been niced.</p>|Zabbix agent|system.cpu.util[,nice]|
|FreeBSD: CPU system time|<p>The time the CPU has spent running the kernel and its processes.</p>|Zabbix agent|system.cpu.util[,system]|
|FreeBSD: CPU user time|<p>The time the CPU has spent running users' processes that are not niced.</p>|Zabbix agent|system.cpu.util[,user]|
|FreeBSD: Host name|<p>A host name of the system.</p>|Zabbix agent|system.hostname<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|FreeBSD: Host local time||Zabbix agent|system.localtime|
|FreeBSD: Free swap space||Zabbix agent|system.swap.size[,free]|
|FreeBSD: Free swap space in %||Zabbix agent|system.swap.size[,pfree]|
|FreeBSD: Total swap space||Zabbix agent|system.swap.size[,total]|
|FreeBSD: System information|<p>The information as normally returned by the 'uname -a'.</p>|Zabbix agent|system.uname<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|FreeBSD: System uptime||Zabbix agent|system.uptime|
|FreeBSD: Number of logged in users|<p>The number of users who are currently logged in.</p>|Zabbix agent|system.users.num|
|FreeBSD: Checksum of /etc/passwd||Zabbix agent|vfs.file.cksum[/etc/passwd,sha256]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|FreeBSD: Available memory|<p>The available memory is defined as free+cached+buffers memory.</p>|Zabbix agent|vm.memory.size[available]|
|FreeBSD: Total memory||Zabbix agent|vm.memory.size[total]|
|FreeBSD: Version of Zabbix agent running||Zabbix agent|agent.version<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|FreeBSD: Host name of Zabbix agent running||Zabbix agent|agent.hostname<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|FreeBSD: Zabbix agent ping|<p>The agent always returns 1 for this item. It could be used in combination with nodata() for the availability check.</p>|Zabbix agent|agent.ping|
|FreeBSD: Zabbix agent availability|<p>Monitoring the availability status of the agent.</p>|Zabbix internal|zabbix[host,agent,available]|
|FreeBSD: Get filesystems|<p>The `vfs.fs.get` key acquires raw information set about the file systems. Later to be extracted by preprocessing in dependent items.</p>|Zabbix agent|vfs.fs.get|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|FreeBSD: Configured max number of opened files is too low on {HOST.NAME}||`last(/FreeBSD by Zabbix agent/kernel.maxfiles)<1024`|Info||
|FreeBSD: Configured max number of processes is too low on {HOST.NAME}||`last(/FreeBSD by Zabbix agent/kernel.maxproc)<256`|Info||
|FreeBSD: Too many processes running on {HOST.NAME}||`avg(/FreeBSD by Zabbix agent/proc.num[,,run],5m)>30`|Warning||
|FreeBSD: Too many processes on {HOST.NAME}||`avg(/FreeBSD by Zabbix agent/proc.num[],5m)>300`|Warning||
|FreeBSD: Processor load is too high on {HOST.NAME}||`avg(/FreeBSD by Zabbix agent/system.cpu.load[percpu,avg1],5m)>5`|Warning||
|FreeBSD: Hostname was changed on {HOST.NAME}||`last(/FreeBSD by Zabbix agent/system.hostname,#1)<>last(/FreeBSD by Zabbix agent/system.hostname,#2)`|Info||
|FreeBSD: Lack of free swap space on {HOST.NAME}|<p>It probably means that the systems requires more physical memory.</p>|`last(/FreeBSD by Zabbix agent/system.swap.size[,pfree])<50`|Warning||
|FreeBSD: Host information was changed on {HOST.NAME}||`last(/FreeBSD by Zabbix agent/system.uname,#1)<>last(/FreeBSD by Zabbix agent/system.uname,#2)`|Info||
|FreeBSD: {HOST.NAME} has just been restarted||`change(/FreeBSD by Zabbix agent/system.uptime)<0`|Info||
|FreeBSD: /etc/passwd has been changed on {HOST.NAME}||`last(/FreeBSD by Zabbix agent/vfs.file.cksum[/etc/passwd,sha256],#1)<>last(/FreeBSD by Zabbix agent/vfs.file.cksum[/etc/passwd,sha256],#2)`|Warning||
|FreeBSD: Lack of available memory on server {HOST.NAME}||`last(/FreeBSD by Zabbix agent/vm.memory.size[available])<20M`|Average||
|FreeBSD: Zabbix agent is not available|<p>For passive checks only the availability of the agents and a host is used with {$AGENT.TIMEOUT} as the time threshold.</p>|`max(/FreeBSD by Zabbix agent/zabbix[host,agent,available],{$AGENT.TIMEOUT})=0`|Average|**Manual close**: Yes|

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
|{#FSNAME}: Filesystem is read-only|<p>The filesystem is mounted as read-only. It is available only for Zabbix agents 6.4 and higher.</p>|Dependent item|vfs.fs.dependent[{#FSNAME},readonly]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.options`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Regular expression: `(?:^|,)read-only\b 1`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|{#FSNAME}: Free inodes, %||Dependent item|vfs.fs.dependent.inode[{#FSNAME},pfree]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.inodes.pfree`</p></li></ul>|
|{#FSNAME}: Free disk space||Dependent item|vfs.fs.dependent.size[{#FSNAME},free]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.free`</p></li></ul>|
|{#FSNAME}: Free disk space, %||Dependent item|vfs.fs.dependent.size[{#FSNAME},pfree]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.pfree`</p></li></ul>|
|{#FSNAME}: Total disk space||Dependent item|vfs.fs.dependent.size[{#FSNAME},total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.total`</p></li></ul>|
|{#FSNAME}: Used disk space||Dependent item|vfs.fs.dependent.size[{#FSNAME},used]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.used`</p></li></ul>|

### Trigger prototypes for Mounted filesystem discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#FSNAME}: Filesystem has become read-only|<p>The filesystem has become read-only. A possible reason is an I/O error. It is available only for Zabbix agents 6.4 and higher.</p>|`last(/FreeBSD by Zabbix agent/vfs.fs.dependent[{#FSNAME},readonly],#2)=0 and last(/FreeBSD by Zabbix agent/vfs.fs.dependent[{#FSNAME},readonly])=1`|Average|**Manual close**: Yes|
|{#FSNAME}: Free inodes is less than 20%||`last(/FreeBSD by Zabbix agent/vfs.fs.dependent.inode[{#FSNAME},pfree])<20`|Warning||
|{#FSNAME}: Free disk space is less than 20%||`last(/FreeBSD by Zabbix agent/vfs.fs.dependent.size[{#FSNAME},pfree])<20`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

