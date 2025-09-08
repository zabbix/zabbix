
# Linux by SNMP

## Overview

This is an official Linux template. It requires an SNMP client.

#### Notes on filesystem (FS) discovery:
- The ext4/3/2 FS reserves space for privileged usage, typically set at 5% by default.
- BTRFS allocates a default of 10% of the volume for its own needs.
- To mitigate potential disasters, FS usage triggers are based on the maximum available space.
  - Utilization formula: `pused = 100 * (used / used + available)`
- The FS utilization chart, derived from graph prototypes, reflects FS reserved space as the difference between used and available space from the total volume.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Linux OS

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Install snmpd agent on Linux OS, enable SNMPv2.

Make sure access to UCD-SNMP-MIB is allowed from Zabbix server/proxy host, since,
by default, snmpd (for example, in Ubuntu) limits access to basic system information only:

```text
rocommunity public  default    -V systemonly
```

Ensure snmpd is monitoring disks, using `includeALLDisks` or `disk` options. Example:
```
includeALLDisks 0% # monitors all disks starting with 0% fill rate
# disk / 0% # monitor only / root volume starting with 0% fill rate
```
Make sure you change that in order to read metrics of UCD-SNMP-MIB and UCD-DISKIO-MIB. Please refer to the documentation:
http://www.net-snmp.org/wiki/index.php/Vacm

You can also try to use `snmpconf`:

