
# DELL PowerEdge R740 by HTTP

## Overview

This is a template for monitoring DELL PowerEdge R740 servers with iDRAC 8/9 firmware 4.32 and later with Redfish API enabled via Zabbix HTTP agent that works without any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- DELL PowerEdge R740

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

1\. Enable Redfish API in Dell iDRAC interface of your server.

2\. Create a user for monitoring with read-only permissions in Dell iDRAC interface.

3\. Create a host for Dell server with iDRAC IP as Zabbix agent interface.

4\. Link the template to the host.

5\. Customize values of {$API.URL}, {$API.USER}, {$API.PASSWORD} macros.



### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$API.URL}|<p>The Dell iDRAC Redfish API URL in the format `<scheme>://<host>:<port>`.</p>|`<Put your URL here>`|
|{$API.USER}|<p>The Dell iDRAC username.</p>|`<Put your username here>`|
|{$API.PASSWORD}|<p>The Dell iDRAC user password.</p>|`<Put your password here>`|
|{$IFCONTROL}|<p>Link status trigger will be fired only for interfaces that have the context macro equaled 1.</p>|`1`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R740: Get system|<p>Returns the metrics of a system.</p>|HTTP agent|dell.server.system.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Dell R740: Overall system health status|<p>This attribute defines the overall rollup status of all components in the system being monitored by the remote access card. Includes system, storage, IO devices, iDRAC, CPU, memory, etc.</p>|Dependent item|dell.server.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: Hardware model name|<p>This attribute defines the model name of the system.</p>|Dependent item|dell.server.hw.model<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.model`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: Hardware serial number|<p>This attribute defines the service tag of the system.</p>|Dependent item|dell.server.hw.serialnumber<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serialnumber`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: Firmware version|<p>This attribute defines the firmware version of a remote access card.</p>|Dependent item|dell.server.hw.firmware<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.firmware`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: Redfish API|<p>The availability of Redfish API on the server.</p><p>Possible values:</p><p>  0 unavailable</p><p>  1 available</p>|Simple check|net.tcp.service[https]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R740: Server is in a critical state|<p>Please check the device for faults.</p>|`find(/DELL PowerEdge R740 by HTTP/dell.server.status,,"like","Critical")=1`|High||
|Dell R740: Server is in warning state|<p>Please check the device for warnings.</p>|`find(/DELL PowerEdge R740 by HTTP/dell.server.status,,"like","Warning")=1`|Warning|**Depends on**:<br><ul><li>Dell R740: Server is in a critical state</li></ul>|
|Dell R740: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/DELL PowerEdge R740 by HTTP/dell.server.hw.serialnumber,#1)<>last(/DELL PowerEdge R740 by HTTP/dell.server.hw.serialnumber,#2) and length(last(/DELL PowerEdge R740 by HTTP/dell.server.hw.serialnumber))>0`|Info|**Manual close**: Yes|
|Dell R740: Firmware has changed|<p>Firmware version has changed. Acknowledge to close the problem manually.</p>|`last(/DELL PowerEdge R740 by HTTP/dell.server.hw.firmware,#1)<>last(/DELL PowerEdge R740 by HTTP/dell.server.hw.firmware,#2) and length(last(/DELL PowerEdge R740 by HTTP/dell.server.hw.firmware))>0`|Info|**Manual close**: Yes|
|Dell R740: Redfish API service is unavailable|<p>The service is unavailable or does not accept TCP connections.</p>|`last(/DELL PowerEdge R740 by HTTP/net.tcp.service[https])=0`|High||

