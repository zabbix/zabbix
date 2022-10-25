
# HPE MSA 2040 Storage by HTTP

## Overview

For Zabbix version: 6.2 and higher  
The template to monitor HPE MSA 2040 by HTTP.
It works without any external scripts and uses the script item.


This template was tested on:

- HPE MSA 2040 Storage

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create user "zabbix" with monitor role on the storage.
2. Link the template to a host.
3. Configure {$HPE.MSA.API.PASSWORD} and an interface with address through which API is accessible.
4. Change {$HPE.MSA.API.SCHEME} and {$HPE.MSA.API.PORT} macros if needed.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$HPE.MSA.API.PASSWORD} |<p>Specify password for API.</p> |`` |
|{$HPE.MSA.API.PORT} |<p>Connection port for API.</p> |`443` |
|{$HPE.MSA.API.SCHEME} |<p>Connection scheme for API.</p> |`https` |
|{$HPE.MSA.API.USERNAME} |<p>Specify user name for API.</p> |`zabbix` |
|{$HPE.MSA.CONTROLLER.CPU.UTIL.CRIT} |<p>The critical threshold of the CPU utilization in %.</p> |`90` |
|{$HPE.MSA.DATA.TIMEOUT} |<p>Response timeout for API.</p> |`30s` |
|{$HPE.MSA.DISKS.GROUP.PUSED.MAX.CRIT} |<p>The critical threshold of the disk group space utilization in %.</p> |`90` |
|{$HPE.MSA.DISKS.GROUP.PUSED.MAX.WARN} |<p>The warning threshold of the disk group space utilization in %.</p> |`80` |
|{$HPE.MSA.POOL.PUSED.MAX.CRIT} |<p>The critical threshold of the pool space utilization in %.</p> |`90` |
|{$HPE.MSA.POOL.PUSED.MAX.WARN} |<p>The warning threshold of the pool space utilization in %.</p> |`80` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Controllers discovery |<p>Discover controllers.</p> |DEPENDENT |hpe.msa.controllers.discovery |
|Disk groups discovery |<p>Discover disk groups.</p> |DEPENDENT |hpe.msa.disks.groups.discovery |
|Disks discovery |<p>Discover disks.</p> |DEPENDENT |hpe.msa.disks.discovery<p>**Overrides:**</p><p>SSD life left<br> - {#TYPE} MATCHES_REGEX `8`<br>  - ITEM_PROTOTYPE REGEXP `SSD life left`<br>  - DISCOVER</p> |
|Enclosures discovery |<p>Discover enclosures.</p> |DEPENDENT |hpe.msa.enclosures.discovery |
|Fans discovery |<p>Discover fans.</p> |DEPENDENT |hpe.msa.fans.discovery |
|FRU discovery |<p>Discover FRU.</p> |DEPENDENT |hpe.msa.frus.discovery<p>**Filter**:</p> <p>- {#TYPE} NOT_MATCHES_REGEX `^(POWER_SUPPLY|RAID_IOM|CHASSIS_MIDPLANE)$`</p> |
|Pools discovery |<p>Discover pools.</p> |DEPENDENT |hpe.msa.pools.discovery |
|Ports discovery |<p>Discover ports.</p> |DEPENDENT |hpe.msa.ports.discovery |
|Power supplies discovery |<p>Discover power supplies.</p> |DEPENDENT |hpe.msa.power_supplies.discovery |
|Volumes discovery |<p>Discover volumes.</p> |DEPENDENT |hpe.msa.volumes.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|HPE |Get system |<p>The system data.</p> |DEPENDENT |hpe.msa.get.system<p>**Preprocessing**:</p><p>- JSONPATH: `$.system[0]`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|HPE |Get FRU |<p>FRU data.</p> |DEPENDENT |hpe.msa.get.fru<p>**Preprocessing**:</p><p>- JSONPATH: `$.['frus']`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|HPE |Get fans |<p>Fans data.</p> |DEPENDENT |hpe.msa.get.fans<p>**Preprocessing**:</p><p>- JSONPATH: `$.['fans']`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|HPE |Get disks |<p>Disks data.</p> |DEPENDENT |hpe.msa.get.disks<p>**Preprocessing**:</p><p>- JSONPATH: `$.['disks']`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|HPE |Get enclosures |<p>Enclosures data.</p> |DEPENDENT |hpe.msa.get.enclosures<p>**Preprocessing**:</p><p>- JSONPATH: `$.['enclosures']`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|HPE |Get ports |<p>Ports data.</p> |DEPENDENT |hpe.msa.get.ports<p>**Preprocessing**:</p><p>- JSONPATH: `$.['ports']`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|HPE |Get power supplies |<p>Power supplies data.</p> |DEPENDENT |hpe.msa.get.power_supplies<p>**Preprocessing**:</p><p>- JSONPATH: `$.['power-supplies']`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|HPE |Get pools |<p>Pools data.</p> |DEPENDENT |hpe.msa.get.pools<p>**Preprocessing**:</p><p>- JSONPATH: `$.['pools']`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|HPE |Get controllers |<p>Controllers data.</p> |DEPENDENT |hpe.msa.get.controllers<p>**Preprocessing**:</p><p>- JSONPATH: `$.['controllers']`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|HPE |Get controller statistics |<p>Controllers statistics data.</p> |DEPENDENT |hpe.msa.get.controller_statistics<p>**Preprocessing**:</p><p>- JSONPATH: `$.['controller-statistics']`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|HPE |Get disk groups |<p>Disk groups data.</p> |DEPENDENT |hpe.msa.get.disks.groups<p>**Preprocessing**:</p><p>- JSONPATH: `$.['disk-groups']`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|HPE |Get disk group statistics |<p>Disk groups statistics data.</p> |DEPENDENT |hpe.msa.disks.get.groups.statistics<p>**Preprocessing**:</p><p>- JSONPATH: `$.['disk-group-statistics']`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|HPE |Get volumes |<p>Volumes data.</p> |DEPENDENT |hpe.msa.get.volumes<p>**Preprocessing**:</p><p>- JSONPATH: `$.['volumes']`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|HPE |Get volume statistics |<p>Volumes statistics data.</p> |DEPENDENT |hpe.msa.get.volumes.statistics<p>**Preprocessing**:</p><p>- JSONPATH: `$.['volume-statistics']`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|HPE |Get method errors |<p>A list of method errors from API requests.</p> |DEPENDENT |hpe.msa.get.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.['errors']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Product ID |<p>The product model identifier.</p> |DEPENDENT |hpe.msa.system.product_id<p>**Preprocessing**:</p><p>- JSONPATH: `$.['product-id']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |System contact |<p>The name of the person who administers the system.</p> |DEPENDENT |hpe.msa.system.contact<p>**Preprocessing**:</p><p>- JSONPATH: `$.['system-contact']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |System information |<p>A brief description of what the system is used for or how it is configured.</p> |DEPENDENT |hpe.msa.system.info<p>**Preprocessing**:</p><p>- JSONPATH: `$.['system-information']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |System location |<p>The location of the system.</p> |DEPENDENT |hpe.msa.system.location<p>**Preprocessing**:</p><p>- JSONPATH: `$.['system-location']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |System name |<p>The name of the storage system.</p> |DEPENDENT |hpe.msa.system.name<p>**Preprocessing**:</p><p>- JSONPATH: `$.['system-name']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Vendor name |<p>The vendor name.</p> |DEPENDENT |hpe.msa.system.vendor_name<p>**Preprocessing**:</p><p>- JSONPATH: `$.['vendor-name']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |System health |<p>System health status.</p> |DEPENDENT |hpe.msa.system.health<p>**Preprocessing**:</p><p>- JSONPATH: `$.['health-numeric']`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 4`</p> |
|HPE |HPE MSA: Service ping |<p>Check if HTTP/HTTPS service accepts TCP connections.</p> |SIMPLE |net.tcp.service["{$HPE.MSA.API.SCHEME}","{HOST.CONN}","{$HPE.MSA.API.PORT}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HPE |Controller [{#CONTROLLER.ID}]: Get data |<p>The discovered controller data.</p> |DEPENDENT |hpe.msa.get.controllers["{#CONTROLLER.ID}",data]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@['durable-id'] == "{#DURABLE.ID}")].first()`</p> |
|HPE |Controller [{#CONTROLLER.ID}]: Get statistics data |<p>The discovered controller statistics data.</p> |DEPENDENT |hpe.msa.get.controller_statistics["{#CONTROLLER.ID}",data]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@['durable-id'] == "{#DURABLE.ID}")].first()`</p> |
|HPE |Controller [{#CONTROLLER.ID}]: Firmware version |<p>Storage controller firmware version.</p> |DEPENDENT |hpe.msa.controllers["{#CONTROLLER.ID}",firmware]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['sc-fw']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Controller [{#CONTROLLER.ID}]: Part number |<p>Part number of the controller.</p> |DEPENDENT |hpe.msa.controllers["{#CONTROLLER.ID}",part_number]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['part-number']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Controller [{#CONTROLLER.ID}]: Serial number |<p>Storage controller serial number.</p> |DEPENDENT |hpe.msa.controllers["{#CONTROLLER.ID}",serial_number]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['serial-number']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Controller [{#CONTROLLER.ID}]: Health |<p>Controller health status.</p> |DEPENDENT |hpe.msa.controllers["{#CONTROLLER.ID}",health]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['health-numeric']`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 4`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HPE |Controller [{#CONTROLLER.ID}]: Status |<p>Storage controller status.</p> |DEPENDENT |hpe.msa.controllers["{#CONTROLLER.ID}",status]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['status-numeric']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Controller [{#CONTROLLER.ID}]: Disks |<p>Number of disks in the storage system.</p> |DEPENDENT |hpe.msa.controllers["{#CONTROLLER.ID}",disks]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['disks']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Controller [{#CONTROLLER.ID}]: Pools |<p>Number of pools in the storage system.</p> |DEPENDENT |hpe.msa.controllers["{#CONTROLLER.ID}",pools]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['number-of-storage-pools']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Controller [{#CONTROLLER.ID}]: Disk groups |<p>Number of disk groups in the storage system.</p> |DEPENDENT |hpe.msa.controllers["{#CONTROLLER.ID}",disk_groups]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['virtual-disks']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Controller [{#CONTROLLER.ID}]: IP address |<p>Controller network port IP address.</p> |DEPENDENT |hpe.msa.controllers["{#CONTROLLER.ID}",ip_address]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['ip-address']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Controller [{#CONTROLLER.ID}]: Cache memory size |<p>Controller cache memory size.</p> |DEPENDENT |hpe.msa.controllers.cache["{#CONTROLLER.ID}",total]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['cache-memory-size']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p><p>- MULTIPLIER: `1048576`</p> |
|HPE |Controller [{#CONTROLLER.ID}]: Cache: Write utilization |<p>Percentage of write cache in use, from 0 to 100.</p> |DEPENDENT |hpe.msa.controllers.cache.write["{#CONTROLLER.ID}",util]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['write-cache-used']`</p> |
|HPE |Controller [{#CONTROLLER.ID}]: Cache: Read hits, rate |<p>For the controller that owns the volume, the number of times the block to be read is found in cache per second.</p> |DEPENDENT |hpe.msa.controllers.cache.read.hits["{#CONTROLLER.ID}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['read-cache-hits']`</p><p>- CHANGE_PER_SECOND</p> |
|HPE |Controller [{#CONTROLLER.ID}]: Cache: Read misses, rate |<p>For the controller that owns the volume, the number of times the block to be read is not found in cache per second.</p> |DEPENDENT |hpe.msa.controllers.cache.read.misses["{#CONTROLLER.ID}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['read-cache-misses']`</p><p>- CHANGE_PER_SECOND</p> |
|HPE |Controller [{#CONTROLLER.ID}]: Cache: Write hits, rate |<p>For the controller that owns the volume, the number of times the block written to is found in cache per second.</p> |DEPENDENT |hpe.msa.controllers.cache.write.hits["{#CONTROLLER.ID}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['write-cache-hits']`</p><p>- CHANGE_PER_SECOND</p> |
|HPE |Controller [{#CONTROLLER.ID}]: Cache: Write misses, rate |<p>For the controller that owns the volume, the number of times the block written to is not found in cache per second.</p> |DEPENDENT |hpe.msa.controllers.cache.write.misses["{#CONTROLLER.ID}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['write-cache-misses']`</p><p>- CHANGE_PER_SECOND</p> |
|HPE |Controller [{#CONTROLLER.ID}]: CPU utilization |<p>Percentage of time the CPU is busy, from 0 to 100.</p> |DEPENDENT |hpe.msa.controllers.cpu["{#CONTROLLER.ID}",util]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['cpu-load']`</p> |
|HPE |Controller [{#CONTROLLER.ID}]: IOPS, total rate |<p>Input/output operations per second, calculated over the interval since these statistics were last requested or reset. This value will be zero if it has not been requested or reset since a controller restart.</p> |DEPENDENT |hpe.msa.controllers.iops.total["{#CONTROLLER.ID}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['iops']`</p> |
|HPE |Controller [{#CONTROLLER.ID}]: IOPS, read rate |<p>Number of read operations per second.</p> |DEPENDENT |hpe.msa.controllers.iops.read["{#CONTROLLER.ID}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['number-of-reads']`</p><p>- CHANGE_PER_SECOND</p> |
|HPE |Controller [{#CONTROLLER.ID}]: IOPS, write rate |<p>Number of write operations per second.</p> |DEPENDENT |hpe.msa.controllers.iops.write["{#CONTROLLER.ID}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['number-of-writes']`</p><p>- CHANGE_PER_SECOND</p> |
|HPE |Controller [{#CONTROLLER.ID}]: Data transfer rate: Total |<p>The data transfer rate, in bytes per second, calculated over the interval since these statistics were last requested or reset. This value will be zero if it has not been requested or reset since a controller restart.</p> |DEPENDENT |hpe.msa.controllers.data_transfer.total["{#CONTROLLER.ID}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['bytes-per-second-numeric']`</p> |
|HPE |Controller [{#CONTROLLER.ID}]: Data transfer rate: Reads |<p>The data read rate, in bytes per second.</p> |DEPENDENT |hpe.msa.controllers.data_transfer.reads["{#CONTROLLER.ID}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['data-read-numeric']`</p><p>- CHANGE_PER_SECOND</p> |
|HPE |Controller [{#CONTROLLER.ID}]: Data transfer rate: Writes |<p>The data write rate, in bytes per second.</p> |DEPENDENT |hpe.msa.controllers.data_transfer.writes["{#CONTROLLER.ID}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['data-written-numeric']`</p><p>- CHANGE_PER_SECOND</p> |
|HPE |Controller [{#CONTROLLER.ID}]: Uptime |<p>Number of seconds since the controller was restarted.</p> |DEPENDENT |hpe.msa.controllers["{#CONTROLLER.ID}",uptime]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['power-on-time']`</p> |
|HPE |Disk group [{#NAME}]: Get data |<p>The discovered disk group data.</p> |DEPENDENT |hpe.msa.get.disks.groups["{#NAME}",data]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@['name'] == "{#NAME}")].first()`</p> |
|HPE |Disk group [{#NAME}]: Get statistics data |<p>The discovered disk group statistics data.</p> |DEPENDENT |hpe.msa.get.disks.groups.statistics["{#NAME}",data]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@['name'] == "{#NAME}")].first()`</p> |
|HPE |Disk group [{#NAME}]: Disks count |<p>Number of disks in the disk group.</p> |DEPENDENT |hpe.msa.disks.groups["{#NAME}",disk_count]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['diskcount']`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HPE |Disk group [{#NAME}]: Pool space used |<p>The percentage of pool capacity that the disk group occupies.</p> |DEPENDENT |hpe.msa.disks.groups.space["{#NAME}",pool_util]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['pool-percentage']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HPE |Disk group [{#NAME}]: Health |<p>Disk group health.</p> |DEPENDENT |hpe.msa.disks.groups["{#NAME}",health]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['health-numeric']`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 4`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HPE |Disk group [{#NAME}]: Space free |<p>The free space in the disk group.</p> |DEPENDENT |hpe.msa.disks.groups.space["{#NAME}",free]<p>**Preprocessing**:</p><p>- JSONPATH: `$['freespace-numeric']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>- MULTIPLIER: `512`</p> |
|HPE |Disk group [{#NAME}]: Space total |<p>The capacity of the disk group.</p> |DEPENDENT |hpe.msa.disks.groups.space["{#NAME}",total]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['size-numeric']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>- MULTIPLIER: `512`</p> |
|HPE |Disk group [{#NAME}]: Space utilization |<p>The space utilization percentage in the disk group.</p> |CALCULATED |hpe.msa.disks.groups.space["{#NAME}",util]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>**Expression**:</p>`100-last(//hpe.msa.disks.groups.space["{#NAME}",free])/last(//hpe.msa.disks.groups.space["{#NAME}",total])*100` |
|HPE |Disk group [{#NAME}]: RAID type |<p>The RAID level of the disk group.</p> |DEPENDENT |hpe.msa.disks.groups.raid["{#NAME}",type]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['raidtype-numeric']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Disk group [{#NAME}]: Status |<p>The status of the disk group:</p><p>- CRIT: Critical. The disk group is online but isn't fault tolerant because some of it's disks are down.</p><p>- DMGD: Damaged. The disk group is online and fault tolerant, but some of it's disks are damaged.</p><p>- FTDN: Fault tolerant with a down disk.The disk group is online and fault tolerant, but some of it's disks are down.</p><p>- FTOL: Fault tolerant.</p><p>- MSNG: Missing. The disk group is online and fault tolerant, but some of it's disks are missing.</p><p>- OFFL: Offline. Either the disk group is using offline initialization, or it's disks are down and data may be lost.</p><p>- QTCR: Quarantined critical. The disk group is critical with at least one inaccessible disk. For example, two disks are inaccessible in a RAID 6 disk group or one disk is inaccessible for other fault-tolerant RAID levels. If the inaccessible disks come online or if after 60 seconds from being quarantined the disk group is QTCRor QTDN, the disk group is automatically dequarantined.</p><p>- QTDN: Quarantined with a down disk. The RAID6 disk group has one inaccessible disk. The disk group is fault tolerant but degraded. If the inaccessible disks come online or if after 60 seconds from being quarantined the disk group is QTCRor QTDN, the disk group is automatically dequarantined.</p><p>- QTOF: Quarantined offline. The disk group is offline with multiple inaccessible disks causing user data to be incomplete, or is an NRAID or RAID 0 disk group.</p><p>- QTUN: Quarantined unsupported. The disk group contains data in a format that is not supported by this system. For example, this system does not support linear disk groups.</p><p>- STOP: The disk group is stopped.</p><p>- UNKN: Unknown.</p><p>- UP: Up. The disk group is online and does not have fault-tolerant attributes.</p> |DEPENDENT |hpe.msa.disks.groups["{#NAME}",status]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['status-numeric']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HPE |Disk group [{#NAME}]: IOPS, total rate |<p>Input/output operations per second, calculated over the interval since these statistics were last requested or reset. This value will be zero if it has not been requested or reset since a controller restart.</p> |DEPENDENT |hpe.msa.disks.groups.iops.total["{#NAME}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['iops']`</p> |
|HPE |Disk group [{#NAME}]: Average response time: Total |<p>Average response time for read and write operations, calculated over the interval since these statistics were last requested or reset.</p> |DEPENDENT |hpe.msa.disks.groups.avg_rsp_time["{#NAME}",total]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['avg-rsp-time']`</p><p>- MULTIPLIER: `0.000001`</p> |
|HPE |Disk group [{#NAME}]: Average response time: Read |<p>Average response time for all read operations, calculated over the interval since these statistics were last requested or reset.</p> |DEPENDENT |hpe.msa.disks.groups.avg_rsp_time["{#NAME}",read]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['avg-read-rsp-time']`</p><p>- MULTIPLIER: `0.000001`</p> |
|HPE |Disk group [{#NAME}]: Average response time: Write |<p>Average response time for all write operations, calculated over the interval since these statistics were last requested or reset.</p> |DEPENDENT |hpe.msa.disks.groups.avg_rsp_time["{#NAME}",write]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['avg-write-rsp-time']`</p><p>- MULTIPLIER: `0.000001`</p> |
|HPE |Disk group [{#NAME}]: IOPS, read rate |<p>Number of read operations per second.</p> |DEPENDENT |hpe.msa.disks.groups.iops.read["{#NAME}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['number-of-reads']`</p><p>- CHANGE_PER_SECOND</p> |
|HPE |Disk group [{#NAME}]: IOPS, write rate |<p>Number of write operations per second.</p> |DEPENDENT |hpe.msa.disks.groups.iops.write["{#NAME}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['number-of-writes']`</p><p>- CHANGE_PER_SECOND</p> |
|HPE |Disk group [{#NAME}]: Data transfer rate: Total |<p>The data transfer rate, in bytes per second, calculated over the interval since these statistics were last requested or reset. This value will be zero if it has not been requested or reset since a controller restart.</p> |DEPENDENT |hpe.msa.disks.groups.data_transfer.total["{#NAME}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['bytes-per-second-numeric']`</p> |
|HPE |Disk group [{#NAME}]: Data transfer rate: Reads |<p>The data read rate, in bytes per second.</p> |DEPENDENT |hpe.msa.disks.groups.data_transfer.reads["{#NAME}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['data-read-numeric']`</p><p>- CHANGE_PER_SECOND</p> |
|HPE |Disk group [{#NAME}]: Data transfer rate: Writes |<p>The data write rate, in bytes per second.</p> |DEPENDENT |hpe.msa.disks.groups.data_transfer.writes["{#NAME}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['data-written-numeric']`</p><p>- CHANGE_PER_SECOND</p> |
|HPE |Pool [{#NAME}]: Get data |<p>The discovered pool data.</p> |DEPENDENT |hpe.msa.get.pools["{#NAME}",data]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@['name'] == "{#NAME}")].first()`</p> |
|HPE |Pool [{#NAME}]: Health |<p>Pool health.</p> |DEPENDENT |hpe.msa.pools["{#NAME}",health]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['health-numeric']`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 4`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HPE |Pool [{#NAME}]: Space free |<p>The free space in the pool.</p> |DEPENDENT |hpe.msa.pools.space["{#NAME}",free]<p>**Preprocessing**:</p><p>- JSONPATH: `$['total-avail-numeric']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>- MULTIPLIER: `512`</p> |
|HPE |Pool [{#NAME}]: Space total |<p>The capacity of the pool.</p> |DEPENDENT |hpe.msa.pools.space["{#NAME}",total]<p>**Preprocessing**:</p><p>- JSONPATH: `$['total-size-numeric']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>- MULTIPLIER: `512`</p> |
|HPE |Pool [{#NAME}]: Space utilization |<p>The space utilization percentage in the pool.</p> |CALCULATED |hpe.msa.pools.space["{#NAME}",util]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>**Expression**:</p>`100-last(//hpe.msa.pools.space["{#NAME}",free])/last(//hpe.msa.pools.space["{#NAME}",total])*100` |
|HPE |Volume [{#NAME}]: Get data |<p>The discovered volume data.</p> |DEPENDENT |hpe.msa.get.volumes["{#NAME}",data]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@['volume-name'] == "{#NAME}")].first()`</p> |
|HPE |Volume [{#NAME}]: Get statistics data |<p>The discovered volume statistics data.</p> |DEPENDENT |hpe.msa.get.volumes.statistics["{#NAME}",data]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@['volume-name'] == "{#NAME}")].first()`</p> |
|HPE |Volume [{#NAME}]: Space allocated |<p>The amount of space currently allocated to the volume.</p> |DEPENDENT |hpe.msa.volumes.space["{#NAME}",allocated]<p>**Preprocessing**:</p><p>- JSONPATH: `$['allocated-size-numeric']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>- MULTIPLIER: `512`</p> |
|HPE |Volume [{#NAME}]: Space total |<p>The capacity of the volume.</p> |DEPENDENT |hpe.msa.volumes.space["{#NAME}",total]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['size-numeric']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>- MULTIPLIER: `512`</p> |
|HPE |Volume [{#NAME}]: IOPS, total rate |<p>Total input/output operations per second, calculated over the interval since these statistics were last requested or reset. This value will be zero if it has not been requested or reset since a controller restart.</p> |DEPENDENT |hpe.msa.volumes.iops.total["{#NAME}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['iops']`</p> |
|HPE |Volume [{#NAME}]: IOPS, read rate |<p>Number of read operations per second.</p> |DEPENDENT |hpe.msa.volumes.iops.read["{#NAME}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['number-of-reads']`</p><p>- CHANGE_PER_SECOND</p> |
|HPE |Volume [{#NAME}]: IOPS, write rate |<p>Number of write operations per second.</p> |DEPENDENT |hpe.msa.volumes.iops.write["{#NAME}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['number-of-writes']`</p><p>- CHANGE_PER_SECOND</p> |
|HPE |Volume [{#NAME}]: Data transfer rate: Total |<p>The data transfer rate, in bytes per second, calculated over the interval since these statistics were last requested or reset. This value will be zero if it has not been requested or reset since a controller restart.</p> |DEPENDENT |hpe.msa.volumes.data_transfer.total["{#NAME}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['bytes-per-second-numeric']`</p> |
|HPE |Volume [{#NAME}]: Data transfer rate: Reads |<p>The data read rate, in bytes per second.</p> |DEPENDENT |hpe.msa.volumes.data_transfer.reads["{#NAME}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['data-read-numeric']`</p><p>- CHANGE_PER_SECOND</p> |
|HPE |Volume [{#NAME}]: Data transfer rate: Writes |<p>The data write rate, in bytes per second.</p> |DEPENDENT |hpe.msa.volumes.data_transfer.writes["{#NAME}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['data-written-numeric']`</p><p>- CHANGE_PER_SECOND</p> |
|HPE |Volume [{#NAME}]: Cache: Read hits, rate |<p>For the controller that owns the volume, the number of times the block to be read is found in cache per second.</p> |DEPENDENT |hpe.msa.volumes.cache.read.hits["{#NAME}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['read-cache-hits']`</p><p>- CHANGE_PER_SECOND</p> |
|HPE |Volume [{#NAME}]: Cache: Read misses, rate |<p>For the controller that owns the volume, the number of times the block to be read is not found in cache per second.</p> |DEPENDENT |hpe.msa.volumes.cache.read.misses["{#NAME}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['read-cache-misses']`</p><p>- CHANGE_PER_SECOND</p> |
|HPE |Volume [{#NAME}]: Cache: Write hits, rate |<p>For the controller that owns the volume, the number of times the block written to is found in cache per second.</p> |DEPENDENT |hpe.msa.volumes.cache.write.hits["{#NAME}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['write-cache-hits']`</p><p>- CHANGE_PER_SECOND</p> |
|HPE |Volume [{#NAME}]: Cache: Write misses, rate |<p>For the controller that owns the volume, the number of times the block written to is not found in cache per second.</p> |DEPENDENT |hpe.msa.volumes.cache.write.misses["{#NAME}",rate]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['write-cache-misses']`</p><p>- CHANGE_PER_SECOND</p> |
|HPE |Enclosure [{#DURABLE.ID}]: Get data |<p>The discovered enclosure data.</p> |DEPENDENT |hpe.msa.get.enclosures["{#DURABLE.ID}",data]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@['durable-id'] == "{#DURABLE.ID}")].first()`</p> |
|HPE |Enclosure [{#DURABLE.ID}]: Health |<p>Enclosure health.</p> |DEPENDENT |hpe.msa.enclosures["{#DURABLE.ID}",health]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['health-numeric']`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 4`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HPE |Enclosure [{#DURABLE.ID}]: Status |<p>Enclosure status.</p> |DEPENDENT |hpe.msa.enclosures["{#DURABLE.ID}",status]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['status-numeric']`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 6`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Enclosure [{#DURABLE.ID}]: Midplane serial number |<p>Midplane serial number.</p> |DEPENDENT |hpe.msa.enclosures["{#DURABLE.ID}",midplane_serial_number]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['midplane-serial-number']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Enclosure [{#DURABLE.ID}]: Part number |<p>Enclosure part number.</p> |DEPENDENT |hpe.msa.enclosures["{#DURABLE.ID}",part_number]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['part-number']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Enclosure [{#DURABLE.ID}]: Model |<p>Enclosure model.</p> |DEPENDENT |hpe.msa.enclosures["{#DURABLE.ID}",model]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['model']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Enclosure [{#DURABLE.ID}]: Power |<p>Enclosure power in watts.</p> |DEPENDENT |hpe.msa.enclosures["{#DURABLE.ID}",power]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['enclosure-power']`</p> |
|HPE |Power supply [{#DURABLE.ID}]: Get data |<p>The discovered power supply data.</p> |DEPENDENT |hpe.msa.get.power_supplies["{#DURABLE.ID}",data]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@['durable-id'] == "{#DURABLE.ID}")].first()`</p> |
|HPE |Power supply [{#DURABLE.ID}]: Health |<p>Power supply health status.</p> |DEPENDENT |hpe.msa.power_supplies["{#DURABLE.ID}",health]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['health-numeric']`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 4`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HPE |Power supply [{#DURABLE.ID}]: Status |<p>Power supply status.</p> |DEPENDENT |hpe.msa.power_supplies["{#DURABLE.ID}",status]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['status-numeric']`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 4`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HPE |Power supply [{#DURABLE.ID}]: Part number |<p>Power supply part number.</p> |DEPENDENT |hpe.msa.power_supplies["{#DURABLE.ID}",part_number]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['part-number']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Power supply [{#DURABLE.ID}]: Serial number |<p>Power supply serial number.</p> |DEPENDENT |hpe.msa.power_supplies["{#DURABLE.ID}",serial_number]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['serial-number']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Power supply [{#DURABLE.ID}]: Temperature |<p>Power supply temperature.</p> |DEPENDENT |hpe.msa.power_supplies["{#DURABLE.ID}",temperature]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['power-supplies'][?(@['durable-id'] == "{#DURABLE.ID}")].['dctemp'].first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HPE |Port [{#NAME}]: Get data |<p>The discovered port data.</p> |DEPENDENT |hpe.msa.get.ports["{#NAME}",,data]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@['port'] == "{#NAME}")].first()`</p> |
|HPE |Port [{#NAME}]: Health |<p>Port health status.</p> |DEPENDENT |hpe.msa.ports["{#NAME}",health]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['health-numeric']`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 4`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HPE |Port [{#NAME}]: Status |<p>Port status.</p> |DEPENDENT |hpe.msa.ports["{#NAME}",status]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['status-numeric']`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 4`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HPE |Port [{#NAME}]: Type |<p>Port type.</p> |DEPENDENT |hpe.msa.ports["{#NAME}",type]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['port-type-numeric']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Fan [{#DURABLE.ID}]: Get data |<p>The discovered fan data.</p> |DEPENDENT |hpe.msa.get.fans["{#DURABLE.ID}",data]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@['durable-id'] == "{#DURABLE.ID}")].first()`</p> |
|HPE |Fan [{#DURABLE.ID}]: Health |<p>Fan health status.</p> |DEPENDENT |hpe.msa.fans["{#DURABLE.ID}",health]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['health-numeric']`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 4`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HPE |Fan [{#DURABLE.ID}]: Status |<p>Fan status.</p> |DEPENDENT |hpe.msa.fans["{#DURABLE.ID}",status]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['status-numeric']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HPE |Fan [{#DURABLE.ID}]: Speed |<p>Fan speed (revolutions per minute).</p> |DEPENDENT |hpe.msa.fans["{#DURABLE.ID}",speed]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['speed']`</p> |
|HPE |Disk [{#DURABLE.ID}]: Get data |<p>The discovered disk data.</p> |DEPENDENT |hpe.msa.get.disks["{#DURABLE.ID}",data]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@['durable-id'] == "{#DURABLE.ID}")].first()`</p> |
|HPE |Disk [{#DURABLE.ID}]: Health |<p>Disk health status.</p> |DEPENDENT |hpe.msa.disks["{#DURABLE.ID}",health]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['health-numeric'].first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 4`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HPE |Disk [{#DURABLE.ID}]: Temperature status |<p>Disk temperature status.</p> |DEPENDENT |hpe.msa.disks["{#DURABLE.ID}",temperature_status]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['temperature-status-numeric']`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- IN_RANGE: `1 3`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 4`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HPE |Disk [{#DURABLE.ID}]: Temperature |<p>Temperature of the disk.</p> |DEPENDENT |hpe.msa.disks["{#DURABLE.ID}",temperature]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['temperature-numeric']`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HPE |Disk [{#DURABLE.ID}]: Type |<p>Disk type:</p><p>SAS: Enterprise SAS spinning disk.</p><p>SAS MDL: Midline SAS spinning disk.</p><p>SSD SAS: SAS solit-state disk.</p> |DEPENDENT |hpe.msa.disks["{#DURABLE.ID}",type]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['description-numeric']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Disk [{#DURABLE.ID}]: Disk group |<p>If the disk is in a disk group, the disk group name.</p> |DEPENDENT |hpe.msa.disks["{#DURABLE.ID}",group]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['disk-group']`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Disk [{#DURABLE.ID}]: Storage pool |<p>If the disk is in a pool, the pool name.</p> |DEPENDENT |hpe.msa.disks["{#DURABLE.ID}",pool]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['storage-pool-name']`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Disk [{#DURABLE.ID}]: Vendor |<p>Disk vendor.</p> |DEPENDENT |hpe.msa.disks["{#DURABLE.ID}",vendor]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['vendor']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Disk [{#DURABLE.ID}]: Model |<p>Disk model.</p> |DEPENDENT |hpe.msa.disks["{#DURABLE.ID}",model]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['model']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Disk [{#DURABLE.ID}]: Serial number |<p>Disk serial number.</p> |DEPENDENT |hpe.msa.disks["{#DURABLE.ID}",serial_number]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['serial-number']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |Disk [{#DURABLE.ID}]: Space total |<p>Total size of the disk.</p> |DEPENDENT |hpe.msa.disks.space["{#DURABLE.ID}",total]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['size-numeric']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>- MULTIPLIER: `512`</p> |
|HPE |Disk [{#DURABLE.ID}]: SSD life left |<p>The percentage of disk life remaining.</p> |DEPENDENT |hpe.msa.disks.ssd["{#DURABLE.ID}",life_left]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['ssd-life-left-numeric']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HPE |FRU [{#ENCLOSURE.ID}: {#LOCATION}]: Get data |<p>The discovered FRU data.</p> |DEPENDENT |hpe.msa.get.frus["{#ENCLOSURE.ID}:{#LOCATION}",data]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@['name'] == "{#TYPE}")].first()`</p> |
|HPE |FRU [{#ENCLOSURE.ID}: {#LOCATION}]: Status |<p>{#DESCRIPTION}. FRU status:</p><p>Absent: Component is not present.</p><p>Fault: At least one subcomponent has a fault.</p><p>Invalid data: For a power supply module, the EEPROM is improperly programmed.</p><p>OK: All subcomponents are operating normally.</p><p>Not available: Status is not available.</p> |DEPENDENT |hpe.msa.frus["{#ENCLOSURE.ID}:{#LOCATION}",status]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['fru-status']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|HPE |FRU [{#ENCLOSURE.ID}: {#LOCATION}]: Part number |<p>{#DESCRIPTION}. Part number of the FRU.</p> |DEPENDENT |hpe.msa.frus["{#ENCLOSURE.ID}:{#LOCATION}",part_number]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['part-number']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HPE |FRU [{#ENCLOSURE.ID}: {#LOCATION}]: Serial number |<p>{#DESCRIPTION}. FRU serial number.</p> |DEPENDENT |hpe.msa.frus["{#ENCLOSURE.ID}:{#LOCATION}",serial_number]<p>**Preprocessing**:</p><p>- JSONPATH: `$.['serial-number']`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Zabbix raw items |HPE MSA: Get data |<p>The JSON with result of API requests.</p> |SCRIPT |hpe.msa.get.data<p>**Expression**:</p>`The text is too long. Please see the template.` |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|There are errors in method requests to API |<p>There are errors in method requests to API.</p> |`length(last(/HPE MSA 2040 Storage by HTTP/hpe.msa.get.errors))>0` |AVERAGE |<p>**Depends on**:</p><p>- Service is down or unavailable</p> |
|System health is in degraded state |<p>System health is in degraded state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.system.health)=1` |WARNING | |
|System health is in fault state |<p>System health is in fault state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.system.health)=2` |AVERAGE | |
|System health is in unknown state |<p>System health is in unknown state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.system.health)=3` |INFO | |
|Service is down or unavailable |<p>HTTP/HTTPS service is down or unable to establish TCP connection.</p> |`max(/HPE MSA 2040 Storage by HTTP/net.tcp.service["{$HPE.MSA.API.SCHEME}","{HOST.CONN}","{$HPE.MSA.API.PORT}"],5m)=0` |HIGH | |
|Controller [{#CONTROLLER.ID}]: Controller health is in degraded state |<p>Controller health is in degraded state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.controllers["{#CONTROLLER.ID}",health])=1` |WARNING |<p>**Depends on**:</p><p>- Controller [{#CONTROLLER.ID}]: Controller is down</p> |
|Controller [{#CONTROLLER.ID}]: Controller health is in fault state |<p>Controller health is in fault state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.controllers["{#CONTROLLER.ID}",health])=2` |AVERAGE |<p>**Depends on**:</p><p>- Controller [{#CONTROLLER.ID}]: Controller is down</p> |
|Controller [{#CONTROLLER.ID}]: Controller health is in unknown state |<p>Controller health is in unknown state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.controllers["{#CONTROLLER.ID}",health])=3` |INFO |<p>**Depends on**:</p><p>- Controller [{#CONTROLLER.ID}]: Controller is down</p> |
|Controller [{#CONTROLLER.ID}]: Controller is down |<p>The controller is down.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.controllers["{#CONTROLLER.ID}",status])=1` |HIGH | |
|Controller [{#CONTROLLER.ID}]: High CPU utilization |<p>Controller CPU utilization is too high. The system might be slow to respond.</p> |`min(/HPE MSA 2040 Storage by HTTP/hpe.msa.controllers.cpu["{#CONTROLLER.ID}",util],5m)>{$HPE.MSA.CONTROLLER.CPU.UTIL.CRIT}` |WARNING | |
|Controller [{#CONTROLLER.ID}]: Controller has been restarted |<p>The controller uptime is less than 10 minutes.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.controllers["{#CONTROLLER.ID}",uptime])<10m` |WARNING | |
|Disk group [{#NAME}]: Disk group health is in degraded state |<p>Disk group health is in degraded state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",health])=1` |WARNING | |
|Disk group [{#NAME}]: Disk group health is in fault state |<p>Disk group health is in fault state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",health])=2` |AVERAGE | |
|Disk group [{#NAME}]: Disk group health is in unknown state |<p>Disk group health is in unknown state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",health])=3` |INFO | |
|Disk group [{#NAME}]: Disk group space is low |<p>Disk group is running low on free space (less than {$HPE.MSA.DISKS.GROUP.PUSED.MAX.WARN:"{#NAME}"}% available).</p> |`min(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups.space["{#NAME}",util],5m)>{$HPE.MSA.DISKS.GROUP.PUSED.MAX.WARN:"{#NAME}"}` |WARNING |<p>**Depends on**:</p><p>- Disk group [{#NAME}]: Disk group space is critically low</p> |
|Disk group [{#NAME}]: Disk group space is critically low |<p>Disk group is running low on free space (less than {$HPE.MSA.DISKS.GROUP.PUSED.MAX.CRIT:"{#NAME}"}% available).</p> |`min(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups.space["{#NAME}",util],5m)>{$HPE.MSA.DISKS.GROUP.PUSED.MAX.CRIT:"{#NAME}"}` |AVERAGE | |
|Disk group [{#NAME}]: Disk group is fault tolerant with a down disk |<p>The disk group is online and fault tolerant, but some of it's disks are down.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",status])=1` |AVERAGE | |
|Disk group [{#NAME}]: Disk group has damaged disks |<p>The disk group is online and fault tolerant, but some of it's disks are damaged.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",status])=9` |AVERAGE | |
|Disk group [{#NAME}]: Disk group has missing disks |<p>The disk group is online and fault tolerant, but some of it's disks are missing.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",status])=8` |AVERAGE | |
|Disk group [{#NAME}]: Disk group is offline |<p>Either the disk group is using offline initialization, or it's disks are down and data may be lost.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",status])=3` |AVERAGE | |
|Disk group [{#NAME}]: Disk group is quarantined critical |<p>The disk group is critical with at least one inaccessible disk. For example, two disks are inaccessible in a RAID 6 disk group or one disk is inaccessible for other fault-tolerant RAID levels. If the inaccessible disks come online or if after 60 seconds from being quarantined the disk group is QTCRor QTDN, the disk group is automatically dequarantined.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",status])=4` |AVERAGE | |
|Disk group [{#NAME}]: Disk group is quarantined offline |<p>The disk group is offline with multiple inaccessible disks causing user data to be incomplete, or is an NRAID or RAID 0 disk group.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",status])=5` |AVERAGE | |
|Disk group [{#NAME}]: Disk group is quarantined unsupported |<p>The disk group contains data in a format that is not supported by this system. For example, this system does not support linear disk groups.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",status])=5` |AVERAGE | |
|Disk group [{#NAME}]: Disk group is quarantined with an inaccessible disk |<p>The RAID6 disk group has one inaccessible disk. The disk group is fault tolerant but degraded. If the inaccessible disks come online or if after 60 seconds from being quarantined the disk group is QTCRor QTDN, the disk group is automatically dequarantined.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",status])=6` |AVERAGE | |
|Disk group [{#NAME}]: Disk group is stopped |<p>The disk group is stopped.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",status])=7` |AVERAGE | |
|Disk group [{#NAME}]: Disk group status is critical |<p>The disk group is online but isn't fault tolerant because some of its disks are down.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",status])=2` |AVERAGE | |
|Pool [{#NAME}]: Pool health is in degraded state |<p>Pool health is in degraded state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.pools["{#NAME}",health])=1` |WARNING | |
|Pool [{#NAME}]: Pool health is in fault state |<p>Pool health is in fault state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.pools["{#NAME}",health])=2` |AVERAGE | |
|Pool [{#NAME}]: Pool health is in unknown state |<p>Pool [{#NAME}] health is in unknown state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.pools["{#NAME}",health])=3` |INFO | |
|Pool [{#NAME}]: Pool space is low |<p>Pool is running low on free space (less than {$HPE.MSA.POOL.PUSED.MAX.WARN:"{#NAME}"}% available).</p> |`min(/HPE MSA 2040 Storage by HTTP/hpe.msa.pools.space["{#NAME}",util],5m)>{$HPE.MSA.POOL.PUSED.MAX.WARN:"{#NAME}"}` |WARNING |<p>**Depends on**:</p><p>- Pool [{#NAME}]: Pool space is critically low</p> |
|Pool [{#NAME}]: Pool space is critically low |<p>Pool is running low on free space (less than {$HPE.MSA.POOL.PUSED.MAX.CRIT:"{#NAME}"}% available).</p> |`min(/HPE MSA 2040 Storage by HTTP/hpe.msa.pools.space["{#NAME}",util],5m)>{$HPE.MSA.POOL.PUSED.MAX.CRIT:"{#NAME}"}` |AVERAGE | |
|Enclosure [{#DURABLE.ID}]: Enclosure health is in degraded state |<p>Enclosure health is in degraded state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.enclosures["{#DURABLE.ID}",health])=1` |WARNING | |
|Enclosure [{#DURABLE.ID}]: Enclosure health is in fault state |<p>Enclosure health is in fault state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.enclosures["{#DURABLE.ID}",health])=2` |AVERAGE | |
|Enclosure [{#DURABLE.ID}]: Enclosure health is in unknown state |<p>Enclosure health is in unknown state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.enclosures["{#DURABLE.ID}",health])=3` |INFO | |
|Enclosure [{#DURABLE.ID}]: Enclosure has critical status |<p>Enclosure has critical status.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.enclosures["{#DURABLE.ID}",status])=2` |HIGH | |
|Enclosure [{#DURABLE.ID}]: Enclosure has warning status |<p>Enclosure has warning status.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.enclosures["{#DURABLE.ID}",status])=3` |WARNING | |
|Enclosure [{#DURABLE.ID}]: Enclosure is unavailable |<p>Enclosure is unavailable.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.enclosures["{#DURABLE.ID}",status])=7` |HIGH | |
|Enclosure [{#DURABLE.ID}]: Enclosure is unrecoverable |<p>Enclosure is unrecoverable.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.enclosures["{#DURABLE.ID}",status])=4` |HIGH | |
|Enclosure [{#DURABLE.ID}]: Enclosure has unknown status |<p>Enclosure has unknown status.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.enclosures["{#DURABLE.ID}",status])=6` |INFO | |
|Power supply [{#DURABLE.ID}]: Power supply health is in degraded state |<p>Power supply health is in degraded state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.power_supplies["{#DURABLE.ID}",health])=1` |WARNING | |
|Power supply [{#DURABLE.ID}]: Power supply health is in fault state |<p>Power supply health is in fault state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.power_supplies["{#DURABLE.ID}",health])=2` |AVERAGE | |
|Power supply [{#DURABLE.ID}]: Power supply health is in unknown state |<p>Power supply health is in unknown state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.power_supplies["{#DURABLE.ID}",health])=3` |INFO | |
|Power supply [{#DURABLE.ID}]: Power supply has error status |<p>Power supply has error status.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.power_supplies["{#DURABLE.ID}",status])=2` |AVERAGE | |
|Power supply [{#DURABLE.ID}]: Power supply has warning status |<p>Power supply has warning status.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.power_supplies["{#DURABLE.ID}",status])=1` |WARNING | |
|Power supply [{#DURABLE.ID}]: Power supply has unknown status |<p>Power supply has unknown status.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.power_supplies["{#DURABLE.ID}",status])=4` |INFO | |
|Port [{#NAME}]: Port health is in degraded state |<p>Port health is in degraded state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.ports["{#NAME}",health])=1` |WARNING | |
|Port [{#NAME}]: Port health is in fault state |<p>Port health is in fault state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.ports["{#NAME}",health])=2` |AVERAGE | |
|Port [{#NAME}]: Port health is in unknown state |<p>Port health is in unknown state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.ports["{#NAME}",health])=3` |INFO | |
|Port [{#NAME}]: Port has error status |<p>Port has error status.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.ports["{#NAME}",status])=2` |AVERAGE | |
|Port [{#NAME}]: Port has warning status |<p>Port has warning status.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.ports["{#NAME}",status])=1` |WARNING | |
|Port [{#NAME}]: Port has unknown status |<p>Port has unknown status.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.ports["{#NAME}",status])=4` |INFO | |
|Fan [{#DURABLE.ID}]: Fan health is in degraded state |<p>Fan health is in degraded state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.fans["{#DURABLE.ID}",health])=1` |WARNING | |
|Fan [{#DURABLE.ID}]: Fan health is in fault state |<p>Fan health is in fault state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.fans["{#DURABLE.ID}",health])=2` |AVERAGE | |
|Fan [{#DURABLE.ID}]: Fan health is in unknown state |<p>Fan health is in unknown state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.fans["{#DURABLE.ID}",health])=3` |INFO | |
|Fan [{#DURABLE.ID}]: Fan has error status |<p>Fan has error status.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.fans["{#DURABLE.ID}",status])=1` |AVERAGE | |
|Fan [{#DURABLE.ID}]: Fan is missing |<p>Fan is missing.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.fans["{#DURABLE.ID}",status])=3` |INFO | |
|Fan [{#DURABLE.ID}]: Fan is off |<p>Fan is off.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.fans["{#DURABLE.ID}",status])=2` |WARNING | |
|Disk [{#DURABLE.ID}]: Disk health is in degraded state |<p>Disk health is in degraded state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks["{#DURABLE.ID}",health])=1` |WARNING | |
|Disk [{#DURABLE.ID}]: Disk health is in fault state |<p>Disk health is in fault state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks["{#DURABLE.ID}",health])=2` |AVERAGE | |
|Disk [{#DURABLE.ID}]: Disk health is in unknown state |<p>Disk health is in unknown state.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks["{#DURABLE.ID}",health])=3` |INFO | |
|Disk [{#DURABLE.ID}]: Disk temperature is high |<p>Disk temperature is high.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks["{#DURABLE.ID}",temperature_status])=3` |WARNING | |
|Disk [{#DURABLE.ID}]: Disk temperature is critically high |<p>Disk temperature is critically high.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks["{#DURABLE.ID}",temperature_status])=2` |AVERAGE | |
|Disk [{#DURABLE.ID}]: Disk temperature is unknown |<p>Disk temperature is unknown.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks["{#DURABLE.ID}",temperature_status])=4` |INFO | |
|FRU [{#ENCLOSURE.ID}: {#LOCATION}]: FRU status is Degraded or Fault |<p>FRU status is Degraded or Fault.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.frus["{#ENCLOSURE.ID}:{#LOCATION}",status])=1` |AVERAGE | |
|FRU [{#ENCLOSURE.ID}: {#LOCATION}]: FRU ID data is invalid |<p>The FRU ID data is invalid. The FRU's EEPROM is improperly programmed.</p> |`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.frus["{#ENCLOSURE.ID}:{#LOCATION}",status])=0` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

