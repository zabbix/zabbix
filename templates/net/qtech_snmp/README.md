
# QTech QSW SNMP

## Overview

For Zabbix version: 6.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>-</p> |`90` |
|{$FAN_CRIT_STATUS} |<p>-</p> |`1` |
|{$MEMORY.UTIL.MAX} |<p>-</p> |`90` |
|{$PSU_CRIT_STATUS} |<p>-</p> |`1` |
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT} |<p>-</p> |`75` |
|{$TEMP_WARN} |<p>-</p> |`65` |

## Template links

|Name|
|----|
|EtherLike-MIB SNMP |
|Generic SNMP |
|Interfaces SNMP |

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|PSU Discovery |<p>-</p> |SNMP |psu.discovery |
|FAN Discovery |<p>-</p> |SNMP |fan.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |CPU utilization |<p>MIB: QTECH-MIB</p><p>CPU utilization in %</p> |SNMP |system.cpu.util[switchCpuUsage.0] |
|Fans |{#SNMPINDEX}: Fan status |<p>MIB: QTECH-MIB</p> |SNMP |sensor.fan.status[sysFanStatus.{#SNMPINDEX}] |
|Inventory |Hardware model name |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.model<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Hardware serial number |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.serialnumber<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Firmware version |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.firmware<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Hardware version(revision) |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.version<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Operating system |<p>MIB: QTECH-MIB</p> |SNMP |system.sw.os[sysSoftwareVersion.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Memory |Used memory |<p>MIB: QTECH-MIB</p><p>Used memory in Bytes</p> |SNMP |vm.memory.used[switchMemoryBusy.0] |
|Memory |Total memory |<p>MIB: QTECH-MIB</p><p>Total memory in Bytes</p> |SNMP |vm.memory.total[switchMemorySize.0] |
|Memory |Memory utilization |<p>Memory utilization in %</p> |CALCULATED |vm.memory.util[vm.memory.util.0]<p>**Expression**:</p>`last(//vm.memory.used[switchMemoryBusy.0])/last(//vm.memory.total[switchMemorySize.0])*100` |
|Power_supply |{#SNMPINDEX}: Power supply status |<p>MIB: QTECH-MIB</p> |SNMP |sensor.psu.status[sysPowerStatus.{#SNMPINDEX}] |
|Temperature |Temperature |<p>MIB: QTECH-MIB</p><p>Temperature readings of testpoint: __RESOURCE__</p> |SNMP |sensor.temp.value[switchTemperature.0] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|High CPU utilization (over {$CPU.UTIL.CRIT}% for 5m) |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/QTech QSW SNMP/system.cpu.util[switchCpuUsage.0],5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|{#SNMPINDEX}: Fan is in critical state |<p>Please check the fan unit</p> |`count(/QTech QSW SNMP/sensor.fan.status[sysFanStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS}")=1` |AVERAGE | |
|Device has been replaced (new serial number received) |<p>Device serial number has changed. Ack to close</p> |`last(/QTech QSW SNMP/system.hw.serialnumber,#1)<>last(/QTech QSW SNMP/system.hw.serialnumber,#2) and length(last(/QTech QSW SNMP/system.hw.serialnumber))>0` |INFO |<p>Manual close: YES</p> |
|Firmware has changed |<p>Firmware version has changed. Ack to close</p> |`last(/QTech QSW SNMP/system.hw.firmware,#1)<>last(/QTech QSW SNMP/system.hw.firmware,#2) and length(last(/QTech QSW SNMP/system.hw.firmware))>0` |INFO |<p>Manual close: YES</p> |
|Operating system description has changed |<p>Operating system description has changed. Possible reasons that system has been updated or replaced. Ack to close.</p> |`last(/QTech QSW SNMP/system.sw.os[sysSoftwareVersion.0],#1)<>last(/QTech QSW SNMP/system.sw.os[sysSoftwareVersion.0],#2) and length(last(/QTech QSW SNMP/system.sw.os[sysSoftwareVersion.0]))>0` |INFO |<p>Manual close: YES</p> |
|High memory utilization (>{$MEMORY.UTIL.MAX}% for 5m) |<p>The system is running out of free memory.</p> |`min(/QTech QSW SNMP/vm.memory.util[vm.memory.util.0],5m)>{$MEMORY.UTIL.MAX}` |AVERAGE | |
|{#SNMPINDEX}: Power supply is in critical state |<p>Please check the power supply unit for errors</p> |`count(/QTech QSW SNMP/sensor.psu.status[sysPowerStatus.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS}")=1` |AVERAGE | |
|Temperature is above warning threshold: >{$TEMP_WARN:""} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/QTech QSW SNMP/sensor.temp.value[switchTemperature.0],5m)>{$TEMP_WARN:""}`<p>Recovery expression:</p>`max(/QTech QSW SNMP/sensor.temp.value[switchTemperature.0],5m)<{$TEMP_WARN:""}-3` |WARNING |<p>**Depends on**:</p><p>- Temperature is above critical threshold: >{$TEMP_CRIT:""}</p> |
|Temperature is above critical threshold: >{$TEMP_CRIT:""} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/QTech QSW SNMP/sensor.temp.value[switchTemperature.0],5m)>{$TEMP_CRIT:""}`<p>Recovery expression:</p>`max(/QTech QSW SNMP/sensor.temp.value[switchTemperature.0],5m)<{$TEMP_CRIT:""}-3` |HIGH | |
|Temperature is too low: <{$TEMP_CRIT_LOW:""} |<p>-</p> |`avg(/QTech QSW SNMP/sensor.temp.value[switchTemperature.0],5m)<{$TEMP_CRIT_LOW:""}`<p>Recovery expression:</p>`min(/QTech QSW SNMP/sensor.temp.value[switchTemperature.0],5m)>{$TEMP_CRIT_LOW:""}+3` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

