
# Brocade FC SNMP

## Overview

For Zabbix version: 6.0 and higher  
https://community.brocade.com/dtscp75322/attachments/dtscp75322/fibre/25235/1/FOS_MIB_Reference_v740.pdf

This template was tested on:

- Brocade 6520, version v7.4.1c
- Brocade 300, version v7.0.0c
- Brocade BL 5480, version v6.3.1c

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>-</p> |`90` |
|{$FAN_CRIT_STATUS} |<p>-</p> |`2` |
|{$FAN_OK_STATUS} |<p>-</p> |`4` |
|{$HEALTH_CRIT_STATUS} |<p>-</p> |`4` |
|{$HEALTH_WARN_STATUS:"offline"} |<p>-</p> |`2` |
|{$HEALTH_WARN_STATUS:"testing"} |<p>-</p> |`3` |
|{$MEMORY.UTIL.MAX} |<p>-</p> |`90` |
|{$PSU_CRIT_STATUS} |<p>-</p> |`2` |
|{$PSU_OK_STATUS} |<p>-</p> |`4` |
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT} |<p>-</p> |`75` |
|{$TEMP_WARN_STATUS} |<p>-</p> |`5` |
|{$TEMP_WARN} |<p>-</p> |`65` |

## Template links

|Name|
|----|
|Generic SNMP |
|Interfaces SNMP |

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Temperature Discovery |<p>-</p> |SNMP |temperature.discovery<p>**Filter**:</p>AND_OR <p>- {#SENSOR_TYPE} MATCHES_REGEX `1`</p> |
|PSU Discovery |<p>-</p> |SNMP |psu.discovery<p>**Filter**:</p>AND_OR <p>- {#SENSOR_TYPE} MATCHES_REGEX `3`</p> |
|FAN Discovery |<p>-</p> |SNMP |fan.discovery<p>**Filter**:</p>AND_OR <p>- {#SENSOR_TYPE} MATCHES_REGEX `2`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |CPU utilization |<p>MIB: SW-MIB</p><p>System's CPU usage.</p> |SNMP |system.cpu.util[swCpuUsage.0] |
|Fans |{#SENSOR_INFO}: Fan status |<p>MIB: SW-MIB</p> |SNMP |sensor.fan.status[swSensorStatus.{#SNMPINDEX}] |
|Fans |{#SENSOR_INFO}: Fan speed |<p>MIB: SW-MIB</p><p>The current value (reading) of the sensor.</p><p>The value, -2147483648, represents an unknown quantity.</p><p>The fan value will be in RPM(revolution per minute)</p> |SNMP |sensor.fan.speed[swSensorValue.{#SNMPINDEX}] |
|Inventory |Hardware serial number |<p>MIB: SW-MIB</p> |SNMP |system.hw.serialnumber<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Firmware version |<p>MIB: SW-MIB</p> |SNMP |system.hw.firmware<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Memory |Memory utilization |<p>MIB: SW-MIB</p><p>Memory utilization in %</p> |SNMP |vm.memory.util[swMemUsage.0] |
|Power_supply |{#SENSOR_INFO}: Power supply status |<p>MIB: SW-MIB</p> |SNMP |sensor.psu.status[swSensorStatus.{#SNMPINDEX}] |
|Status |Overall system health status |<p>MIB: SW-MIB</p><p>The current operational status of the switch.The states are as follow:</p><p>online(1) means the switch is accessible by an external Fibre Channel port</p><p>offline(2) means the switch is not accessible</p><p>testing(3) means the switch is in a built-in test mode and is not accessible by an external Fibre Channel port</p><p>faulty(4) means the switch is not operational.</p> |SNMP |system.status[swOperStatus.0] |
|Temperature |{#SENSOR_INFO}: Temperature |<p>MIB: SW-MIB</p><p>Temperature readings of testpoint: {#SENSOR_INFO}</p> |SNMP |sensor.temp.value[swSensorValue.{#SNMPINDEX}] |
|Temperature |{#SENSOR_INFO}: Temperature status |<p>MIB: SW-MIB</p><p>Temperature status of testpoint: {#SENSOR_INFO}</p> |SNMP |sensor.temp.status[swSensorStatus.{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|High CPU utilization (over {$CPU.UTIL.CRIT}% for 5m) |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/Brocade FC SNMP/system.cpu.util[swCpuUsage.0],5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|{#SENSOR_INFO}: Fan is in critical state |<p>Please check the fan unit</p> |`count(/Brocade FC SNMP/sensor.fan.status[swSensorStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS}")=1` |AVERAGE | |
|{#SENSOR_INFO}: Fan is not in normal state |<p>Please check the fan unit</p> |`count(/Brocade FC SNMP/sensor.fan.status[swSensorStatus.{#SNMPINDEX}],#1,"ne","{$FAN_OK_STATUS}")=1` |INFO |<p>**Depends on**:</p><p>- {#SENSOR_INFO}: Fan is in critical state</p> |
|Device has been replaced (new serial number received) |<p>Device serial number has changed. Ack to close</p> |`last(/Brocade FC SNMP/system.hw.serialnumber,#1)<>last(/Brocade FC SNMP/system.hw.serialnumber,#2) and length(last(/Brocade FC SNMP/system.hw.serialnumber))>0` |INFO |<p>Manual close: YES</p> |
|Firmware has changed |<p>Firmware version has changed. Ack to close</p> |`last(/Brocade FC SNMP/system.hw.firmware,#1)<>last(/Brocade FC SNMP/system.hw.firmware,#2) and length(last(/Brocade FC SNMP/system.hw.firmware))>0` |INFO |<p>Manual close: YES</p> |
|High memory utilization (>{$MEMORY.UTIL.MAX}% for 5m) |<p>The system is running out of free memory.</p> |`min(/Brocade FC SNMP/vm.memory.util[swMemUsage.0],5m)>{$MEMORY.UTIL.MAX}` |AVERAGE | |
|{#SENSOR_INFO}: Power supply is in critical state |<p>Please check the power supply unit for errors</p> |`count(/Brocade FC SNMP/sensor.psu.status[swSensorStatus.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS}")=1` |AVERAGE | |
|{#SENSOR_INFO}: Power supply is not in normal state |<p>Please check the power supply unit for errors</p> |`count(/Brocade FC SNMP/sensor.psu.status[swSensorStatus.{#SNMPINDEX}],#1,"ne","{$PSU_OK_STATUS}")=1` |INFO |<p>**Depends on**:</p><p>- {#SENSOR_INFO}: Power supply is in critical state</p> |
|System status is in critical state |<p>Please check the device for errors</p> |`count(/Brocade FC SNMP/system.status[swOperStatus.0],#1,"eq","{$HEALTH_CRIT_STATUS}")=1` |HIGH | |
|System status is in warning state |<p>Please check the device for warnings</p> |`count(/Brocade FC SNMP/system.status[swOperStatus.0],#1,"eq","{$HEALTH_WARN_STATUS:\"offline\"}")=1 or count(/Brocade FC SNMP/system.status[swOperStatus.0],#1,"eq","{$HEALTH_WARN_STATUS:\"testing\"}")=1` |WARNING |<p>**Depends on**:</p><p>- System status is in critical state</p> |
|{#SENSOR_INFO}: Temperature is above warning threshold: >{$TEMP_WARN:""} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Brocade FC SNMP/sensor.temp.value[swSensorValue.{#SNMPINDEX}],5m)>{$TEMP_WARN:""} or last(/Brocade FC SNMP/sensor.temp.status[swSensorStatus.{#SNMPINDEX}])={$TEMP_WARN_STATUS} `<p>Recovery expression:</p>`max(/Brocade FC SNMP/sensor.temp.value[swSensorValue.{#SNMPINDEX}],5m)<{$TEMP_WARN:""}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_INFO}: Temperature is above critical threshold: >{$TEMP_CRIT:""}</p> |
|{#SENSOR_INFO}: Temperature is above critical threshold: >{$TEMP_CRIT:""} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Brocade FC SNMP/sensor.temp.value[swSensorValue.{#SNMPINDEX}],5m)>{$TEMP_CRIT:""}`<p>Recovery expression:</p>`max(/Brocade FC SNMP/sensor.temp.value[swSensorValue.{#SNMPINDEX}],5m)<{$TEMP_CRIT:""}-3` |HIGH | |
|{#SENSOR_INFO}: Temperature is too low: <{$TEMP_CRIT_LOW:""} |<p>-</p> |`avg(/Brocade FC SNMP/sensor.temp.value[swSensorValue.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:""}`<p>Recovery expression:</p>`min(/Brocade FC SNMP/sensor.temp.value[swSensorValue.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:""}+3` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

## Known Issues

- Description: no IF-MIB::ifAlias is available
  - Version: v6.3.1c, v7.0.0c,  v7.4.1c
  - Device: all

