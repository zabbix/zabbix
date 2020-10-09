
# HP Enterprise Switch SNMP

## Overview

For Zabbix version: 5.2 and higher  

This template was tested on:

- HP ProCurve J4900B Switch 2626, version ProCurve J4900B Switch 2626, revision H.10.31, ROM H.08.02 (/sw/code/build/fish(mkfs))
- HP J9728A 2920-48G Switch, version HP J9728A 2920-48G Switch, revision WB.16.03.0003, ROM WB.16.03 (/ws/swbuildm/rel_tacoma_qaoff/code/build/anm(swbuildm_rel_tacoma_qaoff_rel_tacoma)) (Formerly ProCurve)"

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>-</p> |`90` |
|{$FAN_CRIT_STATUS:"bad"} |<p>-</p> |`2` |
|{$FAN_WARN_STATUS:"warning"} |<p>-</p> |`3` |
|{$MEMORY.UTIL.MAX} |<p>-</p> |`90` |
|{$PSU_CRIT_STATUS:"bad"} |<p>-</p> |`2` |
|{$PSU_WARN_STATUS:"warning"} |<p>-</p> |`3` |
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT} |<p>-</p> |`60` |
|{$TEMP_WARN} |<p>-</p> |`50` |

## Template links

|Name|
|----|
|EtherLike-MIB SNMP |
|Generic SNMP |
|Interfaces SNMP |

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Temperature Discovery |<p>ENTITY-SENSORS-MIB::EntitySensorDataType discovery with celsius filter</p> |SNMP |temp.precision0.discovery<p>**Filter**:</p>AND <p>- B: {#SENSOR_TYPE} MATCHES_REGEX `8`</p><p>- B: {#SENSOR_PRECISION} MATCHES_REGEX `0`</p> |
|Memory Discovery |<p>Discovery of NETSWITCH-MIB::hpLocalMemTable, A table that contains information on all the local memory for each slot.</p> |SNMP |memory.discovery |
|FAN Discovery |<p>Discovering all entities of hpicfSensorObjectId that ends with: 11.2.3.7.8.3.2 - fans and are present</p> |SNMP |fan.discovery<p>**Filter**:</p>AND <p>- A: {#ENT_CLASS} MATCHES_REGEX `.+8.3.2$`</p><p>- A: {#ENT_STATUS} MATCHES_REGEX `(1|2|3|4)`</p> |
|PSU Discovery |<p>Discovering all entities of hpicfSensorObjectId that ends with: 11.2.3.7.8.3.1 - power supplies and are present</p> |SNMP |psu.discovery<p>**Filter**:</p>AND <p>- A: {#ENT_CLASS} MATCHES_REGEX `.+8.3.1$`</p><p>- A: {#ENT_STATUS} MATCHES_REGEX `(1|2|3|4)`</p> |
|Temp Status Discovery |<p>Discovering all entities of hpicfSensorObjectId that ends with: 11.2.3.7.8.3.3 - over temp status and are present</p> |SNMP |temp.status.discovery<p>**Filter**:</p>AND <p>- A: {#ENT_CLASS} MATCHES_REGEX `.+8.3.3$`</p><p>- A: {#ENT_STATUS} MATCHES_REGEX `(1|2|3|4)`</p> |
|Entity Discovery |<p>-</p> |SNMP |entity.discovery<p>**Filter**:</p>AND_OR <p>- A: {#ENT_CLASS} MATCHES_REGEX `3`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |CPU utilization |<p>MIB: STATISTICS-MIB</p><p>The CPU utilization in percent(%).</p><p>Reference: http://h20564.www2.hpe.com/hpsc/doc/public/display?docId=emr_na-c02597344&sp4ts.oid=51079</p> |SNMP |system.cpu.util[hpSwitchCpuStat.0] |
|Fans |{#ENT_DESCR}: Fan status |<p>MIB: HP-ICF-CHASSIS</p><p>Actual status indicated by the sensor: {#ENT_DESCR}</p> |SNMP |sensor.fan.status[hpicfSensorStatus.{#SNMPINDEX}] |
|Inventory |Hardware serial number |<p>MIB: SEMI-MIB</p> |SNMP |system.hw.serialnumber<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Firmware version |<p>MIB: NETSWITCH-MIB</p><p>Contains the operating code version number (also known as software or firmware).</p><p>For example, a software version such as A.08.01 is described as follows:</p><p>A    the function set available in your router</p><p>08   the common release number</p><p>01   updates to the current common release</p> |SNMP |system.hw.firmware<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |{#ENT_NAME}: Hardware model name |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.model[entPhysicalDescr.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |{#ENT_NAME}: Hardware version(revision) |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.version[entPhysicalHardwareRev.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Memory |#{#SNMPVALUE}: Used memory |<p>MIB: NETSWITCH-MIB</p><p>The number of currently allocated bytes.</p> |SNMP |vm.memory.used[hpLocalMemAllocBytes.{#SNMPINDEX}] |
|Memory |#{#SNMPVALUE}: Available memory |<p>MIB: NETSWITCH-MIB</p><p>The number of available (unallocated) bytes.</p> |SNMP |vm.memory.available[hpLocalMemFreeBytes.{#SNMPINDEX}] |
|Memory |#{#SNMPVALUE}: Total memory |<p>MIB: NETSWITCH-MIB</p><p>The number of currently installed bytes.</p> |SNMP |vm.memory.total[hpLocalMemTotalBytes.{#SNMPINDEX}] |
|Memory |#{#SNMPVALUE}: Memory utilization |<p>Memory utilization in %</p> |CALCULATED |vm.memory.util[snmp.{#SNMPINDEX}]<p>**Expression**:</p>`last("vm.memory.used[hpLocalMemAllocBytes.{#SNMPINDEX}]")/last("vm.memory.total[hpLocalMemTotalBytes.{#SNMPINDEX}]")*100` |
|Power_supply |{#ENT_DESCR}: Power supply status |<p>MIB: HP-ICF-CHASSIS</p><p>Actual status indicated by the sensor: {#ENT_DESCR}</p> |SNMP |sensor.psu.status[hpicfSensorStatus.{#SNMPINDEX}] |
|Temperature |{#SENSOR_INFO}: Temperature |<p>MIB: ENTITY-SENSORS-MIB</p><p>The most recent measurement obtained by the agent for this sensor.</p><p>To correctly interpret the value of this object, the associated entPhySensorType,</p><p>entPhySensorScale, and entPhySensorPrecision objects must also be examined.</p> |SNMP |sensor.temp.value[entPhySensorValue.{#SNMPINDEX}] |
|Temperature |{#ENT_DESCR}: Temperature status |<p>MIB: HP-ICF-CHASSIS</p><p>Actual status indicated by the sensor: {#ENT_DESCR}</p> |SNMP |sensor.temp.status[hpicfSensorStatus.{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|High CPU utilization (over {$CPU.UTIL.CRIT}% for 5m) |<p>CPU utilization is too high. The system might be slow to respond.</p> |`{TEMPLATE_NAME:system.cpu.util[hpSwitchCpuStat.0].min(5m)}>{$CPU.UTIL.CRIT}` |WARNING | |
|{#ENT_DESCR}: Fan is in critical state |<p>Please check the fan unit</p> |`{TEMPLATE_NAME:sensor.fan.status[hpicfSensorStatus.{#SNMPINDEX}].count(#1,{$FAN_CRIT_STATUS:"bad"},eq)}=1` |AVERAGE | |
|{#ENT_DESCR}: Fan is in warning state |<p>Please check the fan unit</p> |`{TEMPLATE_NAME:sensor.fan.status[hpicfSensorStatus.{#SNMPINDEX}].count(#1,{$FAN_WARN_STATUS:"warning"},eq)}=1` |WARNING |<p>**Depends on**:</p><p>- {#ENT_DESCR}: Fan is in critical state</p> |
|Device has been replaced (new serial number received) |<p>Device serial number has changed. Ack to close</p> |`{TEMPLATE_NAME:system.hw.serialnumber.diff()}=1 and {TEMPLATE_NAME:system.hw.serialnumber.strlen()}>0` |INFO |<p>Manual close: YES</p> |
|Firmware has changed |<p>Firmware version has changed. Ack to close</p> |`{TEMPLATE_NAME:system.hw.firmware.diff()}=1 and {TEMPLATE_NAME:system.hw.firmware.strlen()}>0` |INFO |<p>Manual close: YES</p> |
|#{#SNMPVALUE}: High memory utilization ( >{$MEMORY.UTIL.MAX}% for 5m) |<p>The system is running out of free memory.</p> |`{TEMPLATE_NAME:vm.memory.util[snmp.{#SNMPINDEX}].min(5m)}>{$MEMORY.UTIL.MAX}` |AVERAGE | |
|{#ENT_DESCR}: Power supply is in critical state |<p>Please check the power supply unit for errors</p> |`{TEMPLATE_NAME:sensor.psu.status[hpicfSensorStatus.{#SNMPINDEX}].count(#1,{$PSU_CRIT_STATUS:"bad"},eq)}=1` |AVERAGE | |
|{#ENT_DESCR}: Power supply is in warning state |<p>Please check the power supply unit for errors</p> |`{TEMPLATE_NAME:sensor.psu.status[hpicfSensorStatus.{#SNMPINDEX}].count(#1,{$PSU_WARN_STATUS:"warning"},eq)}=1` |WARNING |<p>**Depends on**:</p><p>- {#ENT_DESCR}: Power supply is in critical state</p> |
|{#SENSOR_INFO}: Temperature is above warning threshold: >{$TEMP_WARN:""} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`{TEMPLATE_NAME:sensor.temp.value[entPhySensorValue.{#SNMPINDEX}].avg(5m)}>{$TEMP_WARN:""}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[entPhySensorValue.{#SNMPINDEX}].max(5m)}<{$TEMP_WARN:""}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_INFO}: Temperature is above critical threshold: >{$TEMP_CRIT:""}</p> |
|{#SENSOR_INFO}: Temperature is above critical threshold: >{$TEMP_CRIT:""} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`{TEMPLATE_NAME:sensor.temp.value[entPhySensorValue.{#SNMPINDEX}].avg(5m)}>{$TEMP_CRIT:""}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[entPhySensorValue.{#SNMPINDEX}].max(5m)}<{$TEMP_CRIT:""}-3` |HIGH | |
|{#SENSOR_INFO}: Temperature is too low: <{$TEMP_CRIT_LOW:""} |<p>-</p> |`{TEMPLATE_NAME:sensor.temp.value[entPhySensorValue.{#SNMPINDEX}].avg(5m)}<{$TEMP_CRIT_LOW:""}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[entPhySensorValue.{#SNMPINDEX}].min(5m)}>{$TEMP_CRIT_LOW:""}+3` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

