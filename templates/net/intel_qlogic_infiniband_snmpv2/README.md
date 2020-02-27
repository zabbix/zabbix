
# Template Net Intel_Qlogic Infiniband SNMPv2

## Overview

For Zabbix version: 4.4  

## Setup


## Zabbix configuration


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$FAN_CRIT_STATUS}|<p>-</p>|`3`|
|{$PSU_CRIT_STATUS}|<p>-</p>|`3`|
|{$PSU_WARN_STATUS}|<p>-</p>|`4`|
|{$TEMP_CRIT_LOW}|<p>-</p>|`5`|
|{$TEMP_CRIT_STATUS}|<p>-</p>|`3`|
|{$TEMP_CRIT}|<p>-</p>|`60`|
|{$TEMP_WARN_STATUS}|<p>-</p>|`2`|
|{$TEMP_WARN}|<p>-</p>|`50`|

## Template links

|Name|
|----|
|Template Module Generic SNMPv2|
|Template Module Interfaces SNMPv2|

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Temperature Discovery|<p>Discovering sensor's table with temperature filter</p>|SNMP|temp.discovery<p>**Filter**:</p>AND <p>- B: {#SENSOR_TYPE} MATCHES_REGEX `2`</p>|
|Unit Discovery|<p>-</p>|SNMP|unit.discovery<p>**Filter**:</p>AND_OR <p>- A: {#ENT_CLASS} MATCHES_REGEX `2`</p>|
|PSU Discovery|<p>A textual description of the power supply, that can be assigned by the administrator.</p>|SNMP|psu.discovery|
|FAN Discovery|<p>icsChassisFanDescription of icsChassisFanTable</p>|SNMP|fan.discovery|

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Fans|{#SNMPVALUE}: Fan status|<p>MIB: ICS-CHASSIS-MIB</p><p>The operational status of the fan unit.</p>|SNMP|sensor.fan.status[icsChassisFanOperStatus.{#SNMPINDEX}]|
|Inventory|Hardware model name|<p>MIB: ICS-CHASSIS-MIB</p>|SNMP|system.hw.model<p>**Preprocessing**:</p><p>- REGEX: `(.+) - Firmware \1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p>|
|Inventory|Firmware version|<p>MIB: ICS-CHASSIS-MIB</p>|SNMP|system.hw.firmware<p>**Preprocessing**:</p><p>- REGEX: `Firmware Version: ([0-9.]+), \1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p>|
|Inventory|{#ENT_NAME}: Hardware serial number|<p>MIB: ICS-CHASSIS-MIB</p><p>The serial number of the FRU.  If not available, this value is a zero-length string.</p>|SNMP|system.hw.serialnumber[icsChassisSystemUnitFruSerialNumber.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p>|
|Power_supply|{#SNMPVALUE}: Power supply status|<p>MIB: ICS-CHASSIS-MIB</p><p>Actual status of the power supply:</p><p>(1) unknown: status not known.</p><p>(2) disabled: power supply is disabled.</p><p>(3) failed - power supply is unable to supply power due to failure.</p><p>(4) warning - power supply is supplying power, but an output or sensor is bad or warning.</p><p>(5) standby - power supply believed usable,but not supplying power.</p><p>(6) engaged - power supply is supplying power.</p><p>(7) redundant - power supply is supplying power, but not needed.</p><p>(8) notPresent - power supply is supplying power is not present.</p>|SNMP|sensor.psu.status[icsChassisPowerSupplyEntry.{#SNMPINDEX}]|
|Temperature|{#SENSOR_INFO}: Temperature|<p>MIB: ICS-CHASSIS-MIB</p><p>The current value read from the sensor.</p>|SNMP|sensor.temp.value[icsChassisSensorSlotValue.{#SNMPINDEX}]|
|Temperature|{#SENSOR_INFO}: Temperature status|<p>MIB: ICS-CHASSIS-MIB</p><p>The operational status of the sensor.</p>|SNMP|sensor.temp.status[icsChassisSensorSlotOperStatus.{#SNMPINDEX}]|

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#SNMPVALUE}: Fan is in critical state|<p>Please check the fan unit</p>|`{TEMPLATE_NAME:sensor.fan.status[icsChassisFanOperStatus.{#SNMPINDEX}].count(#1,{$FAN_CRIT_STATUS},eq)}=1`|AVERAGE||
|Firmware has changed|<p>Firmware version has changed. Ack to close</p>|`{TEMPLATE_NAME:system.hw.firmware.diff()}=1 and {TEMPLATE_NAME:system.hw.firmware.strlen()}>0`|INFO|<p>Manual close: YES</p>|
|{#ENT_NAME}: Device has been replaced (new serial number received)|<p>Device serial number has changed. Ack to close</p>|`{TEMPLATE_NAME:system.hw.serialnumber[icsChassisSystemUnitFruSerialNumber.{#SNMPINDEX}].diff()}=1 and {TEMPLATE_NAME:system.hw.serialnumber[icsChassisSystemUnitFruSerialNumber.{#SNMPINDEX}].strlen()}>0`|INFO|<p>Manual close: YES</p>|
|{#SNMPVALUE}: Power supply is in critical state|<p>Please check the power supply unit for errors</p>|`{TEMPLATE_NAME:sensor.psu.status[icsChassisPowerSupplyEntry.{#SNMPINDEX}].count(#1,{$PSU_CRIT_STATUS},eq)}=1`|AVERAGE||
|{#SNMPVALUE}: Power supply is in warning state|<p>Please check the power supply unit for errors</p>|`{TEMPLATE_NAME:sensor.psu.status[icsChassisPowerSupplyEntry.{#SNMPINDEX}].count(#1,{$PSU_WARN_STATUS},eq)}=1`|WARNING|<p>**Depends on**:</p><p>- {#SNMPVALUE}: Power supply is in critical state</p>|
|{#SENSOR_INFO}: Temperature is above warning threshold: >{$TEMP_WARN:""}|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`{TEMPLATE_NAME:sensor.temp.value[icsChassisSensorSlotValue.{#SNMPINDEX}].avg(5m)}>{$TEMP_WARN:""} or {Template Net Intel_Qlogic Infiniband SNMPv2:sensor.temp.status[icsChassisSensorSlotOperStatus.{#SNMPINDEX}].last(0)}={$TEMP_WARN_STATUS}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[icsChassisSensorSlotValue.{#SNMPINDEX}].max(5m)}<{$TEMP_WARN:""}-3`|WARNING|<p>**Depends on**:</p><p>- {#SENSOR_INFO}: Temperature is above critical threshold: >{$TEMP_CRIT:""}</p>|
|{#SENSOR_INFO}: Temperature is above critical threshold: >{$TEMP_CRIT:""}|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`{TEMPLATE_NAME:sensor.temp.value[icsChassisSensorSlotValue.{#SNMPINDEX}].avg(5m)}>{$TEMP_CRIT:""} or {Template Net Intel_Qlogic Infiniband SNMPv2:sensor.temp.status[icsChassisSensorSlotOperStatus.{#SNMPINDEX}].last(0)}={$TEMP_CRIT_STATUS}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[icsChassisSensorSlotValue.{#SNMPINDEX}].max(5m)}<{$TEMP_CRIT:""}-3`|HIGH||
|{#SENSOR_INFO}: Temperature is too low: <{$TEMP_CRIT_LOW:""}|<p>-</p>|`{TEMPLATE_NAME:sensor.temp.value[icsChassisSensorSlotValue.{#SNMPINDEX}].avg(5m)}<{$TEMP_CRIT_LOW:""}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[icsChassisSensorSlotValue.{#SNMPINDEX}].min(5m)}>{$TEMP_CRIT_LOW:""}+3`|AVERAGE||

## Feedback

Please report any issues with the template at https://support.zabbix.com

