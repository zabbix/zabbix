
# Cisco UCS SNMP

## Overview

For Zabbix version: 6.0 and higher  
for Cisco UCS via Integrated Management Controller

This template was tested on:

- Cisco UCS C240 M4SX

## Setup

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$DISK_ARRAY_CACHE_BATTERY_CRIT_STATUS} |<p>-</p> |`2` |
|{$DISK_ARRAY_CACHE_BATTERY_OK_STATUS} |<p>-</p> |`1` |
|{$DISK_ARRAY_CRIT_STATUS:"inoperable"} |<p>-</p> |`2` |
|{$DISK_ARRAY_OK_STATUS:"operable"} |<p>-</p> |`1` |
|{$DISK_ARRAY_WARN_STATUS:"degraded"} |<p>-</p> |`3` |
|{$DISK_CRIT_STATUS:"bad"} |<p>-</p> |`16` |
|{$DISK_CRIT_STATUS:"predictiveFailure"} |<p>-</p> |`11` |
|{$DISK_FAIL_STATUS:"failed"} |<p>-</p> |`9` |
|{$FAN_CRIT_STATUS:"inoperable"} |<p>-</p> |`2` |
|{$FAN_WARN_STATUS:"degraded"} |<p>-</p> |`3` |
|{$HEALTH_CRIT_STATUS:"computeFailed"} |<p>-</p> |`30` |
|{$HEALTH_CRIT_STATUS:"configFailure"} |<p>-</p> |`33` |
|{$HEALTH_CRIT_STATUS:"inoperable"} |<p>-</p> |`60` |
|{$HEALTH_CRIT_STATUS:"unconfigFailure"} |<p>-</p> |`34` |
|{$HEALTH_WARN_STATUS:"diagnosticsFailed"} |<p>-</p> |`204` |
|{$HEALTH_WARN_STATUS:"powerProblem"} |<p>-</p> |`62` |
|{$HEALTH_WARN_STATUS:"testFailed"} |<p>-</p> |`35` |
|{$HEALTH_WARN_STATUS:"thermalProblem"} |<p>-</p> |`60` |
|{$HEALTH_WARN_STATUS:"voltageProblem"} |<p>-</p> |`62` |
|{$PSU_CRIT_STATUS:"inoperable"} |<p>-</p> |`2` |
|{$PSU_WARN_STATUS:"degraded"} |<p>-</p> |`3` |
|{$TEMP_CRIT:"Ambient"} |<p>-</p> |`35` |
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT} |<p>-</p> |`60` |
|{$TEMP_WARN:"Ambient"} |<p>-</p> |`30` |
|{$TEMP_WARN} |<p>-</p> |`50` |
|{$VDISK_OK_STATUS:"equipped"} |<p>-</p> |`10` |

## Template links

