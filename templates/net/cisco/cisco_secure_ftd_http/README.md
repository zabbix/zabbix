
# Cisco Secure Firewall Threat Defense by HTTP

## Overview

This template provides monitoring capabilities for Cisco Secure Firewall Threat Defense devices using the REST API.
It includes metrics for CPU and memory usage, interface statistics, connection tracking, and more.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Cisco Secure Firewall 3120 Threat Defense, Software 7.2.8-25

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

You must set the following macros in the template or host configuration:
- `{$CISCO.FTD.API.URL}`: The URL of the Cisco Secure Firewall Threat Defense REST API, e.g., `https://ftd.example.com/api/fdm/latest`.
- `{$CISCO.FTD.API.USERNAME}`: The username for the API.
- `{$CISCO.FTD.API.PASSWORD}`: The password for the API.
- `{$CISCO.FTD.HTTP_PROXY}`: Optional HTTP proxy for API requests.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CISCO.FTD.HTTP_PROXY}|<p>Sets HTTP proxy value. If this macro is empty then no proxy is used.</p>||
|{$CISCO.FTD.API.URL}|<p>Cisco Secure Firewall Threat Defense REST API URL. Format example: `https://ftd.example.com/api/fdm/latest`</p>||
|{$CISCO.FTD.API.USERNAME}|<p>Cisco Secure Firewall Threat Defense REST API username.</p>||
|{$CISCO.FTD.API.PASSWORD}|<p>Cisco Secure Firewall Threat Defense REST API password.</p>||
|{$CISCO.FTD.DATA.TIMEOUT}|<p>Response timeout for the Cisco Secure Firewall Threat Defense REST API.</p>|`15s`|
|{$CISCO.FTD.DATA.INTERVAL}|<p>Update interval for the HTTP item that retrieves data from the API. Can be used with context if needed (check the context values in relevant items).</p>|`1m`|
|{$CISCO.FTD.CPU.UTIL.WARN}|<p>Warning threshold for FTD CPU utilization, expressed as a percentage.</p>|`80`|
|{$CISCO.FTD.MEMORY.UTIL.WARN}|<p>Warning threshold for FTD memory utilization, expressed as a percentage.</p>|`80`|
|{$CISCO.FTD.LLD.FILTER.THROUGHPUT.INTERFACE.NAME.MATCHES}|<p>Filter for discoverable throughput interfaces by name.</p>|`.*`|
|{$CISCO.FTD.LLD.FILTER.THROUGHPUT.INTERFACE.NAME.NOT_MATCHES}|<p>Filter to exclude discoverable throughput interfaces by name.</p>|`CHANGE_IF_NEEDED`|
|{$CISCO.FTD.LLD.FILTER.IF.NAME.MATCHES}|<p>Filter for discoverable interface names.</p>|`.*`|
|{$CISCO.FTD.LLD.FILTER.IF.NAME.NOT_MATCHES}|<p>Filter to exclude discovered interfaces by name.</p>|`CHANGE_IF_NEEDED`|
|{$CISCO.FTD.LLD.FILTER.IF.DESCR.MATCHES}|<p>Filter for discoverable interface descriptions.</p>|`.*`|
|{$CISCO.FTD.LLD.FILTER.IF.DESCR.NOT_MATCHES}|<p>Filter to exclude discovered interfaces by description.</p>|`CHANGE_IF_NEEDED`|
|{$CISCO.FTD.LLD.FILTER.CONN.STATS.NAME.MATCHES}|<p>Filter for discoverable connections by name.</p>|`.*`|
|{$CISCO.FTD.LLD.FILTER.CONN.STATS.NAME.NOT_MATCHES}|<p>Filter to exclude discovered connections by name.</p>|`CHANGE_IF_NEEDED`|
|{$CISCO.FTD.LLD.FILTER.ASP.STATS.NAME.MATCHES}|<p>Filter for discoverable Accelerated Security Path dropped packets or connections by name.</p>|`.*`|
|{$CISCO.FTD.LLD.FILTER.ASP.STATS.NAME.NOT_MATCHES}|<p>Filter to exclude discovered Accelerated Security Path dropped packets or connections by name.</p>|`CHANGE_IF_NEEDED`|
|{$CISCO.FTD.LLD.FILTER.SNORT.ID.MATCHES}|<p>Filter for discoverable Snort and IDS/IPS statistics by name.</p>|`.*`|
|{$CISCO.FTD.LLD.FILTER.SNORT.ID.NOT_MATCHES}|<p>Filter to exclude discovered Snort and IDS/IPS statistics by name.</p>|`CHANGE_IF_NEEDED`|
|{$CISCO.FTD.LLD.FILTER.CPU.NAME.MATCHES}|<p>Filter for discoverable CPUs by name.</p>|`.*`|
|{$CISCO.FTD.LLD.FILTER.CPU.NAME.NOT_MATCHES}|<p>Filter to exclude discovered CPUs by name.</p>|`CHANGE_IF_NEEDED`|
|{$CISCO.FTD.LLD.FILTER.PROCESS.NAME.MATCHES}|<p>Filter for discoverable processes by name.</p>|`.*`|
|{$CISCO.FTD.LLD.FILTER.PROCESS.NAME.NOT_MATCHES}|<p>Filter to exclude discovered processes by name.</p>|`CHANGE_IF_NEEDED`|
|{$CISCO.FTD.LLD.FILTER.SENSOR.NAME.MATCHES}|<p>Filter for discoverable temperature sensors by name.</p>|`.*`|
|{$CISCO.FTD.LLD.FILTER.SENSOR.NAME.NOT_MATCHES}|<p>Filter to exclude discovered temperature sensors by name.</p>|`CHANGE_IF_NEEDED`|
|{$CISCO.FTD.LLD.FILTER.FSNAME.MATCHES}|<p>Filter for discoverable filesystems by name.</p>|`.*`|
|{$CISCO.FTD.LLD.FILTER.FSNAME.NOT_MATCHES}|<p>Filter to exclude discovered filesystems by name.</p>|`CHANGE_IF_NEEDED`|
|{$CISCO.FTD.LLD.FILTER.FSMOUNT.MATCHES}|<p>Filter for discoverable filesystems by mount point.</p>|`.*`|
|{$CISCO.FTD.LLD.FILTER.FSMOUNT.NOT_MATCHES}|<p>Filter to exclude discovered filesystems by mount point.</p>|`CHANGE_IF_NEEDED`|
|{$CISCO.FTD.TEMP.CRIT}|<p>Critical threshold for the temperature sensor trigger. Can be used with the interface name as context.</p>|`60`|
|{$CISCO.FTD.TEMP.WARN}|<p>Warning threshold for the temperature sensor trigger. Can be used with the interface name as context.</p>|`50`|
|{$CISCO.FTD.FS.PUSED.WARN}|<p>Threshold for the filesystem utilization trigger. Can be used with the filesystem name as context.</p>|`80`|
|{$CISCO.FTD.IF.ERRORS.WARN}|<p>Threshold for the error packet rate warning trigger. Can be used with the interface name as context.</p>|`2`|
|{$CISCO.FTD.IF.CONTROL}|<p>Macro for the operational state of the interface for the "link down" trigger. Can be used with the interface name as context.</p>|`1`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get token|<p>Requests an access token using the `password` grant type (username and password).</p>|HTTP agent|cisco.ftd.token.get|
|Get device metrics|<p>Collects device metrics from the Cisco Secure FTD API.</p>|Dependent item|cisco.ftd.device.metrics.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Device metric item errors|<p>Collects errors from device metrics.</p>|Dependent item|cisco.ftd.device.metrics.get.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get operational metrics|<p>Collects device metrics from the Cisco Secure FTD API.</p>|Dependent item|cisco.ftd.operational.metrics.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Operational metric item errors|<p>Collects errors from operational metrics.</p>|Dependent item|cisco.ftd.operational.metrics.get.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|CPU utilization|<p>Average CPU utilization.</p>|Dependent item|cisco.ftd.cpu.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpu.items..avgUsage.first()`</p></li></ul>|
|Memory utilization|<p>Memory utilization percentage.</p>|Dependent item|cisco.ftd.memory.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.memory.items..avgUsage.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Events per second|<p>Average events per second.</p>|Dependent item|cisco.ftd.events.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.eps.items..avgEps.first()`</p></li><li>Change per second</li></ul>|
|Disc space: Utilization|<p>Total disk space utilization percentage.</p>|Dependent item|cisco.ftd.disk.total.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disc space: Total|<p>Total disk size in bytes.</p>|Dependent item|cisco.ftd.disk.total.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "disk_stats.total.size")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disc space: Used|<p>Total used disk space in bytes.</p>|Dependent item|cisco.ftd.disk.total.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "disk_stats.total.used")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Storage: Used|<p>Amount of storage used.</p>|Dependent item|cisco.ftd.storage.usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.disk.used`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1073741824`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Storage: Free|<p>Amount of free storage.</p>|Dependent item|cisco.ftd.storage.free<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.disk.free`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1073741824`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Storage: Total|<p>Amount of total storage.</p>|Dependent item|cisco.ftd.storage.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.disk.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1073741824`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Serial number|<p>Serial number of the Cisco Secure FTD.</p>|Dependent item|cisco.ftd.serialnumber<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.systeminfo.serialNumber`</p></li><li><p>Discard unchanged with heartbeat: `24h`</p></li></ul>|
|Platform model|<p>Platform model of the Cisco Secure FTD.</p>|Dependent item|cisco.ftd.model<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.systeminfo.platformModel`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Software version|<p>Software version of the Cisco Secure FTD.</p>|Dependent item|cisco.ftd.software.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.systeminfo.softwareVersion`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|System uptime|<p>The system uptime.</p>|Dependent item|cisco.ftd.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.systeminfo.systemUptime`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco Secure FTD: There are errors in the 'Get device metrics' metric|<p>An error occurred when trying to get device metrics from the Cisco Secure FTD API.</p>|`length(last(/Cisco Secure Firewall Threat Defense by HTTP/cisco.ftd.device.metrics.get.errors))>0`|Warning||
|Cisco Secure FTD: There are errors in the 'Get operational metrics' metric|<p>An error occurred when trying to get operational metrics from the Cisco Secure FTD API.</p>|`length(last(/Cisco Secure Firewall Threat Defense by HTTP/cisco.ftd.operational.metrics.get.errors))>0`|Warning||
|Cisco Secure FTD: High CPU utilization|<p>CPU utilization is too high. The system might be slow to respond.</p>|`min(/Cisco Secure Firewall Threat Defense by HTTP/cisco.ftd.cpu.utilization,15m)>{$CISCO.FTD.CPU.UTIL.WARN}`|Warning||
|Cisco Secure FTD: High memory utilization|<p>RAM utilization is too high. The system might be slow to respond.</p>|`min(/Cisco Secure Firewall Threat Defense by HTTP/cisco.ftd.memory.utilization,15m) >= {$CISCO.FTD.MEMORY.UTIL.WARN}`|Average||
|Cisco Secure FTD: Device has been replaced|<p>The device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/Cisco Secure Firewall Threat Defense by HTTP/cisco.ftd.serialnumber,#1)<>last(/Cisco Secure Firewall Threat Defense by HTTP/cisco.ftd.serialnumber,#2) and length(last(/Cisco Secure Firewall Threat Defense by HTTP/cisco.ftd.serialnumber))>0`|Info|**Manual close**: Yes|
|Cisco Secure FTD: Device has been restarted|<p>The host uptime is less than 10 minutes.</p>|`last(/Cisco Secure Firewall Threat Defense by HTTP/cisco.ftd.uptime)<10m`|Info|**Manual close**: Yes|

