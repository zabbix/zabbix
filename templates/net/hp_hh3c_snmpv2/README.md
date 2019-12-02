
# Template Net HP Comware HH3C SNMPv2

## Overview

For Zabbix version: 4.4  
http://certifiedgeek.weebly.com/blog/hp-comware-snmp-mib-for-cpu-memory-and-temperature
http://www.h3c.com.hk/products___solutions/technology/system_management/configuration_example/200912/656451_57_0.htm

This template was tested on:

- HP 1910-48, version 1910-48 Switch Software Version 5.20.99, Release 1116 Copyright(c)2010-2016 Hewlett Packard Enterprise Development LP
- HP A5500-24G-4SFP, version HP Comware Platform Software, Software Version 5.20.99 Release 5501P21 HP A5500-24G-4SFP

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT}|<p>-</p>|`90`|
|{$FAN_CRIT_STATUS:"fanError"}|<p>-</p>|`41`|
|{$FAN_CRIT_STATUS:"hardwareFaulty"}|<p>-</p>|`91`|
|{$MEMORY.UTIL.MAX}|<p>-</p>|`90`|
|{$PSU_CRIT_STATUS:"hardwareFaulty"}|<p>-</p>|`91`|
|{$PSU_CRIT_STATUS:"psuError"}|<p>-</p>|`51`|
|{$PSU_CRIT_STATUS:"rpsError"}|<p>-</p>|`61`|
|{$TEMP_CRIT_LOW}|<p>-</p>|`5`|
|{$TEMP_CRIT}|<p>-</p>|`60`|
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
|Module Discovery|<p>Filter limits results to 'Module level1' or Fabric Modules</p>|SNMP|module.discovery<p>**Filter**:</p>OR <p>- A: {#SNMPVALUE} MATCHES_REGEX `^(MODULE|Module) (LEVEL|level)1$`</p><p>- A: {#SNMPVALUE} MATCHES_REGEX `(Fabric|FABRIC) (.+) (Module|MODULE)`</p>|
|Temperature Discovery|<p>Discovering modules temperature (same filter as in Module Discovery) plus and temperature sensors</p>|SNMP|temp.discovery<p>**Filter**:</p>OR <p>- A: {#SNMPVALUE} MATCHES_REGEX `^(MODULE|Module) (LEVEL|level)1$`</p><p>- A: {#SNMPVALUE} MATCHES_REGEX `(Fabric|FABRIC) (.+) (Module|MODULE)`</p><p>- A: {#SNMPVALUE} MATCHES_REGEX `(T|t)emperature.*(s|S)ensor`</p>|
|FAN Discovery|<p>Discovering all entities of PhysicalClass - 7: fan(7)</p>|SNMP|fan.discovery<p>**Filter**:</p>AND_OR <p>- A: {#ENT_CLASS} MATCHES_REGEX `7`</p>|
|PSU Discovery|<p>Discovering all entities of PhysicalClass - 6: powerSupply(6)</p>|SNMP|psu.discovery<p>**Filter**:</p>AND_OR <p>- A: {#ENT_CLASS} MATCHES_REGEX `6`</p>|
|Entity Discovery|<p>-</p>|SNMP|entity.discovery<p>**Filter**:</p>AND_OR <p>- A: {#ENT_CLASS} MATCHES_REGEX `3`</p>|

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU|{#MODULE_NAME}: CPU utilization|<p>MIB: HH3C-ENTITY-EXT-MIB</p><p>The CPU usage for this entity. Generally, the CPU usage</p><p>will calculate the overall CPU usage on the entity, and it</p><p>is not sensible with the number of CPU on the entity</p>|SNMP|system.cpu.util[hh3cEntityExtCpuUsage.{#SNMPINDEX}]|
|Fans|{#ENT_NAME}: Fan status|<p>MIB: HH3C-ENTITY-EXT-MIB</p><p>Indicate the error state of this entity object.</p><p>fanError(41) means that the fan stops working.</p>|SNMP|sensor.fan.status[hh3cEntityExtErrorStatus.{#SNMPINDEX}]|
|Inventory|{#ENT_NAME}: Hardware model name|<p>MIB: ENTITY-MIB</p>|SNMP|system.hw.model[entPhysicalDescr.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p>|
|Inventory|{#ENT_NAME}: Hardware serial number|<p>MIB: ENTITY-MIB</p>|SNMP|system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p>|
|Inventory|{#ENT_NAME}: Firmware version|<p>MIB: ENTITY-MIB</p>|SNMP|system.hw.firmware[entPhysicalFirmwareRev.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p>|
|Inventory|{#ENT_NAME}: Hardware version(revision)|<p>MIB: ENTITY-MIB</p>|SNMP|system.hw.version[entPhysicalHardwareRev.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p>|
|Inventory|{#ENT_NAME}: Operating system|<p>MIB: ENTITY-MIB</p>|SNMP|system.sw.os[entPhysicalSoftwareRev.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p>|
|Memory|{#MODULE_NAME}: Memory utilization|<p>MIB: HH3C-ENTITY-EXT-MIB</p><p>The memory usage for the entity. This object indicates what</p><p>percent of memory are used.</p>|SNMP|vm.memory.util[hh3cEntityExtMemUsage.{#SNMPINDEX}]|
|Power_supply|{#ENT_NAME}: Power supply status|<p>MIB: HH3C-ENTITY-EXT-MIB</p><p>Indicate the error state of this entity object.</p><p>psuError(51) means that the Power Supply Unit is in the state of fault.</p><p>rpsError(61) means the Redundant Power Supply is in the state of fault.</p>|SNMP|sensor.psu.status[hh3cEntityExtErrorStatus.{#SNMPINDEX}]|
|Temperature|{#SNMPVALUE}: Temperature|<p>MIB: HH3C-ENTITY-EXT-MIB</p><p>The temperature for the {#SNMPVALUE}.</p>|SNMP|sensor.temp.value[hh3cEntityExtTemperature.{#SNMPINDEX}]|

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#MODULE_NAME}: High CPU utilization (over {$CPU.UTIL.CRIT}% for 5m)|<p>CPU utilization is too high. The system might be slow to respond.</p>|`{TEMPLATE_NAME:system.cpu.util[hh3cEntityExtCpuUsage.{#SNMPINDEX}].min(5m)}>{$CPU.UTIL.CRIT}`|WARNING||
|{#ENT_NAME}: Fan is in critical state|<p>Please check the fan unit</p>|`{TEMPLATE_NAME:sensor.fan.status[hh3cEntityExtErrorStatus.{#SNMPINDEX}].count(#1,{$FAN_CRIT_STATUS:"fanError"},eq)}=1 or {TEMPLATE_NAME:sensor.fan.status[hh3cEntityExtErrorStatus.{#SNMPINDEX}].count(#1,{$FAN_CRIT_STATUS:"hardwareFaulty"},eq)}=1`|AVERAGE||
|{#ENT_NAME}: Device has been replaced (new serial number received)|<p>Device serial number has changed. Ack to close</p>|`{TEMPLATE_NAME:system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}].diff()}=1 and {TEMPLATE_NAME:system.hw.serialnumber[entPhysicalSerialNum.{#SNMPINDEX}].strlen()}>0`|INFO|<p>Manual close: YES</p>|
|{#ENT_NAME}: Firmware has changed|<p>Firmware version has changed. Ack to close</p>|`{TEMPLATE_NAME:system.hw.firmware[entPhysicalFirmwareRev.{#SNMPINDEX}].diff()}=1 and {TEMPLATE_NAME:system.hw.firmware[entPhysicalFirmwareRev.{#SNMPINDEX}].strlen()}>0`|INFO|<p>Manual close: YES</p>|
|{#ENT_NAME}: Operating system description has changed|<p>Operating system description has changed. Possible reasons that system has been updated or replaced. Ack to close.</p>|`{TEMPLATE_NAME:system.sw.os[entPhysicalSoftwareRev.{#SNMPINDEX}].diff()}=1 and {TEMPLATE_NAME:system.sw.os[entPhysicalSoftwareRev.{#SNMPINDEX}].strlen()}>0`|INFO|<p>Manual close: YES</p>|
|{#MODULE_NAME}: High memory utilization ( >{$MEMORY.UTIL.MAX}% for 5m)|<p>The system is running out of free memory.</p>|`{TEMPLATE_NAME:vm.memory.util[hh3cEntityExtMemUsage.{#SNMPINDEX}].min(5m)}>{$MEMORY.UTIL.MAX}`|AVERAGE||
|{#ENT_NAME}: Power supply is in critical state|<p>Please check the power supply unit for errors</p>|`{TEMPLATE_NAME:sensor.psu.status[hh3cEntityExtErrorStatus.{#SNMPINDEX}].count(#1,{$PSU_CRIT_STATUS:"psuError"},eq)}=1 or {TEMPLATE_NAME:sensor.psu.status[hh3cEntityExtErrorStatus.{#SNMPINDEX}].count(#1,{$PSU_CRIT_STATUS:"rpsError"},eq)}=1 or {TEMPLATE_NAME:sensor.psu.status[hh3cEntityExtErrorStatus.{#SNMPINDEX}].count(#1,{$PSU_CRIT_STATUS:"hardwareFaulty"},eq)}=1`|AVERAGE||
|{#SNMPVALUE}: Temperature is above warning threshold: >{$TEMP_WARN:""}|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`{TEMPLATE_NAME:sensor.temp.value[hh3cEntityExtTemperature.{#SNMPINDEX}].avg(5m)}>{$TEMP_WARN:""}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[hh3cEntityExtTemperature.{#SNMPINDEX}].max(5m)}<{$TEMP_WARN:""}-3`|WARNING|<p>**Depends on**:</p><p>- {#SNMPVALUE}: Temperature is above critical threshold: >{$TEMP_CRIT:""}</p>|
|{#SNMPVALUE}: Temperature is above critical threshold: >{$TEMP_CRIT:""}|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`{TEMPLATE_NAME:sensor.temp.value[hh3cEntityExtTemperature.{#SNMPINDEX}].avg(5m)}>{$TEMP_CRIT:""}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[hh3cEntityExtTemperature.{#SNMPINDEX}].max(5m)}<{$TEMP_CRIT:""}-3`|HIGH||
|{#SNMPVALUE}: Temperature is too low: <{$TEMP_CRIT_LOW:""}|<p>-</p>|`{TEMPLATE_NAME:sensor.temp.value[hh3cEntityExtTemperature.{#SNMPINDEX}].avg(5m)}<{$TEMP_CRIT_LOW:""}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[hh3cEntityExtTemperature.{#SNMPINDEX}].min(5m)}>{$TEMP_CRIT_LOW:""}+3`|AVERAGE||

## Feedback

Please report any issues with the template at https://support.zabbix.com

## Known Issues

- Description: No temperature sensors. All entities of them return 0 for HH3C-ENTITY-EXT-MIB::hh3cEntityExtTemperature
  - Version: 1910-48 Switch Software Version 5.20.99, Release 1116 Copyright(c)2010-2016 Hewlett Packard Enterprise Development LP
  - Device: HP 1910-48

