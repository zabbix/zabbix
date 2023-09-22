
# Cisco SD-WAN by HTTP

## Overview

This template is designed for the effortless deployment of Cisco SD-WAN monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Cisco SD-WAN 20.6.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Put your username and password from Cisco SD-WAN vManage into {$SDWAN.API.USERNAME} and {$SDWAN.API.PASSWORD} macros.
2. Set your Cisco SD-WAN vManage URL as {$SDWAN.API.URL} macro value.

**NOTES**

  The Cisco SD-WAN API token will be generated automatically by the Authentication item every {$SDWAN.AUTH.FREQUENCY}. 
  Don't change the {$SDWAN.AUTH.FREQUENCY} macro value if it's not required.

  The generated Cisco SD-WAN API token and the session ID will be used in all Cisco SD-WAN templates and items.
  These values will be kept in {$SDWAN.AUTH.TOKEN} and {$SDWAN.AUTH.SESSION} macros of each discovered host.

**IMPORTANT**

  Values of {$SDWAN.AUTH.TOKEN} and {$SDWAN.AUTH.SESSION} macros are stored as plain (not secret) text by default.

>Please, refer to the [vendor documentation](https://www.cisco.com/c/en/us/td/docs/routers/sdwan/configuration/sdwan-xe-gs-book/cisco-sd-wan-API-cross-site-request-forgery-prevention.html) about the Cisco SD-WAN REST API Token-Based Authentication.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SDWAN.API.URL}|<p>Cisco SD-WAN Monitor API URL.</p>||
|{$SDWAN.API.USERNAME}|<p>Cisco SD-WAN Monitor API username.</p>||
|{$SDWAN.API.PASSWORD}|<p>Cisco SD-WAN Monitor API password.</p>||
|{$SDWAN.AUTH.FREQUENCY}|<p>The update interval for the Cisco SD-WAN Authentication item, which also equals the access token regeneration request frequency. Check the template documentation notes carefully for more details.</p>|`1h`|
|{$SDWAN.DATA.TIMEOUT}|<p>A response timeout for an API.</p>|`15s`|
|{$SDWAN.DEVICE.NAME.MATCHES}|<p>This macro is used in device discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$SDWAN.DEVICE.NAME.NOT_MATCHES}|<p>This macro is used in device discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$SDWAN.HTTP_PROXY}|<p>HTTP proxy for API requests. You can specify it using the format [protocol://][username[:password]@]proxy.example.com[:port]. See the documentation at https://www.zabbix.com/documentation/7.0/manual/config/items/itemtypes/http</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SD-WAN: Authentication|<p>Cisco SD-WAN authentication with service account parameters and temporary-generated token usage.</p><p>Returns an authentication token and session id; it is required only once and is used for all dependent script items.</p><p>A session will expire after 30 minutes of inactivity or after 24 hours, which is the total lifespan of a session.</p><p>Check the template documentation for the details.</p>|Script|sd_wan.authentication|
|SD-WAN: Authentication item errors|<p>Item for gathering all the data item errors.</p>|Dependent item|sd_wan.auth.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|SD-WAN: Get devices|<p>Item for gathering all devices from Cisco SD-WAN API.</p>|Dependent item|sd_wan.get.devices<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|SD-WAN: Get devices item errors|<p>Item for gathering all the data item errors.</p>|Dependent item|sd_wan.get.devices.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|SD-WAN: Invalid certificates|<p>Number of invalid certificates.</p>|Dependent item|sd_wan.invalid_certificates<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.devices[?(@.cert_valid != "Valid")].length()`</p></li></ul>|
|SD-WAN: Total devices|<p>The total number of all devices.</p>|Dependent item|sd_wan.total.devices<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.devices.length()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|SD-WAN: Number of vEdge devices|<p>The total number of vEdge devices.</p>|Dependent item|sd_wan.vedge.devices<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.devices[?(@.type == "vedge")].length()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|SD-WAN: Number of vBond devices|<p>The total number of vBond devices.</p>|Dependent item|sd_wan.vbond.devices<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.devices[?(@.type == "vbond")].length()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|SD-WAN: Number of vSmart devices|<p>The total number of vSmart devices.</p>|Dependent item|sd_wan.vsmart.devices<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.devices[?(@.type == "vsmart")].length()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|SD-WAN: Number of vManage devices|<p>The total number of vManage devices.</p>|Dependent item|sd_wan.vmanage.devices<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.devices[?(@.type == "vmanage")].length()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|SD-WAN: Authentication has failed||`length(last(/Cisco SD-WAN by HTTP/sd_wan.auth.errors))>0`|Average||
|SD-WAN: There are errors in the 'Get devices' metric||`length(last(/Cisco SD-WAN by HTTP/sd_wan.get.devices.errors))>0`|Warning||

### LLD rule Devices discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Devices discovery|<p>Discovering devices from Cisco SD-WAN API.</p>|Dependent item|sd_wan.devices.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.devices`</p></li></ul>|

# Cisco SD-WAN device by HTTP

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SDWAN.API.URL}|<p>Cisco SD-WAN Monitor API URL.</p>||
|{$SDWAN.TOKEN}|<p>Cisco SD-WAN Monitor API token.</p>||
|{$SDWAN.DATA.TIMEOUT}|<p>Response timeout for an API.</p>|`15s`|
|{$SDWAN.CPU.UTIL.CRIT}|<p>Critical threshold of the CPU utilization, expressed in %.</p>|`90`|
|{$SDWAN.MEMORY.UTIL.MAX}|<p>Critical threshold of the memory utilization, expressed in %.</p>|`90`|
|{$SDWAN.MEMORY.AVAILABLE.MIN}|<p>This macro is used as a threshold in the memory available trigger.</p>|`100K`|
|{$SDWAN.IF.UTIL.MAX}|<p>This macro is used as a threshold in the interface utilization trigger. Can be used with the interface name as context.</p>|`90`|
|{$SDWAN.IF.ERRORS.WARN}|<p>Threshold of the error packets rate for the warning trigger. Can be used with the interface name as context.</p>|`2`|
|{$SDWAN.FS.PUSED.MAX.CRIT}|<p>Critical threshold of the filesystem utilization. Can be used with the filesystem name as context.</p>|`90`|
|{$SDWAN.FS.PUSED.MAX.WARN}|<p>Warning threshold of the filesystem utilization. Can be used with the filesystem name as context.</p>|`80`|
|{$SDWAN.LA.PER.CPU.MAX.WARN}|<p>Load per CPU considered sustainable. Tune if needed.</p>|`1.5`|
|{$SDWAN.LLD.FILTER.FSNAME.MATCHES}|<p>Filter of discoverable filesystems by name.</p>|`.*`|
|{$SDWAN.LLD.FILTER.FSNAME.NOT_MATCHES}|<p>Filter to exclude discoverable filesystems by name.</p>|`CHANGE_IF_NEEDED`|
|{$SDWAN.LLD.FILTER.IFNAME.MATCHES}|<p>Filter of discoverable interfaces by name.</p>|`.*`|
|{$SDWAN.LLD.FILTER.IFNAME.NOT_MATCHES}|<p>Filter to exclude discoverable interfaces by name.</p>|`CHANGE_IF_NEEDED`|
|{$SDWAN.HTTP_PROXY}|<p>HTTP proxy for API requests. You can specify it using the format [protocol://][username[:password]@]proxy.example.com[:port]. See the documentation at https://www.zabbix.com/documentation/7.0/manual/config/items/itemtypes/http</p>||
|{$IFCONTROL}|<p>Macro for operational state of the interface for the link down trigger. Can be used with the interface name as context.</p>|`1`|
|{$SDWAN.ROUTES.FREQUENCY}|<p>Update interval for the Routes item, expressed in hours.</p>|`1h`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SD-WAN: Get interfaces data|<p>Item for gathering device interfaces from Cisco SD-WAN API.</p>|Script|sd_wan.get.interfaces|
|SD-WAN: Device interfaces item errors|<p>Item for gathering errors of the device interfaces.</p>|Dependent item|sd_wan.get.interfaces.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|SD-WAN: Get routes data|<p>Item for gathering device routes from Cisco SD-WAN API.</p>|Script|sd_wan.get.routes|
|SD-WAN: Device routes item errors|<p>Item for gathering errors of the device routes.</p>|Dependent item|sd_wan.get.routes.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|SD-WAN: Get device data|<p>Item for gathering device data from Cisco SD-WAN API.</p>|Script|sd_wan.get.device|
|SD-WAN: Device data item errors|<p>Item for gathering errors of the device item.</p>|Dependent item|sd_wan.get.device.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|SD-WAN: Control connections|<p>The number of control connections.</p>|Dependent item|sd_wan.device.control_conn<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.controlConnections`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Matches regular expression: `^[0-9]+$`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|SD-WAN: Certificate validity|<p>Validity status of the device certificate.</p>|Dependent item|sd_wan.device.certificate_validity<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["certificate-validity"]`</p><p>⛔️Custom on fail: Set value to: `Unknown`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|SD-WAN: Total memory|<p>Total memory, expressed in bytes.</p>|Dependent item|sd_wan.device.memory.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_total`</p></li></ul>|
|SD-WAN: Available memory|<p>The amount of physical memory (in bytes) immediately available for the allocation to a process or for a system use in the device.</p>|Dependent item|sd_wan.device.memory.avail<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_free`</p></li></ul>|
|SD-WAN: Memory (buffers)|<p>The amount of physical memory (in bytes) used by the kernel buffers.</p>|Dependent item|sd_wan.device.memory.buffers<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_buffers`</p></li></ul>|
|SD-WAN: Memory (cached)|<p>The amount of physical memory (in bytes) used by the page cache and slabs.</p>|Dependent item|sd_wan.device.memory.cached<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_cached`</p></li></ul>|
|SD-WAN: Used memory|<p>The amount of physical memory (in bytes) used by applications on the device.</p>|Dependent item|sd_wan.device.memory.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_used`</p></li></ul>|
|SD-WAN: Memory utilization|<p>Calculated percentage of the memory used, in %.</p>|Calculated|sd_wan.device.memory.util|
|SD-WAN: Number of CPUs|<p>The total number of CPU.</p>|Dependent item|sd_wan.device.cpu.num<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_cpu_count`</p></li></ul>|
|SD-WAN: Load average (1m avg)|<p>The average number of processes being or waiting executed over past 1 minute.</p>|Dependent item|sd_wan.device.cpu.load[avg1]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.min1_avg`</p></li></ul>|
|SD-WAN: Load average (5m avg)|<p>The average number of processes being or waiting executed over past 5 minutes.</p>|Dependent item|sd_wan.device.cpu.load[avg5]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.min5_avg`</p></li></ul>|
|SD-WAN: Load average (15m avg)|<p>The average number of processes being or waiting executed over past 15 minutes.</p>|Dependent item|sd_wan.device.cpu.load[avg15]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.min15_avg`</p></li></ul>|
|SD-WAN: CPU idle time|<p>The time the CPU has spent doing nothing.</p>|Dependent item|sd_wan.device.cpu.util[idle]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpu_idle`</p></li></ul>|
|SD-WAN: CPU system time|<p>The time the CPU has spent running the kernel and its processes.</p>|Dependent item|sd_wan.device.cpu.util[system]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpu_system`</p></li></ul>|
|SD-WAN: CPU user time|<p>The time the CPU has spent running users' processes that are not niced.</p>|Dependent item|sd_wan.device.cpu.util[user]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpu_user`</p></li></ul>|
|SD-WAN: CPU utilization|<p>CPU utilization, expressed in %.</p>|Dependent item|sd_wan.device.cpu.util<p>**Preprocessing**</p><ul><li><p>JavaScript: `return (100 - value);`</p></li></ul>|
|SD-WAN: Device reachability|<p>Reachability to the vManager and/or the entire network.</p>|Dependent item|sd_wan.device.reachability<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.reachability`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|SD-WAN: Device state|<p>The device current state.</p>|Dependent item|sd_wan.device.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|SD-WAN: Device state description|<p>The description of the device current state.</p>|Dependent item|sd_wan.device.state_descr<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state_description`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|SD-WAN: Operating system|<p>The device operating system.</p>|Dependent item|sd_wan.device.os<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["device-os"]`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|SD-WAN: Operating system architecture|<p>The architecture of the operating system.</p>|Dependent item|sd_wan.device.arch<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.platform`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|SD-WAN: Device role|<p>The device role in the network.</p>|Dependent item|sd_wan.device.role<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.device_role`</p><p>⛔️Custom on fail: Set value to: `-1`</p></li></ul>|
|SD-WAN: Model name|<p>The model name of the device.</p>|Dependent item|sd_wan.device.model<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["device-model"]`</p></li></ul>|
|SD-WAN: Number of processes|<p>The total number of processes in any state.</p>|Dependent item|sd_wan.device.proc.num<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.procs`</p></li></ul>|
|SD-WAN: Serial Number|<p>The device serial number.</p>|Dependent item|sd_wan.device.serialnumber<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["board-serial"]`</p></li></ul>|
|SD-WAN: System name|<p>The system host name.</p>|Dependent item|sd_wan.device.hostname<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["host-name"]`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|SD-WAN: System uptime|<p>The system uptime is calculated on the basis of boot time.</p>|Dependent item|sd_wan.device.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["uptime-date"]`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|SD-WAN: Version|<p>The version of the device software.</p>|Dependent item|sd_wan.device.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.version`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|SD-WAN: There are errors in the 'Get interfaces data' metric||`length(last(/Cisco SD-WAN device by HTTP/sd_wan.get.interfaces.errors))>0`|Warning||
|SD-WAN: There are errors in the 'Get routes data' metric||`length(last(/Cisco SD-WAN device by HTTP/sd_wan.get.routes.errors))>0`|Warning||
|SD-WAN: There are errors in the 'Get device data' metric||`length(last(/Cisco SD-WAN device by HTTP/sd_wan.get.device.errors))>0`|Warning||
|SD-WAN: Device certificate is invalid||`last(/Cisco SD-WAN device by HTTP/sd_wan.device.certificate_validity)=1`|Warning||
|SD-WAN: Lack of available memory||`max(/Cisco SD-WAN device by HTTP/sd_wan.device.memory.avail,5m)<{$SDWAN.MEMORY.AVAILABLE.MIN} and last(/Cisco SD-WAN device by HTTP/sd_wan.device.memory.total)>0`|Average||
|SD-WAN: High memory utilization|<p>The system is running out of free memory.</p>|`min(/Cisco SD-WAN device by HTTP/sd_wan.device.memory.util,5m)>{$SDWAN.MEMORY.UTIL.MAX}`|Average|**Depends on**:<br><ul><li>SD-WAN: Lack of available memory</li></ul>|
|SD-WAN: Load average is too high|<p>The load average per CPU is too high. The system might be slow to respond.</p>|`min(/Cisco SD-WAN device by HTTP/sd_wan.device.cpu.load[avg1],5m)/last(/Cisco SD-WAN device by HTTP/sd_wan.device.cpu.num)>{$SDWAN.LA.PER.CPU.MAX.WARN} and last(/Cisco SD-WAN device by HTTP/sd_wan.device.cpu.load[avg5])>0 and last(/Cisco SD-WAN device by HTTP/sd_wan.device.cpu.load[avg15])>0`|Average||
|SD-WAN: High CPU utilization|<p>CPU utilization is too high. The system might be slow to respond.</p>|`min(/Cisco SD-WAN device by HTTP/sd_wan.device.cpu.util,5m)>{$SDWAN.CPU.UTIL.CRIT}`|Warning|**Depends on**:<br><ul><li>SD-WAN: Load average is too high</li></ul>|
|SD-WAN: Device is not reachable|<p>Device is not reachable to the vManager and/or the entire network.</p>|`last(/Cisco SD-WAN device by HTTP/sd_wan.device.reachability)<>0`|Warning||
|SD-WAN: Device state is not green|<p>The device current state is not green.</p>|`last(/Cisco SD-WAN device by HTTP/sd_wan.device.state)<>0 and length(last(/Cisco SD-WAN device by HTTP/sd_wan.device.state_descr))>0`|Average||
|SD-WAN: Operating system description has changed|<p>Operating system description has changed. Possible reasons that system has been updated or replaced. Ack to close.</p>|`last(/Cisco SD-WAN device by HTTP/sd_wan.device.os,#1)<>last(/Cisco SD-WAN device by HTTP/sd_wan.device.os,#2) and length(last(/Cisco SD-WAN device by HTTP/sd_wan.device.os))>0`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>SD-WAN: Device has been replaced</li></ul>|
|SD-WAN: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Cisco SD-WAN device by HTTP/sd_wan.device.serialnumber,#1)<>last(/Cisco SD-WAN device by HTTP/sd_wan.device.serialnumber,#2) and length(last(/Cisco SD-WAN device by HTTP/sd_wan.device.serialnumber))>0`|Info|**Manual close**: Yes|
|SD-WAN: System name has changed|<p>System name has changed. Ack to close.</p>|`last(/Cisco SD-WAN device by HTTP/sd_wan.device.hostname,#1)<>last(/Cisco SD-WAN device by HTTP/sd_wan.device.hostname,#2) and length(last(/Cisco SD-WAN device by HTTP/sd_wan.device.hostname))>0`|Info|**Manual close**: Yes|
|SD-WAN: Device has been restarted|<p>The host uptime is less than 10 minutes</p>|`last(/Cisco SD-WAN device by HTTP/sd_wan.device.uptime)<10m`|Info|**Manual close**: Yes|

### LLD rule Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interfaces discovery|<p>Discovering device interfaces from Cisco SD-WAN API.</p>|Dependent item|sd_wan.interfaces.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface ["{#IFNAME}"]: Get data|<p>Item for gathering data for the {#IFNAME} interface.</p>|Dependent item|sd_wan.device.if.get_data["{#IFKEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data[?(@["vdevice-dataKey"] == "{#IFKEY}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Interface ["{#IFNAME}"]: Admin status|<p>Current admin status of the interface.</p>|Dependent item|sd_wan.device.if.adm.status["{#IFKEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["if-admin-status"]`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Interface ["{#IFNAME}"]: Operational status|<p>Current operational status of the interface.</p>|Dependent item|sd_wan.device.if.status["{#IFKEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["if-oper-status"]`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Interface ["{#IFNAME}"]: Speed|<p>Current bandwidth of the interface.</p>|Dependent item|sd_wan.device.if.speed["{#IFKEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["speed-mbps"]`</p></li><li><p>Custom multiplier: `1000000`</p></li></ul>|
|Interface ["{#IFNAME}"]: Bits received|<p>The total number of octets received on the interface.</p>|Dependent item|sd_wan.device.if.in["{#IFKEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["rx-octets"]`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface ["{#IFNAME}"]: Bits sent|<p>The total number of octets transmitted out of the interface.</p>|Dependent item|sd_wan.device.if.out["{#IFKEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["tx-octets"]`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface ["{#IFNAME}"]: Inbound packets discarded|<p>The number of inbound packets that were chosen to be discarded.</p>|Dependent item|sd_wan.device.if.in.discards["{#IFKEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["rx-drops"]`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Interface ["{#IFNAME}"]: Inbound IPv6 packets discarded|<p>The number of inbound IPv6 packets that were chosen to be discarded.</p>|Dependent item|sd_wan.device.if.in.v6.discards["{#IFKEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["ipv6-rx-drops"]`</p><p>⛔️Custom on fail: Set value to: `-1`</p></li></ul>|
|Interface ["{#IFNAME}"]: Inbound packets with errors|<p>The number of inbound packets that were contain errors.</p>|Dependent item|sd_wan.device.if.in.errors["{#IFKEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["rx-errors"]`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Interface ["{#IFNAME}"]: Inbound IPv6 packets with errors|<p>The number of inbound IPv4 packets that were contain errors.</p>|Dependent item|sd_wan.device.if.in.v6.errors["{#IFKEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["ipv6-rx-errors"]`</p><p>⛔️Custom on fail: Set value to: `-1`</p></li></ul>|
|Interface ["{#IFNAME}"]: Outbound packets discarded|<p>The number of outbound packets that were chosen to be discarded.</p>|Dependent item|sd_wan.device.if.out.discards["{#IFKEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["tx-drops"]`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Interface ["{#IFNAME}"]: Outbound IPv6 packets discarded|<p>The number of outbound IPv6 packets that were chosen to be discarded.</p>|Dependent item|sd_wan.device.if.out.v6.discards["{#IFKEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["ipv6-tx-drops"]`</p><p>⛔️Custom on fail: Set value to: `-1`</p></li></ul>|
|Interface ["{#IFNAME}"]: Outbound packets with errors|<p>The number of outbound packets that were contain errors.</p>|Dependent item|sd_wan.device.if.out.errors["{#IFKEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["tx-errors"]`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Interface ["{#IFNAME}"]: Outbound IPv6 packets with errors|<p>The number of outbound IPv6 packets that were contain errors.</p>|Dependent item|sd_wan.device.if.out.v6.errors["{#IFKEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["ipv6-tx-errors"]`</p><p>⛔️Custom on fail: Set value to: `-1`</p></li></ul>|

### Trigger prototypes for Network interfaces discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Interface ["{#IFNAME}"]: Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operational status is down.<br>2. `{$IFCONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, it does not fire for the 'eternal off' interfaces).<br><br>WARNING: If closed manually, it will not fire again on the next poll because of .diff.</p>|`{$IFCONTROL:"{#IFNAME}"}=1 and last(/Cisco SD-WAN device by HTTP/sd_wan.device.if.status["{#IFKEY}"])=1 and (last(/Cisco SD-WAN device by HTTP/sd_wan.device.if.status["{#IFKEY}"],#1)<>last(/Cisco SD-WAN device by HTTP/sd_wan.device.if.status["{#IFKEY}"],#2))`|Average|**Manual close**: Yes|
|Interface ["{#IFNAME}"]: Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/Cisco SD-WAN device by HTTP/sd_wan.device.if.speed["{#IFKEY}"])<0 and last(/Cisco SD-WAN device by HTTP/sd_wan.device.if.speed["{#IFKEY}"])>0 and last(/Cisco SD-WAN device by HTTP/sd_wan.device.if.status["{#IFKEY}"])<>0`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface ["{#IFNAME}"]: Link down</li></ul>|
|Interface ["{#IFNAME}"]: High bandwidth usage|<p>The network interface utilization is close to its estimated maximum bandwidth.</p>|`(avg(/Cisco SD-WAN device by HTTP/sd_wan.device.if.in["{#IFKEY}"],15m)>({$SDWAN.IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Cisco SD-WAN device by HTTP/sd_wan.device.if.speed["{#IFKEY}"]) or avg(/Cisco SD-WAN device by HTTP/sd_wan.device.if.out["{#IFKEY}"],15m)>({$SDWAN.IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/Cisco SD-WAN device by HTTP/sd_wan.device.if.speed["{#IFKEY}"])) and last(/Cisco SD-WAN device by HTTP/sd_wan.device.if.speed["{#IFKEY}"])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface ["{#IFNAME}"]: Link down</li></ul>|
|Interface ["{#IFNAME}"]: High error rate|<p>It recovers when it is below 80% of the `{$SDWAN.IF.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Cisco SD-WAN device by HTTP/sd_wan.device.if.in.errors["{#IFKEY}"],5m)>{$SDWAN.IF.ERRORS.WARN:"{#IFNAME}"} or min(/Cisco SD-WAN device by HTTP/sd_wan.device.if.out.errors["{#IFKEY}"],5m)>{$SDWAN.IF.ERRORS.WARN:"{#IFNAME}"} or min(/Cisco SD-WAN device by HTTP/sd_wan.device.if.in.v6.errors["{#IFKEY}"],5m)>{$SDWAN.IF.ERRORS.WARN:"{#IFNAME}"} or min(/Cisco SD-WAN device by HTTP/sd_wan.device.if.out.v6.errors["{#IFKEY}"],5m)>{$SDWAN.IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Interface ["{#IFNAME}"]: Link down</li></ul>|

### LLD rule Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mounted filesystem discovery|<p>Discovering device filesystems from Cisco SD-WAN API.</p>|Dependent item|sd_wan.fs.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|["{#FSNAME}"]: Get data|<p>Item for gathering data for the {#FSNAME} filesystem.</p>|Dependent item|sd_wan.device.fs.get_data["{#FSNAME}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|["{#FSNAME}"]: Total space|<p>The size of the storage pool, in bytes.</p>|Dependent item|sd_wan.device.fs.total["{#FSNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.size`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|["{#FSNAME}"]: Available space|<p>The available size of the storage pool, in bytes.</p>|Dependent item|sd_wan.device.fs.avail["{#FSNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.avail`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|["{#FSNAME}"]: Used space|<p>The used size of the dataset, in bytes.</p>|Dependent item|sd_wan.device.fs.used["{#FSNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|["{#FSNAME}"]: Space utilization|<p>Space utilization, expressed in %.</p>|Dependent item|sd_wan.device.fs.pused["{#FSNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.use`</p></li></ul>|

### Trigger prototypes for Mounted filesystem discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|["{#FSNAME}"]: Disk space is critically low|<p>Utilization of the space is above {$VFS.FS.PUSED.MAX.CRIT:"{{FSNAME}}"}</p>|`last(/Cisco SD-WAN device by HTTP/sd_wan.device.fs.pused["{#FSNAME}"])>{$SDWAN.FS.PUSED.MAX.CRIT:"{#FSNAME}"}`|Average|**Manual close**: Yes|
|["{#FSNAME}"]: Disk space is low|<p>Utilization of the space is above {$VFS.FS.PUSED.MAX.CRIT:"{{FSNAME}}"}</p>|`last(/Cisco SD-WAN device by HTTP/sd_wan.device.fs.pused["{#FSNAME}"])>{$SDWAN.FS.PUSED.MAX.WARN:"{#FSNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>["{#FSNAME}"]: Disk space is critically low</li></ul>|

### LLD rule Route discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Route discovery|<p>Discovering Application-Aware routes from Cisco SD-WAN API.</p>|Dependent item|sd_wan.routes.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li></ul>|

### Item prototypes for Route discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Route [{#LOCAL} => {#REMOTE}]: Get data|<p>Item for gathering data for the route {#LOCAL} => {#REMOTE}.</p>|Dependent item|sd_wan.routes.get_data[{#LOCAL},{#REMOTE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Route [{#LOCAL} => {#REMOTE}]: Latency|<p>The amount of time it takes for a data packet to travel through the route.</p>|Dependent item|sd_wan.routes.latency[{#LOCAL},{#REMOTE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.latency`</p></li></ul>|
|Route [{#LOCAL} => {#REMOTE}]: Jitter|<p>A change in the time it takes for a data packet to travel through the route.</p>|Dependent item|sd_wan.routes.jitter[{#LOCAL},{#REMOTE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.jitter`</p></li></ul>|
|Route [{#LOCAL} => {#REMOTE}]: Loss|<p>Lost packets of data not reached the destination after being transmitted through the route.</p>|Dependent item|sd_wan.routes.loss[{#LOCAL},{#REMOTE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.loss_percentage`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

