
# FreeBSD by Zabbix agent

## Overview

Official FreeBSD template. Requires agent of Zabbix 6.4 and newer.


## Requirements

Zabbix version: 6.4 and higher.

## Tested versions

This template has been tested on:
- FreeBSD

## Configuration

> Zabbix should be configured according to instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box) section.

## Setup

Install Zabbix agent on FreeBSD according to Zabbix documentation.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AGENT.TIMEOUT}|<p>The timeout after which the agent is considered unavailable. It works only for the agents reachable from Zabbix server/proxy (in passive mode).</p>|`3m`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Maximum number of opened files|<p>It could be increased by using the sysctl utility or modifying the file /etc/sysctl.conf.</p>|Zabbix agent|kernel.maxfiles|
|Maximum number of processes|<p>It could be increased by using the sysctl utility or modifying the file /etc/sysctl.conf.</p>|Zabbix agent|kernel.maxproc|
|Number of running processes|<p>The number of processes in a running state.</p>|Zabbix agent|proc.num[,,run]|
|Number of processes|<p>The total number of processes in any state.</p>|Zabbix agent|proc.num[]|
|Host boot time| |Zabbix agent|system.boottime|
|Interrupts per second| |Zabbix agent|system.cpu.intr<p>**Preprocessing**</p><ul><li>Change per second: ``</li></ul>|
|Processor load (1 min average per core)|<p>The processor load is calculated as the system CPU load divided by the number of CPU cores.</p>|Zabbix agent|system.cpu.load[percpu,avg1]|
|Processor load (5 min average per core)|<p>The processor load is calculated as the system CPU load divided by the number of CPU cores.</p>|Zabbix agent|system.cpu.load[percpu,avg5]|
|Processor load (15 min average per core)|<p>The processor load is calculated as the system CPU load divided by the number of CPU cores.</p>|Zabbix agent|system.cpu.load[percpu,avg15]|
|Context switches per second| |Zabbix agent|system.cpu.switches<p>**Preprocessing**</p><ul><li>Change per second: ``</li></ul>|
|CPU idle time|<p>The time the CPU has spent doing nothing.</p>|Zabbix agent|system.cpu.util[,idle]|
|CPU interrupt time|<p>The amount of time the CPU has been servicing hardware interrupts.</p>|Zabbix agent|system.cpu.util[,interrupt]|
|CPU nice time|<p>The time the CPU has spent running users' processes that have been niced.</p>|Zabbix agent|system.cpu.util[,nice]|
|CPU system time|<p>The time the CPU has spent running the kernel and its processes.</p>|Zabbix agent|system.cpu.util[,system]|
|CPU user time|<p>The time the CPU has spent running users' processes that are not niced.</p>|Zabbix agent|system.cpu.util[,user]|
|Host name|<p>A host name of the system.</p>|Zabbix agent|system.hostname<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `1d`</li></ul>|
|Host local time| |Zabbix agent|system.localtime|
|Free swap space| |Zabbix agent|system.swap.size[,free]|
|Free swap space in %| |Zabbix agent|system.swap.size[,pfree]|
|Total swap space| |Zabbix agent|system.swap.size[,total]|
|System information|<p>The information as normally returned by the 'uname -a'.</p>|Zabbix agent|system.uname<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `1d`</li></ul>|
|System uptime| |Zabbix agent|system.uptime|
|Number of logged in users|<p>The number of users who are currently logged in.</p>|Zabbix agent|system.users.num|
|Checksum of /etc/passwd| |Zabbix agent|vfs.file.cksum[/etc/passwd,sha256]<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `1h`</li></ul>|
|Available memory|<p>The available memory is defined as free+cached+buffers memory.</p>|Zabbix agent|vm.memory.size[available]|
|Total memory| |Zabbix agent|vm.memory.size[total]|
|FreeBSD: Version of Zabbix agent running| |Zabbix agent|agent.version<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `1d`</li></ul>|
|Host name of Zabbix agent running| |Zabbix agent|agent.hostname<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `1d`</li></ul>|
|Zabbix agent ping|<p>The agent always returns 1 for this item. It could be used in combination with nodata() for the availability check.</p>|Zabbix agent|agent.ping|
|FreeBSD: Zabbix agent availability|<p>Monitoring the availability status of the agent.</p>|Zabbix internal|zabbix[host,agent,available]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Configured max number of opened files is too low on {HOST.NAME}||`last(/FreeBSD by Zabbix agent/kernel.maxfiles)<1024`|Info||
|Configured max number of processes is too low on {HOST.NAME}||`last(/FreeBSD by Zabbix agent/kernel.maxproc)<256`|Info||
|Too many processes running on {HOST.NAME}||`avg(/FreeBSD by Zabbix agent/proc.num[,,run],5m)>30`|Warning||
|Too many processes on {HOST.NAME}||`avg(/FreeBSD by Zabbix agent/proc.num[],5m)>300`|Warning||
|Processor load is too high on {HOST.NAME}||`avg(/FreeBSD by Zabbix agent/system.cpu.load[percpu,avg1],5m)>5`|Warning||
|Hostname was changed on {HOST.NAME}||`last(/FreeBSD by Zabbix agent/system.hostname,#1)<>last(/FreeBSD by Zabbix agent/system.hostname,#2)`|Info||
|Lack of free swap space on {HOST.NAME}|<p>It probably means that the systems requires more physical memory.</p>|`last(/FreeBSD by Zabbix agent/system.swap.size[,pfree])<50`|Warning||
|Host information was changed on {HOST.NAME}||`last(/FreeBSD by Zabbix agent/system.uname,#1)<>last(/FreeBSD by Zabbix agent/system.uname,#2)`|Info||
|{HOST.NAME} has just been restarted||`change(/FreeBSD by Zabbix agent/system.uptime)<0`|Info||
|/etc/passwd has been changed on {HOST.NAME}||`last(/FreeBSD by Zabbix agent/vfs.file.cksum[/etc/passwd,sha256],#1)<>last(/FreeBSD by Zabbix agent/vfs.file.cksum[/etc/passwd,sha256],#2)`|Warning||
|Lack of available memory on server {HOST.NAME}||`last(/FreeBSD by Zabbix agent/vm.memory.size[available])<20M`|Average||
|FreeBSD: Zabbix agent is not available|<p>For passive checks only the availability of the agents and a host is used with {$AGENT.TIMEOUT} as the time threshold.</p>|`max(/FreeBSD by Zabbix agent/zabbix[host,agent,available],{$AGENT.TIMEOUT})=0`|Average|**Manual close**: Yes|

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>The discovery of network interfaces as defined in the global regular expression "Network interfaces for discovery".</p>|Zabbix agent|net.if.discovery|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interfaces: Incoming network traffic on {#IFNAME}| |Zabbix agent|net.if.in[{#IFNAME}]<p>**Preprocessing**</p><ul><li>Change per second: ``</li><li>Custom multiplier: `8`</li></ul>|
|Network interfaces: Outgoing network traffic on {#IFNAME}| |Zabbix agent|net.if.out[{#IFNAME}]<p>**Preprocessing**</p><ul><li>Change per second: ``</li><li>Custom multiplier: `8`</li></ul>|

### LLD rule Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mounted filesystem discovery|<p>The discovery of different types of file systems as defined in the global regular expression "File systems for discovery".</p>|Zabbix agent|vfs.fs.discovery|

### Item prototypes for Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Filesystems: Free inodes on {#FSNAME} (percentage)| |Zabbix agent|vfs.fs.inode[{#FSNAME},pfree]|
|Filesystems: Free disk space on {#FSNAME}| |Zabbix agent|vfs.fs.size[{#FSNAME},free]|
|Filesystems: Free disk space on {#FSNAME} (percentage)| |Zabbix agent|vfs.fs.size[{#FSNAME},pfree]|
|Filesystems: Total disk space on {#FSNAME}| |Zabbix agent|vfs.fs.size[{#FSNAME},total]|
|Filesystems: Used disk space on {#FSNAME}| |Zabbix agent|vfs.fs.size[{#FSNAME},used]|

### Trigger prototypes for Mounted filesystem discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Filesystems: Free inodes is less than 20% on volume {#FSNAME}||`last(/FreeBSD by Zabbix agent/vfs.fs.inode[{#FSNAME},pfree])<20`|Warning||
|Filesystems: Free disk space is less than 20% on volume {#FSNAME}||`last(/FreeBSD by Zabbix agent/vfs.fs.size[{#FSNAME},pfree])<20`|Warning||

## Feedback

Please report any issues with the template at `https://support.zabbix.com`.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
