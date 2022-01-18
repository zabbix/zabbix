
# IBM IMM SNMP

## Overview

For Zabbix version: 6.0 and higher  
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
|{$PSU_OK_STATUS} |<p>-</p> |`Normal` |
|{$TEMP_CRIT:"Ambient"} |<p>-</p> |`35` |
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT} |<p>-</p> |`60` |
|{$TEMP_WARN:"Ambient"} |<p>-</p> |`30` |
|{$TEMP_WARN} |<p>-</p> |`50` |

## Template links

|Name|
|----|
|Generic SNMP |

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Temperature Discovery |<p>Scanning IMM-MIB::tempTable</p> |SNMP |tempDescr.discovery<p>**Filter**:</p>AND_OR <p>- {#SNMPVALUE} MATCHES_REGEX `(DIMM|PSU|PCH|RAID|RR|PCI).*`</p> |
|Temperature Discovery Ambient |<p>Scanning IMM-MIB::tempTable with Ambient filter</p> |SNMP |tempDescr.discovery.ambient<p>**Filter**:</p>AND_OR <p>- {#SNMPVALUE} MATCHES_REGEX `Ambient.*`</p> |
|Temperature Discovery CPU |<p>Scanning IMM-MIB::tempTable with CPU filter</p> |SNMP |tempDescr.discovery.cpu<p>**Filter**:</p>AND_OR <p>- {#SNMPVALUE} MATCHES_REGEX `CPU [0-9]* Temp`</p> |
|PSU Discovery |<p>IMM-MIB::powerFruName</p> |SNMP |psu.discovery |
|FAN Discovery |<p>IMM-MIB::fanDescr</p> |SNMP |fan.discovery |
|Physical Disk Discovery |<p>-</p> |SNMP |physicalDisk.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Fans |{#FAN_DESCR}: Fan status |<p>MIB: IMM-MIB</p><p>A description of the fan component status.</p> |SNMP |sensor.fan.status[fanHealthStatus.{#SNMPINDEX}] |
|Fans |{#FAN_DESCR}: Fan speed, % |<p>MIB: IMM-MIB</p><p>Fan speed expressed in percent(%) of maximum RPM.</p><p>An octet string expressed as 'ddd% of maximum' where:d is a decimal digit or blank space for a leading zero.</p><p>If the fan is determined not to be running or the fan speed cannot be determined, the string will indicate 'Offline'.</p> |SNMP |sensor.fan.speed.percentage[fanSpeed.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- REGEX: `(\d{1,3}) *%( of maximum)? \1`</p> |
|Inventory |Hardware model name |<p>MIB: IMM-MIB</p> |SNMP |system.hw.model<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Hardware serial number |<p>MIB: IMM-MIB</p><p>Machine serial number VPD information</p> |SNMP |system.hw.serialnumber<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Physical_disks |{#SNMPINDEX}: Physical disk status |<p>MIB: IMM-MIB</p> |SNMP |system.hw.physicaldisk.status[diskHealthStatus.{#SNMPINDEX}] |
|Physical_disks |{#SNMPINDEX}: Physical disk part number |<p>MIB: IMM-MIB</p><p>disk module FRU name.</p> |SNMP |system.hw.physicaldisk.part_number[diskFruName.{#SNMPINDEX}] |
|Power_supply |{#PSU_DESCR}: Power supply status |<p>MIB: IMM-MIB</p><p>A description of the power module status.</p> |SNMP |sensor.psu.status[powerHealthStatus.{#SNMPINDEX}] |
|Status |Overall system health status |<p>MIB: IMM-MIB</p><p>Indicates status of system health for the system in which the IMM resides. Value of 'nonRecoverable' indicates a severe error has occurred and the system may not be functioning. A value of 'critical' indicates that a error has occurred but the system is currently functioning properly. A value of 'nonCritical' indicates that a condition has occurred that may change the state of the system in the future but currently the system is working properly. A value of 'normal' indicates that the system is operating normally.</p> |SNMP |system.status[systemHealthStat.0] |
|Temperature |{#SNMPVALUE}: Temperature |<p>MIB: IMM-MIB</p><p>Temperature readings of testpoint: {#SNMPVALUE}</p> |SNMP |sensor.temp.value[tempReading.{#SNMPINDEX}] |
|Temperature |Ambient: Temperature |<p>MIB: IMM-MIB</p><p>Temperature readings of testpoint: Ambient</p> |SNMP |sensor.temp.value[tempReading.Ambient.{#SNMPINDEX}] |
|Temperature |CPU: Temperature |<p>MIB: IMM-MIB</p><p>Temperature readings of testpoint: CPU</p> |SNMP |sensor.temp.value[tempReading.CPU.{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#FAN_DESCR}: Fan is not in normal state |<p>Please check the fan unit</p> |`count(/IBM IMM SNMP/sensor.fan.status[fanHealthStatus.{#SNMPINDEX}],#1,"ne","{$FAN_OK_STATUS}")=1` |INFO | |
|Device has been replaced (new serial number received) |<p>Device serial number has changed. Ack to close</p> |`last(/IBM IMM SNMP/system.hw.serialnumber,#1)<>last(/IBM IMM SNMP/system.hw.serialnumber,#2) and length(last(/IBM IMM SNMP/system.hw.serialnumber))>0` |INFO |<p>Manual close: YES</p> |
|{#SNMPINDEX}: Physical disk is not in OK state |<p>Please check physical disk for warnings or errors</p> |`count(/IBM IMM SNMP/system.hw.physicaldisk.status[diskHealthStatus.{#SNMPINDEX}],#1,"ne","{$DISK_OK_STATUS}")=1` |WARNING | |
|{#PSU_DESCR}: Power supply is not in normal state |<p>Please check the power supply unit for errors</p> |`count(/IBM IMM SNMP/sensor.psu.status[powerHealthStatus.{#SNMPINDEX}],#1,"ne","{$PSU_OK_STATUS}")=1` |INFO | |
|System is in unrecoverable state! |<p>Please check the device for faults</p> |`count(/IBM IMM SNMP/system.status[systemHealthStat.0],#1,"eq","{$HEALTH_DISASTER_STATUS}")=1` |HIGH | |
|System status is in critical state |<p>Please check the device for errors</p> |`count(/IBM IMM SNMP/system.status[systemHealthStat.0],#1,"eq","{$HEALTH_CRIT_STATUS}")=1` |HIGH |<p>**Depends on**:</p><p>- System is in unrecoverable state!</p> |
|System status is in warning state |<p>Please check the device for warnings</p> |`count(/IBM IMM SNMP/system.status[systemHealthStat.0],#1,"eq","{$HEALTH_WARN_STATUS}")=1` |WARNING |<p>**Depends on**:</p><p>- System is in unrecoverable state!</p><p>- System status is in critical state</p> |
|{#SNMPVALUE}: Temperature is above warning threshold: >{$TEMP_WARN:""} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/IBM IMM SNMP/sensor.temp.value[tempReading.{#SNMPINDEX}],5m)>{$TEMP_WARN:""}`<p>Recovery expression:</p>`max(/IBM IMM SNMP/sensor.temp.value[tempReading.{#SNMPINDEX}],5m)<{$TEMP_WARN:""}-3` |WARNING |<p>**Depends on**:</p><p>- {#SNMPVALUE}: Temperature is above critical threshold: >{$TEMP_CRIT:""}</p> |
|{#SNMPVALUE}: Temperature is above critical threshold: >{$TEMP_CRIT:""} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/IBM IMM SNMP/sensor.temp.value[tempReading.{#SNMPINDEX}],5m)>{$TEMP_CRIT:""}`<p>Recovery expression:</p>`max(/IBM IMM SNMP/sensor.temp.value[tempReading.{#SNMPINDEX}],5m)<{$TEMP_CRIT:""}-3` |HIGH | |
|{#SNMPVALUE}: Temperature is too low: <{$TEMP_CRIT_LOW:""} |<p>-</p> |`avg(/IBM IMM SNMP/sensor.temp.value[tempReading.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:""}`<p>Recovery expression:</p>`min(/IBM IMM SNMP/sensor.temp.value[tempReading.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:""}+3` |AVERAGE | |
|Ambient: Temperature is above warning threshold: >{$TEMP_WARN:"Ambient"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/IBM IMM SNMP/sensor.temp.value[tempReading.Ambient.{#SNMPINDEX}],5m)>{$TEMP_WARN:"Ambient"}`<p>Recovery expression:</p>`max(/IBM IMM SNMP/sensor.temp.value[tempReading.Ambient.{#SNMPINDEX}],5m)<{$TEMP_WARN:"Ambient"}-3` |WARNING |<p>**Depends on**:</p><p>- Ambient: Temperature is above critical threshold: >{$TEMP_CRIT:"Ambient"}</p> |
|Ambient: Temperature is above critical threshold: >{$TEMP_CRIT:"Ambient"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/IBM IMM SNMP/sensor.temp.value[tempReading.Ambient.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"Ambient"}`<p>Recovery expression:</p>`max(/IBM IMM SNMP/sensor.temp.value[tempReading.Ambient.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"Ambient"}-3` |HIGH | |
|Ambient: Temperature is too low: <{$TEMP_CRIT_LOW:"Ambient"} |<p>-</p> |`avg(/IBM IMM SNMP/sensor.temp.value[tempReading.Ambient.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"Ambient"}`<p>Recovery expression:</p>`min(/IBM IMM SNMP/sensor.temp.value[tempReading.Ambient.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"Ambient"}+3` |AVERAGE | |
|CPU: Temperature is above warning threshold: >{$TEMP_WARN:"CPU"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/IBM IMM SNMP/sensor.temp.value[tempReading.CPU.{#SNMPINDEX}],5m)>{$TEMP_WARN:"CPU"}`<p>Recovery expression:</p>`max(/IBM IMM SNMP/sensor.temp.value[tempReading.CPU.{#SNMPINDEX}],5m)<{$TEMP_WARN:"CPU"}-3` |WARNING |<p>**Depends on**:</p><p>- CPU: Temperature is above critical threshold: >{$TEMP_CRIT:"CPU"}</p> |
|CPU: Temperature is above critical threshold: >{$TEMP_CRIT:"CPU"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/IBM IMM SNMP/sensor.temp.value[tempReading.CPU.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"CPU"}`<p>Recovery expression:</p>`max(/IBM IMM SNMP/sensor.temp.value[tempReading.CPU.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"CPU"}-3` |HIGH | |
|CPU: Temperature is too low: <{$TEMP_CRIT_LOW:"CPU"} |<p>-</p> |`avg(/IBM IMM SNMP/sensor.temp.value[tempReading.CPU.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"CPU"}`<p>Recovery expression:</p>`min(/IBM IMM SNMP/sensor.temp.value[tempReading.CPU.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"CPU"}+3` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

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

