
# Dell iDRAC by SNMP

## Overview

for Dell servers with iDRAC controllers
http://www.dell.com/support/manuals/us/en/19/dell-openmanage-server-administrator-v8.3/snmp_idrac8/idrac-mib?guid=guid-e686536d-bc8e-4e09-8e8b-de8eb052efee
Supported systems: http://www.dell.com/support/manuals/us/en/04/dell-openmanage-server-administrator-v8.3/snmp_idrac8/supported-systems?guid=guid-f72b75ba-e686-4e8a-b8c5-ca11c7c21381

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- iDRAC7, PowerEdge R620 
- iDRAC8, PowerEdge R730xd 
- iDRAC8, PowerEdge R720 

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$TEMP_WARN:"CPU"}||`70`|
|{$TEMP_WARN:"Ambient"}||`30`|
|{$TEMP_CRIT:"CPU"}||`75`|
|{$TEMP_CRIT:"Ambient"}||`35`|
|{$DISK_ARRAY_WARN_STATUS:"nonCritical"}||`4`|
|{$DISK_ARRAY_CRIT_STATUS:"critical"}||`5`|
|{$DISK_ARRAY_FAIL_STATUS:"nonRecoverable"}||`6`|
|{$DISK_ARRAY_CACHE_BATTERY_CRIT_STATUS}||`3`|
|{$DISK_ARRAY_CACHE_BATTERY_WARN_STATUS}||`4`|
|{$DISK_ARRAY_CACHE_BATTERY_OK_STATUS}||`2`|
|{$DISK_WARN_STATUS:"nonCritical"}||`4`|
|{$DISK_FAIL_STATUS:"critical"}||`5`|
|{$DISK_FAIL_STATUS:"nonRecoverable"}||`6`|
|{$DISK_SMART_FAIL_STATUS}||`1`|
|{$VDISK_CRIT_STATUS:"failed"}||`3`|
|{$VDISK_WARN_STATUS:"degraded"}||`4`|
|{$HEALTH_DISASTER_STATUS}||`6`|
|{$HEALTH_CRIT_STATUS}||`5`|
|{$HEALTH_WARN_STATUS}||`4`|
|{$TEMP_WARN_STATUS}||`4`|
|{$TEMP_CRIT_STATUS}||`5`|
|{$TEMP_DISASTER_STATUS}||`6`|
|{$FAN_WARN_STATUS:"nonCriticalUpper"}||`4`|
|{$FAN_WARN_STATUS:"nonCriticalLower"}||`7`|
|{$FAN_CRIT_STATUS:"criticalUpper"}||`5`|
|{$FAN_CRIT_STATUS:"nonRecoverableUpper"}||`6`|
|{$FAN_CRIT_STATUS:"criticalLower"}||`8`|
|{$FAN_CRIT_STATUS:"nonRecoverableLower"}||`9`|
|{$FAN_CRIT_STATUS:"failed"}||`10`|
|{$PSU_WARN_STATUS:"nonCritical"}||`4`|
|{$PSU_CRIT_STATUS:"critical"}||`5`|
|{$PSU_CRIT_STATUS:"nonRecoverable"}||`6`|
|{$TEMP_CRIT}||`60`|
|{$TEMP_CRIT_LOW}||`5`|
|{$TEMP_WARN}||`50`|
|{$SNMP.TIMEOUT}||`5m`|
|{$ICMP_LOSS_WARN}||`20`|
|{$ICMP_RESPONSE_TIME_WARN}||`0.15`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell iDRAC: Overall system health status|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the overall rollup status of all components in the system being monitored by the remote access card. Includes system, storage, IO devices, iDRAC, CPU, memory, etc.</p>|SNMP agent|system.status[globalSystemStatus.0]|
|Dell iDRAC: Hardware model name|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the model name of the system.</p>|SNMP agent|system.hw.model<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Dell iDRAC: Operating system|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the name of the operating system that the hostis running.</p>|SNMP agent|system.sw.os[systemOSName]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Dell iDRAC: Hardware serial number|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the service tag of the system.</p>|SNMP agent|system.hw.serialnumber<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Dell iDRAC: Firmware version|<p>MIB: IDRAC-MIB-SMIv2</p><p>This attribute defines the firmware version of a remote access card.</p>|SNMP agent|system.hw.firmware<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Dell iDRAC: Uptime (network)|<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p>|SNMP agent|system.net.uptime[sysUpTime.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Dell iDRAC: Uptime (hardware)|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p>|SNMP agent|system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Dell iDRAC: SNMP traps (fallback)|<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p>|SNMP trap|snmptrap.fallback|
|Dell iDRAC: System location|<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet, 3rd floor').  If the location is unknown, the value is the zero-length string.</p>|SNMP agent|system.location[sysLocation.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Dell iDRAC: System contact details|<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</p>|SNMP agent|system.contact[sysContact.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Dell iDRAC: System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity.  This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p>|SNMP agent|system.objectid[sysObjectID.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Dell iDRAC: System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name.  If the name is unknown, the value is the zero-length string.</p>|SNMP agent|system.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Dell iDRAC: System description|<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p>|SNMP agent|system.descr[sysDescr.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Dell iDRAC: SNMP agent availability|<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible value:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p>|Zabbix internal|zabbix[host,snmp,available]|
|Dell iDRAC: ICMP ping||Simple check|icmpping|
|Dell iDRAC: ICMP loss||Simple check|icmppingloss|
|Dell iDRAC: ICMP response time||Simple check|icmppingsec|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell iDRAC: System is in unrecoverable state!|<p>Please check the device for faults</p>|`count(/Dell iDRAC by SNMP/system.status[globalSystemStatus.0],#1,"eq","{$HEALTH_DISASTER_STATUS}")=1`|High||
|Dell iDRAC: System status is in critical state|<p>Please check the device for errors</p>|`count(/Dell iDRAC by SNMP/system.status[globalSystemStatus.0],#1,"eq","{$HEALTH_CRIT_STATUS}")=1`|High|**Depends on**:<br><ul><li>Dell iDRAC: System is in unrecoverable state!</li></ul>|
|Dell iDRAC: System status is in warning state|<p>Please check the device for warnings</p>|`count(/Dell iDRAC by SNMP/system.status[globalSystemStatus.0],#1,"eq","{$HEALTH_WARN_STATUS}")=1`|Warning|**Depends on**:<br><ul><li>Dell iDRAC: System is in unrecoverable state!</li><li>Dell iDRAC: System status is in critical state</li></ul>|
|Dell iDRAC: Operating system description has changed|<p>Operating system description has changed. Possible reasons that system has been updated or replaced. Acknowledge to close the problem manually.</p>|`last(/Dell iDRAC by SNMP/system.sw.os[systemOSName],#1)<>last(/Dell iDRAC by SNMP/system.sw.os[systemOSName],#2) and length(last(/Dell iDRAC by SNMP/system.sw.os[systemOSName]))>0`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Dell iDRAC: System name has changed</li></ul>|
|Dell iDRAC: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Dell iDRAC by SNMP/system.hw.serialnumber,#1)<>last(/Dell iDRAC by SNMP/system.hw.serialnumber,#2) and length(last(/Dell iDRAC by SNMP/system.hw.serialnumber))>0`|Info|**Manual close**: Yes|
|Dell iDRAC: Firmware has changed|<p>Firmware version has changed. Acknowledge to close the problem manually.</p>|`last(/Dell iDRAC by SNMP/system.hw.firmware,#1)<>last(/Dell iDRAC by SNMP/system.hw.firmware,#2) and length(last(/Dell iDRAC by SNMP/system.hw.firmware))>0`|Info|**Manual close**: Yes|
|Dell iDRAC: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Dell iDRAC by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Dell iDRAC by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Dell iDRAC by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Dell iDRAC by SNMP/system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Dell iDRAC: No SNMP data collection</li></ul>|
|Dell iDRAC: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/Dell iDRAC by SNMP/system.name,#1)<>last(/Dell iDRAC by SNMP/system.name,#2) and length(last(/Dell iDRAC by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|Dell iDRAC: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/Dell iDRAC by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>Dell iDRAC: Unavailable by ICMP ping</li></ul>|
|Dell iDRAC: Unavailable by ICMP ping|<p>Last three attempts returned timeout.  Please check device connectivity.</p>|`max(/Dell iDRAC by SNMP/icmpping,#3)=0`|High||
|Dell iDRAC: High ICMP ping loss||`min(/Dell iDRAC by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Dell iDRAC by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>Dell iDRAC: Unavailable by ICMP ping</li></ul>|
|Dell iDRAC: High ICMP ping response time||`avg(/Dell iDRAC by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>Dell iDRAC: High ICMP ping loss</li><li>Dell iDRAC: Unavailable by ICMP ping</li></ul>|

### LLD rule Temperature CPU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature CPU Discovery|<p>Scanning table of Temperature Probe Table IDRAC-MIB-SMIv2::temperatureProbeTable</p>|SNMP agent|temp.cpu.discovery|

### Item prototypes for Temperature CPU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SENSOR_LOCALE}: Temperature|<p>MIB: IDRAC-MIB-SMIv2</p><p>0700.0020.0001.0006 This attribute defines the reading for a temperature probe of type other than temperatureProbeTypeIsDiscrete.  When the value for temperatureProbeType is other than temperatureProbeTypeIsDiscrete,the value returned for this attribute is the temperature that the probeis reading in tenths of degrees Centigrade. When the value for temperatureProbeType is temperatureProbeTypeIsDiscrete, a value is not returned for this attribute.</p>|SNMP agent|sensor.temp.value[temperatureProbeReading.CPU.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li></ul>|
|{#SENSOR_LOCALE}: Temperature status|<p>MIB: IDRAC-MIB-SMIv2</p><p>0700.0020.0001.0005 This attribute defines the probe status of the temperature probe.</p>|SNMP agent|sensor.temp.status[temperatureProbeStatus.CPU.{#SNMPINDEX}]|

### Trigger prototypes for Temperature CPU Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SENSOR_LOCALE}: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Dell iDRAC by SNMP/sensor.temp.value[temperatureProbeReading.CPU.{#SNMPINDEX}],5m)>{$TEMP_WARN:"CPU"} or last(/Dell iDRAC by SNMP/sensor.temp.status[temperatureProbeStatus.CPU.{#SNMPINDEX}])={$TEMP_WARN_STATUS}`|Warning|**Depends on**:<br><ul><li>{#SENSOR_LOCALE}: Temperature is above critical threshold</li></ul>|
|{#SENSOR_LOCALE}: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Dell iDRAC by SNMP/sensor.temp.value[temperatureProbeReading.CPU.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"CPU"} or last(/Dell iDRAC by SNMP/sensor.temp.status[temperatureProbeStatus.CPU.{#SNMPINDEX}])={$TEMP_CRIT_STATUS} or last(/Dell iDRAC by SNMP/sensor.temp.status[temperatureProbeStatus.CPU.{#SNMPINDEX}])={$TEMP_DISASTER_STATUS}`|High||
|{#SENSOR_LOCALE}: Temperature is too low||`avg(/Dell iDRAC by SNMP/sensor.temp.value[temperatureProbeReading.CPU.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"CPU"}`|Average||

### LLD rule Temperature Ambient Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature Ambient Discovery|<p>Scanning table of Temperature Probe Table IDRAC-MIB-SMIv2::temperatureProbeTable</p>|SNMP agent|temp.ambient.discovery|

### Item prototypes for Temperature Ambient Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SENSOR_LOCALE}: Temperature|<p>MIB: IDRAC-MIB-SMIv2</p><p>0700.0020.0001.0006 This attribute defines the reading for a temperature probe of type other than temperatureProbeTypeIsDiscrete.  When the value for temperatureProbeType is other than temperatureProbeTypeIsDiscrete,the value returned for this attribute is the temperature that the probeis reading in tenths of degrees Centigrade. When the value for temperatureProbeType is temperatureProbeTypeIsDiscrete, a value is not returned for this attribute.</p>|SNMP agent|sensor.temp.value[temperatureProbeReading.Ambient.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.1`</p></li></ul>|
|{#SENSOR_LOCALE}: Temperature status|<p>MIB: IDRAC-MIB-SMIv2</p><p>0700.0020.0001.0005 This attribute defines the probe status of the temperature probe.</p>|SNMP agent|sensor.temp.status[temperatureProbeStatus.Ambient.{#SNMPINDEX}]|

### Trigger prototypes for Temperature Ambient Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#SENSOR_LOCALE}: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Dell iDRAC by SNMP/sensor.temp.value[temperatureProbeReading.Ambient.{#SNMPINDEX}],5m)>{$TEMP_WARN:"Ambient"} or last(/Dell iDRAC by SNMP/sensor.temp.status[temperatureProbeStatus.Ambient.{#SNMPINDEX}])={$TEMP_WARN_STATUS}`|Warning|**Depends on**:<br><ul><li>{#SENSOR_LOCALE}: Temperature is above critical threshold</li></ul>|
|{#SENSOR_LOCALE}: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/Dell iDRAC by SNMP/sensor.temp.value[temperatureProbeReading.Ambient.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"Ambient"} or last(/Dell iDRAC by SNMP/sensor.temp.status[temperatureProbeStatus.Ambient.{#SNMPINDEX}])={$TEMP_CRIT_STATUS} or last(/Dell iDRAC by SNMP/sensor.temp.status[temperatureProbeStatus.Ambient.{#SNMPINDEX}])={$TEMP_DISASTER_STATUS}`|High||
|{#SENSOR_LOCALE}: Temperature is too low||`avg(/Dell iDRAC by SNMP/sensor.temp.value[temperatureProbeReading.Ambient.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"Ambient"}`|Average||

### LLD rule PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU Discovery|<p>IDRAC-MIB-SMIv2::powerSupplyTable</p>|SNMP agent|psu.discovery|

### Item prototypes for PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#PSU_DESCR}: Power supply status|<p>MIB: IDRAC-MIB-SMIv2</p><p>0600.0012.0001.0005 This attribute defines the status of the power supply.</p>|SNMP agent|sensor.psu.status[powerSupplyStatus.{#SNMPINDEX}]|

### Trigger prototypes for PSU Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#PSU_DESCR}: Power supply is in critical state|<p>Please check the power supply unit for errors</p>|`count(/Dell iDRAC by SNMP/sensor.psu.status[powerSupplyStatus.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS:\"critical\"}")=1 or count(/Dell iDRAC by SNMP/sensor.psu.status[powerSupplyStatus.{#SNMPINDEX}],#1,"eq","{$PSU_CRIT_STATUS:\"nonRecoverable\"}")=1`|Average||
|{#PSU_DESCR}: Power supply is in warning state|<p>Please check the power supply unit for errors</p>|`count(/Dell iDRAC by SNMP/sensor.psu.status[powerSupplyStatus.{#SNMPINDEX}],#1,"eq","{$PSU_WARN_STATUS:\"nonCritical\"}")=1`|Warning|**Depends on**:<br><ul><li>{#PSU_DESCR}: Power supply is in critical state</li></ul>|

### LLD rule FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN Discovery|<p>IDRAC-MIB-SMIv2::coolingDeviceTable</p>|SNMP agent|fan.discovery|

### Item prototypes for FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#FAN_DESCR}: Fan status|<p>MIB: IDRAC-MIB-SMIv2</p><p>0700.0012.0001.0005 This attribute defines the probe status of the cooling device.</p>|SNMP agent|sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}]|
|{#FAN_DESCR}: Fan speed|<p>MIB: IDRAC-MIB-SMIv2</p><p>0700.0012.0001.0006 This attribute defines the reading for a cooling device</p><p>of subtype other than coolingDeviceSubTypeIsDiscrete.  When the value</p><p>for coolingDeviceSubType is other than coolingDeviceSubTypeIsDiscrete, the</p><p>value returned for this attribute is the speed in RPM or the OFF/ON value</p><p>of the cooling device.  When the value for coolingDeviceSubType is</p><p>coolingDeviceSubTypeIsDiscrete, a value is not returned for this attribute.</p>|SNMP agent|sensor.fan.speed[coolingDeviceReading.{#SNMPINDEX}]|

### Trigger prototypes for FAN Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#FAN_DESCR}: Fan is in critical state|<p>Please check the fan unit</p>|`count(/Dell iDRAC by SNMP/sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"criticalUpper\"}")=1 or count(/Dell iDRAC by SNMP/sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"nonRecoverableUpper\"}")=1 or count(/Dell iDRAC by SNMP/sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"criticalLower\"}")=1 or count(/Dell iDRAC by SNMP/sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"nonRecoverableLower\"}")=1 or count(/Dell iDRAC by SNMP/sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}],#1,"eq","{$FAN_CRIT_STATUS:\"failed\"}")=1`|Average||
|{#FAN_DESCR}: Fan is in warning state|<p>Please check the fan unit</p>|`count(/Dell iDRAC by SNMP/sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}],#1,"eq","{$FAN_WARN_STATUS:\"nonCriticalUpper\"}")=1 or count(/Dell iDRAC by SNMP/sensor.fan.status[coolingDeviceStatus.{#SNMPINDEX}],#1,"eq","{$FAN_WARN_STATUS:\"nonCriticalLower\"}")=1`|Warning|**Depends on**:<br><ul><li>{#FAN_DESCR}: Fan is in critical state</li></ul>|

### LLD rule Physical Disk Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Physical Disk Discovery|<p>IDRAC-MIB-SMIv2::physicalDiskTable</p>|SNMP agent|physicaldisk.discovery|

### Item prototypes for Physical Disk Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#DISK_NAME}: Physical disk status|<p>MIB: IDRAC-MIB-SMIv2</p><p>The status of the physical disk itself without the propagation of any contained component status.</p><p>Possible values:</p><p>1: Other</p><p>2: Unknown</p><p>3: OK</p><p>4: Non-critical</p><p>5: Critical</p><p>6: Non-recoverable</p>|SNMP agent|system.hw.physicaldisk.status[physicalDiskComponentStatus.{#SNMPINDEX}]|
|{#DISK_NAME}: Physical disk serial number|<p>MIB: IDRAC-MIB-SMIv2</p><p>The physical disk's unique identification number from the manufacturer.</p>|SNMP agent|system.hw.physicaldisk.serialnumber[physicalDiskSerialNo.{#SNMPINDEX}]|
|{#DISK_NAME}: Physical disk S.M.A.R.T. status|<p>MIB: IDRAC-MIB-SMIv2</p><p>Indicates whether the physical disk has received a predictive failure alert.</p>|SNMP agent|system.hw.physicaldisk.smart_status[physicalDiskSmartAlertIndication.{#SNMPINDEX}]|
|{#DISK_NAME}: Physical disk model name|<p>MIB: IDRAC-MIB-SMIv2</p><p>The model number of the physical disk.</p>|SNMP agent|system.hw.physicaldisk.model[physicalDiskProductID.{#SNMPINDEX}]|
|{#DISK_NAME}: Physical disk part number|<p>MIB: IDRAC-MIB-SMIv2</p><p>The part number of the disk.</p>|SNMP agent|system.hw.physicaldisk.part_number[physicalDiskPartNumber.{#SNMPINDEX}]|
|{#DISK_NAME}: Physical disk media type|<p>MIB: IDRAC-MIB-SMIv2</p><p>The media type of the physical disk. Possible Values:</p><p>1: The media type could not be determined.</p><p>2: Hard Disk Drive (HDD).</p><p>3: Solid State Drive (SSD).</p>|SNMP agent|system.hw.physicaldisk.media_type[physicalDiskMediaType.{#SNMPINDEX}]|
|{#DISK_NAME}: Disk size|<p>MIB: IDRAC-MIB-SMIv2</p><p>The size of the physical disk in megabytes.</p>|SNMP agent|system.hw.physicaldisk.size[physicalDiskCapacityInMB.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li></ul>|

### Trigger prototypes for Physical Disk Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#DISK_NAME}: Physical disk failed|<p>Please check physical disk for warnings or errors</p>|`count(/Dell iDRAC by SNMP/system.hw.physicaldisk.status[physicalDiskComponentStatus.{#SNMPINDEX}],#1,"eq","{$DISK_FAIL_STATUS:\"critical\"}")=1 or count(/Dell iDRAC by SNMP/system.hw.physicaldisk.status[physicalDiskComponentStatus.{#SNMPINDEX}],#1,"eq","{$DISK_FAIL_STATUS:\"nonRecoverable\"}")=1`|High||
|{#DISK_NAME}: Physical disk is in warning state|<p>Please check physical disk for warnings or errors</p>|`count(/Dell iDRAC by SNMP/system.hw.physicaldisk.status[physicalDiskComponentStatus.{#SNMPINDEX}],#1,"eq","{$DISK_WARN_STATUS:\"nonCritical\"}")=1`|Warning|**Depends on**:<br><ul><li>{#DISK_NAME}: Physical disk failed</li></ul>|
|{#DISK_NAME}: Disk has been replaced|<p>Disk serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Dell iDRAC by SNMP/system.hw.physicaldisk.serialnumber[physicalDiskSerialNo.{#SNMPINDEX}],#1)<>last(/Dell iDRAC by SNMP/system.hw.physicaldisk.serialnumber[physicalDiskSerialNo.{#SNMPINDEX}],#2) and length(last(/Dell iDRAC by SNMP/system.hw.physicaldisk.serialnumber[physicalDiskSerialNo.{#SNMPINDEX}]))>0`|Info|**Manual close**: Yes|
|{#DISK_NAME}: Physical disk S.M.A.R.T. failed|<p>Disk probably requires replacement.</p>|`count(/Dell iDRAC by SNMP/system.hw.physicaldisk.smart_status[physicalDiskSmartAlertIndication.{#SNMPINDEX}],#1,"eq","{$DISK_SMART_FAIL_STATUS}")=1`|High|**Depends on**:<br><ul><li>{#DISK_NAME}: Physical disk failed</li></ul>|

### LLD rule Virtual Disk Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Virtual Disk Discovery|<p>IDRAC-MIB-SMIv2::virtualDiskTable</p>|SNMP agent|virtualdisk.discovery|

### Item prototypes for Virtual Disk Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk {#SNMPVALUE}({#DISK_NAME}): Layout type|<p>MIB: IDRAC-MIB-SMIv2</p><p>The virtual disk's RAID type.</p><p>Possible values:</p><p>1: Not one of the following</p><p>2: RAID-0</p><p>3: RAID-1</p><p>4: RAID-5</p><p>5: RAID-6</p><p>6: RAID-10</p><p>7: RAID-50</p><p>8: RAID-60</p><p>9: Concatenated RAID 1</p><p>10: Concatenated RAID 5</p>|SNMP agent|system.hw.virtualdisk.layout[virtualDiskLayout.{#SNMPINDEX}]|
|Disk {#SNMPVALUE}({#DISK_NAME}): Current state|<p>MIB: IDRAC-MIB-SMIv2</p><p>The state of the virtual disk when there are progressive operations ongoing.</p><p>Possible values:</p><p>1: There is no active operation running.</p><p>2: The virtual disk configuration has changed. The physical disks included in the virtual disk are being modified to support the new configuration.</p><p>3: A Consistency Check (CC) is being performed on the virtual disk.</p><p>4: The virtual disk is being initialized.</p><p>5: BackGround Initialization (BGI) is being performed on the virtual disk.</p>|SNMP agent|system.hw.virtualdisk.state[virtualDiskOperationalState.{#SNMPINDEX}]|
|Disk {#SNMPVALUE}({#DISK_NAME}): Read policy|<p>MIB: IDRAC-MIB-SMIv2</p><p>The read policy used by the controller for read operations on this virtual disk.</p><p>Possible values:</p><p>1: No Read Ahead.</p><p>2: Read Ahead.</p><p>3: Adaptive Read Ahead.</p>|SNMP agent|system.hw.virtualdisk.readpolicy[virtualDiskReadPolicy.{#SNMPINDEX}]|
|Disk {#SNMPVALUE}({#DISK_NAME}): Write policy|<p>MIB: IDRAC-MIB-SMIv2</p><p>The write policy used by the controller for write operations on this virtual disk.</p><p>Possible values:</p><p>1: Write Through.</p><p>2: Write Back.</p><p>3: Force Write Back.</p>|SNMP agent|system.hw.virtualdisk.writepolicy[virtualDiskWritePolicy.{#SNMPINDEX}]|
|Disk {#SNMPVALUE}({#DISK_NAME}): Disk size|<p>MIB: IDRAC-MIB-SMIv2</p><p>The size of the virtual disk in megabytes.</p>|SNMP agent|system.hw.virtualdisk.size[virtualDiskSizeInMB.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Disk {#SNMPVALUE}({#DISK_NAME}): Status|<p>MIB: IDRAC-MIB-SMIv2</p><p>The current state of this virtual disk (which includes any member physical disks.)</p><p>Possible states:</p><p>1: The current state could not be determined.</p><p>2: The virtual disk is operating normally or optimally.</p><p>3: The virtual disk has encountered a failure. The data on disk is lost or is about to be lost.</p><p>4: The virtual disk encountered a failure with one or all of the constituent redundant physical disks.</p><p>The data on the virtual disk might no longer be fault tolerant.</p>|SNMP agent|system.hw.virtualdisk.status[virtualDiskState.{#SNMPINDEX}]|

### Trigger prototypes for Virtual Disk Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Disk {#SNMPVALUE}({#DISK_NAME}): Virtual disk failed|<p>Please check virtual disk for warnings or errors</p>|`count(/Dell iDRAC by SNMP/system.hw.virtualdisk.status[virtualDiskState.{#SNMPINDEX}],#1,"eq","{$VDISK_CRIT_STATUS:\"failed\"}")=1`|High||
|Disk {#SNMPVALUE}({#DISK_NAME}): Virtual disk is in warning state|<p>Please check virtual disk for warnings or errors</p>|`count(/Dell iDRAC by SNMP/system.hw.virtualdisk.status[virtualDiskState.{#SNMPINDEX}],#1,"eq","{$VDISK_WARN_STATUS:\"degraded\"}")=1`|Average|**Depends on**:<br><ul><li>Disk {#SNMPVALUE}({#DISK_NAME}): Virtual disk failed</li></ul>|

### LLD rule Array Controller Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Array Controller Discovery|<p>IDRAC-MIB-SMIv2::controllerTable</p>|SNMP agent|physicaldisk.arr.discovery|

### Item prototypes for Array Controller Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#CNTLR_NAME}: Disk array controller status|<p>MIB: IDRAC-MIB-SMIv2</p><p>The status of the controller itself without the propagation of any contained component status.</p><p>Possible values:</p><p>1: Other</p><p>2: Unknown</p><p>3: OK</p><p>4: Non-critical</p><p>5: Critical</p><p>6: Non-recoverable</p>|SNMP agent|system.hw.diskarray.status[controllerComponentStatus.{#SNMPINDEX}]|
|{#CNTLR_NAME}: Disk array controller model|<p>MIB: IDRAC-MIB-SMIv2</p><p>The controller's name as represented in Storage Management.</p>|SNMP agent|system.hw.diskarray.model[controllerName.{#SNMPINDEX}]|

### Trigger prototypes for Array Controller Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#CNTLR_NAME}: Disk array controller is in unrecoverable state!|<p>Please check the device for faults</p>|`count(/Dell iDRAC by SNMP/system.hw.diskarray.status[controllerComponentStatus.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_FAIL_STATUS:\"nonRecoverable\"}")=1`|Disaster||
|{#CNTLR_NAME}: Disk array controller is in critical state|<p>Please check the device for faults</p>|`count(/Dell iDRAC by SNMP/system.hw.diskarray.status[controllerComponentStatus.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_CRIT_STATUS:\"critical\"}")=1`|High|**Depends on**:<br><ul><li>{#CNTLR_NAME}: Disk array controller is in unrecoverable state!</li></ul>|
|{#CNTLR_NAME}: Disk array controller is in warning state|<p>Please check the device for faults</p>|`count(/Dell iDRAC by SNMP/system.hw.diskarray.status[controllerComponentStatus.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_WARN_STATUS:\"nonCritical\"}")=1`|Average|**Depends on**:<br><ul><li>{#CNTLR_NAME}: Disk array controller is in unrecoverable state!</li><li>{#CNTLR_NAME}: Disk array controller is in critical state</li></ul>|

### LLD rule Array Controller Cache Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Array Controller Cache Discovery|<p>IDRAC-MIB-SMIv2::batteryTable</p>|SNMP agent|array.cache.discovery|

### Item prototypes for Array Controller Cache Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Battery {#BATTERY_NUM}: Disk array cache controller battery status|<p>MIB: IDRAC-MIB-SMIv2</p><p>Current state of battery.</p><p>Possible values:</p><p>1: The current state could not be determined.</p><p>2: The battery is operating normally.</p><p>3: The battery has failed and needs to be replaced.</p><p>4: The battery temperature is high or charge level is depleting.</p><p>5: The battery is missing or not detected.</p><p>6: The battery is undergoing the re-charge phase.</p><p>7: The battery voltage or charge level is below the threshold.</p>|SNMP agent|system.hw.diskarray.cache.battery.status[batteryState.{#SNMPINDEX}]|

### Trigger prototypes for Array Controller Cache Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Battery {#BATTERY_NUM}: Disk array cache controller battery is in warning state|<p>Please check the device for faults</p>|`count(/Dell iDRAC by SNMP/system.hw.diskarray.cache.battery.status[batteryState.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_CACHE_BATTERY_WARN_STATUS}")=1`|Warning|**Depends on**:<br><ul><li>Battery {#BATTERY_NUM}: Disk array cache controller battery is in critical state!</li></ul>|
|Battery {#BATTERY_NUM}: Disk array cache controller battery is not in optimal state|<p>Please check the device for faults</p>|`count(/Dell iDRAC by SNMP/system.hw.diskarray.cache.battery.status[batteryState.{#SNMPINDEX}],#1,"ne","{$DISK_ARRAY_CACHE_BATTERY_OK_STATUS}")=1`|Warning|**Depends on**:<br><ul><li>Battery {#BATTERY_NUM}: Disk array cache controller battery is in critical state!</li><li>Battery {#BATTERY_NUM}: Disk array cache controller battery is in warning state</li></ul>|
|Battery {#BATTERY_NUM}: Disk array cache controller battery is in critical state!|<p>Please check the device for faults</p>|`count(/Dell iDRAC by SNMP/system.hw.diskarray.cache.battery.status[batteryState.{#SNMPINDEX}],#1,"eq","{$DISK_ARRAY_CACHE_BATTERY_CRIT_STATUS}")=1`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

