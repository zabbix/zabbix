
# DELL PowerEdge R820 by SNMP

## Overview

This is a template for monitoring DELL PowerEdge R820 servers with iDRAC version 7 (and later) via Zabbix SNMP agent that works without any external scripts.

## Requirements

Zabbix version: 6.0 and higher.

## Tested versions

This template has been tested on:
- DELL PowerEdge R820

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$DELL.SNMP.DISCOVERY.VOLTAGE.NAME.MATCHES}|<p>Sets the regex string of voltage probe names to allow in discovery.</p>|`^.*Voltage.*$`|
|{$DELL.SNMP.DISCOVERY.VOLTAGE.NAME.NOT_MATCHES}|<p>Sets the regex string of voltage probe names to ignore in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$DELL.SNMP.DISCOVERY.VOLTAGE.TYPE.MATCHES}|<p>Sets the regex string of voltage probe types to allow in discovery.</p>|`18\|16`|
|{$DELL.SNMP.DISCOVERY.VOLTAGE.TYPE.NOT_MATCHES}|<p>Sets the regex string of voltage probe types to ignore in discovery.</p>|`CHANGE_IF_NEEDED`|
|{$DELL.SNMP.SENSOR.TEMP.STATUS.OK}|<p>The OK status of the temperature probe for the trigger expression.</p>|`3`|
|{$DELL.SNMP.SENSOR.TEMP.STATUS.WARN:"nonCriticalUpper"}|<p>The warning status of the temperature probe for the trigger expression.</p>|`4`|
|{$DELL.SNMP.SENSOR.TEMP.STATUS.WARN:"nonCriticalLower"}|<p>The warning status of the temperature probe for the trigger expression.</p>|`7`|
|{$DELL.SNMP.SENSOR.TEMP.STATUS.CRIT:"criticalUpper"}|<p>The critical status of the temperature probe for the trigger expression.</p>|`5`|
|{$DELL.SNMP.SENSOR.TEMP.STATUS.CRIT:"nonRecoverableUpper"}|<p>The critical status of the temperature probe for the trigger expression.</p>|`6`|
|{$DELL.SNMP.SENSOR.TEMP.STATUS.CRIT:"criticalLower"}|<p>The critical status of the temperature probe for the trigger expression.</p>|`8`|
|{$DELL.SNMP.SENSOR.TEMP.STATUS.CRIT:"nonRecoverableLower"}|<p>The critical status of the temperature probe for the trigger expression.</p>|`9`|
|{$DELL.SNMP.HEALTH.STATUS.DISASTER}|<p>The disaster status of health for the trigger expression.</p>|`6`|
|{$DELL.SNMP.HEALTH.STATUS.CRIT}|<p>The critical status of health for the trigger expression.</p>|`5`|
|{$DELL.SNMP.HEALTH.STATUS.WARN}|<p>The warning status of health for the trigger expression.</p>|`4`|
|{$DELL.SNMP.PSU.STATUS.WARN:"nonCritical"}|<p>The warning value of the PSU sensor for the trigger expression.</p>|`4`|
|{$DELL.SNMP.PSU.STATUS.CRIT:"critical"}|<p>The critical value of the PSU sensor for the trigger expression.</p>|`5`|
|{$DELL.SNMP.PSU.STATUS.CRIT:"nonRecoverable"}|<p>The critical value of the PSU sensor for the trigger expression.</p>|`6`|
|{$DELL.SNMP.FAN.STATUS.WARN:"nonCriticalUpper"}|<p>The warning value of the FAN sensor for the trigger expression.</p>|`4`|
|{$DELL.SNMP.FAN.STATUS.WARN:"nonCriticalLower"}|<p>The warning value of the FAN sensor for the trigger expression.</p>|`7`|
|{$DELL.SNMP.FAN.STATUS.CRIT:"criticalUpper"}|<p>The critical value of the FAN sensor for the trigger expression.</p>|`5`|
|{$DELL.SNMP.FAN.STATUS.CRIT:"nonRecoverableUpper"}|<p>The critical value of the FAN sensor for the trigger expression.</p>|`6`|
|{$DELL.SNMP.FAN.STATUS.CRIT:"criticalLower"}|<p>The critical value of the FAN sensor for the trigger expression.</p>|`8`|
|{$DELL.SNMP.FAN.STATUS.CRIT:"nonRecoverableLower"}|<p>The critical value of the FAN sensor for the trigger expression.</p>|`9`|
|{$DELL.SNMP.FAN.STATUS.CRIT:"failed"}|<p>The critical value of the FAN sensor for the trigger expression.</p>|`10`|
|{$DELL.SNMP.DISK.ARRAY.STATUS.FAIL}|<p>The disaster status of the disk array for the trigger expression.</p>|`6`|
|{$DELL.SNMP.DISK.ARRAY.STATUS.CRIT}|<p>The critical status of the disk array for the trigger expression.</p>|`5`|
|{$DELL.SNMP.DISK.ARRAY.STATUS.WARN}|<p>The warning status of the disk array for the trigger expression.</p>|`4`|
|{$DELL.SNMP.DISK.ARRAY.CACHE.BATTERY.STATUS.CRIT}|<p>The critical status of the disk array cache battery for the trigger expression.</p>|`3`|
|{$DELL.SNMP.DISK.ARRAY.CACHE.BATTERY.STATUS.WARN}|<p>The warning status of the disk array cache battery for the trigger expression.</p>|`4`|
|{$DELL.SNMP.DISK.ARRAY.CACHE.BATTERY.STATUS.OK}|<p>The OK status of the disk array cache battery for the trigger expression.</p>|`2`|
|{$DELL.SNMP.VDISK.STATUS.CRIT:"failed"}|<p>The critical status of the virtual disk for the trigger expression.</p>|`3`|
|{$DELL.SNMP.VDISK.STATUS.WARN:"degraded"}|<p>The warning status of the virtual disk for the trigger expression.</p>|`4`|
|{$DELL.SNMP.DISK.STATUS.WARN:"nonCritical"}|<p>The warning status of the disk for the trigger expression.</p>|`4`|
|{$DELL.SNMP.DISK.STATUS.FAIL:"critical"}|<p>The critical status of the disk for the trigger expression.</p>|`5`|
|{$DELL.SNMP.DISK.STATUS.FAIL:"nonRecoverable"}|<p>The critical status of the disk for the trigger expression.</p>|`6`|
|{$DELL.SNMP.DISK.SMART.STATUS.FAIL}|<p>The critical S.M.A.R.T status of the disk for the trigger expression.</p>|`1`|
|{$DELL.SNMP.TIMEOUT}|<p>The time interval for the SNMP agent availability trigger expression.</p>|`5m`|
|{$DELL.SNMP.IFCONTROL}|<p>The link status trigger will be fired only for interfaces that have the context macro equal to "1".</p>|`1`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R820: Overall system health status|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the overall rollup status of all the components in the system monitored by the remote access card. Includes system, storage, IO devices, iDRAC, CPU, memory, etc.</p>|SNMP agent|dell.server.status[globalSystemStatus]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: Hardware model name|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the model name of the system.</p>|SNMP agent|dell.server.hw.model[systemModelName]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: Hardware serial number|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the service tag of the system.</p>|SNMP agent|dell.server.hw.serialnumber[systemServiceTag]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: Operating system|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the name of the operating system that the host is running.</p>|SNMP agent|dell.server.sw.os[systemOSName]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Dell R820: Firmware version|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the firmware version of a remote access card.</p>|SNMP agent|dell.server.hw.firmware[racFirmwareVersion]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Dell R820: Uptime (network)|<p>MIB: SNMP-FRAMEWORK-MIB</p><p>The number of seconds since the value of the snmpEngineBoots object last changed.</p>|SNMP agent|dell.server.net.uptime[snmpEngineTime]|
|Dell R820: Uptime (hardware)|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the power-up time of the system in seconds.</p>|SNMP agent|dell.server.hw.uptime[systemPowerUpTime]|
|Dell R820: SNMP traps (fallback)|<p>The item is used to collect all SNMP traps unmatched by other `snmptrap` items</p>|SNMP trap|snmptrap.fallback|
|Dell R820: System location|<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., 'telephone closet, 3rd floor'). If the location is unknown, the value is a zero-length string.</p>|SNMP agent|dell.server.location[sysLocation]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: System contact details|<p>MIB: SNMPv2-MIB</p><p>Name and contact information of the contact person for the node. If not provided, the value is a zero-length string.</p>|SNMP agent|dell.server.contact[sysContact]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Dell R820: System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the entity as part of the vendor's SMI enterprises subtree with the prefix 1.3.6.1.4.1 (e.g., a vendor with the identifier 1.3.6.1.4.1.4242 might assign a system object with the OID 1.3.6.1.4.1.4242.1.1).</p>|SNMP agent|dell.server.objectid[sysObjectID]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node. By convention, this is the node's fully-qualified domain name. If the name is unknown, the value is a zero-length string.</p>|SNMP agent|dell.server.name[sysName]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: System description|<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should include the full name and version identification of the system's hardware type, software operating system, and networking software.</p>|SNMP agent|dell.server.descr[sysDescr]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Dell R820: SNMP agent availability|<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible values:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p>|Zabbix internal|zabbix[host,snmp,available]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: Memory, total size|<p>Total memory amount on the device.</p>|Calculated|dell.server.memory.size.total<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: BIOS version|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the version name of the system BIOS.</p>|SNMP agent|dell.server.bios.version<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R820: System is in unrecoverable state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.status[globalSystemStatus])={$DELL.SNMP.HEALTH.STATUS.DISASTER}`|High||
|Dell R820: System status is in critical state|<p>Please check the device for errors.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.status[globalSystemStatus])={$DELL.SNMP.HEALTH.STATUS.CRIT}`|Average||
|Dell R820: System status is in warning state|<p>Please check the device for warnings.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.status[globalSystemStatus])={$DELL.SNMP.HEALTH.STATUS.WARN}`|Warning|**Depends on**:<br><ul><li>Dell R820: System status is in critical state</li></ul>|
|Dell R820: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.hw.serialnumber[systemServiceTag],#1)<>last(/DELL PowerEdge R820 by SNMP/dell.server.hw.serialnumber[systemServiceTag],#2) and length(last(/DELL PowerEdge R820 by SNMP/dell.server.hw.serialnumber[systemServiceTag]))>0`|Info|**Manual close**: Yes|
|Dell R820: Operating system description has changed|<p>Operating system description has changed. Possibly, the system has been updated or replaced. Acknowledge to close the problem manually.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.sw.os[systemOSName],#1)<>last(/DELL PowerEdge R820 by SNMP/dell.server.sw.os[systemOSName],#2) and length(last(/DELL PowerEdge R820 by SNMP/dell.server.sw.os[systemOSName]))>0`|Info|**Manual close**: Yes|
|Dell R820: Firmware has changed|<p>Firmware version has changed. Acknowledge to close the problem manually.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.hw.firmware[racFirmwareVersion],#1)<>last(/DELL PowerEdge R820 by SNMP/dell.server.hw.firmware[racFirmwareVersion],#2) and length(last(/DELL PowerEdge R820 by SNMP/dell.server.hw.firmware[racFirmwareVersion]))>0`|Info|**Manual close**: Yes|
|Dell R820: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/DELL PowerEdge R820 by SNMP/dell.server.hw.uptime[systemPowerUpTime])>0 and last(/DELL PowerEdge R820 by SNMP/dell.server.hw.uptime[systemPowerUpTime])<10m) or (last(/DELL PowerEdge R820 by SNMP/dell.server.hw.uptime[systemPowerUpTime])=0 and last(/DELL PowerEdge R820 by SNMP/dell.server.net.uptime[snmpEngineTime])<10m)`|Warning|**Manual close**: Yes|
|Dell R820: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.name[sysName],#1)<>last(/DELL PowerEdge R820 by SNMP/dell.server.name[sysName],#2) and length(last(/DELL PowerEdge R820 by SNMP/dell.server.name[sysName]))>0`|Info|**Manual close**: Yes|
|Dell R820: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/DELL PowerEdge R820 by SNMP/zabbix[host,snmp,available],{$DELL.SNMP.TIMEOUT})=0`|Warning||
|Dell R820: Memory amount has changed||`change(/DELL PowerEdge R820 by SNMP/dell.server.memory.size.total)>0`|Average||

### LLD rule Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature discovery|<p>Scanning table of Temperature Probe Table IDRAC-MIB-SMIv2::temperatureProbeTable</p>|SNMP agent|temp.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R820: Probe [{#SENSOR_LOCALE}]: Value|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the reading for a temperature probe of type other than `temperatureProbeTypeIsDiscrete`.</p><p>When the value for `temperatureProbeType` is other than `temperatureProbeTypeIsDiscrete`, the value returned for this attribute is the temperature that the probe is reading in Centigrade.</p><p>When the value for `temperatureProbeType` is `temperatureProbeTypeIsDiscrete`, a value is not returned for this attribute.</p>|SNMP agent|dell.server.sensor.temp.value[temperatureProbeReading.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: Probe [{#SENSOR_LOCALE}]: Status|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the probe status of the temperature probe.</p><p>Possible values:</p><p>other(1),               -- probe status is not one of the following:</p><p>unknown(2),             -- probe status is unknown (not known or monitored)</p><p>ok(3),                  -- probe is reporting a value within the thresholds</p><p>nonCriticalUpper(4),    -- probe has crossed the upper noncritical threshold</p><p>criticalUpper(5),       -- probe has crossed the upper critical threshold</p><p>nonRecoverableUpper(6), -- probe has crossed the upper non-recoverable threshold</p><p>nonCriticalLower(7),    -- probe has crossed the lower noncritical threshold</p><p>criticalLower(8),       -- probe has crossed the lower critical threshold</p><p>nonRecoverableLower(9), -- probe has crossed the lower non-recoverable threshold</p><p>failed(10)              -- probe is not functional</p>|SNMP agent|dell.server.sensor.temp.status[temperatureProbeStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Temperature discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R820: Probe [{#SENSOR_LOCALE}]: Critical status|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.sensor.temp.status[temperatureProbeStatus.{#SNMPINDEX}])={$DELL.SNMP.SENSOR.TEMP.STATUS.CRIT:"criticalUpper"} or last(/DELL PowerEdge R820 by SNMP/dell.server.sensor.temp.status[temperatureProbeStatus.{#SNMPINDEX}])={$DELL.SNMP.SENSOR.TEMP.STATUS.CRIT:"nonRecoverableUpper"} or last(/DELL PowerEdge R820 by SNMP/dell.server.sensor.temp.status[temperatureProbeStatus.{#SNMPINDEX}])={$DELL.SNMP.SENSOR.TEMP.STATUS.CRIT:"criticalLower"} or last(/DELL PowerEdge R820 by SNMP/dell.server.sensor.temp.status[temperatureProbeStatus.{#SNMPINDEX}])={$DELL.SNMP.SENSOR.TEMP.STATUS.CRIT:"nonRecoverableLower"}`|Average||
|Dell R820: Probe [{#SENSOR_LOCALE}]: Warning status|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.sensor.temp.status[temperatureProbeStatus.{#SNMPINDEX}])={$DELL.SNMP.SENSOR.TEMP.STATUS.WARN:"nonCriticalUpper"} or last(/DELL PowerEdge R820 by SNMP/dell.server.sensor.temp.status[temperatureProbeStatus.{#SNMPINDEX}])={$DELL.SNMP.SENSOR.TEMP.STATUS.WARN:"nonCriticalLower"}`|Warning|**Depends on**:<br><ul><li>Dell R820: Probe [{#SENSOR_LOCALE}]: Critical status</li></ul>|
|Dell R820: Probe [{#SENSOR_LOCALE}]: Not in optimal status|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.sensor.temp.status[temperatureProbeStatus.{#SNMPINDEX}])<>{$DELL.SNMP.SENSOR.TEMP.STATUS.OK}`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Dell R820: Probe [{#SENSOR_LOCALE}]: Critical status</li><li>Dell R820: Probe [{#SENSOR_LOCALE}]: Warning status</li></ul>|

### LLD rule PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU discovery|<p>IDRAC-MIB-SMIv2::powerSupplyTable</p>|SNMP agent|psu.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R820: Power supply [{#PSU_DESCR}]: State|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the status of the power supply.</p>|SNMP agent|dell.server.sensor.psu.status[powerSupplyStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for PSU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R820: Power supply [{#PSU_DESCR}]: Critical state|<p>Please check the power supply unit for errors.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.sensor.psu.status[powerSupplyStatus.{#SNMPINDEX}])={$DELL.SNMP.PSU.STATUS.CRIT:"critical"} or last(/DELL PowerEdge R820 by SNMP/dell.server.sensor.psu.status[powerSupplyStatus.{#SNMPINDEX}])={$DELL.SNMP.PSU.STATUS.CRIT:"nonRecoverable"}`|Average||
|Dell R820: Power supply [{#PSU_DESCR}]: Warning state|<p>Please check the power supply unit for errors.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.sensor.psu.status[powerSupplyStatus.{#SNMPINDEX}])={$DELL.SNMP.PSU.STATUS.WARN:"nonCritical"}`|Warning|**Depends on**:<br><ul><li>Dell R820: Power supply [{#PSU_DESCR}]: Critical state</li></ul>|

### LLD rule Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan discovery|<p>IDRAC-MIB-SMIv2::coolingDeviceTable</p>|SNMP agent|fan.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R820: Fan [{#FAN_DESCR}]: Status|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the probe status of the cooling device.</p>|SNMP agent|dell.server.sensor.fan.status[{#FAN_DESCR}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: Fan [{#FAN_DESCR}]: Speed|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the reading for a cooling device of a subtype other than `coolingDeviceSubTypeIsDiscrete`.</p><p>When the value for `coolingDeviceSubType` is other than `coolingDeviceSubTypeIsDiscrete`, the value returned for this attribute is the speed in RPM or the "OFF/ON" value of the cooling device.</p><p>When the value for `coolingDeviceSubType` is `coolingDeviceSubTypeIsDiscrete`, a value is not returned for this attribute.</p>|SNMP agent|dell.server.sensor.fan.speed[{#FAN_DESCR}]|

### Trigger prototypes for Fan discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R820: Fan [{#FAN_DESCR}]: Critical state|<p>Please check the fan unit.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.sensor.fan.status[{#FAN_DESCR}])={$DELL.SNMP.FAN.STATUS.CRIT:"criticalUpper"} or last(/DELL PowerEdge R820 by SNMP/dell.server.sensor.fan.status[{#FAN_DESCR}])={$DELL.SNMP.FAN.STATUS.CRIT:"nonRecoverableUpper"} or last(/DELL PowerEdge R820 by SNMP/dell.server.sensor.fan.status[{#FAN_DESCR}])={$DELL.SNMP.FAN.STATUS.CRIT:"criticalLower"} or last(/DELL PowerEdge R820 by SNMP/dell.server.sensor.fan.status[{#FAN_DESCR}])={$DELL.SNMP.FAN.STATUS.CRIT:"nonRecoverableLower"} or last(/DELL PowerEdge R820 by SNMP/dell.server.sensor.fan.status[{#FAN_DESCR}])={$DELL.SNMP.FAN.STATUS.CRIT:"failed"}`|Average||
|Dell R820: Fan [{#FAN_DESCR}]: Warning state|<p>Please check the fan unit.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.sensor.fan.status[{#FAN_DESCR}])={$DELL.SNMP.FAN.STATUS.WARN:"nonCriticalUpper"} or last(/DELL PowerEdge R820 by SNMP/dell.server.sensor.fan.status[{#FAN_DESCR}])={$DELL.SNMP.FAN.STATUS.WARN:"nonCriticalLower"}`|Warning|**Depends on**:<br><ul><li>Dell R820: Fan [{#FAN_DESCR}]: Critical state</li></ul>|

### LLD rule Array controller discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Array controller discovery|<p>Scanning table of Array controllers: IDRAC-MIB-SMIv2::controllerTable</p>|SNMP agent|array.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Array controller discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R820: Controller [{#CNTLR_NAME}]: Status|<p>MIB: IDRAC-MIB-SMIv2</p><p>The status of the controller itself without the propagation of any contained component status.</p><p>Possible values:</p><p>1: Other</p><p>2: Unknown</p><p>3: OK</p><p>4: Non-critical</p><p>5: Critical</p><p>6: Non-recoverable</p>|SNMP agent|dell.server.hw.diskarray.status[controllerComponentStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: Controller [{#CNTLR_NAME}]: Model|<p>MIB: IDRAC-MIB-SMIv2</p><p>The controller's name as represented in Storage Management.</p>|SNMP agent|dell.server.hw.diskarray.model[controllerName.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Array controller discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R820: Controller [{#CNTLR_NAME}]: Unrecoverable state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.hw.diskarray.status[controllerComponentStatus.{#SNMPINDEX}])={$DELL.SNMP.DISK.ARRAY.STATUS.FAIL}`|High||
|Dell R820: Controller [{#CNTLR_NAME}]: Critical state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.hw.diskarray.status[controllerComponentStatus.{#SNMPINDEX}])={$DELL.SNMP.DISK.ARRAY.STATUS.CRIT}`|Average|**Depends on**:<br><ul><li>Dell R820: Controller [{#CNTLR_NAME}]: Unrecoverable state</li></ul>|
|Dell R820: Controller [{#CNTLR_NAME}]: Warning state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.hw.diskarray.status[controllerComponentStatus.{#SNMPINDEX}])={$DELL.SNMP.DISK.ARRAY.STATUS.WARN}`|Warning|**Depends on**:<br><ul><li>Dell R820: Controller [{#CNTLR_NAME}]: Critical state</li><li>Dell R820: Controller [{#CNTLR_NAME}]: Unrecoverable state</li></ul>|

### LLD rule Battery discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Battery discovery|<p>Scanning Battery Table: IDRAC-MIB-SMIv2::batteryTable</p>|SNMP agent|battery.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Battery discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R820: Battery [{#BATTERY_NAME}]: Status|<p>MIB: IDRAC-MIB-SMIv2</p><p>Current state of battery.</p><p>Possible values:</p><p>1: The current state could not be determined.</p><p>2: The battery is operating normally.</p><p>3: The battery has failed and needs to be replaced.</p><p>4: The battery temperature is high or charge level is depleting.</p><p>5: The battery is missing or not detected.</p><p>6: The battery is undergoing the re-charge phase.</p><p>7: The battery voltage or charge level is below the threshold.</p>|SNMP agent|dell.server.hw.battery.status[batteryState.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Battery discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R820: Battery [{#BATTERY_NAME}]: Critical state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.hw.battery.status[batteryState.{#SNMPINDEX}])={$DELL.SNMP.DISK.ARRAY.CACHE.BATTERY.STATUS.CRIT}`|Average||
|Dell R820: Battery [{#BATTERY_NAME}]: Warning state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.hw.battery.status[batteryState.{#SNMPINDEX}])={$DELL.SNMP.DISK.ARRAY.CACHE.BATTERY.STATUS.WARN}`|Warning|**Depends on**:<br><ul><li>Dell R820: Battery [{#BATTERY_NAME}]: Critical state</li></ul>|
|Dell R820: Battery [{#BATTERY_NAME}]: Not in optimal state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.hw.battery.status[batteryState.{#SNMPINDEX}])<>{$DELL.SNMP.DISK.ARRAY.CACHE.BATTERY.STATUS.OK}`|Info|**Depends on**:<br><ul><li>Dell R820: Battery [{#BATTERY_NAME}]: Critical state</li><li>Dell R820: Battery [{#BATTERY_NAME}]: Warning state</li></ul>|

### LLD rule Physical disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Physical disk discovery|<p>Scanning  table of physical drive entries IDRAC-MIB-SMIv2::physicalDiskTable.</p>|SNMP agent|physicaldisk.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Physical disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R820: Physical disk [{#DISK_NAME}]: Status|<p>MIB: IDRAC-MIB-SMIv2</p><p>The status of the physical disk itself without the propagation of any contained component status.</p><p>Possible values:</p><p>1: Other</p><p>2: Unknown</p><p>3: OK</p><p>4: Non-critical</p><p>5: Critical</p><p>6: Non-recoverable</p>|SNMP agent|dell.server.hw.physicaldisk.status[physicalDiskComponentStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: Physical disk [{#DISK_NAME}]: S.M.A.R.T. Status|<p>MIB: IDRAC-MIB-SMIv2</p><p>Indicates whether the physical disk has received a predictive failure alert.</p>|SNMP agent|dell.server.hw.physicaldisk.smart_status[physicalDiskSmartAlertIndication.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: Physical disk [{#DISK_NAME}]: Serial number|<p>MIB: IDRAC-MIB-SMIv2</p><p>The physical disk's unique identification number from the manufacturer.</p>|SNMP agent|dell.server.hw.physicaldisk.serialnumber[physicalDiskSerialNo.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: Physical disk [{#DISK_NAME}]: Model name|<p>MIB: IDRAC-MIB-SMIv2</p><p>The model number of the physical disk.</p>|SNMP agent|dell.server.hw.physicaldisk.model[physicalDiskProductID.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: Physical disk [{#DISK_NAME}]: Media type|<p>MIB: IDRAC-MIB-SMIv2</p><p>The media type of the physical disk. Possible Values:</p><p>1: The media type could not be determined.</p><p>2: Hard Disk Drive (HDD).</p><p>3: Solid State Drive (SSD).</p>|SNMP agent|dell.server.hw.physicaldisk.media_type[physicalDiskMediaType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: Physical disk [{#DISK_NAME}]: Size|<p>MIB: IDRAC-MIB-SMIv2</p><p>The size of the physical disk in megabytes.</p>|SNMP agent|dell.server.hw.physicaldisk.size[physicalDiskCapacityInMB.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Physical disk discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R820: Physical disk [{#DISK_NAME}]: Failed state|<p>Please check physical disk for warnings or errors.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.hw.physicaldisk.status[physicalDiskComponentStatus.{#SNMPINDEX}])={$DELL.SNMP.DISK.STATUS.FAIL:"critical"} or last(/DELL PowerEdge R820 by SNMP/dell.server.hw.physicaldisk.status[physicalDiskComponentStatus.{#SNMPINDEX}])={$DELL.SNMP.DISK.STATUS.FAIL:"nonRecoverable"}`|High||
|Dell R820: Physical disk [{#DISK_NAME}]: Warning state|<p>Please check physical disk for warnings or errors.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.hw.physicaldisk.status[physicalDiskComponentStatus.{#SNMPINDEX}])={$DELL.SNMP.DISK.STATUS.WARN:"nonCritical"}`|Warning|**Depends on**:<br><ul><li>Dell R820: Physical disk [{#DISK_NAME}]: Failed state</li></ul>|
|Dell R820: Physical disk [{#DISK_NAME}]: S.M.A.R.T. failed|<p>Disk probably requires replacement.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.hw.physicaldisk.smart_status[physicalDiskSmartAlertIndication.{#SNMPINDEX}])={$DISK.SMART.STATUS.FAIL:"replaceDrive"} or last(/DELL PowerEdge R820 by SNMP/dell.server.hw.physicaldisk.smart_status[physicalDiskSmartAlertIndication.{#SNMPINDEX}])={$DISK.SMART.STATUS.FAIL:"replaceDriveSSDWearOut"}`|High|**Depends on**:<br><ul><li>Dell R820: Physical disk [{#DISK_NAME}]: Failed state</li></ul>|
|Dell R820: Physical disk [{#DISK_NAME}]: Has been replaced|<p>[{#DISK_NAME}] serial number has changed. Acknowledge to close the problem manually.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.hw.physicaldisk.serialnumber[physicalDiskSerialNo.{#SNMPINDEX}],#1)<>last(/DELL PowerEdge R820 by SNMP/dell.server.hw.physicaldisk.serialnumber[physicalDiskSerialNo.{#SNMPINDEX}],#2) and length(last(/DELL PowerEdge R820 by SNMP/dell.server.hw.physicaldisk.serialnumber[physicalDiskSerialNo.{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes|

### LLD rule Virtual disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Virtual disk discovery|<p>IDRAC-MIB-SMIv2::virtualDiskTable</p>|SNMP agent|virtualdisk.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Virtual disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R820: Virtual disk [{#DISK_NAME}]: Status|<p>MIB: IDRAC-MIB-SMIv2</p><p>The current state of this virtual disk (which includes any member physical disks.)</p><p>Possible states:</p><p>1: The current state could not be determined.</p><p>2: The virtual disk is operating normally or optimally.</p><p>3: The virtual disk has encountered a failure. Data on the disk is lost or is about to be lost.</p><p>4: The virtual disk encountered a failure with one or all of the constituent redundant physical disks.</p><p>The data on the virtual disk might no longer be fault tolerant.</p>|SNMP agent|dell.server.hw.virtualdisk.status[virtualDiskState.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: Virtual disk [{#DISK_NAME}]: Layout type|<p>MIB: IDRAC-MIB-SMIv2</p><p>The virtual disk's RAID type.</p><p>Possible values:</p><p>1: Not one of the following</p><p>2: RAID-0</p><p>3: RAID-1</p><p>4: RAID-5</p><p>5: RAID-6</p><p>6: RAID-10</p><p>7: RAID-50</p><p>8: RAID-60</p><p>9: Concatenated RAID 1</p><p>10: Concatenated RAID 5</p>|SNMP agent|dell.server.hw.virtualdisk.layout[virtualDiskLayout.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: Virtual disk [{#DISK_NAME}]: Size|<p>MIB: IDRAC-MIB-SMIv2</p><p>The size of the virtual disk in megabytes.</p>|SNMP agent|dell.server.hw.virtualdisk.size[virtualDiskSizeInMB.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: Virtual disk [{#DISK_NAME}]: Operational state|<p>MIB: IDRAC-MIB-SMIv2</p><p>The state of the virtual disk when there are progressive operations ongoing.</p><p>Possible values:</p><p>1: There is no active operation running.</p><p>2: The virtual disk configuration has changed. The physical disks included in the virtual disk are being modified to support the new configuration.</p><p>3: A Consistency Check (CC) is being performed on the virtual disk.</p><p>4: The virtual disk is being initialized.</p><p>5: BackGround Initialization (BGI) is being performed on the virtual disk.</p>|SNMP agent|dell.server.hw.virtualdisk.state[virtualDiskOperationalState.{#SNMPINDEX}]|
|Dell R820: Virtual disk [{#DISK_NAME}]: Read policy|<p>MIB: IDRAC-MIB-SMIv2</p><p>The read policy used by the controller for read operations on this virtual disk.</p><p>Possible values:</p><p>1: No Read Ahead.</p><p>2: Read Ahead.</p><p>3: Adaptive Read Ahead.</p>|SNMP agent|dell.server.hw.virtualdisk.read_policy[virtualDiskReadPolicy.{#SNMPINDEX}]|
|Dell R820: Virtual disk [{#DISK_NAME}]: Write policy|<p>MIB: IDRAC-MIB-SMIv2</p><p>The write policy used by the controller for write operations on this virtual disk.</p><p>Possible values:</p><p>1: Write Through.</p><p>2: Write Back.</p><p>3: Force Write Back.</p>|SNMP agent|dell.server.hw.virtualdisk.write_policy[virtualDiskWritePolicy.{#SNMPINDEX}]|

### Trigger prototypes for Virtual disk discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R820: Virtual disk [{#DISK_NAME}]: Failed state|<p>Please check the virtual disk for warnings or errors.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.hw.virtualdisk.status[virtualDiskState.{#SNMPINDEX}])={$DELL.SNMP.VDISK.STATUS.CRIT:"failed"}`|High||
|Dell R820: Virtual disk [{#DISK_NAME}]: Warning state|<p>Please check the virtual disk for warnings or errors.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.hw.virtualdisk.status[virtualDiskState.{#SNMPINDEX}])={$DELL.SNMP.VDISK.STATUS.WARN:"degraded"}`|Warning|**Depends on**:<br><ul><li>Dell R820: Virtual disk [{#DISK_NAME}]: Failed state</li></ul>|

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>Discovery of network interfaces.</p>|SNMP agent|net.if.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R820: NIC [{#NIC_FQDD}/{#NIC_MAC}]: Link status|<p>This attribute defines the connection status of the network device.</p>|SNMP agent|dell.server.net.if.link[{#NIC_FQDD}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: NIC [{#NIC_FQDD}/{#NIC_MAC}]: Status|<p>This attribute defines the status of the network device.</p>|SNMP agent|dell.server.net.if.status[{#NIC_FQDD}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R820: NIC [{#NIC_FQDD}/{#NIC_MAC}]: Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is Down.<br>2. `{$DELL.SNMP.IFCONTROL:"{#NIC_FQDD}"}=1` - a user can redefine the context macro to "0", marking this interface as not important. No new trigger will be fired if this interface is Down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, does not fire for the "eternal off" interfaces.)</p>|`{$DELL.SNMP.IFCONTROL:"{#NIC_FQDD}"}=1 and last(/DELL PowerEdge R820 by SNMP/dell.server.net.if.link[{#NIC_FQDD}],#1)<>1 and last(/DELL PowerEdge R820 by SNMP/dell.server.net.if.link[{#NIC_FQDD}],#1)<>last(/DELL PowerEdge R820 by SNMP/dell.server.net.if.link[{#NIC_FQDD}],#2)`|Average|**Manual close**: Yes|
|Dell R820: NIC [{#NIC_FQDD}/{#NIC_MAC}]: Status is not OK|<p>MIB: IDRAC-MIB-SMIv2<br>Network interface status is not OK.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.net.if.status[{#NIC_FQDD}],#1)<>3`|Average||

### LLD rule CPU status discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU status discovery|<p>CPU status discovery.</p>|SNMP agent|cpu.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for CPU status discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R820: CPU [{#CPU_FQDD}]: Status|<p>This attribute defines the status of the processor device status probe. This status will be joined into the `processorDeviceStatus` attribute.</p>|SNMP agent|dell.server.cpu.status[cpu.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: CPU [{#CPU_FQDD}]: State|<p>This attribute defines the reading of the processor device status probe.</p>|SNMP agent|dell.server.cpu.state[cpu.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for CPU status discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R820: CPU [{#CPU_FQDD}]: Status is not OK|<p>MIB: IDRAC-MIB-SMIv2<br>CPU status is not OK.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.cpu.status[cpu.{#SNMPINDEX}],#1)<>3`|Average||
|Dell R820: CPU [{#CPU_FQDD}]: Reading error|<p>MIB: IDRAC-MIB-SMIv2<br>CPU probe reading flag is not `processorPresent`.</p>|`bitand(last(/DELL PowerEdge R820 by SNMP/dell.server.cpu.state[cpu.{#SNMPINDEX}],#1),128)=0`|Average||

### LLD rule System battery discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|System battery discovery|<p>System battery discovery.</p>|SNMP agent|system.battery.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for System battery discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R820: System battery [{#SNMPVALUE}]: Status|<p>This attribute defines the status of the battery.</p>|SNMP agent|dell.server.system.battery[{#SNMPVALUE}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for System battery discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R820: System battery [{#SNMPVALUE}]: Status is not OK|<p>MIB: IDRAC-MIB-SMIv2<br>System battery status is not OK.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.system.battery[{#SNMPVALUE}],#1)<>3`|Average||

### LLD rule Memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Memory discovery|<p>Memory discovery.</p>|SNMP agent|memory.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R820: Memory [{#SNMPVALUE}]: Status|<p>This attribute defines the status of the memory device.</p>|SNMP agent|dell.server.memory.status[{#SNMPVALUE}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R820: Memory [{#SNMPVALUE}]: Size|<p>This attribute defines the size, in KB, of the memory device. Zero indicates no memory installed; 2,147,483,647 indicates an unknown memory size.</p>|SNMP agent|dell.server.memory.size[{#SNMPVALUE}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li><li><p>Custom multiplier: `1024`</p></li></ul>|

### Trigger prototypes for Memory discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R820: Memory [{#SNMPVALUE}]: Status is not OK|<p>MIB: IDRAC-MIB-SMIv2<br>Memory status is not OK.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.memory.status[{#SNMPVALUE}],#1)<>3`|Average||

### LLD rule Voltage probe discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Voltage probe discovery|<p>Voltage probe discovery.</p>|SNMP agent|voltage.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Voltage probe discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R820: Voltage probe [{#VPROBE_NAME}]: Voltage|<p>This attribute defines the reading for a voltage probe.</p>|SNMP agent|dell.server.voltage.value[{#VPROBE_NAME}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Dell R820: Voltage probe [{#VPROBE_NAME}]: Status|<p>This attribute defines the status of the voltage probe.</p>|SNMP agent|dell.server.voltage.status[{#VPROBE_NAME}]|

### Trigger prototypes for Voltage probe discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R820: Voltage probe [{#VPROBE_NAME}]: Status is not OK|<p>Please check the device's voltage.</p>|`last(/DELL PowerEdge R820 by SNMP/dell.server.voltage.status[{#VPROBE_NAME}])<>3`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

