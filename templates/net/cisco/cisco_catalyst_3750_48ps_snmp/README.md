
# Cisco Catalyst 3750V2-48PS by SNMP

## Overview

![Product picture](images/pic.png?raw=true)
> Courtesy of Cisco Systems, Inc. Unauthorized use not permitted.

The Cisco Catalyst 3750 Series switches are a premier line of enterprise-class, stackable, multilayer switches that provide high availability, security, and quality of service (QoS) to enhance the operation of the network. Its innovative unified stack management raises the bar in stack management, redundancy, and failover.

https://www.cisco.com/c/en/us/support/switches/catalyst-3750v2-48ps-switch/model.html

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Cisco Catalyst 3750V2-48PS

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

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
|{$NET.IF.IFADMINSTATUS.NOT_MATCHES}|<p>Ignore down(2) administrative status</p>|`^2$`|
|{$NET.IF.IFALIAS.MATCHES}||`.*`|
|{$NET.IF.IFALIAS.NOT_MATCHES}||`CHANGE_IF_NEEDED`|
|{$NET.IF.IFDESCR.MATCHES}||`.*`|
|{$NET.IF.IFDESCR.NOT_MATCHES}||`CHANGE_IF_NEEDED`|
|{$NET.IF.IFNAME.MATCHES}||`^.*$`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>Filter out loopbacks, nulls, docker veth links and docker0 bridge by default</p>|`Macro too long. Please see the template.`|
|{$NET.IF.IFOPERSTATUS.MATCHES}||`^.*$`|
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES}|<p>Ignore notPresent(6)</p>|`^6$`|
|{$NET.IF.IFTYPE.MATCHES}||`.*`|
|{$NET.IF.IFTYPE.NOT_MATCHES}||`CHANGE_IF_NEEDED`|
|{$SNMP.TIMEOUT}||`5m`|
|{$TEMP_CRIT}||`60`|
|{$TEMP_CRIT_LOW}||`5`|
|{$TEMP_WARN}||`50`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cisco Catalyst 3750V2-48PS: ICMP ping||Simple check|icmpping|
|Cisco Catalyst 3750V2-48PS: ICMP loss||Simple check|icmppingloss|
|Cisco Catalyst 3750V2-48PS: ICMP response time||Simple check|icmppingsec|
|Cisco Catalyst 3750V2-48PS: SNMP traps (fallback)|<p>Item is used to collect all SNMP traps unmatched by other snmptrap items</p>|SNMP trap|snmptrap.fallback|
|Cisco Catalyst 3750V2-48PS: System contact details|<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</p>|SNMP agent|system.contact<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Cisco Catalyst 3750V2-48PS: System description|<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p>|SNMP agent|system.descr<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Cisco Catalyst 3750V2-48PS: Hardware model name|<p>MIB: ENTITY-MIB</p>|SNMP agent|system.hw.model<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Cisco Catalyst 3750V2-48PS: Hardware serial number|<p>MIB: ENTITY-MIB</p>|SNMP agent|system.hw.serialnumber<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Cisco Catalyst 3750V2-48PS: System location|<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet, 3rd floor').  If the location is unknown, the value is the zero-length string.</p>|SNMP agent|system.location<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cisco Catalyst 3750V2-48PS: System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name.  If the name is unknown, the value is the zero-length string.</p>|SNMP agent|system.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cisco Catalyst 3750V2-48PS: System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity.  This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p>|SNMP agent|system.objectid<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cisco Catalyst 3750V2-48PS: Operating system|<p>MIB: SNMPv2-MIB</p>|SNMP agent|system.sw.os<p>**Preprocessing**</p><ul><li><p>Regular expression: `Version (.+), RELEASE \1`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Cisco Catalyst 3750V2-48PS: Uptime (network)|<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p>|SNMP agent|system.net.uptime<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Cisco Catalyst 3750V2-48PS: Uptime (hardware)|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p>|SNMP agent|system.hw.uptime<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Cisco Catalyst 3750V2-48PS: SNMP agent availability||Zabbix internal|zabbix[host,snmp,available]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco Catalyst 3750V2-48PS: Unavailable by ICMP ping|<p>Last three attempts returned timeout.  Please check device connectivity.</p>|`max(/Cisco Catalyst 3750V2-48PS by SNMP/icmpping,#3)=0`|High||
|Cisco Catalyst 3750V2-48PS: High ICMP ping loss||`min(/Cisco Catalyst 3750V2-48PS by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Cisco Catalyst 3750V2-48PS by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>Cisco Catalyst 3750V2-48PS: Unavailable by ICMP ping</li></ul>|
|Cisco Catalyst 3750V2-48PS: High ICMP ping response time||`avg(/Cisco Catalyst 3750V2-48PS by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>Cisco Catalyst 3750V2-48PS: High ICMP ping loss</li><li>Cisco Catalyst 3750V2-48PS: Unavailable by ICMP ping</li></ul>|
|Cisco Catalyst 3750V2-48PS: Device has been replaced|<p>The device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Cisco Catalyst 3750V2-48PS by SNMP/system.hw.serialnumber,#1)<>last(/Cisco Catalyst 3750V2-48PS by SNMP/system.hw.serialnumber,#2) and length(last(/Cisco Catalyst 3750V2-48PS by SNMP/system.hw.serialnumber))>0`|Info|**Manual close**: Yes|
|Cisco Catalyst 3750V2-48PS: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/Cisco Catalyst 3750V2-48PS by SNMP/system.name,#1)<>last(/Cisco Catalyst 3750V2-48PS by SNMP/system.name,#2) and length(last(/Cisco Catalyst 3750V2-48PS by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|Cisco Catalyst 3750V2-48PS: Operating system description has changed|<p>The description of the operating system has changed. Possible reasons are that the system has been updated or replaced. Acknowledge to close the problem manually.</p>|`last(/Cisco Catalyst 3750V2-48PS by SNMP/system.sw.os,#1)<>last(/Cisco Catalyst 3750V2-48PS by SNMP/system.sw.os,#2) and length(last(/Cisco Catalyst 3750V2-48PS by SNMP/system.sw.os))>0`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Cisco Catalyst 3750V2-48PS: System name has changed</li></ul>|
|Cisco Catalyst 3750V2-48PS: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Cisco Catalyst 3750V2-48PS by SNMP/system.hw.uptime)>0 and last(/Cisco Catalyst 3750V2-48PS by SNMP/system.hw.uptime)<10m) or (last(/Cisco Catalyst 3750V2-48PS by SNMP/system.hw.uptime)=0 and last(/Cisco Catalyst 3750V2-48PS by SNMP/system.net.uptime)<10m)`|Warning|**Manual close**: Yes|
|Cisco Catalyst 3750V2-48PS: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/Cisco Catalyst 3750V2-48PS by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning||

### LLD rule CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU discovery|<p>If your IOS device has several CPUs, you must use CISCO-PROCESS-MIB and its object cpmCPUTotal5minRev from the table called cpmCPUTotalTable ,</p><p>indexed with cpmCPUTotalIndex .</p><p>This table allows CISCO-PROCESS-MIB to keep CPU statistics for different physical entities in the router,</p><p>like different CPU chips, group of CPUs, or CPUs in different modules/cards.</p><p>In case of a single CPU, cpmCPUTotalTable has only one entry.</p>|SNMP agent|cpu.discovery|

### Item prototypes for CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|#{#SNMPINDEX}: CPU utilization|<p>MIB: CISCO-PROCESS-MIB</p><p>Object name: cpmCPUTotal5minRev</p><p>The cpmCPUTotal5minRev MIB object provides a more accurate view of the performance of the router over time than the MIB objects cpmCPUTotal1minRev and cpmCPUTotal5secRev . These MIB objects are not accurate because they look at CPU at one minute and five second intervals, respectively. These MIBs enable you to monitor the trends and plan the capacity of your network. The recommended baseline rising threshold for cpmCPUTotal5minRev is 90 percent. Depending on the platform, some routers that run at 90 percent, for example, 2500s, can exhibit performance degradation versus a high-end router, for example, the 7500 series, which can operate fine.</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15215-collect-cpu-util-snmp.html</p>|SNMP agent|system.cpu.util[{#SNMPINDEX}]|

### Trigger prototypes for CPU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|#{#SNMPINDEX}: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Cisco Catalyst 3750V2-48PS by SNMP/system.cpu.util[{#SNMPINDEX}],5m)>{$CPU.UTIL.CRIT}`|Warning||

