
# Brocade_Foundry Nonstackable SNMP

## Overview

For Zabbix version: 6.0 and higher  
For devices(old Foundry devices, MLXe and so on) that doesn't support Stackable SNMP Tables: snChasFan2Table, snChasPwrSupply2Table,snAgentTemp2Table -
FOUNDRY-SN-AGENT-MIB::snChasFanTable, snChasPwrSupplyTable,snAgentTempTable are used instead.
For example:
The objects in table snChasPwrSupply2Table is not supported on the NetIron and the FastIron SX devices.
snChasFan2Table is not supported on  on the NetIron devices.
snAgentTemp2Table is not supported on old versions of MLXe.

This template was tested on:

- Brocade MLXe, version (System Mode: MLX), IronWare Version V5.4.0eT163 Compiled on Oct 30 2013 at 16:40:24 labeled as V5.4.00e
- Foundry FLS648, version Foundry Networks, Inc. FLS648, IronWare Version 04.1.00bT7e1 Compiled on Feb 29 2008 at 21:35:28 labeled as FGS04100b
- Foundry FWSX424, version Foundry Networks, Inc. FWSX424, IronWare Version 02.0.00aT1e0 Compiled on Dec 10 2004 at 14:40:19 labeled as FWXS02000a

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>-</p> |`90` |
|{$FAN_CRIT_STATUS} |<p>-</p> |`3` |
|{$FAN_OK_STATUS} |<p>-</p> |`2` |
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
|{$PSU_CRIT_STATUS} |<p>-</p> |`3` |
|{$PSU_OK_STATUS} |<p>-</p> |`2` |
|{$SNMP.TIMEOUT} |<p>-</p> |`5m` |
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT} |<p>-</p> |`75` |
|{$TEMP_WARN} |<p>-</p> |`65` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|FAN Discovery |<p>snChasFanTable: A table of each fan information. Only installed fan appears in a table row.</p> |SNMP |fan.discovery |
|Network interfaces discovery |<p>Discovering interfaces from IF-MIB.</p> |SNMP |net.if.discovery<p>**Filter**:</p>AND <p>- {#IFADMINSTATUS} MATCHES_REGEX `{$NET.IF.IFADMINSTATUS.MATCHES}`</p><p>- {#IFADMINSTATUS} NOT_MATCHES_REGEX `{$NET.IF.IFADMINSTATUS.NOT_MATCHES}`</p><p>- {#IFOPERSTATUS} MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.MATCHES}`</p><p>- {#IFOPERSTATUS} NOT_MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.NOT_MATCHES}`</p><p>- {#IFNAME} MATCHES_REGEX `{$NET.IF.IFNAME.MATCHES}`</p><p>- {#IFNAME} NOT_MATCHES_REGEX `{$NET.IF.IFNAME.NOT_MATCHES}`</p><p>- {#IFDESCR} MATCHES_REGEX `{$NET.IF.IFDESCR.MATCHES}`</p><p>- {#IFDESCR} NOT_MATCHES_REGEX `{$NET.IF.IFDESCR.NOT_MATCHES}`</p><p>- {#IFALIAS} MATCHES_REGEX `{$NET.IF.IFALIAS.MATCHES}`</p><p>- {#IFALIAS} NOT_MATCHES_REGEX `{$NET.IF.IFALIAS.NOT_MATCHES}`</p><p>- {#IFTYPE} MATCHES_REGEX `{$NET.IF.IFTYPE.MATCHES}`</p><p>- {#IFTYPE} NOT_MATCHES_REGEX `{$NET.IF.IFTYPE.NOT_MATCHES}`</p> |
|PSU Discovery |<p>snChasPwrSupplyTable: A table of each power supply information. Only installed power supply appears in a table row.</p> |SNMP |psu.discovery |
|Temperature Discovery |<p>snAgentTempTable:Table to list temperatures of the modules in the device. This table is applicable to only those modules with temperature sensors.</p> |SNMP |temp.discovery |
|Temperature Discovery Chassis |<p>Since temperature of the chassis is not available on all Brocade/Foundry hardware, this LLD is here to avoid unsupported items.</p> |SNMP |temp.chassis.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |CPU utilization |<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The statistics collection of 1 minute CPU utilization.</p> |SNMP |system.cpu.util[snAgGblCpuUtil1MinAvg.0] |
|Fans |Fan {#FAN_INDEX}: Fan status |<p>MIB: FOUNDRY-SN-AGENT-MIB</p> |SNMP |sensor.fan.status[snChasFanOperStatus.{#SNMPINDEX}] |
|General |SNMP traps (fallback) |<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p> |SNMP_TRAP |snmptrap.fallback |
|General |System location |<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet, 3rd floor').  If the location is unknown, the value is the zero-length string.</p> |SNMP |system.location[sysLocation.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System contact details |<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</p> |SNMP |system.contact[sysContact.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System object ID |<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity.  This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p> |SNMP |system.objectid[sysObjectID.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System name |<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name.  If the name is unknown, the value is the zero-length string.</p> |SNMP |system.name<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System description |<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p> |SNMP |system.descr[sysDescr.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|Inventory |Hardware serial number |<p>MIB: FOUNDRY-SN-AGENT-MIB</p> |SNMP |system.hw.serialnumber<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Firmware version |<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The version of the running software in the form'major.minor.maintenance[letters]'</p> |SNMP |system.hw.firmware<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Memory |Memory utilization |<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The system dynamic memory utilization, in unit of percentage.</p><p>Deprecated: Refer to snAgSystemDRAMUtil.</p><p>For NI platforms, refer to snAgentBrdMemoryUtil100thPercent.</p> |SNMP |vm.memory.util[snAgGblDynMemUtil.0] |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Operational status |<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The testing(3) state indicates that no operational packet scan be passed</p><p>- If ifAdminStatus is down(2) then ifOperStatus should be down(2)</p><p>- If ifAdminStatus is changed to up(1) then ifOperStatus should change to up(1) if the interface is ready to transmit and receive network traffic</p><p>- It should change todormant(5) if the interface is waiting for external actions (such as a serial line waiting for an incoming connection)</p><p>- It should remain in the down(2) state if and only if there is a fault that prevents it from going to the up(1) state</p><p>- It should remain in the notPresent(6) state if the interface has missing(typically, hardware) components.</p> |SNMP |net.if.status[ifOperStatus.{#SNMPINDEX}] |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Bits received |<p>MIB: IF-MIB</p><p>The total number of octets received on the interface, including framing characters. This object is a 64-bit version of ifInOctets. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in[ifHCInOctets.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Bits sent |<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. This object is a 64-bit version of ifOutOctets.Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out[ifHCOutOctets.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors |<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in.errors[ifInErrors.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors |<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out.errors[ifOutErrors.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded |<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out.discards[ifOutDiscards.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded |<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in.discards[ifInDiscards.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Interface type |<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for ifType are assigned by the Internet Assigned Numbers Authority (IANA),</p><p>through updating the syntax of the IANAifType textual convention.</p> |SNMP |net.if.type[ifType.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Speed |<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in units of 1,000,000 bits per second. If this object reports a value of `n' then the speed of the interface is somewhere in the range of `n-500,000' to`n+499,999'.  For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth. For a sub-layer which has no concept of bandwidth, this object should be zero.</p> |SNMP |net.if.speed[ifHighSpeed.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1000000`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Power supply |PSU {#PSU_INDEX}: Power supply status |<p>MIB: FOUNDRY-SN-AGENT-MIB</p> |SNMP |sensor.psu.status[snChasPwrSupplyOperStatus.{#SNMPINDEX}] |
|Status |Uptime |<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p> |SNMP |system.uptime[sysUpTime.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Status |SNMP agent availability |<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible value:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p> |INTERNAL |zabbix[host,snmp,available] |
|Status |ICMP ping |<p>-</p> |SIMPLE |icmpping |
|Status |ICMP loss |<p>-</p> |SIMPLE |icmppingloss |
|Status |ICMP response time |<p>-</p> |SIMPLE |icmppingsec |
|Temperature |{#SENSOR_DESCR}: Temperature |<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>Temperature of the sensor represented by this row. Each unit is 0.5 degrees Celsius.</p> |SNMP |sensor.temp.value[snAgentTempValue.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.5`</p> |
|Temperature |Chassis #{#SNMPINDEX}: Temperature |<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>Temperature of the chassis. Each unit is 0.5 degrees Celsius.</p><p>Only management module built with temperature sensor hardware is applicable.</p><p>For those non-applicable management module, it returns no-such-name.</p> |SNMP |sensor.temp.value[snChasActualTemperature.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.5`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|High CPU utilization |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/Brocade_Foundry Nonstackable SNMP/system.cpu.util[snAgGblCpuUtil1MinAvg.0],5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|Fan {#FAN_INDEX}: Fan is in critical state |<p>Please check the fan unit</p> |`count(/Brocade_Foundry Nonstackable SNMP/sensor.fan.status[snChasFanOperStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS}")=1` |AVERAGE | |
|Fan {#FAN_INDEX}: Fan is not in normal state |<p>Please check the fan unit</p> |`count(/Brocade_Foundry Nonstackable SNMP/sensor.fan.status[snChasFanOperStatus.{#SNMPINDEX}],#1,"ne","{$FAN_OK_STATUS}")=1` |INFO |<p>**Depends on**:</p><p>- Fan {#FAN_INDEX}: Fan is in critical state</p> |
|System name has changed |<p>System name has changed. Ack to close.</p> |`last(/Brocade_Foundry Nonstackable SNMP/system.name,#1)<>last(/Brocade_Foundry Nonstackable SNMP/system.name,#2) and length(last(/Brocade_Foundry Nonstackable SNMP/system.name))>0` |INFO |<p>Manual close: YES</p> |
|Device has been replaced |<p>Device serial number has changed. Ack to close</p> |`last(/Brocade_Foundry Nonstackable SNMP/system.hw.serialnumber,#1)<>last(/Brocade_Foundry Nonstackable SNMP/system.hw.serialnumber,#2) and length(last(/Brocade_Foundry Nonstackable SNMP/system.hw.serialnumber))>0` |INFO |<p>Manual close: YES</p> |
|Firmware has changed |<p>Firmware version has changed. Ack to close</p> |`last(/Brocade_Foundry Nonstackable SNMP/system.hw.firmware,#1)<>last(/Brocade_Foundry Nonstackable SNMP/system.hw.firmware,#2) and length(last(/Brocade_Foundry Nonstackable SNMP/system.hw.firmware))>0` |INFO |<p>Manual close: YES</p> |
|High memory utilization |<p>The system is running out of free memory.</p> |`min(/Brocade_Foundry Nonstackable SNMP/vm.memory.util[snAgGblDynMemUtil.0],5m)>{$MEMORY.UTIL.MAX}` |AVERAGE | |
|Interface {#IFNAME}({#IFALIAS}): Link down |<p>This trigger expression works as follows:</p><p>1. Can be triggered if operations status is down.</p><p>2. {$IFCONTROL:"{#IFNAME}"}=1 - user can redefine Context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.</p><p>3. {TEMPLATE_NAME:METRIC.diff()}=1) - trigger fires only if operational status was up(1) sometime before. (So, do not fire 'ethernal off' interfaces.)</p><p>WARNING: if closed manually - won't fire again on next poll, because of .diff.</p> |`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Brocade_Foundry Nonstackable SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/Brocade_Foundry Nonstackable SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/Brocade_Foundry Nonstackable SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`<p>Recovery expression:</p>`last(/Brocade_Foundry Nonstackable SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2 or {$IFCONTROL:"{#IFNAME}"}=0` |AVERAGE |<p>Manual close: YES</p> |
|Interface {#IFNAME}({#IFALIAS}): High bandwidth usage |<p>The network interface utilization is close to its estimated maximum bandwidth.</p> |`(avg(/Brocade_Foundry Nonstackable SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Brocade_Foundry Nonstackable SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) or avg(/Brocade_Foundry Nonstackable SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Brocade_Foundry Nonstackable SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])) and last(/Brocade_Foundry Nonstackable SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0`<p>Recovery expression:</p>`avg(/Brocade_Foundry Nonstackable SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/Brocade_Foundry Nonstackable SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) and avg(/Brocade_Foundry Nonstackable SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/Brocade_Foundry Nonstackable SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): High error rate |<p>Recovers when below 80% of {$IF.ERRORS.WARN:"{#IFNAME}"} threshold</p> |`min(/Brocade_Foundry Nonstackable SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Brocade_Foundry Nonstackable SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`<p>Recovery expression:</p>`max(/Brocade_Foundry Nonstackable SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8 and max(/Brocade_Foundry Nonstackable SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before |<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Ack to close.</p> |`change(/Brocade_Foundry Nonstackable SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])<0 and last(/Brocade_Foundry Nonstackable SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and ( last(/Brocade_Foundry Nonstackable SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/Brocade_Foundry Nonstackable SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/Brocade_Foundry Nonstackable SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/Brocade_Foundry Nonstackable SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/Brocade_Foundry Nonstackable SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/Brocade_Foundry Nonstackable SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/Brocade_Foundry Nonstackable SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`<p>Recovery expression:</p>`(change(/Brocade_Foundry Nonstackable SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and last(/Brocade_Foundry Nonstackable SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}],#2)>0) or (last(/Brocade_Foundry Nonstackable SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2)` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|PSU {#PSU_INDEX}: Power supply is in critical state |<p>Please check the power supply unit for errors</p> |`count(/Brocade_Foundry Nonstackable SNMP/sensor.psu.status[snChasPwrSupplyOperStatus.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS}")=1` |AVERAGE | |
|PSU {#PSU_INDEX}: Power supply is not in normal state |<p>Please check the power supply unit for errors</p> |`count(/Brocade_Foundry Nonstackable SNMP/sensor.psu.status[snChasPwrSupplyOperStatus.{#SNMPINDEX}],#1,"ne","{$PSU_OK_STATUS}")=1` |INFO |<p>**Depends on**:</p><p>- PSU {#PSU_INDEX}: Power supply is in critical state</p> |
|has been restarted |<p>Uptime is less than 10 minutes.</p> |`last(/Brocade_Foundry Nonstackable SNMP/system.uptime[sysUpTime.0])<10m` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- No SNMP data collection</p> |
|No SNMP data collection |<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p> |`max(/Brocade_Foundry Nonstackable SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|Unavailable by ICMP ping |<p>Last three attempts returned timeout.  Please check device connectivity.</p> |`max(/Brocade_Foundry Nonstackable SNMP/icmpping,#3)=0` |HIGH | |
|High ICMP ping loss |<p>-</p> |`min(/Brocade_Foundry Nonstackable SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Brocade_Foundry Nonstackable SNMP/icmppingloss,5m)<100` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|High ICMP ping response time |<p>-</p> |`avg(/Brocade_Foundry Nonstackable SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}` |WARNING |<p>**Depends on**:</p><p>- High ICMP ping loss</p><p>- Unavailable by ICMP ping</p> |
|{#SENSOR_DESCR}: Temperature is above warning threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snAgentTempValue.{#SNMPINDEX}],5m)>{$TEMP_WARN:"{#SENSOR_DESCR}"}`<p>Recovery expression:</p>`max(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snAgentTempValue.{#SNMPINDEX}],5m)<{$TEMP_WARN:"{#SENSOR_DESCR}"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_DESCR}: Temperature is above critical threshold</p> |
|{#SENSOR_DESCR}: Temperature is above critical threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snAgentTempValue.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"{#SENSOR_DESCR}"}`<p>Recovery expression:</p>`max(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snAgentTempValue.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"{#SENSOR_DESCR}"}-3` |HIGH | |
|{#SENSOR_DESCR}: Temperature is too low |<p>-</p> |`avg(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snAgentTempValue.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"{#SENSOR_DESCR}"}`<p>Recovery expression:</p>`min(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snAgentTempValue.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"{#SENSOR_DESCR}"}+3` |AVERAGE | |
|Chassis #{#SNMPINDEX}: Temperature is above warning threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snChasActualTemperature.{#SNMPINDEX}],5m)>{$TEMP_WARN:"Chassis"}`<p>Recovery expression:</p>`max(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snChasActualTemperature.{#SNMPINDEX}],5m)<{$TEMP_WARN:"Chassis"}-3` |WARNING |<p>**Depends on**:</p><p>- Chassis #{#SNMPINDEX}: Temperature is above critical threshold</p> |
|Chassis #{#SNMPINDEX}: Temperature is above critical threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snChasActualTemperature.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"Chassis"}`<p>Recovery expression:</p>`max(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snChasActualTemperature.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"Chassis"}-3` |HIGH | |
|Chassis #{#SNMPINDEX}: Temperature is too low |<p>-</p> |`avg(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snChasActualTemperature.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"Chassis"}`<p>Recovery expression:</p>`min(/Brocade_Foundry Nonstackable SNMP/sensor.temp.value[snChasActualTemperature.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"Chassis"}+3` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

# Brocade_Foundry Stackable SNMP

## Overview

For Zabbix version: 6.0 and higher  
For devices(most of the IronWare Brocade devices) that support Stackable SNMP Tables in FOUNDRY-SN-AGENT-MIB: snChasFan2Table, snChasPwrSupply2Table,snAgentTemp2Table - so objects from all Stack members are provided.

This template was tested on:

- Brocade ICX7250-48, version ICX7250-48, IronWare Version 08.0.30kT211 Compiled on Oct 18 2016 at 05:40:38 labeled as SPS08030k
- Brocade ICX7250-48(Stacked), version Stacking System ICX7250-48, IronWare Version 08.0.30kT211 Compiled on Oct 18 2016 at 05:40:38 labeled as SPS08030k
- Brocade ICX7450-48(Stacked), version Stacking System ICX7450-48, IronWare Version 08.0.30kT211 Compiled on Oct 18 2016 at 05:40:38 labeled as SPS08030k"
- Brocade ICX7250-48(Stacked), version Stacking System ICX7250-48, IronWare Version 08.0.30kT211 Compiled on Oct 18 2016 at 05:40:38 labeled as SPS08030k
- Brocade ICX7450-48F(Stacked), version Stacking System ICX7750-48F, IronWare Version 08.0.40bT203 Compiled on Oct 20 2016 at 23:48:43 labeled as SWR08040b
- Brocade ICX 6600, version 

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>-</p> |`90` |
|{$FAN_CRIT_STATUS} |<p>-</p> |`3` |
|{$FAN_OK_STATUS} |<p>-</p> |`2` |
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
|{$PSU_CRIT_STATUS} |<p>-</p> |`3` |
|{$PSU_OK_STATUS} |<p>-</p> |`2` |
|{$SNMP.TIMEOUT} |<p>-</p> |`5m` |
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT} |<p>-</p> |`75` |
|{$TEMP_WARN} |<p>-</p> |`65` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Chassis Discovery |<p>snChasUnitIndex: The index to chassis table.</p> |SNMP |chassis.discovery |
|FAN Discovery |<p>snChasFan2Table: A table of each fan information for each unit. Only installed fan appears in a table row.</p> |SNMP |fan.discovery |
|Network interfaces discovery |<p>Discovering interfaces from IF-MIB.</p> |SNMP |net.if.discovery<p>**Filter**:</p>AND <p>- {#IFADMINSTATUS} MATCHES_REGEX `{$NET.IF.IFADMINSTATUS.MATCHES}`</p><p>- {#IFADMINSTATUS} NOT_MATCHES_REGEX `{$NET.IF.IFADMINSTATUS.NOT_MATCHES}`</p><p>- {#IFOPERSTATUS} MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.MATCHES}`</p><p>- {#IFOPERSTATUS} NOT_MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.NOT_MATCHES}`</p><p>- {#IFNAME} MATCHES_REGEX `{$NET.IF.IFNAME.MATCHES}`</p><p>- {#IFNAME} NOT_MATCHES_REGEX `{$NET.IF.IFNAME.NOT_MATCHES}`</p><p>- {#IFDESCR} MATCHES_REGEX `{$NET.IF.IFDESCR.MATCHES}`</p><p>- {#IFDESCR} NOT_MATCHES_REGEX `{$NET.IF.IFDESCR.NOT_MATCHES}`</p><p>- {#IFALIAS} MATCHES_REGEX `{$NET.IF.IFALIAS.MATCHES}`</p><p>- {#IFALIAS} NOT_MATCHES_REGEX `{$NET.IF.IFALIAS.NOT_MATCHES}`</p><p>- {#IFTYPE} MATCHES_REGEX `{$NET.IF.IFTYPE.MATCHES}`</p><p>- {#IFTYPE} NOT_MATCHES_REGEX `{$NET.IF.IFTYPE.NOT_MATCHES}`</p> |
|PSU Discovery |<p>snChasPwrSupply2Table: A table of each power supply information for each unit. Only installed power supply appears in a table row.</p> |SNMP |psu.discovery |
|Stack Discovery |<p>Discovering snStackingConfigUnitTable for Model names</p> |SNMP |stack.discovery |
|Temperature Discovery |<p>snAgentTemp2Table:Table to list temperatures of the modules in the device for each unit. This table is applicable to only those modules with temperature sensors.</p> |SNMP |temp.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |CPU utilization |<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The statistics collection of 1 minute CPU utilization.</p> |SNMP |system.cpu.util[snAgGblCpuUtil1MinAvg.0] |
|Fans |Unit {#FAN_UNIT} Fan {#FAN_INDEX}: Fan status |<p>MIB: FOUNDRY-SN-AGENT-MIB</p> |SNMP |sensor.fan.status[snChasFan2OperStatus.{#SNMPINDEX}] |
|General |SNMP traps (fallback) |<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p> |SNMP_TRAP |snmptrap.fallback |
|General |System location |<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet, 3rd floor').  If the location is unknown, the value is the zero-length string.</p> |SNMP |system.location[sysLocation.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System contact details |<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</p> |SNMP |system.contact[sysContact.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System object ID |<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity.  This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p> |SNMP |system.objectid[sysObjectID.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System name |<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name.  If the name is unknown, the value is the zero-length string.</p> |SNMP |system.name<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System description |<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p> |SNMP |system.descr[sysDescr.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|Inventory |Firmware version |<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The version of the running software in the form 'major.minor.maintenance[letters]'</p> |SNMP |system.hw.firmware<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Unit {#SNMPINDEX}: Hardware model name |<p>MIB: FOUNDRY-SN-STACKING-MIB</p><p>A description of the configured/active system type for each unit.</p> |SNMP |system.hw.model[snStackingConfigUnitType.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Unit {#SNMPVALUE}: Hardware serial number |<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The serial number of the chassis for each unit. If the serial number is unknown or unavailable then the value should be a zero length string.</p> |SNMP |system.hw.serialnumber[snChasUnitSerNum.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Memory |Memory utilization |<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The system dynamic memory utilization, in unit of percentage.</p><p>Deprecated: Refer to snAgSystemDRAMUtil.</p><p>For NI platforms, refer to snAgentBrdMemoryUtil100thPercent.</p> |SNMP |vm.memory.util[snAgGblDynMemUtil.0] |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Operational status |<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The testing(3) state indicates that no operational packet scan be passed</p><p>- If ifAdminStatus is down(2) then ifOperStatus should be down(2)</p><p>- If ifAdminStatus is changed to up(1) then ifOperStatus should change to up(1) if the interface is ready to transmit and receive network traffic</p><p>- It should change todormant(5) if the interface is waiting for external actions (such as a serial line waiting for an incoming connection)</p><p>- It should remain in the down(2) state if and only if there is a fault that prevents it from going to the up(1) state</p><p>- It should remain in the notPresent(6) state if the interface has missing(typically, hardware) components.</p> |SNMP |net.if.status[ifOperStatus.{#SNMPINDEX}] |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Bits received |<p>MIB: IF-MIB</p><p>The total number of octets received on the interface, including framing characters. This object is a 64-bit version of ifInOctets. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in[ifHCInOctets.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Bits sent |<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. This object is a 64-bit version of ifOutOctets.Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out[ifHCOutOctets.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors |<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in.errors[ifInErrors.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors |<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out.errors[ifOutErrors.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded |<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out.discards[ifOutDiscards.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded |<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in.discards[ifInDiscards.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Interface type |<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for ifType are assigned by the Internet Assigned Numbers Authority (IANA),</p><p>through updating the syntax of the IANAifType textual convention.</p> |SNMP |net.if.type[ifType.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Speed |<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in units of 1,000,000 bits per second. If this object reports a value of `n' then the speed of the interface is somewhere in the range of `n-500,000' to`n+499,999'.  For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth. For a sub-layer which has no concept of bandwidth, this object should be zero.</p> |SNMP |net.if.speed[ifHighSpeed.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1000000`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Power supply |Unit {#PSU_UNIT} PSU {#PSU_INDEX}: Power supply status |<p>MIB: FOUNDRY-SN-AGENT-MIB</p> |SNMP |sensor.psu.status[snChasPwrSupply2OperStatus.{#SNMPINDEX}] |
|Status |Uptime |<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p> |SNMP |system.uptime[sysUpTime.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Status |SNMP agent availability |<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible value:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p> |INTERNAL |zabbix[host,snmp,available] |
|Status |ICMP ping |<p>-</p> |SIMPLE |icmpping |
|Status |ICMP loss |<p>-</p> |SIMPLE |icmppingloss |
|Status |ICMP response time |<p>-</p> |SIMPLE |icmppingsec |
|Temperature |{#SENSOR_DESCR}: Temperature |<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>Temperature of the sensor represented by this row. Each unit is 0.5 degrees Celsius.</p> |SNMP |sensor.temp.value[snAgentTemp2Value.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.5`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|High CPU utilization |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/Brocade_Foundry Stackable SNMP/system.cpu.util[snAgGblCpuUtil1MinAvg.0],5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|Unit {#FAN_UNIT} Fan {#FAN_INDEX}: Fan is in critical state |<p>Please check the fan unit</p> |`count(/Brocade_Foundry Stackable SNMP/sensor.fan.status[snChasFan2OperStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS}")=1` |AVERAGE | |
|Unit {#FAN_UNIT} Fan {#FAN_INDEX}: Fan is not in normal state |<p>Please check the fan unit</p> |`count(/Brocade_Foundry Stackable SNMP/sensor.fan.status[snChasFan2OperStatus.{#SNMPINDEX}],#1,"ne","{$FAN_OK_STATUS}")=1` |INFO |<p>**Depends on**:</p><p>- Unit {#FAN_UNIT} Fan {#FAN_INDEX}: Fan is in critical state</p> |
|System name has changed |<p>System name has changed. Ack to close.</p> |`last(/Brocade_Foundry Stackable SNMP/system.name,#1)<>last(/Brocade_Foundry Stackable SNMP/system.name,#2) and length(last(/Brocade_Foundry Stackable SNMP/system.name))>0` |INFO |<p>Manual close: YES</p> |
|Firmware has changed |<p>Firmware version has changed. Ack to close</p> |`last(/Brocade_Foundry Stackable SNMP/system.hw.firmware,#1)<>last(/Brocade_Foundry Stackable SNMP/system.hw.firmware,#2) and length(last(/Brocade_Foundry Stackable SNMP/system.hw.firmware))>0` |INFO |<p>Manual close: YES</p> |
|Unit {#SNMPVALUE}: Device has been replaced |<p>Device serial number has changed. Ack to close</p> |`last(/Brocade_Foundry Stackable SNMP/system.hw.serialnumber[snChasUnitSerNum.{#SNMPINDEX}],#1)<>last(/Brocade_Foundry Stackable SNMP/system.hw.serialnumber[snChasUnitSerNum.{#SNMPINDEX}],#2) and length(last(/Brocade_Foundry Stackable SNMP/system.hw.serialnumber[snChasUnitSerNum.{#SNMPINDEX}]))>0` |INFO |<p>Manual close: YES</p> |
|High memory utilization |<p>The system is running out of free memory.</p> |`min(/Brocade_Foundry Stackable SNMP/vm.memory.util[snAgGblDynMemUtil.0],5m)>{$MEMORY.UTIL.MAX}` |AVERAGE | |
|Interface {#IFNAME}({#IFALIAS}): Link down |<p>This trigger expression works as follows:</p><p>1. Can be triggered if operations status is down.</p><p>2. {$IFCONTROL:"{#IFNAME}"}=1 - user can redefine Context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.</p><p>3. {TEMPLATE_NAME:METRIC.diff()}=1) - trigger fires only if operational status was up(1) sometime before. (So, do not fire 'ethernal off' interfaces.)</p><p>WARNING: if closed manually - won't fire again on next poll, because of .diff.</p> |`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Brocade_Foundry Stackable SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/Brocade_Foundry Stackable SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/Brocade_Foundry Stackable SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`<p>Recovery expression:</p>`last(/Brocade_Foundry Stackable SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2 or {$IFCONTROL:"{#IFNAME}"}=0` |AVERAGE |<p>Manual close: YES</p> |
|Interface {#IFNAME}({#IFALIAS}): High bandwidth usage |<p>The network interface utilization is close to its estimated maximum bandwidth.</p> |`(avg(/Brocade_Foundry Stackable SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Brocade_Foundry Stackable SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) or avg(/Brocade_Foundry Stackable SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Brocade_Foundry Stackable SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])) and last(/Brocade_Foundry Stackable SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0`<p>Recovery expression:</p>`avg(/Brocade_Foundry Stackable SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/Brocade_Foundry Stackable SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) and avg(/Brocade_Foundry Stackable SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/Brocade_Foundry Stackable SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): High error rate |<p>Recovers when below 80% of {$IF.ERRORS.WARN:"{#IFNAME}"} threshold</p> |`min(/Brocade_Foundry Stackable SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Brocade_Foundry Stackable SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`<p>Recovery expression:</p>`max(/Brocade_Foundry Stackable SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8 and max(/Brocade_Foundry Stackable SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before |<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Ack to close.</p> |`change(/Brocade_Foundry Stackable SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])<0 and last(/Brocade_Foundry Stackable SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and ( last(/Brocade_Foundry Stackable SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/Brocade_Foundry Stackable SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/Brocade_Foundry Stackable SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/Brocade_Foundry Stackable SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/Brocade_Foundry Stackable SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/Brocade_Foundry Stackable SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/Brocade_Foundry Stackable SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`<p>Recovery expression:</p>`(change(/Brocade_Foundry Stackable SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and last(/Brocade_Foundry Stackable SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}],#2)>0) or (last(/Brocade_Foundry Stackable SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2)` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Unit {#PSU_UNIT} PSU {#PSU_INDEX}: Power supply is in critical state |<p>Please check the power supply unit for errors</p> |`count(/Brocade_Foundry Stackable SNMP/sensor.psu.status[snChasPwrSupply2OperStatus.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS}")=1` |AVERAGE | |
|Unit {#PSU_UNIT} PSU {#PSU_INDEX}: Power supply is not in normal state |<p>Please check the power supply unit for errors</p> |`count(/Brocade_Foundry Stackable SNMP/sensor.psu.status[snChasPwrSupply2OperStatus.{#SNMPINDEX}],#1,"ne","{$PSU_OK_STATUS}")=1` |INFO |<p>**Depends on**:</p><p>- Unit {#PSU_UNIT} PSU {#PSU_INDEX}: Power supply is in critical state</p> |
|has been restarted |<p>Uptime is less than 10 minutes.</p> |`last(/Brocade_Foundry Stackable SNMP/system.uptime[sysUpTime.0])<10m` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- No SNMP data collection</p> |
|No SNMP data collection |<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p> |`max(/Brocade_Foundry Stackable SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|Unavailable by ICMP ping |<p>Last three attempts returned timeout.  Please check device connectivity.</p> |`max(/Brocade_Foundry Stackable SNMP/icmpping,#3)=0` |HIGH | |
|High ICMP ping loss |<p>-</p> |`min(/Brocade_Foundry Stackable SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Brocade_Foundry Stackable SNMP/icmppingloss,5m)<100` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|High ICMP ping response time |<p>-</p> |`avg(/Brocade_Foundry Stackable SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}` |WARNING |<p>**Depends on**:</p><p>- High ICMP ping loss</p><p>- Unavailable by ICMP ping</p> |
|{#SENSOR_DESCR}: Temperature is above warning threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Brocade_Foundry Stackable SNMP/sensor.temp.value[snAgentTemp2Value.{#SNMPINDEX}],5m)>{$TEMP_WARN:"{#SENSOR_DESCR}"}`<p>Recovery expression:</p>`max(/Brocade_Foundry Stackable SNMP/sensor.temp.value[snAgentTemp2Value.{#SNMPINDEX}],5m)<{$TEMP_WARN:"{#SENSOR_DESCR}"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_DESCR}: Temperature is above critical threshold</p> |
|{#SENSOR_DESCR}: Temperature is above critical threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Brocade_Foundry Stackable SNMP/sensor.temp.value[snAgentTemp2Value.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"{#SENSOR_DESCR}"}`<p>Recovery expression:</p>`max(/Brocade_Foundry Stackable SNMP/sensor.temp.value[snAgentTemp2Value.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"{#SENSOR_DESCR}"}-3` |HIGH | |
|{#SENSOR_DESCR}: Temperature is too low |<p>-</p> |`avg(/Brocade_Foundry Stackable SNMP/sensor.temp.value[snAgentTemp2Value.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"{#SENSOR_DESCR}"}`<p>Recovery expression:</p>`min(/Brocade_Foundry Stackable SNMP/sensor.temp.value[snAgentTemp2Value.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"{#SENSOR_DESCR}"}+3` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

## Known Issues

- Description: Correct fan(returns fan status as 'other(1)' and temperature (returns 0) for the non-master Switches are not available in SNMP
  - Version: Version 08.0.40b and above
  - Device: ICX 7750 in stack

