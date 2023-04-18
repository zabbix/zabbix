
# Apache by Zabbix agent

## Overview

This template is designed for the effortless deployment of Apache monitoring by Zabbix via Zabbix agent and doesn't require any external scripts.
Template `Apache by Zabbix agent` - collects metrics by polling [mod_status](https://httpd.apache.org/docs/current/mod/mod_status.html) locally with Zabbix agent:
  
```text
127.0.0.1
ServerVersion: Apache/2.4.41 (Unix)
ServerMPM: event
Server Built: Aug 14 2019 00:35:10
CurrentTime: Friday, 16-Aug-2019 12:38:40 UTC
RestartTime: Wednesday, 14-Aug-2019 07:58:26 UTC
ParentServerConfigGeneration: 1
ParentServerMPMGeneration: 0
ServerUptimeSeconds: 189613
ServerUptime: 2 days 4 hours 40 minutes 13 seconds
Load1: 4.60
Load5: 1.20
Load15: 0.47
Total Accesses: 27860
Total kBytes: 33011
Total Duration: 54118
CPUUser: 18.02
CPUSystem: 31.76
CPUChildrenUser: 0
CPUChildrenSystem: 0
CPULoad: .0262535
Uptime: 189613
ReqPerSec: .146931
BytesPerSec: 178.275
BytesPerReq: 1213.33
DurationPerReq: 1.9425
BusyWorkers: 7
IdleWorkers: 93
Processes: 4
Stopping: 0
BusyWorkers: 7
IdleWorkers: 93
ConnsTotal: 13
ConnsAsyncWriting: 0
ConnsAsyncKeepAlive: 5
ConnsAsyncClosing: 0
Scoreboard: ...

```
  
It also uses Zabbix agent to collect `Apache` Linux process stats like CPU usage, memory usage and whether process is running or not.

## Requirements

Zabbix version: 6.4 and higher.

## Tested versions

This template has been tested on:
- Apache 2.4.41

## Configuration

> Zabbix should be configured according to instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box) section.

## Setup

