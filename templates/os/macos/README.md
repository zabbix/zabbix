
# macOS by Zabbix agent

## Overview

macOS is the operating system that powers every Mac

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- macOS operating system

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Install Zabbix agent on macOS according to Zabbix documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AGENT.TIMEOUT}|<p>The timeout after which the agent is considered unavailable. It works only for the agents reachable from Zabbix server/proxy (in passive mode).</p>|`3m`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|macOS: Maximum number of opened files|<p>It could be increased by using sysctl utility or modifying the file /etc/sysctl.conf.</p>|Zabbix agent|kernel.maxfiles|
|macOS: Maximum number of processes|<p>It could be increased by using sysctl utility or modifying the file /etc/sysctl.conf.</p>|Zabbix agent|kernel.maxproc|
|macOS: Incoming network traffic on en0||Zabbix agent|net.if.in[en0]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|macOS: Outgoing network traffic on en0||Zabbix agent|net.if.out[en0]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|macOS: Host boot time||Zabbix agent|system.boottime|
|macOS: Processor load (1 min average per core)|<p>The processor load is calculated as the system CPU load divided by the number of CPU cores.</p>|Zabbix agent|system.cpu.load[percpu,avg1]|
|macOS: Processor load (5 min average per core)|<p>The processor load is calculated as the system CPU load divided by the number of CPU cores.</p>|Zabbix agent|system.cpu.load[percpu,avg5]|
|macOS: Processor load (15 min average per core)|<p>The processor load is calculated as the system CPU load divided by the number of CPU cores.</p>|Zabbix agent|system.cpu.load[percpu,avg15]|
|macOS: Host name|<p>A host name of the system.</p>|Zabbix agent|system.hostname<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|macOS: Host local time||Zabbix agent|system.localtime|
|macOS: System information|<p>The information as normally returned by the 'uname -a'.</p>|Zabbix agent|system.uname<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|macOS: System uptime||Zabbix agent|system.uptime|
|macOS: Number of logged in users|<p>The number of users who are currently logged in.</p>|Zabbix agent|system.users.num|
|macOS: Checksum of /etc/passwd||Zabbix agent|vfs.file.cksum[/etc/passwd,sha256]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|macOS: Available memory|<p>The available memory is defined as free+cached+buffers memory.</p>|Zabbix agent|vm.memory.size[available]|
|macOS: Total memory||Zabbix agent|vm.memory.size[total]|
|macOS: Version of Zabbix agent running||Zabbix agent|agent.version<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|macOS: Host name of Zabbix agent running||Zabbix agent|agent.hostname<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|macOS: Zabbix agent ping|<p>The agent always returns 1 for this item. It could be used in combination with nodata() for the availability check.</p>|Zabbix agent|agent.ping|
|macOS: Zabbix agent availability|<p>Monitoring the availability status of the agent.</p>|Zabbix internal|zabbix[host,agent,available]|
|macOS: Get filesystems|<p>The `vfs.fs.get` key acquires raw information set about the file systems. Later to be extracted by preprocessing in dependent items.</p>|Zabbix agent|vfs.fs.get|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|macOS: Configured max number of opened files is too low||`last(/macOS by Zabbix agent/kernel.maxfiles)<1024`|Info||
|macOS: Configured max number of processes is too low||`last(/macOS by Zabbix agent/kernel.maxproc)<256`|Info||
|macOS: Processor load is too high||`avg(/macOS by Zabbix agent/system.cpu.load[percpu,avg1],5m)>5`|Warning||
|macOS: Hostname was changed||`last(/macOS by Zabbix agent/system.hostname,#1)<>last(/macOS by Zabbix agent/system.hostname,#2)`|Info||
|macOS: Host information was changed||`last(/macOS by Zabbix agent/system.uname,#1)<>last(/macOS by Zabbix agent/system.uname,#2)`|Info||
|macOS: Server has just been restarted||`change(/macOS by Zabbix agent/system.uptime)<0`|Info||
|macOS: /etc/passwd has been changed||`last(/macOS by Zabbix agent/vfs.file.cksum[/etc/passwd,sha256],#1)<>last(/macOS by Zabbix agent/vfs.file.cksum[/etc/passwd,sha256],#2)`|Warning||
|macOS: Lack of available memory on server||`last(/macOS by Zabbix agent/vm.memory.size[available])<20M`|Average||
|macOS: Zabbix agent is not available|<p>For passive checks only the availability of the agents and a host is used with {$AGENT.TIMEOUT} as the time threshold.</p>|`max(/macOS by Zabbix agent/zabbix[host,agent,available],{$AGENT.TIMEOUT})=0`|Average|**Manual close**: Yes|

### LLD rule Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mounted filesystem discovery|<p>Discovery of different types of file systems as defined in the global regular expression "File systems for discovery". Note that the option to exclude dmg software images from discovery is available only with Zabbix agents 6.4 and higher.</p>|Dependent item|vfs.fs.dependent.discovery|

### Item prototypes for Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#FSNAME}: Get filesystem data||Dependent item|vfs.fs.dependent[{#FSNAME},data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.fsname=='{#FSNAME}')].first()`</p></li></ul>|
|{#FSNAME}: Free inodes, %||Dependent item|vfs.fs.dependent.inode[{#FSNAME},pfree]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.inodes.pfree`</p></li></ul>|
|{#FSNAME}: Free disk space||Dependent item|vfs.fs.dependent.size[{#FSNAME},free]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.free`</p></li></ul>|
|{#FSNAME}: Free disk space, %||Dependent item|vfs.fs.dependent.size[{#FSNAME},pfree]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.pfree`</p></li></ul>|
|{#FSNAME}: Total disk space||Dependent item|vfs.fs.dependent.size[{#FSNAME},total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.total`</p></li></ul>|
|{#FSNAME}: Used disk space||Dependent item|vfs.fs.dependent.size[{#FSNAME},used]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes.used`</p></li></ul>|

### Trigger prototypes for Mounted filesystem discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#FSNAME}: Free inodes is less than 20%||`last(/macOS by Zabbix agent/vfs.fs.dependent.inode[{#FSNAME},pfree])<20`|Warning||
|{#FSNAME}: Free disk space is less than 20%||`last(/macOS by Zabbix agent/vfs.fs.dependent.size[{#FSNAME},pfree])<20`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

