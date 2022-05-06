
# HP-UX by Zabbix agent

## Overview

For Zabbix version: 6.0 and higher  
Official HP-UX template. Requires agent of Zabbix 4.0 and newer.


## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/zabbix_agent) for basic instructions.

Install Zabbix agent on HP-UX OS according to Zabbix documentation.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AGENT.TIMEOUT} |<p>Timeout after which agent is considered unavailable. Works only for agents reachable from Zabbix server/proxy (passive mode).</p> |`3m` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Mounted filesystem discovery |<p>Discovery of file systems of different types as defined in global regular expression "File systems for discovery".</p> |ZABBIX_PASSIVE |vfs.fs.discovery<p>**Filter**:</p> <p>- {#FSTYPE} MATCHES_REGEX `@File systems for discovery`</p> |
|Network interface discovery |<p>Discovery of network interfaces as defined in global regular expression "Network interfaces for discovery".</p> |ZABBIX_PASSIVE |net.if.discovery<p>**Filter**:</p> <p>- {#IFNAME} MATCHES_REGEX `@Network interfaces for discovery`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|HP-UX |Processor load (1 min average per core) |<p>The processor load is calculated as system CPU load divided by number of CPU cores.</p> |ZABBIX_PASSIVE |system.cpu.load[percpu,avg1] |
|HP-UX |Processor load (5 min average per core) |<p>The processor load is calculated as system CPU load divided by number of CPU cores.</p> |ZABBIX_PASSIVE |system.cpu.load[percpu,avg5] |
|HP-UX |Processor load (15 min average per core) |<p>The processor load is calculated as system CPU load divided by number of CPU cores.</p> |ZABBIX_PASSIVE |system.cpu.load[percpu,avg15] |
|HP-UX |CPU idle time |<p>The time the CPU has spent doing nothing.</p> |ZABBIX_PASSIVE |system.cpu.util[,idle] |
|HP-UX |CPU nice time |<p>The time the CPU has spent running users' processes that have been niced.</p> |ZABBIX_PASSIVE |system.cpu.util[,nice] |
|HP-UX |CPU system time |<p>The time the CPU has spent running the kernel and its processes.</p> |ZABBIX_PASSIVE |system.cpu.util[,system] |
|HP-UX |CPU user time |<p>The time the CPU has spent running users' processes that are not niced.</p> |ZABBIX_PASSIVE |system.cpu.util[,user] |
|HP-UX |Host name |<p>System host name.</p> |ZABBIX_PASSIVE |system.hostname<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HP-UX |Host local time |<p>-</p> |ZABBIX_PASSIVE |system.localtime |
|HP-UX |System information |<p>The information as normally returned by 'uname -a'.</p> |ZABBIX_PASSIVE |system.uname<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HP-UX |Number of logged in users |<p>Number of users who are currently logged in.</p> |ZABBIX_PASSIVE |system.users.num |
|HP-UX |Checksum of /etc/passwd |<p>-</p> |ZABBIX_PASSIVE |vfs.file.cksum[/etc/passwd,sha256] |
|HP-UX |Available memory |<p>Available memory is defined as free+cached+buffers memory.</p> |ZABBIX_PASSIVE |vm.memory.size[available] |
|HP-UX |Total memory |<p>-</p> |ZABBIX_PASSIVE |vm.memory.size[total] |
|HP-UX |Interface {#IFNAME}: Incoming network traffic |<p>-</p> |ZABBIX_PASSIVE |net.if.in[{#IFNAME}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p><p>- MULTIPLIER: `8`</p> |
|HP-UX |Interface {#IFNAME}: Outgoing network traffic |<p>-</p> |ZABBIX_PASSIVE |net.if.out[{#IFNAME}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p><p>- MULTIPLIER: `8`</p> |
|HP-UX |{#FSNAME}: Free inodes, % |<p>-</p> |ZABBIX_PASSIVE |vfs.fs.inode[{#FSNAME},pfree] |
|HP-UX |{#FSNAME}: Free disk space |<p>-</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},free] |
|HP-UX |{#FSNAME}: Free disk space, % |<p>-</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},pfree] |
|HP-UX |{#FSNAME}: Total disk space |<p>-</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},total] |
|HP-UX |{#FSNAME}: Used disk space |<p>-</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},used] |
|Monitoring agent |Version of Zabbix agent running |<p>-</p> |ZABBIX_PASSIVE |agent.version<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Monitoring agent |Host name of Zabbix agent running |<p>-</p> |ZABBIX_PASSIVE |agent.hostname<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Monitoring agent |Zabbix agent ping |<p>The agent always returns 1 for this item. It could be used in combination with nodata() for availability check.</p> |ZABBIX_PASSIVE |agent.ping |
|Status |Zabbix agent availability |<p>Monitoring agent availability status</p> |INTERNAL |zabbix[host,agent,available] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Processor load is too high |<p>-</p> |`avg(/HP-UX by Zabbix agent/system.cpu.load[percpu,avg1],5m)>5` |WARNING | |
|Hostname was changed |<p>-</p> |`last(/HP-UX by Zabbix agent/system.hostname,#1)<>last(/HP-UX by Zabbix agent/system.hostname,#2)` |INFO | |
|Host information was changed |<p>-</p> |`last(/HP-UX by Zabbix agent/system.uname,#1)<>last(/HP-UX by Zabbix agent/system.uname,#2)` |INFO | |
|/etc/passwd has been changed |<p>-</p> |`last(/HP-UX by Zabbix agent/vfs.file.cksum[/etc/passwd,sha256],#1)<>last(/HP-UX by Zabbix agent/vfs.file.cksum[/etc/passwd,sha256],#2)` |WARNING | |
|Lack of available memory on server |<p>-</p> |`last(/HP-UX by Zabbix agent/vm.memory.size[available])<20M` |AVERAGE | |
|{#FSNAME}: Free inodes is less than 20% |<p>-</p> |`last(/HP-UX by Zabbix agent/vfs.fs.inode[{#FSNAME},pfree])<20` |WARNING | |
|{#FSNAME}: Free disk space is less than 20% |<p>-</p> |`last(/HP-UX by Zabbix agent/vfs.fs.size[{#FSNAME},pfree])<20` |WARNING | |
|Zabbix agent is not available |<p>For passive only agents, host availability is used with {$AGENT.TIMEOUT} as time threshold.</p> |`max(/HP-UX by Zabbix agent/zabbix[host,agent,available],{$AGENT.TIMEOUT})=0` |AVERAGE |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

