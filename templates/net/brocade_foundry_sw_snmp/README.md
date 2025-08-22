
# Brocade_Foundry Nonstackable by SNMP

## Overview

For devices(old Foundry devices, MLXe and so on) that doesn't support Stackable SNMP Tables: snChasFan2Table, snChasPwrSupply2Table,snAgentTemp2Table -
FOUNDRY-SN-AGENT-MIB::snChasFanTable, snChasPwrSupplyTable,snAgentTempTable are used instead.
For example:
The objects in table snChasPwrSupply2Table is not supported on the NetIron and the FastIron SX devices.
snChasFan2Table is not supported on the NetIron devices.
snAgentTemp2Table is not supported on old versions of MLXe.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Brocade MLXe (System Mode: MLX), IronWare Version V5.4.0eT163 Compiled on Oct 30 2013 at 16:40:24 labeled as V5.4.00e
- Foundry FLS648 Foundry Networks, Inc. FLS648, IronWare Version 04.1.00bT7e1 Compiled on Feb 29 2008 at 21:35:28 labeled as FGS04100b
- Foundry FWSX424 Foundry Networks, Inc. FWSX424, IronWare Version 02.0.00aT1e0 Compiled on Dec 10 2004 at 14:40:19 labeled as FWXS02000a

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$TEMP_CRIT_LOW}||`5`|
|{$TEMP_CRIT}||`75`|
|{$TEMP_WARN}||`65`|
|{$PSU_CRIT_STATUS}||`3`|
|{$FAN_CRIT_STATUS}||`3`|
|{$PSU_OK_STATUS}||`2`|
|{$FAN_OK_STATUS}||`2`|
|{$CPU.UTIL.CRIT}||`90`|
|{$MEMORY.UTIL.MAX}||`90`|
|{$SNMP.TIMEOUT}|<p>Time interval for the SNMP availability trigger.</p>|`5m`|
|{$ICMP_LOSS_WARN}|<p>Warning threshold of ICMP packet loss in %.</p>|`20`|
|{$ICMP_RESPONSE_TIME_WARN}|<p>Warning threshold of the average ICMP response time in seconds.</p>|`0.15`|
|{$IF.ERRORS.WARN}|<p>Warning threshold of error packet rate. Can be used with interface name as context.</p>|`2`|
|{$IF.UTIL.MAX}||`90`|
|{$IFCONTROL}||`1`|
|{$NET.IF.IFNAME.MATCHES}||`^.*$`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>Filter out loopbacks, nulls, docker veth links and docker0 bridge by default</p>|`Macro too long. Please see the template.`|
|{$NET.IF.IFOPERSTATUS.MATCHES}||`^.*$`|
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES}|<p>Ignore notPresent(6)</p>|`^6$`|
|{$NET.IF.IFADMINSTATUS.MATCHES}||`^.*`|
|{$NET.IF.IFADMINSTATUS.NOT_MATCHES}|<p>Ignore down(2) administrative status</p>|`^2$`|
|{$NET.IF.IFDESCR.MATCHES}||`.*`|
|{$NET.IF.IFDESCR.NOT_MATCHES}||`CHANGE_IF_NEEDED`|
|{$NET.IF.IFALIAS.MATCHES}||`.*`|
|{$NET.IF.IFALIAS.NOT_MATCHES}||`CHANGE_IF_NEEDED`|
|{$NET.IF.IFTYPE.MATCHES}||`.*`|
|{$NET.IF.IFTYPE.NOT_MATCHES}||`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Hardware serial number|<p>MIB: FOUNDRY-SN-AGENT-MIB</p>|SNMP agent|system.hw.serialnumber<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Firmware version|<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The version of the running software in the form'major.minor.maintenance[letters]'</p>|SNMP agent|system.hw.firmware<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|CPU utilization|<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The statistics collection of 1 minute CPU utilization.</p>|SNMP agent|system.cpu.util[snAgGblCpuUtil1MinAvg.0]|
|Memory utilization|<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The system dynamic memory utilization, in unit of percentage.</p><p>Deprecated: Refer to snAgSystemDRAMUtil.</p><p>For NI platforms, refer to snAgentBrdMemoryUtil100thPercent.</p>|SNMP agent|vm.memory.util[snAgGblDynMemUtil.0]|
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

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Brocade Nonstackable: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Brocade_Foundry Nonstackable by SNMP/system.hw.serialnumber,#1)<>last(/Brocade_Foundry Nonstackable by SNMP/system.hw.serialnumber,#2) and length(last(/Brocade_Foundry Nonstackable by SNMP/system.hw.serialnumber))>0`|Info|**Manual close**: Yes|
|Brocade Nonstackable: Firmware has changed|<p>Firmware version has changed. Acknowledge to close the problem manually.</p>|`last(/Brocade_Foundry Nonstackable by SNMP/system.hw.firmware,#1)<>last(/Brocade_Foundry Nonstackable by SNMP/system.hw.firmware,#2) and length(last(/Brocade_Foundry Nonstackable by SNMP/system.hw.firmware))>0`|Info|**Manual close**: Yes|
|Brocade Nonstackable: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Brocade_Foundry Nonstackable by SNMP/system.cpu.util[snAgGblCpuUtil1MinAvg.0],5m)>{$CPU.UTIL.CRIT}`|Warning||
|Brocade Nonstackable: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Brocade_Foundry Nonstackable by SNMP/vm.memory.util[snAgGblDynMemUtil.0],5m)>{$MEMORY.UTIL.MAX}`|Average||
|Brocade Nonstackable: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Brocade_Foundry Nonstackable by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Brocade_Foundry Nonstackable by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Brocade_Foundry Nonstackable by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Brocade_Foundry Nonstackable by SNMP/system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Brocade Nonstackable: No SNMP data collection</li></ul>|
|Brocade Nonstackable: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/Brocade_Foundry Nonstackable by SNMP/system.name,#1)<>last(/Brocade_Foundry Nonstackable by SNMP/system.name,#2) and length(last(/Brocade_Foundry Nonstackable by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|Brocade Nonstackable: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/Brocade_Foundry Nonstackable by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>Brocade Nonstackable: Unavailable by ICMP ping</li></ul>|
|Brocade Nonstackable: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/Brocade_Foundry Nonstackable by SNMP/icmpping,#3)=0`|High||
|Brocade Nonstackable: High ICMP ping loss|<p>ICMP packets loss detected.</p>|`min(/Brocade_Foundry Nonstackable by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Brocade_Foundry Nonstackable by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>Brocade Nonstackable: Unavailable by ICMP ping</li></ul>|
|Brocade Nonstackable: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/Brocade_Foundry Nonstackable by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>Brocade Nonstackable: High ICMP ping loss</li><li>Brocade Nonstackable: Unavailable by ICMP ping</li></ul>|

### LLD rule PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU Discovery|<p>snChasPwrSupplyTable: A table of each power supply information. Only installed power supply appears in a table row.</p>|SNMP agent|psu.discovery|

### Item prototypes for PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU {#PSU_INDEX}: Power supply status|<p>MIB: FOUNDRY-SN-AGENT-MIB</p>|SNMP agent|sensor.psu.status[snChasPwrSupplyOperStatus.{#SNMPINDEX}]|

### Trigger prototypes for PSU Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Brocade Nonstackable: PSU {#PSU_INDEX}: Power supply is in critical state|<p>Please check the power supply unit for errors</p>|`count(/Brocade_Foundry Nonstackable by SNMP/sensor.psu.status[snChasPwrSupplyOperStatus.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS}")=1`|Average||
|Brocade Nonstackable: PSU {#PSU_INDEX}: Power supply is not in normal state|<p>Please check the power supply unit for errors</p>|`count(/Brocade_Foundry Nonstackable by SNMP/sensor.psu.status[snChasPwrSupplyOperStatus.{#SNMPINDEX}],#1,"ne","{$PSU_OK_STATUS}")=1`|Info|**Depends on**:<br><ul><li>Brocade Nonstackable: PSU {#PSU_INDEX}: Power supply is in critical state</li></ul>|

### LLD rule FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN Discovery|<p>snChasFanTable: A table of each fan information. Only installed fan appears in a table row.</p>|SNMP agent|fan.discovery|

### Item prototypes for FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan {#FAN_INDEX}: Fan status|<p>MIB: FOUNDRY-SN-AGENT-MIB</p>|SNMP agent|sensor.fan.status[snChasFanOperStatus.{#SNMPINDEX}]|

### Trigger prototypes for FAN Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Brocade Nonstackable: Fan {#FAN_INDEX}: Fan is in critical state|<p>Please check the fan unit</p>|`count(/Brocade_Foundry Nonstackable by SNMP/sensor.fan.status[snChasFanOperStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS}")=1`|Average||
|Brocade Nonstackable: Fan {#FAN_INDEX}: Fan is not in normal state|<p>Please check the fan unit</p>|`count(/Brocade_Foundry Nonstackable by SNMP/sensor.fan.status[snChasFanOperStatus.{#SNMPINDEX}],#1,"ne","{$FAN_OK_STATUS}")=1`|Info|**Depends on**:<br><ul><li>Brocade Nonstackable: Fan {#FAN_INDEX}: Fan is in critical state</li></ul>|

### LLD rule Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature Discovery|<p>snAgentTempTable:Table to list temperatures of the modules in the device. This table is applicable to only those modules with temperature sensors.</p>|SNMP agent|temp.discovery|

### Item prototypes for Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SENSOR_DESCR}: Temperature|<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>Temperature of the sensor represented by this row. Each unit is 0.5 degrees Celsius.</p>|SNMP agent|sensor.temp.value[snAgentTempValue.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.5`</p></li></ul>|

### Trigger prototypes for Temperature Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Brocade Nonstackable: {#SENSOR_DESCR}: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Brocade_Foundry Nonstackable by SNMP/sensor.temp.value[snAgentTempValue.{#SNMPINDEX}],5m)>{$TEMP_WARN:"{#SENSOR_DESCR}"}`|Warning|**Depends on**:<br><ul><li>Brocade Nonstackable: {#SENSOR_DESCR}: Temperature is above critical threshold</li></ul>|
|Brocade Nonstackable: {#SENSOR_DESCR}: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Brocade_Foundry Nonstackable by SNMP/sensor.temp.value[snAgentTempValue.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"{#SENSOR_DESCR}"}`|High||
|Brocade Nonstackable: {#SENSOR_DESCR}: Temperature is too low||`avg(/Brocade_Foundry Nonstackable by SNMP/sensor.temp.value[snAgentTempValue.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"{#SENSOR_DESCR}"}`|Average||

### LLD rule Temperature Discovery Chassis

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature Discovery Chassis|<p>Since temperature of the chassis is not available on all Brocade/Foundry hardware, this LLD is here to avoid unsupported items.</p>|SNMP agent|temp.chassis.discovery|

### Item prototypes for Temperature Discovery Chassis

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Chassis #{#SNMPINDEX}: Temperature|<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>Temperature of the chassis. Each unit is 0.5 degrees Celsius.</p><p>Only management module built with temperature sensor hardware is applicable.</p><p>For those non-applicable management module, it returns no-such-name.</p>|SNMP agent|sensor.temp.value[snChasActualTemperature.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.5`</p></li></ul>|

### Trigger prototypes for Temperature Discovery Chassis

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Brocade Nonstackable: Chassis #{#SNMPINDEX}: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Brocade_Foundry Nonstackable by SNMP/sensor.temp.value[snChasActualTemperature.{#SNMPINDEX}],5m)>{$TEMP_WARN:"Chassis"}`|Warning|**Depends on**:<br><ul><li>Brocade Nonstackable: Chassis #{#SNMPINDEX}: Temperature is above critical threshold</li></ul>|
|Brocade Nonstackable: Chassis #{#SNMPINDEX}: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Brocade_Foundry Nonstackable by SNMP/sensor.temp.value[snChasActualTemperature.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"Chassis"}`|High||
|Brocade Nonstackable: Chassis #{#SNMPINDEX}: Temperature is too low||`avg(/Brocade_Foundry Nonstackable by SNMP/sensor.temp.value[snChasActualTemperature.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"Chassis"}`|Average||

### LLD rule Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interfaces discovery|<p>Discovering interfaces from IF-MIB.</p>|SNMP agent|net.if.discovery|

### Item prototypes for Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}({#IFALIAS}): Operational status|<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The testing(3) state indicates that no operational packet scan be passed</p><p>- If ifAdminStatus is down(2) then ifOperStatus should be down(2)</p><p>- If ifAdminStatus is changed to up(1) then ifOperStatus should change to up(1) if the interface is ready to transmit and receive network traffic</p><p>- It should change todormant(5) if the interface is waiting for external actions (such as a serial line waiting for an incoming connection)</p><p>- It should remain in the down(2) state if and only if there is a fault that prevents it from going to the up(1) state</p><p>- It should remain in the notPresent(6) state if the interface has missing(typically, hardware) components.</p>|SNMP agent|net.if.status[ifOperStatus.{#SNMPINDEX}]|
|Interface {#IFNAME}({#IFALIAS}): Bits received|<p>MIB: IF-MIB</p><p>The total number of octets received on the interface, including framing characters. This object is a 64-bit version of ifInOctets. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.in[ifHCInOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits sent|<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. This object is a 64-bit version of ifOutOctets.Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.out[ifHCOutOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.in.errors[ifInErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.out.errors[ifOutErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded|<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.out.discards[ifOutDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded|<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.in.discards[ifInDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Interface type|<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for ifType are assigned by the Internet Assigned Numbers Authority (IANA),</p><p>through updating the syntax of the IANAifType textual convention.</p>|SNMP agent|net.if.type[ifType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Speed|<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in units of 1,000,000 bits per second. If this object reports a value of `n' then the speed of the interface is somewhere in the range of `n-500,000' to`n+499,999'.  For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth. For a sub-layer which has no concept of bandwidth, this object should be zero.</p>|SNMP agent|net.if.speed[ifHighSpeed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000000`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Network interfaces discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Brocade Nonstackable: Interface {#IFNAME}({#IFALIAS}): Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine the context macro to "0", marking this interface as not important. No new trigger will be fired if this interface is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, does not fire for "eternal off" interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Brocade_Foundry Nonstackable by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/Brocade_Foundry Nonstackable by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/Brocade_Foundry Nonstackable by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`|Average|**Manual close**: Yes|
|Brocade Nonstackable: Interface {#IFNAME}({#IFALIAS}): High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Brocade_Foundry Nonstackable by SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Brocade_Foundry Nonstackable by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) or avg(/Brocade_Foundry Nonstackable by SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Brocade_Foundry Nonstackable by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])) and last(/Brocade_Foundry Nonstackable by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Brocade Nonstackable: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Brocade Nonstackable: Interface {#IFNAME}({#IFALIAS}): High error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Brocade_Foundry Nonstackable by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Brocade_Foundry Nonstackable by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Brocade Nonstackable: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Brocade Nonstackable: Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Brocade_Foundry Nonstackable by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])<0 and last(/Brocade_Foundry Nonstackable by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and ( last(/Brocade_Foundry Nonstackable by SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/Brocade_Foundry Nonstackable by SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/Brocade_Foundry Nonstackable by SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/Brocade_Foundry Nonstackable by SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/Brocade_Foundry Nonstackable by SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/Brocade_Foundry Nonstackable by SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/Brocade_Foundry Nonstackable by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Brocade Nonstackable: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|

# Brocade_Foundry Stackable by SNMP

## Overview

For devices(most of the IronWare Brocade devices) that support Stackable SNMP Tables in FOUNDRY-SN-AGENT-MIB: snChasFan2Table, snChasPwrSupply2Table,snAgentTemp2Table - so objects from all Stack members are provided.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Brocade ICX7250-48 ICX7250-48, IronWare Version 08.0.30kT211 Compiled on Oct 18 2016 at 05:40:38 labeled as SPS08030k
- Brocade ICX7250-48(Stacked) Stacking System ICX7250-48, IronWare Version 08.0.30kT211 Compiled on Oct 18 2016 at 05:40:38 labeled as SPS08030k
- Brocade ICX7450-48(Stacked) Stacking System ICX7450-48, IronWare Version 08.0.30kT211 Compiled on Oct 18 2016 at 05:40:38 labeled as SPS08030k"
- Brocade ICX7250-48(Stacked) Stacking System ICX7250-48, IronWare Version 08.0.30kT211 Compiled on Oct 18 2016 at 05:40:38 labeled as SPS08030k
- Brocade ICX7450-48F(Stacked) Stacking System ICX7750-48F, IronWare Version 08.0.40bT203 Compiled on Oct 20 2016 at 23:48:43 labeled as SWR08040b
- Brocade ICX 6600 

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$TEMP_CRIT_LOW}||`5`|
|{$TEMP_CRIT}||`75`|
|{$TEMP_WARN}||`65`|
|{$PSU_CRIT_STATUS}||`3`|
|{$FAN_CRIT_STATUS}||`3`|
|{$PSU_OK_STATUS}||`2`|
|{$FAN_OK_STATUS}||`2`|
|{$CPU.UTIL.CRIT}||`90`|
|{$MEMORY.UTIL.MAX}||`90`|
|{$SNMP.TIMEOUT}|<p>Time interval for the SNMP availability trigger.</p>|`5m`|
|{$ICMP_LOSS_WARN}|<p>Warning threshold of ICMP packet loss in %.</p>|`20`|
|{$ICMP_RESPONSE_TIME_WARN}|<p>Warning threshold of the average ICMP response time in seconds.</p>|`0.15`|
|{$IF.ERRORS.WARN}|<p>Warning threshold of error packet rate. Can be used with interface name as context.</p>|`2`|
|{$IF.UTIL.MAX}||`90`|
|{$IFCONTROL}||`1`|
|{$NET.IF.IFNAME.MATCHES}||`^.*$`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>Filter out loopbacks, nulls, docker veth links and docker0 bridge by default</p>|`Macro too long. Please see the template.`|
|{$NET.IF.IFOPERSTATUS.MATCHES}||`^.*$`|
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES}|<p>Ignore notPresent(6)</p>|`^6$`|
|{$NET.IF.IFADMINSTATUS.MATCHES}||`^.*`|
|{$NET.IF.IFADMINSTATUS.NOT_MATCHES}|<p>Ignore down(2) administrative status</p>|`^2$`|
|{$NET.IF.IFDESCR.MATCHES}||`.*`|
|{$NET.IF.IFDESCR.NOT_MATCHES}||`CHANGE_IF_NEEDED`|
|{$NET.IF.IFALIAS.MATCHES}||`.*`|
|{$NET.IF.IFALIAS.NOT_MATCHES}||`CHANGE_IF_NEEDED`|
|{$NET.IF.IFTYPE.MATCHES}||`.*`|
|{$NET.IF.IFTYPE.NOT_MATCHES}||`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Firmware version|<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The version of the running software in the form 'major.minor.maintenance[letters]'</p>|SNMP agent|system.hw.firmware<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|CPU utilization|<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The statistics collection of 1 minute CPU utilization.</p>|SNMP agent|system.cpu.util[snAgGblCpuUtil1MinAvg.0]|
|Memory utilization|<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The system dynamic memory utilization, in unit of percentage.</p><p>Deprecated: Refer to snAgSystemDRAMUtil.</p><p>For NI platforms, refer to snAgentBrdMemoryUtil100thPercent.</p>|SNMP agent|vm.memory.util[snAgGblDynMemUtil.0]|
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

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Brocade Stackable: Firmware has changed|<p>Firmware version has changed. Acknowledge to close the problem manually.</p>|`last(/Brocade_Foundry Stackable by SNMP/system.hw.firmware,#1)<>last(/Brocade_Foundry Stackable by SNMP/system.hw.firmware,#2) and length(last(/Brocade_Foundry Stackable by SNMP/system.hw.firmware))>0`|Info|**Manual close**: Yes|
|Brocade Stackable: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Brocade_Foundry Stackable by SNMP/system.cpu.util[snAgGblCpuUtil1MinAvg.0],5m)>{$CPU.UTIL.CRIT}`|Warning||
|Brocade Stackable: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Brocade_Foundry Stackable by SNMP/vm.memory.util[snAgGblDynMemUtil.0],5m)>{$MEMORY.UTIL.MAX}`|Average||
|Brocade Stackable: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Brocade_Foundry Stackable by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Brocade_Foundry Stackable by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Brocade_Foundry Stackable by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Brocade_Foundry Stackable by SNMP/system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Brocade Stackable: No SNMP data collection</li></ul>|
|Brocade Stackable: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/Brocade_Foundry Stackable by SNMP/system.name,#1)<>last(/Brocade_Foundry Stackable by SNMP/system.name,#2) and length(last(/Brocade_Foundry Stackable by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|Brocade Stackable: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/Brocade_Foundry Stackable by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>Brocade Stackable: Unavailable by ICMP ping</li></ul>|
|Brocade Stackable: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/Brocade_Foundry Stackable by SNMP/icmpping,#3)=0`|High||
|Brocade Stackable: High ICMP ping loss|<p>ICMP packets loss detected.</p>|`min(/Brocade_Foundry Stackable by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Brocade_Foundry Stackable by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>Brocade Stackable: Unavailable by ICMP ping</li></ul>|
|Brocade Stackable: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/Brocade_Foundry Stackable by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>Brocade Stackable: High ICMP ping loss</li><li>Brocade Stackable: Unavailable by ICMP ping</li></ul>|

### LLD rule PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU Discovery|<p>snChasPwrSupply2Table: A table of each power supply information for each unit. Only installed power supply appears in a table row.</p>|SNMP agent|psu.discovery|

### Item prototypes for PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Unit {#PSU_UNIT} PSU {#PSU_INDEX}: Power supply status|<p>MIB: FOUNDRY-SN-AGENT-MIB</p>|SNMP agent|sensor.psu.status[snChasPwrSupply2OperStatus.{#SNMPINDEX}]|

### Trigger prototypes for PSU Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Brocade Stackable: Unit {#PSU_UNIT} PSU {#PSU_INDEX}: Power supply is in critical state|<p>Please check the power supply unit for errors</p>|`count(/Brocade_Foundry Stackable by SNMP/sensor.psu.status[snChasPwrSupply2OperStatus.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS}")=1`|Average||
|Brocade Stackable: Unit {#PSU_UNIT} PSU {#PSU_INDEX}: Power supply is not in normal state|<p>Please check the power supply unit for errors</p>|`count(/Brocade_Foundry Stackable by SNMP/sensor.psu.status[snChasPwrSupply2OperStatus.{#SNMPINDEX}],#1,"ne","{$PSU_OK_STATUS}")=1`|Info|**Depends on**:<br><ul><li>Brocade Stackable: Unit {#PSU_UNIT} PSU {#PSU_INDEX}: Power supply is in critical state</li></ul>|

### LLD rule FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN Discovery|<p>snChasFan2Table: A table of each fan information for each unit. Only installed fan appears in a table row.</p>|SNMP agent|fan.discovery|

### Item prototypes for FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Unit {#FAN_UNIT} Fan {#FAN_INDEX}: Fan status|<p>MIB: FOUNDRY-SN-AGENT-MIB</p>|SNMP agent|sensor.fan.status[snChasFan2OperStatus.{#SNMPINDEX}]|

### Trigger prototypes for FAN Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Brocade Stackable: Unit {#FAN_UNIT} Fan {#FAN_INDEX}: Fan is in critical state|<p>Please check the fan unit</p>|`count(/Brocade_Foundry Stackable by SNMP/sensor.fan.status[snChasFan2OperStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS}")=1`|Average||
|Brocade Stackable: Unit {#FAN_UNIT} Fan {#FAN_INDEX}: Fan is not in normal state|<p>Please check the fan unit</p>|`count(/Brocade_Foundry Stackable by SNMP/sensor.fan.status[snChasFan2OperStatus.{#SNMPINDEX}],#1,"ne","{$FAN_OK_STATUS}")=1`|Info|**Depends on**:<br><ul><li>Brocade Stackable: Unit {#FAN_UNIT} Fan {#FAN_INDEX}: Fan is in critical state</li></ul>|

### LLD rule Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature Discovery|<p>snAgentTemp2Table:Table to list temperatures of the modules in the device for each unit. This table is applicable to only those modules with temperature sensors.</p>|SNMP agent|temp.discovery|

### Item prototypes for Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SENSOR_DESCR}: Temperature|<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>Temperature of the sensor represented by this row. Each unit is 0.5 degrees Celsius.</p>|SNMP agent|sensor.temp.value[snAgentTemp2Value.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.5`</p></li></ul>|

### Trigger prototypes for Temperature Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Brocade Stackable: {#SENSOR_DESCR}: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Brocade_Foundry Stackable by SNMP/sensor.temp.value[snAgentTemp2Value.{#SNMPINDEX}],5m)>{$TEMP_WARN:"{#SENSOR_DESCR}"}`|Warning|**Depends on**:<br><ul><li>Brocade Stackable: {#SENSOR_DESCR}: Temperature is above critical threshold</li></ul>|
|Brocade Stackable: {#SENSOR_DESCR}: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Brocade_Foundry Stackable by SNMP/sensor.temp.value[snAgentTemp2Value.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"{#SENSOR_DESCR}"}`|High||
|Brocade Stackable: {#SENSOR_DESCR}: Temperature is too low||`avg(/Brocade_Foundry Stackable by SNMP/sensor.temp.value[snAgentTemp2Value.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"{#SENSOR_DESCR}"}`|Average||

### LLD rule Stack Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Stack Discovery|<p>Discovering snStackingConfigUnitTable for Model names</p>|SNMP agent|stack.discovery|

### Item prototypes for Stack Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Unit {#SNMPINDEX}: Hardware model name|<p>MIB: FOUNDRY-SN-STACKING-MIB</p><p>A description of the configured/active system type for each unit.</p>|SNMP agent|system.hw.model[snStackingConfigUnitType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### LLD rule Chassis Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Chassis Discovery|<p>snChasUnitIndex: The index to chassis table.</p>|SNMP agent|chassis.discovery|

### Item prototypes for Chassis Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Unit {#SNMPVALUE}: Hardware serial number|<p>MIB: FOUNDRY-SN-AGENT-MIB</p><p>The serial number of the chassis for each unit. If the serial number is unknown or unavailable then the value should be a zero length string.</p>|SNMP agent|system.hw.serialnumber[snChasUnitSerNum.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Chassis Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Brocade Stackable: Unit {#SNMPVALUE}: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Brocade_Foundry Stackable by SNMP/system.hw.serialnumber[snChasUnitSerNum.{#SNMPINDEX}],#1)<>last(/Brocade_Foundry Stackable by SNMP/system.hw.serialnumber[snChasUnitSerNum.{#SNMPINDEX}],#2) and length(last(/Brocade_Foundry Stackable by SNMP/system.hw.serialnumber[snChasUnitSerNum.{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes|

### LLD rule Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interfaces discovery|<p>Discovering interfaces from IF-MIB.</p>|SNMP agent|net.if.discovery|

### Item prototypes for Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}({#IFALIAS}): Operational status|<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The testing(3) state indicates that no operational packet scan be passed</p><p>- If ifAdminStatus is down(2) then ifOperStatus should be down(2)</p><p>- If ifAdminStatus is changed to up(1) then ifOperStatus should change to up(1) if the interface is ready to transmit and receive network traffic</p><p>- It should change todormant(5) if the interface is waiting for external actions (such as a serial line waiting for an incoming connection)</p><p>- It should remain in the down(2) state if and only if there is a fault that prevents it from going to the up(1) state</p><p>- It should remain in the notPresent(6) state if the interface has missing(typically, hardware) components.</p>|SNMP agent|net.if.status[ifOperStatus.{#SNMPINDEX}]|
|Interface {#IFNAME}({#IFALIAS}): Bits received|<p>MIB: IF-MIB</p><p>The total number of octets received on the interface, including framing characters. This object is a 64-bit version of ifInOctets. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.in[ifHCInOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits sent|<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. This object is a 64-bit version of ifOutOctets.Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.out[ifHCOutOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.in.errors[ifInErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.out.errors[ifOutErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded|<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.out.discards[ifOutDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded|<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|SNMP agent|net.if.in.discards[ifInDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Interface type|<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for ifType are assigned by the Internet Assigned Numbers Authority (IANA),</p><p>through updating the syntax of the IANAifType textual convention.</p>|SNMP agent|net.if.type[ifType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Speed|<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in units of 1,000,000 bits per second. If this object reports a value of `n' then the speed of the interface is somewhere in the range of `n-500,000' to`n+499,999'.  For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth. For a sub-layer which has no concept of bandwidth, this object should be zero.</p>|SNMP agent|net.if.speed[ifHighSpeed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1000000`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Network interfaces discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Brocade Stackable: Interface {#IFNAME}({#IFALIAS}): Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine the context macro to "0", marking this interface as not important. No new trigger will be fired if this interface is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, does not fire for "eternal off" interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Brocade_Foundry Stackable by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/Brocade_Foundry Stackable by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/Brocade_Foundry Stackable by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`|Average|**Manual close**: Yes|
|Brocade Stackable: Interface {#IFNAME}({#IFALIAS}): High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Brocade_Foundry Stackable by SNMP/net.if.in[ifHCInOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Brocade_Foundry Stackable by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}]) or avg(/Brocade_Foundry Stackable by SNMP/net.if.out[ifHCOutOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Brocade_Foundry Stackable by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])) and last(/Brocade_Foundry Stackable by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Brocade Stackable: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Brocade Stackable: Interface {#IFNAME}({#IFALIAS}): High error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Brocade_Foundry Stackable by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Brocade_Foundry Stackable by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Brocade Stackable: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Brocade Stackable: Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Brocade_Foundry Stackable by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])<0 and last(/Brocade_Foundry Stackable by SNMP/net.if.speed[ifHighSpeed.{#SNMPINDEX}])>0 and ( last(/Brocade_Foundry Stackable by SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/Brocade_Foundry Stackable by SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/Brocade_Foundry Stackable by SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/Brocade_Foundry Stackable by SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/Brocade_Foundry Stackable by SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/Brocade_Foundry Stackable by SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/Brocade_Foundry Stackable by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Brocade Stackable: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

