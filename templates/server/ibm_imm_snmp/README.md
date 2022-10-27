
# IBM IMM by SNMP

## Overview

For Zabbix version: 6.2 and higher.
for IMM2 and IMM1 IBM serverX hardware

This template was tested on:

- IBM System x3550 M2 with IMM1
- IBM x3250M3 with IMM1
- IBM x3550M5 with IMM2
- System x3550 M3 with IMM1

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$DISK_OK_STATUS} |<p>-</p> |`Normal` |
|{$FAN_OK_STATUS} |<p>-</p> |`Normal` |
|{$HEALTH_CRIT_STATUS} |<p>-</p> |`2` |
|{$HEALTH_DISASTER_STATUS} |<p>-</p> |`0` |
|{$HEALTH_WARN_STATUS} |<p>-</p> |`4` |
|{$ICMP_LOSS_WARN} |<p>-</p> |`20` |
|{$ICMP_RESPONSE_TIME_WARN} |<p>-</p> |`0.15` |
|{$PSU_OK_STATUS} |<p>-</p> |`Normal` |
|{$SNMP.TIMEOUT} |<p>-</p> |`5m` |
|{$TEMP_CRIT:"Ambient"} |<p>-</p> |`35` |
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT} |<p>-</p> |`60` |
|{$TEMP_WARN:"Ambient"} |<p>-</p> |`30` |
|{$TEMP_WARN} |<p>-</p> |`50` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|FAN Discovery |<p>IMM-MIB::fanDescr</p> |SNMP |fan.discovery |
|Physical Disk Discovery |<p>-</p> |SNMP |physicalDisk.discovery |
|PSU Discovery |<p>IMM-MIB::powerFruName</p> |SNMP |psu.discovery |
|Temperature Discovery |<p>Scanning IMM-MIB::tempTable</p> |SNMP |tempDescr.discovery<p>**Filter**:</p>AND_OR <p>- {#SNMPVALUE} MATCHES_REGEX `(DIMM|PSU|PCH|RAID|RR|PCI).*`</p> |
|Temperature Discovery Ambient |<p>Scanning IMM-MIB::tempTable with Ambient filter</p> |SNMP |tempDescr.discovery.ambient<p>**Filter**:</p>AND_OR <p>- {#SNMPVALUE} MATCHES_REGEX `Ambient.*`</p> |
|Temperature Discovery CPU |<p>Scanning IMM-MIB::tempTable with CPU filter</p> |SNMP |tempDescr.discovery.cpu<p>**Filter**:</p>AND_OR <p>- {#SNMPVALUE} MATCHES_REGEX `CPU [0-9]* Temp`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Fans |{#FAN_DESCR}: Fan status |<p>MIB: IMM-MIB</p><p>A description of the fan component status.</p> |SNMP |sensor.fan.status[fanHealthStatus.{#SNMPINDEX}] |
|Fans |{#FAN_DESCR}: Fan speed, % |<p>MIB: IMM-MIB</p><p>Fan speed expressed in percent(%) of maximum RPM.</p><p>An octet string expressed as 'ddd% of maximum' where:d is a decimal digit or blank space for a leading zero.</p><p>If the fan is determined not to be running or the fan speed cannot be determined, the string will indicate 'Offline'.</p> |SNMP |sensor.fan.speed.percentage[fanSpeed.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- REGEX: `(\d{1,3}) *%( of maximum)? \1`</p> |
|General |SNMP traps (fallback) |<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p> |SNMP_TRAP |snmptrap.fallback |
|General |System location |<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet, 3rd floor').  If the location is unknown, the value is the zero-length string.</p> |SNMP |system.location[sysLocation.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System contact details |<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</p> |SNMP |system.contact[sysContact.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System object ID |<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity.  This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p> |SNMP |system.objectid[sysObjectID.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System name |<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name.  If the name is unknown, the value is the zero-length string.</p> |SNMP |system.name<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System description |<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p> |SNMP |system.descr[sysDescr.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|Inventory |Hardware model name |<p>MIB: IMM-MIB</p> |SNMP |system.hw.model<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Hardware serial number |<p>MIB: IMM-MIB</p><p>Machine serial number VPD information</p> |SNMP |system.hw.serialnumber<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Physical disks |{#SNMPINDEX}: Physical disk status |<p>MIB: IMM-MIB</p> |SNMP |system.hw.physicaldisk.status[diskHealthStatus.{#SNMPINDEX}] |
|Physical disks |{#SNMPINDEX}: Physical disk part number |<p>MIB: IMM-MIB</p><p>disk module FRU name.</p> |SNMP |system.hw.physicaldisk.part_number[diskFruName.{#SNMPINDEX}] |
|Power supply |{#PSU_DESCR}: Power supply status |<p>MIB: IMM-MIB</p><p>A description of the power module status.</p> |SNMP |sensor.psu.status[powerHealthStatus.{#SNMPINDEX}] |
|Status |Overall system health status |<p>MIB: IMM-MIB</p><p>Indicates status of system health for the system in which the IMM resides. Value of 'nonRecoverable' indicates a severe error has occurred and the system may not be functioning. A value of 'critical' indicates that a error has occurred but the system is currently functioning properly. A value of 'nonCritical' indicates that a condition has occurred that may change the state of the system in the future but currently the system is working properly. A value of 'normal' indicates that the system is operating normally.</p> |SNMP |system.status[systemHealthStat.0] |
|Status |Uptime (network) |<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p> |SNMP |system.net.uptime[sysUpTime.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Status |Uptime (hardware) |<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p> |SNMP |system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- MULTIPLIER: `0.01`</p> |
|Status |SNMP agent availability |<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible value:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p> |INTERNAL |zabbix[host,snmp,available] |
|Status |ICMP ping |<p>-</p> |SIMPLE |icmpping |
|Status |ICMP loss |<p>-</p> |SIMPLE |icmppingloss |
|Status |ICMP response time |<p>-</p> |SIMPLE |icmppingsec |
|Temperature |{#SNMPVALUE}: Temperature |<p>MIB: IMM-MIB</p><p>Temperature readings of testpoint: {#SNMPVALUE}</p> |SNMP |sensor.temp.value[tempReading.{#SNMPINDEX}] |
|Temperature |Ambient: Temperature |<p>MIB: IMM-MIB</p><p>Temperature readings of testpoint: Ambient</p> |SNMP |sensor.temp.value[tempReading.Ambient.{#SNMPINDEX}] |
|Temperature |CPU: Temperature |<p>MIB: IMM-MIB</p><p>Temperature readings of testpoint: CPU</p> |SNMP |sensor.temp.value[tempReading.CPU.{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#FAN_DESCR}: Fan is not in normal state |<p>Please check the fan unit</p> |`count(/IBM IMM by SNMP/sensor.fan.status[fanHealthStatus.{#SNMPINDEX}],#1,"ne","{$FAN_OK_STATUS}")=1` |INFO | |
|System name has changed |<p>System name has changed. Ack to close.</p> |`last(/IBM IMM by SNMP/system.name,#1)<>last(/IBM IMM by SNMP/system.name,#2) and length(last(/IBM IMM by SNMP/system.name))>0` |INFO |<p>Manual close: YES</p> |
|Device has been replaced |<p>Device serial number has changed. Ack to close</p> |`last(/IBM IMM by SNMP/system.hw.serialnumber,#1)<>last(/IBM IMM by SNMP/system.hw.serialnumber,#2) and length(last(/IBM IMM by SNMP/system.hw.serialnumber))>0` |INFO |<p>Manual close: YES</p> |
|{#SNMPINDEX}: Physical disk is not in OK state |<p>Please check physical disk for warnings or errors</p> |`count(/IBM IMM by SNMP/system.hw.physicaldisk.status[diskHealthStatus.{#SNMPINDEX}],#1,"ne","{$DISK_OK_STATUS}")=1` |WARNING | |
|{#PSU_DESCR}: Power supply is not in normal state |<p>Please check the power supply unit for errors</p> |`count(/IBM IMM by SNMP/sensor.psu.status[powerHealthStatus.{#SNMPINDEX}],#1,"ne","{$PSU_OK_STATUS}")=1` |INFO | |
|System is in unrecoverable state! |<p>Please check the device for faults</p> |`count(/IBM IMM by SNMP/system.status[systemHealthStat.0],#1,"eq","{$HEALTH_DISASTER_STATUS}")=1` |HIGH | |
|System status is in critical state |<p>Please check the device for errors</p> |`count(/IBM IMM by SNMP/system.status[systemHealthStat.0],#1,"eq","{$HEALTH_CRIT_STATUS}")=1` |HIGH |<p>**Depends on**:</p><p>- System is in unrecoverable state!</p> |
|System status is in warning state |<p>Please check the device for warnings</p> |`count(/IBM IMM by SNMP/system.status[systemHealthStat.0],#1,"eq","{$HEALTH_WARN_STATUS}")=1` |WARNING |<p>**Depends on**:</p><p>- System is in unrecoverable state!</p><p>- System status is in critical state</p> |
|Host has been restarted |<p>Uptime is less than 10 minutes.</p> |`(last(/IBM IMM by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/IBM IMM by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/IBM IMM by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/IBM IMM by SNMP/system.net.uptime[sysUpTime.0])<10m)` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- No SNMP data collection</p> |
|No SNMP data collection |<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p> |`max(/IBM IMM by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|Unavailable by ICMP ping |<p>Last three attempts returned timeout.  Please check device connectivity.</p> |`max(/IBM IMM by SNMP/icmpping,#3)=0` |HIGH | |
|High ICMP ping loss |<p>-</p> |`min(/IBM IMM by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/IBM IMM by SNMP/icmppingloss,5m)<100` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|High ICMP ping response time |<p>-</p> |`avg(/IBM IMM by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}` |WARNING |<p>**Depends on**:</p><p>- High ICMP ping loss</p><p>- Unavailable by ICMP ping</p> |
|{#SNMPVALUE}: Temperature is above warning threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/IBM IMM by SNMP/sensor.temp.value[tempReading.{#SNMPINDEX}],5m)>{$TEMP_WARN:"{#SNMPVALUE}"}`<p>Recovery expression:</p>`max(/IBM IMM by SNMP/sensor.temp.value[tempReading.{#SNMPINDEX}],5m)<{$TEMP_WARN:"{#SNMPVALUE}"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SNMPVALUE}: Temperature is above critical threshold</p> |
|{#SNMPVALUE}: Temperature is above critical threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/IBM IMM by SNMP/sensor.temp.value[tempReading.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"{#SNMPVALUE}"}`<p>Recovery expression:</p>`max(/IBM IMM by SNMP/sensor.temp.value[tempReading.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"{#SNMPVALUE}"}-3` |HIGH | |
|{#SNMPVALUE}: Temperature is too low |<p>-</p> |`avg(/IBM IMM by SNMP/sensor.temp.value[tempReading.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"{#SNMPVALUE}"}`<p>Recovery expression:</p>`min(/IBM IMM by SNMP/sensor.temp.value[tempReading.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"{#SNMPVALUE}"}+3` |AVERAGE | |
|Ambient: Temperature is above warning threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/IBM IMM by SNMP/sensor.temp.value[tempReading.Ambient.{#SNMPINDEX}],5m)>{$TEMP_WARN:"Ambient"}`<p>Recovery expression:</p>`max(/IBM IMM by SNMP/sensor.temp.value[tempReading.Ambient.{#SNMPINDEX}],5m)<{$TEMP_WARN:"Ambient"}-3` |WARNING |<p>**Depends on**:</p><p>- Ambient: Temperature is above critical threshold</p> |
|Ambient: Temperature is above critical threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/IBM IMM by SNMP/sensor.temp.value[tempReading.Ambient.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"Ambient"}`<p>Recovery expression:</p>`max(/IBM IMM by SNMP/sensor.temp.value[tempReading.Ambient.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"Ambient"}-3` |HIGH | |
|Ambient: Temperature is too low |<p>-</p> |`avg(/IBM IMM by SNMP/sensor.temp.value[tempReading.Ambient.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"Ambient"}`<p>Recovery expression:</p>`min(/IBM IMM by SNMP/sensor.temp.value[tempReading.Ambient.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"Ambient"}+3` |AVERAGE | |
|CPU: Temperature is above warning threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/IBM IMM by SNMP/sensor.temp.value[tempReading.CPU.{#SNMPINDEX}],5m)>{$TEMP_WARN:"CPU"}`<p>Recovery expression:</p>`max(/IBM IMM by SNMP/sensor.temp.value[tempReading.CPU.{#SNMPINDEX}],5m)<{$TEMP_WARN:"CPU"}-3` |WARNING |<p>**Depends on**:</p><p>- CPU: Temperature is above critical threshold</p> |
|CPU: Temperature is above critical threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/IBM IMM by SNMP/sensor.temp.value[tempReading.CPU.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"CPU"}`<p>Recovery expression:</p>`max(/IBM IMM by SNMP/sensor.temp.value[tempReading.CPU.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"CPU"}-3` |HIGH | |
|CPU: Temperature is too low |<p>-</p> |`avg(/IBM IMM by SNMP/sensor.temp.value[tempReading.CPU.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"CPU"}`<p>Recovery expression:</p>`min(/IBM IMM by SNMP/sensor.temp.value[tempReading.CPU.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"CPU"}+3` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

## Known Issues

- Description: Some IMMs (IMM1) do not return disks
  - Version: IMM1
  - Device: IBM x3250M3

- Description: Some IMMs (IMM1) do not return fan status: fanHealthStatus
  - Version: IMM1
  - Device: IBM x3250M3

- Description: IMM1 servers (M2, M3 generations) sysObjectID is NET-SNMP-MIB::netSnmpAgentOIDs.10
  - Version: IMM1
  - Device: IMM1 servers (M2,M3 generations)

- Description: IMM1 servers (M2, M3 generations) only Ambient temperature sensor available
  - Version: IMM1
  - Device: IMM1 servers (M2,M3 generations)

