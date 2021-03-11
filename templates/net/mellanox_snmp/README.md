
# Mellanox SNMP

## Overview

For Zabbix version: 5.0 and higher  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$FAN.STATUS.CRIT} |<p>-</p> |`3` |
|{$PSU.STATUS.CRIT} |<p>-</p> |`2` |
|{$TEMP.MAX.CRIT} |<p>-</p> |`60` |
|{$TEMP.MAX.WARN} |<p>-</p> |`50` |
|{$TEMP.MIN.CRIT} |<p>-</p> |`5` |
|{$TEMP.STATUS.WARN} |<p>-</p> |`3` |

## Template links

|Name|
|----|
|Generic SNMP |
|HOST-RESOURCES-MIB SNMP |
|Interfaces SNMP |

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Temperature Discovery |<p>ENTITY-SENSORS-MIB::EntitySensorDataType discovery with celsius filter</p> |SNMP |temp.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>**Filter**:</p>AND <p>- B: {#SENSOR_TYPE} MATCHES_REGEX `8`</p><p>- B: {#SENSOR_PRECISION} MATCHES_REGEX `1`</p> |
|Fan Discovery |<p>ENTITY-SENSORS-MIB::EntitySensorDataType discovery with rpm filter</p> |SNMP |fan.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>**Filter**:</p>OR <p>- B: {#SNMPVALUE} MATCHES_REGEX `10`</p> |
|Entity Discovery |<p>-</p> |SNMP |entity.discovery<p>**Filter**:</p>AND_OR <p>- A: {#ENT_CLASS} MATCHES_REGEX `3`</p> |
|PSU Discovery |<p>-</p> |SNMP |psu.discovery<p>**Filter**:</p>AND_OR <p>- A: {#ENT_CLASS} MATCHES_REGEX `6`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Fans |{#SENSOR_INFO}: Fan speed |<p>MIB: ENTITY-SENSORS-MIB</p><p>The most recent measurement obtained by the agent for this sensor.</p><p>To correctly interpret the value of this object, the associated entPhySensorType,</p><p>entPhySensorScale, and entPhySensorPrecision objects must also be examined.</p> |SNMP |sensor.fan.speed[entPhySensorValue.{#SNMPINDEX}] |
|Fans |{#SENSOR_INFO}: Fan status |<p>MIB: ENTITY-SENSORS-MIB</p><p>The operational status of the sensor {#SENSOR_INFO}</p> |SNMP |sensor.fan.status[entPhySensorOperStatus.{#SNMPINDEX}] |
|Inventory |{#ENT_NAME}: Hardware model name |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.model[entPhysicalModelName.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |{#ENT_NAME}: Hardware serial number |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Power_supply |{#ENT_NAME}: Power supply status |<p>MIB: ENTITY-STATE-MIB</p> |SNMP |sensor.psu.status[entStateOper.{#SNMPINDEX}] |
|Temperature |{#SENSOR_INFO}: Temperature |<p>MIB: ENTITY-SENSORS-MIB</p><p>The most recent measurement obtained by the agent for this sensor.</p><p>To correctly interpret the value of this object, the associated entPhySensorType,</p><p>entPhySensorScale, and entPhySensorPrecision objects must also be examined.</p> |SNMP |sensor.temp.value[entPhySensorValue.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p> |
|Temperature |{#SENSOR_INFO}: Temperature status |<p>MIB: ENTITY-SENSORS-MIB</p><p>The operational status of the sensor {#SENSOR_INFO}. Possible value:</p><p>- ok(1) indicates that the agent can obtain the sensor value.</p><p>- unavailable(2) indicates that the agent presently cannot obtain the sensor value.</p><p>- nonoperational(3) indicates that the agent believes the sensor is broken. The sensor could have a hard failure (disconnected wire), or a soft failure such as out-of-range, jittery, or wildly fluctuating readings.</p> |SNMP |sensor.temp.status[entPhySensorOperStatus.{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#SENSOR_INFO}: Fan is in critical state |<p>Please check the fan unit</p> |`{TEMPLATE_NAME:sensor.fan.status[entPhySensorOperStatus.{#SNMPINDEX}].count(#1,{$FAN.STATUS.CRIT},eq)}=1` |AVERAGE | |
|{#ENT_NAME}: Device has been replaced (new serial number received) |<p>Device serial number has changed. Ack to close</p> |`{TEMPLATE_NAME:system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}].diff()}=1 and {TEMPLATE_NAME:system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}].strlen()}>0` |INFO |<p>Manual close: YES</p> |
|{#ENT_NAME}: Power supply is in critical state |<p>Please check the power supply unit for errors</p> |`{TEMPLATE_NAME:sensor.psu.status[entStateOper.{#SNMPINDEX}].count(#1,{$PSU.STATUS.CRIT},eq)}=1` |AVERAGE | |
|{#SENSOR_INFO}: Temperature is above warning threshold: >{$TEMP.MAX.WARN:"{#SENSOR_INFO}"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`{TEMPLATE_NAME:sensor.temp.value[entPhySensorValue.{#SNMPINDEX}].avg(5m)}>{$TEMP.MAX.WARN:"{#SENSOR_INFO}"} or {Mellanox SNMP:sensor.temp.status[entPhySensorOperStatus.{#SNMPINDEX}].last()}={$TEMP.STATUS.WARN}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[entPhySensorValue.{#SNMPINDEX}].max(5m)}<{$TEMP.MAX.WARN:"{#SENSOR_INFO}"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_INFO}: Temperature is above critical threshold: >{$TEMP.MAX.CRIT:"{#SENSOR_INFO}"}</p> |
|{#SENSOR_INFO}: Temperature is above critical threshold: >{$TEMP.MAX.CRIT:"{#SENSOR_INFO}"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`{TEMPLATE_NAME:sensor.temp.value[entPhySensorValue.{#SNMPINDEX}].avg(5m)}>{$TEMP.MAX.CRIT:"{#SENSOR_INFO}"}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[entPhySensorValue.{#SNMPINDEX}].max(5m)}<{$TEMP.MAX.CRIT:"{#SENSOR_INFO}"}-3` |HIGH | |
|{#SENSOR_INFO}: Temperature is too low: <{$TEMP.MIN.CRIT:"{#SENSOR_INFO}"} |<p>-</p> |`{TEMPLATE_NAME:sensor.temp.value[entPhySensorValue.{#SNMPINDEX}].avg(5m)}<{$TEMP.MIN.CRIT:"{#SENSOR_INFO}"}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[entPhySensorValue.{#SNMPINDEX}].min(5m)}>{$TEMP.MIN.CRIT:"{#SENSOR_INFO}"}+3` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

