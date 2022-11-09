
# Supermicro Aten by SNMP

## Overview

For Zabbix version: 6.0 and higher.  
for BMC ATEN IPMI controllers of Supermicro servers
https://www.supermicro.com/solutions/IPMI.cfm


This template was tested on:

- Supermicro X10DRI

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ICMP_LOSS_WARN} |<p>-</p> |`20` |
|{$ICMP_RESPONSE_TIME_WARN} |<p>-</p> |`0.15` |
|{$SNMP.TIMEOUT} |<p>-</p> |`5m` |
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT} |<p>-</p> |`60` |
|{$TEMP_WARN} |<p>-</p> |`50` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|FAN Discovery |<p>Scanning ATEN-IPMI-MIB::sensorTable with filter: not connected FAN sensors (Value = 0)</p> |SNMP |fan.discovery<p>**Filter**:</p>AND <p>- {#SNMPVALUE} MATCHES_REGEX `[1-9]+`</p><p>- {#SENSOR_DESCR} MATCHES_REGEX `FAN.*`</p> |
|Temperature Discovery |<p>Scanning ATEN-IPMI-MIB::sensorTable with filter: not connected temp sensors (Value = 0)</p> |SNMP |tempDescr.discovery<p>**Filter**:</p>AND <p>- {#SNMPVALUE} MATCHES_REGEX `[1-9]+`</p><p>- {#SENSOR_DESCR} MATCHES_REGEX `.*Temp.*`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Fans |{#SENSOR_DESCR}: Fan speed, % |<p>MIB: ATEN-IPMI-MIB</p><p>A textual string containing information about the interface.</p><p>This string should include the name of the manufacturer, the product name and the version of the interface hardware/software.</p> |SNMP |sensor.fan.speed.percentage[sensorReading.{#SNMPINDEX}] |
|General |SNMP traps (fallback) |<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p> |SNMP_TRAP |snmptrap.fallback |
|General |System location |<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet, 3rd floor').  If the location is unknown, the value is the zero-length string.</p> |SNMP |system.location[sysLocation.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System contact details |<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</p> |SNMP |system.contact[sysContact.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System object ID |<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity.  This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p> |SNMP |system.objectid[sysObjectID.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System name |<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name.  If the name is unknown, the value is the zero-length string.</p> |SNMP |system.name<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System description |<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p> |SNMP |system.descr[sysDescr.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|Status |Uptime (network) |<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p> |SNMP |system.net.uptime[sysUpTime.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Status |Uptime (hardware) |<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p> |SNMP |system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- MULTIPLIER: `0.01`</p> |
|Status |SNMP agent availability |<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible value:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p> |INTERNAL |zabbix[host,snmp,available] |
|Status |ICMP ping |<p>-</p> |SIMPLE |icmpping |
|Status |ICMP loss |<p>-</p> |SIMPLE |icmppingloss |
|Status |ICMP response time |<p>-</p> |SIMPLE |icmppingsec |
|Temperature |{#SENSOR_DESCR}: Temperature |<p>MIB: ATEN-IPMI-MIB</p><p>A textual string containing information about the interface.</p><p>This string should include the name of the manufacturer, the product name and the version of the interface hardware/software.</p> |SNMP |sensor.temp.value[sensorReading.{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|System name has changed |<p>System name has changed. Ack to close.</p> |`last(/Supermicro Aten by SNMP/system.name,#1)<>last(/Supermicro Aten by SNMP/system.name,#2) and length(last(/Supermicro Aten by SNMP/system.name))>0` |INFO |<p>Manual close: YES</p> |
|Host has been restarted |<p>Uptime is less than 10 minutes.</p> |`(last(/Supermicro Aten by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Supermicro Aten by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Supermicro Aten by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Supermicro Aten by SNMP/system.net.uptime[sysUpTime.0])<10m)` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- No SNMP data collection</p> |
|No SNMP data collection |<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p> |`max(/Supermicro Aten by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|Unavailable by ICMP ping |<p>Last three attempts returned timeout.  Please check device connectivity.</p> |`max(/Supermicro Aten by SNMP/icmpping,#3)=0` |HIGH | |
|High ICMP ping loss |<p>-</p> |`min(/Supermicro Aten by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Supermicro Aten by SNMP/icmppingloss,5m)<100` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|High ICMP ping response time |<p>-</p> |`avg(/Supermicro Aten by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}` |WARNING |<p>**Depends on**:</p><p>- High ICMP ping loss</p><p>- Unavailable by ICMP ping</p> |
|{#SENSOR_DESCR}: Temperature is above warning threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Supermicro Aten by SNMP/sensor.temp.value[sensorReading.{#SNMPINDEX}],5m)>{$TEMP_WARN:"{#SENSOR_DESCR}"}`<p>Recovery expression:</p>`max(/Supermicro Aten by SNMP/sensor.temp.value[sensorReading.{#SNMPINDEX}],5m)<{$TEMP_WARN:"{#SENSOR_DESCR}"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_DESCR}: Temperature is above critical threshold</p> |
|{#SENSOR_DESCR}: Temperature is above critical threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Supermicro Aten by SNMP/sensor.temp.value[sensorReading.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"{#SENSOR_DESCR}"}`<p>Recovery expression:</p>`max(/Supermicro Aten by SNMP/sensor.temp.value[sensorReading.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"{#SENSOR_DESCR}"}-3` |HIGH | |
|{#SENSOR_DESCR}: Temperature is too low |<p>-</p> |`avg(/Supermicro Aten by SNMP/sensor.temp.value[sensorReading.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"{#SENSOR_DESCR}"}`<p>Recovery expression:</p>`min(/Supermicro Aten by SNMP/sensor.temp.value[sensorReading.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"{#SENSOR_DESCR}"}+3` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

