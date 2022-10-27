
# Cisco UCS by SNMP

## Overview

For Zabbix version: 6.2 and higher.
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
|{$ICMP_LOSS_WARN} |<p>-</p> |`20` |
|{$ICMP_RESPONSE_TIME_WARN} |<p>-</p> |`0.15` |
|{$PSU_CRIT_STATUS:"inoperable"} |<p>-</p> |`2` |
|{$PSU_WARN_STATUS:"degraded"} |<p>-</p> |`3` |
|{$SNMP.TIMEOUT} |<p>-</p> |`5m` |
|{$TEMP_CRIT:"Ambient"} |<p>-</p> |`35` |
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT} |<p>-</p> |`60` |
|{$TEMP_WARN:"Ambient"} |<p>-</p> |`30` |
|{$TEMP_WARN} |<p>-</p> |`50` |
|{$VDISK_OK_STATUS:"equipped"} |<p>-</p> |`10` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Array Controller Cache Discovery |<p>Scanning table of Array controllers: CISCO-UNIFIED-COMPUTING-STORAGE-MIB::cucsStorageControllerTable.</p> |SNMP |array.cache.discovery |
|Array Controller Discovery |<p>Scanning table of Array controllers: CISCO-UNIFIED-COMPUTING-STORAGE-MIB::cucsStorageControllerTable.</p> |SNMP |array.discovery |
|FAN Discovery |<p>-</p> |SNMP |fan.discovery |
|Physical Disk Discovery |<p>Scanning table of physical drive entries CISCO-UNIFIED-COMPUTING-STORAGE-MIB::cucsStorageLocalDiskTable.</p> |SNMP |physicalDisk.discovery |
|PSU Discovery |<p>-</p> |SNMP |psu.discovery |
|Temperature CPU Discovery |<p>-</p> |SNMP |temp.cpu.discovery |
|Temperature Discovery |<p>-</p> |SNMP |temp.discovery |
|Unit Discovery |<p>-</p> |SNMP |unit.discovery |
|Virtual Disk Discovery |<p>CISCO-UNIFIED-COMPUTING-STORAGE-MIB::cucsStorageLocalLunTable</p> |SNMP |virtualdisk.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Disk arrays |{#DISKARRAY_LOCATION}: Disk array controller status |<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p> |SNMP |system.hw.diskarray.status[cucsStorageControllerOperState.{#SNMPINDEX}] |
|Disk arrays |{#DISKARRAY_LOCATION}: Disk array controller model |<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p> |SNMP |system.hw.diskarray.model[cucsStorageControllerModel.{#SNMPINDEX}] |
|Disk arrays |{#DISKARRAY_CACHE_LOCATION}: Disk array cache controller battery status |<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p> |SNMP |system.hw.diskarray.cache.battery.status[cucsStorageRaidBatteryOperability.{#SNMPINDEX}] |
|Fans |{#FAN_LOCATION}: Fan status |<p>MIB: CISCO-UNIFIED-COMPUTING-EQUIPMENT-MIB</p><p>Cisco UCS equipment:Fan:operState managed object property</p> |SNMP |sensor.fan.status[cucsEquipmentFanOperState.{#SNMPINDEX}] |
|General |SNMP traps (fallback) |<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p> |SNMP_TRAP |snmptrap.fallback |
|General |System location |<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet, 3rd floor').  If the location is unknown, the value is the zero-length string.</p> |SNMP |system.location[sysLocation.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System contact details |<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</p> |SNMP |system.contact[sysContact.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System object ID |<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity.  This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p> |SNMP |system.objectid[sysObjectID.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System name |<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name.  If the name is unknown, the value is the zero-length string.</p> |SNMP |system.name<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System description |<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p> |SNMP |system.descr[sysDescr.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|Inventory |{#UNIT_LOCATION}: Hardware model name |<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnit:model managed object property</p> |SNMP |system.hw.model[cucsComputeRackUnitModel.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |{#UNIT_LOCATION}: Hardware serial number |<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnit:serial managed object property</p> |SNMP |system.hw.serialnumber[cucsComputeRackUnitSerial.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Physical disks |{#DISK_LOCATION}: Physical disk status |<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalDisk:diskState managed object property.</p> |SNMP |system.hw.physicaldisk.status[cucsStorageLocalDiskDiskState.{#SNMPINDEX}] |
|Physical disks |{#DISK_LOCATION}: Physical disk model name |<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalDisk:serial managed object property. Actually returns part number code</p> |SNMP |system.hw.physicaldisk.model[cucsStorageLocalDiskSerial.{#SNMPINDEX}] |
|Physical disks |{#DISK_LOCATION}: Physical disk media type |<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalDisk:model managed object property. Actually returns 'HDD' or 'SSD'</p> |SNMP |system.hw.physicaldisk.media_type[cucsStorageLocalDiskModel.{#SNMPINDEX}] |
|Physical disks |{#DISK_LOCATION}: Disk size |<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalDisk:size managed object property. In MB.</p> |SNMP |system.hw.physicaldisk.size[cucsStorageLocalDiskSize.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |
|Power supply |{#PSU_LOCATION}: Power supply status |<p>MIB: CISCO-UNIFIED-COMPUTING-EQUIPMENT-MIB</p><p>Cisco UCS equipment:Psu:operState managed object property</p> |SNMP |sensor.psu.status[cucsEquipmentPsuOperState.{#SNMPINDEX}] |
|Status |Uptime (network) |<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p> |SNMP |system.net.uptime[sysUpTime.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Status |Uptime (hardware) |<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p> |SNMP |system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- MULTIPLIER: `0.01`</p> |
|Status |SNMP agent availability |<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible value:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p> |INTERNAL |zabbix[host,snmp,available] |
|Status |ICMP ping |<p>-</p> |SIMPLE |icmpping |
|Status |ICMP loss |<p>-</p> |SIMPLE |icmppingloss |
|Status |ICMP response time |<p>-</p> |SIMPLE |icmppingsec |
|Status |{#UNIT_LOCATION}: Overall system health status |<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnit:operState managed object property</p> |SNMP |system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}] |
|Temperature |{#SENSOR_LOCATION}.Ambient: Temperature |<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Temperature readings of testpoint: {#SENSOR_LOCATION}.Ambient</p> |SNMP |sensor.temp.value[cucsComputeRackUnitMbTempStatsAmbientTemp.{#SNMPINDEX}] |
|Temperature |{#SENSOR_LOCATION}.Front: Temperature |<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnitMbTempStats:frontTemp managed object property</p> |SNMP |sensor.temp.value[cucsComputeRackUnitMbTempStatsFrontTemp.{#SNMPINDEX}] |
|Temperature |{#SENSOR_LOCATION}.Rear: Temperature |<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnitMbTempStats:rearTemp managed object property</p> |SNMP |sensor.temp.value[cucsComputeRackUnitMbTempStatsRearTemp.{#SNMPINDEX}] |
|Temperature |{#SENSOR_LOCATION}.IOH: Temperature |<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnitMbTempStats:ioh1Temp managed object property</p> |SNMP |sensor.temp.value[cucsComputeRackUnitMbTempSltatsIoh1Temp.{#SNMPINDEX}] |
|Temperature |{#SENSOR_LOCATION}: Temperature |<p>MIB: CISCO-UNIFIED-COMPUTING-PROCESSOR-MIB</p><p>Cisco UCS processor:EnvStats:temperature managed object property</p> |SNMP |sensor.temp.value[cucsProcessorEnvStatsTemperature.{#SNMPINDEX}] |
|Virtual disks |{#VDISK_LOCATION}: Status |<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalLun:presence managed object property</p> |SNMP |system.hw.virtualdisk.status[cucsStorageLocalLunPresence.{#SNMPINDEX}] |
|Virtual disks |{#VDISK_LOCATION}: Layout type  |<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalLun:type managed object property</p> |SNMP |system.hw.virtualdisk.layout[cucsStorageLocalLunType.{#SNMPINDEX}] |
|Virtual disks |{#VDISK_LOCATION}: Disk size |<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalLun:size managed object property in MB.</p> |SNMP |system.hw.virtualdisk.size[cucsStorageLocalLunSize.{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#DISKARRAY_LOCATION}: Disk array controller is in critical state |<p>Please check the device for faults</p> |`count(/Cisco UCS by SNMP/system.hw.diskarray.status[cucsStorageControllerOperState.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_CRIT_STATUS:\"inoperable\"}")=1` |HIGH | |
|{#DISKARRAY_LOCATION}: Disk array controller is in warning state |<p>Please check the device for faults</p> |`count(/Cisco UCS by SNMP/system.hw.diskarray.status[cucsStorageControllerOperState.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_WARN_STATUS:\"degraded\"}")=1` |AVERAGE |<p>**Depends on**:</p><p>- {#DISKARRAY_LOCATION}: Disk array controller is in critical state</p> |
|{#DISKARRAY_LOCATION}: Disk array controller is not in optimal state |<p>Please check the device for faults</p> |`count(/Cisco UCS by SNMP/system.hw.diskarray.status[cucsStorageControllerOperState.{#SNMPINDEX}],#1,"ne","{$DISK_ARRAY_OK_STATUS:\"operable\"}")=1` |WARNING |<p>**Depends on**:</p><p>- {#DISKARRAY_LOCATION}: Disk array controller is in critical state</p><p>- {#DISKARRAY_LOCATION}: Disk array controller is in warning state</p> |
|{#DISKARRAY_CACHE_LOCATION}: Disk array cache controller battery is in critical state! |<p>Please check the device for faults</p> |`count(/Cisco UCS by SNMP/system.hw.diskarray.cache.battery.status[cucsStorageRaidBatteryOperability.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_CACHE_BATTERY_CRIT_STATUS}")=1` |AVERAGE | |
|{#DISKARRAY_CACHE_LOCATION}: Disk array cache controller battery is not in optimal state |<p>Please check the device for faults</p> |`count(/Cisco UCS by SNMP/system.hw.diskarray.cache.battery.status[cucsStorageRaidBatteryOperability.{#SNMPINDEX}],#1,"ne","{$DISK_ARRAY_CACHE_BATTERY_OK_STATUS}")=1` |WARNING |<p>**Depends on**:</p><p>- {#DISKARRAY_CACHE_LOCATION}: Disk array cache controller battery is in critical state!</p> |
|{#FAN_LOCATION}: Fan is in critical state |<p>Please check the fan unit</p> |`count(/Cisco UCS by SNMP/sensor.fan.status[cucsEquipmentFanOperState.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"inoperable\"}")=1` |AVERAGE | |
|{#FAN_LOCATION}: Fan is in warning state |<p>Please check the fan unit</p> |`count(/Cisco UCS by SNMP/sensor.fan.status[cucsEquipmentFanOperState.{#SNMPINDEX}],#1,"eq","{$FAN_WARN_STATUS:\"degraded\"}")=1` |WARNING |<p>**Depends on**:</p><p>- {#FAN_LOCATION}: Fan is in critical state</p> |
|System name has changed |<p>System name has changed. Ack to close.</p> |`last(/Cisco UCS by SNMP/system.name,#1)<>last(/Cisco UCS by SNMP/system.name,#2) and length(last(/Cisco UCS by SNMP/system.name))>0` |INFO |<p>Manual close: YES</p> |
|{#UNIT_LOCATION}: Device has been replaced |<p>Device serial number has changed. Ack to close</p> |`last(/Cisco UCS by SNMP/system.hw.serialnumber[cucsComputeRackUnitSerial.{#SNMPINDEX}],#1)<>last(/Cisco UCS by SNMP/system.hw.serialnumber[cucsComputeRackUnitSerial.{#SNMPINDEX}],#2) and length(last(/Cisco UCS by SNMP/system.hw.serialnumber[cucsComputeRackUnitSerial.{#SNMPINDEX}]))>0` |INFO |<p>Manual close: YES</p> |
|{#DISK_LOCATION}: Physical disk failed |<p>Please check physical disk for warnings or errors</p> |`count(/Cisco UCS by SNMP/system.hw.physicaldisk.status[cucsStorageLocalDiskDiskState.{#SNMPINDEX}],#1,"eq","{$DISK_FAIL_STATUS:\"failed\"}")=1` |HIGH | |
|{#DISK_LOCATION}: Physical disk error |<p>Please check physical disk for warnings or errors</p> |`count(/Cisco UCS by SNMP/system.hw.physicaldisk.status[cucsStorageLocalDiskDiskState.{#SNMPINDEX}],#1,"eq","{$DISK_CRIT_STATUS:\"bad\"}")=1 or count(/Cisco UCS by SNMP/system.hw.physicaldisk.status[cucsStorageLocalDiskDiskState.{#SNMPINDEX}],#1,"eq","{$DISK_CRIT_STATUS:\"predictiveFailure\"}")=1` |AVERAGE |<p>**Depends on**:</p><p>- {#DISK_LOCATION}: Physical disk failed</p> |
|{#PSU_LOCATION}: Power supply is in critical state |<p>Please check the power supply unit for errors</p> |`count(/Cisco UCS by SNMP/sensor.psu.status[cucsEquipmentPsuOperState.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS:\"inoperable\"}")=1` |AVERAGE | |
|{#PSU_LOCATION}: Power supply is in warning state |<p>Please check the power supply unit for errors</p> |`count(/Cisco UCS by SNMP/sensor.psu.status[cucsEquipmentPsuOperState.{#SNMPINDEX}],#1,"eq","{$PSU_WARN_STATUS:\"degraded\"}")=1` |WARNING |<p>**Depends on**:</p><p>- {#PSU_LOCATION}: Power supply is in critical state</p> |
|Host has been restarted |<p>Uptime is less than 10 minutes.</p> |`(last(/Cisco UCS by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Cisco UCS by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Cisco UCS by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Cisco UCS by SNMP/system.net.uptime[sysUpTime.0])<10m)` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- No SNMP data collection</p> |
|No SNMP data collection |<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p> |`max(/Cisco UCS by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|Unavailable by ICMP ping |<p>Last three attempts returned timeout.  Please check device connectivity.</p> |`max(/Cisco UCS by SNMP/icmpping,#3)=0` |HIGH | |
|High ICMP ping loss |<p>-</p> |`min(/Cisco UCS by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Cisco UCS by SNMP/icmppingloss,5m)<100` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|High ICMP ping response time |<p>-</p> |`avg(/Cisco UCS by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}` |WARNING |<p>**Depends on**:</p><p>- High ICMP ping loss</p><p>- Unavailable by ICMP ping</p> |
|{#UNIT_LOCATION}: System status is in critical state |<p>Please check the device for errors</p> |`count(/Cisco UCS by SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_CRIT_STATUS:\"computeFailed\"}")=1 or count(/Cisco UCS by SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_CRIT_STATUS:\"configFailure\"}")=1 or count(/Cisco UCS by SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_CRIT_STATUS:\"unconfigFailure\"}")=1 or count(/Cisco UCS by SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_CRIT_STATUS:\"inoperable\"}")=1` |HIGH | |
|{#UNIT_LOCATION}: System status is in warning state |<p>Please check the device for warnings</p> |`count(/Cisco UCS by SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_WARN_STATUS:\"testFailed\"}")=1 or count(/Cisco UCS by SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_WARN_STATUS:\"thermalProblem\"}")=1 or count(/Cisco UCS by SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_WARN_STATUS:\"powerProblem\"}")=1 or count(/Cisco UCS by SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_WARN_STATUS:\"voltageProblem\"}")=1 or count(/Cisco UCS by SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_WARN_STATUS:\"diagnosticsFailed\"}")=1` |WARNING |<p>**Depends on**:</p><p>- {#UNIT_LOCATION}: System status is in critical state</p> |
|{#SENSOR_LOCATION}.Ambient: Temperature is above warning threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsAmbientTemp.{#SNMPINDEX}],5m)>{$TEMP_WARN:"Ambient"}`<p>Recovery expression:</p>`max(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsAmbientTemp.{#SNMPINDEX}],5m)<{$TEMP_WARN:"Ambient"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_LOCATION}.Ambient: Temperature is above critical threshold</p> |
|{#SENSOR_LOCATION}.Ambient: Temperature is above critical threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsAmbientTemp.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"Ambient"}`<p>Recovery expression:</p>`max(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsAmbientTemp.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"Ambient"}-3` |HIGH | |
|{#SENSOR_LOCATION}.Ambient: Temperature is too low |<p>-</p> |`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsAmbientTemp.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"Ambient"}`<p>Recovery expression:</p>`min(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsAmbientTemp.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"Ambient"}+3` |AVERAGE | |
|{#SENSOR_LOCATION}.Front: Temperature is above warning threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsFrontTemp.{#SNMPINDEX}],5m)>{$TEMP_WARN:"Ambient"}`<p>Recovery expression:</p>`max(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsFrontTemp.{#SNMPINDEX}],5m)<{$TEMP_WARN:"Ambient"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_LOCATION}.Front: Temperature is above critical threshold</p> |
|{#SENSOR_LOCATION}.Front: Temperature is above critical threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsFrontTemp.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"Ambient"}`<p>Recovery expression:</p>`max(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsFrontTemp.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"Ambient"}-3` |HIGH | |
|{#SENSOR_LOCATION}.Front: Temperature is too low |<p>-</p> |`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsFrontTemp.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"Ambient"}`<p>Recovery expression:</p>`min(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsFrontTemp.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"Ambient"}+3` |AVERAGE | |
|{#SENSOR_LOCATION}.Rear: Temperature is above warning threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsRearTemp.{#SNMPINDEX}],5m)>{$TEMP_WARN:"Ambient"}`<p>Recovery expression:</p>`max(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsRearTemp.{#SNMPINDEX}],5m)<{$TEMP_WARN:"Ambient"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_LOCATION}.Rear: Temperature is above critical threshold</p> |
|{#SENSOR_LOCATION}.Rear: Temperature is above critical threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsRearTemp.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"Ambient"}`<p>Recovery expression:</p>`max(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsRearTemp.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"Ambient"}-3` |HIGH | |
|{#SENSOR_LOCATION}.Rear: Temperature is too low |<p>-</p> |`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsRearTemp.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"Ambient"}`<p>Recovery expression:</p>`min(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsRearTemp.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"Ambient"}+3` |AVERAGE | |
|{#SENSOR_LOCATION}.IOH: Temperature is above warning threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempSltatsIoh1Temp.{#SNMPINDEX}],5m)>{$TEMP_WARN:"Ambient"}`<p>Recovery expression:</p>`max(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempSltatsIoh1Temp.{#SNMPINDEX}],5m)<{$TEMP_WARN:"Ambient"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_LOCATION}.IOH: Temperature is above critical threshold</p> |
|{#SENSOR_LOCATION}.IOH: Temperature is above critical threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempSltatsIoh1Temp.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"Ambient"}`<p>Recovery expression:</p>`max(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempSltatsIoh1Temp.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"Ambient"}-3` |HIGH | |
|{#SENSOR_LOCATION}.IOH: Temperature is too low |<p>-</p> |`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempSltatsIoh1Temp.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"Ambient"}`<p>Recovery expression:</p>`min(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempSltatsIoh1Temp.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"Ambient"}+3` |AVERAGE | |
|{#SENSOR_LOCATION}: Temperature is above warning threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsProcessorEnvStatsTemperature.{#SNMPINDEX}],5m)>{$TEMP_WARN:"CPU"}`<p>Recovery expression:</p>`max(/Cisco UCS by SNMP/sensor.temp.value[cucsProcessorEnvStatsTemperature.{#SNMPINDEX}],5m)<{$TEMP_WARN:"CPU"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SENSOR_LOCATION}: Temperature is above critical threshold</p> |
|{#SENSOR_LOCATION}: Temperature is above critical threshold |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsProcessorEnvStatsTemperature.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"CPU"}`<p>Recovery expression:</p>`max(/Cisco UCS by SNMP/sensor.temp.value[cucsProcessorEnvStatsTemperature.{#SNMPINDEX}],5m)<{$TEMP_CRIT:"CPU"}-3` |HIGH | |
|{#SENSOR_LOCATION}: Temperature is too low |<p>-</p> |`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsProcessorEnvStatsTemperature.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"CPU"}`<p>Recovery expression:</p>`min(/Cisco UCS by SNMP/sensor.temp.value[cucsProcessorEnvStatsTemperature.{#SNMPINDEX}],5m)>{$TEMP_CRIT_LOW:"CPU"}+3` |AVERAGE | |
|{#VDISK_LOCATION}: Virtual disk is not in OK state |<p>Please check virtual disk for warnings or errors</p> |`count(/Cisco UCS by SNMP/system.hw.virtualdisk.status[cucsStorageLocalLunPresence.{#SNMPINDEX}],#1,"ne","{$VDISK_OK_STATUS:\"equipped\"}")=1` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

