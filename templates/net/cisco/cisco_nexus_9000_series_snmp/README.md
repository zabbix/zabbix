
# Cisco Nexus 9000 Series by SNMP

## Overview

This template is designed to monitor `Cisco Nexus 9000 Series Switches`. 
> See [Cisco support documentation](https://www.cisco.com/c/en/us/support/switches/nexus-9000-series-switches/series.html) for details.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Cisco Nexus 93180YC-FX3 NX-OS 9.3.7

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

> Refer to the [vendor documentation](https://www.cisco.com/c/en/us/support/switches/nexus-9000-series-switches/products-installation-and-configuration-guides-list.html) for details.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT}||`90`|
|{$ICMP_LOSS_WARN}||`20`|
|{$ICMP_RESPONSE_TIME_WARN}||`0.15`|
|{$IF.ERRORS.WARN}||`2`|
|{$IF.UTIL.MAX}||`90`|
|{$IFCONTROL}||`1`|
|{$MEMORY.UTIL.MAX}||`90`|
|{$NET.IF.IFADMINSTATUS.MATCHES}||`^.*`|
|{$NET.IF.IFADMINSTATUS.NOT_MATCHES}|<p>If the administrative status is down (2), then an interface is excluded.</p>|`^2$`|
|{$NET.IF.IFALIAS.MATCHES}||`.*`|
|{$NET.IF.IFALIAS.NOT_MATCHES}||`CHANGE_IF_NEEDED`|
|{$CISCO.LLD.FILTER.FAN.NAME.MATCHES}|<p>It leaves only the matching fan names as indicated in the filter string.</p>|`^(?:Fan Module-\d+\|PowerSupply-\d+ Fan-\d+)$`|
|{$CISCO.LLD.FILTER.PSU.NAME.MATCHES}|<p>It leaves only the matching power supply names as indicated in the filter string.</p>|`^(?:PowerSupply-\d+)$`|
|{$NET.IF.IFDESCR.MATCHES}||`.*`|
|{$NET.IF.IFDESCR.NOT_MATCHES}||`CHANGE_IF_NEEDED`|
|{$NET.IF.IFNAME.MATCHES}||`^.*$`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>It filters out `loopbacks`, `nulls`, `docker veth` links and `docker0 bridge` by default.</p>|`Macro too long. Please see the template.`|
|{$NET.IF.IFOPERSTATUS.MATCHES}||`^.*$`|
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES}|<p>If the operational status is `notPresent (6)`, then an interface is excluded.</p>|`^6$`|
|{$NET.IF.IFTYPE.MATCHES}||`.*`|
|{$NET.IF.IFTYPE.NOT_MATCHES}||`CHANGE_IF_NEEDED`|
|{$SNMP.TIMEOUT}||`5m`|
|{$TEMP_WARN:regex:"BACK"}||`42`|
|{$TEMP_CRIT:regex:"BACK"}||`70`|
|{$TEMP_WARN:regex:"FRONT"}||`70`|
|{$TEMP_CRIT:regex:"FRONT"}||`80`|
|{$TEMP_WARN:regex:"CPU"}||`80`|
|{$TEMP_CRIT:regex:"CPU"}||`90`|
|{$TEMP_WARN:regex:"SUN1"}||`90`|
|{$TEMP_CRIT:regex:"SUN1"}||`110`|
|{$TEMP_WARN:regex:"Transceiver"}||`70`|
|{$TEMP_CRIT:regex:"Transceiver"}||`75`|
|{$TEMP_CRIT_LOW}||`5`|
|{$TEMP_WARN}||`50`|
|{$TEMP_CRIT}||`60`|
|{$ENT_CLASS.NOT_MATCHES}|<p>The filter excludes chassis (3) class from Serial discovery. The chassis (3) are polled with a regular item.</p>|`3`|
|{$ENT_SN.MATCHES}|<p>The filter retrieves only existing serial number strings.</p>|`.+`|
|{$PSU.PROBLEM.STATES}|<p>The PSU states list for average trigger priority.</p>|`^(1\|4\|5\|6\|7\|9\|10\|11\|12)$`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cisco Nexus 9000 Series: ICMP ping||Simple check|icmpping|
|Cisco Nexus 9000 Series: ICMP loss||Simple check|icmppingloss|
|Cisco Nexus 9000 Series: ICMP response time||Simple check|icmppingsec|
|Cisco Nexus 9000 Series: SNMP traps (fallback)|<p>The item is used to collect all the SNMP traps unmatched by the other snmptrap items.</p>|SNMP trap|snmptrap.fallback|
|Cisco Nexus 9000 Series: System contact details|<p>MIB: SNMPv2-MIB.</p><p>The textual identification of the contact person for the managed node (or: this node), together with the contact information of this person. If no contact information is known, the value is a zero-length string.</p>|SNMP agent|system.contact<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Cisco Nexus 9000 Series: System description|<p>MIB: SNMPv2-MIB.</p><p>The textual description of the entity. This value should include the full name and version identification number of the system's hardware type, software operating-system, and the networking software.</p>|SNMP agent|system.descr<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Cisco Nexus 9000 Series: Hardware model name|<p>MIB: ENTITY-MIB.</p>|SNMP agent|system.hw.model<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Cisco Nexus 9000 Series: Hardware serial number|<p>MIB: ENTITY-MIB.</p>|SNMP agent|system.hw.serialnumber<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Cisco Nexus 9000 Series: System location|<p>MIB: SNMPv2-MIB.</p><p>The physical location of this node (e.g., telephone closet, the third floor).</p><p>If the location is unknown, the value is a zero-length string.</p>|SNMP agent|system.location<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cisco Nexus 9000 Series: System name|<p>MIB: SNMPv2-MIB.</p><p>The administratively-assigned name for this node. By convention, this is the node's fully-qualified domain name. If the name is unknown, the value is a zero-length string.</p>|SNMP agent|system.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cisco Nexus 9000 Series: System object ID|<p>MIB: SNMPv2-MIB.</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity. This value is allocated within the SMI enterprise's subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining "what kind of box" is being managed.</p><p>For example, if the vendor "Flintstones, Inc." was assigned the subtree 1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its "Fred Router".</p>|SNMP agent|system.objectid<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cisco Nexus 9000 Series: Operating system|<p>MIB: CISCO-IMAGE-MIB</p>|SNMP agent|system.sw.os<p>**Preprocessing**</p><ul><li><p>Regular expression: `CW_VERSION\$(.*?)\$ \1`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Cisco Nexus 9000 Series: Uptime (snmp)|<p>MIB: SNMP-FRAMEWORK-MIB::snmpEngineTime.</p><p>The number of seconds since the value of the `snmpEngineBoots` object has had a last change.</p><p>When incrementing this object's value would cause it to exceed its maximum, the `snmpEngineBoots` is incremented as if a re-initialization had occurred,</p><p>and this object's value consequently reverts to zero.</p>|SNMP agent|system.uptime|
|Cisco Nexus 9000 Series: SNMP agent availability||Zabbix internal|zabbix[host,snmp,available]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco Nexus 9000 Series: Unavailable by ICMP ping|<p>The last three attempts returned a timeout. Check the connectivity of a device.</p>|`max(/Cisco Nexus 9000 Series by SNMP/icmpping,#3)=0`|High||
|Cisco Nexus 9000 Series: High ICMP ping loss||`min(/Cisco Nexus 9000 Series by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Cisco Nexus 9000 Series by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>Cisco Nexus 9000 Series: Unavailable by ICMP ping</li></ul>|
|Cisco Nexus 9000 Series: High ICMP ping response time||`avg(/Cisco Nexus 9000 Series by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>Cisco Nexus 9000 Series: High ICMP ping loss</li><li>Cisco Nexus 9000 Series: Unavailable by ICMP ping</li></ul>|
|Cisco Nexus 9000 Series: Device has been replaced|<p>The serial number of a device has changed. Acknowledge to close the problem manually.</p>|`change(/Cisco Nexus 9000 Series by SNMP/system.hw.serialnumber)=1 and length(last(/Cisco Nexus 9000 Series by SNMP/system.hw.serialnumber))>0`|Info|**Manual close**: Yes|
|Cisco Nexus 9000 Series: System name has changed|<p>The system name has changed. Acknowledge to close the problem manually.</p>|`change(/Cisco Nexus 9000 Series by SNMP/system.name)=1 and length(last(/Cisco Nexus 9000 Series by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|Cisco Nexus 9000 Series: Operating system description has changed|<p>The description of the operating system has changed. Possible reasons that system has been updated or replaced. Acknowledge to close the problem manually.</p>|`change(/Cisco Nexus 9000 Series by SNMP/system.sw.os)=1 and length(last(/Cisco Nexus 9000 Series by SNMP/system.sw.os))>0`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Cisco Nexus 9000 Series: System name has changed</li></ul>|
|Cisco Nexus 9000 Series: Device has been restarted or reinitialized|<p>The record of SNMP Boots has changed in less than 10 minutes. The restart of a device also counts.</p>|`last(/Cisco Nexus 9000 Series by SNMP/system.uptime)<10m`|Warning|**Manual close**: Yes|
|Cisco Nexus 9000 Series: No SNMP data collection|<p>SNMP is not available for polling. Check the connectivity of a device and SNMP settings.</p>|`max(/Cisco Nexus 9000 Series by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning||

### LLD rule CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU discovery|<p>You must use CISCO-PROCESS-MIB and its object `cpmCPUTotal5minRev` from the table called `cpmCPUTotalTable`, and indexed with `cpmCPUTotalPhysicalIndex`.</p><p>The table `cpmCPUTotalTable` allows CISCO-PROCESS-MIB to keep the CPU statistics for different physical entities in the router, such as different CPU chips, a group of CPUs, or CPUs in different modules/cards.</p><p>In the case of a single CPU, the `cpmCPUTotalTable` has only one entry.</p>|SNMP agent|cpu.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `4h`</p></li></ul>|

### Item prototypes for CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|#{#SNMPINDEX}: CPU utilization|<p>MIB: CISCO-PROCESS-MIB</p><p>The object name: `cpmCPUTotal5minRev`</p><p></p><p>The MIB object `cpmCPUTotal5minRev` provides a more accurate view of the performance of the router over time than the MIB objects `cpmCPUTotal1minRev` and `cpmCPUTotal5secRev`. These MIB objects are not accurate because they look at the CPU with an interval of one minute and five seconds, respectively. These MIBs enable to monitor the trends and plan the capacity of your network. The recommended baseline rising threshold for the `cpmCPUTotal5minRev` is 90 percent. Depending on the platform, some routers that run at 90 percent, for example, 2500 series can exhibit performance degradation versus a high-end router, for example, the 7500 series, which can operate fine.</p>|SNMP agent|system.cpu.util[{#SNMPINDEX}]|

### Trigger prototypes for CPU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|#{#SNMPINDEX}: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Cisco Nexus 9000 Series by SNMP/system.cpu.util[{#SNMPINDEX}],5m)>{$CPU.UTIL.CRIT}`|Warning||

### LLD rule Entity serial numbers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Entity serial numbers discovery|<p>The discovery of serial numbers of the entities from ENTITY-MIB.</p>|SNMP agent|entity_sn.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `4h`</p></li></ul>|

### Item prototypes for Entity serial numbers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#ENT_NAME}: Hardware serial number|<p>MIB: ENTITY-MIB.</p><p>The object name: `entPhysicalSerialNum`.</p><p>The vendor-specific serial number string for the physical entity. The preferred value is the serial number string actually printed on the component itself (if present).</p>|SNMP agent|system.hw.serialnumber[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Entity serial numbers discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#ENT_NAME}: Device has been replaced|<p>The device serial number has changed. Acknowledge to close the problem manually.</p>|`change(/Cisco Nexus 9000 Series by SNMP/system.hw.serialnumber[{#SNMPINDEX}])=1 and length(last(/Cisco Nexus 9000 Series by SNMP/system.hw.serialnumber[{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes|

### LLD rule Fan status discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan status discovery|<p>The discovery of metrics for the fan's status from ENTITY-MIB and CISCO-ENTITY-FRU-CONTROL-MIB.</p>|SNMP agent|fan.status.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `4h`</p></li></ul>|

### Item prototypes for Fan status discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SNMPVALUE}: Fan operational status|<p>MIB: CISCO-ENTITY-FRU-CONTROL-MIB.</p><p>The object name: `cefcFanTrayOperStatus`.</p><p>The operational state of the fan or a fan tray.</p><p>Possible values:</p><p>-  unknown (1) - unknown;</p><p>-  up (2) - powered on;</p><p>-  down (3) - powered down;</p><p>-  warning (4) - partial failure; needs replacement as soon as possible.</p>|SNMP agent|sensor.fan.status[{#SNMPINDEX}]|

### Trigger prototypes for Fan status discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SNMPVALUE}: Fan is down|<p>The fan unit requires immediate attention.</p>|`last(/Cisco Nexus 9000 Series by SNMP/sensor.fan.status[{#SNMPINDEX}])=3`|Average||
|{#SNMPVALUE}: Fan is in warning state|<p>The fan unit requires attention.</p>|`last(/Cisco Nexus 9000 Series by SNMP/sensor.fan.status[{#SNMPINDEX}])=4`|Warning|**Depends on**:<br><ul><li>{#SNMPVALUE}: Fan is down</li></ul>|
|{#SNMPVALUE}: Fan is in unknown state|<p>The fan unit requires attention.</p>|`last(/Cisco Nexus 9000 Series by SNMP/sensor.fan.status[{#SNMPINDEX}])=1`|Info|**Depends on**:<br><ul><li>{#SNMPVALUE}: Fan is down</li></ul>|

### LLD rule Memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Memory discovery|<p>The discovery of `ciscoMemoryPoolTable` - the table that contains monitoring entries of the memory pool.</p><p>For more details see "How to Get Free and Largest Block of Contiguous Memory Using SNMP":</p><p>http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p>|SNMP agent|memory.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `4h`</p></li></ul>|

### Item prototypes for Memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SNMPVALUE}: Free memory|<p>MIB: CISCO-ENHANCED-MEMPOOL-MIB.</p><p>The object name: `cempMemPoolFree`.</p><p>It indicates the number of bytes from the memory pool that are currently unused on the physical entity.</p>|SNMP agent|vm.memory.free[{#SNMPINDEX}]|
|{#SNMPVALUE}: Used memory|<p>MIB: CISCO-ENHANCED-MEMPOOL-MIB.</p><p>The object name: `cempMemPoolUsed`.</p><p>It indicates the number of bytes from the memory pool that are currently in use by applications on the physical entity.</p>|SNMP agent|vm.memory.used[{#SNMPINDEX}]|
|{#SNMPVALUE}: Memory utilization|<p>The memory utilization expressed in %.</p>|Calculated|vm.memory.util[{#SNMPINDEX}]|

### Trigger prototypes for Memory discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SNMPVALUE}: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Cisco Nexus 9000 Series by SNMP/vm.memory.util[{#SNMPINDEX}],5m)>{$MEMORY.UTIL.MAX}`|Average||

### LLD rule Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interfaces discovery|<p>The discovery of interfaces from IF-MIB.</p>|SNMP agent|net.if.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `4h`</p></li></ul>|

### Item prototypes for Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded|<p>MIB: IF-MIB.</p><p>The number of inbound packets, which were chosen to be discarded even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be the necessity to free up the buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of the ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.in.discards[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second: </li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors|<p>MIB: IF-MIB.</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.</p><p>For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of the ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.in.errors[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second: </li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits received|<p>MIB: IF-MIB.</p><p>The total number of octets received on the interface, including framing characters. This object is a 64-bit version of ifInOctets.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of the ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.in[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second: </li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded|<p>MIB: IF-MIB.</p><p>The number of outbound packets, which were chosen to be discarded, even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of the ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.out.discards[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second: </li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors|<p>MIB: IF-MIB.</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.</p><p>For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of the ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.out.errors[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second: </li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits sent|<p>MIB: IF-MIB.</p><p>The total number of octets transmitted out of the interface, including framing characters. This object is a 64-bit version of the ifOutOctets.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of the ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.out[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second: </li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Speed|<p>MIB: IF-MIB.</p><p>An estimate of the interface's current bandwidth in units of 1,000,000 bits per second. If this object reports a value of "n", then the speed of the interface is somewhere in the range from n-500,000 to n+499,999.</p><p>For the interfaces, which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth.</p><p>For a sub-layer, which has no concept of bandwidth, this object should be zero.</p>|SNMP agent|net.if.speed[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000000`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Operational status|<p>MIB: IF-MIB.</p><p>The current operational state of the interface:</p><p>-  The testing (3) state indicates that no operational packet scan be passed.</p><p>-  If ifAdminStatus is down (2), then ifOperStatus should be down (2).</p><p>-  If ifAdminStatus is changed to up (1), then ifOperStatus should change to up (1), provided the interface is ready to transmit and receive network traffic.</p><p>-  It should change to dormant (5) if the interface is waiting for external actions, such as a serial line waiting for an incoming connection.</p><p>-  It should remain in the down (2) state if and only when there is a fault that prevents it from going to the up (1) state.</p><p>-  It should remain in the notPresent (6) state if the interface has missing components (typically, hardware).</p>|SNMP agent|net.if.status[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Interface type|<p>MIB: IF-MIB.</p><p>The type of an interface.</p><p>Additional values for ifType are assigned by the Internet Assigned Numbers Authority (IANA)</p><p>through updating the syntax of the IANAifType textual convention.</p>|SNMP agent|net.if.type[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Network interfaces discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Interface {#IFNAME}({#IFALIAS}): High input error rate|<p>It recovers when it goes below 80% of the {$IF.ERRORS.WARN:"{#IFNAME}"} threshold.</p>|`min(/Cisco Nexus 9000 Series by SNMP/net.if.in.errors[{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): High inbound bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Cisco Nexus 9000 Series by SNMP/net.if.in[{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Cisco Nexus 9000 Series by SNMP/net.if.speed[{#SNMPINDEX}])) and last(/Cisco Nexus 9000 Series by SNMP/net.if.speed[{#SNMPINDEX}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): High output error rate|<p>It recovers when it goes below 80% of the {$IF.ERRORS.WARN:"{#IFNAME}"} threshold.</p>|`min(/Cisco Nexus 9000 Series by SNMP/net.if.out.errors[{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): High outbound bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Cisco Nexus 9000 Series by SNMP/net.if.out[{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Cisco Nexus 9000 Series by SNMP/net.if.speed[{#SNMPINDEX}])) and last(/Cisco Nexus 9000 Series by SNMP/net.if.speed[{#SNMPINDEX}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of issues with autonegotiation. Acknowledge to close the problem manually.</p>|`change(/Cisco Nexus 9000 Series by SNMP/net.if.speed[{#SNMPINDEX}])<0 and last(/Cisco Nexus 9000 Series by SNMP/net.if.speed[{#SNMPINDEX}])>0 and find(/Cisco Nexus 9000 Series by SNMP/net.if.type[{#SNMPINDEX}],#1,"regexp","^(6\|7\|11\|62\|69\|117)$") and last(/Cisco Nexus 9000 Series by SNMP/net.if.status[{#SNMPINDEX}])<>2`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. Use $IFCONTROL macro with context "{#IFNAME}" to void trigger firing on specific interfaces. Values:<br>-  0 : Marks an interface as not important. Trigger does not fire when interface is down.<br>-  1 : Default value to fire the trigger when interface is down<br>3. change(//net.if.status[{#IFNAME}]) - condition prevents firing of trigger if status did not change. It helps in cases, when interfaces were initially down.<br>BEWARE, manual close will ceasefire until at least two status changes happens again!</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Cisco Nexus 9000 Series by SNMP/net.if.status[{#SNMPINDEX}])=2 and change(/Cisco Nexus 9000 Series by SNMP/net.if.status[{#SNMPINDEX}])`|Average|**Manual close**: Yes|

### LLD rule EtherLike discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|EtherLike discovery|<p>The discovery of interfaces from IF-MIB and EtherLike-MIB. The interfaces that have up (1) operational status are discovered.</p>|SNMP agent|net.if.duplex.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `4h`</p></li></ul>|

### Item prototypes for EtherLike discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}({#IFALIAS}): Duplex status|<p>MIB: EtherLike-MIB.</p><p>The object name: dot3StatsDuplexStatus.</p><p>The current mode of operation of the MAC entity 'unknown' indicates that the current duplex mode could not be determined.</p><p>The management control of the duplex mode is accomplished through the MAU MIB. </p><p>When the interface does not support autonegotiation, or when autonegotiation is not enabled, the duplex mode is controlled using ifMauDefaultType.</p><p>When autonegotiation is supported and enabled, duplex mode is controlled using ifMauAutoNegAdvertisedBits.</p><p>In either case, the currently operating duplex mode is reflected in both: in this object and in ifMauType.</p><p>Note that this object provides redundant information with ifMauType.</p><p>Normally, redundant objects are discouraged. </p><p>However, in this instance, it allows the management application to determine the duplex status of an interface without having to know every possible value of ifMauType.</p><p>This was felt to be sufficiently valuable to justify the redundancy.</p><p>For the reference see: [IEEE 802.3 Std.], 30.3.1.1.32, aDuplexStatus.</p>|SNMP agent|net.if.duplex[{#SNMPINDEX}]|

### Trigger prototypes for EtherLike discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Interface {#IFNAME}({#IFALIAS}): In half-duplex mode|<p>Check the autonegotiation settings and cabling.</p>|`last(/Cisco Nexus 9000 Series by SNMP/net.if.duplex[{#SNMPINDEX}])=2`|Warning|**Manual close**: Yes|

### LLD rule PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU discovery|<p>The discovery of power supplies from ENTITY-MIB and CISCO-ENTITY-FRU-CONTROL-MIB.</p>|SNMP agent|psu.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `4h`</p></li></ul>|

### Item prototypes for PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SNMPVALUE}: Power supply status|<p>MIB: CISCO-ENTITY-FRU-CONTROL-MIB.</p><p>The object name: cefcFRUPowerOperStatus.</p><p>The Operational field-replaceable unit (FRU) Status types.</p><p>The valid values are:</p><p>-  offEnvOther (1): FRU is powered off because of a problem not listed below;</p><p>-  on (2): FRU is powered on;</p><p>-  offAdmin (3): administratively off;</p><p>-  offDenied (4): FRU is powered off because available system power is insufficient;</p><p>-  offEnvPower (5): FRU is powered off because of a power problem in the FRU. For example, the FRU's power translation (DC-DC converter) or distribution failed;</p><p>-  offEnvTemp (6): FRU is powered off because of temperature problem;</p><p>-  offEnvFan (7): FRU is powered off because of fan problems;</p><p>-  failed (8): FRU is in failed state;</p><p>-  onButFanFail (9): FRU is on but fan has failed;</p><p>-  offCooling (10): FRU is powered off because of the system's insufficient cooling capacity;</p><p>-  offConnectorRating (11): FRU is powered off because of the system's connector rating exceeded;</p><p>-  onButInlinePowerFail (12): The FRU is on but no inline power is being delivered as the data/inline power component of the FRU has failed.</p>|SNMP agent|sensor.psu.status[{#SNMPINDEX}]|

### Trigger prototypes for PSU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SNMPVALUE}: PSU is off or out of optimal state|<p>The PSU requires attention. Compare the current state from operational data with the table below:<br>-  offEnvOther (1): FRU is powered off because of a problem not listed below;<br>-  on (2): FRU is powered on;<br>-  offAdmin (3): administratively off;<br>-  offDenied (4): FRU is powered off because available system power is insufficient;<br>-  offEnvPower (5): FRU is powered off because of a power problem in the FRU. For example, the FRU's power translation (DC-DC converter) or distribution failed;<br>-  offEnvTemp (6): FRU is powered off because of temperature problem;<br>-  offEnvFan (7): FRU is powered off because of fan problems;<br>-  failed (8): FRU is in failed state;<br>-  onButFanFail (9): FRU is on but fan has failed;<br>-  offCooling (10): FRU is powered off because of the system's insufficient cooling capacity;<br>-  offConnectorRating (11): FRU is powered off because of the system's connector rating exceeded;<br>-  onButInlinePowerFail (12): The FRU is on but no inline power is being delivered as the data/inline power component of the FRU has failed.</p>|`find(/Cisco Nexus 9000 Series by SNMP/sensor.psu.status[{#SNMPINDEX}],#1,"regexp",{$PSU.PROBLEM.STATES})`|Average|**Depends on**:<br><ul><li>{#SNMPVALUE}: PSU is in failed state</li></ul>|
|{#SNMPVALUE}: PSU is off: Administratively|<p>The FRU is administratively off.</p>|`last(/Cisco Nexus 9000 Series by SNMP/sensor.psu.status[{#SNMPINDEX}])=3`|Info|**Depends on**:<br><ul><li>{#SNMPVALUE}: PSU is in failed state</li></ul>|
|{#SNMPVALUE}: PSU is in failed state|<p>The FRU is in a failed state.</p>|`last(/Cisco Nexus 9000 Series by SNMP/sensor.psu.status[{#SNMPINDEX}])=8`|High||

### LLD rule Temperature sensors discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature sensors discovery|<p>The discovery of temperature sensors from CISCO-ENTITY-SENSOR-MIB and ENTITY-MIB. The sensors that have celsius (8) `entSensorType` are discovered. The scale of gathered values is taken from the `entSensorScale` and applied in item preprocessing.</p>|SNMP agent|temperature.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `4h`</p></li></ul>|

### Item prototypes for Temperature sensors discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SNMPVALUE}: Temperature sensor status|<p>MIB: CISCO-ENTITY-SENSOR-MIB.</p><p>The object name: entSensorStatus.</p><p>This variable indicates the present operational status of the sensor.</p><p>Possible values:</p><p>-  ok (1): means the agent can read the sensor value;</p><p>-  unavailable (2): means that the agent presently can not report the sensor value;</p><p>-  nonoperational (3) means that the agent believes the sensor is broken. The sensor could have a hard failure (e.g., disconnected wire), or a soft failure (e.g., out-of-range, jittery, or wildly fluctuating readings).</p>|SNMP agent|sensor.temp.status[{#SNMPINDEX}]|
|{#SNMPVALUE}: Temperature|<p>MIB: CISCO-ENTITY-SENSOR-MIB.</p><p>The object name: entSensorValue.</p><p>This variable reports the most recent measurement seen by the sensor.</p>|SNMP agent|sensor.temp.value[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `{#SENSOR_SCALE}`</p></li></ul>|

### Trigger prototypes for Temperature sensors discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SNMPVALUE}: Temperature sensor is not operational|<p>It means that the agent considers that the sensor is broken. The sensor could have a hard failure (e.g., disconnected wire), or a soft failure (e.g., out-of-range, jittery, or wildly fluctuating readings).</p>|`last(/Cisco Nexus 9000 Series by SNMP/sensor.temp.status[{#SNMPINDEX}])=3`|High||
|{#SNMPVALUE}: Temperature sensor is not available|<p>It means that the agent presently can not report the sensor value.</p>|`last(/Cisco Nexus 9000 Series by SNMP/sensor.temp.status[{#SNMPINDEX}])=2`|Warning||
|{#SNMPVALUE}: Temperature is above critical threshold|<p>This trigger uses the values of the temperature sensor.</p>|`avg(/Cisco Nexus 9000 Series by SNMP/sensor.temp.value[{#SNMPINDEX}],5m)>{$TEMP_CRIT:"{#SNMPVALUE}"}`|High||
|{#SNMPVALUE}: Temperature is above warning threshold|<p>This trigger uses the values of the temperature sensor.</p>|`avg(/Cisco Nexus 9000 Series by SNMP/sensor.temp.value[{#SNMPINDEX}],5m)>{$TEMP_WARN:"{#SNMPVALUE}"}`|Warning|**Depends on**:<br><ul><li>{#SNMPVALUE}: Temperature is above critical threshold</li></ul>|
|{#SNMPVALUE}: Temperature is too low||`avg(/Cisco Nexus 9000 Series by SNMP/sensor.temp.value[{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"{#SNMPVALUE}"}`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

