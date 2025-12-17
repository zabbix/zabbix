
# HP iLO by SNMP

## Overview

for HP iLO adapters that support SNMP get. Or via operating system, using SNMP HP subagent


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- iLo4, HP Proliant G9 

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$HEALTH_CRIT_STATUS}||`4`|
|{$HEALTH_WARN_STATUS}||`3`|
|{$PSU_CRIT_STATUS}||`4`|
|{$PSU_WARN_STATUS}||`3`|
|{$FAN_CRIT_STATUS}||`4`|
|{$FAN_WARN_STATUS}||`3`|
|{$DISK_ARRAY_CRIT_STATUS}||`4`|
|{$DISK_ARRAY_WARN_STATUS}||`3`|
|{$DISK_ARRAY_CACHE_CRIT_STATUS:"cacheModCriticalFailure"}||`8`|
|{$DISK_ARRAY_CACHE_WARN_STATUS:"invalid"}||`2`|
|{$DISK_ARRAY_CACHE_WARN_STATUS:"cacheModDegradedFailsafeSpeed"}||`7`|
|{$DISK_ARRAY_CACHE_WARN_STATUS:"cacheReadCacheNotMapped"}||`9`|
|{$DISK_ARRAY_CACHE_WARN_STATUS:"cacheModFlashMemNotAttached"}||`6`|
|{$DISK_ARRAY_CACHE_OK_STATUS:"enabled"}||`3`|
|{$DISK_ARRAY_CACHE_BATTERY_CRIT_STATUS:"failed"}||`4`|
|{$DISK_ARRAY_CACHE_BATTERY_CRIT_STATUS:"capacitorFailed"}||`7`|
|{$DISK_ARRAY_CACHE_BATTERY_WARN_STATUS:"degraded"}||`5`|
|{$DISK_ARRAY_CACHE_BATTERY_WARN_STATUS:"notPresent"}||`6`|
|{$VDISK_CRIT_STATUS}||`3`|
|{$VDISK_OK_STATUS}||`2`|
|{$DISK_WARN_STATUS}||`4`|
|{$DISK_FAIL_STATUS}||`3`|
|{$DISK_SMART_FAIL_STATUS:"replaceDrive"}||`3`|
|{$DISK_SMART_FAIL_STATUS:"replaceDriveSSDWearOut"}||`4`|
|{$SNMP.TIMEOUT}|<p>Time interval for the SNMP availability trigger.</p>|`5m`|
|{$ICMP_LOSS_WARN}|<p>Warning threshold of ICMP packet loss in %.</p>|`20`|
|{$ICMP_RESPONSE_TIME_WARN}|<p>Warning threshold of the average ICMP response time in seconds.</p>|`0.15`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|System temperature status|<p>MIB: CPQHLTH-MIB</p><p>This value specifies the overall condition of the system's thermal environment.</p><p>This value will be one of the following:</p><p>other(1)  Temperature could not be determined.</p><p>ok(2)  The temperature sensor is within normal operating range.</p><p>degraded(3)  The temperature sensor is outside of normal operating range.</p><p>failed(4)  The temperature sensor detects a condition that could  permanently damage the system.</p>|SNMP agent|sensor.temp.status[cpqHeThermalCondition.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Overall system health status|<p>MIB: CPQHLTH-MIB</p><p>The overall condition. This object represents the overall status of the server information represented by this MIB.</p>|SNMP agent|system.status[cpqHeMibCondition.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Hardware model name|<p>MIB: CPQSINFO-MIB</p><p>The machine product name.The name of the machine used in this system.</p>|SNMP agent|system.hw.model<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Hardware serial number|<p>MIB: CPQSINFO-MIB</p><p>The serial number of the physical system unit. The string will be empty if the system does not report the serial number function.</p>|SNMP agent|system.hw.serialnumber<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
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
|HP iLO: System status is in critical state|<p>Please check the device for errors</p>|`count(/HP iLO by SNMP/system.status[cpqHeMibCondition.0],#1,"eq","{$HEALTH_CRIT_STATUS}")=1`|High||
|HP iLO: System status is in warning state|<p>Please check the device for warnings</p>|`count(/HP iLO by SNMP/system.status[cpqHeMibCondition.0],#1,"eq","{$HEALTH_WARN_STATUS}")=1`|Warning|**Depends on**:<br><ul><li>HP iLO: System status is in critical state</li></ul>|
|HP iLO: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/HP iLO by SNMP/system.hw.serialnumber,#1)<>last(/HP iLO by SNMP/system.hw.serialnumber,#2) and length(last(/HP iLO by SNMP/system.hw.serialnumber))>0`|Info|**Manual close**: Yes|
|HP iLO: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/HP iLO by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/HP iLO by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/HP iLO by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/HP iLO by SNMP/system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>HP iLO: No SNMP data collection</li></ul>|
|HP iLO: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/HP iLO by SNMP/system.name,#1)<>last(/HP iLO by SNMP/system.name,#2) and length(last(/HP iLO by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|HP iLO: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/HP iLO by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>HP iLO: Unavailable by ICMP ping</li></ul>|
|HP iLO: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/HP iLO by SNMP/icmpping,#3)=0`|High||
|HP iLO: High ICMP ping loss|<p>ICMP packets loss detected.</p>|`min(/HP iLO by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/HP iLO by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>HP iLO: Unavailable by ICMP ping</li></ul>|
|HP iLO: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/HP iLO by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>HP iLO: High ICMP ping loss</li><li>HP iLO: Unavailable by ICMP ping</li></ul>|

### LLD rule Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature Discovery|<p>Scanning table of Temperature Sensor Entries: CPQHLTH-MIB::cpqHeTemperatureTable</p>|SNMP agent|tempDescr.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SNMPINDEX}: Temperature|<p>MIB: CPQHLTH-MIB</p><p>Temperature readings of testpoint: {#SNMPINDEX}</p>|SNMP agent|sensor.temp.value[cpqHeTemperatureCelsius.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#SNMPINDEX}: Temperature sensor location|<p>MIB: CPQHLTH-MIB</p><p>This specifies the location of the temperature sensor present in the system.</p>|SNMP agent|sensor.temp.locale[cpqHeTemperatureLocale.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#SNMPINDEX}: Temperature sensor condition|<p>MIB: CPQHLTH-MIB</p><p>The Temperature sensor condition.</p><p>This value will be one of the following:</p><p>other(1)</p><p>  Temperature could not be determined.</p><p>ok(2)</p><p>  The temperature sensor is within normal operating range.</p><p>degraded(3)</p><p>  The temperature sensor is outside of normal operating range.</p><p>failed(4)</p><p>  The temperature sensor detects a condition that could permanently damage the system.</p><p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.</p><p>If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|SNMP agent|sensor.temp.condition[cpqHeTemperatureCondition.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Temperature Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HP iLO: {#SNMPINDEX}: Temperature could not be determined|<p>Temperature could not be determined.</p>|`last(/HP iLO by SNMP/sensor.temp.condition[cpqHeTemperatureCondition.{#SNMPINDEX}]) = 1`|Info|**Manual close**: Yes|
|HP iLO: {#SNMPINDEX}: The temperature sensor is outside of normal operating range|<p>If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|`last(/HP iLO by SNMP/sensor.temp.condition[cpqHeTemperatureCondition.{#SNMPINDEX}]) = 3`|Warning||
|HP iLO: {#SNMPINDEX}: The temperature sensor detects a condition that could permanently damage the system.|<p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.</p>|`last(/HP iLO by SNMP/sensor.temp.condition[cpqHeTemperatureCondition.{#SNMPINDEX}]) = 4`|High||

### LLD rule Temperature Discovery Ambient

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature Discovery Ambient|<p>Scanning table of Temperature Sensor Entries: CPQHLTH-MIB::cpqHeTemperatureTable with ambient(11) and 0.1 index filter</p>|SNMP agent|tempDescr.discovery.ambient<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Temperature Discovery Ambient

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Ambient: Temperature|<p>MIB: CPQHLTH-MIB</p><p>Temperature readings of testpoint: Ambient</p>|SNMP agent|sensor.temp.value[cpqHeTemperatureCelsius.Ambient.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Ambient: Temperature sensor condition|<p>MIB: CPQHLTH-MIB</p><p>The Temperature sensor condition.</p><p>This value will be one of the following:</p><p>other(1)</p><p>  Temperature could not be determined.</p><p>ok(2)</p><p>  The temperature sensor is within normal operating range.</p><p>degraded(3)</p><p>  The temperature sensor is outside of normal operating range.</p><p>failed(4)</p><p>  The temperature sensor detects a condition that could permanently damage the system.</p><p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.  If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|SNMP agent|sensor.temp.condition[cpqHeTemperatureCondition.Ambient.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Temperature Discovery Ambient

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HP iLO: Ambient: Temperature could not be determined|<p>Temperature could not be determined.</p>|`last(/HP iLO by SNMP/sensor.temp.condition[cpqHeTemperatureCondition.Ambient.{#SNMPINDEX}]) = 1`|Info|**Manual close**: Yes|
|HP iLO: Ambient: The temperature sensor is outside of normal operating range|<p>If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|`last(/HP iLO by SNMP/sensor.temp.condition[cpqHeTemperatureCondition.Ambient.{#SNMPINDEX}]) = 3`|Warning||
|HP iLO: Ambient: The temperature sensor detects a condition that could permanently damage the system.|<p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.</p>|`last(/HP iLO by SNMP/sensor.temp.condition[cpqHeTemperatureCondition.Ambient.{#SNMPINDEX}]) = 4`|High||

### LLD rule Temperature Discovery CPU

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature Discovery CPU|<p>Scanning table of Temperature Sensor Entries: CPQHLTH-MIB::cpqHeTemperatureTable with cpu(6) filter</p>|SNMP agent|tempDescr.discovery.cpu<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Temperature Discovery CPU

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU-{#SNMPINDEX}: Temperature|<p>MIB: CPQHLTH-MIB</p><p>Temperature readings of testpoint: CPU-{#SNMPINDEX}</p>|SNMP agent|sensor.temp.value[cpqHeTemperatureCelsius.CPU.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|CPU-{#SNMPINDEX}: Temperature sensor condition|<p>MIB: CPQHLTH-MIB</p><p>The Temperature sensor condition.</p><p>This value will be one of the following:</p><p>other(1)</p><p>  Temperature could not be determined.</p><p>ok(2)</p><p>  The temperature sensor is within normal operating range.</p><p>degraded(3)</p><p>  The temperature sensor is outside of normal operating range.</p><p>failed(4)</p><p>  The temperature sensor detects a condition that could</p><p>  permanently damage the system.</p><p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.  If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|SNMP agent|sensor.temp.condition[cpqHeTemperatureCondition.CPU.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Temperature Discovery CPU

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HP iLO: CPU-{#SNMPINDEX}: Temperature could not be determined|<p>Temperature could not be determined.</p>|`last(/HP iLO by SNMP/sensor.temp.condition[cpqHeTemperatureCondition.CPU.{#SNMPINDEX}]) = 1`|Info|**Manual close**: Yes|
|HP iLO: CPU-{#SNMPINDEX}: The temperature sensor is outside of normal operating range|<p>If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|`last(/HP iLO by SNMP/sensor.temp.condition[cpqHeTemperatureCondition.CPU.{#SNMPINDEX}]) = 3`|Warning||
|HP iLO: CPU-{#SNMPINDEX}: The temperature sensor detects a condition that could permanently damage the system.|<p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.</p>|`last(/HP iLO by SNMP/sensor.temp.condition[cpqHeTemperatureCondition.CPU.{#SNMPINDEX}]) = 4`|High||

### LLD rule Temperature Discovery Memory

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature Discovery Memory|<p>Scanning table of Temperature Sensor Entries: CPQHLTH-MIB::cpqHeTemperatureTable with memory(7) filter</p>|SNMP agent|tempDescr.discovery.memory<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Temperature Discovery Memory

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Memory-{#SNMPINDEX}: Temperature|<p>MIB: CPQHLTH-MIB</p><p>Temperature readings of testpoint: Memory-{#SNMPINDEX}</p>|SNMP agent|sensor.temp.value[cpqHeTemperatureCelsius.Memory.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Memory-{#SNMPINDEX}: Temperature sensor condition|<p>MIB: CPQHLTH-MIB</p><p>The Temperature sensor condition.</p><p>This value will be one of the following:</p><p>other(1)</p><p>  Temperature could not be determined.</p><p>ok(2)</p><p>  The temperature sensor is within normal operating range.</p><p>degraded(3)</p><p>  The temperature sensor is outside of normal operating range.</p><p>failed(4)</p><p>  The temperature sensor detects a condition that could</p><p>  permanently damage the system.</p><p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.  If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|SNMP agent|sensor.temp.condition[cpqHeTemperatureCondition.Memory.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Temperature Discovery Memory

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HP iLO: Memory-{#SNMPINDEX}: Temperature could not be determined|<p>Temperature could not be determined.</p>|`last(/HP iLO by SNMP/sensor.temp.condition[cpqHeTemperatureCondition.Memory.{#SNMPINDEX}]) = 1`|Info|**Manual close**: Yes|
|HP iLO: Memory-{#SNMPINDEX}: The temperature sensor is outside of normal operating range|<p>If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|`last(/HP iLO by SNMP/sensor.temp.condition[cpqHeTemperatureCondition.Memory.{#SNMPINDEX}]) = 3`|Warning||
|HP iLO: Memory-{#SNMPINDEX}: The temperature sensor detects a condition that could permanently damage the system.|<p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.</p>|`last(/HP iLO by SNMP/sensor.temp.condition[cpqHeTemperatureCondition.Memory.{#SNMPINDEX}]) = 4`|High||

### LLD rule Temperature Discovery PSU

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature Discovery PSU|<p>Scanning table of Temperature Sensor Entries: CPQHLTH-MIB::cpqHeTemperatureTable with powerSupply(10) filter</p>|SNMP agent|tempDescr.discovery.psu<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Temperature Discovery PSU

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU-{#SNMPINDEX}: Temperature|<p>MIB: CPQHLTH-MIB</p><p>Temperature readings of testpoint: PSU-{#SNMPINDEX}</p>|SNMP agent|sensor.temp.value[cpqHeTemperatureCelsius.PSU.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|PSU-{#SNMPINDEX}: Temperature sensor condition|<p>MIB: CPQHLTH-MIB</p><p>The Temperature sensor condition.</p><p>This value will be one of the following:</p><p>other(1)</p><p>  Temperature could not be determined.</p><p>ok(2)</p><p>  The temperature sensor is within normal operating range.</p><p>degraded(3)</p><p>  The temperature sensor is outside of normal operating range.</p><p>failed(4)</p><p>  The temperature sensor detects a condition that could</p><p>  permanently damage the system.</p><p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.  If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|SNMP agent|sensor.temp.condition[cpqHeTemperatureCondition.PSU.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Temperature Discovery PSU

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HP iLO: PSU-{#SNMPINDEX}: Temperature could not be determined|<p>Temperature could not be determined.</p>|`last(/HP iLO by SNMP/sensor.temp.condition[cpqHeTemperatureCondition.PSU.{#SNMPINDEX}]) = 1`|Info|**Manual close**: Yes|
|HP iLO: PSU-{#SNMPINDEX}: The temperature sensor is outside of normal operating range|<p>If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|`last(/HP iLO by SNMP/sensor.temp.condition[cpqHeTemperatureCondition.PSU.{#SNMPINDEX}]) = 3`|Warning||
|HP iLO: PSU-{#SNMPINDEX}: The temperature sensor detects a condition that could permanently damage the system.|<p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.</p>|`last(/HP iLO by SNMP/sensor.temp.condition[cpqHeTemperatureCondition.PSU.{#SNMPINDEX}]) = 4`|High||

### LLD rule Temperature Discovery I/O

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature Discovery I/O|<p>Scanning table of Temperature Sensor Entries: CPQHLTH-MIB::cpqHeTemperatureTable with ioBoard(5) filter</p>|SNMP agent|tempDescr.discovery.io<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Temperature Discovery I/O

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|I/O-{#SNMPINDEX}: Temperature|<p>MIB: CPQHLTH-MIB</p><p>Temperature readings of testpoint: I/O-{#SNMPINDEX}</p>|SNMP agent|sensor.temp.value[cpqHeTemperatureCelsius."I/O.{#SNMPINDEX}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|I/O-{#SNMPINDEX}: Temperature sensor condition|<p>MIB: CPQHLTH-MIB</p><p>The Temperature sensor condition.</p><p>This value will be one of the following:</p><p>other(1)</p><p>  Temperature could not be determined.</p><p>ok(2)</p><p>  The temperature sensor is within normal operating range.</p><p>degraded(3)</p><p>  The temperature sensor is outside of normal operating range.</p><p>failed(4)</p><p>  The temperature sensor detects a condition that could</p><p>  permanently damage the system.</p><p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.  If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|SNMP agent|sensor.temp.condition[cpqHeTemperatureCondition."I/O.{#SNMPINDEX}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Temperature Discovery I/O

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HP iLO: I/O-{#SNMPINDEX}: Temperature could not be determined|<p>Temperature could not be determined.</p>|`last(/HP iLO by SNMP/sensor.temp.condition[cpqHeTemperatureCondition."I/O.{#SNMPINDEX}"]) = 1`|Info|**Manual close**: Yes|
|HP iLO: I/O-{#SNMPINDEX}: The temperature sensor is outside of normal operating range|<p>If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|`last(/HP iLO by SNMP/sensor.temp.condition[cpqHeTemperatureCondition."I/O.{#SNMPINDEX}"]) = 3`|Warning||
|HP iLO: I/O-{#SNMPINDEX}: The temperature sensor detects a condition that could permanently damage the system.|<p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.</p>|`last(/HP iLO by SNMP/sensor.temp.condition[cpqHeTemperatureCondition."I/O.{#SNMPINDEX}"]) = 4`|High||

### LLD rule Temperature Discovery System

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature Discovery System|<p>Scanning table of Temperature Sensor Entries: CPQHLTH-MIB::cpqHeTemperatureTable with system(3) filter</p>|SNMP agent|tempDescr.discovery.system<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Temperature Discovery System

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|System-{#SNMPINDEX}: Temperature|<p>MIB: CPQHLTH-MIB</p><p>Temperature readings of testpoint: System-{#SNMPINDEX}</p>|SNMP agent|sensor.temp.value[cpqHeTemperatureCelsius.System.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|System-{#SNMPINDEX}: Temperature sensor condition|<p>MIB: CPQHLTH-MIB</p><p>The Temperature sensor condition.</p><p>This value will be one of the following:</p><p>other(1)</p><p>  Temperature could not be determined.</p><p>ok(2)</p><p>  The temperature sensor is within normal operating range.</p><p>degraded(3)</p><p>  The temperature sensor is outside of normal operating range.</p><p>failed(4)</p><p>  The temperature sensor detects a condition that could permanently damage the system.</p><p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.  If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|SNMP agent|sensor.temp.condition[cpqHeTemperatureCondition.System.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Temperature Discovery System

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HP iLO: System-{#SNMPINDEX}: Temperature could not be determined|<p>Temperature could not be determined.</p>|`last(/HP iLO by SNMP/sensor.temp.condition[cpqHeTemperatureCondition.System.{#SNMPINDEX}]) = 1`|Info|**Manual close**: Yes|
|HP iLO: System-{#SNMPINDEX}: The temperature sensor is outside of normal operating range|<p>If the cpqHeThermalDegradedAction is set to shutdown(3) the system will be shutdown if the degraded(3) condition occurs.</p>|`last(/HP iLO by SNMP/sensor.temp.condition[cpqHeTemperatureCondition.System.{#SNMPINDEX}]) = 3`|Warning||
|HP iLO: System-{#SNMPINDEX}: The temperature sensor detects a condition that could permanently damage the system.|<p>The system will automatically shutdown if the failed(4) condition results, so it is unlikely that this value will ever be returned by the agent.</p>|`last(/HP iLO by SNMP/sensor.temp.condition[cpqHeTemperatureCondition.System.{#SNMPINDEX}]) = 4`|High||

### LLD rule PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU Discovery|<p>CPQHLTH-MIB::cpqHeFltTolPowerSupplyStatus</p>|SNMP agent|psu.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Chassis {#CHASSIS_NUM}, bay {#BAY_NUM}: Power supply status|<p>MIB: CPQHLTH-MIB</p><p>The condition of the power supply. This value will be one of the following:</p><p>other(1)  The status could not be determined or not present.</p><p>ok(2)  The power supply is operating normally.</p><p>degraded(3)  A temperature sensor, fan or other power supply component is  outside of normal operating range.</p><p>failed(4)  A power supply component detects a condition that could  permanently damage the system.</p>|SNMP agent|sensor.psu.status[cpqHeFltTolPowerSupplyCondition.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for PSU Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HP iLO: Chassis {#CHASSIS_NUM}, bay {#BAY_NUM}: Power supply is in critical state|<p>Please check the power supply unit for errors</p>|`count(/HP iLO by SNMP/sensor.psu.status[cpqHeFltTolPowerSupplyCondition.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS}")=1`|Average||
|HP iLO: Chassis {#CHASSIS_NUM}, bay {#BAY_NUM}: Power supply is in warning state|<p>Please check the power supply unit for errors</p>|`count(/HP iLO by SNMP/sensor.psu.status[cpqHeFltTolPowerSupplyCondition.{#SNMPINDEX}],#1,"eq","{$PSU_WARN_STATUS}")=1`|Warning|**Depends on**:<br><ul><li>HP iLO: Chassis {#CHASSIS_NUM}, bay {#BAY_NUM}: Power supply is in critical state</li></ul>|

### LLD rule FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN Discovery|<p>CPQHLTH-MIB::cpqHeFltTolFanCondition</p>|SNMP agent|fan.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan {#SNMPINDEX}: Fan status|<p>MIB: CPQHLTH-MIB</p><p>The condition of the fan.</p><p>This value will be one of the following:</p><p>other(1)  Fan status detection is not supported by this system or driver.</p><p>ok(2)  The fan is operating properly.</p><p>degraded(2)  A redundant fan is not operating properly.</p><p>failed(4)  A non-redundant fan is not operating properly.</p>|SNMP agent|sensor.fan.status[cpqHeFltTolFanCondition.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for FAN Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HP iLO: Fan {#SNMPINDEX}: Fan is in critical state|<p>Please check the fan unit</p>|`count(/HP iLO by SNMP/sensor.fan.status[cpqHeFltTolFanCondition.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS}")=1`|Average||
|HP iLO: Fan {#SNMPINDEX}: Fan is in warning state|<p>Please check the fan unit</p>|`count(/HP iLO by SNMP/sensor.fan.status[cpqHeFltTolFanCondition.{#SNMPINDEX}],#1,"eq","{$FAN_WARN_STATUS}")=1`|Warning|**Depends on**:<br><ul><li>HP iLO: Fan {#SNMPINDEX}: Fan is in critical state</li></ul>|

### LLD rule Physical Disk Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Physical Disk Discovery|<p>Scanning  table of physical drive entries CPQIDA-MIB::cpqDaPhyDrvTable.</p>|SNMP agent|physicalDisk.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Physical Disk Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DISK_LOCATION}: Physical disk status|<p>MIB: CPQIDA-MIB</p><p>Physical Drive Status. This shows the status of the physical drive. The following values are valid for the physical drive status:</p><p>other (1)  Indicates that the instrument agent does not recognize  the drive.</p><p>You may need to upgrade your instrument agent  and/or driver software.</p><p>ok (2)  Indicates the drive is functioning properly.</p><p>failed (3)  Indicates that the drive is no longer operating and  should be replaced.</p><p>predictiveFailure(4)  Indicates that the drive has a predictive failure error and  should be replaced.</p>|SNMP agent|system.hw.physicaldisk.status[cpqDaPhyDrvStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#DISK_LOCATION}: Physical disk S.M.A.R.T. status|<p>MIB: CPQIDA-MIB</p><p>Physical Drive S.M.A.R.T Status.The following values are defined:</p><p>other(1)  The agent is unable to determine if the status of S.M.A.R.T  predictive failure monitoring for this drive.</p><p>ok(2)  Indicates the drive is functioning properly.</p><p>replaceDrive(3)  Indicates that the drive has a S.M.A.R.T predictive failure  error and should be replaced.</p>|SNMP agent|system.hw.physicaldisk.smart_status[cpqDaPhyDrvSmartStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#DISK_LOCATION}: Physical disk serial number|<p>MIB: CPQIDA-MIB</p><p>Physical Drive Serial Number.</p><p>This is the serial number assigned to the physical drive.</p><p>This value is based upon the serial number as returned by the SCSI inquiry command</p><p>but may have been modified due to space limitations.  This can be used for identification purposes.</p>|SNMP agent|system.hw.physicaldisk.serialnumber[cpqDaPhyDrvSerialNum.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#DISK_LOCATION}: Physical disk model name|<p>MIB: CPQIDA-MIB</p><p>Physical Drive Model.This is a text description of the physical drive.</p><p>The text that appears depends upon who manufactured the drive and the drive type.</p><p>If a drive fails, note the model to identify the type of drive necessary for replacement.</p><p>If a model number is not present, you may not have properly initialized the drive array to which the physical drive is attached for monitoring.</p>|SNMP agent|system.hw.physicaldisk.model[cpqDaPhyDrvModel.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#DISK_LOCATION}: Physical disk media type|<p>MIB: CPQIDA-MIB</p><p>Drive Array Physical Drive Media Type.The following values are defined:</p><p>other(1)  The instrument agent is unable to determine the physical drive's media type.</p><p>rotatingPlatters(2)  The physical drive media is composed of rotating platters.</p><p>solidState(3)  The physical drive media is composed of solid state electronics.</p>|SNMP agent|system.hw.physicaldisk.media_type[cpqDaPhyDrvMediaType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#DISK_LOCATION}: Disk size|<p>MIB: CPQIDA-MIB</p><p>Physical Drive Size in MB.</p><p>This is the size of the physical drive in megabytes.</p><p>This value is calculated using the value 1,048,576 (2^20) as a megabyte.</p><p>Drive manufacturers sometimes use the number 1,000,000 as a megabyte when giving drive capacities so this value may differ</p><p>from the advertised size of a drive. This field is only applicable for controllers which support SCSI drives,</p><p>and therefore is not supported by the IDA or IDA-2 controllers. The field will contain 0xFFFFFFFF if the drive capacity cannot be calculated</p><p>or if the controller does not support SCSI drives.</p>|SNMP agent|system.hw.physicaldisk.size[cpqDaPhyDrvMediaType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Physical Disk Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HP iLO: {#DISK_LOCATION}: Physical disk failed|<p>Please check physical disk for warnings or errors</p>|`count(/HP iLO by SNMP/system.hw.physicaldisk.status[cpqDaPhyDrvStatus.{#SNMPINDEX}],#1,"eq","{$DISK_FAIL_STATUS}")=1`|High||
|HP iLO: {#DISK_LOCATION}: Physical disk is in warning state|<p>Please check physical disk for warnings or errors</p>|`count(/HP iLO by SNMP/system.hw.physicaldisk.status[cpqDaPhyDrvStatus.{#SNMPINDEX}],#1,"eq","{$DISK_WARN_STATUS}")=1`|Warning|**Depends on**:<br><ul><li>HP iLO: {#DISK_LOCATION}: Physical disk failed</li></ul>|
|HP iLO: {#DISK_LOCATION}: Physical disk S.M.A.R.T. failed|<p>Disk probably requires replacement.</p>|`count(/HP iLO by SNMP/system.hw.physicaldisk.smart_status[cpqDaPhyDrvSmartStatus.{#SNMPINDEX}],#1,"eq","{$DISK_SMART_FAIL_STATUS:\"replaceDrive\"}")=1 or count(/HP iLO by SNMP/system.hw.physicaldisk.smart_status[cpqDaPhyDrvSmartStatus.{#SNMPINDEX}],#1,"eq","{$DISK_SMART_FAIL_STATUS:\"replaceDriveSSDWearOut\"}")=1`|High|**Depends on**:<br><ul><li>HP iLO: {#DISK_LOCATION}: Physical disk failed</li></ul>|
|HP iLO: {#DISK_LOCATION}: Disk has been replaced|<p>Disk serial number has changed. Acknowledge to close the problem manually.</p>|`last(/HP iLO by SNMP/system.hw.physicaldisk.serialnumber[cpqDaPhyDrvSerialNum.{#SNMPINDEX}],#1)<>last(/HP iLO by SNMP/system.hw.physicaldisk.serialnumber[cpqDaPhyDrvSerialNum.{#SNMPINDEX}],#2) and length(last(/HP iLO by SNMP/system.hw.physicaldisk.serialnumber[cpqDaPhyDrvSerialNum.{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes|

### LLD rule Virtual Disk Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Virtual Disk Discovery|<p>CPQIDA-MIB::cpqDaLogDrvTable</p>|SNMP agent|virtualdisk.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Virtual Disk Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk {#SNMPINDEX}({#DISK_NAME}): Status|<p>Logical Drive Status.</p>|SNMP agent|system.hw.virtualdisk.status[cpqDaLogDrvStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Disk {#SNMPINDEX}({#DISK_NAME}): Layout type|<p>Logical Drive Fault Tolerance.</p><p>This shows the fault tolerance mode of the logical drive.</p>|SNMP agent|system.hw.virtualdisk.layout[cpqDaLogDrvFaultTol.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Disk {#SNMPINDEX}({#DISK_NAME}): Disk size|<p>Logical Drive Size.</p><p>This is the size of the logical drive in megabytes.  This value</p><p>is calculated using the value 1,048,576 (2^20) as a megabyte.</p><p>Drive manufacturers sometimes use the number 1,000,000 as a</p><p>megabyte when giving drive capacities so this value may</p><p>differ from the advertised size of a drive.</p>|SNMP agent|system.hw.virtualdisk.size[cpqDaLogDrvSize.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Virtual Disk Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HP iLO: Disk {#SNMPINDEX}({#DISK_NAME}): Virtual disk failed|<p>Please check virtual disk for warnings or errors</p>|`count(/HP iLO by SNMP/system.hw.virtualdisk.status[cpqDaLogDrvStatus.{#SNMPINDEX}],#1,"eq","{$VDISK_CRIT_STATUS}")=1`|High||
|HP iLO: Disk {#SNMPINDEX}({#DISK_NAME}): Virtual disk is not in OK state|<p>Please check virtual disk for warnings or errors</p>|`count(/HP iLO by SNMP/system.hw.virtualdisk.status[cpqDaLogDrvStatus.{#SNMPINDEX}],#1,"ne","{$VDISK_OK_STATUS}")=1`|Warning|**Depends on**:<br><ul><li>HP iLO: Disk {#SNMPINDEX}({#DISK_NAME}): Virtual disk failed</li></ul>|

### LLD rule Array Controller Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Array Controller Discovery|<p>Scanning table of Array controllers: CPQIDA-MIB::cpqDaCntlrTable</p>|SNMP agent|array.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Array Controller Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#CNTLR_LOCATION}: Disk array controller status|<p>MIB: CPQIDA-MIB</p><p>This value represents the overall condition of this controller,</p><p>and any associated logical drives,physical drives, and array accelerators.</p>|SNMP agent|system.hw.diskarray.status[cpqDaCntlrCondition.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#CNTLR_LOCATION}: Disk array controller model|<p>MIB: CPQIDA-MIB</p><p>Array Controller Model. The type of controller card.</p>|SNMP agent|system.hw.diskarray.model[cpqDaCntlrModel.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Array Controller Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HP iLO: {#CNTLR_LOCATION}: Disk array controller is in critical state|<p>Please check the device for faults</p>|`count(/HP iLO by SNMP/system.hw.diskarray.status[cpqDaCntlrCondition.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_CRIT_STATUS}")=1`|High||
|HP iLO: {#CNTLR_LOCATION}: Disk array controller is in warning state|<p>Please check the device for faults</p>|`count(/HP iLO by SNMP/system.hw.diskarray.status[cpqDaCntlrCondition.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_WARN_STATUS}")=1`|Average|**Depends on**:<br><ul><li>HP iLO: {#CNTLR_LOCATION}: Disk array controller is in critical state</li></ul>|

### LLD rule Array Controller Cache Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Array Controller Cache Discovery|<p>Scanning table of Array controllers: CPQIDA-MIB::cpqDaAccelTable</p>|SNMP agent|array.cache.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Array Controller Cache Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|#{#CACHE_CNTRL_INDEX}: Disk array cache controller status|<p>MIB: CPQIDA-MIB</p><p>Cache Module/Operations Status. This describes the status of the cache module and/or cache operations.</p><p>Note that for some controller models, a cache module board that physically attaches to the controller or chipset may not be an available option.</p><p>The status can be:</p><p></p><p>Other (1)</p><p> Indicates that the instrument agent does not recognize the status of the cache module. You may need to upgrade the instrument agent.</p><p></p><p>Invalid (2)</p><p> Indicates that a cache module board has not been installed in this system or is present but not configured.</p><p></p><p>Enabled (3)</p><p> Indicates that cache operations are currently configured and enabled for at least one logical drive.</p><p></p><p>Temporarily Disabled (4)</p><p> Indicates that cache operations have been temporarily disabled. View the cache module board error code object to determine why the write cache operations have been temporarily disabled.</p><p></p><p>Permanently Disabled (5)</p><p> Indicates that cache operations have been permanently disabled. View the cache module board error code object to determine why the write cache operations have been disabled.</p><p></p><p>Cache Module Flash Memory Not Attached (6)</p><p> Indicates that the flash memory component of the flash backed cache module is not attached. This status will be set when the flash memory is not attached and the Supercap is attached. This value is only used on flash backed cache modules that support removable flash memory.</p><p></p><p>Cache Module Degraded Failsafe Speed (7)</p><p> Indicates that the cache module board is currently degraded and operating at a failsafe speed. View variables cpqDaCacheMemoryDataWidth and cpqDaCacheMemoryTransferRate to obtain the cache module board`s current memory data width and memory transfer rate.</p><p></p><p>Cache Module Critical Failure (8)</p><p> Indicates that the cache module board has encountered a critical failure. The controller is currently operating in Zero Memory Raid mode.</p><p></p><p>Read Cache Could Not Be Mapped (9)</p><p> Indicates that the read cache memory in a split cache configuration could not be mapped by the operating system and as a result is not available. This status may be caused by virtual space limitations in certain operating systems and is only applicable to B-Series controllers.</p>|SNMP agent|system.hw.diskarray.cache.status[cpqDaAccelStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|#{#CACHE_CNTRL_INDEX}: Disk array cache controller battery status|<p>MIB: CPQIDA-MIB</p><p>Cache Module Board Backup Power Status. This monitors the status of each backup power source on the board.</p><p>The backup power source can only recharge when the system has power applied. The type of backup power source used is indicated by cpqDaAccelBackupPowerSource.</p><p>The following values are valid:</p><p>Other (1)  Indicates that the instrument agent does not recognize  backup power status.  You may need to update your software.</p><p></p><p>Ok (2)  The backup power source is fully charged.</p><p></p><p>Recharging (3)  The array controller has one or more cache module backup power  sources that are recharging.</p><p>Cache module operations such as Battery/Flash Backed Write Cache, Expansion, Extension and Migration are temporarily suspended until the backup power source is fully charged.</p><p>Cache module operations will automatically resume  when charging is complete.</p><p></p><p>Failed (4)  The battery pack is below the sufficient voltage level and  has not recharged in 36 hours.</p><p>Your Cache Module board  needs to be serviced.</p><p></p><p>Degraded (5)  The battery is still operating, however, one of the batteries  in the pack has failed to recharge properly.</p><p>Your Cache  Module board should be serviced as soon as possible.</p><p></p><p>NotPresent (6)  A backup power source is not present on the cache module board. Some controllers do not have backup power sources.</p><p></p><p>Capacitor Failed (7)  The flash backed cache module capacitor is below the sufficient voltage level and has not recharged in 10 minutes.  Your Cache Module board needs to be serviced.</p>|SNMP agent|system.hw.diskarray.cache.battery.status[cpqDaAccelBattery.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Array Controller Cache Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HP iLO: #{#CACHE_CNTRL_INDEX}: Disk array cache controller is in critical state!|<p>Please check the device for faults</p>|`count(/HP iLO by SNMP/system.hw.diskarray.cache.status[cpqDaAccelStatus.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_CACHE_CRIT_STATUS:\"cacheModCriticalFailure\"}")=1`|Average||
|HP iLO: #{#CACHE_CNTRL_INDEX}: Disk array cache controller is in warning state|<p>Please check the device for faults</p>|`count(/HP iLO by SNMP/system.hw.diskarray.cache.status[cpqDaAccelStatus.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_CACHE_WARN_STATUS:\"cacheModDegradedFailsafeSpeed\"}")=1 or count(/HP iLO by SNMP/system.hw.diskarray.cache.status[cpqDaAccelStatus.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_CACHE_WARN_STATUS:\"cacheReadCacheNotMapped\"}")=1 or count(/HP iLO by SNMP/system.hw.diskarray.cache.status[cpqDaAccelStatus.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_CACHE_WARN_STATUS:\"cacheModFlashMemNotAttached\"}")=1`|Warning|**Depends on**:<br><ul><li>HP iLO: #{#CACHE_CNTRL_INDEX}: Disk array cache controller is in critical state!</li></ul>|
|HP iLO: #{#CACHE_CNTRL_INDEX}: Disk array cache controller is not in optimal state|<p>Please check the device for faults</p>|`count(/HP iLO by SNMP/system.hw.diskarray.cache.status[cpqDaAccelStatus.{#SNMPINDEX}],#1,"ne","{$DISK_ARRAY_CACHE_OK_STATUS:\"enabled\"}")=1 and last(/HP iLO by SNMP/system.hw.diskarray.cache.status[cpqDaAccelStatus.{#SNMPINDEX}])<>{$DISK_ARRAY_CACHE_WARN_STATUS:"invalid"}`|Warning|**Depends on**:<br><ul><li>HP iLO: #{#CACHE_CNTRL_INDEX}: Disk array cache controller is in warning state</li><li>HP iLO: #{#CACHE_CNTRL_INDEX}: Disk array cache controller is in critical state!</li></ul>|
|HP iLO: #{#CACHE_CNTRL_INDEX}: Disk array cache controller battery is in critical state!|<p>Please check the device for faults</p>|`count(/HP iLO by SNMP/system.hw.diskarray.cache.battery.status[cpqDaAccelBattery.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_CACHE_BATTERY_CRIT_STATUS:\"failed\"}")=1 or count(/HP iLO by SNMP/system.hw.diskarray.cache.battery.status[cpqDaAccelBattery.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_CACHE_BATTERY_CRIT_STATUS:\"capacitorFailed\"}")=1`|Average||
|HP iLO: #{#CACHE_CNTRL_INDEX}: Disk array cache controller battery is in warning state|<p>Please check the device for faults</p>|`count(/HP iLO by SNMP/system.hw.diskarray.cache.battery.status[cpqDaAccelBattery.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_CACHE_BATTERY_WARN_STATUS:\"degraded\"}")=1`|Warning|**Depends on**:<br><ul><li>HP iLO: #{#CACHE_CNTRL_INDEX}: Disk array cache controller battery is in critical state!</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

