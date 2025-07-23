
# Huawei OceanStor 5300 V5 by SNMP

## Overview

The template to monitor SAN Huawei OceanStor 5300 V5 by Zabbix SNMP agent.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Huawei OceanStor 5300 V5  

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1\. Create a host for Huawei OceanStor 5300 V5 with controller management IP as SNMPv2 interface.

2\. Link the template to the host.

3\. Customize macro values if needed.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT}|<p>The critical threshold of the CPU utilization expressed in %.</p>|`90`|
|{$HUAWEI.5300.MEM.MAX.WARN}|<p>Maximum percentage of memory used</p>|`90`|
|{$HUAWEI.5300.MEM.MAX.TIME}|<p>The time during which memory usage may exceed the threshold.</p>|`5m`|
|{$HUAWEI.5300.TEMP.MAX.WARN}|<p>Maximum temperature of enclosure</p>|`35`|
|{$HUAWEI.5300.TEMP.MAX.TIME}|<p>The time during which temperature of enclosure may exceed the threshold.</p>|`3m`|
|{$HUAWEI.5300.DISK.TEMP.MAX.WARN}|<p>Maximum temperature of disk. Can be used with {#MODEL} as context.</p>|`45`|
|{$HUAWEI.5300.DISK.TEMP.MAX.TIME}|<p>The time during which temperature of disk may exceed the threshold.</p>|`5m`|
|{$HUAWEI.5300.NODE.IO.DELAY.MAX.WARN}|<p>Maximum average I/O latency of node in milliseconds.</p>|`20`|
|{$HUAWEI.5300.NODE.IO.DELAY.MAX.TIME}|<p>The time during which average I/O latency of node may exceed the threshold.</p>|`5m`|
|{$HUAWEI.5300.LUN.IO.TIME.MAX.WARN}|<p>Maximum average I/O response time of LUN in milliseconds.</p>|`100`|
|{$HUAWEI.5300.LUN.IO.TIME.MAX.TIME}|<p>The time during which average I/O response time of LUN may exceed the threshold.</p>|`5m`|
|{$HUAWEI.5300.POOL.CAPACITY.THRESH.TIME}|<p>The time during which free capacity may exceed the {#THRESHOLD} from hwInfoStoragePoolFullThreshold.</p>|`5m`|
|{$SNMP.TIMEOUT}|<p>Time interval for the SNMP availability trigger.</p>|`5m`|
|{$ICMP_LOSS_WARN}|<p>Warning threshold of ICMP packet loss in %.</p>|`20`|
|{$ICMP_RESPONSE_TIME_WARN}|<p>Warning threshold of the average ICMP response time in seconds.</p>|`0.15`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Status|<p>System running status.</p>|SNMP agent|huawei.5300.v5[status]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Version|<p>The device version.</p>|SNMP agent|huawei.5300.v5[version]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Capacity total|<p>Total capacity of a device.</p>|SNMP agent|huawei.5300.v5[totalCapacity]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Capacity used|<p>Used capacity of a device.</p>|SNMP agent|huawei.5300.v5[usedCapacity]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li></ul>|
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
|Huawei OceanStor 5300 V5: Storage version has been changed|<p>OceanStor 5300 V5 version has changed. Acknowledge to close the problem manually.</p>|`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[version],#1)<>last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[version],#2) and length(last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[version]))>0`|Info|**Manual close**: Yes|
|Huawei OceanStor 5300 V5: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`(last(/Huawei OceanStor 5300 V5 by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Huawei OceanStor 5300 V5 by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Huawei OceanStor 5300 V5 by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Huawei OceanStor 5300 V5 by SNMP/system.net.uptime[sysUpTime.0])<10m)`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Huawei OceanStor 5300 V5: No SNMP data collection</li></ul>|
|Huawei OceanStor 5300 V5: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/Huawei OceanStor 5300 V5 by SNMP/system.name,#1)<>last(/Huawei OceanStor 5300 V5 by SNMP/system.name,#2) and length(last(/Huawei OceanStor 5300 V5 by SNMP/system.name))>0`|Info|**Manual close**: Yes|
|Huawei OceanStor 5300 V5: No SNMP data collection|<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p>|`max(/Huawei OceanStor 5300 V5 by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0`|Warning|**Depends on**:<br><ul><li>Huawei OceanStor 5300 V5: Unavailable by ICMP ping</li></ul>|
|Huawei OceanStor 5300 V5: Unavailable by ICMP ping|<p>Last three attempts returned timeout. Please check device connectivity.</p>|`max(/Huawei OceanStor 5300 V5 by SNMP/icmpping,#3)=0`|High||
|Huawei OceanStor 5300 V5: High ICMP ping loss|<p>ICMP packets loss detected.</p>|`min(/Huawei OceanStor 5300 V5 by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Huawei OceanStor 5300 V5 by SNMP/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>Huawei OceanStor 5300 V5: Unavailable by ICMP ping</li></ul>|
|Huawei OceanStor 5300 V5: High ICMP ping response time|<p>Average ICMP response time is too high.</p>|`avg(/Huawei OceanStor 5300 V5 by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>Huawei OceanStor 5300 V5: High ICMP ping loss</li><li>Huawei OceanStor 5300 V5: Unavailable by ICMP ping</li></ul>|

### LLD rule Controllers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Controllers discovery|<p>Discovery of controllers</p>|SNMP agent|huawei.5300.controllers.discovery|

### Item prototypes for Controllers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Controller {#ID}: CPU utilization|<p>CPU usage of a controller {#ID}.</p>|SNMP agent|huawei.5300.v5[hwInfoControllerCPUUsage, "{#ID}"]|
|Controller {#ID}: Memory utilization|<p>Memory usage of a controller {#ID}.</p>|SNMP agent|huawei.5300.v5[hwInfoControllerMemoryUsage, "{#ID}"]|
|Controller {#ID}: Health status|<p>Controller health status. For details, see definition of Enum Values (HEALTH_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p>|SNMP agent|huawei.5300.v5[hwInfoControllerHealthStatus, "{#ID}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Controller {#ID}: Running status|<p>Controller running status. For details, see definition of Enum Values (RUNNING_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p>|SNMP agent|huawei.5300.v5[hwInfoControllerRunningStatus, "{#ID}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Controller {#ID}: Role|<p>Controller role.</p>|SNMP agent|huawei.5300.v5[hwInfoControllerRole, "{#ID}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Controllers discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Huawei OceanStor 5300 V5: Controller {#ID}: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoControllerCPUUsage, "{#ID}"],5m)>{$CPU.UTIL.CRIT}`|Warning||
|Huawei OceanStor 5300 V5: Controller {#ID}: Memory usage is too high||`min(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoControllerMemoryUsage, "{#ID}"],{$HUAWEI.5300.MEM.MAX.TIME})>{$HUAWEI.5300.MEM.MAX.WARN}`|Average||
|Huawei OceanStor 5300 V5: Controller {#ID}: Health status is not Normal||`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoControllerHealthStatus, "{#ID}"])<>1`|High||
|Huawei OceanStor 5300 V5: Controller {#ID}: Running status is not Online||`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoControllerRunningStatus, "{#ID}"])<>27`|Average||
|Huawei OceanStor 5300 V5: Controller {#ID}: Role has been changed||`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoControllerRole, "{#ID}"],#1)<>last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoControllerRole, "{#ID}"],#2)`|Warning|**Manual close**: Yes|

### LLD rule Enclosure discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Enclosure discovery|<p>Discovery of enclosures</p>|SNMP agent|huawei.5300.enclosure.discovery|

### Item prototypes for Enclosure discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Enclosure {#NAME}: Health status|<p>Enclosure health status. For details, see definition of Enum Values (HEALTH_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p>|SNMP agent|huawei.5300.v5[hwInfoEnclosureHealthStatus, "{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Enclosure {#NAME}: Running status|<p>Enclosure running status. For details, see definition of Enum Values (RUNNING_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p>|SNMP agent|huawei.5300.v5[hwInfoEnclosureRunningStatus, "{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Enclosure {#NAME}: Temperature|<p>Enclosure temperature.</p>|SNMP agent|huawei.5300.v5[hwInfoEnclosureTemperature, "{#NAME}"]|

### Trigger prototypes for Enclosure discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Huawei OceanStor 5300 V5: Enclosure {#NAME}: Health status is not Normal||`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoEnclosureHealthStatus, "{#NAME}"])<>1`|High||
|Huawei OceanStor 5300 V5: Enclosure {#NAME}: Running status is not Online||`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoEnclosureRunningStatus, "{#NAME}"])<>27`|Average||
|Huawei OceanStor 5300 V5: Enclosure {#NAME}: Temperature is too high||`min(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoEnclosureTemperature, "{#NAME}"],{$HUAWEI.5300.TEMP.MAX.TIME})>{$HUAWEI.5300.TEMP.MAX.WARN}`|High||

### LLD rule FANs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FANs discovery|<p>Discovery of FANs</p>|SNMP agent|huawei.5300.fan.discovery|

### Item prototypes for FANs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN {#ID} on {#LOCATION}: Health status|<p>Health status of a fan. For details, see definition of Enum Values (HEALTH_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p>|SNMP agent|huawei.5300.v5[hwInfoFanHealthStatus, "{#ID}:{#LOCATION}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|FAN {#ID} on {#LOCATION}: Running status|<p>Operating status of a fan. For details, see definition of Enum Values (RUNNING_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p>|SNMP agent|huawei.5300.v5[hwInfoFanRunningStatus, "{#ID}:{#LOCATION}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for FANs discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Huawei OceanStor 5300 V5: FAN {#ID} on {#LOCATION}: Health status is not Normal||`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoFanHealthStatus, "{#ID}:{#LOCATION}"])<>1`|High||
|Huawei OceanStor 5300 V5: FAN {#ID} on {#LOCATION}: Running status is not Running||`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoFanRunningStatus, "{#ID}:{#LOCATION}"])<>2`|Average||

### LLD rule BBU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|BBU discovery|<p>Discovery of BBU</p>|SNMP agent|huawei.5300.bbu.discovery|

### Item prototypes for BBU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|BBU {#ID} on {#LOCATION}: Health status|<p>Health status of a BBU. For details, see definition of Enum Values (HEALTH_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p>|SNMP agent|huawei.5300.v5[hwInfoBBUHealthStatus, "{#ID}:{#LOCATION}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|BBU {#ID} on {#LOCATION}: Running status|<p>Running status of a BBU. For details, see definition of Enum Values (RUNNING_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p>|SNMP agent|huawei.5300.v5[hwInfoBBURunningStatus, "{#ID}:{#LOCATION}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for BBU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Huawei OceanStor 5300 V5: BBU {#ID} on {#LOCATION}: Health status is not Normal||`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoBBUHealthStatus, "{#ID}:{#LOCATION}"])<>1`|High||
|Huawei OceanStor 5300 V5: BBU {#ID} on {#LOCATION}: Running status is not Online||`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoBBURunningStatus, "{#ID}:{#LOCATION}"])<>2`|Average||

### LLD rule Disks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disks discovery|<p>Discovery of disks</p>|SNMP agent|huawei.5300.disks.discovery|

### Item prototypes for Disks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk {#MODEL} on {#LOCATION}: Health status|<p>Disk health status. For details, see definition of Enum Values (HEALTH_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p>|SNMP agent|huawei.5300.v5[hwInfoDiskHealthStatus, "{#ID}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Disk {#MODEL} on {#LOCATION}: Running status|<p>Disk running status. For details, see definition of Enum Values (RUNNING_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p>|SNMP agent|huawei.5300.v5[hwInfoDiskRunningStatus, "{#ID}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Disk {#MODEL} on {#LOCATION}: Temperature|<p>Disk temperature.</p>|SNMP agent|huawei.5300.v5[hwInfoDiskTemperature, "{#ID}"]|
|Disk {#MODEL} on {#LOCATION}: Health score|<p>Health score of a disk. If the value is 255, indicating invalid.</p>|SNMP agent|huawei.5300.v5[hwInfoDiskHealthMark, "{#ID}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Disks discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Huawei OceanStor 5300 V5: Disk {#MODEL} on {#LOCATION}: Health status is not Normal||`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoDiskHealthStatus, "{#ID}"])<>1`|High||
|Huawei OceanStor 5300 V5: Disk {#MODEL} on {#LOCATION}: Running status is not Online||`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoDiskRunningStatus, "{#ID}"])<>27`|Average||
|Huawei OceanStor 5300 V5: Disk {#MODEL} on {#LOCATION}: Temperature is too high||`min(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoDiskTemperature, "{#ID}"],{$HUAWEI.5300.DISK.TEMP.MAX.TIME})>{$HUAWEI.5300.DISK.TEMP.MAX.WARN:"{#MODEL}"}`|High||

### LLD rule Nodes performance discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Nodes performance discovery|<p>Discovery of nodes performance counters</p>|SNMP agent|huawei.5300.nodes.discovery|

### Item prototypes for Nodes performance discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node {#NODE}: CPU utilization|<p>CPU usage of the node {#NODE}.</p>|SNMP agent|huawei.5300.v5[hwPerfNodeCPUUsage, "{#NODE}"]|
|Node {#NODE}: Average I/O latency|<p>Average I/O latency of the node.</p>|SNMP agent|huawei.5300.v5[hwPerfNodeDelay, "{#NODE}"]|
|Node {#NODE}: Total I/O per second|<p>Total IOPS of the node.</p>|SNMP agent|huawei.5300.v5[hwPerfNodeTotalIOPS, "{#NODE}"]|
|Node {#NODE}: Read operations per second|<p>Read IOPS of the node.</p>|SNMP agent|huawei.5300.v5[hwPerfNodeReadIOPS, "{#NODE}"]|
|Node {#NODE}: Write operations per second|<p>Write IOPS of the node.</p>|SNMP agent|huawei.5300.v5[hwPerfNodeWriteIOPS, "{#NODE}"]|
|Node {#NODE}: Total traffic per second|<p>Total bandwidth for the node.</p>|SNMP agent|huawei.5300.v5[hwPerfNodeTotalTraffic, "{#NODE}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Node {#NODE}: Read traffic per second|<p>Read bandwidth for the node.</p>|SNMP agent|huawei.5300.v5[hwPerfNodeReadTraffic, "{#NODE}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Node {#NODE}: Write traffic per second|<p>Write bandwidth for the node.</p>|SNMP agent|huawei.5300.v5[hwPerfNodeWriteTraffic, "{#NODE}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li></ul>|

### Trigger prototypes for Nodes performance discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Huawei OceanStor 5300 V5: Node {#NODE}: High CPU utilization|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwPerfNodeCPUUsage, "{#NODE}"],5m)>{$CPU.UTIL.CRIT}`|Warning||
|Huawei OceanStor 5300 V5: Node {#NODE}: Average I/O latency is too high||`min(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwPerfNodeDelay, "{#NODE}"],{$HUAWEI.5300.NODE.IO.DELAY.MAX.TIME})>{$HUAWEI.5300.NODE.IO.DELAY.MAX.WARN}`|Warning||

### LLD rule LUNs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|LUNs discovery|<p>Discovery of LUNs</p>|SNMP agent|huawei.5300.lun.discovery|

### Item prototypes for LUNs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|LUN {#NAME}: Status|<p>Status of the LUN.</p>|SNMP agent|huawei.5300.v5[hwStorageLunStatus, "{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|LUN {#NAME}: Average total I/O latency|<p>Average I/O latency of the node in milliseconds.</p>|SNMP agent|huawei.5300.v5[hwPerfLunAverageIOResponseTime, "{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|LUN {#NAME}: Average read I/O latency|<p>Average read I/O response time in milliseconds.</p>|SNMP agent|huawei.5300.v5[hwPerfLunAverageReadIOLatency, "{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|LUN {#NAME}: Average write I/O latency|<p>Average write I/O response time in milliseconds.</p>|SNMP agent|huawei.5300.v5[hwPerfLunAverageWriteIOLatency, "{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|LUN {#NAME}: Total I/O per second|<p>Current IOPS of the LUN.</p>|SNMP agent|huawei.5300.v5[hwPerfLunTotalIOPS, "{#NAME}"]|
|LUN {#NAME}: Read operations per second|<p>Read IOPS of the node.</p>|SNMP agent|huawei.5300.v5[hwPerfLunReadIOPS, "{#NAME}"]|
|LUN {#NAME}: Write operations per second|<p>Write IOPS of the node.</p>|SNMP agent|huawei.5300.v5[hwPerfLunWriteIOPS, "{#NAME}"]|
|LUN {#NAME}: Total traffic per second|<p>Current total bandwidth for the LUN.</p>|SNMP agent|huawei.5300.v5[hwPerfLunTotalTraffic, "{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li></ul>|
|LUN {#NAME}: Read traffic per second|<p>Current read bandwidth for the LUN.</p>|SNMP agent|huawei.5300.v5[hwPerfLunReadTraffic, "{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li></ul>|
|LUN {#NAME}: Write traffic per second|<p>Current write bandwidth for the LUN.</p>|SNMP agent|huawei.5300.v5[hwPerfLunWriteTraffic, "{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li></ul>|
|LUN {#NAME}: Capacity|<p>Capacity of the LUN.</p>|SNMP agent|huawei.5300.v5[hwStorageLunCapacity, "{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for LUNs discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Huawei OceanStor 5300 V5: LUN {#NAME}: Status is not Normal||`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwStorageLunStatus, "{#NAME}"])<>1`|Average||
|Huawei OceanStor 5300 V5: LUN {#NAME}: Average I/O response time is too high||`min(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwPerfLunAverageIOResponseTime, "{#NAME}"],{$HUAWEI.5300.LUN.IO.TIME.MAX.TIME})>{$HUAWEI.5300.LUN.IO.TIME.MAX.WARN}`|Warning||

### LLD rule Storage pools discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage pools discovery|<p>Discovery of storage pools</p>|SNMP agent|huawei.5300.pool.discovery|

### Item prototypes for Storage pools discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Pool {#NAME}: Health status|<p>Health status of a storage pool. For details, see definition of Enum Values (HEALTH_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p>|SNMP agent|huawei.5300.v5[hwInfoStoragePoolHealthStatus, "{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Pool {#NAME}: Running status|<p>Operating status of a storage pool. For details, see definition of Enum Values (RUNNING_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p>|SNMP agent|huawei.5300.v5[hwInfoStoragePoolRunningStatus, "{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Pool {#NAME}: Capacity total|<p>Total capacity of a storage pool.</p>|SNMP agent|huawei.5300.v5[hwInfoStoragePoolTotalCapacity, "{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Pool {#NAME}: Capacity free|<p>Available capacity of a storage pool.</p>|SNMP agent|huawei.5300.v5[hwInfoStoragePoolFreeCapacity, "{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Pool {#NAME}: Capacity used|<p>Used capacity of a storage pool.</p>|SNMP agent|huawei.5300.v5[hwInfoStoragePoolSubscribedCapacity, "{#NAME}"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Pool {#NAME}: Capacity used percentage|<p>Used capacity of a storage pool in percents.</p>|Calculated|huawei.5300.v5[hwInfoStoragePoolFreeCapacityPct, "{#NAME}"]|

### Trigger prototypes for Storage pools discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Huawei OceanStor 5300 V5: Pool {#NAME}: Health status is not Normal||`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoStoragePoolHealthStatus, "{#NAME}"])<>1`|High||
|Huawei OceanStor 5300 V5: Pool {#NAME}: Running status is not Online||`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoStoragePoolRunningStatus, "{#NAME}"])<>27`|Average||
|Huawei OceanStor 5300 V5: Pool {#NAME}: Used capacity is too high||`min(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoStoragePoolFreeCapacityPct, "{#NAME}"],{$HUAWEI.5300.POOL.CAPACITY.THRESH.TIME})>{#THRESHOLD}`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

