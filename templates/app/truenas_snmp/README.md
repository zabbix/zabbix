
# TrueNAS CORE by SNMP

## Overview

Template for monitoring TrueNAS CORE by SNMP.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- TrueNAS CORE 12.0-U8
- TrueNAS CORE 13.0-U5.3

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Import the template into Zabbix.
2. Enable SNMP daemon at Services in TrueNAS CORE web interface: https://www.truenas.com/docs/core/uireference/services/snmpscreen/
3. Link the template to the host.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT}|<p>Threshold of CPU utilization for warning trigger in %.</p>|`90`|
|{$LOAD_AVG_PER_CPU.MAX.WARN}|<p>Load per CPU considered sustainable. Tune if needed.</p>|`1.5`|
|{$MEMORY.AVAILABLE.MIN}|<p>Threshold of available memory for trigger in bytes.</p>|`20M`|
|{$MEMORY.UTIL.MAX}|<p>Threshold of memory utilization for trigger in %</p>|`90`|
|{$SWAP.PFREE.MIN.WARN}|<p>Threshold of free swap space for warning trigger in %.</p>|`50`|
|{$VFS.DEV.DEVNAME.MATCHES}|<p>This macro is used in block devices discovery. Can be overridden on the host or linked template level</p>|`.+`|
|{$VFS.DEV.DEVNAME.NOT_MATCHES}|<p>This macro is used in block devices discovery. Can be overridden on the host or linked template level</p>|`Macro too long. Please see the template.`|
|{$DATASET.NAME.MATCHES}|<p>This macro is used in datasets discovery. Can be overridden on the host or linked template level</p>|`.+`|
|{$DATASET.NAME.NOT_MATCHES}|<p>This macro is used in datasets discovery. Can be overridden on the host or linked template level</p>|`^(boot\|.+\.system(.+)?$)`|
|{$ZPOOL.PUSED.MAX.WARN}|<p>Threshold of used pool space for warning trigger in %.</p>|`80`|
|{$ZPOOL.FREE.MIN.WARN}|<p>This macro is used for trigger expression. It can be overridden on the host or linked on the template level.</p>|`5G`|
|{$ZPOOL.PUSED.MAX.CRIT}|<p>Threshold of used pool space for average severity trigger in %.</p>|`90`|
|{$ZPOOL.FREE.MIN.CRIT}|<p>This macro is used for trigger expression. It can be overridden on the host or linked on the template level.</p>|`5G`|
|{$DATASET.PUSED.MAX.WARN}|<p>Threshold of used dataset space for warning trigger in %.</p>|`80`|
|{$DATASET.FREE.MIN.WARN}|<p>This macro is used for trigger expression. It can be overridden on the host or linked on the template level.</p>|`5G`|
|{$DATASET.PUSED.MAX.CRIT}|<p>Threshold of used dataset space for average severity trigger in %.</p>|`90`|
|{$DATASET.FREE.MIN.CRIT}|<p>This macro is used for trigger expression. It can be overridden on the host or linked on the template level.</p>|`5G`|
|{$TEMPERATURE.MAX.WARN}|<p>This macro is used for trigger expression. It can be overridden on the host or linked on the template level.</p>|`50`|
|{$TEMPERATURE.MAX.CRIT}|<p>This macro is used for trigger expression. It can be overridden on the host or linked on the template level.</p>|`65`|
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
|SNMP walk block devices|<p>MIB: UCD-DISKIO-MIB</p><p>SNMP walk through diskIOTable. The collected data used in filesystem LLD and dependent item prototypes.</p>|SNMP agent|vfs.dev.walk|
|SNMP walk system CPUs|<p>MIB: HOST-RESOURCES-MIB</p><p>Discovering system CPUs.</p>|SNMP agent|system.cpu.walk|
|SNMP walk ZFS datasets|<p>MIB: FREENAS-MIB</p><p>SNMP walk through datasetTable. The collected data used in ZFS dataset LLD and dependent item prototypes.</p>|SNMP agent|truenas.zfs.dataset.walk|
|SNMP walk ZFS pools|<p>MIB: FREENAS-MIB</p><p>SNMP walk through zpoolTable. The collected data used in ZFS pool LLD and dependent item prototypes.</p>|SNMP agent|truenas.zfs.pools.walk|
|SNMP walk ZFS volumes|<p>MIB: FREENAS-MIB</p><p>SNMP walk through zvolTable. The collected data used in ZFS volume LLD and dependent item prototypes.</p>|SNMP agent|truenas.zfs.zvols.walk|
|SNMP walk disk temperatures|<p>MIB: FREENAS-MIB</p><p>SNMP walk through hddTempTable. The collected data used in disk temperature LLD and dependent item prototypes.</p>|SNMP agent|truenas.disk.temp.walk|
|SNMP walk ARC L2ARC|<p>MIB: FREENAS-MIB</p><p>SNMP walk through arc and l2arc. The collected data used in dependent ARC and L2ARC items.</p>|SNMP agent|truenas.zfs.arc_l2arc.walk|
|SNMP walk ZIL|<p>MIB: FREENAS-MIB</p><p>SNMP walk through zil. The collected data used in dependent ZIL items.</p>|SNMP agent|truenas.zfs.zil.walk|
|SNMP walk CPU load averages|<p>MIB: UCD-SNMP-MIB</p><p>SNMP walk through laTable. The collected data used in dependent CPU load average items.</p>|SNMP agent|system.cpu.load.walk<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li></ul>|
|Interrupts per second|<p>MIB: UCD-SNMP-MIB</p><p>Number of interrupts processed.</p>|SNMP agent|system.cpu.intr<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Context switches per second|<p>MIB: UCD-SNMP-MIB</p><p>Number of context switches.</p>|SNMP agent|system.cpu.switches<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Load average (1m avg)|<p>MIB: UCD-SNMP-MIB</p><p>The 1 minute load averages.</p>|Dependent item|system.cpu.load.avg1<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.laName == 'Load-1')].laLoad.first()`</p></li></ul>|
|Load average (5m avg)|<p>MIB: UCD-SNMP-MIB</p><p>The 5 minutes load averages.</p>|Dependent item|system.cpu.load.avg5<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.laName == 'Load-5')].laLoad.first()`</p></li></ul>|
|Load average (15m avg)|<p>MIB: UCD-SNMP-MIB</p><p>The 15 minutes load averages.</p>|Dependent item|system.cpu.load.avg15<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.laName == 'Load-15')].laLoad.first()`</p></li></ul>|
|Number of CPUs|<p>MIB: HOST-RESOURCES-MIB</p><p>Count the number of CPU cores by counting number of cores discovered in hrProcessorTable using LLD.</p>|Dependent item|system.cpu.num<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Free memory|<p>MIB: UCD-SNMP-MIB</p><p>The amount of real/physical memory currently unused or available.</p>|SNMP agent|vm.memory.free<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Memory (buffers)|<p>MIB: UCD-SNMP-MIB</p><p>The total amount of real or virtual memory currently allocated for use as memory buffers.</p>|SNMP agent|vm.memory.buffers<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Memory (cached)|<p>MIB: UCD-SNMP-MIB</p><p>The total amount of real or virtual memory currently allocated for use as cached memory.</p>|SNMP agent|vm.memory.cached<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Total memory|<p>MIB: UCD-SNMP-MIB</p><p>The total memory expressed in bytes.</p>|SNMP agent|vm.memory.total<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Available memory|<p>Please note that memory utilization is a rough estimate, since memory available is calculated as free+buffers+cached, which is not 100% accurate, but the best we can get using SNMP.</p>|Calculated|vm.memory.available|
|Memory utilization|<p>Please note that memory utilization is a rough estimate, since memory available is calculated as free+buffers+cached, which is not 100% accurate, but the best we can get using SNMP.</p>|Calculated|vm.memory.util|
|Total swap space|<p>MIB: UCD-SNMP-MIB</p><p>The total amount of swap space configured for this host.</p>|SNMP agent|system.swap.total<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Free swap space|<p>MIB: UCD-SNMP-MIB</p><p>The amount of swap space currently unused or available.</p>|SNMP agent|system.swap.free<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Free swap space in %|<p>The free space of the swap volume/file expressed in %.</p>|Calculated|system.swap.pfree<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `100`</p></li></ul>|
|ARC size|<p>MIB: FREENAS-MIB</p><p>ARC size in bytes.</p>|Dependent item|truenas.zfs.arc.size<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.4.1.0`</p></li><li><p>Custom multiplier: `1024`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ARC metadata size|<p>MIB: FREENAS-MIB</p><p>ARC metadata size used in bytes.</p>|Dependent item|truenas.zfs.arc.meta<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.4.2.0`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|ARC data size|<p>MIB: FREENAS-MIB</p><p>ARC data size used in bytes.</p>|Dependent item|truenas.zfs.arc.data<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.4.3.0`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|
|ARC hits|<p>MIB: FREENAS-MIB</p><p>Total amount of cache hits in the ARC per second.</p>|Dependent item|truenas.zfs.arc.hits<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.4.4.0`</p></li><li>Change per second</li></ul>|
|ARC misses|<p>MIB: FREENAS-MIB</p><p>Total amount of cache misses in the ARC per second.</p>|Dependent item|truenas.zfs.arc.misses<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.4.5.0`</p></li><li>Change per second</li></ul>|
|ARC target size of cache|<p>MIB: FREENAS-MIB</p><p>ARC target size of cache in bytes.</p>|Dependent item|truenas.zfs.arc.c<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.4.6.0`</p></li><li><p>Custom multiplier: `1024`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ARC target size of MRU|<p>MIB: FREENAS-MIB</p><p>ARC target size of MRU in bytes.</p>|Dependent item|truenas.zfs.arc.p<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.4.7.0`</p></li><li><p>Custom multiplier: `1024`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ARC cache hit ratio|<p>MIB: FREENAS-MIB</p><p>ARC cache hit ration percentage.</p>|Dependent item|truenas.zfs.arc.hit.ratio<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.4.9.0`</p></li></ul>|
|ARC cache miss ratio|<p>MIB: FREENAS-MIB</p><p>ARC cache miss ration percentage.</p>|Dependent item|truenas.zfs.arc.miss.ratio<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.4.10.0`</p></li></ul>|
|L2ARC hits|<p>MIB: FREENAS-MIB</p><p>Hits to the L2 cache per second.</p>|Dependent item|truenas.zfs.l2arc.hits<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.5.1.0`</p></li><li>Change per second</li></ul>|
|L2ARC misses|<p>MIB: FREENAS-MIB</p><p>Misses to the L2 cache per second.</p>|Dependent item|truenas.zfs.l2arc.misses<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.5.2.0`</p></li><li>Change per second</li></ul>|
|L2ARC read rate|<p>MIB: FREENAS-MIB</p><p>Read rate from L2 cache in bytes per second.</p>|Dependent item|truenas.zfs.l2arc.read<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.5.3.0`</p></li><li>Change per second</li></ul>|
|L2ARC write rate|<p>MIB: FREENAS-MIB</p><p>Write rate from L2 cache in bytes per second.</p>|Dependent item|truenas.zfs.l2arc.write<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.5.4.0`</p></li><li>Change per second</li></ul>|
|L2ARC size|<p>MIB: FREENAS-MIB</p><p>L2ARC size in bytes.</p>|Dependent item|truenas.zfs.l2arc.size<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.5.5.0`</p></li><li><p>Custom multiplier: `1024`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|ZIL operations 1 second|<p>MIB: FREENAS-MIB</p><p>The ops column parsed from the command zilstat 1 1.</p>|Dependent item|truenas.zfs.zil.ops1<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.6.1.0`</p></li></ul>|
|ZIL operations 5 seconds|<p>MIB: FREENAS-MIB</p><p>The ops column parsed from the command zilstat 5 1.</p>|Dependent item|truenas.zfs.zil.ops5<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.6.2.0`</p></li></ul>|
|ZIL operations 10 seconds|<p>MIB: FREENAS-MIB</p><p>The ops column parsed from the command zilstat 10 1.</p>|Dependent item|truenas.zfs.zil.ops10<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.6.3.0`</p></li></ul>|
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
|TrueNAS CORE: SNMP walk network interfaces|<p>Discovering interfaces from IF-MIB.</p>|SNMP agent|net.if.walk|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|TrueNAS CORE: Load average is too high|<p>The load average per CPU is too high. The system may be slow to respond.</p>|`min(/TrueNAS CORE by SNMP/system.cpu.load.avg1,5m)/last(/TrueNAS CORE by SNMP/system.cpu.num)>{$LOAD_AVG_PER_CPU.MAX.WARN} and last(/TrueNAS CORE by SNMP/system.cpu.load.avg5)>0 and last(/TrueNAS CORE by SNMP/system.cpu.load.avg15)>0`|Average||
|TrueNAS CORE: Lack of available memory|<p>The system is running out of memory.</p>|`min(/TrueNAS CORE by SNMP/vm.memory.available,5m)<{$MEMORY.AVAILABLE.MIN} and last(/TrueNAS CORE by SNMP/vm.memory.total)>0`|Average||
|TrueNAS CORE: High memory utilization|<p>The system is running out of free memory.</p>|`min(/TrueNAS CORE by SNMP/vm.memory.util,5m)>{$MEMORY.UTIL.MAX}`|Average|**Depends on**:<br><ul><li>TrueNAS CORE: Lack of available memory</li></ul>|
|TrueNAS CORE: High swap space usage|<p>If there is no swap configured, this trigger is ignored.</p>|`min(/TrueNAS CORE by SNMP/system.swap.pfree,5m)<{$SWAP.PFREE.MIN.WARN} and last(/TrueNAS CORE by SNMP/system.swap.total)>0`|Warning|**Depends on**:<br><ul><li>TrueNAS CORE: Lack of available memory</li><li>TrueNAS CORE: High memory utilization</li></ul>|
|TrueNAS CORE: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/TrueNAS CORE by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/TrueNAS CORE by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/TrueNAS CORE by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/TrueNAS CORE by SNMP/system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>TrueNAS CORE: No SNMP data collection</li></ul>|
|TrueNAS CORE: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/TrueNAS CORE by SNMP/system.name,#1)<>last(/TrueNAS CORE by SNMP/system.name,#2) and length(last(/TrueNAS CORE by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|TrueNAS CORE: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/TrueNAS CORE by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>TrueNAS CORE: Unavailable by ICMP ping</li></ul>|
|TrueNAS CORE: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/TrueNAS CORE by SNMP/icmpping,#3)=0`|High||
|TrueNAS CORE: High ICMP ping loss|<p>ICMP packets loss detected.</p>|`min(/TrueNAS CORE by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/TrueNAS CORE by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>TrueNAS CORE: Unavailable by ICMP ping</li></ul>|
|TrueNAS CORE: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/TrueNAS CORE by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>TrueNAS CORE: High ICMP ping loss</li><li>TrueNAS CORE: Unavailable by ICMP ping</li></ul>|

### LLD rule CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU discovery|<p>This discovery will create set of per core CPU metrics from UCD-SNMP-MIB, using {#CPU.COUNT} in preprocessing. That's the only reason why LLD is used.</p>|Dependent item|cpu.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU idle time|<p>MIB: UCD-SNMP-MIB</p><p>The time the CPU has spent doing nothing.</p>|Dependent item|system.cpu.idle[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.11.11.0`</p></li></ul>|
|CPU system time|<p>MIB: UCD-SNMP-MIB</p><p>The time the CPU has spent running the kernel and its processes.</p>|Dependent item|system.cpu.system[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.11.52.0`</p></li><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|CPU user time|<p>MIB: UCD-SNMP-MIB</p><p>The time the CPU has spent running users' processes that are not niced.</p>|Dependent item|system.cpu.user[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.11.50.0`</p></li><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|CPU nice time|<p>MIB: UCD-SNMP-MIB</p><p>The time the CPU has spent running users' processes that have been niced.</p>|Dependent item|system.cpu.nice[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.11.51.0`</p></li><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|CPU iowait time|<p>MIB: UCD-SNMP-MIB</p><p>The amount of time the CPU has been waiting for I/O to complete.</p>|Dependent item|system.cpu.iowait[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.11.54.0`</p></li><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|CPU interrupt time|<p>MIB: UCD-SNMP-MIB</p><p>The amount of time the CPU has been servicing hardware interrupts.</p>|Dependent item|system.cpu.interrupt[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.11.56.0`</p></li><li>Change per second</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|CPU utilization|<p>The CPU utilization expressed in %.</p>|Dependent item|system.cpu.util[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `//Calculate utilization<br>return (100 - value)`</p></li></ul>|

### Trigger prototypes for CPU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|TrueNAS CORE: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/TrueNAS CORE by SNMP/system.cpu.util[{#SNMPINDEX}],5m)>{$CPU.UTIL.CRIT}`|Warning|**Depends on**:<br><ul><li>TrueNAS CORE: Load average is too high</li></ul>|

### LLD rule Block devices discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Block devices discovery|<p>Block devices are discovered from UCD-DISKIO-MIB::diskIOTable (http://net-snmp.sourceforge.net/docs/mibs/ucdDiskIOMIB.html#diskIOTable).</p>|Dependent item|vfs.dev.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Block devices discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|TrueNAS CORE: [{#DEVNAME}]: Disk read rate|<p>MIB: UCD-DISKIO-MIB</p><p>The number of read accesses from this device since boot.</p>|Dependent item|vfs.dev.read.rate[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.13.15.1.1.5.{#SNMPINDEX}`</p></li><li>Change per second</li></ul>|
|TrueNAS CORE: [{#DEVNAME}]: Disk write rate|<p>MIB: UCD-DISKIO-MIB</p><p>The number of write accesses from this device since boot.</p>|Dependent item|vfs.dev.write.rate[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.13.15.1.1.6.{#SNMPINDEX}`</p></li><li>Change per second</li></ul>|
|TrueNAS CORE: [{#DEVNAME}]: Disk utilization|<p>MIB: UCD-DISKIO-MIB</p><p>The 1 minute average load of disk (%).</p>|Dependent item|vfs.dev.util[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2021.13.15.1.1.9.{#SNMPINDEX}`</p></li></ul>|

### LLD rule ZFS pools discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZFS pools discovery|<p>ZFS pools discovery from FREENAS-MIB.</p>|Dependent item|truenas.zfs.pools.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for ZFS pools discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Pool [{#POOLNAME}]: Total space|<p>MIB: FREENAS-MIB</p><p>The size of the storage pool in bytes.</p>|Dependent item|truenas.zpool.size.total[{#POOLNAME}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.1.1.1.4.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `{#POOL_ALLOC_UNITS}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Pool [{#POOLNAME}]: Used space|<p>MIB: FREENAS-MIB</p><p>The used size of the storage pool in bytes.</p>|Dependent item|truenas.zpool.used[{#POOLNAME}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.1.1.1.5.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `{#POOL_ALLOC_UNITS}`</p></li></ul>|
|Pool [{#POOLNAME}]: Available space|<p>MIB: FREENAS-MIB</p><p>The available size of the storage pool in bytes.</p>|Dependent item|truenas.zpool.avail[{#POOLNAME}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.1.1.1.6.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `{#POOL_ALLOC_UNITS}`</p></li></ul>|
|Pool [{#POOLNAME}]: Usage in %|<p>The used size of the storage pool in %.</p>|Calculated|truenas.zpool.pused[{#POOLNAME}]|
|Pool [{#POOLNAME}]: Health|<p>MIB: FREENAS-MIB</p><p>The current health of the containing pool, as reported by zpool status.</p>|Dependent item|truenas.zpool.health[{#POOLNAME}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.1.1.1.7.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Pool [{#POOLNAME}]: Read operations rate|<p>MIB: FREENAS-MIB</p><p>The number of read I/O operations sent to the pool or device, including metadata requests (averaged since system booted).</p>|Dependent item|truenas.zpool.read.ops[{#POOLNAME}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.1.1.1.8.{#SNMPINDEX}`</p></li><li>Change per second</li></ul>|
|Pool [{#POOLNAME}]: Write operations rate|<p>MIB: FREENAS-MIB</p><p>The number of write I/O operations sent to the pool or device (averaged since system booted).</p>|Dependent item|truenas.zpool.write.ops[{#POOLNAME}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.1.1.1.9.{#SNMPINDEX}`</p></li><li>Change per second</li></ul>|
|Pool [{#POOLNAME}]: Read rate|<p>MIB: FREENAS-MIB</p><p>The bandwidth of all read operations (including metadata), expressed as units per second (averaged since system booted).</p>|Dependent item|truenas.zpool.read.bytes[{#POOLNAME}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.1.1.1.10.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `{#POOL_ALLOC_UNITS}`</p></li><li>Change per second</li></ul>|
|Pool [{#POOLNAME}]: Write rate|<p>MIB: FREENAS-MIB</p><p>The bandwidth of all write operations, expressed as units per second (averaged since system booted).</p>|Dependent item|truenas.zpool.write.bytes[{#POOLNAME}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.1.1.1.11.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `{#POOL_ALLOC_UNITS}`</p></li><li>Change per second</li></ul>|

### Trigger prototypes for ZFS pools discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|TrueNAS CORE: Pool [{#POOLNAME}]: Very high space usage|<p>Two conditions should match:<br>1. The first condition - utilization of the space should be above `{$ZPOOL.PUSED.MAX.CRIT:"{#POOLNAME}"}%.`<br>2. The second condition - the pool free space is less than `{$ZPOOL.FREE.MIN.CRIT:"{#POOLNAME}"}`.</p>|`min(/TrueNAS CORE by SNMP/truenas.zpool.pused[{#POOLNAME}],5m) > {$ZPOOL.PUSED.MAX.CRIT:"{#POOLNAME}"} and last(/TrueNAS CORE by SNMP/truenas.zpool.avail[{#POOLNAME}]) < {$ZPOOL.FREE.MIN.CRIT:"{#POOLNAME}"}`|Average||
|TrueNAS CORE: Pool [{#POOLNAME}]: High space usage|<p>Two conditions should match:<br>1. The first condition - utilization of the space should be above `{$ZPOOL.PUSED.MAX.WARN:"{#POOLNAME}"}%.`<br>2. The second condition - the pool free space is less than `{$ZPOOL.FREE.MIN.WARN:"{#POOLNAME}"}`.</p>|`min(/TrueNAS CORE by SNMP/truenas.zpool.pused[{#POOLNAME}],5m) > {$ZPOOL.PUSED.MAX.WARN:"{#POOLNAME}"} and last(/TrueNAS CORE by SNMP/truenas.zpool.avail[{#POOLNAME}]) < {$ZPOOL.FREE.MIN.WARN:"{#POOLNAME}"}`|Warning|**Depends on**:<br><ul><li>TrueNAS CORE: Pool [{#POOLNAME}]: Very high space usage</li></ul>|
|TrueNAS CORE: Pool [{#POOLNAME}]: Status is not online|<p>Please check pool status.</p>|`last(/TrueNAS CORE by SNMP/truenas.zpool.health[{#POOLNAME}]) <> 0`|Average||

### LLD rule ZFS datasets discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZFS datasets discovery|<p>ZFS datasets discovery from FREENAS-MIB.</p>|Dependent item|truenas.zfs.dataset.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for ZFS datasets discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dataset [{#DATASET_NAME}]: Total space|<p>MIB: FREENAS-MIB</p><p>The size of the dataset in bytes.</p>|Dependent item|truenas.dataset.size.total[{#DATASET_NAME}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.2.1.1.4.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `{#DATASET_ALLOC_UNITS}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Dataset [{#DATASET_NAME}]: Used space|<p>MIB: FREENAS-MIB</p><p>The used size of the dataset in bytes.</p>|Dependent item|truenas.dataset.used[{#DATASET_NAME}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.2.1.1.5.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `{#DATASET_ALLOC_UNITS}`</p></li></ul>|
|Dataset [{#DATASET_NAME}]: Available space|<p>MIB: FREENAS-MIB</p><p>The available size of the dataset in bytes.</p>|Dependent item|truenas.dataset.avail[{#DATASET_NAME}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.2.1.1.6.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `{#DATASET_ALLOC_UNITS}`</p></li></ul>|
|Dataset [{#DATASET_NAME}]: Usage in %|<p>The used size of the dataset in %.</p>|Calculated|truenas.dataset.pused[{#DATASET_NAME}]|

### Trigger prototypes for ZFS datasets discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|TrueNAS CORE: Dataset [{#DATASET_NAME}]: Very high space usage|<p>Two conditions should match:<br>1. The first condition - utilization of the space should be above `{$DATASET.PUSED.MAX.CRIT:"{#DATASET_NAME}"}%.`<br>2. The second condition - the dataset free space is less than `{$DATASET.FREE.MIN.CRIT:"{#POOLNAME}"}`.</p>|`min(/TrueNAS CORE by SNMP/truenas.dataset.pused[{#DATASET_NAME}],5m) > {$DATASET.PUSED.MAX.CRIT:"{#DATASET_NAME}"} and last(/TrueNAS CORE by SNMP/truenas.dataset.avail[{#DATASET_NAME}]) < {$DATASET.FREE.MIN.CRIT:"{#POOLNAME}"}`|Average||
|TrueNAS CORE: Dataset [{#DATASET_NAME}]: High space usage|<p>Two conditions should match:<br>1. The first condition - utilization of the space should be above `{$DATASET.PUSED.MAX.WARN:"{#DATASET_NAME}"}%.`<br>2. The second condition - the dataset free space is less than `{$DATASET.FREE.MIN.WARN:"{#POOLNAME}"}`.</p>|`min(/TrueNAS CORE by SNMP/truenas.dataset.pused[{#DATASET_NAME}],5m) > {$DATASET.PUSED.MAX.WARN:"{#DATASET_NAME}"} and last(/TrueNAS CORE by SNMP/truenas.dataset.avail[{#DATASET_NAME}]) < {$DATASET.FREE.MIN.WARN:"{#POOLNAME}"}`|Warning|**Depends on**:<br><ul><li>TrueNAS CORE: Dataset [{#DATASET_NAME}]: Very high space usage</li></ul>|

### LLD rule ZFS volumes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ZFS volumes discovery|<p>ZFS volumes discovery from FREENAS-MIB.</p>|Dependent item|truenas.zfs.zvols.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for ZFS volumes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|TrueNAS CORE: ZFS volume [{#ZVOL_NAME}]: Total space|<p>MIB: FREENAS-MIB</p><p>The size of the ZFS volume in bytes.</p>|Dependent item|truenas.zvol.size.total[{#ZVOL_NAME}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.3.1.1.4.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `{#ZVOL_ALLOC_UNITS}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|TrueNAS CORE: ZFS volume [{#ZVOL_NAME}]: Used space|<p>MIB: FREENAS-MIB</p><p>The used size of the ZFS volume in bytes.</p>|Dependent item|truenas.zvol.used[{#ZVOL_NAME}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.3.1.1.5.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `{#ZVOL_ALLOC_UNITS}`</p></li></ul>|
|TrueNAS CORE: ZFS volume [{#ZVOL_NAME}]: Available space|<p>MIB: FREENAS-MIB</p><p>The available of the ZFS volume in bytes.</p>|Dependent item|truenas.zvol.avail[{#ZVOL_NAME}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.1.3.1.1.6.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `{#ZVOL_ALLOC_UNITS}`</p></li></ul>|

### LLD rule Disks temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disks temperature discovery|<p>Disks temperature discovery from FREENAS-MIB.</p>|Dependent item|truenas.disk.temp.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Disks temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk [{#DISK_NAME}]': Temperature|<p>MIB: FREENAS-MIB</p><p>The temperature of this HDD in mC.</p>|Dependent item|truenas.disk.temp[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.50536.3.1.3.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Disks temperature discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|TrueNAS CORE: Disk [{#DISK_NAME}]': Average disk temperature is too high|<p>Disk temperature is high.</p>|`avg(/TrueNAS CORE by SNMP/truenas.disk.temp[{#DISK_NAME}],5m) > {$TEMPERATURE.MAX.CRIT:"{#DISK_NAME}"}`|Average||
|TrueNAS CORE: Disk [{#DISK_NAME}]': Average disk temperature is too high|<p>Disk temperature is high.</p>|`avg(/TrueNAS CORE by SNMP/truenas.disk.temp[{#DISK_NAME}],5m) > {$TEMPERATURE.MAX.WARN:"{#DISK_NAME}"}`|Warning||

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
|TrueNAS CORE: Interface {#IFNAME}({#IFALIAS}): Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine the context macro to "0", marking this interface as not important. No new trigger will be fired if this interface is down.<br>3. `last(/TEMPLATE_NAME/METRIC,#1)<>last(/TEMPLATE_NAME/METRIC,#2)` - the trigger fires only if the operational status was up to (1) sometime before (so, does not fire for "eternal off" interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/TrueNAS CORE by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/TrueNAS CORE by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/TrueNAS CORE by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`|Average|**Manual close**: Yes|
|TrueNAS CORE: Interface {#IFNAME}({#IFALIAS}): High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/TrueNAS CORE by SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/TrueNAS CORE by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) or avg(/TrueNAS CORE by SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/TrueNAS CORE by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])) and last(/TrueNAS CORE by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>TrueNAS CORE: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|TrueNAS CORE: Interface {#IFNAME}({#IFALIAS}): High error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/TrueNAS CORE by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/TrueNAS CORE by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>TrueNAS CORE: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|TrueNAS CORE: Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/TrueNAS CORE by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])<0 and last(/TrueNAS CORE by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and ( last(/TrueNAS CORE by SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/TrueNAS CORE by SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/TrueNAS CORE by SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/TrueNAS CORE by SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/TrueNAS CORE by SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/TrueNAS CORE by SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/TrueNAS CORE by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>TrueNAS CORE: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

