
# AIX by Zabbix agent

## Overview

For Zabbix version: 6.0 and higher.  
It is an official AIX template. It requires Zabbix agent 4.0 or newer.


## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/zabbix_agent) for basic instructions.

Install Zabbix agent on the AIX OS according to Zabbix documentation.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$AGENT.TIMEOUT} |<p>The timeout after which agent is considered unavailable. It works only for the agents reachable from Zabbix server/proxy (in passive mode).</p> |`3m` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Mounted filesystem discovery |<p>The discovery of different types of file systems as defined in the global regular expression "File systems for discovery".</p> |ZABBIX_PASSIVE |vfs.fs.discovery<p>**Filter**:</p> <p>- {#FSTYPE} MATCHES_REGEX `@File systems for discovery`</p> |
|Network interface discovery |<p>The discovery of network interfaces as defined in the global regular expression "Network interfaces for discovery".</p> |ZABBIX_PASSIVE |net.if.discovery<p>**Filter**:</p> <p>- {#IFNAME} MATCHES_REGEX `@Network interfaces for discovery`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|AIX |Number of running processes |<p>The number of processes in a running state.</p> |ZABBIX_PASSIVE |proc.num[,,run] |
|AIX |Number of processes |<p>The total number of processes in any state.</p> |ZABBIX_PASSIVE |proc.num[] |
|AIX |Interrupts per second |<p>-</p> |ZABBIX_PASSIVE |system.cpu.intr<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p> |
|AIX |Processor load (1 min average per core) |<p>The processor load is calculated as the system CPU load divided by the number of CPU cores.</p> |ZABBIX_PASSIVE |system.cpu.load[percpu,avg1] |
|AIX |Processor load (5 min average per core) |<p>The processor load is calculated as the system CPU load divided by the number of CPU cores.</p> |ZABBIX_PASSIVE |system.cpu.load[percpu,avg5] |
|AIX |Processor load (15 min average per core) |<p>The processor load is calculated as the system CPU load divided by the number of CPU cores.</p> |ZABBIX_PASSIVE |system.cpu.load[percpu,avg15] |
|AIX |Context switches per second |<p>-</p> |ZABBIX_PASSIVE |system.cpu.switches<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p> |
|AIX |Host name |<p>A host name of the system.</p> |ZABBIX_PASSIVE |system.hostname<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|AIX |Host local time |<p>-</p> |ZABBIX_PASSIVE |system.localtime |
|AIX |CPU available physical processors in the shared pool |<p>-</p> |ZABBIX_PASSIVE |system.stat[cpu,app] |
|AIX |CPU entitled capacity consumed |<p>-</p> |ZABBIX_PASSIVE |system.stat[cpu,ec] |
|AIX |CPU idle time |<p>-</p> |ZABBIX_PASSIVE |system.stat[cpu,id] |
|AIX |CPU logical processor utilization |<p>-</p> |ZABBIX_PASSIVE |system.stat[cpu,lbusy] |
|AIX |CPU number of physical processors consumed |<p>-</p> |ZABBIX_PASSIVE |system.stat[cpu,pc] |
|AIX |CPU system time |<p>-</p> |ZABBIX_PASSIVE |system.stat[cpu,sy] |
|AIX |CPU user time |<p>-</p> |ZABBIX_PASSIVE |system.stat[cpu,us] |
|AIX |CPU iowait time |<p>-</p> |ZABBIX_PASSIVE |system.stat[cpu,wa] |
|AIX |Amount of data transferred |<p>-</p> |ZABBIX_PASSIVE |system.stat[disk,bps] |
|AIX |Number of transfers |<p>-</p> |ZABBIX_PASSIVE |system.stat[disk,tps] |
|AIX |Processor units is entitled to receive |<p>-</p> |ZABBIX_PASSIVE |system.stat[ent] |
|AIX |Kernel thread context switches |<p>-</p> |ZABBIX_PASSIVE |system.stat[faults,cs] |
|AIX |Device interrupts |<p>-</p> |ZABBIX_PASSIVE |system.stat[faults,in] |
|AIX |System calls |<p>-</p> |ZABBIX_PASSIVE |system.stat[faults,sy] |
|AIX |Length of the swap queue |<p>-</p> |ZABBIX_PASSIVE |system.stat[kthr,b] |
|AIX |Length of the run queue |<p>-</p> |ZABBIX_PASSIVE |system.stat[kthr,r] |
|AIX |Active virtual pages |<p>-</p> |ZABBIX_PASSIVE |system.stat[memory,avm] |
|AIX |Free real memory |<p>-</p> |ZABBIX_PASSIVE |system.stat[memory,fre] |
|AIX |File page-ins per second |<p>-</p> |ZABBIX_PASSIVE |system.stat[page,fi] |
|AIX |File page-outs per second |<p>-</p> |ZABBIX_PASSIVE |system.stat[page,fo] |
|AIX |Pages freed (page replacement) |<p>-</p> |ZABBIX_PASSIVE |system.stat[page,fr] |
|AIX |Pages paged in from paging space |<p>-</p> |ZABBIX_PASSIVE |system.stat[page,pi] |
|AIX |Pages paged out to paging space |<p>-</p> |ZABBIX_PASSIVE |system.stat[page,po] |
|AIX |Pages scanned by page-replacement algorithm |<p>-</p> |ZABBIX_PASSIVE |system.stat[page,sr] |
|AIX |System information |<p>The information as normally returned by the 'uname -a'.</p> |ZABBIX_PASSIVE |system.uname<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|AIX |System uptime |<p>-</p> |ZABBIX_PASSIVE |system.uptime |
|AIX |Number of logged in users |<p>The number of users who are currently logged in.</p> |ZABBIX_PASSIVE |system.users.num |
|AIX |Checksum of /etc/passwd |<p>-</p> |ZABBIX_PASSIVE |vfs.file.cksum[/etc/passwd,sha256]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|AIX |Available memory |<p>The available memory is defined as free+cached+buffers memory.</p> |ZABBIX_PASSIVE |vm.memory.size[available] |
|AIX |Total memory |<p>-</p> |ZABBIX_PASSIVE |vm.memory.size[total] |
|AIX |Interface {#IFNAME}: Incoming network traffic |<p>-</p> |ZABBIX_PASSIVE |net.if.in[{#IFNAME}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p><p>- MULTIPLIER: `8`</p> |
|AIX |Interface {#IFNAME}: Outgoing network traffic |<p>-</p> |ZABBIX_PASSIVE |net.if.out[{#IFNAME}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p><p>- MULTIPLIER: `8`</p> |
|AIX |{#FSNAME}: Free inodes, % |<p>-</p> |ZABBIX_PASSIVE |vfs.fs.inode[{#FSNAME},pfree] |
|AIX |{#FSNAME}: Free disk space |<p>-</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},free] |
|AIX |{#FSNAME}: Free disk space, % |<p>-</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},pfree] |
|AIX |{#FSNAME}: Total disk space |<p>-</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},total] |
|AIX |{#FSNAME}: Used disk space |<p>-</p> |ZABBIX_PASSIVE |vfs.fs.size[{#FSNAME},used] |
|Monitoring agent |Version of Zabbix agent running |<p>-</p> |ZABBIX_PASSIVE |agent.version<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Monitoring agent |Host name of Zabbix agent running |<p>-</p> |ZABBIX_PASSIVE |agent.hostname<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Monitoring agent |Zabbix agent ping |<p>The agent always returns 1 for this item. It could be used in combination with nodata() for the availability check.</p> |ZABBIX_PASSIVE |agent.ping |
|Status |Zabbix agent availability |<p>Monitoring the availability status of the agent.</p> |INTERNAL |zabbix[host,agent,available] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Too many processes running |<p>-</p> |`avg(/AIX by Zabbix agent/proc.num[,,run],5m)>30` |WARNING | |
|Too many processes |<p>-</p> |`avg(/AIX by Zabbix agent/proc.num[],5m)>300` |WARNING | |
|Processor load is too high |<p>-</p> |`avg(/AIX by Zabbix agent/system.cpu.load[percpu,avg1],5m)>5` |WARNING | |
|Hostname was changed |<p>-</p> |`last(/AIX by Zabbix agent/system.hostname,#1)<>last(/AIX by Zabbix agent/system.hostname,#2)` |INFO | |
|Disk I/O is overloaded |<p>The OS spends significant time waiting for the I/O (input/output) operations. It could be an indicator of performance issues with the storage system.</p> |`avg(/AIX by Zabbix agent/system.stat[cpu,wa],5m)>20` |WARNING | |
|Host information was changed |<p>-</p> |`last(/AIX by Zabbix agent/system.uname,#1)<>last(/AIX by Zabbix agent/system.uname,#2)` |INFO | |
|Server has just been restarted |<p>-</p> |`change(/AIX by Zabbix agent/system.uptime)<0` |INFO | |
|/etc/passwd has been changed |<p>-</p> |`last(/AIX by Zabbix agent/vfs.file.cksum[/etc/passwd,sha256],#1)<>last(/AIX by Zabbix agent/vfs.file.cksum[/etc/passwd,sha256],#2)` |WARNING | |
|Lack of available memory on server |<p>-</p> |`last(/AIX by Zabbix agent/vm.memory.size[available])<20M` |AVERAGE | |
|{#FSNAME}: Free inodes is less than 20% |<p>-</p> |`last(/AIX by Zabbix agent/vfs.fs.inode[{#FSNAME},pfree])<20` |WARNING | |
|{#FSNAME}: Free disk space is less than 20% |<p>-</p> |`last(/AIX by Zabbix agent/vfs.fs.size[{#FSNAME},pfree])<20` |WARNING | |
|Zabbix agent is not available |<p>For passive checks only the availability of the agent(s) and a host is used with {$AGENT.TIMEOUT} as the time threshold.</p> |`max(/AIX by Zabbix agent/zabbix[host,agent,available],{$AGENT.TIMEOUT})=0` |AVERAGE |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

