
# Solaris

## Overview

For Zabbix version: 6.2 and higher.
It is an official Solaris OS template. It requires Zabbix agent 4.0.0 or newer.


## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/zabbix_agent) for basic instructions.

Install Zabbix agent on Solaris OS according to Zabbix documentation.


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
|Network interface discovery |<p>The discovery of network interfaces as defined in the global regular expression "Network interfaces for discovery".</p> |ZABBIX_PASSIVE |net.if.discovery<p>**Filter**:</p> <p>- {#IFNAME} MATCHES_REGEX `@Network interfaces for discovery`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Solaris |Maximum number of processes |<p>It could be increased by using the sysctl utility or modifying the file /etc/sysctl.conf.</p> |ZABBIX_PASSIVE |kernel.maxproc |
|Solaris |Number of running processes |<p>The number of processes in a running state.</p> |ZABBIX_PASSIVE |proc.num[,,run] |
|Solaris |Number of processes |<p>The total number of processes in any state.</p> |ZABBIX_PASSIVE |proc.num[] |
|Solaris |Host boot time |<p>-</p> |ZABBIX_PASSIVE |system.boottime |
|Solaris |Interrupts per second |<p>-</p> |ZABBIX_PASSIVE |system.cpu.intr<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Solaris |Processor load (1 min average per core) |<p>The processor load is calculated as the system CPU load divided by the number of CPU cores.</p> |ZABBIX_PASSIVE |system.cpu.load[percpu,avg1] |
|Solaris |Processor load (5 min average per core) |<p>The processor load is calculated as the system CPU load divided by the number of CPU cores.</p> |ZABBIX_PASSIVE |system.cpu.load[percpu,avg5] |
|Solaris |Processor load (15 min average per core) |<p>The processor load is calculated as the system CPU load divided by the number of CPU cores.</p> |ZABBIX_PASSIVE |system.cpu.load[percpu,avg15] |
|Solaris |Context switches per second |<p>-</p> |ZABBIX_PASSIVE |system.cpu.switches<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Solaris |CPU idle time |<p>The time the CPU has spent doing nothing.</p> |ZABBIX_PASSIVE |system.cpu.util[,idle] |
|Solaris |CPU iowait time |<p>The amount of time the CPU has been waiting for the I/O to complete.</p> |ZABBIX_PASSIVE |system.cpu.util[,iowait] |
|Solaris |CPU system time |<p>The time the CPU has spent running the kernel and its processes.</p> |ZABBIX_PASSIVE |system.cpu.util[,system] |
|Solaris |CPU user time |<p>The time the CPU has spent running users' processes that are not niced.</p> |ZABBIX_PASSIVE |system.cpu.util[,user] |
|Solaris |Host name |<p>A host name of the system.</p> |ZABBIX_PASSIVE |system.hostname<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Solaris |Host local time |<p>-</p> |ZABBIX_PASSIVE |system.localtime |
|Solaris |Free swap space |<p>-</p> |ZABBIX_PASSIVE |system.swap.size[,free] |
|Solaris |Free swap space in % |<p>-</p> |ZABBIX_PASSIVE |system.swap.size[,pfree] |
|Solaris |Total swap space |<p>-</p> |ZABBIX_PASSIVE |system.swap.size[,total] |
|Solaris |System information |<p>The information as normally returned by the 'uname -a'.</p> |ZABBIX_PASSIVE |system.uname<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Solaris |System uptime |<p>-</p> |ZABBIX_PASSIVE |system.uptime |
|Solaris |Number of logged in users |<p>The number of users who are currently logged in.</p> |ZABBIX_PASSIVE |system.users.num |
|Solaris |Checksum of /etc/passwd |<p>-</p> |ZABBIX_PASSIVE |vfs.file.cksum[/etc/passwd,sha256]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Solaris |Available memory |<p>The available memory is defined as free+cached+buffers memory.</p> |ZABBIX_PASSIVE |vm.memory.size[available] |
|Solaris |Total memory |<p>-</p> |ZABBIX_PASSIVE |vm.memory.size[total] |
|Solaris |Host name of Zabbix agent running |<p>-</p> |ZABBIX_PASSIVE |agent.hostname<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Solaris |Version of Zabbix agent running |<p>-</p> |ZABBIX_PASSIVE |agent.version<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Solaris |Zabbix agent availability |<p>Monitoring agent availability status</p> |INTERNAL |zabbix[host,agent,available] |
|Solaris |Zabbix agent ping |<p>The agent always returns 1 for this item. It could be used in combination with nodata() for the availability check.</p> |ZABBIX_PASSIVE |agent.ping |
|Solaris |Interface {#IFNAME}: Incoming network traffic |<p>-</p> |ZABBIX_PASSIVE |net.if.in[{#IFNAME}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Solaris |Interface {#IFNAME}: Outgoing network traffic |<p>-</p> |ZABBIX_PASSIVE |net.if.out[{#IFNAME}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Solaris |{#FSNAME}: Free inodes (percentage) |<p>-</p> |ZABBIX_PASSIVE |vfs.fs.inode[{#FSNAME},pfree] |
|Solaris |{#FSNAME}: Free disk space |<p>-</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},free] |
|Solaris |{#FSNAME}: Free disk space (percentage) |<p>-</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},pfree] |
|Solaris |{#FSNAME}: Total disk space |<p>-</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},total] |
|Solaris |{#FSNAME}: Used disk space |<p>-</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},used] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Configured max number of processes is too low |<p>-</p> |`last(/Solaris/kernel.maxproc)<256` |INFO | |
|Too many processes running |<p>-</p> |`avg(/Solaris/proc.num[,,run],5m)>30` |WARNING | |
|Too many processes |<p>-</p> |`avg(/Solaris/proc.num[],5m)>300` |WARNING | |
|Processor load is too high |<p>-</p> |`avg(/Solaris/system.cpu.load[percpu,avg1],5m)>5` |WARNING | |
|Disk I/O is overloaded |<p>The OS spends significant time waiting for the I/O (input/output) operations. It could be an indicator of performance issues with the storage system.</p> |`avg(/Solaris/system.cpu.util[,iowait],5m)>20` |WARNING | |
|Hostname was changed |<p>-</p> |`last(/Solaris/system.hostname,#1)<>last(/Solaris/system.hostname,#2)` |INFO | |
|Lack of free swap space |<p>It probably means that the systems requires more physical memory.</p> |`last(/Solaris/system.swap.size[,pfree])<50` |WARNING | |
|Host information was changed |<p>-</p> |`last(/Solaris/system.uname,#1)<>last(/Solaris/system.uname,#2)` |INFO | |
|Server has just been restarted |<p>-</p> |`change(/Solaris/system.uptime)<0` |INFO | |
|/etc/passwd has been changed |<p>-</p> |`last(/Solaris/vfs.file.cksum[/etc/passwd,sha256],#1)<>last(/Solaris/vfs.file.cksum[/etc/passwd,sha256],#2)` |WARNING | |
|Lack of available memory on server |<p>-</p> |`last(/Solaris/vm.memory.size[available])<20M` |AVERAGE | |
|Zabbix agent is not available |<p>For passive checks only the availability of the agents and a host is used with {$AGENT.TIMEOUT} as the time threshold.</p> |`max(/Solaris/zabbix[host,agent,available],{$AGENT.TIMEOUT})=0` |AVERAGE |<p>Manual close: YES</p> |
|{#FSNAME}: Free inodes is less than 20% |<p>-</p> |`last(/Solaris/vfs.fs.inode[{#FSNAME},pfree])<20` |WARNING | |
|{#FSNAME}: Free disk space is less than 20% |<p>-</p> |`last(/Solaris/vfs.fs.size[{#FSNAME},pfree])<20` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/+).

