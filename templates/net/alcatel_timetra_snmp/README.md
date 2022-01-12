
# Alcatel Timetra TiMOS SNMP

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
|{$FAN_CRIT_STATUS} |<p>-</p> |`4` |
|{$MEMORY.UTIL.MAX} |<p>-</p> |`90` |
|{$PSU_CRIT_STATUS} |<p>-</p> |`4` |
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
|Temperature Discovery |<p>-</p> |SNMP |temperature.discovery<p>**Filter**:</p>AND_OR <p>- {#TEMP_SENSOR} MATCHES_REGEX `1`</p> |
|FAN Discovery |<p>-</p> |SNMP |fan.discovery<p>**Filter**:</p>AND_OR <p>- {#SNMPVALUE} MATCHES_REGEX `[^1]`</p> |
|PSU Discovery |<p>-</p> |SNMP |psu.discovery |
|Entity Serial Numbers Discovery |<p>-</p> |SNMP |entity_sn.discovery<p>**Filter**:</p>AND <p>- {#ENT_SN} MATCHES_REGEX `.+`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |CPU utilization |<p>MIB: TIMETRA-SYSTEM-MIB</p><p>The value of sgiCpuUsage indicates the current CPU utilization for the system.</p> |SNMP |system.cpu.util[sgiCpuUsage.0] |
|Fans |#{#SNMPINDEX}: Fan status |<p>MIB: TIMETRA-SYSTEM-MIB</p><p>Current status of the Fan tray.</p> |SNMP |sensor.fan.status[tmnxChassisFanOperStatus.{#SNMPINDEX}] |
|Inventory |Hardware model name |<p>MIB: SNMPv2-MIB</p> |SNMP |system.hw.model<p>**Preprocessing**:</p><p>- REGEX: `^(\w|-|\.|/)+ (\w|-|\.|/)+ (.+) Copyright \3`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Operating system |<p>MIB: SNMPv2-MIB</p> |SNMP |system.sw.os[sysDescr.0]<p>**Preprocessing**:</p><p>- REGEX: `^((\w|-|\.|/)+) \1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |{#ENT_NAME}: Hardware serial number |<p>MIB: TIMETRA-CHASSIS-MIB</p> |SNMP |system.hw.serialnumber[tmnxHwSerialNumber.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Memory |Used memory |<p>MIB: TIMETRA-SYSTEM-MIB</p><p>The value of sgiKbMemoryUsed indicates the total pre-allocated pool memory, in kilobytes, currently in use on the system.</p> |SNMP |vm.memory.used[sgiKbMemoryUsed.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Memory |Available memory |<p>MIB: TIMETRA-SYSTEM-MIB</p><p>The value of sgiKbMemoryAvailable indicates the amount of free memory, in kilobytes, in the overall system that is not allocated to memory pools, but is available in case a memory pool needs to grow.</p> |SNMP |vm.memory.available[sgiKbMemoryAvailable.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Memory |Total memory |<p>Total memory in Bytes</p> |CALCULATED |vm.memory.total[snmp]<p>**Expression**:</p>`last(//vm.memory.available[sgiKbMemoryAvailable.0])+last(//vm.memory.used[sgiKbMemoryUsed.0])` |
|Memory |Memory utilization |<p>Memory utilization in %</p> |CALCULATED |vm.memory.util[vm.memory.util.0]<p>**Expression**:</p>`last(//vm.memory.used[sgiKbMemoryUsed.0])/(last(//vm.memory.available[sgiKbMemoryAvailable.0])+last(//vm.memory.used[sgiKbMemoryUsed.0]))*100` |
|Power_supply |#{#SNMPINDEX}: Power supply status |<p>MIB: TIMETRA-SYSTEM-MIB</p><p>The overall status of an equipped power supply.</p><p>For AC multiple powersupplies, this represents the overall status of the first power supplyin the tray (or shelf).</p><p>For any other type, this represents the overall status of the power supply.</p><p>If tmnxChassisPowerSupply1Status is'deviceStateOk', then all monitored statuses are 'deviceStateOk'.</p><p>A value of 'deviceStateFailed' represents a condition where at least one monitored status is in a failed state.</p> |SNMP |sensor.psu.status[tmnxChassisPowerSupply1Status.{#SNMPINDEX}] |
|Power_supply |#{#SNMPINDEX}: Power supply status |<p>MIB: TIMETRA-SYSTEM-MIB</p><p>The overall status of an equipped power supply.</p><p>For AC multiple powersupplies, this represents the overall status of the second power supplyin the tray (or shelf).</p><p>For any other type, this field is unused and set to 'deviceNotEquipped'.</p><p>If tmnxChassisPowerSupply2Status is 'deviceStateOk', then all monitored statuses are 'deviceStateOk'.</p><p>A value of 'deviceStateFailed' represents a condition where at least one monitored status is in a failed state.</p> |SNMP |sensor.psu.status[tmnxChassisPowerSupply2Status.{#SNMPINDEX}] |
|Temperature |{#SNMPVALUE}: Temperature |<p>MIB: TIMETRA-SYSTEM-MIB</p><p>The current temperature reading in degrees celsius from this hardware component's temperature sensor.  If this component does not contain a temperature sensor, then the value -1 is returned.</p> |SNMP |sensor.temp.value[tmnxHwTemperature.{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|High CPU utilization (over {$CPU.UTIL.CRIT}% for 5m) |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/Alcatel Timetra TiMOS SNMP/system.cpu.util[sgiCpuUsage.0],5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|#{#SNMPINDEX}: Fan is in critical state |<p>Please check the fan unit</p> |`count(/Alcatel Timetra TiMOS SNMP/sensor.fan.status[tmnxChassisFanOperStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS}")=1` |AVERAGE | |
|Operating system description has changed |<p>Operating system description has changed. Possible reasons that system has been updated or replaced. Ack to close.</p> |`last(/Alcatel Timetra TiMOS SNMP/system.sw.os[sysDescr.0],#1)<>last(/Alcatel Timetra TiMOS SNMP/system.sw.os[sysDescr.0],#2) and length(last(/Alcatel Timetra TiMOS SNMP/system.sw.os[sysDescr.0]))>0` |INFO |<p>Manual close: YES</p> |
|{#ENT_NAME}: Device has been replaced (new serial number received) |<p>Device serial number has changed. Ack to close</p> |`last(/Alcatel Timetra TiMOS SNMP/system.hw.serialnumber[tmnxHwSerialNumber.{#SNMPINDEX}],#1)<>last(/Alcatel Timetra TiMOS SNMP/system.hw.serialnumber[tmnxHwSerialNumber.{#SNMPINDEX}],#2) and length(last(/Alcatel Timetra TiMOS SNMP/system.hw.serialnumber[tmnxHwSerialNumber.{#SNMPINDEX}]))>0` |INFO |<p>Manual close: YES</p> |
|High memory utilization (>{$MEMORY.UTIL.MAX}% for 5m) |<p>The system is running out of free memory.</p> |`min(/Alcatel Timetra TiMOS SNMP/vm.memory.util[vm.memory.util.0],5m)>{$MEMORY.UTIL.MAX}` |AVERAGE | |
|#{#SNMPINDEX}: Power supply is in critical state |<p>Please check the power supply unit for errors</p> |`count(/Alcatel Timetra TiMOS SNMP/sensor.psu.status[tmnxChassisPowerSupply1Status.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS}")=1` |AVERAGE | |
|#{#SNMPINDEX}: Power supply is in critical state |<p>Please check the power supply unit for errors</p> |`count(/Alcatel Timetra TiMOS SNMP/sensor.psu.status[tmnxChassisPowerSupply2Status.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS}")=1` |AVERAGE | |
|{#SNMPVALUE}: Temperature is above warning threshold: >{$TEMP_WARN:""} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Alcatel Timetra TiMOS SNMP/sensor.temp.value[tmnxHwTemperature.{#SNMPINDEX}],5m)>{$TEMP_WARN:""}`<p>Recovery expression:</p>`max(/Alcatel Timetra TiMOS SNMP/sensor.temp.value[tmnxHwTemperature.{#SNMPINDEX}],5m)<{$TEMP_WARN:""}-3` |WARNING |<p>**Depends on**:</p><p>- {#SNMPVALUE}: Temperature is above critical threshold: >{$TEMP_CRIT:""}</p> |
|{#SNMPVALUE}: Temperature is above critical threshold: >{$TEMP_CRIT:""} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Alcatel Timetra TiMOS SNMP/sensor.temp.value[tmnxHwTemperature.{#SNMPINDEX}],5m)>{$TEMP_CRIT:""}`<p>Recovery expression:</p>`max(/Alcatel Timetra TiMOS SNMP/sensor.temp.value[tmnxHwTemperature.{#SNMPINDEX}],5m)<{$TEMP_CRIT:""}-3` |HIGH | |
|{#SNMPVALUE}: Temperature is too low: <{$TEMP_CRIT_LOW:""} |<p>-</p> |`avg(/Alcatel Timetra TiMOS SNMP/sensor.temp.value[tmnxHwTemperature.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:""}`<p>Recovery expression:</p>`min(/Alcatel Timetra TiMOS SNMP/sensor.temp.value[tmnxHwTemperature.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:""}+3` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