Setup [mod_status](https://httpd.apache.org/docs/current/mod/mod_status.html)

Check module availability: `httpd -M 2>/dev/null | grep status_module`

Example configuration of Apache:

```text
<Location "/server-status">
  SetHandler server-status
  Require host example.com
</Location>
```

If you use another path, then don't forget to change `{$APACHE.STATUS.PATH}` macro.
Install and setup [Zabbix agent](https://www.zabbix.com/documentation/6.4/manual/installation/install_from_packages).

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$APACHE.STATUS.HOST}|<p>Hostname or IP address of the Apache status page</p>|`127.0.0.1`|
|{$APACHE.STATUS.PORT}|<p>The port of Apache status page</p>|`80`|
|{$APACHE.STATUS.PATH}|<p>The URL path</p>|`server-status?auto`|
|{$APACHE.STATUS.SCHEME}|<p>Request scheme which may be http or https</p>|`http`|
|{$APACHE.RESPONSE_TIME.MAX.WARN}|<p>Maximum Apache response time in seconds for trigger expression</p>|`10`|
|{$APACHE.PROCESS_NAME}|<p>Apache server process name</p>|`httpd`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Apache: Get status|<p>Getting data from a machine-readable version of the Apache status page.</p><p>https://httpd.apache.org/docs/current/mod/mod_status.html</p>|Zabbix agent|web.page.get["{$APACHE.STATUS.SCHEME}://{$APACHE.STATUS.HOST}:{$APACHE.STATUS.PORT}/{$APACHE.STATUS.PATH}"]<p>**Preprocessing**</p><ul><li>JavaScript: `The text is too long. Please see the template.`</li></ul>|
|Apache: Service ping| |Zabbix agent|net.tcp.service[http,"{$APACHE.STATUS.HOST}","{$APACHE.STATUS.PORT}"]<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `10m`</li></ul>|
|Apache: Service response time| |Zabbix agent|net.tcp.service.perf[http,"{$APACHE.STATUS.HOST}","{$APACHE.STATUS.PORT}"]|
|Apache: Total bytes|<p>The total bytes served.</p>|Dependent item|apache.bytes<p>**Preprocessing**</p><ul><li>JSON Path: `$["Total kBytes"]`</li><li>Custom multiplier: `1024`</li></ul>|
|Apache: Bytes per second|<p>It is calculated as a rate of change for total bytes statistics.</p><p>`ReqPerSec` is not used, as it counts the average since the last Apache server start.</p>|Dependent item|apache.bytes.rate<p>**Preprocessing**</p><ul><li>JSON Path: `$["Total kBytes"]`</li><li>Custom multiplier: `1024`</li><li>Change per second</li></ul>|
|Apache: Requests per second|<p>It is calculated as a rate of change for the "Total requests" statistics.</p><p>`ReqPerSec` is not used, as it counts the average since the last Apache server start.</p>|Dependent item|apache.requests.rate<p>**Preprocessing**</p><ul><li>JSON Path: `$["Total Accesses"]`</li><li>Change per second</li></ul>|
|Apache: Total requests|<p>The total number of the Apache server accesses.</p>|Dependent item|apache.requests<p>**Preprocessing**</p><ul><li>JSON Path: `$["Total Accesses"]`</li></ul>|
|Apache: Uptime|<p>The service uptime expressed in seconds.</p>|Dependent item|apache.uptime<p>**Preprocessing**</p><ul><li>JSON Path: `$.ServerUptimeSeconds`</li></ul>|
|Apache: Version|<p>The Apache service version.</p>|Dependent item|apache.version<p>**Preprocessing**</p><ul><li>JSON Path: `$.ServerVersion`</li><li>Discard unchanged with heartbeat: `1d`</li></ul>|
|Apache: Total workers busy|<p>The total number of busy worker threads/processes.</p>|Dependent item|apache.workers_total.busy<p>**Preprocessing**</p><ul><li>JSON Path: `$.BusyWorkers`</li></ul>|
|Apache: Total workers idle|<p>The total number of idle worker threads/processes.</p>|Dependent item|apache.workers_total.idle<p>**Preprocessing**</p><ul><li>JSON Path: `$.IdleWorkers`</li></ul>|
|Apache: Workers closing connection|<p>The number of workers in closing state.</p>|Dependent item|apache.workers.closing<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.closing`</li></ul>|
|Apache: Workers DNS lookup|<p>The number of workers in `dnslookup` state.</p>|Dependent item|apache.workers.dnslookup<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.dnslookup`</li></ul>|
|Apache: Workers finishing|<p>The number of workers in finishing state.</p>|Dependent item|apache.workers.finishing<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.finishing`</li></ul>|
|Apache: Workers idle cleanup|<p>The number of workers in cleanup state.</p>|Dependent item|apache.workers.cleanup<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.cleanup`</li></ul>|
|Apache: Workers keepalive (read)|<p>The number of workers in `keepalive` state.</p>|Dependent item|apache.workers.keepalive<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.keepalive`</li></ul>|
|Apache: Workers logging|<p>The number of workers in logging state.</p>|Dependent item|apache.workers.logging<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.logging`</li></ul>|
|Apache: Workers reading request|<p>The number of workers in reading state.</p>|Dependent item|apache.workers.reading<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.reading`</li></ul>|
|Apache: Workers sending reply|<p>The number of workers in sending state.</p>|Dependent item|apache.workers.sending<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.sending`</li></ul>|
|Apache: Workers slot with no current process|<p>The number of slots with no current process.</p>|Dependent item|apache.workers.slot<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.slot`</li></ul>|
|Apache: Workers starting up|<p>The number of workers in starting state.</p>|Dependent item|apache.workers.starting<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.starting`</li></ul>|
|Apache: Workers waiting for connection|<p>The number of workers in waiting state.</p>|Dependent item|apache.workers.waiting<p>**Preprocessing**</p><ul><li>JSON Path: `$.Workers.waiting`</li></ul>|
|Apache: Number of processes running| |Zabbix agent|proc.num["{$APACHE.PROCESS_NAME}"]|
|Apache: Memory usage (rss)|<p>Resident set size memory used by process in bytes.</p>|Zabbix agent|proc.mem["{$APACHE.PROCESS_NAME}",,,,rss]|
|Apache: Memory usage (vsize)|<p>Virtual memory size used by process in bytes.</p>|Zabbix agent|proc.mem["{$APACHE.PROCESS_NAME}",,,,vsize]|
|Apache: CPU utilization|<p>Process CPU utilization percentage.</p>|Zabbix agent|proc.cpu.util["{$APACHE.PROCESS_NAME}"]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Apache: Failed to fetch status page|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`nodata(/Apache by Zabbix agent/web.page.get["{$APACHE.STATUS.SCHEME}://{$APACHE.STATUS.HOST}:{$APACHE.STATUS.PORT}/{$APACHE.STATUS.PATH}"],30m)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Apache: Service is down</li><li>Apache: Process is not running</li></ul>|
|Apache: Service is down||`last(/Apache by Zabbix agent/net.tcp.service[http,"{$APACHE.STATUS.HOST}","{$APACHE.STATUS.PORT}"])=0`|Average|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Apache: Process is not running</li></ul>|
|Apache: Service response time is too high||`min(/Apache by Zabbix agent/net.tcp.service.perf[http,"{$APACHE.STATUS.HOST}","{$APACHE.STATUS.PORT}"],5m)>{$APACHE.RESPONSE_TIME.MAX.WARN}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Apache: Process is not running</li><li>Apache: Service is down</li></ul>|
|Apache: has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Apache by Zabbix agent/apache.uptime)<10m`|Info|**Manual close**: Yes|
|Apache: Version has changed|<p>Apache version has changed. Acknowledge to close the problem manually.</p>|`last(/Apache by Zabbix agent/apache.version,#1)<>last(/Apache by Zabbix agent/apache.version,#2) and length(last(/Apache by Zabbix agent/apache.version))>0`|Info|**Manual close**: Yes|
|Apache: Process is not running||`last(/Apache by Zabbix agent/proc.num["{$APACHE.PROCESS_NAME}"])=0`|High||

### LLD rule Event MPM discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Event MPM discovery|<p>Additional metrics if event MPM is used</p><p>https://httpd.apache.org/docs/current/mod/event.html</p>|Dependent item|apache.mpm.event.discovery<p>**Preprocessing**</p><ul><li>JavaScript: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `3h`</li></ul>|

### Item prototypes for Event MPM discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Apache: Connections async closing|<p>The number of asynchronous connections in closing state (applicable only to the event MPM).</p>|Dependent item|apache.connections[async_closing{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `$.ConnsAsyncClosing`</li></ul>|
|Apache: Connections async keepalive|<p>The number of asynchronous connections in keepalive state (applicable only to the event MPM).</p>|Dependent item|apache.connections[async_keep_alive{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `$.ConnsAsyncKeepAlive`</li></ul>|
|Apache: Connections async writing|<p>The number of asynchronous connections in writing state (applicable only to the event MPM).</p>|Dependent item|apache.connections[async_writing{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `$.ConnsAsyncWriting`</li></ul>|
|Apache: Connections total|<p>The number of total connections.</p>|Dependent item|apache.connections[total{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `$.ConnsTotal`</li></ul>|
|Apache: Bytes per request|<p>The average number of client requests per second.</p>|Dependent item|apache.bytes[per_request{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `$.BytesPerReq`</li></ul>|
|Apache: Number of async processes|<p>The number of asynchronous processes.</p>|Dependent item|apache.process[num{#SINGLETON}]<p>**Preprocessing**</p><ul><li>JSON Path: `$.Processes`</li></ul>|

## Feedback

Please report any issues with the template at `https://support.zabbix.com`.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
