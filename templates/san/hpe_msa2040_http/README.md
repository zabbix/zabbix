
# HPE MSA 2040 Storage by HTTP

## Overview

The template to monitor HPE MSA 2040 by HTTP.
It works without any external scripts and uses the script item.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- HPE MSA 2040 Storage

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Create a user with a monitor role on the storage, for example "zabbix".
2. Link the template to a host.
3. Set the hostname or IP address of the host in the {$HPE.MSA.API.HOST} macro and configure the username and password in the {$HPE.MSA.API.USERNAME} and {$HPE.MSA.API.PASSWORD} macros.
4. Change the {$HPE.MSA.API.SCHEME} and {$HPE.MSA.API.PORT} macros if needed.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$HPE.MSA.API.SCHEME}|<p>Connection scheme for API.</p>|`https`|
|{$HPE.MSA.API.HOST}|<p>The hostname or IP address of the API host.</p>||
|{$HPE.MSA.API.PORT}|<p>Connection port for API.</p>|`443`|
|{$HPE.MSA.DATA.TIMEOUT}|<p>Response timeout for API.</p>|`30s`|
|{$HPE.MSA.API.USERNAME}|<p>Specify user name for API.</p>|`zabbix`|
|{$HPE.MSA.API.PASSWORD}|<p>Specify password for API.</p>||
|{$HPE.MSA.DISKS.GROUP.PUSED.MAX.WARN}|<p>The warning threshold of the disk group space utilization in %.</p>|`80`|
|{$HPE.MSA.DISKS.GROUP.PUSED.MAX.CRIT}|<p>The critical threshold of the disk group space utilization in %.</p>|`90`|
|{$HPE.MSA.POOL.PUSED.MAX.WARN}|<p>The warning threshold of the pool space utilization in %.</p>|`80`|
|{$HPE.MSA.POOL.PUSED.MAX.CRIT}|<p>The critical threshold of the pool space utilization in %.</p>|`90`|
|{$HPE.MSA.CONTROLLER.CPU.UTIL.CRIT}|<p>The critical threshold of the CPU utilization expressed in %.</p>|`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>The JSON with result of API requests.</p>|Script|hpe.msa.get.data|
|Get system|<p>The system data.</p>|Dependent item|hpe.msa.get.system<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.system[0]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get FRU|<p>FRU data.</p>|Dependent item|hpe.msa.get.fru<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['frus']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get fans|<p>Fans data.</p>|Dependent item|hpe.msa.get.fans<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['fans']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get disks|<p>Disks data.</p>|Dependent item|hpe.msa.get.disks<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['disks']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get enclosures|<p>Enclosures data.</p>|Dependent item|hpe.msa.get.enclosures<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['enclosures']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get ports|<p>Ports data.</p>|Dependent item|hpe.msa.get.ports<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['ports']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get power supplies|<p>Power supplies data.</p>|Dependent item|hpe.msa.get.power_supplies<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['power-supplies']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get pools|<p>Pools data.</p>|Dependent item|hpe.msa.get.pools<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['pools']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get controllers|<p>Controllers data.</p>|Dependent item|hpe.msa.get.controllers<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['controllers']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get controller statistics|<p>Controllers statistics data.</p>|Dependent item|hpe.msa.get.controller_statistics<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['controller-statistics']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get disk groups|<p>Disk groups data.</p>|Dependent item|hpe.msa.get.disks.groups<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['disk-groups']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get disk group statistics|<p>Disk groups statistics data.</p>|Dependent item|hpe.msa.disks.get.groups.statistics<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['disk-group-statistics']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get volumes|<p>Volumes data.</p>|Dependent item|hpe.msa.get.volumes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['volumes']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get volume statistics|<p>Volumes statistics data.</p>|Dependent item|hpe.msa.get.volumes.statistics<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['volume-statistics']`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get method errors|<p>A list of method errors from API requests.</p>|Dependent item|hpe.msa.get.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['errors']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Product ID|<p>The product model identifier.</p>|Dependent item|hpe.msa.system.product_id<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['product-id']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|System contact|<p>The name of the person who administers the system.</p>|Dependent item|hpe.msa.system.contact<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['system-contact']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|System information|<p>A brief description of what the system is used for or how it is configured.</p>|Dependent item|hpe.msa.system.info<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['system-information']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|System location|<p>The location of the system.</p>|Dependent item|hpe.msa.system.location<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['system-location']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|System name|<p>The name of the storage system.</p>|Dependent item|hpe.msa.system.name<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['system-name']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Vendor name|<p>The vendor name.</p>|Dependent item|hpe.msa.system.vendor_name<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['vendor-name']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|System health|<p>System health status.</p>|Dependent item|hpe.msa.system.health<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['health-numeric']`</p><p>⛔️Custom on fail: Set value to: `4`</p></li></ul>|
|Service ping|<p>Check if HTTP/HTTPS service accepts TCP connections.</p>|Simple check|net.tcp.service["{$HPE.MSA.API.SCHEME}","{$HPE.MSA.API.HOST}","{$HPE.MSA.API.PORT}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE MSA 2040 Storage: There are errors in method requests to API|<p>There are errors in method requests to API.</p>|`length(last(/HPE MSA 2040 Storage by HTTP/hpe.msa.get.errors))>0`|Average|**Depends on**:<br><ul><li>HPE MSA 2040 Storage: Service is down or unavailable</li></ul>|
|HPE MSA 2040 Storage: System health is in degraded state|<p>System health is in degraded state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.system.health)=1`|Warning||
|HPE MSA 2040 Storage: System health is in fault state|<p>System health is in fault state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.system.health)=2`|Average||
|HPE MSA 2040 Storage: System health is in unknown state|<p>System health is in unknown state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.system.health)=3`|Info||
|HPE MSA 2040 Storage: Service is down or unavailable|<p>HTTP/HTTPS service is down or unable to establish TCP connection.</p>|`max(/HPE MSA 2040 Storage by HTTP/net.tcp.service["{$HPE.MSA.API.SCHEME}","{$HPE.MSA.API.HOST}","{$HPE.MSA.API.PORT}"],5m)=0`|High||

### LLD rule Controllers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Controllers discovery|<p>Discover controllers.</p>|Dependent item|hpe.msa.controllers.discovery|

