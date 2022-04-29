
# DELL PowerEdge R740 by HTTP

## Overview

For Zabbix version: 6.0 and higher  
This is a template for monitoring DELL PowerEdge R740 servers with iDRAC 8/9 firmware 4.32 and later with Redfish API enabled via Zabbix HTTP agent that works without any external scripts.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

1\. Enable Redfish API in Dell iDRAC interface of your server.

2\. Create a user for monitoring with read-only permissions in Dell iDRAC interface.

3\. Create a host for Dell server with iDRAC IP as Zabbix agent interface.

4\. Link the template to the host.

5\. Customize values of {$API.URL}, {$API.USER}, {$API.PASSWORD} macros.



## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$API.PASSWORD} |<p>The Dell iDRAC user password.</p> |`<Put your password here>` |
|{$API.URL} |<p>The Dell iDRAC Redfish API URL in the format `<scheme>://<host>:<port>`.</p> |`<Put your URL here>` |
|{$API.USER} |<p>The Dell iDRAC username.</p> |`<Put your username here>` |
|{$IFCONTROL} |<p>Link status trigger will be fired only for interfaces that have the context macro equaled 1.</p> |`1` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Array controller cache discovery |<p>Discovery of a cache of disk array controllers.</p> |HTTP_AGENT |array.cache.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Array controller discovery |<p>Discovery of disk array controllers.</p> |HTTP_AGENT |array.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|FAN discovery |<p>Discovery of FAN sensors.</p> |HTTP_AGENT |fan.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interface discovery |<p>The NetworkInterface schema describes links to the NetworkAdapter and represents the functionality available to the containing system.</p> |HTTP_AGENT |net.if.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Physical disk discovery |<p>Discovery of physical disks.</p> |HTTP_AGENT |physicaldisk.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|PSU discovery |<p>Discovery of PSU sensors.</p> |HTTP_AGENT |psu.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Temperature discovery |<p>Discovery of temperature sensors.</p> |HTTP_AGENT |temp.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Virtual disk discovery |<p>Discovery of virtual disks.</p> |HTTP_AGENT |virtualdisk.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Fans |Dell R740: {#SENSOR_NAME} Speed |<p>The sensor value.</p> |DEPENDENT |dell.server.sensor.fan.speed[{#SENSOR_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Reading`</p> |
|Fans |Dell R740: {#SENSOR_NAME} Status |<p>The status of the job. Possible values: OK, Warning, Critical.</p> |DEPENDENT |dell.server.sensor.fan.status[{#SENSOR_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Status.Health`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Inventory |Dell R740: Hardware model name |<p>This attribute defines the model name of the system.</p> |DEPENDENT |dell.server.hw.model<p>**Preprocessing**:</p><p>- JSONPATH: `$.model`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Inventory |Dell R740: Hardware serial number |<p>This attribute defines the service tag of the system.</p> |DEPENDENT |dell.server.hw.serialnumber<p>**Preprocessing**:</p><p>- JSONPATH: `$.serialnumber`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Inventory |Dell R740: Firmware version |<p>This attribute defines the firmware version of a remote access card.</p> |DEPENDENT |dell.server.hw.firmware<p>**Preprocessing**:</p><p>- JSONPATH: `$.firmware`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |Dell R740: {#IFNAME} Speed |<p>Network port current link speed.</p> |DEPENDENT |dell.server.net.if.speed[{#IFNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.CurrentLinkSpeedMbps`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |Dell R740: {#IFNAME} Link status |<p>The status of the link between this port and its link partner. Possible values: Down, Up, null.</p> |DEPENDENT |dell.server.net.if.status[{#IFNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.LinkStatus`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |Dell R740: {#IFNAME} State |<p>The known state of the Resource, for example, enabled. Possible values: Enabled, Disabled, StandbyOffline, StandbySpare, InTest, Starting, Absent, UnavailableOffline, Deferring, Quiesced, Updating, Qualified.</p> |DEPENDENT |dell.server.net.if.state[{#IFNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Status.State`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interfaces |Dell R740: {#IFNAME} Status |<p>The status of the job. Possible values: OK, Warning, Critical.</p> |DEPENDENT |dell.server.net.if.health[{#IFNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Status.Health`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Physical disks |Dell R740: {#DISK_NAME} Status |<p>The status of the job. Possible values: OK, Warning, Critical.</p> |DEPENDENT |dell.server.hw.physicaldisk.status[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Status.Health`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Physical disks |Dell R740: {#DISK_NAME} Serial number |<p>The serial number of this drive.</p> |DEPENDENT |dell.server.hw.physicaldisk.serialnumber[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.SerialNumber`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Physical disks |Dell R740: {#DISK_NAME} Model name |<p>The model number of the drive.</p> |DEPENDENT |dell.server.hw.physicaldisk.model[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Model`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Physical disks |Dell R740: {#DISK_NAME} Media type |<p>The type of media contained in this drive. Possible values: HDD, SSD, SMR, null.</p> |DEPENDENT |dell.server.hw.physicaldisk.media_type[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.MediaType`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Physical disks |Dell R740: {#DISK_NAME} Size |<p>The size, in bytes, of this drive.</p> |DEPENDENT |dell.server.hw.physicaldisk.size[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.CapacityBytes`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Power supply |Dell R740: {#SENSOR_NAME} Status |<p>The status of the job. Possible values: OK, Warning, Critical.</p> |DEPENDENT |dell.server.sensor.psu.status[{#SENSOR_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Status.Health`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |Dell R740: Overall system health status |<p>This attribute defines the overall rollup status of all components in the system being monitored by the remote access card. Includes system, storage, IO devices, iDRAC, CPU, memory, etc.</p> |DEPENDENT |dell.server.status<p>**Preprocessing**:</p><p>- JSONPATH: `$.status`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |Dell R740: Redfish API |<p>The availability of Redfish API on the server.</p><p>Possible values:</p><p>  0 unavailable</p><p>  1 available</p> |SIMPLE |net.tcp.service[https] |
|Temperature |Dell R740: {#SENSOR_NAME} Value |<p>The sensor value.</p> |DEPENDENT |dell.server.sensor.temp.value[{#SENSOR_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Reading`</p> |
|Temperature |Dell R740: {#SENSOR_NAME} Status |<p>The status of the job. Possible values: OK, Warning, Critical.</p> |DEPENDENT |dell.server.sensor.temp.status[{#SENSOR_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Status.Health`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Virtual disks |Dell R740: {#DISK_NAME} Status |<p>The status of the job. Possible values: OK, Warning, Critical.</p> |DEPENDENT |dell.server.hw.virtualdisk.status[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Status.Health`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Virtual disks |Dell R740: {#DISK_NAME} RAID status |<p>This property represents the RAID specific status. Possible values: Blocked, Degraded, Failed, Foreign, Offline, Online, Ready, Unknown, null.</p> |DEPENDENT |dell.server.hw.virtualdisk.raidstatus[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Oem.Dell.DellVirtualDisk.RaidStatus`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Virtual disks |Dell R740: {#DISK_NAME} Size |<p>The size in bytes of this Volume.</p> |DEPENDENT |dell.server.hw.virtualdisk.size[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.CapacityBytes`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Virtual disks |Dell R740: {#DISK_NAME} Current state |<p>The known state of the Resource, for example, enabled. Possible values: Enabled, Disabled, StandbyOffline, StandbySpare, InTest, Starting, Absent, UnavailableOffline, Deferring, Quiesced, Updating, Qualified.</p> |DEPENDENT |dell.server.hw.virtualdisk.state[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Status.State`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Virtual disks |Dell R740: {#DISK_NAME} Read policy |<p>Indicates the read cache policy setting for the Volume. Possible values: ReadAhead, AdaptiveReadAhead, Off.</p> |DEPENDENT |dell.server.hw.virtualdisk.readpolicy[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Oem.Dell.DellVirtualDisk.ReadCachePolicy`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Virtual disks |Dell R740: {#DISK_NAME} Write policy |<p>Indicates the write cache policy setting for the Volume. Possible values: WriteThrough, ProtectedWriteBack, UnprotectedWriteBack.</p> |DEPENDENT |dell.server.hw.virtualdisk.writepolicy[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Oem.Dell.DellVirtualDisk.WriteCachePolicy`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |Dell R740: Get system |<p>Returns the metrics of a system.</p> |HTTP_AGENT |dell.server.system.get<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Zabbix raw items |Dell R740: {#SENSOR_NAME} Get sensor |<p>Returns the metrics of a sensor.</p> |HTTP_AGENT |dell.server.sensor.temp.get[{#SENSOR_NAME}] |
|Zabbix raw items |Dell R740: {#SENSOR_NAME} Get sensor |<p>Returns the metrics of a sensor.</p> |HTTP_AGENT |dell.server.sensor.psu.get[{#SENSOR_NAME}] |
|Zabbix raw items |Dell R740: {#SENSOR_NAME} Get sensor |<p>Returns the metrics of a sensor.</p> |HTTP_AGENT |dell.server.sensor.fan.get[{#SENSOR_NAME}] |
|Zabbix raw items |Dell R740: {#CNTLR_NAME} in slot {#SLOT} Status |<p>The status of the job. Possible values: OK, Warning, Critical.</p> |HTTP_AGENT |dell.server.hw.diskarray.status[{#CNTLR_NAME}{#SLOT}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Status.Health`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |Dell R740: {#BATTERY_NAME} Status |<p>The status of the job. Possible values: OK, Warning, Critical.</p> |HTTP_AGENT |dell.server.hw.diskarray.cache.battery.status[{#BATTERY_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Oem.Dell.DellControllerBattery.PrimaryStatus`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix raw items |Dell R740: {#DISK_NAME} Get disk |<p>Returns the metrics of a physical disk.</p> |HTTP_AGENT |dell.server.hw.physicaldisk.get[{#DISK_NAME}] |
|Zabbix raw items |Dell R740: {#DISK_NAME} Get disk |<p>Returns the metrics of a virtual disk.</p> |HTTP_AGENT |dell.server.hw.virtualdisk.get[{#DISK_NAME}] |
|Zabbix raw items |Dell R740: {#IFNAME} Get interface |<p>Returns the metrics of a network interface.</p> |HTTP_AGENT |dell.server.net.if.get[{#IFNAME}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Dell R740: {#SENSOR_NAME} is in a critical state |<p>Please check the device for faults.</p> |`find(/DELL PowerEdge R740 by HTTP/dell.server.sensor.fan.status[{#SENSOR_NAME}],,"like","Critical")=1` |HIGH | |
|Dell R740: {#SENSOR_NAME} is in warning state |<p>Please check the device for warnings.</p> |`find(/DELL PowerEdge R740 by HTTP/dell.server.sensor.fan.status[{#SENSOR_NAME}],,"like","Warning")=1` |WARNING |<p>**Depends on**:</p><p>- Dell R740: {#SENSOR_NAME} is in a critical state</p> |
|Dell R740: Device has been replaced |<p>Device serial number has changed. Ack to close</p> |`last(/DELL PowerEdge R740 by HTTP/dell.server.hw.serialnumber,#1)<>last(/DELL PowerEdge R740 by HTTP/dell.server.hw.serialnumber,#2) and length(last(/DELL PowerEdge R740 by HTTP/dell.server.hw.serialnumber))>0` |INFO |<p>Manual close: YES</p> |
|Dell R740: Firmware has changed |<p>Firmware version has changed. Ack to close.</p> |`last(/DELL PowerEdge R740 by HTTP/dell.server.hw.firmware,#1)<>last(/DELL PowerEdge R740 by HTTP/dell.server.hw.firmware,#2) and length(last(/DELL PowerEdge R740 by HTTP/dell.server.hw.firmware))>0` |INFO |<p>Manual close: YES</p> |
|Dell R740: {#IFNAME} Link down |<p>This trigger expression works as follows:</p><p>1. Can be triggered if operations status is down.</p><p>2. {$IFCONTROL:"{#IFNAME}"}=1 - user can redefine Context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.</p><p>3. Condition of difference between last and previous value - trigger fires only if operational status was up(1) sometime before. (So, do not fire 'ethernal off' interfaces.)</p><p>WARNING: if closed manually - won't fire again on next poll, because of .diff.</p> |`{$IFCONTROL:"{#IFNAME}"}=1 and (find(/DELL PowerEdge R740 by HTTP/dell.server.net.if.status[{#IFNAME}],,"like")="Down" and last(/DELL PowerEdge R740 by HTTP/dell.server.net.if.status[{#IFNAME}],#1)<>last(/DELL PowerEdge R740 by HTTP/dell.server.net.if.status[{#IFNAME}],#2))`<p>Recovery expression:</p>`find(/DELL PowerEdge R740 by HTTP/dell.server.net.if.status[{#IFNAME}],,"like")<>"Down" or {$IFCONTROL:"{#IFNAME}"}=0` |AVERAGE |<p>Manual close: YES</p> |
|Dell R740: {#IFNAME} is in a critical state |<p>Please check the device for faults.</p> |`find(/DELL PowerEdge R740 by HTTP/dell.server.net.if.health[{#IFNAME}],,"like","Critical")=1` |HIGH | |
|Dell R740: {#IFNAME} is in warning state |<p>Please check the device for warnings.</p> |`find(/DELL PowerEdge R740 by HTTP/dell.server.net.if.health[{#IFNAME}],,"like","Warning")=1` |WARNING |<p>**Depends on**:</p><p>- Dell R740: {#IFNAME} is in a critical state</p> |
|Dell R740: {#DISK_NAME} is in a critical state |<p>Please check the device for faults.</p> |`find(/DELL PowerEdge R740 by HTTP/dell.server.hw.physicaldisk.status[{#DISK_NAME}],,"like","Critical")=1` |HIGH | |
|Dell R740: {#DISK_NAME} is in warning state |<p>Please check the device for warnings.</p> |`find(/DELL PowerEdge R740 by HTTP/dell.server.hw.physicaldisk.status[{#DISK_NAME}],,"like","Warning")=1` |WARNING |<p>**Depends on**:</p><p>- Dell R740: {#DISK_NAME} is in a critical state</p> |
|Dell R740: {#DISK_NAME} has been replaced |<p>{#DISK_NAME} serial number has changed. Ack to close</p> |`last(/DELL PowerEdge R740 by HTTP/dell.server.hw.physicaldisk.serialnumber[{#DISK_NAME}],#1)<>last(/DELL PowerEdge R740 by HTTP/dell.server.hw.physicaldisk.serialnumber[{#DISK_NAME}],#2) and length(last(/DELL PowerEdge R740 by HTTP/dell.server.hw.physicaldisk.serialnumber[{#DISK_NAME}]))>0` |INFO |<p>Manual close: YES</p> |
|Dell R740: {#SENSOR_NAME} is in a critical state |<p>Please check the device for faults.</p> |`find(/DELL PowerEdge R740 by HTTP/dell.server.sensor.psu.status[{#SENSOR_NAME}],,"like","Critical")=1` |HIGH | |
|Dell R740: {#SENSOR_NAME} is in warning state |<p>Please check the device for warnings.</p> |`find(/DELL PowerEdge R740 by HTTP/dell.server.sensor.psu.status[{#SENSOR_NAME}],,"like","Warning")=1` |WARNING |<p>**Depends on**:</p><p>- Dell R740: {#SENSOR_NAME} is in a critical state</p> |
|Dell R740: Server is in a critical state |<p>Please check the device for faults.</p> |`find(/DELL PowerEdge R740 by HTTP/dell.server.status,,"like","Critical")=1` |HIGH | |
|Dell R740: Server is in warning state |<p>Please check the device for warnings.</p> |`find(/DELL PowerEdge R740 by HTTP/dell.server.status,,"like","Warning")=1` |WARNING |<p>**Depends on**:</p><p>- Dell R740: Server is in a critical state</p> |
|Dell R740: Redfish API service is unavailable |<p>The service is unavailable or does not accept TCP connections.</p> |`last(/DELL PowerEdge R740 by HTTP/net.tcp.service[https])=0` |HIGH | |
|Dell R740: {#SENSOR_NAME} is in a critical state |<p>Please check the device for faults.</p> |`find(/DELL PowerEdge R740 by HTTP/dell.server.sensor.temp.status[{#SENSOR_NAME}],,"like","Critical")=1` |HIGH | |
|Dell R740: {#SENSOR_NAME} is in warning state |<p>Please check the device for warnings.</p> |`find(/DELL PowerEdge R740 by HTTP/dell.server.sensor.temp.status[{#SENSOR_NAME}],,"like","Warning")=1` |WARNING |<p>**Depends on**:</p><p>- Dell R740: {#SENSOR_NAME} is in a critical state</p> |
|Dell R740: {#DISK_NAME} is in a critical state |<p>Please check the device for faults.</p> |`find(/DELL PowerEdge R740 by HTTP/dell.server.hw.virtualdisk.status[{#DISK_NAME}],,"like","Critical")=1` |HIGH | |
|Dell R740: {#DISK_NAME} is in warning state |<p>Please check the device for warnings.</p> |`find(/DELL PowerEdge R740 by HTTP/dell.server.hw.virtualdisk.status[{#DISK_NAME}],,"like","Warning")=1` |WARNING |<p>**Depends on**:</p><p>- Dell R740: {#DISK_NAME} is in a critical state</p> |
|Dell R740: {#CNTLR_NAME} in slot {#SLOT} is in a critical state |<p>Please check the device for faults.</p> |`find(/DELL PowerEdge R740 by HTTP/dell.server.hw.diskarray.status[{#CNTLR_NAME}{#SLOT}],,"like","Critical")=1` |HIGH | |
|Dell R740: {#CNTLR_NAME} in slot {#SLOT} is in warning state |<p>Please check the device for warnings.</p> |`find(/DELL PowerEdge R740 by HTTP/dell.server.hw.diskarray.status[{#CNTLR_NAME}{#SLOT}],,"like","Warning")=1` |WARNING |<p>**Depends on**:</p><p>- Dell R740: {#CNTLR_NAME} in slot {#SLOT} is in a critical state</p> |
|Dell R740: {#BATTERY_NAME} is in a critical state |<p>Please check the device for faults.</p> |`find(/DELL PowerEdge R740 by HTTP/dell.server.hw.diskarray.cache.battery.status[{#BATTERY_NAME}],,"like","Critical")=1` |HIGH | |
|Dell R740: {#BATTERY_NAME} is in warning state |<p>Please check the device for warnings.</p> |`find(/DELL PowerEdge R740 by HTTP/dell.server.hw.diskarray.cache.battery.status[{#BATTERY_NAME}],,"like","Warning")=1` |WARNING |<p>**Depends on**:</p><p>- Dell R740: {#BATTERY_NAME} is in a critical state</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/426752-discussion-thread-for-official-zabbix-dell-templates).