### LLD rule Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature discovery|<p>Discovery of temperature sensors.</p>|HTTP agent|temp.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R740: {#SENSOR_NAME} Get sensor|<p>Returns the metrics of a sensor.</p>|HTTP agent|dell.server.sensor.temp.get[{#SENSOR_NAME}]|
|Dell R740: {#SENSOR_NAME} Value|<p>The sensor value.</p>|Dependent item|dell.server.sensor.temp.value[{#SENSOR_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Reading`</p></li></ul>|
|Dell R740: {#SENSOR_NAME} Status|<p>The status of the job. Possible values: OK, Warning, Critical.</p>|Dependent item|dell.server.sensor.temp.status[{#SENSOR_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Status.Health`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Temperature discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R740: {#SENSOR_NAME} is in a critical state|<p>Please check the device for faults.</p>|`find(/DELL PowerEdge R740 by HTTP/dell.server.sensor.temp.status[{#SENSOR_NAME}],,"like","Critical")=1`|High||
|Dell R740: {#SENSOR_NAME} is in warning state|<p>Please check the device for warnings.</p>|`find(/DELL PowerEdge R740 by HTTP/dell.server.sensor.temp.status[{#SENSOR_NAME}],,"like","Warning")=1`|Warning|**Depends on**:<br><ul><li>Dell R740: {#SENSOR_NAME} is in a critical state</li></ul>|

### LLD rule PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU discovery|<p>Discovery of PSU sensors.</p>|HTTP agent|psu.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R740: {#SENSOR_NAME} Get sensor|<p>Returns the metrics of a sensor.</p>|HTTP agent|dell.server.sensor.psu.get[{#SENSOR_NAME}]|
|Dell R740: {#SENSOR_NAME} Status|<p>The status of the job. Possible values: OK, Warning, Critical.</p>|Dependent item|dell.server.sensor.psu.status[{#SENSOR_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Status.Health`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for PSU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R740: {#SENSOR_NAME} is in a critical state|<p>Please check the device for faults.</p>|`find(/DELL PowerEdge R740 by HTTP/dell.server.sensor.psu.status[{#SENSOR_NAME}],,"like","Critical")=1`|High||
|Dell R740: {#SENSOR_NAME} is in warning state|<p>Please check the device for warnings.</p>|`find(/DELL PowerEdge R740 by HTTP/dell.server.sensor.psu.status[{#SENSOR_NAME}],,"like","Warning")=1`|Warning|**Depends on**:<br><ul><li>Dell R740: {#SENSOR_NAME} is in a critical state</li></ul>|

### LLD rule FAN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN discovery|<p>Discovery of FAN sensors.</p>|HTTP agent|fan.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for FAN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R740: {#SENSOR_NAME} Get sensor|<p>Returns the metrics of a sensor.</p>|HTTP agent|dell.server.sensor.fan.get[{#SENSOR_NAME}]|
|Dell R740: {#SENSOR_NAME} Speed|<p>The sensor value.</p>|Dependent item|dell.server.sensor.fan.speed[{#SENSOR_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Reading`</p></li></ul>|
|Dell R740: {#SENSOR_NAME} Status|<p>The status of the job. Possible values: OK, Warning, Critical.</p>|Dependent item|dell.server.sensor.fan.status[{#SENSOR_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Status.Health`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for FAN discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R740: {#SENSOR_NAME} is in a critical state|<p>Please check the device for faults.</p>|`find(/DELL PowerEdge R740 by HTTP/dell.server.sensor.fan.status[{#SENSOR_NAME}],,"like","Critical")=1`|High||
|Dell R740: {#SENSOR_NAME} is in warning state|<p>Please check the device for warnings.</p>|`find(/DELL PowerEdge R740 by HTTP/dell.server.sensor.fan.status[{#SENSOR_NAME}],,"like","Warning")=1`|Warning|**Depends on**:<br><ul><li>Dell R740: {#SENSOR_NAME} is in a critical state</li></ul>|

### LLD rule Array controller discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Array controller discovery|<p>Discovery of disk array controllers.</p>|HTTP agent|array.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Array controller discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R740: {#CNTLR_NAME} in slot {#SLOT} Status|<p>The status of the job. Possible values: OK, Warning, Critical.</p>|HTTP agent|dell.server.hw.diskarray.status[{#CNTLR_NAME}{#SLOT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Status.Health`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Array controller discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R740: {#CNTLR_NAME} in slot {#SLOT} is in a critical state|<p>Please check the device for faults.</p>|`find(/DELL PowerEdge R740 by HTTP/dell.server.hw.diskarray.status[{#CNTLR_NAME}{#SLOT}],,"like","Critical")=1`|High||
|Dell R740: {#CNTLR_NAME} in slot {#SLOT} is in warning state|<p>Please check the device for warnings.</p>|`find(/DELL PowerEdge R740 by HTTP/dell.server.hw.diskarray.status[{#CNTLR_NAME}{#SLOT}],,"like","Warning")=1`|Warning|**Depends on**:<br><ul><li>Dell R740: {#CNTLR_NAME} in slot {#SLOT} is in a critical state</li></ul>|

### LLD rule Array controller cache discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Array controller cache discovery|<p>Discovery of a cache of disk array controllers.</p>|HTTP agent|array.cache.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Array controller cache discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R740: {#BATTERY_NAME} Status|<p>The status of the job. Possible values: OK, Warning, Critical.</p>|HTTP agent|dell.server.hw.diskarray.cache.battery.status[{#BATTERY_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Oem.Dell.DellControllerBattery.PrimaryStatus`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Array controller cache discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R740: {#BATTERY_NAME} is in a critical state|<p>Please check the device for faults.</p>|`find(/DELL PowerEdge R740 by HTTP/dell.server.hw.diskarray.cache.battery.status[{#BATTERY_NAME}],,"like","Critical")=1`|High||
|Dell R740: {#BATTERY_NAME} is in warning state|<p>Please check the device for warnings.</p>|`find(/DELL PowerEdge R740 by HTTP/dell.server.hw.diskarray.cache.battery.status[{#BATTERY_NAME}],,"like","Warning")=1`|Warning|**Depends on**:<br><ul><li>Dell R740: {#BATTERY_NAME} is in a critical state</li></ul>|

### LLD rule Physical disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Physical disk discovery|<p>Discovery of physical disks.</p>|HTTP agent|physicaldisk.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Physical disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R740: {#DISK_NAME} Get disk|<p>Returns the metrics of a physical disk.</p>|HTTP agent|dell.server.hw.physicaldisk.get[{#DISK_NAME}]|
|Dell R740: {#DISK_NAME} Status|<p>The status of the job. Possible values: OK, Warning, Critical.</p>|Dependent item|dell.server.hw.physicaldisk.status[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Status.Health`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#DISK_NAME} Serial number|<p>The serial number of this drive.</p>|Dependent item|dell.server.hw.physicaldisk.serialnumber[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SerialNumber`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#DISK_NAME} Model name|<p>The model number of the drive.</p>|Dependent item|dell.server.hw.physicaldisk.model[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Model`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#DISK_NAME} Media type|<p>The type of media contained in this drive. Possible values: HDD, SSD, SMR, null.</p>|Dependent item|dell.server.hw.physicaldisk.media_type[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.MediaType`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#DISK_NAME} Size|<p>The size, in bytes, of this drive.</p>|Dependent item|dell.server.hw.physicaldisk.size[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CapacityBytes`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Physical disk discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R740: {#DISK_NAME} is in a critical state|<p>Please check the device for faults.</p>|`find(/DELL PowerEdge R740 by HTTP/dell.server.hw.physicaldisk.status[{#DISK_NAME}],,"like","Critical")=1`|High||
|Dell R740: {#DISK_NAME} is in warning state|<p>Please check the device for warnings.</p>|`find(/DELL PowerEdge R740 by HTTP/dell.server.hw.physicaldisk.status[{#DISK_NAME}],,"like","Warning")=1`|Warning|**Depends on**:<br><ul><li>Dell R740: {#DISK_NAME} is in a critical state</li></ul>|
|Dell R740: {#DISK_NAME} has been replaced|<p>{#DISK_NAME} serial number has changed. Acknowledge to close the problem manually.</p>|`last(/DELL PowerEdge R740 by HTTP/dell.server.hw.physicaldisk.serialnumber[{#DISK_NAME}],#1)<>last(/DELL PowerEdge R740 by HTTP/dell.server.hw.physicaldisk.serialnumber[{#DISK_NAME}],#2) and length(last(/DELL PowerEdge R740 by HTTP/dell.server.hw.physicaldisk.serialnumber[{#DISK_NAME}]))>0`|Info|**Manual close**: Yes|

### LLD rule Virtual disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Virtual disk discovery|<p>Discovery of virtual disks.</p>|HTTP agent|virtualdisk.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Virtual disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R740: {#DISK_NAME} Get disk|<p>Returns the metrics of a virtual disk.</p>|HTTP agent|dell.server.hw.virtualdisk.get[{#DISK_NAME}]|
|Dell R740: {#DISK_NAME} Status|<p>The status of the job. Possible values: OK, Warning, Critical.</p>|Dependent item|dell.server.hw.virtualdisk.status[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Status.Health`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#DISK_NAME} RAID status|<p>This property represents the RAID specific status. Possible values: Blocked, Degraded, Failed, Foreign, Offline, Online, Ready, Unknown, null.</p>|Dependent item|dell.server.hw.virtualdisk.raidstatus[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Oem.Dell.DellVirtualDisk.RaidStatus`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#DISK_NAME} Size|<p>The size in bytes of this Volume.</p>|Dependent item|dell.server.hw.virtualdisk.size[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CapacityBytes`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#DISK_NAME} Current state|<p>The known state of the Resource, for example, enabled. Possible values: Enabled, Disabled, StandbyOffline, StandbySpare, InTest, Starting, Absent, UnavailableOffline, Deferring, Quiesced, Updating, Qualified.</p>|Dependent item|dell.server.hw.virtualdisk.state[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Status.State`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#DISK_NAME} Read policy|<p>Indicates the read cache policy setting for the Volume. Possible values: ReadAhead, AdaptiveReadAhead, Off.</p>|Dependent item|dell.server.hw.virtualdisk.readpolicy[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Oem.Dell.DellVirtualDisk.ReadCachePolicy`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#DISK_NAME} Write policy|<p>Indicates the write cache policy setting for the Volume. Possible values: WriteThrough, ProtectedWriteBack, UnprotectedWriteBack.</p>|Dependent item|dell.server.hw.virtualdisk.writepolicy[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Oem.Dell.DellVirtualDisk.WriteCachePolicy`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Virtual disk discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R740: {#DISK_NAME} is in a critical state|<p>Please check the device for faults.</p>|`find(/DELL PowerEdge R740 by HTTP/dell.server.hw.virtualdisk.status[{#DISK_NAME}],,"like","Critical")=1`|High||
|Dell R740: {#DISK_NAME} is in warning state|<p>Please check the device for warnings.</p>|`find(/DELL PowerEdge R740 by HTTP/dell.server.hw.virtualdisk.status[{#DISK_NAME}],,"like","Warning")=1`|Warning|**Depends on**:<br><ul><li>Dell R740: {#DISK_NAME} is in a critical state</li></ul>|

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>The NetworkInterface schema describes links to the NetworkAdapter and represents the functionality available to the containing system.</p>|HTTP agent|net.if.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Dell R740: {#IFNAME} Get interface|<p>Returns the metrics of a network interface.</p>|HTTP agent|dell.server.net.if.get[{#IFNAME}]|
|Dell R740: {#IFNAME} Speed|<p>Network port current link speed.</p>|Dependent item|dell.server.net.if.speed[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CurrentLinkSpeedMbps`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#IFNAME} Link status|<p>The status of the link between this port and its link partner. Possible values: Down, Up, null.</p>|Dependent item|dell.server.net.if.status[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.LinkStatus`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#IFNAME} State|<p>The known state of the Resource, for example, enabled. Possible values: Enabled, Disabled, StandbyOffline, StandbySpare, InTest, Starting, Absent, UnavailableOffline, Deferring, Quiesced, Updating, Qualified.</p>|Dependent item|dell.server.net.if.state[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Status.State`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Dell R740: {#IFNAME} Status|<p>The status of the job. Possible values: OK, Warning, Critical.</p>|Dependent item|dell.server.net.if.health[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Status.Health`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R740: {#IFNAME} Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, do not fire for the 'eternal off' interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll, because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and (find(/DELL PowerEdge R740 by HTTP/dell.server.net.if.status[{#IFNAME}],,"like")="Down" and last(/DELL PowerEdge R740 by HTTP/dell.server.net.if.status[{#IFNAME}],#1)<>last(/DELL PowerEdge R740 by HTTP/dell.server.net.if.status[{#IFNAME}],#2))`|Average|**Manual close**: Yes|
|Dell R740: {#IFNAME} is in a critical state|<p>Please check the device for faults.</p>|`find(/DELL PowerEdge R740 by HTTP/dell.server.net.if.health[{#IFNAME}],,"like","Critical")=1`|High||
|Dell R740: {#IFNAME} is in warning state|<p>Please check the device for warnings.</p>|`find(/DELL PowerEdge R740 by HTTP/dell.server.net.if.health[{#IFNAME}],,"like","Warning")=1`|Warning|**Depends on**:<br><ul><li>Dell R740: {#IFNAME} is in a critical state</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

