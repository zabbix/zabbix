
# Huawei VRP SNMP

## Overview

For Zabbix version: 6.0 and higher  
Reference: https://www.slideshare.net/Huanetwork/huawei-s5700-naming-conventions-and-port-numbering-conventions
Reference: http://support.huawei.com/enterprise/KnowledgebaseReadAction.action?contentId=KB1000090234

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>-</p> |`90` |
|{$FAN_CRIT_STATUS} |<p>-</p> |`2` |
|{$MEMORY.UTIL.MAX} |<p>-</p> |`90` |
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
|MPU Discovery |<p>http://support.huawei.com/enterprise/KnowledgebaseReadAction.action?contentId=KB1000090234. Filter limits results to Main Processing Units</p> |SNMP |mpu.discovery<p>**Filter**:</p>AND_OR <p>- {#ENT_NAME} MATCHES_REGEX `MPU.*`</p> |
|Entity Discovery |<p>-</p> |SNMP |entity.discovery<p>**Filter**:</p>AND_OR <p>- {#ENT_CLASS} MATCHES_REGEX `3`</p> |
|FAN Discovery |<p>-</p> |SNMP |discovery.fans |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |{#ENT_NAME}: CPU utilization |<p>MIB: HUAWEI-ENTITY-EXTENT-MIB</p><p>The CPU usage for this entity. Generally, the CPU usage will calculate the overall CPU usage on the entity, and itis not sensible with the number of CPU on the entity.</p><p>Reference: http://support.huawei.com/enterprise/KnowledgebaseReadAction.action?contentId=KB1000090234</p> |SNMP |system.cpu.util[hwEntityCpuUsage.{#SNMPINDEX}] |
|Fans |#{#SNMPVALUE}: Fan status |<p>MIB: HUAWEI-ENTITY-EXTENT-MIB</p> |SNMP |sensor.fan.status[hwEntityFanState.{#SNMPINDEX}] |
|Inventory |{#ENT_NAME}: Hardware serial number |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |{#ENT_NAME}: Hardware version(revision) |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.version[entPhysicalHardwareRev.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |{#ENT_NAME}: Operating system |<p>MIB: ENTITY-MIB</p> |SNMP |system.sw.os[entPhysicalSoftwareRev.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |{#ENT_NAME}: Hardware model name |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.model[entPhysicalDescr.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Memory |{#ENT_NAME}: Memory utilization |<p>MIB: HUAWEI-ENTITY-EXTENT-MIB</p><p>The memory usage for the entity. This object indicates what percent of memory are used.</p><p>Reference: http://support.huawei.com/enterprise/KnowledgebaseReadAction.action?contentId=KB1000090234</p> |SNMP |vm.memory.util[hwEntityMemUsage.{#SNMPINDEX}] |
|Temperature |{#ENT_NAME}: Temperature |<p>MIB: HUAWEI-ENTITY-EXTENT-MIB</p><p>The temperature for the {#SNMPVALUE}.</p> |SNMP |sensor.temp.value[hwEntityTemperature.{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#ENT_NAME}: High CPU utilization (over {$CPU.UTIL.CRIT}% for 5m) |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/Huawei VRP SNMP/system.cpu.util[hwEntityCpuUsage.{#SNMPINDEX}],5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|#{#SNMPVALUE}: Fan is in critical state |<p>Please check the fan unit</p> |`count(/Huawei VRP SNMP/sensor.fan.status[hwEntityFanState.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS}")=1` |AVERAGE | |
|{#ENT_NAME}: Device has been replaced (new serial number received) |<p>Device serial number has changed. Ack to close</p> |`last(/Huawei VRP SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}],#1)<>last(/Huawei VRP SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}],#2) and length(last(/Huawei VRP SNMP/system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}]))>0` |INFO |<p>Manual close: YES</p> |
|{#ENT_NAME}: Operating system description has changed |<p>Operating system description has changed. Possible reasons that system has been updated or replaced. Ack to close.</p> |`last(/Huawei VRP SNMP/system.sw.os[entPhysicalSoftwareRev.{#SNMPINDEX}],#1)<>last(/Huawei VRP SNMP/system.sw.os[entPhysicalSoftwareRev.{#SNMPINDEX}],#2) and length(last(/Huawei VRP SNMP/system.sw.os[entPhysicalSoftwareRev.{#SNMPINDEX}]))>0` |INFO |<p>Manual close: YES</p> |
|{#ENT_NAME}: High memory utilization (>{$MEMORY.UTIL.MAX}% for 5m) |<p>The system is running out of free memory.</p> |`min(/Huawei VRP SNMP/vm.memory.util[hwEntityMemUsage.{#SNMPINDEX}],5m)>{$MEMORY.UTIL.MAX}` |AVERAGE | |
|{#ENT_NAME}: Temperature is above warning threshold: >{$TEMP_WARN:""} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Huawei VRP SNMP/sensor.temp.value[hwEntityTemperature.{#SNMPINDEX}],5m)>{$TEMP_WARN:""}`<p>Recovery expression:</p>`max(/Huawei VRP SNMP/sensor.temp.value[hwEntityTemperature.{#SNMPINDEX}],5m)<{$TEMP_WARN:""}-3` |WARNING |<p>**Depends on**:</p><p>- {#ENT_NAME}: Temperature is above critical threshold: >{$TEMP_CRIT:""}</p> |
|{#ENT_NAME}: Temperature is above critical threshold: >{$TEMP_CRIT:""} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Huawei VRP SNMP/sensor.temp.value[hwEntityTemperature.{#SNMPINDEX}],5m)>{$TEMP_CRIT:""}`<p>Recovery expression:</p>`max(/Huawei VRP SNMP/sensor.temp.value[hwEntityTemperature.{#SNMPINDEX}],5m)<{$TEMP_CRIT:""}-3` |HIGH | |
|{#ENT_NAME}: Temperature is too low: <{$TEMP_CRIT_LOW:""} |<p>-</p> |`avg(/Huawei VRP SNMP/sensor.temp.value[hwEntityTemperature.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:""}`<p>Recovery expression:</p>`min(/Huawei VRP SNMP/sensor.temp.value[hwEntityTemperature.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:""}+3` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

