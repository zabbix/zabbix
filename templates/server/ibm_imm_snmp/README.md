
# IBM IMM by SNMP

## Overview

for IMM2 and IMM1 IBM serverX hardware

### Known Issues:

Description: Some IMMs (IMM1) do not return disks
 - version: IMM1
 - device: IBM x3250M3

Description: Some IMMs (IMM1) do not return fan status: fanHealthStatus
 - version: IMM1
 - device: IBM x3250M3

Description: IMM1 servers (M2, M3 generations) sysObjectID is NET-SNMP-MIB::netSnmpAgentOIDs.10
 - version: IMM1
 - device: IMM1 servers (M2,M3 generations)

Description: IMM1 servers (M2, M3 generations) only Ambient temperature sensor available
 - version: IMM1
 - device: IMM1 servers (M2,M3 generations)

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- IBM System x3550 M2 with IMM1
- IBM x3250M3 with IMM1
- IBM x3550M5 with IMM2
- System x3550 M3 with IMM1

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$TEMP_CRIT}||`60`|
|{$TEMP_CRIT_LOW}||`5`|
|{$TEMP_WARN}||`50`|
|{$HEALTH_CRIT_STATUS}||`2`|
|{$HEALTH_DISASTER_STATUS}||`0`|
|{$HEALTH_WARN_STATUS}||`4`|
|{$TEMP_CRIT:"Ambient"}||`35`|
|{$TEMP_WARN:"Ambient"}||`30`|
|{$DISK_OK_STATUS}||`Normal`|
|{$PSU_OK_STATUS}||`Normal`|
|{$FAN_OK_STATUS}||`Normal`|
|{$SNMP.TIMEOUT}|<p>Time interval for the SNMP availability trigger.</p>|`5m`|
|{$ICMP_LOSS_WARN}|<p>Warning threshold of ICMP packet loss in %.</p>|`20`|
|{$ICMP_RESPONSE_TIME_WARN}|<p>Warning threshold of the average ICMP response time in seconds.</p>|`0.15`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Overall system health status|<p>MIB: IMM-MIB</p><p>Indicates status of system health for the system in which the IMM resides. Value of 'nonRecoverable' indicates a severe error has occurred and the system may not be functioning. A value of 'critical' indicates that an error has occurred but the system is currently functioning properly. A value of 'nonCritical' indicates that a condition has occurred that may change the state of the system in the future but currently the system is working properly. A value of 'normal' indicates that the system is operating normally.</p>|SNMP agent|system.status[systemHealthStat.0]|
|Hardware model name|<p>MIB: IMM-MIB</p>|SNMP agent|system.hw.model<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Hardware serial number|<p>MIB: IMM-MIB</p><p>Machine serial number VPD information</p>|SNMP agent|system.hw.serialnumber<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
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
|IBM IMM: System is in unrecoverable state!|<p>Please check the device for faults</p>|`count(/IBM IMM by SNMP/system.status[systemHealthStat.0],#1,"eq","{$HEALTH_DISASTER_STATUS}")=1`|High||
|IBM IMM: System status is in critical state|<p>Please check the device for errors</p>|`count(/IBM IMM by SNMP/system.status[systemHealthStat.0],#1,"eq","{$HEALTH_CRIT_STATUS}")=1`|High|**Depends on**:<br><ul><li>IBM IMM: System is in unrecoverable state!</li></ul>|
|IBM IMM: System status is in warning state|<p>Please check the device for warnings</p>|`count(/IBM IMM by SNMP/system.status[systemHealthStat.0],#1,"eq","{$HEALTH_WARN_STATUS}")=1`|Warning|**Depends on**:<br><ul><li>IBM IMM: System is in unrecoverable state!</li><li>IBM IMM: System status is in critical state</li></ul>|
|IBM IMM: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/IBM IMM by SNMP/system.hw.serialnumber,#1)<>last(/IBM IMM by SNMP/system.hw.serialnumber,#2) and length(last(/IBM IMM by SNMP/system.hw.serialnumber))>0`|Info|**Manual close**: Yes|
|IBM IMM: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/IBM IMM by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/IBM IMM by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/IBM IMM by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/IBM IMM by SNMP/system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>IBM IMM: No SNMP data collection</li></ul>|
|IBM IMM: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/IBM IMM by SNMP/system.name,#1)<>last(/IBM IMM by SNMP/system.name,#2) and length(last(/IBM IMM by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|IBM IMM: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/IBM IMM by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>IBM IMM: Unavailable by ICMP ping</li></ul>|
|IBM IMM: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/IBM IMM by SNMP/icmpping,#3)=0`|High||
|IBM IMM: High ICMP ping loss|<p>ICMP packets loss detected.</p>|`min(/IBM IMM by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/IBM IMM by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>IBM IMM: Unavailable by ICMP ping</li></ul>|
|IBM IMM: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/IBM IMM by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>IBM IMM: High ICMP ping loss</li><li>IBM IMM: Unavailable by ICMP ping</li></ul>|

### LLD rule Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature Discovery|<p>Scanning IMM-MIB::tempTable</p>|SNMP agent|tempDescr.discovery|

### Item prototypes for Temperature Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SNMPVALUE}: Temperature|<p>MIB: IMM-MIB</p><p>Temperature readings of testpoint: {#SNMPVALUE}</p>|SNMP agent|sensor.temp.value[tempReading.{#SNMPINDEX}]|

### Trigger prototypes for Temperature Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|IBM IMM: {#SNMPVALUE}: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/IBM IMM by SNMP/sensor.temp.value[tempReading.{#SNMPINDEX}],5m)>{$TEMP_WARN:"{#SNMPVALUE}"}`|Warning|**Depends on**:<br><ul><li>IBM IMM: {#SNMPVALUE}: Temperature is above critical threshold</li></ul>|
|IBM IMM: {#SNMPVALUE}: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/IBM IMM by SNMP/sensor.temp.value[tempReading.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"{#SNMPVALUE}"}`|High||
|IBM IMM: {#SNMPVALUE}: Temperature is too low||`avg(/IBM IMM by SNMP/sensor.temp.value[tempReading.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"{#SNMPVALUE}"}`|Average||

### LLD rule Temperature Discovery Ambient

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature Discovery Ambient|<p>Scanning IMM-MIB::tempTable with Ambient filter</p>|SNMP agent|tempDescr.discovery.ambient|

### Item prototypes for Temperature Discovery Ambient

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Ambient: Temperature|<p>MIB: IMM-MIB</p><p>Temperature readings of testpoint: Ambient</p>|SNMP agent|sensor.temp.value[tempReading.Ambient.{#SNMPINDEX}]|

### Trigger prototypes for Temperature Discovery Ambient

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|IBM IMM: Ambient: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/IBM IMM by SNMP/sensor.temp.value[tempReading.Ambient.{#SNMPINDEX}],5m)>{$TEMP_WARN:"Ambient"}`|Warning|**Depends on**:<br><ul><li>IBM IMM: Ambient: Temperature is above critical threshold</li></ul>|
|IBM IMM: Ambient: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/IBM IMM by SNMP/sensor.temp.value[tempReading.Ambient.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"Ambient"}`|High||
|IBM IMM: Ambient: Temperature is too low||`avg(/IBM IMM by SNMP/sensor.temp.value[tempReading.Ambient.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"Ambient"}`|Average||

### LLD rule Temperature Discovery CPU

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature Discovery CPU|<p>Scanning IMM-MIB::tempTable with CPU filter</p>|SNMP agent|tempDescr.discovery.cpu|

### Item prototypes for Temperature Discovery CPU

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU: Temperature|<p>MIB: IMM-MIB</p><p>Temperature readings of testpoint: CPU</p>|SNMP agent|sensor.temp.value[tempReading.CPU.{#SNMPINDEX}]|

### Trigger prototypes for Temperature Discovery CPU

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|IBM IMM: CPU: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/IBM IMM by SNMP/sensor.temp.value[tempReading.CPU.{#SNMPINDEX}],5m)>{$TEMP_WARN:"CPU"}`|Warning|**Depends on**:<br><ul><li>IBM IMM: CPU: Temperature is above critical threshold</li></ul>|
|IBM IMM: CPU: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p>|`avg(/IBM IMM by SNMP/sensor.temp.value[tempReading.CPU.{#SNMPINDEX}],5m)>{$TEMP_CRIT:"CPU"}`|High||
|IBM IMM: CPU: Temperature is too low||`avg(/IBM IMM by SNMP/sensor.temp.value[tempReading.CPU.{#SNMPINDEX}],5m)<{$TEMP_CRIT_LOW:"CPU"}`|Average||

### LLD rule PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU Discovery|<p>IMM-MIB::powerFruName</p>|SNMP agent|psu.discovery|

### Item prototypes for PSU Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#PSU_DESCR}: Power supply status|<p>MIB: IMM-MIB</p><p>A description of the power module status.</p>|SNMP agent|sensor.psu.status[powerHealthStatus.{#SNMPINDEX}]|

### Trigger prototypes for PSU Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|IBM IMM: {#PSU_DESCR}: Power supply is not in normal state|<p>Please check the power supply unit for errors</p>|`count(/IBM IMM by SNMP/sensor.psu.status[powerHealthStatus.{#SNMPINDEX}],#1,"ne","{$PSU_OK_STATUS}")=1`|Info||

### LLD rule FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN Discovery|<p>IMM-MIB::fanDescr</p>|SNMP agent|fan.discovery|

### Item prototypes for FAN Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#FAN_DESCR}: Fan status|<p>MIB: IMM-MIB</p><p>A description of the fan component status.</p>|SNMP agent|sensor.fan.status[fanHealthStatus.{#SNMPINDEX}]|
|{#FAN_DESCR}: Fan speed, %|<p>MIB: IMM-MIB</p><p>Fan speed expressed in percent(%) of maximum RPM.</p><p>An octet string expressed as 'ddd% of maximum' where:d is a decimal digit or blank space for a leading zero.</p><p>If the fan is determined not to be running or the fan speed cannot be determined, the string will indicate 'Offline'.</p>|SNMP agent|sensor.fan.speed.percentage[fanSpeed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>Regular expression: `(\d{1,3}) *%( of maximum)? \1`</p></li></ul>|

### Trigger prototypes for FAN Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|IBM IMM: {#FAN_DESCR}: Fan is not in normal state|<p>Please check the fan unit</p>|`count(/IBM IMM by SNMP/sensor.fan.status[fanHealthStatus.{#SNMPINDEX}],#1,"ne","{$FAN_OK_STATUS}")=1`|Info||

### LLD rule Physical Disk Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Physical Disk Discovery||SNMP agent|physicalDisk.discovery|

### Item prototypes for Physical Disk Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#SNMPINDEX}: Physical disk status|<p>MIB: IMM-MIB</p>|SNMP agent|system.hw.physicaldisk.status[diskHealthStatus.{#SNMPINDEX}]|
|{#SNMPINDEX}: Physical disk part number|<p>MIB: IMM-MIB</p><p>disk module FRU name.</p>|SNMP agent|system.hw.physicaldisk.part_number[diskFruName.{#SNMPINDEX}]|

### Trigger prototypes for Physical Disk Discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|IBM IMM: {#SNMPINDEX}: Physical disk is not in OK state|<p>Please check physical disk for warnings or errors</p>|`count(/IBM IMM by SNMP/system.hw.physicaldisk.status[diskHealthStatus.{#SNMPINDEX}],#1,"ne","{$DISK_OK_STATUS}")=1`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

