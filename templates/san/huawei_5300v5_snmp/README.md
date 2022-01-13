
# Huawei OceanStor 5300 V5 SNMP

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor SAN Huawei OceanStor 5300 V5 by Zabbix SNMP agent.




This template was tested on:

- Huawei OceanStor 5300 V5

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/network_devices) for basic instructions.

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
|{$HUAWEI.5300.NODE.IO.DELAY.MAX.TIME} |<p>The time during which verage I/O latency of node may exceed the threshold.</p> |`5m` |
|{$HUAWEI.5300.NODE.IO.DELAY.MAX.WARN} |<p>Maximum average I/O latency of node in milliseconds.</p> |`20` |
|{$HUAWEI.5300.POOL.CAPACITY.THRESH.TIME} |<p>The time during which free capacity may exceed the {#THRESHOLD} from hwInfoStoragePoolFullThreshold.</p> |`5m` |
|{$HUAWEI.5300.TEMP.MAX.TIME} |<p>The time during which temperature of enclosure may exceed the threshold.</p> |`3m` |
|{$HUAWEI.5300.TEMP.MAX.WARN} |<p>Maximum temperature of enclosure</p> |`35` |

## Template links

|Name|
|----|
|Generic SNMP |

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Controllers discovery |<p>Discovery of controllers</p> |SNMP |huawei.5300.controllers.discovery |
|Enclosure discovery |<p>Discovery of enclosures</p> |SNMP |huawei.5300.enclosure.discovery |
|FANs discovery |<p>Discovery of FANs</p> |SNMP |huawei.5300.fan.discovery |
|BBU discovery |<p>Discovery of BBU</p> |SNMP |huawei.5300.bbu.discovery |
|Disks discovery |<p>Discovery of disks</p> |SNMP |huawei.5300.disks.discovery |
|Nodes performance discovery |<p>Discovery of nodes performance counters</p> |SNMP |huawei.5300.nodes.discovery |
|LUNs discovery |<p>Discovery of LUNs</p> |SNMP |huawei.5300.lun.discovery |
|Storage pools discovery |<p>Discovery of storage pools</p> |SNMP |huawei.5300.pool.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |Controller {#ID}: CPU utilization |<p>CPU usage of a controller {#ID}.</p> |SNMP |huawei.5300.v5[hwInfoControllerCPUUsage, "{#ID}"] |
|CPU |Node {#NODE}: CPU utilization |<p>CPU usage of the node {#NODE}.</p> |SNMP |huawei.5300.v5[hwPerfNodeCPUUsage, "{#NODE}"] |
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

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Controller {#ID}: High CPU utilization (over {$CPU.UTIL.CRIT}% for 5m) |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwInfoControllerCPUUsage, "{#ID}"],5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|Node {#NODE}: High CPU utilization (over {$CPU.UTIL.CRIT}% for 5m) |<p>CPU utilization is too high. The system might be slow to respond.</p> |`min(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwPerfNodeCPUUsage, "{#NODE}"],5m)>{$CPU.UTIL.CRIT}` |WARNING | |
|OceanStor 5300 V5: Storage version has been changed |<p>OceanStor 5300 V5 version has changed. Ack to close.</p> |`last(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[version],#1)<>last(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[version],#2) and length(last(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[version]))>0` |INFO |<p>Manual close: YES</p> |
|Controller {#ID}: Memory usage is too high (over {$HUAWEI.5300.MEM.MAX.WARN} for {$HUAWEI.5300.MEM.MAX.TIME}) |<p>-</p> |`min(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwInfoControllerMemoryUsage, "{#ID}"],{$HUAWEI.5300.MEM.MAX.TIME})>{$HUAWEI.5300.MEM.MAX.WARN}` |AVERAGE | |
|Controller {#ID}: Health status is not Normal |<p>-</p> |`last(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwInfoControllerHealthStatus, "{#ID}"])<>1` |HIGH | |
|Controller {#ID}: Running status is not Online |<p>-</p> |`last(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwInfoControllerRunningStatus, "{#ID}"])<>27` |AVERAGE | |
|Controller {#ID}: Role has been changed |<p>-</p> |`last(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwInfoControllerRole, "{#ID}"],#1)<>last(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwInfoControllerRole, "{#ID}"],#2)` |WARNING |<p>Manual close: YES</p> |
|Enclosure {#NAME}: Health status is not Normal |<p>-</p> |`last(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwInfoEnclosureHealthStatus, "{#NAME}"])<>1` |HIGH | |
|Enclosure {#NAME}: Running status is not Online |<p>-</p> |`last(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwInfoEnclosureRunningStatus, "{#NAME}"])<>27` |AVERAGE | |
|Enclosure {#NAME}: Temperature is too high (over {$HUAWEI.5300.TEMP.MAX.WARN} for {$HUAWEI.5300.TEMP.MAX.TIME}) |<p>-</p> |`min(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwInfoEnclosureTemperature, "{#NAME}"],{$HUAWEI.5300.TEMP.MAX.TIME})>{$HUAWEI.5300.TEMP.MAX.WARN}` |HIGH | |
|FAN {#ID} on {#LOCATION}: Health status is not Normal |<p>-</p> |`last(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwInfoFanHealthStatus, "{#ID}:{#LOCATION}"])<>1` |HIGH | |
|FAN {#ID} on {#LOCATION}: Running status is not Running |<p>-</p> |`last(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwInfoFanRunningStatus, "{#ID}:{#LOCATION}"])<>2` |AVERAGE | |
|BBU {#ID} on {#LOCATION}: Health status is not Normal |<p>-</p> |`last(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwInfoBBUHealthStatus, "{#ID}:{#LOCATION}"])<>1` |HIGH | |
|BBU {#ID} on {#LOCATION}: Running status is not Online |<p>-</p> |`last(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwInfoBBURunningStatus, "{#ID}:{#LOCATION}"])<>2` |AVERAGE | |
|Disk {#MODEL} on {#LOCATION}: Health status is not Normal |<p>-</p> |`last(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwInfoDiskHealthStatus, "{#ID}"])<>1` |HIGH | |
|Disk {#MODEL} on {#LOCATION}: Running status is not Online |<p>-</p> |`last(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwInfoDiskRunningStatus, "{#ID}"])<>27` |AVERAGE | |
|Disk {#MODEL} on {#LOCATION}: Temperature is too high (over {$HUAWEI.5300.DISK.TEMP.MAX.WARN:"{#MODEL}"} for {$HUAWEI.5300.DISK.TEMP.MAX.TIME}) |<p>-</p> |`min(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwInfoDiskTemperature, "{#ID}"],{$HUAWEI.5300.DISK.TEMP.MAX.TIME})>{$HUAWEI.5300.DISK.TEMP.MAX.WARN:"{#MODEL}"}` |HIGH | |
|Node {#NODE}: Average I/O latency is too high (over {$HUAWEI.5300.NODE.IO.DELAY.MAX.WARN}ms for {$HUAWEI.5300.NODE.IO.DELAY.MAX.TIME}) |<p>-</p> |`min(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwPerfNodeDelay, "{#NODE}"],{$HUAWEI.5300.NODE.IO.DELAY.MAX.TIME})>{$HUAWEI.5300.NODE.IO.DELAY.MAX.WARN}` |WARNING | |
|LUN {#NAME}: Status is not Normal |<p>-</p> |`last(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwStorageLunStatus, "{#NAME}"])<>1` |AVERAGE | |
|LUN {#NAME}: Average I/O response time is too high (over {$HUAWEI.5300.LUN.IO.TIME.MAX.WARN}ms for {$HUAWEI.5300.LUN.IO.TIME.MAX.TIME}) |<p>-</p> |`min(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwPerfLunAverageIOResponseTime, "{#NAME}"],{$HUAWEI.5300.LUN.IO.TIME.MAX.TIME})>{$HUAWEI.5300.LUN.IO.TIME.MAX.WARN}` |WARNING | |
|Pool {#NAME}: Health status is not Normal |<p>-</p> |`last(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwInfoStoragePoolHealthStatus, "{#NAME}"])<>1` |HIGH | |
|Pool {#NAME}: Running status is not Online |<p>-</p> |`last(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwInfoStoragePoolRunningStatus, "{#NAME}"])<>27` |AVERAGE | |
|Pool {#NAME}: Used capacity is too high (over {#THRESHOLD}%) |<p>-</p> |`min(/Huawei OceanStor 5300 V5 SNMP/huawei.5300.v5[hwInfoStoragePoolFreeCapacityPct, "{#NAME}"],{$HUAWEI.5300.POOL.CAPACITY.THRESH.TIME})>{#THRESHOLD}` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide a feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/418855-discussion-thread-for-official-zabbix-template-huawei-oceanstor).