### LLD rule Entity Serial Numbers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Entity Serial Numbers discovery||SNMP agent|entity_sn.discovery|

### Item prototypes for Entity Serial Numbers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#ENT_NAME}: Hardware serial number|<p>MIB: ENTITY-MIB</p><p>Object name: entPhysicalSerialNum</p>|SNMP agent|system.hw.serialnumber[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Entity Serial Numbers discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#ENT_NAME}: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Cisco Catalyst 3750V2-48PS by SNMP/system.hw.serialnumber[{#SNMPINDEX}],#1)<>last(/Cisco Catalyst 3750V2-48PS by SNMP/system.hw.serialnumber[{#SNMPINDEX}],#2) and length(last(/Cisco Catalyst 3750V2-48PS by SNMP/system.hw.serialnumber[{#SNMPINDEX}]))>0`|Info||

### LLD rule FAN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN discovery|<p>The table of fan status maintained by the environmental monitor.</p>|SNMP agent|fan.discovery|

### Item prototypes for FAN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SNMPVALUE}: Fan status|<p>MIB: CISCO-ENVMON-MIB</p><p>Object name: ciscoEnvMonFanState</p>|SNMP agent|sensor.fan.status[{#SNMPINDEX}]|

### Trigger prototypes for FAN discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SNMPVALUE}: Fan is in critical state|<p>Please check the fan unit</p>|`last(/Cisco Catalyst 3750V2-48PS by SNMP/sensor.fan.status[{#SNMPINDEX}])=3 or last(/Cisco Catalyst 3750V2-48PS by SNMP/sensor.fan.status[{#SNMPINDEX}])=4`|Average||
|{#SNMPVALUE}: Fan is in warning state|<p>Please check the fan unit</p>|`last(/Cisco Catalyst 3750V2-48PS by SNMP/sensor.fan.status[{#SNMPINDEX}])=2`|Warning|**Depends on**:<br><ul><li>{#SNMPVALUE}: Fan is in critical state</li></ul>|

### LLD rule Memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Memory discovery|<p>Discovery of ciscoMemoryPoolTable, a table of memory pool monitoring entries.</p><p>http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p>|SNMP agent|memory.discovery|

### Item prototypes for Memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SNMPVALUE}: Free memory|<p>MIB: CISCO-MEMORY-POOL-MIB</p><p>Object name: ciscoMemoryPoolFree</p><p>Indicates the number of bytes from the memory pool that are currently unused on the managed device. Note that the sum of ciscoMemoryPoolUsed and ciscoMemoryPoolFree is the total amount of memory in the pool</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p>|SNMP agent|vm.memory.free[{#SNMPINDEX}]|
|{#SNMPVALUE}: Used memory|<p>MIB: CISCO-MEMORY-POOL-MIB</p><p>Object name: ciscoMemoryPoolUsed</p><p>Indicates the number of bytes from the memory pool that are currently in use by applications on the managed device.</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p>|SNMP agent|vm.memory.used[{#SNMPINDEX}]|
|{#SNMPVALUE}: Memory utilization|<p>Memory utilization in %</p>|Calculated|vm.memory.util[{#SNMPINDEX}]|

### Trigger prototypes for Memory discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SNMPVALUE}: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Cisco Catalyst 3750V2-48PS by SNMP/vm.memory.util[{#SNMPINDEX}],5m)>{$MEMORY.UTIL.MAX}`|Average||

### LLD rule Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interfaces discovery|<p>Discovering interfaces from IF-MIB.</p>|SNMP agent|net.if.discovery|

### Item prototypes for Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded|<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.in.discards[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second: </li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.in.errors[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second: </li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits received|<p>MIB: IF-MIB</p><p>The total number of octets received on the interface, including framing characters. This object is a 64-bit version of ifInOctets. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.in[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second: </li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded|<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.out.discards[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second: </li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.out.errors[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second: </li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits sent|<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. This object is a 64-bit version of ifOutOctets.Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.out[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second: </li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Speed|<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in units of 1,000,000 bits per second. If this object reports a value of `n' then the speed of the interface is somewhere in the range of `n-500,000' to`n+499,999'.  For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth. For a sub-layer which has no concept of bandwidth, this object should be zero.</p>|SNMP agent|net.if.speed[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000000`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Operational status|<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The testing(3) state indicates that no operational packet scan be passed</p><p>- If ifAdminStatus is down(2) then ifOperStatus should be down(2)</p><p>- If ifAdminStatus is changed to up(1) then ifOperStatus should change to up(1) if the interface is ready to transmit and receive network traffic</p><p>- It should change todormant(5) if the interface is waiting for external actions (such as a serial line waiting for an incoming connection)</p><p>- It should remain in the down(2) state if and only if there is a fault that prevents it from going to the up(1) state</p><p>- It should remain in the notPresent(6) state if the interface has missing(typically, hardware) components.</p>|SNMP agent|net.if.status[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Interface type|<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for ifType are assigned by the Internet Assigned Numbers Authority (IANA),</p><p>through updating the syntax of the IANAifType textual convention.</p>|SNMP agent|net.if.type[{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Network interfaces discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Interface {#IFNAME}({#IFALIAS}): High input error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Cisco Catalyst 3750V2-48PS by SNMP/net.if.in.errors[{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): High inbound bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Cisco Catalyst 3750V2-48PS by SNMP/net.if.in[{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Cisco Catalyst 3750V2-48PS by SNMP/net.if.speed[{#SNMPINDEX}])) and last(/Cisco Catalyst 3750V2-48PS by SNMP/net.if.speed[{#SNMPINDEX}])>0`|Warning|**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): High output error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Cisco Catalyst 3750V2-48PS by SNMP/net.if.out.errors[{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): High outbound bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Cisco Catalyst 3750V2-48PS by SNMP/net.if.out[{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Cisco Catalyst 3750V2-48PS by SNMP/net.if.speed[{#SNMPINDEX}])) and last(/Cisco Catalyst 3750V2-48PS by SNMP/net.if.speed[{#SNMPINDEX}])>0`|Warning|**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Cisco Catalyst 3750V2-48PS by SNMP/net.if.speed[{#SNMPINDEX}])<0 and last(/Cisco Catalyst 3750V2-48PS by SNMP/net.if.speed[{#SNMPINDEX}])>0 and ( last(/Cisco Catalyst 3750V2-48PS by SNMP/net.if.type[{#SNMPINDEX}])=6 or last(/Cisco Catalyst 3750V2-48PS by SNMP/net.if.type[{#SNMPINDEX}])=7 or last(/Cisco Catalyst 3750V2-48PS by SNMP/net.if.type[{#SNMPINDEX}])=11 or last(/Cisco Catalyst 3750V2-48PS by SNMP/net.if.type[{#SNMPINDEX}])=62 or last(/Cisco Catalyst 3750V2-48PS by SNMP/net.if.type[{#SNMPINDEX}])=69 or last(/Cisco Catalyst 3750V2-48PS by SNMP/net.if.type[{#SNMPINDEX}])=117 ) and (last(/Cisco Catalyst 3750V2-48PS by SNMP/net.if.status[{#SNMPINDEX}])<>2)`|Info|**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and (last(/Cisco Catalyst 3750V2-48PS by SNMP/net.if.status[{#SNMPINDEX}])=2)`|Average||

### LLD rule EtherLike discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|EtherLike discovery|<p>Discovering interfaces from IF-MIB and EtherLike-MIB. Interfaces with up(1) Operational Status are discovered.</p>|SNMP agent|net.if.duplex.discovery|

### Item prototypes for EtherLike discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}({#IFALIAS}): Duplex status|<p>MIB: EtherLike-MIB</p><p>Object name: dot3StatsDuplexStatus</p><p>The current mode of operation of the MAC</p><p>entity.  'unknown' indicates that the current</p><p>duplex mode could not be determined.</p><p></p><p>Management control of the duplex mode is</p><p>accomplished through the MAU MIB.  When</p><p>an interface does not support autonegotiation,</p><p>or when autonegotiation is not enabled, the</p><p>duplex mode is controlled using</p><p>ifMauDefaultType.  When autonegotiation is</p><p>supported and enabled, duplex mode is controlled</p><p>using ifMauAutoNegAdvertisedBits.  In either</p><p>case, the currently operating duplex mode is</p><p>reflected both in this object and in ifMauType.</p><p></p><p>Note that this object provides redundant</p><p>information with ifMauType.  Normally, redundant</p><p>objects are discouraged.  However, in this</p><p>instance, it allows a management application to</p><p>determine the duplex status of an interface</p><p>without having to know every possible value of</p><p>ifMauType.  This was felt to be sufficiently</p><p>valuable to justify the redundancy.</p><p>Reference: [IEEE 802.3 Std.], 30.3.1.1.32,aDuplexStatus.</p>|SNMP agent|net.if.duplex[{#SNMPINDEX}]|

### Trigger prototypes for EtherLike discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Interface {#IFNAME}({#IFALIAS}): In half-duplex mode|<p>Please check autonegotiation settings and cabling</p>|`last(/Cisco Catalyst 3750V2-48PS by SNMP/net.if.duplex[{#SNMPINDEX}])=2`|Warning||

### LLD rule PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU discovery|<p>The table of power supply status maintained by the environmental monitor card.</p>|SNMP agent|psu.discovery|

### Item prototypes for PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SNMPVALUE}: Power supply status|<p>MIB: CISCO-ENVMON-MIB</p><p>Object name: ciscoEnvMonSupplyState</p>|SNMP agent|sensor.psu.status[{#SNMPINDEX}]|

### Trigger prototypes for PSU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SNMPVALUE}: Power supply is in critical state|<p>Please check the power supply unit for errors</p>|`last(/Cisco Catalyst 3750V2-48PS by SNMP/sensor.psu.status[{#SNMPINDEX}])=3 or last(/Cisco Catalyst 3750V2-48PS by SNMP/sensor.psu.status[{#SNMPINDEX}])=4`|Average||
|{#SNMPVALUE}: Power supply is in warning state|<p>Please check the power supply unit for errors</p>|`last(/Cisco Catalyst 3750V2-48PS by SNMP/sensor.psu.status[{#SNMPINDEX}])=2`|Warning|**Depends on**:<br><ul><li>{#SNMPVALUE}: Power supply is in critical state</li></ul>|

### LLD rule Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature discovery|<p>Discovery of ciscoEnvMonTemperatureTable (ciscoEnvMonTemperatureDescr), a table of ambient temperature status</p><p>maintained by the environmental monitor.</p>|SNMP agent|temperature.discovery|

### Item prototypes for Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SNMPVALUE}: Temperature status|<p>MIB: CISCO-ENVMON-MIB</p><p>Object name: ciscoEnvMonTemperatureState</p><p>The current state of the test point being instrumented.</p>|SNMP agent|sensor.temp.status[{#SNMPINDEX}]|
|{#SNMPVALUE}: Temperature|<p>MIB: CISCO-ENVMON-MIB</p><p>Object name: ciscoEnvMonTemperatureValue</p><p>The current measurement of the test point being instrumented.</p>|SNMP agent|sensor.temp.value[{#SNMPINDEX}]|

### Trigger prototypes for Temperature discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SNMPVALUE}: Temperature is in critical state|<p>This trigger uses temperature sensor state</p>|`last(/Cisco Catalyst 3750V2-48PS by SNMP/sensor.temp.status[{#SNMPINDEX}])=3 or last(/Cisco Catalyst 3750V2-48PS by SNMP/sensor.temp.status[{#SNMPINDEX}])=4`|High||
|{#SNMPVALUE}: Temperature is in warning state|<p>This trigger uses temperature sensor state</p>|`last(/Cisco Catalyst 3750V2-48PS by SNMP/sensor.temp.status[{#SNMPINDEX}])=2`|Warning|**Depends on**:<br><ul><li>{#SNMPVALUE}: Temperature is in critical state</li></ul>|
|{#SNMPVALUE}: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco Catalyst 3750V2-48PS by SNMP/sensor.temp.value[{#SNMPINDEX}],5m)>{$TEMP_CRIT:"{#SNMPVALUE}"}`|High||
|{#SNMPVALUE}: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco Catalyst 3750V2-48PS by SNMP/sensor.temp.value[{#SNMPINDEX}],5m)>{$TEMP_WARN:"{#SNMPVALUE}"}`|Warning|**Depends on**:<br><ul><li>{#SNMPVALUE}: Temperature is above critical threshold</li></ul>|
|{#SNMPVALUE}: Temperature is too low||`avg(/Cisco Catalyst 3750V2-48PS by SNMP/sensor.temp.value[{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"{#SNMPVALUE}"}`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

