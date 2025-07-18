
# Supermicro Aten by SNMP

## Overview

for BMC ATEN IPMI controllers of Supermicro servers
https://www.supermicro.com/solutions/IPMI.cfm


## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Supermicro X10DRI

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$TEMP_CRIT}||`60`|
|{$TEMP_CRIT_LOW}||`5`|
|{$TEMP_WARN}||`50`|
|{$SNMP.TIMEOUT}|<p>Time interval for the SNMP availability trigger.</p>|`5m`|
|{$ICMP_LOSS_WARN}|<p>Warning threshold of ICMP packet loss in %.</p>|`20`|
|{$ICMP_RESPONSE_TIME_WARN}|<p>Warning threshold of the average ICMP response time in seconds.</p>|`0.15`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
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
|Supermicro Aten: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Supermicro Aten by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Supermicro Aten by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Supermicro Aten by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Supermicro Aten by SNMP/system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Supermicro Aten: No SNMP data collection</li></ul>|
|Supermicro Aten: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/Supermicro Aten by SNMP/system.name,#1)<>last(/Supermicro Aten by SNMP/system.name,#2) and length(last(/Supermicro Aten by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|Supermicro Aten: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/Supermicro Aten by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>Supermicro Aten: Unavailable by ICMP ping</li></ul>|
|Supermicro Aten: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/Supermicro Aten by SNMP/icmpping,#3)=0`|High||
|Supermicro Aten: High ICMP ping loss|<p>ICMP packets loss detected.</p>|`min(/Supermicro Aten by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Supermicro Aten by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>Supermicro Aten: Unavailable by ICMP ping</li></ul>|
|Supermicro Aten: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/Supermicro Aten by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>Supermicro Aten: High ICMP ping loss</li><li>Supermicro Aten: Unavailable by ICMP ping</li></ul>|

### LLD rule Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature Discovery|<p>Scanning ATEN-IPMI-MIB::sensorTable with filter: not connected temp sensors (Value = 0)</p>|SNMP agent|tempDescr.discovery|

### Item prototypes for Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SENSOR_DESCR}: Temperature|<p>MIB: ATEN-IPMI-MIB</p><p>A textual string containing information about the interface.</p><p>This string should include the name of the manufacturer, the product name and the version of the interface hardware/software.</p>|SNMP agent|sensor.temp.value[sensorReading.{#SNMPINDEX}]|

### Trigger prototypes for Temperature Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Supermicro Aten: {#SENSOR_DESCR}: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Supermicro Aten by SNMP/sensor.temp.value[sensorReading.{#SNMPINDEX}],5m)>{$TEMP_WARN:"{#SENSOR_DESCR}"}`|Warning|**Depends on**:<br><ul><li>Supermicro Aten: {#SENSOR_DESCR}: Temperature is above critical threshold</li></ul>|
|Supermicro Aten: {#SENSOR_DESCR}: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Supermicro Aten by SNMP/sensor.temp.value[sensorReading.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"{#SENSOR_DESCR}"}`|High||
|Supermicro Aten: {#SENSOR_DESCR}: Temperature is too low||`avg(/Supermicro Aten by SNMP/sensor.temp.value[sensorReading.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"{#SENSOR_DESCR}"}`|Average||

### LLD rule FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN Discovery|<p>Scanning ATEN-IPMI-MIB::sensorTable with filter: not connected FAN sensors (Value = 0)</p>|SNMP agent|fan.discovery|

### Item prototypes for FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SENSOR_DESCR}: Fan speed, %|<p>MIB: ATEN-IPMI-MIB</p><p>A textual string containing information about the interface.</p><p>This string should include the name of the manufacturer, the product name and the version of the interface hardware/software.</p>|SNMP agent|sensor.fan.speed.percentage[sensorReading.{#SNMPINDEX}]|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

