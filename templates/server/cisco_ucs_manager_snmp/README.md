
# Cisco UCS Manager by SNMP

## Overview

Cisco UCS® Manager provides unified, embedded management of all software and hardware components of the Cisco Unified Computing System™ (Cisco UCS) across multiple chassis and rack servers. It enables server, fabric, and storage provisioning as well as,
device discovery, inventory, configuration, diagnostics, monitoring, fault detection, auditing, and statistics collection.
This is a template for Cisco UCS Manager monitoring via Zabbix SNMP Agent that works without any external scripts.
You can download UCS MIB files there ftp://ftp.cisco.com/pub/mibs/ucs-mibs/.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Cisco UCS Manager

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Create a host for Cisco USC Manager IP as SNMPv2 interface.
2. Link the template to the host.
3. Customize macro values if needed.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PSU.STATUS.CRIT:"inoperable"}|<p>The critical value of the PSU sensor for trigger expression.</p>|`2`|
|{$PSU.STATUS.WARN:"degraded"}|<p>The warning value of the PSU sensor for trigger expression.</p>|`3`|
|{$FAN.STATUS.CRIT:"inoperable"}|<p>The critical value of the FAN sensor for trigger expression.</p>|`2`|
|{$FAN.STATUS.WARN:"degraded"}|<p>The warning value of the FAN sensor for trigger expression.</p>|`3`|
|{$TEMP.MAX.CRIT:"Ambient"}|<p>The temperature maximum critical value for trigger expression.</p>|`35`|
|{$TEMP.MAX.WARN:"Ambient"}|<p>The temperature maximum warning value for trigger expression.</p>|`30`|
|{$DISK.ARRAY.CACHE.BATTERY.STATUS.OK}|<p>The cache battery normal state for trigger expression.</p>|`1`|
|{$DISK.ARRAY.CACHE.BATTERY.STATUS.CRIT}|<p>The cache battery critical state for trigger expression.</p>|`2`|
|{$DISK.ARRAY.STATUS.CRIT:"inoperable"}|<p>The array controller critical state for trigger expression.</p>|`2`|
|{$DISK.ARRAY.STATUS.WARN:"degraded"}|<p>The array controller warning state for trigger expression.</p>|`3`|
|{$DISK.ARRAY.STATUS.OK:"operable"}|<p>The array controller normal state for trigger expression.</p>|`1`|
|{$DISK.STATUS.FAIL:"failed"}|<p>The disk fail state for trigger expression.</p>|`9`|
|{$DISK.STATUS.CRIT:"predictiveFailure"}|<p>The disk critical state for trigger expression.</p>|`11`|
|{$DISK.STATUS.CRIT:"bad"}|<p>The disk critical state for trigger expression.</p>|`16`|
|{$VDISK.STATUS.OK:"equipped"}|<p>The vdisk normal state for trigger expression.</p>|`10`|
|{$HEALTH.STATUS.CRIT:"computeFailed"}|<p>The unit health critical state for trigger expression.</p>|`30`|
|{$HEALTH.STATUS.CRIT:"configFailure"}|<p>The unit health critical state for trigger expression.</p>|`33`|
|{$HEALTH.STATUS.CRIT:"unconfigFailure"}|<p>The unit health critical state for trigger expression.</p>|`34`|
|{$HEALTH.STATUS.CRIT:"inoperable"}|<p>The unit health critical state for trigger expression.</p>|`60`|
|{$HEALTH.STATUS.WARN:"testFailed"}|<p>The unit health warning state for trigger expression.</p>|`35`|
|{$HEALTH.STATUS.WARN:"thermalProblem"}|<p>The unit health warning state for trigger expression.</p>|`60`|
|{$HEALTH.STATUS.WARN:"powerProblem"}|<p>The unit health warning state for trigger expression.</p>|`62`|
|{$HEALTH.STATUS.WARN:"voltageProblem"}|<p>The unit health warning state for trigger expression.</p>|`62`|
|{$IF.ERRORS.WARN}||`2`|
|{$HEALTH.STATUS.WARN:"diagnosticsFailed"}|<p>The unit health warning state for trigger expression.</p>|`204`|
|{$NET.IFNAME.MATCHES}|<p>This macro is used in network interface discovery. Can be overridden on the host level.</p>|`^.*$`|
|{$NET.IFNAME.NOT_MATCHES}|<p>This macro is used in network interface discovery. Can be overridden on the host level. Filter out loopbacks, sup-fc0, nulls, docker veth links and docker0 bridge by default.</p>|`Macro too long. Please see the template.`|
|{$NET.IFOPERSTATUS.MATCHES}|<p>This macro is used in network interface discovery. Can be overridden on the host level.</p>|`^.*$`|
|{$NET.IFOPERSTATUS.NOT_MATCHES}|<p>This macro is used in network interface discovery. Can be overridden on the host level. Ignore notPresent(6) by default.</p>|`^6$`|
|{$NET.IFADMINSTATUS.MATCHES}|<p>This macro is used in network interface discovery. Can be overridden on the host level. Ignore notPresent(6) by default.</p>|`^.*`|
|{$NET.IFADMINSTATUS.NOT_MATCHES}|<p>This macro is used in network interface discovery. Can be overridden on the host level. Ignore down(2) administrative status by default.</p>|`^2$`|
|{$NET.IFDESCR.MATCHES}|<p>This macro is used in network interface discovery. Can be overridden on the host level.</p>|`.*`|
|{$NET.IFDESCR.NOT_MATCHES}|<p>This macro is used in network interface discovery. Can be overridden on the host level.</p>|`CHANGE_IF_NEEDED`|
|{$NET.IFALIAS.MATCHES}|<p>This macro is used in network interface discovery. Can be overridden on the host level.</p>|`.*`|
|{$NET.IFALIAS.NOT_MATCHES}|<p>This macro is used in network interface discovery. Can be overridden on the host level.</p>|`CHANGE_IF_NEEDED`|
|{$NET.IFTYPE.MATCHES}|<p>This macro is used in network interface discovery. Can be overridden on the host level.</p>|`.*`|
|{$NET.IFTYPE.NOT_MATCHES}|<p>This macro is used in network interface discovery. Can be overridden on the host level.</p>|`CHANGE_IF_NEEDED`|
|{$SNMP.TIMEOUT}|<p>The time interval for SNMP agent availability trigger expression.</p>|`5m`|
|{$IFCONTROL}||`1`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cisco UCS Manager: Uptime (network)|<p>MIB: SNMPv2-MIB</p><p>The time in seconds since the network management</p><p>portion of the system was last re-initialized.</p>|SNMP agent|cisco.ucs.net.uptime[sysUpTime.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Cisco UCS Manager: Uptime (hardware)|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized.</p><p>Note that this is different from sysUpTime in the SNMPv2-MIB</p><p>[RFC1907] because sysUpTime is the uptime of the</p><p>network management portion of the system.</p>|SNMP agent|cisco.ucs.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Cisco UCS Manager: SNMP traps (fallback)|<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p>|SNMP trap|snmptrap.fallback|
|Cisco UCS Manager: System location|<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet,</p><p>3rd floor').  If the location is unknown, the value is the zero-length string.</p>|SNMP agent|cisco.ucs.location[sysLocation.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Cisco UCS Manager: System contact details|<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed</p><p>node, together with information on how to contact this person.  If no contact</p><p>information is known, the value is the zero-length string.</p>|SNMP agent|cisco.ucs.contact[sysContact.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Cisco UCS Manager: System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management</p><p>subsystem contained in the entity.  This value is allocated within the SMI enterprises</p><p>subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining 'what</p><p>kind of box' is being managed. For example, if vendor 'Flintstones, Inc.' was</p><p>assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1</p><p>to its 'Fred Router'.</p>|SNMP agent|cisco.ucs.objectid[sysObjectID.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Cisco UCS Manager: System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By</p><p>convention, this is the node's fully-qualified domain name.  If the name is unknown,</p><p>the value is the zero-length string.</p>|SNMP agent|cisco.ucs.name[sysName.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Cisco UCS Manager: System description|<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p>|SNMP agent|cisco.ucs.descr[sysDescr.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Cisco UCS Manager: SNMP agent availability|<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible value:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p>|Zabbix internal|zabbix[host,snmp,available]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco UCS Manager: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Cisco UCS Manager by SNMP/cisco.ucs.hw.uptime[hrSystemUptime.0])>0 and last(/Cisco UCS Manager by SNMP/cisco.ucs.hw.uptime[hrSystemUptime.0])<10m) or (last(/Cisco UCS Manager by SNMP/cisco.ucs.hw.uptime[hrSystemUptime.0])=0 and last(/Cisco UCS Manager by SNMP/cisco.ucs.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Cisco UCS Manager: No SNMP data collection</li></ul>|
|Cisco UCS Manager: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/Cisco UCS Manager by SNMP/cisco.ucs.name[sysName.0],#1)<>last(/Cisco UCS Manager by SNMP/cisco.ucs.name[sysName.0],#2) and length(last(/Cisco UCS Manager by SNMP/cisco.ucs.name[sysName.0]))>0`|Info|**Manual close**: Yes|
|Cisco UCS Manager: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/Cisco UCS Manager by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning||

### LLD rule Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature discovery||SNMP agent|cisco.ucs.temp.discovery|

### Item prototypes for Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SENSOR_LOCATION}.Ambient: Temperature|<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Temperature readings of testpoint: {#SENSOR_LOCATION}.Ambient</p>|SNMP agent|cisco.ucs.sensor.temp.value[cucsComputeRackUnitMbTempStatsAmbientTemp.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#SENSOR_LOCATION}.Front: Temperature|<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnitMbTempStats:frontTemp managed object property</p>|SNMP agent|cisco.ucs.sensor.temp.value[cucsComputeRackUnitMbTempStatsFrontTemp.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#SENSOR_LOCATION}.Rear: Temperature|<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnitMbTempStats:rearTemp managed object property</p>|SNMP agent|cisco.ucs.sensor.temp.value[cucsComputeRackUnitMbTempStatsRearTemp.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#SENSOR_LOCATION}.IOH: Temperature|<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnitMbTempStats:ioh1Temp managed object property</p>|SNMP agent|cisco.ucs.sensor.temp.value[cucsComputeRackUnitMbTempSltatsIoh1Temp.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Temperature discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SENSOR_LOCATION}.Ambient: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco UCS Manager by SNMP/cisco.ucs.sensor.temp.value[cucsComputeRackUnitMbTempStatsAmbientTemp.{#SNMPINDEX}],5m)>{$TEMP.MAX.WARN:"Ambient"}`|Warning|**Depends on**:<br><ul><li>{#SENSOR_LOCATION}.Ambient: Temperature is above critical threshold</li></ul>|
|{#SENSOR_LOCATION}.Ambient: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco UCS Manager by SNMP/cisco.ucs.sensor.temp.value[cucsComputeRackUnitMbTempStatsAmbientTemp.{#SNMPINDEX}],5m)>{$TEMP.MAX.CRIT:"Ambient"}`|High||
|{#SENSOR_LOCATION}.Front: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco UCS Manager by SNMP/cisco.ucs.sensor.temp.value[cucsComputeRackUnitMbTempStatsFrontTemp.{#SNMPINDEX}],5m)>{$TEMP.MAX.WARN:"Ambient"}`|Warning|**Depends on**:<br><ul><li>{#SENSOR_LOCATION}.Front: Temperature is above critical threshold</li></ul>|
|{#SENSOR_LOCATION}.Front: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco UCS Manager by SNMP/cisco.ucs.sensor.temp.value[cucsComputeRackUnitMbTempStatsFrontTemp.{#SNMPINDEX}],5m)>{$TEMP.MAX.CRIT:"Ambient"}`|High||
|{#SENSOR_LOCATION}.Rear: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco UCS Manager by SNMP/cisco.ucs.sensor.temp.value[cucsComputeRackUnitMbTempStatsRearTemp.{#SNMPINDEX}],5m)>{$TEMP.MAX.WARN:"Ambient"}`|Warning|**Depends on**:<br><ul><li>{#SENSOR_LOCATION}.Rear: Temperature is above critical threshold</li></ul>|
|{#SENSOR_LOCATION}.Rear: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco UCS Manager by SNMP/cisco.ucs.sensor.temp.value[cucsComputeRackUnitMbTempStatsRearTemp.{#SNMPINDEX}],5m)>{$TEMP.MAX.CRIT:"Ambient"}`|High||
|{#SENSOR_LOCATION}.IOH: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco UCS Manager by SNMP/cisco.ucs.sensor.temp.value[cucsComputeRackUnitMbTempSltatsIoh1Temp.{#SNMPINDEX}],5m)>{$TEMP.MAX.WARN:"Ambient"}`|Warning|**Depends on**:<br><ul><li>{#SENSOR_LOCATION}.IOH: Temperature is above critical threshold</li></ul>|
|{#SENSOR_LOCATION}.IOH: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco UCS Manager by SNMP/cisco.ucs.sensor.temp.value[cucsComputeRackUnitMbTempSltatsIoh1Temp.{#SNMPINDEX}],5m)>{$TEMP.MAX.CRIT:"Ambient"}`|High||

### LLD rule Temperature CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature CPU discovery||SNMP agent|cisco.ucs.temp.cpu.discovery|

### Item prototypes for Temperature CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SENSOR_LOCATION}: Temperature|<p>MIB: CISCO-UNIFIED-COMPUTING-PROCESSOR-MIB</p><p>Cisco UCS processor:EnvStats:temperature managed object property</p>|SNMP agent|cisco.ucs.sensor.temp.value[cucsProcessorEnvStatsTemperature.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Temperature CPU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SENSOR_LOCATION}: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco UCS Manager by SNMP/cisco.ucs.sensor.temp.value[cucsProcessorEnvStatsTemperature.{#SNMPINDEX}],5m)>{$TEMP.MAX.WARN:"CPU"}`|Warning|**Depends on**:<br><ul><li>{#SENSOR_LOCATION}: Temperature is above critical threshold</li></ul>|
|{#SENSOR_LOCATION}: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco UCS Manager by SNMP/cisco.ucs.sensor.temp.value[cucsProcessorEnvStatsTemperature.{#SNMPINDEX}],5m)>{$TEMP.MAX.CRIT:"CPU"}`|High||

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>Discovering interfaces from IF-MIB.</p>|SNMP agent|cisco.ucs.net.if.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}({#IFALIAS}): Operational status|<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The testing(3) state indicates that no operational packet scan be passed</p><p>- If ifAdminStatus is down(2) then ifOperStatus should be down(2)</p><p>- If ifAdminStatus is changed to up(1) then ifOperStatus should change to up(1) if the interface is ready to transmit and receive network traffic</p><p>- It should change todormant(5) if the interface is waiting for external actions (such as a serial line waiting for an incoming connection)</p><p>- It should remain in the down(2) state if and only if there is a fault that prevents it from going to the up(1) state</p><p>- It should remain in the notPresent(6) state if the interface has missing(typically, hardware) components.</p>|SNMP agent|cisco.ucs.if.status[ifOperStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits received|<p>MIB: IF-MIB</p><p>The total number of octets received on the interface, including framing characters. This object is a 64-bit version of ifInOctets. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|cisco.ucs.if.in[ifHCInOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits sent|<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. This object is a 64-bit version of ifOutOctets.Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|cisco.ucs.if.out[ifHCOutOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|cisco.ucs.if.in.errors[ifInErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|cisco.ucs.if.out.errors[ifOutErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded|<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|cisco.ucs.if.out.discards[ifOutDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded|<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|cisco.ucs.if.in.discards[ifInDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Multicast packets received|<p>MIB: IF-MIB</p><p>The number of packets, delivered by this sub-layer to a</p><p>higher (sub-)layer, which were addressed to a multicast</p><p>address at this sub-layer.  For a MAC layer protocol, this</p><p>includes both Group and Functional addresses.  This object</p><p>is a 64-bit version of ifInMulticastPkts.</p><p></p><p>Discontinuities in the value of this counter can occur at</p><p>re-initialization of the management system, and at other</p><p>times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|cisco.ucs.if.in.multicast[ifHCInMulticastPkts.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Multicast packets sent|<p>MIB: IF-MIB</p><p>The total number of packets that higher-level protocols</p><p>requested be transmitted, and which were addressed to a</p><p>multicast address at this sub-layer, including those that</p><p>were discarded or not sent.  For a MAC layer protocol, this</p><p>includes both Group and Functional addresses.  This object</p><p>is a 64-bit version of ifOutMulticastPkts.</p><p></p><p>Discontinuities in the value of this counter can occur at</p><p>re-initialization of the management system, and at other</p><p>times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|cisco.ucs.if.out.multicast[ifHCOutMulticastPkts.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Broadcast packets received|<p>MIB: IF-MIB</p><p>The number of packets, delivered by this sub-layer to a</p><p>higher (sub-)layer, which were addressed to a broadcast</p><p>address at this sub-layer.  This object is a 64-bit version</p><p>of ifInBroadcastPkts.</p><p></p><p>Discontinuities in the value of this counter can occur at</p><p>re-initialization of the management system, and at other</p><p>times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|cisco.ucs.if.in.broadcast[ifHCInBroadcastPkts.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Broadcast packets sent|<p>MIB: IF-MIB</p><p>The total number of packets that higher-level protocols</p><p>requested be transmitted, and which were addressed to a</p><p>broadcast address at this sub-layer, including those that</p><p>were discarded or not sent.  This object is a 64-bit version</p><p>of ifOutBroadcastPkts.</p><p></p><p>Discontinuities in the value of this counter can occur at</p><p>re-initialization of the management system, and at other</p><p>times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|cisco.ucs.if.out.broadcast[ifHCOutBroadcastPkts.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Interface type|<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for ifType are assigned by the Internet Assigned Numbers Authority (IANA),</p><p>through updating the syntax of the IANAifType textual convention.</p>|SNMP agent|cisco.ucs.if.type[ifType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Interface description|<p>MIB: IF-MIB</p><p>A textual string containing information about the</p><p>interface.  This string should include the name of the</p><p>manufacturer, the product name and the version of the</p><p>interface hardware/software.</p>|SNMP agent|cisco.ucs.if.descr[ifDescr.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Speed|<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in units of 1,000,000 bits per second. If this object reports a value of `n' then the speed of the interface is somewhere in the range of `n-500,000' to`n+499,999'.  For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth. For a sub-layer which has no concept of bandwidth, this object should be zero.</p>|SNMP agent|cisco.ucs.if.speed[ifHighSpeed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000000`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Interface {#IFNAME}({#IFALIAS}): Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, do not fire for the 'eternal off' interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Cisco UCS Manager by SNMP/cisco.ucs.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/Cisco UCS Manager by SNMP/cisco.ucs.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/Cisco UCS Manager by SNMP/cisco.ucs.if.status[ifOperStatus.{#SNMPINDEX}],#2))`|Average|**Manual close**: Yes|
|Interface {#IFNAME}({#IFALIAS}): High error rate on {#IFNAME}|<p>Recovers when value below {$IF.ERRORS.WARN:"{#IFNAME}"} threshold.</p>|`min(/Cisco UCS Manager by SNMP/cisco.ucs.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Cisco UCS Manager by SNMP/cisco.ucs.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|

### LLD rule PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU discovery||SNMP agent|cisco.ucs.psu.discovery|

### Item prototypes for PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#PSU_LOCATION}: Power supply status|<p>MIB: CISCO-UNIFIED-COMPUTING-EQUIPMENT-MIB</p><p>Cisco UCS equipment:Psu:operState managed object property</p>|SNMP agent|cisco.ucs.sensor.psu.status[cucsEquipmentPsuOperState.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for PSU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#PSU_LOCATION}: Power supply is in critical state|<p>Please check the power supply unit for errors</p>|`last(/Cisco UCS Manager by SNMP/cisco.ucs.sensor.psu.status[cucsEquipmentPsuOperState.{#SNMPINDEX}])={$PSU.STATUS.CRIT:"inoperable"}`|Average||
|{#PSU_LOCATION}: Power supply is in warning state|<p>Please check the power supply unit for errors</p>|`last(/Cisco UCS Manager by SNMP/cisco.ucs.sensor.psu.status[cucsEquipmentPsuOperState.{#SNMPINDEX}])={$PSU.STATUS.WARN:"degraded"}`|Warning|**Depends on**:<br><ul><li>{#PSU_LOCATION}: Power supply is in critical state</li></ul>|

### LLD rule Unit discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Unit discovery||SNMP agent|cisco.ucs.unit.discovery|

### Item prototypes for Unit discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#UNIT_LOCATION}: Overall system health status|<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnit:operState managed object property</p>|SNMP agent|cisco.ucs.status[cucsComputeRackUnitOperState.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#UNIT_LOCATION}: Hardware model name|<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnit:model managed object property</p>|SNMP agent|cisco.ucs.hw.model[cucsComputeRackUnitModel.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|{#UNIT_LOCATION}: Hardware serial number|<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnit:serial managed object property</p>|SNMP agent|cisco.ucs.hw.serialnumber[cucsComputeRackUnitSerial.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Unit discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#UNIT_LOCATION}: System status is in critical state|<p>Please check the device for errors</p>|`last(/Cisco UCS Manager by SNMP/cisco.ucs.status[cucsComputeRackUnitOperState.{#SNMPINDEX}])={$HEALTH.STATUS.CRIT:"computeFailed"} or last(/Cisco UCS Manager by SNMP/cisco.ucs.status[cucsComputeRackUnitOperState.{#SNMPINDEX}])={$HEALTH.STATUS.CRIT:"configFailure"} or last(/Cisco UCS Manager by SNMP/cisco.ucs.status[cucsComputeRackUnitOperState.{#SNMPINDEX}])={$HEALTH.STATUS.CRIT:"unconfigFailure"} or last(/Cisco UCS Manager by SNMP/cisco.ucs.status[cucsComputeRackUnitOperState.{#SNMPINDEX}])={$HEALTH.STATUS.CRIT:"inoperable"}`|High||
|{#UNIT_LOCATION}: System status is in warning state|<p>Please check the device for warnings</p>|`last(/Cisco UCS Manager by SNMP/cisco.ucs.status[cucsComputeRackUnitOperState.{#SNMPINDEX}])={$HEALTH.STATUS.WARN:"testFailed"} or last(/Cisco UCS Manager by SNMP/cisco.ucs.status[cucsComputeRackUnitOperState.{#SNMPINDEX}])={$HEALTH.STATUS.WARN:"thermalProblem"} or last(/Cisco UCS Manager by SNMP/cisco.ucs.status[cucsComputeRackUnitOperState.{#SNMPINDEX}])={$HEALTH.STATUS.WARN:"powerProblem"} or last(/Cisco UCS Manager by SNMP/cisco.ucs.status[cucsComputeRackUnitOperState.{#SNMPINDEX}])={$HEALTH.STATUS.WARN:"voltageProblem"} or last(/Cisco UCS Manager by SNMP/cisco.ucs.status[cucsComputeRackUnitOperState.{#SNMPINDEX}])={$HEALTH.STATUS.WARN:"diagnosticsFailed"}`|Warning|**Depends on**:<br><ul><li>{#UNIT_LOCATION}: System status is in critical state</li></ul>|
|{#UNIT_LOCATION}: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Cisco UCS Manager by SNMP/cisco.ucs.hw.serialnumber[cucsComputeRackUnitSerial.{#SNMPINDEX}],#1)<>last(/Cisco UCS Manager by SNMP/cisco.ucs.hw.serialnumber[cucsComputeRackUnitSerial.{#SNMPINDEX}],#2) and length(last(/Cisco UCS Manager by SNMP/cisco.ucs.hw.serialnumber[cucsComputeRackUnitSerial.{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes|

### LLD rule FAN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN discovery||SNMP agent|cisco.ucs.fan.discovery|

### Item prototypes for FAN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#FAN_LOCATION}: Fan status|<p>MIB: CISCO-UNIFIED-COMPUTING-EQUIPMENT-MIB</p><p>Cisco UCS equipment:Fan:operState managed object property</p>|SNMP agent|cisco.ucs.sensor.fan.status[cucsEquipmentFanOperState.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for FAN discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#FAN_LOCATION}: Fan is in warning state|<p>Please check the fan unit</p>|`last(/Cisco UCS Manager by SNMP/cisco.ucs.sensor.fan.status[cucsEquipmentFanOperState.{#SNMPINDEX}])={$FAN.STATUS.WARN:"degraded"}`|Warning|**Depends on**:<br><ul><li>{#FAN_LOCATION}: Fan is in critical state</li></ul>|
|{#FAN_LOCATION}: Fan is in critical state|<p>Please check the fan unit</p>|`last(/Cisco UCS Manager by SNMP/cisco.ucs.sensor.fan.status[cucsEquipmentFanOperState.{#SNMPINDEX}])={$FAN.STATUS.CRIT:"inoperable"}`|Average||

### LLD rule Physical disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Physical disk discovery|<p>Scanning table of physical drive entries CISCO-UNIFIED-COMPUTING-STORAGE-MIB::cucsStorageLocalDiskTable.</p>|SNMP agent|cisco.ucs.physicalDisk.discovery|

### Item prototypes for Physical disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DISK_LOCATION}: Physical disk status|<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalDisk:diskState managed object property.</p>|SNMP agent|cisco.ucs.hw.physicaldisk.status[cucsStorageLocalDiskDiskState.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#DISK_LOCATION}: Physical disk model name|<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalDisk:model managed object property.</p>|SNMP agent|cisco.ucs.hw.physicaldisk.model[cucsStorageLocalDiskModel.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|{#DISK_LOCATION}: Physical disk serial number|<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalDisk:serial managed object property. Actually returns part number code.</p>|SNMP agent|cisco.ucs.hw.physicaldisk.serialnumber[cucsStorageLocalDiskSerial.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|{#DISK_LOCATION}: Physical disk media type|<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalDisk:deviceType managed object property. Actually returns 'HDD' or 'SSD'.</p>|SNMP agent|cisco.ucs.hw.physicaldisk.media_type[cucsStorageLocalDiskDeviceType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|{#DISK_LOCATION}: Disk size|<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalDisk:size managed object property. In MB.</p>|SNMP agent|cisco.ucs.hw.physicaldisk.size[cucsStorageLocalDiskSize.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Physical disk discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#DISK_LOCATION}: Physical disk failed|<p>Please check physical disk for warnings or errors</p>|`last(/Cisco UCS Manager by SNMP/cisco.ucs.hw.physicaldisk.status[cucsStorageLocalDiskDiskState.{#SNMPINDEX}])={$DISK.STATUS.FAIL:"failed"}`|High||
|{#DISK_LOCATION}: Physical disk error|<p>Please check physical disk for warnings or errors</p>|`last(/Cisco UCS Manager by SNMP/cisco.ucs.hw.physicaldisk.status[cucsStorageLocalDiskDiskState.{#SNMPINDEX}])={$DISK.STATUS.CRIT:"bad"} or last(/Cisco UCS Manager by SNMP/cisco.ucs.hw.physicaldisk.status[cucsStorageLocalDiskDiskState.{#SNMPINDEX}])={$DISK.STATUS.CRIT:"predictiveFailure"}`|Average|**Depends on**:<br><ul><li>{#DISK_LOCATION}: Physical disk failed</li></ul>|
|{#DISK_LOCATION}: Disk has been replaced|<p>Disk serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Cisco UCS Manager by SNMP/cisco.ucs.hw.physicaldisk.serialnumber[cucsStorageLocalDiskSerial.{#SNMPINDEX}],#1)<>last(/Cisco UCS Manager by SNMP/cisco.ucs.hw.physicaldisk.serialnumber[cucsStorageLocalDiskSerial.{#SNMPINDEX}],#2) and length(last(/Cisco UCS Manager by SNMP/cisco.ucs.hw.physicaldisk.serialnumber[cucsStorageLocalDiskSerial.{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes|

### LLD rule Virtual disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Virtual disk discovery|<p>CISCO-UNIFIED-COMPUTING-STORAGE-MIB::cucsStorageLocalLunTable</p>|SNMP agent|cisco.ucs.virtualDisk.discovery|

### Item prototypes for Virtual disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#VDISK_LOCATION}: Status|<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalLun:presence managed object property</p>|SNMP agent|cisco.ucs.hw.virtualdisk.status[cucsStorageLocalLunPresence.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#VDISK_LOCATION}: Layout type|<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalLun:type managed object property</p>|SNMP agent|cisco.ucs.hw.virtualdisk.layout[cucsStorageLocalLunType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|{#VDISK_LOCATION}: Disk size|<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalLun:size managed object property in MB.</p>|SNMP agent|cisco.ucs.hw.virtualdisk.size[cucsStorageLocalLunSize.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Virtual disk discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#VDISK_LOCATION}: Virtual disk is not in OK state|<p>Please check virtual disk for warnings or errors</p>|`last(/Cisco UCS Manager by SNMP/cisco.ucs.hw.virtualdisk.status[cucsStorageLocalLunPresence.{#SNMPINDEX}])<>{$VDISK.STATUS.OK:"equipped"}`|Warning||

### LLD rule Array controller discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Array controller discovery|<p>Scanning table of Array controllers: CISCO-UNIFIED-COMPUTING-STORAGE-MIB::cucsStorageControllerTable.</p>|SNMP agent|cisco.ucs.array.discovery|

### Item prototypes for Array controller discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DISKARRAY_LOCATION}: Disk array controller status|<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:RaidBattery:operability managed object property.</p>|SNMP agent|cisco.ucs.hw.diskarray.status[cucsStorageControllerOperState.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#DISKARRAY_LOCATION}: Disk array controller model|<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p>|SNMP agent|cisco.ucs.hw.diskarray.model[cucsStorageControllerModel.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Array controller discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#DISKARRAY_LOCATION}: Disk array controller is in critical state|<p>Please check the device for faults</p>|`last(/Cisco UCS Manager by SNMP/cisco.ucs.hw.diskarray.status[cucsStorageControllerOperState.{#SNMPINDEX}])={$DISK.ARRAY.STATUS.CRIT:"inoperable"}`|High||
|{#DISKARRAY_LOCATION}: Disk array controller is in warning state|<p>Please check the device for faults</p>|`last(/Cisco UCS Manager by SNMP/cisco.ucs.hw.diskarray.status[cucsStorageControllerOperState.{#SNMPINDEX}])={$DISK.ARRAY.STATUS.WARN:"degraded"}`|Average|**Depends on**:<br><ul><li>{#DISKARRAY_LOCATION}: Disk array controller is in critical state</li></ul>|
|{#DISKARRAY_LOCATION}: Disk array controller is not in optimal state|<p>Please check the device for faults</p>|`last(/Cisco UCS Manager by SNMP/cisco.ucs.hw.diskarray.status[cucsStorageControllerOperState.{#SNMPINDEX}])>{$DISK.ARRAY.STATUS.OK:"operable"}`|Warning|**Depends on**:<br><ul><li>{#DISKARRAY_LOCATION}: Disk array controller is in critical state</li><li>{#DISKARRAY_LOCATION}: Disk array controller is in warning state</li></ul>|

### LLD rule Array controller cache discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Array controller cache discovery|<p>Scanning table of Array controllers: CISCO-UNIFIED-COMPUTING-STORAGE-MIB::cucsStorageControllerTable.</p>|SNMP agent|cisco.ucs.array.cache.discovery|

### Item prototypes for Array controller cache discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DISKARRAY_CACHE_LOCATION}: Disk array cache controller battery status|<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p>|SNMP agent|cisco.ucs.hw.diskarray.cache.battery.status[cucsStorageRaidBatteryOperability.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Array controller cache discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#DISKARRAY_CACHE_LOCATION}: Disk array cache controller battery is in critical state!|<p>Please check the device for faults</p>|`last(/Cisco UCS Manager by SNMP/cisco.ucs.hw.diskarray.cache.battery.status[cucsStorageRaidBatteryOperability.{#SNMPINDEX}])={$DISK.ARRAY.CACHE.BATTERY.STATUS.CRIT}`|Average||
|{#DISKARRAY_CACHE_LOCATION}: Disk array cache controller battery is not in optimal state|<p>Please check the device for faults</p>|`last(/Cisco UCS Manager by SNMP/cisco.ucs.hw.diskarray.cache.battery.status[cucsStorageRaidBatteryOperability.{#SNMPINDEX}])<>{$DISK.ARRAY.CACHE.BATTERY.STATUS.OK}`|Warning|**Depends on**:<br><ul><li>{#DISKARRAY_CACHE_LOCATION}: Disk array cache controller battery is in critical state!</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

