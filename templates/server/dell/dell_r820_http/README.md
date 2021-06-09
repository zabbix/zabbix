
# DELL PowerEdge R820 by HTTP

## Overview

For Zabbix version: 5.0 and higher  
This is a template for monitoring DELL PowerEdge R820 servers with iDRAC version 7 and later via Zabbix HTTP Agent that works without any external scripts.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/5.0/manual/config/templates_out_of_the_box/http) for basic instructions.

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$API.PASSWORD} |<p>The Dell iDRAC user password.</p> |`<Put your password here>` |
|{$API.URL} |<p>The Dell iDRAC Redfish API URL in the format `<scheme>://<host>:<port>`.</p> |`<Put your URL here>` |
|{$API.USER} |<p>The Dell iDRAC username.</p> |`<Put your username here>` |
|{$DISK.ARRAY.CACHE.BATTERY.STATUS.CRIT} |<p>The critical status of the disk array cache battery for trigger expression.</p> |`3` |
|{$DISK.ARRAY.CACHE.BATTERY.STATUS.OK} |<p>The OK status of the disk array cache battery for trigger expression.</p> |`2` |
|{$DISK.ARRAY.CACHE.BATTERY.STATUS.WARN} |<p>The warning status of the disk array cache battery for trigger expression.</p> |`4` |
|{$DISK.ARRAY.STATUS.CRIT} |<p>The critical status of the disk array for trigger expression.</p> |`5` |
|{$DISK.ARRAY.STATUS.FAIL} |<p>The disaster status of the disk array for trigger expression.</p> |`6` |
|{$DISK.ARRAY.STATUS.WARN} |<p>The warning status of the disk array for trigger expression.</p> |`4` |
|{$DISK.SMART.STATUS.FAIL} |<p>The critical S.M.A.R.T status of the disk for trigger expression.</p> |`1` |
|{$DISK.STATUS.FAIL:"critical"} |<p>The critical status of the disk for trigger expression.</p> |`5` |
|{$DISK.STATUS.FAIL:"nonRecoverable"} |<p>The critical status of the disk for trigger expression.</p> |`6` |
|{$DISK.STATUS.WARN:"nonCritical"} |<p>The warning status of the disk for trigger expression.</p> |`4` |
|{$FAN.STATUS.CRIT:"criticalLower"} |<p>The critical value of the FAN sensor for trigger expression.</p> |`8` |
|{$FAN.STATUS.CRIT:"criticalUpper"} |<p>The critical value of the FAN sensor for trigger expression.</p> |`5` |
|{$FAN.STATUS.CRIT:"failed"} |<p>The critical value of the FAN sensor for trigger expression.</p> |`10` |
|{$FAN.STATUS.CRIT:"nonRecoverableLower"} |<p>The critical value of the FAN sensor for trigger expression.</p> |`9` |
|{$FAN.STATUS.CRIT:"nonRecoverableUpper"} |<p>The critical value of the FAN sensor for trigger expression.</p> |`6` |
|{$FAN.STATUS.WARN:"nonCriticalLower"} |<p>The warning value of the FAN sensor for trigger expression.</p> |`7` |
|{$FAN.STATUS.WARN:"nonCriticalUpper"} |<p>The warning value of the FAN sensor for trigger expression.</p> |`4` |
|{$HEALTH.STATUS.CRIT} |<p>The critical status of the health for trigger expression.</p> |`5` |
|{$HEALTH.STATUS.DISASTER} |<p>The disaster status of the health for trigger expression.</p> |`6` |
|{$HEALTH.STATUS.WARN} |<p>The warning status of the health for trigger expression.</p> |`4` |
|{$PSU.STATUS.CRIT:"critical"} |<p>The critical value of the PSU sensor for trigger expression.</p> |`5` |
|{$PSU.STATUS.CRIT:"nonRecoverable"} |<p>The critical value of the PSU sensor for trigger expression.</p> |`6` |
|{$PSU.STATUS.WARN:"nonCritical"} |<p>The warning value of the PSU sensor for trigger expression.</p> |`4` |
|{$SENSOR.TEMP.STATUS.CRIT:"criticalLower"} |<p>The critical status of the temperature probe for trigger expression.</p> |`8` |
|{$SENSOR.TEMP.STATUS.CRIT:"criticalUpper"} |<p>The critical status of the temperature probe for trigger expression.</p> |`5` |
|{$SENSOR.TEMP.STATUS.CRIT:"nonRecoverableLower"} |<p>The critical status of the temperature probe for trigger expression.</p> |`9` |
|{$SENSOR.TEMP.STATUS.CRIT:"nonRecoverableUpper"} |<p>The critical status of the temperature probe for trigger expression.</p> |`6` |
|{$SENSOR.TEMP.STATUS.OK} |<p>The OK status of the temperature probe for trigger expression.</p> |`3` |
|{$SENSOR.TEMP.STATUS.WARN:"nonCriticalLower"} |<p>The warning status of the temperature probe for trigger expression.</p> |`7` |
|{$SENSOR.TEMP.STATUS.WARN:"nonCriticalUpper"} |<p>The warning status of the temperature probe for trigger expression.</p> |`4` |
|{$SNMP.TIMEOUT} |<p>The time interval for SNMP agent availability trigger expression.</p> |`5m` |
|{$VDISK.STATUS.CRIT:"failed"} |<p>The critical status of the virtual disk for trigger expression.</p> |`3` |
|{$VDISK.STATUS.WARN:"degraded"} |<p>The warning status of the virtual disk for trigger expression.</p> |`4` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Temperature discovery |<p>Discovery of temperature sensors.</p> |HTTP_AGENT |temp.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|PSU discovery |<p>Discovery of PSU sensors.</p> |HTTP_AGENT |psu.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|FAN discovery |<p>Discovery of FAN sensors.</p> |HTTP_AGENT |fan.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Array controller discovery |<p>Discovery of disk array controllers.</p> |HTTP_AGENT |array.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Array controller cache discovery |<p>Discovery of a cache of disk array controllers.</p> |HTTP_AGENT |array.cache.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Physical disk discovery |<p>Discovery of physical disks.</p> |HTTP_AGENT |physicaldisk.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Virtual disk discovery |<p>Discovery of virtual disks.</p> |HTTP_AGENT |virtualdisk.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network interface discovery |<p>The NetworkInterface schema describes links to the NetworkAdapter and represents the functionality available to the containing system.</p> |HTTP_AGENT |net.if.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Fans |Dell R820: {#SENSOR_NAME} Status |<p>The sensor value.</p> |DEPENDENT |dell.server.sensor.fan.speed[{#SENSOR_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Reading`</p> |
|Fans |Dell R820: {#SENSOR_NAME} Status |<p>The status of the job. Possible value: OK, Warning, Critical.</p> |DEPENDENT |dell.server.sensor.fan.status[{#SENSOR_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Status.Health`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Inventory |Dell R820: Hardware model name |<p>This attribute defines the model name of the system.</p> |DEPENDENT |dell.server.hw.model<p>**Preprocessing**:</p><p>- JSONPATH: `$.model`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Inventory |Dell R820: Hardware serial number |<p>This attribute defines the service tag of the system.</p> |DEPENDENT |dell.server.hw.serialnumber<p>**Preprocessing**:</p><p>- JSONPATH: `$.serialnumber`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Inventory |Dell R820: Firmware version |<p>This attribute defines the firmware version of a remote access card.</p> |DEPENDENT |dell.server.hw.firmware<p>**Preprocessing**:</p><p>- JSONPATH: `$.firmware`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network_interfaces |Dell R820: {#NETIF} Speed |<p>Network port current link speed.</p> |DEPENDENT |dell.server.net.if.speed[{#NETIF}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.CurrentLinkSpeedMbps`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network_interfaces |Dell R820: {#NETIF} Link status |<p>The status of the link between this port and its link partner. Possible value: Down, Up, null.</p> |DEPENDENT |dell.server.net.if.status[{#NETIF}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.LinkStatus`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network_interfaces |Dell R820: {#NETIF} State |<p>The known state of the Resource, such as, enabled. Possible value: Enabled, Disabled, StandbyOffline, StandbySpare, InTest, Starting, Absent, UnavailableOffline, Deferring, Quiesced, Updating, Qualified.</p> |DEPENDENT |dell.server.net.if.state[{#NETIF}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Status.State`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network_interfaces |Dell R820: {#NETIF} Health |<p>The status of the job. Possible value: OK, Warning, Critical.</p> |DEPENDENT |dell.server.net.if.health[{#NETIF}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Status.Health`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Physical_disks |Dell R820: {#DISK_NAME} Status |<p>The status of the job. Possible value: OK, Warning, Critical.</p> |DEPENDENT |dell.server.hw.physicaldisk.status[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Status.Health`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Physical_disks |Dell R820: {#DISK_NAME} Serial number |<p>The serial number for this drive.</p> |DEPENDENT |dell.server.hw.physicaldisk.serialnumber[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.SerialNumber`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Physical_disks |Dell R820: {#DISK_NAME} Model name |<p>The model number for the drive.</p> |DEPENDENT |dell.server.hw.physicaldisk.model[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Model`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Physical_disks |Dell R820: {#DISK_NAME} Media type |<p>The type of media contained in this drive. Possible value: HDD, SSD, SMR, null.</p> |DEPENDENT |dell.server.hw.physicaldisk.media_type[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.MediaType`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Physical_disks |Dell R820: {#DISK_NAME} Size |<p>The size, in bytes, of this drive.</p> |DEPENDENT |dell.server.hw.physicaldisk.size[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.CapacityBytes`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Power_supply |Dell R820: {#SENSOR_NAME} Status |<p>The status of the job. Possible value: OK, Warning, Critical.</p> |DEPENDENT |dell.server.sensor.psu.status[{#SENSOR_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Status.Health`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Status |Dell R820: Overall system health status |<p>This attribute defines the overall rollup status of all components in the system being monitored by the remote access card. Includes system, storage, IO devices, iDRAC, CPU, memory, etc.</p> |DEPENDENT |dell.server.status<p>**Preprocessing**:</p><p>- JSONPATH: `$.status`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Temperature |Dell R820: {#SENSOR_NAME} Value |<p>The sensor value.</p> |DEPENDENT |dell.server.sensor.temp.value[{#SENSOR_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Reading`</p> |
|Temperature |Dell R820: {#SENSOR_NAME} Status |<p>The status of the job. Possible value: OK, Warning, Critical.</p> |DEPENDENT |dell.server.sensor.temp.status[{#SENSOR_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Status.Health`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Virtual_disks |Dell R820: {#DISK_NAME} Status |<p>The status of the job. Possible value: OK, Warning, Critical.</p> |DEPENDENT |dell.server.hw.virtualdisk.status[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Status.Health`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Virtual_disks |Dell R820: {#DISK_NAME} Layout type |<p>The RAID type of this volume. Possible value: RAID0, RAID1, RAID3, RAID4, RAID5, RAID6, RAID10, RAID01, RAID6TP, RAID1E, RAID50, RAID60, RAID00, RAID10E, RAID1Triple, RAID10Triple.</p> |DEPENDENT |dell.server.hw.virtualdisk.layout[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.RAIDType`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Virtual_disks |Dell R820: {#DISK_NAME} Size |<p>The size in bytes of this Volume.</p> |DEPENDENT |dell.server.hw.virtualdisk.size[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.CapacityBytes`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Virtual_disks |Dell R820: {#DISK_NAME} Current state |<p>The known state of the Resource, such as, enabled. Possible value: Enabled, Disabled, StandbyOffline, StandbySpare, InTest, Starting, Absent, UnavailableOffline, Deferring, Quiesced, Updating, Qualified.</p> |DEPENDENT |dell.server.hw.virtualdisk.state[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Status.State`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Virtual_disks |Dell R820: {#DISK_NAME} Read policy |<p>Indicates the read cache policy setting for the Volume. Possible value: ReadAhead, AdaptiveReadAhead, Off.</p> |DEPENDENT |dell.server.hw.virtualdisk.readpolicy[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.ReadCachePolicy`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Virtual_disks |Dell R820: {#DISK_NAME} Write policy |<p>Indicates the write cache policy setting for the Volume. Possible value: WriteThrough, ProtectedWriteBack, UnprotectedWriteBack.</p> |DEPENDENT |dell.server.hw.virtualdisk.writepolicy[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.WriteCachePolicy`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix_raw_items |Get system |<p>Returns the metrics of a system.</p> |HTTP_AGENT |dell.server.system.get<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix_raw_items |Dell R820: {#SENSOR_NAME} Get sensor |<p>Returns the metrics of a sensor.</p> |HTTP_AGENT |dell.server.sensor.temp.get[{#SENSOR_NAME}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix_raw_items |Dell R820: {#SENSOR_NAME} Get sensor |<p>Returns the metrics of a sensor.</p> |HTTP_AGENT |dell.server.sensor.psu.get[{#SENSOR_NAME}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix_raw_items |Dell R820: {#SENSOR_NAME} Get sensor |<p>Returns the metrics of a sensor.</p> |HTTP_AGENT |dell.server.sensor.fan.get[{#SENSOR_NAME}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix_raw_items |Dell R820: {#CNTLR_NAME} Status |<p>The status of the job. Possible value: OK, Warning, Critical.</p> |HTTP_AGENT |dell.server.hw.diskarray.status[{#CNTLR_NAME}{#SLOT}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Status.Health`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix_raw_items |Dell R820: {#BATTERY_NAME} Status |<p>The status of the job. Possible value: OK, Warning, Critical.</p> |HTTP_AGENT |dell.server.hw.diskarray.cache.battery.status[{#BATTERY_NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Oem.Dell.DellControllerBattery.PrimaryStatus`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix_raw_items |Dell R820: {#DISK_NAME} Get disk |<p>Returns the metrics of a physical disk.</p> |HTTP_AGENT |dell.server.hw.physicaldisk.get[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix_raw_items |Dell R820: {#DISK_NAME} Get disk |<p>Returns the metrics of a virtual disk.</p> |HTTP_AGENT |dell.server.hw.virtualdisk.get[{#DISK_NAME}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Zabbix_raw_items |Dell R820: {#NETIF} Get interface |<p>Returns the metrics of a network interface.</p> |HTTP_AGENT |dell.server.net.if.get[{#NETIF}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Dell R820: Device has been replaced (new serial number received) |<p>Device serial number has changed. Ack to close</p> |`{TEMPLATE_NAME:dell.server.hw.serialnumber.diff()}=1 and {TEMPLATE_NAME:dell.server.hw.serialnumber.strlen()}>0` |INFO |<p>Manual close: YES</p> |
|Dell R820: Firmware has changed |<p>Firmware version has changed. Ack to close.</p> |`{TEMPLATE_NAME:dell.server.hw.firmware.diff()}=1 and {TEMPLATE_NAME:dell.server.hw.firmware.strlen()}>0` |INFO |<p>Manual close: YES</p> |
|Dell R820: {#DISK_NAME} has been replaced (new serial number received) |<p>{#DISK_NAME} serial number has changed. Ack to close</p> |`{TEMPLATE_NAME:dell.server.hw.physicaldisk.serialnumber[{#DISK_NAME}].diff()}=1 and {TEMPLATE_NAME:dell.server.hw.physicaldisk.serialnumber[{#DISK_NAME}].strlen()}>0` |INFO |<p>Manual close: YES</p> |
|Dell R820: System is in unrecoverable state |<p>Please check the device for faults.</p> |`{TEMPLATE_NAME:dell.server.status.last()}={$HEALTH.STATUS.DISASTER}` |DISASTER | |
|Dell R820: System status is in critical state |<p>Please check the device for errors.</p> |`{TEMPLATE_NAME:dell.server.status.last()}={$HEALTH.STATUS.CRIT}` |HIGH | |
|Dell R820: System status is in warning state |<p>Please check the device for warnings.</p> |`{TEMPLATE_NAME:dell.server.status.last()}={$HEALTH.STATUS.WARN}` |WARNING |<p>**Depends on**:</p><p>- Dell R820: System status is in critical state</p> |
|Dell R820: {#BATTERY_NAME} is in critical state |<p>Please check the device for faults.</p> |`{TEMPLATE_NAME:dell.server.hw.diskarray.cache.battery.status[{#BATTERY_NAME}].last()}={$DISK.ARRAY.CACHE.BATTERY.STATUS.CRIT}` |AVERAGE | |
|Dell R820: {#BATTERY_NAME} is in warning state |<p>Please check the device for faults.</p> |`{TEMPLATE_NAME:dell.server.hw.diskarray.cache.battery.status[{#BATTERY_NAME}].last()}={$DISK.ARRAY.CACHE.BATTERY.STATUS.WARN}` |WARNING |<p>**Depends on**:</p><p>- Dell R820: {#BATTERY_NAME} is in critical state</p> |
|Dell R820: {#BATTERY_NAME} is not in optimal state |<p>Please check the device for faults.</p> |`{TEMPLATE_NAME:dell.server.hw.diskarray.cache.battery.status[{#BATTERY_NAME}].last()}<>{$DISK.ARRAY.CACHE.BATTERY.STATUS.OK}` |WARNING |<p>**Depends on**:</p><p>- Dell R820: {#BATTERY_NAME} is in critical state</p><p>- Dell R820: {#BATTERY_NAME} is in warning state</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

