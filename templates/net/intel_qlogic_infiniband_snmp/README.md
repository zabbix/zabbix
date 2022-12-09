
# Intel_Qlogic Infiniband by SNMP

## Overview

For Zabbix version: 6.0 and higher.  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$FAN_CRIT_STATUS} |<p>-</p> |`3` |
|{$ICMP_LOSS_WARN} |<p>-</p> |`20` |
|{$ICMP_RESPONSE_TIME_WARN} |<p>-</p> |`0.15` |
|{$IF.ERRORS.WARN} |<p>-</p> |`2` |
|{$IF.UTIL.MAX} |<p>-</p> |`90` |
|{$IFCONTROL} |<p>-</p> |`1` |
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
|{$PSU_WARN_STATUS} |<p>-</p> |`4` |
|{$SNMP.TIMEOUT} |<p>-</p> |`5m` |
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT_STATUS} |<p>-</p> |`3` |
|{$TEMP_CRIT} |<p>-</p> |`60` |
|{$TEMP_WARN_STATUS} |<p>-</p> |`2` |
|{$TEMP_WARN} |<p>-</p> |`50` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|FAN Discovery |<p>icsChassisFanDescription of icsChassisFanTable</p> |SNMP |fan.discovery |
|Network interfaces discovery |<p>Discovering interfaces from IF-MIB.</p> |SNMP |net.if.discovery<p>**Filter**:</p>AND <p>- {#IFADMINSTATUS} MATCHES_REGEX `{$NET.IF.IFADMINSTATUS.MATCHES}`</p><p>- {#IFADMINSTATUS} NOT_MATCHES_REGEX `{$NET.IF.IFADMINSTATUS.NOT_MATCHES}`</p><p>- {#IFOPERSTATUS} MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.MATCHES}`</p><p>- {#IFOPERSTATUS} NOT_MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.NOT_MATCHES}`</p><p>- {#IFNAME} MATCHES_REGEX `{$NET.IF.IFNAME.MATCHES}`</p><p>- {#IFNAME} NOT_MATCHES_REGEX `{$NET.IF.IFNAME.NOT_MATCHES}`</p><p>- {#IFDESCR} MATCHES_REGEX `{$NET.IF.IFDESCR.MATCHES}`</p><p>- {#IFDESCR} NOT_MATCHES_REGEX `{$NET.IF.IFDESCR.NOT_MATCHES}`</p><p>- {#IFALIAS} MATCHES_REGEX `{$NET.IF.IFALIAS.MATCHES}`</p><p>- {#IFALIAS} NOT_MATCHES_REGEX `{$NET.IF.IFALIAS.NOT_MATCHES}`</p><p>- {#IFTYPE} MATCHES_REGEX `{$NET.IF.IFTYPE.MATCHES}`</p><p>- {#IFTYPE} NOT_MATCHES_REGEX `{$NET.IF.IFTYPE.NOT_MATCHES}`</p> |
|PSU Discovery |<p>A textual description of the power supply, that can be assigned by the administrator.</p> |SNMP |psu.discovery |
|Temperature Discovery |<p>Discovering sensor's table with temperature filter</p> |SNMP |temp.discovery<p>**Filter**:</p>AND <p>- {#SENSOR_TYPE} MATCHES_REGEX `2`</p> |
|Unit Discovery |<p>-</p> |SNMP |unit.discovery<p>**Filter**:</p>AND_OR <p>- {#ENT_CLASS} MATCHES_REGEX `2`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Fans |{#SNMPVALUE}: Fan status |<p>MIB: ICS-CHASSIS-MIB</p><p>The operational status of the fan unit.</p> |SNMP |sensor.fan.status[icsChassisFanOperStatus.{#SNMPINDEX}] |
|General |SNMP traps (fallback) |<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p> |SNMP_TRAP |snmptrap.fallback |
|General |System location |<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet, 3rd floor').  If the location is unknown, the value is the zero-length string.</p> |SNMP |system.location[sysLocation.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System contact details |<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</p> |SNMP |system.contact[sysContact.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System object ID |<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity.  This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p> |SNMP |system.objectid[sysObjectID.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System name |<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name.  If the name is unknown, the value is the zero-length string.</p> |SNMP |system.name<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System description |<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p> |SNMP |system.descr[sysDescr.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|Inventory |Hardware model name |<p>MIB: ICS-CHASSIS-MIB</p> |SNMP |system.hw.model<p>**Preprocessing**:</p><p>- REGEX: `(.+) - Firmware \1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Firmware version |<p>MIB: ICS-CHASSIS-MIB</p> |SNMP |system.hw.firmware<p>**Preprocessing**:</p><p>- REGEX: `Firmware Version: ([0-9.]+), \1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |{#ENT_NAME}: Hardware serial number |<p>MIB: ICS-CHASSIS-MIB</p><p>The serial number of the FRU.  If not available, this value is a zero-length string.</p> |SNMP |system.hw.serialnumber[icsChassisSystemUnitFruSerialNumber.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Operational status |<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The testing(3) state indicates that no operational packet scan be passed</p><p>- If ifAdminStatus is down(2) then ifOperStatus should be down(2)</p><p>- If ifAdminStatus is changed to up(1) then ifOperStatus should change to up(1) if the interface is ready to transmit and receive network traffic</p><p>- It should change todormant(5) if the interface is waiting for external actions (such as a serial line waiting for an incoming connection)</p><p>- It should remain in the down(2) state if and only if there is a fault that prevents it from going to the up(1) state</p><p>- It should remain in the notPresent(6) state if the interface has missing(typically, hardware) components.</p> |SNMP |net.if.status[ifOperStatus.{#SNMPINDEX}] |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Bits received |<p>MIB: IF-MIB</p><p>The total number of octets received on the interface, including framing characters. This object is a 64-bit version of ifInOctets. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in[ifHCInOctets.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Bits sent |<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. This object is a 64-bit version of ifOutOctets.Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out[ifHCOutOctets.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors |<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in.errors[ifInErrors.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors |<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out.errors[ifOutErrors.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded |<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out.discards[ifOutDiscards.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded |<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in.discards[ifInDiscards.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Interface type |<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for ifType are assigned by the Internet Assigned Numbers Authority (IANA),</p><p>through updating the syntax of the IANAifType textual convention.</p> |SNMP |net.if.type[ifType.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Network interfaces |Interface {#IFNAME}({#IFALIAS}): Speed |<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in units of 1,000,000 bits per second. If this object reports a value of `n' then the speed of the interface is somewhere in the range of `n-500,000' to`n+499,999'.  For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth. For a sub-layer which has no concept of bandwidth, this object should be zero.</p> |SNMP |net.if.speed[ifHighSpeed.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1000000`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Power supply |{#SNMPVALUE}: Power supply status |<p>MIB: ICS-CHASSIS-MIB</p><p>Actual status of the power supply:</p><p>(1) unknown: status not known.</p><p>(2) disabled: power supply is disabled.</p><p>(3) failed - power supply is unable to supply power due to failure.</p><p>(4) warning - power supply is supplying power, but an output or sensor is bad or warning.</p><p>(5) standby - power supply believed usable,but not supplying power.</p><p>(6) engaged - power supply is supplying power.</p><p>(7) redundant - power supply is supplying power, but not needed.</p><p>(8) notPresent - power supply is supplying power is not present.</p> |SNMP |sensor.psu.status[icsChassisPowerSupplyEntry.{#SNMPINDEX}] |
|Status |Uptime (network) |<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p> |SNMP |system.net.uptime[sysUpTime.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Status |Uptime (hardware) |<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p> |SNMP |system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- MULTIPLIER: `0.01`</p> |
|Status |SNMP agent availability |<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible value:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p> |INTERNAL |zabbix[host,snmp,available] |
|Status |ICMP ping |<p>-</p> |SIMPLE |icmpping |
|Status |ICMP loss |<p>-</p> |SIMPLE |icmppingloss |
|Status |ICMP response time |<p>-</p> |SIMPLE |icmppingsec |
|Temperature |{#SENSOR_INFO}: Temperature |<p>MIB: ICS-CHASSIS-MIB</p><p>The current value read from the sensor.</p> |SNMP |sensor.temp.value[icsChassisSensorSlotValue.{#SNMPINDEX}] |
|Temperature |{#SENSOR_INFO}: Temperature status |<p>MIB: ICS-CHASSIS-MIB</p><p>The operational status of the sensor.</p> |SNMP |sensor.temp.status[icsChassisSensorSlotOperStatus.{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#SNMPVALUE}: Fan is in critical state |<p>Please check the fan unit</p> |`count(/Intel_Qlogic Infiniband by SNMP/sensor.fan.status[icsChassisFanOperStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS}")=1` |AVERAGE | |
|System name has changed |<p>System name has changed. Ack to close.</p> |`last(/Intel_Qlogic Infiniband by SNMP/system.name,#1)<>last(/Intel_Qlogic Infiniband by SNMP/system.name,#2) and length(last(/Intel_Qlogic Infiniband by SNMP/system.name))>0` |INFO |<p>Manual close: YES</p> |
|Firmware has changed |<p>Firmware version has changed. Ack to close</p> |`last(/Intel_Qlogic Infiniband by SNMP/system.hw.firmware,#1)<>last(/Intel_Qlogic Infiniband by SNMP/system.hw.firmware,#2) and length(last(/Intel_Qlogic Infiniband by SNMP/system.hw.firmware))>0` |INFO |<p>Manual close: YES</p> |
|{#ENT_NAME}: Device has been replaced |<p>Device serial number has changed. Ack to close</p> |`last(/Intel_Qlogic Infiniband by SNMP/system.hw.serialnumber[icsChassisSystemUnitFruSerialNumber.{#SNMPINDEX}],#1)<>last(/Intel_Qlogic Infiniband by SNMP/system.hw.serialnumber[icsChassisSystemUnitFruSerialNumber.{#SNMPINDEX}],#2) and length(last(/Intel_Qlogic Infiniband by SNMP/system.hw.serialnumber[icsChassisSystemUnitFruSerialNumber.{#SNMPINDEX}]))>0` |INFO |<p>Manual close: YES</p> |
|Interface {#IFNAME}({#IFALIAS}): Link down |<p>This trigger expression works as follows:</p><p>1. Can be triggered if operations status is down.</p><p>2. {$IFCONTROL:"{#IFNAME}"}=1 - user can redefine Context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.</p><p>3. {TEMPLATE_NAME:METRIC.diff()}=1) - trigger fires only if operational status was up(1) sometime before. (So, do not fire 'ethernal off' interfaces.)</p><p>WARNING: if closed manually - won't fire again on next poll, because of .diff.</p> |`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Intel_Qlogic Infiniband by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/Intel_Qlogic Infiniband by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/Intel_Qlogic Infiniband by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`<p>Recovery expression:</p>`last(/Intel_Qlogic Infiniband by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2 or {$IFCONTROL:"{#IFNAME}"}=0` |AVERAGE |<p>Manual close: YES</p> |
|Interface {#IFNAME}({#IFALIAS}): High bandwidth usage |<p>The network interface utilization is close to its estimated maximum bandwidth.</p> |`(avg(/Intel_Qlogic Infiniband by SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Intel_Qlogic Infiniband by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) or avg(/Intel_Qlogic Infiniband by SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Intel_Qlogic Infiniband by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])) and last(/Intel_Qlogic Infiniband by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0`<p>Recovery expression:</p>`avg(/Intel_Qlogic Infiniband by SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/Intel_Qlogic Infiniband by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) and avg(/Intel_Qlogic Infiniband by SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*last(/Intel_Qlogic Infiniband by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): High error rate |<p>Recovers when below 80% of {$IF.ERRORS.WARN:"{#IFNAME}"} threshold</p> |`min(/Intel_Qlogic Infiniband by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Intel_Qlogic Infiniband by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`<p>Recovery expression:</p>`max(/Intel_Qlogic Infiniband by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8 and max(/Intel_Qlogic Infiniband by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before |<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Ack to close.</p> |`change(/Intel_Qlogic Infiniband by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])<0 and last(/Intel_Qlogic Infiniband by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and ( last(/Intel_Qlogic Infiniband by SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/Intel_Qlogic Infiniband by SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/Intel_Qlogic Infiniband by SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/Intel_Qlogic Infiniband by SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/Intel_Qlogic Infiniband by SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/Intel_Qlogic Infiniband by SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/Intel_Qlogic Infiniband by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`<p>Recovery expression:</p>`(change(/Intel_Qlogic Infiniband by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and last(/Intel_Qlogic Infiniband by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}],#2)>0) or (last(/Intel_Qlogic Infiniband by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2)` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|{#SNMPVALUE}: Power supply is in critical state |<p>Please check the power supply unit for errors</p> |`count(/Intel_Qlogic Infiniband by SNMP/sensor.psu.status[icsChassisPowerSupplyEntry.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS}")=1` |AVERAGE | |
|{#SNMPVALUE}: Power supply is in warning state |<p>Please check the power supply unit for errors</p> |`count(/Intel_Qlogic Infiniband by SNMP/sensor.psu.status[icsChassisPowerSupplyEntry.{#SNMPINDEX}],#1,"eq","{$PSU_WARN_STATUS}")=1` |WARNING |<p>**Depends on**:</p><p>- {#SNMPVALUE}: Power supply is in critical state</p> |
|Host has been restarted |<p>Uptime is less than 10 minutes.</p> |`(last(/Intel_Qlogic Infiniband by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Intel_Qlogic Infiniband by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Intel_Qlogic Infiniband by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Intel_Qlogic Infiniband by SNMP/system.net.uptime[sysUpTime.0])<10m)` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- No SNMP data collection</p> |
|No SNMP data collection |<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p> |`max(/Intel_Qlogic Infiniband by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|Unavailable by ICMP ping |<p>Last three attempts returned timeout.  Please check device connectivity.</p> |`max(/Intel_Qlogic Infiniband by SNMP/icmpping,#3)=0` |HIGH | |
|High ICMP ping loss |<p>-</p> |`min(/Intel_Qlogic Infiniband by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Intel_Qlogic Infiniband by SNMP/icmppingloss,5m)<100` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|High ICMP ping response time |<p>-</p> |`avg(/Intel_Qlogic Infiniband by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}` |WARNING |<p>**Depends on**:</p><p>- High ICMP ping loss</p><p>- Unavailable by ICMP ping</p> |
|{#SENSOR_INFO}: Temperature is above warning threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Intel_Qlogic Infiniband by SNMP/sensor.temp.value[icsChassisSensorSlotValue.{#SNMPINDEX}],5m)>{$TEMP_WARN:"{#SENSOR_INFO}"} or last(/Intel_Qlogic Infiniband by SNMP/sensor.temp.status[icsChassisSensorSlotOperStatus.{#SNMPINDEX}])={$TEMP_WARN_STATUS} `<p>Recovery expression:</p>`max(/Intel_Qlogic Infiniband by SNMP/sensor.temp.value[icsChassisSensorSlotValue.{#SNMPINDEX}],5m)<{$TEMP_WARN:"{#SENSOR_INFO}"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_INFO}: Temperature is above critical threshold</p> |
|{#SENSOR_INFO}: Temperature is above critical threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Intel_Qlogic Infiniband by SNMP/sensor.temp.value[icsChassisSensorSlotValue.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"{#SENSOR_INFO}"} or last(/Intel_Qlogic Infiniband by SNMP/sensor.temp.status[icsChassisSensorSlotOperStatus.{#SNMPINDEX}])={$TEMP_CRIT_STATUS} `<p>Recovery expression:</p>`max(/Intel_Qlogic Infiniband by SNMP/sensor.temp.value[icsChassisSensorSlotValue.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"{#SENSOR_INFO}"}-3` |HIGH | |
|{#SENSOR_INFO}: Temperature is too low |<p>-</p> |`avg(/Intel_Qlogic Infiniband by SNMP/sensor.temp.value[icsChassisSensorSlotValue.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"{#SENSOR_INFO}"}`<p>Recovery expression:</p>`min(/Intel_Qlogic Infiniband by SNMP/sensor.temp.value[icsChassisSensorSlotValue.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"{#SENSOR_INFO}"}+3` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

