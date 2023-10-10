
# DELL PowerEdge R740 by SNMP

## Overview

This is a template for monitoring DELL PowerEdge R740 servers with iDRAC version 7 and later via Zabbix SNMP agent that works without any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- DELL PowerEdge R740

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SENSOR.TEMP.STATUS.OK}|<p>The OK status of the temperature probe for trigger expression.</p>|`3`|
|{$SENSOR.TEMP.STATUS.WARN:"nonCriticalUpper"}|<p>The warning status of the temperature probe for trigger expression.</p>|`4`|
|{$SENSOR.TEMP.STATUS.WARN:"nonCriticalLower"}|<p>The warning status of the temperature probe for trigger expression.</p>|`7`|
|{$SENSOR.TEMP.STATUS.CRIT:"criticalUpper"}|<p>The critical status of the temperature probe for trigger expression.</p>|`5`|
|{$SENSOR.TEMP.STATUS.CRIT:"nonRecoverableUpper"}|<p>The critical status of the temperature probe for trigger expression.</p>|`6`|
|{$SENSOR.TEMP.STATUS.CRIT:"criticalLower"}|<p>The critical status of the temperature probe for trigger expression.</p>|`8`|
|{$SENSOR.TEMP.STATUS.CRIT:"nonRecoverableLower"}|<p>The critical status of the temperature probe for trigger expression.</p>|`9`|
|{$HEALTH.STATUS.DISASTER}|<p>The disaster status of the health for trigger expression.</p>|`6`|
|{$HEALTH.STATUS.CRIT}|<p>The critical status of the health for trigger expression.</p>|`5`|
|{$HEALTH.STATUS.WARN}|<p>The warning status of the health for trigger expression.</p>|`4`|
|{$PSU.STATUS.WARN:"nonCritical"}|<p>The warning value of the PSU sensor for trigger expression.</p>|`4`|
|{$PSU.STATUS.CRIT:"critical"}|<p>The critical value of the PSU sensor for trigger expression.</p>|`5`|
|{$PSU.STATUS.CRIT:"nonRecoverable"}|<p>The critical value of the PSU sensor for trigger expression.</p>|`6`|
|{$FAN.STATUS.WARN:"nonCriticalUpper"}|<p>The warning value of the FAN sensor for trigger expression.</p>|`4`|
|{$FAN.STATUS.WARN:"nonCriticalLower"}|<p>The warning value of the FAN sensor for trigger expression.</p>|`7`|
|{$FAN.STATUS.CRIT:"criticalUpper"}|<p>The critical value of the FAN sensor for trigger expression.</p>|`5`|
|{$FAN.STATUS.CRIT:"nonRecoverableUpper"}|<p>The critical value of the FAN sensor for trigger expression.</p>|`6`|
|{$FAN.STATUS.CRIT:"criticalLower"}|<p>The critical value of the FAN sensor for trigger expression.</p>|`8`|
|{$FAN.STATUS.CRIT:"nonRecoverableLower"}|<p>The critical value of the FAN sensor for trigger expression.</p>|`9`|
|{$FAN.STATUS.CRIT:"failed"}|<p>The critical value of the FAN sensor for trigger expression.</p>|`10`|
|{$DISK.ARRAY.STATUS.FAIL}|<p>The disaster status of the disk array for trigger expression.</p>|`6`|
|{$DISK.ARRAY.STATUS.CRIT}|<p>The critical status of the disk array for trigger expression.</p>|`5`|
|{$DISK.ARRAY.STATUS.WARN}|<p>The warning status of the disk array for trigger expression.</p>|`4`|
|{$DISK.ARRAY.CACHE.BATTERY.STATUS.CRIT}|<p>The critical status of the disk array cache battery for trigger expression.</p>|`3`|
|{$DISK.ARRAY.CACHE.BATTERY.STATUS.WARN}|<p>The warning status of the disk array cache battery for trigger expression.</p>|`4`|
|{$DISK.ARRAY.CACHE.BATTERY.STATUS.OK}|<p>The OK status of the disk array cache battery for trigger expression.</p>|`2`|
|{$VDISK.STATUS.CRIT:"failed"}|<p>The critical status of the virtual disk for trigger expression.</p>|`3`|
|{$VDISK.STATUS.WARN:"degraded"}|<p>The warning status of the virtual disk for trigger expression.</p>|`4`|
|{$DISK.STATUS.WARN:"nonCritical"}|<p>The warning status of the disk for trigger expression.</p>|`4`|
|{$DISK.STATUS.FAIL:"critical"}|<p>The critical status of the disk for trigger expression.</p>|`5`|
|{$DISK.STATUS.FAIL:"nonRecoverable"}|<p>The critical status of the disk for trigger expression.</p>|`6`|
|{$DISK.SMART.STATUS.FAIL}|<p>The critical S.M.A.R.T status of the disk for trigger expression.</p>|`1`|
|{$SNMP.TIMEOUT}|<p>The time interval for SNMP agent availability trigger expression.</p>|`5m`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R740: Overall system health status|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the overall rollup status of all components in the system being monitored by the remote access card. Includes system, storage, IO devices, iDRAC, CPU, memory, etc.</p>|SNMP agent|dell.server.status[globalSystemStatus]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: Hardware model name|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the model name of the system.</p>|SNMP agent|dell.server.hw.model[systemModelName]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: Hardware serial number|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the service tag of the system.</p>|SNMP agent|dell.server.hw.serialnumber[systemServiceTag]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: Operating system|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the name of the operating system that the host is running.</p>|SNMP agent|dell.server.sw.os[systemOSName]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Dell R740: Firmware version|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the firmware version of a remote access card.</p>|SNMP agent|dell.server.hw.firmware[racFirmwareVersion]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Dell R740: Uptime (network)|<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p>|SNMP agent|dell.server.net.uptime[sysUpTime]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Dell R740: Uptime (hardware)|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p>|SNMP agent|dell.server.hw.uptime[hrSystemUptime]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Dell R740: SNMP traps (fallback)|<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p>|SNMP trap|snmptrap.fallback|
|Dell R740: System location|<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., 'telephone closet, 3rd floor'). If the location is unknown, the value is the zero-length string.</p>|SNMP agent|dell.server.location[sysLocation]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: System contact details|<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person. If no contact information is known, the value is the zero-length string.</p>|SNMP agent|dell.server.contact[sysContact]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Dell R740: System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity. This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining 'what kind of box' is being managed. For example, if vendor 'Flintstones, Inc.' was assigned the subtree 1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its 'Fred Router'.</p>|SNMP agent|dell.server.objectid[sysObjectID]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node. By convention, this is the node's fully-qualified domain name. If the name is unknown, the value is the zero-length string.</p>|SNMP agent|dell.server.name[sysName]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: System description|<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p>|SNMP agent|dell.server.descr[sysDescr]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Dell R740: SNMP agent availability||Zabbix internal|zabbix[host,snmp,available]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R740: System is in unrecoverable state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.status[globalSystemStatus])={$HEALTH.STATUS.DISASTER}`|Disaster||
|Dell R740: System status is in critical state|<p>Please check the device for errors.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.status[globalSystemStatus])={$HEALTH.STATUS.CRIT}`|High||
|Dell R740: System status is in warning state|<p>Please check the device for warnings.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.status[globalSystemStatus])={$HEALTH.STATUS.WARN}`|Warning|**Depends on**:<br><ul><li>Dell R740: System status is in critical state</li></ul>|
|Dell R740: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.hw.serialnumber[systemServiceTag],#1)<>last(/DELL PowerEdge R740 by SNMP/dell.server.hw.serialnumber[systemServiceTag],#2) and length(last(/DELL PowerEdge R740 by SNMP/dell.server.hw.serialnumber[systemServiceTag]))>0`|Info|**Manual close**: Yes|
|Dell R740: Operating system description has changed|<p>Operating system description has changed. Possibly, the system has been updated or replaced. Acknowledge to close the problem manually.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.sw.os[systemOSName],#1)<>last(/DELL PowerEdge R740 by SNMP/dell.server.sw.os[systemOSName],#2) and length(last(/DELL PowerEdge R740 by SNMP/dell.server.sw.os[systemOSName]))>0`|Info|**Manual close**: Yes|
|Dell R740: Firmware has changed|<p>Firmware version has changed. Acknowledge to close the problem manually.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.hw.firmware[racFirmwareVersion],#1)<>last(/DELL PowerEdge R740 by SNMP/dell.server.hw.firmware[racFirmwareVersion],#2) and length(last(/DELL PowerEdge R740 by SNMP/dell.server.hw.firmware[racFirmwareVersion]))>0`|Info|**Manual close**: Yes|
|Dell R740: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/DELL PowerEdge R740 by SNMP/dell.server.hw.uptime[hrSystemUptime])>0 and last(/DELL PowerEdge R740 by SNMP/dell.server.hw.uptime[hrSystemUptime])<10m) or (last(/DELL PowerEdge R740 by SNMP/dell.server.hw.uptime[hrSystemUptime])=0 and last(/DELL PowerEdge R740 by SNMP/dell.server.net.uptime[sysUpTime])<10m)`|Warning|**Manual close**: Yes|
|Dell R740: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.name[sysName],#1)<>last(/DELL PowerEdge R740 by SNMP/dell.server.name[sysName],#2) and length(last(/DELL PowerEdge R740 by SNMP/dell.server.name[sysName]))>0`|Info|**Manual close**: Yes|
|Dell R740: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/DELL PowerEdge R740 by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning||