|Name|
|----|
|Generic SNMP |

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Temperature Discovery |<p>-</p> |SNMP |temp.discovery |
|Temperature CPU Discovery |<p>-</p> |SNMP |temp.cpu.discovery |
|PSU Discovery |<p>-</p> |SNMP |psu.discovery |
|Unit Discovery |<p>-</p> |SNMP |unit.discovery |
|FAN Discovery |<p>-</p> |SNMP |fan.discovery |
|Physical Disk Discovery |<p>Scanning table of physical drive entries CISCO-UNIFIED-COMPUTING-STORAGE-MIB::cucsStorageLocalDiskTable.</p> |SNMP |physicalDisk.discovery |
|Virtual Disk Discovery |<p>CISCO-UNIFIED-COMPUTING-STORAGE-MIB::cucsStorageLocalLunTable</p> |SNMP |virtualdisk.discovery |
|Array Controller Discovery |<p>Scanning table of Array controllers: CISCO-UNIFIED-COMPUTING-STORAGE-MIB::cucsStorageControllerTable.</p> |SNMP |array.discovery |
|Array Controller Cache Discovery |<p>Scanning table of Array controllers: CISCO-UNIFIED-COMPUTING-STORAGE-MIB::cucsStorageControllerTable.</p> |SNMP |array.cache.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Disk_arrays |{#DISKARRAY_LOCATION}: Disk array controller status |<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p> |SNMP |system.hw.diskarray.status[cucsStorageControllerOperState.{#SNMPINDEX}] |
|Disk_arrays |{#DISKARRAY_LOCATION}: Disk array controller model |<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p> |SNMP |system.hw.diskarray.model[cucsStorageControllerModel.{#SNMPINDEX}] |
|Disk_arrays |{#DISKARRAY_CACHE_LOCATION}: Disk array cache controller battery status |<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p> |SNMP |system.hw.diskarray.cache.battery.status[cucsStorageRaidBatteryOperability.{#SNMPINDEX}] |
|Fans |{#FAN_LOCATION}: Fan status |<p>MIB: CISCO-UNIFIED-COMPUTING-EQUIPMENT-MIB</p><p>Cisco UCS equipment:Fan:operState managed object property</p> |SNMP |sensor.fan.status[cucsEquipmentFanOperState.{#SNMPINDEX}] |
|Inventory |{#UNIT_LOCATION}: Hardware model name |<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnit:model managed object property</p> |SNMP |system.hw.model[cucsComputeRackUnitModel.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |{#UNIT_LOCATION}: Hardware serial number |<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnit:serial managed object property</p> |SNMP |system.hw.serialnumber[cucsComputeRackUnitSerial.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Physical_disks |{#DISK_LOCATION}: Physical disk status |<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalDisk:diskState managed object property.</p> |SNMP |system.hw.physicaldisk.status[cucsStorageLocalDiskDiskState.{#SNMPINDEX}] |
|Physical_disks |{#DISK_LOCATION}: Physical disk model name |<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalDisk:serial managed object property. Actually returns part number code</p> |SNMP |system.hw.physicaldisk.model[cucsStorageLocalDiskSerial.{#SNMPINDEX}] |
|Physical_disks |{#DISK_LOCATION}: Physical disk media type |<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalDisk:model managed object property. Actually returns 'HDD' or 'SSD'</p> |SNMP |system.hw.physicaldisk.media_type[cucsStorageLocalDiskModel.{#SNMPINDEX}] |
|Physical_disks |{#DISK_LOCATION}: Disk size |<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalDisk:size managed object property. In MB.</p> |SNMP |system.hw.physicaldisk.size[cucsStorageLocalDiskSize.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |
|Power_supply |{#PSU_LOCATION}: Power supply status |<p>MIB: CISCO-UNIFIED-COMPUTING-EQUIPMENT-MIB</p><p>Cisco UCS equipment:Psu:operState managed object property</p> |SNMP |sensor.psu.status[cucsEquipmentPsuOperState.{#SNMPINDEX}] |
|Status |{#UNIT_LOCATION}: Overall system health status |<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnit:operState managed object property</p> |SNMP |system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}] |
|Temperature |{#SENSOR_LOCATION}.Ambient: Temperature |<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Temperature readings of testpoint: {#SENSOR_LOCATION}.Ambient</p> |SNMP |sensor.temp.value[cucsComputeRackUnitMbTempStatsAmbientTemp.{#SNMPINDEX}] |
|Temperature |{#SENSOR_LOCATION}.Front: Temperature |<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnitMbTempStats:frontTemp managed object property</p> |SNMP |sensor.temp.value[cucsComputeRackUnitMbTempStatsFrontTemp.{#SNMPINDEX}] |
|Temperature |{#SENSOR_LOCATION}.Rear: Temperature |<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnitMbTempStats:rearTemp managed object property</p> |SNMP |sensor.temp.value[cucsComputeRackUnitMbTempStatsRearTemp.{#SNMPINDEX}] |
|Temperature |{#SENSOR_LOCATION}.IOH: Temperature |<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnitMbTempStats:ioh1Temp managed object property</p> |SNMP |sensor.temp.value[cucsComputeRackUnitMbTempSltatsIoh1Temp.{#SNMPINDEX}] |
|Temperature |{#SENSOR_LOCATION}: Temperature |<p>MIB: CISCO-UNIFIED-COMPUTING-PROCESSOR-MIB</p><p>Cisco UCS processor:EnvStats:temperature managed object property</p> |SNMP |sensor.temp.value[cucsProcessorEnvStatsTemperature.{#SNMPINDEX}] |
|Virtual_disks |{#VDISK_LOCATION}: Status |<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalLun:presence managed object property</p> |SNMP |system.hw.virtualdisk.status[cucsStorageLocalLunPresence.{#SNMPINDEX}] |
|Virtual_disks |{#VDISK_LOCATION}: Layout type  |<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalLun:type managed object property</p> |SNMP |system.hw.virtualdisk.layout[cucsStorageLocalLunType.{#SNMPINDEX}] |
|Virtual_disks |{#VDISK_LOCATION}: Disk size |<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalLun:size managed object property in MB.</p> |SNMP |system.hw.virtualdisk.size[cucsStorageLocalLunSize.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#DISKARRAY_LOCATION}: Disk array controller is in critical state |<p>Please check the device for faults</p> |`count(/Cisco UCS SNMP/system.hw.diskarray.status[cucsStorageControllerOperState.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_CRIT_STATUS:\"inoperable\"}")=1` |HIGH | |
|{#DISKARRAY_LOCATION}: Disk array controller is in warning state |<p>Please check the device for faults</p> |`count(/Cisco UCS SNMP/system.hw.diskarray.status[cucsStorageControllerOperState.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_WARN_STATUS:\"degraded\"}")=1` |AVERAGE |<p>**Depends on**:</p><p>- {#DISKARRAY_LOCATION}: Disk array controller is in critical state</p> |
|{#DISKARRAY_LOCATION}: Disk array controller is not in optimal state |<p>Please check the device for faults</p> |`count(/Cisco UCS SNMP/system.hw.diskarray.status[cucsStorageControllerOperState.{#SNMPINDEX}],#1,"ne","{$DISK_ARRAY_OK_STATUS:\"operable\"}")=1` |WARNING |<p>**Depends on**:</p><p>- {#DISKARRAY_LOCATION}: Disk array controller is in critical state</p><p>- {#DISKARRAY_LOCATION}: Disk array controller is in warning state</p> |
|{#DISKARRAY_CACHE_LOCATION}: Disk array cache controller battery is in critical state! |<p>Please check the device for faults</p> |`count(/Cisco UCS SNMP/system.hw.diskarray.cache.battery.status[cucsStorageRaidBatteryOperability.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_CACHE_BATTERY_CRIT_STATUS}")=1` |AVERAGE | |
|{#DISKARRAY_CACHE_LOCATION}: Disk array cache controller battery is not in optimal state |<p>Please check the device for faults</p> |`count(/Cisco UCS SNMP/system.hw.diskarray.cache.battery.status[cucsStorageRaidBatteryOperability.{#SNMPINDEX}],#1,"ne","{$DISK_ARRAY_CACHE_BATTERY_OK_STATUS}")=1` |WARNING |<p>**Depends on**:</p><p>- {#DISKARRAY_CACHE_LOCATION}: Disk array cache controller battery is in critical state!</p> |
|{#FAN_LOCATION}: Fan is in critical state |<p>Please check the fan unit</p> |`count(/Cisco UCS SNMP/sensor.fan.status[cucsEquipmentFanOperState.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"inoperable\"}")=1` |AVERAGE | |
|{#FAN_LOCATION}: Fan is in warning state |<p>Please check the fan unit</p> |`count(/Cisco UCS SNMP/sensor.fan.status[cucsEquipmentFanOperState.{#SNMPINDEX}],#1,"eq","{$FAN_WARN_STATUS:\"degraded\"}")=1` |WARNING |<p>**Depends on**:</p><p>- {#FAN_LOCATION}: Fan is in critical state</p> |
|{#UNIT_LOCATION}: Device has been replaced (new serial number received) |<p>Device serial number has changed. Ack to close</p> |`last(/Cisco UCS SNMP/system.hw.serialnumber[cucsComputeRackUnitSerial.{#SNMPINDEX}],#1)<>last(/Cisco UCS SNMP/system.hw.serialnumber[cucsComputeRackUnitSerial.{#SNMPINDEX}],#2) and length(last(/Cisco UCS SNMP/system.hw.serialnumber[cucsComputeRackUnitSerial.{#SNMPINDEX}]))>0` |INFO |<p>Manual close: YES</p> |
|{#DISK_LOCATION}: Physical disk failed |<p>Please check physical disk for warnings or errors</p> |`count(/Cisco UCS SNMP/system.hw.physicaldisk.status[cucsStorageLocalDiskDiskState.{#SNMPINDEX}],#1,"eq","{$DISK_FAIL_STATUS:\"failed\"}")=1` |HIGH | |
|{#DISK_LOCATION}: Physical disk error |<p>Please check physical disk for warnings or errors</p> |`count(/Cisco UCS SNMP/system.hw.physicaldisk.status[cucsStorageLocalDiskDiskState.{#SNMPINDEX}],#1,"eq","{$DISK_CRIT_STATUS:\"bad\"}")=1 or count(/Cisco UCS SNMP/system.hw.physicaldisk.status[cucsStorageLocalDiskDiskState.{#SNMPINDEX}],#1,"eq","{$DISK_CRIT_STATUS:\"predictiveFailure\"}")=1` |AVERAGE |<p>**Depends on**:</p><p>- {#DISK_LOCATION}: Physical disk failed</p> |
|{#PSU_LOCATION}: Power supply is in critical state |<p>Please check the power supply unit for errors</p> |`count(/Cisco UCS SNMP/sensor.psu.status[cucsEquipmentPsuOperState.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS:\"inoperable\"}")=1` |AVERAGE | |
|{#PSU_LOCATION}: Power supply is in warning state |<p>Please check the power supply unit for errors</p> |`count(/Cisco UCS SNMP/sensor.psu.status[cucsEquipmentPsuOperState.{#SNMPINDEX}],#1,"eq","{$PSU_WARN_STATUS:\"degraded\"}")=1` |WARNING |<p>**Depends on**:</p><p>- {#PSU_LOCATION}: Power supply is in critical state</p> |
|{#UNIT_LOCATION}: System status is in critical state |<p>Please check the device for errors</p> |`count(/Cisco UCS SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_CRIT_STATUS:\"computeFailed\"}")=1 or count(/Cisco UCS SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_CRIT_STATUS:\"configFailure\"}")=1 or count(/Cisco UCS SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_CRIT_STATUS:\"unconfigFailure\"}")=1 or count(/Cisco UCS SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_CRIT_STATUS:\"inoperable\"}")=1` |HIGH | |
|{#UNIT_LOCATION}: System status is in warning state |<p>Please check the device for warnings</p> |`count(/Cisco UCS SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_WARN_STATUS:\"testFailed\"}")=1 or count(/Cisco UCS SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_WARN_STATUS:\"thermalProblem\"}")=1 or count(/Cisco UCS SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_WARN_STATUS:\"powerProblem\"}")=1 or count(/Cisco UCS SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_WARN_STATUS:\"voltageProblem\"}")=1 or count(/Cisco UCS SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_WARN_STATUS:\"diagnosticsFailed\"}")=1` |WARNING |<p>**Depends on**:</p><p>- {#UNIT_LOCATION}: System status is in critical state</p> |
|{#SENSOR_LOCATION}.Ambient: Temperature is above warning threshold: >{$TEMP_WARN:"Ambient"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsAmbientTemp.{#SNMPINDEX}],5m)>{$TEMP_WARN:"Ambient"}`<p>Recovery expression:</p>`max(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsAmbientTemp.{#SNMPINDEX}],5m)<{$TEMP_WARN:"Ambient"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_LOCATION}.Ambient: Temperature is above critical threshold: >{$TEMP_CRIT:"Ambient"}</p> |
|{#SENSOR_LOCATION}.Ambient: Temperature is above critical threshold: >{$TEMP_CRIT:"Ambient"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsAmbientTemp.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"Ambient"}`<p>Recovery expression:</p>`max(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsAmbientTemp.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"Ambient"}-3` |HIGH | |
|{#SENSOR_LOCATION}.Ambient: Temperature is too low: <{$TEMP_CRIT_LOW:"Ambient"} |<p>-</p> |`avg(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsAmbientTemp.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"Ambient"}`<p>Recovery expression:</p>`min(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsAmbientTemp.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"Ambient"}+3` |AVERAGE | |
|{#SENSOR_LOCATION}.Front: Temperature is above warning threshold: >{$TEMP_WARN:"Ambient"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsFrontTemp.{#SNMPINDEX}],5m)>{$TEMP_WARN:"Ambient"}`<p>Recovery expression:</p>`max(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsFrontTemp.{#SNMPINDEX}],5m)<{$TEMP_WARN:"Ambient"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_LOCATION}.Front: Temperature is above critical threshold: >{$TEMP_CRIT:"Ambient"}</p> |
|{#SENSOR_LOCATION}.Front: Temperature is above critical threshold: >{$TEMP_CRIT:"Ambient"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsFrontTemp.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"Ambient"}`<p>Recovery expression:</p>`max(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsFrontTemp.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"Ambient"}-3` |HIGH | |
|{#SENSOR_LOCATION}.Front: Temperature is too low: <{$TEMP_CRIT_LOW:"Ambient"} |<p>-</p> |`avg(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsFrontTemp.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"Ambient"}`<p>Recovery expression:</p>`min(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsFrontTemp.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"Ambient"}+3` |AVERAGE | |
|{#SENSOR_LOCATION}.Rear: Temperature is above warning threshold: >{$TEMP_WARN:"Ambient"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsRearTemp.{#SNMPINDEX}],5m)>{$TEMP_WARN:"Ambient"}`<p>Recovery expression:</p>`max(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsRearTemp.{#SNMPINDEX}],5m)<{$TEMP_WARN:"Ambient"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_LOCATION}.Rear: Temperature is above critical threshold: >{$TEMP_CRIT:"Ambient"}</p> |
|{#SENSOR_LOCATION}.Rear: Temperature is above critical threshold: >{$TEMP_CRIT:"Ambient"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsRearTemp.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"Ambient"}`<p>Recovery expression:</p>`max(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsRearTemp.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"Ambient"}-3` |HIGH | |
|{#SENSOR_LOCATION}.Rear: Temperature is too low: <{$TEMP_CRIT_LOW:"Ambient"} |<p>-</p> |`avg(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsRearTemp.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"Ambient"}`<p>Recovery expression:</p>`min(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsRearTemp.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"Ambient"}+3` |AVERAGE | |
|{#SENSOR_LOCATION}.IOH: Temperature is above warning threshold: >{$TEMP_WARN:"Ambient"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempSltatsIoh1Temp.{#SNMPINDEX}],5m)>{$TEMP_WARN:"Ambient"}`<p>Recovery expression:</p>`max(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempSltatsIoh1Temp.{#SNMPINDEX}],5m)<{$TEMP_WARN:"Ambient"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_LOCATION}.IOH: Temperature is above critical threshold: >{$TEMP_CRIT:"Ambient"}</p> |
|{#SENSOR_LOCATION}.IOH: Temperature is above critical threshold: >{$TEMP_CRIT:"Ambient"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempSltatsIoh1Temp.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"Ambient"}`<p>Recovery expression:</p>`max(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempSltatsIoh1Temp.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"Ambient"}-3` |HIGH | |
|{#SENSOR_LOCATION}.IOH: Temperature is too low: <{$TEMP_CRIT_LOW:"Ambient"} |<p>-</p> |`avg(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempSltatsIoh1Temp.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"Ambient"}`<p>Recovery expression:</p>`min(/Cisco UCS SNMP/sensor.temp.value[cucsComputeRackUnitMbTempSltatsIoh1Temp.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"Ambient"}+3` |AVERAGE | |
|{#SENSOR_LOCATION}: Temperature is above warning threshold: >{$TEMP_WARN:"CPU"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco UCS SNMP/sensor.temp.value[cucsProcessorEnvStatsTemperature.{#SNMPINDEX}],5m)>{$TEMP_WARN:"CPU"}`<p>Recovery expression:</p>`max(/Cisco UCS SNMP/sensor.temp.value[cucsProcessorEnvStatsTemperature.{#SNMPINDEX}],5m)<{$TEMP_WARN:"CPU"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_LOCATION}: Temperature is above critical threshold: >{$TEMP_CRIT:"CPU"}</p> |
|{#SENSOR_LOCATION}: Temperature is above critical threshold: >{$TEMP_CRIT:"CPU"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco UCS SNMP/sensor.temp.value[cucsProcessorEnvStatsTemperature.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"CPU"}`<p>Recovery expression:</p>`max(/Cisco UCS SNMP/sensor.temp.value[cucsProcessorEnvStatsTemperature.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"CPU"}-3` |HIGH | |
|{#SENSOR_LOCATION}: Temperature is too low: <{$TEMP_CRIT_LOW:"CPU"} |<p>-</p> |`avg(/Cisco UCS SNMP/sensor.temp.value[cucsProcessorEnvStatsTemperature.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"CPU"}`<p>Recovery expression:</p>`min(/Cisco UCS SNMP/sensor.temp.value[cucsProcessorEnvStatsTemperature.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"CPU"}+3` |AVERAGE | |
|{#VDISK_LOCATION}: Virtual disk is not in OK state |<p>Please check virtual disk for warnings or errors</p> |`count(/Cisco UCS SNMP/system.hw.virtualdisk.status[cucsStorageLocalLunPresence.{#SNMPINDEX}],#1,"ne","{$VDISK_OK_STATUS:\"equipped\"}")=1` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

