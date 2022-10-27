
# Huawei OceanStor 5300 V5 by SNMP

## Overview

For Zabbix version: 6.2 and higher.
The template to monitor SAN Huawei OceanStor 5300 V5 by Zabbix SNMP agent.

This template was tested on:

- Huawei OceanStor 5300 V5

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/network_devices) for basic instructions.

1\. Create a host for Huawei OceanStor 5300 V5 with controller management IP as SNMPv2 interface.

2\. Link the template to the host.

3\. Customize macro values if needed.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>The critical threshold of the CPU utilization in %.</p> |`90` |
|{$HUAWEI.5300.DISK.TEMP.MAX.TIME} |<p>The time during which temperature of disk may exceed the threshold.</p> |`5m` |
|{$HUAWEI.5300.DISK.TEMP.MAX.WARN} |<p>Maximum temperature of disk. Can be used with {#MODEL} as context.</p> |`45` |
|{$HUAWEI.5300.LUN.IO.TIME.MAX.TIME} |<p>The time during which average I/O response time of LUN may exceed the threshold.</p> |`5m` |
|{$HUAWEI.5300.LUN.IO.TIME.MAX.WARN} |<p>Maximum average I/O response time of LUN in milliseconds.</p> |`100` |
|{$HUAWEI.5300.MEM.MAX.TIME} |<p>The time during which memory usage may exceed the threshold.</p> |`5m` |
|{$HUAWEI.5300.MEM.MAX.WARN} |<p>Maximum percentage of memory used</p> |`90` |
|{$HUAWEI.5300.NODE.IO.DELAY.MAX.TIME} |<p>The time during which average I/O latency of node may exceed the threshold.</p> |`5m` |
|{$HUAWEI.5300.NODE.IO.DELAY.MAX.WARN} |<p>Maximum average I/O latency of node in milliseconds.</p> |`20` |
|{$HUAWEI.5300.POOL.CAPACITY.THRESH.TIME} |<p>The time during which free capacity may exceed the {#THRESHOLD} from hwInfoStoragePoolFullThreshold.</p> |`5m` |
|{$HUAWEI.5300.TEMP.MAX.TIME} |<p>The time during which temperature of enclosure may exceed the threshold.</p> |`3m` |
|{$HUAWEI.5300.TEMP.MAX.WARN} |<p>Maximum temperature of enclosure</p> |`35` |
|{$ICMP_LOSS_WARN} |<p>-</p> |`20` |
|{$ICMP_RESPONSE_TIME_WARN} |<p>-</p> |`0.15` |
|{$SNMP.TIMEOUT} |<p>-</p> |`5m` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|BBU discovery |<p>Discovery of BBU</p> |SNMP |huawei.5300.bbu.discovery |
|Controllers discovery |<p>Discovery of controllers</p> |SNMP |huawei.5300.controllers.discovery |
|Disks discovery |<p>Discovery of disks</p> |SNMP |huawei.5300.disks.discovery |
|Enclosure discovery |<p>Discovery of enclosures</p> |SNMP |huawei.5300.enclosure.discovery |
|FANs discovery |<p>Discovery of FANs</p> |SNMP |huawei.5300.fan.discovery |
|LUNs discovery |<p>Discovery of LUNs</p> |SNMP |huawei.5300.lun.discovery |
|Nodes performance discovery |<p>Discovery of nodes performance counters</p> |SNMP |huawei.5300.nodes.discovery |
|Storage pools discovery |<p>Discovery of storage pools</p> |SNMP |huawei.5300.pool.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |Controller {#ID}: CPU utilization |<p>CPU usage of a controller {#ID}.</p> |SNMP |huawei.5300.v5[hwInfoControllerCPUUsage, "{#ID}"] |
|CPU |Node {#NODE}: CPU utilization |<p>CPU usage of the node {#NODE}.</p> |SNMP |huawei.5300.v5[hwPerfNodeCPUUsage, "{#NODE}"] |
|General |SNMP traps (fallback) |<p>The item is used to collect all SNMP traps unmatched by other snmptrap items</p> |SNMP_TRAP |snmptrap.fallback |
|General |System location |<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet, 3rd floor').  If the location is unknown, the value is the zero-length string.</p> |SNMP |system.location[sysLocation.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System contact details |<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</p> |SNMP |system.contact[sysContact.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System object ID |<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity.  This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p> |SNMP |system.objectid[sysObjectID.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System name |<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name.  If the name is unknown, the value is the zero-length string.</p> |SNMP |system.name<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |System description |<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p> |SNMP |system.descr[sysDescr.0]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|Huawei |OceanStor 5300 V5: Status |<p>System running status.</p> |SNMP |huawei.5300.v5[status]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Huawei |OceanStor 5300 V5: Version |<p>The device version.</p> |SNMP |huawei.5300.v5[version]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Huawei |OceanStor 5300 V5: Capacity total |<p>Total capacity of a device.</p> |SNMP |huawei.5300.v5[totalCapacity]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Huawei |OceanStor 5300 V5: Capacity used |<p>Used capacity of a device.</p> |SNMP |huawei.5300.v5[usedCapacity]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |
|Huawei |Controller {#ID}: Memory utilization |<p>Memory usage of a controller {#ID}.</p> |SNMP |huawei.5300.v5[hwInfoControllerMemoryUsage, "{#ID}"] |
|Huawei |Controller {#ID}: Health status |<p>Controller health status. For details, see definition of Enum Values (HEALTH_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p> |SNMP |huawei.5300.v5[hwInfoControllerHealthStatus, "{#ID}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Huawei |Controller {#ID}: Running status |<p>Controller running status. For details, see definition of Enum Values (RUNNING_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p> |SNMP |huawei.5300.v5[hwInfoControllerRunningStatus, "{#ID}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Huawei |Controller {#ID}: Role |<p>Controller role..</p> |SNMP |huawei.5300.v5[hwInfoControllerRole, "{#ID}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Huawei |Enclosure {#NAME}: Health status |<p>Enclosure health status. For details, see definition of Enum Values (HEALTH_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p> |SNMP |huawei.5300.v5[hwInfoEnclosureHealthStatus, "{#NAME}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Huawei |Enclosure {#NAME}: Running status |<p>Enclosure running status. For details, see definition of Enum Values (RUNNING_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p> |SNMP |huawei.5300.v5[hwInfoEnclosureRunningStatus, "{#NAME}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Huawei |Enclosure {#NAME}: Temperature |<p>Enclosure temperature.</p> |SNMP |huawei.5300.v5[hwInfoEnclosureTemperature, "{#NAME}"] |
|Huawei |FAN {#ID} on {#LOCATION}: Health status |<p>Health status of a fan. For details, see definition of Enum Values (HEALTH_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p> |SNMP |huawei.5300.v5[hwInfoFanHealthStatus, "{#ID}:{#LOCATION}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Huawei |FAN {#ID} on {#LOCATION}: Running status |<p>Operating status of a fan. For details, see definition of Enum Values (RUNNING_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p> |SNMP |huawei.5300.v5[hwInfoFanRunningStatus, "{#ID}:{#LOCATION}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Huawei |BBU {#ID} on {#LOCATION}: Health status |<p>Health status of a BBU. For details, see definition of Enum Values (HEALTH_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p> |SNMP |huawei.5300.v5[hwInfoBBUHealthStatus, "{#ID}:{#LOCATION}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Huawei |BBU {#ID} on {#LOCATION}: Running status |<p>Running status of a BBU. For details, see definition of Enum Values (RUNNING_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p> |SNMP |huawei.5300.v5[hwInfoBBURunningStatus, "{#ID}:{#LOCATION}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Huawei |Disk {#MODEL} on {#LOCATION}: Health status |<p>Disk health status. For details, see definition of Enum Values (HEALTH_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p> |SNMP |huawei.5300.v5[hwInfoDiskHealthStatus, "{#ID}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Huawei |Disk {#MODEL} on {#LOCATION}: Running status |<p>Disk running status. For details, see definition of Enum Values (RUNNING_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p> |SNMP |huawei.5300.v5[hwInfoDiskRunningStatus, "{#ID}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Huawei |Disk {#MODEL} on {#LOCATION}: Temperature |<p>Disk temperature.</p> |SNMP |huawei.5300.v5[hwInfoDiskTemperature, "{#ID}"] |
|Huawei |Disk {#MODEL} on {#LOCATION}: Health score |<p>Health score of a disk. If the value is 255, indicating invalid.</p> |SNMP |huawei.5300.v5[hwInfoDiskHealthMark, "{#ID}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Huawei |Node {#NODE}: Average I/O latency |<p>Average I/O latency of the node.</p> |SNMP |huawei.5300.v5[hwPerfNodeDelay, "{#NODE}"] |
|Huawei |Node {#NODE}: Total I/O per second |<p>Total IOPS of the node.</p> |SNMP |huawei.5300.v5[hwPerfNodeTotalIOPS, "{#NODE}"] |
|Huawei |Node {#NODE}: Read operations per second |<p>Read IOPS of the node.</p> |SNMP |huawei.5300.v5[hwPerfNodeReadIOPS, "{#NODE}"] |
|Huawei |Node {#NODE}: Write operations per second |<p>Write IOPS of the node.</p> |SNMP |huawei.5300.v5[hwPerfNodeWriteIOPS, "{#NODE}"] |
|Huawei |Node {#NODE}: Total traffic per second |<p>Total bandwidth for the node.</p> |SNMP |huawei.5300.v5[hwPerfNodeTotalTraffic, "{#NODE}"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |
|Huawei |Node {#NODE}: Read traffic per second |<p>Read bandwidth for the node.</p> |SNMP |huawei.5300.v5[hwPerfNodeReadTraffic, "{#NODE}"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |
|Huawei |Node {#NODE}: Write traffic per second |<p>Write bandwidth for the node.</p> |SNMP |huawei.5300.v5[hwPerfNodeWriteTraffic, "{#NODE}"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |
|Huawei |LUN {#NAME}: Status |<p>Status of the LUN.</p> |SNMP |huawei.5300.v5[hwStorageLunStatus, "{#NAME}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Huawei |LUN {#NAME}: Average total I/O latency |<p>Average I/O latency of the node in milliseconds.</p> |SNMP |huawei.5300.v5[hwPerfLunAverageIOResponseTime, "{#NAME}"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Huawei |LUN {#NAME}: Average read I/O latency |<p>Average read I/O response time in milliseconds.</p> |SNMP |huawei.5300.v5[hwPerfLunAverageReadIOLatency, "{#NAME}"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Huawei |LUN {#NAME}: Average write I/O latency |<p>Average write I/O response time in milliseconds.</p> |SNMP |huawei.5300.v5[hwPerfLunAverageWriteIOLatency, "{#NAME}"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Huawei |LUN {#NAME}: Total I/O per second |<p>Current IOPS of the LUN.</p> |SNMP |huawei.5300.v5[hwPerfLunTotalIOPS, "{#NAME}"] |
|Huawei |LUN {#NAME}: Read operations per second |<p>Read IOPS of the node.</p> |SNMP |huawei.5300.v5[hwPerfLunReadIOPS, "{#NAME}"] |
|Huawei |LUN {#NAME}: Write operations per second |<p>Write IOPS of the node.</p> |SNMP |huawei.5300.v5[hwPerfLunWriteIOPS, "{#NAME}"] |
|Huawei |LUN {#NAME}: Total traffic per second |<p>Current total bandwidth for the LUN.</p> |SNMP |huawei.5300.v5[hwPerfLunTotalTraffic, "{#NAME}"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |
|Huawei |LUN {#NAME}: Read traffic per second |<p>Current read bandwidth for the LUN.</p> |SNMP |huawei.5300.v5[hwPerfLunReadTraffic, "{#NAME}"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |
|Huawei |LUN {#NAME}: Write traffic per second |<p>Current write bandwidth for the LUN.</p> |SNMP |huawei.5300.v5[hwPerfLunWriteTraffic, "{#NAME}"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |
|Huawei |LUN {#NAME}: Capacity |<p>Capacity of the LUN.</p> |SNMP |huawei.5300.v5[hwStorageLunCapacity, "{#NAME}"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Huawei |Pool {#NAME}: Health status |<p>Health status of a storage pool. For details, see definition of Enum Values (HEALTH_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p> |SNMP |huawei.5300.v5[hwInfoStoragePoolHealthStatus, "{#NAME}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Huawei |Pool {#NAME}: Running status |<p>Operating status of a storage pool. For details, see definition of Enum Values (RUNNING_STATUS_E).</p><p>https://support.huawei.com/enterprise/en/centralized-storage/oceanstor-5300-v5-pid-22462029?category=reference-guides&subcategory=mib-reference</p> |SNMP |huawei.5300.v5[hwInfoStoragePoolRunningStatus, "{#NAME}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Huawei |Pool {#NAME}: Capacity total |<p>Total capacity of a storage pool.</p> |SNMP |huawei.5300.v5[hwInfoStoragePoolTotalCapacity, "{#NAME}"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Huawei |Pool {#NAME}: Capacity free |<p>Available capacity of a storage pool.</p> |SNMP |huawei.5300.v5[hwInfoStoragePoolFreeCapacity, "{#NAME}"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |
|Huawei |Pool {#NAME}: Capacity used |<p>Used capacity of a storage pool.</p> |SNMP |huawei.5300.v5[hwInfoStoragePoolSubscribedCapacity, "{#NAME}"]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |
|Huawei |Pool {#NAME}: Capacity used percentage |<p>Used capacity of a storage pool in percents.</p> |CALCULATED |huawei.5300.v5[hwInfoStoragePoolFreeCapacityPct, "{#NAME}"]<p>**Expression**:</p>`last(//huawei.5300.v5[hwInfoStoragePoolSubscribedCapacity, "{#NAME}"])/last(//huawei.5300.v5[hwInfoStoragePoolTotalCapacity, "{#NAME}"])*100` |
|Status |Uptime (network) |<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p> |SNMP |system.net.uptime[sysUpTime.0]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Status |Uptime (hardware) |<p>MIB: HOST-RESOURCES-MIB</p><p>The amount of time since this host was last initialized. Note that this is different from sysUpTime in the SNMPv2-MIB [RFC1907] because sysUpTime is the uptime of the network management portion of the system.</p> |SNMP |system.hw.uptime[hrSystemUptime.0]<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- MULTIPLIER: `0.01`</p> |
|Status |SNMP agent availability |<p>Availability of SNMP checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible value:</p><p>0 - not available</p><p>1 - available</p><p>2 - unknown</p> |INTERNAL |zabbix[host,snmp,available] |
|Status |ICMP ping |<p>-</p> |SIMPLE |icmpping |
|Status |ICMP loss |<p>-</p> |SIMPLE |icmppingloss |
|Status |ICMP response time |<p>-</p> |SIMPLE |icmppingsec |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Controller {#ID}: High CPU utilization |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoControllerCPUUsage, "{#ID}"],5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|Node {#NODE}: High CPU utilization |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwPerfNodeCPUUsage, "{#NODE}"],5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|System name has changed |<p>System name has changed. Ack to close.</p> |`last(/Huawei OceanStor 5300 V5 by SNMP/system.name,#1)<>last(/Huawei OceanStor 5300 V5 by SNMP/system.name,#2) and length(last(/Huawei OceanStor 5300 V5 by SNMP/system.name))>0` |INFO |<p>Manual close: YES</p> |
|OceanStor 5300 V5: Storage version has been changed |<p>OceanStor 5300 V5 version has changed. Ack to close.</p> |`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[version],#1)<>last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[version],#2) and length(last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[version]))>0` |INFO |<p>Manual close: YES</p> |
|Controller {#ID}: Memory usage is too high |<p>-</p> |`min(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoControllerMemoryUsage, "{#ID}"],{$HUAWEI.5300.MEM.MAX.TIME})>{$HUAWEI.5300.MEM.MAX.WARN}` |AVERAGE | |
|Controller {#ID}: Health status is not Normal |<p>-</p> |`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoControllerHealthStatus, "{#ID}"])<>1` |HIGH | |
|Controller {#ID}: Running status is not Online |<p>-</p> |`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoControllerRunningStatus, "{#ID}"])<>27` |AVERAGE | |
|Controller {#ID}: Role has been changed |<p>-</p> |`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoControllerRole, "{#ID}"],#1)<>last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoControllerRole, "{#ID}"],#2)` |WARNING |<p>Manual close: YES</p> |
|Enclosure {#NAME}: Health status is not Normal |<p>-</p> |`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoEnclosureHealthStatus, "{#NAME}"])<>1` |HIGH | |
|Enclosure {#NAME}: Running status is not Online |<p>-</p> |`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoEnclosureRunningStatus, "{#NAME}"])<>27` |AVERAGE | |
|Enclosure {#NAME}: Temperature is too high |<p>-</p> |`min(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoEnclosureTemperature, "{#NAME}"],{$HUAWEI.5300.TEMP.MAX.TIME})>{$HUAWEI.5300.TEMP.MAX.WARN}` |HIGH | |
|FAN {#ID} on {#LOCATION}: Health status is not Normal |<p>-</p> |`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoFanHealthStatus, "{#ID}:{#LOCATION}"])<>1` |HIGH | |
|FAN {#ID} on {#LOCATION}: Running status is not Running |<p>-</p> |`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoFanRunningStatus, "{#ID}:{#LOCATION}"])<>2` |AVERAGE | |
|BBU {#ID} on {#LOCATION}: Health status is not Normal |<p>-</p> |`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoBBUHealthStatus, "{#ID}:{#LOCATION}"])<>1` |HIGH | |
|BBU {#ID} on {#LOCATION}: Running status is not Online |<p>-</p> |`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoBBURunningStatus, "{#ID}:{#LOCATION}"])<>2` |AVERAGE | |
|Disk {#MODEL} on {#LOCATION}: Health status is not Normal |<p>-</p> |`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoDiskHealthStatus, "{#ID}"])<>1` |HIGH | |
|Disk {#MODEL} on {#LOCATION}: Running status is not Online |<p>-</p> |`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoDiskRunningStatus, "{#ID}"])<>27` |AVERAGE | |
|Disk {#MODEL} on {#LOCATION}: Temperature is too high |<p>-</p> |`min(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoDiskTemperature, "{#ID}"],{$HUAWEI.5300.DISK.TEMP.MAX.TIME})>{$HUAWEI.5300.DISK.TEMP.MAX.WARN:"{#MODEL}"}` |HIGH | |
|Node {#NODE}: Average I/O latency is too high |<p>-</p> |`min(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwPerfNodeDelay, "{#NODE}"],{$HUAWEI.5300.NODE.IO.DELAY.MAX.TIME})>{$HUAWEI.5300.NODE.IO.DELAY.MAX.WARN}` |WARNING | |
|LUN {#NAME}: Status is not Normal |<p>-</p> |`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwStorageLunStatus, "{#NAME}"])<>1` |AVERAGE | |
|LUN {#NAME}: Average I/O response time is too high |<p>-</p> |`min(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwPerfLunAverageIOResponseTime, "{#NAME}"],{$HUAWEI.5300.LUN.IO.TIME.MAX.TIME})>{$HUAWEI.5300.LUN.IO.TIME.MAX.WARN}` |WARNING | |
|Pool {#NAME}: Health status is not Normal |<p>-</p> |`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoStoragePoolHealthStatus, "{#NAME}"])<>1` |HIGH | |
|Pool {#NAME}: Running status is not Online |<p>-</p> |`last(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoStoragePoolRunningStatus, "{#NAME}"])<>27` |AVERAGE | |
|Pool {#NAME}: Used capacity is too high |<p>-</p> |`min(/Huawei OceanStor 5300 V5 by SNMP/huawei.5300.v5[hwInfoStoragePoolFreeCapacityPct, "{#NAME}"],{$HUAWEI.5300.POOL.CAPACITY.THRESH.TIME})>{#THRESHOLD}` |AVERAGE | |
|Host has been restarted |<p>Uptime is less than 10 minutes.</p> |`(last(/Huawei OceanStor 5300 V5 by SNMP/system.hw.uptime[hrSystemUptime.0])>0 and last(/Huawei OceanStor 5300 V5 by SNMP/system.hw.uptime[hrSystemUptime.0])<10m) or (last(/Huawei OceanStor 5300 V5 by SNMP/system.hw.uptime[hrSystemUptime.0])=0 and last(/Huawei OceanStor 5300 V5 by SNMP/system.net.uptime[sysUpTime.0])<10m)` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- No SNMP data collection</p> |
|No SNMP data collection |<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p> |`max(/Huawei OceanStor 5300 V5 by SNMP/zabbix[host,snmp,available],{$SNMP.TIMEOUT})=0` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|Unavailable by ICMP ping |<p>Last three attempts returned timeout.  Please check device connectivity.</p> |`max(/Huawei OceanStor 5300 V5 by SNMP/icmpping,#3)=0` |HIGH | |
|High ICMP ping loss |<p>-</p> |`min(/Huawei OceanStor 5300 V5 by SNMP/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/Huawei OceanStor 5300 V5 by SNMP/icmppingloss,5m)<100` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|High ICMP ping response time |<p>-</p> |`avg(/Huawei OceanStor 5300 V5 by SNMP/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}` |WARNING |<p>**Depends on**:</p><p>- High ICMP ping loss</p><p>- Unavailable by ICMP ping</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/418855-discussion-thread-for-official-zabbix-template-huawei-oceanstor).

