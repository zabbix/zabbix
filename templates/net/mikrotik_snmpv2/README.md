
# Template Net Mikrotik SNMPv2

## Overview

For Zabbix version: 4.4  

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT}|<p>-</p>|`90`|
|{$MEMORY.UTIL.MAX}|<p>-</p>|`90`|
|{$TEMP_CRIT:"CPU"}|<p>-</p>|`75`|
|{$TEMP_CRIT_LOW}|<p>-</p>|`5`|
|{$TEMP_CRIT}|<p>-</p>|`60`|
|{$TEMP_WARN:"CPU"}|<p>-</p>|`70`|
|{$TEMP_WARN}|<p>-</p>|`50`|
|{$VFS.FS.PUSED.MAX.CRIT}|<p>-</p>|`90`|
|{$VFS.FS.PUSED.MAX.WARN}|<p>-</p>|`80`|

## Template links

|Name|
|----|
|Template Module Generic SNMPv2|
|Template Module Interfaces SNMPv2|

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|CPU discovery|<p>HOST-RESOURCES-MIB::hrProcessorTable discovery</p>|SNMP|hrProcessorLoad.discovery|
|Temperature CPU discovery|<p>MIKROTIK-MIB::mtxrHlProcessorTemperature</p><p>Since temperature of CPU is not available on all Mikrotik hardware, this is done to avoid unsupported items.</p>|SNMP|mtxrHlProcessorTemperature.discovery|
|Storage discovery|<p>HOST-RESOURCES-MIB::hrStorage discovery with storage filter</p>|SNMP|storage.discovery<p>**Filter**:</p>OR <p>- B: {#STORAGE_TYPE} MATCHES_REGEX `.+4$`</p><p>- A: {#STORAGE_TYPE} MATCHES_REGEX `.+hrStorageFixedDisk`</p>|

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU|#{#SNMPINDEX}: CPU utilization|<p>MIB: HOST-RESOURCES-MIB</p><p>The average, over the last minute, of the percentage of time that this processor was not idle. Implementations may approximate this one minute smoothing period if necessary.</p>|SNMP|system.cpu.util[hrProcessorLoad.{#SNMPINDEX}]|
|Inventory|Operating system|<p>MIB: MIKROTIK-MIB</p><p>Software version</p>|SNMP|system.sw.os[mtxrLicVersion.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p>|
|Inventory|Hardware model name|<p>-</p>|SNMP|system.hw.model<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p>|
|Inventory|Hardware serial number|<p>MIB: MIKROTIK-MIB</p><p>RouterBOARD serial number</p>|SNMP|system.hw.serialnumber<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p>|
|Inventory|Firmware version|<p>MIB: MIKROTIK-MIB</p><p>Current firmware version</p>|SNMP|system.hw.firmware<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p>|
|Memory|Used memory|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of the storage represented by this entry that is allocated, in units of hrStorageAllocationUnits.</p>|SNMP|vm.memory.used[hrStorageUsed.Memory]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p>|
|Memory|Total memory|<p>MIB: HOST-RESOURCES-MIB</p><p>The size of the storage represented by this entry, in</p><p>units of hrStorageAllocationUnits. This object is</p><p>writable to allow remote configuration of the size of</p><p>the storage area in those cases where such an</p><p>operation makes sense and is possible on the</p><p>underlying system. For example, the amount of main</p><p>memory allocated to a buffer pool might be modified or</p><p>the amount of disk space allocated to virtual memory</p><p>might be modified.</p>|SNMP|vm.memory.total[hrStorageSize.Memory]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p>|
|Memory|Memory utilization|<p>Memory utilization in %</p>|CALCULATED|vm.memory.util[memoryUsedPercentage.Memory]<p>**Expression**:</p>`last("vm.memory.used[hrStorageUsed.Memory]")/last("vm.memory.total[hrStorageSize.Memory]")*100`|
|Storage|Disk-{#SNMPINDEX}: Used space|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of the storage represented by this entry that is allocated, in units of hrStorageAllocationUnits.</p>|SNMP|vfs.fs.used[hrStorageSize.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p>|
|Storage|Disk-{#SNMPINDEX}: Total space|<p>MIB: HOST-RESOURCES-MIB</p><p>The size of the storage represented by this entry, in</p><p>units of hrStorageAllocationUnits. This object is</p><p>writable to allow remote configuration of the size of</p><p>the storage area in those cases where such an</p><p>operation makes sense and is possible on the</p><p>underlying system. For example, the amount of main</p><p>memory allocated to a buffer pool might be modified or</p><p>the amount of disk space allocated to virtual memory</p><p>might be modified.</p>|SNMP|vfs.fs.total[hrStorageSize.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p>|
|Storage|Disk-{#SNMPINDEX}: Space utilization|<p>Space utilization in % for Disk-{#SNMPINDEX}</p>|CALCULATED|vfs.fs.pused[hrStorageSize.{#SNMPINDEX}]<p>**Expression**:</p>`(last("vfs.fs.used[hrStorageSize.{#SNMPINDEX}]")/last("vfs.fs.total[hrStorageSize.{#SNMPINDEX}]"))*100`|
|Temperature|Device: Temperature|<p>MIB: MIKROTIK-MIB</p><p>(mtxrHlTemperature) Device temperature in Celsius (degrees C). Might be missing in entry models (RB750, RB450G..)</p><p>Reference: http://wiki.mikrotik.com/wiki/Manual:SNMP</p>|SNMP|sensor.temp.value[mtxrHlTemperature]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p>|
|Temperature|CPU: Temperature|<p>MIB: MIKROTIK-MIB</p><p>(mtxrHlProcessorTemperature) Processor temperature in Celsius (degrees C). Might be missing in entry models (RB750, RB450G..)</p>|SNMP|sensor.temp.value[mtxrHlProcessorTemperature.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.1`</p>|

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|#{#SNMPINDEX}: High CPU utilization (over {$CPU.UTIL.CRIT}% for 5m)|<p>CPU utilization is too high. The system might be slow to respond.</p>|`{TEMPLATE_NAME:system.cpu.util[hrProcessorLoad.{#SNMPINDEX}].min(5m)}>{$CPU.UTIL.CRIT}`|WARNING||
|Operating system description has changed|<p>Operating system description has changed. Possible reasons that system has been updated or replaced. Ack to close.</p>|`{TEMPLATE_NAME:system.sw.os[mtxrLicVersion.0].diff()}=1 and {TEMPLATE_NAME:system.sw.os[mtxrLicVersion.0].strlen()}>0`|INFO|<p>Manual close: YES</p>|
|Device has been replaced (new serial number received)|<p>Device serial number has changed. Ack to close</p>|`{TEMPLATE_NAME:system.hw.serialnumber.diff()}=1 and {TEMPLATE_NAME:system.hw.serialnumber.strlen()}>0`|INFO|<p>Manual close: YES</p>|
|Firmware has changed|<p>Firmware version has changed. Ack to close</p>|`{TEMPLATE_NAME:system.hw.firmware.diff()}=1 and {TEMPLATE_NAME:system.hw.firmware.strlen()}>0`|INFO|<p>Manual close: YES</p>|
|High memory utilization ( >{$MEMORY.UTIL.MAX}% for 5m)|<p>The system is running out of free memory.</p>|`{TEMPLATE_NAME:vm.memory.util[memoryUsedPercentage.Memory].min(5m)}>{$MEMORY.UTIL.MAX}`|AVERAGE||
|Disk-{#SNMPINDEX}: Disk space is critically low (used > {$VFS.FS.PUSED.MAX.CRIT:"Disk-{#SNMPINDEX}"}%)|<p>Two conditions should match: First, space utilization should be above {$VFS.FS.PUSED.MAX.CRIT:"Disk-{#SNMPINDEX}"}.</p><p> Second condition should be one of the following:</p><p> - The disk free space is less than 5G.</p><p> - The disk will be full in less than 24 hours.</p>|`{TEMPLATE_NAME:vfs.fs.pused[hrStorageSize.{#SNMPINDEX}].last()}>{$VFS.FS.PUSED.MAX.CRIT:"Disk-{#SNMPINDEX}"} and (({Template Net Mikrotik SNMPv2:vfs.fs.total[hrStorageSize.{#SNMPINDEX}].last()}-{Template Net Mikrotik SNMPv2:vfs.fs.used[hrStorageSize.{#SNMPINDEX}].last()})<5G or {TEMPLATE_NAME:vfs.fs.pused[hrStorageSize.{#SNMPINDEX}].timeleft(1h,,100)}<1d)`|AVERAGE|<p>Manual close: YES</p>|
|Disk-{#SNMPINDEX}: Disk space is low (used > {$VFS.FS.PUSED.MAX.WARN:"Disk-{#SNMPINDEX}"}%)|<p>Two conditions should match: First, space utilization should be above {$VFS.FS.PUSED.MAX.WARN:"Disk-{#SNMPINDEX}"}.</p><p> Second condition should be one of the following:</p><p> - The disk free space is less than 10G.</p><p> - The disk will be full in less than 24 hours.</p>|`{TEMPLATE_NAME:vfs.fs.pused[hrStorageSize.{#SNMPINDEX}].last()}>{$VFS.FS.PUSED.MAX.WARN:"Disk-{#SNMPINDEX}"} and (({Template Net Mikrotik SNMPv2:vfs.fs.total[hrStorageSize.{#SNMPINDEX}].last()}-{Template Net Mikrotik SNMPv2:vfs.fs.used[hrStorageSize.{#SNMPINDEX}].last()})<10G or {TEMPLATE_NAME:vfs.fs.pused[hrStorageSize.{#SNMPINDEX}].timeleft(1h,,100)}<1d)`|WARNING|<p>Manual close: YES</p><p>**Depends on**:</p><p>- Disk-{#SNMPINDEX}: Disk space is critically low (used > {$VFS.FS.PUSED.MAX.CRIT:"Disk-{#SNMPINDEX}"}%)</p>|
|Device: Temperature is above warning threshold: >{$TEMP_WARN:"Device"}|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`{TEMPLATE_NAME:sensor.temp.value[mtxrHlTemperature].avg(5m)}>{$TEMP_WARN:"Device"}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[mtxrHlTemperature].max(5m)}<{$TEMP_WARN:"Device"}-3`|WARNING|<p>**Depends on**:</p><p>- Device: Temperature is above critical threshold: >{$TEMP_CRIT:"Device"}</p>|
|Device: Temperature is above critical threshold: >{$TEMP_CRIT:"Device"}|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`{TEMPLATE_NAME:sensor.temp.value[mtxrHlTemperature].avg(5m)}>{$TEMP_CRIT:"Device"}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[mtxrHlTemperature].max(5m)}<{$TEMP_CRIT:"Device"}-3`|HIGH||
|Device: Temperature is too low: <{$TEMP_CRIT_LOW:"Device"}|<p>-</p>|`{TEMPLATE_NAME:sensor.temp.value[mtxrHlTemperature].avg(5m)}<{$TEMP_CRIT_LOW:"Device"}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[mtxrHlTemperature].min(5m)}>{$TEMP_CRIT_LOW:"Device"}+3`|AVERAGE||
|CPU: Temperature is above warning threshold: >{$TEMP_WARN:"CPU"}|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`{TEMPLATE_NAME:sensor.temp.value[mtxrHlProcessorTemperature.{#SNMPINDEX}].avg(5m)}>{$TEMP_WARN:"CPU"}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[mtxrHlProcessorTemperature.{#SNMPINDEX}].max(5m)}<{$TEMP_WARN:"CPU"}-3`|WARNING|<p>**Depends on**:</p><p>- CPU: Temperature is above critical threshold: >{$TEMP_CRIT:"CPU"}</p>|
|CPU: Temperature is above critical threshold: >{$TEMP_CRIT:"CPU"}|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`{TEMPLATE_NAME:sensor.temp.value[mtxrHlProcessorTemperature.{#SNMPINDEX}].avg(5m)}>{$TEMP_CRIT:"CPU"}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[mtxrHlProcessorTemperature.{#SNMPINDEX}].max(5m)}<{$TEMP_CRIT:"CPU"}-3`|HIGH||
|CPU: Temperature is too low: <{$TEMP_CRIT_LOW:"CPU"}|<p>-</p>|`{TEMPLATE_NAME:sensor.temp.value[mtxrHlProcessorTemperature.{#SNMPINDEX}].avg(5m)}<{$TEMP_CRIT_LOW:"CPU"}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[mtxrHlProcessorTemperature.{#SNMPINDEX}].min(5m)}>{$TEMP_CRIT_LOW:"CPU"}+3`|AVERAGE||

## Feedback

Please report any issues with the template at https://support.zabbix.com

## Known Issues

- Description: Doesn't have ifHighSpeed filled. fixed in more recent versions
  - Version: RouterOS 6.28 or lower

- Description: Doesn't have any temperature sensors
  - Version: RouterOS 6.38.5
  - Device: Mikrotik 941-2nD, Mikrotik 951G-2HnD

