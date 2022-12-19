
# Apache by Zabbix agent

## Overview

For Zabbix version: 6.4 and higher.
This template is developed to monitor Apache HTTPD by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.  
The template `Apache by Zabbix agent` - collects metrics by polling [Apache Satus module](https://httpd.apache.org/docs/current/mod/mod_status.html) locally with Zabbix agent:

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
Scoreboard: __________________________________________W_____________W___________________LW_____W______W_W_______............................................................................................................................................................................................................................................................................................................

```

It also uses Zabbix agent to collect `Apache` Linux process statistics, such as CPU usage, memory usage, and whether the process is running or not.



This template was tested on:

- Apache, version 2.4.41

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box/zabbix_agent) for basic instructions.

See the setup instructions for [Apache Satus module](https://httpd.apache.org/docs/current/mod/mod_status.html).

Check the availability of the module with this command line: `httpd -M 2>/dev/null | grep status_module`

This is an example configuration of the Apache web server:

```text
<Location "/server-status">
  SetHandler server-status
  Require host example.com
</Location>
```

If you use another path, then do not forget to change the `{$APACHE.STATUS.PATH}` macro.
Install and setup [Zabbix agent](https://www.zabbix.com/documentation/6.4/manual/installation/install_from_packages).


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$APACHE.PROCESS_NAME} |<p>The process name of the Apache web server (Apache).</p> |`(httpd|apache2)` |
|{$APACHE.RESPONSE_TIME.MAX.WARN} |<p>The maximum Apache response time expressed in seconds for a trigger expression.</p> |`10` |
|{$APACHE.STATUS.HOST} |<p>The Hostname or an IP address of the Apache status page.</p> |`127.0.0.1` |
|{$APACHE.STATUS.PATH} |<p>The URL path.</p> |`server-status?auto` |
|{$APACHE.STATUS.PORT} |<p>The port of the Apache status page.</p> |`80` |
|{$APACHE.STATUS.SCHEME} |<p>The request scheme, which may be either HTTP or HTTPS.</p> |`http` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Apache process discovery |<p>The discovery of the Apache process summary.</p> |DEPENDENT |apache.proc.discovery<p>**Filter**:</p>AND <p>- {#NAME} MATCHES_REGEX `{$APACHE.PROCESS_NAME}`</p> |
|Event MPM discovery |<p>The discovery of additional metrics if the event Multi-Processing Module (MPM) is used.</p><p>For more details see [Apache MPM event](https://httpd.apache.org/docs/current/mod/event.html).</p> |DEPENDENT |apache.mpm.event.discovery<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return JSON.stringify(JSON.parse(value).ServerMPM === 'event'     ? [{'{#SINGLETON}': ''}] : []);`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Apache |Apache: Service ping |<p>-</p> |ZABBIX_PASSIVE |net.tcp.service[http,"{$APACHE.STATUS.HOST}","{$APACHE.STATUS.PORT}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Apache |Apache: Service response time |<p>-</p> |ZABBIX_PASSIVE |net.tcp.service.perf[http,"{$APACHE.STATUS.HOST}","{$APACHE.STATUS.PORT}"] |
|Apache |Apache: Total bytes |<p>The total bytes served.</p> |DEPENDENT |apache.bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$["Total kBytes"]`</p><p>- MULTIPLIER: `1024`</p> |
|Apache |Apache: Bytes per second |<p>It is calculated as a rate of change for total bytes statistics.</p><p>`ReqPerSec` is not used, as it counts the average since the last Apache server start.</p> |DEPENDENT |apache.bytes.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$["Total kBytes"]`</p><p>- MULTIPLIER: `1024`</p><p>- CHANGE_PER_SECOND</p> |
|Apache |Apache: Requests per second |<p>It is calculated as a rate of change for the "Total requests" statistics.</p><p>`ReqPerSec` is not used, as it counts the average since the last Apache server start.</p> |DEPENDENT |apache.requests.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$["Total Accesses"]`</p><p>- CHANGE_PER_SECOND</p> |
|Apache |Apache: Total requests |<p>The total number of the Apache server accesses.</p> |DEPENDENT |apache.requests<p>**Preprocessing**:</p><p>- JSONPATH: `$["Total Accesses"]`</p> |
|Apache |Apache: Uptime |<p>The service uptime expressed in seconds.</p> |DEPENDENT |apache.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$.ServerUptimeSeconds`</p> |
|Apache |Apache: Version |<p>The Apache service version.</p> |DEPENDENT |apache.version<p>**Preprocessing**:</p><p>- JSONPATH: `$.ServerVersion`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Apache |Apache: Total workers busy |<p>The total number of busy worker threads/processes.</p> |DEPENDENT |apache.workers_total.busy<p>**Preprocessing**:</p><p>- JSONPATH: `$.BusyWorkers`</p> |
|Apache |Apache: Total workers idle |<p>The total number of idle worker threads/processes.</p> |DEPENDENT |apache.workers_total.idle<p>**Preprocessing**:</p><p>- JSONPATH: `$.IdleWorkers`</p> |
|Apache |Apache: Workers closing connection |<p>The number of workers in closing state.</p> |DEPENDENT |apache.workers.closing<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.closing`</p> |
|Apache |Apache: Workers DNS lookup |<p>The number of workers in `dnslookup` state.</p> |DEPENDENT |apache.workers.dnslookup<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.dnslookup`</p> |
|Apache |Apache: Workers finishing |<p>The number of workers in finishing state.</p> |DEPENDENT |apache.workers.finishing<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.finishing`</p> |
|Apache |Apache: Workers idle cleanup |<p>The number of workers in cleanup state.</p> |DEPENDENT |apache.workers.cleanup<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.cleanup`</p> |
|Apache |Apache: Workers keepalive (read) |<p>The number of workers in `keepalive` state.</p> |DEPENDENT |apache.workers.keepalive<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.keepalive`</p> |
|Apache |Apache: Workers logging |<p>The number of workers in logging state.</p> |DEPENDENT |apache.workers.logging<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.logging`</p> |
|Apache |Apache: Workers reading request |<p>The number of workers in reading state.</p> |DEPENDENT |apache.workers.reading<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.reading`</p> |
|Apache |Apache: Workers sending reply |<p>The number of workers in sending state.</p> |DEPENDENT |apache.workers.sending<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.sending`</p> |
|Apache |Apache: Workers slot with no current process |<p>The number of slots with no current process.</p> |DEPENDENT |apache.workers.slot<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.slot`</p> |
|Apache |Apache: Workers starting up |<p>The number of workers in starting state.</p> |DEPENDENT |apache.workers.starting<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.starting`</p> |
|Apache |Apache: Workers waiting for connection |<p>The number of workers in waiting state.</p> |DEPENDENT |apache.workers.waiting<p>**Preprocessing**:</p><p>- JSONPATH: `$.Workers.waiting`</p> |
|Apache |Apache: Get processes summary |<p>The aggregated data of summary metrics for all processes.</p> |ZABBIX_PASSIVE |proc.get[,,,summary] |
|Apache |Apache: CPU utilization |<p>The percentage of the CPU utilization by a process {#NAME}.</p> |ZABBIX_PASSIVE |proc.cpu.util[{#NAME}] |
|Apache |Apache: Get process data |<p>The summary metrics aggregated by a process {#NAME}.</p> |DEPENDENT |apache.proc.get[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@["name"]=="{#NAME}")].first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> Failed to retrieve process {#NAME} data`</p> |
|Apache |Apache: Memory usage (rss) |<p>The summary of resident set size memory used by a process {#NAME} expressed in bytes.</p> |DEPENDENT |apache.proc.rss[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.rss`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Apache |Apache: Memory usage (vsize) |<p>The summary of virtual memory used by a process {#NAME} expressed in bytes.</p> |DEPENDENT |apache.proc.vmem[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.vsize`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Apache |Apache: Memory usage, % |<p>The percentage of real memory used by a process {#NAME}.</p> |DEPENDENT |apache.proc.pmem[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.pmem`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Apache |Apache: Number of running processes |<p>The number of running processes {#NAME}.</p> |DEPENDENT |apache.proc.num[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.processes`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Apache |Apache: Connections async closing |<p>The number of asynchronous connections in closing state (applicable only to the event MPM).</p> |DEPENDENT |apache.connections[async_closing{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.ConnsAsyncClosing`</p> |
|Apache |Apache: Connections async keepalive |<p>The number of asynchronous connections in keepalive state (applicable only to the event MPM).</p> |DEPENDENT |apache.connections[async_keep_alive{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.ConnsAsyncKeepAlive`</p> |
|Apache |Apache: Connections async writing |<p>The number of asynchronous connections in writing state (applicable only to the event MPM).</p> |DEPENDENT |apache.connections[async_writing{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.ConnsAsyncWriting`</p> |
|Apache |Apache: Connections total |<p>The number of total connections.</p> |DEPENDENT |apache.connections[total{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.ConnsTotal`</p> |
|Apache |Apache: Bytes per request |<p>The average number of client requests per second.</p> |DEPENDENT |apache.bytes[per_request{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.BytesPerReq`</p> |
|Apache |Apache: Number of async processes |<p>The number of asynchronous processes.</p> |DEPENDENT |apache.process[num{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.Processes`</p> |
|Zabbix raw items |Apache: Get status |<p>Getting data from a machine-readable version of the Apache status page.</p><p> For more information see Apache Module [mod_status](https://httpd.apache.org/docs/current/mod/mod_status.html).</p> |ZABBIX_PASSIVE |web.page.get["{$APACHE.STATUS.SCHEME}://{$APACHE.STATUS.HOST}:{$APACHE.STATUS.PORT}/{$APACHE.STATUS.PATH}"]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Apache: Host has been restarted |<p>Uptime is less than 10 minutes.</p> |`last(/Apache by Zabbix agent/apache.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|Apache: Version has changed |<p>The Apache version has changed. Acknowledge (Ack) to close manually.</p> |`last(/Apache by Zabbix agent/apache.version,#1)<>last(/Apache by Zabbix agent/apache.version,#2) and length(last(/Apache by Zabbix agent/apache.version))>0` |INFO |<p>Manual close: YES</p> |
|Apache: Process is not running |<p>-</p> |`last(/Apache by Zabbix agent/apache.proc.num[{#NAME}])=0` |HIGH | |
|Apache: Failed to fetch status page |<p>Zabbix has not received any data for items for the last 30 minutes.</p> |`nodata(/Apache by Zabbix agent/web.page.get["{$APACHE.STATUS.SCHEME}://{$APACHE.STATUS.HOST}:{$APACHE.STATUS.PORT}/{$APACHE.STATUS.PATH}"],30m)=1 and last(/Apache by Zabbix agent/apache.proc.num[{#NAME}])>0` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Apache: Service is down</p> |
|Apache: Service is down |<p>-</p> |`last(/Apache by Zabbix agent/net.tcp.service[http,"{$APACHE.STATUS.HOST}","{$APACHE.STATUS.PORT}"])=0 and last(/Apache by Zabbix agent/apache.proc.num[{#NAME}])>0` |AVERAGE |<p>Manual close: YES</p> |
|Apache: Service response time is too high |<p>-</p> |`min(/Apache by Zabbix agent/net.tcp.service.perf[http,"{$APACHE.STATUS.HOST}","{$APACHE.STATUS.PORT}"],5m)>{$APACHE.RESPONSE_TIME.MAX.WARN} and last(/Apache by Zabbix agent/apache.proc.num[{#NAME}])>0` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Apache: Service is down</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/384764-discussion-thread-for-official-zabbix-template-apache).

