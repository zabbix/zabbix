
# Supermicro Aten SNMP

## Overview

For Zabbix version: 6.0 and higher  
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
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT} |<p>-</p> |`60` |
|{$TEMP_WARN} |<p>-</p> |`50` |

## Template links

|Name|
|----|
|Generic SNMP |

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Temperature Discovery |<p>Scanning ATEN-IPMI-MIB::sensorTable with filter: not connected temp sensors (Value = 0)</p> |SNMP |tempDescr.discovery<p>**Filter**:</p>AND <p>- {#SNMPVALUE} MATCHES_REGEX `[1-9]+`</p><p>- {#SENSOR_DESCR} MATCHES_REGEX `.*Temp.*`</p> |
|FAN Discovery |<p>Scanning ATEN-IPMI-MIB::sensorTable with filter: not connected FAN sensors (Value = 0)</p> |SNMP |fan.discovery<p>**Filter**:</p>AND <p>- {#SNMPVALUE} MATCHES_REGEX `[1-9]+`</p><p>- {#SENSOR_DESCR} MATCHES_REGEX `FAN.*`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Fans |{#SENSOR_DESCR}: Fan speed, % |<p>MIB: ATEN-IPMI-MIB</p><p>A textual string containing information about the interface.</p><p>This string should include the name of the manufacturer, the product name and the version of the interface hardware/software.</p> |SNMP |sensor.fan.speed.percentage[sensorReading.{#SNMPINDEX}] |
|Temperature |{#SENSOR_DESCR}: Temperature |<p>MIB: ATEN-IPMI-MIB</p><p>A textual string containing information about the interface.</p><p>This string should include the name of the manufacturer, the product name and the version of the interface hardware/software.</p> |SNMP |sensor.temp.value[sensorReading.{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#SENSOR_DESCR}: Temperature is above warning threshold: >{$TEMP_WARN:""} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Supermicro Aten SNMP/sensor.temp.value[sensorReading.{#SNMPINDEX}],5m)>{$TEMP_WARN:""}`<p>Recovery expression:</p>`max(/Supermicro Aten SNMP/sensor.temp.value[sensorReading.{#SNMPINDEX}],5m)<{$TEMP_WARN:""}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_DESCR}: Temperature is above critical threshold: >{$TEMP_CRIT:""}</p> |
|{#SENSOR_DESCR}: Temperature is above critical threshold: >{$TEMP_CRIT:""} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Supermicro Aten SNMP/sensor.temp.value[sensorReading.{#SNMPINDEX}],5m)>{$TEMP_CRIT:""}`<p>Recovery expression:</p>`max(/Supermicro Aten SNMP/sensor.temp.value[sensorReading.{#SNMPINDEX}],5m)<{$TEMP_CRIT:""}-3` |HIGH | |
|{#SENSOR_DESCR}: Temperature is too low: <{$TEMP_CRIT_LOW:""} |<p>-</p> |`avg(/Supermicro Aten SNMP/sensor.temp.value[sensorReading.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:""}`<p>Recovery expression:</p>`min(/Supermicro Aten SNMP/sensor.temp.value[sensorReading.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:""}+3` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

