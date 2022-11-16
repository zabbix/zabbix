
# Cisco IOS by SNMP

## Overview

For Zabbix version: 6.2 and higher.

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>-</p> |`90` |
|{$FAN_CRIT_STATUS:"critical"} |<p>-</p> |`3` |
|{$FAN_CRIT_STATUS:"shutdown"} |<p>-</p> |`4` |
|{$FAN_WARN_STATUS:"notFunctioning"} |<p>-</p> |`6` |
|{$FAN_WARN_STATUS:"warning"} |<p>-</p> |`2` |
|{$ICMP_LOSS_WARN} |<p>-</p> |`20` |
|{$ICMP_RESPONSE_TIME_WARN} |<p>-</p> |`0.15` |
|{$IF.ERRORS.WARN} |<p>-</p> |`2` |
|{$IF.UTIL.MAX} |<p>-</p> |`90` |
|{$IFCONTROL} |<p>-</p> |`1` |
|{$MEMORY.UTIL.MAX} |<p>-</p> |`90` |
|{$NET.IF.IFADMINSTATUS.MATCHES} |<p>Ignore notPresent(6)</p> |`^.*` |
|{$NET.IF.IFADMINSTATUS.NOT_MATCHES} |<p>Ignore down(2) administrative status</p> |`^2$` |
|{$NET.IF.IFALIAS.MATCHES} |<p>-</p> |`.*` |
|{$NET.IF.IFALIAS.NOT_MATCHES} |<p>-</p> |`CHANGE_IF_NEEDED` |
|{$NET.IF.IFDESCR.MATCHES} |<p>-</p> |`.*` |
|{$NET.IF.IFDESCR.NOT_MATCHES} |<p>-</p> |`CHANGE_IF_NEEDED` |
|{$NET.IF.IFNAME.MATCHES} |<p>-</p> |`^.*$` |
|{$NET.IF.IFNAME.NOT_MATCHES} |<p>Filter out loopbacks, nulls, docker veth links and docker0 bridge by default</p> |`(^Software Loopback Interface|^NULL[0-9.]*$|^[Ll]o[0-9.]*$|^[Ss]ystem$|^Nu[0-9.]*$|^veth[0-9a-z]+$|docker[0-9]+|br-[a-z0-9]{12})` |
|{$NET.IF.IFOPERSTATUS.MATCHES} |<p>-</p> |`^.*$` |
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES} |<p>Ignore notPresent(6)</p> |`^6$` |
|{$NET.IF.IFTYPE.MATCHES} |<p>-</p> |`.*` |
|{$NET.IF.IFTYPE.NOT_MATCHES} |<p>-</p> |`CHANGE_IF_NEEDED` |
|{$PSU_CRIT_STATUS:"critical"} |<p>-</p> |`3` |
|{$PSU_CRIT_STATUS:"shutdown"} |<p>-</p> |`4` |
|{$PSU_WARN_STATUS:"notFunctioning"} |<p>-</p> |`6` |
|{$PSU_WARN_STATUS:"warning"} |<p>-</p> |`2` |
|{$SNMP.TIMEOUT} |<p>-</p> |`5m` |
|{$TEMP_CRIT:"CPU"} |<p>-</p> |`75` |
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT_STATUS} |<p>-</p> |`3` |
|{$TEMP_CRIT} |<p>-</p> |`60` |
|{$TEMP_DISASTER_STATUS} |<p>-</p> |`4` |
|{$TEMP_WARN:"CPU"} |<p>-</p> |`70` |
|{$TEMP_WARN_STATUS} |<p>-</p> |`2` |
|{$TEMP_WARN} |<p>-</p> |`50` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|CPU Discovery |<p>If your IOS device has several CPUs, you must use CISCO-PROCESS-MIB and its object cpmCPUTotal5minRev from the table called cpmCPUTotalTable,</p><p>indexed with cpmCPUTotalIndex.</p><p>This table allows CISCO-PROCESS-MIB to keep CPU statistics for different physical entities in the router,</p><p>like different CPU chips, group of CPUs, or CPUs in different modules/cards.</p><p>In case of a single CPU, cpmCPUTotalTable has only one entry.</p> |SNMP |cpu.discovery |
|Entity Serial Numbers Discovery |<p>-</p> |SNMP |entity_sn.discovery<p>**Filter**:</p>AND <p>- {#ENT_SN} MATCHES_REGEX `.+`</p><p>- {#ENT_CLASS} MATCHES_REGEX `^3$`</p> |
|EtherLike-MIB Discovery |<p>Discovering interfaces from IF-MIB and EtherLike-MIB. Interfaces with up(1) Operational Status are discovered.</p> |SNMP |net.if.duplex.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>**Filter**:</p>AND <p>- {#IFOPERSTATUS} MATCHES_REGEX `1`</p><p>- {#SNMPVALUE} MATCHES_REGEX `(2|3)`</p> |
|FAN Discovery |<p>The table of fan status maintained by the environmental monitor.</p> |SNMP |fan.discovery |
|Memory Discovery |<p>Discovery of ciscoMemoryPoolTable, a table of memory pool monitoring entries.</p><p>http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p> |SNMP |memory.discovery |
|Network interfaces discovery |<p>Discovering interfaces from IF-MIB.</p> |SNMP |net.if.discovery<p>**Filter**:</p>AND <p>- {#IFADMINSTATUS} MATCHES_REGEX `{$NET.IF.IFADMINSTATUS.MATCHES}`</p><p>- {#IFADMINSTATUS} NOT_MATCHES_REGEX `{$NET.IF.IFADMINSTATUS.NOT_MATCHES}`</p><p>- {#IFOPERSTATUS} MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.MATCHES}`</p><p>- {#IFOPERSTATUS} NOT_MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.NOT_MATCHES}`</p><p>- {#IFNAME} MATCHES_REGEX `{$NET.IF.IFNAME.MATCHES}`</p><p>- {#IFNAME} NOT_MATCHES_REGEX `{$NET.IF.IFNAME.NOT_MATCHES}`</p><p>- {#IFDESCR} MATCHES_REGEX `{$NET.IF.IFDESCR.MATCHES}`</p><p>- {#IFDESCR} NOT_MATCHES_REGEX `{$NET.IF.IFDESCR.NOT_MATCHES}`</p><p>- {#IFALIAS} MATCHES_REGEX `{$NET.IF.IFALIAS.MATCHES}`</p><p>- {#IFALIAS} NOT_MATCHES_REGEX `{$NET.IF.IFALIAS.NOT_MATCHES}`</p><p>- {#IFTYPE} MATCHES_REGEX `{$NET.IF.IFTYPE.MATCHES}`</p><p>- {#IFTYPE} NOT_MATCHES_REGEX `{$NET.IF.IFTYPE.NOT_MATCHES}`</p> |
|PSU Discovery |<p>The table of power supply status maintained by the environmental monitor card.</p> |SNMP |psu.discovery |
|Temperature Discovery |<p>Discovery of ciscoEnvMonTemperatureTable (ciscoEnvMonTemperatureDescr), a table of ambient temperature status</p><p>maintained by the environmental monitor.</p> |SNMP |temperature.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |#{#SNMPINDEX}: CPU utilization |<p>MIB: CISCO-PROCESS-MIB</p><p>The cpmCPUTotal5minRev MIB object provides a more accurate view of the performance of the router over time than the MIB objects cpmCPUTotal1minRev and cpmCPUTotal5secRev . These MIB objects are not accurate because they look at CPU at one minute and five second intervals, respectively. These MIBs enable you to monitor the trends and plan the capacity of your network. The recommended baseline rising threshold for cpmCPUTotal5minRev is 90 percent. Depending on the platform, some routers that run at 90 percent, for example, 2500s, can exhibit performance degradation versus a high-end router, for example, the 7500 series, which can operate fine.</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15215-collect-cpu-util-snmp.html</p> |SNMP |system.cpu.util[cpmCPUTotal5minRev.{#SNMPINDEX}] |
|Fans |{#SENSOR_INFO}: Fan status |<p>MIB: CISCO-ENVMON-MIB</p> |SNMP |sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}] |
|General |SNMP traps (fallback) |<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p> |SNMP_TRAP |snmptrap.fallback |
|General |System location |<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet, 3rd floor').  If the location is unknown, the value is the zero-length string.</p> |SNMP |system.location[sysLocation.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System contact details |<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</p> |SNMP |system.contact[sysContact.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System object ID |<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity.  This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p> |SNMP |system.objectid[sysObjectID.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System name |<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name.  If the name is unknown, the value is the zero-length string.</p> |SNMP |system.name<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System description |<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p> |SNMP |system.descr[sysDescr.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|Inventory |Hardware model name |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.model<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Hardware serial number |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.serialnumber<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Operating system |<p>MIB: SNMPv2-MIB</p> |SNMP |system.sw.os[sysDescr.0]<p>**Preprocessing**:</p><p>- REGEX: `Version (.+), RELEASE \1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |{#ENT_NAME}: Hardware serial number |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Memory |{#SNMPVALUE}: Used memory |<p>MIB: CISCO-MEMORY-POOL-MIB</p><p>Indicates the number of bytes from the memory pool that are currently in use by applications on the managed device.</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p> |SNMP |vm.memory.used[ciscoMemoryPoolUsed.{#SNMPINDEX}] |
|Memory |{#SNMPVALUE}: Free memory |<p>MIB: CISCO-MEMORY-POOL-MIB</p><p>Indicates the number of bytes from the memory pool that are currently unused on the managed device. Note that the sum of ciscoMemoryPoolUsed and ciscoMemoryPoolFree is the total amount of memory in the pool</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p> |SNMP |vm.memory.free[ciscoMemoryPoolFree.{#SNMPINDEX}] |
|Memory |{#SNMPVALUE}: Memory utilization |<p>Memory utilization in %.</p> |CALCULATED |vm.memory.util[vm.memory.util.{#SNMPINDEX}]<p>**Expression**:</p>`last(//vm.memory.used[ciscoMemoryPoolUsed.{#SNMPINDEX}])/(last(//vm.memory.free[ciscoMemoryPoolFree.{#SNMPINDEX}])+last(//vm.memory.used[ciscoMemoryPoolUsed.{#SNMPINDEX}]))*100` |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Duplex status |<p>MIB: EtherLike-MIB</p><p>The current mode of operation of the MAC</p><p>entity.  'unknown' indicates that the current</p><p>duplex mode could not be determined.</p><p>Management control of the duplex mode is</p><p>accomplished through the MAU MIB.  When</p><p>an interface does not support autonegotiation,</p><p>or when autonegotiation is not enabled, the</p><p>duplex mode is controlled using</p><p>ifMauDefaultType.  When autonegotiation is</p><p>supported and enabled, duplex mode is controlled</p><p>using ifMauAutoNegAdvertisedBits.  In either</p><p>case, the currently operating duplex mode is</p><p>reflected both in this object and in ifMauType.</p><p>Note that this object provides redundant</p><p>information with ifMauType.  Normally, redundant</p><p>objects are discouraged.  However, in this</p><p>instance, it allows a management application to</p><p>determine the duplex status of an interface</p><p>without having to know every possible value of</p><p>ifMauType.  This was felt to be sufficiently</p><p>valuable to justify the redundancy.</p><p>Reference: [IEEE 802.3 Std.], 30.3.1.1.32,aDuplexStatus.</p> |SNMP |net.if.duplex[dot3StatsDuplexStatus.{#SNMPINDEX}] |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Operational status |<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The testing(3) state indicates that no operational packet scan be passed</p><p>- If ifAdminStatus is down(2) then ifOperStatus should be down(2)</p><p>- If ifAdminStatus is changed to up(1) then ifOperStatus should change to up(1) if the interface is ready to transmit and receive network traffic</p><p>- It should change todormant(5) if the interface is waiting for external actions (such as a serial line waiting for an incoming connection)</p><p>- It should remain in the down(2) state if and only if there is a fault that prevents it from going to the up(1) state</p><p>- It should remain in the notPresent(6) state if the interface has missing(typically, hardware) components.</p> |SNMP |net.if.status[ifOperStatus.{#SNMPINDEX}] |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Bits received |<p>MIB: IF-MIB</p><p>The total number of octets received on the interface, including framing characters. This object is a 64-bit version of ifInOctets. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in[ifHCInOctets.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Bits sent |<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. This object is a 64-bit version of ifOutOctets.Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out[ifHCOutOctets.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors |<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in.errors[ifInErrors.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors |<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out.errors[ifOutErrors.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded |<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out.discards[ifOutDiscards.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded |<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in.discards[ifInDiscards.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Interface type |<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for ifType are assigned by the Internet Assigned Numbers Authority (IANA),</p><p>through updating the syntax of the IANAifType textual convention.</p> |SNMP |net.if.type[ifType.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Speed |<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in units of 1,000,000 bits per second. If this object reports a value of `n' then the speed of the interface is somewhere in the range of `n-500,000' to`n+499,999'.  For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth. For a sub-layer which has no concept of bandwidth, this object should be zero.</p> |SNMP |net.if.speed[ifHighSpeed.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1000000`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Power supply |{#SENSOR_INFO}: Power supply status |<p>MIB: CISCO-ENVMON-MIB</p> |SNMP |sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}] |
|Status |Uptime (network) |<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p> |SNMP |system.net.uptime[sysUpTime.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Status |Uptime (hardware) |<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p> |SNMP |system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- MULTIPLIER: `0.01`</p> |
|Status |SNMP agent availability |<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible value:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p> |INTERNAL |zabbix[host,snmp,available] |
|Status |ICMP ping |<p>-</p> |SIMPLE |icmpping |
|Status |ICMP loss |<p>-</p> |SIMPLE |icmppingloss |
|Status |ICMP response time |<p>-</p> |SIMPLE |icmppingsec |
|Temperature |{#SNMPVALUE}: Temperature |<p>MIB: CISCO-ENVMON-MIB</p><p>The current measurement of the test point being instrumented.</p> |SNMP |sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}] |
|Temperature |{#SNMPVALUE}: Temperature status |<p>MIB: CISCO-ENVMON-MIB</p><p>The current state of the test point being instrumented.</p> |SNMP |sensor.temp.status[ciscoEnvMonTemperatureState.{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|#{#SNMPINDEX}: High CPU utilization |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/Cisco IOS by SNMP/system.cpu.util[cpmCPUTotal5minRev.{#SNMPINDEX}],5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|{#SENSOR_INFO}: Fan is in critical state |<p>Please check the fan unit</p> |`count(/Cisco IOS by SNMP/sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"critical\"}")=1 or count(/Cisco IOS by SNMP/sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"shutdown\"}")=1` |AVERAGE | |
|{#SENSOR_INFO}: Fan is in warning state |<p>Please check the fan unit</p> |`count(/Cisco IOS by SNMP/sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}],#1,"eq","{$FAN_WARN_STATUS:\"warning\"}")=1 or count(/Cisco IOS by SNMP/sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}],#1,"eq","{$FAN_WARN_STATUS:\"notFunctioning\"}")=1` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_INFO}: Fan is in critical state</p> |
|System name has changed |<p>System name has changed. Ack to close.</p> |`last(/Cisco IOS by SNMP/system.name,#1)<>last(/Cisco IOS by SNMP/system.name,#2) and length(last(/Cisco IOS by SNMP/system.name))>0` |INFO |<p>Manual close: YES</p> |
|Device has been replaced |<p>Device serial number has changed. Ack to close</p> |`last(/Cisco IOS by SNMP/system.hw.serialnumber,#1)<>last(/Cisco IOS by SNMP/system.hw.serialnumber,#2) and length(last(/Cisco IOS by SNMP/system.hw.serialnumber))>0` |INFO |<p>Manual close: YES</p> |
|Operating system description has changed |<p>Operating system description has changed. Possible reasons that system has been updated or replaced. Ack to close.</p> |`last(/Cisco IOS by SNMP/system.sw.os[sysDescr.0],#1)<>last(/Cisco IOS by SNMP/system.sw.os[sysDescr.0],#2) and length(last(/Cisco IOS by SNMP/system.sw.os[sysDescr.0]))>0` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- System name has changed</p> |
|{#ENT_NAME}: Device has been replaced |<p>Device serial number has changed. Ack to close</p> |`last(/Cisco IOS by SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}],#1)<>last(/Cisco IOS by SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}],#2) and length(last(/Cisco IOS by SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}]))>0` |INFO |<p>Manual close: YES</p> |
|{#SNMPVALUE}: High memory utilization |<p>The system is running out of free memory.</p> |`min(/Cisco IOS by SNMP/vm.memory.util[vm.memory.util.{#SNMPINDEX}],5m)>{$MEMORY.UTIL.MAX}` |AVERAGE | |
|Interface {#IFNAME}({#IFALIAS}): In half-duplex mode |<p>Please check autonegotiation settings and cabling</p> |`last(/Cisco IOS by SNMP/net.if.duplex[dot3StatsDuplexStatus.{#SNMPINDEX}])=2` |WARNING |<p>Manual close: YES</p> |
|Interface {#IFNAME}({#IFALIAS}): Link down |<p>This trigger expression works as follows:</p><p>1. Can be triggered if operations status is down.</p><p>2. {$IFCONTROL:"{#IFNAME}"}=1 - user can redefine Context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.</p><p>3. {TEMPLATE_NAME:METRIC.diff()}=1) - trigger fires only if operational status was up(1) sometime before. (So, do not fire 'ethernal off' interfaces.)</p><p>WARNING: if closed manually - won't fire again on next poll, because of .diff.</p> |`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Cisco IOS by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/Cisco IOS by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/Cisco IOS by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`<p>Recovery expression:</p>`last(/Cisco IOS by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2 or {$IFCONTROL:"{#IFNAME}"}=0` |AVERAGE |<p>Manual close: YES</p> |
|Interface {#IFNAME}({#IFALIAS}): High bandwidth usage |<p>The network interface utilization is close to its estimated maximum bandwidth.</p> |`(avg(/Cisco IOS by SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Cisco IOS by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) or avg(/Cisco IOS by SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Cisco IOS by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])) and last(/Cisco IOS by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0`<p>Recovery expression:</p>`avg(/Cisco IOS by SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/Cisco IOS by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) and avg(/Cisco IOS by SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/Cisco IOS by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): High error rate |<p>Recovers when below 80% of {$IF.ERRORS.WARN:"{#IFNAME}"} threshold</p> |`min(/Cisco IOS by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Cisco IOS by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`<p>Recovery expression:</p>`max(/Cisco IOS by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8 and max(/Cisco IOS by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before |<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Ack to close.</p> |`change(/Cisco IOS by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])<0 and last(/Cisco IOS by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and ( last(/Cisco IOS by SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/Cisco IOS by SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/Cisco IOS by SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/Cisco IOS by SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/Cisco IOS by SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/Cisco IOS by SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/Cisco IOS by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`<p>Recovery expression:</p>`(change(/Cisco IOS by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and last(/Cisco IOS by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}],#2)>0) or (last(/Cisco IOS by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2)` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|{#SENSOR_INFO}: Power supply is in critical state |<p>Please check the power supply unit for errors</p> |`count(/Cisco IOS by SNMP/sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS:\"critical\"}")=1 or count(/Cisco IOS by SNMP/sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS:\"shutdown\"}")=1` |AVERAGE | |
|{#SENSOR_INFO}: Power supply is in warning state |<p>Please check the power supply unit for errors</p> |`count(/Cisco IOS by SNMP/sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}],#1,"eq","{$PSU_WARN_STATUS:\"warning\"}")=1 or count(/Cisco IOS by SNMP/sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}],#1,"eq","{$PSU_WARN_STATUS:\"notFunctioning\"}")=1` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_INFO}: Power supply is in critical state</p> |
|Host has been restarted |<p>Uptime is less than 10 minutes.</p> |`(last(/Cisco IOS by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Cisco IOS by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Cisco IOS by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Cisco IOS by SNMP/system.net.uptime[sysUpTime.0])<10m)` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- No SNMP data collection</p> |
|No SNMP data collection |<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p> |`max(/Cisco IOS by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|Unavailable by ICMP ping |<p>Last three attempts returned timeout.  Please check device connectivity.</p> |`max(/Cisco IOS by SNMP/icmpping,#3)=0` |HIGH | |
|High ICMP ping loss |<p>-</p> |`min(/Cisco IOS by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Cisco IOS by SNMP/icmppingloss,5m)<100` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|High ICMP ping response time |<p>-</p> |`avg(/Cisco IOS by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}` |WARNING |<p>**Depends on**:</p><p>- High ICMP ping loss</p><p>- Unavailable by ICMP ping</p> |
|{#SNMPVALUE}: Temperature is above warning threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco IOS by SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)>{$TEMP_WARN:"{#SNMPVALUE}"} or last(/Cisco IOS by SNMP/sensor.temp.status[ciscoEnvMonTemperatureState.{#SNMPINDEX}])={$TEMP_WARN_STATUS} `<p>Recovery expression:</p>`max(/Cisco IOS by SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)<{$TEMP_WARN:"{#SNMPVALUE}"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SNMPVALUE}: Temperature is above critical threshold</p> |
|{#SNMPVALUE}: Temperature is above critical threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco IOS by SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"{#SNMPVALUE}"} or last(/Cisco IOS by SNMP/sensor.temp.status[ciscoEnvMonTemperatureState.{#SNMPINDEX}])={$TEMP_CRIT_STATUS} or last(/Cisco IOS by SNMP/sensor.temp.status[ciscoEnvMonTemperatureState.{#SNMPINDEX}])={$TEMP_DISASTER_STATUS} `<p>Recovery expression:</p>`max(/Cisco IOS by SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"{#SNMPVALUE}"}-3` |HIGH | |
|{#SNMPVALUE}: Temperature is too low |<p>-</p> |`avg(/Cisco IOS by SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"{#SNMPVALUE}"}`<p>Recovery expression:</p>`min(/Cisco IOS by SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"{#SNMPVALUE}"}+3` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

## Known Issues

- Description: no if(in|out)(Errors|Discards) are available for vlan ifType
  - Version: IOS for example: 12.1(22)EA11, 15.4(3)M2
  - Device: C2911, C7600

# Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP

## Overview

For Zabbix version: 6.2 and higher.

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>-</p> |`90` |
|{$FAN_CRIT_STATUS:"critical"} |<p>-</p> |`3` |
|{$FAN_CRIT_STATUS:"shutdown"} |<p>-</p> |`4` |
|{$FAN_WARN_STATUS:"notFunctioning"} |<p>-</p> |`6` |
|{$FAN_WARN_STATUS:"warning"} |<p>-</p> |`2` |
|{$ICMP_LOSS_WARN} |<p>-</p> |`20` |
|{$ICMP_RESPONSE_TIME_WARN} |<p>-</p> |`0.15` |
|{$IF.ERRORS.WARN} |<p>-</p> |`2` |
|{$IF.UTIL.MAX} |<p>-</p> |`90` |
|{$IFCONTROL} |<p>-</p> |`1` |
|{$MEMORY.UTIL.MAX} |<p>-</p> |`90` |
|{$NET.IF.IFADMINSTATUS.MATCHES} |<p>Ignore notPresent(6)</p> |`^.*` |
|{$NET.IF.IFADMINSTATUS.NOT_MATCHES} |<p>Ignore down(2) administrative status</p> |`^2$` |
|{$NET.IF.IFALIAS.MATCHES} |<p>-</p> |`.*` |
|{$NET.IF.IFALIAS.NOT_MATCHES} |<p>-</p> |`CHANGE_IF_NEEDED` |
|{$NET.IF.IFDESCR.MATCHES} |<p>-</p> |`.*` |
|{$NET.IF.IFDESCR.NOT_MATCHES} |<p>-</p> |`CHANGE_IF_NEEDED` |
|{$NET.IF.IFNAME.MATCHES} |<p>-</p> |`^.*$` |
|{$NET.IF.IFNAME.NOT_MATCHES} |<p>Filter out loopbacks, nulls, docker veth links and docker0 bridge by default</p> |`(^Software Loopback Interface|^NULL[0-9.]*$|^[Ll]o[0-9.]*$|^[Ss]ystem$|^Nu[0-9.]*$|^veth[0-9a-z]+$|docker[0-9]+|br-[a-z0-9]{12})` |
|{$NET.IF.IFOPERSTATUS.MATCHES} |<p>-</p> |`^.*$` |
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES} |<p>Ignore notPresent(6)</p> |`^6$` |
|{$NET.IF.IFTYPE.MATCHES} |<p>-</p> |`.*` |
|{$NET.IF.IFTYPE.NOT_MATCHES} |<p>-</p> |`CHANGE_IF_NEEDED` |
|{$PSU_CRIT_STATUS:"critical"} |<p>-</p> |`3` |
|{$PSU_CRIT_STATUS:"shutdown"} |<p>-</p> |`4` |
|{$PSU_WARN_STATUS:"notFunctioning"} |<p>-</p> |`6` |
|{$PSU_WARN_STATUS:"warning"} |<p>-</p> |`2` |
|{$SNMP.TIMEOUT} |<p>-</p> |`5m` |
|{$TEMP_CRIT:"CPU"} |<p>-</p> |`75` |
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT_STATUS} |<p>-</p> |`3` |
|{$TEMP_CRIT} |<p>-</p> |`60` |
|{$TEMP_DISASTER_STATUS} |<p>-</p> |`4` |
|{$TEMP_WARN:"CPU"} |<p>-</p> |`70` |
|{$TEMP_WARN_STATUS} |<p>-</p> |`2` |
|{$TEMP_WARN} |<p>-</p> |`50` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|CPU Discovery |<p>If your IOS device has several CPUs, you must use CISCO-PROCESS-MIB and its object cpmCPUTotal5minRev from the table called cpmCPUTotalTable,</p><p>indexed with cpmCPUTotalIndex.</p><p>This table allows CISCO-PROCESS-MIB to keep CPU statistics for different physical entities in the router,</p><p>like different CPU chips, group of CPUs, or CPUs in different modules/cards.</p><p>In case of a single CPU, cpmCPUTotalTable has only one entry.</p> |SNMP |cpu.discovery |
|Entity Serial Numbers Discovery |<p>-</p> |SNMP |entity_sn.discovery<p>**Filter**:</p>AND <p>- {#ENT_SN} MATCHES_REGEX `.+`</p><p>- {#ENT_CLASS} MATCHES_REGEX `^3$`</p> |
|FAN Discovery |<p>The table of fan status maintained by the environmental monitor.</p> |SNMP |fan.discovery |
|Memory Discovery |<p>Discovery of ciscoMemoryPoolTable, a table of memory pool monitoring entries.</p><p>http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p> |SNMP |memory.discovery |
|Network interfaces discovery |<p>Discovering interfaces from IF-MIB.</p> |SNMP |net.if.discovery<p>**Filter**:</p>AND <p>- {#IFADMINSTATUS} MATCHES_REGEX `{$NET.IF.IFADMINSTATUS.MATCHES}`</p><p>- {#IFADMINSTATUS} NOT_MATCHES_REGEX `{$NET.IF.IFADMINSTATUS.NOT_MATCHES}`</p><p>- {#IFOPERSTATUS} MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.MATCHES}`</p><p>- {#IFOPERSTATUS} NOT_MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.NOT_MATCHES}`</p><p>- {#IFNAME} MATCHES_REGEX `{$NET.IF.IFNAME.MATCHES}`</p><p>- {#IFNAME} NOT_MATCHES_REGEX `{$NET.IF.IFNAME.NOT_MATCHES}`</p><p>- {#IFDESCR} MATCHES_REGEX `{$NET.IF.IFDESCR.MATCHES}`</p><p>- {#IFDESCR} NOT_MATCHES_REGEX `{$NET.IF.IFDESCR.NOT_MATCHES}`</p><p>- {#IFALIAS} MATCHES_REGEX `{$NET.IF.IFALIAS.MATCHES}`</p><p>- {#IFALIAS} NOT_MATCHES_REGEX `{$NET.IF.IFALIAS.NOT_MATCHES}`</p><p>- {#IFTYPE} MATCHES_REGEX `{$NET.IF.IFTYPE.MATCHES}`</p><p>- {#IFTYPE} NOT_MATCHES_REGEX `{$NET.IF.IFTYPE.NOT_MATCHES}`</p> |
|PSU Discovery |<p>The table of power supply status maintained by the environmental monitor card.</p> |SNMP |psu.discovery |
|Temperature Discovery |<p>Discovery of ciscoEnvMonTemperatureTable (ciscoEnvMonTemperatureDescr), a table of ambient temperature status</p><p>maintained by the environmental monitor.</p> |SNMP |temperature.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |{#SNMPVALUE}: CPU utilization |<p>MIB: CISCO-PROCESS-MIB</p><p>The overall CPU busy percentage in the last 5 minute</p><p>period. This object deprecates the avgBusy5 object from</p><p>the OLD-CISCO-SYSTEM-MIB. This object is deprecated</p><p>by cpmCPUTotal5minRev which has the changed range</p><p>of value (0..100).</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15215-collect-cpu-util-snmp.html</p> |SNMP |system.cpu.util[cpmCPUTotal5min.{#SNMPINDEX}] |
|Fans |{#SENSOR_INFO}: Fan status |<p>MIB: CISCO-ENVMON-MIB</p> |SNMP |sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}] |
|General |SNMP traps (fallback) |<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p> |SNMP_TRAP |snmptrap.fallback |
|General |System location |<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet, 3rd floor').  If the location is unknown, the value is the zero-length string.</p> |SNMP |system.location[sysLocation.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System contact details |<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</p> |SNMP |system.contact[sysContact.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System object ID |<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity.  This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p> |SNMP |system.objectid[sysObjectID.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System name |<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name.  If the name is unknown, the value is the zero-length string.</p> |SNMP |system.name<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System description |<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p> |SNMP |system.descr[sysDescr.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|Inventory |Hardware model name |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.model<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Hardware serial number |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.serialnumber<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Operating system |<p>MIB: SNMPv2-MIB</p> |SNMP |system.sw.os[sysDescr.0]<p>**Preprocessing**:</p><p>- REGEX: `Version (.+), RELEASE \1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |{#ENT_NAME}: Hardware serial number |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Memory |{#SNMPVALUE}: Used memory |<p>MIB: CISCO-MEMORY-POOL-MIB</p><p>Indicates the number of bytes from the memory pool that are currently in use by applications on the managed device.</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p> |SNMP |vm.memory.used[ciscoMemoryPoolUsed.{#SNMPINDEX}] |
|Memory |{#SNMPVALUE}: Free memory |<p>MIB: CISCO-MEMORY-POOL-MIB</p><p>Indicates the number of bytes from the memory pool that are currently unused on the managed device. Note that the sum of ciscoMemoryPoolUsed and ciscoMemoryPoolFree is the total amount of memory in the pool</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p> |SNMP |vm.memory.free[ciscoMemoryPoolFree.{#SNMPINDEX}] |
|Memory |{#SNMPVALUE}: Memory utilization |<p>Memory utilization in %.</p> |CALCULATED |vm.memory.util[vm.memory.util.{#SNMPINDEX}]<p>**Expression**:</p>`last(//vm.memory.used[ciscoMemoryPoolUsed.{#SNMPINDEX}])/(last(//vm.memory.free[ciscoMemoryPoolFree.{#SNMPINDEX}])+last(//vm.memory.used[ciscoMemoryPoolUsed.{#SNMPINDEX}]))*100` |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Operational status |<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The testing(3) state indicates that no operational packet scan be passed</p><p>- If ifAdminStatus is down(2) then ifOperStatus should be down(2)</p><p>- If ifAdminStatus is changed to up(1) then ifOperStatus should change to up(1) if the interface is ready to transmit and receive network traffic</p><p>- It should change todormant(5) if the interface is waiting for external actions (such as a serial line waiting for an incoming connection)</p><p>- It should remain in the down(2) state if and only if there is a fault that prevents it from going to the up(1) state</p><p>- It should remain in the notPresent(6) state if the interface has missing(typically, hardware) components.</p> |SNMP |net.if.status[ifOperStatus.{#SNMPINDEX}] |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Bits received |<p>MIB: IF-MIB</p><p>The total number of octets received on the interface, including framing characters. This object is a 64-bit version of ifInOctets. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in[ifHCInOctets.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Bits sent |<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. This object is a 64-bit version of ifOutOctets.Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out[ifHCOutOctets.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors |<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in.errors[ifInErrors.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors |<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out.errors[ifOutErrors.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded |<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out.discards[ifOutDiscards.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded |<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in.discards[ifInDiscards.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Interface type |<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for ifType are assigned by the Internet Assigned Numbers Authority (IANA),</p><p>through updating the syntax of the IANAifType textual convention.</p> |SNMP |net.if.type[ifType.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Speed |<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in units of 1,000,000 bits per second. If this object reports a value of `n' then the speed of the interface is somewhere in the range of `n-500,000' to`n+499,999'.  For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth. For a sub-layer which has no concept of bandwidth, this object should be zero.</p> |SNMP |net.if.speed[ifHighSpeed.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1000000`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Power supply |{#SENSOR_INFO}: Power supply status |<p>MIB: CISCO-ENVMON-MIB</p> |SNMP |sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}] |
|Status |Uptime (network) |<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p> |SNMP |system.net.uptime[sysUpTime.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Status |Uptime (hardware) |<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p> |SNMP |system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- MULTIPLIER: `0.01`</p> |
|Status |SNMP agent availability |<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible value:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p> |INTERNAL |zabbix[host,snmp,available] |
|Status |ICMP ping |<p>-</p> |SIMPLE |icmpping |
|Status |ICMP loss |<p>-</p> |SIMPLE |icmppingloss |
|Status |ICMP response time |<p>-</p> |SIMPLE |icmppingsec |
|Temperature |{#SNMPVALUE}: Temperature |<p>MIB: CISCO-ENVMON-MIB</p><p>The current measurement of the test point being instrumented.</p> |SNMP |sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}] |
|Temperature |{#SNMPVALUE}: Temperature status |<p>MIB: CISCO-ENVMON-MIB</p><p>The current state of the test point being instrumented.</p> |SNMP |sensor.temp.status[ciscoEnvMonTemperatureState.{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#SNMPVALUE}: High CPU utilization |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/system.cpu.util[cpmCPUTotal5min.{#SNMPINDEX}],5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|{#SENSOR_INFO}: Fan is in critical state |<p>Please check the fan unit</p> |`count(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"critical\"}")=1 or count(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"shutdown\"}")=1` |AVERAGE | |
|{#SENSOR_INFO}: Fan is in warning state |<p>Please check the fan unit</p> |`count(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}],#1,"eq","{$FAN_WARN_STATUS:\"warning\"}")=1 or count(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}],#1,"eq","{$FAN_WARN_STATUS:\"notFunctioning\"}")=1` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_INFO}: Fan is in critical state</p> |
|System name has changed |<p>System name has changed. Ack to close.</p> |`last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/system.name,#1)<>last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/system.name,#2) and length(last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/system.name))>0` |INFO |<p>Manual close: YES</p> |
|Device has been replaced |<p>Device serial number has changed. Ack to close</p> |`last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/system.hw.serialnumber,#1)<>last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/system.hw.serialnumber,#2) and length(last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/system.hw.serialnumber))>0` |INFO |<p>Manual close: YES</p> |
|Operating system description has changed |<p>Operating system description has changed. Possible reasons that system has been updated or replaced. Ack to close.</p> |`last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/system.sw.os[sysDescr.0],#1)<>last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/system.sw.os[sysDescr.0],#2) and length(last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/system.sw.os[sysDescr.0]))>0` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- System name has changed</p> |
|{#ENT_NAME}: Device has been replaced |<p>Device serial number has changed. Ack to close</p> |`last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}],#1)<>last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}],#2) and length(last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}]))>0` |INFO |<p>Manual close: YES</p> |
|{#SNMPVALUE}: High memory utilization |<p>The system is running out of free memory.</p> |`min(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/vm.memory.util[vm.memory.util.{#SNMPINDEX}],5m)>{$MEMORY.UTIL.MAX}` |AVERAGE | |
|Interface {#IFNAME}({#IFALIAS}): Link down |<p>This trigger expression works as follows:</p><p>1. Can be triggered if operations status is down.</p><p>2. {$IFCONTROL:"{#IFNAME}"}=1 - user can redefine Context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.</p><p>3. {TEMPLATE_NAME:METRIC.diff()}=1) - trigger fires only if operational status was up(1) sometime before. (So, do not fire 'ethernal off' interfaces.)</p><p>WARNING: if closed manually - won't fire again on next poll, because of .diff.</p> |`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`<p>Recovery expression:</p>`last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2 or {$IFCONTROL:"{#IFNAME}"}=0` |AVERAGE |<p>Manual close: YES</p> |
|Interface {#IFNAME}({#IFALIAS}): High bandwidth usage |<p>The network interface utilization is close to its estimated maximum bandwidth.</p> |`(avg(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) or avg(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])) and last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0`<p>Recovery expression:</p>`avg(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) and avg(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): High error rate |<p>Recovers when below 80% of {$IF.ERRORS.WARN:"{#IFNAME}"} threshold</p> |`min(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`<p>Recovery expression:</p>`max(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8 and max(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before |<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Ack to close.</p> |`change(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])<0 and last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and ( last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`<p>Recovery expression:</p>`(change(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}],#2)>0) or (last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2)` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|{#SENSOR_INFO}: Power supply is in critical state |<p>Please check the power supply unit for errors</p> |`count(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS:\"critical\"}")=1 or count(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS:\"shutdown\"}")=1` |AVERAGE | |
|{#SENSOR_INFO}: Power supply is in warning state |<p>Please check the power supply unit for errors</p> |`count(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}],#1,"eq","{$PSU_WARN_STATUS:\"warning\"}")=1 or count(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}],#1,"eq","{$PSU_WARN_STATUS:\"notFunctioning\"}")=1` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_INFO}: Power supply is in critical state</p> |
|Host has been restarted |<p>Uptime is less than 10 minutes.</p> |`(last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/system.net.uptime[sysUpTime.0])<10m)` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- No SNMP data collection</p> |
|No SNMP data collection |<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p> |`max(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|Unavailable by ICMP ping |<p>Last three attempts returned timeout.  Please check device connectivity.</p> |`max(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/icmpping,#3)=0` |HIGH | |
|High ICMP ping loss |<p>-</p> |`min(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/icmppingloss,5m)<100` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|High ICMP ping response time |<p>-</p> |`avg(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}` |WARNING |<p>**Depends on**:</p><p>- High ICMP ping loss</p><p>- Unavailable by ICMP ping</p> |
|{#SNMPVALUE}: Temperature is above warning threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)>{$TEMP_WARN:"{#SNMPVALUE}"} or last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/sensor.temp.status[ciscoEnvMonTemperatureState.{#SNMPINDEX}])={$TEMP_WARN_STATUS} `<p>Recovery expression:</p>`max(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)<{$TEMP_WARN:"{#SNMPVALUE}"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SNMPVALUE}: Temperature is above critical threshold</p> |
|{#SNMPVALUE}: Temperature is above critical threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"{#SNMPVALUE}"} or last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/sensor.temp.status[ciscoEnvMonTemperatureState.{#SNMPINDEX}])={$TEMP_CRIT_STATUS} or last(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/sensor.temp.status[ciscoEnvMonTemperatureState.{#SNMPINDEX}])={$TEMP_DISASTER_STATUS} `<p>Recovery expression:</p>`max(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"{#SNMPVALUE}"}-3` |HIGH | |
|{#SNMPVALUE}: Temperature is too low |<p>-</p> |`avg(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"{#SNMPVALUE}"}`<p>Recovery expression:</p>`min(/Cisco IOS versions 12.0_3_T-12.2_3.5 by SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"{#SNMPVALUE}"}+3` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

# Cisco IOS prior to 12.0_3_T by SNMP

## Overview

For Zabbix version: 6.2 and higher.

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>-</p> |`90` |
|{$FAN_CRIT_STATUS:"critical"} |<p>-</p> |`3` |
|{$FAN_CRIT_STATUS:"shutdown"} |<p>-</p> |`4` |
|{$FAN_WARN_STATUS:"notFunctioning"} |<p>-</p> |`6` |
|{$FAN_WARN_STATUS:"warning"} |<p>-</p> |`2` |
|{$ICMP_LOSS_WARN} |<p>-</p> |`20` |
|{$ICMP_RESPONSE_TIME_WARN} |<p>-</p> |`0.15` |
|{$MEMORY.UTIL.MAX} |<p>-</p> |`90` |
|{$PSU_CRIT_STATUS:"critical"} |<p>-</p> |`3` |
|{$PSU_CRIT_STATUS:"shutdown"} |<p>-</p> |`4` |
|{$PSU_WARN_STATUS:"notFunctioning"} |<p>-</p> |`6` |
|{$PSU_WARN_STATUS:"warning"} |<p>-</p> |`2` |
|{$SNMP.TIMEOUT} |<p>-</p> |`5m` |
|{$TEMP_CRIT:"CPU"} |<p>-</p> |`75` |
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT_STATUS} |<p>-</p> |`3` |
|{$TEMP_CRIT} |<p>-</p> |`60` |
|{$TEMP_DISASTER_STATUS} |<p>-</p> |`4` |
|{$TEMP_WARN:"CPU"} |<p>-</p> |`70` |
|{$TEMP_WARN_STATUS} |<p>-</p> |`2` |
|{$TEMP_WARN} |<p>-</p> |`50` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Entity Serial Numbers Discovery |<p>-</p> |SNMP |entity_sn.discovery<p>**Filter**:</p>AND <p>- {#ENT_SN} MATCHES_REGEX `.+`</p><p>- {#ENT_CLASS} MATCHES_REGEX `^3$`</p> |
|FAN Discovery |<p>The table of fan status maintained by the environmental monitor.</p> |SNMP |fan.discovery |
|Memory Discovery |<p>Discovery of ciscoMemoryPoolTable, a table of memory pool monitoring entries.</p><p>http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p> |SNMP |memory.discovery |
|PSU Discovery |<p>The table of power supply status maintained by the environmental monitor card.</p> |SNMP |psu.discovery |
|Temperature Discovery |<p>Discovery of ciscoEnvMonTemperatureTable (ciscoEnvMonTemperatureDescr), a table of ambient temperature status</p><p>maintained by the environmental monitor.</p> |SNMP |temperature.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |CPU utilization |<p>MIB: OLD-CISCO-CPU-MIB</p><p>5 minute exponentially-decayed moving average of the CPU busy percentage.</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15215-collect-cpu-util-snmp.html</p> |SNMP |system.cpu.util[avgBusy5] |
|Fans |{#SENSOR_INFO}: Fan status |<p>MIB: CISCO-ENVMON-MIB</p> |SNMP |sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}] |
|General |SNMP traps (fallback) |<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p> |SNMP_TRAP |snmptrap.fallback |
|General |System location |<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet, 3rd floor').  If the location is unknown, the value is the zero-length string.</p> |SNMP |system.location[sysLocation.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System contact details |<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</p> |SNMP |system.contact[sysContact.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System object ID |<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity.  This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p> |SNMP |system.objectid[sysObjectID.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System name |<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name.  If the name is unknown, the value is the zero-length string.</p> |SNMP |system.name<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System description |<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p> |SNMP |system.descr[sysDescr.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|Inventory |Hardware model name |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.model<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Hardware serial number |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.serialnumber<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Operating system |<p>MIB: SNMPv2-MIB</p> |SNMP |system.sw.os[sysDescr.0]<p>**Preprocessing**:</p><p>- REGEX: `Version (.+), RELEASE \1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |{#ENT_NAME}: Hardware serial number |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Memory |{#SNMPVALUE}: Used memory |<p>MIB: CISCO-MEMORY-POOL-MIB</p><p>Indicates the number of bytes from the memory pool that are currently in use by applications on the managed device.</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p> |SNMP |vm.memory.used[ciscoMemoryPoolUsed.{#SNMPINDEX}] |
|Memory |{#SNMPVALUE}: Free memory |<p>MIB: CISCO-MEMORY-POOL-MIB</p><p>Indicates the number of bytes from the memory pool that are currently unused on the managed device. Note that the sum of ciscoMemoryPoolUsed and ciscoMemoryPoolFree is the total amount of memory in the pool</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p> |SNMP |vm.memory.free[ciscoMemoryPoolFree.{#SNMPINDEX}] |
|Memory |{#SNMPVALUE}: Memory utilization |<p>Memory utilization in %.</p> |CALCULATED |vm.memory.util[vm.memory.util.{#SNMPINDEX}]<p>**Expression**:</p>`last(//vm.memory.used[ciscoMemoryPoolUsed.{#SNMPINDEX}])/(last(//vm.memory.free[ciscoMemoryPoolFree.{#SNMPINDEX}])+last(//vm.memory.used[ciscoMemoryPoolUsed.{#SNMPINDEX}]))*100` |
|Power supply |{#SENSOR_INFO}: Power supply status |<p>MIB: CISCO-ENVMON-MIB</p> |SNMP |sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}] |
|Status |Uptime (network) |<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p> |SNMP |system.net.uptime[sysUpTime.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Status |Uptime (hardware) |<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p> |SNMP |system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- MULTIPLIER: `0.01`</p> |
|Status |SNMP agent availability |<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible value:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p> |INTERNAL |zabbix[host,snmp,available] |
|Status |ICMP ping |<p>-</p> |SIMPLE |icmpping |
|Status |ICMP loss |<p>-</p> |SIMPLE |icmppingloss |
|Status |ICMP response time |<p>-</p> |SIMPLE |icmppingsec |
|Temperature |{#SNMPVALUE}: Temperature |<p>MIB: CISCO-ENVMON-MIB</p><p>The current measurement of the test point being instrumented.</p> |SNMP |sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}] |
|Temperature |{#SNMPVALUE}: Temperature status |<p>MIB: CISCO-ENVMON-MIB</p><p>The current state of the test point being instrumented.</p> |SNMP |sensor.temp.status[ciscoEnvMonTemperatureState.{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|High CPU utilization |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/Cisco IOS prior to 12.0_3_T by SNMP/system.cpu.util[avgBusy5],5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|{#SENSOR_INFO}: Fan is in critical state |<p>Please check the fan unit</p> |`count(/Cisco IOS prior to 12.0_3_T by SNMP/sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"critical\"}")=1 or count(/Cisco IOS prior to 12.0_3_T by SNMP/sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"shutdown\"}")=1` |AVERAGE | |
|{#SENSOR_INFO}: Fan is in warning state |<p>Please check the fan unit</p> |`count(/Cisco IOS prior to 12.0_3_T by SNMP/sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}],#1,"eq","{$FAN_WARN_STATUS:\"warning\"}")=1 or count(/Cisco IOS prior to 12.0_3_T by SNMP/sensor.fan.status[ciscoEnvMonFanState.{#SNMPINDEX}],#1,"eq","{$FAN_WARN_STATUS:\"notFunctioning\"}")=1` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_INFO}: Fan is in critical state</p> |
|System name has changed |<p>System name has changed. Ack to close.</p> |`last(/Cisco IOS prior to 12.0_3_T by SNMP/system.name,#1)<>last(/Cisco IOS prior to 12.0_3_T by SNMP/system.name,#2) and length(last(/Cisco IOS prior to 12.0_3_T by SNMP/system.name))>0` |INFO |<p>Manual close: YES</p> |
|Device has been replaced |<p>Device serial number has changed. Ack to close</p> |`last(/Cisco IOS prior to 12.0_3_T by SNMP/system.hw.serialnumber,#1)<>last(/Cisco IOS prior to 12.0_3_T by SNMP/system.hw.serialnumber,#2) and length(last(/Cisco IOS prior to 12.0_3_T by SNMP/system.hw.serialnumber))>0` |INFO |<p>Manual close: YES</p> |
|Operating system description has changed |<p>Operating system description has changed. Possible reasons that system has been updated or replaced. Ack to close.</p> |`last(/Cisco IOS prior to 12.0_3_T by SNMP/system.sw.os[sysDescr.0],#1)<>last(/Cisco IOS prior to 12.0_3_T by SNMP/system.sw.os[sysDescr.0],#2) and length(last(/Cisco IOS prior to 12.0_3_T by SNMP/system.sw.os[sysDescr.0]))>0` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- System name has changed</p> |
|{#ENT_NAME}: Device has been replaced |<p>Device serial number has changed. Ack to close</p> |`last(/Cisco IOS prior to 12.0_3_T by SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}],#1)<>last(/Cisco IOS prior to 12.0_3_T by SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}],#2) and length(last(/Cisco IOS prior to 12.0_3_T by SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}]))>0` |INFO |<p>Manual close: YES</p> |
|{#SNMPVALUE}: High memory utilization |<p>The system is running out of free memory.</p> |`min(/Cisco IOS prior to 12.0_3_T by SNMP/vm.memory.util[vm.memory.util.{#SNMPINDEX}],5m)>{$MEMORY.UTIL.MAX}` |AVERAGE | |
|{#SENSOR_INFO}: Power supply is in critical state |<p>Please check the power supply unit for errors</p> |`count(/Cisco IOS prior to 12.0_3_T by SNMP/sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS:\"critical\"}")=1 or count(/Cisco IOS prior to 12.0_3_T by SNMP/sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS:\"shutdown\"}")=1` |AVERAGE | |
|{#SENSOR_INFO}: Power supply is in warning state |<p>Please check the power supply unit for errors</p> |`count(/Cisco IOS prior to 12.0_3_T by SNMP/sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}],#1,"eq","{$PSU_WARN_STATUS:\"warning\"}")=1 or count(/Cisco IOS prior to 12.0_3_T by SNMP/sensor.psu.status[ciscoEnvMonSupplyState.{#SNMPINDEX}],#1,"eq","{$PSU_WARN_STATUS:\"notFunctioning\"}")=1` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_INFO}: Power supply is in critical state</p> |
|Host has been restarted |<p>Uptime is less than 10 minutes.</p> |`(last(/Cisco IOS prior to 12.0_3_T by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Cisco IOS prior to 12.0_3_T by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Cisco IOS prior to 12.0_3_T by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Cisco IOS prior to 12.0_3_T by SNMP/system.net.uptime[sysUpTime.0])<10m)` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- No SNMP data collection</p> |
|No SNMP data collection |<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p> |`max(/Cisco IOS prior to 12.0_3_T by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|Unavailable by ICMP ping |<p>Last three attempts returned timeout.  Please check device connectivity.</p> |`max(/Cisco IOS prior to 12.0_3_T by SNMP/icmpping,#3)=0` |HIGH | |
|High ICMP ping loss |<p>-</p> |`min(/Cisco IOS prior to 12.0_3_T by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Cisco IOS prior to 12.0_3_T by SNMP/icmppingloss,5m)<100` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|High ICMP ping response time |<p>-</p> |`avg(/Cisco IOS prior to 12.0_3_T by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}` |WARNING |<p>**Depends on**:</p><p>- High ICMP ping loss</p><p>- Unavailable by ICMP ping</p> |
|{#SNMPVALUE}: Temperature is above warning threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco IOS prior to 12.0_3_T by SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)>{$TEMP_WARN:"{#SNMPVALUE}"} or last(/Cisco IOS prior to 12.0_3_T by SNMP/sensor.temp.status[ciscoEnvMonTemperatureState.{#SNMPINDEX}])={$TEMP_WARN_STATUS} `<p>Recovery expression:</p>`max(/Cisco IOS prior to 12.0_3_T by SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)<{$TEMP_WARN:"{#SNMPVALUE}"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SNMPVALUE}: Temperature is above critical threshold</p> |
|{#SNMPVALUE}: Temperature is above critical threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco IOS prior to 12.0_3_T by SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"{#SNMPVALUE}"} or last(/Cisco IOS prior to 12.0_3_T by SNMP/sensor.temp.status[ciscoEnvMonTemperatureState.{#SNMPINDEX}])={$TEMP_CRIT_STATUS} or last(/Cisco IOS prior to 12.0_3_T by SNMP/sensor.temp.status[ciscoEnvMonTemperatureState.{#SNMPINDEX}])={$TEMP_DISASTER_STATUS} `<p>Recovery expression:</p>`max(/Cisco IOS prior to 12.0_3_T by SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"{#SNMPVALUE}"}-3` |HIGH | |
|{#SNMPVALUE}: Temperature is too low |<p>-</p> |`avg(/Cisco IOS prior to 12.0_3_T by SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"{#SNMPVALUE}"}`<p>Recovery expression:</p>`min(/Cisco IOS prior to 12.0_3_T by SNMP/sensor.temp.value[ciscoEnvMonTemperatureValue.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"{#SNMPVALUE}"}+3` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

