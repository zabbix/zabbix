
# macOS

## Overview

For Zabbix version: 6.0 and higher.  
It is an official macOS template. It requires Zabbix agent 4.0.0 or newer.


## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/zabbix_agent) for basic instructions.

Install Zabbix agent on macOS according to Zabbix documentation.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AGENT.TIMEOUT} |<p>The timeout after which the agent is considered unavailable. It works only for the agents reachable from Zabbix server/proxy (in passive mode).</p> |`3m` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Mounted filesystem discovery |<p>Discovery of different types of file systems as defined in the global regular expression "File systems for discovery".</p> |ZABBIX_PASSIVE |vfs.fs.discovery<p>**Filter**:</p> <p>- {#FSTYPE} MATCHES_REGEX `@File systems for discovery`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|macOS |Maximum number of opened files |<p>It could be increased by using sysctl utility or modifying the file /etc/sysctl.conf.</p> |ZABBIX_PASSIVE |kernel.maxfiles |
|macOS |Maximum number of processes |<p>It could be increased by using sysctl utility or modifying the file /etc/sysctl.conf.</p> |ZABBIX_PASSIVE |kernel.maxproc |
|macOS |Incoming network traffic on en0 |<p>-</p> |ZABBIX_PASSIVE |net.if.in[en0]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|macOS |Outgoing network traffic on en0 |<p>-</p> |ZABBIX_PASSIVE |net.if.out[en0]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|macOS |Host boot time |<p>-</p> |ZABBIX_PASSIVE |system.boottime |
|macOS |Processor load (1 min average per core) |<p>The processor load is calculated as the system CPU load divided by the number of CPU cores.</p> |ZABBIX_PASSIVE |system.cpu.load[percpu,avg1] |
|macOS |Processor load (5 min average per core) |<p>The processor load is calculated as the system CPU load divided by the number of CPU cores.</p> |ZABBIX_PASSIVE |system.cpu.load[percpu,avg5] |
|macOS |Processor load (15 min average per core) |<p>The processor load is calculated as the system CPU load divided by the number of CPU cores.</p> |ZABBIX_PASSIVE |system.cpu.load[percpu,avg15] |
|macOS |Host name |<p>A host name of the system.</p> |ZABBIX_PASSIVE |system.hostname<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|macOS |Host local time |<p>-</p> |ZABBIX_PASSIVE |system.localtime |
|macOS |System information |<p>The information as normally returned by the 'uname -a'.</p> |ZABBIX_PASSIVE |system.uname<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|macOS |System uptime |<p>-</p> |ZABBIX_PASSIVE |system.uptime |
|macOS |Number of logged in users |<p>The number of users who are currently logged in.</p> |ZABBIX_PASSIVE |system.users.num |
|macOS |Checksum of /etc/passwd |<p>-</p> |ZABBIX_PASSIVE |vfs.file.cksum[/etc/passwd,sha256]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|macOS |Available memory |<p>The available memory is defined as free+cached+buffers memory.</p> |ZABBIX_PASSIVE |vm.memory.size[available] |
|macOS |Total memory |<p>-</p> |ZABBIX_PASSIVE |vm.memory.size[total] |
|macOS |Host name of Zabbix agent running |<p>-</p> |ZABBIX_PASSIVE |agent.hostname<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|macOS |Zabbix agent ping |<p>The agent always returns 1 for this item. It could be used in combination with nodata() for the availability check.</p> |ZABBIX_PASSIVE |agent.ping |
|macOS |{#FSNAME}: Free inodes (percentage) |<p>-</p> |ZABBIX_PASSIVE |vfs.fs.inode[{#FSNAME},pfree] |
|macOS |{#FSNAME}: Free disk space |<p>-</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},free] |
|macOS |{#FSNAME}: Free disk space (percentage) |<p>-</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},pfree] |
|macOS |{#FSNAME}: Total disk space |<p>-</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},total] |
|macOS |{#FSNAME}: Used disk space |<p>-</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},used] |
|Monitoring agent |Version of Zabbix agent running |<p>-</p> |ZABBIX_PASSIVE |agent.version<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Status |Zabbix agent availability |<p>Monitoring the availability status of the agent.</p> |INTERNAL |zabbix[host,agent,available] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Configured max number of opened files is too low |<p>-</p> |`last(/macOS/kernel.maxfiles)<1024` |INFO | |
|Configured max number of processes is too low |<p>-</p> |`last(/macOS/kernel.maxproc)<256` |INFO | |
|Processor load is too high |<p>-</p> |`avg(/macOS/system.cpu.load[percpu,avg1],5m)>5` |WARNING | |
|Hostname was changed |<p>-</p> |`last(/macOS/system.hostname,#1)<>last(/macOS/system.hostname,#2)` |INFO | |
|Host information was changed |<p>-</p> |`last(/macOS/system.uname,#1)<>last(/macOS/system.uname,#2)` |INFO | |
|Server has just been restarted |<p>-</p> |`change(/macOS/system.uptime)<0` |INFO | |
|/etc/passwd has been changed |<p>-</p> |`last(/macOS/vfs.file.cksum[/etc/passwd,sha256],#1)<>last(/macOS/vfs.file.cksum[/etc/passwd,sha256],#2)` |WARNING | |
|Lack of available memory on server |<p>-</p> |`last(/macOS/vm.memory.size[available])<20M` |AVERAGE | |
|{#FSNAME}: Free inodes is less than 20% |<p>-</p> |`last(/macOS/vfs.fs.inode[{#FSNAME},pfree])<20` |WARNING | |
|{#FSNAME}: Free disk space is less than 20% |<p>-</p> |`last(/macOS/vfs.fs.size[{#FSNAME},pfree])<20` |WARNING | |
|Zabbix agent is not available |<p>For passive checks only the availability of the agents and a host is used with {$AGENT.TIMEOUT} as the time threshold.</p> |`max(/macOS/zabbix[host,agent,available],{$AGENT.TIMEOUT})=0` |AVERAGE |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

