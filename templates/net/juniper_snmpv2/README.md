
# Template Net Juniper SNMPv2

## Overview

For Zabbix version: 4.4  

## Setup


## Zabbix configuration


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT}|<p>-</p>|`90`|
|{$FAN_CRIT_STATUS}|<p>-</p>|`6`|
|{$HEALTH_CRIT_STATUS}|<p>-</p>|`3`|
|{$MEMORY.UTIL.MAX}|<p>-</p>|`90`|
|{$PSU_CRIT_STATUS}|<p>-</p>|`6`|
|{$TEMP_CRIT:"Routing Engine"}|<p>-</p>|`80`|
|{$TEMP_CRIT_LOW}|<p>-</p>|`5`|
|{$TEMP_CRIT}|<p>-</p>|`60`|
|{$TEMP_WARN:"Routing Engine"}|<p>-</p>|`70`|
|{$TEMP_WARN}|<p>-</p>|`50`|

## Template links

|Name|
|----|
|Template Module EtherLike-MIB SNMPv2|
|Template Module Generic SNMPv2|
|Template Module Interfaces SNMPv2|

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|CPU and Memory Discovery|<p>Scanning JUNIPER-MIB::jnxOperatingTable for CPU and Memory</p><p>http://kb.juniper.net/InfoCenter/index?page=content&id=KB17526&actp=search. Filter limits results to Routing Engines</p>|SNMP|jnxOperatingTable.discovery<p>**Filter**:</p>AND_OR <p>- A: {#SNMPVALUE} MATCHES_REGEX `Routing Engine.*`</p>|
|Temperature discovery|<p>Scanning JUNIPER-MIB::jnxOperatingTable for Temperature</p><p>http://kb.juniper.net/InfoCenter/index?page=content&id=KB17526&actp=search. Filter limits results to Routing Engines</p>|SNMP|jnxOperatingTable.discovery.temp<p>**Filter**:</p>AND_OR <p>- A: {#SNMPVALUE} MATCHES_REGEX `[^0]+`</p>|
|FAN Discovery|<p>Scanning JUNIPER-MIB::jnxOperatingTable for Fans</p>|SNMP|jnxOperatingTable.discovery.fans|
|PSU Discovery|<p>Scanning JUNIPER-MIB::jnxOperatingTable for Power Supplies</p>|SNMP|jnxOperatingTable.discovery.psu|

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU|{#SNMPVALUE}: CPU utilization|<p>MIB: JUNIPER-MIB</p><p>The CPU utilization in percentage of this subject. Zero if unavailable or inapplicable.</p><p>Reference: http://kb.juniper.net/library/CUSTOMERSERVICE/GLOBAL_JTAC/BK26199/SRX%20SNMP%20Monitoring%20Guide_v1.1.pdf</p>|SNMP|system.cpu.util[jnxOperatingCPU.{#SNMPINDEX}]|
|Fans|{#SNMPVALUE}: Fan status|<p>MIB: JUNIPER-MIB</p>|SNMP|sensor.fan.status[jnxOperatingState.4.{#SNMPINDEX}]|
|Inventory|Hardware serial number|<p>MIB: JUNIPER-MIB</p><p>The serial number of this subject, blank if unknown or unavailable.</p>|SNMP|system.hw.serialnumber<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p>|
|Inventory|Hardware model name|<p>MIB: JUNIPER-MIB</p><p>The name, model, or detailed description of the box,indicating which product the box is about, for example 'M40'.</p>|SNMP|system.hw.model<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p>|
|Inventory|Operating system|<p>MIB: SNMPv2-MIB</p>|SNMP|system.sw.os[sysDescr.0]<p>**Preprocessing**:</p><p>- REGEX: `kernel (JUNOS [0-9a-zA-Z\.\-]+) \1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p>|
|Memory|{#SNMPVALUE}: Memory utilization|<p>MIB: JUNIPER-MIB</p><p>The buffer pool utilization in percentage of this subject.  Zero if unavailable or inapplicable.</p><p>Reference: http://kb.juniper.net/library/CUSTOMERSERVICE/GLOBAL_JTAC/BK26199/SRX%20SNMP%20Monitoring%20Guide_v1.1.pdf</p>|SNMP|vm.memory.util[jnxOperatingBuffer.{#SNMPINDEX}]|
|Power_supply|{#SNMPVALUE}: Power supply status|<p>MIB: JUNIPER-MIB</p><p>If they are using DC power supplies there is a known issue on PR 1064039 where the fans do not detect the temperature correctly and fail to cool the power supply causing the shutdown to occur.</p><p>This is fixed in Junos 13.3R7 https://forums.juniper.net/t5/Routing/PEM-0-not-OK-MX104/m-p/289644#M14122</p>|SNMP|sensor.psu.status[jnxOperatingState.2.{#SNMPINDEX}]|
|Status|Overall system health status|<p>MIB: JUNIPER-ALARM-MIB</p><p>The red alarm indication on the craft interface panel.</p><p>The red alarm is on when there is some system</p><p>failure or power supply failure or the system</p><p>is experiencing a hardware malfunction or some</p><p>threshold is being exceeded.</p><p>This red alarm state could be turned off by the</p><p>ACO/LT (Alarm Cut Off / Lamp Test) button on the</p><p>front panel module.</p>|SNMP|system.status[jnxRedAlarmState.0]|
|Temperature|{#SENSOR_INFO}: Temperature|<p>MIB: JUNIPER-MIB</p><p>The temperature in Celsius (degrees C) of {#SENSOR_INFO}</p>|SNMP|sensor.temp.value[jnxOperatingTemp.{#SNMPINDEX}]|

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#SNMPVALUE}: High CPU utilization (over {$CPU.UTIL.CRIT}% for 5m)|<p>CPU utilization is too high. The system might be slow to respond.</p>|`{TEMPLATE_NAME:system.cpu.util[jnxOperatingCPU.{#SNMPINDEX}].min(5m)}>{$CPU.UTIL.CRIT}`|WARNING||
|{#SNMPVALUE}: Fan is in critical state|<p>Please check the fan unit</p>|`{TEMPLATE_NAME:sensor.fan.status[jnxOperatingState.4.{#SNMPINDEX}].count(#1,{$FAN_CRIT_STATUS},eq)}=1`|AVERAGE||
|Device has been replaced (new serial number received)|<p>Device serial number has changed. Ack to close</p>|`{TEMPLATE_NAME:system.hw.serialnumber.diff()}=1 and {TEMPLATE_NAME:system.hw.serialnumber.strlen()}>0`|INFO|<p>Manual close: YES</p>|
|Operating system description has changed|<p>Operating system description has changed. Possible reasons that system has been updated or replaced. Ack to close.</p>|`{TEMPLATE_NAME:system.sw.os[sysDescr.0].diff()}=1 and {TEMPLATE_NAME:system.sw.os[sysDescr.0].strlen()}>0`|INFO|<p>Manual close: YES</p>|
|{#SNMPVALUE}: High memory utilization ( >{$MEMORY.UTIL.MAX}% for 5m)|<p>The system is running out of free memory.</p>|`{TEMPLATE_NAME:vm.memory.util[jnxOperatingBuffer.{#SNMPINDEX}].min(5m)}>{$MEMORY.UTIL.MAX}`|AVERAGE||
|{#SNMPVALUE}: Power supply is in critical state|<p>Please check the power supply unit for errors</p>|`{TEMPLATE_NAME:sensor.psu.status[jnxOperatingState.2.{#SNMPINDEX}].count(#1,{$PSU_CRIT_STATUS},eq)}=1`|AVERAGE||
|System status is in critical state|<p>Please check the device for errors</p>|`{TEMPLATE_NAME:system.status[jnxRedAlarmState.0].count(#1,{$HEALTH_CRIT_STATUS},eq)}=1`|HIGH||
|{#SENSOR_INFO}: Temperature is above warning threshold: >{$TEMP_WARN:""}|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`{TEMPLATE_NAME:sensor.temp.value[jnxOperatingTemp.{#SNMPINDEX}].avg(5m)}>{$TEMP_WARN:""}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[jnxOperatingTemp.{#SNMPINDEX}].max(5m)}<{$TEMP_WARN:""}-3`|WARNING|<p>**Depends on**:</p><p>- {#SENSOR_INFO}: Temperature is above critical threshold: >{$TEMP_CRIT:""}</p>|
|{#SENSOR_INFO}: Temperature is above critical threshold: >{$TEMP_CRIT:""}|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`{TEMPLATE_NAME:sensor.temp.value[jnxOperatingTemp.{#SNMPINDEX}].avg(5m)}>{$TEMP_CRIT:""}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[jnxOperatingTemp.{#SNMPINDEX}].max(5m)}<{$TEMP_CRIT:""}-3`|HIGH||
|{#SENSOR_INFO}: Temperature is too low: <{$TEMP_CRIT_LOW:""}|<p>-</p>|`{TEMPLATE_NAME:sensor.temp.value[jnxOperatingTemp.{#SNMPINDEX}].avg(5m)}<{$TEMP_CRIT_LOW:""}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[jnxOperatingTemp.{#SNMPINDEX}].min(5m)}>{$TEMP_CRIT_LOW:""}+3`|AVERAGE||

## Feedback

Please report any issues with the template at https://support.zabbix.com

