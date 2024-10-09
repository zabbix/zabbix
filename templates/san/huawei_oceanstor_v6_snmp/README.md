
# Huawei OceanStor V6 by SNMP

## Overview

This template is developed to monitor SAN Huawei OceanStor V6 via the Zabbix SNMP agent.

## Requirements

Zabbix version: 7.2 and higher.

## Tested versions

This template has been tested on:
- Huawei OceanStor V6 Dorado 5000

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.2/manual/config/templates_out_of_the_box) section.

## Setup

1\. Create a host for Huawei OceanStor V6 with the controller management IP as the SNMP interface.

2\. Link the template to the host.

3\. Customize macro values if needed.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT}|<p>The critical threshold of CPU utilization expressed in %.</p>|`90`|
|{$HUAWEI.OCEANSTOR_V6.MEM.MAX.WARN}|<p>Maximum percentage of memory used.</p>|`90`|
|{$HUAWEI.OCEANSTOR_V6.MEM.MAX.TIME}|<p>The time during which memory usage may exceed the threshold.</p>|`5m`|
|{$HUAWEI.OCEANSTOR_V6.TEMP.MAX.WARN}|<p>Maximum enclosure temperature.</p>|`35`|
|{$HUAWEI.OCEANSTOR_V6.TEMP.MAX.TIME}|<p>The time during which the enclosure temperature may exceed the threshold.</p>|`3m`|
|{$HUAWEI.OCEANSTOR_V6.DISK.TEMP.MAX.WARN}|<p>Maximum disk temperature. Can be used with `{#MODEL}` as context.</p>|`45`|
|{$HUAWEI.OCEANSTOR_V6.DISK.TEMP.MAX.TIME}|<p>The time during which the disk temperature may exceed the threshold.</p>|`5m`|
|{$HUAWEI.OCEANSTOR_V6.LUN.IO.TIME.MAX.WARN}|<p>Maximum average I/O response time of a LUN in seconds.</p>|`0.0001`|
|{$HUAWEI.OCEANSTOR_V6.LUN.IO.TIME.MAX.TIME}|<p>The time during which the average I/O response time of a LUN may exceed the threshold.</p>|`5m`|
|{$SNMP.TIMEOUT}|<p>Time interval for the SNMP availability trigger.</p>|`5m`|
|{$ICMP_LOSS_WARN}|<p>Warning threshold of ICMP packet loss in %.</p>|`20`|
|{$ICMP_RESPONSE_TIME_WARN}|<p>Warning threshold of the average ICMP response time in seconds.</p>|`0.15`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Status|<p>System status.</p>|SNMP agent|huawei.oceanstor.v6.status<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Version|<p>The device version.</p>|SNMP agent|huawei.oceanstor.v6.version<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Capacity total|<p>Total capacity of a device.</p>|SNMP agent|huawei.oceanstor.v6.capacity.total<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Capacity used|<p>Used capacity of a device.</p>|SNMP agent|huawei.oceanstor.v6.capacity.used<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Controller SNMP walk|<p>Discovery of device controllers.</p>|SNMP agent|huawei.oceanstor.v6.controller.snmp.walk|
|Enclosure SNMP walk|<p>Discovery of device enclosures.</p>|SNMP agent|huawei.oceanstor.v6.enclosure.snmp.walk|
|Fan SNMP walk|<p>Discovery of device fans.</p>|SNMP agent|huawei.oceanstor.v6.fan.snmp.walk|
|BBU SNMP walk|<p>Discovery of device backup battery units.</p>|SNMP agent|huawei.oceanstor.v6.bbu.snmp.walk|
|Disk SNMP walk|<p>Discovery of device disks.</p>|SNMP agent|huawei.oceanstor.v6.disk.snmp.walk|
|Node SNMP walk|<p>Discovery of device nodes.</p>|SNMP agent|huawei.oceanstor.v6.node.snmp.walk|
|LUN SNMP walk|<p>Discovery of device logical unit numbers.</p>|SNMP agent|huawei.oceanstor.v6.lun.snmp.walk|
|Storage pool SNMP walk|<p>Discovery of device storage pools.</p>|SNMP agent|huawei.oceanstor.v6.pool.snmp.walk|
|Uptime (network)|<p>MIB: SNMPv2-MIB</p><p>Time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p>|SNMP agent|system.net.uptime[sysUpTime.0]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Uptime (hardware)|<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p>|SNMP agent|system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `0.01`</p></li></ul>|
|SNMP traps (fallback)|<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p>|SNMP trap|snmptrap.fallback|
|System location|<p>MIB: SNMPv2-MIB</p><p>Physical location of the node (e.g., `equipment room`, `3rd floor`). If not provided, the value is a zero-length string.</p>|SNMP agent|system.location[sysLocation.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System contact details|<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</p>|SNMP agent|system.contact[sysContact.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System object ID|<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity.  This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p>|SNMP agent|system.objectid[sysObjectID.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System name|<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name.  If the name is unknown, the value is the zero-length string.</p>|SNMP agent|system.name<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System description|<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p>|SNMP agent|system.descr[sysDescr.0]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|SNMP agent availability|<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible values:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p>|Zabbix internal|zabbix[host,snmp,available]|
|ICMP ping||Simple check|icmpping|
|ICMP loss||Simple check|icmppingloss|
|ICMP response time||Simple check|icmppingsec|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Storage version has changed||`last(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.version,#1)<>last(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.version,#2) and length(last(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.version))>0`|Info|**Manual close**: Yes|
|Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Huawei OceanStor V6 by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Huawei OceanStor V6 by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Huawei OceanStor V6 by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Huawei OceanStor V6 by SNMP/system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>No SNMP data collection</li></ul>|
|System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/Huawei OceanStor V6 by SNMP/system.name,#1)<>last(/Huawei OceanStor V6 by SNMP/system.name,#2) and length(last(/Huawei OceanStor V6 by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/Huawei OceanStor V6 by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>Unavailable by ICMP ping</li></ul>|
|Unavailable by ICMP ping|<p>Last three attempts returned timeout.  Please check device connectivity.</p>|`max(/Huawei OceanStor V6 by SNMP/icmpping,#3)=0`|High||
|High ICMP ping loss||`min(/Huawei OceanStor V6 by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Huawei OceanStor V6 by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>Unavailable by ICMP ping</li></ul>|
|High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/Huawei OceanStor V6 by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>High ICMP ping loss</li><li>Unavailable by ICMP ping</li></ul>|

### LLD rule Controller discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Controller discovery|<p>Discovery of controllers.</p>|Dependent item|huawei.oceanstor.v6.controller.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Controller discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Controller [{#ID}]: CPU utilization|<p>CPU utilization of the controller.</p>|Dependent item|huawei.oceanstor.v6.controller.cpu["{#ID}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.23.5.2.1.8.{#SNMPINDEX}`</p></li></ul>|
|Controller [{#ID}]: Memory utilization|<p>Memory utilization of the controller.</p>|Dependent item|huawei.oceanstor.v6.controller.memory["{#ID}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.23.5.2.1.9.{#SNMPINDEX}`</p></li></ul>|
|Controller [{#ID}]: Health status|<p>Controller health status.</p>|Dependent item|huawei.oceanstor.v6.controller.health_status["{#ID}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.23.5.2.1.2.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Controller [{#ID}]: Running status|<p>Controller running status.</p>|Dependent item|huawei.oceanstor.v6.controller.running_status["{#ID}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.23.5.2.1.3.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Controller [{#ID}]: Role|<p>Controller role.</p>|Dependent item|huawei.oceanstor.v6.controller.role["{#ID}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.23.5.2.1.6.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Controller discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Controller [{#ID}]: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.controller.cpu["{#ID}"],5m)>{$CPU.UTIL.CRIT}`|Warning||
|Controller [{#ID}]: Memory usage is too high||`min(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.controller.memory["{#ID}"],{$HUAWEI.OCEANSTOR_V6.MEM.MAX.TIME})>{$HUAWEI.OCEANSTOR_V6.MEM.MAX.WARN}`|Average||
|Controller [{#ID}]: Health status is not Normal||`last(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.controller.health_status["{#ID}"])<>1`|High||
|Controller [{#ID}]: Running status is not Online||`last(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.controller.running_status["{#ID}"])<>27`|Average||
|Controller [{#ID}]: Role has been changed||`last(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.controller.role["{#ID}"],#1)<>last(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.controller.role["{#ID}"],#2)`|Warning|**Manual close**: Yes|

### LLD rule Enclosure discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Enclosure discovery|<p>Discovery of enclosures.</p>|Dependent item|huawei.oceanstor.v6.enclosure.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Enclosure discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Enclosure [{#NAME}]: Health status|<p>Enclosure health status.</p>|Dependent item|huawei.oceanstor.v6.enclosure.health_status["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.23.5.6.1.4.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Enclosure [{#NAME}]: Running status|<p>Enclosure running status.</p>|Dependent item|huawei.oceanstor.v6.enclosure.running_status["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.23.5.6.1.5.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Enclosure [{#NAME}]: Temperature|<p>Enclosure temperature.</p>|Dependent item|huawei.oceanstor.v6.enclosure.temperature["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.23.5.6.1.8.{#SNMPINDEX}`</p></li></ul>|

### Trigger prototypes for Enclosure discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Enclosure [{#NAME}]: Health status is not Normal||`last(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.enclosure.health_status["{#NAME}"])<>1`|High||
|Enclosure [{#NAME}]: Running status is not Online||`last(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.enclosure.running_status["{#NAME}"])<>27`|Average||
|Enclosure [{#NAME}]: Temperature is too high||`min(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.enclosure.temperature["{#NAME}"],{$HUAWEI.OCEANSTOR_V6.TEMP.MAX.TIME})>{$HUAWEI.OCEANSTOR_V6.TEMP.MAX.WARN}`|High||

### LLD rule Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan discovery|<p>Discovery of fans.</p>|Dependent item|huawei.oceanstor.v6.fan.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Fan discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan [{#ID}] on [{#LOCATION}]: Health status|<p>Health status of a fan.</p>|Dependent item|huawei.oceanstor.v6.fan.health_status["{#ID}:{#LOCATION}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.23.5.4.1.3.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Fan [{#ID}] on [{#LOCATION}]: Running status|<p>Operating status of a fan.</p>|Dependent item|huawei.oceanstor.v6.fan.running_status["{#ID}:{#LOCATION}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.23.5.4.1.4.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Fan discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Fan [{#ID}] on [{#LOCATION}]: Health status is not Normal||`last(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.fan.health_status["{#ID}:{#LOCATION}"])<>1`|High||
|Fan [{#ID}] on [{#LOCATION}]: Running status is not Running||`last(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.fan.running_status["{#ID}:{#LOCATION}"])<>2`|Average||

### LLD rule BBU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|BBU discovery|<p>Discovery of BBUs.</p>|Dependent item|huawei.oceanstor.v6.bbu.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for BBU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|BBU [{#ID}] on [{#LOCATION}]: Health status|<p>Health status of a BBU.</p>|Dependent item|huawei.oceanstor.v6.bbu.health_status["{#ID}:{#LOCATION}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.23.5.5.1.3.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|BBU [{#ID}] on [{#LOCATION}]: Running status|<p>Running status of a BBU.</p>|Dependent item|huawei.oceanstor.v6.bbu.running_status["{#ID}:{#LOCATION}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.23.5.5.1.4.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for BBU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|BBU [{#ID}] on [{#LOCATION}]: Health status is not Normal||`last(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.bbu.health_status["{#ID}:{#LOCATION}"])<>1`|High||
|BBU [{#ID}] on [{#LOCATION}]: Running status is not Online||`last(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.bbu.running_status["{#ID}:{#LOCATION}"])<>27`|Average||

### LLD rule Disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk discovery|<p>Discovery of disks.</p>|Dependent item|huawei.oceanstor.v6.disk.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk [{#MODEL}] on [{#LOCATION}]: Health status|<p>Disk health status.</p>|Dependent item|huawei.oceanstor.v6.disk.health_status["{#ID}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.23.5.1.1.2.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Disk [{#MODEL}] on [{#LOCATION}]: Running status|<p>Disk running status.</p>|Dependent item|huawei.oceanstor.v6.disk.running_status["{#ID}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.23.5.1.1.3.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Disk [{#MODEL}] on [{#LOCATION}]: Temperature|<p>Disk temperature.</p>|Dependent item|huawei.oceanstor.v6.disk.temperature["{#ID}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.23.5.1.1.11.{#SNMPINDEX}`</p></li></ul>|

### Trigger prototypes for Disk discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Disk [{#MODEL}] on [{#LOCATION}]: Health status is not Normal||`last(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.disk.health_status["{#ID}"])<>1`|High||
|Disk [{#MODEL}] on [{#LOCATION}]: Running status is not Online||`last(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.disk.running_status["{#ID}"])<>27`|Average||
|Disk [{#MODEL}] on [{#LOCATION}]: Temperature is too high||`min(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.disk.temperature["{#ID}"],{$HUAWEI.OCEANSTOR_V6.DISK.TEMP.MAX.TIME})>{$HUAWEI.OCEANSTOR_V6.DISK.TEMP.MAX.WARN:"{#MODEL}"}`|High||

### LLD rule Node performance discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node performance discovery|<p>Discovery of node performance counters.</p>|Dependent item|huawei.oceanstor.v6.node.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li></ul>|

### Item prototypes for Node performance discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NODE}]: CPU utilization|<p>CPU utilization of the node.</p>|Dependent item|huawei.oceanstor.v6.node.cpu["{#NODE}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.21.3.1.2.{#SNMPINDEX}`</p></li></ul>|
|Node [{#NODE}]: Total I/O per second|<p>Total IOPS of the node.</p>|Dependent item|huawei.oceanstor.v6.node.iops.total["{#NODE}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.21.3.1.5.{#SNMPINDEX}`</p></li></ul>|
|Node [{#NODE}]: Read operations per second|<p>Read IOPS of the node.</p>|Dependent item|huawei.oceanstor.v6.node.iops.read["{#NODE}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.21.3.1.6.{#SNMPINDEX}`</p></li></ul>|
|Node [{#NODE}]: Write operations per second|<p>Write IOPS of the node.</p>|Dependent item|huawei.oceanstor.v6.node.iops.write["{#NODE}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.21.3.1.7.{#SNMPINDEX}`</p></li></ul>|
|Node [{#NODE}]: Total traffic per second|<p>Total bandwidth for the node.</p>|Dependent item|huawei.oceanstor.v6.node.bps.total["{#NODE}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.21.3.1.8.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Node [{#NODE}]: Read traffic per second|<p>Read bandwidth for the node.</p>|Dependent item|huawei.oceanstor.v6.node.bps.read["{#NODE}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.21.3.1.9.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Node [{#NODE}]: Write traffic per second|<p>Write bandwidth for the node.</p>|Dependent item|huawei.oceanstor.v6.node.bps.write["{#NODE}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.21.3.1.10.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|

### Trigger prototypes for Node performance discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Node [{#NODE}]: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.node.cpu["{#NODE}"],5m)>{$CPU.UTIL.CRIT}`|Warning||

### LLD rule LUN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|LUN discovery|<p>Discovery of LUNs.</p>|Dependent item|huawei.oceanstor.v6.lun.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li></ul>|

### Item prototypes for LUN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|LUN [{#NAME}]: Status|<p>Status of the LUN.</p>|Dependent item|huawei.oceanstor.v6.lun.status["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.19.9.4.1.11.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|LUN [{#NAME}]: Average total I/O latency|<p>Average I/O latency of the node.</p>|Dependent item|huawei.oceanstor.v6.lun.latency.total["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.21.4.1.13.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `1e-06`</p></li></ul>|
|LUN [{#NAME}]: Average read I/O latency|<p>Average read I/O response time.</p>|Dependent item|huawei.oceanstor.v6.lun.latency.read["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.21.4.1.15.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `1e-06`</p></li></ul>|
|LUN [{#NAME}]: Average write I/O latency|<p>Average write I/O response time.</p>|Dependent item|huawei.oceanstor.v6.lun.latency.write["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.21.4.1.16.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `1e-06`</p></li></ul>|
|LUN [{#NAME}]: Total I/O per second|<p>Current IOPS of the LUN.</p>|Dependent item|huawei.oceanstor.v6.lun.iops.total["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.21.4.1.3.{#SNMPINDEX}`</p></li></ul>|
|LUN [{#NAME}]: Read operations per second|<p>Read IOPS of the node.</p>|Dependent item|huawei.oceanstor.v6.lun.iops.read["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.21.4.1.4.{#SNMPINDEX}`</p></li></ul>|
|LUN [{#NAME}]: Write operations per second|<p>Write IOPS of the node.</p>|Dependent item|huawei.oceanstor.v6.lun.iops.write["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.21.4.1.5.{#SNMPINDEX}`</p></li></ul>|
|LUN [{#NAME}]: Total traffic per second|<p>Current total bandwidth for the LUN.</p>|Dependent item|huawei.oceanstor.v6.lun.bps.total["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.21.4.1.6.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|LUN [{#NAME}]: Read traffic per second|<p>Current read bandwidth for the LUN.</p>|Dependent item|huawei.oceanstor.v6.lun.bps.read["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.21.4.1.7.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|LUN [{#NAME}]: Write traffic per second|<p>Current write bandwidth for the LUN.</p>|Dependent item|huawei.oceanstor.v6.lun.bps.write["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.21.4.1.8.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|LUN [{#NAME}]: Capacity|<p>Capacity of the LUN.</p>|Dependent item|huawei.oceanstor.v6.lun.capacity["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.19.9.4.1.5.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `1024`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for LUN discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|LUN [{#NAME}]: Status is not Normal||`last(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.lun.status["{#NAME}"])<>1`|Average||
|LUN [{#NAME}]: Average I/O response time is too high||`min(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.lun.latency.total["{#NAME}"],{$HUAWEI.OCEANSTOR_V6.LUN.IO.TIME.MAX.TIME})>{$HUAWEI.OCEANSTOR_V6.LUN.IO.TIME.MAX.WARN}`|Warning||

### LLD rule Storage pool discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage pool discovery|<p>Discovery of storage pools.</p>|Dependent item|huawei.oceanstor.v6.pool.discovery<p>**Preprocessing**</p><ul><li><p>SNMP walk to JSON</p></li></ul>|

### Item prototypes for Storage pool discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage pool [{#NAME}]: Health status|<p>Health status of a storage pool.</p>|Dependent item|huawei.oceanstor.v6.pool.health_status["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.23.4.2.1.5.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Storage pool [{#NAME}]: Running status|<p>Operating status of a storage pool.</p>|Dependent item|huawei.oceanstor.v6.pool.running_status["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.23.4.2.1.6.{#SNMPINDEX}`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Storage pool [{#NAME}]: Capacity total|<p>Total capacity of a storage pool.</p>|Dependent item|huawei.oceanstor.v6.pool.capacity.total["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.23.4.2.1.7.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Storage pool [{#NAME}]: Capacity free|<p>Available capacity of a storage pool.</p>|Dependent item|huawei.oceanstor.v6.pool.capacity.free["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>SNMP walk value: `1.3.6.1.4.1.34774.4.1.23.4.2.1.9.{#SNMPINDEX}`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|

### Trigger prototypes for Storage pool discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Storage pool [{#NAME}]: Health status is not Normal||`last(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.pool.health_status["{#NAME}"])<>1`|High||
|Storage pool [{#NAME}]: Running status is not Online||`last(/Huawei OceanStor V6 by SNMP/huawei.oceanstor.v6.pool.running_status["{#NAME}"])<>27`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

