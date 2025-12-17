
# FortiGate by HTTP

## Overview

This template is designed for the effortless deployment of FortiGate monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- FortiGate v7.6.4

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. On the FortiGate GUI, select `System > Admin Profiles > Create New`.
2. Enter a profile name (ex. zabbix_ro) and enable all the Read permissions. Please note the profile name, it will be used a bit later.
3. Go to `System > Administrators > Create New > REST API Admin`.
4. Enter the API-user's name and select the profile name you created in step 2.
5. The trusted host can be specified to ensure that only Zabbix server can reach the FortiGate.
6. Click OK and an API token will be generated. Make a note of the API token as it's only shown once and cannot be retrieved.
7. Put the API token into `{$FGATE.API.TOKEN}` macro.
8. Set your FortiGate GUI IP/FQDN as `{$FGATE.API.FQDN}` macro value.
9. If FortiGate GUI uses HTTPS, put **https** value into `{$FGATE.SCHEME}` macro and **443** into `{$FGATE.API.PORT}` macro.
10. If FortiGate GUI port differs from the standard one, specify it in `{$FGATE.API.PORT}` macro.

NOTE: Starting from template version '8.0-2', the API token is used in the request header. For older template versions (where the API token is passed in the URL query parameter), when using FortiGate v7.4.5+, you must enable the following global setting:
[Using APIs](https://docs.fortinet.com/document/fortigate/7.6.4/administration-guide/940602/using-apis)

For added security, it is strongly recommended to use the latest template version, which passes the API token in the request header instead of the URL parameter.

>Please, refer to the [vendor documentation](https://docs.fortinet.com/document/fortigate/7.6.4/administration-guide/399023/rest-api-administrator) about the FortiGate REST API Authentication.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$FGATE.SCHEME}|<p>Request scheme which may be http or https.</p>|`http`|
|{$FGATE.API.FQDN}|<p>FortiGate API FQDN/IP (ex. ngfw.example.com).</p>||
|{$FGATE.API.TOKEN}|<p>FortiGate API token.</p>||
|{$FGATE.API.PORT}|<p>The port of FortiGate API endpoint.</p>|`80`|
|{$FGATE.DATA.TIMEOUT}|<p>Response timeout for an API.</p>|`15s`|
|{$FGATE.HTTP.PROXY}|<p>HTTP proxy for API requests. You can specify it using the format [protocol://][username[:password]@]proxy.example.com[:port]. See the documentation at https://www.zabbix.com/documentation/8.0/manual/config/items/itemtypes/http</p>||
|{$FIRMWARE.UPDATES.CONTROL}|<p>This macro is used in "New available firmware found" trigger.</p>|`1`|
|{$CPU.UTIL.WARN}|<p>Threshold of CPU utilization for warning trigger in %.</p>|`85`|
|{$CPU.UTIL.CRIT}|<p>Threshold of CPU utilization for critical trigger in %.</p>|`95`|
|{$MEMORY.UTIL.WARN}|<p>Threshold of memory utilization for warning trigger in %.</p>|`80`|
|{$MEMORY.UTIL.CRIT}|<p>Threshold of memory utilization for critical trigger in %.</p>|`90`|
|{$DISK.FREE.WARN}|<p>Threshold of disk free space for warning trigger in %.</p>|`20`|
|{$DISK.FREE.CRIT}|<p>Threshold of disk free space for critical trigger in %.</p>|`10`|
|{$NET.IF.CONTROL}|<p>Macro for operational state of the interface for "Link down" trigger. Can be used with interface name as context.</p>|`1`|
|{$NET.IF.ERRORS.WARN}|<p>Threshold of error packets rate for warning trigger. Can be used with interface name as context.</p>|`2`|
|{$NET.IF.UTIL.MAX}|<p>Threshold of interface bandwidth utilization for warning trigger in %. Can be used with interface name as context.</p>|`95`|
|{$NET.IF.IFDESCR.MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFDESCR.NOT_MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$NET.IF.IFNAME.MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFNAME.NOT_MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$NET.IF.IFTYPE.MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFTYPE.NOT_MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$NET.IF.IFALIAS.MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFALIAS.NOT_MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$NET.IF.IFSTATUS.MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$NET.IF.IFSTATUS.NOT_MATCHES}|<p>This macro is used in Network interfaces discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$FWP.FWACTION.MATCHES}|<p>This macro is used in Firewall policies discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$FWP.FWACTION.NOT_MATCHES}|<p>This macro is used in Firewall policies discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$FWP.FWTYPE.MATCHES}|<p>This macro is used in Firewall policies discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$FWP.FWTYPE.NOT_MATCHES}|<p>This macro is used in Firewall policies discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$FWP.FWNAME.MATCHES}|<p>This macro is used in Firewall policies discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$FWP.FWNAME.NOT_MATCHES}|<p>This macro is used in Firewall policies discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$SERVICE.EXPIRY.WARN}|<p>Number of days until the license expires.</p>|`7`|
|{$SERVICE.LICENSE.CONTROL}|<p>This macro is used in Service discovery. Can be used with interface name as context.</p>|`1`|
|{$SERVICE.KEY.MATCHES}|<p>This macro is used in Service discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$SERVICE.KEY.NOT_MATCHES}|<p>This macro is used in Service discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$SERVICE.STATUS.MATCHES}|<p>This macro is used in Service discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$SERVICE.STATUS.NOT_MATCHES}|<p>This macro is used in Service discovery. Can be overridden on the host or linked template level.</p>|`(no_support\|no_license)`|
|{$SERVICE.TYPE.MATCHES}|<p>This macro is used in Service discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$SERVICE.TYPE.NOT_MATCHES}|<p>This macro is used in Service discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$SDWAN.MEMBER.IF.CONTROL}|<p>Macro for the interface state for "Link down" trigger. Can be used with interface name as context.</p>|`1`|
|{$SDWAN.MEMBER.ID.MATCHES}|<p>This macro is used in SD-WAN members discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$SDWAN.MEMBER.ID.NOT_MATCHES}|<p>This macro is used in SD-WAN members discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$SDWAN.MEMBER.NAME.MATCHES}|<p>This macro is used in SD-WAN members discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$SDWAN.MEMBER.NAME.NOT_MATCHES}|<p>This macro is used in SD-WAN members discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$SDWAN.MEMBER.STATUS.MATCHES}|<p>This macro is used in SD-WAN members discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$SDWAN.MEMBER.STATUS.NOT_MATCHES}|<p>This macro is used in SD-WAN members discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$SDWAN.MEMBER.ZONE.MATCHES}|<p>This macro is used in SD-WAN members discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$SDWAN.MEMBER.ZONE.NOT_MATCHES}|<p>This macro is used in SD-WAN members discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$SDWAN.HEALTH.IF.CONTROL}|<p>Macro for the interface state for "Link down" trigger. Can be used with interface name as context.</p>|`1`|
|{$SDWAN.HEALTH.ID.MATCHES}|<p>This macro is used in SD-WAN health-checks discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$SDWAN.HEALTH.ID.NOT_MATCHES}|<p>This macro is used in SD-WAN health-checks discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$SDWAN.HEALTH.NAME.MATCHES}|<p>This macro is used in SD-WAN health-checks discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$SDWAN.HEALTH.NAME.NOT_MATCHES}|<p>This macro is used in SD-WAN health-checks discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$SDWAN.HEALTH.IFNAME.MATCHES}|<p>This macro is used in SD-WAN health-checks discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$SDWAN.HEALTH.IFNAME.NOT_MATCHES}|<p>This macro is used in SD-WAN health-checks discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$SDWAN.HEALTH.STATUS.MATCHES}|<p>This macro is used in SD-WAN health-checks discovery. Can be overridden on the host or linked template level.</p>|`.*`|
|{$SDWAN.HEALTH.STATUS.NOT_MATCHES}|<p>This macro is used in SD-WAN health-checks discovery. Can be overridden on the host or linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$SDWAN.HEALTH.IF.LOSS.WARN}|<p>Threshold of packets loss for warning trigger in %. Can be used with interface name as context.</p>|`20`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Check port availability||Simple check|net.tcp.service["{$FGATE.SCHEME}","{$FGATE.API.FQDN}","{$FGATE.API.PORT}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Get system info|<p>Item for gathering device system info from FortiGate API.</p>|HTTP agent|fgate.system.get_data<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `{"error":"Not supported value received"}`</p></li></ul>|
|Device system info item errors|<p>Item for gathering errors of the device system info.</p>|Dependent item|fgate.system.data_errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|API availability status|<p>Checking API availability by response.</p>|Dependent item|fgate.api.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.build`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>In range: ` -> 0`</p><p>⛔️Custom on fail: Set value to: `1`</p></li></ul>|
|Get firmware info|<p>Item for gathering device firmware info from FortiGate API.</p>|HTTP agent|fgate.firmware.get_data<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `{"error":"Not supported value received"}`</p></li></ul>|
|Device firmware info item errors|<p>Item for gathering errors of the device firmware info.</p>|Dependent item|fgate.firmware.data_errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get service licenses|<p>Item for gathering information about service licenses from FortiGate API.</p>|Script|fgate.service.get_data|
|Service licenses item errors|<p>Item for gathering errors of the service licenses data.</p>|Dependent item|fgate.service.data_errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get resources data|<p>Item for gathering device resource data from FortiGate API.</p>|Script|fgate.resources.get_data|
|Device resources item errors|<p>Item for gathering errors of the device resources.</p>|Dependent item|fgate.resources.data_errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get interfaces data|<p>Item for gathering network interfaces info from FortiGate API.</p>|Script|fgate.netif.get_data|
|Device interfaces item errors|<p>Item for gathering errors of network interfaces.</p>|Dependent item|fgate.netif.data_errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get SD-WAN data|<p>Item for gathering SD-WAN information from FortiGate API.</p>|Script|fgate.sdwan.get_data|
|Get SD-WAN item errors|<p>Item for gathering errors of SD-WAN.</p>|Dependent item|fgate.sdwan.data_errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get firewall data|<p>Item for gathering firewall policies info from FortiGate API.</p>|Script|fgate.fwp.get_data|
|Firewall data item errors|<p>Item for gathering errors of firewall policies.</p>|Dependent item|fgate.fwp.data_errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Available firmware versions|<p>Number of available firmware versions to download.</p>|Dependent item|fgate.device.firmwares_avail<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.results.available.length()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Device firmware version|<p>Current version of the device firmware.</p>|Dependent item|fgate.device.firmware<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.results.current`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Device model name|<p>The model name of the device.</p>|Dependent item|fgate.device.model<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.results`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Device serial number|<p>The device serial number.</p>|Dependent item|fgate.device.serialnumber<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serial`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Current VDOM|<p>Name of the current Virtual Domain.</p>|Dependent item|fgate.device.vdom<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.vdom`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System name|<p>The system host name.</p>|Dependent item|fgate.name<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.results.hostname`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System uptime|<p>The system uptime is calculated on the basis of boot time.</p>|Dependent item|fgate.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.results.utc_last_reboot`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Number of CPUs|<p>Number of processors according to the current license.</p>|Dependent item|fgate.cpu.num<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.vm.cpu_used`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|CPU utilization|<p>CPU utilization, expressed in %.</p>|Dependent item|fgate.cpu.util<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.cpu`</p></li></ul>|
|Total memory|<p>Total memory, expressed in bytes.</p>|Dependent item|fgate.memory.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.vm.mem_used`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Memory utilization|<p>Memory utilization, expressed in %.</p>|Dependent item|fgate.memory.util<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.mem`</p></li></ul>|
|Total disk space|<p>The total space of the current disk, in bytes.</p>|Dependent item|fgate.fs.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.disk_total`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Used disk space|<p>The used space of the current disk, in bytes.</p>|Dependent item|fgate.fs.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.disk_used`</p></li></ul>|
|Free disk space|<p>The free space of the current disk, in bytes.</p>|Dependent item|fgate.fs.free<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.disk_free`</p></li></ul>|
|Disk utilization|<p>Disk utilization, expressed in %.</p>|Dependent item|fgate.fs.util<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.disk`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|FortiGate: Port {$FGATE.API.PORT} is unavailable||`last(/FortiGate by HTTP/net.tcp.service["{$FGATE.SCHEME}","{$FGATE.API.FQDN}","{$FGATE.API.PORT}"])=0`|Average|**Manual close**: Yes|
|FortiGate: There are errors in the 'Get system info' metric||`length(last(/FortiGate by HTTP/fgate.system.data_errors))>0 and length(last(/FortiGate by HTTP/fgate.system.data_errors,#1:now-1m))>0 and nodata(/FortiGate by HTTP/fgate.system.data_errors,2m)=0`|Warning|**Depends on**:<br><ul><li>FortiGate: Unexpected response from API</li></ul>|
|FortiGate: Unexpected response from API|<p>Received an unexpected response from API. It may be unavailable.</p>|`last(/FortiGate by HTTP/fgate.api.status)=0`|Average|**Depends on**:<br><ul><li>FortiGate: Port {$FGATE.API.PORT} is unavailable</li></ul>|
|FortiGate: There are errors in the 'Get firmware info' metric||`length(last(/FortiGate by HTTP/fgate.firmware.data_errors))>0 and length(last(/FortiGate by HTTP/fgate.firmware.data_errors,#1:now-1m))>0 and nodata(/FortiGate by HTTP/fgate.firmware.data_errors,2m)=0`|Warning|**Depends on**:<br><ul><li>FortiGate: Unexpected response from API</li></ul>|
|FortiGate: There are errors in the 'Get service licenses' metric||`length(last(/FortiGate by HTTP/fgate.service.data_errors))>0 and length(last(/FortiGate by HTTP/fgate.service.data_errors,#1:now-1m))>0 and nodata(/FortiGate by HTTP/fgate.service.data_errors,2m)=0`|Warning|**Depends on**:<br><ul><li>FortiGate: Unexpected response from API</li></ul>|
|FortiGate: There are errors in the 'Get resources data' metric||`length(last(/FortiGate by HTTP/fgate.resources.data_errors))>0 and length(last(/FortiGate by HTTP/fgate.resources.data_errors,#1:now-1m))>0 and nodata(/FortiGate by HTTP/fgate.resources.data_errors,2m)=0`|Warning|**Depends on**:<br><ul><li>FortiGate: Unexpected response from API</li></ul>|
|FortiGate: There are errors in the 'Get interfaces data' metric||`length(last(/FortiGate by HTTP/fgate.netif.data_errors))>0 and length(last(/FortiGate by HTTP/fgate.netif.data_errors,#1:now-1m))>0 and nodata(/FortiGate by HTTP/fgate.netif.data_errors,2m)=0`|Warning|**Depends on**:<br><ul><li>FortiGate: Unexpected response from API</li></ul>|
|FortiGate: There are errors in the 'Get SD-WAN data' metric||`length(last(/FortiGate by HTTP/fgate.sdwan.data_errors))>0 and length(last(/FortiGate by HTTP/fgate.sdwan.data_errors,#1:now-1m))>0 and nodata(/FortiGate by HTTP/fgate.sdwan.data_errors,2m)=0`|Warning|**Depends on**:<br><ul><li>FortiGate: Unexpected response from API</li></ul>|
|FortiGate: There are errors in the 'Get firewall policies data' metric||`length(last(/FortiGate by HTTP/fgate.fwp.data_errors))>0 and length(last(/FortiGate by HTTP/fgate.fwp.data_errors,#1:now-1m))>0 and nodata(/FortiGate by HTTP/fgate.fwp.data_errors,2m)=0`|Warning|**Depends on**:<br><ul><li>FortiGate: Unexpected response from API</li></ul>|
|FortiGate: New available firmware found|<p>New available firmware versions found to download.<br><br>This trigger expression works as follows:<br>1. It can be triggered if there are one or more available firmware versions.<br>2. `{$FIRMWARE.UPDATES.CONTROL}=1` - a user can redefine context macro to value - 0. That marks this notification as not important. No new trigger will be fired if new firmware is found.</p>|`{$FIRMWARE.UPDATES.CONTROL}=1 and last(/FortiGate by HTTP/fgate.device.firmwares_avail)>0`|Info|**Manual close**: Yes|
|FortiGate: Device has been replaced|<p>Device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/FortiGate by HTTP/fgate.device.serialnumber,#1)<>last(/FortiGate by HTTP/fgate.device.serialnumber,#2) and length(last(/FortiGate by HTTP/fgate.device.serialnumber))>0`|Info|**Manual close**: Yes|
|FortiGate: System name has changed|<p>The name of the system has changed. Acknowledge to close the problem manually.</p>|`last(/FortiGate by HTTP/fgate.name,#1)<>last(/FortiGate by HTTP/fgate.name,#2) and length(last(/FortiGate by HTTP/fgate.name))>0`|Info|**Manual close**: Yes|
|FortiGate: Device has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/FortiGate by HTTP/fgate.uptime)<10m`|Info|**Manual close**: Yes|
|FortiGate: CPU utilization is too high|<p>The CPU utilization is too high. The system might be slow to respond.</p>|`min(/FortiGate by HTTP/fgate.cpu.util,5m)>{$CPU.UTIL.CRIT}`|High||
|FortiGate: CPU utilization is high|<p>The CPU utilization is high.</p>|`min(/FortiGate by HTTP/fgate.cpu.util,5m)>{$CPU.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>FortiGate: CPU utilization is too high</li></ul>|
|FortiGate: Memory utilization is too high|<p>Free memory size is too low.</p>|`min(/FortiGate by HTTP/fgate.memory.util,5m)>{$MEMORY.UTIL.CRIT}`|High||
|FortiGate: Memory utilization is high|<p>The system is running out of free memory.</p>|`min(/FortiGate by HTTP/fgate.memory.util,5m)>{$MEMORY.UTIL.WARN}`|Average|**Depends on**:<br><ul><li>FortiGate: Memory utilization is too high</li></ul>|
|FortiGate: Free disk space is too low|<p>Left disk space is too low.</p>|`(100-last(/FortiGate by HTTP/fgate.fs.util))<{$DISK.FREE.CRIT}`|High||
|FortiGate: Free disk space is low|<p>Left disk space is not enough.</p>|`(100-last(/FortiGate by HTTP/fgate.fs.util))<{$DISK.FREE.WARN}`|Warning|**Depends on**:<br><ul><li>FortiGate: Free disk space is too low</li></ul>|

### LLD rule Firewall policies discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Firewall policies discovery|<p>Discovery for FortiGate firewall policies.</p>|Dependent item|fgate.fwp.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Firewall policies discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FW Policy [{#FWNAME}]: Get data|<p>Item for gathering data for the {#FWNAME} firewall policy.</p>|Dependent item|fgate.fwp.get_data[{#FWUUID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data[?(@.uuid == "{#FWUUID}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|FW Policy [{#FWNAME}]: Active sessions|<p>Number of active sessions covered by this rule.</p>|Dependent item|fgate.fwp.sessions[{#FWUUID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.active_sessions`</p></li></ul>|
|FW Policy [{#FWNAME}]: Software processed bytes|<p>Number of bytes processed only by the software firewall.</p>|Dependent item|fgate.fwp.sw_bytes[{#FWUUID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.software_bytes`</p></li><li>Change per second</li></ul>|
|FW Policy [{#FWNAME}]: Hardware processed bytes|<p>Number of bytes processed only by the hardware (ASIC) firewall.</p>|Dependent item|fgate.fwp.hw_bytes[{#FWUUID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.asic_bytes`</p></li><li>Change per second</li></ul>|
|FW Policy [{#FWNAME}]: Total bytes processed|<p>Number of bytes processed by both the software and hardware (ASIC) firewall.</p>|Dependent item|fgate.fwp.bytes[{#FWUUID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes`</p></li><li>Change per second</li></ul>|
|FW Policy [{#FWNAME}]: Hits into the policy|<p>Number of packets hit into the firewall policy per second.</p>|Dependent item|fgate.fwp.hits[{#FWUUID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hit_count`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|FW Policy [{#FWNAME}]: Last using time|<p>The time at which the firewall policy was used the last time.</p>|Dependent item|fgate.fwp.last_used[{#FWUUID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.last_used`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|FW Policy [{#FWNAME}]: Action|<p>The firewall policy action (accept / deny / ipsec).</p>|Dependent item|fgate.fwp.action[{#FWUUID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.action`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|FW Policy [{#FWNAME}]: Status|<p>The firewall policy status.</p>|Dependent item|fgate.fwp.status[{#FWUUID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Service discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Service discovery|<p>Discovery for FortiGate services.</p>|Dependent item|fgate.service.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lld`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Service discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Service [{#NAME}]: Get data|<p>Item for gathering data about license for the {#NAME} service.</p>|Dependent item|fgate.service.get_data["{#KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data["{#KEY}"]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Service [{#NAME}]: License status|<p>Current license status of the {#NAME} service.</p>|Dependent item|fgate.service.license["{#KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [{#NAME}]: Service type|<p>Current type of the {#NAME} service.</p>|Dependent item|fgate.service.type["{#KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.type`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [{#NAME}]: Service version|<p>Current version of the {#NAME} service.</p>|Dependent item|fgate.service.version["{#KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.version`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [{#NAME}]: Expiration date|<p>Expiration date for the license of the current service.</p>|Dependent item|fgate.service.expire["{#KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.expires`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [{#NAME}]: Last update time|<p>Last update time of the current service.</p>|Dependent item|fgate.service.update_time["{#KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.last_update`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [{#NAME}]: Last attempt to update|<p>Last update attempt time of the current service.</p>|Dependent item|fgate.service.update_attempt["{#KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.last_update_attempt`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [{#NAME}]: Update method|<p>Current update method of the {#NAME} service.</p>|Dependent item|fgate.service.update_method["{#KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.last_update_method_status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Service [{#NAME}]: Update result|<p>Last update result of the {#NAME} service.</p>|Dependent item|fgate.service.update_result["{#KEY}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.last_update_result_status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Service discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|FortiGate: Service [{#NAME}]: License status is unsuccessful|<p>This trigger expression works as follows:<br>1. It can be triggered if the license status is unsuccessful.<br>2. `{$SERVICE.LICENSE.CONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value - 0. That marks the license of this service as not important. No new trigger will be fired if this license is unsuccessful.</p>|`{$SERVICE.LICENSE.CONTROL:"{#KEY}"}=1 and last(/FortiGate by HTTP/fgate.service.license["{#KEY}"])>5`|Average|**Manual close**: Yes|
|FortiGate: Service [{#NAME}]: License expires soon|<p>This trigger expression works as follows:<br>1. It can be triggered if the license expires soon.<br>2. `{$SERVICE.LICENSE.CONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value - 0. That marks the license of this service as not important. No new trigger will be fired if this license expires.</p>|`{$SERVICE.LICENSE.CONTROL:"{#KEY}"}=1 and (last(/FortiGate by HTTP/fgate.service.expire["{#KEY}"]) - now()) / 86400 < {$SERVICE.EXPIRY.WARN:"{#KEY}"} and last(/FortiGate by HTTP/fgate.service.expire["{#KEY}"]) > now()`|Warning|**Manual close**: Yes|

### LLD rule SD-WAN members discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SD-WAN members discovery|<p>Discovery for FortiGate SD-WAN members.</p>|Dependent item|fgate.sdwan_member.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.member_lld`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for SD-WAN members discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SD-WAN [{#ZONE}]:[{#NAME}]: Get data|<p>Item for gathering data about the {#NAME} interface in the {#ZONE} zone.</p>|Dependent item|fgate.sdwan_member.get_data[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.member_lld[?(@.interface == "{#NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|SD-WAN [{#ZONE}]:[{#NAME}]: Member status|<p>Current status of the {#NAME} interface in the {#ZONE} zone.</p>|Dependent item|fgate.sdwan_member.status[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|SD-WAN [{#ZONE}]:[{#NAME}]: Link status|<p>Current link status of the {#NAME} interface in the {#ZONE} zone.</p>|Dependent item|fgate.sdwan_member.link_status[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.link`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|SD-WAN [{#ZONE}]:[{#NAME}]: Sessions|<p>Number of active sessions opened through the {#NAME} interface in the {#ZONE} zone.</p>|Dependent item|fgate.sdwan_member.sessions[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.session`</p></li></ul>|
|SD-WAN [{#ZONE}]:[{#NAME}]: Bytes sent per second|<p>Bytes sent through the {#NAME} interface in the {#ZONE} zone per second.</p>|Dependent item|fgate.sdwan_member.tx_bytes[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_bytes`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|SD-WAN [{#ZONE}]:[{#NAME}]: Bytes received per second|<p>Bytes received from the {#NAME} interface in the {#ZONE} zone per second.</p>|Dependent item|fgate.sdwan_member.rx_bytes[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rx_bytes`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|SD-WAN [{#ZONE}]:[{#NAME}]: Output bandwidth|<p>Transmitting bandwidth of the {#NAME} interface in the {#ZONE} zone.</p>|Dependent item|fgate.sdwan_member.tx_bandwidth[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_bandwidth`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|SD-WAN [{#ZONE}]:[{#NAME}]: Input bandwidth|<p>Receiving bandwidth of the {#NAME} interface in the {#ZONE} zone.</p>|Dependent item|fgate.sdwan_member.rx_bandwidth[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rx_bandwidth`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|SD-WAN [{#ZONE}]:[{#NAME}]: State changing time|<p>Last state changing time of the {#NAME} interface in the {#ZONE} zone.</p>|Dependent item|fgate.service.state_changed[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state_changed`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for SD-WAN members discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|FortiGate: SD-WAN [{#ZONE}]:[{#NAME}]: Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the interface status is down.<br>2. `{$SDWAN.MEMBER.IF.CONTROL:"{#NAME}"}=1` - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the interface status was up to (1) sometime before.<br><br>WARNING: If closed manually, it will not fire again on the next poll because of .diff.</p>|`{$SDWAN.MEMBER.IF.CONTROL:"{#NAME}"}=1 and last(/FortiGate by HTTP/fgate.sdwan_member.link_status[{#ID}])=1 and (last(/FortiGate by HTTP/fgate.sdwan_member.link_status[{#ID}],#1)<>last(/FortiGate by HTTP/fgate.sdwan_member.link_status[{#ID}],#2))`|Average|**Manual close**: Yes|

### LLD rule SD-WAN health-checks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SD-WAN health-checks discovery|<p>Discovery for FortiGate SD-WAN health-checks.</p>|Dependent item|fgate.sdwan_health.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.health_lld`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for SD-WAN health-checks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|SD-WAN [{#NAME}]:[{#IFNAME}]: Get data|<p>Item for gathering data about the {#IFNAME} interface in the {#NAME} health-check.</p>|Dependent item|fgate.sdwan_health.get_data["{#HID}.{#MID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|SD-WAN [{#NAME}]:[{#IFNAME}]: Interface status|<p>Current status of the {#IFNAME} interface in the {#NAME} health-check.</p>|Dependent item|fgate.sdwan_health.status["{#HID}.{#MID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|SD-WAN [{#NAME}]:[{#IFNAME}]: Jitter|<p>Current jitter value for the {#IFNAME} interface in the {#NAME} health-check.</p>|Dependent item|fgate.sdwan_health.jitter["{#HID}.{#MID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.jitter`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|SD-WAN [{#NAME}]:[{#IFNAME}]: Latency|<p>Current latency value for the {#IFNAME} interface in the {#NAME} health-check.</p>|Dependent item|fgate.sdwan_health.latency["{#HID}.{#MID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.latency`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|SD-WAN [{#NAME}]:[{#IFNAME}]: Packets loss|<p>Percent of lost packets for the {#IFNAME} interface in the {#NAME} health-check.</p>|Dependent item|fgate.sdwan_health.loss["{#HID}.{#MID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.packet_loss`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|SD-WAN [{#NAME}]:[{#IFNAME}]: Packets sent per second|<p>Number of packets sent through the {#IFNAME} interface in the {#NAME} health-check per second.</p>|Dependent item|fgate.sdwan_health.sent["{#HID}.{#MID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.packet_sent`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|SD-WAN [{#NAME}]:[{#IFNAME}]: Packets received per second|<p>Number of packets received from the {#IFNAME} interface in the {#NAME} health-check per second.</p>|Dependent item|fgate.sdwan_health.received["{#HID}.{#MID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.packet_received`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### Trigger prototypes for SD-WAN health-checks discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|FortiGate: SD-WAN [{#NAME}]:[{#IFNAME}]: Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the interface status is down.<br>2. `{$SDWAN.HEALTH.IF.CONTROL:"{#NAME}"}=1` - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down/error.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the interface status was up to (1) sometime before.<br><br>WARNING: If closed manually, it will not fire again on the next poll because of .diff.</p>|`{$SDWAN.HEALTH.IF.CONTROL:"{#NAME}"}=1 and last(/FortiGate by HTTP/fgate.sdwan_health.status["{#HID}.{#MID}"])=1 and (last(/FortiGate by HTTP/fgate.sdwan_health.status["{#HID}.{#MID}"],#1)<>last(/FortiGate by HTTP/fgate.sdwan_health.status["{#HID}.{#MID}"],#2))`|Average|**Manual close**: Yes|
|FortiGate: SD-WAN [{#NAME}]:[{#IFNAME}]: Link state is error|<p>This trigger expression works as follows:<br>1. It can be triggered if the interface status is error.<br>2. `{$SDWAN.HEALTH.IF.CONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down/error.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the interface status was up to (1) sometime before.<br><br>WARNING: If closed manually, it will not fire again on the next poll because of .diff.</p>|`{$SDWAN.HEALTH.IF.CONTROL:"{#IFNAME}"}=1 and last(/FortiGate by HTTP/fgate.sdwan_health.status["{#HID}.{#MID}"])=2 and (last(/FortiGate by HTTP/fgate.sdwan_health.status["{#HID}.{#MID}"],#1)<>last(/FortiGate by HTTP/fgate.sdwan_health.status["{#HID}.{#MID}"],#2))`|Average|**Manual close**: Yes|
|FortiGate: SD-WAN [{#NAME}]:[{#IFNAME}]: High packets loss|<p>High level of packets loss detected.</p>|`min(/FortiGate by HTTP/fgate.sdwan_health.loss["{#HID}.{#MID}"],5m)>{$SDWAN.HEALTH.IF.LOSS.WARN:"{#IFNAME}"}`|Warning||

### LLD rule Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interfaces discovery|<p>Discovery for FortiGate network interfaces.</p>|Dependent item|fgate.netif.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface [{#IFNAME}({#IFALIAS})]: Get data|<p>Item for gathering data for the {#IFKEY} interface.</p>|Dependent item|fgate.netif.get_data[{#IFKEY}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data[?(@.id == "{#IFKEY}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Interface [{#IFNAME}({#IFALIAS})]: Link status|<p>Current link status of the interface.</p>|Dependent item|fgate.netif.status[{#IFKEY}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.link`</p></li><li>Boolean to decimal</li></ul>|
|Interface [{#IFNAME}({#IFALIAS})]: Bits received|<p>The total number of octets received on the interface per second.</p>|Dependent item|fgate.netif.in[{#IFKEY}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rx_bytes`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface [{#IFNAME}({#IFALIAS})]: Inbound packets|<p>The total number of packets received on the interface per second.</p>|Dependent item|fgate.netif.in_packets[{#IFKEY}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rx_packets`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Interface [{#IFNAME}({#IFALIAS})]: Bits sent|<p>The total number of octets transmitted out of the interface.</p>|Dependent item|fgate.netif.out[{#IFKEY}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_bytes`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface [{#IFNAME}({#IFALIAS})]: Outbound packets|<p>The total number of packets transmitted out of the interface per second.</p>|Dependent item|fgate.netif.out_packets[{#IFKEY}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_packets`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Interface [{#IFNAME}({#IFALIAS})]: Inbound packets with errors|<p>The total number of errors received.</p>|Dependent item|fgate.netif.in_errors[{#IFKEY}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rx_errors`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Interface [{#IFNAME}({#IFALIAS})]: Outbound packets with errors|<p>The total number of errors transmitted.</p>|Dependent item|fgate.netif.out_errors[{#IFKEY}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.tx_errors`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li>Change per second</li></ul>|
|Interface [{#IFNAME}({#IFALIAS})]: Interface type|<p>Type of the interface.</p>|Dependent item|fgate.netif.type[{#IFKEY}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.type`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface [{#IFNAME}({#IFALIAS})]: Speed|<p>Speed of the interface.</p>|Dependent item|fgate.netif.speed[{#IFKEY}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.speed`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Custom multiplier: `1000000`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Network interfaces discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|FortiGate: Interface [{#IFNAME}({#IFALIAS})]: Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the interface link status is down.<br>2. `{$NET.IF.CONTROL:"{#IFNAME}"}=1` - a user can redefine context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface link is down.<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the interface link status was up to (1) sometime before.<br><br>WARNING: If closed manually, it will not fire again on the next poll because of .diff.</p>|`{$NET.IF.CONTROL:"{#IFNAME}"}=1 and last(/FortiGate by HTTP/fgate.netif.status[{#IFKEY}])=1 and (last(/FortiGate by HTTP/fgate.netif.status[{#IFKEY}],#1)<>last(/FortiGate by HTTP/fgate.netif.status[{#IFKEY}],#2))`|Average|**Manual close**: Yes|
|FortiGate: Interface [{#IFNAME}({#IFALIAS})]: High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/FortiGate by HTTP/fgate.netif.in[{#IFKEY}],15m)>({$NET.IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/FortiGate by HTTP/fgate.netif.speed[{#IFKEY}]) or avg(/FortiGate by HTTP/fgate.netif.out[{#IFKEY}],15m)>({$NET.IF.UTIL.MAX:"{#IFNAME}"}/100)*last(/FortiGate by HTTP/fgate.netif.speed[{#IFKEY}])) and last(/FortiGate by HTTP/fgate.netif.speed[{#IFKEY}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>FortiGate: Interface [{#IFNAME}({#IFALIAS})]: Link down</li></ul>|
|FortiGate: Interface [{#IFNAME}({#IFALIAS})]: High error rate|<p>It recovers when it is below 80% of the `{$NET.IF.ERRORS.WARN:"{#IFKEY}"}` threshold.</p>|`min(/FortiGate by HTTP/fgate.netif.in_errors[{#IFKEY}],5m)>{$NET.IF.ERRORS.WARN:"{#IFKEY}"} or min(/FortiGate by HTTP/fgate.netif.in_errors[{#IFKEY}],5m)>{$NET.IF.ERRORS.WARN:"{#IFKEY}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>FortiGate: Interface [{#IFNAME}({#IFALIAS})]: Link down</li></ul>|
|FortiGate: Interface [{#IFNAME}({#IFALIAS})]: Ethernet has changed to lower speed than it was before|<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Acknowledge to close the problem manually.</p>|`change(/FortiGate by HTTP/fgate.netif.speed[{#IFKEY}])<0 and last(/FortiGate by HTTP/fgate.netif.speed[{#IFKEY}])>0 and last(/FortiGate by HTTP/fgate.netif.status[{#IFKEY}])<>0`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>FortiGate: Interface [{#IFNAME}({#IFALIAS})]: Link down</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

