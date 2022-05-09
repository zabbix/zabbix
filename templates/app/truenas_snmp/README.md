
# TrueNAS SNMP

## Overview

For Zabbix version: 6.0 and higher  
Template for monitoring TrueNAS by SNMP

This template was tested on:

- TrueNAS Core, version 12.0-U8

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/network_devices) for basic instructions.

1. Import template into Zabbix
2. Enable SNMP daemon at Services in TrueNAS web interface https://www.truenas.com/docs/core/services/snmp
3. Link template to the host


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>Threshold of CPU utilization for warning trigger in %.</p> |`90` |
|{$DATASET.FREE.MIN.CRIT} |<p>This macro is used for trigger expression. It can be overridden on the host or linked on the template level.</p> |`5G` |
|{$DATASET.FREE.MIN.WARN} |<p>This macro is used for trigger expression. It can be overridden on the host or linked on the template level.</p> |`5G` |
|{$DATASET.NAME.MATCHES} |<p>This macro is used in datasets discovery. Can be overridden on the host or linked template level</p> |`.+` |
|{$DATASET.NAME.NOT_MATCHES} |<p>This macro is used in datasets discovery. Can be overridden on the host or linked template level</p> |`^(boot|.+\.system(.+)?$)` |
|{$DATASET.PUSED.MAX.CRIT} |<p>Threshold of used dataset space for average severity trigger in %.</p> |`90` |
|{$DATASET.PUSED.MAX.WARN} |<p>Threshold of used dataset space for warning trigger in %.</p> |`80` |
|{$ICMP_LOSS_WARN} |<p>Threshold of ICMP packets loss for warning trigger in %.</p> |`20` |
|{$ICMP_RESPONSE_TIME_WARN} |<p>Threshold of average ICMP response time for warning trigger in seconds.</p> |`0.15` |
|{$IF.ERRORS.WARN} |<p>Threshold of error packets rate for warning trigger. Can be used with interface name as context.</p> |`2` |
|{$IF.UTIL.MAX} |<p>Threshold of interface bandwidth utilization for warning trigger in %. Can be used with interface name as context.</p> |`90` |
|{$IFCONTROL} |<p>Macro for operational state of the interface for link down trigger. Can be used with interface name as context.</p> |`1` |
|{$LOAD_AVG_PER_CPU.MAX.WARN} |<p>Load per CPU considered sustainable. Tune if needed.</p> |`1.5` |
|{$MEMORY.AVAILABLE.MIN} |<p>Threshold of available memory for trigger in bytes.</p> |`20M` |
|{$MEMORY.UTIL.MAX} |<p>Threshold of memory utilization for trigger in %</p> |`90` |
|{$NET.IF.IFADMINSTATUS.MATCHES} |<p>This macro is used in filters of network interfaces discovery rule.</p> |`^.*` |
|{$NET.IF.IFADMINSTATUS.NOT_MATCHES} |<p>Ignore down(2) administrative status</p> |`^2$` |
|{$NET.IF.IFALIAS.MATCHES} |<p>This macro is used in filters of network interfaces discovery rule.</p> |`.*` |
|{$NET.IF.IFALIAS.NOT_MATCHES} |<p>This macro is used in filters of network interfaces discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$NET.IF.IFDESCR.MATCHES} |<p>This macro used in filters of network interfaces discovery rule.</p> |`.*` |
|{$NET.IF.IFDESCR.NOT_MATCHES} |<p>This macro used in filters of network interfaces discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$NET.IF.IFNAME.MATCHES} |<p>This macro used in filters of network interfaces discovery rule.</p> |`^em[0-9]+$` |
|{$NET.IF.IFNAME.NOT_MATCHES} |<p>This macro used in filters of network interfaces discovery rule.</p> |`^$` |
|{$NET.IF.IFOPERSTATUS.MATCHES} |<p>This macro used in filters of network interfaces discovery rule.</p> |`^.*$` |
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES} |<p>Ignore notPresent(6)</p> |`^6$` |
|{$NET.IF.IFTYPE.MATCHES} |<p>This macro used in filters of network interfaces discovery rule.</p> |`.*` |
|{$NET.IF.IFTYPE.NOT_MATCHES} |<p>This macro used in filters of network interfaces discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$SNMP.TIMEOUT} |<p>The time interval for SNMP availability trigger.</p> |`5m` |
|{$SWAP.PFREE.MIN.WARN} |<p>Threshold of free swap space for warning trigger in %.</p> |`50` |
|{$TEMPERATURE.MAX.CRIT} |<p>This macro is used for trigger expression. It can be overridden on the host or linked on the template level.</p> |`65` |
|{$TEMPERATURE.MAX.WARN} |<p>This macro is used for trigger expression. It can be overridden on the host or linked on the template level.</p> |`50` |
|{$VFS.DEV.DEVNAME.MATCHES} |<p>This macro is used in block devices discovery. Can be overridden on the host or linked template level</p> |`.+` |
|{$VFS.DEV.DEVNAME.NOT_MATCHES} |<p>This macro is used in block devices discovery. Can be overridden on the host or linked template level</p> |`^(loop[0-9]*|sd[a-z][0-9]+|nbd[0-9]+|sr[0-9]+|fd[0-9]+|dm-[0-9]+|ram[0-9]+|ploop[a-z0-9]+|md[0-9]*|hcp[0-9]*|cd[0-9]*|pass[0-9]*|zram[0-9]*)` |
|{$ZPOOL.FREE.MIN.CRIT} |<p>This macro is used for trigger expression. It can be overridden on the host or linked on the template level.</p> |`5G` |
|{$ZPOOL.FREE.MIN.WARN} |<p>This macro is used for trigger expression. It can be overridden on the host or linked on the template level.</p> |`5G` |
|{$ZPOOL.PUSED.MAX.CRIT} |<p>Threshold of used pool space for average severity trigger in %.</p> |`90` |
|{$ZPOOL.PUSED.MAX.WARN} |<p>Threshold of used pool space for warning trigger in %.</p> |`80` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Block devices discovery |<p>Block devices are discovered from UCD-DISKIO-MIB::diskIOTable (http://net-snmp.sourceforge.net/docs/mibs/ucdDiskIOMIB.html#diskIOTable).</p> |SNMP |vfs.dev.discovery<p>**Filter**:</p>AND <p>- {#DEVNAME} MATCHES_REGEX `{$VFS.DEV.DEVNAME.MATCHES}`</p><p>- {#DEVNAME} NOT_MATCHES_REGEX `{$VFS.DEV.DEVNAME.NOT_MATCHES}`</p> |
|CPU discovery |<p>This discovery will create set of per core CPU metrics from UCD-SNMP-MIB, using {#CPU.COUNT} in preprocessing. That's the only reason why LLD is used.</p> |DEPENDENT |cpu.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Disks temperature discovery |<p>Disks temperature discovery from FREENAS-MIB.</p> |SNMP |truenas.disk.temp.discovery<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces discovery |<p>Discovering interfaces from IF-MIB.</p> |SNMP |net.if.discovery<p>**Filter**:</p>AND <p>- {#IFADMINSTATUS} MATCHES_REGEX `{$NET.IF.IFADMINSTATUS.MATCHES}`</p><p>- {#IFADMINSTATUS} NOT_MATCHES_REGEX `{$NET.IF.IFADMINSTATUS.NOT_MATCHES}`</p><p>- {#IFOPERSTATUS} MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.MATCHES}`</p><p>- {#IFOPERSTATUS} NOT_MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.NOT_MATCHES}`</p><p>- {#IFNAME} MATCHES_REGEX `{$NET.IF.IFNAME.MATCHES}`</p><p>- {#IFNAME} NOT_MATCHES_REGEX `{$NET.IF.IFNAME.NOT_MATCHES}`</p><p>- {#IFDESCR} MATCHES_REGEX `{$NET.IF.IFDESCR.MATCHES}`</p><p>- {#IFDESCR} NOT_MATCHES_REGEX `{$NET.IF.IFDESCR.NOT_MATCHES}`</p><p>- {#IFALIAS} MATCHES_REGEX `{$NET.IF.IFALIAS.MATCHES}`</p><p>- {#IFALIAS} NOT_MATCHES_REGEX `{$NET.IF.IFALIAS.NOT_MATCHES}`</p><p>- {#IFTYPE} MATCHES_REGEX `{$NET.IF.IFTYPE.MATCHES}`</p><p>- {#IFTYPE} NOT_MATCHES_REGEX `{$NET.IF.IFTYPE.NOT_MATCHES}`</p> |
|ZFS datasets discovery |<p>ZFS datasets discovery from FREENAS-MIB.</p> |SNMP |truenas.zfs.dataset.discovery<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p><p>**Filter**:</p>AND <p>- {#DATASET_NAME} MATCHES_REGEX `{$DATASET.NAME.MATCHES}`</p><p>- {#DATASET_NAME} NOT_MATCHES_REGEX `{$DATASET.NAME.NOT_MATCHES}`</p> |
|ZFS pools discovery |<p>ZFS pools discovery from FREENAS-MIB.</p> |SNMP |truenas.zfs.pools.discovery<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|ZFS volumes discovery |<p>ZFS volumes discovery from FREENAS-MIB.</p> |SNMP |truenas.zfs.zvols.discovery<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |TrueNAS: Interrupts per second |<p>MIB: UCD-SNMP-MIB</p><p>Number of interrupts processed.</p> |SNMP |system.cpu.intr<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|CPU |TrueNAS: Context switches per second |<p>MIB: UCD-SNMP-MIB</p><p>Number of context switches.</p> |SNMP |system.cpu.switches<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|CPU |TrueNAS: Load average (1m avg) |<p>MIB: UCD-SNMP-MIB</p><p>The 1 minute load averages.</p> |SNMP |system.cpu.load.avg1 |
|CPU |TrueNAS: Load average (5m avg) |<p>MIB: UCD-SNMP-MIB</p><p>The 5 minutes load averages.</p> |SNMP |system.cpu.load.avg5 |
|CPU |TrueNAS: Load average (15m avg) |<p>MIB: UCD-SNMP-MIB</p><p>The 15 minutes load averages.</p> |SNMP |system.cpu.load.avg15 |
|CPU |TrueNAS: Number of CPUs |<p>MIB: HOST-RESOURCES-MIB</p><p>Count the number of CPU cores by counting number of cores discovered in hrProcessorTable using LLD.</p> |SNMP |system.cpu.num<p>**Preprocessing**:</p><p>- JAVASCRIPT: `//count the number of cores return JSON.parse(value).length; `</p> |
|CPU |TrueNAS: CPU idle time |<p>MIB: UCD-SNMP-MIB</p><p>The time the CPU has spent doing nothing.</p> |SNMP |system.cpu.idle[{#SNMPINDEX}] |
|CPU |TrueNAS: CPU system time |<p>MIB: UCD-SNMP-MIB</p><p>The time the CPU has spent running the kernel and its processes.</p> |SNMP |system.cpu.system[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- JAVASCRIPT: `//to get utilization in %, divide by N, where N is number of cores. return value/{#CPU.COUNT} `</p> |
|CPU |TrueNAS: CPU user time |<p>MIB: UCD-SNMP-MIB</p><p>The time the CPU has spent running users' processes that are not niced.</p> |SNMP |system.cpu.user[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- JAVASCRIPT: `//to get utilization in %, divide by N, where N is number of cores. return value/{#CPU.COUNT} `</p> |
|CPU |TrueNAS: CPU nice time |<p>MIB: UCD-SNMP-MIB</p><p>The time the CPU has spent running users' processes that have been niced.</p> |SNMP |system.cpu.nice[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- JAVASCRIPT: `//to get utilization in %, divide by N, where N is number of cores. return value/{#CPU.COUNT} `</p> |
|CPU |TrueNAS: CPU iowait time |<p>MIB: UCD-SNMP-MIB</p><p>Amount of time the CPU has been waiting for I/O to complete.</p> |SNMP |system.cpu.iowait[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- JAVASCRIPT: `//to get utilization in %, divide by N, where N is number of cores. return value/{#CPU.COUNT} `</p> |
|CPU |TrueNAS: CPU interrupt time |<p>MIB: UCD-SNMP-MIB</p><p>The amount of time the CPU has been servicing hardware interrupts.</p> |SNMP |system.cpu.interrupt[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- JAVASCRIPT: `//to get utilization in %, divide by N, where N is number of cores. return value/{#CPU.COUNT} `</p> |
|CPU |TrueNAS: CPU utilization |<p>CPU utilization in %.</p> |DEPENDENT |system.cpu.util[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `//Calculate utilization return (100 - value) `</p> |
|General |TrueNAS: System contact details |<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person. If no contact information is known, the value is the zero-length string.</p> |SNMP |system.contact<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |TrueNAS: System description |<p>MIB: SNMPv2-MIB</p><p>System description of the host.</p> |SNMP |system.descr<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |TrueNAS: System location |<p>MIB: SNMPv2-MIB</p><p>The physical location of this node. If the location is unknown, the value is the zero-length string.</p> |SNMP |system.location<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |TrueNAS: System name |<p>MIB: SNMPv2-MIB</p><p>System host name.</p> |SNMP |system.name<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|General |TrueNAS: System object ID |<p>MIB: SNMPv2-MIB</p><p>The vendor authoritative identification of the network management subsystem contained in the entity. This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining what kind of box is being managed.</p> |SNMP |system.objectid<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Memory |TrueNAS: Free memory |<p>MIB: UCD-SNMP-MIB</p><p>The amount of real/physical memory currently unused or available.</p> |SNMP |vm.memory.free<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Memory |TrueNAS: Memory (buffers) |<p>MIB: UCD-SNMP-MIB</p><p>The total amount of real or virtual memory currently allocated for use as memory buffers.</p> |SNMP |vm.memory.buffers<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Memory |TrueNAS: Memory (cached) |<p>MIB: UCD-SNMP-MIB</p><p>The total amount of real or virtual memory currently allocated for use as cached memory.</p> |SNMP |vm.memory.cached<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Memory |TrueNAS: Total memory |<p>MIB: UCD-SNMP-MIB</p><p>Total memory in Bytes.</p> |SNMP |vm.memory.total<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Memory |TrueNAS: Available memory |<p>Please note that memory utilization is a rough estimate, since memory available is calculated as free+buffers+cached, which is not 100% accurate, but the best we can get using SNMP.</p> |CALCULATED |vm.memory.available<p>**Expression**:</p>`last(//vm.memory.free)+last(//vm.memory.buffers)+last(//vm.memory.cached)` |
|Memory |TrueNAS: Memory utilization |<p>Please note that memory utilization is a rough estimate, since memory available is calculated as free+buffers+cached, which is not 100% accurate, but the best we can get using SNMP.</p> |CALCULATED |vm.memory.util<p>**Expression**:</p>`(last(//vm.memory.total)-(last(//vm.memory.free)+last(//vm.memory.buffers)+last(//vm.memory.cached)))/last(//vm.memory.total)*100` |
|Memory |TrueNAS: Total swap space |<p>MIB: UCD-SNMP-MIB</p><p>The total amount of swap space configured for this host.</p> |SNMP |system.swap.total<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Memory |TrueNAS: Free swap space |<p>MIB: UCD-SNMP-MIB</p><p>The amount of swap space currently unused or available.</p> |SNMP |system.swap.free<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Memory |TrueNAS: Free swap space in % |<p>The free space of swap volume/file in percent.</p> |CALCULATED |system.swap.pfree<p>**Expression**:</p>`last(//system.swap.free)/last(//system.swap.total)*100` |
|Network interfaces |TrueNAS: Interface [{#IFNAME}({#IFALIAS})]: Inbound packets discarded |<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in.discards[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p> |
|Network interfaces |TrueNAS: Interface [{#IFNAME}({#IFALIAS})]: Inbound packets with errors |<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in.errors[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p> |
|Network interfaces |TrueNAS: Interface [{#IFNAME}({#IFALIAS})]: Bits received |<p>MIB: IF-MIB</p><p>The total number of octets received on the interface, including framing characters. This object is a 64-bit version of ifInOctets. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |TrueNAS: Interface [{#IFNAME}({#IFALIAS})]: Outbound packets discarded |<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out.discards[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p> |
|Network interfaces |TrueNAS: Interface [{#IFNAME}({#IFALIAS})]: Outbound packets with errors |<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out.errors[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p> |
|Network interfaces |TrueNAS: Interface [{#IFNAME}({#IFALIAS})]: Bits sent |<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. This object is a 64-bit version of ifOutOctets.Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |TrueNAS: Interface [{#IFNAME}({#IFALIAS})]: Speed |<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in units of 1,000,000 bits per second. If this object reports a value of `n' then the speed of the interface is somewhere in the range of `n-500,000' to`n+499,999'.  For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth. For a sub-layer which has no concept of bandwidth, this object should be zero.</p> |SNMP |net.if.speed[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1000000`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Network interfaces |TrueNAS: Interface [{#IFNAME}({#IFALIAS})]: Operational status |<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The testing(3) state indicates that no operational packet scan be passed</p><p>- If ifAdminStatus is down(2) then ifOperStatus should be down(2)</p><p>- If ifAdminStatus is changed to up(1) then ifOperStatus should change to up(1) if the interface is ready to transmit and receive network traffic</p><p>- It should change todormant(5) if the interface is waiting for external actions (such as a serial line waiting for an incoming connection)</p><p>- It should remain in the down(2) state if and only if there is a fault that prevents it from going to the up(1) state</p><p>- It should remain in the notPresent(6) state if the interface has missing(typically, hardware) components.</p> |SNMP |net.if.status[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |TrueNAS: Interface [{#IFNAME}({#IFALIAS})]: Interface type |<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for ifType are assigned by the Internet Assigned Numbers Authority (IANA),</p><p>through updating the syntax of the IANAifType textual convention.</p> |SNMP |net.if.type[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |TrueNAS: ICMP ping |<p>Host accessibility by ICMP.</p><p>0 - ICMP ping fails.</p><p>1 - ICMP ping successful.</p> |SIMPLE |icmpping |
|Status |TrueNAS: ICMP loss |<p>Percentage of lost packets.</p> |SIMPLE |icmppingloss |
|Status |TrueNAS: ICMP response time |<p>ICMP ping response time (in seconds).</p> |SIMPLE |icmppingsec |
|Status |TrueNAS: Uptime |<p>MIB: SNMPv2-MIB</p><p>System uptime in 'N days, hh:mm:ss' format.</p> |SNMP |system.uptime<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Status |TrueNAS: SNMP agent availability |<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible value:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p> |INTERNAL |zabbix[host,snmp,available] |
|Storage |TrueNAS: [{#DEVNAME}]: Disk read rate |<p>MIB: UCD-DISKIO-MIB</p><p>The number of read accesses from this device since boot.</p> |SNMP |vfs.dev.read.rate[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Storage |TrueNAS: [{#DEVNAME}]: Disk write rate |<p>MIB: UCD-DISKIO-MIB</p><p>The number of write accesses from this device since boot.</p> |SNMP |vfs.dev.write.rate[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Storage |TrueNAS: [{#DEVNAME}]: Disk utilization |<p>MIB: UCD-DISKIO-MIB</p><p>The 1 minute average load of disk (%).</p> |SNMP |vfs.dev.util[{#SNMPINDEX}] |
|TrueNAS |TrueNAS: ARC size |<p>MIB: FREENAS-MIB</p><p>ARC size in bytes.</p> |SNMP |truenas.zfs.arc.size<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|TrueNAS |TrueNAS: ARC metadata size |<p>MIB: FREENAS-MIB</p><p>ARC metadata size used in bytes.</p> |SNMP |truenas.zfs.arc.meta<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|TrueNAS |TrueNAS: ARC data size |<p>MIB: FREENAS-MIB</p><p>ARC data size used in bytes.</p> |SNMP |truenas.zfs.arc.data<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|TrueNAS |TrueNAS: ARC hits |<p>MIB: FREENAS-MIB</p><p>Total amount of cache hits in the ARC per second.</p> |SNMP |truenas.zfs.arc.hits<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|TrueNAS |TrueNAS: ARC misses |<p>MIB: FREENAS-MIB</p><p>Total amount of cache misses in the ARC per second.</p> |SNMP |truenas.zfs.arc.misses<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|TrueNAS |TrueNAS: ARC target size of cache |<p>MIB: FREENAS-MIB</p><p>ARC target size of cache in bytes.</p> |SNMP |truenas.zfs.arc.c<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|TrueNAS |TrueNAS: ARC target size of MRU |<p>MIB: FREENAS-MIB</p><p>ARC target size of MRU in bytes.</p> |SNMP |truenas.zfs.arc.p<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|TrueNAS |TrueNAS: ARC cache hit ratio |<p>MIB: FREENAS-MIB</p><p>ARC cache hit ration percentage.</p> |SNMP |truenas.zfs.arc.hit.ratio |
|TrueNAS |TrueNAS: ARC cache miss ratio |<p>MIB: FREENAS-MIB</p><p>ARC cache miss ration percentage.</p> |SNMP |truenas.zfs.arc.miss.ratio |
|TrueNAS |TrueNAS: L2ARC hits |<p>MIB: FREENAS-MIB</p><p>Hits to the L2 cache per second.</p> |SNMP |truenas.zfs.l2arc.hits<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|TrueNAS |TrueNAS: L2ARC misses |<p>MIB: FREENAS-MIB</p><p>Misses to the L2 cache per second.</p> |SNMP |truenas.zfs.l2arc.misses<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|TrueNAS |TrueNAS: L2ARC read rate |<p>MIB: FREENAS-MIB</p><p>Read rate from L2 cache in bytes per second.</p> |SNMP |truenas.zfs.l2arc.read<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|TrueNAS |TrueNAS: L2ARC write rate |<p>MIB: FREENAS-MIB</p><p>Write rate from L2 cache in bytes per second.</p> |SNMP |truenas.zfs.l2arc.write<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|TrueNAS |TrueNAS: L2ARC size |<p>MIB: FREENAS-MIB</p><p>L2ARC size in bytes.</p> |SNMP |truenas.zfs.l2arc.size<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|TrueNAS |TrueNAS: ZIL operations 1 second |<p>MIB: FREENAS-MIB</p><p>The ops column parsed from the command zilstat 1 1.</p> |SNMP |truenas.zfs.zil.ops1 |
|TrueNAS |TrueNAS: ZIL operations 5 seconds |<p>MIB: FREENAS-MIB</p><p>The ops column parsed from the command zilstat 5 1.</p> |SNMP |truenas.zfs.zil.ops5 |
|TrueNAS |TrueNAS: ZIL operations 10 seconds |<p>MIB: FREENAS-MIB</p><p>The ops column parsed from the command zilstat 10 1.</p> |SNMP |truenas.zfs.zil.ops10 |
|TrueNAS |TrueNAS: Pool [{#POOLNAME}]: Total space |<p>MIB: FREENAS-MIB</p><p>The size of the storage pool in bytes.</p> |SNMP |truenas.zpool.size.total[{#POOLNAME}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `{#POOL_ALLOC_UNITS}`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|TrueNAS |TrueNAS: Pool [{#POOLNAME}]: Used space |<p>MIB: FREENAS-MIB</p><p>The used size of the storage pool in bytes.</p> |SNMP |truenas.zpool.used[{#POOLNAME}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `{#POOL_ALLOC_UNITS}`</p> |
|TrueNAS |TrueNAS: Pool [{#POOLNAME}]: Available space |<p>MIB: FREENAS-MIB</p><p>The available size of the storage pool in bytes.</p> |SNMP |truenas.zpool.avail[{#POOLNAME}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `{#POOL_ALLOC_UNITS}`</p> |
|TrueNAS |TrueNAS: Pool [{#POOLNAME}]: Usage in % |<p>The used size of the storage pool in %.</p> |CALCULATED |truenas.zpool.pused[{#POOLNAME}]<p>**Expression**:</p>`last(//truenas.zpool.used[{#POOLNAME}]) * 100 / last(//truenas.zpool.size.total[{#POOLNAME}])` |
|TrueNAS |TrueNAS: Pool [{#POOLNAME}]: Health |<p>MIB: FREENAS-MIB</p><p>The current health of the containing pool, as reported by zpool status.</p> |SNMP |truenas.zpool.health[{#POOLNAME}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|TrueNAS |TrueNAS: Pool [{#POOLNAME}]: Read operations rate |<p>MIB: FREENAS-MIB</p><p>The number of read I/O operations sent to the pool or device, including metadata requests (averaged since system booted).</p> |SNMP |truenas.zpool.read.ops[{#POOLNAME}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|TrueNAS |TrueNAS: Pool [{#POOLNAME}]: Write operations rate |<p>MIB: FREENAS-MIB</p><p>The number of write I/O operations sent to the pool or device (averaged since system booted).</p> |SNMP |truenas.zpool.write.ops[{#POOLNAME}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|TrueNAS |TrueNAS: Pool [{#POOLNAME}]: Read rate |<p>MIB: FREENAS-MIB</p><p>The bandwidth of all read operations (including metadata), expressed as units per second (averaged since system booted).</p> |SNMP |truenas.zpool.read.bytes[{#POOLNAME}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `{#POOL_ALLOC_UNITS}`</p><p>- CHANGE_PER_SECOND</p> |
|TrueNAS |TrueNAS: Pool [{#POOLNAME}]: Write rate |<p>MIB: FREENAS-MIB</p><p>The bandwidth of all write operations, expressed as units per second (averaged since system booted).</p> |SNMP |truenas.zpool.write.bytes[{#POOLNAME}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `{#POOL_ALLOC_UNITS}`</p><p>- CHANGE_PER_SECOND</p> |
|TrueNAS |TrueNAS: Dataset [{#DATASET_NAME}]: Total space |<p>MIB: FREENAS-MIB</p><p>The size of the dataset in bytes.</p> |SNMP |truenas.dataset.size.total[{#DATASET_NAME}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `{#DATASET_ALLOC_UNITS}`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|TrueNAS |TrueNAS: Dataset [{#DATASET_NAME}]: Used space |<p>MIB: FREENAS-MIB</p><p>The used size of the dataset in bytes.</p> |SNMP |truenas.dataset.used[{#DATASET_NAME}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `{#DATASET_ALLOC_UNITS}`</p> |
|TrueNAS |TrueNAS: Dataset [{#DATASET_NAME}]: Available space |<p>MIB: FREENAS-MIB</p><p>The available size of the dataset in bytes.</p> |SNMP |truenas.dataset.avail[{#DATASET_NAME}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `{#DATASET_ALLOC_UNITS}`</p> |
|TrueNAS |TrueNAS: Dataset [{#DATASET_NAME}]: Usage in % |<p>The used size of the dataset in %.</p> |CALCULATED |truenas.dataset.pused[{#DATASET_NAME}]<p>**Expression**:</p>`last(//truenas.dataset.used[{#DATASET_NAME}]) * 100 / last(//truenas.dataset.size.total[{#DATASET_NAME}])` |
|TrueNAS |TrueNAS: ZFS volume [{#ZVOL_NAME}]: Total space |<p>MIB: FREENAS-MIB</p><p>The size of the ZFS volume in bytes.</p> |SNMP |truenas.zvol.size.total[{#ZVOL_NAME}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `{#ZVOL_ALLOC_UNITS}`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|TrueNAS |TrueNAS: ZFS volume [{#ZVOL_NAME}]: Used space |<p>MIB: FREENAS-MIB</p><p>The used size of the ZFS volume in bytes.</p> |SNMP |truenas.zvol.used[{#ZVOL_NAME}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `{#ZVOL_ALLOC_UNITS}`</p> |
|TrueNAS |TrueNAS: ZFS volume [{#ZVOL_NAME}]: Available space |<p>MIB: FREENAS-MIB</p><p>The available of the ZFS volume in bytes.</p> |SNMP |truenas.zvol.avail[{#ZVOL_NAME}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `{#ZVOL_ALLOC_UNITS}`</p> |
|TrueNAS |TrueNAS: Disk [{#DISK_NAME}]: Temperature |<p>MIB: FREENAS-MIB</p><p>The temperature of this HDD in mC.</p> |SNMP |truenas.disk.temp[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|TrueNAS: Load average is too high |<p>Per CPU load average is too high. Your system may be slow to respond.</p> |`min(/TrueNAS SNMP/system.cpu.load.avg1,5m)/last(/TrueNAS SNMP/system.cpu.num)>{$LOAD_AVG_PER_CPU.MAX.WARN} and last(/TrueNAS SNMP/system.cpu.load.avg5)>0 and last(/TrueNAS SNMP/system.cpu.load.avg15)>0 ` |AVERAGE | |
|TrueNAS: High CPU utilization |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/TrueNAS SNMP/system.cpu.util[{#SNMPINDEX}],5m)>{$CPU.UTIL.CRIT}` |WARNING |<p>**Depends on**:</p><p>- TrueNAS: Load average is too high</p> |
|TrueNAS: System name has changed |<p>System name has changed. Ack to close.</p> |`last(/TrueNAS SNMP/system.name,#1)<>last(/TrueNAS SNMP/system.name,#2) and length(last(/TrueNAS SNMP/system.name))>0` |INFO |<p>Manual close: YES</p> |
|TrueNAS: Lack of available memory |<p>The system is running out of memory.</p> |`min(/TrueNAS SNMP/vm.memory.available,5m)<{$MEMORY.AVAILABLE.MIN} and last(/TrueNAS SNMP/vm.memory.total)>0` |AVERAGE | |
|TrueNAS: High memory utilization |<p>The system is running out of free memory.</p> |`min(/TrueNAS SNMP/vm.memory.util,5m)>{$MEMORY.UTIL.MAX}` |AVERAGE |<p>**Depends on**:</p><p>- TrueNAS: Lack of available memory</p> |
|TrueNAS: High swap space usage |<p>This trigger is ignored, if there is no swap configured.</p> |`min(/TrueNAS SNMP/system.swap.pfree,5m)<{$SWAP.PFREE.MIN.WARN} and last(/TrueNAS SNMP/system.swap.total)>0` |WARNING |<p>**Depends on**:</p><p>- TrueNAS: High memory utilization</p><p>- TrueNAS: Lack of available memory</p> |
|TrueNAS: Interface [{#IFNAME}({#IFALIAS})]: High input error rate |<p>Recovers when below 80% of {$IF.ERRORS.WARN:"{#IFNAME}"} threshold.</p> |`min(/TrueNAS SNMP/net.if.in.errors[{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`<p>Recovery expression:</p>`max(/TrueNAS SNMP/net.if.in.errors[{#SNMPINDEX}],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8` |WARNING |<p>**Depends on**:</p><p>- TrueNAS: Interface [{#IFNAME}({#IFALIAS})]: Link down</p> |
|TrueNAS: Interface [{#IFNAME}({#IFALIAS})]: High inbound bandwidth usage |<p>The network interface utilization is close to its estimated maximum bandwidth.</p> |`(avg(/TrueNAS SNMP/net.if.in[{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/TrueNAS SNMP/net.if.speed[{#SNMPINDEX}])) and last(/TrueNAS SNMP/net.if.speed[{#SNMPINDEX}])>0 `<p>Recovery expression:</p>`avg(/TrueNAS SNMP/net.if.in[{#SNMPINDEX}],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/TrueNAS SNMP/net.if.speed[{#SNMPINDEX}])` |WARNING |<p>**Depends on**:</p><p>- TrueNAS: Interface [{#IFNAME}({#IFALIAS})]: Link down</p> |
|TrueNAS: Interface [{#IFNAME}({#IFALIAS})]: High output error rate |<p>Recovers when below 80% of {$IF.ERRORS.WARN:"{#IFNAME}"} threshold.</p> |`min(/TrueNAS SNMP/net.if.out.errors[{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`<p>Recovery expression:</p>`max(/TrueNAS SNMP/net.if.out.errors[{#SNMPINDEX}],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8` |WARNING |<p>**Depends on**:</p><p>- TrueNAS: Interface [{#IFNAME}({#IFALIAS})]: Link down</p> |
|TrueNAS: Interface [{#IFNAME}({#IFALIAS})]: High outbound bandwidth usage |<p>The network interface utilization is close to its estimated maximum bandwidth.</p> |`(avg(/TrueNAS SNMP/net.if.out[{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/TrueNAS SNMP/net.if.speed[{#SNMPINDEX}])) and last(/TrueNAS SNMP/net.if.speed[{#SNMPINDEX}])>0 `<p>Recovery expression:</p>`avg(/TrueNAS SNMP/net.if.out[{#SNMPINDEX}],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/TrueNAS SNMP/net.if.speed[{#SNMPINDEX}])` |WARNING |<p>**Depends on**:</p><p>- TrueNAS: Interface [{#IFNAME}({#IFALIAS})]: Link down</p> |
|TrueNAS: Interface [{#IFNAME}({#IFALIAS})]: Ethernet has changed to lower speed than it was before |<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Ack to close.</p> |`change(/TrueNAS SNMP/net.if.speed[{#SNMPINDEX}])<0 and last(/TrueNAS SNMP/net.if.speed[{#SNMPINDEX}])>0 and ( last(/TrueNAS SNMP/net.if.type[{#SNMPINDEX}])=6 or last(/TrueNAS SNMP/net.if.type[{#SNMPINDEX}])=7 or last(/TrueNAS SNMP/net.if.type[{#SNMPINDEX}])=11 or last(/TrueNAS SNMP/net.if.type[{#SNMPINDEX}])=62 or last(/TrueNAS SNMP/net.if.type[{#SNMPINDEX}])=69 or last(/TrueNAS SNMP/net.if.type[{#SNMPINDEX}])=117 ) and (last(/TrueNAS SNMP/net.if.status[{#SNMPINDEX}])<>2) `<p>Recovery expression:</p>`(change(/TrueNAS SNMP/net.if.speed[{#SNMPINDEX}])>0 and last(/TrueNAS SNMP/net.if.speed[{#SNMPINDEX}],#2)>0) or (last(/TrueNAS SNMP/net.if.status[{#SNMPINDEX}])=2) ` |INFO |<p>**Depends on**:</p><p>- TrueNAS: Interface [{#IFNAME}({#IFALIAS})]: Link down</p> |
|TrueNAS: Interface [{#IFNAME}({#IFALIAS})]: Link down |<p>This trigger expression works as follows:</p><p>1. Can be triggered if operations status is down.</p><p>2. {$IFCONTROL:"{#IFNAME}"}=1 - user can redefine Context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.</p> |`{$IFCONTROL:"{#IFNAME}"}=1 and (last(/TrueNAS SNMP/net.if.status[{#SNMPINDEX}])=2)` |AVERAGE | |
|TrueNAS: Unavailable by ICMP ping |<p>Last three attempts returned timeout.  Please check device connectivity.</p> |`max(/TrueNAS SNMP/icmpping,#3)=0` |HIGH | |
|TrueNAS: High ICMP ping loss |<p>ICMP packets loss detected.</p> |`min(/TrueNAS SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/TrueNAS SNMP/icmppingloss,5m)<100` |WARNING |<p>**Depends on**:</p><p>- TrueNAS: Unavailable by ICMP ping</p> |
|TrueNAS: High ICMP ping response time |<p>Average ICMP response time is too big.</p> |`avg(/TrueNAS SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}` |WARNING |<p>**Depends on**:</p><p>- TrueNAS: Unavailable by ICMP ping</p> |
|TrueNAS: has been restarted |<p>Uptime is less than 10 minutes.</p> |`last(/TrueNAS SNMP/system.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|TrueNAS: No SNMP data collection |<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p> |`max(/TrueNAS SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0` |WARNING |<p>**Depends on**:</p><p>- TrueNAS: Unavailable by ICMP ping</p> |
|TrueNAS: Pool [{#POOLNAME}]: Very high space usage |<p>Two conditions should match: First, space utilization should be above {$ZPOOL.PUSED.MAX.CRIT:"{#POOLNAME}"}%.</p><p>Second condition: The pool free space is less than {$ZPOOL.FREE.MIN.CRIT:"{#POOLNAME}"}.</p> |`min(/TrueNAS SNMP/truenas.zpool.pused[{#POOLNAME}],5m) > {$ZPOOL.PUSED.MAX.CRIT:"{#POOLNAME}"} and last(/TrueNAS SNMP/truenas.zpool.avail[{#POOLNAME}]) < {$ZPOOL.FREE.MIN.CRIT:"{#POOLNAME}"}` |AVERAGE | |
|TrueNAS: Pool [{#POOLNAME}]: High space usage |<p>Two conditions should match: First, space utilization should be above {$ZPOOL.PUSED.MAX.WARN:"{#POOLNAME}"}%.</p><p>Second condition: The pool free space is less than {$ZPOOL.FREE.MIN.WARN:"{#POOLNAME}"}.</p> |`min(/TrueNAS SNMP/truenas.zpool.pused[{#POOLNAME}],5m) > {$ZPOOL.PUSED.MAX.WARN:"{#POOLNAME}"} and last(/TrueNAS SNMP/truenas.zpool.avail[{#POOLNAME}]) < {$ZPOOL.FREE.MIN.WARN:"{#POOLNAME}"}` |WARNING |<p>**Depends on**:</p><p>- TrueNAS: Pool [{#POOLNAME}]: Very high space usage</p> |
|TrueNAS: Pool [{#POOLNAME}]: Status is not online |<p>Please check pool status.</p> |`last(/TrueNAS SNMP/truenas.zpool.health[{#POOLNAME}]) <> 0` |AVERAGE | |
|TrueNAS: Dataset [{#DATASET_NAME}]: Very high space usage |<p>Two conditions should match: First, space utilization should be above {$DATASET.PUSED.MAX.CRIT:"{#DATASET_NAME}"}%.</p><p>Second condition: The dataset free space is less than {$DATASET.FREE.MIN.CRIT:"{#POOLNAME}"}.</p> |`min(/TrueNAS SNMP/truenas.dataset.pused[{#DATASET_NAME}],5m) > {$DATASET.PUSED.MAX.CRIT:"{#DATASET_NAME}"} and last(/TrueNAS SNMP/truenas.dataset.avail[{#DATASET_NAME}]) < {$DATASET.FREE.MIN.CRIT:"{#POOLNAME}"}` |AVERAGE | |
|TrueNAS: Dataset [{#DATASET_NAME}]: High space usage |<p>Two conditions should match: First, space utilization should be above {$DATASET.PUSED.MAX.WARN:"{#DATASET_NAME}"}%.</p><p>Second condition: The dataset free space is less than {$DATASET.FREE.MIN.WARN:"{#POOLNAME}"}.</p> |`min(/TrueNAS SNMP/truenas.dataset.pused[{#DATASET_NAME}],5m) > {$DATASET.PUSED.MAX.WARN:"{#DATASET_NAME}"} and last(/TrueNAS SNMP/truenas.dataset.avail[{#DATASET_NAME}]) < {$DATASET.FREE.MIN.WARN:"{#POOLNAME}"}` |WARNING |<p>**Depends on**:</p><p>- TrueNAS: Dataset [{#DATASET_NAME}]: Very high space usage</p> |
|TrueNAS: Disk [{#DISK_NAME}]: Average disk temperature is too high |<p>Disk temperature is high.</p> |`avg(/TrueNAS SNMP/truenas.disk.temp[{#DISK_NAME}],5m) > {$TEMPERATURE.MAX.CRIT:"{#DISK_NAME}"}` |AVERAGE | |
|TrueNAS: Disk [{#DISK_NAME}]: Average disk temperature is too high |<p>Disk temperature is high.</p> |`avg(/TrueNAS SNMP/truenas.disk.temp[{#DISK_NAME}],5m) > {$TEMPERATURE.MAX.WARN:"{#DISK_NAME}"}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

