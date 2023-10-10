
# HPE ProLiant DL360 by SNMP

## Overview

This is a template for monitoring HPE ProLiant DL360 servers with HP iLO version 4 and later via Zabbix SNMP agent that works without any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- HPE ProLiant DL360 server

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$HEALTH.STATUS.CRIT}|<p>The critical status of the health for trigger expression.</p>|`4`|
|{$HEALTH.STATUS.WARN}|<p>The warning status of the health for trigger expression.</p>|`3`|
|{$PSU.STATUS.CRIT}|<p>The critical value of the PSU sensor for trigger expression.</p>|`4`|
|{$PSU.STATUS.WARN}|<p>The warning value of the PSU sensor for trigger expression.</p>|`3`|
|{$FAN.STATUS.CRIT}|<p>The critical value of the FAN sensor for trigger expression.</p>|`4`|
|{$FAN.STATUS.WARN}|<p>The warning value of the FAN sensor for trigger expression.</p>|`3`|
|{$DISK.ARRAY.STATUS.CRIT}|<p>The critical status of the disk array for trigger expression.</p>|`4`|
|{$DISK.ARRAY.STATUS.WARN}|<p>The warning status of the disk array for trigger expression.</p>|`3`|
|{$DISK.ARRAY.CACHE.STATUS.CRIT:"cacheModCriticalFailure"}|<p>The critical status of the disk array cache for trigger expression.</p>|`8`|
|{$DISK.ARRAY.CACHE.STATUS.WARN:"invalid"}|<p>The warning status of the disk array cache for trigger expression.</p>|`2`|
|{$DISK.ARRAY.CACHE.STATUS.WARN:"cacheModDegradedFailsafeSpeed"}|<p>The warning status of the disk array cache for trigger expression.</p>|`7`|
|{$DISK.ARRAY.CACHE.STATUS.WARN:"cacheReadCacheNotMapped"}|<p>The warning status of the disk array cache for trigger expression.</p>|`9`|
|{$DISK.ARRAY.CACHE.STATUS.WARN:"cacheModFlashMemNotAttached"}|<p>The warning status of the disk array cache for trigger expression.</p>|`6`|
|{$DISK.ARRAY.CACHE.STATUS.OK:"enabled"}|<p>The normal status of the disk array cache for trigger expression.</p>|`3`|
|{$DISK.ARRAY.CACHE.BATTERY.STATUS.CRIT:"failed"}|<p>The critical status of the disk array cache battery for trigger expression.</p>|`4`|
|{$DISK.ARRAY.CACHE.BATTERY.STATUS.CRIT:"capacitorFailed"}|<p>The critical status of the disk array cache battery for trigger expression.</p>|`7`|
|{$DISK.ARRAY.CACHE.BATTERY.STATUS.WARN:"degraded"}|<p>The warning status of the disk array cache battery for trigger expression.</p>|`5`|
|{$DISK.ARRAY.CACHE.BATTERY.STATUS.WARN:"notPresent"}|<p>The warning status of the disk array cache battery for trigger expression.</p>|`6`|
|{$VDISK.STATUS.CRIT}|<p>The critical status of the virtual disk for trigger expression.</p>|`3`|
|{$VDISK.STATUS.OK}|<p>The normal status of the virtual disk for trigger expression.</p>|`2`|
|{$DISK.STATUS.WARN}|<p>The warning status of the disk for trigger expression.</p>|`4`|
|{$DISK.STATUS.FAIL}|<p>The critical status of the disk for trigger expression.</p>|`3`|
|{$DISK.SMART.STATUS.FAIL:"replaceDrive"}|<p>The critical S.M.A.R.T status of the disk for trigger expression.</p>|`3`|
|{$DISK.SMART.STATUS.FAIL:"replaceDriveSSDWearOut"}|<p>The critical S.M.A.R.T status of the disk for trigger expression.</p>|`4`|
|{$SNMP.TIMEOUT}|<p>The time interval for SNMP agent availability trigger expression.</p>|`5m`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HPE ProLiant DL360: Overall system health status|<p>MIB: CPQHLTH-MIB</p><p>The overall condition. This object represents the overall status of the server information represented by this MIB.</p>|SNMP agent|hp.server.status[cpqHeMibCondition]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE ProLiant DL360: Hardware model name|<p>MIB: CPQSINFO-MIB</p><p>The machine product name. The name of the machine used in this system.</p>|SNMP agent|hp.server.hw.model[cpqSiProductName]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE ProLiant DL360: Hardware serial number|<p>MIB: CPQSINFO-MIB</p><p>The serial number of the physical system unit. The string will be empty if the system does not report the serial number function.</p>|SNMP agent|hp.server.hw.serialnumber[cpqSiSysSerialNum]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE ProLiant DL360: System temperature status|<p>MIB: CPQHLTH-MIB</p><p>This value specifies the overall condition of the system's thermal environment.</p><p>This value will be one of the following:</p><p>other(1)  Temperature could not be determined.</p><p>ok(2)  The temperature sensor is within normal operating range.</p><p>degraded(3)  The temperature sensor is outside of normal operating range.</p><p>failed(4)  The temperature sensor detects a condition that could  permanently damage the system.</p>|SNMP agent|hp.server.sensor.temp.status[cpqHeThermalCondition]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE ProLiant DL360: Uptime (network)|<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p>|SNMP agent|hp.server.net.uptime[sysUpTime]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|HPE ProLiant DL360: Uptime (hardware)|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p>|SNMP agent|hp.server.hw.uptime[hrSystemUptime]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|HPE ProLiant DL360: SNMP traps (fallback)|<p>The item is used to collect all SNMP traps unmatched by other snmptrap items.</p>|SNMP trap|snmptrap.fallback|
|HPE ProLiant DL360: System location|<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet, 3rd floor').  If the location is unknown, the value is the zero-length string.</p>|SNMP agent|hp.server.location[sysLocation]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE ProLiant DL360: System contact details|<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</p>|SNMP agent|hp.server.contact[sysContact]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|HPE ProLiant DL360: System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity.  This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p>|SNMP agent|hp.server.objectid[sysObjectID]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE ProLiant DL360: System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name.  If the name is unknown, the value is the zero-length string.</p>|SNMP agent|hp.server.name[sysName]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE ProLiant DL360: System description|<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p>|SNMP agent|hp.server.descr[sysDescr]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|HPE ProLiant DL360: SNMP agent availability||Zabbix internal|zabbix[host,snmp,available]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE ProLiant DL360: System status is in critical state|<p>Please check the device for errors.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.status[cpqHeMibCondition])={$HEALTH.STATUS.CRIT}`|High||
|HPE ProLiant DL360: System status is in warning state|<p>Please check the device for warnings.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.status[cpqHeMibCondition])={$HEALTH.STATUS.WARN}`|Warning|**Depends on**:<br><ul><li>HPE ProLiant DL360: System status is in critical state</li></ul>|
|HPE ProLiant DL360: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.hw.serialnumber[cpqSiSysSerialNum],#1)<>last(/HPE ProLiant DL360 by SNMP/hp.server.hw.serialnumber[cpqSiSysSerialNum],#2) and length(last(/HPE ProLiant DL360 by SNMP/hp.server.hw.serialnumber[cpqSiSysSerialNum]))>0`|Info|**Manual close**: Yes|
|HPE ProLiant DL360: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/HPE ProLiant DL360 by SNMP/hp.server.hw.uptime[hrSystemUptime])>0 and last(/HPE ProLiant DL360 by SNMP/hp.server.hw.uptime[hrSystemUptime])<10m) or (last(/HPE ProLiant DL360 by SNMP/hp.server.hw.uptime[hrSystemUptime])=0 and last(/HPE ProLiant DL360 by SNMP/hp.server.net.uptime[sysUpTime])<10m)`|Warning|**Manual close**: Yes|
|HPE ProLiant DL360: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.name[sysName],#1)<>last(/HPE ProLiant DL360 by SNMP/hp.server.name[sysName],#2) and length(last(/HPE ProLiant DL360 by SNMP/hp.server.name[sysName]))>0`|Info|**Manual close**: Yes|
|HPE ProLiant DL360: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/HPE ProLiant DL360 by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning||

