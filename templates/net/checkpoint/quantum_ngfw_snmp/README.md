
# Check Point Next Generation Firewall by SNMP

## Overview

This template is designed for the effortless deployment of Check Point Next Generation Firewall monitoring by Zabbix via SNMP and doesn't require any external scripts.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Check Point 4800 Appliance Next Generation Firewall

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

> Refer to vendor [documentation](https://support.checkpoint.com/results/sk/sk90860).

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT}|<p>Threshold of CPU utilization for the Warning trigger in %.</p>|`90`|
|{$LOAD_AVG_PER_CPU.MAX.WARN}|<p>Load per CPU considered sustainable. Change if needed.</p>|`1.5`|
|{$ICMP_LOSS_WARN}|<p>Threshold of ICMP packet loss for the Warning trigger in %.</p>|`20`|
|{$ICMP_RESPONSE_TIME_WARN}|<p>Threshold of average ICMP response time for the Warning trigger in seconds.</p>|`0.15`|
|{$SNMP.TIMEOUT}|<p>Time interval for the SNMP availability trigger.</p>|`5m`|
|{$MEMORY.UTIL.MAX}|<p>Warning threshold for the item "Physical memory: Memory utilization".</p>|`90`|
|{$FW.DROPPED.PACKETS.TH}|<p>Used in Firewall discovery.</p>|`0`|
|{$DISK.FREE.MIN.CRIT}|<p>Critical threshold of disk space usage.</p>|`5G`|
|{$DISK.FREE.MIN.WARN}|<p>Warning threshold of disk space usage.</p>|`10G`|
|{$DISK.PUSED.MAX.WARN}|<p>Disk utilization threshold for Warning trigger in %.</p>|`80`|
|{$DISK.PUSED.MAX.CRIT}|<p>Disk utilization threshold for Critical trigger in %.</p>|`90`|
|{$DISK.NAME.MATCHES}|<p>Used in Storage discovery. Can be overridden on the host or linked template level.</p>|`.+`|
|{$DISK.NAME.NOT_MATCHES}|<p>Used in Storage discovery. Can be overridden on the host or linked template level.</p>|`^(/dev\|/sys\|/run\|/proc\|.+/shm$)`|
|{$VPN.NAME.MATCHES}|<p>Used in VPN discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$VPN.NAME.NOT_MATCHES}|<p>Used in VPN discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$VPN.STATE.CONTROL}|<p>Used in the "Tunnel down" trigger. Can be used with the interface name as context.</p>|`1`|
|{$NET.IF.ERRORS.WARN}|<p>Threshold of error packet rate for the Warning trigger. Can be used with the interface name as context.</p>|`2`|
|{$NET.IF.UTIL.MAX}|<p>Threshold of interface bandwidth utilization for the Warning trigger in %. Can be used with interface name as context.</p>|`95`|
|{$NET.IF.CONTROL}|<p>Macro for the interface operational state for the "Link down" trigger. Can be used with the interface name as context.</p>|`1`|
|{$NET.IF.IFADMINSTATUS.MATCHES}|<p>Used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFADMINSTATUS.NOT_MATCHES}|<p>Used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`^2$`|
|{$NET.IF.IFDESCR.MATCHES}|<p>Used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFDESCR.NOT_MATCHES}|<p>Used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$NET.IF.IFNAME.MATCHES}|<p>Used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>Used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$NET.IF.IFOPERSTATUS.MATCHES}|<p>Used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES}|<p>Used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`^6$`|
|{$NET.IF.IFTYPE.MATCHES}|<p>Used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFTYPE.NOT_MATCHES}|<p>Used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$NET.IF.IFALIAS.MATCHES}|<p>Used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFALIAS.NOT_MATCHES}|<p>Used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$TEMP.NAME.MATCHES}|<p>Used in Temperature discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$TEMP.NAME.NOT_MATCHES}|<p>Used in Temperature discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$TEMP.VALUE.LOW}|<p>Used in Temperature discovery. Can be overridden on the host or linked template level.</p>|`5`|
|{$TEMP.VALUE.CRIT}|<p>Used in Temperature discovery. Can be overridden on the host or linked template level.</p>|`75`|
|{$TEMP.VALUE.WARN}|<p>Used in Temperature discovery. Can be overridden on the host or linked template level.</p>|`65`|
|{$VOLT.NAME.MATCHES}|<p>Used in Voltage discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$VOLT.NAME.NOT_MATCHES}|<p>Used in Voltage discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$SW.NAME.MATCHES}|<p>Used in Software blade discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$SW.NAME.NOT_MATCHES}|<p>Used in Software blade discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$LICENSE.EXPIRY.WARN}|<p>Number of days until the license expires.</p>|`7`|
|{$LICENSE.CONTROL}|<p>Used in Software blade discovery. Can be overridden on the host or linked template level.</p>|`1`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Appliance product name|<p>MIB: CHECKPOINT-MIB</p><p>Appliance product name.</p>|SNMP agent|system.hw.model<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Appliance serial number|<p>MIB: CHECKPOINT-MIB</p><p>Appliance serial number.</p>|SNMP agent|system.hw.serialnumber<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Appliance manufacturer|<p>MIB: CHECKPOINT-MIB</p><p>Appliance manufacturer.</p>|SNMP agent|system.hw.manufacturer<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Remote Access users|<p>MIB: CHECKPOINT-MIB</p><p>Number of remote access users.</p>|SNMP agent|remote.users.number<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.length()`</p></li></ul>|
|System contact details|<p>MIB: SNMPv2-MIB</p><p>Name and contact information of the contact person for the node. If not provided, the value is a zero-length string.</p>|SNMP agent|system.contact<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System description|<p>MIB: SNMPv2-MIB</p><p>Full name and version identification of the system's hardware type, software operating system, and networking software.</p>|SNMP agent|system.descr<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System location|<p>MIB: SNMPv2-MIB</p><p>Physical location of the node (e.g., `equipment room`, `3rd floor`). If not provided, the value is a zero-length string.</p>|SNMP agent|system.location<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for the node (the node's fully-qualified domain name). If not provided, the value is a zero-length string.</p>|SNMP agent|system.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the entity as part of the vendor's SMI enterprises subtree with the prefix 1.3.6.1.4.1 (e.g., a vendor with the identifier 1.3.6.1.4.1.4242 might assign a system object with the OID 1.3.6.1.4.1.4242.1.1).</p>|SNMP agent|system.objectid<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System uptime|<p>MIB: HOST-RESOURCES-V2-MIB</p><p>Time since the network management portion of the system was last re-initialized.</p>|SNMP agent|system.uptime<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Number of CPUs|<p>MIB: CHECKPOINT-MIB</p><p>Number of processors.</p>|SNMP agent|system.cpu.num<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU utilization|<p>MIB: CHECKPOINT-MIB</p><p>CPU utilization per core in %.</p>|SNMP agent|system.cpu.util|
|Load average (1m avg)|<p>MIB: UCD-SNMP-MIB</p><p>Average number of processes being executed or waiting over the last minute.</p>|Dependent item|system.cpu.load.avg1<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.laName == 'Load-1')].laLoad.first()`</p></li></ul>|
|Load average (5m avg)|<p>MIB: UCD-SNMP-MIB</p><p>Average number of processes being executed or waiting over the last 5 minutes.</p>|Dependent item|system.cpu.load.avg5<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.laName == 'Load-5')].laLoad.first()`</p></li></ul>|
|Load average (15m avg)|<p>MIB: UCD-SNMP-MIB</p><p>Average number of processes being executed or waiting over the last 15 minutes.</p>|Dependent item|system.cpu.load.avg15<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.laName == 'Load-15')].laLoad.first()`</p></li></ul>|
|CPU user time|<p>MIB: CHECKPOINT-MIB</p><p>Average time the CPU has spent running user processes that are not niced.</p>|SNMP agent|system.cpu.user|
|CPU system time|<p>MIB: CHECKPOINT-MIB</p><p>Average time the CPU has spent running the kernel and its processes.</p>|SNMP agent|system.cpu.system|
|CPU idle time|<p>MIB: CHECKPOINT-MIB</p><p>Average time the CPU has spent doing nothing.</p>|SNMP agent|system.cpu.idle|
|Context switches per second|<p>MIB: UCD-SNMP-MIB</p><p>Number of context switches per second.</p>|SNMP agent|system.cpu.switches<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|CPU interrupts per second|<p>MIB: CHECKPOINT-MIB</p><p>Number of interrupts processed per second.</p>|SNMP agent|system.cpu.intr|
|Total memory|<p>MIB: CHECKPOINT-MIB</p><p>Total real memory in bytes. Memory used by applications.</p>|SNMP agent|vm.memory.total|
|Active memory|<p>MIB: CHECKPOINT-MIB</p><p>Active real memory (memory used by applications that is not cached to the disk) in bytes.</p>|SNMP agent|vm.memory.active|
|Free memory|<p>MIB: CHECKPOINT-MIB</p><p>Free memory available for applications in bytes.</p>|SNMP agent|vm.memory.free|
|Used memory|<p>Used real memory calculated by total real memory and free real memory in bytes.</p>|Calculated|vm.memory.used|
|Memory utilization|<p>Memory utilization in %.</p>|Calculated|vm.memory.util|
|Encrypted packets per second|<p>MIB: CHECKPOINT-MIB</p><p>Number of encrypted packets per second.</p>|SNMP agent|vpn.packets.encrypted<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Decrypted packets per second|<p>MIB: CHECKPOINT-MIB</p><p>Number of decrypted packets per second.</p>|SNMP agent|vpn.packets.decrypted<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|ICMP ping|<p>Host accessibility by ICMP.</p><p>0 - ICMP ping fails.</p><p>1 - ICMP ping successful.</p>|Simple check|icmpping|
|ICMP loss|<p>Percentage of lost packets.</p>|Simple check|icmppingloss|
|ICMP response time|<p>ICMP ping response time (in seconds).</p>|Simple check|icmppingsec|
|SNMP agent availability|<p>Availability of SNMP checks on the host. The value of this item corresponds to the availability icons in the host list.</p><p></p><p>Possible values:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p>|Zabbix internal|zabbix[host,snmp,available]|
|SNMP traps (fallback)|<p>Used to collect all SNMP traps unmatched by other `snmptrap` items.</p>|SNMP trap|snmptrap.fallback|
|SNMP walk network interfaces|<p>Used for discovering interfaces from IF-MIB.</p>|SNMP agent|net.if.walk|
|SNMP walk CPU|<p>Used for discovering CPU from CHECKPOINT-MIB.</p>|SNMP agent|system.cpu.walk|
|SNMP walk CPU load averages|<p>MIB: UCD-SNMP-MIB</p><p>SNMP walk through laTable. The collected data used in dependent CPU load average items.</p>|SNMP agent|system.cpu.load.walk<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li></ul>|
|SNMP walk VPN tunnels|<p>Used for discovering VPN tunnels from CHECKPOINT-MIB.</p>|SNMP agent|vpn.tunnel.walk|
|SNMP walk disks|<p>Used for discovering storage disks from CHECKPOINT-MIB.</p>|SNMP agent|vfs.fs.walk|
|SNMP walk temperature sensors|<p>Used for discovering temperature sensors from CHECKPOINT-MIB.</p>|SNMP agent|sensor.temp.walk|
|SNMP walk fan sensors|<p>Used for discovering fan sensors from CHECKPOINT-MIB.</p>|SNMP agent|sensor.fan.walk|
|SNMP walk voltage sensors|<p>Used for discovering voltage sensors from CHECKPOINT-MIB.</p>|SNMP agent|sensor.volt.walk|
|SNMP walk PSU sensors|<p>Used for discovering power supply sensors from CHECKPOINT-MIB.</p>|SNMP agent|sensor.psu.walk|
|SNMP walk svn features|<p>Used for discovering software blades and features from CHECKPOINT-MIB.</p>|SNMP agent|svn.feature.walk|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Check Point: Device has been replaced|<p>The device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Check Point Next Generation Firewall by SNMP/system.hw.serialnumber,#1)<>last(/Check Point Next Generation Firewall by SNMP/system.hw.serialnumber,#2) and length(last(/Check Point Next Generation Firewall by SNMP/system.hw.serialnumber))>0`|Info|**Manual close**: Yes|
|Check Point: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/Check Point Next Generation Firewall by SNMP/system.name,#1)<>last(/Check Point Next Generation Firewall by SNMP/system.name,#2) and length(last(/Check Point Next Generation Firewall by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|Check Point: Device has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Check Point Next Generation Firewall by SNMP/system.uptime)<10m`|Info|**Manual close**: Yes|
|Check Point: High CPU utilization|<p>CPU utilization is too high. The system might be slow to respond.</p>|`min(/Check Point Next Generation Firewall by SNMP/system.cpu.util,5m)>{$CPU.UTIL.CRIT}`|Warning||
|Check Point: Load average is too high|<p>The load average per CPU is too high. The system may be slow to respond.</p>|`min(/Check Point Next Generation Firewall by SNMP/system.cpu.load.avg1,5m)/last(/Check Point Next Generation Firewall by SNMP/system.cpu.num)>{$LOAD_AVG_PER_CPU.MAX.WARN} and last(/Check Point Next Generation Firewall by SNMP/system.cpu.load.avg5)>0 and last(/Check Point Next Generation Firewall by SNMP/system.cpu.load.avg15)>0`|Average||
|Check Point: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Check Point Next Generation Firewall by SNMP/vm.memory.util,5m)>{$MEMORY.UTIL.MAX}`|Average||
|Check Point: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/Check Point Next Generation Firewall by SNMP/icmpping,#3)=0`|High||
|Check Point: High ICMP ping loss|<p>ICMP packet loss detected.</p>|`min(/Check Point Next Generation Firewall by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Check Point Next Generation Firewall by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>Check Point: Unavailable by ICMP ping</li></ul>|
|Check Point: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/Check Point Next Generation Firewall by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>Check Point: Unavailable by ICMP ping</li><li>Check Point: High ICMP ping loss</li></ul>|
|Check Point: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/Check Point Next Generation Firewall by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>Check Point: Unavailable by ICMP ping</li></ul>|

### LLD rule Firewall discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Firewall discovery|<p>This discovery will create a set of firewall metrics from CHECKPOINT-MIB if the firewall is installed.</p>|SNMP agent|fw.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Firewall discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Check Point Firewall: Firewall filter name{#SINGLETON}|<p>MIB: CHECKPOINT-MIB</p><p>Name of the firewall filter.</p>|SNMP agent|fw.filter.name[fwFilterName.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Check Point Firewall: Firewall filter install time{#SINGLETON}|<p>MIB: CHECKPOINT-MIB</p><p>Last install time of the firewall filter.</p>|SNMP agent|fw.filter.installed[fwFilterDate.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Check Point Firewall: Firewall version{#SINGLETON}|<p>MIB: CHECKPOINT-MIB</p><p>Current version of the firewall.</p>|SNMP agent|fw.version[fwVersion.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Check Point Firewall: Accepted packets per second{#SINGLETON}|<p>MIB: CHECKPOINT-MIB</p><p>Number of accepted packets per second.</p>|SNMP agent|fw.accepted[fwAccepted.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Check Point Firewall: Rejected packets per second{#SINGLETON}|<p>MIB: CHECKPOINT-MIB</p><p>Number of rejected packets per second.</p>|SNMP agent|fw.rejected[fwRejected.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Check Point Firewall: Dropped packets per second{#SINGLETON}|<p>MIB: CHECKPOINT-MIB</p><p>Number of dropped packets per second.</p>|SNMP agent|fw.dropped[fwDropped.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Check Point Firewall: Logged packets per second{#SINGLETON}|<p>MIB: CHECKPOINT-MIB</p><p>Number of logged packets per second.</p>|SNMP agent|fw.logged[fwLogged.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Check Point Firewall: SIC Trust State{#SINGLETON}|<p>MIB: CHECKPOINT-MIB</p><p>Firewall SIC Trust State.</p>|SNMP agent|fw.sic.trust.state[fwSICTrustState.{#SNMPINDEX}]|
|Check Point Firewall: Utilized drops number per second{#SINGLETON}|<p>MIB: CHECKPOINT-MIB</p><p>Number of dropped packets per second due to instance being fully utilized.</p>|SNMP agent|fw.utilized.drops[fwFullyUtilizedDrops.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Check Point Firewall: Concurrent connections{#SINGLETON}|<p>MIB: CHECKPOINT-MIB</p><p>Number of concurrent IPv6 and IPv4 connections.</p>|SNMP agent|fw.conn.num[fwNumConn.{#SNMPINDEX}]|
|Check Point Firewall: Peak concurrent connections{#SINGLETON}|<p>MIB: CHECKPOINT-MIB</p><p>Peak number of concurrent connections since last reboot.</p>|SNMP agent|fw.conn.num.peak[fwPeakNumConn.{#SNMPINDEX}]|

### Trigger prototypes for Firewall discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Check Point Firewall: Instance is currently fully utilized|<p>This trigger uses the number of dropped packets, an increase of which indicates that the instance is fully utilized.</p>|`avg(/Check Point Next Generation Firewall by SNMP/fw.utilized.drops[fwFullyUtilizedDrops.{#SNMPINDEX}],5m)>{$FW.DROPPED.PACKETS.TH}`|High||

### LLD rule VPN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VPN discovery|<p>For discovering VPN tunnels from CHECKPOINT-MIB.</p>|Dependent item|vpn.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for VPN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VPN {#VPN.NAME}: Peer IP address|<p>MIB: CHECKPOINT-MIB</p><p>VPN peer IP address.</p>|Dependent item|vpn.tunnel.peer_ip[tunnelPeerIpAddr.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.500.9002.1.1.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|VPN {#VPN.NAME}: Tunnel state|<p>MIB: CHECKPOINT-MIB</p><p>VPN tunnel state:</p><p>3 - active</p><p>4 - destroy</p><p>129 - idle</p><p>130 - phase1</p><p>131 - down</p><p>132 - init</p>|Dependent item|vpn.tunnel.state[tunnelState.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.500.9002.1.3.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VPN {#VPN.NAME}: Community|<p>MIB: CHECKPOINT-MIB</p><p>VPN tunnel community.</p>|Dependent item|vpn.tunnel.community[tunnelCommunity.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.500.9002.1.4.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|VPN {#VPN.NAME}: Tunnel interface|<p>MIB: CHECKPOINT-MIB</p><p>VPN tunnel interface.</p>|Dependent item|vpn.tunnel.netif[tunnelInterface.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.500.9002.1.6.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|VPN {#VPN.NAME}: Source IP|<p>MIB: CHECKPOINT-MIB</p><p>Source IP address.</p>|Dependent item|vpn.tunnel.src_ip[tunnelSourceIpAddr.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.500.9002.1.7.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|VPN {#VPN.NAME}: Link priority|<p>MIB: CHECKPOINT-MIB</p><p>Link priority.</p>|Dependent item|vpn.tunnel.priority[tunnelLinkPriority.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.500.9002.1.8.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VPN {#VPN.NAME}: Probing state|<p>MIB: CHECKPOINT-MIB</p><p>VPN tunnel probing state:</p><p>0 - unknown</p><p>1 - alive</p><p>2 - dead</p>|Dependent item|vpn.tunnel.prob_state[tunnelProbState.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.500.9002.1.9.{#SNMPINDEX}`</p></li></ul>|
|VPN {#VPN.NAME}: Peer type|<p>MIB: CHECKPOINT-MIB</p><p>VPN peer type.</p>|Dependent item|vpn.tunnel.peer_type[tunnelPeerType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.500.9002.1.10.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|VPN {#VPN.NAME}: Tunnel type|<p>MIB: CHECKPOINT-MIB</p><p>VPN tunnel type.</p>|Dependent item|vpn.tunnel.type[tunnelType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.500.9002.1.11.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for VPN discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Check Point: VPN {#VPN.NAME}: Tunnel down|<p>This trigger expression works as follows:<br>1. It can be triggered if the current tunnel state is down.<br>2. `{$VPN.STATE.CONTROL:"{#VPN.NAME}"}=1` - a user can redefine the context macro to "0", marking this notification as not important. No new trigger will be fired if this tunnel is down.</p>|`{$VPN.STATE.CONTROL:"{#VPN.NAME}"}=1 and last(/Check Point Next Generation Firewall by SNMP/vpn.tunnel.state[tunnelState.{#SNMPINDEX}])=131`|Average|**Manual close**: Yes|

### LLD rule CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU discovery|<p>For discovering CPU from CHECKPOINT-MIB.</p>|Dependent item|cpu.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU Core {#CPU.ID}: CPU user time|<p>MIB: CHECKPOINT-MIB</p><p>The time the CPU `{#CPU.ID}` has spent running user processes that are not niced.</p>|Dependent item|system.core.user[multiProcUserTime.{#CPU.ID}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.1.6.7.5.1.4.{#SNMPINDEX}`</p></li></ul>|
|CPU Core {#CPU.ID}: CPU system time|<p>MIB: CHECKPOINT-MIB</p><p>The time the CPU `{#CPU.ID}` has spent running the kernel and its processes.</p>|Dependent item|system.core.system[multiProcSystemTime.{#CPU.ID}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.1.6.7.5.1.3.{#SNMPINDEX}`</p></li></ul>|
|CPU Core {#CPU.ID}: CPU idle time|<p>MIB: CHECKPOINT-MIB</p><p>The time the CPU `{#CPU.ID}` has spent doing nothing.</p>|Dependent item|system.core.idle[multiProcIdleTime.{#CPU.ID}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.1.6.7.5.1.2.{#SNMPINDEX}`</p></li></ul>|
|CPU Core {#CPU.ID}: CPU utilization|<p>MIB: CHECKPOINT-MIB</p><p>CPU `{#CPU.ID}` utilization in %.</p>|Dependent item|system.core.util[multiProcUsage.{#CPU.ID}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.1.6.7.5.1.5.{#SNMPINDEX}`</p></li></ul>|

### LLD rule Storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage discovery|<p>For discovering storage disks from CHECKPOINT-MIB.</p>|Dependent item|vfs.fs.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DISK.NAME}: Total disk space|<p>MIB: CHECKPOINT-MIB</p><p>Total disk size in bytes.</p>|Dependent item|vfs.fs.total[multiDiskSize.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.1.6.7.6.1.3.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#DISK.NAME}: Used disk space|<p>MIB: CHECKPOINT-MIB</p><p>Amount of disk used in bytes.</p>|Dependent item|vfs.fs.used[multiDiskUsed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.1.6.7.6.1.4.{#SNMPINDEX}`</p></li></ul>|
|{#DISK.NAME}: Free disk space|<p>MIB: CHECKPOINT-MIB</p><p>Free disk capacity in bytes.</p>|Dependent item|vfs.fs.free[multiDiskFreeTotalBytes.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.1.6.7.6.1.5.{#SNMPINDEX}`</p></li></ul>|
|{#DISK.NAME}: Available disk space|<p>MIB: CHECKPOINT-MIB</p><p>Available free disk (not reserved by the OS) in bytes.</p>|Dependent item|vfs.fs.avail[multiDiskFreeAvailableBytes.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.1.6.7.6.1.7.{#SNMPINDEX}`</p></li></ul>|
|{#DISK.NAME}: Disk space utilization|<p>Space utilization calculated by the free percentage metric `multiDiskFreeTotalPercent`, expressed in %</p>|Dependent item|vfs.fs.pused[multiDiskUsagePercent.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.1.6.7.6.1.6.{#SNMPINDEX}`</p></li><li><p>JavaScript: `return 100 - Number(value);`</p></li></ul>|

### Trigger prototypes for Storage discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Check Point: {#DISK.NAME}: Disk space is critically low|<p>Two conditions should match:<br>1. The first condition - utilization of the space should be above `{$DISK.PUSED.MAX.CRIT:"{#DISK.NAME}"}`.<br>2. The second condition should be one of the following:<br>- the disk free space is less than `{$DISK.FREE.MIN.CRIT:"{#DISK.NAME}"}`;<br>- the disk will be full in less than 24 hours.</p>|`last(/Check Point Next Generation Firewall by SNMP/vfs.fs.pused[multiDiskUsagePercent.{#SNMPINDEX}])>{$DISK.PUSED.MAX.CRIT:"{#DISK.NAME}"} and (last(/Check Point Next Generation Firewall by SNMP/vfs.fs.total[multiDiskSize.{#SNMPINDEX}])-last(/Check Point Next Generation Firewall by SNMP/vfs.fs.used[multiDiskUsed.{#SNMPINDEX}]))<{$DISK.FREE.MIN.CRIT:"{#DISK.NAME}"}`|Average|**Manual close**: Yes|
|Check Point: {#DISK.NAME}: Disk space is low|<p>Two conditions should match:<br>1. The first condition - utilization of the space should be above `{$DISK.PUSED.MAX.WARN:"{#DISK.NAME}"}`.<br>2. The second condition should be one of the following:<br>- the disk free space is less than `{$DISK.FREE.MIN.WARN:"{#DISK.NAME}"}`;<br>- the disk will be full in less than 24 hours.</p>|`last(/Check Point Next Generation Firewall by SNMP/vfs.fs.pused[multiDiskUsagePercent.{#SNMPINDEX}])>{$DISK.PUSED.MAX.WARN:"{#DISK.NAME}"} and (last(/Check Point Next Generation Firewall by SNMP/vfs.fs.total[multiDiskSize.{#SNMPINDEX}])-last(/Check Point Next Generation Firewall by SNMP/vfs.fs.used[multiDiskUsed.{#SNMPINDEX}]))<{$DISK.FREE.MIN.WARN:"{#DISK.NAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Check Point: {#DISK.NAME}: Disk space is critically low</li></ul>|

### LLD rule Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interfaces discovery|<p>For discovering interfaces from IF-MIB.</p>|Dependent item|net.if.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}({#IFALIAS}): Operational status|<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The `testing(3)` state indicates that no operational packets can be passed.</p><p>- If `ifAdminStatus` is `down(2)`, then `ifOperStatus` should be `down(2)`.</p><p>- If `ifAdminStatus` is changed to `up(1)`, then `ifOperStatus` should change to `up(1)` if the interface is ready to transmit and receive network traffic.</p><p>- It should change to `dormant(5)` if the interface is waiting for external actions (such as a serial line waiting for an incoming connection).</p><p>- It should remain in the `down(2)` state if and only if there is a fault that prevents it from going to the `up(1)` state.</p><p>- It should remain in the `notPresent(6)` state if the interface has missing (typically, hardware) components.</p>|Dependent item|net.if.status[ifOperStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.8.{#SNMPINDEX}`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits received|<p>MIB: IF-MIB</p><p>The total number of octets received on the interface, including framing characters. This object is a 64-bit version of `ifInOctets`.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|net.if.in[ifInOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.31.1.1.1.6.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits sent|<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. This object is a 64-bit version of `ifOutOctets`.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|net.if.out[ifOutOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.31.1.1.1.10.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces - the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.</p><p>For character-oriented or fixed-length interfaces - the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|net.if.in.errors[ifInErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.14.{#SNMPINDEX}`</p></li><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces - the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.</p><p>For character-oriented or fixed-length interfaces - the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|net.if.out.errors[ifOutErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.20.{#SNMPINDEX}`</p></li><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded|<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded even though no errors had been detected to prevent their being deliverable to a higher-layer protocol. One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|net.if.out.discards[ifOutDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.19.{#SNMPINDEX}`</p></li><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded|<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded even though no errors had been detected to prevent their being deliverable to a higher-layer protocol. One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of `ifCounterDiscontinuityTime`.</p>|Dependent item|net.if.in.discards[ifInDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.13.{#SNMPINDEX}`</p></li><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Interface type|<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for `ifType` are assigned by the Internet Assigned Numbers Authority (IANA) through updating the syntax of the IANAifType textual convention.</p>|Dependent item|net.if.type[ifType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.3.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Speed|<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in units of 1,000,000 bits per second.</p><p>If this object reports a value of `n`, then the speed of the interface is somewhere in the range of `n-500,000` to `n+499,999`.</p><p>For interfaces that do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth.</p><p>For a sub-layer which has no concept of bandwidth, this object should be zero.</p>|Dependent item|net.if.speed[ifSpeed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.31.1.1.1.15.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `1000000`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Network interfaces discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Check Point: Interface {#IFNAME}({#IFALIAS}): Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the interface link status is down.<br>2. `{$NET.IF.CONTROL:"{#IFNAME}"}=1` - a user can redefine the context macro to "0", marking this interface as not important. No new trigger will be fired if this interface link is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the interface link status was up to "1" sometime before.<br><br>WARNING: If closed manually, it will not fire again on the next poll because of `diff`.</p>|`{$NET.IF.CONTROL:"{#IFNAME}"}=1 and last(/Check Point Next Generation Firewall by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/Check Point Next Generation Firewall by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/Check Point Next Generation Firewall by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`|Average|**Manual close**: Yes|
|Check Point: Interface {#IFNAME}({#IFALIAS}): High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Check Point Next Generation Firewall by SNMP/net.if.in[ifInOctets.{#SNMPINDEX}],15m)>({$NET.IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Check Point Next Generation Firewall by SNMP/net.if.speed[ifSpeed.{#SNMPINDEX}]) or avg(/Check Point Next Generation Firewall by SNMP/net.if.out[ifOutOctets.{#SNMPINDEX}],15m)>({$NET.IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Check Point Next Generation Firewall by SNMP/net.if.speed[ifSpeed.{#SNMPINDEX}])) and last(/Check Point Next Generation Firewall by SNMP/net.if.speed[ifSpeed.{#SNMPINDEX}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Check Point: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Check Point: Interface {#IFNAME}({#IFALIAS}): High error rate|<p>It recovers when it is below 80% of the `{$NET.IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Check Point Next Generation Firewall by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$NET.IF.ERRORS.WARN:"{#IFNAME}"} or min(/Check Point Next Generation Firewall by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$NET.IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Check Point: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Check Point: Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Check Point Next Generation Firewall by SNMP/net.if.speed[ifSpeed.{#SNMPINDEX}])<0 and last(/Check Point Next Generation Firewall by SNMP/net.if.speed[ifSpeed.{#SNMPINDEX}])>0 and ( last(/Check Point Next Generation Firewall by SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/Check Point Next Generation Firewall by SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/Check Point Next Generation Firewall by SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/Check Point Next Generation Firewall by SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/Check Point Next Generation Firewall by SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/Check Point Next Generation Firewall by SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/Check Point Next Generation Firewall by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Check Point: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|

### LLD rule Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature discovery|<p>For discovering temperature sensors from CHECKPOINT-MIB.</p>|Dependent item|temperature.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SENSOR.NAME}: Temperature|<p>MIB: CHECKPOINT-MIB</p><p>Current temperature reading in degrees Celsius from the hardware component's temperature sensor.</p>|Dependent item|sensor.temp.value[tempertureSensorValue.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.1.6.7.8.1.1.3.{#SNMPINDEX}`</p></li></ul>|

### Trigger prototypes for Temperature discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Check Point: {#SENSOR.NAME}: Temperature is above critical threshold|<p>This trigger uses temperature sensor values.</p>|`avg(/Check Point Next Generation Firewall by SNMP/sensor.temp.value[tempertureSensorValue.{#SNMPINDEX}],5m)>{$TEMP.VALUE.CRIT:"{#SENSOR.NAME}"}`|High||
|Check Point: {#SENSOR.NAME}: Temperature is above warning threshold|<p>This trigger uses temperature sensor values.</p>|`avg(/Check Point Next Generation Firewall by SNMP/sensor.temp.value[tempertureSensorValue.{#SNMPINDEX}],5m)>{$TEMP.VALUE.WARN:"{#SENSOR.NAME}"}`|Warning|**Depends on**:<br><ul><li>Check Point: {#SENSOR.NAME}: Temperature is above critical threshold</li></ul>|
|Check Point: {#SENSOR.NAME}: Temperature is too low|<p>This trigger uses temperature sensor values.</p>|`avg(/Check Point Next Generation Firewall by SNMP/sensor.temp.value[tempertureSensorValue.{#SNMPINDEX}],5m)<{$TEMP.VALUE.LOW:"{#SENSOR.NAME}"}`|Average||

### LLD rule FAN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN discovery|<p>For discovering fan sensors from CHECKPOINT-MIB.</p>|Dependent item|fan.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for FAN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN {#SNMPINDEX}: Fan status|<p>MIB: CHECKPOINT-MIB</p><p>Current status of the fan tray.</p>|Dependent item|sensor.fan.status[fanSpeedSensorStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.1.6.7.8.2.1.6.{#SNMPINDEX}`</p></li></ul>|
|FAN {#SNMPINDEX}: Fan speed|<p>MIB: CHECKPOINT-MIB</p><p>Current speed of the fan.</p>|Dependent item|sensor.fan.speed[fanSpeedSensorValue.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.1.6.7.8.2.1.3.{#SNMPINDEX}`</p></li></ul>|

### Trigger prototypes for FAN discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Check Point: FAN {#SNMPINDEX}: Fan speed is out of range|<p>Please check the fan unit.</p>|`count(/Check Point Next Generation Firewall by SNMP/sensor.fan.status[fanSpeedSensorStatus.{#SNMPINDEX}],#3,"eq",1)=3`|Average||

### LLD rule Voltage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Voltage discovery|<p>For discovering voltage sensors from CHECKPOINT-MIB.</p>|Dependent item|voltage.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Voltage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SENSOR.NAME}: Voltage value|<p>MIB: CHECKPOINT-MIB</p><p>Most recent measurement obtained by the agent for this sensor.</p>|Dependent item|sensor.volt.value[voltageSensorValue.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.1.6.7.8.3.1.3.{#SNMPINDEX}`</p></li></ul>|

### LLD rule PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU discovery|<p>For discovering power supply sensors from CHECKPOINT-MIB.</p>|Dependent item|psu.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU {#SNMPINDEX}: Power supply status|<p>MIB: CHECKPOINT-MIB</p><p>Power supply status.</p>|Dependent item|sensor.psu.status[powerSupplyStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.1.6.7.9.1.1.2.{#SNMPINDEX}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for PSU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Check Point: PSU {#SNMPINDEX}: Power supply is in down state|<p>Please check the power supply unit for errors.</p>|`count(/Check Point Next Generation Firewall by SNMP/sensor.psu.status[powerSupplyStatus.{#SNMPINDEX}],#3,"eq",1)=3`|Average||

### LLD rule Software blades discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Software blades discovery|<p>For discovering software blades and features from CHECKPOINT-MIB.</p>|Dependent item|svn.sw.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Software blades discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SW.NAME}: License state|<p>MIB: CHECKPOINT-MIB</p><p>Current license state of the software blade.</p>|Dependent item|svn.sw.license.state[licensingState.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.1.6.18.1.1.5.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#SW.NAME}: License expiration date|<p>MIB: CHECKPOINT-MIB</p><p>Expiration date for the license of the software blade. Doesn't return a value if the license doesn't have an expiration date.</p>|Dependent item|svn.sw.license.exp_date[licensingExpirationDate.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.1.6.18.1.1.6.{#SNMPINDEX}`</p></li><li><p>Does not match regular expression: `^0$`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#SW.NAME}: Software blade status|<p>MIB: CHECKPOINT-MIB</p><p>Current software blade status.</p>|Dependent item|svn.sw.status[licensingBladeActive.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.1.6.18.1.1.8.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|{#SW.NAME}: License total quota|<p>MIB: CHECKPOINT-MIB</p><p>Total quota amount for the license of the software blade.</p>|Dependent item|svn.sw.license.quota.total[licensingTotalQuota.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.1.6.18.1.1.9.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#SW.NAME}: License used quota|<p>MIB: CHECKPOINT-MIB</p><p>Used quota amount for the license of the software blade.</p>|Dependent item|svn.sw.license.quota.used[licensingUsedQuota.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.2620.1.6.18.1.1.10.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Software blades discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Check Point: {#SW.NAME}: License expires soon|<p>This trigger expression works as follows:<br>1. It can be triggered if the license expires soon.<br>2. `{$LICENSE.CONTROL:"{#SW.NAME}"}=1` - a user can redefine the context macro to "0", marking the current license as not important. No new trigger will be fired if this license expires.</p>|`{$LICENSE.CONTROL:"{#SW.NAME}"}=1 and (last(/Check Point Next Generation Firewall by SNMP/svn.sw.license.exp_date[licensingExpirationDate.{#SNMPINDEX}]) - now()) / 86400 < {$LICENSE.EXPIRY.WARN:"{#SW.NAME}"} and last(/Check Point Next Generation Firewall by SNMP/svn.sw.license.exp_date[licensingExpirationDate.{#SNMPINDEX}]) > now()`|Warning|**Manual close**: Yes|
|Check Point: {#SW.NAME}: License has been expired|<p>This trigger expression works as follows:<br>1. It can be triggered if the license has been expired.<br>2. `{$LICENSE.CONTROL:"{#SW.NAME}"}=1` - a user can redefine the context macro to "0", marking the current license as not important. No new trigger will be fired if this license is expired.</p>|`{$LICENSE.CONTROL:"{#SW.NAME}"}=1 and last(/Check Point Next Generation Firewall by SNMP/svn.sw.license.exp_date[licensingExpirationDate.{#SNMPINDEX}]) < now()`|Average|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

