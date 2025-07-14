
# Cisco UCS by SNMP

## Overview

Template for Cisco UCS monitoring via Integrated Management Controller

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Cisco UCS C240 M4SX

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PSU_CRIT_STATUS:"inoperable"}||`2`|
|{$PSU_WARN_STATUS:"degraded"}||`3`|
|{$FAN_CRIT_STATUS:"inoperable"}||`2`|
|{$FAN_WARN_STATUS:"degraded"}||`3`|
|{$TEMP_CRIT:"Ambient"}||`35`|
|{$TEMP_WARN:"Ambient"}||`30`|
|{$DISK_ARRAY_CACHE_BATTERY_OK_STATUS}||`1`|
|{$DISK_ARRAY_CACHE_BATTERY_CRIT_STATUS}||`2`|
|{$DISK_ARRAY_CRIT_STATUS:"inoperable"}||`2`|
|{$DISK_ARRAY_WARN_STATUS:"degraded"}||`3`|
|{$DISK_ARRAY_OK_STATUS:"operable"}||`1`|
|{$DISK_FAIL_STATUS:"failed"}||`9`|
|{$DISK_CRIT_STATUS:"predictiveFailure"}||`11`|
|{$DISK_CRIT_STATUS:"bad"}||`16`|
|{$VDISK_OK_STATUS:"equipped"}||`10`|
|{$HEALTH_CRIT_STATUS:"computeFailed"}||`30`|
|{$HEALTH_CRIT_STATUS:"configFailure"}||`33`|
|{$HEALTH_CRIT_STATUS:"unconfigFailure"}||`34`|
|{$HEALTH_CRIT_STATUS:"inoperable"}||`60`|
|{$HEALTH_WARN_STATUS:"testFailed"}||`35`|
|{$HEALTH_WARN_STATUS:"thermalProblem"}||`60`|
|{$HEALTH_WARN_STATUS:"powerProblem"}||`62`|
|{$HEALTH_WARN_STATUS:"voltageProblem"}||`62`|
|{$HEALTH_WARN_STATUS:"diagnosticsFailed"}||`204`|
|{$TEMP_CRIT}||`60`|
|{$TEMP_CRIT_LOW}||`5`|
|{$TEMP_WARN}||`50`|
|{$SNMP.TIMEOUT}|<p>Time interval for the SNMP availability trigger.</p>|`5m`|
|{$ICMP_LOSS_WARN}|<p>Warning threshold of ICMP packet loss in %.</p>|`20`|
|{$ICMP_RESPONSE_TIME_WARN}|<p>Warning threshold of the average ICMP response time in seconds.</p>|`0.15`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Uptime (network)|<p>MIB: SNMPv2-MIB</p><p>Time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p>|SNMP agent|system.net.uptime[sysUpTime.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Uptime (hardware)|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p>|SNMP agent|system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|SNMP traps (fallback)|<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p>|SNMP trap|snmptrap.fallback|
|System location|<p>MIB: SNMPv2-MIB</p><p>Physical location of the node (e.g., `equipment room`, `3rd floor`). If not provided, the value is a zero-length string.</p>|SNMP agent|system.location[sysLocation.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System contact details|<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person. If no contact information is known, the value is the zero-length string.</p>|SNMP agent|system.contact[sysContact.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity. This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p>|SNMP agent|system.objectid[sysObjectID.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name. If the name is unknown, the value is the zero-length string.</p>|SNMP agent|system.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System description|<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p>|SNMP agent|system.descr[sysDescr.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|SNMP agent availability|<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible values:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p>|Zabbix internal|zabbix[host,snmp,available]|
|ICMP ping|<p>The host accessibility by ICMP ping.</p><p></p><p>0 - ICMP ping fails;</p><p>1 - ICMP ping successful.</p>|Simple check|icmpping|
|ICMP loss|<p>The percentage of lost packets.</p>|Simple check|icmppingloss|
|ICMP response time|<p>The ICMP ping response time (in seconds).</p>|Simple check|icmppingsec|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco UCS: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Cisco UCS by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Cisco UCS by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Cisco UCS by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Cisco UCS by SNMP/system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Cisco UCS: No SNMP data collection</li></ul>|
|Cisco UCS: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/Cisco UCS by SNMP/system.name,#1)<>last(/Cisco UCS by SNMP/system.name,#2) and length(last(/Cisco UCS by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|Cisco UCS: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/Cisco UCS by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>Cisco UCS: Unavailable by ICMP ping</li></ul>|
|Cisco UCS: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/Cisco UCS by SNMP/icmpping,#3)=0`|High||
|Cisco UCS: High ICMP ping loss|<p>ICMP packets loss detected.</p>|`min(/Cisco UCS by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Cisco UCS by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>Cisco UCS: Unavailable by ICMP ping</li></ul>|
|Cisco UCS: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/Cisco UCS by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>Cisco UCS: High ICMP ping loss</li><li>Cisco UCS: Unavailable by ICMP ping</li></ul>|

### LLD rule Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature Discovery||SNMP agent|temp.discovery|

### Item prototypes for Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SENSOR_LOCATION}.Ambient: Temperature|<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Temperature readings of testpoint: {#SENSOR_LOCATION}.Ambient</p>|SNMP agent|sensor.temp.value[cucsComputeRackUnitMbTempStatsAmbientTemp.{#SNMPINDEX}]|
|{#SENSOR_LOCATION}.Front: Temperature|<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnitMbTempStats:frontTemp managed object property</p>|SNMP agent|sensor.temp.value[cucsComputeRackUnitMbTempStatsFrontTemp.{#SNMPINDEX}]|
|{#SENSOR_LOCATION}.Rear: Temperature|<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnitMbTempStats:rearTemp managed object property</p>|SNMP agent|sensor.temp.value[cucsComputeRackUnitMbTempStatsRearTemp.{#SNMPINDEX}]|
|{#SENSOR_LOCATION}.IOH: Temperature|<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnitMbTempStats:ioh1Temp managed object property</p>|SNMP agent|sensor.temp.value[cucsComputeRackUnitMbTempSltatsIoh1Temp.{#SNMPINDEX}]|

### Trigger prototypes for Temperature Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco UCS: {#SENSOR_LOCATION}.Ambient: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsAmbientTemp.{#SNMPINDEX}],5m)>{$TEMP_WARN:"Ambient"}`|Warning|**Depends on**:<br><ul><li>Cisco UCS: {#SENSOR_LOCATION}.Ambient: Temperature is above critical threshold</li></ul>|
|Cisco UCS: {#SENSOR_LOCATION}.Ambient: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsAmbientTemp.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"Ambient"}`|High||
|Cisco UCS: {#SENSOR_LOCATION}.Ambient: Temperature is too low||`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsAmbientTemp.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"Ambient"}`|Average||
|Cisco UCS: {#SENSOR_LOCATION}.Front: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsFrontTemp.{#SNMPINDEX}],5m)>{$TEMP_WARN:"Ambient"}`|Warning|**Depends on**:<br><ul><li>Cisco UCS: {#SENSOR_LOCATION}.Front: Temperature is above critical threshold</li></ul>|
|Cisco UCS: {#SENSOR_LOCATION}.Front: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsFrontTemp.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"Ambient"}`|High||
|Cisco UCS: {#SENSOR_LOCATION}.Front: Temperature is too low||`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsFrontTemp.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"Ambient"}`|Average||
|Cisco UCS: {#SENSOR_LOCATION}.Rear: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsRearTemp.{#SNMPINDEX}],5m)>{$TEMP_WARN:"Ambient"}`|Warning|**Depends on**:<br><ul><li>Cisco UCS: {#SENSOR_LOCATION}.Rear: Temperature is above critical threshold</li></ul>|
|Cisco UCS: {#SENSOR_LOCATION}.Rear: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsRearTemp.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"Ambient"}`|High||
|Cisco UCS: {#SENSOR_LOCATION}.Rear: Temperature is too low||`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempStatsRearTemp.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"Ambient"}`|Average||
|Cisco UCS: {#SENSOR_LOCATION}.IOH: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempSltatsIoh1Temp.{#SNMPINDEX}],5m)>{$TEMP_WARN:"Ambient"}`|Warning|**Depends on**:<br><ul><li>Cisco UCS: {#SENSOR_LOCATION}.IOH: Temperature is above critical threshold</li></ul>|
|Cisco UCS: {#SENSOR_LOCATION}.IOH: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempSltatsIoh1Temp.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"Ambient"}`|High||
|Cisco UCS: {#SENSOR_LOCATION}.IOH: Temperature is too low||`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsComputeRackUnitMbTempSltatsIoh1Temp.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"Ambient"}`|Average||

### LLD rule Temperature CPU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature CPU Discovery||SNMP agent|temp.cpu.discovery|

### Item prototypes for Temperature CPU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SENSOR_LOCATION}: Temperature|<p>MIB: CISCO-UNIFIED-COMPUTING-PROCESSOR-MIB</p><p>Cisco UCS processor:EnvStats:temperature managed object property</p>|SNMP agent|sensor.temp.value[cucsProcessorEnvStatsTemperature.{#SNMPINDEX}]|

### Trigger prototypes for Temperature CPU Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco UCS: {#SENSOR_LOCATION}: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsProcessorEnvStatsTemperature.{#SNMPINDEX}],5m)>{$TEMP_WARN:"CPU"}`|Warning|**Depends on**:<br><ul><li>Cisco UCS: {#SENSOR_LOCATION}: Temperature is above critical threshold</li></ul>|
|Cisco UCS: {#SENSOR_LOCATION}: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsProcessorEnvStatsTemperature.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"CPU"}`|High||
|Cisco UCS: {#SENSOR_LOCATION}: Temperature is too low||`avg(/Cisco UCS by SNMP/sensor.temp.value[cucsProcessorEnvStatsTemperature.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"CPU"}`|Average||

### LLD rule PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU Discovery||SNMP agent|psu.discovery|

### Item prototypes for PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#PSU_LOCATION}: Power supply status|<p>MIB: CISCO-UNIFIED-COMPUTING-EQUIPMENT-MIB</p><p>Cisco UCS equipment:Psu:operState managed object property</p>|SNMP agent|sensor.psu.status[cucsEquipmentPsuOperState.{#SNMPINDEX}]|

### Trigger prototypes for PSU Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco UCS: {#PSU_LOCATION}: Power supply is in critical state|<p>Please check the power supply unit for errors</p>|`count(/Cisco UCS by SNMP/sensor.psu.status[cucsEquipmentPsuOperState.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS:\"inoperable\"}")=1`|Average||
|Cisco UCS: {#PSU_LOCATION}: Power supply is in warning state|<p>Please check the power supply unit for errors</p>|`count(/Cisco UCS by SNMP/sensor.psu.status[cucsEquipmentPsuOperState.{#SNMPINDEX}],#1,"eq","{$PSU_WARN_STATUS:\"degraded\"}")=1`|Warning|**Depends on**:<br><ul><li>Cisco UCS: {#PSU_LOCATION}: Power supply is in critical state</li></ul>|

### LLD rule Unit Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Unit Discovery||SNMP agent|unit.discovery|

### Item prototypes for Unit Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#UNIT_LOCATION}: Overall system health status|<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnit:operState managed object property</p>|SNMP agent|system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}]|
|{#UNIT_LOCATION}: Hardware model name|<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnit:model managed object property</p>|SNMP agent|system.hw.model[cucsComputeRackUnitModel.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|{#UNIT_LOCATION}: Hardware serial number|<p>MIB: CISCO-UNIFIED-COMPUTING-COMPUTE-MIB</p><p>Cisco UCS compute:RackUnit:serial managed object property</p>|SNMP agent|system.hw.serialnumber[cucsComputeRackUnitSerial.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Unit Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco UCS: {#UNIT_LOCATION}: System status is in critical state|<p>Please check the device for errors</p>|`count(/Cisco UCS by SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_CRIT_STATUS:\"computeFailed\"}")=1 or count(/Cisco UCS by SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_CRIT_STATUS:\"configFailure\"}")=1 or count(/Cisco UCS by SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_CRIT_STATUS:\"unconfigFailure\"}")=1 or count(/Cisco UCS by SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_CRIT_STATUS:\"inoperable\"}")=1`|High||
|Cisco UCS: {#UNIT_LOCATION}: System status is in warning state|<p>Please check the device for warnings</p>|`count(/Cisco UCS by SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_WARN_STATUS:\"testFailed\"}")=1 or count(/Cisco UCS by SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_WARN_STATUS:\"thermalProblem\"}")=1 or count(/Cisco UCS by SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_WARN_STATUS:\"powerProblem\"}")=1 or count(/Cisco UCS by SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_WARN_STATUS:\"voltageProblem\"}")=1 or count(/Cisco UCS by SNMP/system.status[cucsComputeRackUnitOperState.{#SNMPINDEX}],#1,"eq","{$HEALTH_WARN_STATUS:\"diagnosticsFailed\"}")=1`|Warning|**Depends on**:<br><ul><li>Cisco UCS: {#UNIT_LOCATION}: System status is in critical state</li></ul>|
|Cisco UCS: {#UNIT_LOCATION}: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Cisco UCS by SNMP/system.hw.serialnumber[cucsComputeRackUnitSerial.{#SNMPINDEX}],#1)<>last(/Cisco UCS by SNMP/system.hw.serialnumber[cucsComputeRackUnitSerial.{#SNMPINDEX}],#2) and length(last(/Cisco UCS by SNMP/system.hw.serialnumber[cucsComputeRackUnitSerial.{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes|

### LLD rule FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN Discovery||SNMP agent|fan.discovery|

### Item prototypes for FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#FAN_LOCATION}: Fan status|<p>MIB: CISCO-UNIFIED-COMPUTING-EQUIPMENT-MIB</p><p>Cisco UCS equipment:Fan:operState managed object property</p>|SNMP agent|sensor.fan.status[cucsEquipmentFanOperState.{#SNMPINDEX}]|

### Trigger prototypes for FAN Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco UCS: {#FAN_LOCATION}: Fan is in warning state|<p>Please check the fan unit</p>|`count(/Cisco UCS by SNMP/sensor.fan.status[cucsEquipmentFanOperState.{#SNMPINDEX}],#1,"eq","{$FAN_WARN_STATUS:\"degraded\"}")=1`|Warning|**Depends on**:<br><ul><li>Cisco UCS: {#FAN_LOCATION}: Fan is in critical state</li></ul>|
|Cisco UCS: {#FAN_LOCATION}: Fan is in critical state|<p>Please check the fan unit</p>|`count(/Cisco UCS by SNMP/sensor.fan.status[cucsEquipmentFanOperState.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"inoperable\"}")=1`|Average||

### LLD rule Physical Disk Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Physical Disk Discovery|<p>Scanning table of physical drive entries CISCO-UNIFIED-COMPUTING-STORAGE-MIB::cucsStorageLocalDiskTable.</p>|SNMP agent|physicalDisk.discovery|

### Item prototypes for Physical Disk Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DISK_LOCATION}: Physical disk status|<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalDisk:diskState managed object property.</p>|SNMP agent|system.hw.physicaldisk.status[cucsStorageLocalDiskDiskState.{#SNMPINDEX}]|
|{#DISK_LOCATION}: Physical disk model name|<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalDisk:serial managed object property. Actually returns part number code</p>|SNMP agent|system.hw.physicaldisk.model[cucsStorageLocalDiskSerial.{#SNMPINDEX}]|
|{#DISK_LOCATION}: Physical disk media type|<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalDisk:model managed object property. Actually returns 'HDD' or 'SSD'</p>|SNMP agent|system.hw.physicaldisk.media_type[cucsStorageLocalDiskModel.{#SNMPINDEX}]|
|{#DISK_LOCATION}: Disk size|<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalDisk:size managed object property. In MB.</p>|SNMP agent|system.hw.physicaldisk.size[cucsStorageLocalDiskSize.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li></ul>|

### Trigger prototypes for Physical Disk Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco UCS: {#DISK_LOCATION}: Physical disk failed|<p>Please check physical disk for warnings or errors</p>|`count(/Cisco UCS by SNMP/system.hw.physicaldisk.status[cucsStorageLocalDiskDiskState.{#SNMPINDEX}],#1,"eq","{$DISK_FAIL_STATUS:\"failed\"}")=1`|High||
|Cisco UCS: {#DISK_LOCATION}: Physical disk error|<p>Please check physical disk for warnings or errors</p>|`count(/Cisco UCS by SNMP/system.hw.physicaldisk.status[cucsStorageLocalDiskDiskState.{#SNMPINDEX}],#1,"eq","{$DISK_CRIT_STATUS:\"bad\"}")=1 or count(/Cisco UCS by SNMP/system.hw.physicaldisk.status[cucsStorageLocalDiskDiskState.{#SNMPINDEX}],#1,"eq","{$DISK_CRIT_STATUS:\"predictiveFailure\"}")=1`|Average|**Depends on**:<br><ul><li>Cisco UCS: {#DISK_LOCATION}: Physical disk failed</li></ul>|

### LLD rule Virtual Disk Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Virtual Disk Discovery|<p>CISCO-UNIFIED-COMPUTING-STORAGE-MIB::cucsStorageLocalLunTable</p>|SNMP agent|virtualdisk.discovery|

### Item prototypes for Virtual Disk Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#VDISK_LOCATION}: Status|<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalLun:presence managed object property</p>|SNMP agent|system.hw.virtualdisk.status[cucsStorageLocalLunPresence.{#SNMPINDEX}]|
|{#VDISK_LOCATION}: Layout type|<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalLun:type managed object property</p>|SNMP agent|system.hw.virtualdisk.layout[cucsStorageLocalLunType.{#SNMPINDEX}]|
|{#VDISK_LOCATION}: Disk size|<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p><p>Cisco UCS storage:LocalLun:size managed object property in MB.</p>|SNMP agent|system.hw.virtualdisk.size[cucsStorageLocalLunSize.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li></ul>|

### Trigger prototypes for Virtual Disk Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco UCS: {#VDISK_LOCATION}: Virtual disk is not in OK state|<p>Please check virtual disk for warnings or errors</p>|`count(/Cisco UCS by SNMP/system.hw.virtualdisk.status[cucsStorageLocalLunPresence.{#SNMPINDEX}],#1,"ne","{$VDISK_OK_STATUS:\"equipped\"}")=1`|Warning||

### LLD rule Array Controller Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Array Controller Discovery|<p>Scanning table of Array controllers: CISCO-UNIFIED-COMPUTING-STORAGE-MIB::cucsStorageControllerTable.</p>|SNMP agent|array.discovery|

### Item prototypes for Array Controller Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DISKARRAY_LOCATION}: Disk array controller status|<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p>|SNMP agent|system.hw.diskarray.status[cucsStorageControllerOperState.{#SNMPINDEX}]|
|{#DISKARRAY_LOCATION}: Disk array controller model|<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p>|SNMP agent|system.hw.diskarray.model[cucsStorageControllerModel.{#SNMPINDEX}]|

### Trigger prototypes for Array Controller Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco UCS: {#DISKARRAY_LOCATION}: Disk array controller is in critical state|<p>Please check the device for faults</p>|`count(/Cisco UCS by SNMP/system.hw.diskarray.status[cucsStorageControllerOperState.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_CRIT_STATUS:\"inoperable\"}")=1`|High||
|Cisco UCS: {#DISKARRAY_LOCATION}: Disk array controller is in warning state|<p>Please check the device for faults</p>|`count(/Cisco UCS by SNMP/system.hw.diskarray.status[cucsStorageControllerOperState.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_WARN_STATUS:\"degraded\"}")=1`|Average|**Depends on**:<br><ul><li>Cisco UCS: {#DISKARRAY_LOCATION}: Disk array controller is in critical state</li></ul>|
|Cisco UCS: {#DISKARRAY_LOCATION}: Disk array controller is not in optimal state|<p>Please check the device for faults</p>|`count(/Cisco UCS by SNMP/system.hw.diskarray.status[cucsStorageControllerOperState.{#SNMPINDEX}],#1,"ne","{$DISK_ARRAY_OK_STATUS:\"operable\"}")=1`|Warning|**Depends on**:<br><ul><li>Cisco UCS: {#DISKARRAY_LOCATION}: Disk array controller is in critical state</li><li>Cisco UCS: {#DISKARRAY_LOCATION}: Disk array controller is in warning state</li></ul>|

### LLD rule Array Controller Cache Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Array Controller Cache Discovery|<p>Scanning table of Array controllers: CISCO-UNIFIED-COMPUTING-STORAGE-MIB::cucsStorageControllerTable.</p>|SNMP agent|array.cache.discovery|

### Item prototypes for Array Controller Cache Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DISKARRAY_CACHE_LOCATION}: Disk array cache controller battery status|<p>MIB: CISCO-UNIFIED-COMPUTING-STORAGE-MIB</p>|SNMP agent|system.hw.diskarray.cache.battery.status[cucsStorageRaidBatteryOperability.{#SNMPINDEX}]|

### Trigger prototypes for Array Controller Cache Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco UCS: {#DISKARRAY_CACHE_LOCATION}: Disk array cache controller battery is in critical state!|<p>Please check the device for faults</p>|`count(/Cisco UCS by SNMP/system.hw.diskarray.cache.battery.status[cucsStorageRaidBatteryOperability.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_CACHE_BATTERY_CRIT_STATUS}")=1`|Average||
|Cisco UCS: {#DISKARRAY_CACHE_LOCATION}: Disk array cache controller battery is not in optimal state|<p>Please check the device for faults</p>|`count(/Cisco UCS by SNMP/system.hw.diskarray.cache.battery.status[cucsStorageRaidBatteryOperability.{#SNMPINDEX}],#1,"ne","{$DISK_ARRAY_CACHE_BATTERY_OK_STATUS}")=1`|Warning|**Depends on**:<br><ul><li>Cisco UCS: {#DISKARRAY_CACHE_LOCATION}: Disk array cache controller battery is in critical state!</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

