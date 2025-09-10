
# Aruba CX 8300s by SNMP

## Overview

The Aruba CX 8300 series is designed for core and aggregation in enterprise campus networks as well as top-of-rack/data center environments. These are high-performance fixed switches offering port speeds from 1 GbE up to 100 GbE, with maximum switching capacity up to 6.4 Tbps.


## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Aruba JL636A 8325, Aruba JL717A 8360

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Refer to the vendor documentation.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ARUBA.POWER.SUPPLY.THR.MAX}|<p>Threshold of power utilization expressed in %.</p>|`90`|
|{$ARUBA.MEMORY.UTIL.MAX}|<p>Threshold of memory utilization expressed in %.</p>|`90`|
|{$ARUBA.CPU.UTIL.MAX}|<p>Threshold of CPU utilization expressed in %.</p>|`90`|
|{$ICMP_LOSS_WARN}|<p>Warning threshold of ICMP packet loss in %.</p>|`20`|
|{$ICMP_RESPONSE_TIME_WARN}|<p>Warning threshold of average ICMP response time in seconds.</p>|`0.15`|
|{$SNMP.TIMEOUT}|<p>Time interval for the SNMP availability trigger.</p>|`5m`|
|{$IFCONTROL}||`1`|
|{$IF.UTIL.MAX}||`95`|
|{$NET.IF.IFNAME.MATCHES}||`^.*$`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>Filter out loopbacks, nulls, docker veth links and docker0 bridge by default</p>|`Macro too long. Please see the template.`|
|{$NET.IF.IFOPERSTATUS.MATCHES}||`^.*$`|
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES}|<p>Ignore notPresent(6)</p>|`^6$`|
|{$NET.IF.IFADMINSTATUS.MATCHES}||`^.*`|
|{$NET.IF.IFADMINSTATUS.NOT_MATCHES}|<p>Ignore down(2) administrative status</p>|`^2$`|
|{$NET.IF.IFDESCR.MATCHES}||`.*`|
|{$NET.IF.IFDESCR.NOT_MATCHES}||`CHANGE_IF_NEEDED`|
|{$NET.IF.IFTYPE.MATCHES}||`.*`|
|{$NET.IF.IFTYPE.NOT_MATCHES}||`CHANGE_IF_NEEDED`|
|{$NET.IF.IFALIAS.MATCHES}||`.*`|
|{$NET.IF.IFALIAS.NOT_MATCHES}||`CHANGE_IF_NEEDED`|
|{$IF.ERRORS.WARN}|<p>Warning threshold of error packet rate. Can be used with interface name as context.</p>|`2`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SNMP walk system FAN|<p>MIB: ARUBAWIRED-FAN-MIB</p><p>Used for discovering system fans.</p>|SNMP agent|aruba.system.fan.walk|
|SNMP walk system PSU|<p>MIB: ARUBAWIRED-POWERSUPPLY-MIB</p><p>Used for discovering the system power supply.</p>|SNMP agent|aruba.system.psu.walk|
|SNMP walk system temperature sensor|<p>MIB: ARUBAWIRED-TEMPSENSOR-MIB</p><p>Used for discovering system temperature sensors.</p>|SNMP agent|aruba.system.sensor.walk|
|SNMP walk system resource|<p>MIB: ARUBAWIRED-SYSTEMINFO-MIB</p><p>Used for discovering system resources.</p>|SNMP agent|aruba.system.resource.walk|
|SNMP walk OSPF area|<p>MIB: OSPF-MIB</p><p>Used for discovering OSPF areas.</p>|SNMP agent|aruba.ospf.area.walk<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|SNMP walk OSPF neighbor|<p>MIB: OSPF-MIB</p><p>Used for discovering OSPF neighbors.</p>|SNMP agent|aruba.ospf.neighbor.walk<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|SNMP walk OSPF interface|<p>MIB: OSPF-MIB</p><p>Used for discovering OSPF interfaces.</p>|SNMP agent|aruba.ospf.interface.walk<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|ICMP ping|<p>The host accessibility by ICMP ping.</p><p></p><p>0 - ICMP ping fails;</p><p>1 - ICMP ping successful.</p>|Simple check|icmpping|
|ICMP loss|<p>The percentage of lost packets.</p>|Simple check|icmppingloss|
|ICMP response time|<p>ICMP ping response time (in seconds).</p>|Simple check|icmppingsec|
|Uptime (network)|<p>MIB: SNMPv2-MIB</p><p>Time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p>|SNMP agent|system.net.uptime[sysUpTime.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Uptime (hardware)|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p>|SNMP agent|system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|SNMP traps (fallback)|<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p>|SNMP trap|snmptrap.fallback|
|System location|<p>MIB: SNMPv2-MIB</p><p>Physical location of the node (e.g., `equipment room`, `3rd floor`). If not provided, the value is a zero-length string.</p>|SNMP agent|system.location[sysLocation.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System contact details|<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person. If no contact information is known, the value is the zero-length string.</p>|SNMP agent|system.contact[sysContact.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity. This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p>|SNMP agent|system.objectid[sysObjectID.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name. If the name is unknown, the value is the zero-length string.</p>|SNMP agent|system.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System description|<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p>|SNMP agent|system.descr[sysDescr.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|SNMP agent availability|<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible values:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p>|Zabbix internal|zabbix[host,snmp,available]|
|SNMP walk network interfaces|<p>Used for discovering interfaces from IF-MIB.</p>|SNMP agent|net.if.walk|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Aruba: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/Aruba CX 8300s by SNMP/icmpping,#3)=0`|High||
|Aruba: High ICMP ping loss|<p>ICMP packet loss detected.</p>|`min(/Aruba CX 8300s by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Aruba CX 8300s by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>Aruba: Unavailable by ICMP ping</li></ul>|
|Aruba: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/Aruba CX 8300s by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>Aruba: High ICMP ping loss</li><li>Aruba: Unavailable by ICMP ping</li></ul>|
|Aruba: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Aruba CX 8300s by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Aruba CX 8300s by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Aruba CX 8300s by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Aruba CX 8300s by SNMP/system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Aruba: No SNMP data collection</li></ul>|
|Aruba: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/Aruba CX 8300s by SNMP/system.name,#1)<>last(/Aruba CX 8300s by SNMP/system.name,#2) and length(last(/Aruba CX 8300s by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|Aruba: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/Aruba CX 8300s by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>Aruba: Unavailable by ICMP ping</li></ul>|

### LLD rule Resource discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Resource discovery|<p>Used for discovering system resources from ARUBAWIRED-SYSTEMINFO-MIB.</p>|Dependent item|aruba.resource.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Resource discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Module [{#SNMPVALUE}]: Memory usage|<p>MIB: ARUBAWIRED-SYSTEMINFO-MIB</p><p>Subsystem memory usage in percent.</p>|Dependent item|aruba.system.memory.usage[arubaWiredSystemInfoMemory.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.47196.4.1.1.3.22.1.0.1.1.4.{#SNMPINDEX}`</p></li></ul>|
|Module [{#SNMPVALUE}]: CPU utilization|<p>MIB: ARUBAWIRED-SYSTEMINFO-MIB</p><p>The percentage of CPU utilization of the subsystem averaged across all the CPUs of the system.</p>|Dependent item|aruba.system.cpu.utilization[arubaWiredSystemInfoCpu.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.47196.4.1.1.3.22.1.0.1.1.3.{#SNMPINDEX}`</p></li></ul>|
|Module [{#SNMPVALUE}]: CPU load average 1 min|<p>MIB: ARUBAWIRED-SYSTEMINFO-MIB</p><p>The percentage of CPU utilization of the subsystem averaged across all the CPUs of the system over a one-minute period.</p>|Dependent item|aruba.system.cpu.la1[arubaWiredSystemInfoCpuAvgOneMin.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.47196.4.1.1.3.22.1.0.1.1.10.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Module [{#SNMPVALUE}]: CPU load average 5 min|<p>MIB: ARUBAWIRED-SYSTEMINFO-MIB</p><p>The percentage of CPU utilization of the subsystem averaged across all the CPUs of the system period of five minutes.</p>|Dependent item|aruba.system.cpu.la5[arubaWiredSystemInfoCpuAvgFiveMin.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.47196.4.1.1.3.22.1.0.1.1.11.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Module [{#SNMPVALUE}]: Storage NOS utilization|<p>MIB: ARUBAWIRED-SYSTEMINFO-MIB</p><p>Utilization of network operating system storage partition in percent.</p>|Dependent item|aruba.system.storage.nos.utilization[arubaWiredSystemInfoStorageNos.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.47196.4.1.1.3.22.1.0.1.1.5.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Module [{#SNMPVALUE}]: Storage log utilization|<p>MIB: ARUBAWIRED-SYSTEMINFO-MIB</p><p>Utilization of log storage partition in percent.</p>|Dependent item|aruba.system.storage.log.utilization[arubaWiredSystemInfoStorageLog.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.47196.4.1.1.3.22.1.0.1.1.6.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Module [{#SNMPVALUE}]: Storage core dump utilization|<p>MIB: ARUBAWIRED-SYSTEMINFO-MIB</p><p>Utilization of core dump storage partition in percent.</p>|Dependent item|aruba.system.storage.coredump.utilization[arubaWiredSystemInfoStorageCoredump.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.47196.4.1.1.3.22.1.0.1.1.7.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Module [{#SNMPVALUE}]: Storage security utilization|<p>MIB: ARUBAWIRED-SYSTEMINFO-MIB</p><p>Utilization of security storage partition in percent.</p>|Dependent item|aruba.system.storage.security.utilization[arubaWiredSystemInfoStorageSecurity.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.47196.4.1.1.3.22.1.0.1.1.8.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Module [{#SNMPVALUE}]: Storage self test utilization|<p>MIB: ARUBAWIRED-SYSTEMINFO-MIB</p><p>Utilization of self test storage partition in percent.</p>|Dependent item|aruba.system.storage.selftest.utilization[arubaWiredSystemInfoStorageSelftest.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.47196.4.1.1.3.22.1.0.1.1.9.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Resource discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Aruba: Generic SNMP: Module [{#SNMPVALUE}]: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Aruba CX 8300s by SNMP/aruba.system.memory.usage[arubaWiredSystemInfoMemory.{#SNMPINDEX}],5m)>{$ARUBA.MEMORY.UTIL.MAX}`|Average||
|Aruba: Generic SNMP: Module [{#SNMPVALUE}]: High CPU utilization|<p>The system is running out of free memory.</p>|`min(/Aruba CX 8300s by SNMP/aruba.system.cpu.utilization[arubaWiredSystemInfoCpu.{#SNMPINDEX}],5m)>{$ARUBA.CPU.UTIL.MAX}`|Average||

### LLD rule Sensor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Sensor discovery|<p>Used for discovering temperature sensors from ARUBAWIRED-TEMPSENSOR-MIB.</p>|Dependent item|aruba.sensor.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Sensor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Sensor [{#SNMPVALUE}]: Temperature|<p>MIB: ARUBAWIRED-TEMPSENSOR-MIB</p><p>Current temperature value read from the temperature sensor.</p>|Dependent item|aruba.system.sensors[arubaWiredTempSensorTemperature.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.47196.4.1.1.3.11.3.1.1.7.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Sensor [{#SNMPVALUE}]: State|<p>MIB: ARUBAWIRED-TEMPSENSOR-MIB</p><p>Current status for the temperature sensor.</p>|Dependent item|aruba.system.sensors[arubaWiredTempSensorState.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.47196.4.1.1.3.11.3.1.1.6.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for Sensor discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Aruba: Sensor [{#SNMPVALUE}]: High temperature|<p>The current temperature is greater than the threshold state.</p>|`min(/Aruba CX 8300s by SNMP/aruba.system.sensors[arubaWiredTempSensorTemperature.{#SNMPINDEX}],5m)>{#ARUBA.TEMPERATURE.MAX}`|Average||
|Aruba: Sensor [{#SNMPVALUE}]: Low temperature|<p>The current temperature is less than the threshold state.</p>|`max(/Aruba CX 8300s by SNMP/aruba.system.sensors[arubaWiredTempSensorTemperature.{#SNMPINDEX}],5m)<{#ARUBA.TEMPERATURE.MIN}`|Average||
|Aruba: Sensor [{#SNMPVALUE}]: State is not "normal"|<p>The current temperature sensor state is not "normal".</p>|`last(/Aruba CX 8300s by SNMP/aruba.system.sensors[arubaWiredTempSensorState.{#SNMPINDEX}])<>"normal"`|Average||

### LLD rule Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan discovery|<p>Used for discovering fans from ARUBAWIRED-FAN-MIB.</p>|Dependent item|aruba.fan.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan [{#SNMPVALUE}]: Status|<p>MIB: ARUBAWIRED-FAN-MIB</p><p>Current status of the fan.</p><p>Possible values:</p><p>1 - Unknown;</p><p>2 - Empty;</p><p>3 - Uninitialized;</p><p>4 - Ok;</p><p>5 - Fault.</p>|Dependent item|aruba.fan.status[arubaWiredFanStateEnum.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.47196.4.1.1.3.11.5.1.1.10.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Fan [{#SNMPVALUE}]: Speed|<p>MIB: ARUBAWIRED-FAN-MIB</p><p>Current RPM read for the fan. RPM of -1 indicates the fan does not have RPM readback capability.</p>|Dependent item|aruba.fan.speed[arubaWiredFanRPM.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.47196.4.1.1.3.11.5.1.1.8.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Fan discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Aruba: Fan [{#SNMPVALUE}]: Status is not "Ok"|<p>The fan status is not "Ok".</p>|`last(/Aruba CX 8300s by SNMP/aruba.fan.status[arubaWiredFanStateEnum.{#SNMPINDEX}])<>4`|Average||

### LLD rule PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU discovery|<p>Used for discovering PSU from ARUBAWIRED-POWERSUPPLY-MIB.</p>|Dependent item|aruba.psu.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU [{#SNMPVALUE}]: State|<p>MIB: ARUBAWIRED-POWERSUPPLY-MIB</p><p>Current status for the power supply.</p><p>Possible values:</p><p>1 - Ok;</p><p>2 - Fault Absent;</p><p>3 - Fault Input;</p><p>4 - Fault Output;</p><p>5 - Fault POE;</p><p>6 - Fault No Recov;</p><p>7 - Alert;</p><p>8 - Unknown;</p><p>9 - Unsupported;</p><p>10 - Warning;</p><p>11 - Init;</p><p>12 - Empty;</p><p>13 - Fault Airflow;</p><p>14 - Fault Redundancy.</p>|Dependent item|aruba.psu.state[arubaWiredPSUStateEnum.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.47196.4.1.1.3.11.2.1.1.11.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|PSU [{#SNMPVALUE}]: Instantaneous power|<p>MIB: ARUBAWIRED-POWERSUPPLY-MIB</p><p>Total instantaneous power supplied by the power supply in watts.</p>|Dependent item|aruba.psu.power[arubaWiredPSUInstantaneousPower.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.47196.4.1.1.3.11.2.1.1.7.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for PSU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Aruba: PSU [{#SNMPVALUE}]: State is not "Ok"|<p>The PSU status is not "Ok".</p>|`last(/Aruba CX 8300s by SNMP/aruba.psu.state[arubaWiredPSUStateEnum.{#SNMPINDEX}])<>1`|Average||
|Aruba: PSU [{#SNMPVALUE}]: High power utilization|<p>Instantaneous power supplied more than {$ARUBA.POWER.SUPPLY.THR.MAX} of maximum.</p>|`(last(/Aruba CX 8300s by SNMP/aruba.psu.power[arubaWiredPSUInstantaneousPower.{#SNMPINDEX}])*100/{#ARUBA.POWER.SUPPLY.MAX.POWER}) > {$ARUBA.POWER.SUPPLY.THR.MAX}`|Average||

### LLD rule OSPF Area discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OSPF Area discovery|<p>Used for discovering PSU from OSPF-MIB.</p>|Dependent item|aruba.ospf.area.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for OSPF Area discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OSPF area [{#SNMPVALUE}]: Auth type|<p>MIB: OSPF-MIB</p><p>The authentication type specified for an area.</p><p>Possible values:</p><p>0 - None;</p><p>1 - Simple password;</p><p>2 - md5.</p>|Dependent item|aruba.ospf.area.type[ospfAuthType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.2.1.2.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|OSPF area [{#SNMPVALUE}]: Runs|<p>MIB: OSPF-MIB</p><p>The number of times that the intra-area route table has been calculated using this area's link state database.</p><p>This is typically done using Dijkstra's algorithm.</p><p>Discontinuities in the value of this counter may occur at re-initialization of the management system, and at other</p><p>times as indicated by the value of ospfDiscontinuityTime.</p>|Dependent item|aruba.ospf.area.runs[ospfSpfRuns.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.2.1.4.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|OSPF area [{#SNMPVALUE}]: LSA count|<p>MIB: OSPF-MIB</p><p>The total number of link state advertisements in this area's link state database, excluding</p><p>AS-external LSAs.</p>|Dependent item|aruba.ospf.area.lsa[ospfAreaLsaCount.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.2.1.7.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|OSPF area [{#SNMPVALUE}]: LSA checksum|<p>MIB: OSPF-MIB</p><p>The 32-bit sum of the checksums of the LSAs contained in this area's link state database.</p><p>This sum excludes external (LS type-5) link state advertisements. The sum can be used to determine if there has</p><p>been a change in a router's link state database, and to compare the link state database of two routers.</p><p>The value should be treated as unsigned when comparing two sums of checksums.</p>|Dependent item|aruba.ospf.area.lsa.cksumsum[ospfAreaLsaCksumSum.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.2.1.8.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|OSPF area [{#SNMPVALUE}]: Status|<p>MIB: OSPF-MIB</p><p>This object permits management of the table by facilitating actions such as row creation, construction,</p><p>and destruction. The value of this object has no effect on whether other objects in this conceptual row can be</p><p>modified.</p><p>Possible values:</p><p>1 - Active;</p><p>2 - Not in service;</p><p>3 - Not ready;</p><p>4 - Create and go;</p><p>5 - Create and wait;</p><p>6 - Destroy.</p>|Dependent item|aruba.ospf.area.status[ospfAreaStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.2.1.10.{#SNMPINDEX}`</p></li></ul>|
|OSPF area [{#SNMPVALUE}]: Translator role|<p>MIB: OSPF-MIB</p><p>Indicates an NSSA border router's ability to perform NSSA translation of type-7 LSAs into type-5 LSAs.</p><p>Possible values:</p><p>1 - Always;</p><p>2 - Candidate.</p>|Dependent item|aruba.ospf.area.lsa.translator.role[ospfAreaNssaTranslatorRole.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.2.1.11.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|OSPF area [{#SNMPVALUE}]: Translator state|<p>MIB: OSPF-MIB</p><p>Indicates NSSA border router translation state of type-7 LSAs into type-5 LSAs.</p><p>Possible values:</p><p>1 - Enabled;</p><p>2 - Elected;</p><p>3 - Disabled.</p><p>When `Enabled`, the NSSA Border router's `OspfAreaNssaExtTranslatorRole` is `Always`.</p><p>When `Elected`, a candidate NSSA border router is translating type-7 LSAs into type-5.</p><p>When `Disabled`, a candidate NSSA border router is not translating type-7 LSAs into type-5.</p>|Dependent item|aruba.ospf.area.lsa.translator.state[ospfAreaNssaTranslatorState.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.2.1.12.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for OSPF Area discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Aruba: OSPF area [{#SNMPVALUE}]: Status is not "Active"|<p>The status of OSPF area is not "Active".</p>|`count(/Aruba CX 8300s by SNMP/aruba.ospf.area.status[ospfAreaStatus.{#SNMPINDEX}],#2,"eq",1)=0`|Average||

### LLD rule OSPF Neighbor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OSPF Neighbor discovery|<p>Used for discovering PSU from OSPF-MIB.</p>|Dependent item|aruba.ospf.neighbor.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for OSPF Neighbor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OSPF neighbor [{#SNMPVALUE}]: Neighbor router ID|<p>MIB: OSPF-MIB</p><p>A 32-bit integer (represented as a type IpAddress) uniquely identifying the neighboring router in the Autonomous System.</p>|Dependent item|aruba.ospf.neighbor.rtr.id[ospfNbrRtrId.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.10.1.3.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|OSPF neighbor [{#SNMPVALUE}]: Options|<p>MIB: OSPF-MIB</p><p>A bit mask corresponding to the neighbor's options field.</p><p></p><p>Bit 0, if set, indicates that the system will operate on Type of Service metrics other than TOS 0.</p><p>If zero, the neighbor will ignore all metrics except the TOS 0 metric.</p><p></p><p>Bit 1, if set, indicates that the associated area accepts and operates on external</p><p>information; if zero, it is a stub area.</p><p></p><p>Bit 2, if set, indicates that the system is capable of routing IP multicast datagrams – it implements the multicast extensions to OSPF.</p><p></p><p>Bit 3, if set, indicates that the associated area is an NSSA. These areas are capable of carrying type-7 external advertisements,</p><p>which are translated into type-5 external advertisements at NSSA borders.</p>|Dependent item|aruba.ospf.neighbor.options[ospfNbrOptions.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.10.1.4.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|OSPF neighbor [{#SNMPVALUE}]: Priority|<p>MIB: OSPF-MIB</p><p>The priority of this neighbor in the designated router election algorithm. The value 0 signifies</p><p>that the neighbor is not eligible to become the designated router on this particular network.</p>|Dependent item|aruba.ospf.neighbor.priority[ospfNbrPriority.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.10.1.5.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|OSPF neighbor [{#SNMPVALUE}]: State|<p>MIB: OSPF-MIB</p><p>The state of the relationship with this neighbor.</p><p>Possible values:</p><p>1 - Down;</p><p>2 - Attempt;</p><p>3 - Init;</p><p>4 - Two way;</p><p>5 - Exchange start;</p><p>6 - Exchange;</p><p>7 - Loading;</p><p>8 - Full.</p>|Dependent item|aruba.ospf.neighbor.state[ospfNbrState.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.10.1.6.{#SNMPINDEX}`</p></li></ul>|
|OSPF neighbor [{#SNMPVALUE}]: Events|<p>MIB: OSPF-MIB</p><p>The number of times this neighbor relationship has changed state or an error has occurred.</p><p></p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other</p><p>times as indicated by the value of ospfDiscontinuityTime.</p>|Dependent item|aruba.ospf.neighbor.events[ospfNbrEvents.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.10.1.7.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|OSPF neighbor [{#SNMPVALUE}]: Retrans queue length|<p>MIB: OSPF-MIB</p><p>The current length of the retransmission queue.</p>|Dependent item|aruba.ospf.neighbor.retrans.q.len[ospfNbrLsRetransQLen.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.10.1.8.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|OSPF neighbor [{#SNMPVALUE}]: Status|<p>MIB: OSPF-MIB</p><p>This object permits management of the table by facilitating actions such as row creation,</p><p>construction, and destruction.</p><p></p><p>The value of this object has no effect on whether other objects in this conceptual row can be</p><p>modified.</p><p>Possible values:</p><p>1 - Active;</p><p>2 - Not in service;</p><p>3 - Not ready;</p><p>4 - Create and go;</p><p>5 - Create and wait;</p><p>6 - Destroy.</p>|Dependent item|aruba.ospf.neighbor.status[ospfNbmaNbrStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.10.1.9.{#SNMPINDEX}`</p></li></ul>|

### Trigger prototypes for OSPF Neighbor discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Aruba: OSPF neighbor [{#SNMPVALUE}]: State is not "Full" or "Two way"|<p>The neighbor state is not "Full" or "Two way".</p>|`count(/Aruba CX 8300s by SNMP/aruba.ospf.neighbor.state[ospfNbrState.{#SNMPINDEX}],#2,"eq",8)=0 and count(/Aruba CX 8300s by SNMP/aruba.ospf.neighbor.state[ospfNbrState.{#SNMPINDEX}],#2,"eq",4)=0`|Average||
|Aruba: OSPF neighbor [{#SNMPVALUE}]: Status is not "Active"|<p>The neighbor status is not "Active".</p>|`count(/Aruba CX 8300s by SNMP/aruba.ospf.neighbor.status[ospfNbmaNbrStatus.{#SNMPINDEX}],#2,"eq",1)=0`|Average||

### LLD rule OSPF interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OSPF interface discovery|<p>Used for discovering PSU from OSPF-MIB.</p>|Dependent item|aruba.ospf.interface.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for OSPF interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OSPF interface [{#SNMPVALUE}]: Area ID|<p>MIB: OSPF-MIB</p><p>A 32-bit integer uniquely identifying the area to which the interface connects. Area ID 0.0.0.0 is used for the OSPF backbone.</p>|Dependent item|aruba.ospf.interface.area.id[ospfIfAreaId.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.7.1.3.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|OSPF interface [{#SNMPVALUE}]: Type|<p>MIB: OSPF-MIB</p><p>The OSPF interface type. By way of a default, this field may be intuited from the corresponding value of ifType.</p><p>Broadcast LANs, such as Ethernet and IEEE 802.5, take the value 'broadcast', X.25 and similar</p><p>technologies take the value 'nbma', and links that are definitively point-to-point take the value 'pointToPoint'.</p><p>Possible values:</p><p>1 - Broadcast;</p><p>2 - NBMA;</p><p>3 - Point to point;</p><p>4 - Virtual link;</p><p>5 - Point to multipoint.</p>|Dependent item|aruba.ospf.interface.type[ospfIfType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.7.1.4.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|OSPF interface [{#SNMPVALUE}]: Admin status|<p>MIB: OSPF-MIB</p><p>The OSPF interface's administrative status. If enabled, the interface is advertised as an internal route to an area.</p><p>The value 'disabled' denotes that the interface is external to OSPF.</p><p>Possible values:</p><p>1 - Enabled;</p><p>2 - Disabled.</p>|Dependent item|aruba.ospf.interface.admin.stat[ospfIfAdminStat.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.7.1.5.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|OSPF interface [{#SNMPVALUE}]: Priority|<p>MIB: OSPF-MIB</p><p>The priority of this interface. Used in multi-access networks, this field is used in</p><p>the designated router election algorithm. The value 0 signifies that the router is not eligible</p><p>to become the designated router on this particular network. In the event of a tie in this value,</p><p>routers will use their Router ID as a tie breaker.</p>|Dependent item|aruba.ospf.interface.rtr.priority[ospfIfRtrPriority.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.7.1.6.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|OSPF interface [{#SNMPVALUE}]: State|<p>MIB: OSPF-MIB</p><p>The OSPF Interface State.</p><p>Possible values:</p><p>1 - Down;</p><p>2 - Loopback;</p><p>3 - Waiting;</p><p>4 - Point to point;</p><p>5 - Designated router;</p><p>6 - Backup designated router;</p><p>7 - Other designated router.</p>|Dependent item|aruba.ospf.interface.state[ospfIfState.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.7.1.12.{#SNMPINDEX}`</p></li></ul>|
|OSPF interface [{#SNMPVALUE}]: Events|<p>MIB: OSPF-MIB</p><p>The number of times this OSPF interface has changed its state or an error has occurred.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other</p><p>times as indicated by the value of ospfDiscontinuityTime.</p>|Dependent item|aruba.ospf.interface.events[ospfIfEvents.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.7.1.15.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|OSPF interface [{#SNMPVALUE}]: Status|<p>MIB: OSPF-MIB</p><p>This object permits management of the table by facilitating actions such as row creation, construction, and destruction.</p><p>The value of this object has no effect on whether other objects in this conceptual row can be modified.</p><p>Possible values:</p><p>1 - Active;</p><p>2 - Not in service;</p><p>3 - Not ready;</p><p>4 - Create and go;</p><p>5 - Create and wait;</p><p>6 - Destroy.</p>|Dependent item|aruba.ospf.interface.status[ospfIfStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.14.7.1.17.{#SNMPINDEX}`</p></li></ul>|

### Trigger prototypes for OSPF interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Aruba: OSPF Interface [{#SNMPVALUE}]: State is "Down" or "Waiting"|<p>The OSPF Interface state is "Down" or "Waiting".</p>|`(count(/Aruba CX 8300s by SNMP/aruba.ospf.interface.state[ospfIfState.{#SNMPINDEX}],#2,"eq",3)=2 or count(/Aruba CX 8300s by SNMP/aruba.ospf.interface.state[ospfIfState.{#SNMPINDEX}],#2,"eq",1)=2) and last(/Aruba CX 8300s by SNMP/aruba.ospf.interface.admin.stat[ospfIfAdminStat.{#SNMPINDEX}])=1`|Average||
|Aruba: OSPF interface [{#SNMPVALUE}]: Status is not "Active"|<p>The OSPF interface status is not "Active".</p>|`count(/Aruba CX 8300s by SNMP/aruba.ospf.interface.status[ospfIfStatus.{#SNMPINDEX}],#2,"ne",1)=2 and last(/Aruba CX 8300s by SNMP/aruba.ospf.interface.admin.stat[ospfIfAdminStat.{#SNMPINDEX}])=1`|Average||

### LLD rule Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interfaces discovery|<p>Discovering interfaces from IF-MIB.</p>|Dependent item|net.if.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface {#IFNAME}({#IFALIAS}): Operational status|<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The testing(3) state indicates that no operational packet scan be passed</p><p>- If ifAdminStatus is down(2) then ifOperStatus should be down(2)</p><p>- If ifAdminStatus is changed to up(1) then ifOperStatus should change to up(1) if the interface is ready to transmit and receive network traffic</p><p>- It should change todormant(5) if the interface is waiting for external actions (such as a serial line waiting for an incoming connection)</p><p>- It should remain in the down(2) state if and only if there is a fault that prevents it from going to the up(1) state</p><p>- It should remain in the notPresent(6) state if the interface has missing(typically, hardware) components.</p>|Dependent item|net.if.status[ifOperStatus.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.8.{#SNMPINDEX}`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits received|<p>MIB: IF-MIB</p><p>The total number of octets received on the interface,including framing characters. Discontinuities in the value of this counter can occur at re-initialization of the management system, and another times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.in[ifInOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.10.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Bits sent|<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.out[ifOutOctets.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.16.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.in.errors[ifInErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.14.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors|<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.out.errors[ifOutErrors.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.20.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded|<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.out.discards[ifOutDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.19.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded|<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p>|Dependent item|net.if.in.discards[ifInDiscards.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.13.{#SNMPINDEX}`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `3m`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Interface type|<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for ifType are assigned by the Internet Assigned Numbers Authority (IANA),</p><p>through updating the syntax of the IANAifType textual convention.</p>|Dependent item|net.if.type[ifType.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.3.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interface {#IFNAME}({#IFALIAS}): Speed|<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in bits per second.</p><p>For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made,</p><p>this object should contain the nominal bandwidth.</p><p>If the bandwidth of the interface is greater than the maximum value reportable by this object then</p><p>this object should report its maximum value (4,294,967,295) and ifHighSpeed must be used to report the interface's speed.</p><p>For a sub-layer which has no concept of bandwidth, this object should be zero.</p>|Dependent item|net.if.speed[ifSpeed.{#SNMPINDEX}]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.2.1.2.2.1.5.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `5m`</p></li></ul>|

### Trigger prototypes for Network interfaces discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Aruba: Interface {#IFNAME}({#IFALIAS}): Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine the context macro to "0", marking this interface as not important. No new trigger will be fired if this interface is down.<br>3. `last(/TEMPLATE_NAME/METRIC,#1)<>last(/TEMPLATE_NAME/METRIC,#2)` - the trigger fires only if the operational status was up to (1) sometime before (so, does not fire for "eternal off" interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Aruba CX 8300s by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])=2 and (last(/Aruba CX 8300s by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#1)<>last(/Aruba CX 8300s by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}],#2))`|Average|**Manual close**: Yes|
|Aruba: Interface {#IFNAME}({#IFALIAS}): High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Aruba CX 8300s by SNMP/net.if.in[ifInOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Aruba CX 8300s by SNMP/net.if.speed[ifSpeed.{#SNMPINDEX}]) or avg(/Aruba CX 8300s by SNMP/net.if.out[ifOutOctets.{#SNMPINDEX}],15m)>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Aruba CX 8300s by SNMP/net.if.speed[ifSpeed.{#SNMPINDEX}])) and last(/Aruba CX 8300s by SNMP/net.if.speed[ifSpeed.{#SNMPINDEX}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Aruba: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Aruba: Interface {#IFNAME}({#IFALIAS}): High error rate|<p>It recovers when it is below 80% of the `{$IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Aruba CX 8300s by SNMP/net.if.in.errors[ifInErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"} or min(/Aruba CX 8300s by SNMP/net.if.out.errors[ifOutErrors.{#SNMPINDEX}],5m)>{$IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Aruba: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|
|Aruba: Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Aruba CX 8300s by SNMP/net.if.speed[ifSpeed.{#SNMPINDEX}])<0 and last(/Aruba CX 8300s by SNMP/net.if.speed[ifSpeed.{#SNMPINDEX}])>0 and ( last(/Aruba CX 8300s by SNMP/net.if.type[ifType.{#SNMPINDEX}])=6 or last(/Aruba CX 8300s by SNMP/net.if.type[ifType.{#SNMPINDEX}])=7 or last(/Aruba CX 8300s by SNMP/net.if.type[ifType.{#SNMPINDEX}])=11 or last(/Aruba CX 8300s by SNMP/net.if.type[ifType.{#SNMPINDEX}])=62 or last(/Aruba CX 8300s by SNMP/net.if.type[ifType.{#SNMPINDEX}])=69 or last(/Aruba CX 8300s by SNMP/net.if.type[ifType.{#SNMPINDEX}])=117 ) and (last(/Aruba CX 8300s by SNMP/net.if.status[ifOperStatus.{#SNMPINDEX}])<>2)`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Aruba: Interface {#IFNAME}({#IFALIAS}): Link down</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