http://www.net-snmp.org/wiki/index.php/TUT:snmpd_configuration

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MEMORY.UTIL.MAX}|<p>Used as a threshold in the memory utilization trigger.</p>|`90`|
|{$MEMORY.AVAILABLE.MIN}|<p>Used as a threshold in the available memory trigger.</p>|`20M`|
|{$SWAP.PFREE.MIN.WARN}|<p>The warning threshold of the minimum free swap.</p>|`50`|
|{$VFS.DEV.DEVNAME.MATCHES}|<p>Used in block device discovery. Can be overridden on the host or linked template level.</p>|`.+`|
|{$VFS.DEV.DEVNAME.NOT_MATCHES}|<p>Used in block device discovery. Can be overridden on the host or linked template level.</p>|`Macro too long. Please see the template.`|
|{$CPU.UTIL.CRIT}|<p>Critical threshold of CPU utilization expressed in %.</p>|`90`|
|{$LOAD_AVG_PER_CPU.MAX.WARN}|<p>Load per CPU considered sustainable. Tune if needed.</p>|`1.5`|
|{$VFS.FS.PUSED.MAX.WARN}|<p>The warning threshold of the filesystem utilization.</p>|`80`|
|{$VFS.FS.PUSED.MAX.CRIT}|<p>The critical threshold of the filesystem utilization.</p>|`90`|
|{$VFS.FS.INODE.PFREE.MIN.WARN}|<p>The warning threshold of the filesystem metadata utilization.</p>|`20`|
|{$VFS.FS.INODE.PFREE.MIN.CRIT}|<p>The critical threshold of the filesystem metadata utilization.</p>|`10`|
|{$VFS.FS.FSNAME.MATCHES}|<p>Used in filesystem discovery. Can be overridden on the host or linked template level.</p>|`.+`|
|{$VFS.FS.FSNAME.NOT_MATCHES}|<p>Used in filesystem discovery. Can be overridden on the host or linked template level.</p>|`^(/dev\|/sys\|/run\|/proc\|.+/shm$)`|
|{$VFS.FS.FSDEVICE.MATCHES}|<p>Used in filesystem discovery. Can be overridden on the host or linked template level.</p>|`.+`|
|{$VFS.FS.FSDEVICE.NOT_MATCHES}|<p>Used in filesystem discovery. Can be overridden on the host or linked template level.</p>|`.*(tmpfs\|shm)$`|
|{$SNMP.TIMEOUT}|<p>Time interval for the SNMP availability trigger.</p>|`5m`|
|{$ICMP_LOSS_WARN}|<p>Warning threshold of ICMP packet loss in %.</p>|`20`|
|{$ICMP_RESPONSE_TIME_WARN}|<p>Warning threshold of the average ICMP response time in seconds.</p>|`0.15`|
|{$IFCONTROL}|<p>Link status trigger will be fired only for interfaces where the context macro equals "1".</p>|`1`|
|{$IF.UTIL.MAX}|<p>Used as a threshold in the interface utilization trigger.</p>|`90`|
|{$IF.ERRORS.WARN}|<p>Warning threshold of error packet rate. Can be used with interface name as context.</p>|`2`|
|{$NET.IF.IFNAME.MATCHES}|<p>Used for network interface discovery. Can be overridden on the host or linked template level.</p>|`^.*$`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>Filters out `loopbacks`, `nulls`, docker `veth` links and `docker0 bridge` by default.</p>|`Macro too long. Please see the template.`|
|{$NET.IF.IFOPERSTATUS.MATCHES}|<p>Used for network interface discovery. Can be overridden on the host or linked template level.</p>|`^.*$`|
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES}|<p>Ignore `notPresent(6)`</p>|`^6$`|
|{$NET.IF.IFADMINSTATUS.MATCHES}|<p>Used in network interface discovery rule filters.</p>|`^.*`|
|{$NET.IF.IFADMINSTATUS.NOT_MATCHES}|<p>Ignore `down(2)` administrative status</p>|`^2$`|
|{$NET.IF.IFDESCR.MATCHES}|<p>Used in network interface discovery rule filters.</p>|`.*`|
|{$NET.IF.IFDESCR.NOT_MATCHES}|<p>Used in network interface discovery rule filters.</p>|`CHANGE_IF_NEEDED`|
|{$NET.IF.IFALIAS.MATCHES}|<p>Used in network interface discovery rule filters.</p>|`.*`|
|{$NET.IF.IFALIAS.NOT_MATCHES}|<p>Used in network interface discovery rule filters.</p>|`CHANGE_IF_NEEDED`|
|{$NET.IF.IFTYPE.MATCHES}|<p>Used in network interface discovery rule filters.</p>|`.*`|
|{$NET.IF.IFTYPE.NOT_MATCHES}|<p>Used in network interface discovery rule filters.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Memory utilization|<p>Please note that memory utilization is a rough estimate, since memory available is calculated as free+buffers+cached, which is not 100% accurate, but the best we can get using SNMP.</p>|Calculated|vm.memory.util[snmp]|
|Free memory|<p>MIB: UCD-SNMP-MIB</p>|SNMP agent|vm.memory.free[memAvailReal.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Memory (buffers)|<p>MIB: UCD-SNMP-MIB</p><p>Memory used by kernel buffers (Buffers in /proc/meminfo).</p>|SNMP agent|vm.memory.buffers[memBuffer.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Memory (cached)|<p>MIB: UCD-SNMP-MIB</p><p>Memory used by the page cache and slabs (Cached and Slab in /proc/meminfo).</p>|SNMP agent|vm.memory.cached[memCached.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Total memory|<p>MIB: UCD-SNMP-MIB</p><p>Total memory expressed in bytes.</p>|SNMP agent|vm.memory.total[memTotalReal.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Available memory|<p>Please note that memory utilization is a rough estimate, since memory available is calculated as free+buffers+cached, which is not 100% accurate, but the best we can get using SNMP.</p>|Calculated|vm.memory.available[snmp]|
|Total swap space|<p>MIB: UCD-SNMP-MIB</p><p>The total amount of swap space configured for this host.</p>|SNMP agent|system.swap.total[memTotalSwap.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Free swap space|<p>MIB: UCD-SNMP-MIB</p><p>The amount of swap space currently unused or available.</p>|SNMP agent|system.swap.free[memAvailSwap.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Free swap space in %|<p>The free space of the swap volume/file expressed in %.</p>|Calculated|system.swap.pfree[snmp]|
|SNMP walk block devices|<p>Block devices are discovered from UCD-DISKIO-MIB::diskIOTable (http://net-snmp.sourceforge.net/docs/mibs/ucdDiskIOMIB.html#diskIOTable).</p>|SNMP agent|vfs.dev.walk|
|Load average (1m avg)|<p>MIB: UCD-SNMP-MIB</p>|Dependent item|system.cpu.load.avg1[laLoad.1]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.laName == 'Load-1')].laLoad.first()`</p></li></ul>|
|Load average (5m avg)|<p>MIB: UCD-SNMP-MIB</p>|Dependent item|system.cpu.load.avg5[laLoad.2]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.laName == 'Load-5')].laLoad.first()`</p></li></ul>|
|Load average (15m avg)|<p>MIB: UCD-SNMP-MIB</p>|Dependent item|system.cpu.load.avg15[laLoad.3]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.laName == 'Load-15')].laLoad.first()`</p></li></ul>|
|SNMP walk system CPUs|<p>MIB: HOST-RESOURCES-MIB</p><p>Discovering system CPUs.</p>|SNMP agent|system.cpu.walk|
|SNMP walk CPU load averages|<p>MIB: UCD-SNMP-MIB</p><p>SNMP walk through laTable. The collected data used in dependent CPU load average items.</p>|SNMP agent|system.cpu.load.walk<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li></ul>|
|Number of CPUs|<p>Count the number of CPU cores by counting number of cores discovered in hrProcessorTable using LLD.</p>|Dependent item|system.cpu.num[snmp]<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Interrupts per second|<p>Number of interrupts processed.</p>|SNMP agent|system.cpu.intr[ssRawInterrupts.0]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Context switches per second|<p>The combined rate at which all processors on the computer are switched from one thread to another.</p>|SNMP agent|system.cpu.switches[ssRawContexts.0]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|SNMP walk mounted filesystems|<p>MIB: UCD-SNMP-MIB</p><p>Snmp walk through dskEntry table. Collected data used in filesystem lld and dependent item prototypes.</p>|SNMP agent|vfs.fs.walk<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li></ul>|
|Uptime (network)|<p>MIB: SNMPv2-MIB</p><p>Time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p>|SNMP agent|system.net.uptime[sysUpTime.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Uptime (hardware)|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p>|SNMP agent|system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|SNMP traps (fallback)|<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p>|SNMP trap|snmptrap.fallback|
|System location|<p>MIB: SNMPv2-MIB</p><p>Physical location of the node (e.g., `equipment room`, `3rd floor`). If not provided, the value is a zero-length string.</p>|SNMP agent|system.location[sysLocation.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System contact details|<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person. If no contact information is known, the value is the zero-length string.</p>|SNMP agent|system.contact[sysContact.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity. This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p>|SNMP agent|system.objectid[sysObjectID.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name. If the name is unknown, the value is the zero-length string.</p>|SNMP agent|system.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System description|<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p>|SNMP agent|system.descr[sysDescr.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|SNMP agent availability|<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible values:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p>|Zabbix internal|zabbix[host,snmp,available]|
|ICMP ping|<p>The host accessibility by ICMP ping.</p><p></p><p>0 - ICMP ping fails;</p><p>1 - ICMP ping successful.</p>|Simple check|icmpping|
|ICMP loss|<p>The percentage of lost packets.</p>|Simple check|icmppingloss|
|ICMP response time|<p>The ICMP ping response time (in seconds).</p>|Simple check|icmppingsec|
|Linux: SNMP walk network interfaces|<p>Discovering interfaces from IF-MIB.</p>|SNMP agent|net.if.walk|
|Linux: SNMP walk EtherLike-MIB interfaces|<p>Discovering interfaces from IF-MIB and EtherLike-MIB. Interfaces with `up(1)` Operational Status are discovered.</p>|SNMP agent|net.if.duplex.walk|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Linux: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Linux by SNMP/vm.memory.util[snmp],5m)>{$MEMORY.UTIL.MAX}`|Average|**Depends on**:<br><ul><li>Linux: Lack of available memory</li></ul>|
|Linux: Lack of available memory|<p>The system is running out of memory.</p>|`max(/Linux by SNMP/vm.memory.available[snmp],5m)<{$MEMORY.AVAILABLE.MIN} and last(/Linux by SNMP/vm.memory.total[memTotalReal.0])>0`|Average||
|Linux: High swap space usage|<p>If there is no swap configured, this trigger is ignored.</p>|`max(/Linux by SNMP/system.swap.pfree[snmp],5m)<{$SWAP.PFREE.MIN.WARN} and last(/Linux by SNMP/system.swap.total[memTotalSwap.0])>0`|Warning|**Depends on**:<br><ul><li>Linux: Lack of available memory</li><li>Linux: High memory utilization</li></ul>|
|Linux: Load average is too high|<p>The load average per CPU is too high. The system may be slow to respond.</p>|`min(/Linux by SNMP/system.cpu.load.avg1[laLoad.1],5m)/last(/Linux by SNMP/system.cpu.num[snmp])>{$LOAD_AVG_PER_CPU.MAX.WARN} and last(/Linux by SNMP/system.cpu.load.avg5[laLoad.2])>0 and last(/Linux by SNMP/system.cpu.load.avg15[laLoad.3])>0`|Average||
|Linux: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Linux by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Linux by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Linux by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Linux by SNMP/system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: No SNMP data collection</li></ul>|
|Linux: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/Linux by SNMP/system.name,#1)<>last(/Linux by SNMP/system.name,#2) and length(last(/Linux by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|Linux: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/Linux by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>Linux: Unavailable by ICMP ping</li></ul>|
|Linux: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/Linux by SNMP/icmpping,#3)=0`|High||
|Linux: High ICMP ping loss|<p>ICMP packets loss detected.</p>|`min(/Linux by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Linux by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>Linux: Unavailable by ICMP ping</li></ul>|
|Linux: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/Linux by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>Linux: High ICMP ping loss</li><li>Linux: Unavailable by ICMP ping</li></ul>|

### LLD rule Block devices discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Block devices discovery|<p>Block devices are discovered from UCD-DISKIO-MIB::diskIOTable (http://net-snmp.sourceforge.net/docs/mibs/ucdDiskIOMIB.html#diskIOTable)</p>|Dependent item|vfs.dev.discovery[snmp]<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Block devices discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DEVNAME}: Disk read rate|<p>MIB: UCD-DISKIO-MIB</p><p>The number of read accesses from this device since boot.</p>|Dependent item|vfs.dev.read.rate[diskIOReads.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.13.15.1.1.5.{#SNMPINDEX}`</p></li><li>Change per second</li></ul>|
|{#DEVNAME}: Disk write rate|<p>MIB: UCD-DISKIO-MIB</p><p>The number of write accesses from this device since boot.</p>|Dependent item|vfs.dev.write.rate[diskIOWrites.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.13.15.1.1.6.{#SNMPINDEX}`</p></li><li>Change per second</li></ul>|
|{#DEVNAME}: Disk utilization|<p>MIB: UCD-DISKIO-MIB</p><p>The 1 minute average load of disk (%)</p>|Dependent item|vfs.dev.util[diskIOLA1.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.13.15.1.1.9.{#SNMPINDEX}`</p></li></ul>|

### LLD rule CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU discovery|<p>This discovery will create set of per core CPU metrics from UCD-SNMP-MIB, using {#CPU.COUNT} in preprocessing. That's the only reason why LLD is used.</p>|Dependent item|cpu.discovery[snmp]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU idle time|<p>MIB: UCD-SNMP-MIB</p><p>Time the CPU has spent doing nothing.</p>|Dependent item|system.cpu.idle[ssCpuRawIdle.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.11.53.0`</p></li><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|CPU system time|<p>MIB: UCD-SNMP-MIB</p><p>Time the CPU has spent running the kernel and its processes.</p>|Dependent item|system.cpu.system[ssCpuRawSystem.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.11.52.0`</p></li><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|CPU user time|<p>MIB: UCD-SNMP-MIB</p><p>Time the CPU has spent running users' processes that are not niced.</p>|Dependent item|system.cpu.user[ssCpuRawUser.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.11.50.0`</p></li><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|CPU steal time|<p>MIB: UCD-SNMP-MIB</p><p>The amount of "stolen" CPU from this virtual machine by the hypervisor for other tasks, such as running another virtual machine.</p>|Dependent item|system.cpu.steal[ssCpuRawSteal.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.11.64.0`</p></li><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|CPU softirq time|<p>MIB: UCD-SNMP-MIB</p><p>Time the CPU has been servicing software interrupts.</p>|Dependent item|system.cpu.softirq[ssCpuRawSoftIRQ.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.11.61.0`</p></li><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|CPU nice time|<p>MIB: UCD-SNMP-MIB</p><p>Time the CPU has spent running users' processes that have been niced.</p>|Dependent item|system.cpu.nice[ssCpuRawNice.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.11.51.0`</p></li><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|CPU iowait time|<p>MIB: UCD-SNMP-MIB</p><p>Time the CPU has been waiting for I/O to complete.</p>|Dependent item|system.cpu.iowait[ssCpuRawWait.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.11.54.0`</p></li><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|CPU interrupt time|<p>MIB: UCD-SNMP-MIB</p><p>Time the CPU has spent servicing hardware interrupts.</p>|Dependent item|system.cpu.interrupt[ssCpuRawInterrupt.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.11.56.0`</p></li><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|CPU guest time|<p>MIB: UCD-SNMP-MIB</p><p>Time spent on running a virtual CPU for a guest operating system.</p>|Dependent item|system.cpu.guest[ssCpuRawGuest.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.11.65.0`</p></li><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|CPU guest nice time|<p>MIB: UCD-SNMP-MIB</p><p>Time spent on running a niced guest (a virtual CPU for guest operating systems under the control of the Linux kernel).</p>|Dependent item|system.cpu.guest_nice[ssCpuRawGuestNice.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.11.66.0`</p></li><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|CPU utilization|<p>CPU utilization expressed in %.</p>|Dependent item|system.cpu.util[snmp,{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `//Calculate utilization<br>return (100 - value)`</p></li></ul>|

### Trigger prototypes for CPU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Linux: High CPU utilization|<p>CPU utilization is too high. The system might be slow to respond.</p>|`min(/Linux by SNMP/system.cpu.util[snmp,{#SNMPINDEX}],5m)>{$CPU.UTIL.CRIT}`|Warning||

### LLD rule Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mounted filesystem discovery|<p>UCD-SNMP-MIB::dskEntry table discovery with storage filter</p>|Dependent item|vfs.fs.discovery[snmp]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FS [{#FSNAME}]: Get data|<p>MIB: UCD-SNMP-MIB</p><p>Intermediate data for subsequent processing.</p>|Dependent item|vfs.fs.walk.data[dskEntry.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|FS [{#FSNAME}]: Space: Used|<p>MIB: UCD-SNMP-MIB</p><p>Used storage is calculated from two portions:</p><p>  dskUsedHigh</p><p>  dskUsedLow</p><p>Together they compose 64-bit number.</p><p>Reserved space is not counted in.</p>|Dependent item|vfs.fs.used[dskUsed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|FS [{#FSNAME}]: Space: Total|<p>MIB: UCD-SNMP-MIB</p><p>Total storage is calculated from two portions:</p><p>  dskTotalHigh</p><p>  dskTotalLow</p><p>Together they compose 64-bit number.</p>|Dependent item|vfs.fs.total[dskTotal.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|FS [{#FSNAME}]: Space: Available|<p>UCD-SNMP-MIB::dskEntry</p><p>Available storage space is calculated from two portions:</p><p>  dskAvailHigh</p><p>  dskAvailLow</p><p>Together they compose 64-bit number.</p>|Dependent item|vfs.fs.free[dskAvail.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|FS [{#FSNAME}]: Space: Used, in %|<p>UCD-SNMP-MIB::dskEntry</p><p>Space utilization is calculated as the percentage of currently used space compared to the maximum available space.</p>|Dependent item|vfs.fs.pused[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|FS [{#FSNAME}]: Inodes: Free, in %|<p>MIB: UCD-SNMP-MIB</p><p>Free metadata space expressed as percentage.</p>|Dependent item|vfs.fs.inode.pfree[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Mounted filesystem discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Linux: FS [{#FSNAME}]: Space is critically low|<p>The volume's space usage exceeds the '{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}%' limit;<br>The trigger expression is based on the current used and maximum available spaces.<br>Event name represents the total volume space, which can differ from the maximum available space, depending on the filesystem type.</p>|`min(/Linux by SNMP/vfs.fs.pused[{#SNMPINDEX}],5m)>{$VFS.FS.PUSED.MAX.CRIT:"{#FSNAME}"}`|Average|**Manual close**: Yes|
|Linux: FS [{#FSNAME}]: Space is low|<p>The volume's space usage exceeds the '{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}%' limit;<br>The trigger expression is based on the current used and maximum available spaces.<br>Event name represents the total volume space, which can differ from the maximum available space, depending on the filesystem type.</p>|`min(/Linux by SNMP/vfs.fs.pused[{#SNMPINDEX}],5m)>{$VFS.FS.PUSED.MAX.WARN:"{#FSNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: FS [{#FSNAME}]: Space is critically low</li></ul>|
|Linux: FS [{#FSNAME}]: Running out of free inodes|<p>Disk writing may fail if index nodes are exhausted, leading to error messages like "No space left on device" or "Disk is full", despite available free space.</p>|`min(/Linux by SNMP/vfs.fs.inode.pfree[{#SNMPINDEX}],5m)<{$VFS.FS.INODE.PFREE.MIN.CRIT:"{#FSNAME}"}`|Average||
|Linux: FS [{#FSNAME}]: Running out of free inodes|<p>Disk writing may fail if index nodes are exhausted, leading to error messages like "No space left on device" or "Disk is full", despite available free space.</p>|`min(/Linux by SNMP/vfs.fs.inode.pfree[{#SNMPINDEX}],5m)<{$VFS.FS.INODE.PFREE.MIN.WARN:"{#FSNAME}"}`|Warning|**Depends on**:<br><ul><li>Linux: FS [{#FSNAME}]: Running out of free inodes</li></ul>|

### LLD rule Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interfaces discovery|<p>Discovering interfaces from IF-MIB.</p>|Dependent item|net.if.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}({#IFALIAS}): Operational status|<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The testing(3) state indicates that no operational packet scan be passed</p><p>- If ifAdminStatus is down(2) then ifOperStatus should be down(2)</p><p>- If ifAdminStatus is changed to up(1) then ifOperStatus should change to up(1) if the interface is ready to transmit and receive network traffic</p><p>- It should change todormant(5) if the interface is waiting for external actions (such as a serial line waiting for an incoming connection)</p><p>- It should remain in the down(2) state if and only if there is a fault that prevents it from going to the up(1) state</p><p>- It should remain in the notPresent(6) state if the interface has missing(typically, hardware) components.</p>|Dependent item|net.if.status[ifOperStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.8.{#SNMPINDEX}`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits received|<p>MIB: IF-MIB</p><p>The total number of octets received on the interface, including framing characters. This object is a 64-bit version of ifInOctets. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.in[ifHCInOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.31.1.1.1.6.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits sent|<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. This object is a 64-bit version of ifOutOctets.Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.out[ifHCOutOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.31.1.1.1.10.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.in.errors[ifInErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.14.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.out.errors[ifOutErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.20.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded|<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.out.discards[ifOutDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.19.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded|<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.in.discards[ifInDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.13.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Interface type|<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for ifType are assigned by the Internet Assigned Numbers Authority (IANA),</p><p>through updating the syntax of the IANAifType textual convention.</p>|Dependent item|net.if.type[ifType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.3.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Speed|<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in units of 1,000,000 bits per second. If this object reports a value of `n`, then the speed of the interface is somewhere in the range of `n-500,000` to `n+499,999`.</p><p>For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth.</p><p>For a sub-layer which has no concept of bandwidth, this object should be zero.</p>|Dependent item|net.if.speed[ifHighSpeed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.31.1.1.1.15.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `1000000`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Network interfaces discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Linux: Interface {#IFNAME}({#IFALIAS}): Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine the context macro to "0", marking this interface as not important. No new trigger will be fired if this interface is down.<br>3. `last(/TEMPLATE_NAME/METRIC,#1)<>last(/TEMPLATE_NAME/METRIC,#2)` - the trigger fires only if the operational status was up to (1) sometime before (so, does not fire for "eternal off" interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Linux by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/Linux by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/Linux by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`|Average|**Manual close**: Yes|
|Linux: Interface {#IFNAME}({#IFALIAS}): High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Linux by SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Linux by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) or avg(/Linux by SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Linux by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])) and last(/Linux by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Linux: Interface {#IFNAME}({#IFALIAS}): High error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Linux by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Linux by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Linux: Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Linux by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])<0 and last(/Linux by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and ( last(/Linux by SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/Linux by SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/Linux by SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/Linux by SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/Linux by SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/Linux by SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/Linux by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Linux: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|

### LLD rule EtherLike-MIB Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|EtherLike-MIB Discovery|<p>Discovering interfaces from IF-MIB and EtherLike-MIB. Interfaces with `up(1)` Operational Status are discovered.</p>|Dependent item|net.if.duplex.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for EtherLike-MIB Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}({#IFALIAS}): Duplex status|<p>MIB: EtherLike-MIB</p><p>The current mode of operation of the MAC entity. `unknown` indicates that the current duplex mode could not be determined.</p><p>Management control of the duplex mode is accomplished through the MAU MIB. When an interface does not support autonegotiation or when autonegotiation is not enabled, the duplex mode is controlled using `ifMauDefaultType`. When autonegotiation is supported and enabled, duplex mode is controlled using `ifMauAutoNegAdvertisedBits`. In either case, the currently operating duplex mode in reflected both in this object and in `ifMauType`.</p><p>Note that this object provides redundant information with `ifMauType`. Normally, redundant objects are discouraged. However, in this instance, it allows a management application to determine the duplex status of an interface without having to know every possible value of `ifMauType`. This was felt to be sufficiently valuable to justify the redundancy.</p><p>Reference: [IEEE 802.3 Std.], 30.3.1.1.32,aDuplexStatus.</p>|Dependent item|net.if.duplex[dot3StatsDuplexStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.10.7.2.1.19.{#SNMPINDEX}`</p></li></ul>|

### Trigger prototypes for EtherLike-MIB Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Linux: Interface {#IFNAME}({#IFALIAS}): In half-duplex mode|<p>Please check autonegotiation settings and cabling.</p>|`last(/Linux by SNMP/net.if.duplex[dot3StatsDuplexStatus.{#SNMPINDEX}])=2`|Warning|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