### Item prototypes for Controllers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Controller [{#CONTROLLER.ID}]: Get data|<p>The discovered controller data.</p>|Dependent item|hpe.msa.get.controllers["{#CONTROLLER.ID}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@['durable-id'] == "{#DURABLE.ID}")].first()`</p></li></ul>|
|Controller [{#CONTROLLER.ID}]: Get statistics data|<p>The discovered controller statistics data.</p>|Dependent item|hpe.msa.get.controller_statistics["{#CONTROLLER.ID}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@['durable-id'] == "{#DURABLE.ID}")].first()`</p></li></ul>|
|Controller [{#CONTROLLER.ID}]: Firmware version|<p>Storage controller firmware version.</p>|Dependent item|hpe.msa.controllers["{#CONTROLLER.ID}",firmware]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['sc-fw']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Controller [{#CONTROLLER.ID}]: Part number|<p>Part number of the controller.</p>|Dependent item|hpe.msa.controllers["{#CONTROLLER.ID}",part_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['part-number']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Controller [{#CONTROLLER.ID}]: Serial number|<p>Storage controller serial number.</p>|Dependent item|hpe.msa.controllers["{#CONTROLLER.ID}",serial_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['serial-number']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Controller [{#CONTROLLER.ID}]: Health|<p>Controller health status.</p>|Dependent item|hpe.msa.controllers["{#CONTROLLER.ID}",health]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['health-numeric']`</p><p>⛔️Custom on fail: Set value to: `4`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Controller [{#CONTROLLER.ID}]: Status|<p>Storage controller status.</p>|Dependent item|hpe.msa.controllers["{#CONTROLLER.ID}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['status-numeric']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Controller [{#CONTROLLER.ID}]: Disks|<p>Number of disks in the storage system.</p>|Dependent item|hpe.msa.controllers["{#CONTROLLER.ID}",disks]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['disks']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Controller [{#CONTROLLER.ID}]: Pools|<p>Number of pools in the storage system.</p>|Dependent item|hpe.msa.controllers["{#CONTROLLER.ID}",pools]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['number-of-storage-pools']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Controller [{#CONTROLLER.ID}]: Disk groups|<p>Number of disk groups in the storage system.</p>|Dependent item|hpe.msa.controllers["{#CONTROLLER.ID}",disk_groups]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['virtual-disks']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Controller [{#CONTROLLER.ID}]: IP address|<p>Controller network port IP address.</p>|Dependent item|hpe.msa.controllers["{#CONTROLLER.ID}",ip_address]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['ip-address']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Controller [{#CONTROLLER.ID}]: Cache memory size|<p>Controller cache memory size.</p>|Dependent item|hpe.msa.controllers.cache["{#CONTROLLER.ID}",total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['cache-memory-size']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Controller [{#CONTROLLER.ID}]: Cache: Write utilization|<p>Percentage of write cache in use, from 0 to 100.</p>|Dependent item|hpe.msa.controllers.cache.write["{#CONTROLLER.ID}",util]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['write-cache-used']`</p></li></ul>|
|Controller [{#CONTROLLER.ID}]: Cache: Read hits, rate|<p>For the controller that owns the volume, the number of times the block to be read is found in cache per second.</p>|Dependent item|hpe.msa.controllers.cache.read.hits["{#CONTROLLER.ID}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['read-cache-hits']`</p></li><li>Change per second</li></ul>|
|Controller [{#CONTROLLER.ID}]: Cache: Read misses, rate|<p>For the controller that owns the volume, the number of times the block to be read is not found in cache per second.</p>|Dependent item|hpe.msa.controllers.cache.read.misses["{#CONTROLLER.ID}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['read-cache-misses']`</p></li><li>Change per second</li></ul>|
|Controller [{#CONTROLLER.ID}]: Cache: Write hits, rate|<p>For the controller that owns the volume, the number of times the block written to is found in cache per second.</p>|Dependent item|hpe.msa.controllers.cache.write.hits["{#CONTROLLER.ID}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['write-cache-hits']`</p></li><li>Change per second</li></ul>|
|Controller [{#CONTROLLER.ID}]: Cache: Write misses, rate|<p>For the controller that owns the volume, the number of times the block written to is not found in cache per second.</p>|Dependent item|hpe.msa.controllers.cache.write.misses["{#CONTROLLER.ID}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['write-cache-misses']`</p></li><li>Change per second</li></ul>|
|Controller [{#CONTROLLER.ID}]: CPU utilization|<p>Percentage of time the CPU is busy, from 0 to 100.</p>|Dependent item|hpe.msa.controllers.cpu["{#CONTROLLER.ID}",util]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['cpu-load']`</p></li></ul>|
|Controller [{#CONTROLLER.ID}]: IOPS, total rate|<p>Input/output operations per second, calculated over the interval since these statistics were last requested or reset. This value will be zero if it has not been requested or reset since a controller restart.</p>|Dependent item|hpe.msa.controllers.iops.total["{#CONTROLLER.ID}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['iops']`</p></li></ul>|
|Controller [{#CONTROLLER.ID}]: IOPS, read rate|<p>Number of read operations per second.</p>|Dependent item|hpe.msa.controllers.iops.read["{#CONTROLLER.ID}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['number-of-reads']`</p></li><li>Change per second</li></ul>|
|Controller [{#CONTROLLER.ID}]: IOPS, write rate|<p>Number of write operations per second.</p>|Dependent item|hpe.msa.controllers.iops.write["{#CONTROLLER.ID}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['number-of-writes']`</p></li><li>Change per second</li></ul>|
|Controller [{#CONTROLLER.ID}]: Data transfer rate: Total|<p>The data transfer rate, in bytes per second, calculated over the interval since these statistics were last requested or reset. This value will be zero if it has not been requested or reset since a controller restart.</p>|Dependent item|hpe.msa.controllers.data_transfer.total["{#CONTROLLER.ID}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['bytes-per-second-numeric']`</p></li></ul>|
|Controller [{#CONTROLLER.ID}]: Data transfer rate: Reads|<p>The data read rate, in bytes per second.</p>|Dependent item|hpe.msa.controllers.data_transfer.reads["{#CONTROLLER.ID}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['data-read-numeric']`</p></li><li>Change per second</li></ul>|
|Controller [{#CONTROLLER.ID}]: Data transfer rate: Writes|<p>The data write rate, in bytes per second.</p>|Dependent item|hpe.msa.controllers.data_transfer.writes["{#CONTROLLER.ID}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['data-written-numeric']`</p></li><li>Change per second</li></ul>|
|Controller [{#CONTROLLER.ID}]: Uptime|<p>Number of seconds since the controller was restarted.</p>|Dependent item|hpe.msa.controllers["{#CONTROLLER.ID}",uptime]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['power-on-time']`</p></li></ul>|

### Trigger prototypes for Controllers discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE MSA 2040 Storage: Controller [{#CONTROLLER.ID}]: Controller health is in degraded state|<p>Controller health is in degraded state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.controllers["{#CONTROLLER.ID}",health])=1`|Warning|**Depends on**:<br><ul><li>HPE MSA 2040 Storage: Controller [{#CONTROLLER.ID}]: Controller is down</li></ul>|
|HPE MSA 2040 Storage: Controller [{#CONTROLLER.ID}]: Controller health is in fault state|<p>Controller health is in fault state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.controllers["{#CONTROLLER.ID}",health])=2`|Average|**Depends on**:<br><ul><li>HPE MSA 2040 Storage: Controller [{#CONTROLLER.ID}]: Controller is down</li></ul>|
|HPE MSA 2040 Storage: Controller [{#CONTROLLER.ID}]: Controller health is in unknown state|<p>Controller health is in unknown state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.controllers["{#CONTROLLER.ID}",health])=3`|Info|**Depends on**:<br><ul><li>HPE MSA 2040 Storage: Controller [{#CONTROLLER.ID}]: Controller is down</li></ul>|
|HPE MSA 2040 Storage: Controller [{#CONTROLLER.ID}]: Controller is down|<p>The controller is down.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.controllers["{#CONTROLLER.ID}",status])=1`|High||
|HPE MSA 2040 Storage: Controller [{#CONTROLLER.ID}]: High CPU utilization|<p>Controller CPU utilization is too high. The system might be slow to respond.</p>|`min(/HPE MSA 2040 Storage by HTTP/hpe.msa.controllers.cpu["{#CONTROLLER.ID}",util],5m)>{$HPE.MSA.CONTROLLER.CPU.UTIL.CRIT}`|Warning||
|HPE MSA 2040 Storage: Controller [{#CONTROLLER.ID}]: Controller has been restarted|<p>The controller uptime is less than 10 minutes.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.controllers["{#CONTROLLER.ID}",uptime])<10m`|Warning||

### LLD rule Disk groups discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk groups discovery|<p>Discover disk groups.</p>|Dependent item|hpe.msa.disks.groups.discovery|

### Item prototypes for Disk groups discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk group [{#NAME}]: Get data|<p>The discovered disk group data.</p>|Dependent item|hpe.msa.get.disks.groups["{#NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@['name'] == "{#NAME}")].first()`</p></li></ul>|
|Disk group [{#NAME}]: Get statistics data|<p>The discovered disk group statistics data.</p>|Dependent item|hpe.msa.get.disks.groups.statistics["{#NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@['name'] == "{#NAME}")].first()`</p></li></ul>|
|Disk group [{#NAME}]: Disks count|<p>Number of disks in the disk group.</p>|Dependent item|hpe.msa.disks.groups["{#NAME}",disk_count]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['diskcount']`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk group [{#NAME}]: Pool space used|<p>The percentage of pool capacity that the disk group occupies.</p>|Dependent item|hpe.msa.disks.groups.space["{#NAME}",pool_util]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['pool-percentage']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk group [{#NAME}]: Health|<p>Disk group health.</p>|Dependent item|hpe.msa.disks.groups["{#NAME}",health]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['health-numeric']`</p><p>⛔️Custom on fail: Set value to: `4`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk group [{#NAME}]: Space free|<p>The free space in the disk group.</p>|Dependent item|hpe.msa.disks.groups.space["{#NAME}",free]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['freespace-numeric']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `512`</p></li></ul>|
|Disk group [{#NAME}]: Space total|<p>The capacity of the disk group.</p>|Dependent item|hpe.msa.disks.groups.space["{#NAME}",total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['size-numeric']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `512`</p></li></ul>|
|Disk group [{#NAME}]: Space utilization|<p>The space utilization percentage in the disk group.</p>|Calculated|hpe.msa.disks.groups.space["{#NAME}",util]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk group [{#NAME}]: RAID type|<p>The RAID level of the disk group.</p>|Dependent item|hpe.msa.disks.groups.raid["{#NAME}",type]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['raidtype-numeric']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Disk group [{#NAME}]: Status|<p>The status of the disk group:</p><p></p><p>- CRIT: Critical. The disk group is online but isn't fault tolerant because some of it's disks are down.</p><p>- DMGD: Damaged. The disk group is online and fault tolerant, but some of it's disks are damaged.</p><p>- FTDN: Fault tolerant with a down disk.The disk group is online and fault tolerant, but some of it's disks are down.</p><p>- FTOL: Fault tolerant.</p><p>- MSNG: Missing. The disk group is online and fault tolerant, but some of it's disks are missing.</p><p>- OFFL: Offline. Either the disk group is using offline initialization, or it's disks are down and data may be lost.</p><p>- QTCR: Quarantined critical. The disk group is critical with at least one inaccessible disk. For example, two disks are inaccessible in a RAID 6 disk group or one disk is inaccessible for other fault-tolerant RAID levels. If the inaccessible disks come online or if after 60 seconds from being quarantined the disk group is QTCRor QTDN, the disk group is automatically dequarantined.</p><p>- QTDN: Quarantined with a down disk. The RAID6 disk group has one inaccessible disk. The disk group is fault tolerant but degraded. If the inaccessible disks come online or if after 60 seconds from being quarantined the disk group is QTCRor QTDN, the disk group is automatically dequarantined.</p><p>- QTOF: Quarantined offline. The disk group is offline with multiple inaccessible disks causing user data to be incomplete, or is an NRAID or RAID 0 disk group.</p><p>- QTUN: Quarantined unsupported. The disk group contains data in a format that is not supported by this system. For example, this system does not support linear disk groups.</p><p>- STOP: The disk group is stopped.</p><p>- UNKN: Unknown.</p><p>- UP: Up. The disk group is online and does not have fault-tolerant attributes.</p>|Dependent item|hpe.msa.disks.groups["{#NAME}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['status-numeric']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk group [{#NAME}]: IOPS, total rate|<p>Input/output operations per second, calculated over the interval since these statistics were last requested or reset. This value will be zero if it has not been requested or reset since a controller restart.</p>|Dependent item|hpe.msa.disks.groups.iops.total["{#NAME}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['iops']`</p></li></ul>|
|Disk group [{#NAME}]: Average response time: Total|<p>Average response time for read and write operations, calculated over the interval since these statistics were last requested or reset.</p>|Dependent item|hpe.msa.disks.groups.avg_rsp_time["{#NAME}",total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['avg-rsp-time']`</p></li><li><p>Custom multiplier: `0.000001`</p></li></ul>|
|Disk group [{#NAME}]: Average response time: Read|<p>Average response time for all read operations, calculated over the interval since these statistics were last requested or reset.</p>|Dependent item|hpe.msa.disks.groups.avg_rsp_time["{#NAME}",read]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['avg-read-rsp-time']`</p></li><li><p>Custom multiplier: `0.000001`</p></li></ul>|
|Disk group [{#NAME}]: Average response time: Write|<p>Average response time for all write operations, calculated over the interval since these statistics were last requested or reset.</p>|Dependent item|hpe.msa.disks.groups.avg_rsp_time["{#NAME}",write]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['avg-write-rsp-time']`</p></li><li><p>Custom multiplier: `0.000001`</p></li></ul>|
|Disk group [{#NAME}]: IOPS, read rate|<p>Number of read operations per second.</p>|Dependent item|hpe.msa.disks.groups.iops.read["{#NAME}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['number-of-reads']`</p></li><li>Change per second</li></ul>|
|Disk group [{#NAME}]: IOPS, write rate|<p>Number of write operations per second.</p>|Dependent item|hpe.msa.disks.groups.iops.write["{#NAME}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['number-of-writes']`</p></li><li>Change per second</li></ul>|
|Disk group [{#NAME}]: Data transfer rate: Total|<p>The data transfer rate, in bytes per second, calculated over the interval since these statistics were last requested or reset. This value will be zero if it has not been requested or reset since a controller restart.</p>|Dependent item|hpe.msa.disks.groups.data_transfer.total["{#NAME}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['bytes-per-second-numeric']`</p></li></ul>|
|Disk group [{#NAME}]: Data transfer rate: Reads|<p>The data read rate, in bytes per second.</p>|Dependent item|hpe.msa.disks.groups.data_transfer.reads["{#NAME}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['data-read-numeric']`</p></li><li>Change per second</li></ul>|
|Disk group [{#NAME}]: Data transfer rate: Writes|<p>The data write rate, in bytes per second.</p>|Dependent item|hpe.msa.disks.groups.data_transfer.writes["{#NAME}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['data-written-numeric']`</p></li><li>Change per second</li></ul>|

### Trigger prototypes for Disk groups discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE MSA 2040 Storage: Disk group [{#NAME}]: Disk group health is in degraded state|<p>Disk group health is in degraded state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",health])=1`|Warning||
|HPE MSA 2040 Storage: Disk group [{#NAME}]: Disk group health is in fault state|<p>Disk group health is in fault state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",health])=2`|Average||
|HPE MSA 2040 Storage: Disk group [{#NAME}]: Disk group health is in unknown state|<p>Disk group health is in unknown state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",health])=3`|Info||
|HPE MSA 2040 Storage: Disk group [{#NAME}]: Disk group space is low|<p>Disk group is running low on free space (less than {$HPE.MSA.DISKS.GROUP.PUSED.MAX.WARN:"{#NAME}"}% available).</p>|`min(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups.space["{#NAME}",util],5m)>{$HPE.MSA.DISKS.GROUP.PUSED.MAX.WARN:"{#NAME}"}`|Warning|**Depends on**:<br><ul><li>HPE MSA 2040 Storage: Disk group [{#NAME}]: Disk group space is critically low</li></ul>|
|HPE MSA 2040 Storage: Disk group [{#NAME}]: Disk group space is critically low|<p>Disk group is running low on free space (less than {$HPE.MSA.DISKS.GROUP.PUSED.MAX.CRIT:"{#NAME}"}% available).</p>|`min(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups.space["{#NAME}",util],5m)>{$HPE.MSA.DISKS.GROUP.PUSED.MAX.CRIT:"{#NAME}"}`|Average||
|HPE MSA 2040 Storage: Disk group [{#NAME}]: Disk group is fault tolerant with a down disk|<p>The disk group is online and fault tolerant, but some of it's disks are down.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",status])=1`|Average||
|HPE MSA 2040 Storage: Disk group [{#NAME}]: Disk group has damaged disks|<p>The disk group is online and fault tolerant, but some of it's disks are damaged.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",status])=9`|Average||
|HPE MSA 2040 Storage: Disk group [{#NAME}]: Disk group has missing disks|<p>The disk group is online and fault tolerant, but some of it's disks are missing.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",status])=8`|Average||
|HPE MSA 2040 Storage: Disk group [{#NAME}]: Disk group is offline|<p>Either the disk group is using offline initialization, or it's disks are down and data may be lost.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",status])=3`|Average||
|HPE MSA 2040 Storage: Disk group [{#NAME}]: Disk group is quarantined critical|<p>The disk group is critical with at least one inaccessible disk. For example, two disks are inaccessible in a RAID 6 disk group or one disk is inaccessible for other fault-tolerant RAID levels. If the inaccessible disks come online or if after 60 seconds from being quarantined the disk group is QTCRor QTDN, the disk group is automatically dequarantined.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",status])=4`|Average||
|HPE MSA 2040 Storage: Disk group [{#NAME}]: Disk group is quarantined offline|<p>The disk group is offline with multiple inaccessible disks causing user data to be incomplete, or is an NRAID or RAID 0 disk group.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",status])=5`|Average||
|HPE MSA 2040 Storage: Disk group [{#NAME}]: Disk group is quarantined unsupported|<p>The disk group contains data in a format that is not supported by this system. For example, this system does not support linear disk groups.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",status])=5`|Average||
|HPE MSA 2040 Storage: Disk group [{#NAME}]: Disk group is quarantined with an inaccessible disk|<p>The RAID6 disk group has one inaccessible disk. The disk group is fault tolerant but degraded. If the inaccessible disks come online or if after 60 seconds from being quarantined the disk group is QTCRor QTDN, the disk group is automatically dequarantined.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",status])=6`|Average||
|HPE MSA 2040 Storage: Disk group [{#NAME}]: Disk group is stopped|<p>The disk group is stopped.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",status])=7`|Average||
|HPE MSA 2040 Storage: Disk group [{#NAME}]: Disk group status is critical|<p>The disk group is online but isn't fault tolerant because some of its disks are down.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks.groups["{#NAME}",status])=2`|Average||

### LLD rule Pools discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Pools discovery|<p>Discover pools.</p>|Dependent item|hpe.msa.pools.discovery|

### Item prototypes for Pools discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Pool [{#NAME}]: Get data|<p>The discovered pool data.</p>|Dependent item|hpe.msa.get.pools["{#NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@['name'] == "{#NAME}")].first()`</p></li></ul>|
|Pool [{#NAME}]: Health|<p>Pool health.</p>|Dependent item|hpe.msa.pools["{#NAME}",health]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['health-numeric']`</p><p>⛔️Custom on fail: Set value to: `4`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Pool [{#NAME}]: Space free|<p>The free space in the pool.</p>|Dependent item|hpe.msa.pools.space["{#NAME}",free]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['total-avail-numeric']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `512`</p></li></ul>|
|Pool [{#NAME}]: Space total|<p>The capacity of the pool.</p>|Dependent item|hpe.msa.pools.space["{#NAME}",total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['total-size-numeric']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `512`</p></li></ul>|
|Pool [{#NAME}]: Space utilization|<p>The space utilization percentage in the pool.</p>|Calculated|hpe.msa.pools.space["{#NAME}",util]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Pools discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE MSA 2040 Storage: Pool [{#NAME}]: Pool health is in degraded state|<p>Pool health is in degraded state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.pools["{#NAME}",health])=1`|Warning||
|HPE MSA 2040 Storage: Pool [{#NAME}]: Pool health is in fault state|<p>Pool health is in fault state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.pools["{#NAME}",health])=2`|Average||
|HPE MSA 2040 Storage: Pool [{#NAME}]: Pool health is in unknown state|<p>Pool [{#NAME}] health is in unknown state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.pools["{#NAME}",health])=3`|Info||
|HPE MSA 2040 Storage: Pool [{#NAME}]: Pool space is low|<p>Pool is running low on free space (less than {$HPE.MSA.POOL.PUSED.MAX.WARN:"{#NAME}"}% available).</p>|`min(/HPE MSA 2040 Storage by HTTP/hpe.msa.pools.space["{#NAME}",util],5m)>{$HPE.MSA.POOL.PUSED.MAX.WARN:"{#NAME}"}`|Warning|**Depends on**:<br><ul><li>HPE MSA 2040 Storage: Pool [{#NAME}]: Pool space is critically low</li></ul>|
|HPE MSA 2040 Storage: Pool [{#NAME}]: Pool space is critically low|<p>Pool is running low on free space (less than {$HPE.MSA.POOL.PUSED.MAX.CRIT:"{#NAME}"}% available).</p>|`min(/HPE MSA 2040 Storage by HTTP/hpe.msa.pools.space["{#NAME}",util],5m)>{$HPE.MSA.POOL.PUSED.MAX.CRIT:"{#NAME}"}`|Average||

### LLD rule Volumes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Volumes discovery|<p>Discover volumes.</p>|Dependent item|hpe.msa.volumes.discovery|

### Item prototypes for Volumes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Volume [{#NAME}]: Get data|<p>The discovered volume data.</p>|Dependent item|hpe.msa.get.volumes["{#NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@['volume-name'] == "{#NAME}")].first()`</p></li></ul>|
|Volume [{#NAME}]: Health|<p>Volume health status.</p>|Dependent item|hpe.msa.volumes["{#DURABLE.ID}",health]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['health-numeric']`</p><p>⛔️Custom on fail: Set value to: `4`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Volume [{#NAME}]: Get statistics data|<p>The discovered volume statistics data.</p>|Dependent item|hpe.msa.get.volumes.statistics["{#NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@['volume-name'] == "{#NAME}")].first()`</p></li></ul>|
|Volume [{#NAME}]: Space allocated|<p>The amount of space currently allocated to the volume.</p>|Dependent item|hpe.msa.volumes.space["{#NAME}",allocated]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['allocated-size-numeric']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `512`</p></li></ul>|
|Volume [{#NAME}]: Space total|<p>The capacity of the volume.</p>|Dependent item|hpe.msa.volumes.space["{#NAME}",total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['size-numeric']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `512`</p></li></ul>|
|Volume [{#NAME}]: IOPS, total rate|<p>Total input/output operations per second, calculated over the interval since these statistics were last requested or reset. This value will be zero if it has not been requested or reset since a controller restart.</p>|Dependent item|hpe.msa.volumes.iops.total["{#NAME}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['iops']`</p></li></ul>|
|Volume [{#NAME}]: IOPS, read rate|<p>Number of read operations per second.</p>|Dependent item|hpe.msa.volumes.iops.read["{#NAME}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['number-of-reads']`</p></li><li>Change per second</li></ul>|
|Volume [{#NAME}]: IOPS, write rate|<p>Number of write operations per second.</p>|Dependent item|hpe.msa.volumes.iops.write["{#NAME}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['number-of-writes']`</p></li><li>Change per second</li></ul>|
|Volume [{#NAME}]: Data transfer rate: Total|<p>The data transfer rate, in bytes per second, calculated over the interval since these statistics were last requested or reset. This value will be zero if it has not been requested or reset since a controller restart.</p>|Dependent item|hpe.msa.volumes.data_transfer.total["{#NAME}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['bytes-per-second-numeric']`</p></li></ul>|
|Volume [{#NAME}]: Data transfer rate: Reads|<p>The data read rate, in bytes per second.</p>|Dependent item|hpe.msa.volumes.data_transfer.reads["{#NAME}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['data-read-numeric']`</p></li><li>Change per second</li></ul>|
|Volume [{#NAME}]: Data transfer rate: Writes|<p>The data write rate, in bytes per second.</p>|Dependent item|hpe.msa.volumes.data_transfer.writes["{#NAME}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['data-written-numeric']`</p></li><li>Change per second</li></ul>|
|Volume [{#NAME}]: Cache: Read hits, rate|<p>For the controller that owns the volume, the number of times the block to be read is found in cache per second.</p>|Dependent item|hpe.msa.volumes.cache.read.hits["{#NAME}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['read-cache-hits']`</p></li><li>Change per second</li></ul>|
|Volume [{#NAME}]: Cache: Read misses, rate|<p>For the controller that owns the volume, the number of times the block to be read is not found in cache per second.</p>|Dependent item|hpe.msa.volumes.cache.read.misses["{#NAME}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['read-cache-misses']`</p></li><li>Change per second</li></ul>|
|Volume [{#NAME}]: Cache: Write hits, rate|<p>For the controller that owns the volume, the number of times the block written to is found in cache per second.</p>|Dependent item|hpe.msa.volumes.cache.write.hits["{#NAME}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['write-cache-hits']`</p></li><li>Change per second</li></ul>|
|Volume [{#NAME}]: Cache: Write misses, rate|<p>For the controller that owns the volume, the number of times the block written to is not found in cache per second.</p>|Dependent item|hpe.msa.volumes.cache.write.misses["{#NAME}",rate]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['write-cache-misses']`</p></li><li>Change per second</li></ul>|

### Trigger prototypes for Volumes discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE MSA 2040 Storage: Volume [{#NAME}]: Volume health is in degraded state|<p>Volume health is in degraded state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.volumes["{#DURABLE.ID}",health])=1`|Warning||
|HPE MSA 2040 Storage: Volume [{#NAME}]: Volume health is in fault state|<p>Volume health is in fault state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.volumes["{#DURABLE.ID}",health])=2`|Average||
|HPE MSA 2040 Storage: Volume [{#NAME}]: Volume health is in unknown state|<p>Volume health is in unknown state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.volumes["{#DURABLE.ID}",health])=3`|Info||

### LLD rule Enclosures discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Enclosures discovery|<p>Discover enclosures.</p>|Dependent item|hpe.msa.enclosures.discovery|

### Item prototypes for Enclosures discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Enclosure [{#DURABLE.ID}]: Get data|<p>The discovered enclosure data.</p>|Dependent item|hpe.msa.get.enclosures["{#DURABLE.ID}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@['durable-id'] == "{#DURABLE.ID}")].first()`</p></li></ul>|
|Enclosure [{#DURABLE.ID}]: Health|<p>Enclosure health.</p>|Dependent item|hpe.msa.enclosures["{#DURABLE.ID}",health]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['health-numeric']`</p><p>⛔️Custom on fail: Set value to: `4`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Enclosure [{#DURABLE.ID}]: Status|<p>Enclosure status.</p>|Dependent item|hpe.msa.enclosures["{#DURABLE.ID}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['status-numeric']`</p><p>⛔️Custom on fail: Set value to: `6`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Enclosure [{#DURABLE.ID}]: Midplane serial number|<p>Midplane serial number.</p>|Dependent item|hpe.msa.enclosures["{#DURABLE.ID}",midplane_serial_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['midplane-serial-number']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Enclosure [{#DURABLE.ID}]: Part number|<p>Enclosure part number.</p>|Dependent item|hpe.msa.enclosures["{#DURABLE.ID}",part_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['part-number']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Enclosure [{#DURABLE.ID}]: Model|<p>Enclosure model.</p>|Dependent item|hpe.msa.enclosures["{#DURABLE.ID}",model]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['model']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Enclosure [{#DURABLE.ID}]: Power|<p>Enclosure power in watts.</p>|Dependent item|hpe.msa.enclosures["{#DURABLE.ID}",power]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['enclosure-power']`</p></li></ul>|

### Trigger prototypes for Enclosures discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE MSA 2040 Storage: Enclosure [{#DURABLE.ID}]: Enclosure health is in degraded state|<p>Enclosure health is in degraded state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.enclosures["{#DURABLE.ID}",health])=1`|Warning||
|HPE MSA 2040 Storage: Enclosure [{#DURABLE.ID}]: Enclosure health is in fault state|<p>Enclosure health is in fault state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.enclosures["{#DURABLE.ID}",health])=2`|Average||
|HPE MSA 2040 Storage: Enclosure [{#DURABLE.ID}]: Enclosure health is in unknown state|<p>Enclosure health is in unknown state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.enclosures["{#DURABLE.ID}",health])=3`|Info||
|HPE MSA 2040 Storage: Enclosure [{#DURABLE.ID}]: Enclosure has critical status|<p>Enclosure has critical status.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.enclosures["{#DURABLE.ID}",status])=2`|High||
|HPE MSA 2040 Storage: Enclosure [{#DURABLE.ID}]: Enclosure has warning status|<p>Enclosure has warning status.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.enclosures["{#DURABLE.ID}",status])=3`|Warning||
|HPE MSA 2040 Storage: Enclosure [{#DURABLE.ID}]: Enclosure is unavailable|<p>Enclosure is unavailable.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.enclosures["{#DURABLE.ID}",status])=7`|High||
|HPE MSA 2040 Storage: Enclosure [{#DURABLE.ID}]: Enclosure is unrecoverable|<p>Enclosure is unrecoverable.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.enclosures["{#DURABLE.ID}",status])=4`|High||
|HPE MSA 2040 Storage: Enclosure [{#DURABLE.ID}]: Enclosure has unknown status|<p>Enclosure has unknown status.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.enclosures["{#DURABLE.ID}",status])=6`|Info||

### LLD rule Power supplies discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Power supplies discovery|<p>Discover power supplies.</p>|Dependent item|hpe.msa.power_supplies.discovery|

### Item prototypes for Power supplies discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Power supply [{#DURABLE.ID}]: Get data|<p>The discovered power supply data.</p>|Dependent item|hpe.msa.get.power_supplies["{#DURABLE.ID}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@['durable-id'] == "{#DURABLE.ID}")].first()`</p></li></ul>|
|Power supply [{#DURABLE.ID}]: Health|<p>Power supply health status.</p>|Dependent item|hpe.msa.power_supplies["{#DURABLE.ID}",health]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['health-numeric']`</p><p>⛔️Custom on fail: Set value to: `4`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Power supply [{#DURABLE.ID}]: Status|<p>Power supply status.</p>|Dependent item|hpe.msa.power_supplies["{#DURABLE.ID}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['status-numeric']`</p><p>⛔️Custom on fail: Set value to: `4`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Power supply [{#DURABLE.ID}]: Part number|<p>Power supply part number.</p>|Dependent item|hpe.msa.power_supplies["{#DURABLE.ID}",part_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['part-number']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Power supply [{#DURABLE.ID}]: Serial number|<p>Power supply serial number.</p>|Dependent item|hpe.msa.power_supplies["{#DURABLE.ID}",serial_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['serial-number']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Power supply [{#DURABLE.ID}]: Temperature|<p>Power supply temperature.</p>|Dependent item|hpe.msa.power_supplies["{#DURABLE.ID}",temperature]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['dctemp']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Power supplies discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE MSA 2040 Storage: Power supply [{#DURABLE.ID}]: Power supply health is in degraded state|<p>Power supply health is in degraded state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.power_supplies["{#DURABLE.ID}",health])=1`|Warning||
|HPE MSA 2040 Storage: Power supply [{#DURABLE.ID}]: Power supply health is in fault state|<p>Power supply health is in fault state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.power_supplies["{#DURABLE.ID}",health])=2`|Average||
|HPE MSA 2040 Storage: Power supply [{#DURABLE.ID}]: Power supply health is in unknown state|<p>Power supply health is in unknown state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.power_supplies["{#DURABLE.ID}",health])=3`|Info||
|HPE MSA 2040 Storage: Power supply [{#DURABLE.ID}]: Power supply has error status|<p>Power supply has error status.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.power_supplies["{#DURABLE.ID}",status])=2`|Average||
|HPE MSA 2040 Storage: Power supply [{#DURABLE.ID}]: Power supply has warning status|<p>Power supply has warning status.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.power_supplies["{#DURABLE.ID}",status])=1`|Warning||
|HPE MSA 2040 Storage: Power supply [{#DURABLE.ID}]: Power supply has unknown status|<p>Power supply has unknown status.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.power_supplies["{#DURABLE.ID}",status])=4`|Info||

### LLD rule Ports discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Ports discovery|<p>Discover ports.</p>|Dependent item|hpe.msa.ports.discovery|

### Item prototypes for Ports discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Port [{#NAME}]: Get data|<p>The discovered port data.</p>|Dependent item|hpe.msa.get.ports["{#NAME}",,data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@['port'] == "{#NAME}")].first()`</p></li></ul>|
|Port [{#NAME}]: Health|<p>Port health status.</p>|Dependent item|hpe.msa.ports["{#NAME}",health]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['health-numeric']`</p><p>⛔️Custom on fail: Set value to: `4`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Port [{#NAME}]: Status|<p>Port status.</p>|Dependent item|hpe.msa.ports["{#NAME}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['status-numeric']`</p><p>⛔️Custom on fail: Set value to: `4`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Port [{#NAME}]: Type|<p>Port type.</p>|Dependent item|hpe.msa.ports["{#NAME}",type]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['port-type-numeric']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Ports discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE MSA 2040 Storage: Port [{#NAME}]: Port health is in degraded state|<p>Port health is in degraded state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.ports["{#NAME}",health])=1`|Warning||
|HPE MSA 2040 Storage: Port [{#NAME}]: Port health is in fault state|<p>Port health is in fault state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.ports["{#NAME}",health])=2`|Average||
|HPE MSA 2040 Storage: Port [{#NAME}]: Port health is in unknown state|<p>Port health is in unknown state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.ports["{#NAME}",health])=3`|Info||
|HPE MSA 2040 Storage: Port [{#NAME}]: Port has error status|<p>Port has error status.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.ports["{#NAME}",status])=2`|Average||
|HPE MSA 2040 Storage: Port [{#NAME}]: Port has warning status|<p>Port has warning status.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.ports["{#NAME}",status])=1`|Warning||
|HPE MSA 2040 Storage: Port [{#NAME}]: Port has unknown status|<p>Port has unknown status.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.ports["{#NAME}",status])=4`|Info||

### LLD rule Fans discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fans discovery|<p>Discover fans.</p>|Dependent item|hpe.msa.fans.discovery|

### Item prototypes for Fans discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan [{#DURABLE.ID}]: Get data|<p>The discovered fan data.</p>|Dependent item|hpe.msa.get.fans["{#DURABLE.ID}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@['durable-id'] == "{#DURABLE.ID}")].first()`</p></li></ul>|
|Fan [{#DURABLE.ID}]: Health|<p>Fan health status.</p>|Dependent item|hpe.msa.fans["{#DURABLE.ID}",health]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['health-numeric']`</p><p>⛔️Custom on fail: Set value to: `4`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Fan [{#DURABLE.ID}]: Status|<p>Fan status.</p>|Dependent item|hpe.msa.fans["{#DURABLE.ID}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['status-numeric']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Fan [{#DURABLE.ID}]: Speed|<p>Fan speed (revolutions per minute).</p>|Dependent item|hpe.msa.fans["{#DURABLE.ID}",speed]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['speed']`</p></li></ul>|

### Trigger prototypes for Fans discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE MSA 2040 Storage: Fan [{#DURABLE.ID}]: Fan health is in degraded state|<p>Fan health is in degraded state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.fans["{#DURABLE.ID}",health])=1`|Warning||
|HPE MSA 2040 Storage: Fan [{#DURABLE.ID}]: Fan health is in fault state|<p>Fan health is in fault state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.fans["{#DURABLE.ID}",health])=2`|Average||
|HPE MSA 2040 Storage: Fan [{#DURABLE.ID}]: Fan health is in unknown state|<p>Fan health is in unknown state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.fans["{#DURABLE.ID}",health])=3`|Info||
|HPE MSA 2040 Storage: Fan [{#DURABLE.ID}]: Fan has error status|<p>Fan has error status.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.fans["{#DURABLE.ID}",status])=1`|Average||
|HPE MSA 2040 Storage: Fan [{#DURABLE.ID}]: Fan is missing|<p>Fan is missing.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.fans["{#DURABLE.ID}",status])=3`|Info||
|HPE MSA 2040 Storage: Fan [{#DURABLE.ID}]: Fan is off|<p>Fan is off.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.fans["{#DURABLE.ID}",status])=2`|Warning||

### LLD rule Disks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disks discovery|<p>Discover disks.</p>|Dependent item|hpe.msa.disks.discovery|

### Item prototypes for Disks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Disk [{#DURABLE.ID}]: Get data|<p>The discovered disk data.</p>|Dependent item|hpe.msa.get.disks["{#DURABLE.ID}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@['durable-id'] == "{#DURABLE.ID}")].first()`</p></li></ul>|
|Disk [{#DURABLE.ID}]: Health|<p>Disk health status.</p>|Dependent item|hpe.msa.disks["{#DURABLE.ID}",health]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['health-numeric']`</p><p>⛔️Custom on fail: Set value to: `4`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk [{#DURABLE.ID}]: Temperature status|<p>Disk temperature status.</p>|Dependent item|hpe.msa.disks["{#DURABLE.ID}",temperature_status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['temperature-status-numeric']`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>In range: `1 -> 3`</p><p>⛔️Custom on fail: Set value to: `4`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk [{#DURABLE.ID}]: Temperature|<p>Temperature of the disk.</p>|Dependent item|hpe.msa.disks["{#DURABLE.ID}",temperature]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['temperature-numeric']`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk [{#DURABLE.ID}]: Type|<p>Disk type:</p><p>SAS: Enterprise SAS spinning disk.</p><p>SAS MDL: Midline SAS spinning disk.</p><p>SSD SAS: SAS solit-state disk.</p>|Dependent item|hpe.msa.disks["{#DURABLE.ID}",type]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['description-numeric']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Disk [{#DURABLE.ID}]: Disk group|<p>If the disk is in a disk group, the disk group name.</p>|Dependent item|hpe.msa.disks["{#DURABLE.ID}",group]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['disk-group']`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Disk [{#DURABLE.ID}]: Storage pool|<p>If the disk is in a pool, the pool name.</p>|Dependent item|hpe.msa.disks["{#DURABLE.ID}",pool]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['storage-pool-name']`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Disk [{#DURABLE.ID}]: Vendor|<p>Disk vendor.</p>|Dependent item|hpe.msa.disks["{#DURABLE.ID}",vendor]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['vendor']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Disk [{#DURABLE.ID}]: Model|<p>Disk model.</p>|Dependent item|hpe.msa.disks["{#DURABLE.ID}",model]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['model']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Disk [{#DURABLE.ID}]: Serial number|<p>Disk serial number.</p>|Dependent item|hpe.msa.disks["{#DURABLE.ID}",serial_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['serial-number']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Disk [{#DURABLE.ID}]: Space total|<p>Total size of the disk.</p>|Dependent item|hpe.msa.disks.space["{#DURABLE.ID}",total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['size-numeric']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>Custom multiplier: `512`</p></li></ul>|
|Disk [{#DURABLE.ID}]: SSD life left|<p>The percentage of disk life remaining.</p>|Dependent item|hpe.msa.disks.ssd["{#DURABLE.ID}",life_left]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['ssd-life-left-numeric']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Disks discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE MSA 2040 Storage: Disk [{#DURABLE.ID}]: Disk health is in degraded state|<p>Disk health is in degraded state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks["{#DURABLE.ID}",health])=1`|Warning||
|HPE MSA 2040 Storage: Disk [{#DURABLE.ID}]: Disk health is in fault state|<p>Disk health is in fault state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks["{#DURABLE.ID}",health])=2`|Average||
|HPE MSA 2040 Storage: Disk [{#DURABLE.ID}]: Disk health is in unknown state|<p>Disk health is in unknown state.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks["{#DURABLE.ID}",health])=3`|Info||
|HPE MSA 2040 Storage: Disk [{#DURABLE.ID}]: Disk temperature is high|<p>Disk temperature is high.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks["{#DURABLE.ID}",temperature_status])=3`|Warning||
|HPE MSA 2040 Storage: Disk [{#DURABLE.ID}]: Disk temperature is critically high|<p>Disk temperature is critically high.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks["{#DURABLE.ID}",temperature_status])=2`|Average||
|HPE MSA 2040 Storage: Disk [{#DURABLE.ID}]: Disk temperature is unknown|<p>Disk temperature is unknown.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.disks["{#DURABLE.ID}",temperature_status])=4`|Info||

### LLD rule FRU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FRU discovery|<p>Discover FRU.</p>|Dependent item|hpe.msa.frus.discovery|

### Item prototypes for FRU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FRU [{#ENCLOSURE.ID}: {#LOCATION}]: Get data|<p>The discovered FRU data.</p>|Dependent item|hpe.msa.get.frus["{#ENCLOSURE.ID}:{#LOCATION}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@['name'] == "{#TYPE}")].first()`</p></li></ul>|
|FRU [{#ENCLOSURE.ID}: {#LOCATION}]: Status|<p>{#DESCRIPTION}. FRU status:</p><p></p><p>Absent: Component is not present.</p><p>Fault: At least one subcomponent has a fault.</p><p>Invalid data: For a power supply module, the EEPROM is improperly programmed.</p><p>OK: All subcomponents are operating normally.</p><p>Not available: Status is not available.</p>|Dependent item|hpe.msa.frus["{#ENCLOSURE.ID}:{#LOCATION}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['fru-status']`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|FRU [{#ENCLOSURE.ID}: {#LOCATION}]: Part number|<p>{#DESCRIPTION}. Part number of the FRU.</p>|Dependent item|hpe.msa.frus["{#ENCLOSURE.ID}:{#LOCATION}",part_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['part-number']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|FRU [{#ENCLOSURE.ID}: {#LOCATION}]: Serial number|<p>{#DESCRIPTION}. FRU serial number.</p>|Dependent item|hpe.msa.frus["{#ENCLOSURE.ID}:{#LOCATION}",serial_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['serial-number']`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for FRU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE MSA 2040 Storage: FRU [{#ENCLOSURE.ID}: {#LOCATION}]: FRU status is Degraded or Fault|<p>FRU status is Degraded or Fault.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.frus["{#ENCLOSURE.ID}:{#LOCATION}",status])=1`|Average||
|HPE MSA 2040 Storage: FRU [{#ENCLOSURE.ID}: {#LOCATION}]: FRU ID data is invalid|<p>The FRU ID data is invalid. The FRU's EEPROM is improperly programmed.</p>|`last(/HPE MSA 2040 Storage by HTTP/hpe.msa.frus["{#ENCLOSURE.ID}:{#LOCATION}",status])=0`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