### LLD rule Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature discovery|<p>Scanning table of Temperature Probe Table IDRAC-MIB-SMIv2::temperatureProbeTable</p>|SNMP agent|temp.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R740: {#SENSOR_LOCALE} Value|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the reading for a temperature probe of type other than temperatureProbeTypeIsDiscrete. When the value for temperatureProbeType is other than temperatureProbeTypeIsDiscrete, the value returned for this attribute is the temperature that the probe is reading in Centigrade. When the value for temperatureProbeType is temperatureProbeTypeIsDiscrete, a value is not returned for this attribute.</p>|SNMP agent|dell.server.sensor.temp.value[temperatureProbeReading.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#SENSOR_LOCALE} Status|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the probe status of the temperature probe.</p><p>Possible values:</p><p>other(1),               -- probe status is not one of the following:</p><p>unknown(2),             -- probe status is unknown (not known or monitored)</p><p>ok(3),                  -- probe is reporting a value within the thresholds</p><p>nonCriticalUpper(4),    -- probe has crossed the upper noncritical threshold</p><p>criticalUpper(5),       -- probe has crossed the upper critical threshold</p><p>nonRecoverableUpper(6), -- probe has crossed the upper non-recoverable threshold</p><p>nonCriticalLower(7),    -- probe has crossed the lower noncritical threshold</p><p>criticalLower(8),       -- probe has crossed the lower critical threshold</p><p>nonRecoverableLower(9), -- probe has crossed the lower non-recoverable threshold</p><p>failed(10)              -- probe is not functional</p>|SNMP agent|dell.server.sensor.temp.status[temperatureProbeStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Temperature discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R740: Probe {#SENSOR_LOCALE} is in critical status|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.sensor.temp.status[temperatureProbeStatus.{#SNMPINDEX}])={$SENSOR.TEMP.STATUS.CRIT:"criticalUpper"} or last(/DELL PowerEdge R740 by SNMP/dell.server.sensor.temp.status[temperatureProbeStatus.{#SNMPINDEX}])={$SENSOR.TEMP.STATUS.CRIT:"nonRecoverableUpper"} or last(/DELL PowerEdge R740 by SNMP/dell.server.sensor.temp.status[temperatureProbeStatus.{#SNMPINDEX}])={$SENSOR.TEMP.STATUS.CRIT:"criticalLower"} or last(/DELL PowerEdge R740 by SNMP/dell.server.sensor.temp.status[temperatureProbeStatus.{#SNMPINDEX}])={$SENSOR.TEMP.STATUS.CRIT:"nonRecoverableLower"}`|Average||
|Dell R740: Probe {#SENSOR_LOCALE} is in warning status|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.sensor.temp.status[temperatureProbeStatus.{#SNMPINDEX}])={$SENSOR.TEMP.STATUS.WARN:"nonCriticalUpper"} or last(/DELL PowerEdge R740 by SNMP/dell.server.sensor.temp.status[temperatureProbeStatus.{#SNMPINDEX}])={$SENSOR.TEMP.STATUS.WARN:"nonCriticalLower"}`|Warning|**Depends on**:<br><ul><li>Dell R740: Probe {#SENSOR_LOCALE} is in critical status</li></ul>|
|Dell R740: Probe {#SENSOR_LOCALE} is not in optimal status|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.sensor.temp.status[temperatureProbeStatus.{#SNMPINDEX}])<>{$SENSOR.TEMP.STATUS.OK}`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Dell R740: Probe {#SENSOR_LOCALE} is in critical status</li><li>Dell R740: Probe {#SENSOR_LOCALE} is in warning status</li></ul>|

### LLD rule PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU discovery|<p>IDRAC-MIB-SMIv2::powerSupplyTable</p>|SNMP agent|psu.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R740: {#PSU_DESCR}|<p>MIB: IDRAC-MIB-SMIv2</p><p>0600.0012.0001.0005 This attribute defines the status of the power supply.</p>|SNMP agent|dell.server.sensor.psu.status[powerSupplyStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for PSU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R740: Power supply {#PSU_DESCR} is in critical state|<p>Please check the power supply unit for errors.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.sensor.psu.status[powerSupplyStatus.{#SNMPINDEX}])={$PSU.STATUS.CRIT:"critical"} or last(/DELL PowerEdge R740 by SNMP/dell.server.sensor.psu.status[powerSupplyStatus.{#SNMPINDEX}])={$PSU.STATUS.CRIT:"nonRecoverable"}`|Average||
|Dell R740: Power supply {#PSU_DESCR} is in warning state|<p>Please check the power supply unit for errors.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.sensor.psu.status[powerSupplyStatus.{#SNMPINDEX}])={$PSU.STATUS.WARN:"nonCritical"}`|Warning|**Depends on**:<br><ul><li>Dell R740: Power supply {#PSU_DESCR} is in critical state</li></ul>|

### LLD rule FAN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN discovery|<p>IDRAC-MIB-SMIv2::coolingDeviceTable</p>|SNMP agent|fan.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for FAN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R740: {#FAN_DESCR} Status|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the probe status of the cooling device.</p>|SNMP agent|dell.server.sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#FAN_DESCR} Speed|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the reading for a cooling device</p><p>of subtype other than coolingDeviceSubTypeIsDiscrete. When the value</p><p>for coolingDeviceSubType is other than coolingDeviceSubTypeIsDiscrete, the</p><p>value returned for this attribute is the speed in RPM or the OFF/ON value</p><p>of the cooling device. When the value for coolingDeviceSubType is</p><p>coolingDeviceSubTypeIsDiscrete, a value is not returned for this attribute.</p>|SNMP agent|dell.server.sensor.fan.speed[coolingDeviceReading.{#SNMPINDEX}]|

### Trigger prototypes for FAN discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R740: {#FAN_DESCR} is in critical state|<p>Please check the fan unit.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}])={$FAN.STATUS.CRIT:"criticalUpper"} or last(/DELL PowerEdge R740 by SNMP/dell.server.sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}])={$FAN.STATUS.CRIT:"nonRecoverableUpper"} or last(/DELL PowerEdge R740 by SNMP/dell.server.sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}])={$FAN.STATUS.CRIT:"criticalLower"} or last(/DELL PowerEdge R740 by SNMP/dell.server.sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}])={$FAN.STATUS.CRIT:"nonRecoverableLower"} or last(/DELL PowerEdge R740 by SNMP/dell.server.sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}])={$FAN.STATUS.CRIT:"failed"}`|Average||
|Dell R740: {#FAN_DESCR} is in warning state|<p>Please check the fan unit.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}])={$FAN.STATUS.WARN:"nonCriticalUpper"} or last(/DELL PowerEdge R740 by SNMP/dell.server.sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}])={$FAN.STATUS.WARN:"nonCriticalLower"}`|Warning|**Depends on**:<br><ul><li>Dell R740: {#FAN_DESCR} is in critical state</li></ul>|

### LLD rule Array controller discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Array controller discovery|<p>Scanning table of Array controllers: IDRAC-MIB-SMIv2::controllerTable</p>|SNMP agent|array.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Array controller discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R740: {#CNTLR_NAME} Status|<p>MIB: IDRAC-MIB-SMIv2</p><p>The status of the controller itself without the propagation of any contained component status.</p><p>Possible values:</p><p>1: Other</p><p>2: Unknown</p><p>3: OK</p><p>4: Non-critical</p><p>5: Critical</p><p>6: Non-recoverable</p>|SNMP agent|dell.server.hw.diskarray.status[controllerComponentStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#CNTLR_NAME} Model|<p>MIB: IDRAC-MIB-SMIv2</p><p>The controller's name as represented in Storage Management.</p>|SNMP agent|dell.server.hw.diskarray.model[controllerName.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Array controller discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R740: {#CNTLR_NAME} is in unrecoverable state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.hw.diskarray.status[controllerComponentStatus.{#SNMPINDEX}])={$DISK.ARRAY.STATUS.FAIL}`|Disaster||
|Dell R740: {#CNTLR_NAME} is in critical state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.hw.diskarray.status[controllerComponentStatus.{#SNMPINDEX}])={$DISK.ARRAY.STATUS.CRIT}`|High|**Depends on**:<br><ul><li>Dell R740: {#CNTLR_NAME} is in unrecoverable state</li></ul>|
|Dell R740: {#CNTLR_NAME} is in warning state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.hw.diskarray.status[controllerComponentStatus.{#SNMPINDEX}])={$DISK.ARRAY.STATUS.WARN}`|Average|**Depends on**:<br><ul><li>Dell R740: {#CNTLR_NAME} is in critical state</li><li>Dell R740: {#CNTLR_NAME} is in unrecoverable state</li></ul>|

### LLD rule Array controller cache discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Array controller cache discovery|<p>Scanning table of Array controllers: IDRAC-MIB-SMIv2::batteryTable</p>|SNMP agent|array.cache.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Array controller cache discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R740: {#BATTERY_NAME} Status|<p>MIB: IDRAC-MIB-SMIv2</p><p>Current state of battery.</p><p>Possible values:</p><p>1: The current state could not be determined.</p><p>2: The battery is operating normally.</p><p>3: The battery has failed and needs to be replaced.</p><p>4: The battery temperature is high or charge level is depleting.</p><p>5: The battery is missing or not detected.</p><p>6: The battery is undergoing the re-charge phase.</p><p>7: The battery voltage or charge level is below the threshold.</p>|SNMP agent|dell.server.hw.diskarray.cache.battery.status[batteryState.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Array controller cache discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R740: {#BATTERY_NAME} is in critical state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.hw.diskarray.cache.battery.status[batteryState.{#SNMPINDEX}])={$DISK.ARRAY.CACHE.BATTERY.STATUS.CRIT}`|Average||
|Dell R740: {#BATTERY_NAME} is in warning state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.hw.diskarray.cache.battery.status[batteryState.{#SNMPINDEX}])={$DISK.ARRAY.CACHE.BATTERY.STATUS.WARN}`|Warning|**Depends on**:<br><ul><li>Dell R740: {#BATTERY_NAME} is in critical state</li></ul>|
|Dell R740: {#BATTERY_NAME} is not in optimal state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.hw.diskarray.cache.battery.status[batteryState.{#SNMPINDEX}])<>{$DISK.ARRAY.CACHE.BATTERY.STATUS.OK}`|Warning|**Depends on**:<br><ul><li>Dell R740: {#BATTERY_NAME} is in critical state</li><li>Dell R740: {#BATTERY_NAME} is in warning state</li></ul>|

### LLD rule Physical disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Physical disk discovery|<p>Scanning  table of physical drive entries IDRAC-MIB-SMIv2::physicalDiskTable.</p>|SNMP agent|physicaldisk.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Physical disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R740: {#DISK_NAME} Status|<p>MIB: IDRAC-MIB-SMIv2</p><p>The status of the physical disk itself without the propagation of any contained component status.</p><p>Possible values:</p><p>1: Other</p><p>2: Unknown</p><p>3: OK</p><p>4: Non-critical</p><p>5: Critical</p><p>6: Non-recoverable</p>|SNMP agent|dell.server.hw.physicaldisk.status[physicalDiskComponentStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#DISK_NAME} S.M.A.R.T. Status|<p>MIB: IDRAC-MIB-SMIv2</p><p>Indicates whether the physical disk has received a predictive failure alert.</p>|SNMP agent|dell.server.hw.physicaldisk.smart_status[physicalDiskSmartAlertIndication.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#DISK_NAME} Serial number|<p>MIB: IDRAC-MIB-SMIv2</p><p>The physical disk's unique identification number from the manufacturer.</p>|SNMP agent|dell.server.hw.physicaldisk.serialnumber[physicalDiskSerialNo.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#DISK_NAME} Model name|<p>MIB: IDRAC-MIB-SMIv2</p><p>The model number of the physical disk.</p>|SNMP agent|dell.server.hw.physicaldisk.model[physicalDiskProductID.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#DISK_NAME} Media type|<p>MIB: IDRAC-MIB-SMIv2</p><p>The media type of the physical disk. Possible Values:</p><p>1: The media type could not be determined.</p><p>2: Hard Disk Drive (HDD).</p><p>3: Solid State Drive (SSD).</p>|SNMP agent|dell.server.hw.physicaldisk.media_type[physicalDiskMediaType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#DISK_NAME} Size|<p>MIB: IDRAC-MIB-SMIv2</p><p>The size of the physical disk in megabytes.</p>|SNMP agent|dell.server.hw.physicaldisk.size[physicalDiskCapacityInMB.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Physical disk discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R740: {#DISK_NAME} failed|<p>Please check physical disk for warnings or errors.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.hw.physicaldisk.status[physicalDiskComponentStatus.{#SNMPINDEX}])={$DISK.STATUS.FAIL:"critical"} or last(/DELL PowerEdge R740 by SNMP/dell.server.hw.physicaldisk.status[physicalDiskComponentStatus.{#SNMPINDEX}])={$DISK.STATUS.FAIL:"nonRecoverable"}`|High||
|Dell R740: {#DISK_NAME} is in warning state|<p>Please check physical disk for warnings or errors.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.hw.physicaldisk.status[physicalDiskComponentStatus.{#SNMPINDEX}])={$DISK.STATUS.WARN:"nonCritical"}`|Warning|**Depends on**:<br><ul><li>Dell R740: {#DISK_NAME} failed</li></ul>|
|Dell R740: {#DISK_NAME} S.M.A.R.T. failed|<p>Disk probably requires replacement.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.hw.physicaldisk.smart_status[physicalDiskSmartAlertIndication.{#SNMPINDEX}])={$DISK.SMART.STATUS.FAIL:"replaceDrive"} or last(/DELL PowerEdge R740 by SNMP/dell.server.hw.physicaldisk.smart_status[physicalDiskSmartAlertIndication.{#SNMPINDEX}])={$DISK.SMART.STATUS.FAIL:"replaceDriveSSDWearOut"}`|High|**Depends on**:<br><ul><li>Dell R740: {#DISK_NAME} failed</li></ul>|
|Dell R740: {#DISK_NAME} has been replaced|<p>{#DISK_NAME} serial number has changed. Acknowledge to close the problem manually.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.hw.physicaldisk.serialnumber[physicalDiskSerialNo.{#SNMPINDEX}],#1)<>last(/DELL PowerEdge R740 by SNMP/dell.server.hw.physicaldisk.serialnumber[physicalDiskSerialNo.{#SNMPINDEX}],#2) and length(last(/DELL PowerEdge R740 by SNMP/dell.server.hw.physicaldisk.serialnumber[physicalDiskSerialNo.{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes|

### LLD rule Virtual disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Virtual disk discovery|<p>IDRAC-MIB-SMIv2::virtualDiskTable</p>|SNMP agent|virtualdisk.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Virtual disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R740: {#DISK_NAME} Status|<p>MIB: IDRAC-MIB-SMIv2</p><p>The current state of this virtual disk (which includes any member physical disks.)</p><p>Possible states:</p><p>1: The current state could not be determined.</p><p>2: The virtual disk is operating normally or optimally.</p><p>3: The virtual disk has encountered a failure. Data on the disk is lost or is about to be lost.</p><p>4: The virtual disk encountered a failure with one or all of the constituent redundant physical disks.</p><p>The data on the virtual disk might no longer be fault tolerant.</p>|SNMP agent|dell.server.hw.virtualdisk.status[virtualDiskState.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#DISK_NAME} Layout type|<p>MIB: IDRAC-MIB-SMIv2</p><p>The virtual disk's RAID type.</p><p>Possible values:</p><p>1: Not one of the following</p><p>2: RAID-0</p><p>3: RAID-1</p><p>4: RAID-5</p><p>5: RAID-6</p><p>6: RAID-10</p><p>7: RAID-50</p><p>8: RAID-60</p><p>9: Concatenated RAID 1</p><p>10: Concatenated RAID 5</p>|SNMP agent|dell.server.hw.virtualdisk.layout[virtualDiskLayout.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#DISK_NAME} Size|<p>MIB: IDRAC-MIB-SMIv2</p><p>The size of the virtual disk in megabytes.</p>|SNMP agent|dell.server.hw.virtualdisk.size[virtualDiskSizeInMB.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#DISK_NAME} Current state|<p>MIB: IDRAC-MIB-SMIv2</p><p>The state of the virtual disk when there are progressive operations ongoing.</p><p>Possible values:</p><p>1: There is no active operation running.</p><p>2: The virtual disk configuration has changed. The physical disks included in the virtual disk are being modified to support the new configuration.</p><p>3: A Consistency Check (CC) is being performed on the virtual disk.</p><p>4: The virtual disk is being initialized.</p><p>5: BackGround Initialization (BGI) is being performed on the virtual disk.</p>|SNMP agent|dell.server.hw.virtualdisk.state[virtualDiskOperationalState.{#SNMPINDEX}]|
|Dell R740: {#DISK_NAME} Read policy|<p>MIB: IDRAC-MIB-SMIv2</p><p>The read policy used by the controller for read operations on this virtual disk.</p><p>Possible values:</p><p>1: No Read Ahead.</p><p>2: Read Ahead.</p><p>3: Adaptive Read Ahead.</p>|SNMP agent|dell.server.hw.virtualdisk.readpolicy[virtualDiskReadPolicy.{#SNMPINDEX}]|
|Dell R740: {#DISK_NAME} Write policy|<p>MIB: IDRAC-MIB-SMIv2</p><p>The write policy used by the controller for write operations on this virtual disk.</p><p>Possible values:</p><p>1: Write Through.</p><p>2: Write Back.</p><p>3: Force Write Back.</p>|SNMP agent|dell.server.hw.virtualdisk.writepolicy[virtualDiskWritePolicy.{#SNMPINDEX}]|

### Trigger prototypes for Virtual disk discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R740: {#DISK_NAME} failed|<p>Please check the virtual disk for warnings or errors.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.hw.virtualdisk.status[virtualDiskState.{#SNMPINDEX}])={$VDISK.STATUS.CRIT:"failed"}`|High||
|Dell R740: {#DISK_NAME} is in warning state|<p>Please check the virtual disk for warnings or errors.</p>|`last(/DELL PowerEdge R740 by SNMP/dell.server.hw.virtualdisk.status[virtualDiskState.{#SNMPINDEX}])={$VDISK.STATUS.WARN:"degraded"}`|Average|**Depends on**:<br><ul><li>Dell R740: {#DISK_NAME} failed</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