### LLD rule Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature discovery|<p>Scanning table of Temperature Sensor Entries:</p><p>CPQHLTH-MIB::cpqHeTemperatureTable</p>|SNMP agent|temp.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SNMPINDEX}: Temperature|<p>MIB: CPQHLTH-MIB</p><p>Temperature readings of testpoint: {#SNMPINDEX}</p>|SNMP agent|hp.server.sensor.temp.value[cpqHeTemperatureCelsius.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#SNMPINDEX}: Temperature sensor location|<p>MIB: CPQHLTH-MIB</p><p>This specifies the location of the temperature sensor present in the system.</p>|SNMP agent|hp.server.sensor.temp.locale[cpqHeTemperatureLocale.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#SNMPINDEX}: Temperature sensor condition|<p>MIB: CPQHLTH-MIB</p><p>The Temperature sensor condition.</p><p>This value will be one of the following:</p><p>other(1)</p><p>  Temperature could not be determined.</p><p>ok(2)</p><p>  The temperature sensor is within normal operating range.</p><p>degraded(3)</p><p>  The temperature sensor is outside of normal operating range.</p><p>failed(4)</p><p>  The temperature sensor detects a condition that could</p><p>  permanently damage the system.</p><p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.  If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|SNMP agent|hp.server.sensor.temp.condition[cpqHeTemperatureCondition.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Temperature discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SNMPINDEX}: Temperature could not be determined|<p>Temperature could not be determined.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.temp.condition[cpqHeTemperatureCondition.{#SNMPINDEX}]) = 1`|Info|**Manual close**: Yes|
|{#SNMPINDEX}: The temperature sensor is outside of normal operating range|<p>If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.temp.condition[cpqHeTemperatureCondition.{#SNMPINDEX}]) = 3`|Warning||
|{#SNMPINDEX}: The temperature sensor detects a condition that could permanently damage the system.|<p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.temp.condition[cpqHeTemperatureCondition.{#SNMPINDEX}]) = 4`|High||

### LLD rule Temperature ambient discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature ambient discovery|<p>Scanning table of Temperature Sensor Entries:</p><p>CPQHLTH-MIB::cpqHeTemperatureTable with ambient(11) and 0.1 index filter</p>|SNMP agent|temp.ambient.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Temperature ambient discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Ambient: Temperature|<p>MIB: CPQHLTH-MIB</p><p>Temperature readings of testpoint: Ambient</p>|SNMP agent|hp.server.sensor.temp.value[cpqHeTemperatureCelsius.Ambient.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Ambient: Temperature sensor condition|<p>MIB: CPQHLTH-MIB</p><p>The Temperature sensor condition.</p><p>This value will be one of the following:</p><p>other(1)</p><p>  Temperature could not be determined.</p><p>ok(2)</p><p>  The temperature sensor is within normal operating range.</p><p>degraded(3)</p><p>  The temperature sensor is outside of normal operating range.</p><p>failed(4)</p><p>  The temperature sensor detects a condition that could</p><p>  permanently damage the system.</p><p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.  If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|SNMP agent|hp.server.sensor.temp.condition[cpqHeTemperatureCondition.Ambient.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Temperature ambient discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Ambient: Temperature could not be determined|<p>Temperature could not be determined.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.temp.condition[cpqHeTemperatureCondition.Ambient.{#SNMPINDEX}]) = 1`|Info|**Manual close**: Yes|
|Ambient: The temperature sensor is outside of normal operating range|<p>If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.temp.condition[cpqHeTemperatureCondition.Ambient.{#SNMPINDEX}]) = 3`|Warning||
|Ambient: The temperature sensor detects a condition that could permanently damage the system.|<p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.temp.condition[cpqHeTemperatureCondition.Ambient.{#SNMPINDEX}]) = 4`|High||

### LLD rule Temperature CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature CPU discovery|<p>Scanning table of Temperature Sensor Entries:</p><p>CPQHLTH-MIB::cpqHeTemperatureTable with cpu(6) filter</p>|SNMP agent|temp.cpu.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Temperature CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU-{#SNMPINDEX}: Temperature|<p>MIB: CPQHLTH-MIB</p><p>Temperature readings of testpoint: CPU-{#SNMPINDEX}</p>|SNMP agent|hp.server.sensor.temp.value[cpqHeTemperatureCelsius.CPU.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|CPU-{#SNMPINDEX}: Temperature sensor condition|<p>MIB: CPQHLTH-MIB</p><p>The Temperature sensor condition.</p><p>This value will be one of the following:</p><p>other(1)</p><p>  Temperature could not be determined.</p><p>ok(2)</p><p>  The temperature sensor is within normal operating range.</p><p>degraded(3)</p><p>  The temperature sensor is outside of normal operating range.</p><p>failed(4)</p><p>  The temperature sensor detects a condition that could</p><p>  permanently damage the system.</p><p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.  If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|SNMP agent|hp.server.sensor.temp.condition[cpqHeTemperatureCondition.CPU.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Temperature CPU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|CPU-{#SNMPINDEX}: Temperature could not be determined|<p>Temperature could not be determined.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.temp.condition[cpqHeTemperatureCondition.CPU.{#SNMPINDEX}]) = 1`|Info|**Manual close**: Yes|
|CPU-{#SNMPINDEX}: The temperature sensor is outside of normal operating range|<p>If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.temp.condition[cpqHeTemperatureCondition.CPU.{#SNMPINDEX}]) = 3`|Warning||
|CPU-{#SNMPINDEX}: The temperature sensor detects a condition that could permanently damage the system.|<p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.temp.condition[cpqHeTemperatureCondition.CPU.{#SNMPINDEX}]) = 4`|High||

### LLD rule Temperature memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature memory discovery|<p>Scanning table of Temperature Sensor Entries:</p><p>CPQHLTH-MIB::cpqHeTemperatureTable with memory(7) filter</p>|SNMP agent|temp.memory.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Temperature memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Memory-{#SNMPINDEX}: Temperature|<p>MIB: CPQHLTH-MIB</p><p>Temperature readings of testpoint: Memory-{#SNMPINDEX}</p>|SNMP agent|hp.server.sensor.temp.value[cpqHeTemperatureCelsius.Memory.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Memory-{#SNMPINDEX}: Temperature sensor condition|<p>MIB: CPQHLTH-MIB</p><p>The Temperature sensor condition.</p><p>This value will be one of the following:</p><p>other(1)</p><p>  Temperature could not be determined.</p><p>ok(2)</p><p>  The temperature sensor is within normal operating range.</p><p>degraded(3)</p><p>  The temperature sensor is outside of normal operating range.</p><p>failed(4)</p><p>  The temperature sensor detects a condition that could</p><p>  permanently damage the system.</p><p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.  If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|SNMP agent|hp.server.sensor.temp.condition[cpqHeTemperatureCondition.Memory.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Temperature memory discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Memory-{#SNMPINDEX}: Temperature could not be determined|<p>Temperature could not be determined.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.temp.condition[cpqHeTemperatureCondition.Memory.{#SNMPINDEX}]) = 1`|Info|**Manual close**: Yes|
|Memory-{#SNMPINDEX}: The temperature sensor is outside of normal operating range|<p>If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.temp.condition[cpqHeTemperatureCondition.Memory.{#SNMPINDEX}]) = 3`|Warning||
|Memory-{#SNMPINDEX}: The temperature sensor detects a condition that could permanently damage the system.|<p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.temp.condition[cpqHeTemperatureCondition.Memory.{#SNMPINDEX}]) = 4`|High||

### LLD rule Temperature PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature PSU discovery|<p>Scanning table of Temperature Sensor Entries:</p><p>CPQHLTH-MIB::cpqHeTemperatureTable with powerSupply(10) filter</p>|SNMP agent|temp.psu.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Temperature PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU-{#SNMPINDEX}: Temperature|<p>MIB: CPQHLTH-MIB</p><p>Temperature readings of testpoint: PSU-{#SNMPINDEX}</p>|SNMP agent|hp.server.sensor.temp.value[cpqHeTemperatureCelsius.PSU.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|PSU-{#SNMPINDEX}: Temperature sensor condition|<p>MIB: CPQHLTH-MIB</p><p>The Temperature sensor condition.</p><p>This value will be one of the following:</p><p>other(1)</p><p>  Temperature could not be determined.</p><p>ok(2)</p><p>  The temperature sensor is within normal operating range.</p><p>degraded(3)</p><p>  The temperature sensor is outside of normal operating range.</p><p>failed(4)</p><p>  The temperature sensor detects a condition that could</p><p>  permanently damage the system.</p><p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.  If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|SNMP agent|hp.server.sensor.temp.condition[cpqHeTemperatureCondition.PSU.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Temperature PSU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PSU-{#SNMPINDEX}: Temperature could not be determined|<p>Temperature could not be determined.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.temp.condition[cpqHeTemperatureCondition.PSU.{#SNMPINDEX}]) = 1`|Info|**Manual close**: Yes|
|PSU-{#SNMPINDEX}: The temperature sensor is outside of normal operating range|<p>If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.temp.condition[cpqHeTemperatureCondition.PSU.{#SNMPINDEX}]) = 3`|Warning||
|PSU-{#SNMPINDEX}: The temperature sensor detects a condition that could permanently damage the system.|<p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.temp.condition[cpqHeTemperatureCondition.PSU.{#SNMPINDEX}]) = 4`|High||

### LLD rule Temperature I/O discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature I/O discovery|<p>Scanning table of Temperature Sensor Entries:</p><p>CPQHLTH-MIB::cpqHeTemperatureTable with ioBoard(5) filter</p>|SNMP agent|temp.io.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Temperature I/O discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|I/O-{#SNMPINDEX}: Temperature|<p>MIB: CPQHLTH-MIB</p><p>Temperature readings of testpoint: I/O-{#SNMPINDEX}</p>|SNMP agent|hp.server.sensor.temp.value[cpqHeTemperatureCelsius."I/O.{#SNMPINDEX}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|I/O-{#SNMPINDEX}: Temperature sensor condition|<p>MIB: CPQHLTH-MIB</p><p>The Temperature sensor condition.</p><p>This value will be one of the following:</p><p>other(1)</p><p>  Temperature could not be determined.</p><p>ok(2)</p><p>  The temperature sensor is within normal operating range.</p><p>degraded(3)</p><p>  The temperature sensor is outside of normal operating range.</p><p>failed(4)</p><p>  The temperature sensor detects a condition that could</p><p>  permanently damage the system.</p><p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.  If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|SNMP agent|hp.server.sensor.temp.condition[cpqHeTemperatureCondition."I/O.{#SNMPINDEX}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Temperature I/O discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|I/O-{#SNMPINDEX}: Temperature could not be determined|<p>Temperature could not be determined.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.temp.condition[cpqHeTemperatureCondition."I/O.{#SNMPINDEX}"]) = 1`|Info|**Manual close**: Yes|
|I/O-{#SNMPINDEX}: The temperature sensor is outside of normal operating range|<p>If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.temp.condition[cpqHeTemperatureCondition."I/O.{#SNMPINDEX}"]) = 3`|Warning||
|I/O-{#SNMPINDEX}: The temperature sensor detects a condition that could permanently damage the system.|<p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.temp.condition[cpqHeTemperatureCondition."I/O.{#SNMPINDEX}"]) = 4`|High||

### LLD rule Temperature system discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature system discovery|<p>Scanning table of Temperature Sensor Entries:</p><p>CPQHLTH-MIB::cpqHeTemperatureTable with system(3) filter</p>|SNMP agent|temp.system.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Temperature system discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|System-{#SNMPINDEX}: Temperature|<p>MIB: CPQHLTH-MIB</p><p>Temperature readings of testpoint: System-{#SNMPINDEX}</p>|SNMP agent|hp.server.sensor.temp.value[cpqHeTemperatureCelsius.System.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|System-{#SNMPINDEX}: Temperature sensor condition|<p>MIB: CPQHLTH-MIB</p><p>The Temperature sensor condition.</p><p>This value will be one of the following:</p><p>other(1)</p><p>  Temperature could not be determined.</p><p>ok(2)</p><p>  The temperature sensor is within normal operating range.</p><p>degraded(3)</p><p>  The temperature sensor is outside of normal operating range.</p><p>failed(4)</p><p>  The temperature sensor detects a condition that could</p><p>  permanently damage the system.</p><p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.  If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|SNMP agent|hp.server.sensor.temp.condition[cpqHeTemperatureCondition.System.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Temperature system discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|System-{#SNMPINDEX}: Temperature could not be determined|<p>Temperature could not be determined.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.temp.condition[cpqHeTemperatureCondition.System.{#SNMPINDEX}]) = 1`|Info|**Manual close**: Yes|
|System-{#SNMPINDEX}: The temperature sensor is outside of normal operating range|<p>If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.temp.condition[cpqHeTemperatureCondition.System.{#SNMPINDEX}]) = 3`|Warning||
|System-{#SNMPINDEX}: The temperature sensor detects a condition that could permanently damage the system.|<p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.temp.condition[cpqHeTemperatureCondition.System.{#SNMPINDEX}]) = 4`|High||

### LLD rule PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU discovery|<p>CPQHLTH-MIB::cpqHeFltTolPowerSupplyStatus</p>|SNMP agent|psu.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Chassis {#CHASSIS_NUM}, bay {#BAY_NUM}: Power supply status|<p>MIB: CPQHLTH-MIB</p><p>The condition of the power supply. This value will be one of the following:</p><p>other(1)  The status could not be determined or not present.</p><p>ok(2)  The power supply is operating normally.</p><p>degraded(3)  A temperature sensor, fan or other power supply component is  outside of normal operating range.</p><p>failed(4)  A power supply component detects a condition that could  permanently damage the system.</p>|SNMP agent|hp.server.sensor.psu.status[cpqHeFltTolPowerSupplyCondition.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for PSU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Chassis {#CHASSIS_NUM}, bay {#BAY_NUM}: Power supply is in critical state|<p>Please check the power supply unit for errors.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.psu.status[cpqHeFltTolPowerSupplyCondition.{#SNMPINDEX}])={$PSU.STATUS.CRIT}`|Average||
|Chassis {#CHASSIS_NUM}, bay {#BAY_NUM}: Power supply is in warning state|<p>Please check the power supply unit for errors.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.psu.status[cpqHeFltTolPowerSupplyCondition.{#SNMPINDEX}])={$PSU.STATUS.WARN}`|Warning|**Depends on**:<br><ul><li>Chassis {#CHASSIS_NUM}, bay {#BAY_NUM}: Power supply is in critical state</li></ul>|

### LLD rule FAN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN discovery|<p>CPQHLTH-MIB::cpqHeFltTolFanCondition</p>|SNMP agent|fan.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for FAN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan {#SNMPINDEX}: Fan status|<p>MIB: CPQHLTH-MIB</p><p>The condition of the fan.</p><p>This value will be one of the following:</p><p>other(1)  Fan status detection is not supported by this system or driver.</p><p>ok(2)  The fan is operating properly.</p><p>degraded(2)  A redundant fan is not operating properly.</p><p>failed(4)  A non-redundant fan is not operating properly.</p>|SNMP agent|hp.server.sensor.fan.status[cpqHeFltTolFanCondition.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for FAN discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Fan {#SNMPINDEX}: Fan is in critical state|<p>Please check the fan unit.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.fan.status[cpqHeFltTolFanCondition.{#SNMPINDEX}])={$FAN.STATUS.CRIT}`|Average||
|Fan {#SNMPINDEX}: Fan is in warning state|<p>Please check the fan unit.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.sensor.fan.status[cpqHeFltTolFanCondition.{#SNMPINDEX}])={$FAN.STATUS.WARN}`|Warning|**Depends on**:<br><ul><li>Fan {#SNMPINDEX}: Fan is in critical state</li></ul>|

### LLD rule Array controller discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Array controller discovery|<p>Scanning table of Array controllers: CPQIDA-MIB::cpqDaCntlrTable</p>|SNMP agent|array.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Array controller discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#CNTLR_LOCATION}: Disk array controller status|<p>MIB: CPQIDA-MIB</p><p>This value represents the overall condition of this controller,</p><p>and any associated logical drives, physical drives, and array accelerators.</p>|SNMP agent|hp.server.hw.diskarray.status[cpqDaCntlrCondition.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#CNTLR_LOCATION}: Disk array controller model|<p>MIB: CPQIDA-MIB</p><p>Array Controller Model. The type of controller card.</p>|SNMP agent|hp.server.hw.diskarray.model[cpqDaCntlrModel.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Array controller discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#CNTLR_LOCATION}: Disk array controller is in critical state|<p>Please check the device for faults.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.hw.diskarray.status[cpqDaCntlrCondition.{#SNMPINDEX}])={$DISK.ARRAY.STATUS.CRIT}`|High||
|{#CNTLR_LOCATION}: Disk array controller is in warning state|<p>Please check the device for faults.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.hw.diskarray.status[cpqDaCntlrCondition.{#SNMPINDEX}])={$DISK.ARRAY.STATUS.WARN}`|Average|**Depends on**:<br><ul><li>{#CNTLR_LOCATION}: Disk array controller is in critical state</li></ul>|

### LLD rule Array controller cache discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Array controller cache discovery|<p>Scanning table of Array controllers: CPQIDA-MIB::cpqDaAccelTable</p>|SNMP agent|array.cache.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Array controller cache discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|#{#CACHE_CNTRL_INDEX}: Disk array cache controller status|<p>MIB: CPQIDA-MIB</p><p>Cache Module/Operations Status. This describes the status of the cache module and/or cache operations.</p><p>Note that for some controller models, a cache module board that physically attaches to the controller or chipset may not be an available option.</p><p></p><p>The status can be:</p><p>Other (1)</p><p> Indicates that the instrument agent does not recognize the status of the cache module. You may need to upgrade the instrument agent.</p><p></p><p>Invalid (2)</p><p> Indicates that a cache module board has not been installed in this system or is present but not configured.</p><p></p><p>Enabled (3)</p><p> Indicates that cache operations are currently configured and enabled for at least one logical drive.</p><p></p><p>Temporarily Disabled (4)</p><p> Indicates that cache operations have been temporarily disabled. View the cache module board error code object to determine why the write cache operations have been temporarily disabled.</p><p></p><p>Permanently Disabled (5)</p><p> Indicates that cache operations have been permanently disabled. View the cache module board error code object to determine why the write cache operations have been disabled.</p><p></p><p>Cache Module Flash Memory Not Attached (6)</p><p> Indicates that the flash memory component of the flash backed cache module is not attached. This status will be set when the flash memory is not attached and the Supercap is attached. This value is only used on flash backed cache modules that support removable flash memory.</p><p></p><p>Cache Module Degraded Failsafe Speed (7)</p><p> Indicates that the cache module board is currently degraded and operating at a failsafe speed. View variables cpqDaCacheMemoryDataWidth and cpqDaCacheMemoryTransferRate to obtain the cache module board`s current memory data width and memory transfer rate.</p><p></p><p>Cache Module Critical Failure (8)</p><p> Indicates that the cache module board has encountered a critical failure. The controller is currently operating in Zero Memory Raid mode.</p><p></p><p>Read Cache Could Not Be Mapped (9)</p><p> Indicates that the read cache memory in a split cache configuration could not be mapped by the operating system and as a result is not available. This status may be caused by virtual space limitations in certain operating systems and is only applicable to B-Series controllers.</p>|SNMP agent|hp.server.hw.diskarray.cache.status[cpqDaAccelStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|#{#CACHE_CNTRL_INDEX}: Disk array cache controller battery status|<p>MIB: CPQIDA-MIB</p><p>Cache Module Board Backup Power Status. This monitors the status of each backup power source on the board.</p><p>The backup power source can only recharge when the system has power applied. The type of backup power source used is indicated by cpqDaAccelBackupPowerSource.</p><p>The following values are valid:</p><p>Other (1)  Indicates that the instrument agent does not recognize  backup power status.  You may need to update your software.</p><p></p><p>Ok (2)  The backup power source is fully charged.</p><p></p><p>Recharging (3)  The array controller has one or more cache module backup power  sources that are recharging.</p><p>Cache module operations such as Battery/Flash Backed Write Cache, Expansion, Extension and Migration are temporarily suspended until the backup power source is fully charged.</p><p>Cache module operations will automatically resume  when charging is complete.</p><p></p><p>Failed (4)  The battery pack is below the sufficient voltage level and  has not recharged in 36 hours.</p><p>Your Cache Module board  needs to be serviced.</p><p></p><p>Degraded (5)  The battery is still operating, however, one of the batteries  in the pack has failed to recharge properly.</p><p>Your Cache  Module board should be serviced as soon as possible.</p><p></p><p>NotPresent (6)  A backup power source is not present on the cache module board. Some controllers do not have backup power sources.</p><p></p><p>Capacitor Failed (7)  The flash backed cache module capacitor is below the sufficient voltage level and has not recharged in 10 minutes.  Your Cache Module board needs to be serviced.</p>|SNMP agent|hp.server.hw.diskarray.cache.battery.status[cpqDaAccelBattery.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Array controller cache discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|#{#CACHE_CNTRL_INDEX}: Disk array cache controller is in critical state!|<p>Please check the device for faults.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.hw.diskarray.cache.status[cpqDaAccelStatus.{#SNMPINDEX}])={$DISK.ARRAY.CACHE.STATUS.CRIT:"cacheModCriticalFailure"}`|Average||
|#{#CACHE_CNTRL_INDEX}: Disk array cache controller is in warning state|<p>Please check the device for faults.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.hw.diskarray.cache.status[cpqDaAccelStatus.{#SNMPINDEX}])={$DISK.ARRAY.CACHE.STATUS.WARN:"cacheModDegradedFailsafeSpeed"} or last(/HPE ProLiant DL360 by SNMP/hp.server.hw.diskarray.cache.status[cpqDaAccelStatus.{#SNMPINDEX}])={$DISK.ARRAY.CACHE.STATUS.WARN:"cacheReadCacheNotMapped"} or last(/HPE ProLiant DL360 by SNMP/hp.server.hw.diskarray.cache.status[cpqDaAccelStatus.{#SNMPINDEX}])={$DISK.ARRAY.CACHE.STATUS.WARN:"cacheModFlashMemNotAttached"}`|Warning|**Depends on**:<br><ul><li>#{#CACHE_CNTRL_INDEX}: Disk array cache controller is in critical state!</li></ul>|
|#{#CACHE_CNTRL_INDEX}: Disk array cache controller is not in optimal state|<p>Please check the device for faults.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.hw.diskarray.cache.status[cpqDaAccelStatus.{#SNMPINDEX}])<>{$DISK.ARRAY.CACHE.STATUS.OK:"enabled"} and last(/HPE ProLiant DL360 by SNMP/hp.server.hw.diskarray.cache.status[cpqDaAccelStatus.{#SNMPINDEX}])<>{$DISK.ARRAY.CACHE.STATUS.WARN:"invalid"}`|Warning|**Depends on**:<br><ul><li>#{#CACHE_CNTRL_INDEX}: Disk array cache controller is in warning state</li><li>#{#CACHE_CNTRL_INDEX}: Disk array cache controller is in critical state!</li></ul>|
|#{#CACHE_CNTRL_INDEX}: Disk array cache controller battery is in critical state|<p>Please check the device for faults.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.hw.diskarray.cache.battery.status[cpqDaAccelBattery.{#SNMPINDEX}])={$DISK.ARRAY.CACHE.BATTERY.STATUS.CRIT:"failed"} or last(/HPE ProLiant DL360 by SNMP/hp.server.hw.diskarray.cache.battery.status[cpqDaAccelBattery.{#SNMPINDEX}])={$DISK.ARRAY.CACHE.BATTERY.STATUS.CRIT:"capacitorFailed"}`|Average||
|#{#CACHE_CNTRL_INDEX}: Disk array cache controller battery is in warning state|<p>Please check the device for faults.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.hw.diskarray.cache.battery.status[cpqDaAccelBattery.{#SNMPINDEX}])={$DISK.ARRAY.CACHE.BATTERY.STATUS.WARN:"degraded"}`|Warning|**Depends on**:<br><ul><li>#{#CACHE_CNTRL_INDEX}: Disk array cache controller battery is in critical state</li></ul>|

### LLD rule Physical disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Physical disk discovery|<p>Scanning  table of physical drive entries CPQIDA-MIB::cpqDaPhyDrvTable.</p>|SNMP agent|physicaldisk.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Physical disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DISK_LOCATION}: Physical disk status|<p>MIB: CPQIDA-MIB</p><p>Physical Drive Status. This shows the status of the physical drive. The following values are valid for the physical drive status:</p><p>other (1)  Indicates that the instrument agent does not recognize  the drive.</p><p>You may need to upgrade your instrument agent  and/or driver software.</p><p>ok (2)  Indicates the drive is functioning properly.</p><p>failed (3)  Indicates that the drive is no longer operating and  should be replaced.</p><p>predictiveFailure(4)  Indicates that the drive has a predictive failure error and  should be replaced.</p>|SNMP agent|hp.server.hw.physicaldisk.status[cpqDaPhyDrvStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#DISK_LOCATION}: Physical disk S.M.A.R.T. status|<p>MIB: CPQIDA-MIB</p><p>Physical Drive S.M.A.R.T Status. The following values are defined:</p><p>other(1)  The agent is unable to determine if the status of S.M.A.R.T  predictive failure monitoring for this drive.</p><p>ok(2)  Indicates the drive is functioning properly.</p><p>replaceDrive(3)  Indicates that the drive has a S.M.A.R.T predictive failure  error and should be replaced.</p>|SNMP agent|hp.server.hw.physicaldisk.smart_status[cpqDaPhyDrvSmartStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#DISK_LOCATION}: Physical disk serial number|<p>MIB: CPQIDA-MIB</p><p>Physical Drive Serial Number.</p><p>This is the serial number assigned to the physical drive.</p><p>This value is based upon the serial number as returned by the SCSI inquiry command</p><p>but may have been modified due to space limitations.  This can be used for identification purposes.</p>|SNMP agent|hp.server.hw.physicaldisk.serialnumber[cpqDaPhyDrvSerialNum.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#DISK_LOCATION}: Physical disk model name|<p>MIB: CPQIDA-MIB</p><p>Physical Drive Model. This is a text description of the physical drive.</p><p>The text that appears depends upon who manufactured the drive and the drive type.</p><p>If a drive fails, note the model to identify the type of drive necessary for replacement.</p><p>If a model number is not present, you may not have properly initialized the drive array to which the physical drive is attached for monitoring.</p>|SNMP agent|hp.server.hw.physicaldisk.model[cpqDaPhyDrvModel.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#DISK_LOCATION}: Physical disk media type|<p>MIB: CPQIDA-MIB</p><p>Drive Array Physical Drive Media Type. The following values are defined:</p><p>other(1)  The instrument agent is unable to determine the physical drive's media type.</p><p>rotatingPlatters(2)  The physical drive media is composed of rotating platters.</p><p>solidState(3)  The physical drive media is composed of solid state electronics.</p>|SNMP agent|hp.server.hw.physicaldisk.media_type[cpqDaPhyDrvMediaType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#DISK_LOCATION}: Disk size|<p>MIB: CPQIDA-MIB</p><p>Physical Drive Size in MB.</p><p>This is the size of the physical drive in megabytes.</p><p>This value is calculated using the value 1,048,576 (2^20) as a megabyte.</p><p>Drive manufacturers sometimes use the number 1,000,000 as a megabyte when giving drive capacities so this value may differ</p><p>from the advertised size of a drive. This field is only applicable for controllers which support SCSI drives,</p><p>and therefore is not supported by the IDA or IDA-2 controllers. The field will contain 0xFFFFFFFF if the drive capacity cannot be calculated</p><p>or if the controller does not support SCSI drives.</p>|SNMP agent|hp.server.hw.physicaldisk.size[cpqDaPhyDrvMediaType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Physical disk discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#DISK_LOCATION}: Physical disk failed|<p>Please check physical disk for warnings or errors.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.hw.physicaldisk.status[cpqDaPhyDrvStatus.{#SNMPINDEX}])={$DISK.STATUS.FAIL}`|High||
|{#DISK_LOCATION}: Physical disk is in warning state|<p>Please check physical disk for warnings or errors.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.hw.physicaldisk.status[cpqDaPhyDrvStatus.{#SNMPINDEX}])={$DISK.STATUS.WARN}`|Warning|**Depends on**:<br><ul><li>{#DISK_LOCATION}: Physical disk failed</li></ul>|
|{#DISK_LOCATION}: Physical disk S.M.A.R.T. failed|<p>Disk probably requires replacement.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.hw.physicaldisk.smart_status[cpqDaPhyDrvSmartStatus.{#SNMPINDEX}])={$DISK.SMART.STATUS.FAIL:"replaceDrive"} or last(/HPE ProLiant DL360 by SNMP/hp.server.hw.physicaldisk.smart_status[cpqDaPhyDrvSmartStatus.{#SNMPINDEX}])={$DISK.SMART.STATUS.FAIL:"replaceDriveSSDWearOut"}`|High|**Depends on**:<br><ul><li>{#DISK_LOCATION}: Physical disk failed</li></ul>|
|{#DISK_LOCATION}: Disk has been replaced|<p>Disk serial number has changed. Acknowledge to close the problem manually.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.hw.physicaldisk.serialnumber[cpqDaPhyDrvSerialNum.{#SNMPINDEX}],#1)<>last(/HPE ProLiant DL360 by SNMP/hp.server.hw.physicaldisk.serialnumber[cpqDaPhyDrvSerialNum.{#SNMPINDEX}],#2) and length(last(/HPE ProLiant DL360 by SNMP/hp.server.hw.physicaldisk.serialnumber[cpqDaPhyDrvSerialNum.{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes|

### LLD rule Virtual disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Virtual disk discovery|<p>CPQIDA-MIB::cpqDaLogDrvTable</p>|SNMP agent|virtualdisk.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Virtual disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk {#SNMPINDEX}({#DISK_NAME}): Status|<p>Logical Drive Status.</p>|SNMP agent|hp.server.hw.virtualdisk.status[cpqDaLogDrvStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Disk {#SNMPINDEX}({#DISK_NAME}): Layout type|<p>Logical Drive Fault Tolerance.</p><p>This shows the fault tolerance mode of the logical drive.</p>|SNMP agent|hp.server.hw.virtualdisk.layout[cpqDaLogDrvFaultTol.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Disk {#SNMPINDEX}({#DISK_NAME}): Disk size|<p>Logical Drive Size.</p><p>This is the size of the logical drive in megabytes.  This value</p><p>is calculated using the value 1,048,576 (2^20) as a megabyte.</p><p>Drive manufacturers sometimes use the number 1,000,000 as a</p><p>megabyte when giving drive capacities so this value may</p><p>differ from the advertised size of a drive.</p>|SNMP agent|hp.server.hw.virtualdisk.size[cpqDaLogDrvSize.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Virtual disk discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Disk {#SNMPINDEX}({#DISK_NAME}): Virtual disk failed|<p>Please check virtual disk for warnings or errors.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.hw.virtualdisk.status[cpqDaLogDrvStatus.{#SNMPINDEX}])={$VDISK.STATUS.CRIT}`|High||
|Disk {#SNMPINDEX}({#DISK_NAME}): Virtual disk is not in OK state|<p>Please check virtual disk for warnings or errors.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.hw.virtualdisk.status[cpqDaLogDrvStatus.{#SNMPINDEX}])<>{$VDISK.STATUS.OK}`|Warning|**Depends on**:<br><ul><li>Disk {#SNMPINDEX}({#DISK_NAME}): Virtual disk failed</li></ul>|

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>CPQIDA-MIB::cpqNicIfPhysAdapterTable</p>|SNMP agent|net.if.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#ADAPTER_NAME} port {#ADAPTER_INDEX}: Status|<p>MIB: CPQNIC-MIB</p><p>The physical adapter status. The following values are valid:</p><p>unknown(1)</p><p>  The instrument agent was not able to determine the status of the adapter. The instrument agent may need to be upgraded.</p><p>ok(2)</p><p>  The physical adapter is operating properly.</p><p>generalFailure(3)</p><p>  The physical adapter has failed.</p><p>linkFailure(4)</p><p>  The physical adapter has lost link. Check the cable connections to this adapter.</p>|SNMP agent|hp.server.net.if.status[cpqNicIfPhysAdapterStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#ADAPTER_NAME} port {#ADAPTER_INDEX}: Adapter has failed|<p>Please check the physical adapter.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.net.if.status[cpqNicIfPhysAdapterStatus.{#SNMPINDEX}])=3`|High||
|{#ADAPTER_NAME} port {#ADAPTER_INDEX}: Adapter has lost link|<p>Please check the cable connections to this adapter.</p>|`last(/HPE ProLiant DL360 by SNMP/hp.server.net.if.status[cpqNicIfPhysAdapterStatus.{#SNMPINDEX}])=4`|Average|**Depends on**:<br><ul><li>{#ADAPTER_NAME} port {#ADAPTER_INDEX}: Adapter has failed</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