### LLD rule Throughput discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Throughput discovery|<p>Discovery of throughput interfaces from the Cisco Secure FTD API.</p>|Dependent item|cisco.ftd.throughput.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.throughput.items`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Throughput discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface [{#NAME}]: Throughput|<p>Throughput of the `{#NAME}` interface.</p>|Dependent item|cisco.ftd.interface.throughput["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `8`</p></li></ul>|

### LLD rule Interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface discovery|<p>Discovery of interfaces from the Cisco Secure FTD API.</p>|Dependent item|cisco.ftd.interface.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface [{#NAME}][{#DESCR}]: Get metric data|<p>Gets data from the interface `{#NAME}`.</p>|Dependent item|cisco.ftd.interface.get["{#NAME}","{#DESCR}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.id == "{#NAME}")]`</p></li></ul>|
|Interface [{#NAME}][{#DESCR}]: Incoming traffic|<p>Input traffic `{#NAME}` interface.</p>|Dependent item|cisco.ftd.net.if.in.traffic["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "input_bytes")].value.first()`</p></li><li><p>Custom multiplier: `8`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Interface [{#NAME}][{#DESCR}]: Outgoing traffic|<p>Outgoing traffic `{#NAME}` interface.</p>|Dependent item|cisco.ftd.interface.out.traffic["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "output_bytes")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `8`</p></li><li>Change per second</li></ul>|
|Interface [{#NAME}][{#DESCR}]: Input packets|<p>Input packets `{#NAME}` interface.</p>|Dependent item|cisco.ftd.interface.input.packets["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "input_packets")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Interface [{#NAME}][{#DESCR}]: Output packets|<p>Output packets `{#NAME}` interface.</p>|Dependent item|cisco.ftd.interface.output.packets["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "output_packets")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Interface [{#NAME}][{#DESCR}]: Input errors|<p>Input errors `{#NAME}` interface.</p>|Dependent item|cisco.ftd.interface.input.errors["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "input_errors")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Interface [{#NAME}][{#DESCR}]: Output errors|<p>Output errors `{#NAME}` interface.</p>|Dependent item|cisco.ftd.interface.output.errors["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "output_errors")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Interface [{#NAME}][{#DESCR}]: Dropped packets|<p>Number of dropped packets per second `{#NAME}` interface.</p>|Dependent item|cisco.ftd.interface.drop.packets["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "drop_packets")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Interface [{#NAME}][{#DESCR}]: Status|<p>Status `{#NAME}` interface.</p>|Dependent item|cisco.ftd.interface.status["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "status")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco Secure FTD: Interface [{#NAME}][{#DESCR}]: High input error rate|<p>Recovers when below 80% of the `{$CISCO.FTD.IF.ERRORS.WARN:"{#NAME}"}` threshold.</p>|`min(/Cisco Secure Firewall Threat Defense by HTTP/cisco.ftd.interface.input.errors["{#NAME}"],5m)>{$CISCO.FTD.IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Depends on**:<br><ul><li>Cisco Secure FTD: Interface [{#NAME}][{#DESCR}]: Link down</li></ul>|
|Cisco Secure FTD: Interface [{#NAME}][{#DESCR}]: High output error rate|<p>Recovers when below 80% of the `{$CISCO.FTD.IF.ERRORS.WARN:"{#NAME}"}` threshold.</p>|`min(/Cisco Secure Firewall Threat Defense by HTTP/cisco.ftd.interface.output.errors["{#NAME}"],5m)>{$CISCO.FTD.IF.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Depends on**:<br><ul><li>Cisco Secure FTD: Interface [{#NAME}][{#DESCR}]: Link down</li></ul>|
|Cisco Secure FTD: Interface [{#NAME}][{#DESCR}]: Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is `down`.<br>2. `{$CISCO.FTD.IF.CONTROL:"{#IFNAME}"}=1` - a user can redefine the context macro to`0`, marking this interface as not important. No new trigger will be fired if this interface is down.</p>|`{$CISCO.FTD.IF.CONTROL:"{#IFNAME}"}=1 and (last(/Cisco Secure Firewall Threat Defense by HTTP/cisco.ftd.interface.status["{#NAME}"])=0)`|Average||

### LLD rule Connection discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Connection discovery|<p>Discovery of connection statistics from the Cisco Secure FTD API.</p>|Dependent item|cisco.ftd.conn_stats.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Connection discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Statistic [{#NAME}][{#METRIC}]|<p>Connection statistic for `{#NAME}` and metric `{#METRIC}`.</p>|Dependent item|cisco.ftd.conn_stats["{#NAME}","{#METRIC}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule ASP drop discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ASP drop discovery|<p>Discovery of the Accelerated Security Path drops or connections from the Cisco Secure FTD API.</p>|Dependent item|cisco.ftd.asp_drops.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for ASP drop discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ASP [{#METRIC}]|<p>Number of Accelerated Security Path (ASP) drops per second for `{#METRIC}`.</p>|Dependent item|cisco.ftd.asp_drops["{#ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|

### LLD rule Snort discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Snort discovery|<p>Discovery of Snort and IDS/IPS statistics from the Cisco Secure FTD API.</p>|Dependent item|cisco.ftd.snort.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Snort discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Statistic [{#NAME}][{#METRIC}]|<p>Discovery of Snort `{#NAME}` statistics of `{#METRIC}`.</p>|Dependent item|cisco.ftd.snort["{#ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "{#ID}")].value.first()`</p></li></ul>|

### LLD rule CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU discovery|<p>Discovery of CPU monitoring entries from the Cisco Secure FTD API.</p>|Dependent item|cisco.ftd.cpu.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for CPU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|CPU [{#METRIC}] utilization|<p>Discovery of CPU `{#METRIC}` utilization (in percent).</p>|Dependent item|cisco.ftd.cpu.util["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "{#NAME}")].value.first()`</p></li></ul>|

### Trigger prototypes for CPU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco Secure FTD: High CPU [{#METRIC}] utilization|<p>CPU utilization is too high. The system might be slow to respond.</p>|`min(/Cisco Secure Firewall Threat Defense by HTTP/cisco.ftd.cpu.util["{#NAME}"],15m)>{$CISCO.FTD.CPU.UTIL.WARN:"{#NAME}"}`|Warning||

### LLD rule Memory utilization discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Memory utilization discovery|<p>Discovery of utilization memory monitoring entries from the Cisco Secure FTD API.</p>|Dependent item|cisco.ftd.memory.util.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>JSON Path: `$.mem_percentage`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Memory utilization discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Memory [{#METRIC}] utilization|<p>Discovery of memory `{#METRIC}` utilization (in percent).</p>|Dependent item|cisco.ftd.memory.util["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "{#NAME}")].value.first()`</p></li></ul>|

### Trigger prototypes for Memory utilization discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco Secure FTD: High memory utilization|<p>Memory utilization is too high. The system might be slow to respond.</p>|`min(/Cisco Secure Firewall Threat Defense by HTTP/cisco.ftd.memory.util["{#NAME}"],15m)>{$CISCO.FTD.MEMORY.UTIL.WARN:"{#NAME}"}`|Warning||

### LLD rule Memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Memory discovery|<p>Discovery of memory monitoring entries from the Cisco Secure FTD API.</p>|Dependent item|cisco.ftd.memory.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>JSON Path: `$.mem_bytes`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Memory [{#METRIC}]|<p>Amount of memory in bytes for `{#METRIC}`.</p>|Dependent item|cisco.ftd.memory["{#NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "{#NAME}")].value.first()`</p></li></ul>|

### LLD rule Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Mounted filesystem discovery|<p>Discovery of mounted filesystems from the Cisco Secure FTD API.</p>|Dependent item|cisco.ftd.vfs.fs.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Mounted filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FS [{#FSNAME}][{#FSMOUNT}]: Space utilization|<p>Calculated as the percentage of the currently used space compared to the maximum available space.</p>|Dependent item|cisco.ftd.fs.pused["{#FSNAME}","{#FSMOUNT}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Mounted filesystem discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco Secure FTD: FS [{#FSNAME}][{#FSMOUNT}]: Space is low|<p>The trigger expression is based on the current used and maximum available space. The system might be slow to respond.</p>|`min(/Cisco Secure Firewall Threat Defense by HTTP/cisco.ftd.fs.pused["{#FSNAME}","{#FSMOUNT}"],15m)>{$CISCO.FTD.FS.PUSED.WARN:"{#FSNAME}"}`|Warning||

### LLD rule Critical process discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Critical process discovery|<p>Discovery of critical process statistics from the Cisco Secure FTD API.</p>|Dependent item|cisco.ftd.critical_process.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Critical process discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Process [{#ID}]: Uptime|<p>Uptime of process `{#ID}`.</p>|Dependent item|cisco.ftd.critical_process.uptime["{#ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.id == "{#ID}" && @.metric == "uptime")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Process [{#ID}]: Status|<p>Status of process `{#ID}`.</p>|Dependent item|cisco.ftd.critical_process.status["{#ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.id == "{#ID}" && @.metric == "status")].status.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Process [{#ID}]: Restart count|<p>Restart count of process `{#ID}`.</p>|Dependent item|cisco.ftd.critical_process.restart_count["{#ID}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Critical process discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco Secure FTD: Process [{#ID}]: Status failed|<p>Process `{#ID}` is the `failed` status.</p>|`last(/Cisco Secure Firewall Threat Defense by HTTP/cisco.ftd.critical_process.status["{#ID}"])=3`|Average||
|Cisco Secure FTD: Process [{#ID}]: Status stopped|<p>Process `{#ID}` is the `stopped` status.</p>|`last(/Cisco Secure Firewall Threat Defense by HTTP/cisco.ftd.critical_process.status["{#ID}"])=1`|Average||

### LLD rule Temperature sensor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature sensor discovery|<p>Discovery of temperature sensors from the Cisco Secure FTD API.</p>|Dependent item|cisco.ftd.temp.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Temperature sensor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature [{#SENSOR}]|<p>Temperature of sensor `{#SENSOR}`.</p>|Dependent item|cisco.ftd.sensor.temp.value["{#SENSOR}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == "{#SENSOR}")].value.first()`</p></li></ul>|

### Trigger prototypes for Temperature sensor discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cisco Secure FTD: Temperature is above critical threshold|<p>This trigger uses temperature sensor values as well as the temperature sensor status, if available.</p>|`avg(/Cisco Secure Firewall Threat Defense by HTTP/cisco.ftd.sensor.temp.value["{#SENSOR}"],5m)>{$CISCO.FTD.TEMP.CRIT:"{#SENSOR}"}`|High||
|Cisco Secure FTD: Temperature is above warning threshold|<p>This trigger uses temperature sensor values as well as the temperature sensor status, if available.</p>|`avg(/Cisco Secure Firewall Threat Defense by HTTP/cisco.ftd.sensor.temp.value["{#SENSOR}"],5m)>{$CISCO.FTD.TEMP.WARN:"{#SENSOR}"}`|Warning|**Depends on**:<br><ul><li>Cisco Secure FTD: Temperature is above critical threshold</li></ul>|

### LLD rule PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU discovery|<p>Discovery of PSU sensors.</p>|Dependent item|cisco.ftd.psu.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Power supply [{#SENSOR}]|<p>Power supply unit `{#SENSOR}` power consumption in watts.</p>|Dependent item|cisco.ftd.sensor.psu.pwr[{#SENSOR}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == '{#SENSOR}')].value.first()`</p></li></ul>|

### LLD rule FAN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN discovery|<p>Discovery of FAN sensors.</p>|Dependent item|cisco.ftd.fan.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for FAN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan speed [{#NAME}]|<p>FAN `{#SENSOR}` speed in RPM.</p>|Dependent item|cisco.ftd.sensor.fan.rpm[{#SENSOR}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.metric == '{#SENSOR}')].value.first()`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

